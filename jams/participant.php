<?php
// Сначала подключаем только config.php для работы с БД (без вывода)
require_once('../swad/config.php');
session_start();

$dbInst = new Database();
$conn = $dbInst->connect();
if (!$conn) {
    die('Ошибка подключения к базе данных');
}

$userId = $_SESSION['USERDATA']['id'] ?? 0;
$sprintId = (int)($_GET['sprint_id'] ?? 0);

// Если нет ID спринта или пользователя – редирект
if (!$sprintId || !$userId) {
    header('Location: /jams');
    exit;
}

// Проверяем, участвует ли пользователь в этом спринте
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
if (!$sprint) {
    die('Спринт не найден');
}

// Загружаем участников (команду)
$teamStmt = $conn->prepare("
    SELECT u.id, u.username, u.role, sp.joined_at
    FROM sprint_participants sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.sprint_id = ?
");
$teamStmt->execute([$sprintId]);
$team = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

// Загружаем сданную работу пользователя (если есть)
$subStmt = $conn->prepare("SELECT * FROM sprint_submissions WHERE sprint_id = ? AND user_id = ?");
$subStmt->execute([$sprintId, $userId]);
$submission = $subStmt->fetch(PDO::FETCH_ASSOC);

// Загружаем объявления
$annStmt = $conn->prepare("SELECT * FROM sprint_announcements WHERE sprint_id = ? ORDER BY created_at DESC");
$annStmt->execute([$sprintId]);
$announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);

// Загружаем критерии
// $criteriaStmt = $conn->prepare("SELECT * FROM sprint_criteria WHERE sprint_id = ? ORDER BY weight DESC");
// $criteriaStmt->execute([$sprintId]);
// $criteria = $criteriaStmt->fetchAll(PDO::FETCH_ASSOC);
// $timezone = new DateTimeZone('Europe/Moscow');

$now = new DateTime('now', $timezone);
$start = new DateTime($sprint['start_at'], $timezone);

$unit = $sprint['duration_unit'];
$val = $sprint['duration_value'];
$end = clone $start;
if ($unit === 'hours') $end->modify("+{$val} hours");
elseif ($unit === 'days') $end->modify("+{$val} days");
elseif ($unit === 'weeks') $end->modify("+{$val} weeks");
elseif ($unit === 'months') $end->modify("+{$val} months");

// Определяем статус
if ($now < $start) {
    $status = 'upcoming';
} elseif ($now >= $start && $now <= $end) {
    $status = 'ongoing';
} else {
    $status = 'finished';
}

// Формируем строку обратного отсчёта (только для будущих событий)
if ($status === 'upcoming') {
    $diff = $start->getTimestamp() - $now->getTimestamp();
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $countdownStr = ($days > 0 ? $days . 'д ' : '') . $hours . 'ч ' . $minutes . 'м';
} else {
    $countdownStr = ($status === 'ongoing') ? 'Идёт' : 'Завершён';
}

// Теперь, когда вся логика выполнена и редиректов больше не будет,
// можно подключать header.php (который выводит начало HTML)
require_once('../swad/static/elements/header.php');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($sprint['title']) ?> — Панель участника</title>
    <style>
        /* Все стили (можно скопировать из вашего шаблона) */
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0d0414;
            font-family: 'Manrope', system-ui, sans-serif;
            color: #e8ddf0;
            background-image: radial-gradient(ellipse 80% 50% at 20% -10%, rgba(195,33,120,.11) 0%, transparent 60%),
                              radial-gradient(ellipse 60% 40% at 80% 110%, rgba(120,20,80,.08) 0%, transparent 55%);
        }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(195,33,120,.3); border-radius: 4px; }
        .sprint-header {
            /* background: rgba(13,4,20,.96); */
            /* border-bottom: 1px solid rgba(195,33,120,.18); */
            padding: 13px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 0;
            backdrop-filter: blur(12px);
        }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: #e8ddf0; }
        .logo .brand { color: #c32178; }
        .nav-btn {
            padding: 7px 15px;
            border-radius: 7px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255,255,255,.05);
            color: rgba(255,255,255,.5);
            transition: .15s;
            text-decoration: none;
            display: inline-block;
        }
        .nav-btn:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .timer-badge {
            background: rgba(195,33,120,.12);
            border: 1px solid rgba(195,33,120,.3);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .participant-layout { display: flex; min-height: calc(100vh - 54px); }
        .sidebar {
            width: 230px;
            flex-shrink: 0;
            background: rgba(0,0,0,.25);
            border-right: 1px solid rgba(255,255,255,.07);
            padding: 20px 12px;
        }
        .sidebar-section {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,.25);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 10px 10px 5px;
            margin-top: 6px;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,.45);
            transition: .15s;
            border: 1px solid transparent;
        }
        .sidebar-item:hover { background: rgba(255,255,255,.05); color: #e8ddf0; }
        .sidebar-item.active {
            background: rgba(195,33,120,.12);
            color: #e8ddf0;
            border-color: rgba(195,33,120,.25);
        }
        .sprint-info-card {
            background: rgba(195,33,120,.07);
            border: 1px solid rgba(195,33,120,.2);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .si-title { font-size: 13px; font-weight: 800; margin-bottom: 3px; }
        .si-host { font-size: 11px; color: rgba(255,255,255,.35); margin-bottom: 10px; }
        .si-stat { font-size: 11px; display: flex; justify-content: space-between; margin-bottom: 4px; }
        .si-stat span { color: #e8ddf0; font-weight: 600; }
        .countdown-mini {
            background: rgba(0,0,0,.3);
            border-radius: 7px;
            padding: 8px;
            text-align: center;
            margin-top: 10px;
        }
        .countdown-mini .cm-val { font-size: 18px; font-weight: 800; color: #c32178; font-variant-numeric: tabular-nums; }
        .main-content { flex: 1; padding: 26px 28px; overflow-y: auto; max-height: calc(100vh - 54px); }
        .view { display: none; }
        .view.active { display: block; }
        .page-title { font-size: 20px; font-weight: 800; margin-bottom: 4px; letter-spacing: -.3px; }
        .page-sub { color: rgba(255,255,255,.35); font-size: 13px; margin-bottom: 24px; }
        .welcome-hero {
            background: linear-gradient(135deg, rgba(195,33,120,.12), rgba(120,20,80,.06));
            border: 1px solid rgba(195,33,120,.2);
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }
        .wh-top { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .wh-avatar {
            width: 52px; height: 52px;
            background: rgba(195,33,120,.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .wh-name { font-size: 18px; font-weight: 800; }
        .wh-status {
            margin-left: auto;
            background: rgba(34,197,94,.1);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,.25);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .wh-progress-row { display: flex; align-items: center; gap: 14px; }
        .wh-prog-bar { flex: 1; height: 6px; background: rgba(255,255,255,.06); border-radius: 99px; overflow: hidden; }
        .wh-prog-fill { height: 100%; background: #c32178; border-radius: 99px; }
        .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card {
            background: rgba(0,0,0,.3);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            padding: 15px;
        }
        .stat-card .sc-val { font-size: 20px; font-weight: 800; margin-bottom: 2px; }
        .stat-card .sc-lbl { font-size: 11px; color: rgba(255,255,255,.35); }
        .card {
            background: rgba(0,0,0,.3);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 14px;
        }
        .card-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            display: flex;
            justify-content: space-between;
        }
        .team-member {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
        }
        .tm-av {
            width: 36px; height: 36px;
            background: rgba(195,33,120,.15);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .tm-name { font-size: 13px; font-weight: 600; }
        .me-badge { font-size: 10px; background: rgba(195,33,120,.2); border-radius: 4px; padding: 1px 6px; margin-left: 6px; }
        .btn-primary {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 8px;
            padding: 11px 22px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-primary:hover { background: #9e1a66; }
        .form-input-s, .form-textarea-s {
            width: 100%;
            background: rgba(0,0,0,.4);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            padding: 9px 13px;
            color: #e8ddf0;
            font-size: 13px;
        }
        .form-label-s { display: block; color: rgba(255,255,255,.4); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-row-s { margin-bottom: 13px; }
        .alert {
            background: rgba(245,158,11,.08);
            border: 1px solid rgba(245,158,11,.2);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 12px;
            color: rgba(245,158,11,.9);
        }
        .alert.success { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.2); color: #22c55e; }
        .criteria-item {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 9px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .ci-head { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .ci-name { font-size: 13px; font-weight: 600; }
        .ci-weight { font-size: 11px; color: #c32178; font-weight: 700; }
        .ci-bar { height: 3px; background: rgba(255,255,255,.06); border-radius: 99px; margin-top: 8px; overflow: hidden; }
        .ci-bar-fill { height: 100%; background: #c32178; border-radius: 99px; }
        .ann-item {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 9px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .ann-item.new-ann { border-color: rgba(195,33,120,.3); background: rgba(195,33,120,.05); }
        .ann-title { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; margin-bottom: 4px; }
        .ann-body { font-size: 12px; color: rgba(255,255,255,.5); line-height: 1.6; margin-bottom: 5px; }
        .ann-meta { font-size: 10px; color: rgba(255,255,255,.25); }
    </style>
</head>
<body>

<header class="sprint-header">
    <div class="logo"><span class="brand"></div>
    <div style="display:flex;align-items:center;gap:10px">
        <div class="timer-badge">⏳ <span id="countdown-header"><?= $countdownStr ?></span></div>
        <a class="nav-btn" href="/jams">← К спринтам</a>
    </div>
</header>

<div class="participant-layout">
    <div class="sidebar">
        <div class="sprint-info-card">
            <div class="si-title"><?= htmlspecialchars($sprint['title']) ?></div>
            <div class="si-host">от <?= htmlspecialchars($sprint['host_name'] ?? 'Dustore') ?></div>
            <div class="si-stat">Участников <span><?= count($team) ?> / <?= $sprint['max_participants'] ?></span></div>
            <div class="si-stat">Длительность <span><?= $sprint['duration_value'] ?> <?= $sprint['duration_unit'] ?></span></div>
            <div class="countdown-mini">
            <?php
                // --- Расчёт статуса и обратного отсчёта (с учётом временной зоны) ---
                $timezone = new DateTimeZone('Europe/Moscow'); // замените на вашу зону при необходимости
                $now = new DateTime('now', $timezone);
                $start = new DateTime($sprint['start_at'], $timezone);
                $end = clone $start;
                $unit = $sprint['duration_unit'];
                $val = $sprint['duration_value'];

                if ($unit === 'hours') $end->modify("+{$val} hours");
                elseif ($unit === 'days') $end->modify("+{$val} days");
                elseif ($unit === 'weeks') $end->modify("+{$val} weeks");
                elseif ($unit === 'months') $end->modify("+{$val} months");

                if ($now < $start) {
                    $status = 'upcoming';
                    $diff = $start->getTimestamp() - $now->getTimestamp();
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    $countdownStr = ($days > 0 ? $days . 'д ' : '') . $hours . 'ч ' . $minutes . 'м';
                    $countdownLabel = 'до старта';
                } elseif ($now >= $start && $now <= $end) {
                    $status = 'ongoing';
                    $diff = $end->getTimestamp() - $now->getTimestamp();
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    $countdownStr = ($days > 0 ? $days . 'д ' : '') . $hours . 'ч ' . $minutes . 'м';
                    $countdownLabel = 'до окончания';
                } else {
                    $status = 'finished';
                    $countdownStr = 'Спринт завершён';
                    $countdownLabel = '';
                }
            ?>
            <div class="cm-val" id="countdown-sidebar"><?= htmlspecialchars($countdownStr) ?></div>
                <?php if ($status === 'upcoming'): ?>
                    <div class="cm-lbl">до старта</div>
                <?php elseif ($status === 'ongoing'): ?>
                    
                    <div class="cm-lbl">до окончания</div>
                <?php endif; ?>
            </div>    
        </div>
        <div class="sidebar-section">Моё участие</div>
        <div class="sidebar-item active" onclick="showView('overview',this)"><span class="ico">🏠</span> Обзор</div>
        <div class="sidebar-item" onclick="showView('team',this)"><span class="ico">👥</span> Команда</div>
        <div class="sidebar-item" onclick="showView('submit',this)"><span class="ico">🚀</span> Сдать работу</div>
        <div class="sidebar-section">Спринт</div>
        <div class="sidebar-item" onclick="showView('scoreboard',this)"><span class="ico">🏆</span> Рейтинг</div>
        <div class="sidebar-item" onclick="showView('criteria',this)"><span class="ico">📋</span> Критерии</div>
        <div class="sidebar-item" onclick="showView('announcements',this)"><span class="ico">📢</span> Объявления</div>
    </div>

    <div class="main-content">
        <!-- Overview -->
        <div class="view active" id="view-overview">
            <div class="welcome-hero">
                <div class="wh-top">
                    <div class="wh-avatar" style='background-image: url("<?= htmlspecialchars($_SESSION['USERDATA']['profile_picture']) ?>"); background-size: cover; background-repeat: no-repeat; background-position: center;'></div>
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
                <div class="stat-card"><div class="sc-val"><?= count($team) ?></div><div class="sc-lbl">Участников команды</div></div>
                <div class="stat-card"><div class="sc-val"><?= $submission ? '1' : '0' ?></div><div class="sc-lbl">Работ сдано</div></div>
            </div>
            <!-- <div class="card"><div class="card-title">📋 Чеклист участника</div><div id="checklist"></div></div> -->
            <div class="card"><div class="card-title">🎯 Тема спринта</div>
                <div style="text-align:center;padding:20px 0">
                    <?php if ($status === 'ongoing' || $status === 'finished'): ?>
                        <div style="font-size:36px">🎲</div>
                        <div style="font-size:15px;font-weight:700"><?= htmlspecialchars($sprint['theme'] ?? 'Тема не указана') ?></div>
                    <?php else: ?>
                        <div style="font-size:36px">🔒</div>
                        <div style="font-size:15px;font-weight:700">Тема скрыта до старта</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Team -->
        <div class="view" id="view-team">
            <div class="page-title">Команда</div>
            <div class="card"><div class="card-title">👥 Состав команды</div>
                <div id="team-list">
                    <?php foreach ($team as $member): ?>
                        <div class="team-member">
                            <div class="tm-av">👤</div>
                            <div><div class="tm-name"><?= htmlspecialchars($member['username']) ?><?= $member['id'] == $userId ? ' <span class="me-badge">Я</span>' : '' ?></div><div class="tm-role"><?= htmlspecialchars($member['role'] ?? 'Участник') ?></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="view" id="view-submit">
            <div class="page-title">Сдать работу</div>
            <?php if ($status !== 'ongoing' && $status !== 'finished'): ?>
                <div class="alert">⚠️ Спринт ещё не начался. Сдача откроется со стартом.</div>
            <?php elseif ($submission): ?>
                <div class="alert success">✅ Вы уже сдали работу. Вы можете её отредактировать.</div>
            <?php endif; ?>
            <form id="submissionForm" method="post" enctype="multipart/form-data" action="/swad/controllers/submit_sprint.php">
                <input type="hidden" name="sprint_id" value="<?= $sprintId ?>">
                <div class="card">
                    <div class="card-title">📝 Карточка работы</div>
                    <div class="form-row-s"><label class="form-label-s">Название игры *</label><input class="form-input-s" name="game_title" value="<?= htmlspecialchars($submission['game_title'] ?? '') ?>" required></div>
                    <div class="form-row-s"><label class="form-label-s">Описание</label><textarea class="form-textarea-s" name="description"><?= htmlspecialchars($submission['description'] ?? '') ?></textarea></div>
                    <div class="form-row-s"><label class="form-label-s">Движок</label><input class="form-input-s" name="engine" value="<?= htmlspecialchars($submission['engine'] ?? '') ?>"></div>
                    <div class="form-row-s"><label class="form-label-s">Резервная сылка на игру (itch.io / Google Drive / Яндекс.Диск)</label><input class="form-input-s" name="external_link" value="<?= htmlspecialchars($submission['external_link'] ?? '') ?>"></div>
                    
                    <div class="form-row-s">
                        <label class="form-label-s">Файл игры</label>

                        <?php if (!empty($submission['build_url'])): ?>
                            <div class="alert success">
                                ✅ Билд загружен
                                <?php if (!empty($submission['build_size'])): ?>
                                    (<?= round($submission['build_size'] / 1024 / 1024, 1) ?> МБ)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div id="jam-drop"
                            style="border:2px dashed rgba(195,33,120,.3);
                                    border-radius:12px;
                                    padding:28px;
                                    text-align:center;
                                    cursor:pointer;">

                            <div style="font-size:32px">📦</div>

                            <div id="jam-label">
                                <?= !empty($submission['build_url'])
                                    ? 'Заменить билд'
                                    : 'Загрузить билд' ?>
                            </div>

                            <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:6px;">
                                До 500 МБ — обычная загрузка<br>
                                Более 500 МБ — загрузка чанками
                            </div>
                        </div>

                        <input
                            type="file"
                            id="jam-file"
                            accept=".zip,.rar,.7z"
                            style="display:none;"
                        >

                        <input
                            type="hidden"
                            name="build_url"
                            id="build_url"
                            value="<?= htmlspecialchars($submission['build_url'] ?? '') ?>"
                        >

                        <div id="jam-progress" style="display:none;margin-top:12px;">
                            <div style="display:flex;justify-content:space-between">
                                <span id="jam-status">Подготовка...</span>
                                <span id="jam-percent">0%</span>
                            </div>

                            <div style="
                                height:8px;
                                background:rgba(255,255,255,.08);
                                border-radius:999px;
                                overflow:hidden;
                                margin-top:6px;
                            ">
                                <div id="jam-bar"
                                    style="
                                        width:0%;
                                        height:100%;
                                        background:#c32178;
                                    ">
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden"
                        name="build_size"
                        id="build_size"
                        value="<?= (int)($submission['build_size'] ?? 0) ?>">
                    <button type="submit" class="btn-primary"><?= $submission ? '✏ Обновить' : '🚀 Сдать работу' ?></button>
                </div>
            </form>
        </div>

        <!-- Scoreboard -->
        <div class="view" id="view-scoreboard">
            <div class="page-title">Рейтинг</div>
            <div class="card">⏳ Рейтинг будет доступен после завершения спринта.</div>
        </div>

        <!-- Criteria -->
        <div class="view" id="view-criteria">
            <div class="page-title">Критерии оценки</div>
            <div class="card"><div class="card-title">📊 Критерии жюри</div>
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
    document.getElementById('checklist').innerHTML = `
        <div style="padding:10px 0;"><span style="display:inline-block;width:18px;">✅</span> Зарегистрироваться</div>
        <div style="padding:10px 0;"><span style="display:inline-block;width:18px;">✅</span> Ознакомиться с правилами</div>
        <div style="padding:10px 0;"><span style="display:inline-block;width:18px;"><?= $submission ? '✅' : '⬜' ?></span> Сдать работу</div>
    `;
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

    if (!file) {
        return;
    }

    uploadBuild(file);
});

async function uploadBuild(file)
{
    const big = file.size >= LARGE;

    const chunkSize = big
        ? LARGE_CHUNK
        : SMALL_CHUNK;

    const totalChunks = Math.ceil(
        file.size / chunkSize
    );

    const progress = document.getElementById('jam-progress');
    const bar      = document.getElementById('jam-bar');
    const percent  = document.getElementById('jam-percent');
    const status   = document.getElementById('jam-status');

    progress.style.display = 'block';

    for(let i = 0; i < totalChunks; i++)
    {
        const fd = new FormData();

        fd.append(
            'chunk',
            file.slice(
                i * chunkSize,
                (i + 1) * chunkSize
            )
        );

        fd.append('chunk_index', i);
        fd.append('total_chunks', totalChunks);
        fd.append('file_name', file.name);
        fd.append('file_size', file.size);

        fd.append('sprint_id', SPRINT_ID);

        const res = await fetch(
            '/swad/controllers/jams/upload_chunk.php',
            {
                method: 'POST',
                body: fd,
                credentials: 'include'
            }
        );

        const data = await res.json();

        if (!data.success)
        {
            alert(data.message);
            return;
        }

        const p = Math.round(
            ((i + 1) / totalChunks) * 100
        );

        bar.style.width = p + '%';
        percent.textContent = p + '%';

        status.textContent =
            'Чанк ' +
            (i + 1) +
            ' из ' +
            totalChunks;

        if(data.done)
        {
            document.getElementById('build_url').value =
                data.url;

            document.getElementById('build_size').value =
                file.size;

            status.textContent =
                '✅ Билд загружен';

            break;
        }
    }
}
</script>
</body>
</html>