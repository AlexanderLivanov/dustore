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

// Загружаем призы и экспертов для каждого спринта
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

// Определяем, кому показывать кнопку "Оценить" (все авторизованные, кроме хоста)
$canRateMap = [];
foreach ($sprints as $sprint) {
    $canRateMap[$sprint['id']] = ($userId != 0);
}

// Добавляем флаг can_rate в каждый спринт для JS
foreach ($sprints as &$sprint) {
    $sprint['can_rate'] = $canRateMap[$sprint['id']];
}
unset($sprint);
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

        .sprint-header {
            padding: 13px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 0;
        }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 800; color: #e8ddf0; letter-spacing: -.3px; }
        .logo .brand { color: #c32178; }
        .header-nav { display: flex; gap: 6px; }
        .nav-btn {
            padding: 7px 16px;
            border-radius: 7px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            background: rgba(255,255,255,.05);
            color: rgba(255,255,255,.5);
            transition: .001s;
            text-decoration: none;
            display: inline-block;
        }
        .nav-btn:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .nav-btn.active { background: rgba(195,33,120,.15); color: #e8ddf0; border: 1px solid rgba(195,33,120,.3); }
        .btn-primary {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 7px;
            padding: 8px 18px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: .001s;
        }
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
            border-radius: 14px;
            padding: 26px 30px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
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
        .search-input {
            width: 100%;
            background: rgba(0,0,0,.4);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            padding: 8px 12px 8px 32px;
            color: #e8ddf0;
            font-size: 13px;
            outline: none;
        }
        .search-input:focus { border-color: #c32178; }
        .filters { display: flex; gap: 5px; flex-wrap: wrap; }
        .filter-btn {
            padding: 7px 13px;
            border-radius: 7px;
            border: 1px solid rgba(255,255,255,.1);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255,255,255,.04);
            color: rgba(255,255,255,.45);
            transition: .001s;
        }
        .filter-btn.active { background: rgba(195,33,120,.18); border-color: rgba(195,33,120,.4); color: #e8ddf0; }
        .filter-btn:hover:not(.active) { background: rgba(255,255,255,.08); color: #e8ddf0; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
        .empty { text-align: center; padding: 60px 20px; color: rgba(255,255,255,.25); }
        .empty .ico { font-size: 40px; margin-bottom: 10px; }

        /* ===== НОВЫЙ СТИЛЬ КАРТОЧЕК (уменьшенные) ===== */
        .card {
            background: #00000050;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.001s ease;
            display: flex;
            flex-direction: column;
            max-width: 500px;
            padding: 5px;
        }
        .card:hover {
            /* Убираем transform, чтобы не перебивать JS-эффект наклона */
            /* transform: translateY(-4px); */
            border-color: rgba(195, 33, 120, 0.4);
            transform: scale(1.02); /* только масштаб, без наклона */
        }
        .card-banner {
            height: 230px;
            background-size: cover;
            background-position: center;
            background-color: #ffffff05;
            position: relative;
            border-radius: 15px;

        }
        }
        .card-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(195,33,120,0.2), rgba(0,0,0,0.6));
            pointer-events: none;
        }
        .card-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .card-info {
            padding: 12px 14px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: rgba(0, 0, 0, 0.25);
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

            /* НОВОЕ: поднимаем на половину высоты, чтобы середина была на нижней грани баннера */
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

            /* НОВОЕ: те же свойства для лунной темы */
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
            background: rgba(0,0,0,0.3);
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
            margin: 5px 20px 5px;
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

        /* Остальные стили (модалки, формы) без изменений */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.75);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: .001s;
        }
        .overlay.open { opacity: 1; pointer-events: all; }
        .modal, .create-modal {
            background: #160822;
            border: 1px solid rgba(195,33,120,.3);
            border-radius: 14px;
            max-width: 740px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 28px;
            transform: translateY(16px);
            transition: .2s;
            box-shadow: 0 0 60px rgba(195,33,120,.15);
        }
        .overlay.open .modal, .overlay.open .create-modal { transform: translateY(0); }
        .modal-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .modal-title-row { display: flex; gap: 14px; align-items: center; }
        .modal-banner { font-size: 42px; line-height: 1; }
        .modal-banner img { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; }
        .modal-h2 { font-size: 20px; font-weight: 800; margin: 5px 0 2px; letter-spacing: -.3px; }
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
        .btn-rate {
            background: rgba(195,33,120,.2);
            border: 1px solid #c32178;
            color: #e8ddf0;
        }
        .btn-rate:hover { background: rgba(195,33,120,.4); }

        .create-modal { max-width: 600px; }
        .steps { display: flex; gap: 6px; margin-bottom: 20px; }
        .step-tab { flex: 1; padding: 8px 0; text-align: center; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.05); color: rgba(255,255,255,.4); border: 1px solid rgba(255,255,255,.08); }
        .step-tab.active { background: rgba(195,33,120,.18); border-color: rgba(195,33,120,.4); color: #e8ddf0; }
        .step-panel { display: none; }
        .step-panel.active { display: block; }
        .form-label { display: block; color: rgba(255,255,255,.45); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-label .req { color: #f87171; margin-left: 3px; }
        .form-input, .form-textarea, .dynamic-row input, .dynamic-row select {
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
        .form-group { margin-bottom: 13px; }
        .duration-group { display: flex; gap: 8px; }
        .duration-group .form-input { width: auto; flex: 1; }
        .duration-group select { width: auto; flex-shrink: 0; }
        .dynamic-row { display: flex; gap: 7px; align-items: center; margin-bottom: 7px; }
        .btn-remove { background: rgba(239,68,68,.1); border: none; color: #f87171; border-radius: 7px; padding: 7px 10px; cursor: pointer; }
        .btn-add { background: rgba(195,33,120,.1); border: 1px solid rgba(195,33,120,.25); color: rgba(195,33,120,.9); border-radius: 7px; padding: 5px 12px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .form-nav { display: flex; gap: 8px; margin-top: 20px; }
        .btn-next, .btn-submit { flex: 2; background: #c32178; border: none; color: #fff; border-radius: 9px; padding: 12px; cursor: pointer; font-weight: 700; }
        .btn-next:hover, .btn-submit:hover { background: #9e1a66; }
        .btn-back { flex: 1; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); border-radius: 9px; padding: 12px; cursor: pointer; }
        .btn-back:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .l4t-toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 999;
            background: #160822; border: 1px solid rgba(195,33,120,.4); border-radius: 10px;
            padding: 14px 18px; box-shadow: 0 8px 32px rgba(195,33,120,.2);
            transform: translateY(20px); opacity: 0; transition: .3s; pointer-events: none;
            max-width: 320px;
        }
        .l4t-toast.show { transform: translateY(0); opacity: 1; pointer-events: all; }
        .l4t-toast-title { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .l4t-toast-body { font-size: 12px; color: rgba(255,255,255,.45); margin-bottom: 10px; }
        .l4t-toast-btn { background: #c32178; border: none; color: #fff; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .l4t-toast-close { position: absolute; top: 10px; right: 12px; cursor: pointer; color: rgba(255,255,255,.3); }

        /* ===== ЛУННАЯ ТЕМА ===== */
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

        /* ===== ЗАГРУЗОЧНЫЙ ОВЕРЛЕЙ (на весь экран, с плавностью) ===== */
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
            <button class="filter-btn" onclick="setFilter('ongoing',this)">Идут</button>
            <button class="filter-btn" onclick="setFilter('finished',this)">Завершены</button>
        </div>
    </div>

    <div class="grid" id="grid"></div>
    <div class="empty" id="empty" style="display:none"><div class="ico">🔍</div><p>Спринты не найдены</p></div>
</div>

<!-- VIEW MODAL -->
<div class="overlay" id="view-overlay" onclick="closeView(event)"><div class="modal" id="view-modal" onclick="event.stopPropagation()"></div></div>

<!-- CREATE MODAL -->
<div class="overlay" id="create-overlay" onclick="closeCreateOverlay(event)">
    <div class="create-modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <h2 style="color:#e8ddf0;font-size:18px;font-weight:800">Создать спринт</h2>
            <button class="btn-close" onclick="closeCreate()">✕</button>
        </div>
        <div class="steps">
            <button class="step-tab active" id="tab1" onclick="goStep(1)">1. Основное</button>
            <button class="step-tab" id="tab2" onclick="goStep(2)">2. Время</button>
            <button class="step-tab" id="tab3" onclick="goStep(3)">3. Призы и эксперты</button>
        </div>

        <div class="step-panel active" id="step1">
            <div class="form-group">
                <label class="form-label">Логотип</label>
                <input type="file" id="f-logo" accept="image/*" class="form-input">
                <div id="logo-preview" style="margin-top:10px"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Название <span class="req">*</span></label>
                <input class="form-input" id="f-title" placeholder="Pixel Chaos Sprint #4">
            </div>
            <div class="form-group">
                <label class="form-label">Описание <span class="req">*</span></label>
                <textarea class="form-textarea" id="f-desc" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Тема</label>
                <input class="form-input" id="f-theme" placeholder="Киберпанк / Выживание / ...">
            </div>
            <div class="form-group">
                <label class="form-label">Теги (через запятую)</label>
                <input class="form-input" id="f-tags" placeholder="Unity, 48h, Пиксель-арт">
            </div>
            <div class="form-nav"><button class="btn-next" onclick="goStep(2)">Далее →</button></div>
        </div>

        <div class="step-panel" id="step2">
            <div class="form-group">
                <label class="form-label">Дата и время старта <span class="req">*</span></label>
                <input class="form-input" type="datetime-local" id="f-datetime">
            </div>
            <div class="form-group">
                <label class="form-label">Длительность</label>
                <div class="duration-group">
                    <input class="form-input" type="number" id="f-dur" value="24" style="width:100px">
                    <select id="f-dur-unit" class="form-input">
                        <option value="hours">Часы</option>
                        <option value="days" selected>Дни</option>
                        <option value="weeks">Недели</option>
                        <option value="months">Месяцы</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Макс. участников</label>
                <input class="form-input" type="number" id="f-maxp" value="100">
            </div>
            <div class="form-nav">
                <button class="btn-back" onclick="goStep(1)">← Назад</button>
                <button class="btn-next" onclick="goStep(3)">Далее →</button>
            </div>
        </div>

        <div class="step-panel" id="step3">
            <div class="sec-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
                <span class="section-title">Призы</span>
                <button class="btn-add" onclick="addPrize()">+ Добавить</button>
            </div>
            <div id="prizes-list"></div>
            <div class="sec-row" style="display:flex; justify-content:space-between; align-items:center; margin:16px 0 10px">
                <span class="section-title">Эксперты</span>
                <button class="btn-add" onclick="addExpert()">+ Добавить эксперта</button>
            </div>
            <div id="experts-list"></div>
            <div class="form-nav">
                <button class="btn-back" onclick="goStep(2)">← Назад</button>
                <button class="btn-submit" onclick="submitSprint()">Опубликовать</button>
            </div>
        </div>
    </div>
</div>

<!-- L4T Toast -->
<div class="l4t-toast" id="l4t-toast">
    <span class="l4t-toast-close" onclick="closeToast()">✕</span>
    <div class="l4t-toast-title">Собрать команду на L4T</div>
    <div class="l4t-toast-body">Разместите заявку на бирже L4T — найдите программиста, художника или геймдизайнера для вашего спринта.</div>
    <a href="/l4t" class="l4t-toast-btn">Открыть биржу L4T →</a>
</div>

<!-- ======== ЗАГРУЗОЧНЫЙ ОВЕРЛЕЙ ДЛЯ ГИФКИ (на весь экран, плавное появление) ======== -->
<div id="loading-overlay">
    <img src="/swad/static/img/intro_conturjam.gif" alt="Загрузка...">
</div>

<script>
    const sprintsData = <?php echo json_encode($sprints, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const allUsers = <?php echo json_encode($allUsers, JSON_HEX_TAG); ?>;
    let sprints = sprintsData;
    let userSprintIds = <?php echo json_encode($userSprintIds); ?>;
    let curFilter = 'all';
    let prizes = [{ place: '1', reward: '' }];
    let selectedExperts = [];

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function getStatus(sprint) {
        const now = new Date();
        const start = new Date(sprint.start_at);
        let end = new Date(start);
        const unit = sprint.duration_unit;
        const val = sprint.duration_value;
        if (unit === 'hours') end.setHours(end.getHours() + val);
        else if (unit === 'days') end.setDate(end.getDate() + val);
        else if (unit === 'weeks') end.setDate(end.getDate() + val * 7);
        else if (unit === 'months') end.setMonth(end.getMonth() + val);
        if (now < start) return 'upcoming';
        if (now > end) return 'finished';
        return 'ongoing';
    }

    function formatDuration(dur, unit) {
        const units = { hours: 'ч', days: 'д', weeks: 'нед', months: 'мес' };
        return dur + ' ' + (units[unit] || unit);
    }

    function countdown(startDate) {
        const diff = new Date(startDate) - new Date();
        if (diff <= 0) return 'Уже началось';
        const days = Math.floor(diff / 864e5);
        const hours = Math.floor((diff % 864e5) / 36e5);
        if (days > 0) return days + 'д ' + hours + 'ч';
        return hours + 'ч';
    }

    function badgeHtml(status) {
        const map = {
            registration: ['badge-active', 'Регистрация'],
            upcoming: ['badge-upcoming', 'Скоро'],
            ongoing: ['badge-ongoing', 'Идёт'],
            finished: ['badge-finished', 'Завершён']
        };
        const [cls, txt] = map[status] || map.upcoming;
        return `<span class="badge ${cls}">${txt}</span>`;
    }

    function updateStats() {
        const totalEl = document.getElementById('stat-total');
        const membersEl = document.getElementById('stat-members');
        const activeEl = document.getElementById('stat-active');
        if (totalEl) totalEl.textContent = sprints.length;
        if (membersEl) {
            const totalMembers = sprints.reduce((sum, s) => sum + (s.current_participants || 0), 0);
            membersEl.textContent = totalMembers;
        }
        if (activeEl) {
            const activeCount = sprints.filter(s => getStatus(s) !== 'finished').length;
            activeEl.textContent = activeCount;
        }
    }

function showLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        // Берём картинку внутри оверлея
        const img = overlay.querySelector('img');
        if (img) {
            // Сохраняем текущий src, сбрасываем и возвращаем
            const src = img.src;
            img.src = '';
            img.src = src;
        }
        overlay.classList.remove('visible');
        void overlay.offsetWidth; // принудительный reflow
        overlay.classList.add('visible');
    }
}

    // ===== ОБНОВЛЁННАЯ openView =====
    function openView(id) {
        const sprint = sprints.find(s => s.id == id);
        if (!sprint) return;

        // === ЕСЛИ ЭТО ЦЕЛЕВОЙ СПРИНТ (по названию) — ПОКАЗЫВАЕМ ГИФКУ И РЕДИРЕКТИМ ===
        if (sprint.title === 'К.О.Н.Т.У.Р.') {
            showLoadingOverlay();
            setTimeout(() => {
                location.href = '/jams/participant.php?sprint_id=' + sprint.id;
            }, 1950);
            return;
        }

        // === ОБЫЧНАЯ ЛОГИКА ДЛЯ ВСЕХ ОСТАЛЬНЫХ СПРИНТОВ ===
        const status = getStatus(sprint);
        const prizesHtml = (sprint.prizes || []).map((p, i) => {
            const medal = ['🥇','🥈','🥉'][i] || '🎖';
            return `<div class="prize-item"><span style="font-size:20px">${medal}</span><div><div class="pi-place">${p.place_num} место</div><div class="pi-reward">${escapeHtml(p.reward)}</div></div></div>`;
        }).join('');
        const expertsHtml = (sprint.experts || []).map(e => `
            <div class="expert-item">
                <span class="av">👤</span>
                <div><div class="ex-name">${escapeHtml(e.username)}</div><div class="ex-role">${escapeHtml(e.role || 'Эксперт')}</div></div>
            </div>
        `).join('');
        const logoHtml = sprint.logo_url ? `<img src="${escapeHtml(sprint.logo_url)}" alt="logo">` : '🎮';
        const themeHtml = sprint.theme ? `<div class="theme-box"><strong>Тема:</strong> ${escapeHtml(sprint.theme)}</div>` : '';
        const isJoined = userSprintIds.includes(sprint.id);
        let actionButton = isJoined
            ? `<a href="participant.php?sprint_id=${sprint.id}" class="btn-join">Панель участника</a>`
            : `<button class="btn-join" onclick="joinSprint(${sprint.id}, this, null, true)">Участвовать</button>`;
        let rateButton = '';
        if (sprint.can_rate) {
            rateButton = `<a href="/jams/rate.php?id=${sprint.id}" class="btn-rate">Оценить</a>`;
        }
        const modal = document.getElementById('view-modal');
        if (!modal) return;
        modal.innerHTML = `
            <div class="modal-head">
                <div class="modal-title-row">
                    <span class="modal-banner">${logoHtml}</span>
                    <div>${badgeHtml(status)}<div class="modal-h2">${escapeHtml(sprint.title)}</div><div class="modal-host">Организатор: ${escapeHtml(sprint.host_name || 'Dustore')}</div></div>
                </div>
                <button class="btn-close" onclick="closeView()">✕</button>
            </div>
            <p class="modal-desc">${escapeHtml(sprint.description)}</p>
            ${themeHtml}
            <div class="modal-stats">
                <div class="m-stat"><div class="val">${countdown(sprint.start_at)}</div><div class="lbl">До старта</div></div>
                <div class="m-stat"><div class="val">${formatDuration(sprint.duration_value, sprint.duration_unit)}</div><div class="lbl">Длительность</div></div>
                <div class="m-stat"><div class="val">${sprint.current_participants || 0}/${sprint.max_participants}</div><div class="lbl">Участники</div></div>
            </div>
            <div class="modal-body-cols" style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px;">
                <div><span class="section-title">Призы</span>${prizesHtml || '<p style="color:rgba(255,255,255,.3);">Нет призов</p>'}</div>
                <div><span class="section-title">Эксперты</span>${expertsHtml || '<p style="color:rgba(255,255,255,.3);">Нет экспертов</p>'}</div>
            </div>
            <div class="modal-actions">
                ${actionButton}
                ${rateButton}
                <button class="btn-team" onclick="window.location.href='/l4t/?action=jam&jam_id='+${sprint.id};">Команда</button>
                <button class="btn-share" onclick="shareSprint(${sprint.id})">Поделиться</button>
            </div>
        `;
        document.getElementById('view-overlay').classList.add('open');
    }

    // ===== ПЕРЕХВАТ КЛИКОВ ПО ССЫЛКАМ "Панель участника" ДЛЯ ЦЕЛЕВОГО СПРИНТА =====
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a.btn-join');
        if (link) {
            const card = link.closest('.card');
            if (card) {
                const url = link.href;
                const match = url.match(/sprint_id=(\d+)/);
                if (match) {
                    const sprintId = parseInt(match[1]);
                    const sprint = sprints.find(s => s.id == sprintId);
                    if (sprint && sprint.title === 'К.О.Н.Т.У.Р.') {
                        e.preventDefault();
                        showLoadingOverlay();
                        setTimeout(() => {
                            location.href = url;
                        }, 1950);
                    }
                }
            }
        }
    });

    // ===== ОСТАЛЬНЫЕ ФУНКЦИИ (БЕЗ ИЗМЕНЕНИЙ) =====
    async function joinSprint(sprintId, buttonEl, cardEl, isModal = false) {
        if (buttonEl.disabled) return;
        buttonEl.disabled = true;
        buttonEl.textContent = 'Подождите...';
        try {
            const formData = new FormData();
            formData.append('sprint_id', sprintId);
            const resp = await fetch('/swad/controllers/jams/join_sprint.php', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result.success) {
                const sprintIndex = sprints.findIndex(s => s.id == sprintId);
                if (sprintIndex !== -1) {
                    sprints[sprintIndex].current_participants = result.new_count;
                    userSprintIds.push(sprintId);
                }
                if (cardEl) {
                    const countSpan = cardEl.querySelector('.prog-lbl span:last-child');
                    if (countSpan) {
                        const max = sprints[sprintIndex].max_participants;
                        countSpan.textContent = `${result.new_count} / ${max}`;
                        const fill = cardEl.querySelector('.prog-fill');
                        if (fill) fill.style.width = `${Math.min(100, Math.round(result.new_count / max * 100))}%`;
                    }
                    const actionsDiv = cardEl.querySelector('.modal-actions');
                    if (actionsDiv) {
                        actionsDiv.innerHTML = `<a href="participant.php?sprint_id=${sprintId}" class="btn-join">Панель участника</a>
                                                <button class="btn-team" onclick="event.stopPropagation(); showL4tToast()">Команда</button>
                                                <button class="btn-share" onclick="event.stopPropagation(); shareSprint(${sprintId})">Поделиться</button>`;
                    }
                }
                if (isModal) openView(sprintId);
                else renderGrid();
                alert('Вы успешно присоединились к спринту!');
            } else {
                alert('Ошибка: ' + result.message);
                buttonEl.disabled = false;
                buttonEl.textContent = 'Участвовать';
            }
        } catch (err) {
            alert('Ошибка соединения: ' + err.message);
            buttonEl.disabled = false;
            buttonEl.textContent = 'Участвовать';
        }
    }

    function shareSprint(id) {
        const url = window.location.href + '?sprint=' + id;
        navigator.clipboard.writeText(url);
        alert('Ссылка скопирована');
    }

    function closeView(e) {
        const overlay = document.getElementById('view-overlay');
        if (!e || e.target === overlay) overlay.classList.remove('open');
    }

    function setFilter(f, el) {
        curFilter = f;
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        el.classList.add('active');
        renderGrid();
    }

    function showL4tToast() {
        const toast = document.getElementById('l4t-toast');
        if (toast) {
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 10000);
        }
    }
    function closeToast() { document.getElementById('l4t-toast')?.classList.remove('show'); }

    // ---------- CREATE SPRINT LOGIC ----------
    function openCreate() {
        prizes = [{ place: '1', reward: '' }];
        selectedExperts = [];
        document.getElementById('f-title').value = '';
        document.getElementById('f-desc').value = '';
        document.getElementById('f-theme').value = '';
        document.getElementById('f-tags').value = '';
        document.getElementById('f-datetime').value = '';
        document.getElementById('f-dur').value = '24';
        document.getElementById('f-dur-unit').value = 'days';
        document.getElementById('f-maxp').value = '100';
        document.getElementById('f-logo').value = '';
        document.getElementById('logo-preview').innerHTML = '';
        buildPrizes();
        renderExpertsSelects();
        goStep(1);
        document.getElementById('create-overlay').classList.add('open');
    }

    function closeCreate() { document.getElementById('create-overlay').classList.remove('open'); }
    function closeCreateOverlay(e) { if (e.target === document.getElementById('create-overlay')) closeCreate(); }
    function goStep(n) {
        for (let i = 1; i <= 3; i++) {
            document.getElementById(`step${i}`)?.classList.toggle('active', i === n);
            document.getElementById(`tab${i}`)?.classList.toggle('active', i === n);
        }
    }

    function buildPrizes() {
        const container = document.getElementById('prizes-list');
        if (!container) return;
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
        if (!container) return;
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
        const title = document.getElementById('f-title')?.value.trim() || '';
        const desc = document.getElementById('f-desc')?.value.trim() || '';
        const datetime = document.getElementById('f-datetime')?.value || '';
        if (!title || !desc || !datetime) {
            alert('Заполните название, описание и дату старта');
            return;
        }
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('theme', document.getElementById('f-theme')?.value.trim() || '');
        formData.append('tags', document.getElementById('f-tags')?.value.trim() || '');
        formData.append('start_at', datetime.replace('T', ' ') + ':00');
        formData.append('duration_value', document.getElementById('f-dur')?.value || '24');
        formData.append('duration_unit', document.getElementById('f-dur-unit')?.value || 'days');
        formData.append('max_participants', document.getElementById('f-maxp')?.value || '100');
        const logoFile = document.getElementById('f-logo')?.files[0];
        if (logoFile) formData.append('logo', logoFile);
        const validPrizes = prizes.filter(p => p.reward && p.reward.trim() !== '');
        formData.append('prizes', JSON.stringify(validPrizes));
        const validExperts = selectedExperts.filter(id => id && id !== '');
        formData.append('experts', JSON.stringify(validExperts));

        try {
            const response = await fetch('/swad/controllers/create_sprint.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert('Спринт успешно создан!');
                closeCreate();
                location.reload();
            } else {
                alert('Ошибка: ' + (result.message || 'Неизвестная ошибка'));
            }
        } catch (err) {
            alert('Ошибка соединения: ' + err.message);
        }
    }

    function renderGrid() {
        const searchText = document.getElementById('search')?.value.toLowerCase() || '';
        let filtered = sprints.filter(s => {
            const status = getStatus(s);
            const matchesFilter = curFilter === 'all' || status === curFilter;
            const matchesSearch = s.title.toLowerCase().includes(searchText) || s.description.toLowerCase().includes(searchText);
            return matchesFilter && matchesSearch;
        });
        const grid = document.getElementById('grid');
        const empty = document.getElementById('empty');
        if (!grid) return;
        if (filtered.length === 0) {
            grid.innerHTML = '';
            if (empty) empty.style.display = 'block';
            updateStats();
            return;
        }
        if (empty) empty.style.display = 'none';
        let html = '';
        filtered.forEach(s => {
            const status = getStatus(s);
            const pct = Math.min(100, Math.round(((s.current_participants || 0) / s.max_participants) * 100));
            const tags = (s.tags ? s.tags.split(',') : []).map(t => `<span class="tag">${escapeHtml(t.trim())}</span>`).join('');
            const bannerStyle = s.logo_url ? `background-image: url('${escapeHtml(s.logo_url)}'); background-size: cover; background-position: center;` : '';
            const isJoined = userSprintIds.includes(s.id);
            let actionButton = isJoined
                ? `<a href="participant.php?sprint_id=${s.id}" class="btn-join">Панель участника</a>`
                : `<button class="btn-join" onclick="event.stopPropagation(); joinSprint(${s.id}, this, this.closest('.card'))">Участвовать</button>`;
            let rateButton = '';
            if (s.can_rate) {
                rateButton = `<a href="/jams/rate.php?id=${s.id}" class="btn-rate">Оценить</a>`;
            }
            html += `<div class="card" onclick="openView(${s.id})">
                        <div class="card-banner" style="${bannerStyle}">
                            ${s.logo_url ? `<img src="${escapeHtml(s.logo_url)}" alt="logo" style="display:none;">` : ''}
                        </div>
                        <div class="card-desc">${escapeHtml(s.description)}</div>
                        <div class="tags">${tags}</div>
                        <div class="card-stats">
                            <div class="stat-box"><div class="s-lbl">До старта</div><div class="s-val">${countdown(s.start_at)}</div></div>
                            <div class="stat-box"><div class="s-lbl">Длительность</div><div class="s-val">${formatDuration(s.duration_value, s.duration_unit)}</div></div>
                        </div>
                        <div class="prog-wrap">
                            <div class="prog-lbl"><span>Участники</span><span>${s.current_participants || 0} / ${s.max_participants}</span></div>
                            <div class="prog-bar"><div class="prog-fill" style="width:${pct}%"></div></div>
                        </div>
                    </div>`;
        });
        grid.innerHTML = html;
        updateStats();
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderGrid();
        setInterval(renderGrid, 30000);
        const logoInput = document.getElementById('f-logo');
        if (logoInput) logoInput.addEventListener('change', function() {
            const preview = document.getElementById('logo-preview');
            if (preview && this.files[0]) {
                const url = URL.createObjectURL(this.files[0]);
                preview.innerHTML = `<img src="${url}" style="max-width:100px; border-radius:8px">`;
            } else if (preview) preview.innerHTML = '';
        });
    });
</script>

<!-- ====== СКРИПТ ДЛЯ КНОПОК ВНЕ .grid (статические) ====== -->
<script>
(function() {
    // Выбираем все интерактивные элементы, но исключаем те, что находятся внутри .grid
    const allBtns = document.querySelectorAll(`
        .btn-primary, .btn-join, .btn-team, .btn-share,
        .btn-next, .btn-submit, .btn-back, .btn-add, .btn-remove,
        .filter-btn, .nav-btn, .step-tab, .btn-close
    `);

    if (!allBtns.length) return;

    function resetTilt(btn) { btn.style.transform = ''; }

    function handleMouseMove(e) {
        const btn = e.currentTarget;
        // Если кнопка находится внутри .grid — пропускаем, т.к. там работает делегирование
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
        const scale = 1.05;
        btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
    }

    function handleMouseLeave(e) { resetTilt(e.currentTarget); }

    allBtns.forEach(btn => {
        btn.addEventListener('mousemove', handleMouseMove);
        btn.addEventListener('mouseleave', handleMouseLeave);
    });
})();
</script>

<!-- ====== СКРИПТ ДЛЯ КНОПОК ВНУТРИ .grid (делегирование) ====== -->
<script>
(function() {
    const grid = document.getElementById('grid');
    if (!grid) return;

    let currentTarget = null;

    function resetTilt(el) {
        if (el) el.style.transform = '';
    }

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
        const scale = 1.06;
        el.style.transform =
            `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
    }

    function onMouseMove(e) {
        // Ищем только кнопки, но не карточку (.card)
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
</script>

<style>
@keyframes shakeSearch {
    0%, 100% { transform: translateX(0) rotateX(0deg) rotateY(0deg); }
    20%      { transform: translateX(-3px) rotate(-1deg); }
    40%      { transform: translateX(3px) rotate(1deg); }
    60%      { transform: translateX(-2px) rotate(-0.5deg); }
    80%      { transform: translateX(2px) rotate(0.5deg); }
}
.shake-it { animation: shakeSearch 0.3s ease-in-out; }
</style>

<script>
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

</body>
</html>