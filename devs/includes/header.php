<?php
/**
 * devs/includes/header.php — единый layout консоли разработчика.
 * Переменные до подключения:
 *   $page_title  — заголовок страницы
 *   $active_nav  — id активного пункта меню
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../swad/config.php');
require_once(__DIR__ . '/../../swad/controllers/user.php');
require_once(__DIR__ . '/../../swad/controllers/organization.php');

$curr_user = new User();
$db        = new Database();
$org       = new Organization();

if ($curr_user->checkAuth() > 0) {
    header('Location: /login?backUrl=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

if (empty($_SESSION['studio_id'])) {
    header('Location: /devs/select');
    exit();
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

// ── Счётчики сайдбара (кеш 5 минут в сессии) ─────────────────────────────
$_ck    = 'sb_cnt_' . $studio_id;
$_ck_ts = $_ck . '_ts';
if (empty($_SESSION[$_ck]) || (time() - ($_SESSION[$_ck_ts] ?? 0)) > 300) {
    $_pdo = $db->connect();

    // IDs игр студии
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

    // Непрочитанные события
    if ($is_moder) {
        $sb_events = (int)$_pdo->query("SELECT COUNT(*) FROM platform_events WHERE is_read=0")->fetchColumn();
        $sb_orgs   = (int)$_pdo->query("SELECT COUNT(*) FROM studios WHERE status='pending'")->fetchColumn();
        $sb_experts= (int)$_pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
    } else {
        $_ev = $_pdo->prepare("SELECT COUNT(*) FROM platform_events WHERE studio_id=? AND is_read=0");
        $_ev->execute([$studio_id]);
        $sb_events  = (int)$_ev->fetchColumn();
        $sb_orgs    = 0;
        $sb_experts = 0;
    }

    $_SESSION[$_ck] = [
        'reviews' => $sb_reviews,
        'expert'  => $sb_expert,
        'events'  => $sb_events,
        'orgs'    => $sb_orgs,
        'experts' => $sb_experts,
    ];
    $_SESSION[$_ck_ts] = time();
}
$sb = $_SESSION[$_ck];

// ── Nav ───────────────────────────────────────────────────────────────────
$nav_items = [
    ['id' => 'dashboard',  'href' => '/devs/',          'icon' => 'dashboard',          'label' => 'Обзор'],
    ['id' => 'projects',   'href' => '/devs/projects',  'icon' => 'videogame_asset',    'label' => 'Проекты'],
    ['id' => 'new',        'href' => '/devs/new',       'icon' => 'add_circle_outline', 'label' => 'Новый проект'],
    ['id' => 'events',     'href' => '/devs/events',    'icon' => 'notifications',      'label' => 'События',
     'badge' => $sb['events']],
    ['id' => 'wishlists',  'href' => '/devs/wishlists', 'icon' => 'favorite',           'label' => 'Вишлисты'],
    ['divider' => true, 'label' => 'Аудитория'],
    ['id' => 'replies',    'href' => '/devs/replies',   'icon' => 'rate_review',        'label' => 'Отзывы',
     'badge' => $sb['reviews']],
    ['id' => 'expert_reviews', 'href' => '/devs/expert_reviews', 'icon' => 'workspace_premium', 'label' => 'Рецензии',
     'badge' => $sb['expert']],
    ['id' => 'analytics',  'href' => '/devs/analytics', 'icon' => 'bar_chart',          'label' => 'Аналитика'],
    ['id' => 'monetization','href'=> '/devs/monetization','icon'=> 'currency_ruble',    'label' => 'Монетизация'],
    ['divider' => true, 'label' => 'Студия'],
    ['id' => 'studio',     'href' => '/devs/mystudio',  'icon' => 'apartment',          'label' => 'Моя студия'],
    ['id' => 'staff',      'href' => '/devs/staff',     'icon' => 'groups',             'label' => 'Сотрудники'],
    ['id' => 'select',     'href' => '/devs/select',    'icon' => 'swap_horiz',         'label' => 'Сменить студию'],
];

if ($is_moder) {
    $nav_items[] = ['divider' => true, 'label' => 'Администрирование'];
    $nav_items[] = ['id' => 'recentorgs', 'href' => '/devs/recentorgs', 'icon' => 'domain_add',   'label' => 'Новые организации', 'badge' => $sb['orgs']];
    $nav_items[] = ['id' => 'experts',    'href' => '/devs/experts',    'icon' => 'verified_user', 'label' => 'Эксперты',          'badge' => $sb['experts']];
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
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --p:    #c32178;
            --pd:   #9a1a5e;
            --pl:   #ff5ba8;
            --dark: #14041d;
            --surf: #1a0a24;
            --elev: #241030;
            --tp:   #ffffff;
            --ts:   #b0b0c0;
            --tm:   #5a5a6e;
            --tt:   #f0e6ff;
            --ok:   #00d68f;
            --warn: #ffaa00;
            --err:  #ff3d71;
            --bd:   rgba(255,255,255,.07);
            --sw:   260px;
            --hh:   58px;
            --r:    10px;
        }

        html, body { height: 100%; font-family: 'Inter', -apple-system, sans-serif; background: var(--dark); color: var(--tp); overflow: hidden; }

        .ds-layout { display: flex; height: 100vh; }

        /* ── Sidebar ── */
        .ds-sidebar {
            width: var(--sw); background: var(--surf);
            border-right: 1px solid var(--bd);
            display: flex; flex-direction: column; flex-shrink: 0; overflow-y: auto;
        }
        .ds-sidebar::-webkit-scrollbar { width: 4px; }
        .ds-sidebar::-webkit-scrollbar-thumb { background: var(--p); border-radius: 2px; }

        .sb-logo {
            padding: 16px 18px; border-bottom: 1px solid var(--bd);
            display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .sb-logo-icon {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--p), var(--pl));
            border-radius: 9px; display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; color: #fff;
        }
        .sb-logo-name { font-size: 14px; font-weight: 700; }
        .sb-logo-sub  { font-size: 10px; color: var(--tm); letter-spacing: .5px; text-transform: uppercase; }

        .sb-studio {
            padding: 12px 14px; border-bottom: 1px solid var(--bd);
            display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .sb-ava {
            width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), #7a155d);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; overflow: hidden;
        }
        .sb-ava img { width: 100%; height: 100%; object-fit: cover; }
        .sb-sname { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-role  { font-size: 10px; color: var(--p); background: rgba(195,33,120,.15); padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; }

        .sb-nav { padding: 8px; flex: 1; }
        .sb-section { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--tm); padding: 10px 10px 4px; font-weight: 600; }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: var(--r);
            color: var(--ts); text-decoration: none; font-size: 13px; font-weight: 500;
            transition: all .15s; position: relative; margin: 1px 0;
        }
        .nav-item:hover  { background: var(--elev); color: #fff; }
        .nav-item.active { background: rgba(195,33,120,.14); color: var(--pl); }
        .nav-item.active::before {
            content: ''; position: absolute; left: 0; top: 25%; bottom: 25%;
            width: 3px; background: var(--p); border-radius: 0 3px 3px 0;
        }
        .nav-icon { font-size: 18px; width: 20px; text-align: center; flex-shrink: 0; }

        .sb-badge {
            margin-left: auto; background: var(--p); color: #fff;
            font-size: 10px; font-weight: 700; border-radius: 10px;
            padding: 1px 6px; min-width: 18px; text-align: center;
            line-height: 1.6; flex-shrink: 0;
            animation: badge-pop .3s ease;
        }
        @keyframes badge-pop { 0%{transform:scale(0);} 70%{transform:scale(1.2);} 100%{transform:scale(1);} }

        .sb-user-block { padding: 10px; border-top: 1px solid var(--bd); flex-shrink: 0; }
        .sb-user {
            display: flex; align-items: center; gap: 9px; padding: 8px 10px;
            border-radius: var(--r); cursor: pointer; transition: background .15s; text-decoration: none;
        }
        .sb-user:hover { background: var(--elev); }
        .sb-uava {
            width: 28px; height: 28px; border-radius: 50%; background: var(--elev);
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700; flex-shrink: 0;
        }
        .sb-uava img { width: 100%; height: 100%; object-fit: cover; }
        .sb-uname  { font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-uemail { font-size: 10px; color: var(--tm); }

        /* ── Main ── */
        .ds-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

        .ds-topbar {
            height: var(--hh); flex-shrink: 0; background: var(--surf);
            border-bottom: 1px solid var(--bd); padding: 0 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .topbar-title { font-size: 15px; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 8px; }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; background: var(--p); color: #fff;
            border: none; border-radius: var(--r); font-size: 13px; font-weight: 600;
            cursor: pointer; text-decoration: none; font-family: inherit; transition: background .15s;
        }
        .btn-primary:hover { background: var(--pd); }
        .btn-primary .material-icons { font-size: 16px; }

        .icon-btn {
            width: 36px; height: 36px; border-radius: var(--r);
            background: var(--elev); border: 1px solid var(--bd); color: var(--ts);
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            transition: all .15s; font-size: 0; font-family: inherit;
        }
        .icon-btn:hover { background: rgba(195,33,120,.15); color: var(--pl); border-color: rgba(195,33,120,.3); }
        .icon-btn .material-icons { font-size: 18px; }

        .menu-toggle { display: none; }

        .ds-content { flex: 1; overflow-y: auto; padding: 20px; }
        .ds-content::-webkit-scrollbar { width: 6px; }
        .ds-content::-webkit-scrollbar-thumb { background: rgba(195,33,120,.3); border-radius: 3px; }

        /* ── Cards / shared UI ── */
        .card {
            background: var(--surf); border: 1px solid var(--bd);
            border-radius: var(--r); padding: 16px; margin-bottom: 0;
        }
        .card-title {
            font-size: 13px; font-weight: 600; margin-bottom: 14px;
            display: flex; align-items: center; gap: 6px;
        }
        .card-title .material-icons { font-size: 16px; color: var(--p); }

        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 11px; color: var(--tm); margin-bottom: 4px; font-weight: 500; }
        .field input, .field select, .field textarea {
            width: 100%; background: var(--elev); border: 1px solid var(--bd);
            border-radius: 8px; padding: 8px 12px; color: var(--tt); font-size: 13px;
            font-family: inherit; outline: none; transition: border-color .15s;
        }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: rgba(195,33,120,.5); }
        .field textarea { resize: vertical; min-height: 80px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .col-full { grid-column: 1/-1; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--r); font-size: 13px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; text-decoration: none; transition: all .15s; }
        .btn-p   { background: var(--p); color: #fff; }
        .btn-p:hover { background: var(--pd); }
        .btn-g   { background: var(--elev); color: var(--ts); border: 1px solid var(--bd); }
        .btn-g:hover { color: #fff; }
        .btn-d   { background: rgba(255,61,113,.12); color: var(--err); border: 1px solid rgba(255,61,113,.2); }
        .btn-d:hover { background: rgba(255,61,113,.2); }
        .btn .material-icons { font-size: 16px; }

        .alert { padding: 12px 16px; border-radius: var(--r); font-size: 13px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-ok  { background: rgba(0,214,143,.08);  border: 1px solid rgba(0,214,143,.2);  color: var(--ok); }
        .alert-err { background: rgba(255,61,113,.08); border: 1px solid rgba(255,61,113,.2); color: var(--err); }
        .alert-warn{ background: rgba(255,170,0,.08);  border: 1px solid rgba(255,170,0,.2);  color: var(--warn); }

        .badge { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-pub  { background: rgba(0,214,143,.1);  color: var(--ok); }
        .badge-draft{ background: rgba(255,255,255,.07); color: var(--tm); }
        .badge-rev  { background: rgba(255,170,0,.1);  color: var(--warn); }
        .badge-err  { background: rgba(255,61,113,.1); color: var(--err); }

        .sec-head  { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .sec-title { font-size: 15px; font-weight: 700; }
        .sec-link  { font-size: 12px; color: var(--p); text-decoration: none; }
        .sec-link:hover { text-decoration: underline; }

        .stat-card { background: var(--surf); border: 1px solid var(--bd); border-radius: var(--r); padding: 16px; }
        .stat-icon { font-size: 20px; color: var(--p); margin-bottom: 8px; }
        .stat-num  { font-size: 24px; font-weight: 700; line-height: 1; }
        .stat-label{ font-size: 11px; color: var(--tm); margin-top: 4px; }
        .stats-grid{ display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }

        /* ── Overlay sidebar mobile ── */
        .sb-overlay { display: none; }

        @media(max-width:860px) {
            .ds-sidebar {
                position: fixed; left: 0; top: 0; bottom: 0; z-index: 200;
                transform: translateX(-100%); transition: transform .25s;
            }
            .ds-sidebar.open { transform: translateX(0); }
            .sb-overlay {
                display: block; position: fixed; inset: 0; background: rgba(0,0,0,.5);
                z-index: 199; opacity: 0; pointer-events: none; transition: opacity .25s;
            }
            .sb-overlay.open { opacity: 1; pointer-events: all; }
            .menu-toggle { display: flex; }
            .ds-content { padding: 16px; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .col-full { grid-column: auto; }
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
                <?php if ($studio_avatar): ?>
                    <img src="<?= htmlspecialchars($studio_avatar) ?>" alt="">
                <?php else: ?>
                    <?= $studio_initials ?>
                <?php endif; ?>
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
                        <span class="nav-icon material-icons"><?= $item['icon'] ?></span>
                        <span style="flex:1;"><?= $item['label'] ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="sb-badge"><?= min((int)$item['badge'], 99) ?><?= $item['badge'] > 99 ? '+' : '' ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sb-user-block">
            <a href="/me" class="sb-user">
                <div class="sb-uava">
                    <?php if (!empty($curr_user_data['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($curr_user_data['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <?= mb_strtoupper(mb_substr($curr_user_data['username'] ?? 'U', 0, 2)) ?>
                    <?php endif; ?>
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
                <button class="icon-btn menu-toggle" onclick="openSidebar()">
                    <span class="material-icons">menu</span>
                </button>
                <div class="topbar-title"><?= $page_title ?></div>
            </div>
            <div class="topbar-right">
                <a href="/devs/new" class="btn-primary">
                    <span class="material-icons">add</span>Новый проект
                </a>
            </div>
        </header>
        <main class="ds-content">