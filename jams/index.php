<?php
require_once('../swad/static/elements/header.php');
require_once('../swad/config.php');

$dbInst = new Database();
$conn = $dbInst->connect();
if (!$conn) die('Ошибка подключения к базе данных');

$stmt = $conn->query("
    SELECT s.*,
           u.username as host_name,
           (SELECT COUNT(*) FROM sprint_participants WHERE sprint_id = s.id) as current_participants,
           (SELECT COUNT(*) FROM team_members tm WHERE tm.sprint_id = s.id)  as members_in_teams,
           (SELECT COUNT(*) FROM sprint_teams  st WHERE st.sprint_id = s.id)  as teams_count
    FROM sprints s
    LEFT JOIN users u ON s.host_user_id = u.id
    ORDER BY s.created_at DESC
");
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$userSprintIds = [];
$userId = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = $_SESSION['USERDATA']['id'];
    $partStmt = $conn->prepare("SELECT sprint_id FROM sprint_participants WHERE user_id = ?");
    $partStmt->execute([$userId]);
    $userSprintIds = $partStmt->fetchAll(PDO::FETCH_COLUMN);
}

function getPhase($s) {
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $regStart  = isset($s['registration_start']) ? new DateTime($s['registration_start'], new DateTimeZone('Europe/Moscow')) : null;
    $regEnd    = isset($s['registration_end'])   ? new DateTime($s['registration_end'],   new DateTimeZone('Europe/Moscow')) : null;
    $jamStart  = isset($s['jam_start'])          ? new DateTime($s['jam_start'],          new DateTimeZone('Europe/Moscow')) : null;
    $jamEnd    = isset($s['jam_end'])            ? new DateTime($s['jam_end'],            new DateTimeZone('Europe/Moscow')) : null;
    $voteStart = isset($s['voting_start'])       ? new DateTime($s['voting_start'],       new DateTimeZone('Europe/Moscow')) : null;
    $voteEnd   = isset($s['voting_end'])         ? new DateTime($s['voting_end'],         new DateTimeZone('Europe/Moscow')) : null;

    if ($regStart && $now < $regStart) return 'upcoming';
    if ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) return 'registration';
    if ($regEnd && $jamStart && $now >= $regEnd && $now < $jamStart) return 'pre_jam';
    if ($jamStart && $jamEnd && $now >= $jamStart && $now < $jamEnd) return 'jam';
    if ($jamEnd && $voteStart && $now >= $jamEnd && $now < $voteStart) return 'post_jam';
    if ($voteStart && $voteEnd && $now >= $voteStart && $now < $voteEnd) return 'voting';
    return 'finished';
}

foreach ($sprints as &$s) { $s['phase'] = getPhase($s); }
unset($s);

$canRateMap = [];
foreach ($sprints as $sprint) { $canRateMap[$sprint['id']] = ($userId != 0); }
foreach ($sprints as &$s) { $s['can_rate'] = $canRateMap[$s['id']]; }
unset($s);

$sprintFromGet   = isset($_GET['sprint']) ? (int)$_GET['sprint'] : 0;
$currentUsername = $_SESSION['USERDATA']['username'] ?? $_SESSION['USERDATA']['telegram_username'] ?? 'Участник';
$isLoggedIn      = !empty($_SESSION['USERDATA']['id']);
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
        body { min-height: 100vh; font-family: 'Manrope', system-ui, sans-serif; color: #e8ddf0; background: linear-gradient(180deg, #0f0a20, #240038, #780066); }
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
        .hero { background: rgba(0,0,0,.3); border: 1px solid rgba(195,33,120,.2); border-radius: 14px; padding: 26px 30px; margin-bottom: 24px; position: relative; overflow: hidden; }
        body.moonlight-theme .hero { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); }
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

        /* ===== КАРТОЧКИ ===== */
        .card {
            background: #00000050;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 20px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.001s ease;
            display: flex;
            flex-direction: column;
            max-width: 500px;
            padding: 5px;
        }
        .card:hover { border-color: rgba(195,33,120,.4); transform: scale(1.02); }
        .card-banner {
            height: 230px;
            background-size: cover;
            background-position: center;
            background-color: #ffffff05;
            position: relative;
            border-radius: 15px;
        }
        .card-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(195,33,120,.2), rgba(0,0,0,.6));
            pointer-events: none;
            border-radius: 15px;
        }
        .card-banner img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 15px; }

        .card-desc {
            font-size: 15px;
            color: rgba(255,255,255,.65);
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
            color: rgba(255,255,255,.85);
        }

        .tags { display: flex; flex-wrap: wrap; gap: 5px; margin: 3px 0; }
        .tag { background: rgba(195,33,120,.15); border: 1px solid rgba(195,33,120,.3); color: #e8ddf0; font-size: 10px; padding: 2px 8px; border-radius: 20px; }

        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin: -60px 0 0 0;
        }
        .stat-box { background: rgba(0,0,0,.3); border-radius: 10px; padding: 4px; text-align: center; }
        .stat-box .s-lbl { font-size: 9px; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .5px; }
        .stat-box .s-val { font-size: 12px; font-weight: 600; margin-top: 2px; }

        .prog-wrap { margin: 5px 20px 5px; }
        .prog-lbl { font-size: 10px; display: flex; justify-content: space-between; color: rgba(255,255,255,.5); margin-bottom: 4px; }
        .prog-bar { height: 4px; background: rgba(255,255,255,.1); border-radius: 4px; overflow: hidden; }
        .prog-fill { background: #c32178; height: 100%; border-radius: 4px; transition: width .3s; }

        /* ===== Сегментированная полоска состава ===== */
        .comp-wrap { margin: 5px 20px 5px; }
        .comp-head { font-size: 10px; display: flex; justify-content: space-between; color: rgba(255,255,255,.5); margin-bottom: 4px; }
        .comp-bar { height: 6px; background: rgba(255,255,255,.1); border-radius: 4px; overflow: hidden; display: flex; }
        .comp-seg { height: 100%; transition: width .3s; }
        .comp-seg-solo { background: #c32178; }
        .comp-seg-team { background: #5b8def; }
        .comp-legend { font-size: 9px; color: rgba(255,255,255,.45); margin-top: 5px; display: flex; align-items: center; flex-wrap: wrap; gap: 2px; }
        .comp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-right: 4px; }
        .comp-dot.solo { background: #c32178; }
        .comp-dot.team { background: #5b8def; }

        /* ===== МОДАЛКИ ===== */
        .overlay { position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 200; display: none; align-items: center; justify-content: center; padding: 16px; }
        .overlay.open { display: flex; }
        .modal { background: #160822; border: 1px solid rgba(195,33,120,.3); border-radius: 14px; max-width: 1200px; width: 100%; max-height: 98vh; overflow-y: auto; padding: 28px; box-shadow: 0 0 60px rgba(195,33,120,.15); }

        /* Модалка просмотра джема — фуллскрин с фиксированными шапкой/подвалом */
        #view-modal {
            max-width: 1100px;
            width: 100%;
            height: 94vh;
            max-height: 94vh;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        #view-modal .modal-head { padding: 22px 28px 0; margin-bottom: 14px; flex: none; }
        #view-modal .tabs        { padding: 0 28px; margin-bottom: 0; flex: none; }
        #view-modal .view-body   { flex: 1 1 auto; overflow-y: auto; padding: 16px 28px; min-height: 0; display: flex; flex-direction: column; }
        #view-modal .view-body .tab-panel.active { flex: 1 1 auto; }
        #view-modal .modal-actions {
            flex: none; margin-top: 0; padding: 16px 28px;
            border-top: 1px solid rgba(255,255,255,.1);
            background: #160822;
        }
        body.moonlight-theme #view-modal .modal-actions { background: transparent; backdrop-filter: blur(20px); }

        /* Мобилка — модалка во весь экран */
        @media (max-width: 640px) {
            .overlay { padding: 0; }
            #view-modal {
                height: 100dvh; max-height: 100dvh;
                border-radius: 0; border: none;
            }
            #view-modal .modal-head    { padding: 16px 16px 0; }
            #view-modal .tabs          { padding: 0 16px; overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; }
            #view-modal .tabs::-webkit-scrollbar { height: 0; }
            #view-modal .tab-btn       { white-space: nowrap; flex: none; }
            #view-modal .view-body     { padding: 14px 16px; }
            #view-modal .modal-actions { padding: 12px 16px; }
            #view-modal .modal-actions .btn-join,
            #view-modal .modal-actions .btn-rate,
            #view-modal .modal-actions .btn-team,
            #view-modal .modal-actions .btn-share { flex: 1 1 auto; }
        }
        .modal-sm { max-width: 600px; }
        .modal-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .modal-title-row { display: flex; gap: 14px; align-items: center; }
        .modal-banner { font-size: 42px; line-height: 1; }
        .modal-banner img { width: 48px; height: 48px; object-fit: cover; border-radius: 12px; }
        .modal-h2 { font-size: 22px; font-weight: 800; margin: 5px 0 2px; letter-spacing: -.3px; }
        .modal-host { color: rgba(255,255,255,.35); font-size: 12px; }
        .btn-close { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); border-radius: 7px; padding: 5px 11px; cursor: pointer; font-size: 16px; }
        .btn-close:hover { background: rgba(255,255,255,.12); color: #e8ddf0; }
        .theme-box { background: rgba(195,33,120,.07); border: 1px solid rgba(195,33,120,.2); border-radius: 10px; padding: 11px 15px; margin-bottom: 16px; font-size: 13px; }
        .theme-box strong { color: #c32178; }
        .section-title { font-weight: 700; font-size: 13px; margin: 0 0 9px; display: block; text-transform: uppercase; letter-spacing: .05em; opacity: .7; }
        .prize-item, .expert-item { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); border-radius: 9px; padding: 9px 13px; margin-bottom: 7px; }
        .prize-item .pi-reward, .expert-item .ex-name { font-weight: 600; font-size: 13px; }
        .prize-item .pi-place, .expert-item .ex-role { color: rgba(255,255,255,.35); font-size: 11px; }
        .modal-actions { display: flex; gap: 8px; margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,.07); flex-wrap: wrap; }
        .btn-join, .btn-team, .btn-share, .btn-rate {
            background: #c32178; border: none; color: #fff; border-radius: 13px; padding: 6px 12px;
            font-weight: 600; font-size: 12px; cursor: pointer; transition: .001s;
            text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        }
        .btn-join:hover:not(:disabled) { background: #9e1a66; }
        .btn-join:disabled { opacity: .6; cursor: not-allowed; }
        .btn-team { background: rgba(195,33,120,.1); border: 1px solid rgba(195,33,120,.3); color: #e8ddf0; }
        .btn-team:hover { background: rgba(195,33,120,.2); }
        .btn-share { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); }
        .btn-share:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .btn-rate { background: rgba(195,33,120,.2); border: 1px solid #c32178; color: #e8ddf0; }
        .btn-rate:hover { background: rgba(195,33,120,.4); }

        /* Табы в модалке просмотра */
        .tabs { display: flex; gap: 4px; border-bottom: 1px solid rgba(255,255,255,.1); margin-bottom: 16px; flex-wrap: wrap; }
        .tab-btn { padding: 8px 16px; background: transparent; border: none; color: rgba(255,255,255,.4); font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: .001s; }
        .tab-btn:hover { color: #e8ddf0; }
        .tab-btn.active { color: #e8ddf0; border-bottom-color: #c32178; }
        .tab-panel { display: none; padding: 8px 0 16px; }
        .tab-panel.active { display: block; }
        .tab-panel p, .tab-panel div { color: rgba(255,255,255,.7); line-height: 1.7; }

        .desc-content { max-height: none; overflow: visible; }
        .desc-content.expanded { max-height: none; }
        .desc-content .desc-text { white-space: pre-wrap; word-break: break-word; }
        .desc-more-btn { background: none; border: none; color: #c32178; cursor: pointer; font-weight: 600; font-size: 13px; padding: 4px 0; margin-top: 6px; }
        .desc-more-btn:hover { text-decoration: underline; }

        /* Формы */
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; color: rgba(255,255,255,.45); font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .form-input, .form-textarea { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.14); border-radius: 8px; padding: 9px 12px; color: #e8ddf0; font-size: 13px; outline: none; }
        .form-input:focus, .form-textarea:focus { border-color: #c32178; }
        .form-textarea { resize: vertical; }
        .radio-group { display: flex; gap: 20px; margin-top: 6px; }
        .radio-group label { display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer; }
        .radio-group input[type="radio"] { accent-color: #c32178; width: 18px; height: 18px; }

        /* Регламент / Соглашение (consent-гейт) */
        .rules-consent-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.9); z-index: 300; display: none; align-items: center; justify-content: center; padding: 16px; }
        .rules-consent-overlay.open { display: flex; }
        .rules-consent-modal { background: #160822; border: 1px solid rgba(195,33,120,.3); border-radius: 14px; max-width: 1000px; width: 100%; padding: 28px; max-height: 88vh; display: flex; flex-direction: column; }
        .rules-consent-modal h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; flex: none; }
        .consent-cols { display: flex; gap: 18px; flex: 1 1 auto; min-height: 0; margin-bottom: 4px; }
        .consent-cols > div { flex: 1 1 0; display: flex; flex-direction: column; min-height: 0; }
        @media (max-width: 720px) {
            .consent-cols { flex-direction: column; gap: 0; overflow-y: auto; }
            .consent-cols > div { flex: none; }
        }
        .rules-consent-modal .rules-body { font-size: 13px; color: rgba(255,255,255,.7); background: rgba(0,0,0,.3); border-radius: 8px; padding: 12px; margin-bottom: 0; flex: 1 1 auto; min-height: 120px; overflow-y: auto; white-space: pre-wrap; }
        .rules-consent-modal .consent-check,
        .rules-consent-modal .actions { flex: none; }
        .rules-consent-modal .actions { display: flex; gap: 10px; justify-content: flex-end; }
        .rules-consent-modal .btn-agree { background: #c32178; color: #fff; border: none; padding: 8px 20px; border-radius: 7px; font-weight: 600; cursor: pointer; }
        .rules-consent-modal .btn-agree:hover { background: #9e1a66; }
        .rules-consent-modal .btn-decline { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); padding: 8px 20px; border-radius: 7px; cursor: pointer; }
        .rules-consent-modal .btn-decline:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }

        /* Форма создания */
        .form-nav { display: flex; gap: 8px; margin-top: 20px; }
        .btn-next, .btn-submit { flex: 2; background: #c32178; border: none; color: #fff; border-radius: 9px; padding: 12px; cursor: pointer; font-weight: 700; }
        .btn-next:hover, .btn-submit:hover { background: #9e1a66; }
        .btn-back { flex: 1; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); border-radius: 9px; padding: 12px; cursor: pointer; }
        .btn-back:hover { background: rgba(255,255,255,.1); color: #e8ddf0; }
        .step-panel { display: none; }
        .step-panel.active { display: block; }
        .dynamic-row { display: flex; gap: 7px; align-items: center; margin-bottom: 7px; }
        .dynamic-row input, .dynamic-row select { width: 100%; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.14); border-radius: 8px; padding: 9px 12px; color: #e8ddf0; font-size: 13px; outline: none; }
        .btn-remove { background: rgba(239,68,68,.1); border: none; color: #f87171; border-radius: 7px; padding: 7px 10px; cursor: pointer; }
        .btn-add { background: rgba(195,33,120,.1); border: 1px solid rgba(195,33,120,.25); color: rgba(195,33,120,.9); border-radius: 7px; padding: 5px 12px; cursor: pointer; font-size: 12px; font-weight: 600; }

        /* Лунная тема */
        body.moonlight-theme { background: #05020a; background-image: url("/swad/static/img/Moonlight_pict.jpeg"); background-size: cover; background-attachment: fixed; background-position: center 35%; }
        body.moonlight-theme .btn-primary, body.moonlight-theme .btn-next, body.moonlight-theme .btn-submit, body.moonlight-theme .btn-join { background: #285682 !important; }
        body.moonlight-theme .btn-primary:hover, body.moonlight-theme .btn-next:hover, body.moonlight-theme .btn-submit:hover, body.moonlight-theme .btn-join:hover { background: #193753 !important; }
        body.moonlight-theme .sprint-header, body.moonlight-theme .hero, body.moonlight-theme .card, body.moonlight-theme .modal { border-color: rgba(255,255,255,.08); }
        body.moonlight-theme .filter-btn, body.moonlight-theme .nav-btn { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
        body.moonlight-theme .filter-btn.active { background: rgb(24 105 147 / 22%); border-color: rgb(25 105 151 / 40%); }
        body.moonlight-theme .form-input, body.moonlight-theme .form-textarea, body.moonlight-theme .dynamic-row input, body.moonlight-theme .dynamic-row select { background: rgba(0,0,0,.5); border-color: rgba(255,255,255,.12); }
        body.moonlight-theme .stat-box, body.moonlight-theme .prize-item, body.moonlight-theme .expert-item, body.moonlight-theme .theme-box { background: rgba(0,0,0,.35); border-color: rgba(255,255,255,.06); }
        body.moonlight-theme .tag { background: rgba(195,33,120,.18); border-color: rgba(195,33,120,.28); }
        body.moonlight-theme .overlay { background: rgba(0,0,0,.85); }
        body.moonlight-theme .hero h1 span { color: #e00000; }
        body.moonlight-theme .search-input { background: rgba(0,0,0,.6); border-color: rgba(255,255,255,.2); color: #f0e6ff; }
        body.moonlight-theme .search-input:focus { border-color: #4a9eff; }
        body.moonlight-theme .modal, body.moonlight-theme .create-modal { background: #0a132545; border: 1px solid rgba(255,255,255,.15); box-shadow: 0 0 60px rgba(0,0,0,.6); backdrop-filter: blur(20px); }
        body.moonlight-theme .card { background: #ffffff07; }
        body.moonlight-theme .btn-team, body.moonlight-theme .btn-share { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.2); }

        #loading-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.92); display: flex; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(4px); opacity: 0; pointer-events: none; transition: opacity .3s; }
        #loading-overlay.visible { opacity: 1; pointer-events: auto; }
        #loading-overlay img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* FAQ / Соглашение — аккордеон */
        .faq-list { display:flex; flex-direction:column; gap:8px; }
        .faq-item {
            border:1px solid rgba(255,255,255,.08);
            border-radius:10px; overflow:hidden;
            background:rgba(255,255,255,.02);
        }
        .faq-q {
            display:flex; justify-content:space-between; align-items:center; gap:10px;
            padding:14px 16px; cursor:pointer; font-weight:600;
            color:#e8ddf0; user-select:none; transition:background .2s;
        }
        .faq-q:hover { background:rgba(195,33,120,.08); }
        .faq-q .faq-chevron {
            color:rgba(255,255,255,.5); flex-shrink:0;
            font-size:18px; line-height:1;
            transition:transform .25s ease;
        }
        .faq-item.open .faq-q .faq-chevron { transform:rotate(180deg); }
        .faq-a {
            max-height:0; overflow:hidden; padding:0 16px;
            color:rgba(255,255,255,.7); line-height:1.6;
            transition:max-height .3s ease, padding .3s ease;
        }
        .faq-item.open .faq-a { max-height:2000px; padding:0 16px 14px; }

        /* Consent-гейт: подписи и чекбокс */
        .consent-section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: rgba(255,255,255,.4); margin: 0 0 6px; }
        .consent-check { display: flex; align-items: flex-start; gap: 10px; margin: 16px 0; padding: 12px 14px; background: rgba(195,33,120,.07); border: 1px solid rgba(195,33,120,.25); border-radius: 9px; cursor: pointer; font-size: 13px; line-height: 1.5; color: #e8ddf0; }
        .consent-check input[type="checkbox"] { accent-color: #c32178; width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; cursor: pointer; }
        .btn-agree:disabled { opacity: .45; cursor: not-allowed; }

        /* Шаг «Команда» в модалке регистрации */
        .ts-member { display:flex; align-items:center; gap:8px; padding:7px 0; font-size:13px; color:#e8ddf0; }
        .ts-input-row { display:flex; gap:8px; margin:10px 0; }
        .ts-input-row .form-input { flex:1; }
        .ts-msg { font-size:12px; margin-top:8px; }
    </style>
</head>
<body>

<header class="sprint-header">
    <div class="logo"><span class="brand"></span></div>
    <div style="display:flex;align-items:center;gap:10px">
        <div class="header-nav">
            <a class="nav-btn" href="participant.php">Моё участие</a>
        </div>
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

<!-- Модалка просмотра -->
<div class="overlay" id="view-overlay" onclick="closeView(event)">
    <div class="modal" id="view-modal" onclick="event.stopPropagation()"></div>
</div>

<!-- Модалка регламента / соглашения -->
<div class="rules-consent-overlay" id="rules-consent-overlay" onclick="closeRulesConsent(event)">
    <div class="rules-consent-modal" onclick="event.stopPropagation()">
        <h3 id="consent-title">📜 Перед участием</h3>

        <div class="consent-cols">
            <div id="consent-rules-block">
                <div class="consent-section-label">Регламент</div>
                <div class="rules-body" id="rules-body"></div>
            </div>

            <div id="consent-agreement-block">
                <div class="consent-section-label">Пользовательское соглашение</div>
                <div class="rules-body" id="agreement-body"></div>
            </div>
        </div>

        <label class="consent-check" for="consent-accept">
            <input type="checkbox" id="consent-accept" onchange="document.getElementById('consent-agree-btn').disabled = !this.checked">
            <span>Я прочитал(а) и принимаю регламент и пользовательское соглашение джема</span>
        </label>

        <div class="actions">
            <button class="btn-decline" onclick="closeRulesConsent()">Отказаться</button>
            <button class="btn-agree" id="consent-agree-btn" onclick="agreeToRules()" disabled>Принять и продолжить</button>
        </div>
    </div>
</div>

<!-- Модалка регистрации -->
<div class="overlay" id="register-overlay" onclick="closeRegister(event)">
    <div class="modal modal-sm" onclick="event.stopPropagation()">
        <div class="modal-head">
            <h3 id="reg-modal-title" style="color:#e8ddf0;font-size:18px;font-weight:800">Регистрация на спринт</h3>
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
                <div class="form-group"><label class="form-label">О себе</label><textarea class="form-textarea" id="reg-extra" rows="3" placeholder="Расскажите о своих навыках, опыте..."></textarea></div>
                <div class="form-group"><label class="form-label">Ссылки – каждая с новой строки</label><textarea class="form-textarea" id="reg-links" rows="2" placeholder="https://..."></textarea></div>
            </div>
            <div id="team-fields" style="display:none;">
                <p style="color:rgba(255,255,255,.5);font-size:13px;margin-bottom:12px;">Остальные участники команды регистрируются отдельно.</p>
                <div class="form-group"><label class="form-label">Ваш псевдоним в команде</label><input class="form-input" id="reg-team-alias" placeholder="Ваш ник" value="<?= htmlspecialchars($currentUsername) ?>"></div>
                <div class="form-group"><label class="form-label">Город</label><input class="form-input" id="reg-team-city" placeholder="Город"></div>
                <div class="form-group"><label class="form-label">О вас</label><textarea class="form-textarea" id="reg-team-extra" rows="3" placeholder="Ваши навыки, роль в команде..."></textarea></div>
                <div class="form-group"><label class="form-label">Ваши ссылки</label><textarea class="form-textarea" id="reg-team-links" rows="2" placeholder="https://..."></textarea></div>
            </div>
            <div class="modal-actions" style="border-top:none;padding-top:0;margin-top:10px;">
                <button type="submit" class="btn-submit" style="width:100%;">Зарегистрироваться</button>
            </div>
        </form>

        <!-- Шаг 2: Команда (после успешной регистрации) -->
        <div id="team-step" style="display:none;">
            <div id="team-step-body"></div>
            <div class="modal-actions" style="border-top:1px solid rgba(255,255,255,.07);padding-top:14px;margin-top:16px;">
                <button type="button" class="btn-team" style="flex:1;cursor:pointer;" onclick="finishRegistration()">Продолжить соло</button>
            </div>
        </div>
    </div>
</div>

<!-- Модалка создания -->
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
            <div class="form-group"><label class="form-label">Название *</label><input class="form-input" id="f-title" placeholder="Pixel Chaos Sprint #4"></div>
            <div class="form-group"><label class="form-label">Описание *</label><textarea class="form-textarea" id="f-desc" rows="3"></textarea></div>
            <div class="form-group"><label class="form-label">Тема</label><input class="form-input" id="f-theme" placeholder="Киберпанк / Выживание / ..."></div>
            <div class="form-group"><label class="form-label">Теги (через запятую)</label><input class="form-input" id="f-tags" placeholder="Unity, 48h, Пиксель-арт"></div>
            <div class="form-group"><label class="form-label">Регламент (Markdown)</label><textarea class="form-textarea" id="f-rules" rows="4" placeholder="Правила участия, требования к работам..."></textarea></div>
            <div class="form-group"><label class="form-label">Полезные ссылки (каждая с новой строки)</label><textarea class="form-textarea" id="f-links" rows="2" placeholder="https://..."></textarea></div>
            <div class="form-nav"><button class="btn-next" onclick="goStep(2)">Далее →</button></div>
        </div>
        <div class="step-panel" id="step2">
            <div class="form-group"><label class="form-label">Регистрация с</label><input class="form-input" type="datetime-local" id="f-reg-start"></div>
            <div class="form-group"><label class="form-label">Регистрация до</label><input class="form-input" type="datetime-local" id="f-reg-end"></div>
            <div class="form-group"><label class="form-label">Начало джема</label><input class="form-input" type="datetime-local" id="f-jam-start"></div>
            <div class="form-group"><label class="form-label">Окончание джема</label><input class="form-input" type="datetime-local" id="f-jam-end"></div>
            <div class="form-group"><label class="form-label">Голосование с</label><input class="form-input" type="datetime-local" id="f-vote-start"></div>
            <div class="form-group"><label class="form-label">Голосование до</label><input class="form-input" type="datetime-local" id="f-vote-end"></div>
            <div class="form-group"><label class="form-label">Макс. участников</label><input class="form-input" type="number" id="f-maxp" value="100"></div>
            <div class="form-nav"><button class="btn-back" onclick="goStep(1)">← Назад</button><button class="btn-next" onclick="goStep(3)">Далее →</button></div>
        </div>
        <div class="step-panel" id="step3">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><span class="section-title">Призы</span><button class="btn-add" onclick="addPrize()">+ Добавить</button></div>
            <div id="prizes-list"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 10px"><span class="section-title">Эксперты</span><button class="btn-add" onclick="addExpert()">+ Добавить эксперта</button></div>
            <div id="experts-list"></div>
            <div class="form-nav"><button class="btn-back" onclick="goStep(2)">← Назад</button><button class="btn-submit" onclick="submitSprint()">Опубликовать</button></div>
        </div>
    </div>
</div>

<div id="loading-overlay">
    <img src="/swad/static/img/intro_conturjam.gif" alt="Загрузка...">
</div>

<script>
    const sprintsData    = <?php echo json_encode($sprints, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const allUsers       = <?php echo json_encode($allUsers, JSON_HEX_TAG); ?>;
    const sprintFromGet  = <?php echo (int)$sprintFromGet; ?>;
    const currentUsername = <?php echo json_encode($currentUsername); ?>;
    const isLoggedIn     = <?php echo json_encode($isLoggedIn); ?>;

    let sprints        = sprintsData;
    let userSprintIds  = <?php echo json_encode($userSprintIds); ?>;
    let curFilter      = 'all';
    let prizes         = [{ place: '1', reward: '' }];
    let selectedExperts = [];
    let pendingSprintId = null;
    let regSprintId    = null;   // спринт, в который только что зарегались

    const viewOverlay     = document.getElementById('view-overlay');
    const viewModal       = document.getElementById('view-modal');
    const rulesOverlay    = document.getElementById('rules-consent-overlay');
    const rulesBody       = document.getElementById('rules-body');
    const registerOverlay = document.getElementById('register-overlay');

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
    }

    function formatDateRange(start, end) {
        if (!start) return '—';
        const d1 = new Date(start);
        const d2 = end ? new Date(end) : null;
        if (d2) return d1.toLocaleDateString('ru-RU') + ' – ' + d2.toLocaleDateString('ru-RU');
        return d1.toLocaleDateString('ru-RU');
    }

    function renderSprintFaq(raw) {
        if (!raw || !raw.trim())
            return '<p style="color:rgba(255,255,255,.3);">Пока пусто</p>';

        const blocks = raw.split(/\n(?=##\s)/);
        const items = [];
        blocks.forEach(block => {
            const m = block.match(/^##\s*(.+?)\n([\s\S]*)$/);
            if (m) items.push({ q: m[1].trim(), a: m[2].trim() });
            else if (block.trim().startsWith('##')) items.push({ q: block.replace(/^##\s*/, '').trim(), a: '' });
        });
        if (!items.length) return `<div class="faq-fallback">${markdownToHtml(raw)}</div>`;

        return items.map(it => `
            <div class="faq-item">
                <div class="faq-q" onclick="this.closest('.faq-item').classList.toggle('open')">
                    <span>${escapeHtml(it.q)}</span>
                    <span class="faq-chevron">⌄</span>
                </div>
                <div class="faq-a">${markdownToHtml(it.a)}</div>
            </div>`).join('');
    }

    function markdownToHtml(text) {
        if (!text) return '';
        let html = escapeHtml(text);
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim,  '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim,   '<h1>$1</h1>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g,     '<em>$1</em>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        html = html.replace(/(?<![">])(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        html = html.replace(/^\- (.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    function badgeHtml(phase) {
        const map = {
            registration: 'Регистрация', upcoming: 'Скоро', jam: 'Джем',
            voting: 'Голосование', finished: 'Завершён', pre_jam: 'Скоро джем', post_jam: 'Пост-джем'
        };
        return `<span class="badge">${map[phase] || phase}</span>`;
    }

    function updateStats() {
        const t = document.getElementById('stat-total');
        const m = document.getElementById('stat-members');
        const a = document.getElementById('stat-active');
        if (t) t.textContent = sprints.length;
        if (m) m.textContent = sprints.reduce((s, x) => s + (x.current_participants || 0), 0);
        if (a) a.textContent = sprints.filter(s => s.phase !== 'finished').length;
    }

    const phaseMap = {
        upcoming: 'Скоро', registration: 'Регистрация', pre_jam: 'Скоро джем',
        jam: 'Джем', post_jam: 'Завершён джем', voting: 'Голосование', finished: 'Завершён'
    };

    /* ===== renderGrid ===== */
    function renderGrid() {
        const q = document.getElementById('search')?.value.toLowerCase() || '';
        const filtered = sprints.filter(s => {
            const phase = s.phase || 'finished';
            return (curFilter === 'all' || phase === curFilter) &&
                   (s.title.toLowerCase().includes(q) || s.description.toLowerCase().includes(q));
        });
        const grid  = document.getElementById('grid');
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
            const phase     = s.phase || 'finished';
            const phaseText = phaseMap[phase] || phase;
            const tags      = (s.tags ? s.tags.split(',') : []).map(t => `<span class="tag">${escapeHtml(t.trim())}</span>`).join('');
            const banner    = s.logo_url ? `background-image:url('${escapeHtml(s.logo_url)}');background-size:cover;background-position:center;` : '';

            html += `<div class="card" onclick="openView(${s.id})">
                <div class="card-banner" style="${banner}">
                    ${s.logo_url ? `<img src="${escapeHtml(s.logo_url)}" alt="" style="display:none">` : ''}
                </div>
                <div class="card-desc">${escapeHtml(s.title)}</div>
                <div class="tags">${tags}</div>
                <div class="card-stats">
                    <div class="stat-box"><div class="s-lbl">Статус</div><div class="s-val">${phaseText}</div></div>
                    <div class="stat-box"><div class="s-lbl">Регистрация</div><div class="s-val">${formatDateRange(s.registration_start, s.registration_end)}</div></div>
                </div>
               ${(() => {
                    const total   = s.current_participants || 0;
                    const inTeams = s.members_in_teams || 0;
                    const solo    = Math.max(0, total - inTeams);
                    const soloPct = total ? (solo / total * 100) : 0;
                    const teamPct = total ? (inTeams / total * 100) : 0;
                    return `<div class="comp-wrap">
                    <div class="comp-head"><span>Участники</span><span>${total} / ${s.max_participants}</span></div>
                    <div class="comp-bar">
                        <div class="comp-seg comp-seg-solo" style="width:${soloPct}%"></div>
                        <div class="comp-seg comp-seg-team" style="width:${teamPct}%"></div>
                    </div>
                    <div class="comp-legend">
                        <span class="comp-dot solo"></span>${solo} соло
                        <span class="comp-dot team" style="margin-left:8px"></span>${inTeams} в командах${(s.teams_count|0) ? ` · команд: ${s.teams_count}` : ''}
                    </div>
                </div>`;
                })()}
            </div>`;
        });
        grid.innerHTML = html;
        updateStats();
    }

    /* ===== openView ===== */
    function openView(id) {
        const sprint = sprints.find(s => s.id == id);
        if (!sprint) return;

        const phase    = sprint.phase || 'finished';
        const isJoined = userSprintIds.includes(sprint.id);
        const canRate  = sprint.can_rate && phase === 'voting';

        const descHtml  = markdownToHtml(sprint.description || '');
        const rulesHtml = markdownToHtml(sprint.rules || '');
        const faqHtml = renderSprintFaq(sprint.faq || '');
        const agreementHtml = renderSprintFaq(sprint.agreement || '');

        const linksHtml = markdownToHtml(sprint.useful_links || '');

        const prizesHtml = (sprint.prizes || []).map((p, i) => {
            const medal = ['🥇','🥈','🥉'][i] || '🎖';
            return `<div class="prize-item"><span style="font-size:20px">${medal}</span><div><div class="pi-place">${p.place_num} место</div><div class="pi-reward">${escapeHtml(p.reward)}</div></div></div>`;
        }).join('') || '<p style="color:rgba(255,255,255,.3);">Нет призов</p>';

        const expertsHtml = (sprint.experts || []).map(e =>
            `<div class="expert-item"><span>👤</span><div><div class="ex-name">${escapeHtml(e.username)}</div><div class="ex-role">${escapeHtml(e.role || 'Эксперт')}</div></div></div>`
        ).join('') || '<p style="color:rgba(255,255,255,.3);">Нет экспертов</p>';

        const logoHtml  = sprint.logo_url ? `<img src="${escapeHtml(sprint.logo_url)}" alt="logo">` : '🎮';
        const themeHtml = sprint.theme ? `<p><strong>Тема:</strong> ${escapeHtml(sprint.theme)}</p>` : '';

        let actionButton = '';
        if (isJoined) {
            actionButton = `<a href="participant.php?sprint_id=${sprint.id}" class="btn-join">Панель участника</a>`;
        } else {
            const canJoin = ['registration','upcoming','pre_jam'].includes(phase);
            actionButton = canJoin
                ? `<button class="btn-join" onclick="startJoin(${sprint.id})">Участвовать</button>`
                : `<button class="btn-join" disabled>Регистрация закрыта</button>`;
        }

        viewModal.innerHTML = `
            <div class="modal-head">
                <div class="modal-title-row">
                    <span class="modal-banner">${logoHtml}</span>
                    <div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            ${badgeHtml(phase)}
                            <span style="font-size:12px;color:rgba(255,255,255,.4);">${sprint.current_participants || 0} участников</span>
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
                ${sprint.rules       ? `<button class="tab-btn" data-tab="rules">Регламент</button>` : ''}
                ${sprint.useful_links ? `<button class="tab-btn" data-tab="links">Ссылки</button>` : ''}
                ${sprint.faq ? `<button class="tab-btn" data-tab="faq">FAQ</button>` : ''}
                ${sprint.agreement ? `<button class="tab-btn" data-tab="agreement">Соглашение</button>` : ''}
            </div>
            <div class="view-body">
            <div class="tab-panel active" id="tab-overview">
                <div class="desc-content" id="desc-content"><div class="desc-text">${descHtml}</div></div>
                ${themeHtml}
            </div>
            <div class="tab-panel" id="tab-dates">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;color:rgba(255,255,255,.7);">
                    <div><strong>Регистрация:</strong> ${formatDateRange(sprint.registration_start, sprint.registration_end)}</div>
                    <div><strong>Джем:</strong> ${formatDateRange(sprint.jam_start, sprint.jam_end)}</div>
                    <div><strong>Голосование:</strong> ${formatDateRange(sprint.voting_start, sprint.voting_end)}</div>
                    <div><strong>Макс. участников:</strong> ${sprint.max_participants}</div>
                </div>
            </div>
            <div class="tab-panel" id="tab-prizes">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                    <div><strong>Призы</strong><br>${prizesHtml}</div>
                    <div><strong>Эксперты</strong><br>${expertsHtml}</div>
                </div>
            </div>
            ${sprint.rules        ? `<div class="tab-panel" id="tab-rules"><div style="color:rgba(255,255,255,.7);line-height:1.7;">${rulesHtml}</div></div>` : ''}
            ${sprint.useful_links ? `<div class="tab-panel" id="tab-links"><div style="color:rgba(255,255,255,.7);line-height:1.7;">${linksHtml}</div></div>` : ''}
            ${sprint.faq ? `<div class="tab-panel" id="tab-faq"><div class="faq-list">${faqHtml}</div></div>` : ''}
            ${sprint.agreement ? `<div class="tab-panel" id="tab-agreement"><div class="faq-list">${agreementHtml}</div></div>` : ''}
            </div><!-- /view-body -->
            <div class="modal-actions">
                ${actionButton}
                ${canRate ? `<a href="/jams/rate.php?id=${sprint.id}" class="btn-rate">Оценить</a>` : ''}
                <button class="btn-team" onclick="event.stopPropagation();window.location.href='/l4t?jam_id=${sprint.id}&action=create_team';">Команда</button>
                <button class="btn-team" onclick="event.stopPropagation();window.location.href='/jams/jam1';">Страница джема</button>
                <button class="btn-share" onclick="event.stopPropagation();shareSprint(${sprint.id})">Поделиться</button>
            </div>
        `;

        viewModal.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                viewModal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                viewModal.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                const panel = document.getElementById('tab-' + this.dataset.tab);
                if (panel) panel.classList.add('active');
            });
        });

        viewOverlay.style.display = 'flex';
        if (history.pushState) history.pushState({sprint: sprint.id}, '', window.location.pathname + '?sprint=' + sprint.id);
        const dc = document.getElementById('desc-content');
        if (dc) dc.classList.remove('expanded');
    }

    function closeView(e) {
        if (e && e.target !== viewOverlay) return;
        viewOverlay.style.display = 'none';
        if (history.pushState) history.pushState({}, '', window.location.pathname);
    }

    function toggleDesc() {
        const c = document.getElementById('desc-content');
        if (!c) return;
        c.classList.toggle('expanded');
        const btn = document.querySelector('.desc-more-btn');
        if (btn) btn.textContent = c.classList.contains('expanded') ? 'Свернуть' : 'Ещё';
    }

    function shareSprint(id) {
        navigator.clipboard.writeText(window.location.origin + window.location.pathname + '?sprint=' + id);
        alert('Ссылка скопирована');
    }

    function startJoin(sprintId) {
        if (!isLoggedIn) {
            if (confirm('Для участия необходимо авторизоваться. Перейти на страницу входа?'))
                window.location.href = '/login?backUrl=' + encodeURIComponent(window.location.href);
            return;
        }
        const sprint = sprints.find(s => s.id == sprintId);
        if (!sprint) return;

        if (sprint.rules || sprint.agreement) {
            pendingSprintId = sprintId;

            const rulesBlock = document.getElementById('consent-rules-block');
            if (sprint.rules) {
                rulesBody.innerHTML = markdownToHtml(sprint.rules);
                rulesBlock.style.display = '';
            } else {
                rulesBlock.style.display = 'none';
            }

            const agrBlock = document.getElementById('consent-agreement-block');
            if (sprint.agreement) {
                document.getElementById('agreement-body').innerHTML = markdownToHtml(sprint.agreement);
                agrBlock.style.display = '';
            } else {
                agrBlock.style.display = 'none';
            }

            const chk = document.getElementById('consent-accept');
            chk.checked = false;
            document.getElementById('consent-agree-btn').disabled = true;

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
        if (!document.getElementById('consent-accept').checked) return;
        if (pendingSprintId) {
            rulesOverlay.style.display = 'none';
            openRegister(pendingSprintId);
            pendingSprintId = null;
        }
    }

    function openRegister(sprintId) {
        if (!isLoggedIn) { window.location.href = '/login?backUrl=' + encodeURIComponent(window.location.href); return; }
        document.getElementById('reg-sprint-id').value = sprintId;
        document.getElementById('reg-alias').value = currentUsername;
        ['reg-city','reg-extra','reg-links','reg-team-city','reg-team-extra','reg-team-links'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('reg-team-alias').value = currentUsername;
        document.querySelector('input[name="participant_type"][value="solo"]').checked = true;
        toggleTeamFields();
        document.getElementById('register-form').style.display = 'block';
        document.getElementById('team-step').style.display = 'none';
        document.getElementById('reg-modal-title').textContent = 'Регистрация на спринт';
        registerOverlay.style.display = 'flex';
    }

    function closeRegister(e) {
        if (e && e.target !== registerOverlay) return;
        registerOverlay.style.display = 'none';
        const f = document.getElementById('register-form');
        const ts = document.getElementById('team-step');
        if (f) f.style.display = 'block';
        if (ts) ts.style.display = 'none';
        const title = document.getElementById('reg-modal-title');
        if (title) title.textContent = 'Регистрация на спринт';
    }

    function toggleTeamFields() {
        const t = document.querySelector('input[name="participant_type"]:checked').value;
        document.getElementById('solo-fields').style.display  = t === 'solo' ? 'block' : 'none';
        document.getElementById('team-fields').style.display  = t === 'team' ? 'block' : 'none';
    }

    async function submitRegistration(e) {
        e.preventDefault();
        const sprintId = document.getElementById('reg-sprint-id').value;
        const type = document.querySelector('input[name="participant_type"]:checked').value;
        const p = type === 'solo' ? '' : '-team';
        const fd = new FormData();
        fd.append('sprint_id',        sprintId);
        fd.append('participant_type', type);
        fd.append('alias',      document.getElementById('reg' + p + '-alias')?.value.trim()  || currentUsername);
        fd.append('city',       document.getElementById('reg' + p + '-city')?.value.trim()   || '');
        fd.append('extra_info', document.getElementById('reg' + p + '-extra')?.value.trim()  || '');
        fd.append('links',      document.getElementById('reg' + p + '-links')?.value.trim()  || '');
        try {
            const resp   = await fetch('/swad/controllers/jams/join_sprint.php', { method: 'POST', body: fd });
            const result = await resp.json();
            if (result.success) {
                const idx = sprints.findIndex(s => s.id == sprintId);
                if (idx !== -1) { sprints[idx].current_participants = result.new_count; userSprintIds.push(parseInt(sprintId)); }
                regSprintId = parseInt(sprintId);
                renderGrid();
                showTeamStep(regSprintId);
            } else { alert('Ошибка: ' + result.message); }
        } catch (err) { alert('Ошибка соединения: ' + err.message); }
    }

    /* ===== Шаг «Команда» после регистрации ===== */
    function teamApiJ(url, payload) {
        return fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) }).then(r => r.json());
    }

    function showTeamStep(sprintId) {
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('team-step').style.display = 'block';
        document.getElementById('reg-modal-title').textContent = 'Команда';
        const body = document.getElementById('team-step-body');
        body.innerHTML = '<p style="color:rgba(255,255,255,.5);font-size:13px;">Загрузка…</p>';

        fetch(`/swad/controllers/jams/get_team.php?sprint_id=${sprintId}`)
            .then(r => r.json())
            .then(state => {
                if (state.has_team)                       renderTeamStepView(state);
                else if (state.pending_invites?.length)   renderTeamStepInvites(sprintId, state.pending_invites);
                else                                      renderTeamStepCreate(sprintId);
            })
            .catch(() => { body.innerHTML = '<p style="color:#f88;font-size:13px;">Не удалось загрузить. Можно продолжить соло.</p>'; });
    }

    function renderTeamStepCreate(sprintId) {
        document.getElementById('team-step-body').innerHTML = `
            <p style="color:rgba(255,255,255,.6);font-size:13px;margin-bottom:14px;">Вы зарегистрированы. Соберите команду или продолжите соло.</p>
            <div class="form-group"><label class="form-label">Название команды</label><input class="form-input" id="ts-name" maxlength="120" placeholder="Например, Pixel Wizards"></div>
            <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" id="ts-desc" rows="2" maxlength="2000" placeholder="Кого ищете, что за проект..."></textarea></div>
            <div class="ts-input-row">
                <div style="flex:1;"><label class="form-label">Видимость</label>
                    <select class="form-input" id="ts-vis"><option value="public">Публичная</option><option value="private">Приватная</option></select></div>
                <div style="width:110px;"><label class="form-label">Лимит</label>
                    <input class="form-input" id="ts-limit" type="number" min="2" max="20" value="5"></div>
            </div>
            <button type="button" class="btn-submit" style="width:100%;margin-top:6px;" onclick="createTeamStep(${sprintId})">Создать команду</button>`;
    }

    function createTeamStep(sprintId) {
        const name = document.getElementById('ts-name').value.trim();
        if (!name) { alert('Введите название команды'); return; }
        teamApiJ('/swad/controllers/jams/create_team.php', {
            sprint_id: sprintId,
            team_name: name,
            team_desc: document.getElementById('ts-desc').value.trim(),
            visibility: document.getElementById('ts-vis').value,
            team_limit: parseInt(document.getElementById('ts-limit').value) || 5,
        }).then(d => {
            if (!d.success) { alert(d.message || 'Ошибка'); return; }
            showTeamStep(sprintId);
        });
    }

    function renderTeamStepView(state) {
        const t = state.team;
        const members = (state.members || []).map(m =>
            `<div class="ts-member">${m.member_role==='captain'?'👑':'👤'} ${escapeHtml(m.username)}${m.member_role==='captain'?' <span style="color:rgba(255,255,255,.4);font-size:11px;">капитан</span>':''}</div>`
        ).join('');
        let html = `
            <div style="font-weight:700;font-size:15px;margin-bottom:2px;">${escapeHtml(t.team_name)}</div>
            <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:10px;">${state.members.length} / ${t.team_limit} · ${t.visibility==='private'?'приватная':'публичная'}</div>
            <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:8px;">${members}</div>`;
        if (state.is_captain) {
            html += `
                <div class="ts-input-row" style="margin-top:14px;">
                    <input class="form-input" id="ts-inv" placeholder="Ник участника (от 4 символов)" autocomplete="off">
                    <button type="button" class="btn-submit" style="flex:none;padding:9px 16px;" onclick="inviteStep(${t.id})">Пригласить</button>
                </div>
                <div class="ts-msg" id="ts-inv-msg"></div>`;
        }
        document.getElementById('team-step-body').innerHTML = html;
        const inp = document.getElementById('ts-inv');
        if (inp) attachTeamStepAutocomplete(inp, t.sprint_id);
    }

    function inviteStep(teamId) {
        const nick = document.getElementById('ts-inv').value.trim();
        if (!nick) return;
        teamApiJ('/swad/controllers/jams/invite_member.php', { team_id: teamId, username: nick }).then(d => {
            const m = document.getElementById('ts-inv-msg');
            m.textContent = d.message || (d.success ? 'Приглашение отправлено' : 'Ошибка');
            m.style.color = d.success ? '#5b8def' : '#f88';
            if (d.success) document.getElementById('ts-inv').value = '';
        });
    }

    function renderTeamStepInvites(sprintId, invites) {
        const rows = invites.map(inv => `
            <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                <div style="flex:1;font-size:13px;">«${escapeHtml(inv.team_name)}»<div style="font-size:11px;color:rgba(255,255,255,.35);">от ${escapeHtml(inv.inviter)}</div></div>
                <button type="button" class="btn-submit" style="flex:none;padding:7px 12px;" onclick="acceptInviteStep(${inv.invite_id},${sprintId})">Принять</button>
            </div>`).join('');
        document.getElementById('team-step-body').innerHTML = `
            <p style="color:rgba(255,255,255,.6);font-size:13px;margin-bottom:10px;">Вас уже приглашали в команды:</p>
            ${rows}
            <button type="button" class="btn-team" style="width:100%;margin-top:14px;cursor:pointer;" onclick="renderTeamStepCreate(${sprintId})">Или создать свою команду</button>`;
    }

    function acceptInviteStep(inviteId, sprintId) {
        teamApiJ('/swad/controllers/jams/respond_invite.php', { invite_id: inviteId, action: 'accept' }).then(d => {
            if (!d.success) { alert(d.message || 'Ошибка'); return; }
            showTeamStep(sprintId);
        });
    }

    function finishRegistration() {
        const sid = regSprintId;
        document.getElementById('register-form').style.display = 'block';
        document.getElementById('team-step').style.display = 'none';
        document.getElementById('reg-modal-title').textContent = 'Регистрация на спринт';
        registerOverlay.style.display = 'none';
        closeView();
        if (sid) openView(sid);
    }

    function attachTeamStepAutocomplete(input, sprintId) {
        const wrap = document.createElement('div'); wrap.style.cssText = 'position:relative;flex:1;';
        input.parentNode.insertBefore(wrap, input); wrap.appendChild(input); input.style.width = '100%';
        const dd = document.createElement('div');
        dd.style.cssText = 'position:absolute;left:0;right:0;top:100%;z-index:50;background:#1c0c2a;border:1px solid rgba(195,33,120,.35);border-radius:8px;margin-top:4px;max-height:180px;overflow:auto;display:none;';
        wrap.appendChild(dd);
        let timer;
        input.addEventListener('input', () => {
            const q = input.value.trim(); clearTimeout(timer);
            if (q.length < 4) { dd.style.display = 'none'; return; }
            timer = setTimeout(() => {
                fetch(`/swad/controllers/jams/search_participants.php?sprint_id=${sprintId}&q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(list => {
                        if (!list.length) { dd.innerHTML = '<div style="padding:8px 10px;color:rgba(255,255,255,.4);font-size:13px;">Никого не найдено</div>'; }
                        else dd.innerHTML = list.map(u => {
                            const handle = u.username || u.telegram_username || '';
                            const disp   = u.username || ('@' + (u.telegram_username||''));
                            return `<div class="ts-ac" data-u="${escapeHtml(handle)}" style="padding:7px 10px;cursor:pointer;font-size:13px;color:#e8ddf0;">${escapeHtml(disp)}</div>`;
                        }).join('');
                        dd.style.display = 'block';
                    });
            }, 250);
        });
        dd.addEventListener('click', e => { const it = e.target.closest('.ts-ac'); if (!it) return; input.value = it.dataset.u; dd.style.display = 'none'; });
        document.addEventListener('click', e => { if (!wrap.contains(e.target)) dd.style.display = 'none'; });
    }

    function setFilter(f, el) {
        curFilter = f;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        renderGrid();
    }

    // ---------- Создание спринта ----------
    function openCreate() {
        prizes = [{ place: '1', reward: '' }];
        selectedExperts = [];
        ['f-title','f-desc','f-theme','f-tags','f-rules','f-links',
         'f-reg-start','f-reg-end','f-jam-start','f-jam-end','f-vote-start','f-vote-end'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('f-maxp').value = '100';
        document.getElementById('f-logo').value = '';
        document.getElementById('logo-preview').innerHTML = '';
        buildPrizes(); renderExpertsSelects(); goStep(1);
        document.getElementById('create-overlay').style.display = 'flex';
    }

    function closeCreate() { document.getElementById('create-overlay').style.display = 'none'; }
    function closeCreateOverlay(e) { if (e.target === document.getElementById('create-overlay')) closeCreate(); }

    function goStep(n) {
        document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('#create-overlay .tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('step' + n)?.classList.add('active');
        document.querySelector('#create-overlay .tab-btn:nth-child(' + n + ')')?.classList.add('active');
    }

    function buildPrizes() {
        const c = document.getElementById('prizes-list'); if (!c) return;
        c.innerHTML = prizes.map((p, i) => `
            <div class="dynamic-row">
                <span style="font-size:18px;flex-shrink:0">${['🥇','🥈','🥉'][i] || '🎖'}</span>
                <input value="${escapeHtml(p.reward)}" placeholder="Приз за ${p.place} место" oninput="prizes[${i}].reward=this.value">
                ${i > 0 ? `<button class="btn-remove" onclick="removePrize(${i})">✕</button>` : ''}
            </div>`).join('');
    }
    function addPrize()        { prizes.push({ place: String(prizes.length+1), reward:'' }); buildPrizes(); }
    function removePrize(idx)  { prizes.splice(idx,1); prizes.forEach((p,i) => p.place=String(i+1)); buildPrizes(); }

    function renderExpertsSelects() {
        const c = document.getElementById('experts-list'); if (!c) return;
        c.innerHTML = selectedExperts.map((uid, idx) => `
            <div class="dynamic-row">
                <select onchange="updateExpert(${idx},this.value)" style="flex:2;background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.14);border-radius:8px;padding:9px 12px;color:#e8ddf0;font-size:13px;outline:none;">
                    <option value="">-- Выберите пользователя --</option>
                    ${allUsers.map(u => `<option value="${u.id}" ${u.id==uid?'selected':''}>${escapeHtml(u.username)} (${u.role||'пользователь'})</option>`).join('')}
                </select>
                <button class="btn-remove" onclick="removeExpert(${idx})">✕</button>
            </div>`).join('');
        if (!selectedExperts.length) c.innerHTML = '<div style="color:rgba(255,255,255,.3);padding:8px 0;">Нет экспертов, нажмите «Добавить эксперта»</div>';
    }
    function addExpert()              { selectedExperts.push(''); renderExpertsSelects(); }
    function updateExpert(idx, val)   { selectedExperts[idx] = val ? parseInt(val) : ''; }
    function removeExpert(idx)        { selectedExperts.splice(idx,1); renderExpertsSelects(); }

    async function submitSprint() {
        const title = document.getElementById('f-title')?.value.trim();
        const desc  = document.getElementById('f-desc')?.value.trim();
        if (!title || !desc) { alert('Заполните название и описание'); return; }
        const fd = new FormData();
        fd.append('title', title); fd.append('description', desc);
        fd.append('theme',       document.getElementById('f-theme')?.value.trim()  || '');
        fd.append('tags',        document.getElementById('f-tags')?.value.trim()   || '');
        fd.append('rules',       document.getElementById('f-rules')?.value.trim()  || '');
        fd.append('useful_links',document.getElementById('f-links')?.value.trim()  || '');
        fd.append('registration_start', document.getElementById('f-reg-start')?.value  || '');
        fd.append('registration_end',   document.getElementById('f-reg-end')?.value    || '');
        fd.append('jam_start',          document.getElementById('f-jam-start')?.value  || '');
        fd.append('jam_end',            document.getElementById('f-jam-end')?.value     || '');
        fd.append('voting_start',       document.getElementById('f-vote-start')?.value || '');
        fd.append('voting_end',         document.getElementById('f-vote-end')?.value   || '');
        fd.append('max_participants',   document.getElementById('f-maxp')?.value || '100');
        const logo = document.getElementById('f-logo')?.files[0];
        if (logo) fd.append('logo', logo);
        fd.append('prizes',  JSON.stringify(prizes.filter(p => p.reward.trim())));
        fd.append('experts', JSON.stringify(selectedExperts.filter(id => id)));
        try {
            const r = await (await fetch('/swad/controllers/create_sprint.php', { method:'POST', body:fd })).json();
            if (r.success) { alert('Спринт создан!'); closeCreate(); location.reload(); }
            else alert('Ошибка: ' + r.message);
        } catch (e) { alert('Ошибка: ' + e.message); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderGrid();
        if (sprintFromGet) setTimeout(() => openView(sprintFromGet), 300);
        window.addEventListener('popstate', e => e.state?.sprint ? openView(e.state.sprint) : closeView());
        document.addEventListener('click', e => {
            if (e.target === viewOverlay)     closeView();
            if (e.target === rulesOverlay)    closeRulesConsent();
            if (e.target === registerOverlay) closeRegister();
        });
        document.getElementById('f-logo')?.addEventListener('change', function() {
            const p = document.getElementById('logo-preview');
            if (this.files[0]) { p.innerHTML = `<img src="${URL.createObjectURL(this.files[0])}" style="max-width:100px;border-radius:8px">`; }
            else p.innerHTML = '';
        });
    });

    // Tilt вне .grid
    (function() {
        const btns = document.querySelectorAll('.btn-primary,.btn-join,.btn-team,.btn-share,.btn-next,.btn-submit,.btn-back,.btn-add,.btn-remove,.filter-btn,.nav-btn,.tab-btn,.btn-close');
        function reset(b)  { b.style.transform = ''; }
        function tilt(e) {
            const b = e.currentTarget; if (b.closest('.grid')) return;
            const r = b.getBoundingClientRect();
            const nx = (e.clientX-r.left)/r.width*2-1, ny = (e.clientY-r.top)/r.height*2-1;
            b.style.transform = `perspective(400px) rotateX(${-15*ny}deg) rotateY(${15*nx}deg) translateY(-3px) scale(1.04)`;
        }
        btns.forEach(b => { b.addEventListener('mousemove', tilt); b.addEventListener('mouseleave', e => reset(e.currentTarget)); });
    })();

    // Tilt внутри .grid (делегирование)
    (function() {
        const grid = document.getElementById('grid'); if (!grid) return;
        let cur = null;
        const sel = '.btn-primary,.btn-join,.btn-team,.btn-share,.btn-next,.btn-submit,.btn-back,.btn-add,.btn-remove,.filter-btn,.nav-btn,.step-tab,.btn-close';
        function reset(el) { if (el) el.style.transform = ''; }
        function tilt(el, e) {
            const r = el.getBoundingClientRect();
            const nx = (e.clientX-r.left)/r.width*2-1, ny = (e.clientY-r.top)/r.height*2-1;
            el.style.transform = `perspective(400px) rotateX(${-15*ny}deg) rotateY(${15*nx}deg) translateY(-3px) scale(1.06)`;
        }
        grid.addEventListener('mousemove', e => {
            const t = e.target.closest(sel);
            if (!t) { reset(cur); cur = null; return; }
            if (cur && cur !== t) reset(cur);
            cur = t; tilt(t, e);
        });
        grid.addEventListener('mouseleave', () => { reset(cur); cur = null; });
    })();

    // Анимация поиска
    (function() {
        const sw = document.querySelector('.search-wrap'), si = document.querySelector('.search-input');
        if (!sw || !si) return;
        sw.addEventListener('mousemove', e => {
            const r = sw.getBoundingClientRect();
            const nx = (e.clientX-r.left)/r.width*2-1, ny = (e.clientY-r.top)/r.height*10-5;
            sw.style.transform = `perspective(400px) rotateX(${-5*ny}deg) rotateY(${5*nx}deg) translateY(-2px)`;
        });
        sw.addEventListener('mouseleave', () => sw.style.transform = '');
        si.addEventListener('input', function() {
            this.classList.remove('shake-it'); void this.offsetWidth; this.classList.add('shake-it');
            this.addEventListener('animationend', function f() { this.classList.remove('shake-it'); this.removeEventListener('animationend',f); });
        });
    })();
</script>

<style>
@keyframes shakeSearch {
    0%,100% { transform:translateX(0) rotateX(0) rotateY(0); }
    20% { transform:translateX(-3px) rotate(-1deg); }
    40% { transform:translateX(3px) rotate(1deg); }
    60% { transform:translateX(-2px) rotate(-.5deg); }
    80% { transform:translateX(2px) rotate(.5deg); }
}
.shake-it { animation: shakeSearch .3s ease-in-out; }
</style>

</body>
</html>