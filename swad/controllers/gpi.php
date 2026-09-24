<?php
declare(strict_types=1);

/**
 * swad/controllers/gpi.php — Game Platform Index (GPI).
 *
 * ────────────────────────────────────────────────────────────────────────
 * ЧТО ЭТО
 * Одно число, которое растёт, когда платформа живёт быстрее обычного, и
 * падает, когда замедляется. База — 1000 = «платформа идёт своим обычным
 * темпом». 1080 = на 8% живее, чем в среднем за последний месяц.
 *
 * КАК СЧИТАЕТСЯ (идея из PMI и фондовых индексов)
 *   1. Каждая метрика меряется за окно 7 дней:            v(D)
 *   2. База — среднее той же метрики за 4 прошлые недели:  b(D) = avg(v(D-7), v(D-14), v(D-21), v(D-28))
 *   3. Моментум метрики:  s = (v + k) / (b + k), зажат в [0.25; 4]
 *        k — сглаживание Лапласа. Для маленькой платформы критично:
 *        без него 0 → 1 отзыв = бесконечный рост, 1 → 0 = обвал в ноль.
 *   4. Столп (Игроки, Контент, ...) = геометрическое среднее своих метрик.
 *   5. GPI = 1000 × взвешенное геометрическое среднее столпов.
 *
 * Почему геометрическое, а не арифметическое: +100% и −50% должны
 * гасить друг друга (×2 и ×0.5 → 1), а не давать «+25% в среднем».
 *
 * Почему моментум, а не абсолютные значения: абсолют растёт у любой
 * живой платформы просто от времени, по нему не видно, ускоряемся мы
 * или тормозим. Масштаб показан рядом — в сырых значениях метрик.
 *
 * ДОСТУПНОСТЬ ДАННЫХ
 * Схема проверяется через information_schema: нет таблицы/колонки —
 * метрика получает статус «нет источника». Есть, но данных меньше, чем
 * нужно окну и базе, — «аналитика собирается, осталось N дней».
 * Метрики без данных исключаются, веса перенормируются — индекс остаётся
 * честным, просто считается по меньшему числу сигналов.
 *
 * Активность (DAU, retention, сессии) берётся только из live-строк
 * user_daily_activity. Бэкфилл по «следам» (006_backfill_activity.php)
 * занижает активность, и склейка «след → живой хартбит» дала бы в
 * индексе фальшивый рост +300% в первый месяц. Это называется
 * структурный разрыв ряда — его нельзя сравнивать с самим собой.
 * ────────────────────────────────────────────────────────────────────────
 */

final class GPI
{
    public const BASE      = 1000.0;
    private const WIN      = 7;      // окно метрики, дней
    private const WEEKS    = 4;      // сколько прошлых окон в базе
    private const PRELOAD  = 220;    // сколько дней сырья тянем из БД
    private const HISTORY  = 90;     // сколько дней истории индекса отдаём
    private const CLAMP    = [0.25, 4.0];
    private const GROW_EPS = 0.02;   // |s-1| < 2% — «без изменений» в breadth

    public const PILLARS = [
        'players'   => ['Игроки',       0.25, 'Возвращаются ли люди и играют ли они'],
        'content'   => ['Контент',      0.20, 'Появляется ли новое и живёт ли старое'],
        'community' => ['Сообщество',   0.20, 'Разговаривают ли люди друг с другом'],
        'devs'      => ['Разработчики', 0.15, 'Работают ли разработчики на платформе'],
        'growth'    => ['Рост',         0.12, 'Приходят ли новые и возвращаются ли ушедшие'],
        'economy'   => ['Экономика',    0.08, 'Платят ли за игры и ассеты'],
    ];

    private PDO $pdo;
    private ?PDO $l4t;
    private array $cols = ['main' => [], 'l4t' => []];

    private int $today;
    private int $from;

    /** @var array<string, array<int,float>> flow-ряды: day => value */
    private array $flow = [];
    /** @var array<string, array<int, array<string,int>>> uniq-ряды: day => [key=>1] */
    private array $uniq = [];
    /** @var array<string, array<string,int>> первое появление ключа (для repeat) */
    private array $first = [];
    /** @var array<string, int|null> начало покрытия по источнику метрики */
    private array $coverage = [];
    /** @var array<string, bool> источник существует в схеме (даже если пуст) */
    private array $resolved = [];

    /** live-активность: day => [uid => sessions] */
    private array $act = [];
    private ?int $actStart = null;
    /** регистрации: day => [uid, ...] */
    private array $reg = [];

    private array $memo = [];
    private array $catalog;

    public function __construct(PDO $pdo, ?PDO $l4t = null)
    {
        $this->pdo = $pdo;
        $this->l4t = $l4t;
        $this->today = self::dn(date('Y-m-d'));
        $this->from  = $this->today - self::PRELOAD;
        $this->probe();
        $this->catalog = $this->catalog();
    }

    /* =====================================================================
     * ПУБЛИЧНОЕ API
     * ===================================================================*/

    /**
     * Полный снимок для страницы. Тяжёлый (десятки запросов), поэтому
     * кэшируется файлом на $ttl секунд — страница /stat публичная.
     */
    public static function snapshot(PDO $pdo, ?PDO $l4t = null, int $ttl = 600): array
    {
        $file = sys_get_temp_dir() . '/dustore_gpi_' . md5(__FILE__) . '.json';
        if (is_readable($file) && time() - filemtime($file) < $ttl) {
            $j = json_decode((string)file_get_contents($file), true);
            if (is_array($j) && ($j['asof'] ?? '') === date('Y-m-d')) return $j;
        }
        $snap = (new self($pdo, $l4t))->compute();
        @file_put_contents($file, json_encode($snap, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $snap;
    }

    public function compute(): array
    {
        $this->load();

        $D = $this->today;
        $metrics = [];
        foreach ($this->catalog as $m) {
            $metrics[$m['id']] = $this->describe($m, $D);
        }

        $now = $this->indexAt($D);

        /* История: прошлые дни морозим в gpi_daily, сегодняшний — всегда свежий. */
        $history = $this->history();

        $pillars = [];
        foreach (self::PILLARS as $pid => [$label, $w, $about]) {
            $ids = array_values(array_map(fn($m) => $m['id'],
                array_filter($this->catalog, fn($m) => $m['p'] === $pid)));
            $pillars[$pid] = [
                'label'   => $label,
                'weight'  => $w,
                'about'   => $about,
                'index'   => isset($now['pillars'][$pid]) ? round($now['pillars'][$pid] * self::BASE, 1) : null,
                'metrics' => $ids,
            ];
        }

        $prev1 = $this->historyValue($history, $D - 1);
        $prev7 = $this->historyValue($history, $D - 7);

        return [
            'asof'     => date('Y-m-d'),
            'gpi'      => $now['gpi'] !== null ? round($now['gpi'], 1) : null,
            'delta_1d' => ($now['gpi'] !== null && $prev1) ? round($now['gpi'] - $prev1, 1) : null,
            'delta_7d' => ($now['gpi'] !== null && $prev7) ? round($now['gpi'] - $prev7, 1) : null,
            'breadth'  => $now['breadth'],
            'counted'  => $now['counted'],
            'total'    => count(array_filter($this->catalog, fn($m) => empty($m['info']))),
            'pillars'  => $pillars,
            'metrics'  => $metrics,
            'history'  => $history,
            'activity_since' => $this->actStart !== null ? self::ds($this->actStart) : null,
        ];
    }

    /* =====================================================================
     * КАТАЛОГ МЕТРИК
     *   type: flow   — сумма событий за окно (src: список источников)
     *         uniq   — уникальные ключи за окно (объединение источников)
     *         repeat — ключи, встречавшиеся раньше (повторные сессии)
     *         share  — uniq / накопленный знаменатель
     *         act    — считается по live-активности (fn)
     *   look: сколько дней сырья нужно, чтобы посчитать v(D)
     *   k:    сглаживание (для долей — в долях, для счётчиков — в штуках)
     *   info: показываем, но в индекс не берём
     *   money: сырое значение видят только админы
     * ===================================================================*/

    private function catalog(): array
    {
        $gameEv = ['db' => 'main', 't' => 'analytics_events', 'd' => 'created_at',
                   'k' => "CONCAT(COALESCE(user_id, anon_id), ':', subject_id)",
                   'need' => ['user_id', 'anon_id', 'subject_id', 'subject_type', 'event_type'],
                   'w' => "subject_type = 'game' AND event_type IN ('launch','playtime','download')"];

        return [
            /* ── Игроки ─────────────────────────────────────────── */
            ['id' => 'dau_mau', 'p' => 'players', 'label' => 'DAU / MAU', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'dauMau', 'look' => 30 + 6, 'k' => 0.02,
             'hint' => 'Липкость: какая доля месячной аудитории заходит в среднем каждый день. 20%+ — хорошо для игровых сервисов.'],
            ['id' => 'wau_mau', 'p' => 'players', 'label' => 'WAU / MAU', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'wauMau', 'look' => 30, 'k' => 0.02,
             'hint' => 'Какая доля месячной аудитории была хотя бы раз за неделю.'],
            ['id' => 'ret_d1',  'p' => 'players', 'label' => 'Retention D1', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'retD1', 'look' => 1 + 7, 'k' => 0.03,
             'hint' => 'Доля зарегистрировавшихся, кто вернулся на следующий день. Когорта — регистрации за неделю.'],
            ['id' => 'ret_d7',  'p' => 'players', 'label' => 'Retention D7', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'retD7', 'look' => 8 + 7, 'k' => 0.03,
             'hint' => 'Вернулся на 6–8 день после регистрации (окно ±1 день сглаживает шум маленьких когорт).'],
            ['id' => 'ret_d30', 'p' => 'players', 'label' => 'Retention D30', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'retD30', 'look' => 32 + 7, 'k' => 0.03,
             'hint' => 'Вернулся на 28–32 день после регистрации.'],
            ['id' => 'sessions', 'p' => 'players', 'label' => 'Сессии на платформе', 'fmt' => 'int', 'type' => 'act', 'fn' => 'sessions', 'look' => 7, 'k' => 5,
             'hint' => 'Визиты авторизованных пользователей. Новая сессия — после 30 минут тишины.'],
            ['id' => 'sess_per_user', 'p' => 'players', 'label' => 'Сессий на активного', 'fmt' => 'dec', 'type' => 'act', 'fn' => 'sessPerUser', 'look' => 7, 'k' => 0.2,
             'hint' => 'Сколько раз за неделю возвращается средний активный пользователь. Глубина, а не ширина.'],
            ['id' => 'game_sessions', 'p' => 'players', 'label' => 'Игровые сессии', 'fmt' => 'int', 'type' => 'uniq', 'src' => [$gameEv], 'k' => 3,
             'hint' => 'Уникальные «игрок × игра × день» по запускам, скачиваниям и времени в веб-игре.'],
            ['id' => 'game_sessions_rep', 'p' => 'players', 'label' => 'Повторные игровые сессии', 'fmt' => 'int', 'type' => 'repeat', 'src' => [$gameEv], 'k' => 3,
             'hint' => 'Игровые сессии в игру, в которую этот игрок уже играл раньше. Главный сигнал качества каталога.'],

            /* ── Контент ────────────────────────────────────────── */
            ['id' => 'new_games', 'p' => 'content', 'label' => 'Новые игры', 'fmt' => 'int', 'type' => 'flow', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'games', 'd' => 'created_at']],
             'hint' => 'Созданные страницы игр.'],
            ['id' => 'updated_games', 'p' => 'content', 'label' => 'Обновлённые игры', 'fmt' => 'int', 'type' => 'uniq', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'game_change_log', 'd' => 'created_at', 'k' => 'game_id'],
                       ['db' => 'main', 't' => 'game_builds',     'd' => 'updated_at', 'k' => 'game_id']],
             'hint' => 'Сколько разных игр получили изменения или новый билд.'],
            ['id' => 'wishlists', 'p' => 'content', 'label' => 'Новые вишлисты', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'wishlists', 'd' => 'created_at']],
             'hint' => 'Добавления игр в список желаемого.'],
            ['id' => 'demos', 'p' => 'content', 'label' => 'Новые демо', 'fmt' => 'int', 'type' => 'flow', 'k' => 2, 'src' => [],
             'hint' => 'Для демо нет отдельной сущности в БД. Появится флаг/тип билда — метрика включится.'],
            ['id' => 'played_share', 'p' => 'content', 'label' => 'Игр, в которые играют', 'fmt' => 'pct', 'type' => 'share', 'k' => 0.02,
             'src' => [['db' => 'main', 't' => 'library', 'd' => 'date', 'k' => 'game_id'],
                       ['db' => 'main', 't' => 'jam_plays', 'd' => 'first_download_at', 'k' => 'game_id'],
                       ['db' => 'main', 't' => 'analytics_events', 'd' => 'created_at', 'k' => 'subject_id',
                        'need' => ['subject_type'], 'w' => "subject_type = 'game'"]],
             'den' => ['db' => 'main', 't' => 'games', 'd' => 'created_at', 'w' => "status = 'published'", 'need' => ['status']],
             'hint' => 'Доля опубликованных игр, у которых за неделю было хоть одно скачивание, добавление или запуск.'],
            ['id' => 'game_reviews', 'p' => 'content', 'label' => 'Новые отзывы к играм', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'game_reviews', 'd' => 'created_at']]],

            /* ── Сообщество ─────────────────────────────────────── */
            ['id' => 'media_comments', 'p' => 'community', 'label' => 'Комментарии в медиа', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => ['media_comments', 'article_comments', 'comments'], 'd' => 'created_at']],
             'hint' => 'Включится автоматически, как только в БД появится таблица комментариев медиа.'],
            ['id' => 'reviews_talk', 'p' => 'community', 'label' => 'Отзывы на ассеты и ответы', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'asset_reviews',  'd' => 'created_at'],
                       ['db' => 'main', 't' => 'review_replies', 'd' => 'created_at']],
             'hint' => 'Отзывы к играм уже в «Контенте» — здесь то, что вокруг них: отзывы на ассеты и ответы студий.'],
            ['id' => 'follows', 'p' => 'community', 'label' => 'Подписки и друзья', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'friends', 'd' => 'created_at']]],
            ['id' => 'active_profiles', 'p' => 'community', 'label' => 'Активные профили', 'fmt' => 'int', 'type' => 'uniq', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'game_reviews',  'd' => 'created_at', 'k' => 'user_id'],
                       ['db' => 'main', 't' => 'asset_reviews', 'd' => 'created_at', 'k' => 'user_id'],
                       ['db' => 'main', 't' => 'friends',       'd' => 'created_at', 'k' => 'player_id'],
                       ['db' => 'main', 't' => 'sprint_teams',  'd' => 'created_at', 'k' => 'captain_id'],
                       ['db' => 'l4t',  't' => 'bids',          'd' => 'created_at', 'k' => 'bidder_id'],
                       ['db' => 'l4t',  't' => 'responds',      'd' => 'created_at', 'k' => 'user_id']],
             'hint' => 'Уникальные люди, сделавшие публичное действие: отзыв, заявка, отклик, дружба, команда.'],
            ['id' => 'l4t_teams', 'p' => 'community', 'label' => 'Команды в L4T', 'fmt' => 'int', 'type' => 'flow', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'sprint_teams', 'd' => 'created_at']]],
            ['id' => 'l4t_bids', 'p' => 'community', 'label' => 'Новые заявки L4T', 'fmt' => 'int', 'type' => 'flow', 'k' => 2,
             'src' => [['db' => 'l4t', 't' => 'bids', 'd' => 'created_at']]],
            ['id' => 'l4t_responds', 'p' => 'community', 'label' => 'Отклики L4T', 'fmt' => 'int', 'type' => 'flow', 'k' => 2,
             'src' => [['db' => 'l4t', 't' => 'responds', 'd' => 'created_at']],
             'hint' => 'Заявка без откликов — мёртвая. Отклики показывают, работает ли биржа как рынок.'],

            /* ── Разработчики ───────────────────────────────────── */
            ['id' => 'active_devs', 'p' => 'devs', 'label' => 'Активные разработчики', 'fmt' => 'int', 'type' => 'uniq', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'game_change_log',    'd' => 'created_at', 'k' => 'user_id'],
                       ['db' => 'main', 't' => 'sprint_submissions', 'd' => 'created_at', 'k' => 'user_id']],
             'hint' => 'Уникальные люди, менявшие игры или сдававшие билд на джем.'],
            ['id' => 'new_studios', 'p' => 'devs', 'label' => 'Новые студии', 'fmt' => 'int', 'type' => 'flow', 'k' => 1,
             'src' => [['db' => 'main', 't' => 'studios', 'd' => 'created_at']]],
            ['id' => 'releases', 'p' => 'devs', 'label' => 'Релизы', 'fmt' => 'int', 'type' => 'flow', 'k' => 1,
             'src' => [['db' => 'main', 't' => 'games', 'd' => 'release_date', 'w' => "status = 'published'", 'need' => ['status']]],
             'hint' => 'Опубликованные игры по дате релиза.'],
            ['id' => 'dev_updates', 'p' => 'devs', 'label' => 'Обновления', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'game_change_log', 'd' => 'created_at'],
                       ['db' => 'main', 't' => 'game_builds',     'd' => 'updated_at']],
             'hint' => 'Все изменения страниц и загрузки билдов — интенсивность работы.'],
            ['id' => 'jam_join', 'p' => 'devs', 'label' => 'Участие в джемах', 'fmt' => 'int', 'type' => 'flow', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'sprint_participants', 'd' => 'joined_at']]],

            /* ── Рост ───────────────────────────────────────────── */
            ['id' => 'new_users', 'p' => 'growth', 'label' => 'Новые пользователи', 'fmt' => 'int', 'type' => 'flow', 'k' => 3,
             'src' => [['db' => 'main', 't' => 'users', 'd' => 'added']]],
            ['id' => 'active_users', 'p' => 'growth', 'label' => 'Активные пользователи', 'fmt' => 'int', 'type' => 'act', 'fn' => 'wau', 'look' => 7, 'k' => 3,
             'hint' => 'Уникальные авторизованные за неделю (WAU).'],
            ['id' => 'resurrected', 'p' => 'growth', 'label' => 'Вернувшиеся', 'fmt' => 'int', 'type' => 'act', 'fn' => 'resurrected', 'look' => 7 + 14, 'k' => 2,
             'hint' => 'Были активны на неделе, но до этого пропадали 14+ дней.'],
            ['id' => 'activation', 'p' => 'growth', 'label' => 'Вернулись после первого входа', 'fmt' => 'pct', 'type' => 'act', 'fn' => 'activation', 'look' => 14, 'k' => 0.03,
             'hint' => 'Из зарегистрированных 7–13 дней назад: доля, у кого был хоть один день после дня регистрации.'],
            ['id' => 'activation_all', 'p' => 'growth', 'label' => 'Вернулись хоть раз (за всё время)', 'fmt' => 'pct', 'type' => 'static', 'fn' => 'activationAll', 'info' => true,
             'hint' => 'Доля всех пользователей, кто пришёл хотя бы ещё раз после дня регистрации. Считается и по следам действий из прошлого — это нижняя оценка.'],

            /* ── Экономика ─────────────────────────────────────── */
            ['id' => 'gmv', 'p' => 'economy', 'label' => 'Оборот (GMV)', 'fmt' => 'rub', 'type' => 'flow', 'k' => 300, 'money' => true,
             'src' => [['db' => 'main', 't' => 'game_orders',    'd' => 'created_at', 'sum' => 'amount', 'w' => "status = 'succeeded'", 'need' => ['status']],
                       ['db' => 'main', 't' => 'asset_payments', 'd' => 'created_at', 'sum' => 'amount', 'w' => "status = 'succeeded'", 'need' => ['status']]],
             'hint' => 'Оплаченные заказы игр и ассетов.'],
            ['id' => 'payers', 'p' => 'economy', 'label' => 'Платящие', 'fmt' => 'int', 'type' => 'uniq', 'k' => 2,
             'src' => [['db' => 'main', 't' => 'game_orders',    'd' => 'created_at', 'k' => 'user_id', 'w' => "status = 'succeeded'", 'need' => ['status']],
                       ['db' => 'main', 't' => 'asset_payments', 'd' => 'created_at', 'k' => 'user_id', 'w' => "status = 'succeeded'", 'need' => ['status']]],
             'hint' => 'Уникальные покупатели за неделю.'],
        ];
    }

    /* =====================================================================
     * СХЕМА И ЗАГРУЗКА
     * ===================================================================*/

    private function probe(): void
    {
        foreach (['main' => $this->pdo, 'l4t' => $this->l4t] as $db => $conn) {
            if (!$conn) continue;
            try {
                $rows = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                                       WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) $this->cols[$db][$r['t']][$r['c']] = true;
            } catch (Throwable $e) {
                error_log('[gpi] probe ' . $db . ': ' . $e->getMessage());
            }
        }
    }

    private function has(string $db, string $t, array $c): bool
    {
        foreach ($c as $col) if (empty($this->cols[$db][$t][$col])) return false;
        return !empty($this->cols[$db][$t]);
    }

    /** Разрешает источник: для списка кандидатов таблиц берёт первую существующую. */
    private function resolve(array $s): ?array
    {
        $need = array_merge([$s['d']], $s['need'] ?? []);
        if (isset($s['k']) && preg_match('/^[a-z_]+$/', $s['k'])) $need[] = $s['k'];
        if (isset($s['sum'])) $need[] = $s['sum'];
        foreach ((array)$s['t'] as $t) {
            if ($this->has($s['db'], $t, $need)) return ['t' => $t] + $s;
        }
        return null;
    }

    private function conn(string $db): ?PDO
    {
        return $db === 'l4t' ? $this->l4t : $this->pdo;
    }

    private function load(): void
    {
        $fromSql = self::ds($this->from);

        foreach ($this->catalog as $m) {
            $id = $m['id'];
            if (!in_array($m['type'], ['flow', 'uniq', 'repeat', 'share'], true)) continue;

            $srcs = array_values(array_filter(array_map([$this, 'resolve'], $m['src'] ?? [])));
            if (!$srcs) { $this->coverage[$id] = null; continue; }
            $this->resolved[$id] = true;

            $cov = null;
            foreach ($srcs as $s) {
                $c = $this->conn($s['db']);
                $t = $s['t']; $d = $s['d'];
                $w = isset($s['w']) ? " AND ({$s['w']})" : '';
                try {
                    $min = $c->query("SELECT MIN(DATE(`$d`)) FROM `$t` WHERE `$d` > '2000-01-01'$w")->fetchColumn();
                    if ($min) $cov = $cov === null ? self::dn($min) : min($cov, self::dn($min));

                    if ($m['type'] === 'flow') {
                        $agg = isset($s['sum']) ? "SUM(`{$s['sum']}`)" : 'COUNT(*)';
                        $st = $c->prepare("SELECT DATE(`$d`) d, $agg v FROM `$t`
                                            WHERE `$d` >= ? AND `$d` < NOW() + INTERVAL 1 DAY$w GROUP BY d");
                        $st->execute([$fromSql]);
                        foreach ($st as $r) {
                            $k = self::dn($r['d']);
                            $this->flow[$id][$k] = ($this->flow[$id][$k] ?? 0) + (float)$r['v'];
                        }
                    } else {
                        $key = preg_match('/^[a-z_]+$/', $s['k']) ? "`{$s['k']}`" : $s['k'];
                        $st = $c->prepare("SELECT DISTINCT DATE(`$d`) d, $key k FROM `$t`
                                            WHERE `$d` >= ? AND `$d` < NOW() + INTERVAL 1 DAY AND $key IS NOT NULL$w");
                        $st->execute([$fromSql]);
                        foreach ($st as $r) $this->uniq[$id][self::dn($r['d'])][(string)$r['k']] = 1;

                        if ($m['type'] === 'repeat') {
                            $st = $c->query("SELECT $key k, MIN(DATE(`$d`)) f FROM `$t`
                                              WHERE $key IS NOT NULL$w GROUP BY k");
                            foreach ($st as $r) {
                                $f = self::dn($r['f']);
                                $kk = (string)$r['k'];
                                $this->first[$id][$kk] = min($this->first[$id][$kk] ?? PHP_INT_MAX, $f);
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log("[gpi] $id/$t: " . $e->getMessage());
                }
            }
            $this->coverage[$id] = $cov;

            if ($m['type'] === 'share' && ($den = $this->resolve($m['den']))) {
                $c = $this->conn($den['db']);
                $w = isset($den['w']) ? " AND ({$den['w']})" : '';
                try {
                    $acc = 0;
                    foreach ($c->query("SELECT DATE(`{$den['d']}`) d, COUNT(*) v FROM `{$den['t']}`
                                         WHERE `{$den['d']}` IS NOT NULL$w GROUP BY d ORDER BY d") as $r) {
                        $acc += (int)$r['v'];
                        $this->flow[$id . ':den'][self::dn($r['d'])] = $acc;   // накопительно
                    }
                } catch (Throwable $e) {
                    error_log("[gpi] $id/den: " . $e->getMessage());
                }
            }
        }

        /* Live-активность и регистрации — общие для столпов «Игроки» и «Рост». */
        if ($this->has('main', 'user_daily_activity', ['user_id', 'day', 'sessions', 'source'])) {
            try {
                $this->actStart = ($v = $this->pdo->query("SELECT MIN(day) FROM user_daily_activity WHERE source = 'live'")->fetchColumn())
                    ? self::dn($v) : null;
                $st = $this->pdo->prepare("SELECT user_id, day, sessions FROM user_daily_activity
                                            WHERE source = 'live' AND day >= ?");
                $st->execute([$fromSql]);
                foreach ($st as $r) $this->act[self::dn($r['day'])][(int)$r['user_id']] = (int)$r['sessions'];
            } catch (Throwable $e) {
                error_log('[gpi] activity: ' . $e->getMessage());
            }
        }
        if ($this->has('main', 'users', ['id', 'added'])) {
            $st = $this->pdo->prepare("SELECT id, DATE(added) d FROM users WHERE added >= ?");
            $st->execute([$fromSql]);
            foreach ($st as $r) $this->reg[self::dn($r['d'])][] = (int)$r['id'];
        }
    }

    /* =====================================================================
     * ЗНАЧЕНИЕ МЕТРИКИ v(D)
     * ===================================================================*/

    /** Начало данных, от которого метрика в принципе считается. */
    private function startOf(array $m): ?int
    {
        return match ($m['type']) {
            'act'    => $this->actStart,
            'static' => 0,
            default  => $this->coverage[$m['id']] ?? null,
        };
    }

    private function look(array $m): int
    {
        return $m['look'] ?? self::WIN;
    }

    /** v(D) или null, если данных для этого дня ещё не было. */
    private function value(array $m, int $D): ?float
    {
        $key = $m['id'] . '@' . $D;
        if (array_key_exists($key, $this->memo)) return $this->memo[$key];

        $start = $this->startOf($m);
        if ($start === null || $D - $this->look($m) + 1 < $start || $D - $this->look($m) < $this->from) {
            return $this->memo[$key] = null;
        }

        $id = $m['id'];
        $v = match ($m['type']) {
            'flow'   => $this->sumWin($this->flow[$id] ?? [], $D),
            'uniq'   => (float)count($this->unionWin($this->uniq[$id] ?? [], $D)),
            'repeat' => $this->repeatWin($id, $D),
            'share'  => $this->shareWin($id, $D),
            'act'    => $this->{$m['fn']}($D),
            'static' => null,
        };
        return $this->memo[$key] = $v;
    }

    private function sumWin(array $daily, int $D, int $n = self::WIN): float
    {
        $s = 0.0;
        for ($i = 0; $i < $n; $i++) $s += $daily[$D - $i] ?? 0;
        return $s;
    }

    private function unionWin(array $daily, int $D, int $n = self::WIN): array
    {
        $u = [];
        for ($i = 0; $i < $n; $i++) {
            if (!empty($daily[$D - $i])) $u += $daily[$D - $i];
        }
        return $u;
    }

    private function repeatWin(string $id, int $D): float
    {
        $n = 0;
        for ($i = 0; $i < self::WIN; $i++) {
            $day = $D - $i;
            foreach ($this->uniq[$id][$day] ?? [] as $k => $_) {
                if (($this->first[$id][$k] ?? $day) < $day) $n++;
            }
        }
        return (float)$n;
    }

    private function shareWin(string $id, int $D): ?float
    {
        $den = 0;
        foreach ($this->flow[$id . ':den'] ?? [] as $day => $acc) {
            if ($day <= $D) $den = $acc; else break;
        }
        if ($den <= 0) return null;
        return min(1.0, count($this->unionWin($this->uniq[$id] ?? [], $D)) / $den);
    }

    /* ── Активность ─────────────────────────────────────────────── */

    private function activeSet(int $D, int $n): array
    {
        $u = [];
        for ($i = 0; $i < $n; $i++) {
            if (!empty($this->act[$D - $i])) $u += $this->act[$D - $i];
        }
        return $u;
    }

    private function dauMau(int $D): ?float
    {
        $sum = 0.0;
        for ($i = 0; $i < self::WIN; $i++) {
            $mau = count($this->activeSet($D - $i, 30));
            if ($mau) $sum += count($this->act[$D - $i] ?? []) / $mau;
        }
        return $sum / self::WIN;
    }

    private function wauMau(int $D): ?float
    {
        $mau = count($this->activeSet($D, 30));
        return $mau ? count($this->activeSet($D, 7)) / $mau : null;
    }

    private function wau(int $D): float
    {
        return (float)count($this->activeSet($D, 7));
    }

    private function sessions(int $D): float
    {
        $s = 0;
        for ($i = 0; $i < self::WIN; $i++) $s += array_sum($this->act[$D - $i] ?? []);
        return (float)$s;
    }

    private function sessPerUser(int $D): ?float
    {
        $u = count($this->activeSet($D, 7));
        return $u ? $this->sessions($D) / $u : null;
    }

    /**
     * Retention когорты. Когорта — регистрации за 7 дней, заканчивающиеся
     * в D-N-slack. Вернулся = был активен в любой из дней [reg+N-slack; reg+N+slack].
     */
    private function retention(int $D, int $N, int $slack): ?float
    {
        $cohort = 0; $back = 0;
        $end = $D - $N - $slack;
        for ($c = $end - self::WIN + 1; $c <= $end; $c++) {
            foreach ($this->reg[$c] ?? [] as $uid) {
                $cohort++;
                for ($d = $c + $N - $slack; $d <= $c + $N + $slack; $d++) {
                    if (isset($this->act[$d][$uid])) { $back++; break; }
                }
            }
        }
        return $cohort ? $back / $cohort : null;
    }

    private function retD1(int $D): ?float  { return $this->retention($D, 1, 0); }
    private function retD7(int $D): ?float  { return $this->retention($D, 7, 1); }
    private function retD30(int $D): ?float { return $this->retention($D, 30, 2); }

    private function resurrected(int $D): float
    {
        $n = 0;
        foreach ($this->activeSet($D, self::WIN) as $uid => $_) {
            // первый день пользователя внутри окна
            $firstIn = null;
            for ($d = $D - self::WIN + 1; $d <= $D; $d++) {
                if (isset($this->act[$d][$uid])) { $firstIn = $d; break; }
            }
            // до него — 14 дней тишины, но хоть какая-то активность раньше
            $silent = true;
            for ($d = $firstIn - 14; $d < $firstIn; $d++) {
                if (isset($this->act[$d][$uid])) { $silent = false; break; }
            }
            if (!$silent) continue;
            $seenBefore = false;
            for ($d = max($this->actStart ?? $firstIn, $this->from); $d < $firstIn - 14; $d++) {
                if (isset($this->act[$d][$uid])) { $seenBefore = true; break; }
            }
            if ($seenBefore) $n++;
        }
        return (float)$n;
    }

    private function activation(int $D): ?float
    {
        $cohort = 0; $back = 0;
        for ($c = $D - 13; $c <= $D - 7; $c++) {
            foreach ($this->reg[$c] ?? [] as $uid) {
                $cohort++;
                for ($d = $c + 1; $d <= $c + 7; $d++) {
                    if (isset($this->act[$d][$uid])) { $back++; break; }
                }
            }
        }
        return $cohort ? $back / $cohort : null;
    }

    private function activationAll(): ?float
    {
        if (!$this->has('main', 'user_daily_activity', ['user_id', 'day'])) return null;
        try {
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if (!$total) return null;
            $back = (int)$this->pdo->query("
                SELECT COUNT(DISTINCT a.user_id)
                  FROM user_daily_activity a
                  JOIN users u ON u.id = a.user_id
                 WHERE a.day > DATE(u.added)")->fetchColumn();
            return $back / $total;
        } catch (Throwable $e) {
            return null;
        }
    }

    /* =====================================================================
     * МОМЕНТУМ И ИНДЕКС
     * ===================================================================*/

    /** [score, base] или null. */
    private function score(array $m, int $D): ?array
    {
        if (!empty($m['info'])) return null;
        $v = $this->value($m, $D);
        if ($v === null) return null;

        $prev = [];
        for ($w = 1; $w <= self::WEEKS; $w++) {
            $p = $this->value($m, $D - self::WIN * $w);
            if ($p !== null) $prev[] = $p;
        }
        if (!$prev) return null;

        $base = array_sum($prev) / count($prev);
        $k = (float)$m['k'];
        $s = ($v + $k) / ($base + $k);
        return [max(self::CLAMP[0], min(self::CLAMP[1], $s)), $base];
    }

    private function indexAt(int $D): array
    {
        $byPillar = [];
        $grow = 0; $n = 0;
        foreach ($this->catalog as $m) {
            $sc = $this->score($m, $D);
            if (!$sc) continue;
            $byPillar[$m['p']][] = log($sc[0]);
            $n++;
            if ($sc[0] > 1 + self::GROW_EPS) $grow++;
        }

        $pillars = [];
        $num = 0.0; $wsum = 0.0;
        foreach (self::PILLARS as $pid => [, $w]) {
            if (empty($byPillar[$pid])) continue;
            $lp = array_sum($byPillar[$pid]) / count($byPillar[$pid]);
            $pillars[$pid] = exp($lp);
            $num += $w * $lp;
            $wsum += $w;
        }

        return [
            'gpi'     => $wsum > 0 ? self::BASE * exp($num / $wsum) : null,
            'pillars' => $pillars,
            'breadth' => $n ? round($grow / $n * 100, 1) : null,
            'counted' => $n,
        ];
    }

    /** Полное описание метрики для фронта. */
    private function describe(array $m, int $D): array
    {
        $srcOk = match ($m['type']) {
            'act'    => $this->has('main', 'user_daily_activity', ['user_id', 'day']),
            'static' => true,
            default  => !empty($this->resolved[$m['id']]),
        };

        $v  = $m['type'] === 'static' ? $this->{$m['fn']}() : $this->value($m, $D);
        $sc = $this->score($m, $D);

        /* Сколько ещё копить: v(D) нужно look дней, базе — ещё хотя бы одно окно. */
        $start = $this->startOf($m);
        $need  = $this->look($m) + self::WIN;
        $left  = $start !== null ? max(0, $start + $need - 1 - $D) : null;

        $status = 'ok';
        if (!$srcOk)                                   $status = 'nosource';
        elseif ($m['type'] !== 'static' && !$sc && empty($m['info'])) $status = 'collecting';
        elseif ($v === null)                           $status = 'collecting';

        /* Спарклайн — 8 недельных точек. */
        $spark = [];
        if ($m['type'] !== 'static') {
            for ($w = 7; $w >= 0; $w--) {
                $p = $this->value($m, $D - self::WIN * $w);
                if ($p !== null) $spark[] = round($p, 4);
            }
        }

        return [
            'id'        => $m['id'],
            'pillar'    => $m['p'],
            'label'     => $m['label'],
            'hint'      => $m['hint'] ?? '',
            'fmt'       => $m['fmt'],
            'money'     => !empty($m['money']),
            'info'      => !empty($m['info']),
            'status'    => $status,
            'days_left' => $status === 'collecting' ? $left : null,
            'value'     => $v !== null ? round($v, 4) : null,
            'base'      => $sc ? round($sc[1], 4) : null,
            'score'     => $sc ? round($sc[0], 4) : null,
            'spark'     => $spark,
        ];
    }

    /* =====================================================================
     * ИСТОРИЯ
     * ===================================================================*/

    private function history(): array
    {
        $D = $this->today;
        $frozen = [];
        $canStore = $this->has('main', 'gpi_daily', ['day', 'gpi']);

        if ($canStore) {
            try {
                $st = $this->pdo->prepare("SELECT day, gpi FROM gpi_daily WHERE day >= ?");
                $st->execute([self::ds($D - self::HISTORY)]);
                foreach ($st as $r) $frozen[self::dn($r['day'])] = (float)$r['gpi'];
            } catch (Throwable $e) { $canStore = false; }
        }

        $out = [];
        $ins = $canStore ? $this->pdo->prepare(
            "INSERT IGNORE INTO gpi_daily (day, gpi, pillars, breadth, computed_at) VALUES (?, ?, ?, ?, NOW())") : null;

        for ($d = $D - self::HISTORY; $d <= $D; $d++) {
            if ($d < $D && isset($frozen[$d])) {
                $out[] = [$d * 86400000, $frozen[$d]];
                continue;
            }
            $ix = $this->indexAt($d);
            if ($ix['gpi'] === null) continue;
            $g = round($ix['gpi'], 1);
            $out[] = [$d * 86400000, $g];
            if ($ins && $d < $D) {
                try {
                    $ins->execute([self::ds($d), $g,
                        json_encode(array_map(fn($x) => round($x * self::BASE, 1), $ix['pillars'])),
                        $ix['breadth']]);
                } catch (Throwable $e) { /* кэш не обязателен */ }
            }
        }
        return $out;
    }

    private function historyValue(array $h, int $d): ?float
    {
        foreach ($h as [$ms, $v]) if (intdiv($ms, 86400000) === $d) return $v;
        return null;
    }

    /* ── даты как номера дней: арифметика без DateTime в горячих циклах ── */
    private static function dn(string $ymd): int
    {
        return intdiv((int)gmmktime(0, 0, 0, (int)substr($ymd, 5, 2), (int)substr($ymd, 8, 2), (int)substr($ymd, 0, 4)), 86400);
    }

    private static function ds(int $dn): string
    {
        return gmdate('Y-m-d', $dn * 86400);
    }
}
