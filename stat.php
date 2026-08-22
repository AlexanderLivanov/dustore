<?php
session_start();
require_once('swad/config.php');

$db  = new Database();
$pdo = $db->connect();

/* =========================================================================
 * ХЕЛПЕРЫ
 * =======================================================================*/

/** Скаляр с безопасным фолбэком — страница не падает, если таблицы/колонки нет. */
function qv(PDO $pdo, string $sql, $fallback = 0)
{
    try {
        $v = $pdo->query($sql)->fetchColumn();
        return $v === false ? $fallback : $v;
    } catch (Throwable $e) {
        return $fallback;
    }
}

/** Набор строк с безопасным фолбэком. */
function qa(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Каноничный ежедневный ряд ПРИРОСТОВ с заполнением дыр нулями.
 * Возвращает [[ms, inc], ...] — от первого события до сегодня, без пропусков.
 * Дыры критичны: без них Chart.js рисует «сжатое» время и любой прирост врёт.
 */
function daily_series(PDO $pdo, string $table, string $col): array
{
    $rows = qa($pdo, "SELECT DATE(`$col`) d, COUNT(*) c FROM `$table`
                      WHERE `$col` IS NOT NULL GROUP BY d ORDER BY d");
    if (!$rows) return [];

    $map = [];
    foreach ($rows as $r) {
        if (!empty($r['d'])) $map[$r['d']] = (int)$r['c'];
    }
    if (!$map) return [];

    $cur = new DateTimeImmutable(array_key_first($map) . ' 00:00:00');
    $end = new DateTimeImmutable(date('Y-m-d') . ' 00:00:00');
    $out = [];
    $guard = 0;
    while ($cur <= $end && $guard++ < 20000) {
        $k = $cur->format('Y-m-d');
        $out[] = [$cur->getTimestamp() * 1000, $map[$k] ?? 0];
        $cur = $cur->modify('+1 day');
    }
    return $out;
}

/** Сумма приростов за последние $n дней ряда. */
function tail_sum(array $pts, int $n, int $offset = 0): int
{
    $len = count($pts);
    $to  = $len - $offset;
    $from = max(0, $to - $n);
    if ($to <= 0) return 0;
    $s = 0;
    for ($i = $from; $i < $to; $i++) $s += $pts[$i][1];
    return $s;
}

/** Дельта в % между последними n днями и предыдущими n днями. */
function delta_pct(array $pts, int $n = 7): ?float
{
    if (count($pts) < $n + 1) return null;
    $a = tail_sum($pts, $n);
    $b = tail_sum($pts, $n, $n);
    if ($b === 0) return $a > 0 ? 100.0 : null;
    return round((($a - $b) / $b) * 100, 1);
}

/** Накопительный хвост для спарклайна карточки. */
function spark_tail(array $pts, int $n = 30): array
{
    $tail = array_slice($pts, -$n);
    $base = 0;
    $out  = [];
    foreach ($tail as $p) {
        $base += $p[1];
        $out[] = $base;
    }
    return $out;
}

/* =========================================================================
 * МЕТРИКИ
 * =======================================================================*/

$users_total     = (int)qv($pdo, "SELECT COUNT(*) FROM users");
/* Было INTERVAL 185 MINUTE — это 3 часа «запаса» под рассинхрон часовых
   поясов плюс 5 минут реального окна. Пояса выровнены в config.php,
   запас больше не нужен: окно общее с коллектором (activity.php). */
$users_online    = (int)qv($pdo, "SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL " . ONLINE_WINDOW_MIN . " MINUTE");
$games_total     = (int)qv($pdo, "SELECT COUNT(*) FROM games");
$games_published = (int)qv($pdo, "SELECT COUNT(*) FROM games WHERE status='published'");
$studios_total   = (int)qv($pdo, "SELECT COUNT(*) FROM studios");
$reviews_total   = (int)qv($pdo, "SELECT COUNT(*) FROM game_reviews");
$installs_total  = (int)qv($pdo, "SELECT COUNT(*) FROM library");

$avg_rating_raw = qv($pdo, "SELECT AVG(rating) FROM ratings", null);
$avg_gqi_raw    = qv($pdo, "SELECT AVG(gqi) FROM games WHERE gqi IS NOT NULL", null);
$avg_rating = $avg_rating_raw === null ? null : round((float)$avg_rating_raw, 1);
$avg_gqi    = $avg_gqi_raw    === null ? null : round((float)$avg_gqi_raw, 1);

$posts_7d = (int)qv($pdo, "SELECT COUNT(*) FROM posts WHERE created_at >= NOW() - INTERVAL 7 DAY");
$likes_7d = (int)qv($pdo, "SELECT COUNT(*) FROM likes WHERE created_at >= NOW() - INTERVAL 7 DAY");

/* =========================================================================
 * РЯДЫ. Один каноничный ряд на метрику — всё остальное считает фронт.
 * =======================================================================*/

$s_users   = daily_series($pdo, 'users',        'added');
$s_games   = daily_series($pdo, 'games',        'created_at');
$s_reviews = daily_series($pdo, 'game_reviews', 'created_at');
$s_studios = daily_series($pdo, 'studios',      'created_at');
$s_installs = daily_series($pdo, 'library',     'created_at');

/* Онлайн — это gauge, а не счётчик. Бакетим по 30 минут, чтобы не гнать
   десятки тысяч точек в HTML. */
$online_rows = qa($pdo, "
    SELECT UNIX_TIMESTAMP(MIN(ts)) t, ROUND(AVG(online_count)) v
    FROM users_online_history
    GROUP BY FLOOR(UNIX_TIMESTAMP(ts) / 1800)
    ORDER BY t ASC
");
$s_online = [];
foreach ($online_rows as $r) {
    $s_online[] = [((int)$r['t']) * 1000, (int)$r['v']];
}

/* Пик считаем с оглядкой на текущее значение: коллектор пишет историю
   раз в час, живой замер может его обогнать — и «пик» оказывался МЕНЬШЕ
   «сейчас», что для пользователя выглядит как враньё. */
$online_peak = $users_online;
foreach ($s_online as $p) {
    if ($p[1] > $online_peak) $online_peak = $p[1];
}

/* =========================================================================
 * СПРАВОЧНЫЕ БЛОКИ
 * =======================================================================*/

$genres = qa($pdo, "
    SELECT COALESCE(NULLIF(genre,''),'Без жанра') genre, COUNT(*) c
    FROM games WHERE status='published'
    GROUP BY genre ORDER BY c DESC
");

$top_games = qa($pdo, "
    SELECT g.name, COUNT(l.id) installs
    FROM library l
    JOIN games g ON g.id = l.game_id
    GROUP BY g.id
    ORDER BY installs DESC
    LIMIT 10
");
$top_max = 0;
foreach ($top_games as $g) {
    if ((int)$g['installs'] > $top_max) $top_max = (int)$g['installs'];
}

/* =========================================================================
 * PAYLOAD ДЛЯ ФРОНТА
 * =======================================================================*/

$PAYLOAD = [
    'series' => [
        'users' => [
            'label' => 'Пользователи',
            'kind'  => 'counter',
            'color' => '#c32178',
            'points' => $s_users,
        ],
        'games' => [
            'label' => 'Игры',
            'kind'  => 'counter',
            'color' => '#7b5cff',
            'points' => $s_games,
        ],
        'online' => [
            'label' => 'Онлайн',
            'kind'  => 'gauge',
            'color' => '#2ee6a8',
            'points' => $s_online,
        ],
        'installs' => [
            'label' => 'Скачивания',
            'kind'  => 'counter',
            'color' => '#ffb020',
            'points' => $s_installs,
        ],
    ],
    'genres' => [
        'labels' => array_column($genres, 'genre'),
        'values' => array_map('intval', array_column($genres, 'c')),
    ],
];

/* Карточки метрик: значение + дельта + спарклайн одним описанием. */
$CARDS = [
    ['Пользователей',   $users_total,     $s_users,    true],
    ['Онлайн сейчас',   $users_online,    [],          false],
    ['Игр всего',       $games_total,     $s_games,    true],
    ['Опубликовано',    $games_published, [],          false],
    ['Студий',          $studios_total,   $s_studios,  true],
    ['Отзывов',         $reviews_total,   $s_reviews,  true],
    ['Скачиваний',      $installs_total,  $s_installs, true],
    ['Средний рейтинг', $avg_rating === null ? '—' : $avg_rating, [], false],
    ['Средний GQI',     $avg_gqi === null ? '—' : $avg_gqi,       [], false],
    ['Пик онлайна',     $online_peak,     [],          false],
];
?>
<?php require_once('swad/static/elements/header.php'); ?>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>

<style>
    /* Фон страницы. Минимальный глобальный след — только background-color,
       чтобы убить белый холст под шапкой/подвалом и в оверскролле. */
    html.dst-dark,
    html.dst-dark body {
        background: #14041d;
    }

    /* Красивый слой — fixed-подложка от самой страницы, а не от body.
       z-index:-1 кладёт её под контент, но над фоном body. */
    .dst-st::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background:
            radial-gradient(900px 520px at 78% -8%, rgba(195, 33, 120, .28), transparent 65%),
            radial-gradient(700px 460px at 8% 12%, rgba(123, 92, 255, .16), transparent 62%),
            linear-gradient(180deg, #14041d 0%, #22072a 42%, #4a0e3c 78%, #74155d 100%);
    }

    /* Всё под неймспейсом .dst-st — ничего не течёт в глобальные стили сайта. */
    .dst-st {
        position: relative;
        z-index: 0;
        isolation: isolate;
        min-height: 70vh;
        --p: #c32178;
        --p2: #74155d;
        --bg: #14041d;
        --fg: #f4eef8;
        --muted: rgba(244, 238, 248, .55);
        --line: rgba(255, 255, 255, .12);
        --card: rgba(255, 255, 255, .045);
        --up: #2ee6a8;
        --down: #ff5f7a;
        --s: 5px;

        max-width: 1240px;
        margin: 0 auto;
        padding: 64px 20px 80px;
        color: var(--fg);
        font-family: 'Inter', 'Segoe UI', sans-serif;
        font-size: 15px;
        line-height: 1.5;
    }

    /* Пиксельные углы — ортогональная лесенка, как в дизайн-системе. */
    .dst-st .pix {
        clip-path: polygon(0 var(--s),
                var(--s) var(--s),
                var(--s) 0,
                calc(100% - var(--s)) 0,
                calc(100% - var(--s)) var(--s),
                100% var(--s),
                100% calc(100% - var(--s)),
                calc(100% - var(--s)) calc(100% - var(--s)),
                calc(100% - var(--s)) 100%,
                var(--s) 100%,
                var(--s) calc(100% - var(--s)),
                0 calc(100% - var(--s)));
    }

    /* clip-path съедает border, поэтому «рамка» = внешний слой-подложка. */
    .dst-st .frame {
        background: var(--line);
        padding: 1px;
        transition: background .18s ease;
    }

    .dst-st .frame>.frame__i {
        background: linear-gradient(180deg, rgba(255, 255, 255, .06), rgba(255, 255, 255, .025));
        backdrop-filter: blur(6px);
        height: 100%;
        box-sizing: border-box;
    }

    .dst-st .frame:hover {
        background: rgba(195, 33, 120, .45);
    }

    /* ---------- шапка ---------- */
    .dst-st__head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 34px;
    }

    .dst-st h1 {
        font-family: 'Syne', sans-serif;
        font-weight: 800;
        font-size: clamp(30px, 5vw, 46px);
        letter-spacing: -.02em;
        margin: 0;
        line-height: 1.05;
    }

    .dst-st h1 em {
        font-style: normal;
        color: var(--p);
    }

    .dst-st__sub {
        color: var(--muted);
        font-size: 14px;
        margin-top: 8px;
    }

    .dst-st__live {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .dst-st__dot {
        width: 7px;
        height: 7px;
        background: var(--up);
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(46, 230, 168, .6);
        animation: dstPulse 2s infinite;
    }

    @keyframes dstPulse {
        70% {
            box-shadow: 0 0 0 9px rgba(46, 230, 168, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(46, 230, 168, 0);
        }
    }

    /* ---------- карточки метрик ---------- */
    .dst-st__cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(178px, 1fr));
        gap: 10px;
    }

    .dst-st__card .frame__i {
        padding: 14px 15px 10px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-height: 104px;
    }

    .dst-st__card-label {
        font-size: 11px;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .dst-st__card-val {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 700;
        font-size: 28px;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .dst-st__card-foot {
        margin-top: auto;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 8px;
        height: 26px;
    }

    .dst-st__delta {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        white-space: nowrap;
    }

    .dst-st__delta--up {
        color: var(--up);
    }

    .dst-st__delta--down {
        color: var(--down);
    }

    .dst-st__delta--flat {
        color: var(--muted);
    }

    .dst-st__spark {
        width: 74px;
        height: 24px;
        overflow: visible;
    }

    .dst-st__spark path {
        fill: none;
        stroke: var(--p);
        stroke-width: 2;
        stroke-linejoin: round;
        vector-effect: non-scaling-stroke;
    }

    /* ---------- секции ---------- */
    .dst-st__section {
        margin-top: 46px;
    }

    .dst-st__section-title {
        font-family: 'Syne', sans-serif;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 0 0 16px;
    }

    .dst-st__section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--line);
    }

    /* ---------- глобальный тулбар ---------- */
    .dst-st__toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .dst-st__btn {
        appearance: none;
        border: 0;
        background: rgba(255, 255, 255, .07);
        color: var(--fg);
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        padding: 7px 12px;
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
        --s: 3px;
    }

    .dst-st__btn:hover {
        background: rgba(255, 255, 255, .14);
    }

    .dst-st__btn.is-on {
        background: var(--p);
        color: #fff;
    }

    .dst-st__btn--play {
        background: rgba(46, 230, 168, .16);
        color: var(--up);
    }

    .dst-st__btn--play.is-on {
        background: var(--up);
        color: #06251a;
    }

    .dst-st__toolbar-sep {
        width: 1px;
        height: 20px;
        background: var(--line);
        margin: 0 4px;
    }

    .dst-st__hint {
        font-size: 12px;
        color: var(--muted);
        margin-left: auto;
    }

    /* ---------- графики ---------- */
    .dst-st__charts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(430px, 1fr));
        gap: 12px;
    }

    @media (max-width: 900px) {
        .dst-st__charts {
            grid-template-columns: 1fr;
        }
    }

    .dst-st__chart .frame__i {
        padding: 16px 16px 12px;
    }

    .dst-st__chart-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 4px;
    }

    .dst-st__chart-title {
        font-family: 'Syne', sans-serif;
        font-weight: 600;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0;
    }

    .dst-st__swatch {
        width: 9px;
        height: 9px;
        --s: 2px;
        display: inline-block;
    }

    .dst-st__chart-readout {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        color: var(--muted);
        min-height: 18px;
        margin-bottom: 6px;
    }

    .dst-st__chart-readout b {
        color: var(--fg);
        font-weight: 700;
    }

    .dst-st__canvas-wrap {
        position: relative;
        height: 230px;
    }

    .dst-st__modes {
        display: flex;
        gap: 4px;
    }

    .dst-st__modes .dst-st__btn {
        padding: 5px 9px;
        font-size: 11px;
    }

    /* ---------- брашь (биржевой ползунок) ---------- */
    .dst-st__brush {
        position: relative;
        height: 46px;
        margin-top: 10px;
        background: rgba(0, 0, 0, .22);
        touch-action: none;
        user-select: none;
        --s: 3px;
    }

    .dst-st__brush svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .dst-st__brush svg path {
        fill: rgba(255, 255, 255, .13);
        stroke: rgba(255, 255, 255, .28);
        stroke-width: 1;
        vector-effect: non-scaling-stroke;
    }

    .dst-st__brush-win {
        position: absolute;
        top: 0;
        bottom: 0;
        background: rgba(195, 33, 120, .17);
        border-left: 2px solid var(--p);
        border-right: 2px solid var(--p);
        cursor: grab;
    }

    .dst-st__brush-win:active {
        cursor: grabbing;
    }

    .dst-st__brush-h {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 14px;
        cursor: ew-resize;
    }

    .dst-st__brush-h--l {
        left: -8px;
    }

    .dst-st__brush-h--r {
        right: -8px;
    }

    .dst-st__brush-h::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 2px;
        height: 14px;
        background: #fff;
        opacity: .8;
    }

    /* ---------- нижний ряд ---------- */
    .dst-st__bottom {
        display: grid;
        grid-template-columns: 1fr 1.25fr;
        gap: 12px;
    }

    @media (max-width: 900px) {
        .dst-st__bottom {
            grid-template-columns: 1fr;
        }
    }

    .dst-st__bottom .frame__i {
        padding: 16px;
    }

    .dst-st__top {
        list-style: none;
        padding: 0;
        margin: 10px 0 0;
        counter-reset: tg;
    }

    .dst-st__top li {
        position: relative;
        display: grid;
        grid-template-columns: 24px 1fr auto;
        align-items: center;
        gap: 10px;
        padding: 9px 8px;
        overflow: hidden;
    }

    .dst-st__top li::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: var(--w, 0%);
        background: linear-gradient(90deg, rgba(195, 33, 120, .34), rgba(195, 33, 120, .04));
        z-index: 0;
    }

    .dst-st__top li>* {
        position: relative;
        z-index: 1;
    }

    .dst-st__top-rank {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        color: var(--muted);
    }

    .dst-st__top-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dst-st__top-num {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 700;
        font-size: 14px;
    }

    .dst-st__top-unit {
        font-family: 'Inter', sans-serif;
        font-weight: 400;
        font-size: 11px;
        color: var(--muted);
        margin-left: 5px;
    }

    .dst-st__empty {
        color: var(--muted);
        font-size: 13px;
        padding: 18px 0;
        text-align: center;
    }
</style>

<script>document.documentElement.classList.add('dst-dark');</script>

<div class="dst-st">

    <header class="dst-st__head">
        <div>
            <h1>Статистика <em>Dustore</em></h1>
            <div class="dst-st__sub">Живые данные платформы. Тяни ползунок под графиком — как на бирже.</div>
        </div>
        <div class="dst-st__live"><span class="dst-st__dot"></span> обновлено <?= date('d.m.Y H:i') ?></div>
    </header>

    <!-- ===================== МЕТРИКИ ===================== -->
    <div class="dst-st__cards">
        <?php foreach ($CARDS as [$label, $value, $series, $withDelta]):
            $d = ($withDelta && $series) ? delta_pct($series, 7) : null;
            $sp = $series ? spark_tail($series, 30) : [];
            $cls = $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
        ?>
            <div class="dst-st__card frame pix">
                <div class="frame__i pix">
                    <div class="dst-st__card-label"><?= htmlspecialchars($label) ?></div>
                    <div class="dst-st__card-val"><?= is_numeric($value) ? number_format((float)$value, (floor((float)$value) == $value ? 0 : 1), ',', ' ') : htmlspecialchars((string)$value) ?></div>
                    <div class="dst-st__card-foot">
                        <span class="dst-st__delta dst-st__delta--<?= $cls ?>">
                            <?php if ($d === null): ?>&nbsp;
                            <?php else: ?><?= $d > 0 ? '▲' : ($d < 0 ? '▼' : '•') ?> <?= abs($d) ?>% <span style="opacity:.6">7д</span>
                            <?php endif; ?>
                        </span>
                        <?php if (count($sp) > 1): ?>
                            <svg class="dst-st__spark" viewBox="0 0 100 28" preserveAspectRatio="none"
                                data-spark="<?= htmlspecialchars(json_encode($sp), ENT_QUOTES) ?>"></svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===================== ГРАФИКИ ===================== -->
    <section class="dst-st__section">
        <h2 class="dst-st__section-title">Динамика</h2>

        <div class="dst-st__toolbar">
            <button class="dst-st__btn pix" data-range="7">7д</button>
            <button class="dst-st__btn pix" data-range="30">30д</button>
            <button class="dst-st__btn pix" data-range="90">90д</button>
            <button class="dst-st__btn pix" data-range="365">1г</button>
            <button class="dst-st__btn pix is-on" data-range="all">Всё</button>
            <span class="dst-st__toolbar-sep"></span>
            <button class="dst-st__btn dst-st__btn--play pix" id="dstPlay">▶ Проиграть историю</button>
            <span class="dst-st__hint">колесо — зум, drag — панорама, двойной клик — сброс</span>
        </div>

        <div class="dst-st__charts" id="dstCharts"></div>
    </section>

    <!-- ===================== ЖАНРЫ + ТОП ===================== -->
    <section class="dst-st__section">
        <h2 class="dst-st__section-title">Каталог</h2>
        <div class="dst-st__bottom">
            <div class="frame pix">
                <div class="frame__i pix">
                    <h3 class="dst-st__chart-title">Жанры</h3>
                    <div class="dst-st__canvas-wrap" style="height:280px;margin-top:10px">
                        <canvas id="dstGenres"></canvas>
                    </div>
                </div>
            </div>
            <div class="frame pix">
                <div class="frame__i pix">
                    <h3 class="dst-st__chart-title">Топ игр по скачиваниям</h3>
                    <?php if (!$top_games): ?>
                        <div class="dst-st__empty">Пока нет данных</div>
                    <?php else: ?>
                        <ol class="dst-st__top">
                            <?php foreach ($top_games as $i => $g):
                                $inst = (int)$g['installs'];
                                $w = $top_max > 0 ? round($inst / $top_max * 100) : 0;
                            ?>
                                <li style="--w:<?= $w ?>%">
                                    <span class="dst-st__top-rank"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                    <span class="dst-st__top-name"><?= htmlspecialchars($g['name']) ?></span>
                                    <span class="dst-st__top-num"><?= number_format($inst, 0, ',', ' ') ?><span class="dst-st__top-unit">скачиваний</span></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($posts_7d || $likes_7d): ?>
        <section class="dst-st__section">
            <h2 class="dst-st__section-title">Активность за 7 дней</h2>
            <div class="dst-st__cards">
                <div class="dst-st__card frame pix">
                    <div class="frame__i pix">
                        <div class="dst-st__card-label">Постов</div>
                        <div class="dst-st__card-val"><?= number_format($posts_7d, 0, ',', ' ') ?></div>
                    </div>
                </div>
                <div class="dst-st__card frame pix">
                    <div class="frame__i pix">
                        <div class="dst-st__card-label">Лайков</div>
                        <div class="dst-st__card-val"><?= number_format($likes_7d, 0, ',', ' ') ?></div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

</div>

<?php require_once('swad/static/elements/footer.php'); ?>

<script>
    (function () {
        'use strict';

        var DATA = <?= json_encode($PAYLOAD, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
        var root = document.querySelector('.dst-st');
        if (!root || typeof Chart === 'undefined') return;

        if (window.ChartZoom) Chart.register(window.ChartZoom);
        Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
        Chart.defaults.color = 'rgba(244,238,248,.5)';
        Chart.defaults.animation.duration = 320;

        var DAY = 86400000;
        var nf = new Intl.NumberFormat('ru-RU');
        var df = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' });

        /* ---------------------------------------------------------------
         * ТРАНСФОРМАЦИИ РЯДА
         * PHP отдаёт ОДИН ряд приростов. Все режимы — производные от него.
         * ------------------------------------------------------------- */
        function toCumulative(pts) {
            var s = 0;
            return pts.map(function (p) { s += p[1]; return [p[0], s]; });
        }
        function movingAvg(pts, w) {
            var out = [], sum = 0;
            for (var i = 0; i < pts.length; i++) {
                sum += pts[i][1];
                if (i >= w) sum -= pts[i - w][1];
                out.push([pts[i][0], +(sum / Math.min(i + 1, w)).toFixed(2)]);
            }
            return out;
        }
        function view(series, mode) {
            var p = series.points;
            if (series.kind === 'gauge') return mode === 'speed' ? movingAvg(p, 12) : p;
            if (mode === 'daily') return p;
            if (mode === 'speed') return movingAvg(p, 7);
            return toCumulative(p);
        }
        function slice(pts, t0, t1) {
            return pts.filter(function (p) { return p[0] >= t0 && p[0] <= t1; });
        }
        function xy(pts) {
            return pts.map(function (p) { return { x: p[0], y: p[1] }; });
        }

        /* ---------------------------------------------------------------
         * БРАШЬ — «биржевой» ползунок под графиком
         * ------------------------------------------------------------- */
        function Brush(host, pts, onChange) {
            this.host = host; this.pts = pts; this.onChange = onChange;
            this.f0 = 0; this.f1 = 1;
            host.classList.add('dst-st__brush', 'pix');
            host.innerHTML =
                '<svg viewBox="0 0 100 100" preserveAspectRatio="none"><path d=""/></svg>' +
                '<div class="dst-st__brush-win">' +
                '<div class="dst-st__brush-h dst-st__brush-h--l" data-h="l"></div>' +
                '<div class="dst-st__brush-h dst-st__brush-h--r" data-h="r"></div>' +
                '</div>';
            this.win = host.querySelector('.dst-st__brush-win');
            this.path = host.querySelector('path');
            this.drawSpark();
            this.bind();
            this.apply(false);
        }
        Brush.prototype.drawSpark = function () {
            var p = this.pts;
            if (p.length < 2) return;
            var max = 1;
            for (var i = 0; i < p.length; i++) if (p[i][1] > max) max = p[i][1];
            var d = 'M0,100';
            for (var j = 0; j < p.length; j++) {
                d += ' L' + (j / (p.length - 1) * 100).toFixed(3) + ',' + (100 - p[j][1] / max * 92).toFixed(3);
            }
            d += ' L100,100 Z';
            this.path.setAttribute('d', d);
        };
        Brush.prototype.bind = function () {
            var self = this, drag = null, startX = 0, s0 = 0, s1 = 0;
            function frac(e) {
                var r = self.host.getBoundingClientRect();
                return Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
            }
            self.host.addEventListener('pointerdown', function (e) {
                var h = e.target.getAttribute && e.target.getAttribute('data-h');
                drag = h || (e.target === self.win ? 'move' : 'new');
                startX = frac(e); s0 = self.f0; s1 = self.f1;
                if (drag === 'new') { self.f0 = startX; self.f1 = startX; drag = 'r'; s0 = startX; s1 = startX; }
                self.host.setPointerCapture(e.pointerId);
                e.preventDefault();
            });
            self.host.addEventListener('pointermove', function (e) {
                if (!drag) return;
                var f = frac(e), d = f - startX;
                if (drag === 'l') self.f0 = Math.min(f, self.f1 - 0.01);
                else if (drag === 'r') self.f1 = Math.max(f, self.f0 + 0.01);
                else {
                    var w = s1 - s0;
                    self.f0 = Math.min(Math.max(0, s0 + d), 1 - w);
                    self.f1 = self.f0 + w;
                }
                self.f0 = Math.max(0, self.f0); self.f1 = Math.min(1, self.f1);
                self.apply(true);
            });
            function stop() { drag = null; }
            self.host.addEventListener('pointerup', stop);
            self.host.addEventListener('pointercancel', stop);
        };
        Brush.prototype.setFractions = function (f0, f1, silent) {
            this.f0 = Math.max(0, Math.min(f0, 1));
            this.f1 = Math.max(this.f0 + 0.005, Math.min(f1, 1));
            this.apply(!silent);
        };
        Brush.prototype.apply = function (emit) {
            this.win.style.left = (this.f0 * 100) + '%';
            this.win.style.width = ((this.f1 - this.f0) * 100) + '%';
            if (!emit || !this.pts.length) return;
            var a = this.pts[0][0], b = this.pts[this.pts.length - 1][0];
            this.onChange(a + (b - a) * this.f0, a + (b - a) * this.f1);
        };

        /* ---------------------------------------------------------------
         * КАРТОЧКА ГРАФИКА
         * ------------------------------------------------------------- */
        function ChartCard(key, series) {
            this.key = key; this.series = series;
            this.mode = series.kind === 'gauge' ? 'raw' : 'total';
            this.compare = false;
            var pts = series.points;
            this.tMin = pts.length ? pts[0][0] : Date.now() - 30 * DAY;
            this.tMax = pts.length ? pts[pts.length - 1][0] : Date.now();
            this.t0 = this.tMin; this.t1 = this.tMax;
            this.build();
        }

        ChartCard.prototype.modeButtons = function () {
            if (this.series.kind === 'gauge') {
                return [['raw', 'Онлайн'], ['speed', 'Сглажено']];
            }
            return [['total', 'Всего'], ['daily', 'В день'], ['speed', 'Скорость']];
        };

        ChartCard.prototype.build = function () {
            var self = this, s = this.series;
            var wrap = document.createElement('div');
            wrap.className = 'dst-st__chart frame pix';
            wrap.innerHTML =
                '<div class="frame__i pix">' +
                '<div class="dst-st__chart-head">' +
                '<h3 class="dst-st__chart-title"><i class="dst-st__swatch pix" style="background:' + s.color + '"></i>' + s.label + '</h3>' +
                '<div class="dst-st__modes"></div>' +
                '</div>' +
                '<div class="dst-st__chart-readout"></div>' +
                '<div class="dst-st__canvas-wrap"><canvas></canvas></div>' +
                '<div class="dst-st__brush-host"></div>' +
                '</div>';
            document.getElementById('dstCharts').appendChild(wrap);

            var modes = wrap.querySelector('.dst-st__modes');
            this.modeButtons().forEach(function (m) {
                var b = document.createElement('button');
                b.className = 'dst-st__btn pix' + (m[0] === self.mode ? ' is-on' : '');
                b.textContent = m[1];
                b.onclick = function () {
                    self.mode = m[0];
                    modes.querySelectorAll('[data-role="mode"]').forEach(function (x) { x.classList.remove('is-on'); });
                    b.classList.add('is-on');
                    self.render();
                };
                b.setAttribute('data-role', 'mode');
                modes.appendChild(b);
            });
            if (s.kind === 'counter') {
                var cb = document.createElement('button');
                cb.className = 'dst-st__btn pix';
                cb.textContent = '⇄ Пред. период';
                cb.onclick = function () {
                    self.compare = !self.compare;
                    cb.classList.toggle('is-on', self.compare);
                    self.render();
                };
                modes.appendChild(cb);
            }

            this.readout = wrap.querySelector('.dst-st__chart-readout');
            this.canvas = wrap.querySelector('canvas');
            this.chart = new Chart(this.canvas, {
                type: 'line',
                data: { datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },   // <- вот из-за него было «undefined»
                        tooltip: {
                            backgroundColor: 'rgba(20,4,29,.95)',
                            borderColor: 'rgba(255,255,255,.15)',
                            borderWidth: 1,
                            padding: 10,
                            titleFont: { size: 12 },
                            bodyFont: { family: "'JetBrains Mono', monospace", size: 12 },
                            callbacks: {
                                title: function (it) { return df.format(new Date(it[0].parsed.x)); },
                                label: function (c) {
                                    return (c.dataset.label || '') + ': ' + nf.format(c.parsed.y);
                                }
                            }
                        },
                        zoom: {
                            pan: { enabled: true, mode: 'x' },
                            zoom: {
                                wheel: { enabled: true, speed: 0.08 },
                                pinch: { enabled: true },
                                mode: 'x',
                                onZoomComplete: function (ctx) { self.syncBrushFromChart(ctx.chart); }
                            },
                            limits: { x: { min: this.tMin, max: this.tMax } }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: 'day', tooltipFormat: 'dd.MM.yyyy', displayFormats: { day: 'dd.MM', month: 'LLL yy' } },
                            grid: { color: 'rgba(255,255,255,.05)' },
                            border: { color: 'rgba(255,255,255,.12)' },
                            ticks: { maxRotation: 0, autoSkipPadding: 22, font: { size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255,255,255,.05)' },
                            border: { display: false },
                            ticks: { precision: 0, font: { size: 11 }, callback: function (v) { return nf.format(v); } }
                        }
                    }
                }
            });

            this.canvas.addEventListener('dblclick', function () { self.chart.resetZoom(); self.setRange(self.tMin, self.tMax); });

            this.brush = new Brush(wrap.querySelector('.dst-st__brush-host'), view(s, 'total'), function (a, b) {
                self.t0 = a; self.t1 = b; self.render();
            });
            this.render();
        };

        ChartCard.prototype.syncBrushFromChart = function (chart) {
            var sx = chart.scales.x, span = this.tMax - this.tMin;
            if (span <= 0) return;
            this.t0 = sx.min; this.t1 = sx.max;
            this.brush.setFractions((sx.min - this.tMin) / span, (sx.max - this.tMin) / span, true);
        };

        ChartCard.prototype.setRange = function (t0, t1) {
            this.t0 = Math.max(t0, this.tMin);
            this.t1 = Math.min(t1, this.tMax);
            var span = this.tMax - this.tMin;
            if (span > 0) this.brush.setFractions((this.t0 - this.tMin) / span, (this.t1 - this.tMin) / span, true);
            this.render();
        };

        ChartCard.prototype.render = function () {
            var s = this.series, full = view(s, this.mode);
            var main = slice(full, this.t0, this.t1);
            var ds = [{
                label: s.label,
                data: xy(main),
                borderColor: s.color,
                backgroundColor: (function () {
                    var g = this.canvas.getContext('2d').createLinearGradient(0, 0, 0, 220);
                    g.addColorStop(0, s.color + '55'); g.addColorStop(1, s.color + '00');
                    return g;
                }).call(this),
                fill: true,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: this.mode === 'total' ? 0.25 : 0.15
            }];

            /* Сравнение с предыдущим периодом: тот же по длине отрезок,
               сдвинутый вперёд на длину окна — линии ложатся друг на друга. */
            if (this.compare) {
                var L = this.t1 - this.t0;
                var prev = slice(full, this.t0 - L, this.t0).map(function (p) { return [p[0] + L, p[1]]; });
                if (prev.length > 1) {
                    ds.push({
                        label: 'Пред. период',
                        data: xy(prev),
                        borderColor: 'rgba(255,255,255,.42)',
                        borderDash: [5, 4],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: false,
                        tension: 0.2
                    });
                }
            }

            this.chart.data.datasets = ds;
            this.chart.options.scales.x.min = this.t0;
            this.chart.options.scales.x.max = this.t1;
            this.chart.update('none');
            this.updateReadout(main);
        };

        ChartCard.prototype.updateReadout = function (pts) {
            if (!pts.length) { this.readout.textContent = 'Нет данных в выбранном диапазоне'; return; }
            var first = pts[0][1], last = pts[pts.length - 1][1];
            var days = Math.max(1, Math.round((pts[pts.length - 1][0] - pts[0][0]) / DAY));
            var txt;
            if (this.series.kind === 'gauge') {
                var max = 0, sum = 0;
                pts.forEach(function (p) { if (p[1] > max) max = p[1]; sum += p[1]; });
                txt = 'пик <b>' + nf.format(max) + '</b> · средний <b>' + nf.format(Math.round(sum / pts.length)) + '</b> · сейчас <b>' + nf.format(last) + '</b>';
            } else if (this.mode === 'total') {
                var grew = last - first;
                var pct = first > 0 ? Math.round(grew / first * 100) : null;
                txt = '<b>' + nf.format(last) + '</b> всего · за период <b>+' + nf.format(grew) + '</b>' +
                    (pct !== null ? ' (<b>+' + pct + '%</b>)' : '') + ' · <b>' + (grew / days).toFixed(1) + '</b>/день';
            } else {
                var s2 = 0; pts.forEach(function (p) { s2 += p[1]; });
                txt = 'сумма за период <b>' + nf.format(Math.round(s2)) + '</b> · в среднем <b>' + (s2 / pts.length).toFixed(1) + '</b>/день';
            }
            this.readout.innerHTML = txt + ' · ' + days + ' дн.';
        };

        /* ---------------------------------------------------------------
         * ИНИЦИАЛИЗАЦИЯ
         * ------------------------------------------------------------- */
        var cards = [];
        ['users', 'games', 'online', 'installs'].forEach(function (k) {
            var s = DATA.series[k];
            if (s && s.points && s.points.length > 1) cards.push(new ChartCard(k, s));
        });

        /* Глобальные пресеты диапазона */
        root.querySelectorAll('[data-range]').forEach(function (btn) {
            btn.onclick = function () {
                root.querySelectorAll('[data-range]').forEach(function (b) { b.classList.remove('is-on'); });
                btn.classList.add('is-on');
                var r = btn.getAttribute('data-range');
                cards.forEach(function (c) {
                    c.chart.resetZoom();
                    if (r === 'all') c.setRange(c.tMin, c.tMax);
                    else c.setRange(c.tMax - parseInt(r, 10) * DAY, c.tMax);
                });
            };
        });

        /* Плеер: прокручивает конец окна по истории платформы */
        var playBtn = document.getElementById('dstPlay'), timer = null;
        playBtn.onclick = function () {
            if (timer) {
                clearInterval(timer); timer = null;
                playBtn.classList.remove('is-on'); playBtn.textContent = '▶ Проиграть историю';
                return;
            }
            playBtn.classList.add('is-on'); playBtn.textContent = '❚❚ Стоп';
            var step = 0, steps = 90;
            cards.forEach(function (c) { c.chart.resetZoom(); });
            timer = setInterval(function () {
                step++;
                var k = step / steps;
                cards.forEach(function (c) {
                    var span = c.tMax - c.tMin;
                    c.setRange(c.tMin, c.tMin + span * Math.min(1, k));
                });
                if (step >= steps) {
                    clearInterval(timer); timer = null;
                    playBtn.classList.remove('is-on'); playBtn.textContent = '▶ Проиграть историю';
                }
            }, 55);
        };

        /* Спарклайны в карточках метрик */
        root.querySelectorAll('[data-spark]').forEach(function (svg) {
            var v;
            try { v = JSON.parse(svg.getAttribute('data-spark')); } catch (e) { return; }
            if (!v || v.length < 2) return;
            var min = Math.min.apply(null, v), max = Math.max.apply(null, v), rng = (max - min) || 1;
            var d = v.map(function (y, i) {
                return (i ? 'L' : 'M') + (i / (v.length - 1) * 100).toFixed(2) + ',' + (26 - (y - min) / rng * 24).toFixed(2);
            }).join(' ');
            var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('d', d);
            svg.appendChild(p);
        });

        /* Жанры */
        var gEl = document.getElementById('dstGenres');
        if (gEl && DATA.genres.labels.length) {
            var palette = ['#c32178', '#7b5cff', '#2ee6a8', '#ffb020', '#4aa8ff', '#ff5f7a', '#a0e34a', '#e34ac8', '#4ae3d8', '#9a8cff'];
            new Chart(gEl, {
                type: 'doughnut',
                data: {
                    labels: DATA.genres.labels,
                    datasets: [{
                        label: 'Игр',
                        data: DATA.genres.values,
                        backgroundColor: DATA.genres.labels.map(function (_, i) { return palette[i % palette.length]; }),
                        borderColor: 'rgba(20,4,29,.85)',
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 10, boxHeight: 10, padding: 10, font: { size: 12 } } },
                        tooltip: {
                            backgroundColor: 'rgba(20,4,29,.95)',
                            borderColor: 'rgba(255,255,255,.15)',
                            borderWidth: 1,
                            callbacks: {
                                label: function (c) {
                                    var tot = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    return ' ' + c.label + ': ' + nf.format(c.parsed) + ' (' + Math.round(c.parsed / tot * 100) + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    })();
</script>