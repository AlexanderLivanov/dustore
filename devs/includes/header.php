<?php

/**
 * devs/includes/header.php — единый layout для всей консоли разработчика.
 * Переменные до подключения:
 *   $page_title  — заголовок страницы
 *   $active_nav  — id активного пункта меню
 */

// ── Session guard (не двойной session_start) ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../swad/config.php');
require_once(__DIR__ . '/../../swad/controllers/user.php');
require_once(__DIR__ . '/../../swad/controllers/organization.php');

$curr_user = new User();
$db        = new Database();
$org       = new Organization();

// ── Auth ─────────────────────────────────────────────────────────────────
if ($curr_user->checkAuth() > 0) {
    header('Location: /login?backUrl=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// ── Studio guard ──────────────────────────────────────────────────────────
if (empty($_SESSION['studio_id'])) {
    header('Location: /devs/select');
    exit();
}

$curr_user_data     = $_SESSION['USERDATA'];
$studio_id          = (int)$_SESSION['studio_id'];
$curr_user_org_data = $curr_user->getOrgData($studio_id);
$_SESSION['STUDIODATA'] = $curr_user_org_data;

$user_id      = (int)($curr_user_data["id"] ?? 0);
$tg_id        = (string)($curr_user_data["telegram_id"] ?? "");

// Роль из таблицы staff (не user_organization)
$_s = $db->connect()->prepare("SELECT role FROM staff WHERE telegram_id=? AND org_id=? LIMIT 1");
$_s->execute([$tg_id, $studio_id]);
$_sr = $_s->fetch(PDO::FETCH_ASSOC);
$role_name = $_sr ? ($_sr["role"] ?? "Участник") : "Участник";

$is_admin = ((int)($curr_user_data["global_role"] ?? 0)) === -1;
$is_moder = ($is_admin || in_array($role_name, ["Модератор", "Администратор"]));
$is_owner = ($is_admin || $role_name === "Владелец");

$studio_name    = htmlspecialchars($curr_user_org_data['name'] ?? 'Studio');
$studio_avatar  = $curr_user_org_data['avatar_link'] ?? '';
$studio_initials = mb_strtoupper(mb_substr($studio_name, 0, 2));

$page_title = $page_title ?? 'Консоль';
$active_nav = $active_nav ?? 'dashboard';

// ── Nav config ────────────────────────────────────────────────────────────
$nav_items = [
    ['id' => 'dashboard',    'href' => '/devs/',             'icon' => 'dashboard',         'label' => 'Обзор'],
    ['id' => 'projects',     'href' => '/devs/projects',     'icon' => 'videogame_asset',   'label' => 'Проекты'],
    ['id' => 'new',          'href' => '/devs/new',          'icon' => 'add_circle_outline', 'label' => 'Новый проект'],
    ['divider' => true, 'label' => 'Аудитория'],
    ['id' => 'replies',      'href' => '/devs/replies',      'icon' => 'rate_review',       'label' => 'Отзывы'],
    ['id' => 'expert_reviews', 'href' => '/devs/expert_reviews', 'icon' => 'workspace_premium', 'label' => 'Рецензии экспертов'],
    ['id' => 'analytics',   'href' => '/devs/analytics',    'icon' => 'bar_chart',         'label' => 'Аналитика'],
    ['id' => 'monetization', 'href' => '/devs/monetization', 'icon' => 'currency_ruble',    'label' => 'Монетизация'],
    ['divider' => true, 'label' => 'Студия'],
    ['id' => 'studio',       'href' => '/devs/mystudio',     'icon' => 'apartment',         'label' => 'Моя студия'],
    ['id' => 'staff',        'href' => '/devs/staff',        'icon' => 'groups',            'label' => 'Сотрудники'],
];
if ($is_moder) {
    $nav_items[] = ['divider' => true, 'label' => 'Администрирование'];
    if ($is_admin || $role_name === 'Модератор' || $role_name === 'Администратор') {
        $nav_items[] = ['id' => 'recentorgs', 'href' => '/devs/recentorgs', 'icon' => 'domain_add',  'label' => 'Новые организации'];
        $nav_items[] = ['id' => 'experts',    'href' => '/devs/experts',    'icon' => 'verified_user', 'label' => 'Эксперты'];
    }
    if ($is_admin) {
        $nav_items[] = ['id' => 'giveach', 'href' => '/devs/giveach', 'icon' => 'military_tech', 'label' => 'Выдать достижение'];
    }
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
            --ok: #00d68f;
            --warn: #ffaa00;
            --err: #ff3d71;
            --bd: rgba(255, 255, 255, 0.07);
            --sw: 260px;
            --hh: 58px;
            --r: 10px;
        }

        html,
        body {
            height: 100%;
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--dark);
            color: var(--tp);
            overflow: hidden;
        }

        .ds-layout {
            display: flex;
            height: 100vh;
        }

        /* ── Sidebar ── */
        .ds-sidebar {
            width: var(--sw);
            background: var(--surf);
            border-right: 1px solid var(--bd);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow-y: auto;
        }

        .ds-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .ds-sidebar::-webkit-scrollbar-thumb {
            background: var(--p);
            border-radius: 2px;
        }

        .sb-logo {
            padding: 16px 18px;
            border-bottom: 1px solid var(--bd);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .sb-logo-icon {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--p), var(--pl));
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        .sb-logo-name {
            font-size: 14px;
            font-weight: 700;
        }

        .sb-logo-sub {
            font-size: 10px;
            color: var(--tm);
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .sb-studio {
            padding: 12px 14px;
            border-bottom: 1px solid var(--bd);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .sb-ava {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), #7a155d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            overflow: hidden;
        }

        .sb-ava img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sb-sname {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sb-role {
            font-size: 10px;
            color: var(--p);
            background: rgba(195, 33, 120, .15);
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 2px;
        }

        .sb-nav {
            padding: 8px;
            flex: 1;
        }

        .sb-section {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--tm);
            padding: 10px 10px 4px;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: var(--r);
            color: var(--ts);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all .15s;
            position: relative;
            margin: 1px 0;
        }

        .nav-item:hover {
            background: var(--elev);
            color: #fff;
        }

        .nav-item.active {
            background: rgba(195, 33, 120, .14);
            color: var(--pl);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 25%;
            bottom: 25%;
            width: 3px;
            background: var(--p);
            border-radius: 0 3px 3px 0;
        }

        .nav-icon {
            font-size: 18px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .sb-user-block {
            padding: 10px;
            border-top: 1px solid var(--bd);
            flex-shrink: 0;
        }

        .sb-user {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: var(--r);
            cursor: pointer;
            transition: background .15s;
            text-decoration: none;
        }

        .sb-user:hover {
            background: var(--elev);
        }

        .sb-uava {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #533, #a33);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            overflow: hidden;
        }

        .sb-uava img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sb-uname {
            font-size: 12px;
            font-weight: 500;
            color: #fff;
        }

        .sb-uemail {
            font-size: 10px;
            color: var(--tm);
        }

        /* ── Main ── */
        .ds-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
        }

        .ds-topbar {
            height: var(--hh);
            background: rgba(26, 10, 36, .85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--bd);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            z-index: 10;
        }

        .topbar-title {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--pl));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--elev);
            border: 1px solid var(--bd);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--ts);
            transition: all .15s;
            text-decoration: none;
        }

        .icon-btn:hover {
            background: var(--p);
            color: #fff;
        }

        .icon-btn .material-icons {
            font-size: 17px;
        }

        .btn-primary {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--p);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background .15s;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--pd);
        }

        .btn-primary .material-icons {
            font-size: 16px;
        }

        .ds-content {
            flex: 1;
            overflow-y: auto;
            padding: 24px 28px;
            background: linear-gradient(135deg, var(--dark) 0%, #1e0830 100%);
        }

        .ds-content::-webkit-scrollbar {
            width: 5px;
        }

        .ds-content::-webkit-scrollbar-thumb {
            background: var(--p);
            border-radius: 3px;
        }

        /* ── Components ── */
        .card {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 20px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title .material-icons {
            font-size: 17px;
            color: var(--p);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 18px;
            position: relative;
            overflow: hidden;
            transition: all .2s;
        }

        .stat-card:hover {
            border-color: rgba(195, 33, 120, .3);
            transform: translateY(-2px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--p), var(--pl));
        }

        .stat-num {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--ts);
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-sub {
            font-size: 11px;
            color: var(--tm);
            margin-top: 6px;
        }

        .stat-icon {
            position: absolute;
            right: 14px;
            top: 14px;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: rgba(195, 33, 120, .12);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon .material-icons {
            font-size: 17px;
            color: var(--p);
        }

        .sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .sec-title {
            font-size: 15px;
            font-weight: 600;
        }

        .sec-link {
            font-size: 12px;
            color: var(--p);
            text-decoration: none;
            transition: color .15s;
        }

        .sec-link:hover {
            color: var(--pl);
        }

        .badge {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .badge-pub {
            background: rgba(0, 214, 143, .12);
            color: var(--ok);
        }

        .badge-draft {
            background: rgba(255, 255, 255, .08);
            color: var(--tm);
        }

        .badge-rev {
            background: rgba(255, 170, 0, .12);
            color: var(--warn);
        }

        .badge-err {
            background: rgba(255, 61, 113, .12);
            color: var(--err);
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: 11px;
            color: var(--ts);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            background: var(--elev);
            border: 1px solid var(--bd);
            border-radius: 8px;
            padding: 9px 12px;
            color: #fff;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color .15s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--p);
        }

        .field select option {
            background: var(--elev);
        }

        .field textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all .15s;
            text-decoration: none;
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
            background: var(--surf);
            color: #fff;
        }

        .btn-d {
            background: rgba(255, 61, 113, .1);
            color: var(--err);
        }

        .btn-d:hover {
            background: rgba(255, 61, 113, .2);
        }

        .btn .material-icons {
            font-size: 16px;
        }

        .upload-zone {
            border: 2px dashed rgba(195, 33, 120, .3);
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
        }

        .upload-zone:hover {
            border-color: var(--p);
            background: rgba(195, 33, 120, .04);
        }

        .upload-zone .material-icons {
            font-size: 32px;
            color: var(--p);
            display: block;
            margin-bottom: 8px;
        }

        .upload-zone p {
            font-size: 13px;
            color: var(--ts);
            margin: 0;
        }

        .upload-zone small {
            font-size: 11px;
            color: var(--tm);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-ok {
            background: rgba(0, 214, 143, .1);
            border: 1px solid rgba(0, 214, 143, .2);
            color: var(--ok);
        }

        .alert-warn {
            background: rgba(255, 170, 0, .1);
            border: 1px solid rgba(255, 170, 0, .2);
            color: var(--warn);
        }

        .alert-err {
            background: rgba(255, 61, 113, .1);
            border: 1px solid rgba(255, 61, 113, .2);
            color: var(--err);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .col-full {
            grid-column: 1/-1;
        }

        .menu-toggle {
            display: none;
        }

        .sb-overlay {
            display: none;
        }

        @media(max-width:860px) {
            .ds-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 200;
                transform: translateX(-100%);
                transition: transform .25s;
            }

            .ds-sidebar.open {
                transform: translateX(0);
            }

            .sb-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .5);
                z-index: 199;
                opacity: 0;
                pointer-events: none;
                transition: opacity .25s;
            }

            .sb-overlay.open {
                opacity: 1;
                pointer-events: all;
            }

            .menu-toggle {
                display: flex;
            }

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
</head>

<body>
    <div class="ds-layout">
        <aside class="ds-sidebar" id="sidebar">
            <div class="sb-logo">
                <div class="sb-logo-icon">D</div>
                <div>
                    <div class="sb-logo-name">Dustore.Devs</div>
                    <div class="sb-logo-sub">Developer Console</div>
                </div>
            </div>
            <div class="sb-studio">
                <div class="sb-ava">
                    <?php if ($studio_avatar): ?><img src="<?= htmlspecialchars($studio_avatar) ?>" alt="">
                        <?php else: ?><?= $studio_initials ?><?php endif; ?>
                </div>
                <div style="min-width:0;">
                    <div class="sb-sname"><?= $studio_name ?></div>
                    <div class="sb-role"><?= htmlspecialchars($role_name) ?></div>
                </div>
            </div>
            <nav class="sb-nav">
                <?php foreach ($nav_items as $item): ?>
                    <?php if (!empty($item['divider'])): ?>
                        <div class="sb-section"><?= $item['label'] ?></div>
                    <?php else: ?>
                        <a href="<?= $item['href'] ?>" class="nav-item <?= $active_nav === $item['id'] ? 'active' : '' ?>">
                            <span class="nav-icon material-icons"><?= $item['icon'] ?></span><?= $item['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="sb-user-block">
                <a href="/me" class="sb-user">
                    <div class="sb-uava">
                        <?php if (!empty($curr_user_data['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($curr_user_data['profile_picture']) ?>" alt="">
                            <?php else: ?><?= mb_strtoupper(mb_substr($curr_user_data['username'] ?? 'U', 0, 2)) ?><?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="sb-uname"><?= htmlspecialchars($curr_user_data['username'] ?? '') ?></div>
                        <div class="sb-uemail">← Назад в профиль</div>
                    </div>
                    <span class="material-icons" style="margin-left:auto;font-size:15px;color:var(--tm);">logout</span>
                </a>
            </div>
        </aside>
        <div class="sb-overlay" id="sb-overlay" onclick="closeSidebar()"></div>
        <div class="ds-main">
            <header class="ds-topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="icon-btn menu-toggle" onclick="openSidebar()"><span class="material-icons">menu</span></button>
                    <div class="topbar-title"><?= $page_title ?></div>
                </div>
                <div class="topbar-right">
                    <a href="/devs/new" class="btn-primary"><span class="material-icons">add</span>Новый проект</a>
                </div>
            </header>
            <main class="ds-content">