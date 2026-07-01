<?php
/**
 * Мобильная версия страницы спринтов
 * Доступна по URL /m/sprints
 */

require_once(__DIR__ . '/../../swad/config.php');
require_once(__DIR__ . '/../../swad/controllers/user.php');

$dbInst = new Database();
$conn = $dbInst->connect();
if (!$conn) die('Ошибка подключения к БД');

// Получаем все спринты
$stmt = $conn->query("
    SELECT s.*,
           u.username as host_name,
           (SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = s.id) as current_participants
    FROM sprints s
    LEFT JOIN users u ON s.host_user_id = u.id
    ORDER BY s.created_at DESC
");
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подгружаем призы и экспертов
foreach ($sprints as &$s) {
    $prizeStmt = $conn->prepare("SELECT place_num, reward FROM sprint_prizes WHERE sprint_id = ? ORDER BY place_num");
    $prizeStmt->execute([$s['id']]);
    $s['prizes'] = $prizeStmt->fetchAll(PDO::FETCH_ASSOC);

    $expStmt = $conn->prepare("
        SELECT u.id, u.username, u.role
        FROM sprint_experts se
        JOIN users u ON se.user_id = u.id
        WHERE se.sprint_id = ?
    ");
    $expStmt->execute([$s['id']]);
    $s['experts'] = $expStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($s);

// Фазы
function getPhaseMobile($s) {
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $regStart = isset($s['registration_start']) ? new DateTime($s['registration_start'], new DateTimeZone('Europe/Moscow')) : null;
    $regEnd   = isset($s['registration_end']) ? new DateTime($s['registration_end'], new DateTimeZone('Europe/Moscow')) : null;
    $jamStart = isset($s['jam_start']) ? new DateTime($s['jam_start'], new DateTimeZone('Europe/Moscow')) : null;
    $jamEnd   = isset($s['jam_end']) ? new DateTime($s['jam_end'], new DateTimeZone('Europe/Moscow')) : null;
    $voteStart = isset($s['voting_start']) ? new DateTime($s['voting_start'], new DateTimeZone('Europe/Moscow')) : null;
    $voteEnd   = isset($s['voting_end']) ? new DateTime($s['voting_end'], new DateTimeZone('Europe/Moscow')) : null;

    if ($regStart && $now < $regStart) return ['upcoming', 'Скоро'];
    if ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) return ['registration', 'Регистрация'];
    if ($regEnd && $jamStart && $now >= $regEnd && $now < $jamStart) return ['pre_jam', 'Скоро джем'];
    if ($jamStart && $jamEnd && $now >= $jamStart && $now < $jamEnd) return ['jam', 'Джем'];
    if ($jamEnd && $voteStart && $now >= $jamEnd && $now < $voteStart) return ['post_jam', 'Джем окончен'];
    if ($voteStart && $voteEnd && $now >= $voteStart && $now < $voteEnd) return ['voting', 'Голосование'];
    return ['finished', 'Завершён'];
}

foreach ($sprints as &$s) {
    [$s['phase'], $s['phase_label']] = getPhaseMobile($s);
}
unset($s);

// ID спринтов, в которых участвует текущий пользователь
$userSprintIds = [];
$userId = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = $_SESSION['USERDATA']['id'];
    $partStmt = $conn->prepare("SELECT sprint_id FROM sprint_participants WHERE user_id = ?");
    $partStmt->execute([$userId]);
    $userSprintIds = $partStmt->fetchAll(PDO::FETCH_COLUMN);
}

$currentUsername = $_SESSION['USERDATA']['username'] ?? 'Участник';

// Все пользователи для выбора экспертов (в модалке создания)
$usersStmt = $conn->query("SELECT id, username, role FROM users ORDER BY username");
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Определяем ID спринта из GET для открытия модалки
$sprintFromGet = isset($_GET['sprint']) ? (int)$_GET['sprint'] : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Спринты — Dustore</title>
    <link rel="stylesheet" href="/swad/css/mobile-base.css">
    <style>
        /* ===== Стили специфичные для мобильных спринтов ===== */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0d0414;
            font-family: -apple-system, 'Manrope', system-ui, sans-serif;
            color: #e8ddf0;
            padding-bottom: 30px;
        }
        .m-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 14px;
        }
        .m-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 16px;
        }
        .m-header .logo {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.5px;
        }
        .m-header .logo span { color: #c32178; }
        .m-header .actions {
            display: flex;
            gap: 8px;
        }
        .m-header .actions button {
            background: rgba(255,255,255,0.06);
            border: none;
            color: #e8ddf0;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }
        .m-header .actions button.primary {
            background: #c32178;
            color: #fff;
        }
        .m-header .actions button.primary:active { background: #9e1a66; }

        /* Поисковая пилюля */
        .search-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 30px;
            padding: 8px 16px;
            margin-bottom: 16px;
            color: rgba(255,255,255,0.5);
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .search-pill:active { background: rgba(255,255,255,0.12); }
        .search-pill i { font-size: 18px; }

        /* Фильтры (чипсы) */
        .chip-group {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding: 4px 0 12px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .chip-group::-webkit-scrollbar { display: none; }
        .chip {
            flex-shrink: 0;
            padding: 6px 14px;
            border-radius: 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            transition: 0.15s;
            white-space: nowrap;
        }
        .chip.active {
            background: rgba(195,33,120,0.2);
            border-color: rgba(195,33,120,0.4);
            color: #fff;
        }
        .chip:active { transform: scale(0.95); }

        /* Карточка спринта (горизонтальный скролл) */
        .h-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 4px 0 16px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .h-scroll::-webkit-scrollbar { display: none; }
        .sprint-card {
            flex: 0 0 280px;
            scroll-snap-align: start;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: 0.15s;
        }
        .sprint-card:active { transform: scale(0.97); }
        .sprint-card .cover {
            height: 120px;
            background: #1a0a24;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .sprint-card .cover .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
        }
        .sprint-card .info {
            padding: 12px 14px;
        }
        .sprint-card .info .title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sprint-card .info .host {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 6px;
        }
        .sprint-card .info .meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        .sprint-card .info .meta span { display: flex; align-items: center; gap: 4px; }

        /* Секция «Все спринты» (сетка) */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 10px;
        }
        .section-header .title {
            font-weight: 700;
            font-size: 18px;
        }
        .section-header .link {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
        }
        .mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }
        .mini-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: 0.15s;
        }
        .mini-card:active { transform: scale(0.96); }
        .mini-card .cover {
            height: 80px;
            background: #1a0a24;
            background-size: cover;
            background-position: center;
        }
        .mini-card .info {
            padding: 8px 10px;
        }
        .mini-card .info .title {
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mini-card .info .host {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }
        .mini-card .info .phase {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 2px;
        }

        /* Модалки */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.open { display: flex; }
        .modal-sheet {
            background: #160822;
            border-radius: 20px 20px 0 0;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.06);
            border-bottom: none;
        }
        .modal-sheet .handle {
            width: 40px;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            margin: 0 auto 16px;
        }
        .modal-sheet .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .modal-sheet .head .title {
            font-size: 20px;
            font-weight: 800;
        }
        .modal-sheet .head .close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            font-size: 24px;
            cursor: pointer;
            padding: 0 4px;
        }
        .modal-sheet .body { color: rgba(255,255,255,0.7); line-height: 1.6; font-size: 14px; }
        .modal-sheet .body .desc { margin-bottom: 12px; }
        .modal-sheet .body .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 12px;
            margin: 12px 0;
        }
        .modal-sheet .body .meta-grid .item {
            text-align: center;
        }
        .modal-sheet .body .meta-grid .item .label {
            font-size: 10px;
            color: rgba(255,255,255,0.3);
            text-transform: uppercase;
        }
        .modal-sheet .body .meta-grid .item .value {
            font-weight: 600;
            font-size: 14px;
        }
        .modal-sheet .actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .modal-sheet .actions button {
            flex: 1;
            padding: 10px 0;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            background: rgba(255,255,255,0.06);
            color: #e8ddf0;
            cursor: pointer;
            transition: 0.15s;
        }
        .modal-sheet .actions button.primary {
            background: #c32178;
            color: #fff;
        }
        .modal-sheet .actions button.primary:active { background: #9e1a66; }
        .modal-sheet .actions button:active { transform: scale(0.96); }

        /* Форма создания */
        .form-group { margin-bottom: 12px; }
        .form-group label {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 4px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.4);
            color: #e8ddf0;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #c32178;
        }
        .form-group textarea { min-height: 60px; resize: vertical; }
        .form-row { display: flex; gap: 8px; align-items: center; }
        .form-row .remove { color: rgba(255,255,255,0.4); background: none; border: none; font-size: 18px; cursor: pointer; }

        .btn-add {
            background: rgba(255,255,255,0.06);
            border: 1px dashed rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 6px 12px;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            cursor: pointer;
            width: 100%;
        }
        .btn-add:active { background: rgba(255,255,255,0.1); }

        .tab-bar {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 16px;
        }
        .tab-bar .tab {
            flex: 1;
            text-align: center;
            padding: 8px 0;
            font-weight: 600;
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .tab-bar .tab.active {
            color: #e8ddf0;
            border-bottom-color: #c32178;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Адаптив */
        @media (min-width: 480px) {
            .sprint-card { flex: 0 0 300px; }
            .mini-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 380px) {
            .sprint-card { flex: 0 0 240px; }
            .mini-grid { grid-template-columns: 1fr 1fr; }
        }
        /* Лунная тема */
        body.moonlight-theme {
            background: #05020a;
            background-image: url("/swad/static/img/Moonlight_pict.jpeg");
            background-size: cover;
            background-attachment: fixed;
            background-position: center 35%;
        }
        body.moonlight-theme .modal-sheet {
            background: rgba(10,19,37,0.95);
            backdrop-filter: blur(12px);
            border-color: rgba(255,255,255,0.1);
        }
        body.moonlight-theme .sprint-card,
        body.moonlight-theme .mini-card {
            background: rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.08);
        }
        body.moonlight-theme .chip.active {
            background: rgba(40,86,130,0.3);
            border-color: rgba(40,86,130,0.5);
        }
        body.moonlight-theme .m-header .logo span { color: #4a9eff; }
        body.moonlight-theme .m-header .actions button.primary {
            background: #285682;
        }
        body.moonlight-theme .m-header .actions button.primary:active {
            background: #193753;
        }
        body.moonlight-theme .modal-sheet .actions button.primary {
            background: #285682;
        }
        body.moonlight-theme .modal-sheet .actions button.primary:active {
            background: #193753;
        }
        body.moonlight-theme .form-group input:focus,
        body.moonlight-theme .form-group textarea:focus,
        body.moonlight-theme .form-group select:focus {
            border-color: #4a9eff;
        }
        body.moonlight-theme .tab-bar .tab.active {
            border-bottom-color: #4a9eff;
        }
    </style>
</head>
<body>

<div class="m-container">
    

    <!-- Поиск -->
    <div class="search-pill" onclick="location.href='/m/search'">
        <i>🔍</i> <span>Поиск спринтов...</span>
    </div>

    <!-- Фильтры -->
    <div class="chip-group" id="filterChips">
        <div class="chip active" data-filter="all">Все</div>
        <div class="chip" data-filter="registration">Регистрация</div>
        <div class="chip" data-filter="jam">Джем</div>
        <div class="chip" data-filter="voting">Голосование</div>
        <div class="chip" data-filter="finished">Завершены</div>
    </div>

    <!-- Горизонтальный список (активные/популярные) -->
    <div class="section-header">
        <span class="title">Активные</span>
        <a href="#" class="link" onclick="document.getElementById('allGrid').scrollIntoView({behavior:'smooth'})">Все →</a>
    </div>
    <div class="h-scroll" id="featuredScroll"></div>

    <!-- Сетка всех спринтов -->
    <div class="section-header" id="allGrid">
        <span class="title">Все спринты</span>
    </div>
    <div class="mini-grid" id="allGridContainer"></div>

    <!-- Пустое состояние -->
    <div id="emptyState" style="display:none; text-align:center; padding:40px 0; color:rgba(255,255,255,0.3);">
        <div style="font-size:48px;">🔍</div>
        <p style="margin-top:8px;">Спринты не найдены</p>
    </div>
</div>

<!-- Модалка просмотра -->
<div class="modal-overlay" id="viewOverlay" onclick="closeModal(event, 'viewOverlay')">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="handle"></div>
        <div class="head">
            <div class="title" id="modalTitle">Название</div>
            <button class="close" onclick="closeModal(null, 'viewOverlay')">✕</button>
        </div>
        <div class="body" id="modalBody">
            <div class="desc" id="modalDesc"></div>
            <div class="meta-grid" id="modalMeta"></div>
            <div id="modalExtra"></div>
        </div>
        <div class="actions" id="modalActions"></div>
    </div>
</div>

<!-- Модалка регистрации -->
<div class="modal-overlay" id="registerOverlay" onclick="closeModal(event, 'registerOverlay')">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="handle"></div>
        <div class="head">
            <div class="title">Регистрация</div>
            <button class="close" onclick="closeModal(null, 'registerOverlay')">✕</button>
        </div>
        <div class="body">
            <form id="registerForm" onsubmit="submitRegistration(event)">
                <input type="hidden" id="regSprintId">
                <div class="form-group">
                    <label>Тип участия</label>
                    <div style="display:flex; gap:16px; margin-top:4px;">
                        <label><input type="radio" name="pType" value="solo" checked onchange="toggleTeamFields()"> Соло</label>
                        <label><input type="radio" name="pType" value="team" onchange="toggleTeamFields()"> Команда</label>
                    </div>
                </div>
                <div id="soloFields">
                    <div class="form-group"><label>Псевдоним</label><input id="regAlias" value="<?=htmlspecialchars($currentUsername)?>"></div>
                    <div class="form-group"><label>Город</label><input id="regCity" placeholder="Город"></div>
                    <div class="form-group"><label>О себе</label><textarea id="regExtra" rows="2" placeholder="Навыки, опыт..."></textarea></div>
                    <div class="form-group"><label>Ссылки (каждая с новой строки)</label><textarea id="regLinks" rows="2" placeholder="https://..."></textarea></div>
                </div>
                <div id="teamFields" style="display:none;">
                    <p style="color:rgba(255,255,255,0.5); font-size:13px;">Заполните информацию о себе как участнике команды.</p>
                    <div class="form-group"><label>Ваш псевдоним</label><input id="regTeamAlias" value="<?=htmlspecialchars($currentUsername)?>"></div>
                    <div class="form-group"><label>Город</label><input id="regTeamCity" placeholder="Город"></div>
                    <div class="form-group"><label>О себе</label><textarea id="regTeamExtra" rows="2" placeholder="Роль в команде..."></textarea></div>
                    <div class="form-group"><label>Ссылки</label><textarea id="regTeamLinks" rows="2" placeholder="https://..."></textarea></div>
                </div>
                <button type="submit" style="width:100%; margin-top:8px;" class="primary">Зарегистрироваться</button>
            </form>
        </div>
    </div>
</div>

<!-- Модалка создания спринта -->
<div class="modal-overlay" id="createOverlay" onclick="closeModal(event, 'createOverlay')">
    <div class="modal-sheet" onclick="event.stopPropagation()">
        <div class="handle"></div>
        <div class="head">
            <div class="title">Создать спринт</div>
            <button class="close" onclick="closeModal(null, 'createOverlay')">✕</button>
        </div>
        <div class="body">
            <div class="tab-bar" id="createTabs">
                <div class="tab active" data-step="1">Основное</div>
                <div class="tab" data-step="2">Даты</div>
                <div class="tab" data-step="3">Призы & эксперты</div>
            </div>
            <!-- Шаг 1 -->
            <div class="tab-panel active" id="step1">
                <div class="form-group"><label>Логотип</label><input type="file" id="fLogo" accept="image/*"></div>
                <div class="form-group"><label>Название *</label><input id="fTitle" placeholder="Название спринта"></div>
                <div class="form-group"><label>Описание *</label><textarea id="fDesc" rows="3"></textarea></div>
                <div class="form-group"><label>Тема</label><input id="fTheme" placeholder="Киберпанк, Выживание..."></div>
                <div class="form-group"><label>Теги (через запятую)</label><input id="fTags" placeholder="Unity, 48h..."></div>
                <div class="form-group"><label>Регламент (Markdown)</label><textarea id="fRules" rows="3"></textarea></div>
                <div class="form-group"><label>Полезные ссылки (каждая с новой строки)</label><textarea id="fLinks" rows="2"></textarea></div>
                <button class="btn-add" onclick="goStep(2)">Далее →</button>
            </div>
            <!-- Шаг 2 -->
            <div class="tab-panel" id="step2">
                <div class="form-group"><label>Регистрация с</label><input type="datetime-local" id="fRegStart"></div>
                <div class="form-group"><label>Регистрация до</label><input type="datetime-local" id="fRegEnd"></div>
                <div class="form-group"><label>Начало джема</label><input type="datetime-local" id="fJamStart"></div>
                <div class="form-group"><label>Окончание джема</label><input type="datetime-local" id="fJamEnd"></div>
                <div class="form-group"><label>Голосование с</label><input type="datetime-local" id="fVoteStart"></div>
                <div class="form-group"><label>Голосование до</label><input type="datetime-local" id="fVoteEnd"></div>
                <div class="form-group"><label>Макс. участников</label><input type="number" id="fMaxp" value="100"></div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-add" onclick="goStep(1)">← Назад</button>
                    <button class="btn-add" onclick="goStep(3)">Далее →</button>
                </div>
            </div>
            <!-- Шаг 3 -->
            <div class="tab-panel" id="step3">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-weight:600; font-size:14px;">Призы</span>
                    <button class="btn-add" style="width:auto;" onclick="addPrize()">+ Добавить</button>
                </div>
                <div id="prizesList"></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin:12px 0 8px;">
                    <span style="font-weight:600; font-size:14px;">Эксперты</span>
                    <button class="btn-add" style="width:auto;" onclick="addExpert()">+ Добавить</button>
                </div>
                <div id="expertsList"></div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn-add" onclick="goStep(2)">← Назад</button>
                    <button class="btn-add primary" onclick="submitSprint()" style="background:#c32178; color:#fff;">Опубликовать</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Данные из PHP
    const sprintsData = <?= json_encode($sprints, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const userSprintIds = <?= json_encode($userSprintIds) ?>;
    const allUsers = <?= json_encode($allUsers) ?>;
    const currentUsername = <?= json_encode($currentUsername) ?>;
    const sprintFromGet = <?= (int)$sprintFromGet ?>;

    let sprints = sprintsData;
    let curFilter = 'all';
    let prizes = [{ place: '1', reward: '' }];
    let selectedExperts = [];

    // Функции отображения
    function renderFeatured() {
        const container = document.getElementById('featuredScroll');
        const active = sprints.filter(s => s.phase !== 'finished').slice(0, 5);
        if (active.length === 0) {
            container.innerHTML = '<div style="padding:10px 0; color:rgba(255,255,255,0.3);">Нет активных спринтов</div>';
            return;
        }
        container.innerHTML = active.map(s => cardHTML(s, 'horizontal')).join('');
    }

    function renderAll() {
        const container = document.getElementById('allGridContainer');
        const filtered = sprints.filter(s => curFilter === 'all' || s.phase === curFilter);
        if (filtered.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
            container.innerHTML = '';
            return;
        }
        document.getElementById('emptyState').style.display = 'none';
        container.innerHTML = filtered.map(s => cardHTML(s, 'mini')).join('');
    }

    function cardHTML(sprint, type) {
        const phaseLabel = sprint.phase_label || sprint.phase;
        const cover = sprint.logo_url ? `background-image:url('${escapeHtml(sprint.logo_url)}')` : '';
        const participants = sprint.current_participants || 0;
        const maxp = sprint.max_participants || 100;
        const pct = Math.min(100, Math.round((participants / maxp) * 100));

        if (type === 'horizontal') {
            return `
                <div class="sprint-card" onclick="openView(${sprint.id})">
                    <div class="cover" style="${cover}">
                        <span class="badge">${phaseLabel}</span>
                    </div>
                    <div class="info">
                        <div class="title">${escapeHtml(sprint.title)}</div>
                        <div class="host">${escapeHtml(sprint.host_name || 'Dustore')}</div>
                        <div class="meta">
                            <span>👥 ${participants}/${maxp}</span>
                            <span>${sprint.jam_start ? formatDate(sprint.jam_start) : ''}</span>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // мини-карточка для сетки
            return `
                <div class="mini-card" onclick="openView(${sprint.id})">
                    <div class="cover" style="${cover}"></div>
                    <div class="info">
                        <div class="title">${escapeHtml(sprint.title)}</div>
                        <div class="host">${escapeHtml(sprint.host_name || 'Dustore')}</div>
                        <div class="phase">${phaseLabel} · ${participants} уч.</div>
                    </div>
                </div>
            `;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m] || m);
    }

    function formatDate(d) {
        if (!d) return '';
        const dt = new Date(d);
        return dt.toLocaleDateString('ru-RU', {day:'numeric', month:'short'});
    }

    // Фильтры
    document.getElementById('filterChips').addEventListener('click', function(e) {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        curFilter = chip.dataset.filter;
        renderAll();
    });

    // Модалка просмотра
    function openView(id) {
        const sprint = sprints.find(s => s.id == id);
        if (!sprint) return;
        const overlay = document.getElementById('viewOverlay');
        document.getElementById('modalTitle').textContent = sprint.title;
        document.getElementById('modalDesc').innerHTML = markdownToHtml(sprint.description || 'Описание отсутствует');

        // Мета
        const meta = document.getElementById('modalMeta');
        meta.innerHTML = `
            <div class="item"><div class="label">Статус</div><div class="value">${sprint.phase_label || sprint.phase}</div></div>
            <div class="item"><div class="label">Участники</div><div class="value">${sprint.current_participants || 0}/${sprint.max_participants}</div></div>
            <div class="item"><div class="label">Джем</div><div class="value">${sprint.jam_start ? formatDate(sprint.jam_start) : '—'}</div></div>
            <div class="item"><div class="label">Голосование</div><div class="value">${sprint.voting_start ? formatDate(sprint.voting_start) : '—'}</div></div>
        `;

        // Доп. инфо (тема, призы, эксперты)
        let extra = '';
        if (sprint.theme) extra += `<p><strong>Тема:</strong> ${escapeHtml(sprint.theme)}</p>`;
        if (sprint.tags) extra += `<p><strong>Теги:</strong> ${escapeHtml(sprint.tags)}</p>`;
        if (sprint.prizes && sprint.prizes.length) {
            extra += `<p><strong>Призы:</strong> ${sprint.prizes.map(p => `${p.place_num} место: ${escapeHtml(p.reward)}`).join(', ')}</p>`;
        }
        if (sprint.experts && sprint.experts.length) {
            extra += `<p><strong>Эксперты:</strong> ${sprint.experts.map(e => escapeHtml(e.username)).join(', ')}</p>`;
        }
        document.getElementById('modalExtra').innerHTML = extra;

        // Действия
        const actions = document.getElementById('modalActions');
        const isJoined = userSprintIds.includes(sprint.id);
        let btns = '';
        if (isJoined) {
            btns += `<button onclick="location.href='/m/participant?sprint_id=${sprint.id}'">Панель участника</button>`;
        } else {
            const canJoin = ['registration','upcoming','pre_jam'].includes(sprint.phase);
            if (canJoin) {
                btns += `<button class="primary" onclick="startJoin(${sprint.id})">Участвовать</button>`;
            } else {
                btns += `<button disabled>Регистрация закрыта</button>`;
            }
        }
        if (sprint.phase === 'voting') {
            btns += `<button onclick="location.href='/m/rate?sprint=${sprint.id}'">Оценить</button>`;
        }
        btns += `<button onclick="shareSprint(${sprint.id})">Поделиться</button>`;
        actions.innerHTML = btns;

        overlay.classList.add('open');
        if (history.pushState) {
            history.pushState({sprint: id}, '', window.location.pathname + '?sprint=' + id);
        }
    }

    function closeModal(e, id) {
        if (e && e.target !== document.getElementById(id)) return;
        document.getElementById(id).classList.remove('open');
        if (id === 'viewOverlay' && history.pushState) {
            history.pushState({}, '', window.location.pathname);
        }
    }

    // Регистрация
    function startJoin(sprintId) {
        const sprint = sprints.find(s => s.id == sprintId);
        if (!sprint) return;
        // Если есть регламент, можно показать короткое согласие, но для мобилки пропускаем или показываем в модалке
        if (sprint.rules) {
            if (!confirm('Ознакомьтесь с регламентом:\n\n' + sprint.rules + '\n\nПродолжить?')) return;
        }
        document.getElementById('regSprintId').value = sprintId;
        document.getElementById('regAlias').value = currentUsername;
        document.getElementById('regTeamAlias').value = currentUsername;
        document.getElementById('registerOverlay').classList.add('open');
    }

    function toggleTeamFields() {
        const type = document.querySelector('input[name="pType"]:checked').value;
        document.getElementById('soloFields').style.display = (type === 'solo') ? 'block' : 'none';
        document.getElementById('teamFields').style.display = (type === 'team') ? 'block' : 'none';
    }

    async function submitRegistration(e) {
        e.preventDefault();
        const sprintId = document.getElementById('regSprintId').value;
        const type = document.querySelector('input[name="pType"]:checked').value;
        const data = new FormData();
        data.append('sprint_id', sprintId);
        data.append('participant_type', type);
        if (type === 'solo') {
            data.append('alias', document.getElementById('regAlias').value.trim() || currentUsername);
            data.append('city', document.getElementById('regCity').value.trim());
            data.append('extra_info', document.getElementById('regExtra').value.trim());
            data.append('links', document.getElementById('regLinks').value.trim());
        } else {
            data.append('alias', document.getElementById('regTeamAlias').value.trim() || currentUsername);
            data.append('city', document.getElementById('regTeamCity').value.trim());
            data.append('extra_info', document.getElementById('regTeamExtra').value.trim());
            data.append('links', document.getElementById('regTeamLinks').value.trim());
        }
        try {
            const resp = await fetch('/swad/controllers/jams/join_sprint.php', { method: 'POST', body: data });
            const result = await resp.json();
            if (result.success) {
                alert('Вы зарегистрированы!');
                closeModal(null, 'registerOverlay');
                // Обновляем данные
                const idx = sprints.findIndex(s => s.id == sprintId);
                if (idx !== -1) sprints[idx].current_participants = result.new_count;
                userSprintIds.push(parseInt(sprintId));
                renderFeatured();
                renderAll();
                openView(sprintId);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (err) {
            alert('Ошибка соединения');
        }
    }

    // Создание спринта
    function openCreate() {
        prizes = [{ place: '1', reward: '' }];
        selectedExperts = [];
        document.getElementById('fTitle').value = '';
        document.getElementById('fDesc').value = '';
        document.getElementById('fTheme').value = '';
        document.getElementById('fTags').value = '';
        document.getElementById('fRules').value = '';
        document.getElementById('fLinks').value = '';
        document.getElementById('fRegStart').value = '';
        document.getElementById('fRegEnd').value = '';
        document.getElementById('fJamStart').value = '';
        document.getElementById('fJamEnd').value = '';
        document.getElementById('fVoteStart').value = '';
        document.getElementById('fVoteEnd').value = '';
        document.getElementById('fMaxp').value = '100';
        document.getElementById('fLogo').value = '';
        buildPrizes();
        renderExperts();
        goStep(1);
        document.getElementById('createOverlay').classList.add('open');
    }

    function goStep(n) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('#createTabs .tab').forEach(t => t.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');
        document.querySelector('#createTabs .tab[data-step="' + n + '"]').classList.add('active');
    }

    function buildPrizes() {
        const container = document.getElementById('prizesList');
        container.innerHTML = prizes.map((p, i) => `
            <div class="form-row">
                <span style="font-size:18px;">${['🥇','🥈','🥉'][i] || '🎖'}</span>
                <input style="flex:1;" value="${escapeHtml(p.reward)}" placeholder="Приз за ${p.place} место" oninput="prizes[${i}].reward = this.value">
                ${i > 0 ? `<button class="remove" onclick="removePrize(${i})">✕</button>` : ''}
            </div>
        `).join('');
    }

    function addPrize() { prizes.push({ place: String(prizes.length+1), reward: '' }); buildPrizes(); }
    function removePrize(idx) { prizes.splice(idx, 1); prizes.forEach((p,i)=>p.place=String(i+1)); buildPrizes(); }

    function renderExperts() {
        const container = document.getElementById('expertsList');
        if (selectedExperts.length === 0) {
            container.innerHTML = '<div style="color:rgba(255,255,255,0.3);padding:8px 0;">Нет экспертов</div>';
            return;
        }
        container.innerHTML = selectedExperts.map((id, idx) => `
            <div class="form-row">
                <select style="flex:1;" onchange="selectedExperts[${idx}]=this.value">
                    <option value="">-- Выберите --</option>
                    ${allUsers.map(u => `<option value="${u.id}" ${u.id == id ? 'selected' : ''}>${escapeHtml(u.username)}</option>`).join('')}
                </select>
                <button class="remove" onclick="removeExpert(${idx})">✕</button>
            </div>
        `).join('');
    }

    function addExpert() { selectedExperts.push(''); renderExperts(); }
    function removeExpert(idx) { selectedExperts.splice(idx, 1); renderExperts(); }

    async function submitSprint() {
        const title = document.getElementById('fTitle').value.trim();
        const desc = document.getElementById('fDesc').value.trim();
        if (!title || !desc) { alert('Заполните название и описание'); return; }
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('theme', document.getElementById('fTheme').value.trim());
        formData.append('tags', document.getElementById('fTags').value.trim());
        formData.append('rules', document.getElementById('fRules').value.trim());
        formData.append('useful_links', document.getElementById('fLinks').value.trim());
        formData.append('registration_start', document.getElementById('fRegStart').value);
        formData.append('registration_end', document.getElementById('fRegEnd').value);
        formData.append('jam_start', document.getElementById('fJamStart').value);
        formData.append('jam_end', document.getElementById('fJamEnd').value);
        formData.append('voting_start', document.getElementById('fVoteStart').value);
        formData.append('voting_end', document.getElementById('fVoteEnd').value);
        formData.append('max_participants', document.getElementById('fMaxp').value);
        const logo = document.getElementById('fLogo').files[0];
        if (logo) formData.append('logo', logo);
        formData.append('prizes', JSON.stringify(prizes.filter(p => p.reward.trim())));
        formData.append('experts', JSON.stringify(selectedExperts.filter(id => id)));
        try {
            const resp = await fetch('/swad/controllers/create_sprint.php', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) {
                alert('Спринт создан!');
                closeModal(null, 'createOverlay');
                location.reload();
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (err) {
            alert('Ошибка: ' + err.message);
        }
    }

    // Поделиться
    function shareSprint(id) {
        const url = window.location.origin + window.location.pathname + '?sprint=' + id;
        if (navigator.share) {
            navigator.share({ title: 'Спринт', url: url });
        } else {
            navigator.clipboard.writeText(url).then(() => alert('Ссылка скопирована'));
        }
    }

    // Markdown → HTML (упрощённый)
    function markdownToHtml(text) {
        if (!text) return '';
        let html = escapeHtml(text);
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        html = html.replace(/(?<![">])(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        html = html.replace(/^\- (.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        renderFeatured();
        renderAll();

        // Открытие спринта из GET
        if (sprintFromGet) {
            setTimeout(() => openView(sprintFromGet), 300);
        }

        // Обработка кнопки "Назад" (popstate)
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.sprint) {
                openView(e.state.sprint);
            } else {
                closeModal(null, 'viewOverlay');
            }
        });
    });

    // Закрытие модалок по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ['viewOverlay','registerOverlay','createOverlay'].forEach(id => {
                const el = document.getElementById(id);
                if (el.classList.contains('open')) el.classList.remove('open');
            });
        }
    });
</script>

</body>
</html>