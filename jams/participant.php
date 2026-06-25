<?php
require_once('../swad/config.php');
session_start();

$dbInst = new Database();
$conn = $dbInst->connect();
if (!$conn) die('Ошибка подключения к базе данных');

$userId = $_SESSION['USERDATA']['id'] ?? 0;
$sprintId = (int)($_GET['sprint_id'] ?? 0);

if (!$sprintId || !$userId) {
    header('Location: /jams');
    exit;
}

// Проверяем участие
$check = $conn->prepare("SELECT id FROM sprint_participants WHERE sprint_id = ? AND user_id = ?");
$check->execute([$sprintId, $userId]);
if (!$check->fetch()) {
    header('Location: /jams');
    exit;
}

// Загружаем данные спринта
$sprintStmt = $conn->prepare("
    SELECT s.*, u.username as host_name 
    FROM sprints s 
    LEFT JOIN users u ON s.host_user_id = u.id 
    WHERE s.id = ?
");
$sprintStmt->execute([$sprintId]);
$sprint = $sprintStmt->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Спринт не найден');

// ---------- Функции ----------
function markdownToHtml($text) {
    if (!$text) return '';
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Заголовки
    $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
    // Жирный и курсив
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    // Markdown-ссылки [текст](url)
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);
    // Авто-ссылки (голые URL)
    $html = preg_replace('/(?<![">])(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" rel="noopener">$1</a>', $html);
    // Списки
    $html = preg_replace('/^\- (.*$)/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    // Переносы строк
    $html = nl2br($html);
    return $html;
}

function getSprintPhase($sprint) {
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $regStart = isset($sprint['registration_start']) ? new DateTime($sprint['registration_start'], new DateTimeZone('Europe/Moscow')) : null;
    $regEnd   = isset($sprint['registration_end']) ? new DateTime($sprint['registration_end'], new DateTimeZone('Europe/Moscow')) : null;
    $jamStart = isset($sprint['jam_start']) ? new DateTime($sprint['jam_start'], new DateTimeZone('Europe/Moscow')) : null;
    $jamEnd   = isset($sprint['jam_end']) ? new DateTime($sprint['jam_end'], new DateTimeZone('Europe/Moscow')) : null;
    $voteStart = isset($sprint['voting_start']) ? new DateTime($sprint['voting_start'], new DateTimeZone('Europe/Moscow')) : null;
    $voteEnd   = isset($sprint['voting_end']) ? new DateTime($sprint['voting_end'], new DateTimeZone('Europe/Moscow')) : null;

    if ($regStart && $now < $regStart) return 'upcoming';
    if ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) return 'registration';
    if ($regEnd && $jamStart && $now >= $regEnd && $now < $jamStart) return 'pre_jam';
    if ($jamStart && $jamEnd && $now >= $jamStart && $now < $jamEnd) return 'jam';
    if ($jamEnd && $voteStart && $now >= $jamEnd && $now < $voteStart) return 'post_jam';
    if ($voteStart && $voteEnd && $now >= $voteStart && $now < $voteEnd) return 'voting';
    return 'finished';
}

$phase = getSprintPhase($sprint);

// Карта фаз на русский
$phaseMap = [
    'upcoming'     => 'Скоро',
    'registration' => 'Регистрация',
    'pre_jam'      => 'Скоро джем',
    'jam'          => 'Джем',
    'post_jam'     => 'Завершён джем',
    'voting'       => 'Голосование',
    'finished'     => 'Завершён'
];
$phaseRu = $phaseMap[$phase] ?? $phase;

// Подсчёт времени до следующего события
function getNextEvent($sprint) {
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $events = [];
    if (!empty($sprint['registration_start'])) $events['registration_start'] = new DateTime($sprint['registration_start'], new DateTimeZone('Europe/Moscow'));
    if (!empty($sprint['registration_end']))   $events['registration_end']   = new DateTime($sprint['registration_end'], new DateTimeZone('Europe/Moscow'));
    if (!empty($sprint['jam_start']))          $events['jam_start']          = new DateTime($sprint['jam_start'], new DateTimeZone('Europe/Moscow'));
    if (!empty($sprint['jam_end']))            $events['jam_end']            = new DateTime($sprint['jam_end'], new DateTimeZone('Europe/Moscow'));
    if (!empty($sprint['voting_start']))       $events['voting_start']       = new DateTime($sprint['voting_start'], new DateTimeZone('Europe/Moscow'));
    if (!empty($sprint['voting_end']))         $events['voting_end']         = new DateTime($sprint['voting_end'], new DateTimeZone('Europe/Moscow'));
    foreach ($events as $key => $dt) {
        if ($dt > $now) return ['key' => $key, 'datetime' => $dt];
    }
    return null;
}
$nextEvent = getNextEvent($sprint);
if ($nextEvent) {
    $diff = $nextEvent['datetime']->getTimestamp() - (new DateTime('now', new DateTimeZone('Europe/Moscow')))->getTimestamp();
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $countdownStr = ($days > 0 ? $days . 'д ' : '') . $hours . 'ч ' . $minutes . 'м';
    $countdownLabel = 'до ' . str_replace('_', ' ', $nextEvent['key']);
} else {
    $countdownStr = 'Завершён';
    $countdownLabel = '';
}

// Загружаем участников (всех)
$teamStmt = $conn->prepare("
    SELECT u.id, u.username, u.role, sp.joined_at
    FROM sprint_participants sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.sprint_id = ?
");
$teamStmt->execute([$sprintId]);
$team = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

// Работа пользователя
$subStmt = $conn->prepare("SELECT * FROM sprint_submissions WHERE sprint_id = ? AND user_id = ?");
$subStmt->execute([$sprintId, $userId]);
$submission = $subStmt->fetch(PDO::FETCH_ASSOC);

// Объявления
$annStmt = $conn->prepare("SELECT * FROM sprint_announcements WHERE sprint_id = ? ORDER BY created_at DESC");
$annStmt->execute([$sprintId]);
$announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);

$criteria = [];

require_once('../swad/static/elements/header.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($sprint['title']) ?> — Панель участника</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0d0414;
            font-family: 'Manrope', system-ui, sans-serif;
            color: #e8ddf0;
            background: linear-gradient(180deg, #0f0a20, #240038, #780066);
        }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(195,33,120,.3); border-radius: 4px; }
        .sprint-header { padding: 13px 26px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 0; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: #e8ddf0; }
        .logo .brand { color: #c32178; }
        .nav-btn { padding: 7px 15px; border-radius: 7px; border: none; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.05); color: rgba(255,255,255,.5); transition: .001s; text-decoration: none; display: inline-block; }
        .nav-btn:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .timer-badge { background: rgba(195,33,120,.12); border: 1px solid rgba(195,33,120,.3); border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 7px; }
        .participant-layout { display: flex; height: 80vh; }
        .sidebar { width: 230px; flex-shrink: 0; background: #00000030; padding: 10px; margin: 10px 0px 10px 10px; border-radius: 15px; }
        .sidebar-section { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.25); text-transform: uppercase; letter-spacing: .08em; padding: 10px 10px 5px; margin-top: 6px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: rgba(255,255,255,.45); transition: .001s; border: 1px solid transparent; }
        .sidebar-item:hover { background: rgba(255,255,255,.05); color: #e8ddf0; }
        .sidebar-item.active { background: rgba(195,33,120,.12); color: #e8ddf0; border-color: rgba(195,33,120,.25); }
        .sprint-info-card { background: rgba(195,33,120,.07); border: 1px solid rgba(195,33,120,.2); border-radius: 10px; padding: 14px; margin-bottom: 16px; }
        .si-title { font-size: 13px; font-weight: 800; margin-bottom: 3px; }
        .si-host { font-size: 11px; color: rgba(255,255,255,.35); margin-bottom: 10px; }
        .si-stat { font-size: 11px; display: flex; justify-content: space-between; margin-bottom: 4px; }
        .si-stat span { color: #e8ddf0; font-weight: 600; }
        .countdown-mini { background: rgba(0,0,0,.3); border-radius: 7px; padding: 8px; text-align: center; margin-top: 10px; }
        .countdown-mini .cm-val { font-size: 18px; font-weight: 800; color: #c32178; font-variant-numeric: tabular-nums; }
        .countdown-mini .cm-lbl { font-size: 10px; color: rgba(255,255,255,.3); margin-top: 2px; }
        .main-content { flex: 1; padding: 10px; margin: 10px; border-radius: 15px; overflow-y: auto; max-height: calc(100vh - 54px); background: #00000030; }
        .view { display: none; }
        .view.active { display: block; }
        .page-title { font-size: 20px; font-weight: 800; margin-bottom: 4px; letter-spacing: -.3px; }
        .page-sub { color: rgba(255,255,255,.35); font-size: 13px; margin-bottom: 24px; }
        .welcome-hero { background: #00000030; border-radius: 14px; padding: 24px 28px; margin-bottom: 22px; position: relative; overflow: hidden; }
        .wh-top { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .wh-avatar { width: 52px; height: 52px; background: rgba(195,33,120,.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; background-size: cover; background-position: center; }
        .wh-name { font-size: 18px; font-weight: 800; }
        .wh-status { margin-left: auto; background: rgba(34,197,94,.1); color: #22c55e; border: 1px solid rgba(34,197,94,.25); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .wh-progress-row { display: flex; align-items: center; gap: 14px; }
        .wh-prog-bar { flex: 1; height: 6px; background: rgba(255,255,255,.06); border-radius: 99px; overflow: hidden; }
        .wh-prog-fill { height: 100%; background: #c32178; border-radius: 99px; }
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #00000030; border-radius: 12px; padding: 15px; }
        .stat-card .sc-val { font-size: 20px; font-weight: 800; margin-bottom: 2px; }
        .stat-card .sc-lbl { font-size: 11px; color: rgba(255,255,255,.35); }
        .card { background: #00000030; border-radius: 12px; padding: 18px; margin-bottom: 14px; }
        .card-title { font-size: 14px; font-weight: 700; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,.07); display: flex; justify-content: space-between; align-items: center; }
        .team-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
        .team-card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 14px 12px; text-align: center; transition: .15s; }
        .team-card:hover { background: rgba(195,33,120,.08); border-color: rgba(195,33,120,.2); transform: translateY(-2px); }
        .team-card .tc-av { width: 48px; height: 48px; background: rgba(195,33,120,.15); border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .team-card .tc-name { font-size: 13px; font-weight: 600; }
        .team-card .tc-name a { color: #e8ddf0; text-decoration: none; }
        .team-card .tc-name a:hover { color: #c32178; }
        .team-card .tc-badge { font-size: 9px; background: rgba(195,33,120,.2); border-radius: 4px; padding: 1px 6px; display: inline-block; margin-top: 4px; }
        .btn-primary { background: #c32178; border: none; color: #fff; border-radius: 8px; padding: 11px 22px; font-weight: 700; cursor: pointer; }
        .btn-primary:hover { background: #9e1a66; }
        .btn-team { background: rgba(195,33,120,.1); border: 1px solid rgba(195,33,120,.3); color: #e8ddf0; border-radius: 7px; padding: 7px 16px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: .15s; }
        .btn-team:hover { background: rgba(195,33,120,.2); }
        .form-input-s, .form-textarea-s { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 9px 13px; color: #e8ddf0; font-size: 13px; }
        .form-label-s { display: block; color: rgba(255,255,255,.4); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-row-s { margin-bottom: 13px; }
        .alert { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.2); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; font-size: 12px; color: rgba(245,158,11,.9); }
        .alert.success { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.2); color: #22c55e; }
        .criteria-item { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); border-radius: 9px; padding: 12px 14px; margin-bottom: 8px; }
        .ci-head { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .ci-name { font-size: 13px; font-weight: 600; }
        .ci-weight { font-size: 11px; color: #c32178; font-weight: 700; }
        .ci-bar { height: 3px; background: rgba(255,255,255,.06); border-radius: 99px; margin-top: 8px; overflow: hidden; }
        .ci-bar-fill { height: 100%; background: #c32178; border-radius: 99px; }
        .ann-item { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 9px; padding: 12px 14px; margin-bottom: 8px; }
        .ann-item.new-ann { border-color: rgba(195,33,120,.3); background: rgba(195,33,120,.05); }
        .ann-title { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; margin-bottom: 4px; }
        .ann-body { font-size: 12px; color: rgba(255,255,255,.5); line-height: 1.6; margin-bottom: 5px; }
        .ann-meta { font-size: 10px; color: rgba(255,255,255,.25); }
        .btn-remove { background: none; border: none; color: #f44336; cursor: pointer; font-size: 1.1rem; }
        .drop-zone { border: 2px dashed rgba(195,33,120,.3); border-radius: 12px; padding: 28px; text-align: center; cursor: pointer; }
        .drop-zone:hover { border-color: #c32178; background: rgba(195,33,120,.05); }
        #jam-progress { margin-top: 12px; }
        #jam-bar { height: 8px; background: #c32178; border-radius: 999px; width: 0%; transition: width .3s; }
        .md-content { font-size: 13px; color: rgba(255,255,255,.7); line-height: 1.7; }
        .md-content h1, .md-content h2, .md-content h3 { color: #e8ddf0; margin: 0 0 8px 0; }
        .md-content ul { padding-left: 20px; margin: 6px 0; }
        .md-content li { margin-bottom: 3px; }
        .md-content a { color: #c32178; text-decoration: none; }
        .md-content a:hover { text-decoration: underline; }
        .md-content strong { color: #fff; }
    </style>
</head>
<body>

<header class="sprint-header">
    <div class="logo"><span class="brand"></span></div>
    <div style="display:flex;align-items:center;gap:10px">
        <div class="timer-badge">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
            <span id="countdown-header"><?= $countdownStr ?></span>
        </div>
        <a class="nav-btn" href="/jams">← К спринтам</a>
    </div>
</header>

<div class="participant-layout">
    <div class="sidebar">
        <div class="sprint-info-card">
            <div class="si-title"><?= htmlspecialchars($sprint['title']) ?></div>
            <div class="si-host">от <?= htmlspecialchars($sprint['host_name'] ?? 'Dustore') ?></div>
            <div class="si-stat">Участников <span><?= count($team) ?> / <?= $sprint['max_participants'] ?></span></div>
            <div class="si-stat">Текущий этап <span><?= $phaseRu ?></span></div>
            <div class="countdown-mini">
                <div class="cm-val" id="countdown-sidebar"><?= $countdownStr ?></div>
                <div class="cm-lbl"><?= $countdownLabel ?></div>
            </div>
        </div>
        <div class="sidebar-section">Моё участие</div>
        <div class="sidebar-item active" onclick="showView('overview',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></span>Обзор</div>
        <div class="sidebar-item" onclick="showView('team',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>Участники</div>
        <div class="sidebar-item" onclick="showView('submit',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-7.07 17.07L12 22l7.07-2.93A10 10 0 0 0 12 2zm0 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg></span>Сдать работу</div>
        <div class="sidebar-section">Спринт</div>
        <div class="sidebar-item" onclick="showView('scoreboard',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg></span>Рейтинг</div>
        <div class="sidebar-item" onclick="showView('criteria',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm4 12h-4v-2h4v2zm0-4h-4v-2h4v2zm-8 4H8v-2h2v2zm0-4H8v-2h2v2z"/></svg></span>Критерии</div>
        <div class="sidebar-item" onclick="showView('announcements',this)"><span class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg></span>Объявления</div>
    </div>

    <div class="main-content">
        <!-- Overview -->
        <div class="view active" id="view-overview">
            <div class="welcome-hero">
                <div class="wh-top">
                    <div class="wh-avatar" style='background-image: url("<?= htmlspecialchars($_SESSION['USERDATA']['profile_picture'] ?? '') ?>");'></div>
                    <div><div class="wh-name"><?= htmlspecialchars($_SESSION['USERDATA']['username'] ?? 'Участник') ?></div><div class="wh-sub">Участник спринта</div></div>
                    <div class="wh-status">✓ Зарегистрирован</div>
                </div>
                <div class="wh-progress-row">
                    <span class="wh-prog-label">Готовность</span>
                    <div class="wh-prog-bar"><div class="wh-prog-fill" style="width:<?= $submission ? '100' : '20' ?>%"></div></div>
                    <span class="wh-prog-val"><?= $submission ? '100%' : '20%' ?></span>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-card"><div class="sc-val"><?= count($team) ?></div><div class="sc-lbl">Участников</div></div>
                <div class="stat-card"><div class="sc-val"><?= $submission ? '1' : '0' ?></div><div class="sc-lbl">Работ сдано</div></div>
                <div class="stat-card"><div class="sc-val"><?= $phaseRu ?></div><div class="sc-lbl">Текущий этап</div></div>
            </div>
            <!-- Описание спринта (с MD) -->
            <?php if (!empty($sprint['description'])): ?>
                <div class="card">
                    <div class="card-title"><span>📖 Описание спринта</span></div>
                    <div class="md-content"><?= markdownToHtml($sprint['description']) ?></div>
                </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-title"><span><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>Тема спринта</span></div>
                <div style="text-align:center;padding:20px 0">
                    <?php if (in_array($phase, ['jam', 'post_jam', 'voting', 'finished'])): ?>
                        <div style="font-size:36px">🎲</div>
                        <div style="font-size:15px;font-weight:700"><?= htmlspecialchars($sprint['theme'] ?? 'Тема не указана') ?></div>
                    <?php else: ?>
                        <div style="font-size:36px">🔒</div>
                        <div style="font-size:15px;font-weight:700">Тема скрыта до старта джема</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($sprint['rules'])): ?>
                <div class="card">
                    <div class="card-title"><span>📜 Регламент</span></div>
                    <div class="md-content"><?= markdownToHtml($sprint['rules']) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($sprint['useful_links'])): ?>
                <div class="card">
                    <div class="card-title"><span>🔗 Полезные ссылки</span></div>
                    <div class="md-content"><?= markdownToHtml($sprint['useful_links']) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Team (Участники) -->
        <div class="view" id="view-team">
            <div class="page-title">Участники спринта</div>
            <div class="card">
                <div class="card-title">
                    <span>👥 Все участники</span>
                    <a href="/l4t?action=create_team&sprint_id=<?= $sprintId ?>" class="btn-team" style="font-size:12px; padding:6px 12px;">+ Создать команду</a>
                </div>
                <div class="team-grid" id="team-list">
                    <?php foreach ($team as $member): ?>
                        <div class="team-card">
                            <div class="tc-av">👤</div>
                            <div class="tc-name">
                                <a href="/player/<?= urlencode($member['username']) ?>"><?= htmlspecialchars($member['username']) ?></a>
                            </div>
                            <?php if ($member['id'] == $userId): ?>
                                <div class="tc-badge">Я</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:12px; text-align:center;">
                    <a href="/l4t?action=find_team&sprint_id=<?= $sprintId ?>" class="btn-team">🔍 Найти команду на L4T</a>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="view" id="view-submit">
            <div class="page-title">Сдать работу</div>
            <?php if ($phase === 'jam'): ?>
                <?php if ($submission): ?>
                    <div class="alert success">✅ Вы уже сдали работу. Вы можете её отредактировать.</div>
                <?php endif; ?>
                <form id="submissionForm" method="post" enctype="multipart/form-data" action="/swad/controllers/submit_sprint.php">
                    <input type="hidden" name="sprint_id" value="<?= $sprintId ?>">
                    <div class="card">
                        <div class="card-title"><span><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a1.0 1.0 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>Карточка работы</span></div>
                        <div class="form-row-s"><label class="form-label-s">Название игры *</label><input class="form-input-s" name="game_title" value="<?= htmlspecialchars($submission['title'] ?? '') ?>" required></div>
                        <div class="form-row-s"><label class="form-label-s">Описание</label><textarea class="form-textarea-s" name="description"><?= htmlspecialchars($submission['description'] ?? '') ?></textarea></div>
                        <div class="form-row-s"><label class="form-label-s">Движок</label><input class="form-input-s" name="engine" value="<?= htmlspecialchars($submission['engine'] ?? '') ?>"></div>
                        <div class="form-row-s"><label class="form-label-s">Резервная ссылка (itch.io / Google Drive / Яндекс.Диск)</label><input class="form-input-s" name="external_link" value="<?= htmlspecialchars($submission['external_link'] ?? '') ?>"></div>
                        <div class="form-row-s">
                            <label class="form-label-s">Файл игры</label>
                            <?php if (!empty($submission['build_url'])): ?>
                                <div class="alert success">✅ Билд загружен <?php if (!empty($submission['build_size'])): ?>(<?= round($submission['build_size'] / 1024 / 1024, 1) ?> МБ)<?php endif; ?></div>
                            <?php endif; ?>
                            <div id="jam-drop" class="drop-zone">
                                <div style="font-size:32px">📦</div>
                                <div id="jam-label"><?= !empty($submission['build_url']) ? 'Заменить билд' : 'Загрузить билд' ?></div>
                                <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:6px;">До 500 МБ — обычная загрузка<br>Более 500 МБ — загрузка чанками</div>
                            </div>
                            <input type="file" id="jam-file" accept=".zip,.rar,.7z" style="display:none;">
                            <input type="hidden" name="build_url" id="build_url" value="<?= htmlspecialchars($submission['build_url'] ?? '') ?>">
                            <input type="hidden" name="build_size" id="build_size" value="<?= (int)($submission['build_size'] ?? 0) ?>">
                            <div id="jam-progress" style="display:none;margin-top:12px;">
                                <div style="display:flex;justify-content:space-between"><span id="jam-status">Подготовка...</span><span id="jam-percent">0%</span></div>
                                <div style="height:8px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden;margin-top:6px;"><div id="jam-bar" style="width:0%;height:100%;background:#c32178;"></div></div>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary"><?= $submission ? '✏ Обновить' : '🚀 Сдать работу' ?></button>
                    </div>
                </form>
            <?php elseif ($phase === 'registration' || $phase === 'pre_jam' || $phase === 'upcoming'): ?>
                <div class="alert">⏳ Приём работ начнётся с началом джема.</div>
            <?php elseif (in_array($phase, ['post_jam', 'voting', 'finished'])): ?>
                <div class="alert">⛔ Приём работ завершён.</div>
            <?php endif; ?>
        </div>

        <!-- Scoreboard -->
        <div class="view" id="view-scoreboard">
            <div class="page-title">Рейтинг</div>
            <div class="card">⏳ Рейтинг будет доступен после завершения спринта.</div>
        </div>

        <!-- Criteria -->
        <div class="view" id="view-criteria">
            <div class="page-title">Критерии оценки</div>
            <div class="card">
                <div class="card-title"><span><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Критерии жюри</span></div>
                <?php foreach ($criteria as $c): ?>
                    <div class="criteria-item">
                        <div class="ci-head"><span class="ci-name"><?= htmlspecialchars($c['name']) ?></span><span class="ci-weight"><?= $c['weight'] ?>%</span></div>
                        <div class="ci-desc"><?= htmlspecialchars($c['description']) ?></div>
                        <div class="ci-bar"><div class="ci-bar-fill" style="width:<?= $c['weight'] ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="view" id="view-announcements">
            <div class="page-title">Объявления</div>
            <?php foreach ($announcements as $ann): ?>
                <div class="ann-item <?= $ann['is_new'] ? 'new-ann' : '' ?>">
                    <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
                    <div class="ann-body"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                    <div class="ann-meta"><?= date('d.m.Y H:i', strtotime($ann['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function showView(name, el) {
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.getElementById('view-'+name).classList.add('active');
        document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
        if (el) el.classList.add('active');
    }
</script>

<script>
    const LARGE       = 500 * 1024 * 1024;
    const SMALL_CHUNK = 5 * 1024 * 1024;
    const LARGE_CHUNK = 50 * 1024 * 1024;
    const SPRINT_ID = <?= (int)$sprintId ?>;

    document.getElementById('jam-drop').onclick = () => {
        document.getElementById('jam-file').click();
    };
    document.getElementById('jam-file').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        uploadBuild(file);
    });

    async function uploadBuild(file) {
        const big = file.size >= LARGE;
        const chunkSize = big ? LARGE_CHUNK : SMALL_CHUNK;
        const totalChunks = Math.ceil(file.size / chunkSize);
        const progress = document.getElementById('jam-progress');
        const bar      = document.getElementById('jam-bar');
        const percent  = document.getElementById('jam-percent');
        const status   = document.getElementById('jam-status');
        progress.style.display = 'block';

        for(let i = 0; i < totalChunks; i++) {
            const fd = new FormData();
            fd.append('chunk', file.slice(i * chunkSize, (i + 1) * chunkSize));
            fd.append('chunk_index', i);
            fd.append('total_chunks', totalChunks);
            fd.append('file_name', file.name);
            fd.append('file_size', file.size);
            fd.append('sprint_id', SPRINT_ID);
            const res = await fetch('/swad/controllers/jams/upload_chunk.php', { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (!data.success) { alert(data.message); return; }
            const p = Math.round(((i + 1) / totalChunks) * 100);
            bar.style.width = p + '%';
            percent.textContent = p + '%';
            status.textContent = 'Чанк ' + (i + 1) + ' из ' + totalChunks;
            if(data.done) {
                document.getElementById('build_url').value = data.url;
                document.getElementById('build_size').value = file.size;
                status.textContent = '✅ Билд загружен';
                break;
            }
        }
    }
</script>

<!-- Эффект наклона -->
<script>
(function() {
    const buttons = document.querySelectorAll('.nav-btn, .btn-primary, .sidebar-item, .btn-team');
    if (!buttons.length) return;
    function resetTilt(btn) { btn.style.transform = ''; }
    function handleMouseMove(e) {
        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const nx = (x / rect.width) * 2 - 1;
        const ny = (y / rect.height) * 2 - 1;
        const maxAngle = 15;
        const rotateY = maxAngle * nx;
        const rotateX = -maxAngle * ny;
        const translateY = -3;
        const scale = 1.1;
        btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
    }
    function handleMouseLeave(e) { resetTilt(e.currentTarget); }
    buttons.forEach(btn => {
        btn.addEventListener('mousemove', handleMouseMove);
        btn.addEventListener('mouseleave', handleMouseLeave);
    });
})();
</script>

</body>
</html>