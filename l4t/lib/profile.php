<?php
declare(strict_types=1);

/**
 * l4t/lib/profile.php — всё, что нужно странице профиля L4T, одним объектом.
 *
 * Почему отдельный файл, а не SQL в index.php: страница — это разметка.
 * Когда запросы живут в шаблоне, первое же «а покажи ещё вот это»
 * превращает его в 2000 строк, где SELECT перемешан с <div>. Здесь —
 * только данные, в index.php — только вывод.
 *
 * Каждый запрос обёрнут в safe-хелперы: миграция 006 может быть ещё
 * не накатана, у старой установки может не быть колонки — профиль всё
 * равно откроется, просто с прочерками. Та же философия, что в stat.php.
 */

final class L4TProfile
{
    public const ACCENTS = ['#c32178', '#7b5cff', '#2e9bff', '#2ee6a8', '#ffb020', '#ff5f7a', '#e34ac8', '#a0e34a'];

    public const AVAILABILITY = [
        'open'   => ['Открыт к предложениям', 'ok'],
        'hiring' => ['Собираю команду',       'acc'],
        'busy'   => ['Занят, но читаю',       'warn'],
        'closed' => ['Не ищу',                'mute'],
    ];

    /** Блоки, которые владелец может скрыть от гостей. */
    public const HIDEABLE = [
        'stats'    => 'Статистику L4T',
        'activity' => 'Календарь активности',
        'platform' => 'Статистику на платформе',
    ];

    /** [код, название, описание, ключ счётчика, пороги бронза/серебро/золото, иконка] */
    private const ACHIEVEMENTS = [
        ['recruiter',  'Рекрутер',     'Создавать заявки в L4T',              'bids_total',  [1, 5, 20],     'briefcase'],
        ['responsive', 'Отзывчивый',   'Откликаться на чужие заявки',         'resp_sent',   [1, 10, 30],    'send'],
        ['magnet',     'Магнит',       'Получать отклики на свои заявки',     'resp_in',     [3, 20, 100],   'inbox'],
        ['captain',    'Капитан',      'Собирать команды на джемы',           'teams',       [1, 3, 10],     'users'],
        ['jammer',     'Джемер',       'Участвовать в джемах',                'jams',        [1, 3, 10],     'flame'],
        ['shipper',    'Релизер',      'Выпускать игры своей студией',        'releases',    [1, 3, 10],     'gamepad'],
        ['critic',     'Критик',       'Писать отзывы к играм',               'reviews',     [1, 10, 50],    'star'],
        ['collector',  'Коллекционер', 'Собирать игры в библиотеке',          'library',     [5, 25, 100],   'grid'],
        ['regular',    'Постоянство',  'Активные дни на платформе',           'active_days', [7, 30, 120],   'calendar'],
        ['oldtimer',   'Старожил',     'Дни с регистрации',                   'days_on',     [30, 180, 365], 'clock'],
        ['noticed',    'Заметный',     'Просмотры профиля',                   'views_total', [10, 100, 1000],'eye'],
        ['trusted',    'С рекомендациями', 'Рекомендации от коллег',          'recs',        [1, 5, 15],     'check'],
        ['networker',  'На виду',      'Мероприятия, где отмечен',            'events',      [1, 3, 10],     'pin'],
    ];

    public array $user;
    public bool  $isOwner;
    public array $profile   = [];
    public array $counters  = [];
    public array $views     = ['total' => 0, 'd30' => 0, 'prev30' => 0, 'series' => []];
    public array $l4t       = ['bids_total' => 0, 'bids_active' => 0, 'bid_views' => 0, 'resp_in' => 0, 'resp_in_30' => 0, 'resp_sent' => 0];
    public ?array $revenue  = null;
    public array $activity  = ['map' => [], 'days' => 0, 'streak' => 0, 'best' => 0, 'tracked' => false];
    public array $badges    = [];
    public array $achievements = [];
    public array $studios   = [];
    public array $completeness = ['pct' => 0, 'todo' => []];
    public array $userBids  = [];

    private PDO $main;
    private ?PDO $l4tdb;

    public function __construct(PDO $main, ?PDO $l4tdb, array $user, bool $isOwner)
    {
        $this->main    = $main;
        $this->l4tdb   = $l4tdb;
        $this->user    = $user;
        $this->isOwner = $isOwner;
    }

    public function load(): self
    {
        $uid = (int)$this->user['id'];

        $this->loadProfile($uid);
        $this->loadStudios($uid);
        $this->loadL4T($uid);
        $this->loadViews($uid);
        $this->loadActivity($uid);
        $this->loadPlatform($uid);
        $this->loadBadges($uid);
        if ($this->isOwner) $this->loadRevenue();
        $this->buildAchievements();
        $this->buildCompleteness();

        return $this;
    }

    /* ──────────────────────── запись просмотра ─────────────────────── */

    /**
     * Уникальный просмотр: один зритель — один раз в сутки. Владелец себя
     * не накручивает, боты не считаются. Вызывать ДО вывода HTML: для гостя
     * нужна анонимная кука.
     */
    public static function trackView(?PDO $l4tdb, int $profileId, ?int $viewerId, string $anonId): void
    {
        if (!$l4tdb || $profileId <= 0 || $viewerId === $profileId) return;
        if (preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|telegram/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
        $key = md5($viewerId ? 'u' . $viewerId : 'a' . $anonId);
        try {
            $l4tdb->prepare("INSERT IGNORE INTO profile_views (profile_id, day, viewer_key) VALUES (?, CURDATE(), ?)")
                  ->execute([$profileId, $key]);
        } catch (Throwable $e) { /* таблицы ещё нет — не страшно */ }
    }

    /* ──────────────────────── загрузчики ───────────────────────────── */

    private function loadProfile(int $uid): void
    {
        $row = $this->l4tdb ? $this->row($this->l4tdb, "SELECT * FROM profiles WHERE user_id = ?", [$uid]) : null;
        $p = $row ?: [];

        $accent = strtolower((string)($p['accent'] ?? ''));
        $this->profile = [
            'headline'      => (string)($p['headline'] ?? ''),
            'status_emoji'  => (string)($p['status_emoji'] ?? ''),
            'status_text'   => (string)($p['status_text'] ?? ''),
            'availability'  => isset(self::AVAILABILITY[$p['availability'] ?? '']) ? $p['availability'] : '',
            'location'      => (string)($p['location'] ?? ''),
            'banner_url'    => (string)($p['banner_url'] ?? ''),
            'accent'        => preg_match('/^#[0-9a-f]{6}$/', $accent) ? $accent : self::ACCENTS[0],
            'pinned'        => array_values(array_filter(explode(',', (string)($p['pinned_badges'] ?? '')))),
            'hidden'        => array_values(array_filter(explode(',', (string)($p['hidden_blocks'] ?? '')))),
            'work_modes'    => array_values(array_filter(explode(',', (string)($p['work_modes'] ?? '')))),
            'rate'          => (string)($p['rate'] ?? ''),
            'tz'            => isset($p['tz']) ? (int)$p['tz'] : null,
            'manual'        => (string)($p['manual'] ?? ''),
            'avail_expired' => false,
        ];

        /* «Ищу» протухает через 30 дней. Гость видит, что статуса нет; владелец —
           плашку «вы всё ещё открыты?» с продлением в один клик. */
        if ($this->profile['availability'] !== '' && !empty($p['avail_until']) && $p['avail_until'] < date('Y-m-d')) {
            $this->profile['avail_expired'] = true;
            if (!$this->isOwner) $this->profile['availability'] = '';
        }
    }

    private function loadStudios(int $uid): void
    {
        $own = $this->rows($this->main, "SELECT id, name, tiker, avatar_link, foundation_date, created_at
                                           FROM studios WHERE owner_id = ?", [$uid]);
        if (!$own) {
            $own = $this->rows($this->main, "SELECT id, name, tiker, foundation_date, created_at
                                               FROM studios WHERE owner_id = ?", [$uid]);
        }
        foreach ($own as &$s) $s['role'] = 'владелец';
        unset($s);

        /* staff ↔ users только через telegram_id и только двумя запросами:
           типы колонок разные (BIGINT vs VARCHAR), JOIN не берёт индекс. */
        $tg = (string)($this->user['telegram_id'] ?? '');
        if ($tg !== '') {
            $ids = array_map('intval', array_column(
                $this->rows($this->main, "SELECT org_id FROM staff WHERE telegram_id = ?", [$tg]), 'org_id'));
            $ids = array_values(array_diff($ids, array_map('intval', array_column($own, 'id'))));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                foreach ($this->rows($this->main, "SELECT id, name, tiker, foundation_date, created_at
                                                     FROM studios WHERE id IN ($in)", $ids) as $s) {
                    $s['role'] = 'в команде';
                    $own[] = $s;
                }
            }
        }

        foreach ($own as &$s) {
            $s['games'] = (int)$this->val($this->main, "SELECT COUNT(*) FROM games WHERE developer = ? AND status = 'published'", [$s['id']]);
            $s['staff'] = (int)$this->val($this->main, "SELECT COUNT(*) FROM staff WHERE org_id = ?", [$s['id']]);
        }
        unset($s);
        $this->studios = $own;
    }

    private function loadL4T(int $uid): void
    {
        if (!$this->l4tdb) return;
        $hasViews = $this->hasCol($this->l4tdb, 'bids', 'views');

        $r = $this->row($this->l4tdb, "SELECT COUNT(*) total, SUM(stage = 'active') active"
            . ($hasViews ? ", COALESCE(SUM(views),0) v" : ", 0 v") . " FROM bids WHERE bidder_id = ?", [$uid]) ?? [];

        $this->l4t['bids_total']  = (int)($r['total'] ?? 0);
        $this->l4t['bids_active'] = (int)($r['active'] ?? 0);
        $this->l4t['bid_views']   = (int)($r['v'] ?? 0);
        $this->l4t['resp_in']     = (int)$this->val($this->l4tdb,
            "SELECT COUNT(*) FROM responds r JOIN bids b ON b.id = r.bid_id WHERE b.bidder_id = ?", [$uid]);
        $this->l4t['resp_in_30']  = (int)$this->val($this->l4tdb,
            "SELECT COUNT(*) FROM responds r JOIN bids b ON b.id = r.bid_id
              WHERE b.bidder_id = ? AND r.created_at >= NOW() - INTERVAL 30 DAY", [$uid]);
        $this->l4t['resp_sent']   = (int)$this->val($this->l4tdb, "SELECT COUNT(*) FROM responds WHERE user_id = ?", [$uid]);

        $this->userBids = $this->rows($this->l4tdb,
            "SELECT * FROM bids WHERE bidder_id = ? AND stage = 'active' ORDER BY created_at DESC LIMIT 6", [$uid]);
    }

    private function loadViews(int $uid): void
    {
        if (!$this->l4tdb) return;
        $r = $this->row($this->l4tdb, "SELECT COUNT(*) total,
                    SUM(day >= CURDATE() - INTERVAL 29 DAY) d30,
                    SUM(day <  CURDATE() - INTERVAL 29 DAY AND day >= CURDATE() - INTERVAL 59 DAY) p30
               FROM profile_views WHERE profile_id = ?", [$uid]) ?? [];
        $this->views['total']  = (int)($r['total'] ?? 0);
        $this->views['d30']    = (int)($r['d30'] ?? 0);
        $this->views['prev30'] = (int)($r['p30'] ?? 0);

        $map = [];
        foreach ($this->rows($this->l4tdb, "SELECT day, COUNT(*) c FROM profile_views
                                              WHERE profile_id = ? AND day >= CURDATE() - INTERVAL 29 DAY
                                              GROUP BY day", [$uid]) as $row) {
            $map[$row['day']] = (int)$row['c'];
        }
        for ($i = 29; $i >= 0; $i--) $this->views['series'][] = $map[date('Y-m-d', strtotime("-$i day"))] ?? 0;
    }

    private function loadActivity(int $uid): void
    {
        $rows = $this->rows($this->main, "SELECT day, hits FROM user_daily_activity
                                           WHERE user_id = ? AND day >= CURDATE() - INTERVAL 371 DAY", [$uid]);
        if (!$rows && !$this->hasCol($this->main, 'user_daily_activity', 'day')) return;

        $this->activity['tracked'] = true;
        foreach ($rows as $r) $this->activity['map'][$r['day']] = (int)$r['hits'];
        $this->activity['days'] = (int)$this->val($this->main, "SELECT COUNT(*) FROM user_daily_activity WHERE user_id = ?", [$uid]);

        /* Серия: подряд идущие дни до сегодня (или до вчера — день ещё не закончился). */
        $m = $this->activity['map'];
        $d = isset($m[date('Y-m-d')]) ? 0 : 1;
        $streak = 0;
        while (isset($m[date('Y-m-d', strtotime("-$d day"))])) { $streak++; $d++; }
        $this->activity['streak'] = $streak;

        $best = 0; $cur = 0; $prev = null;
        $days = array_keys($m); sort($days);
        foreach ($days as $day) {
            $t = strtotime($day);
            $cur = ($prev !== null && $t - $prev <= 90000) ? $cur + 1 : 1;
            $best = max($best, $cur);
            $prev = $t;
        }
        $this->activity['best'] = $best;
    }

    private function loadPlatform(int $uid): void
    {
        $studioIds = array_map('intval', array_column($this->studios, 'id'));
        $releases = 0;
        if ($studioIds) {
            $in = implode(',', array_fill(0, count($studioIds), '?'));
            $releases = (int)$this->val($this->main,
                "SELECT COUNT(*) FROM games WHERE status = 'published' AND developer IN ($in)", $studioIds);
        }
        $added = strtotime((string)($this->user['added'] ?? 'now')) ?: time();

        $this->counters = [
            'library'     => (int)$this->val($this->main, "SELECT COUNT(*) FROM library WHERE player_id = ?", [$uid]),
            'reviews'     => (int)$this->val($this->main, "SELECT COUNT(*) FROM game_reviews WHERE user_id = ?", [$uid]),
            'jams'        => (int)$this->val($this->main, "SELECT COUNT(DISTINCT sprint_id) FROM sprint_participants WHERE user_id = ?", [$uid]),
            'teams'       => (int)$this->val($this->main, "SELECT COUNT(*) FROM sprint_teams WHERE captain_id = ?", [$uid]),
            'friends'     => (int)$this->val($this->main, "SELECT COUNT(*) FROM friends
                                WHERE (player_id = ? OR friend_id = ?) AND status = 'accepted'", [$uid, $uid]),
            'releases'    => $releases,
            'days_on'     => max(0, (int)floor((time() - $added) / 86400)),
            'active_days' => $this->activity['days'],
            'views_total' => $this->views['total'],
            'recs'        => $this->l4tdb ? (int)$this->val($this->l4tdb, "SELECT COUNT(*) FROM recommendations WHERE target_id = ? AND hidden = 0", [$uid]) : 0,
            'events'      => $this->l4tdb ? (int)$this->val($this->l4tdb, "SELECT COUNT(*) FROM event_checkins WHERE user_id = ?", [$uid]) : 0,
        ] + $this->l4t;
    }

    private function loadBadges(int $uid): void
    {
        foreach ($this->rows($this->main, "SELECT b.id, b.name, b.description, b.icon_url, gub.awarded_at
                                             FROM given_user_badges gub JOIN badges b ON b.id = gub.badge_id
                                            WHERE gub.user_id = ? ORDER BY gub.awarded_at DESC", [$uid]) as $b) {
            $this->badges[] = [
                'code'  => 'b' . (int)$b['id'],
                'title' => (string)$b['name'],
                'desc'  => (string)($b['description'] ?? ''),
                'img'   => (string)($b['icon_url'] ?? ''),
                'date'  => $b['awarded_at'],
            ];
        }
    }

    /**
     * Выручка — валовая: все успешные оплаты игр и ассетов студий, где
     * пользователь владелец или в команде. Комиссия платформы не вычтена.
     * Видна только владельцу профиля.
     */
    private function loadRevenue(): void
    {
        $ids = array_map('intval', array_column($this->studios, 'id'));
        $rev = ['total' => 0.0, 'd30' => 0.0, 'prev30' => 0.0, 'games' => 0.0, 'assets' => 0.0,
                'orders' => 0, 'months' => [], 'top' => null, 'has_studio' => (bool)$ids];
        for ($i = 5; $i >= 0; $i--) $rev['months'][date('Y-m', strtotime("first day of -$i month"))] = 0.0;

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sources = [
                'games'  => "SELECT o.amount, o.created_at, g.name item FROM game_orders o
                              JOIN games g ON g.id = o.game_id
                             WHERE o.status = 'succeeded' AND g.developer IN ($in)",
                'assets' => "SELECT p.amount, p.created_at, a.name item FROM asset_payments p
                              JOIN assets a ON a.id = p.asset_id
                             WHERE p.status = 'succeeded' AND a.studio_id IN ($in)",
            ];
            $byItem = [];
            $t30 = strtotime('-30 day'); $t60 = strtotime('-60 day');
            foreach ($sources as $kind => $sql) {
                foreach ($this->rows($this->main, $sql, $ids) as $r) {
                    $a = (float)$r['amount'];
                    $t = strtotime((string)$r['created_at']) ?: time();
                    $rev['total'] += $a;
                    $rev[$kind]   += $a;
                    $rev['orders']++;
                    if ($t >= $t30) $rev['d30'] += $a; elseif ($t >= $t60) $rev['prev30'] += $a;
                    $ym = date('Y-m', $t);
                    if (isset($rev['months'][$ym])) $rev['months'][$ym] += $a;
                    $byItem[(string)$r['item']] = ($byItem[(string)$r['item']] ?? 0) + $a;
                }
            }
            if ($byItem) { arsort($byItem); $rev['top'] = [array_key_first($byItem), reset($byItem)]; }
        }
        $this->revenue = $rev;
    }

    /* ──────────────────────── производные ──────────────────────────── */

    private function buildAchievements(): void
    {
        foreach (self::ACHIEVEMENTS as [$code, $title, $desc, $key, $thr, $icon]) {
            $v = (int)($this->counters[$key] ?? 0);
            $tier = 0;
            foreach ($thr as $i => $t) if ($v >= $t) $tier = $i + 1;
            $next = $thr[$tier] ?? null;
            $prev = $tier ? $thr[$tier - 1] : 0;
            $this->achievements[] = [
                'code'  => $code,
                'title' => $title,
                'desc'  => $desc,
                'icon'  => $icon,
                'tier'  => $tier,                              // 0 — не открыто, 1..3 — бронза/серебро/золото
                'value' => $v,
                'next'  => $next,
                'pct'   => $next ? (int)round(($v - $prev) / max(1, $next - $prev) * 100) : 100,
            ];
        }
        usort($this->achievements, fn($a, $b) => [$b['tier'], $b['pct']] <=> [$a['tier'], $a['pct']]);
    }

    private function buildCompleteness(): void
    {
        $u = $this->user; $p = $this->profile;
        $checks = [
            ['avatar',   'Загрузить аватар',              !empty($u['profile_picture'])],
            ['banner',   'Поставить обложку профиля',     $p['banner_url'] !== ''],
            ['headline', 'Написать строку о себе',        $p['headline'] !== ''],
            ['role',     'Указать роль',                  trim((string)($u['l4t_role'] ?? '')) !== ''],
            ['avail',    'Отметить, открыт ли к предложениям', $p['availability'] !== ''],
            ['about',    'Рассказать о себе (от 80 символов)', mb_strlen((string)($u['l4t_about'] ?? '')) >= 80],
            ['exp',      'Добавить опыт',                 count(json_decode((string)($u['l4t_exp'] ?? '[]'), true) ?: []) > 0],
            ['projects', 'Добавить проект в портфолио',   count(json_decode((string)($u['l4t_projects'] ?? '[]'), true) ?: []) > 0],
            ['links',    'Добавить ссылку или резюме',    count(json_decode((string)($u['l4t_files'] ?? '[]'), true) ?: []) > 0],
        ];
        $done = count(array_filter($checks, fn($c) => $c[2]));
        $this->completeness = [
            'pct'  => (int)round($done / count($checks) * 100),
            'todo' => array_values(array_map(fn($c) => ['key' => $c[0], 'label' => $c[1]],
                                  array_filter($checks, fn($c) => !$c[2]))),
        ];
    }

    /** Все бейджи, которые можно закрепить: платформенные + открытые достижения. */
    public function pinnable(): array
    {
        $out = [];
        foreach ($this->badges as $b) $out[$b['code']] = ['title' => $b['title'], 'img' => $b['img'], 'icon' => 'shield', 'tier' => 0];
        foreach ($this->achievements as $a) if ($a['tier'] > 0) $out[$a['code']] = ['title' => $a['title'], 'img' => '', 'icon' => $a['icon'], 'tier' => $a['tier']];
        return $out;
    }

    public function hidden(string $block): bool
    {
        return !$this->isOwner && in_array($block, $this->profile['hidden'], true);
    }

    /* ──────────────────────── safe-хелперы ─────────────────────────── */

    private function rows(PDO $db, string $sql, array $p = []): array
    {
        try { $st = $db->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }

    private function row(PDO $db, string $sql, array $p = []): ?array
    {
        return $this->rows($db, $sql, $p)[0] ?? null;
    }

    private function val(PDO $db, string $sql, array $p = [])
    {
        try { $st = $db->prepare($sql); $st->execute($p); $v = $st->fetchColumn(); return $v === false ? 0 : $v; }
        catch (Throwable $e) { return 0; }
    }

    private function hasCol(PDO $db, string $t, string $c): bool
    {
        static $cache = [];
        $k = spl_object_id($db) . ".$t.$c";
        if (!isset($cache[$k])) {
            $cache[$k] = (bool)$this->val($db, "SELECT COUNT(*) FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);
        }
        return $cache[$k];
    }
}
