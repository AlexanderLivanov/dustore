<?php
// ========== НАЧАЛО: ПОДКЛЮЧЕНИЕ К БД И ПОЛУЧЕНИЕ ДАННЫХ ==========
require_once('../../swad/config.php');

$db = (new Database())->connect();
if (!$db) die('Ошибка подключения к БД');

// ID спринта – из GET или по умолчанию 12
$sprint_id = isset($_GET['sprint']) ? (int)$_GET['sprint'] : 12;

// Получаем данные спринта
$stmt = $db->prepare("
    SELECT s.*,
           u.username as host_name,
           (SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = s.id) as participant_count
    FROM sprints s
    LEFT JOIN users u ON s.host_user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$sprint_id]);
$sprint = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Спринт не найден');

// Призы и эксперты
$prizeStmt = $db->prepare("SELECT place_num, reward FROM sprint_prizes WHERE sprint_id = ? ORDER BY place_num");
$prizeStmt->execute([$sprint_id]);
$prizes = $prizeStmt->fetchAll(PDO::FETCH_ASSOC);

$expStmt = $db->prepare("
    SELECT u.username, u.role
    FROM sprint_experts se
    JOIN users u ON se.user_id = u.id
    WHERE se.sprint_id = ?
");
$expStmt->execute([$sprint_id]);
$experts = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// Функция определения фазы
function getPhase($s) {
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $regStart = isset($s['registration_start']) ? new DateTime($s['registration_start'], new DateTimeZone('Europe/Moscow')) : null;
    $regEnd   = isset($s['registration_end']) ? new DateTime($s['registration_end'], new DateTimeZone('Europe/Moscow')) : null;
    $jamStart = isset($s['jam_start']) ? new DateTime($s['jam_start'], new DateTimeZone('Europe/Moscow')) : null;
    $jamEnd   = isset($s['jam_end']) ? new DateTime($s['jam_end'], new DateTimeZone('Europe/Moscow')) : null;
    $voteStart = isset($s['voting_start']) ? new DateTime($s['voting_start'], new DateTimeZone('Europe/Moscow')) : null;
    $voteEnd   = isset($s['voting_end']) ? new DateTime($s['voting_end'], new DateTimeZone('Europe/Moscow')) : null;

    if ($regStart && $now < $regStart) return 'upcoming';
    if ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) return 'registration';
    if ($regEnd && $jamStart && $now >= $regEnd && $now < $jamStart) return 'pre_jam';
    if ($jamStart && $jamEnd && $now >= $jamStart && $now < $jamEnd) return 'jam';
    if ($jamEnd && $voteStart && $now >= $jamEnd && $now < $voteStart) return 'post_jam';
    if ($voteStart && $voteEnd && $now >= $voteStart && $now < $voteEnd) return 'voting';
    return 'finished';
}
$phase = getPhase($sprint);
$phaseLabels = [
    'registration' => 'Регистрация',
    'upcoming' => 'Скоро',
    'pre_jam' => 'Скоро джем',
    'jam' => 'Джем',
    'post_jam' => 'Завершён джем',
    'voting' => 'Голосование',
    'finished' => 'Завершён'
];
$phaseText = $phaseLabels[$phase] ?? $phase;
$badgeClass = $phase === 'registration' ? 'registration' : ($phase === 'jam' ? 'jam' : ($phase === 'voting' ? 'voting' : ($phase === 'finished' ? 'finished' : '')));

// Форматирование дат
function formatDate($d) {
    return $d ? date('d.m.Y', strtotime($d)) : '—';
}
$registration_start = $sprint['registration_start'] ? formatDate($sprint['registration_start']) : '—';
$registration_end   = $sprint['registration_end'] ? formatDate($sprint['registration_end']) : '—';
$jam_start          = $sprint['jam_start'] ? formatDate($sprint['jam_start']) : '—';
$jam_end            = $sprint['jam_end'] ? formatDate($sprint['jam_end']) : '—';
$voting_start       = $sprint['voting_start'] ? formatDate($sprint['voting_start']) : '—';
$voting_end         = $sprint['voting_end'] ? formatDate($sprint['voting_end']) : '—';


// ===== ФУНКЦИЯ ПРЕОБРАЗОВАНИЯ MARKDOWN В HTML =====
function markdownToHtml($text) {
    if (!$text) return '';
    // Экранируем HTML-сущности
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Заголовки
    $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
    // Жирный и курсив
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    // Ссылки
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);
    // Списки
    $html = preg_replace('/^\- (.*$)/m', '<li>$1</li>', $html);
    // Обёртка списков (простая, без вложенности)
    $html = preg_replace('/((?:<li>.*<\/li>\s*)+)/', '<ul>$1</ul>', $html);
    // Переносы строк
    $html = nl2br($html);
    return $html;
}

// Экранирование
function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// Формируем JSON для передачи в JavaScript
$sprintData = [
    'id' => $sprint_id,
    'title' => $sprint['title'],
    'description' => $sprint['description'],
    'theme' => $sprint['theme'],
    'setting' => $sprint['setting'],
    'host_name' => $sprint['host_name'],
    'participant_count' => $sprint['participant_count'],
    'phase' => $phase,
    'phaseText' => $phaseText,
    'badgeClass' => $badgeClass,
    'registration_start' => $registration_start,
    'registration_end' => $registration_end,
    'jam_start' => $jam_start,
    'jam_end' => $jam_end,
    'voting_start' => $voting_start,
    'voting_end' => $voting_end,
    'max_participants' => $sprint['max_participants'],
    'rules' => $sprint['rules'],
    'useful_links' => $sprint['useful_links'],
    'prizes' => $prizes,
    'experts' => $experts,
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($sprint['title']) ?> — Джем-страница</title>
    <style>
        /* ============================================================
           BASE — reset, variables, typography
           ============================================================ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --c-bg: #c0c0c0;
            --c-bg-dark: #808080;
            --c-bg-light: #dfdfdf;
            --c-bg-inset: #ffffff;
            --c-navy: #000080;
            --c-navy-grad: linear-gradient(to right, #000080, #1084d0);
            --c-red: #800000;
            --c-green: #006400;
            --c-purple: #4a0060;
            --c-orange: #804000;
            --c-text: #000000;
            --c-text-dim: #404040;
            --c-text-mid: #606060;
            --b-raise: 2px solid;
            --b-raise-color: #ffffff #404040 #404040 #ffffff;
            --b-sink: 2px solid;
            --b-sink-color: #808080 #ffffff #ffffff #808080;
            --font: 'Courier New', Courier, monospace;
            --font-size: 12px;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            font-family: var(--font);
            font-size: var(--font-size);
            color: var(--c-text);
            user-select: none;
            background: #008080;
        }

        ::-webkit-scrollbar {
            width: 16px;
        }
        ::-webkit-scrollbar-track {
            background: var(--c-bg);
            border: 1px solid var(--c-bg-dark);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--c-bg-light);
        }
        ::-webkit-scrollbar-button {
            display: block;
            height: 16px;
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
        }

        /* ============================================================
           APP LAYOUT
           ============================================================ */
        #app {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: #008080;
        }

        #desktop {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        /* ── desktop icons ── */
        .desk-icons {
            position: absolute;
            top: 12px;
            left: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1;
        }

        .dicon {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            width: 72px;
            padding: 4px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .dicon:hover {
            background: rgba(255, 255, 255, .08);
        }
        .dicon:active .ibox {
            border-color: #808080 #fff #fff #808080;
        }

        .dicon .ibox {
            width: 44px;
            height: 44px;
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 2px;
        }
        .dicon .ibox svg {
            width: 28px;
            height: 28px;
            fill: none;
            stroke: #000;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .dicon span {
            font-size: 10px;
            color: #fff;
            text-align: center;
            background: rgba(0, 0, 60, .75);
            padding: 2px 4px;
            white-space: nowrap;
            max-width: 72px;
            overflow: hidden;
            text-overflow: ellipsis;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 2px;
        }

        /* ── taskbar ── */
        #taskbar {
            height: 30px;
            background: var(--c-bg);
            border-top: 2px solid #fff;
            display: flex;
            align-items: center;
            padding: 0 4px;
            gap: 4px;
            flex-shrink: 0;
            box-shadow: 0 -1px 0 #808080;
            z-index: 9000;
            position: relative;
        }

        .tbstart {
            padding: 2px 10px;
            font-family: var(--font);
            font-weight: bold;
            font-size: 12px;
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .tbstart:active {
            border-color: #808080 #fff #fff #808080;
        }
        .tbstart svg {
            width: 14px;
            height: 14px;
            stroke: #000;
            stroke-width: 2;
            fill: none;
        }

        .tbwins {
            flex: 1;
            display: flex;
            gap: 3px;
            overflow: hidden;
            min-width: 0;
        }

        .tbwin {
            padding: 2px 8px;
            font-size: 11px;
            font-family: var(--font);
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            cursor: pointer;
            white-space: nowrap;
            max-width: 140px;
            min-width: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }
        .tbwin.active {
            border-color: #808080 #fff #fff #808080;
            background: #b4b4b4;
        }
        .tbwin:active {
            border-color: #808080 #fff #fff #808080;
        }

        .tbright {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
            flex-shrink: 0;
        }

        #tb-clock {
            font-size: 11px;
            font-family: var(--font);
            padding: 2px 6px;
            border: 1px solid;
            border-color: #808080 #fff #fff #808080;
            background: var(--c-bg);
            min-width: 40px;
            text-align: center;
        }

        /* ============================================================
           WINDOWS
           ============================================================ */
        .win {
            position: absolute;
            display: flex;
            flex-direction: column;
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            box-shadow: 3px 3px 0 #000;
            min-width: 280px;
            min-height: 200px;
            overflow: hidden;
        }
        .win.hidden-win {
            display: none !important;
        }

        .win-titlebar {
            background: var(--c-navy-grad);
            color: #fff;
            padding: 3px 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: bold;
            font-size: 12px;
            flex-shrink: 0;
            cursor: default;
            user-select: none;
        }
        .win-titlebar.dragging {
            cursor: grabbing;
        }
        .win-titlebar.inactive {
            background: #808080;
        }

        .win-tb-left {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
            min-width: 0;
        }

        .win-emblem {
            width: 20px;
            height: 20px;
            border: 1.5px solid rgba(255, 255, 255, .6);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 2px;
        }
        .win-emblem svg {
            width: 14px;
            height: 14px;
            stroke: #fff;
            stroke-width: 2;
            fill: none;
        }

        .win-title-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .win-tb-btns {
            display: flex;
            gap: 2px;
            flex-shrink: 0;
        }

        .wbtn {
            width: 17px;
            height: 15px;
            background: var(--c-bg);
            color: #000;
            border: 1px solid;
            border-color: #fff #404040 #404040 #fff;
            font-size: 10px;
            font-weight: bold;
            font-family: var(--font);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }
        .wbtn:active {
            border-color: #404040 #fff #fff #404040;
        }

        .win-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
            background: var(--c-bg);
        }

        .win-content {
            flex: 1;
            overflow-y: auto;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: var(--c-bg);
        }

        .win-statusbar {
            border-top: 2px solid;
            border-color: #808080 #fff #fff #808080;
            padding: 2px 8px;
            font-size: 10px;
            color: var(--c-text-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--c-bg);
            flex-shrink: 0;
            gap: 8px;
        }

        .win.minimized {
            display: none;
        }

        .win-resize {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 14px;
            height: 14px;
            cursor: se-resize;
            font-size: 10px;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            padding: 1px;
            color: #808080;
            z-index: 10;
        }

        /* ── tabbar ── */
        .win-tabbar {
            display: flex;
            gap: 2px;
            padding: 4px 7px 0;
            background: var(--c-bg);
            border-bottom: 2px solid #808080;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        .wtab {
            padding: 2px 12px;
            border: 2px solid;
            border-color: #fff #808080 transparent #fff;
            background: #a0a0a0;
            font-size: 11px;
            font-family: var(--font);
            cursor: pointer;
            position: relative;
            top: 2px;
            white-space: nowrap;
        }
        .wtab.active {
            background: var(--c-bg);
            font-weight: bold;
            border-bottom-color: var(--c-bg);
            z-index: 1;
        }
        .wtab:hover:not(.active) {
            background: #b4b4b4;
        }

        /* ── panels ── */
        .panel {
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        .panel.active {
            display: flex;
        }

        /* ── content helper ── */
        .chdr {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid;
            border-color: #808080 #fff #fff #808080;
            padding-bottom: 8px;
        }
        .ctitle {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .csub {
            font-size: 10px;
            color: var(--c-text-dim);
            margin-top: 2px;
            letter-spacing: .4px;
        }
        .stamp {
            background: var(--c-red);
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            padding: 3px 7px;
            border: 2px solid #600000;
            letter-spacing: 2px;
            transform: rotate(-4deg);
            display: inline-block;
            flex-shrink: 0;
            margin-top: 4px;
        }

        .orgbox {
            background: var(--c-bg-light);
            border: var(--b-sink);
            border-color: var(--b-sink-color);
            padding: 11px 13px;
            line-height: 1.7;
            font-size: 11px;
        }
        .orgbox p+p {
            margin-top: 7px;
        }
        .orgbox b {
            color: var(--c-navy);
        }

        .igrid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 7px;
        }
        .icard {
            background: var(--c-bg-light);
            border: var(--b-sink);
            border-color: var(--b-sink-color);
            padding: 7px 9px;
        }
        .icard-t {
            font-size: 9px;
            font-weight: bold;
            color: var(--c-navy);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 4px;
        }
        .icard-v {
            font-size: 11px;
            line-height: 1.4;
        }

        .slabel {
            font-size: 11px;
            font-weight: bold;
            background: #808080;
            color: #fff;
            padding: 2px 7px;
            letter-spacing: .4px;
            margin-bottom: 2px;
        }

        /* ── image grid for references ── */
        .ref-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            padding: 4px 0;
        }

        .ref-thumb {
            background: var(--c-bg-light);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            padding: 6px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            transition: filter 0.1s;
            border-radius: 2px;
        }
        .ref-thumb:hover {
            filter: brightness(0.95);
        }
        .ref-thumb:active {
            border-color: #808080 #fff #fff #808080;
        }
        .ref-thumb .thumb-img {
            width: 100%;
            aspect-ratio: 1/1;
            background: #a0a0a0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #808080;
            overflow: hidden;
            border-radius: 2px;
        }
        .ref-thumb .thumb-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ref-thumb .thumb-img svg {
            width: 48px;
            height: 48px;
            stroke: #555;
            stroke-width: 1.5;
            fill: none;
        }
        .ref-thumb .thumb-label {
            font-size: 10px;
            color: var(--c-text-dim);
            text-align: center;
            word-break: break-word;
            max-width: 100%;
        }

        /* ── fullscreen image overlay ── */
        #img-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .85);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        #img-overlay.open {
            display: flex;
        }
        #img-overlay img {
            max-width: 92vw;
            max-height: 88vh;
            border: 3px solid #fff;
            box-shadow: 0 0 60px rgba(0, 0, 0, .8);
            image-rendering: pixelated;
        }
        #img-overlay .img-close {
            position: absolute;
            top: 16px;
            right: 24px;
            color: #fff;
            font-size: 32px;
            font-family: var(--font);
            cursor: pointer;
            opacity: .7;
            text-shadow: 0 0 8px #000;
        }
        #img-overlay .img-close:hover {
            opacity: 1;
        }
        #img-overlay .img-label {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            font-size: 13px;
            background: rgba(0, 0, 0, .6);
            padding: 4px 16px;
            border: 1px solid rgba(255, 255, 255, .2);
            font-family: var(--font);
            letter-spacing: .5px;
            max-width: 80%;
            text-align: center;
        }

        /* ── design doc content ── */
        .doc-content {
            font-size: 11px;
            line-height: 1.8;
            padding: 4px 0;
        }
        .doc-content h2 {
            font-size: 14px;
            color: var(--c-navy);
            border-bottom: 1px solid #808080;
            padding-bottom: 2px;
            margin-top: 14px;
            margin-bottom: 6px;
        }
        .doc-content h2:first-child {
            margin-top: 0;
        }
        .doc-content h3 {
            font-size: 12px;
            color: var(--c-navy);
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .doc-content ul {
            padding-left: 20px;
            margin: 4px 0;
        }
        .doc-content ul li {
            margin-bottom: 2px;
        }
        .doc-content .doc-meta {
            background: var(--c-bg-light);
            border: var(--b-sink);
            border-color: var(--b-sink-color);
            padding: 6px 10px;
            font-size: 10px;
            color: var(--c-text-dim);
        }
        .doc-content .lore-block {
            background: var(--c-bg-light);
            border-left: 3px solid var(--c-navy);
            padding: 6px 10px;
            margin: 4px 0;
        }
        .doc-content .term {
            font-weight: bold;
            color: var(--c-navy);
        }

        /* ── sprint modal content (inside window) ── */
        .sprint-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .sprint-header-info .sprint-status {
            font-size: 11px;
            color: var(--c-text-dim);
        }
        .sprint-header-info .sprint-status .badge {
            background: var(--c-green);
            color: #fff;
            padding: 0 6px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 9px;
        }
        .sprint-title {
            font-size: 16px;
            font-weight: bold;
        }
        .sprint-host {
            font-size: 11px;
            color: var(--c-text-dim);
        }

        .sprint-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid #808080;
            padding-bottom: 2px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .sprint-tab {
            padding: 3px 12px;
            background: #a0a0a0;
            border: 2px solid;
            border-color: #fff #808080 transparent #fff;
            cursor: pointer;
            font-size: 11px;
            font-family: var(--font);
            position: relative;
            top: 2px;
        }
        .sprint-tab.active {
            background: var(--c-bg);
            font-weight: bold;
            border-bottom-color: var(--c-bg);
        }
        .sprint-tab:hover:not(.active) {
            background: #b4b4b4;
        }

        .sprint-tab-content {
            display: none;
            flex-direction: column;
            gap: 10px;
            font-size: 11px;
            line-height: 1.6;
            padding: 4px 0;
        }
        .sprint-tab-content.active {
            display: flex;
        }
        .sprint-tab-content .sprint-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }
        .sprint-tab-content .sprint-dates .date-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted #a0a0a0;
            padding: 2px 0;
        }
        .sprint-tab-content .sprint-dates .date-item .label {
            color: var(--c-text-dim);
        }
        .sprint-tab-content .sprint-dates .date-item .value {
            font-weight: bold;
        }
        .sprint-tab-content .prize-placeholder,
        .sprint-tab-content .expert-placeholder {
            color: var(--c-text-dim);
            font-style: italic;
        }
        .sprint-tab-content .rules-text {
            white-space: pre-wrap;
            font-size: 11px;
        }
        .sprint-tab-content .links-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sprint-tab-content .links-list a {
            color: var(--c-navy);
            text-decoration: underline;
            cursor: pointer;
        }

        .sprint-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            border-top: 2px solid #808080;
            padding-top: 10px;
            margin-top: 4px;
        }
        .sprint-actions .btn {
            padding: 4px 14px;
            background: var(--c-bg);
            border: var(--b-raise);
            border-color: var(--b-raise-color);
            font-family: var(--font);
            font-size: 11px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 2px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: #000;
        }
        .sprint-actions .btn:active {
            border-color: #808080 #fff #fff #808080;
        }
        .sprint-actions .btn.primary {
            background: var(--c-navy);
            color: #fff;
            border-color: #4040c0 #000030 #000030 #4040c0;
        }
        .sprint-actions .btn.primary:hover {
            background: #0000a0;
        }
        .sprint-actions .btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }
        .sprint-actions .btn svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* ── responsive tweaks ── */
        @media (max-width: 640px) {
            .igrid {
                grid-template-columns: repeat(2, 1fr);
            }
            .win {
                min-width: 200px !important;
                width: 92vw !important;
                left: 4vw !important;
                right: 4vw !important;
                max-height: 80vh !important;
            }
            .win .win-content {
                padding: 10px 12px;
            }
            .ref-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            .dicon {
                width: 56px;
            }
            .dicon .ibox {
                width: 36px;
                height: 36px;
            }
            .dicon .ibox svg {
                width: 22px;
                height: 22px;
            }
            .dicon span {
                font-size: 8px;
                max-width: 56px;
            }
            .sprint-dates {
                grid-template-columns: 1fr !important;
            }
            .sprint-actions {
                flex-direction: column;
                gap: 6px;
            }
            .sprint-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .sprint-tabs {
                gap: 2px;
            }
            .sprint-tab {
                font-size: 10px;
                padding: 2px 8px;
            }
            .sprint-title {
                font-size: 14px;
            }
            .ctitle {
                font-size: 16px;
            }
            .desk-icons {
                top: 8px;
                left: 8px;
                gap: 6px;
            }
            #taskbar {
                height: 26px;
            }
            .tbstart {
                font-size: 10px;
                padding: 1px 6px;
            }
            .tbwin {
                font-size: 9px;
                padding: 1px 4px;
                min-width: 40px;
                max-width: 80px;
            }
            #tb-clock {
                font-size: 9px;
                min-width: 30px;
                padding: 1px 4px;
            }
            .win-resize {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .win {
                min-width: 160px !important;
                width: 96vw !important;
                left: 2vw !important;
                height: 85vh !important;
                max-height: 85vh !important;
            }
            .win .win-content {
                padding: 8px 10px;
                font-size: 10px;
            }
            .ref-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
            .ref-thumb .thumb-label {
                font-size: 8px;
            }
            .dicon {
                width: 48px;
            }
            .dicon .ibox {
                width: 32px;
                height: 32px;
            }
            .dicon .ibox svg {
                width: 18px;
                height: 18px;
            }
            .dicon span {
                font-size: 7px;
                max-width: 48px;
            }
            .igrid {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .icard {
                padding: 4px 6px;
            }
            .icard-v {
                font-size: 10px;
            }
            .orgbox {
                font-size: 10px;
                padding: 8px 10px;
            }
            .stamp {
                font-size: 7px;
                padding: 2px 4px;
            }
            .slabel {
                font-size: 9px;
                padding: 1px 5px;
            }
            .sprint-tab {
                font-size: 9px;
                padding: 2px 6px;
            }
            .sprint-header-info {
                font-size: 10px;
                gap: 6px;
            }
            .sprint-title {
                font-size: 13px;
            }
            .sprint-host {
                font-size: 10px;
            }
            .sprint-tab-content {
                font-size: 10px;
            }
            .sprint-tab-content .sprint-dates .date-item {
                font-size: 9px;
            }
            .sprint-actions .btn {
                font-size: 10px;
                padding: 3px 10px;
            }
        }
    </style>
</head>

<body>

    <!-- ════════════════════════════════════════════════════════════
    APP
    ════════════════════════════════════════════════════════════ -->
    <div id="app">
        <div id="desktop">
            <!-- Desktop icons -->
            <div class="desk-icons" id="desk-icons">
                <div class="dicon" id="dicon-main" onclick="openWindow('main')" title="Главная — информация о джеме">
                    <div class="ibox">
                        <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </div>
                    <span>Главная</span>
                </div>
                <div class="dicon" id="dicon-ref" onclick="openWindow('refs')" title="Референсы — галерея изображений">
                    <div class="ibox">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                    <span>Референсы</span>
                </div>
                <div class="dicon" id="dicon-doc" onclick="openWindow('doc')" title="Диздок — дизайн-документ">
                    <div class="ibox">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <span>Диздок</span>
                </div>
                <div class="dicon" id="dicon-sprint" onclick="openWindow('sprint')" title="Спринты — текущий джем">
                    <div class="ibox">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <span>Спринты</span>
                </div>
                <div class="dicon" id="dicon-back" onclick="WM.goBack()" title="Назад в dustore">
                    <div class="ibox">
                        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    </div>
                    <span>Назад</span>
                </div>
            </div>
        </div>

        <!-- Taskbar -->
        <div id="taskbar">
            <button class="tbstart" onclick="openWindow('main')">
                <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                ПУСК
            </button>
            <div class="tbwins" id="tbwins"></div>
            <div class="tbright">
                <span id="tb-clock">00:00</span>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
    IMAGE OVERLAY (full-screen)
    ════════════════════════════════════════════════════════════ -->
    <div id="img-overlay" onclick="ImgOverlay.close()">
        <span class="img-close" onclick="ImgOverlay.close()">✕</span>
        <img id="img-overlay-src" src="" alt="" />
        <div class="img-label" id="img-overlay-label"></div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
    CONTEXT MENU
    ════════════════════════════════════════════════════════════ -->
    <div id="ctx-menu"></div>

    <!-- ════════════════════════════════════════════════════════════
    TOAST CONTAINER
    ════════════════════════════════════════════════════════════ -->
    <div id="toast-container"></div>

    <!-- ════════════════════════════════════════════════════════════
    MAIN WINDOW TEMPLATE (динамический)
    ════════════════════════════════════════════════════════════ -->
    <template id="tpl-main-content">
        <div class="win-content">
            <div class="panel active">
                <div class="chdr">
                    <div>
                        <div class="ctitle">🏆 ДЖЕМ · <?= e($sprint['title']) ?></div>
                        <div class="csub"><?= e($sprint['setting'] ?? 'КОНТРОЛЬ ОБЪЕКТОВ НЕИЗВЕСТНОГО ТИПА И УСЛОВНОЙ РЕАЛЬНОСТИ') ?></div>
                    </div>
                    <div class="stamp">JAM 2026</div>
                </div>

                <div class="orgbox">
                    <p><b>Добро пожаловать на джем-страницу <?= e($sprint['title']) ?>!</b></p>
<div><?= markdownToHtml($sprint['description'] ?? 'Описание отсутствует.') ?></div>
                    <?php if (!empty($sprint['theme'])): ?>
                        <p style="margin-top:6px;color:var(--c-text-dim);font-size:10px;">⚙ <b>Тема джема:</b> <?= e($sprint['theme']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sprint['setting'])): ?>
                        <p style="color:var(--c-text-dim);font-size:10px;">⚙ <b>Сеттинг:</b> <?= e($sprint['setting']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="igrid">
                    <div class="icard">
                        <div class="icard-t">Организация</div>
                        <div class="icard-v">К.О.Н.Т.У.Р.<br/><span style="font-size:10px;color:#606060;">Основана 1976</span></div>
                    </div>
                    <div class="icard">
                        <div class="icard-t">Ключевой объект</div>
                        <div class="icard-v" style="font-weight:bold;font-size:15px;color:#800000;">О-41</div>
                    </div>
                    <div class="icard">
                        <div class="icard-t">Угроза</div>
                        <div class="icard-v">Споровый Синдром<br/>Аномальные сущности</div>
                    </div>
                    <div class="icard">
                        <div class="icard-t">Гриф</div>
                        <div class="icard-v" style="color:#800000;font-weight:bold;">СОВЕРШЕННО<br/>СЕКРЕТНО</div>
                    </div>
                </div>

                <div>
                    <div class="slabel">📋 ЧТО ЗДЕСЬ ЕСТЬ</div>
                    <div style="background:var(--c-bg-light);border:var(--b-sink);border-color:var(--b-sink-color);padding:8px 12px;font-size:11px;line-height:1.6;">
                        <div>▶ <b>Главная</b> — вы здесь. Вся основная информация о джеме и проекте.</div>
                        <div>▶ <b>Референсы</b> — галерея вдохновляющих изображений. Кликните по картинке для полного просмотра.</div>
                        <div>▶ <b>Диздок</b> — дизайн-документ с полным лором К.О.Н.Т.У.Р.</div>
                        <div>▶ <b>Спринты</b> — текущий джем. Здесь вы можете посмотреть детали и зарегистрироваться.</div>
                        <div>▶ <b>Назад</b> — возврат на главную страницу магазина.</div>
                    </div>
                </div>

                <div style="margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="back-btn" onclick="openWindow('refs')">Референсы</button>
                    <button class="back-btn" onclick="openWindow('doc')" style="background:var(--c-purple);border-color:#8040a0 #300060 #300060 #8040a0;color:white;">Диздок</button>
                    <button class="back-btn" onclick="openWindow('sprint')" style="background:var(--c-green);border-color:#008040 #004020 #004020 #008040;color:white;">Спринты</button>
                </div>
            </div>
        </div>
        <div class="win-statusbar">
            <span class="sblink">ДЖЕМ-РЕЖИМ · Добро пожаловать!</span>
            <div class="spills" style="display:flex;gap:6px;"><span class="spill" style="background:var(--c-navy);color:#fff;">v.2.0 · 2026</span></div>
        </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════
    REFERENCES WINDOW TEMPLATE (можно оставить статическим или тоже динамическим, но пока оставим как есть)
    ════════════════════════════════════════════════════════════ -->
    <template id="tpl-refs-content">
        <div class="win-content" style="gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #808080;padding-bottom:4px;">
                <span style="font-size:14px;font-weight:bold;">🖼 РЕФЕРЕНСЫ</span>
                <span style="font-size:9px;color:var(--c-text-dim);">клик по картинке — полноэкранный просмотр</span>
            </div>
            <div class="ref-grid" id="ref-grid"></div>
        </div>
        <div class="win-statusbar">
            <span>Галерея референсов · нажмите на изображение для увеличения</span>
            <span id="ref-count" style="font-size:10px;color:var(--c-text-dim);">0 изображений</span>
        </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════
    DESIGN DOC WINDOW TEMPLATE (статичный)
    ════════════════════════════════════════════════════════════ -->
    <template id="tpl-doc-content">
        <div class="win-content">
            <div class="panel active">
                <div class="chdr">
                    <div>
                        <div class="ctitle" style="font-size:16px;">📄 ДИЗАЙН-ДОКУМЕНТ</div>
                        <div class="csub">К.О.Н.Т.У.Р. — визуальный роман / point-and-click</div>
                    </div>
                    <div class="stamp" style="background:var(--c-purple);border-color:#400060;">v.0.3</div>
                </div>

                <div class="doc-content">
                    <div class="doc-meta">
                        <b>Автор:</b> Команда К.О.Н.Т.У.Р. &nbsp;·&nbsp; <b>Дата:</b> 2026 &nbsp;·&nbsp;
                        <b>Статус:</b> Прототип
                    </div>

                    <h2>Что такое вселенная К.О.Н.Т.У.Р.</h2>
                    <p><b>К.О.Н.Т.У.Р.</b> — <b>К</b>онтроль <b>О</b>бъектов <b>Н</b>еизвестного <b>Т</b>ипа и <b>У</b>словной <b>Р</b>еальности.</p>
                    <p>Это художественный хоррор-проект о советской, а затем постсоветской одноимённой организации, которая сдерживает распространение аномального грибка <b>О-41</b>, найденного в разгар холодной войны. Ряд ошибок привёл к утечке этого странного патогена, с последствиями которой организация разбирается вплоть до наших дней.</p>

                    <h2>Что такое К.О.Н.Т.У.Р.</h2>
                    <p>Это засекреченная организация со специальными полномочиями. Её задача — изучать грибок О-41, сдерживать заражение и искать способы уничтожения опасного патогена. Организация действует инкогнито и передвигается на машинах газовой службы, пользуясь легендой о газовой опасности как удобным инструментом для расселения гражданских из опасной зоны.</p>

                    <h2>Грибок О-41</h2>
                    <p>Аномальный грибок, который прорастает в бетоне. Растёт годами, постепенно пронизывая всю бетонную конструкцию насквозь.</p>
                    <p>Результат — заражение стен и превращение здания в аномальный <b>Объект</b>. Когда грибок прорастает через все бетонные поверхности помещения, происходит <b>Сочленение</b>. Теперь, переступая порог привычного помещения, вы оказываетесь в его аномальной копии. Грибок понимает вещи поверхностно, поэтому копия искажена: законы физики работают не всегда, пространство плывёт, надписи деформируются, а предметы получают чужие свойства. Например, руль ведёт себя как вентиль, лампочка выглядит как светящееся яйцо, а дверной глазок оказывается настоящим глазом.</p>
                    <p>Геометрия здания часто нарушена. Попытка выйти на улицу может привести в соседнее помещение, а каждый неверный шаг способен стать последним.</p>

                    <p>Однако грибок симулирует не только пространство, но и живых существ. Вот некоторые из них:</p>
                    <ul>
                        <li><b>Мимики</b> — примитивные копии людей, находящиеся на Объектах и совершающие простые действия, не понимая зачем.</li>
                        <li><b>Симулякры</b> — нечто среднее между человеком, животным и предметом. Грибок может скрестить два живых организма или объединить живое и неживое, создавая странные и непредсказуемые формы.</li>
                        <li><b>Перекожники</b> — существа-ловушки, которые прикидываются обитателями исходной локации. Не двигаются и обычно стоят спиной.</li>
                    </ul>

                    <h2>Как переносится грибок</h2>
                    <p>Сам по себе грибок почти не распространяется, и заражение обычно заносится извне. Путей три: заражённый бетон (крошка и обломки из снесённых построек, повторно использованные в строительстве), споры, попавшие в воздух вследствие разрушения других бетонных Объектов, и заражённые люди, которые переносят споры через слизь и биологические жидкости.</p>

                    <h2>Заражённые люди</h2>
                    <p>Грибок О-41 небезопасен для людей и животных.</p>
                    <p>Когда в организме накапливается критическое количество спор, начинается <b>споровый синдром</b>: личность постепенно разрушается, человек теряет своё «я», тело мутирует, растут сила и выносливость. С этого момента он сам становится переносчиком и заражает новый бетон.</p>
                    <p>Заразиться можно через чёрную воду на Объектах или при пренебрежении средствами респираторной защиты.</p>

                    <h2>Словарь</h2>
                    <div class="lore-block">
                        <p><span class="term">О-41</span> — аномальный грибок, прорастающий в бетоне; основа всего заражения.</p>
                        <p><span class="term">К.О.Н.Т.У.Р.</span> — засекреченная организация, изучающая О-41 и контролирующая заражение людей и бетонных конструкций.</p>
                        <p><span class="term">Заражение</span> — заражение помещения или человека грибком О-41.</p>
                        <p><span class="term">Сочленение</span> — заражение всех бетонных поверхностей помещения. Момент, когда вход в привычную локацию теперь ведёт в аномальный Объект, откуда трудно выбраться.</p>
                        <p><span class="term">Объект</span> — аномальное пространство (помещение), в котором уже произошло Сочленение.</p>
                        <p><span class="term">Симуляция</span> — режим работы Объекта: грибок имитирует исходное помещение, беря его за основу. Каждый Объект уникален из-за уникального контекста каждого помещения.</p>
                        <p><span class="term">Вторженец</span> — человек, попавший на Объект.</p>
                        <p><span class="term">Споровый синдром</span> — критическое накопление спор в организме: разрушение личности, мутации, рост силы и выносливости; носитель ищет новый бетон для распространения грибка О-41.</p>
                        <p><span class="term">Доброволец</span> — заключённый или доброволец, которого отправляют на опасный Объект для выявления аномалий.</p>
                    </div>

                    <h2>Регламент спринта «К.О.Н.Т.У.Р.»</h2>
                    <p><b>Суть:</b> 1 месяц на создание играбельного прототипа игры по вселенной К.О.Н.Т.У.Р. Это может быть сырая альфа-версия, но она должна работать.</p>
                    <p><b>Платформа:</b> ПК, Windows.</p>

                    <h3>Обязательно</h3>
                    <ul>
                        <li><b>Одна чёткая работающая механика</b> как ядро игры. Лучше одна механика, доведённая до играбельного состояния, чем пять плохо работающих.</li>
                        <li><b>Решения игрока влияют на происходящее.</b> Действия в ключевые моменты должны менять ход событий, а не быть декорацией.</li>
                        <li><b>Атмосфера и узнаваемость К.О.Н.Т.У.Р.</b> Советский институциональный хоррор, аномалии, Объекты, заражённые.</li>
                        <li><b>Желательно, чтобы идея и ядро игры были понятны с первых минут.</b></li>
                    </ul>

                    <h3>На ваше усмотрение</h3>
                    <ul>
                        <li><b>Жанр:</b> выживание, прятки, исследование, визуальная новелла или гибрид.</li>
                        <li><b>Атмосфера:</b> хоррор, мистика, тревога.</li>
                        <li><b>Визуальный стиль:</b> 3D с атмосферным освещением, low poly, стилистика PS1–PS2, analog horror.</li>
                    </ul>

                    <h3>Референсы (для ориентира, не для копирования)</h3>
                    <ul>
                        <li><b>Решения и разветвления:</b> <i>Papers, Please</i>, <i>Until Dawn</i></li>
                        <li><b>Одна сильная механика:</b> <i>Iron Lung</i>, <i>Buckshot Roulette</i></li>
                        <li><b>Атмосфера и Объекты:</b> <i>SCP: Containment Breach</i>, <i>That's Not My Neighbor</i></li>
                    </ul>

                    <h3>Ориентиры: что делает заявку сильной</h3>
                    <ul>
                        <li>Сила и ясность основной механики.</li>
                        <li>Насколько решения игрока реально влияют на ход игры.</li>
                        <li>Играбельность основного каркаса.</li>
                        <li>Соответствие вселенной К.О.Н.Т.У.Р.</li>
                        <li>Цельность и лаконичность без лишнего усложнения.</li>
                    </ul>

                    <div style="margin-top:12px;padding:8px 10px;background:#d0d0d0;border:var(--b-sink);border-color:var(--b-sink-color);font-size:10px;color:var(--c-text-dim);">
                        ⚙ <b>Примечание:</b> Дизайн-документ находится в разработке. Механики и сюжет могут меняться.
                    </div>
                </div>
            </div>
        </div>
        <div class="win-statusbar">
            <span>Диздок · версия 0.3</span>
            <span style="font-size:10px;color:var(--c-text-dim);">К.О.Н.Т.У.Р. — 2026</span>
        </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════
    SPRINT WINDOW TEMPLATE (полностью динамический)
    ════════════════════════════════════════════════════════════ -->
    <template id="tpl-sprint-content">
        <div class="win-content" style="padding:12px 16px;">
            <!-- header -->
            <div class="sprint-header-info">
                <span class="sprint-status">
                    <span class="badge <?= $badgeClass ?>"><?= $phaseText ?></span> · <?= (int)$sprint['participant_count'] ?> участников
                </span>
                <span style="margin-left:auto;font-size:11px;color:var(--c-text-dim);">Организатор: <?= e($sprint['host_name'] ?? 'TheCreator') ?></span>
            </div>
            <div class="sprint-title"><?= e($sprint['title']) ?></div>
            <div class="sprint-host">Организатор: <?= e($sprint['host_name'] ?? 'TheCreator') ?></div>

            <!-- tabs -->
            <div class="sprint-tabs" id="sprint-tabs">
                <div class="sprint-tab active" data-tab="overview">Обзор</div>
                <div class="sprint-tab" data-tab="dates">Даты</div>
                <div class="sprint-tab" data-tab="prizes">Призы и эксперты</div>
                <div class="sprint-tab" data-tab="rules">Регламент</div>
                <div class="sprint-tab" data-tab="links">Ссылки</div>
            </div>

            <!-- tab content -->
            <div id="sprint-tab-content">
                <!-- Обзор -->
                <div class="sprint-tab-content active" data-tab="overview">
<div><?= markdownToHtml($sprint['description'] ?? 'Описание отсутствует.') ?></div>                </div>

                <!-- Даты -->
                <div class="sprint-tab-content" data-tab="dates">
                    <div class="sprint-dates">
                        <div class="date-item"><span class="label">Регистрация:</span><span class="value"><?= $registration_start ?> - <?= $registration_end ?></span></div>
                        <div class="date-item"><span class="label">Джем:</span><span class="value"><?= $jam_start ?> - <?= $jam_end ?></span></div>
                        <div class="date-item"><span class="label">Голосование:</span><span class="value"><?= $voting_start ?> - <?= $voting_end ?></span></div>
                        <div class="date-item"><span class="label">Макс. участников:</span><span class="value"><?= $sprint['max_participants'] ?></span></div>
                    </div>
                </div>

                <!-- Призы и эксперты -->
                <div class="sprint-tab-content" data-tab="prizes">
                    <div><b>Призы</b><br>
                        <?php if (empty($prizes)): ?>
                            <span class="prize-placeholder">Нет призов</span>
                        <?php else: ?>
                            <?php foreach ($prizes as $p): ?>
                                <div><?= $p['place_num'] ?> место: <?= e($p['reward']) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div><b>Эксперты</b><br>
                        <?php if (empty($experts)): ?>
                            <span class="expert-placeholder">Нет экспертов</span>
                        <?php else: ?>
                            <?php foreach ($experts as $e): ?>
                                <div><?= e($e['username']) ?> (<?= e($e['role'] ?? 'Эксперт') ?>)</div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Регламент -->
                <div class="sprint-tab-content" data-tab="rules">
<div class="rules-text"><?= markdownToHtml($sprint['rules'] ?? 'Регламент не задан.') ?></div>
                </div>

                <!-- Ссылки -->
                <div class="sprint-tab-content" data-tab="links">
                    <div class="links-list">
                        <?php
                        $links = explode("\n", $sprint['useful_links'] ?? '');
                        foreach ($links as $link):
                            $link = trim($link);
                            if ($link === '') continue;
                            // Формат строки: "URL (подпись)" — разбираем на адрес и подпись
                            if (preg_match('/^(\S+)\s*\((.+)\)\s*$/u', $link, $m)) {
                                $url   = $m[1];
                                $label = trim($m[2]);
                            } else {
                                $url   = $link;
                                $label = $link;
                            }
                        ?>
                            <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- actions -->
            <div class="sprint-actions" id="sprint-actions">
                <a href="https://dustore.ru/jams/?sprint=<?= $sprint_id ?>" target="_blank" class="btn primary" id="btn-join-register">Участвовать</a>
                <a href="/jams/participant.php?sprint_id=<?= $sprint_id ?>" class="btn">Панель участника</a>
                <a href="https://dustore.ru/l4t" target="_blank" class="btn">Команда</a>
                <button class="btn" onclick="shareSprint(<?= $sprint_id ?>)">Поделиться</button>
            </div>
        </div>
        <div class="win-statusbar">
            <span>Текущий спринт · <?= e($sprint['title']) ?></span>
            <span style="font-size:10px;color:var(--c-text-dim);">Джем 2026</span>
        </div>
    </template>


    <!-- ════════════════════════════════════════════════════════════
    JAVASCRIPT (с адаптацией под динамические данные)
    ════════════════════════════════════════════════════════════ -->
    <script>
        // ============================================================
        //  TOAST
        // ============================================================
        const Toast = {
            _container: null,
            _init() {
                if (this._container) return;
                this._container = document.getElementById('toast-container');
            },
            show(msg, dur = 3000) {
                this._init();
                const el = document.createElement('div');
                el.className = 'toast';
                el.innerHTML = `
                    <div class="toast-tb">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        Уведомление
                    </div>
                    <div class="toast-body">${msg}</div>`;
                this._container.appendChild(el);
                setTimeout(() => {
                    el.classList.add('hide');
                    setTimeout(() => el.remove(), 220);
                }, dur);
                return el;
            }
        };

        // ============================================================
        //  CONTEXT MENU
        // ============================================================
        const CtxMenu = {
            _el: null,
            _init() {
                if (this._el) return;
                this._el = document.getElementById('ctx-menu');
                document.addEventListener('mousedown', e => {
                    if (!this._el.contains(e.target)) this.hide();
                });
                document.addEventListener('keydown', e => { if (e.key === 'Escape') this.hide(); });
            },
            show(x, y, items) {
                this._init();
                this._el.innerHTML = items.filter(Boolean).map(item => {
                    if (item.sep) return '<div class="ctx-sep"></div>';
                    const dis = item.disabled ? 'disabled' : '';
                    const icon = item.icon ?
                        `<span style="width:16px;text-align:center;flex-shrink:0;display:flex;align-items:center;justify-content:center;">${item.icon}</span>` :
                        `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;
                    return `<div class="ctx-item ${dis}" data-key="${item.key || ''}">${icon}<span style="flex:1;">${item.label}</span></div>`;
                }).join('');
                this._el.querySelectorAll('.ctx-item:not(.disabled)').forEach(el => {
                    const item = items.find(i => i && i.key === el.dataset.key);
                    if (item?.action) el.addEventListener('click', () => { this.hide();
                        item.action(); });
                });
                this._el.style.display = 'block';
                this._el.style.left = '0';
                this._el.style.top = '0';
                const mw = this._el.offsetWidth,
                    mh = this._el.offsetHeight;
                this._el.style.left = (x + mw > window.innerWidth ? window.innerWidth - mw - 4 : x) + 'px';
                this._el.style.top = (y + mh > window.innerHeight ? window.innerHeight - mh - 4 : y) + 'px';
            },
            hide() { if (this._el) this._el.style.display = 'none'; }
        };

        // ============================================================
        //  IMAGE OVERLAY (full-screen)
        // ============================================================
        const ImgOverlay = {
            _el: null,
            _img: null,
            _label: null,
            _init() {
                if (this._el) return;
                this._el = document.getElementById('img-overlay');
                this._img = document.getElementById('img-overlay-src');
                this._label = document.getElementById('img-overlay-label');
            },
            open(src, label) {
                this._init();
                this._img.src = src;
                this._img.alt = label || '';
                this._label.textContent = label || '';
                this._el.classList.add('open');
                document.body.style.overflow = 'hidden';
            },
            close() {
                this._init();
                this._el.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        // ============================================================
        //  WINDOW MANAGER
        // ============================================================
        const WM = (() => {
            const _wins = {};
            let _topZ = 100;
            const DESKTOP = () => document.getElementById('desktop');

            function _addTaskbarBtn(id, title, iconSvg) {
                const tb = document.getElementById('tbwins');
                if (!tb) return null;
                const old = document.getElementById('tbwin-' + id);
                if (old) old.remove();
                const btn = document.createElement('div');
                btn.className = 'tbwin';
                btn.id = 'tbwin-' + id;
                btn.textContent = title.substring(0, 16);
                btn.title = title;
                btn.addEventListener('click', () => toggle(id));
                tb.appendChild(btn);
                return btn;
            }

            function _makeDraggable(el, id) {
                const tb = el.querySelector('.win-titlebar');
                if (!tb) return;
                let ox, oy, sx, sy, dragging = false;
                tb.addEventListener('mousedown', e => {
                    if (e.target.closest('.win-tb-btns')) return;
                    dragging = true;
                    ox = el.offsetLeft;
                    oy = el.offsetTop;
                    sx = e.clientX;
                    sy = e.clientY;
                    tb.classList.add('dragging');
                    e.preventDefault();
                });
                document.addEventListener('mousemove', e => {
                    if (!dragging) return;
                    const desk = DESKTOP();
                    const dRect = desk.getBoundingClientRect();
                    const nx = ox + e.clientX - sx;
                    const ny = oy + e.clientY - sy;
                    el.style.left = Math.max(0, Math.min(nx, dRect.width - el.offsetWidth)) + 'px';
                    el.style.top = Math.max(0, Math.min(ny, dRect.height - 30)) + 'px';
                });
                document.addEventListener('mouseup', () => { if (dragging) { dragging = false;
                        tb.classList.remove('dragging'); } });
            }

            function _makeResizable(el) {
                const handle = el.querySelector('.win-resize');
                if (!handle) return;
                let dragging = false,
                    ox, oy, sw, sh;
                handle.addEventListener('mousedown', e => {
                    dragging = true;
                    ox = e.clientX;
                    oy = e.clientY;
                    sw = el.offsetWidth;
                    sh = el.offsetHeight;
                    e.preventDefault();
                    e.stopPropagation();
                });
                document.addEventListener('mousemove', e => {
                    if (!dragging) return;
                    const nw = Math.max(280, sw + e.clientX - ox);
                    const nh = Math.max(200, sh + e.clientY - oy);
                    el.style.width = nw + 'px';
                    el.style.height = nh + 'px';
                });
                document.addEventListener('mouseup', () => { dragging = false; });
            }

            function focus(id) {
                const w = _wins[id];
                if (!w) return;
                if (w.hidden) { show(id); return; }
                document.querySelectorAll('.tbwin').forEach(b => b.classList.remove('active'));
                if (w.minimized) { w.el.classList.remove('minimized');
                    w.minimized = false; }
                w.el.style.zIndex = ++_topZ;
                Object.values(_wins).forEach(win => {
                    const tb = win.el.querySelector('.win-titlebar');
                    if (tb) tb.classList.toggle('inactive', win !== w);
                });
                if (w.tbBtn) w.tbBtn.classList.add('active');
            }

            function minimize(id) {
                const w = _wins[id];
                if (!w || w.hidden) return;
                w.el.classList.add('minimized');
                w.minimized = true;
                if (w.tbBtn) w.tbBtn.classList.remove('active');
                const tb = w.el.querySelector('.win-titlebar');
                if (tb) tb.classList.add('inactive');
            }

            function close(id) {
                const w = _wins[id];
                if (!w) return;
                if (w.onClose && w.onClose() === false) return;
                w.el.classList.add('hidden-win');
                w.hidden = true;
                w.minimized = false;
                if (w.tbBtn) { w.tbBtn.remove();
                    w.tbBtn = null; }
                const tbBtn = document.getElementById('tbwin-' + id);
                if (tbBtn) tbBtn.remove();
            }

            function show(id) {
                const w = _wins[id];
                if (!w) return;
                w.el.classList.remove('hidden-win', 'minimized');
                w.hidden = false;
                w.minimized = false;
                const btn = _addTaskbarBtn(id, w.title);
                w.tbBtn = btn;
                focus(id);
            }

            function toggle(id) {
                const w = _wins[id];
                if (!w) { Toast.show('Окно не найдено', 1500); return; }
                if (w.hidden) { show(id); } else if (w.minimized) { focus(id); } else { minimize(id); }
            }

            function create({ id, title, iconSvg, width, height, x, y, content, onClose }) {
                if (_wins[id]) {
                    if (_wins[id].hidden) { show(id); return _wins[id].el; }
                    focus(id);
                    return _wins[id].el;
                }
                const desk = DESKTOP();
                const dRect = desk.getBoundingClientRect();
                const off = Object.keys(_wins).length * 22;
                const px = x ?? Math.min(40 + off, dRect.width - width - 20);
                const py = y ?? Math.min(30 + off, dRect.height - height - 10);

                const el = document.createElement('div');
                el.className = 'win';
                el.id = 'win-' + id;
                el.style.cssText =
                    `width:${width}px;height:${height}px;left:${Math.max(0, px)}px;top:${Math.max(0, py)}px;z-index:${++_topZ};`;

                const emblemContent = iconSvg || `<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>`;

                el.innerHTML = `
                    <div class="win-titlebar" data-win="${id}">
                        <div class="win-tb-left">
                            <div class="win-emblem">${emblemContent}</div>
                            <span class="win-title-text">${title}</span>
                        </div>
                        <div class="win-tb-btns">
                            <div class="wbtn" data-action="minimize" title="Свернуть">_</div>
                            <div class="wbtn" data-action="maximize" title="Развернуть">□</div>
                            <div class="wbtn" data-action="close" title="Закрыть" style="font-size:11px;">✕</div>
                        </div>
                    </div>
                    <div class="win-inner" id="${id}-inner">${content}</div>
                    <div class="win-resize" title="Изменить размер">◢</div>`;

                desk.appendChild(el);
                _makeDraggable(el, id);
                _makeResizable(el);

                el.querySelector('[data-action="minimize"]').addEventListener('click', e => { e.stopPropagation();
                    minimize(id); });
                el.querySelector('[data-action="maximize"]').addEventListener('click', e => { e.stopPropagation();
                    _toggleMax(el); });
                el.querySelector('[data-action="close"]').addEventListener('click', e => { e.stopPropagation();
                    close(id); });

                el.addEventListener('mousedown', () => focus(id), true);

                const tbBtn = _addTaskbarBtn(id, title);
                _wins[id] = { el, minimized: false, hidden: false, tbBtn, title, onClose };
                focus(id);
                return el;
            }

            function _toggleMax(el) {
                if (el._maxed) {
                    const s = el._prevStyle;
                    el.style.left = s.left;
                    el.style.top = s.top;
                    el.style.width = s.width;
                    el.style.height = s.height;
                    el._maxed = false;
                } else {
                    el._prevStyle = { left: el.style.left, top: el.style.top, width: el.style.width, height: el.style
                            .height };
                    const desk = DESKTOP();
                    el.style.left = '0';
                    el.style.top = '0';
                    el.style.width = desk.offsetWidth + 'px';
                    el.style.height = desk.offsetHeight + 'px';
                    el._maxed = true;
                }
            }

            function inner(id) { return document.getElementById(id + '-inner'); }

            function goBack() {
                Toast.show('⬅ Возврат в dustore...', 2000);
                setTimeout(() => { window.location.href = 'https://dustore.ru'; }, 600);
            }

            return { create, focus, minimize, close, show, toggle, inner, wins: _wins, goBack };
        })();

        // ============================================================
        //  GLOBAL openWindow
        // ============================================================
        function openWindow(id) {
            const win = WM.wins[id];
            if (win) {
                if (win.hidden) { WM.show(id); } else { WM.toggle(id); }
            } else {
                Toast.show('Окно не найдено', 1500);
            }
        }

        // ============================================================
        //  REFERENCES DATA (можно заменить на путь из БД, но пока оставим)
        // ============================================================
        const REF_DATA = [
            { label: '1', file: '/jams/jam1/mood/1.png' },
            { label: '2', file: '/jams/jam1/mood/2.png' },
            { label: '3', file: '/jams/jam1/mood/3.png' },
            { label: '4', file: '/jams/jam1/mood/4.png' },
            { label: '5', file: '/jams/jam1/mood/5.png' },
            { label: '6', file: '/jams/jam1/mood/6.png' },
            { label: '7', file: '/jams/jam1/mood/7.png' },
            { label: '8', file: '/jams/jam1/mood/8.png' },
            { label: '9', file: '/jams/jam1/mood/9.png' },
            { label: '10', file: '/jams/jam1/mood/10.png' },
            { label: '11', file: '/jams/jam1/mood/11.png' },
            { label: '12', file: '/jams/jam1/mood/12.png' },
            { label: '13', file: '/jams/jam1/mood/13.png' },
            { label: '14', file: '/jams/jam1/mood/14.png' },
            { label: '15', file: '/jams/jam1/mood/15.png' },
            { label: '16', file: '/jams/jam1/mood/16.png' },
            { label: '17', file: '/jams/jam1/mood/17.png' },
            { label: '18', file: '/jams/jam1/mood/18.png' },
            { label: '19', file: '/jams/jam1/mood/19.png' },
            { label: '20', file: '/jams/jam1/mood/20.png' },
            { label: '21', file: '/jams/jam1/mood/21.png' },
            { label: '22', file: '/jams/jam1/mood/22.png' },
            { label: '23', file: '/jams/jam1/mood/23.png' },
            { label: '25', file: '/jams/jam1/mood/25.png' },
            { label: '26', file: '/jams/jam1/mood/26.png' },
            { label: '27', file: '/jams/jam1/mood/27.png' },
            { label: '28', file: '/jams/jam1/mood/28.png' },
            { label: '29', file: '/jams/jam1/mood/29.png' },
            { label: '30', file: '/jams/jam1/mood/30.png' },
            { label: '31', file: '/jams/jam1/mood/31.png' },
            { label: 'Логотип', file: '/jams/jam1/mood/fg_00000.png' },

        ];

        // ============================================================
        //  BUILD REFERENCE GRID
        // ============================================================
        function buildRefGrid() {
            const grid = document.getElementById('ref-grid');
            if (!grid) return;
            grid.innerHTML = REF_DATA.map((item, i) => {
                const bgColors = ['#4a4a5a', '#5a3a3a', '#2a4a3a', '#3a3a5a', '#5a4a2a', '#3a4a5a', '#4a2a4a', '#2a5a4a'];
                const c = bgColors[i % bgColors.length];
                const imgSrc = item.file;
                const label = item.label;
                return `
                    <div class="ref-thumb" data-label="${label}" data-src="${imgSrc}">
                        <div class="thumb-img">
                            <img src="${imgSrc}" alt="${label}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <svg viewBox="0 0 24 24" style="display:none;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        </div>
                        <span class="thumb-label">${label}</span>
                    </div>`;
            }).join('');

            grid.querySelectorAll('.ref-thumb').forEach(el => {
                const src = el.dataset.src;
                const label = el.dataset.label;
                el.addEventListener('click', () => { ImgOverlay.open(src, label); });
                el.addEventListener('contextmenu', e => {
                    e.preventDefault();
                    e.stopPropagation();
                    CtxMenu.show(e.clientX, e.clientY, [
                        { key: 'open', icon: '🖼', label: 'Открыть на весь экран', action: () => ImgOverlay
                                .open(src, label) },
                        { sep: true },
                        { key: 'copy', icon: '📋', label: 'Скопировать название', action: () => {
                                navigator.clipboard?.writeText(label).catch(() => {});
                                Toast.show('Название скопировано!', 1500);
                            } },
                    ]);
                });
            });

            const count = document.getElementById('ref-count');
            if (count) count.textContent = REF_DATA.length + ' изображений';
        }

        // ============================================================
        //  SPRINT FUNCTIONS
        // ============================================================
        function initSprintTabs() {
            const tabs = document.querySelectorAll('#sprint-tabs .sprint-tab');
            const contents = document.querySelectorAll('#sprint-tab-content .sprint-tab-content');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tab;
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    contents.forEach(c => {
                        c.classList.toggle('active', c.dataset.tab === target);
                    });
                });
            });
        }

        function shareSprint(id) {
            const url = window.location.origin + window.location.pathname + '?sprint=' + id;
            navigator.clipboard?.writeText(url).catch(() => {});
            Toast.show('Ссылка скопирована!', 1500);
        }

        // ============================================================
        //  CLOCK
        // ============================================================
        function initClock() {
            function tick() {
                const d = new Date();
                const el = document.getElementById('tb-clock');
                if (el) el.textContent =
                    String(d.getHours()).padStart(2, '0') + ':' +
                    String(d.getMinutes()).padStart(2, '0');
            }
            tick();
            setInterval(tick, 1000);
        }

        // ============================================================
        //  INIT
        // ============================================================
        (function init() {
            initClock();

            // Данные спринта из PHP передаём в JS
            const SPRINT_DATA = <?= json_encode($sprintData) ?>;

            // ── Build main window ──────────────────────────────
            const tplMain = document.getElementById('tpl-main-content');
            const tmpMain = document.createElement('div');
            tmpMain.appendChild(tplMain.content.cloneNode(true));
            WM.create({
                id: 'main',
                title: '🏠 ' + SPRINT_DATA.title,
                iconSvg: '<svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
                width: 720,
                height: 500,
                x: 40,
                y: 20,
                content: tmpMain.innerHTML,
                onClose: () => true
            });
            const winMain = document.getElementById('win-main');
            const innerMain = document.getElementById('main-inner');
            if (winMain && innerMain) {
                while (innerMain.firstChild) winMain.insertBefore(innerMain.firstChild, innerMain);
                innerMain.remove();
            }

            // ── Build refs window ──────────────────────────────
            const tplRefs = document.getElementById('tpl-refs-content');
            const tmpRefs = document.createElement('div');
            tmpRefs.appendChild(tplRefs.content.cloneNode(true));
            WM.create({
                id: 'refs',
                title: '🖼 Референсы — галерея',
                iconSvg: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
                width: 560,
                height: 420,
                x: 60,
                y: 80,
                content: tmpRefs.innerHTML,
                onClose: () => true
            });
            const winRefs = document.getElementById('win-refs');
            const innerRefs = document.getElementById('refs-inner');
            if (winRefs && innerRefs) {
                while (innerRefs.firstChild) winRefs.insertBefore(innerRefs.firstChild, innerRefs);
                innerRefs.remove();
            }
            WM.close('refs');

            setTimeout(buildRefGrid, 50);

            // ── Build doc window ───────────────────────────────
            const tplDoc = document.getElementById('tpl-doc-content');
            const tmpDoc = document.createElement('div');
            tmpDoc.appendChild(tplDoc.content.cloneNode(true));
            WM.create({
                id: 'doc',
                title: '📄 Диздок — Дизайн-документ',
                iconSvg: '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                width: 620,
                height: 480,
                x: 100,
                y: 60,
                content: tmpDoc.innerHTML,
                onClose: () => true
            });
            const winDoc = document.getElementById('win-doc');
            const innerDoc = document.getElementById('doc-inner');
            if (winDoc && innerDoc) {
                while (innerDoc.firstChild) winDoc.insertBefore(innerDoc.firstChild, innerDoc);
                innerDoc.remove();
            }
            WM.close('doc');

            // ── Build sprint window ─────────────────────────────
            const tplSprint = document.getElementById('tpl-sprint-content');
            const tmpSprint = document.createElement('div');
            tmpSprint.appendChild(tplSprint.content.cloneNode(true));
            WM.create({
                id: 'sprint',
                title: '⏱ Текущий спринт',
                iconSvg: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                width: 500,
                height: 500,
                x: 780,
                y: 20,
                content: tmpSprint.innerHTML,
                onClose: () => true
            });
            const winSprint = document.getElementById('win-sprint');
            const innerSprint = document.getElementById('sprint-inner');
            if (winSprint && innerSprint) {
                while (innerSprint.firstChild) winSprint.insertBefore(innerSprint.firstChild, innerSprint);
                innerSprint.remove();
            }

            // init tabs
            initSprintTabs();

            // ── Desktop icon context menus ──────────────────────
            document.querySelectorAll('.dicon').forEach(icon => {
                const id = icon.id;
                let actions = [];
                if (id === 'dicon-main') {
                    actions = [
                        { key: 'open', icon: '🏠', label: 'Открыть главную', action: () => openWindow('main') },
                        { key: 'close', icon: '✕', label: 'Закрыть окно', action: () => WM.close('main') },
                    ];
                } else if (id === 'dicon-ref') {
                    actions = [
                        { key: 'open', icon: '🖼', label: 'Открыть референсы', action: () => openWindow('refs') },
                        { key: 'close', icon: '✕', label: 'Закрыть окно', action: () => WM.close('refs') },
                    ];
                } else if (id === 'dicon-doc') {
                    actions = [
                        { key: 'open', icon: '📄', label: 'Открыть диздок', action: () => openWindow('doc') },
                        { key: 'close', icon: '✕', label: 'Закрыть окно', action: () => WM.close('doc') },
                    ];
                } else if (id === 'dicon-sprint') {
                    actions = [
                        { key: 'open', icon: '⏱', label: 'Открыть спринт', action: () => openWindow('sprint') },
                        { key: 'close', icon: '✕', label: 'Закрыть окно', action: () => WM.close('sprint') },
                    ];
                } else if (id === 'dicon-back') {
                    actions = [
                        { key: 'back', icon: '⬅', label: 'Назад в dustore', action: () => WM.goBack() },
                    ];
                }
                icon.addEventListener('contextmenu', e => {
                    e.preventDefault();
                    e.stopPropagation();
                    CtxMenu.show(e.clientX, e.clientY, actions);
                });
            });

            // ── Desktop background context menu ──────────────────
            document.getElementById('desktop').addEventListener('contextmenu', e => {
                if (e.target.closest('.dicon') || e.target.closest('.win')) return;
                e.preventDefault();
                CtxMenu.show(e.clientX, e.clientY, [
                    { key: 'main', icon: '🏠', label: 'Открыть главную', action: () => openWindow('main') },
                    { key: 'refs', icon: '🖼', label: 'Открыть референсы', action: () => openWindow('refs') },
                    { key: 'doc', icon: '📄', label: 'Открыть диздок', action: () => openWindow('doc') },
                    { key: 'sprint', icon: '⏱', label: 'Открыть спринт', action: () => openWindow('sprint') },
                    { sep: true },
                    { key: 'back', icon: '⬅', label: 'Назад в dustore', action: () => WM.goBack() },
                ]);
            });

            // ── Keyboard shortcuts ──────────────────────────────
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    if (document.getElementById('img-overlay').classList.contains('open')) { ImgOverlay.close(); }
                }
                if (e.key === 'F11') {
                    e.preventDefault();
                    document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()
                        .catch(() => {});
                }
            });

            // ── Toast welcome ────────────────────────────────────
            setTimeout(() => {
                Toast.show('🏠 Добро пожаловать на джем-страницу К.О.Н.Т.У.Р.!', 3500);
            }, 400);

            // ── Ensure main and sprint windows are open ──────────
            const mainW = WM.wins['main'];
            if (mainW && mainW.hidden) { WM.show('main'); }

            const sprintW = WM.wins['sprint'];
            if (sprintW && sprintW.hidden) { WM.show('sprint'); }

            console.log('🏁 К.О.Н.Т.У.Р. — Джем-страница загружена!');
        })();
    </script>

</body>

</html>