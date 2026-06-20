<?php
// ========== НАЧАЛО: ЛОГИКА БЕЗ ВЫВОДА ==========
require_once('../swad/config.php');
session_start();

$db = (new Database())->connect();
if (!$db) die('Ошибка подключения к БД');

$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) {
    header('Location: /login');
    exit;
}

$sprint_id = (int)($_GET['id'] ?? 0);
if (!$sprint_id && isset($_SESSION['last_admin_sprint'])) {
    $sprint_id = (int)$_SESSION['last_admin_sprint'];
}

// Если ID не передан – показываем список спринтов пользователя
if (!$sprint_id) {
    $sprintListStmt = $db->prepare("SELECT id, title, status, start_at FROM sprints WHERE host_user_id = ? ORDER BY created_at DESC");
    $sprintListStmt->execute([$userId]);
    $userSprints = $sprintListStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($userSprints)) {
        echo "<h2>У вас нет созданных спринтов</h2><a href='/jams'>Вернуться к списку спринтов</a>";
        exit;
    }
    // Вывод страницы выбора (оставлен без изменений)
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head><meta charset="UTF-8"><title>Выбор спринта</title><style>body{background:#0d0414;color:#e8ddf0;font-family:monospace;padding:40px}.sprint-item{background:rgba(0,0,0,.3);border:1px solid #c32178;border-radius:12px;padding:16px;margin-bottom:12px}.btn{background:#c32178;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none}</style></head>
    <body><div style="max-width:600px;margin:0 auto"><h1>🎮 Выберите спринт</h1>
    <?php foreach ($userSprints as $s): ?>
        <div class="sprint-item">
            <div><strong><?= htmlspecialchars($s['title']) ?></strong> (<?= $s['status'] ?>)<br><small>Старт: <?= date('d.m.Y H:i', strtotime($s['start_at'])) ?></small></div>
            <a href="admin.php?id=<?= $s['id'] ?>" class="btn">Управлять</a>
        </div>
    <?php endforeach; ?>
    <a href="/jams" style="color:#c32178">← Назад к списку спринтов</a>
    </div></body></html>
    <?php
    exit;
}

// Проверка прав (только хост)
$check = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
$check->execute([$sprint_id]);
if ($check->fetchColumn() != $userId) {
    die('У вас нет прав на управление этим спринтом');
}

$_SESSION['last_admin_sprint'] = $sprint_id;

// Загружаем данные спринта
$sprintStmt = $db->prepare("SELECT * FROM sprints WHERE id = ?");
$sprintStmt->execute([$sprint_id]);
$sprint = $sprintStmt->fetch(PDO::FETCH_ASSOC);

// Участники, работы, объявления, эксперты, призы (без изменений)
$partStmt = $db->prepare("
    SELECT
        u.id,
        u.username,
        u.role,
        sp.joined_at,
        ss.title,
        ss.build_url,
        ss.build_size
    FROM sprint_participants sp
    JOIN users u
        ON sp.user_id = u.id
    LEFT JOIN sprint_submissions ss
        ON ss.sprint_id = sp.sprint_id
       AND ss.user_id = sp.user_id
    WHERE sp.sprint_id = ?
    ORDER BY sp.joined_at DESC
");
$partStmt->execute([$sprint_id]);
$participants = $partStmt->fetchAll(PDO::FETCH_ASSOC);

$subStmt = $db->prepare("
    SELECT s.*, u.username as user_name, u.role
    FROM sprint_submissions s
    JOIN users u ON s.user_id = u.id
    WHERE s.sprint_id = ?
    ORDER BY s.submitted_at DESC
");
$subStmt->execute([$sprint_id]);
$submissions = $subStmt->fetchAll(PDO::FETCH_ASSOC);

$annStmt = $db->prepare("SELECT * FROM sprint_announcements WHERE sprint_id = ? ORDER BY created_at DESC");
$annStmt->execute([$sprint_id]);
$announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);

$expStmt = $db->prepare("
    SELECT u.id, u.username, u.role
    FROM sprint_experts se
    JOIN users u ON se.user_id = u.id
    WHERE se.sprint_id = ?
");
$expStmt->execute([$sprint_id]);
$experts = $expStmt->fetchAll(PDO::FETCH_ASSOC);

$prizeStmt = $db->prepare("SELECT * FROM sprint_prizes WHERE sprint_id = ? ORDER BY place_num");
$prizeStmt->execute([$sprint_id]);
$prizes = $prizeStmt->fetchAll(PDO::FETCH_ASSOC);

$totalParticipants = count($participants);
$totalSubmissions = count($submissions);
$newTodayStmt = $db->prepare("SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = ? AND DATE(joined_at) = CURDATE()");
$newTodayStmt->execute([$sprint_id]);
$newToday = $newTodayStmt->fetchColumn();

// Данные для графика (без изменений)
$chartData = [];
$chartLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayStmt = $db->prepare("SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = ? AND DATE(joined_at) = ?");
    $dayStmt->execute([$sprint_id, $date]);
    $chartData[] = (int)$dayStmt->fetchColumn();
    $chartLabels[] = date('d.m', strtotime($date));
}
$hourlyData = array_fill(0, 24, 0);
$hourlyLabels = range(0, 23);

// ========== ПОДКЛЮЧАЕМ header.php ==========
require_once('../swad/static/elements/header.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($sprint['title']) ?> — Админка</title>
    <style>
        /* ---- Стили (сокращены, основные классы сохранены) ---- */
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #0d0414; font-family: 'Manrope', system-ui, sans-serif; color: #e8ddf0; background-image: radial-gradient(ellipse 80% 50% at 20% -10%, rgba(195,33,120,.1) 0%, transparent 60%), radial-gradient(ellipse 60% 40% at 80% 110%, rgba(120,20,80,.08) 0%, transparent 55%); }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(195,33,120,.3); border-radius: 4px; }
        .sprint-header { padding: 13px 26px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 0; backdrop-filter: blur(12px); }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: #e8ddf0; }
        .logo .brand { color: #c32178; }
        .nav-btn { padding: 7px 15px; border-radius: 7px; border: none; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.05); color: rgba(255,255,255,.5); transition: .15s; text-decoration: none; display: inline-block; }
        .nav-btn:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .badge-live { background: rgba(34,197,94,.12); color: #22c55e; border: 1px solid rgba(34,197,94,.25); border-radius: 20px; padding: 2px 10px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-live::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .admin-layout { display: flex; min-height: calc(100vh - 54px); }
        .sidebar { width: 220px; flex-shrink: 0; background: rgba(0,0,0,.25); border-right: 1px solid rgba(255,255,255,.07); padding: 20px 12px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-section { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.25); text-transform: uppercase; letter-spacing: .08em; padding: 10px 10px 5px; margin-top: 6px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: rgba(255,255,255,.45); transition: .15s; border: 1px solid transparent; }
        .sidebar-item:hover { background: rgba(255,255,255,.05); color: #e8ddf0; }
        .sidebar-item.active { background: rgba(195,33,120,.12); color: #e8ddf0; border-color: rgba(195,33,120,.25); }
        .sidebar-item .ico { font-size: 16px; width: 20px; text-align: center; }
        .sidebar-badge { margin-left: auto; background: rgba(195,33,120,.25); color: #e8ddf0; border-radius: 10px; padding: 1px 7px; font-size: 10px; font-weight: 700; }
        .main-content { flex: 1; overflow-y: auto; padding: 26px 28px; max-height: calc(100vh - 54px); }
        .view { display: none; }
        .view.active { display: block; }
        .page-title { font-size: 20px; font-weight: 800; margin-bottom: 4px; letter-spacing: -.3px; }
        .page-sub { color: rgba(255,255,255,.35); font-size: 13px; margin-bottom: 24px; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 16px; }
        .stat-card .sc-ico { font-size: 22px; margin-bottom: 8px; }
        .stat-card .sc-val { font-size: 22px; font-weight: 800; margin-bottom: 2px; }
        .stat-card .sc-lbl { font-size: 11px; color: rgba(255,255,255,.35); }
        .stat-card .sc-delta { font-size: 11px; margin-top: 4px; }
        .sc-delta.up { color: #22c55e; }
        .sc-delta.down { color: #f87171; }
        .chart-wrap { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 18px; margin-bottom: 18px; }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .chart-title { font-size: 14px; font-weight: 700; }
        .chart-tabs { display: flex; gap: 4px; }
        .chart-tab { padding: 5px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 11px; font-weight: 600; background: rgba(255,255,255,.05); color: rgba(255,255,255,.4); }
        .chart-tab.active { background: rgba(195,33,120,.18); color: #e8ddf0; border: 1px solid rgba(195,33,120,.3); }
        .chart-area { height: 160px; position: relative; overflow: hidden; }
        .chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 100%; padding-top: 10px; }
        .chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .chart-bar { width: 100%; border-radius: 4px 4px 0 0; background: linear-gradient(180deg, rgba(195,33,120,.7), rgba(195,33,120,.25)); transition: height .6s cubic-bezier(.4,0,.2,1); min-height: 4px; }
        .chart-bar:hover { background: linear-gradient(180deg, rgba(195,33,120,1), rgba(195,33,120,.4)); }
        .chart-bar::after { content: attr(data-val); position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 9px; color: rgba(255,255,255,.4); white-space: nowrap; }
        .chart-lbl { font-size: 9px; color: rgba(255,255,255,.25); text-align: center; }
        .chart-grid { position: absolute; inset: 0; pointer-events: none; display: flex; flex-direction: column; justify-content: space-between; padding-bottom: 20px; }
        .chart-gridline { border-top: 1px solid rgba(255,255,255,.05); width: 100%; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
        .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 18px; }
        .list-card { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 16px; }
        .list-card-title { font-size: 13px; font-weight: 700; margin-bottom: 12px; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
        .list-item:last-child { border-bottom: none; padding-bottom: 0; }
        .li-rank { font-size: 14px; width: 20px; text-align: center; flex-shrink: 0; }
        .li-info { flex: 1; min-width: 0; }
        .li-name { font-size: 13px; font-weight: 600; color: #e8ddf0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .li-sub { font-size: 11px; color: rgba(255,255,255,.3); }
        .li-val { font-size: 13px; font-weight: 700; color: #c32178; flex-shrink: 0; }
        .table-wrap { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; overflow: hidden; }
        .table-toolbar { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,.07); display: flex; gap: 10px; align-items: center; }
        .tbl-search { flex: 1; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); border-radius: 7px; padding: 7px 12px; color: #e8ddf0; font-size: 12px; outline: none; }
        .tbl-search:focus { border-color: #c32178; }
        .tbl-btn { padding: 7px 14px; border-radius: 7px; border: 1px solid rgba(195,33,120,.3); background: rgba(195,33,120,.1); color: #e8ddf0; cursor: pointer; font-size: 12px; font-weight: 600; }
        .tbl-btn:hover { background: rgba(195,33,120,.2); }
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 700; color: rgba(255,255,255,.3); text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,.07); }
        tbody tr { border-bottom: 1px solid rgba(255,255,255,.04); transition: .15s; cursor: pointer; }
        tbody tr:hover { background: rgba(195,33,120,.05); }
        tbody td { padding: 11px 14px; font-size: 12px; color: rgba(255,255,255,.7); }
        td .td-name { color: #e8ddf0; font-weight: 600; font-size: 13px; }
        td .td-sub { color: rgba(255,255,255,.3); font-size: 11px; }
        .status-pill { border-radius: 20px; padding: 2px 9px; font-size: 10px; font-weight: 700; display: inline-block; }
        .pill-green { background: rgba(34,197,94,.1); color: #22c55e; border: 1px solid rgba(34,197,94,.2); }
        .pill-yellow { background: rgba(245,158,11,.1); color: #f59e0b; border: 1px solid rgba(245,158,11,.2); }
        .pill-gray { background: rgba(255,255,255,.05); color: rgba(255,255,255,.3); border: 1px solid rgba(255,255,255,.1); }
        .pill-pink { background: rgba(195,33,120,.1); color: #d946a8; border: 1px solid rgba(195,33,120,.25); }
        .settings-section { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 20px; margin-bottom: 14px; }
        .settings-section-title { font-size: 13px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,.07); }
        .form-row-s { margin-bottom: 14px; }
        .form-label-s { display: block; color: rgba(255,255,255,.4); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-input-s { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 9px 13px; color: #e8ddf0; font-size: 13px; outline: none; }
        .form-input-s:focus { border-color: #c32178; }
        .logo-preview { margin-top: 10px; display: flex; align-items: center; gap: 12px; }
        .logo-preview img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .btn-sm { background: rgba(195,33,120,.1); border: 1px solid rgba(195,33,120,.25); color: #e8ddf0; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 11px; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
        .toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
        .toggle-info .ti-title { font-size: 13px; font-weight: 600; color: #e8ddf0; }
        .toggle-info .ti-desc { font-size: 11px; color: rgba(255,255,255,.3); margin-top: 2px; }
        .toggle { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: rgba(255,255,255,.1); border-radius: 22px; cursor: pointer; transition: .2s; }
        .toggle-slider::before { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #fff; left: 3px; top: 3px; transition: .2s; }
        .toggle input:checked + .toggle-slider { background: #c32178; }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }
        .btn-save { background: #c32178; border: none; color: #fff; border-radius: 8px; padding: 10px 22px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .btn-save:hover { background: #9e1a66; }
        .btn-danger { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #f87171; border-radius: 8px; padding: 10px 22px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .btn-danger:hover { background: rgba(239,68,68,.2); }
        .feed-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
        .feed-item:last-child { border-bottom: none; }
        .feed-ico { font-size: 18px; flex-shrink: 0; width: 24px; text-align: center; margin-top: 1px; }
        .feed-text { font-size: 12px; color: rgba(255,255,255,.6); line-height: 1.5; }
        .feed-text strong { color: #e8ddf0; }
        .feed-time { font-size: 10px; color: rgba(255,255,255,.25); margin-top: 2px; }
        .ann-item { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 9px; padding: 12px 14px; margin-bottom: 8px; cursor: pointer; }
        .ann-item:hover { border-color: rgba(195,33,120,.3); background: rgba(195,33,120,.04); }
        .ann-title { font-size: 13px; font-weight: 600; margin-bottom: 3px; }
        .ann-meta { font-size: 11px; color: rgba(255,255,255,.3); }
        .ann-textarea { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 10px 13px; color: #e8ddf0; font-size: 13px; outline: none; resize: vertical; min-height: 90px; }
        .ann-textarea:focus { border-color: #c32178; }
        .btn-remove { background: none; border: none; color: #f44336; cursor: pointer; font-size: 1.1rem; }
    </style>
</head>
<body>

<header class="sprint-header">
    <div style="display:flex;align-items:center;gap:16px">
        <div class="logo">🎮 <span class="brand">Dustore</span><span class="sep">/</span>Админка</div>
        <span class="badge-live" id="sprint-title-badge"><?= htmlspecialchars($sprint['title']) ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <a class="nav-btn" href="admin">↺ Выбрать спринт</a>
        <a class="nav-btn" href="/jams">← К спринтам</a>
        <a class="nav-btn" href="participant?sprint_id=<?= $sprint_id ?>">Панель участника</a>
    </div>
</header>

<div class="admin-layout">
    <div class="sidebar">
        <div class="sidebar-section">Обзор</div>
        <div class="sidebar-item" data-view="dashboard"><span class="ico">📊</span> Дашборд</div>
        <div class="sidebar-item" data-view="analytics"><span class="ico">📈</span> Аналитика</div>
        <div class="sidebar-item" data-view="activity"><span class="ico">⚡</span> Активность</div>
        <div class="sidebar-section">Управление</div>
        <div class="sidebar-item" data-view="participants"><span class="ico">👥</span> Участники <span class="sidebar-badge"><?= $totalParticipants ?></span></div>
        <div class="sidebar-item" data-view="submissions"><span class="ico">🎮</span> Работы <span class="sidebar-badge"><?= $totalSubmissions ?></span></div>
        <div class="sidebar-item" data-view="announcements"><span class="ico">📢</span> Объявления</div>
        <div class="sidebar-section">Настройки</div>
        <div class="sidebar-item" data-view="settings"><span class="ico">⚙️</span> Параметры</div>
        <div class="sidebar-item" data-view="judges"><span class="ico">⭐</span> Жюри</div>
        <div class="sidebar-item" data-view="prizes"><span class="ico">🏆</span> Призы</div>
    </div>

    <div class="main-content">
        <!-- DASHBOARD (без изменений) -->
        <div class="view" id="view-dashboard">
            <div class="page-title">Дашборд</div>
            <div class="page-sub"><?= htmlspecialchars($sprint['title']) ?> · Статус: <?= $sprint['status'] ?></div>
            <div class="stats-row">
                <div class="stat-card"><div class="sc-ico">👥</div><div class="sc-val"><?= $totalParticipants ?></div><div class="sc-lbl">Участников</div><div class="sc-delta up">↑ +<?= $newToday ?> сегодня</div></div>
                <div class="stat-card"><div class="sc-ico">🎮</div><div class="sc-val"><?= $totalSubmissions ?></div><div class="sc-lbl">Работ сдано</div><div class="sc-delta up">↑ +0 за час</div></div>
                <div class="stat-card"><div class="sc-ico">👁</div><div class="sc-val">—</div><div class="sc-lbl">Просмотров</div><div class="sc-delta up">↑ +0%</div></div>
                <div class="stat-card"><div class="sc-ico">💬</div><div class="sc-val">—</div><div class="sc-lbl">Комментариев</div><div class="sc-delta down">↓ 0</div></div>
            </div>
            <div class="chart-wrap">
                <div class="chart-head"><span class="chart-title">Регистрации участников</span><div class="chart-tabs"><button class="chart-tab active">7 дней</button></div></div>
                <div class="chart-area"><div class="chart-bars" id="regChart"></div></div>
            </div>
            <div class="two-col">
                <div class="list-card"><div class="list-card-title">🏅 Топ участников по активности</div><div id="top-members"></div></div>
                <div class="list-card"><div class="list-card-title">🔖 Топ тегов</div><div id="top-tags"></div></div>
            </div>
        </div>

        <!-- ANALYTICS (без изменений) -->
        <div class="view" id="view-analytics">
            <div class="page-title">Аналитика</div>
            <div class="page-sub">Детальная статистика спринта</div>
            <div class="stats-row">
                <div class="stat-card"><div class="sc-ico">📉</div><div class="sc-val">—%</div><div class="sc-lbl">Конверсия</div></div>
                <div class="stat-card"><div class="sc-ico">⌛</div><div class="sc-val">—ч</div><div class="sc-lbl">Ср. время на странице</div></div>
                <div class="stat-card"><div class="sc-ico">🔁</div><div class="sc-val">—%</div><div class="sc-lbl">Повторных участников</div></div>
                <div class="stat-card"><div class="sc-ico">🌍</div><div class="sc-val">—</div><div class="sc-lbl">Стран</div></div>
            </div>
            <div class="chart-wrap"><div class="chart-head"><span class="chart-title">Активность по часам суток</span></div><div class="chart-area"><div class="chart-bars" id="hourlyChart"></div></div></div>
        </div>

        <!-- ACTIVITY (без изменений) -->
        <div class="view" id="view-activity">
            <div class="page-title">Активность</div>
            <div class="page-sub">Лента событий в реальном времени</div>
            <div class="list-card"><div class="list-card-title">🔴 Последние события</div><div id="feed-list"></div></div>
        </div>

        <!-- PARTICIPANTS (без изменений) -->
        <div class="view" id="view-participants">
            <div class="page-title">Участники <span style="font-size:14px;color:rgba(255,255,255,.35)"><?= $totalParticipants ?></span></div>
            <div class="table-wrap">
                <div class="table-toolbar"><input class="tbl-search" placeholder="Поиск..." oninput="filterParticipants(this.value)"><button class="tbl-btn">⬇ Экспорт CSV</button><button class="tbl-btn" onclick="sendMassNotification()">📢 Рассылка</button></div>
                <table><thead><th>#</th><th>Участник</th><th>Движок</th><th>Роль</th><th>Статус</th><th>Регистрация</th><th>Действия</th></tr></thead><tbody id="participants-tbody"></tbody></table>
            </div>
        </div>

        <!-- SUBMISSIONS (без изменений) -->
        <div class="view" id="view-submissions">
            <div class="page-title">Работы <span style="font-size:14px;color:rgba(255,255,255,.35)"><?= $totalSubmissions ?></span></div>
            <div class="stats-row" style="grid-template-columns:repeat(3,1fr)">
                <div class="stat-card"><div class="sc-ico">✅</div><div class="sc-val"><?= $totalSubmissions ?></div><div class="sc-lbl">Работ сдано</div></div>
                <div class="stat-card"><div class="sc-ico">⏳</div><div class="sc-val">—</div><div class="sc-lbl">На проверке</div></div>
                <div class="stat-card"><div class="sc-ico">🏆</div><div class="sc-val">—</div><div class="sc-lbl">Финалистов</div></div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <th>#</th>
                        <th>Название игры</th>
                        <th>Участник</th>
                        <th>Движок</th>
                        <th>Оценка</th>
                        <th>Статус</th>
                        <th>Файл</th>
                    </thead>
                    <tbody id="submissions-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- ANNOUNCEMENTS (без изменений) -->
        <div class="view" id="view-announcements">
            <div class="page-title">Объявления</div>
            <div class="settings-section">
                <div class="settings-section-title">📝 Новое объявление</div>
                <div class="form-row-s"><label class="form-label-s">Заголовок</label><input class="form-input-s" id="ann-title" placeholder="Например: Важное обновление правил..."></div>
                <div class="form-row-s"><label class="form-label-s">Текст</label><textarea class="ann-textarea" id="ann-body" placeholder="Текст объявления..."></textarea></div>
                <button class="btn-save" onclick="sendAnnouncement()">📢 Отправить всем</button>
            </div>
            <div class="list-card"><div class="list-card-title">📋 История объявлений</div><div id="ann-list"></div></div>
        </div>

        <!-- SETTINGS (ОБНОВЛЁН) -->
        <div class="view" id="view-settings">
            <div class="page-title">Параметры спринта</div>
            <div class="settings-section">
                <div class="settings-section-title">🎮 Основная информация</div>
                <div class="form-row-s">
                    <label class="form-label-s">Логотип</label>
                    <input type="file" id="set-logo" accept="image/*" class="form-input-s">
                    <div id="logo-preview" class="logo-preview">
                        <?php if ($sprint['logo_url']): ?>
                            <img src="<?= htmlspecialchars($sprint['logo_url']) ?>" alt="logo">
                            <button type="button" class="btn-sm" onclick="removeLogo()">Удалить</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-row-s"><label class="form-label-s">Название спринта</label><input class="form-input-s" id="set-title" value="<?= htmlspecialchars($sprint['title']) ?>"></div>
                <!-- Новое поле: Описание -->
                <div class="form-row-s"><label class="form-label-s">Описание спринта</label><textarea class="form-input-s" id="set-description" rows="4"><?= htmlspecialchars($sprint['description'] ?? '') ?></textarea></div>
                <!-- Новое поле: Полезные ссылки -->
                <div class="form-row-s"><label class="form-label-s">Полезные ссылки (каждая с новой строки)</label><textarea class="form-input-s" id="set-useful_links" rows="3"><?= htmlspecialchars($sprint['useful_links'] ?? '') ?></textarea></div>
                <!-- Новое поле: Регламент -->
                <div class="form-row-s"><label class="form-label-s">Регламент (показывается перед регистрацией)</label><textarea class="form-input-s" id="set-rules" rows="6"><?= htmlspecialchars($sprint['rules'] ?? '') ?></textarea></div>

                <!-- Блок с датами (новые поля) -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <div><label class="form-label-s">Регистрация с</label><input class="form-input-s" type="datetime-local" id="set-registration_start" value="<?= date('Y-m-d\TH:i', strtotime($sprint['registration_start'] ?? 'now')) ?>"></div>
                    <div><label class="form-label-s">Регистрация до</label><input class="form-input-s" type="datetime-local" id="set-registration_end" value="<?= date('Y-m-d\TH:i', strtotime($sprint['registration_end'] ?? '')) ?>"></div>
                    <div><label class="form-label-s">Начало джема</label><input class="form-input-s" type="datetime-local" id="set-jam_start" value="<?= date('Y-m-d\TH:i', strtotime($sprint['jam_start'] ?? '')) ?>"></div>
                    <div><label class="form-label-s">Окончание джема (приём работ)</label><input class="form-input-s" type="datetime-local" id="set-jam_end" value="<?= date('Y-m-d\TH:i', strtotime($sprint['jam_end'] ?? '')) ?>"></div>
                    <div><label class="form-label-s">Голосование с</label><input class="form-input-s" type="datetime-local" id="set-voting_start" value="<?= date('Y-m-d\TH:i', strtotime($sprint['voting_start'] ?? '')) ?>"></div>
                    <div><label class="form-label-s">Голосование до</label><input class="form-input-s" type="datetime-local" id="set-voting_end" value="<?= date('Y-m-d\TH:i', strtotime($sprint['voting_end'] ?? '')) ?>"></div>
                </div>

                <!-- Удалены старые поля start_at, duration_value, duration_unit, max_participants -->
                <!-- оставляем max_participants, если нужно -->
                <div class="form-row-s" style="margin-top:12px;"><label class="form-label-s">Макс. участников</label><input class="form-input-s" type="number" id="set-maxp" value="<?= $sprint['max_participants'] ?>" style="width:160px"></div>

                <button class="btn-save" onclick="saveSettings()">Сохранить изменения</button>
            </div>
            <div class="settings-section">
                <div class="settings-section-title">🔧 Функции</div>
                <div class="toggle-row"><div class="toggle-info"><div class="ti-title">Командный режим</div><div class="ti-desc">Разрешить создание команд</div></div><label class="toggle"><input type="checkbox" id="toggle-teamMode" checked><span class="toggle-slider"></span></label></div>
                <div class="toggle-row"><div class="toggle-info"><div class="ti-title">Тема скрыта до старта</div><div class="ti-desc">Участники узнают тему только в момент старта</div></div><label class="toggle"><input type="checkbox" id="toggle-hideTheme" <?= $sprint['theme'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label></div>
                <div class="toggle-row"><div class="toggle-info"><div class="ti-title">Публичные работы</div><div class="ti-desc">Все могут просматривать сданные игры</div></div><label class="toggle"><input type="checkbox" id="toggle-publicWorks"><span class="toggle-slider"></span></label></div>
                <div class="toggle-row"><div class="toggle-info"><div class="ti-title">Голосование сообщества</div><div class="ti-desc">Зрители могут голосовать за работы</div></div><label class="toggle"><input type="checkbox" id="toggle-communityVote" checked><span class="toggle-slider"></span></label></div>
                <div class="toggle-row"><div class="toggle-info"><div class="ti-title">Биржа команд (L4T)</div><div class="ti-desc">Показывать кнопку поиска команды через L4T</div></div><label class="toggle"><input type="checkbox" id="toggle-l4t" checked><span class="toggle-slider"></span></label></div>
            </div>
            <div style="display:flex;gap:10px"><button class="btn-save" onclick="saveSettings()">Сохранить изменения</button><button class="btn-danger" onclick="if(confirm('Удалить спринт?')) deleteSprint()">Удалить спринт</button></div>
        </div>

        <!-- JUDGES (без изменений) -->
        <div class="view" id="view-judges">
            <div class="page-title">Жюри</div>
            <div class="settings-section"><div class="settings-section-title">➕ Добавить судью</div><div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end"><select class="form-input-s" id="newJudgeId"><option value="">-- Выберите пользователя --</select><button class="btn-save" onclick="addJudge()">+ Добавить</button></div></div>
            <div class="list-card"><div class="list-card-title">⭐ Текущее жюри</div><div id="judges-list"></div></div>
        </div>

        <!-- PRIZES (без изменений) -->
        <div class="view" id="view-prizes">
            <div class="page-title">Призы</div>
            <div class="settings-section"><div class="settings-section-title">🏆 Призовые места</div><div id="prizes-settings"></div><button class="tbl-btn" onclick="addPrize()">+ Добавить место</button></div>
        </div>
    </div>
</div>

<script>
    // JAM_ID и прочие данные
    const JAM_ID = <?= json_encode((int)$sprint_id) ?>;
    console.log('JAM_ID set to', JAM_ID);
</script>
<script>
    // Данные из PHP
    const sprintId = <?= $sprint_id ?>;
    const participants = <?= json_encode($participants) ?>;
    const submissions = <?= json_encode($submissions) ?>;
    const announcements = <?= json_encode($announcements) ?>;
    const experts = <?= json_encode($experts) ?>;
    const prizes = <?= json_encode($prizes) ?>;
    const chartData = <?= json_encode($chartData) ?>;
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const hourlyData = <?= json_encode($hourlyData) ?>;
    const hourlyLabels = <?= json_encode($hourlyLabels) ?>;

    let allUsers = [];

    async function loadUsers() {
        try {
            const response = await fetch('/api/get_all_users.php');
            if (!response.ok) throw new Error('Ошибка загрузки пользователей');
            const users = await response.json();
            allUsers = users;
            const select = document.getElementById('newJudgeId');
            if (!select) return;
            select.innerHTML = '<option value="">-- Выберите пользователя --</option>';
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                const displayName = (user.username && user.username.trim() !== '') ? user.username : `Пользователь ${user.id}`;
                option.textContent = displayName;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Ошибка в loadUsers:', error);
        }
    }

    async function loadJudges(jamId) {
        try {
            const response = await fetch(`/swad/controllers/jams/get_jam_judges.php?jam_id=${jamId}`);
            if (!response.ok) throw new Error('Ошибка загрузки жюри');
            const judges = await response.json();
            const container = document.getElementById('judges-list');
            if (!container) return;
            if (judges.length === 0) {
                container.innerHTML = '<div style="padding:12px; color:rgba(255,255,255,.5);">Жюри пока не назначено</div>';
                return;
            }
            container.innerHTML = judges.map(judge => `
                <div class="list-item" data-judge-id="${judge.id}">
                    <span>⭐ ${escapeHtml(judge.username || `Пользователь ${judge.id}`)}</span>
                    <button class="btn-remove" onclick="removeJudge(${judge.id})" style="background:none; border:none; color:#f44336; cursor:pointer;">✕</button>
                </div>
            `).join('');
        } catch (error) {
            console.error('Ошибка в loadJudges:', error);
        }
    }

    async function addJudge() {
        const select = document.getElementById('newJudgeId');
        const userId = select.value;
        if (!userId) { alert('Выберите пользователя'); return; }
        if (typeof JAM_ID === 'undefined') { alert('Ошибка: идентификатор джема не найден'); return; }
        try {
            const response = await fetch('/swad/controllers/jams/add_judge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jam_id: JAM_ID, user_id: userId })
            });
            const result = await response.json();
            if (result.success) {
                alert('Судья добавлен');
                loadJudges(JAM_ID);
                select.value = '';
            } else {
                alert(result.error || 'Ошибка при добавлении');
            }
        } catch (error) {
            console.error('Ошибка addJudge:', error);
            alert('Не удалось добавить судью');
        }
    }

    async function removeJudge(judgeId) {
        if (!confirm('Удалить этого судью из жюри?')) return;
        try {
            const response = await fetch('/swad/controllers/jams/remove_judge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ jam_id: JAM_ID, user_id: judgeId })
            });
            const result = await response.json();
            if (result.success) {
                loadJudges(JAM_ID);
            } else {
                alert(result.error || 'Ошибка удаления');
            }
        } catch (error) {
            console.error('Ошибка removeJudge:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof JAM_ID !== 'undefined' && JAM_ID) {
            loadUsers();
            loadJudges(JAM_ID);
        } else {
            console.warn('JAM_ID не определён, жюри не загружено');
        }
    });

    loadUsers();

    // ----- Сохранение активной вкладки -----
    function saveActiveTab(viewName) {
        localStorage.setItem('admin_active_tab_' + sprintId, viewName);
    }
    function restoreActiveTab() {
        const saved = localStorage.getItem('admin_active_tab_' + sprintId);
        if (saved) {
            const target = document.querySelector(`.sidebar-item[data-view="${saved}"]`);
            if (target) showView(saved, target);
        } else {
            const defaultTab = document.querySelector('.sidebar-item[data-view="dashboard"]');
            if (defaultTab) showView('dashboard', defaultTab);
        }
    }
    function showView(viewName, el) {
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        const targetView = document.getElementById('view-' + viewName);
        if (targetView) targetView.classList.add('active');
        document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
        if (el) el.classList.add('active');
        saveActiveTab(viewName);
    }
    document.querySelectorAll('.sidebar-item').forEach(item => {
        const viewName = item.getAttribute('data-view');
        if (viewName) {
            item.addEventListener('click', () => showView(viewName, item));
        }
    });
    restoreActiveTab();

    // ----- Вспомогательные функции отрисовки -----
    function buildBar(containerId, data, labels) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const max = Math.max(...data, 1);
        container.innerHTML = '';
        data.forEach((v, i) => {
            const pct = Math.round(v / max * 100);
            const wrap = document.createElement('div');
            wrap.className = 'chart-bar-wrap';
            const bar = document.createElement('div');
            bar.className = 'chart-bar';
            bar.style.height = '0%';
            bar.dataset.val = v;
            setTimeout(() => bar.style.height = pct + '%', 50 + i * 30);
            wrap.appendChild(bar);
            if (labels && labels[i]) {
                const lbl = document.createElement('div');
                lbl.className = 'chart-lbl';
                lbl.textContent = labels[i];
                wrap.appendChild(lbl);
            }
            container.appendChild(wrap);
        });
    }

    function renderDashboard() {
        buildBar('regChart', chartData, chartLabels);
        document.getElementById('top-members').innerHTML = participants.slice(0,4).map((p,i) => `<div class="list-item"><div class="li-rank">${i+1}</div><div class="li-info"><div class="li-name">${escapeHtml(p.username)}</div><div class="li-sub">${p.role || 'Участник'}</div></div><div class="li-val">⭐ —</div></div>`).join('');
        document.getElementById('top-tags').innerHTML = '<div class="list-item"><div class="li-info"><div class="li-name">Unity</div></div><div class="li-val">60%</div></div><div class="list-item"><div class="li-info"><div class="li-name">Godot</div></div><div class="li-val">25%</div></div>';
    }
    function renderAnalytics() { buildBar('hourlyChart', hourlyData, hourlyLabels); }
    function renderActivity() {
        document.getElementById('feed-list').innerHTML = '<div class="feed-item"><div class="feed-ico">👤</div><div><div class="feed-text"><strong>Администратор</strong> загрузил админку</div><div class="feed-time">только что</div></div></div>';
    }
    function renderParticipants(filter = '') {
        const filtered = participants.filter(p => !filter || p.username.toLowerCase().includes(filter) || (p.engine || '').toLowerCase().includes(filter) || (p.role || '').toLowerCase().includes(filter));
        const tbody = document.getElementById('participants-tbody');
        if (!tbody) return;
        tbody.innerHTML = filtered.map((p, i) => `
            <tr>
                <td>${i+1}</td>
                <td><div class="td-name">${escapeHtml(p.username)}</div><div class="td-sub">—</div></td>
                <td>${escapeHtml(p.engine || '—')}</td>
                <td>${escapeHtml(p.role || '—')}</td>
                <td><span class="status-pill pill-green">Активен</span></td>
                <td style="color:rgba(255,255,255,.35)">${new Date(p.joined_at).toLocaleDateString()}</td>
                <td><button class="tbl-btn" onclick="removeParticipant(${p.id})">Исключить</button></td>
            </tr>
        `).join('');
    }
    function filterParticipants(val) { renderParticipants(val.toLowerCase()); }
    function renderSubmissions() {
        const tbody = document.getElementById('submissions-tbody');
        if (!tbody) return;
        tbody.innerHTML = submissions.map((s, i) => {
            const fileLink = s.build_url ? `<a href="${escapeHtml(s.build_url)}" class="tbl-btn" download style="display:inline-block; text-decoration:none; padding:4px 8px;">⬇ Скачать</a>` : `<span style="color:rgba(255,255,255,.3);">—</span>`;
            return `<tr>
                <td>${i+1}</td>
                <td><div class="td-name">${escapeHtml(s.game_title || s.title || 'Без названия')}</div></td>
                <td>${escapeHtml(s.user_name)}</td>
                <td>${escapeHtml(s.engine || '—')}</td>
                <td style="font-weight:700;color:#c32178">${s.score ?? '—'}</td>
                <td><span class="status-pill pill-yellow">${s.status || 'На проверке'}</span></td>
                <td>${fileLink}</td>
            </tr>`;
        }).join('');
    }
    function renderAnnouncements() {
        const container = document.getElementById('ann-list');
        if (container) container.innerHTML = announcements.map(a => `<div class="ann-item"><div class="ann-title">${escapeHtml(a.title)}</div><div class="ann-meta">${new Date(a.created_at).toLocaleString()} · всем участникам</div></div>`).join('');
    }
    function renderJudges() {
        const container = document.getElementById('judges-list');
        if (container) container.innerHTML = experts.map(e => `<div class="list-item"><div class="li-rank">⭐</div><div class="li-info"><div class="li-name">${escapeHtml(e.username)}</div><div class="li-sub">${escapeHtml(e.role || 'Эксперт')}</div></div><button class="tbl-btn" onclick="removeJudge(${e.id})">Убрать</button></div>`).join('');
    }
    function renderPrizes() {
        const container = document.getElementById('prizes-settings');
        if (!container) return;
        container.innerHTML = prizes.map(p => `
            <div style="display:grid;grid-template-columns:40px 1fr auto;gap:10px;align-items:center;margin-bottom:8px">
                <span style="font-size:20px;text-align:center">${['🥇','🥈','🥉'][p.place_num-1] || '🎖'}</span>
                <input class="form-input-s" value="${escapeHtml(p.reward)}" id="prize-${p.id}" data-id="${p.id}">
                <button class="btn-save" onclick="updatePrize(${p.id}, document.getElementById('prize-${p.id}').value)">✓</button>
            </div>
        `).join('');
    }

    // ----- AJAX-функции (обновлены для новых полей) -----
    async function saveSettings() {
        const formData = new FormData();
        formData.append('sprint_id', sprintId);
        formData.append('title', document.getElementById('set-title').value);
        formData.append('description', document.getElementById('set-description').value);
        formData.append('useful_links', document.getElementById('set-useful_links').value);
        formData.append('rules', document.getElementById('set-rules').value);
        formData.append('registration_start', document.getElementById('set-registration_start').value);
        formData.append('registration_end', document.getElementById('set-registration_end').value);
        formData.append('jam_start', document.getElementById('set-jam_start').value);
        formData.append('jam_end', document.getElementById('set-jam_end').value);
        formData.append('voting_start', document.getElementById('set-voting_start').value);
        formData.append('voting_end', document.getElementById('set-voting_end').value);
        formData.append('max_participants', document.getElementById('set-maxp').value);
        const logoFile = document.getElementById('set-logo').files[0];
        if (logoFile) formData.append('logo', logoFile);
        // toggles (можно добавить позже)
        const resp = await fetch('/swad/controllers/update_sprint_settings.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            alert('Настройки сохранены');
            document.getElementById('sprint-title-badge').textContent = document.getElementById('set-title').value;
            if (res.logo_url) {
                const previewDiv = document.getElementById('logo-preview');
                previewDiv.innerHTML = `<img src="${escapeHtml(res.logo_url)}" alt="logo"><button type="button" class="btn-sm" onclick="removeLogo()">Удалить</button>`;
            }
        } else {
            alert('Ошибка: ' + res.message);
        }
    }

    async function removeLogo() {
        const resp = await fetch('/swad/controllers/remove_logo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: sprintId })
        });
        const res = await resp.json();
        if (res.success) {
            document.getElementById('logo-preview').innerHTML = '';
            alert('Логотип удалён');
        } else alert(res.message);
    }

    async function sendAnnouncement() {
        const title = document.getElementById('ann-title').value.trim();
        const body = document.getElementById('ann-body').value.trim();
        if (!title) return alert('Введите заголовок');
        const resp = await fetch('/swad/controllers/send_announcement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: sprintId, title, body })
        });
        const res = await resp.json();
        if (res.success) {
            alert('Объявление отправлено');
            location.reload();
        } else alert(res.message);
    }

    async function removeJudge(userId) {
        if (!confirm('Убрать эксперта?')) return;
        const resp = await fetch('/swad/controllers/remove_judge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: sprintId, user_id: userId })
        });
        const res = await resp.json();
        if (res.success) location.reload();
        else alert(res.message);
    }

    async function updatePrize(prizeId, reward) {
        const resp = await fetch('/swad/controllers/update_prize.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prize_id: prizeId, reward })
        });
        const res = await resp.json();
        if (res.success) alert('Сохранено');
        else alert(res.message);
    }

    async function addPrize() {
        const resp = await fetch('/swad/controllers/add_prize.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: sprintId })
        });
        const res = await resp.json();
        if (res.success) location.reload();
        else alert(res.message);
    }

    async function removeParticipant(userId) {
        if (!confirm('Исключить участника?')) return;
        const resp = await fetch('/swad/controllers/remove_participant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: sprintId, user_id: userId })
        });
        const res = await resp.json();
        if (res.success) location.reload();
        else alert(res.message);
    }

    function sendMassNotification() { alert('Рассылка будет реализована позже'); }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function populateUserSelect() {
        const select = document.getElementById('newJudgeId');
        if (!select) return;
        select.innerHTML = '<option value="">-- Выберите пользователя --</option>';
        allUsers.forEach(u => {
            select.innerHTML += `<option value="${u.id}">${u.username} (${u.role || 'пользователь'})</option>`;
        });
    }

    // Инициализация
    renderDashboard();
    renderAnalytics();
    renderActivity();
    renderParticipants();
    renderSubmissions();
    renderAnnouncements();
    renderJudges();
    renderPrizes();
    populateUserSelect();

    // Предпросмотр логотипа
    document.getElementById('set-logo')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const previewDiv = document.getElementById('logo-preview');
                previewDiv.innerHTML = `<img src="${ev.target.result}" alt="preview"><button type="button" class="btn-sm" onclick="removeLogo()">Удалить</button>`;
            };
            reader.readAsDataURL(file);
        }
    });
</script>
</body>
</html>