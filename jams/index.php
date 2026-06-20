<?php
require_once('../swad/static/elements/header.php');
require_once('../swad/config.php');

$dbInst = new Database();
$conn = $dbInst->connect();
if (!$conn) die('Ошибка подключения к базе данных');

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

// Загружаем призы и экспертов
foreach ($sprints as &$sprint) {
    $prizeStmt = $conn->prepare("SELECT place_num, reward FROM sprint_prizes WHERE sprint_id = ? ORDER BY place_num");
    $prizeStmt->execute([$sprint['id']]);
    $sprint['prizes'] = $prizeStmt->fetchAll(PDO::FETCH_ASSOC);

    $expStmt = $conn->prepare("
        SELECT u.id, u.username, u.role
        FROM sprint_experts se
        JOIN users u ON se.user_id = u.id
        WHERE se.sprint_id = ?
    ");
    $expStmt->execute([$sprint['id']]);
    $sprint['experts'] = $expStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($sprint);

$usersStmt = $conn->query("SELECT id, username, role FROM users ORDER BY username");
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// ID спринтов, в которых участвует текущий пользователь
$userSprintIds = [];
$userId = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = $_SESSION['USERDATA']['id'];
    $partStmt = $conn->prepare("SELECT sprint_id FROM sprint_participants WHERE user_id = ?");
    $partStmt->execute([$userId]);
    $userSprintIds = $partStmt->fetchAll(PDO::FETCH_COLUMN);
}

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

foreach ($sprints as &$s) {
    $s['phase'] = getPhase($s);
}
unset($s);

// Определяем, может ли пользователь оценивать
$canRateMap = [];
foreach ($sprints as $sprint) {
    $canRateMap[$sprint['id']] = ($userId != 0);
}
foreach ($sprints as &$s) {
    $s['can_rate'] = $canRateMap[$s['id']];
}
unset($s);

// Определяем ID спринта из GET
$sprintFromGet = isset($_GET['sprint']) ? (int)$_GET['sprint'] : 0;

// Получаем username текущего пользователя для подстановки в псевдоним
$currentUsername = $_SESSION['USERDATA']['username'] ?? 'Участник';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dustore | Спринты</title>
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
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(195,33,120,.35); border-radius: 4px; }
        .sprint-header { padding: 13px 26px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 0; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800; color: #e8ddf0; letter-spacing: -.3px; }
        .logo .brand { color: #c32178; }
        .header-nav { display: flex; gap: 6px; }
        .nav-btn { padding: 7px 16px; border-radius: 7px; border: none; font-size: 13px; font-weight: 600; background: rgba(255,255,255,.05); color: rgba(255,255,255,.5); transition: .001s; text-decoration: none; display: inline-block; }
        .nav-btn:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .nav-btn.active { background: rgba(195,33,120,.15); color: #e8ddf0; border: 1px solid rgba(195,33,120,.3); }
        .btn-primary { background: #c32178; border: none; color: #fff; border-radius: 7px; padding: 8px 18px; cursor: pointer; font-weight: 700; font-size: 13px; transition: .001s; }
        .btn-primary:hover { background: #9e1a66; transform: translateY(-1px); }
        .container { max-width: 980px; margin: 0 auto; padding: 28px 18px; }
        .hero {
            background: rgba(0,0,0,.3);
            border: 1px solid rgba(195,33,120,.2);
            border-radius: 14px;
            padding: 26px 30px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        body.moonlight-theme .hero {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .hero h1 { font-size: 24px; font-weight: 800; margin-bottom: 5px; letter-spacing: -.4px; }
        .hero h1 span { color: #c32178; }
        .hero p { color: rgba(255,255,255,.4); font-size: 14px; margin-bottom: 20px; }
        .hero-stats { display: flex; gap: 28px; flex-wrap: wrap; }
        .hero-stat .val { font-size: 20px; font-weight: 800; color: #e8ddf0; }
        .hero-stat .lbl { color: rgba(255,255,255,.35); font-size: 11px; margin-top: 2px; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
        .search-wrap { position: relative; flex: 1; min-width: 180px; }
        .search-ico { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.3); font-size: 13px; pointer-events: none; }
        .search-input { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 8px 12px 8px 32px; color: #e8ddf0; font-size: 13px; outline: none; }
        .search-input:focus { border-color: #c32178; }
        .filters { display: flex; gap: 5px; flex-wrap: wrap; }
        .filter-btn { padding: 7px 13px; border-radius: 7px; border: 1px solid rgba(255,255,255,.1); cursor: pointer; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.04); color: rgba(255,255,255,.45); transition: .001s; }
        .filter-btn.active { background: rgba(195,33,120,.18); border-color: rgba(195,33,120,.4); color: #e8ddf0; }
        .filter-btn:hover:not(.active) { background: rgba(255,255,255,.08); color: #e8ddf0; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
        .empty { text-align: center; padding: 60px 20px; color: rgba(255,255,255,.25); }
        .empty .ico { font-size: 40px; margin-bottom: 10px; }

        .card {
            background: #00000050;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.001s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            max-width: 500px;
            padding: 5px;
        }
        .card:hover {
            border-color: rgba(195, 33, 120, 0.4);
            transform: scale(1.02);
        }
        .card-banner {
            height: 230px;
            background-size: cover;
            background-position: center;
            background-color: #1a0a1e;
            position: relative;
            border-radius: 15px;
        }
        .card-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(195,33,120,.2), rgba(0,0,0,.6));
            pointer-events: none;
        }
        .card-banner img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .card-info {
            padding: 12px 14px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: rgba(0,0,0,.25);
            flex: 1;
        }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            margin: 0;
            line-height: 1.3;
        }
        .card-host {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 2px;
        }
        .card-desc {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin: 0;
            text-align: -webkit-center;
            background: linear-gradient(180deg, #00000040, #00000000);
            backdrop-filter: blur(10px);
            border-radius: 13px;
            height: 70px;
            position: relative;
            z-index: 1;
            transform: translateY(-90%);
        }
        body.moonlight-theme .card-desc {
            background: linear-gradient(180deg, #ffffff07, #00000000);
            backdrop-filter: blur(8px);
            color: rgba(255, 255, 255, 0.85);
            border-radius: 13px;
            height: 70px;
            position: relative;
            z-index: 1;
            transform: translateY(-90%);
        }
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin: 3px 0;
        }
        .tag {
            background: rgba(195,33,120,0.15);
            border: 1px solid rgba(195,33,120,0.3);
            color: #e8ddf0;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin: -60px 0 0 0;
        }
        .stat-box {
            background: rgba(0,0,0,.3);
            border-radius: 10px;
            padding: 4px 4px;
            text-align: center;
        }
        .stat-box .s-lbl {
            font-size: 9px;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-box .s-val {
            font-size: 12px;
            font-weight: 600;
            margin-top: 2px;
        }
        .prog-wrap {
            margin: 4px 0 2px;
        }
        .prog-lbl {
            font-size: 10px;
            display: flex;
            justify-content: space-between;
            color: rgba(255,255,255,0.5);
            margin-bottom: 4px;
        }
        .prog-bar {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        .prog-fill {
            background: #c32178;
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .modal-actions {
            display: flex;
            gap: 5px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .btn-join, .btn-team, .btn-share {
            flex: 1;
            padding: 5px 0;
            font-size: 11px;
            font-weight: 600;
            border-radius: 13px;
            background: rgba(195,33,120,0.2);
            border: 1px solid rgba(195,33,120,0.3);
            color: #fff;
            transition: 0.001s;
            text-align: center;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .btn-join:hover, .btn-team:hover, .btn-share:hover {
            background: rgba(195,33,120,0.4);
            transform: translateY(-1px);
            border-radius: 13px;
        }
        .btn-join {
            background: #c32178;
            border: none;
            border-radius: 13px;
        }
        .btn-join:hover {
            background: #9e1a66;
            border-radius: 13px;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.8);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .overlay.open { display: flex; }
        .modal {
            background: #160822;
            border: 1px solid rgba(195,33,120,.3);
            border-radius: 14px;
            max-width: 900px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 0 60px rgba(195,33,120,.15);
        }
        .modal-sm { max-width: 600px; }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
        }
        .modal-title-row { display: flex; gap: 14px; align-items: center; }
        .modal-banner { font-size: 42px; line-height: 1; }
        .modal-banner img { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; }
        .modal-h2 { font-size: 22px; font-weight: 800; margin: 5px 0 2px; letter-spacing: -.3px; }
        .modal-host { color: rgba(255,255,255,.35); font-size: 12px; }
        .btn-close { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); border-radius: 7px; padding: 5px 11px; cursor: pointer; font-size: 16px; }
        .btn-close:hover { background: rgba(255,255,255,.12); color: #e8ddf0; }
        .modal-desc { color: rgba(255,255,255,.5); line-height: 1.7; margin-bottom: 16px; font-size: 13px; }
        .theme-box { background: rgba(195,33,120,.07); border: 1px solid rgba(195,33,120,.2); border-radius: 10px; padding: 11px 15px; margin-bottom: 16px; font-size: 13px; }
        .theme-box strong { color: #c32178; }
        .modal-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 9px; margin-bottom: 18px; }
        .m-stat { background: rgba(255,255,255,.04); border-radius: 10px; padding: 12px; text-align: center; }
        .m-stat .val { font-weight: 700; font-size: 14px; }
        .m-stat .lbl { color: rgba(255,255,255,.3); font-size: 10px; margin-top: 2px; }
        .section-title { font-weight: 700; font-size: 13px; margin: 0 0 9px; display: block; text-transform: uppercase; letter-spacing: .05em; opacity: .7; }
        .prize-item, .expert-item { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); border-radius: 9px; padding: 9px 13px; margin-bottom: 7px; }
        .prize-item .pi-reward, .expert-item .ex-name { font-weight: 600; font-size: 13px; }
        .prize-item .pi-place, .expert-item .ex-role { color: rgba(255,255,255,.35); font-size: 11px; }
        .modal-actions { display: flex; gap: 8px; margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,.07); flex-wrap: wrap; }
        .btn-join, .btn-team, .btn-share, .btn-rate {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 13px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: .001s;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .btn-join:hover:not(:disabled) { background: #9e1a66; }
        .btn-join:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-team {
            background: rgba(195,33,120,.1);
            border: 1px solid rgba(195,33,120,.3);
            color: #e8ddf0;
        }
        .btn-team:hover { background: rgba(195,33,120,.2); }
        .btn-share {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            color: rgba(255,255,255,.5);
        }
        .btn-share:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .btn-rate {
            background: rgba(195,33,120,.2);
            border: 1px solid #c32178;
            color: #e8ddf0;
        }
        .btn-rate:hover { background: rgba(195,33,120,.4); }

        .tabs { display: flex; gap: 4px; border-bottom: 1px solid rgba(255,255,255,.1); margin-bottom: 16px; flex-wrap: wrap; }
        .tab-btn {
            padding: 8px 16px;
            background: transparent;
            border: none;
            color: rgba(255,255,255,.4);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: .001s;
        }
        .tab-btn:hover { color: #e8ddf0; }
        .tab-btn.active { color: #e8ddf0; border-bottom-color: #c32178; }
        .tab-panel { display: none; padding: 8px 0 16px; }
        .tab-panel.active { display: block; }
        .tab-panel p, .tab-panel div { color: rgba(255,255,255,.7); line-height: 1.7; }

        .desc-content {
            max-height: 120px;
            overflow: hidden;
            position: relative;
            transition: max-height .3s ease;
        }
        .desc-content.expanded { max-height: none; }
        .desc-content .desc-text { white-space: pre-wrap; word-break: break-word; }
        .desc-more-btn {
            background: none;
            border: none;
            color: #c32178;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            padding: 4px 0;
            margin-top: 6px;
        }
        .desc-more-btn:hover { text-decoration: underline; }

        .form-group { margin-bottom: 14px; }
        .form-label { display: block; color: rgba(255,255,255,.45); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-input, .form-textarea {
            width: 100%;
            background: rgba(0,0,0,.4);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 8px;
            padding: 9px 12px;
            color: #e8ddf0;
            font-size: 13px;
            outline: none;
        }
        .form-input:focus, .form-textarea:focus { border-color: #c32178; }
        .form-textarea { resize: vertical; }
        .radio-group { display: flex; gap: 20px; margin-top: 6px; }
        .radio-group label { display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer; }
        .radio-group input[type="radio"] { accent-color: #c32178; width: 18px; height: 18px; }

        .rules-consent-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.9);
            z-index: 300;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .rules-consent-overlay.open { display: flex; }
        .rules-consent-modal {
            background: #160822;
            border: 1px solid rgba(195,33,120,.3);
            border-radius: 14px;
            max-width: 600px;
            width: 100%;
            padding: 28px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .rules-consent-modal h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
        .rules-consent-modal .rules-body {
            font-size: 13px;
            color: rgba(255,255,255,.7);
            background: rgba(0,0,0,.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .rules-consent-modal .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .rules-consent-modal .btn-agree {
            background: #c32178;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 7px;
            font-weight: 600;
            cursor: pointer;
        }
        .rules-consent-modal .btn-agree:hover { background: #9e1a66; }
        .rules-consent-modal .btn-decline {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            color: rgba(255,255,255,.5);
            padding: 8px 20px;
            border-radius: 7px;
            cursor: pointer;
        }
        .rules-consent-modal .btn-decline:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }

        body.moonlight-theme {
            background: #05020a;
            background-image: url("/swad/static/img/Moonlight_pict.jpeg");
            background-size: cover;
            background-attachment: fixed;
            background-position: center 35%;
        }
        body.moonlight-theme .btn-primary,
        body.moonlight-theme .btn-next,
        body.moonlight-theme .btn-submit,
        body.moonlight-theme .btn-join {
            background: #285682 !important;
        }
        body.moonlight-theme .btn-primary:hover,
        body.moonlight-theme .btn-next:hover,
        body.moonlight-theme .btn-submit:hover,
        body.moonlight-theme .btn-join:hover {
            background: #193753 !important;
        }
        body.moonlight-theme .sprint-header,
        body.moonlight-theme .hero,
        body.moonlight-theme .card,
        body.moonlight-theme .modal,
        body.moonlight-theme .create-modal,
        body.moonlight-theme .l4t-toast {
            border-color: rgba(255, 255, 255, 0.08);
        }
        body.moonlight-theme .hero::before {
            background: radial-gradient(ellipse 60% 80% at 0% 50%, rgba(255,255,255,.04), transparent);
        }
        body.moonlight-theme .filter-btn,
        body.moonlight-theme .nav-btn,
        body.moonlight-theme .step-tab {
            background: rgba(255,255,255,.04);
            border-color: rgba(255,255,255,.08);
        }
        body.moonlight-theme .filter-btn.active,
        body.moonlight-theme .step-tab.active {
            background: rgb(24 105 147 / 22%);
            border-color: rgb(25 105 151 / 40%);
        }
        body.moonlight-theme .form-input,
        body.moonlight-theme .form-textarea,
        body.moonlight-theme .dynamic-row input,
        body.moonlight-theme .dynamic-row select {
            background: rgba(0,0,0,.5);
            border-color: rgba(255,255,255,.12);
        }
        body.moonlight-theme .stat-box,
        body.moonlight-theme .prize-item,
        body.moonlight-theme .expert-item,
        body.moonlight-theme .theme-box {
            background: rgba(0,0,0,.35);
            border-color: rgba(255,255,255,.06);
        }
        body.moonlight-theme .tag {
            background: rgba(195,33,120,.18);
            border-color: rgba(195,33,120,.28);
        }
        body.moonlight-theme .overlay {
            background: rgba(0,0,0,.85);
        }
        body.moonlight-theme .hero h1 span {
            color: #e00000;
        }
        body.moonlight-theme .search-input {
            background: rgba(0, 0, 0, 0.6);
            border-color: rgba(255, 255, 255, 0.2);
            color: #f0e6ff;
        }
        body.moonlight-theme .search-input:focus {
            border-color: #4a9eff;
        }
        body.moonlight-theme .modal, body.moonlight-theme .create-modal {
            background: #0a132545;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 60px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
        }
        body.moonlight-theme .card-info {
            background: rgba(0, 0, 0, 0.4);
        }
        body.moonlight-theme .card {
            background: #ffffff07;
            padding: 5px;
        }
        body.moonlight-theme .btn-team, body.moonlight-theme .btn-share {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.2);
        }

        .form-row { margin-bottom: 12px; }
        .form-row label { display: block; font-size: 12px; color: rgba(255,255,255,.5); margin-bottom: 4px; }
        .form-row input, .form-row textarea { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,.12); background: rgba(0,0,0,.3); color: #e8ddf0; font-size: 13px; }
        .form-row input:focus, .form-row textarea:focus { border-color: #c32178; outline: none; }
        .form-row textarea { min-height: 60px; resize: vertical; }
        .btn-submit {
            background: #c32178;
            color: #fff;
            border: none;
            padding: 8px 24px;
            font-size: 14px;
            border-radius: 7px;
            cursor: pointer;
        }
        .btn-submit:hover { background: #9e1a66; }
        #loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        #loading-overlay.visible {
            opacity: 1;
            pointer-events: auto;
        }
        #loading-overlay img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>

<header class="sprint-header">
    <div class="logo"><span class="brand"></span><span class="sep"></span></div>
    <div style="display:flex;align-items:center;gap:10px">
        <div class="header-nav">
            <a class="nav-btn active" href="sprints">Спринты</a>
            <a class="nav-btn" href="participant.php">Моё участие</a>
        </div>
        <button class="btn-primary" onclick="openCreate()">+ Создать спринт</button>
    </div>
</header>

<div class="container">
    <div class="hero">
        <h1><span>Спринты</span></h1>
        <p>Создавай игры в сжатые сроки · Соревнуйся с командами · Получай признание</p>
        <div class="hero-stats">
            <div class="hero-stat"><div class="val" id="stat-total">0</div><div class="lbl">Спринтов</div></div>
            <div class="hero-stat"><div class="val" id="stat-members">0</div><div class="lbl">Участников</div></div>
            <div class="hero-stat"><div class="val" id="stat-active">0</div><div class="lbl">Открытых</div></div>
        </div>
    </div>

    <div class="toolbar">
        <div class="search-wrap"><span class="search-ico">🔍</span><input class="search-input" id="search" placeholder="Поиск спринтов..." oninput="renderGrid()"></div>
        <div class="filters">
            <button class="filter-btn active" onclick="setFilter('all',this)">Все</button>
            <button class="filter-btn" onclick="setFilter('registration',this)">Регистрация</button>
            <button class="filter-btn" onclick="setFilter('jam',this)">Джем</button>
            <button class="filter-btn" onclick="setFilter('voting',this)">Голосование</button>
            <button class="filter-btn" onclick="setFilter('finished',this)">Завершены</button>
        </div>
    </div>

    <div class="grid" id="grid"></div>
    <div class="empty" id="empty" style="display:none"><div class="ico">🔍</div><p>Спринты не найдены</p></div>
</div>

<!-- Модалка просмотра спринта -->
<div class="overlay" id="view-overlay" onclick="closeView(event)">
    <div class="modal" id="view-modal" onclick="event.stopPropagation()"></div>
</div>

<!-- Модалка согласия с регламентом -->
<div class="rules-consent-overlay" id="rules-consent-overlay" onclick="closeRulesConsent(event)">
    <div class="rules-consent-modal" onclick="event.stopPropagation()">
        <h3>📜 Ознакомьтесь с регламентом</h3>
        <div class="rules-body" id="rules-body"></div>
        <div class="actions">
            <button class="btn-decline" onclick="closeRulesConsent()">Отказаться</button>
            <button class="btn-agree" onclick="agreeToRules()">Принять и продолжить</button>
        </div>
    </div>
</div>

<!-- Модалка регистрации -->
<div class="overlay" id="register-overlay" onclick="closeRegister(event)">
    <div class="modal modal-sm" onclick="event.stopPropagation()">
        <div class="modal-head">
            <h3 style="color:#e8ddf0;font-size:18px;font-weight:800">Регистрация на спринт</h3>
            <button class="btn-close" onclick="closeRegister()">✕</button>
        </div>
        <form id="register-form" onsubmit="submitRegistration(event)">
            <input type="hidden" id="reg-sprint-id" value="">
            <div class="form-group">
                <label class="form-label">Тип участия</label>
                <div class="radio-group">
                    <label><input type="radio" name="participant_type" value="solo" checked onchange="toggleTeamFields()"> Соло</label>
                    <label><input type="radio" name="participant_type" value="team" onchange="toggleTeamFields()"> Команда</label>
                </div>
            </div>
            <div id="solo-fields">
                <div class="form-group"><label class="form-label">Псевдоним (никнейм)</label><input class="form-input" id="reg-alias" placeholder="Ваш игровой ник" value="<?= htmlspecialchars($currentUsername) ?>"></div>
                <div class="form-group"><label class="form-label">Город</label><input class="form-input" id="reg-city" placeholder="Город"></div>
                <div class="form-group"><label class="form-label">Дополнительная информация о себе</label><textarea class="form-textarea" id="reg-extra" rows="3" placeholder="Расскажите о своих навыках, опыте..."></textarea></div>
                <div class="form-group"><label class="form-label">Ссылки (портфолио, соцсети) – каждая с новой строки</label><textarea class="form-textarea" id="reg-links" rows="2" placeholder="https://..."></textarea></div>
            </div>
            <div id="team-fields" style="display:none;">
                <p style="color:rgba(255,255,255,.5); font-size:13px;">Для команды заполните информацию о себе как участнике команды. Остальные участники должны будут зарегистрироваться отдельно.</p>
                <div class="form-group"><label class="form-label">Ваш псевдоним в команде</label><input class="form-input" id="reg-team-alias" placeholder="Ваш ник" value="<?= htmlspecialchars($currentUsername) ?>"></div>
                <div class="form-group"><label class="form-label">Город</label><input class="form-input" id="reg-team-city" placeholder="Город"></div>
                <div class="form-group"><label class="form-label">Дополнительная информация о вас</label><textarea class="form-textarea" id="reg-team-extra" rows="3" placeholder="Ваши навыки, роль в команде..."></textarea></div>
                <div class="form-group"><label class="form-label">Ваши ссылки</label><textarea class="form-textarea" id="reg-team-links" rows="2" placeholder="https://..."></textarea></div>
            </div>
            <div class="modal-actions" style="border-top: none; padding-top: 0; margin-top: 10px;">
                <button type="submit" class="btn-submit" style="width:100%;">Зарегистрироваться</button>
            </div>
        </form>
    </div>
</div>

<!-- Модалка создания спринта -->
<div class="overlay" id="create-overlay" onclick="closeCreateOverlay(event)">
    <div class="modal" style="max-width:600px;" onclick="event.stopPropagation()">
        <div class="modal-head">
            <h2 style="color:#e8ddf0;font-size:18px;font-weight:800">Создать спринт</h2>
            <button class="btn-close" onclick="closeCreate()">✕</button>
        </div>
        <div class="tabs" style="border-bottom:1px solid rgba(255,255,255,.1);">
            <button class="tab-btn active" onclick="goStep(1)">1. Основное</button>
            <button class="tab-btn" onclick="goStep(2)">2. Даты</button>
            <button class="tab-btn" onclick="goStep(3)">3. Призы и эксперты</button>
        </div>
        <div class="step-panel active" id="step1">
            <div class="form-group"><label class="form-label">Логотип</label><input type="file" id="f-logo" accept="image/*" class="form-input"><div id="logo-preview" style="margin-top:10px"></div></div>
            <div class="form-group"><label class="form-label">Название <span class="req">*</span></label><input class="form-input" id="f-title" placeholder="Pixel Chaos Sprint #4"></div>
            <div class="form-group"><label class="form-label">Описание <span class="req">*</span></label><textarea class="form-textarea" id="f-desc" rows="3"></textarea></div>
            <div class="form-group"><label class="form-label">Тема</label><input class="form-input" id="f-theme" placeholder="Киберпанк / Выживание / ..."></div>
            <div class="form-group"><label class="form-label">Теги (через запятую)</label><input class="form-input" id="f-tags" placeholder="Unity, 48h, Пиксель-арт"></div>
            <div class="form-group"><label class="form-label">Регламент (правила) – поддерживается Markdown</label><textarea class="form-textarea" id="f-rules" rows="4" placeholder="Правила участия, требования к работам..."></textarea></div>
            <div class="form-group"><label class="form-label">Полезные ссылки (каждая с новой строки)</label><textarea class="form-textarea" id="f-links" rows="2" placeholder="https://..."></textarea></div>
            <div class="form-nav"><button class="btn-next" onclick="goStep(2)">Далее →</button></div>
        </div>
        <div class="step-panel" id="step2">
            <div class="form-group"><label class="form-label">Регистрация с</label><input class="form-input" type="datetime-local" id="f-reg-start"></div>
            <div class="form-group"><label class="form-label">Регистрация до</label><input class="form-input" type="datetime-local" id="f-reg-end"></div>
            <div class="form-group"><label class="form-label">Начало джема</label><input class="form-input" type="datetime-local" id="f-jam-start"></div>
            <div class="form-group"><label class="form-label">Окончание джема (приём работ)</label><input class="form-input" type="datetime-local" id="f-jam-end"></div>
            <div class="form-group"><label class="form-label">Голосование с</label><input class="form-input" type="datetime-local" id="f-vote-start"></div>
            <div class="form-group"><label class="form-label">Голосование до</label><input class="form-input" type="datetime-local" id="f-vote-end"></div>
            <div class="form-group"><label class="form-label">Макс. участников</label><input class="form-input" type="number" id="f-maxp" value="100"></div>
            <div class="form-nav"><button class="btn-back" onclick="goStep(1)">← Назад</button><button class="btn-next" onclick="goStep(3)">Далее →</button></div>
        </div>
        <div class="step-panel" id="step3">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px"><span class="section-title">Призы</span><button class="btn-add" onclick="addPrize()">+ Добавить</button></div>
            <div id="prizes-list"></div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin:16px 0 10px"><span class="section-title">Эксперты</span><button class="btn-add" onclick="addExpert()">+ Добавить эксперта</button></div>
            <div id="experts-list"></div>
            <div class="form-nav"><button class="btn-back" onclick="goStep(2)">← Назад</button><button class="btn-submit" onclick="submitSprint()">Опубликовать</button></div>
        </div>
    </div>
</div>

<script>
    // Данные из PHP
    const sprintsData = <?php echo json_encode($sprints, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const allUsers = <?php echo json_encode($allUsers, JSON_HEX_TAG); ?>;
    const sprintFromGet = <?php echo (int)$sprintFromGet; ?>;
    const currentUsername = <?php echo json_encode($currentUsername); ?>;

    let sprints = sprintsData;
    let userSprintIds = <?php echo json_encode($userSprintIds); ?>;
    let curFilter = 'all';
    let prizes = [{ place: '1', reward: '' }];
    let selectedExperts = [];
    let isRedirecting = false;
    let pendingSprintId = null;

    const viewOverlay = document.getElementById('view-overlay');
    const viewModal = document.getElementById('view-modal');
    const rulesOverlay = document.getElementById('rules-consent-overlay');
    const rulesBody = document.getElementById('rules-body');
    const registerOverlay = document.getElementById('register-overlay');

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function markdownToHtml(text) {
        if (!text) return '';
        let html = escapeHtml(text);
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        html = html.replace(/^\- (.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    function getPhase(sprint) { return sprint.phase || 'finished'; }

    function formatDateRange(start, end) {
        if (!start) return '—';
        const d1 = new Date(start);
        const d2 = end ? new Date(end) : null;
        if (d2) return d1.toLocaleDateString() + ' – ' + d2.toLocaleDateString();
        return d1.toLocaleDateString();
    }

    function badgeHtml(phase) {
        const map = {
            'registration': ['badge-active', 'Регистрация'],
            'upcoming': ['badge-upcoming', 'Скоро'],
            'jam': ['badge-ongoing', 'Джем'],
            'voting': ['badge-voting', 'Голосование'],
            'finished': ['badge-finished', 'Завершён']
        };
        const [cls, txt] = map[phase] || map.finished;
        return `<span class="badge ${cls}">${txt}</span>`;
    }

    function updateStats() {
        document.getElementById('stat-total').textContent = sprints.length;
        const totalMembers = sprints.reduce((sum, s) => sum + (s.current_participants || 0), 0);
        document.getElementById('stat-members').textContent = totalMembers;
        const activeCount = sprints.filter(s => s.phase !== 'finished').length;
        document.getElementById('stat-active').textContent = activeCount;
    }

    function renderGrid() {
        const searchText = document.getElementById('search').value.toLowerCase();
        let filtered = sprints.filter(s => {
            const phase = s.phase || 'finished';
            const matchesFilter = curFilter === 'all' || phase === curFilter;
            const matchesSearch = s.title.toLowerCase().includes(searchText) || s.description.toLowerCase().includes(searchText);
            return matchesFilter && matchesSearch;
        });
        const grid = document.getElementById('grid');
        const empty = document.getElementById('empty');
        if (filtered.length === 0) {
            grid.innerHTML = '';
            empty.style.display = 'block';
            updateStats();
            return;
        }
        empty.style.display = 'none';
        let html = '';
        filtered.forEach(s => {
            const phase = s.phase || 'finished';
            const pct = Math.min(100, Math.round(((s.current_participants || 0) / s.max_participants) * 100));
            const tags = (s.tags ? s.tags.split(',') : []).map(t => `<span class="tag">${escapeHtml(t.trim())}</span>`).join('');
            const bannerStyle = s.logo_url ? `background-image: url('${escapeHtml(s.logo_url)}'); background-size: cover; background-position: center;` : '';
            html += `<div class="card" onclick="openView(${s.id})">
                        <div class="card-banner" style="${bannerStyle}">
                            ${s.logo_url ? `<img src="${escapeHtml(s.logo_url)}" alt="logo" style="display:none;">` : ''}
                        </div>
                        <div class="card-info">
                            <div class="card-title">${escapeHtml(s.title)}</div>
                            <div class="card-host">от ${escapeHtml(s.host_name || 'Dustore')}</div>
                            <div class="card-desc">${escapeHtml(s.description)}</div>
                            <div class="tags">${tags}</div>
                            <div class="card-stats">
                                <div class="stat-box"><div class="s-lbl">Регистрация</div><div class="s-val">${s.registration_start ? formatDateRange(s.registration_start, s.registration_end) : '—'}</div></div>
                                <div class="stat-box"><div class="s-lbl">Джем</div><div class="s-val">${s.jam_start ? formatDateRange(s.jam_start, s.jam_end) : '—'}</div></div>
                            </div>
                            <div class="prog-wrap">
                                <div class="prog-lbl"><span>Участники</span><span>${s.current_participants || 0} / ${s.max_participants}</span></div>
                                <div class="prog-bar"><div class="prog-fill" style="width:${pct}%"></div></div>
                            </div>
                        </div>
                    </div>`;
        });
        grid.innerHTML = html;
        updateStats();
    }

    function openView(id) {
        if (isRedirecting) return;
        const sprint = sprints.find(s => s.id == id);
        if (!sprint) return;

        const phase = sprint.phase || 'finished';
        const isJoined = userSprintIds.includes(sprint.id);
        const canRate = sprint.can_rate && phase === 'voting';

        const descHtml = markdownToHtml(sprint.description || '');
        const rulesHtml = markdownToHtml(sprint.rules || '');
        const linksHtml = markdownToHtml(sprint.useful_links || '');

        const prizesHtml = (sprint.prizes || []).map((p, i) => {
            const medal = ['🥇','🥈','🥉'][i] || '🎖';
            return `<div class="prize-item"><span style="font-size:20px">${medal}</span><div><div class="pi-place">${p.place_num} место</div><div class="pi-reward">${escapeHtml(p.reward)}</div></div></div>`;
        }).join('') || '<p style="color:rgba(255,255,255,.3);">Нет призов</p>';

        const expertsHtml = (sprint.experts || []).map(e => `
            <div class="expert-item">
                <span class="av">👤</span>
                <div><div class="ex-name">${escapeHtml(e.username)}</div><div class="ex-role">${escapeHtml(e.role || 'Эксперт')}</div></div>
            </div>
        `).join('') || '<p style="color:rgba(255,255,255,.3);">Нет экспертов</p>';

        const logoHtml = sprint.logo_url ? `<img src="${escapeHtml(sprint.logo_url)}" alt="logo">` : '🎮';
        const themeHtml = sprint.theme ? `<p><strong>Тема:</strong> ${escapeHtml(sprint.theme)}</p>` : '';

        let actionButton = '';
        if (isJoined) {
            actionButton = `<a href="participant.php?sprint_id=${sprint.id}" class="btn-join">Панель участника</a>`;
        } else {
            const canJoin = (phase === 'registration' || phase === 'upcoming' || phase === 'pre_jam');
            if (canJoin) {
                actionButton = `<button class="btn-join" onclick="startJoin(${sprint.id})">Участвовать</button>`;
            } else {
                actionButton = `<button class="btn-join" disabled>Регистрация закрыта</button>`;
            }
        }

        let rateButton = canRate ? `<a href="/jams/rate.php?id=${sprint.id}" class="btn-rate">Оценить</a>` : '';

        viewModal.innerHTML = `
            <div class="modal-head">
                <div class="modal-title-row">
                    <span class="modal-banner">${logoHtml}</span>
                    <div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            ${badgeHtml(phase)}
                            <span style="font-size:12px; color:rgba(255,255,255,.4);">${sprint.current_participants || 0} участников</span>
                        </div>
                        <div class="modal-h2">${escapeHtml(sprint.title)}</div>
                        <div class="modal-host">Организатор: ${escapeHtml(sprint.host_name || 'Dustore')}</div>
                    </div>
                </div>
                <button class="btn-close" onclick="closeView()">✕</button>
            </div>

            <div class="tabs">
                <button class="tab-btn active" data-tab="overview">Обзор</button>
                <button class="tab-btn" data-tab="dates">Даты</button>
                <button class="tab-btn" data-tab="prizes">Призы и эксперты</button>
                ${sprint.rules ? `<button class="tab-btn" data-tab="rules">Регламент</button>` : ''}
                ${sprint.useful_links ? `<button class="tab-btn" data-tab="links">Ссылки</button>` : ''}
            </div>

            <div class="tab-panel active" id="tab-overview">
                <div class="desc-wrapper">
                    <div class="desc-content" id="desc-content">
                        <div class="desc-text">${descHtml}</div>
                    </div>
                    ${descHtml.length > 300 ? `<button class="desc-more-btn" onclick="toggleDesc()">Ещё</button>` : ''}
                </div>
                ${themeHtml}
            </div>

            <div class="tab-panel" id="tab-dates">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div><strong>Регистрация:</strong> ${sprint.registration_start ? formatDateRange(sprint.registration_start, sprint.registration_end) : '—'}</div>
                    <div><strong>Джем:</strong> ${sprint.jam_start ? formatDateRange(sprint.jam_start, sprint.jam_end) : '—'}</div>
                    <div><strong>Голосование:</strong> ${sprint.voting_start ? formatDateRange(sprint.voting_start, sprint.voting_end) : '—'}</div>
                    <div><strong>Макс. участников:</strong> ${sprint.max_participants}</div>
                </div>
            </div>

            <div class="tab-panel" id="tab-prizes">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
                    <div><strong>Призы</strong><br>${prizesHtml}</div>
                    <div><strong>Эксперты</strong><br>${expertsHtml}</div>
                </div>
            </div>

            ${sprint.rules ? `<div class="tab-panel" id="tab-rules"><div class="rules-text">${rulesHtml}</div></div>` : ''}
            ${sprint.useful_links ? `<div class="tab-panel" id="tab-links"><div class="links-text">${linksHtml}</div></div>` : ''}

            <div class="modal-actions">
                ${actionButton}
                ${rateButton}
                <button class="btn-team" onclick="event.stopPropagation(); window.location.href='/l4t/?action=jam&jam_id='+${sprint.id};">Команда</button>
                <button class="btn-share" onclick="event.stopPropagation(); shareSprint(${sprint.id})">Поделиться</button>
            </div>
        `;

        viewModal.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                viewModal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const tabId = this.dataset.tab;
                viewModal.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                const panel = document.getElementById('tab-' + tabId);
                if (panel) panel.classList.add('active');
            });
        });

        viewOverlay.style.display = 'flex';
        if (history.pushState) {
            const newUrl = window.location.pathname + '?sprint=' + sprint.id;
            history.pushState({sprint: sprint.id}, '', newUrl);
        }
        const descContent = document.getElementById('desc-content');
        if (descContent) descContent.classList.remove('expanded');
    }

    function closeView(e) {
        if (e && e.target !== viewOverlay) return;
        viewOverlay.style.display = 'none';
        if (history.pushState) {
            history.pushState({}, '', window.location.pathname);
        }
    }

    function shareSprint(id) {
        const url = window.location.origin + window.location.pathname + '?sprint=' + id;
        navigator.clipboard.writeText(url);
        alert('Ссылка скопирована');
    }

    function toggleDesc() {
        const content = document.getElementById('desc-content');
        if (content) {
            content.classList.toggle('expanded');
            const btn = document.querySelector('.desc-more-btn');
            if (btn) {
                btn.textContent = content.classList.contains('expanded') ? 'Свернуть' : 'Ещё';
            }
        }
    }

    function startJoin(sprintId) {
        const sprint = sprints.find(s => s.id == sprintId);
        if (!sprint) return;
        if (sprint.rules) {
            pendingSprintId = sprintId;
            rulesBody.innerHTML = markdownToHtml(sprint.rules);
            rulesOverlay.style.display = 'flex';
        } else {
            openRegister(sprintId);
        }
    }

    function closeRulesConsent(e) {
        if (e && e.target !== rulesOverlay) return;
        rulesOverlay.style.display = 'none';
        pendingSprintId = null;
    }

    function agreeToRules() {
        if (pendingSprintId) {
            rulesOverlay.style.display = 'none';
            openRegister(pendingSprintId);
            pendingSprintId = null;
        }
    }

    function openRegister(sprintId) {
        document.getElementById('reg-sprint-id').value = sprintId;
        document.getElementById('reg-alias').value = currentUsername;
        document.getElementById('reg-city').value = '';
        document.getElementById('reg-extra').value = '';
        document.getElementById('reg-links').value = '';
        document.getElementById('reg-team-alias').value = currentUsername;
        document.getElementById('reg-team-city').value = '';
        document.getElementById('reg-team-extra').value = '';
        document.getElementById('reg-team-links').value = '';
        document.querySelector('input[name="participant_type"][value="solo"]').checked = true;
        toggleTeamFields();
        registerOverlay.style.display = 'flex';
    }

    function closeRegister(e) {
        if (e && e.target !== registerOverlay) return;
        registerOverlay.style.display = 'none';
    }

    function toggleTeamFields() {
        const type = document.querySelector('input[name="participant_type"]:checked').value;
        document.getElementById('solo-fields').style.display = (type === 'solo') ? 'block' : 'none';
        document.getElementById('team-fields').style.display = (type === 'team') ? 'block' : 'none';
    }

    async function submitRegistration(e) {
        e.preventDefault();
        const sprintId = document.getElementById('reg-sprint-id').value;
        const type = document.querySelector('input[name="participant_type"]:checked').value;
        let alias, city, extra, links;
        if (type === 'solo') {
            alias = document.getElementById('reg-alias').value.trim() || currentUsername;
            city = document.getElementById('reg-city').value.trim();
            extra = document.getElementById('reg-extra').value.trim();
            links = document.getElementById('reg-links').value.trim();
        } else {
            alias = document.getElementById('reg-team-alias').value.trim() || currentUsername;
            city = document.getElementById('reg-team-city').value.trim();
            extra = document.getElementById('reg-team-extra').value.trim();
            links = document.getElementById('reg-team-links').value.trim();
        }

        const formData = new FormData();
        formData.append('sprint_id', sprintId);
        formData.append('participant_type', type);
        formData.append('alias', alias);
        formData.append('city', city);
        formData.append('extra_info', extra);
        formData.append('links', links);

        try {
            const resp = await fetch('/swad/controllers/jams/join_sprint.php', {
                method: 'POST',
                body: formData
            });
            const result = await resp.json();
            if (result.success) {
                const sprintIndex = sprints.findIndex(s => s.id == sprintId);
                if (sprintIndex !== -1) {
                    sprints[sprintIndex].current_participants = result.new_count;
                    userSprintIds.push(parseInt(sprintId));
                }
                alert('Вы успешно зарегистрировались!');
                closeRegister();
                closeView();
                openView(sprintId);
                renderGrid();
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (err) {
            alert('Ошибка соединения: ' + err.message);
        }
    }

    function setFilter(f, el) {
        curFilter = f;
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        el.classList.add('active');
        renderGrid();
    }

    // ---------- Создание спринта ----------
    function openCreate() {
        prizes = [{ place: '1', reward: '' }];
        selectedExperts = [];
        document.getElementById('f-title').value = '';
        document.getElementById('f-desc').value = '';
        document.getElementById('f-theme').value = '';
        document.getElementById('f-tags').value = '';
        document.getElementById('f-rules').value = '';
        document.getElementById('f-links').value = '';
        document.getElementById('f-reg-start').value = '';
        document.getElementById('f-reg-end').value = '';
        document.getElementById('f-jam-start').value = '';
        document.getElementById('f-jam-end').value = '';
        document.getElementById('f-vote-start').value = '';
        document.getElementById('f-vote-end').value = '';
        document.getElementById('f-maxp').value = '100';
        document.getElementById('f-logo').value = '';
        document.getElementById('logo-preview').innerHTML = '';
        buildPrizes();
        renderExpertsSelects();
        goStep(1);
        document.getElementById('create-overlay').style.display = 'flex';
    }

    function closeCreate() { document.getElementById('create-overlay').style.display = 'none'; }
    function closeCreateOverlay(e) { if (e.target === document.getElementById('create-overlay')) closeCreate(); }

    function goStep(n) {
        document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('#create-overlay .tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');
        document.querySelector('#create-overlay .tab-btn:nth-child(' + n + ')').classList.add('active');
    }

    function buildPrizes() {
        const container = document.getElementById('prizes-list');
        container.innerHTML = prizes.map((p, i) => `
            <div class="dynamic-row">
                <span style="font-size:18px; flex-shrink:0">${['🥇','🥈','🥉'][i] || '🎖'}</span>
                <input class="dyn-input" value="${escapeHtml(p.reward)}" placeholder="Приз за ${p.place} место" oninput="prizes[${i}].reward = this.value">
                ${i > 0 ? `<button class="btn-remove" onclick="removePrize(${i})">✕</button>` : ''}
            </div>
        `).join('');
    }

    function addPrize() { prizes.push({ place: String(prizes.length + 1), reward: '' }); buildPrizes(); }
    function removePrize(idx) { prizes.splice(idx, 1); prizes.forEach((p, i) => p.place = String(i+1)); buildPrizes(); }

    function renderExpertsSelects() {
        const container = document.getElementById('experts-list');
        container.innerHTML = selectedExperts.map((userId, idx) => `
            <div class="dynamic-row">
                <select class="form-input user-select" onchange="updateExpert(${idx}, this.value)" style="flex:2">
                    <option value="">-- Выберите пользователя --</option>
                    ${allUsers.map(u => `<option value="${u.id}" ${u.id == userId ? 'selected' : ''}>${escapeHtml(u.username)} (${u.role || 'пользователь'})</option>`).join('')}
                </select>
                <button class="btn-remove" onclick="removeExpert(${idx})">✕</button>
            </div>
        `).join('');
        if (selectedExperts.length === 0) container.innerHTML = '<div style="color:rgba(255,255,255,.3); padding:8px 0;">Нет экспертов, нажмите "Добавить эксперта"</div>';
    }

    function addExpert() { selectedExperts.push(''); renderExpertsSelects(); }
    function updateExpert(idx, value) { selectedExperts[idx] = value ? parseInt(value) : ''; }
    function removeExpert(idx) { selectedExperts.splice(idx, 1); renderExpertsSelects(); }

    async function submitSprint() {
        const title = document.getElementById('f-title').value.trim();
        const desc = document.getElementById('f-desc').value.trim();
        if (!title || !desc) { alert('Заполните название и описание'); return; }
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('theme', document.getElementById('f-theme').value.trim());
        formData.append('tags', document.getElementById('f-tags').value.trim());
        formData.append('rules', document.getElementById('f-rules').value.trim());
        formData.append('useful_links', document.getElementById('f-links').value.trim());
        formData.append('registration_start', document.getElementById('f-reg-start').value);
        formData.append('registration_end', document.getElementById('f-reg-end').value);
        formData.append('jam_start', document.getElementById('f-jam-start').value);
        formData.append('jam_end', document.getElementById('f-jam-end').value);
        formData.append('voting_start', document.getElementById('f-vote-start').value);
        formData.append('voting_end', document.getElementById('f-vote-end').value);
        formData.append('max_participants', document.getElementById('f-maxp').value);
        const logoFile = document.getElementById('f-logo').files[0];
        if (logoFile) formData.append('logo', logoFile);
        formData.append('prizes', JSON.stringify(prizes.filter(p => p.reward.trim())));
        formData.append('experts', JSON.stringify(selectedExperts.filter(id => id)));

        try {
            const resp = await fetch('/swad/controllers/create_sprint.php', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) { alert('Спринт создан!'); closeCreate(); location.reload(); }
            else alert('Ошибка: ' + result.message);
        } catch (err) { alert('Ошибка: ' + err.message); }
    }

    // ---------- Инициализация ----------
    document.addEventListener('DOMContentLoaded', function() {
        renderGrid();
        if (sprintFromGet) {
            const sprint = sprints.find(s => s.id == sprintFromGet);
            if (sprint) {
                setTimeout(() => openView(sprintFromGet), 300);
            }
        }
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.sprint) {
                openView(event.state.sprint);
            } else {
                closeView();
            }
        });
        document.addEventListener('click', function(e) {
            if (e.target === viewOverlay) closeView();
            if (e.target === rulesOverlay) closeRulesConsent();
            if (e.target === registerOverlay) closeRegister();
        });
        document.getElementById('f-logo')?.addEventListener('change', function() {
            const preview = document.getElementById('logo-preview');
            if (this.files[0]) {
                const url = URL.createObjectURL(this.files[0]);
                preview.innerHTML = `<img src="${url}" style="max-width:100px; border-radius:8px">`;
            } else preview.innerHTML = '';
        });
    });

    // Эффект наклона для кнопок вне .grid
    (function() {
        const allBtns = document.querySelectorAll(`
            .btn-primary, .btn-join, .btn-team, .btn-share,
            .btn-next, .btn-submit, .btn-back, .btn-add, .btn-remove,
            .filter-btn, .nav-btn, .tab-btn, .btn-close
        `);
        if (!allBtns.length) return;
        function resetTilt(btn) { btn.style.transform = ''; }
        function handleMouseMove(e) {
            const btn = e.currentTarget;
            if (btn.closest('.grid')) return;
            const rect = btn.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const nx = (x / rect.width) * 2 - 1;
            const ny = (y / rect.height) * 2 - 1;
            const maxAngle = 15;
            const rotateY = maxAngle * nx;
            const rotateX = -maxAngle * ny;
            const translateY = -3;
            const scale = 1.04;
            btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
        }
        function handleMouseLeave(e) { resetTilt(e.currentTarget); }
        allBtns.forEach(btn => {
            btn.addEventListener('mousemove', handleMouseMove);
            btn.addEventListener('mouseleave', handleMouseLeave);
        });
    })();

    // Эффект наклона для кнопок внутри .grid (делегирование)
    (function() {
        const grid = document.getElementById('grid');
        if (!grid) return;
        let currentTarget = null;
        function resetTilt(el) { if (el) el.style.transform = ''; }
        function applyTilt(el, e) {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const nx = (x / rect.width) * 2 - 1;
            const ny = (y / rect.height) * 2 - 1;
            const maxAngle = 15;
            const rotateY = maxAngle * nx;
            const rotateX = -maxAngle * ny;
            const translateY = -3;
            const scale = 1.04;
            el.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
        }
        function onMouseMove(e) {
            const target = e.target.closest(`
                .btn-primary, .btn-join, .btn-team, .btn-share,
                .btn-next, .btn-submit, .btn-back, .btn-add, .btn-remove,
                .filter-btn, .nav-btn, .step-tab, .btn-close
            `);
            if (!target) {
                if (currentTarget) resetTilt(currentTarget);
                currentTarget = null;
                return;
            }
            if (currentTarget && currentTarget !== target) {
                resetTilt(currentTarget);
            }
            currentTarget = target;
            applyTilt(target, e);
        }
        function onMouseLeave() {
            if (currentTarget) {
                resetTilt(currentTarget);
                currentTarget = null;
            }
        }
        grid.addEventListener('mousemove', onMouseMove);
        grid.addEventListener('mouseleave', onMouseLeave);
    })();

    // Анимация поиска
    (function() {
        const searchWrap = document.querySelector('.search-wrap');
        const searchInput = document.querySelector('.search-input');
        if (!searchWrap || !searchInput) return;
        searchWrap.addEventListener('mousemove', function(e) {
            const rect = searchWrap.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const nx = (x / rect.width) * 2 - 1;
            const ny = (y / rect.height) * 10 - 5;
            const maxAngle = 5;
            const rotateY = maxAngle * nx;
            const rotateX = -maxAngle * ny;
            searchWrap.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
        });
        searchWrap.addEventListener('mouseleave', function() { searchWrap.style.transform = ''; });
        searchInput.addEventListener('input', function() {
            this.classList.remove('shake-it');
            void this.offsetWidth;
            this.classList.add('shake-it');
            this.addEventListener('animationend', function onAnimEnd() {
                this.classList.remove('shake-it');
                this.removeEventListener('animationend', onAnimEnd);
            });
        });
    })();
</script>

<style>
    @keyframes shakeSearch {
        0%, 100% { transform: translateX(0) rotateX(0deg) rotateY(0deg); }
        20% { transform: translateX(-3px) rotate(-1deg); }
        40% { transform: translateX(3px) rotate(1deg); }
        60% { transform: translateX(-2px) rotate(-0.5deg); }
        80% { transform: translateX(2px) rotate(0.5deg); }
    }
    .shake-it { animation: shakeSearch 0.3s ease-in-out; }
</style>

</body>
</html>