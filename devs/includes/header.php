<?php

/**
 * devs/includes/header.php — единый layout консоли разработчика.
 * Переменные до подключения:
 *   $page_title  — заголовок страницы
 *   $active_nav  — id приложения (см. $apps)
 *
 * Два режима:
 *   оболочка  — обычный заход в браузере: док + тайловые окна (includes/shell.php), страница дальше не выполняется;
 *   встроенный — страница грузится в окно-iframe оболочки: отдаём только контент.
 * Режим определяем по заголовку Sec-Fetch-Dest, который браузер сам ставит навигации внутри iframe.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../swad/config.php');
require_once(__DIR__ . '/../../swad/controllers/user.php');
require_once(__DIR__ . '/../../swad/controllers/organization.php');

$ds_embed = ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe' || isset($_GET['embed']);

// Из окна уводим на верхний уровень, иначе логин/выбор студии откроются внутри iframe
function ds_redirect(string $url, bool $embed): void
{
    if ($embed) {
        echo '<script>top.location.href=' . json_encode($url) . '</script>';
    } else {
        header('Location: ' . $url);
    }
    exit();
}

$curr_user = new User();
$db        = new Database();
$org       = new Organization();

if ($curr_user->checkAuth() > 0) {
    ds_redirect('/login?backUrl=' . urlencode($_SERVER['REQUEST_URI']), $ds_embed);
}

if (empty($_SESSION['studio_id'])) {
    ds_redirect('/devs/select', $ds_embed);
}

$curr_user_data     = $_SESSION['USERDATA'];
$studio_id          = (int)$_SESSION['studio_id'];
$curr_user_org_data = $curr_user->getOrgData($studio_id);
$_SESSION['STUDIODATA'] = $curr_user_org_data;

$user_id = (int)($curr_user_data['id'] ?? 0);
$tg_id   = (string)($curr_user_data['telegram_id'] ?? '');

$_s = $db->connect()->prepare("SELECT role FROM staff WHERE telegram_id=? AND org_id=? LIMIT 1");
$_s->execute([$tg_id, $studio_id]);
$_sr = $_s->fetch(PDO::FETCH_ASSOC);
$role_name = $_sr ? ($_sr['role'] ?? 'Участник') : 'Участник';

$is_admin = ((int)($curr_user_data['global_role'] ?? 0)) === -1;
$is_moder = ($is_admin || in_array($role_name, ['Модератор', 'Администратор']));
$is_owner = ($is_admin || $role_name === 'Владелец');

$studio_name     = htmlspecialchars($curr_user_org_data['name'] ?? 'Studio');
$studio_avatar   = $curr_user_org_data['avatar_link'] ?? '';
$studio_initials = mb_strtoupper(mb_substr($studio_name, 0, 2));

$page_title = $page_title ?? 'Консоль';
$active_nav = $active_nav ?? 'dashboard';

// ── Счётчики (кеш 5 минут в сессии) ─────────────────────────────────────
$_ck    = 'sb_cnt_' . $studio_id;
$_ck_ts = $_ck . '_ts';
if (empty($_SESSION[$_ck]) || (time() - ($_SESSION[$_ck_ts] ?? 0)) > 300) {
    $_pdo = $db->connect();

    $_gs = $_pdo->prepare("SELECT id FROM games WHERE developer=?");
    $_gs->execute([$studio_id]);
    $_pids = $_gs->fetchAll(PDO::FETCH_COLUMN);

    $sb_reviews = 0;
    $sb_expert  = 0;

    if (!empty($_pids)) {
        $in = implode(',', array_map('intval', $_pids));
        // Отзывы без ответа за 30 дней
        $sb_reviews = (int)$_pdo->query("
            SELECT COUNT(*) FROM game_reviews r
            WHERE r.game_id IN ($in)
              AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM review_replies rr
                  WHERE rr.review_id=r.id AND rr.studio_id=$studio_id
              )
        ")->fetchColumn();
        // Рецензии за 14 дней
        $sb_expert = (int)$_pdo->query("
            SELECT COUNT(*) FROM moderation_reviews
            WHERE game_id IN ($in)
              AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ")->fetchColumn();
    }

    if ($is_moder) {
        $sb_events  = (int)$_pdo->query("SELECT COUNT(*) FROM platform_events WHERE is_read=0")->fetchColumn();
        $sb_orgs    = (int)$_pdo->query("SELECT COUNT(*) FROM studios WHERE status='pending'")->fetchColumn();
        $sb_experts = (int)$_pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
        $sb_assets  = (int)$_pdo->query("SELECT COUNT(*) FROM assets WHERE status='pending'")->fetchColumn();
    } else {
        $_ev = $_pdo->prepare("SELECT COUNT(*) FROM platform_events WHERE studio_id=? AND is_read=0");
        $_ev->execute([$studio_id]);
        $sb_events  = (int)$_ev->fetchColumn();
        $sb_orgs    = 0;
        $sb_experts = 0;
        $sb_assets  = 0;
    }

    // заявки на монетизацию — только администратору платформы
    $sb_mon = 0;
    if ($is_admin) {
        try {
            $sb_mon = (int)$_pdo->query("SELECT COUNT(*) FROM mon_requests WHERE status = 'pending'")->fetchColumn();
        } catch (Throwable $e) {
        }
    }

    $_SESSION[$_ck] = [
        'mon'     => $sb_mon,
        'reviews' => $sb_reviews,
        'expert'  => $sb_expert,
        'events'  => $sb_events,
        'orgs'    => $sb_orgs,
        'experts' => $sb_experts,
        'assets'  => $sb_assets,
    ];
    $_SESSION[$_ck_ts] = time();
}
$sb = $_SESSION[$_ck];

// ── Приложения ──────────────────────────────────────────────────────────
// dock => true  — закреплено в доке по умолчанию; дальше каждый закрепляет своё галочкой в поиске (Ctrl+K)
// top  => true  — открывается на всю вкладку, а не в окне
$apps = [
    ['id' => 'dashboard',      'href' => '/devs/',               'icon' => 'dashboard',          'label' => 'Обзор',        'group' => 'Проекты', 'dock' => true],
    ['id' => 'projects',       'href' => '/devs/projects',       'icon' => 'videogame_asset',    'label' => 'Проекты',      'group' => 'Проекты', 'dock' => true],
    ['id' => 'new',            'href' => '/devs/new',            'icon' => 'add_circle_outline', 'label' => 'Новый проект', 'group' => 'Проекты', 'dock' => true],
    ['id' => 'events',         'href' => '/devs/events',         'icon' => 'notifications',      'label' => 'События',      'group' => 'Проекты', 'dock' => true, 'badge' => $sb['events']],
    ['id' => 'wishlists',      'href' => '/devs/wishlists',      'icon' => 'favorite',           'label' => 'Вишлисты',     'group' => 'Проекты', 'dock' => false],

    ['id' => 'replies',        'href' => '/devs/replies',        'icon' => 'rate_review',        'label' => 'Отзывы',       'group' => 'Аудитория', 'dock' => true, 'badge' => $sb['reviews']],
    ['id' => 'expert_reviews', 'href' => '/devs/expert_reviews', 'icon' => 'workspace_premium',  'label' => 'Рецензии',     'group' => 'Аудитория', 'dock' => false, 'badge' => $sb['expert']],
    ['id' => 'analytics',      'href' => '/devs/analytics',      'icon' => 'bar_chart',          'label' => 'Аналитика',    'group' => 'Аудитория', 'dock' => false],
    ['id' => 'monetization',   'href' => '/devs/monetization',   'icon' => 'currency_ruble',     'label' => 'Монетизация',  'group' => 'Аудитория', 'dock' => false],

    ['id' => 'studio',         'href' => '/devs/mystudio',       'icon' => 'apartment',          'label' => 'Моя студия',   'group' => 'Студия', 'dock' => true],
    ['id' => 'staff',          'href' => '/devs/staff',          'icon' => 'groups',             'label' => 'Сотрудники',   'group' => 'Студия', 'dock' => true],
    ['id' => 'coop',           'href' => '/devs/coop',           'icon' => 'group_work',         'label' => 'Соучастники проектов', 'group' => 'Студия', 'dock' => false],
    ['id' => 'promotion',      'href' => '/devs/promotion',      'icon' => 'campaign',           'label' => 'Продвижение',  'group' => 'Студия', 'dock' => false],
    ['id' => 'select',         'href' => '/devs/select',         'icon' => 'swap_horiz',         'label' => 'Сменить студию', 'group' => 'Студия', 'dock' => false, 'top' => true],
];

if ($is_moder) {
    $apps[] = ['id' => 'recentorgs',       'href' => '/devs/recentorgs',       'icon' => 'domain_add',    'label' => 'Новые организации', 'group' => 'Администрирование', 'dock' => false, 'badge' => $sb['orgs']];
    $apps[] = ['id' => 'experts',          'href' => '/devs/experts',          'icon' => 'verified_user', 'label' => 'Эксперты',          'group' => 'Администрирование', 'dock' => false, 'badge' => $sb['experts']];
    $apps[] = ['id' => 'scan-reports',     'href' => '/devs/scan-reports',     'icon' => 'security',      'label' => 'Отчёты проверок',   'group' => 'Администрирование', 'dock' => false];
    $apps[] = ['id' => 'mobile_analytics', 'href' => '/devs/mobile_analytics', 'icon' => 'smartphone',    'label' => 'Аналитика: мобильная версия', 'group' => 'Администрирование', 'dock' => false];
    $apps[] = ['id' => 'assets-mod',       'href' => '/assetstore/manage.php', 'icon' => 'inventory_2',   'label' => 'Модерация ассетов', 'group' => 'Администрирование', 'dock' => false, 'top' => true, 'badge' => $sb['assets'] ?? 0];
    if ($is_admin) {
        $apps[] = ['id' => 'mon_review', 'href' => '/devs/mon_review', 'icon' => 'fact_check', 'label' => 'Монетизация: проверка анкет', 'group' => 'Администрирование', 'dock' => false, 'badge' => $sb['mon'] ?? 0];
        $apps[] = ['id' => 'giveach', 'href' => '/devs/giveach', 'icon' => 'military_tech', 'label' => 'Выдать достижение', 'group' => 'Администрирование', 'dock' => false];
        if (is_file(__DIR__ . '/../premium.php')) {
            $apps[] = ['id' => 'premium', 'href' => '/devs/premium', 'icon' => 'verified', 'label' => 'Dustore Premium', 'group' => 'Администрирование', 'dock' => false];
        }
        $apps[] = ['id' => 'notifications', 'href' => '/devs/notifications', 'icon' => 'campaign', 'label' => 'Уведомления', 'group' => 'Администрирование', 'dock' => false];
    }
}
foreach ($apps as &$_a) $_a['badge'] = (int)($_a['badge'] ?? 0);
unset($_a);

$ds_tokens = '
        :root {
            --p: #c32178;
            --pd: #9a1a5e;
            --pl: #ff5ba8;
            --dark: #14041d;
            --surf: #1a0a24;
            --elev: #241030;
            --tp: #ffffff;
            --ts: #b0b0c0;
            --tm: #5a5a6e;
            --tt: #f0e6ff;
            --ok: #00d68f;
            --warn: #ffaa00;
            --err: #ff3d71;
            --bd: rgba(255, 255, 255, .07);
            --r: 10px;
        }';

if (!$ds_embed) {
    require __DIR__ . '/shell.php';
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Dustore.Devs</title>
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        <?= $ds_tokens ?>html,
        body {
            height: 100%;
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--dark);
            color: var(--tp);
            overflow: hidden;
        }

        .ds-content {
            height: 100%;
            overflow-y: auto;
            padding: 20px;
        }

        .ds-content::-webkit-scrollbar {
            width: 6px;
        }

        .ds-content::-webkit-scrollbar-thumb {
            background: rgba(var(--brand-rgb, 195, 33, 120), .3);
            border-radius: 3px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--p);
            color: #fff;
            border: none;
            border-radius: var(--r);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            transition: background .15s;
        }

        .btn-primary:hover {
            background: var(--pd);
        }

        .btn-primary .material-icons {
            font-size: 16px;
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--r);
            background: var(--elev);
            border: 1px solid var(--bd);
            color: var(--ts);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .15s;
            font-size: 0;
            font-family: inherit;
        }

        .icon-btn:hover {
            background: rgba(var(--brand-rgb, 195, 33, 120), .15);
            color: var(--pl);
            border-color: rgba(var(--brand-rgb, 195, 33, 120), .3);
        }

        .icon-btn .material-icons {
            font-size: 18px;
        }


        /* ── Cards / shared UI ── */
        .card {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: var(--r);
            padding: 16px;
            margin-bottom: 0;
        }

        .card-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-title .material-icons {
            font-size: 16px;
            color: var(--p);
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            font-size: 11px;
            color: var(--tm);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            background: var(--elev);
            border: 1px solid var(--bd);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--tt);
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: border-color .15s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: rgba(var(--brand-rgb, 195, 33, 120), .5);
        }

        .field textarea {
            resize: vertical;
            min-height: 80px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .col-full {
            grid-column: 1/-1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--r);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
            text-decoration: none;
            transition: all .15s;
        }

        .btn-p {
            background: var(--p);
            color: #fff;
        }

        .btn-p:hover {
            background: var(--pd);
        }

        .btn-g {
            background: var(--elev);
            color: var(--ts);
            border: 1px solid var(--bd);
        }

        .btn-g:hover {
            color: #fff;
        }

        .btn-d {
            background: rgba(var(--brand-hi-rgb, 255, 61, 113), .12);
            color: var(--err);
            border: 1px solid rgba(var(--brand-hi-rgb, 255, 61, 113), .2);
        }

        .btn-d:hover {
            background: rgba(var(--brand-hi-rgb, 255, 61, 113), .2);
        }

        .btn .material-icons {
            font-size: 16px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--r);
            font-size: 13px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-ok {
            background: rgba(0, 214, 143, .08);
            border: 1px solid rgba(0, 214, 143, .2);
            color: var(--ok);
        }

        .alert-err {
            background: rgba(var(--brand-hi-rgb, 255, 61, 113), .08);
            border: 1px solid rgba(var(--brand-hi-rgb, 255, 61, 113), .2);
            color: var(--err);
        }

        .alert-warn {
            background: rgba(255, 170, 0, .08);
            border: 1px solid rgba(255, 170, 0, .2);
            color: var(--warn);
        }

        .badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-pub {
            background: rgba(0, 214, 143, .1);
            color: var(--ok);
        }

        .badge-draft {
            background: rgba(255, 255, 255, .07);
            color: var(--tm);
        }

        .badge-rev {
            background: rgba(255, 170, 0, .1);
            color: var(--warn);
        }

        .badge-err {
            background: rgba(var(--brand-hi-rgb, 255, 61, 113), .1);
            color: var(--err);
        }

        .sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .sec-title {
            font-size: 15px;
            font-weight: 700;
        }

        .sec-link {
            font-size: 12px;
            color: var(--p);
            text-decoration: none;
        }

        .sec-link:hover {
            text-decoration: underline;
        }

        .stat-card {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: var(--r);
            padding: 16px;
        }

        .stat-icon {
            font-size: 20px;
            color: var(--p);
            margin-bottom: 8px;
        }

        .stat-num {
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: var(--tm);
            margin-top: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }


        @media(max-width:860px) {
            .ds-content {
                padding: 16px;
            }

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }

            .col-full {
                grid-column: auto;
            }
        }
    </style>
    <link rel="stylesheet" href="/swad/css/scrollbar.css?v=1">
</head>

<body class="ds-embed">
    <main class="ds-content">