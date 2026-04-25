<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore L4T</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .hidden {
            display: none !important;
        }

        .l4t-input,
        .l4t-select,
        .l4t-textarea {
            background: rgba(0, 0, 0, .45);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 5px;
            color: #e8ddf0;
            padding: 6px 10px;
            font-family: inherit;
            font-size: .88rem;
            outline: none;
            transition: border-color .15s;
            width: 100%;
            box-sizing: border-box;
        }

        .l4t-input:focus,
        .l4t-select:focus,
        .l4t-textarea:focus {
            border-color: #c32178;
        }

        .l4t-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .l4t-select {
            appearance: none;
            cursor: pointer;
            padding-right: 28px;
            background-image: linear-gradient(45deg, transparent 50%, rgba(255, 255, 255, .4) 50%),
                linear-gradient(135deg, rgba(255, 255, 255, .4) 50%, transparent 50%);
            background-position: calc(100% - 14px) 50%, calc(100% - 8px) 50%;
            background-size: 5px 5px;
            background-repeat: no-repeat;
        }

        .editable-text {
            border-bottom: 1px dashed rgba(255, 255, 255, .35);
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 3px;
            display: inline-block;
            min-width: 40px;
            transition: background .15s, border-color .15s;
        }

        .editable-text:hover {
            background: rgba(255, 255, 255, .06);
            border-color: #c32178;
        }

        .exp-tags-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }

        .exp-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(195, 33, 120, .15);
            border: 1px solid rgba(195, 33, 120, .3);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .82rem;
            color: #e8ddf0;
        }

        .exp-tag input {
            background: transparent;
            border: none;
            border-bottom: 1px dashed rgba(255, 255, 255, .3);
            color: inherit;
            font-size: inherit;
            outline: none;
            padding: 0 2px;
        }

        .exp-tag input:focus {
            border-bottom-color: #c32178;
        }

        .exp-tag .exp-role {
            width: 110px;
        }

        .exp-tag .exp-years {
            width: 36px;
            text-align: center;
            -moz-appearance: textfield;
        }

        .exp-tag .exp-years::-webkit-outer-spin-button,
        .exp-tag .exp-years::-webkit-inner-spin-button {
            -webkit-appearance: none;
        }

        .exp-tag .del-btn {
            cursor: pointer;
            color: rgba(255, 255, 255, .3);
            transition: color .1s;
            font-size: .9rem;
        }

        .exp-tag .del-btn:hover {
            color: #f44336;
        }

        .l4t-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: transparent;
            border: 1px dashed rgba(255, 255, 255, .2);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .82rem;
            color: rgba(255, 255, 255, .4);
            cursor: pointer;
            transition: border-color .15s, color .15s;
            font-family: inherit;
        }

        .l4t-add-btn:hover {
            border-color: #c32178;
            color: #e8ddf0;
        }

        .files-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .file-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: .8rem;
            color: #e8ddf0;
            text-decoration: none;
            transition: background .15s;
            cursor: pointer;
        }

        .file-chip:hover {
            background: rgba(255, 255, 255, .12);
        }

        .file-chip .chip-icon {
            opacity: .6;
        }

        .projects-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }

        .proj-thumb {
            width: 72px;
            height: 72px;
            border-radius: 6px;
            background: rgba(255, 255, 255, .06) center/cover no-repeat;
            border: 1px solid rgba(255, 255, 255, .12);
            cursor: pointer;
            transition: border-color .15s;
            position: relative;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
        }

        .proj-thumb:hover {
            border-color: #c32178;
        }

        .proj-thumb .proj-label {
            width: 100%;
            background: rgba(0, 0, 0, .65);
            font-size: .6rem;
            color: #fff;
            padding: 3px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0;
            transition: opacity .2s;
        }

        .proj-thumb:hover .proj-label {
            opacity: 1;
        }

        .about-block {
            font-size: .88rem;
            color: #e8ddf0;
            line-height: 1.6;
        }

        .about-more {
            color: #c32178;
            cursor: pointer;
            font-size: .8rem;
            display: inline-block;
            margin-top: 4px;
        }

        .about-more:hover {
            text-decoration: underline;
        }

        .about-empty {
            color: rgba(255, 255, 255, .35);
            font-size: .85rem;
            font-style: italic;
        }

        .about-edit-btn {
            margin-top: 6px;
            background: transparent;
            border: 1px dashed rgba(255, 255, 255, .2);
            border-radius: 5px;
            padding: 3px 10px;
            font-size: .78rem;
            color: rgba(255, 255, 255, .4);
            cursor: pointer;
            transition: border-color .15s, color .15s;
            font-family: inherit;
        }

        .about-edit-btn:hover {
            border-color: #c32178;
            color: #e8ddf0;
        }

        /* ── Модал ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            z-index: 900;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.hidden {
            display: none !important;
        }

        .modal-box {
            background: #160822;
            border: 1px solid rgba(195, 33, 120, .35);
            border-radius: 12px;
            padding: 26px;
            width: 480px;
            max-width: 95vw;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 0 40px rgba(195, 33, 120, .2);
            position: relative;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
            color: #fff;
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 16px;
            cursor: pointer;
            color: rgba(255, 255, 255, .4);
            font-size: 1.2rem;
            line-height: 1;
            transition: color .15s;
        }

        .modal-close:hover {
            color: #fff;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, .08);
        }

        .modal-btn {
            padding: 7px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: .85rem;
            font-family: inherit;
            transition: background .15s;
        }

        .modal-btn-primary {
            background: #c32178;
            color: #fff;
        }

        .modal-btn-primary:hover {
            background: #9e1a66;
        }

        .modal-btn-ghost {
            background: rgba(255, 255, 255, .08);
            color: #e8ddf0;
        }

        .modal-btn-ghost:hover {
            background: rgba(255, 255, 255, .15);
        }

        .modal-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .modal-row .l4t-input {
            flex: 1;
        }

        .modal-row .modal-del {
            cursor: pointer;
            color: rgba(255, 255, 255, .3);
            font-size: 1rem;
            padding: 4px;
            flex-shrink: 0;
            transition: color .1s;
        }

        .modal-row .modal-del:hover {
            color: #f44336;
        }

        .modal-field {
            margin-bottom: 12px;
        }

        .modal-label {
            font-size: .75rem;
            color: rgba(255, 255, 255, .45);
            display: block;
            margin-bottom: 4px;
        }

        .cover-preview {
            width: 100%;
            height: 110px;
            border-radius: 7px;
            margin-top: 8px;
            background: rgba(255, 255, 255, .05) center/cover no-repeat;
            border: 1px solid rgba(255, 255, 255, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .3);
            font-size: .8rem;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: .82rem;
            color: #e8ddf0;
            cursor: pointer;
            transition: background .15s;
            margin-top: 6px;
        }

        .upload-btn:hover {
            background: rgba(255, 255, 255, .13);
        }

        .upload-btn input[type=file] {
            display: none;
        }

        .char-count {
            font-size: .7rem;
            color: rgba(255, 255, 255, .3);
            text-align: right;
            margin-top: 3px;
        }

        /* ── Карточки биржи ── */
        .bid-card-item {
            background: rgba(0, 0, 0, .25);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 10px;
        }

        .bid-badge {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .bid-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .bid-icon.user {
            background: rgba(195, 33, 120, .15);
        }

        .bid-icon.studio {
            background: rgba(33, 195, 120, .15);
        }

        .bid-type {
            font-size: 11px;
            color: rgba(255, 255, 255, .35);
        }

        .bid-main {
            flex: 1;
            min-width: 0;
        }

        .bid-role {
            font-size: 15px;
            font-weight: 500;
            color: #e8ddf0;
            margin-bottom: 4px;
        }

        .bid-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 8px;
        }

        .bid-tag {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 11px;
            color: rgba(255, 255, 255, .6);
        }

        .bid-desc {
            font-size: 13px;
            color: rgba(255, 255, 255, .5);
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 400px;
        }

        .bid-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        .bid-date {
            font-size: 11px;
            color: rgba(255, 255, 255, .3);
        }

        .bid-stats {
            font-size: 11px;
            color: rgba(255, 255, 255, .3);
            display: flex;
            gap: 8px;
        }

        .respond-btn {
            font-size: 12px;
            padding: 5px 14px;
            border-radius: 6px;
            border: 1px solid rgba(195, 33, 120, .4);
            background: rgba(195, 33, 120, .1);
            color: #e8ddf0;
            cursor: pointer;
            transition: background .15s;
        }

        .respond-btn:hover {
            background: rgba(195, 33, 120, .25);
        }

        /* ── Тулбар поиска / фильтры ── */
        .my-bids-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .my-bids-search {
            flex: 1;
            min-width: 160px;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 7px;
            color: #e8ddf0;
            padding: 6px 10px 6px 30px;
            font-family: inherit;
            font-size: .85rem;
            outline: none;
            transition: border-color .15s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,.35)' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 8px center;
        }

        .my-bids-search:focus {
            border-color: #c32178;
        }

        .my-bids-filter-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .my-bids-ftag {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .78rem;
            color: rgba(255, 255, 255, .55);
            cursor: pointer;
            transition: background .12s, border-color .12s, color .12s;
            font-family: inherit;
        }

        .my-bids-ftag.active {
            background: rgba(195, 33, 120, .18);
            border-color: rgba(195, 33, 120, .45);
            color: #e8ddf0;
        }

        .my-bids-ftag:hover {
            border-color: rgba(195, 33, 120, .35);
            color: #e8ddf0;
        }

        /* ── Баннер редактирования ── */
        .editing-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(195, 33, 120, .12);
            border: 1px solid rgba(195, 33, 120, .3);
            border-radius: 8px;
            padding: 8px 14px;
            margin-bottom: 12px;
            font-size: .84rem;
            color: #e8ddf0;
        }

        .editing-banner .cancel-edit {
            margin-left: auto;
            cursor: pointer;
            color: rgba(255, 255, 255, .4);
            font-size: .78rem;
            background: none;
            border: none;
            font-family: inherit;
            transition: color .12s;
        }

        .editing-banner .cancel-edit:hover {
            color: #f44336;
        }

        /* ── Кнопка отправки ── */
        .ok-btn {
            opacity: 0.4;
            pointer-events: none;
            transition: background .2s, opacity .2s;
            background: #555 !important;
        }

        .ok-btn.dirty {
            opacity: 1;
            pointer-events: all;
            background: #c32178 !important;
        }

        /* ── Бейдж вкладки ── */
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #c32178;
            color: #fff;
            border-radius: 10px;
            font-size: .65rem;
            font-weight: 600;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            margin-left: 4px;
            line-height: 1;
        }

        .tab-badge.zero {
            display: none;
        }

        /* ── Подвкладки откликов ── */
        .resp-sub-tabs {
            display: flex;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
            margin-bottom: 14px;
        }

        .resp-sub-tab {
            padding: 6px 16px;
            font-size: .83rem;
            color: rgba(255, 255, 255, .45);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: color .12s, border-color .12s;
            margin-bottom: -1px;
        }

        .resp-sub-tab.active {
            color: #e8ddf0;
            border-bottom-color: #c32178;
        }

        .resp-sub-tab:hover {
            color: #e8ddf0;
        }

        .resp-pane {
            display: none;
        }

        .resp-pane.active {
            display: block;
        }

        /* ── Карточки откликов ── */
        .resp-card {
            background: rgba(0, 0, 0, .22);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: border-color .15s;
        }

        .resp-card:hover {
            border-color: rgba(195, 33, 120, .35);
        }

        .resp-card-title {
            font-size: .9rem;
            color: #e8ddf0;
            margin-bottom: 4px;
        }

        .resp-card-meta {
            font-size: .75rem;
            color: rgba(255, 255, 255, .35);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .resp-card-meta .rc-status {
            color: #c32178;
        }

        /* ── Блок контактов в модале отклика ── */
        .contact-block {
            margin-top: 16px;
            padding: 14px 16px;
            background: rgba(195, 33, 120, .07);
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 10px;
        }

        .contact-block-title {
            font-size: .72rem;
            color: rgba(255, 255, 255, .4);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .contact-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: .88rem;
            color: #e8ddf0;
        }

        .contact-row:last-child {
            margin-bottom: 0;
        }

        .contact-row a {
            color: #c32178;
            text-decoration: none;
        }

        .contact-row a:hover {
            text-decoration: underline;
        }

        .contact-icon {
            font-size: 1rem;
            opacity: .7;
            flex-shrink: 0;
        }

        /* ── Прокрутка ── */
        .profile-page {
            padding-right: 4px;
        }

        .right-content-view {
            overflow-y: auto;
            max-height: calc(100vh - 80px);
        }

        .right-content-view::-webkit-scrollbar {
            width: 4px;
        }

        .right-content-view::-webkit-scrollbar-track {
            background: transparent;
        }

        .right-content-view::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .3);
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php

    require_once('../swad/config.php');
    require_once('../swad/controllers/user.php');
    require_once(__DIR__ . '/../swad/static/elements/header.php');

    $db        = new Database();
    $pdo       = $db->connect();
    $desl4tpdo = $db->connect('desl4t');

    $my_bids = [];
    if (!empty($_SESSION['USERDATA']['id'])) {
        $stmt = $desl4tpdo->prepare("SELECT * FROM bids WHERE bidder_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['USERDATA']['id']]);
        $my_bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $curr_user = new User();
    $isOwner   = false;
    $userdata  = [];

    if (!empty($_GET['username'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR telegram_username = ?");
        $stmt->execute([$_GET['username'], $_GET['username']]);
        $userdata = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $isOwner  = !empty($_SESSION['USERDATA']['id'])
            && (int)$_SESSION['USERDATA']['id'] === (int)($userdata['id'] ?? 0);
    } elseif (!empty($_SESSION['USERDATA']['id'])) {
        $userdata = $_SESSION['USERDATA'];
        $isOwner  = true;
    }

    $loggedIn  = !empty($userdata['id']);
    $user_orgs = $loggedIn ? $curr_user->getUO($userdata['id']) : [];

    $l4t_exp      = json_decode($userdata['l4t_exp']      ?? '[]', true) ?: [];
    $l4t_files    = json_decode($userdata['l4t_files']    ?? '[]', true) ?: [];
    $l4t_projects = json_decode($userdata['l4t_projects'] ?? '[]', true) ?: [];
    $l4t_about    = $userdata['l4t_about'] ?? '';

    $stmt = $desl4tpdo->prepare("SELECT * FROM bids WHERE stage = 'open' ORDER BY created_at DESC");
    $stmt->execute();
    $bids_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $aboutPreview = mb_substr($l4t_about, 0, 200);
    $aboutHasMore = mb_strlen($l4t_about) > 200;

    $my_responds       = [];
    $incoming_responds = [];
    if (!empty($_SESSION['USERDATA']['id'])) {
        try {
            $stmt2 = $desl4tpdo->prepare("
            SELECT r.*, b.search_role, b.search_spec, b.conditions, b.created_at AS bid_date
            FROM responds r
            LEFT JOIN bids b ON b.id = r.bid_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
        ");
            $stmt2->execute([$_SESSION['USERDATA']['id']]);
            $my_responds = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $stmt3 = $desl4tpdo->prepare("
            SELECT r.*, b.search_role, b.search_spec, b.conditions,
                   u.username, u.telegram_username, u.l4t_role
            FROM responds r
            LEFT JOIN bids b ON b.id = r.bid_id
            LEFT JOIN dustore.users u ON u.id = r.user_id
            WHERE b.bidder_id = ?
            ORDER BY r.created_at DESC
        ");
            $stmt3->execute([$_SESSION['USERDATA']['id']]);
            $incoming_responds = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $my_responds = [];
            $incoming_responds = [];
        }
    }
    ?>

    <div class="main-container">
        <div class="header-container"></div>
        <div class="view-container">

            <!-- ══ ЛЕВОЕ МЕНЮ ══════════════════════════════════════════ -->
            <div class="left-side-menu">
                <div class="avatar-canvas" id="btn-profile">
                    <div class="profile-image-container" style="
                    width:100%; height:400px; border-radius:10px;
                    background-image:url('<?= htmlspecialchars($userdata['profile_picture'] ?? '', ENT_QUOTES) ?>');
                    background-size:cover; background-position:center;
                    -webkit-mask-image:linear-gradient(to bottom,rgba(0,0,0,0) 0%,rgba(0,0,0,1) 40%);
                    mask-image:linear-gradient(to bottom,rgba(0,0,0,1) 60%,rgba(0,0,0,0) 100%);
                "></div>
                    <div class="image-subtitle">Профиль L4T</div>
                </div>
                <?php if ($isOwner): ?>
                    <div class="buttons-container">
                        <div class="left-side-button">Биржа</div>
                        <hr style="width:50%;margin:0 25%;opacity:20%">
                        <div class="left-side-button1">Создать заявку</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ ПРАВАЯ ПАНЕЛЬ ════════════════════════════════════════ -->
            <div class="right-content-view">
                <div class="content-background">

                    <!-- ── ПРОФИЛЬ ─────────────────────────────────────── -->
                    <?php if ($loggedIn): ?>
                        <div class="profile-page">

                            <div class="card user-card">
                                <div class="card-header">
                                    <div>
                                        <div class="label">Имя пользователя:</div>
                                        <h2 class="username">
                                            <?= htmlspecialchars($userdata['username'] ?: '@' . $userdata['telegram_username']) ?>
                                            <span style="font-size:.9rem;color:#ffffff3b;">⧉</span>
                                        </h2>
                                    </div>
                                    <div class="since">
                                        На платформе с: <?= (new DateTime($userdata['added']))->format('d.m.Y') ?>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="data-for">Данные для L4T</div>
                                    <div class="card-body-main">
                                        <div class="left">
                                            <span class="label">Роль:</span>
                                            <div class="row role"
                                                data-userid="<?= (int)$userdata['id'] ?>"
                                                data-editable="<?= $isOwner ? '1' : '0' ?>">
                                                <?php if ($isOwner): ?>
                                                    <span class="role-text editable-text">
                                                        <?= htmlspecialchars($userdata['l4t_role'] ?? 'Роль не указана') ?>
                                                    </span>
                                                    <input class="l4t-input role-edit hidden"
                                                        type="text" maxlength="40"
                                                        value="<?= htmlspecialchars($userdata['l4t_role'] ?? '') ?>"
                                                        style="max-width:260px;">
                                                <?php else: ?>
                                                    <span class="role-text">
                                                        <?= htmlspecialchars($userdata['l4t_role'] ?? 'Роль не указана') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="row" style="margin-top:12px;">
                                                <span class="label">Опыт:</span>
                                                <div class="exp-tags-wrap" id="expTags"></div>
                                            </div>

                                            <div class="row" style="margin-top:12px;">
                                                <span class="label">Доп. данные:</span>
                                                <div class="files-wrap" id="filesWrap">
                                                    <?php foreach ($l4t_files as $f): ?>
                                                        <a class="file-chip"
                                                            href="<?= htmlspecialchars($f['value']) ?>"
                                                            target="_blank"
                                                            title="<?= htmlspecialchars($f['name']) ?>">
                                                            <span class="chip-icon"><?= $f['type'] === 'link' ? '🔗' : '📄' ?></span>
                                                            <?= htmlspecialchars(mb_substr($f['name'], 0, 22)) ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                    <?php if ($isOwner): ?>
                                                        <button class="l4t-add-btn" id="filesAddBtn">+ добавить</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div><!-- /left -->

                                        <div class="right">
                                            <div class="projects-right">
                                                <div class="label">Проекты:</div>
                                                <div class="projects-grid" id="projGrid">
                                                    <?php foreach ($l4t_projects as $p): ?>
                                                        <div class="proj-thumb"
                                                            style="<?= $p['cover'] ? 'background-image:url(' . htmlspecialchars($p['cover'], ENT_QUOTES) . ')' : '' ?>"
                                                            data-proj="<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>">
                                                            <div class="proj-label"><?= htmlspecialchars($p['title']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if ($isOwner): ?>
                                                        <button class="l4t-add-btn" id="projAddBtn"
                                                            style="height:72px;width:72px;flex-direction:column;font-size:1.2rem;">+</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="projects-right" style="margin-top:14px;flex-direction:column;align-items:flex-start;">
                                                <div class="label">О себе:</div>
                                                <?php if ($l4t_about): ?>
                                                    <div class="about-block">
                                                        <?= htmlspecialchars($aboutPreview) ?><?= $aboutHasMore ? '...' : '' ?>
                                                    </div>
                                                    <?php if ($aboutHasMore): ?>
                                                        <span class="about-more" id="aboutMoreBtn">подробнее...</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="about-empty">Не заполнено</div>
                                                <?php endif; ?>
                                                <?php if ($isOwner): ?>
                                                    <button class="about-edit-btn" id="aboutEditBtn">✏ редактировать</button>
                                                <?php endif; ?>
                                            </div>
                                        </div><!-- /right -->
                                    </div><!-- /card-body-main -->
                                </div><!-- /card-body -->
                            </div><!-- /user-card -->

                            <!-- СТУДИЯ -->
                            <?php if (!empty($user_orgs)): ?>
                                <div class="card user-card">
                                    <div class="card-header">
                                        <div>
                                            <div class="label">Студия:</div>
                                            <h2 class="username">
                                                <?= htmlspecialchars($user_orgs[0]['name']) ?>
                                                <a href="/d/<?= htmlspecialchars($user_orgs[0]['tiker']) ?>"
                                                    target="_blank" style="font-size:.9rem;color:#ffffff75;">↗</a>
                                            </h2>
                                        </div>
                                        <div class="since">
                                            Студия на платформе с:
                                            <?= (new DateTime($user_orgs[0]['foundation_date']))->format('d.m.Y') ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="data-for">Данные для L4T</div>
                                        <div class="card-body-main">
                                            <div class="left">
                                                <div class="row">
                                                    <span class="label">Участники:</span>
                                                    <div class="users-total">0</div>
                                                </div>
                                            </div>
                                            <div class="right">
                                                <div class="info-block">Скоро</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card user-card">
                                    <div class="card-header">
                                        <h4 class="username">У пользователя нет зарегистрированных организаций</h4>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div><!-- /profile-page -->
                    <?php else: ?>
                        <h2 class="username" style="padding:3rem;">Вы не вошли в аккаунт</h2>
                    <?php endif; ?>

                    <!-- ══ БИРЖА ══════════════════════════════════════════ -->
                    <div id="view-market" class="content-view">
                        <div class="content-filter">
                            <div class="filter-item active" data-filter="projects">Проекты</div>
                            <div class="filter-item" data-filter="people">Люди</div>
                        </div>

                        <!-- Тулбар фильтрации -->
                        <div class="my-bids-toolbar" id="marketToolbar">
                            <input class="my-bids-search" type="text" id="marketSearch"
                                placeholder="Поиск по роли, условиям, описанию…">
                            <div class="my-bids-filter-tags" id="marketFilterTags">
                                <button class="my-bids-ftag active" data-tag="">Все</button>
                                <?php
                                $market_tags = [];
                                foreach ($bids_array as $bid) {
                                    foreach (['search_spec', 'experience', 'conditions'] as $col) {
                                        $v = trim($bid[$col] ?? '');
                                        if ($v && !in_array($v, $market_tags)) {
                                            $market_tags[] = $v;
                                            echo '<button class="my-bids-ftag" data-tag="'
                                                . htmlspecialchars($v, ENT_QUOTES) . '">'
                                                . htmlspecialchars($v) . '</button>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>

                        <div id="market-projects" class="market-view active">
                            <?php foreach ($bids_array as $bid): ?>
                                <div class="bid-card-item"
                                    data-role="<?= htmlspecialchars($bid['search_role']  ?? '', ENT_QUOTES) ?>"
                                    data-spec="<?= htmlspecialchars($bid['search_spec']  ?? '', ENT_QUOTES) ?>"
                                    data-exp="<?= htmlspecialchars($bid['experience']    ?? '', ENT_QUOTES) ?>"
                                    data-cond="<?= htmlspecialchars($bid['conditions']   ?? '', ENT_QUOTES) ?>"
                                    data-goal="<?= htmlspecialchars($bid['goal']         ?? '', ENT_QUOTES) ?>"
                                    data-details="<?= htmlspecialchars(mb_substr($bid['details'] ?? '', 0, 300), ENT_QUOTES) ?>">

                                    <div class="bid-badge">
                                        <div class="bid-icon <?= $bid['owner_type'] === 'studio' ? 'studio' : 'user' ?>">
                                            <?= $bid['owner_type'] === 'studio' ? '🏢' : '👤' ?>
                                        </div>
                                        <div class="bid-type">
                                            <?= $bid['owner_type'] === 'studio' ? 'студия' : 'пользователь' ?>
                                        </div>
                                    </div>

                                    <div class="bid-main">
                                        <div class="bid-role"><?= htmlspecialchars($bid['search_role']) ?></div>
                                        <div class="bid-meta">
                                            <?php if ($bid['search_spec']): ?><span class="bid-tag"><?= htmlspecialchars($bid['search_spec']) ?></span><?php endif; ?>
                                            <?php if ($bid['experience']): ?><span class="bid-tag"><?= htmlspecialchars($bid['experience']) ?></span><?php endif; ?>
                                            <?php if ($bid['conditions']): ?><span class="bid-tag"><?= htmlspecialchars($bid['conditions']) ?></span><?php endif; ?>
                                            <?php if ($bid['goal']): ?><span class="bid-tag"><?= mb_substr($bid['goal'], 0, 20) ?></span><?php endif; ?>
                                        </div>
                                        <div class="bid-desc"><?= htmlspecialchars(mb_substr($bid['details'] ?? '', 0, 120)) ?></div>
                                    </div>

                                    <div class="bid-right">
                                        <div class="bid-date"><?= date('d.m.Y', strtotime($bid['created_at'])) ?></div>
                                        <div class="bid-stats">
                                            <span>👁 <?= (int)$bid['views'] ?></span>
                                            <span>💬 <?= (int)$bid['responses'] ?></span>
                                        </div>
                                        <button class="respond-btn"
                                            data-bid="<?= (int)$bid['id'] ?>"
                                            data-role="<?= htmlspecialchars($bid['search_role']) ?>"
                                            data-spec="<?= htmlspecialchars($bid['search_spec']  ?? '') ?>"
                                            data-exp="<?= htmlspecialchars($bid['experience']    ?? '') ?>"
                                            data-cond="<?= htmlspecialchars($bid['conditions']   ?? '') ?>"
                                            data-goal="<?= htmlspecialchars($bid['goal']         ?? '') ?>"
                                            data-details="<?= htmlspecialchars($bid['details']   ?? '') ?>"
                                            data-type="<?= htmlspecialchars($bid['owner_type']   ?? 'user') ?>"
                                            data-date="<?= date('d.m.Y', strtotime($bid['created_at'])) ?>"
                                            data-views="<?= (int)$bid['views'] ?>"
                                            data-responses="<?= (int)$bid['responses'] ?>">
                                            Откликнуться
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div><!-- /market-projects -->

                        <div id="market-people" class="market-view">
                            <div class="bid-container"></div>
                        </div><!-- /market-people -->

                    </div><!-- /view-market -->

                    <!-- ══ СОЗДАТЬ ЗАЯВКУ ═════════════════════════════════ -->
                    <div id="view-create" class="content-view">
                        <div class="content-filter">
                            <div class="filter-item active" data-filter="new_reqs">Новые заявки</div>
                            <div class="filter-item" data-filter="my_reqs">Созданные заявки</div>
                            <div class="filter-item" data-filter="responses">
                                Отклики <span class="tab-badge" id="respBadge">0</span>
                            </div>
                        </div>

                        <!-- Вкладка: Новая заявка -->
                        <div id="tab-new" class="req-view active">
                            <div class="editing-banner hidden" id="editingBanner">
                                ✏️ <span>Редактирование заявки <strong id="editingBidLabel">#—</strong></span>
                                <button class="cancel-edit" id="cancelEditBtn">✕ отменить</button>
                            </div>
                            <div class="switch-row">
                                <?php if (!empty($user_orgs)): ?>
                                    <span>Студия (<?= htmlspecialchars($user_orgs[0]['name']) ?>)</span>
                                <?php else: ?>
                                    <span style="opacity:.4;">Студия недоступна</span>
                                <?php endif; ?>
                                <label class="switch">
                                    <input type="checkbox" id="typeToggle">
                                    <span class="slider"></span>
                                </label>
                                <span>Пользователь (<?= htmlspecialchars($_SESSION['USERDATA']['username'] ?? '') ?>)</span>
                            </div>

                            <form action="/swad/controllers/l4t/upsert_bid.php" method="POST">
                                <input type="hidden" name="owner_type" id="owner_type">
                                <input type="hidden" name="bidder_id" id="bidder_id">
                                <input type="hidden" name="bid_id" id="bid_id">
                                <input type="hidden" name="owner_id" id="owner_id">
                                <div class="grid-2x2">
                                    <div class="form-row">
                                        <label>Я хочу найти:</label>
                                        <select name="role">
                                            <option>Unity программист</option>
                                            <option>CGI художник</option>
                                            <option>Геймдизайнер</option>
                                            <option>Саунд дизайнер</option>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label>Уточнение:</label>
                                        <select name="spec">
                                            <option>Junior</option>
                                            <option>Middle</option>
                                            <option>Senior</option>
                                            <option>Любой уровень</option>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label>Опыт:</label>
                                        <select name="exp">
                                            <option>до 1 года</option>
                                            <option>1–3 года</option>
                                            <option>3–5 лет</option>
                                            <option>5+ лет</option>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <label>Условия:</label>
                                        <select name="cond">
                                            <option>Оплата за задачу</option>
                                            <option>Доля в проекте</option>
                                            <option>Оклад</option>
                                            <option>Бесплатно/энтузиазм</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row full">
                                    <label>Цель:</label>
                                    <select name="goal" style="width:85%">
                                        <option>Найти человека в команду</option>
                                        <option>Консультация</option>
                                        <option>Разовая работа</option>
                                    </select>
                                </div>
                                <div class="desc-row">
                                    <label>Детальное описание:</label>
                                    <div class="desc-wrap">
                                        <textarea name="details">Ищу бойца в команду для крутого проекта...</textarea>
                                        <button type="submit" class="ok-btn">✓</button>
                                    </div>
                                </div>
                            </form>
                        </div><!-- /tab-new -->

                        <!-- Вкладка: Мои заявки -->
                        <div id="tab-my" class="req-view">
                            <div class="my-bids-toolbar">
                                <input class="my-bids-search" type="text" id="myBidsSearch"
                                    placeholder="Поиск по названию или ключевым словам…">
                                <div class="my-bids-filter-tags" id="myBidsFilterTags">
                                    <button class="my-bids-ftag active" data-tag="">Все</button>
                                    <?php
                                    $seen_tags = [];
                                    foreach ($my_bids as $bid) {
                                        foreach (['search_spec', 'experience', 'conditions'] as $col) {
                                            $v = trim($bid[$col] ?? '');
                                            if ($v && !in_array($v, $seen_tags)) {
                                                $seen_tags[] = $v;
                                                echo '<button class="my-bids-ftag" data-tag="'
                                                    . htmlspecialchars($v, ENT_QUOTES) . '">'
                                                    . htmlspecialchars($v) . '</button>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div id="myBidsList">
                                <?php foreach ($my_bids as $bid): ?>
                                    <div class="my-bid"
                                        data-role="<?= htmlspecialchars($bid['search_role'] ?? '', ENT_QUOTES) ?>"
                                        data-spec="<?= htmlspecialchars($bid['search_spec'] ?? '', ENT_QUOTES) ?>"
                                        data-exp="<?= htmlspecialchars($bid['experience']   ?? '', ENT_QUOTES) ?>"
                                        data-cond="<?= htmlspecialchars($bid['conditions']  ?? '', ENT_QUOTES) ?>"
                                        data-details="<?= htmlspecialchars(mb_substr($bid['details'] ?? '', 0, 200), ENT_QUOTES) ?>">
                                        <div class="my-bid-main">
                                            <div>
                                                <strong><?= htmlspecialchars($bid['search_role']) ?></strong>
                                                <div class="bid-date">
                                                    <?= date('d.m.Y H:i', strtotime($bid['created_at'])) ?>
                                                    <span class="stats">👁 <?= (int)$bid['views'] ?> | 💬 <?= (int)$bid['responses'] ?></span>
                                                </div>
                                            </div>
                                            <button class="submit-btn edit-btn"
                                                data-id="<?= (int)$bid['id'] ?>"
                                                data-role="<?= htmlspecialchars($bid['search_role']) ?>"
                                                data-spec="<?= htmlspecialchars($bid['search_spec']  ?? '') ?>"
                                                data-exp="<?= htmlspecialchars($bid['experience']    ?? '') ?>"
                                                data-cond="<?= htmlspecialchars($bid['conditions']   ?? '') ?>"
                                                data-goal="<?= htmlspecialchars($bid['goal']         ?? '') ?>"
                                                data-details="<?= htmlspecialchars($bid['details']   ?? '') ?>">
                                                Редактировать
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div><!-- /tab-my -->

                        <!-- Вкладка: Отклики -->
                        <div id="tab-responses" class="req-view">
                            <div class="resp-sub-tabs">
                                <div class="resp-sub-tab active" data-resp="mine">Мои отклики</div>
                                <div class="resp-sub-tab" data-resp="incoming">Отклики на мои заявки</div>
                            </div>

                            <!-- Мои отклики -->
                            <div id="resp-mine" class="resp-pane active">
                                <?php if (empty($my_responds)): ?>
                                    <div class="resp-empty">Вы ещё не откликались на заявки</div>
                                <?php else: ?>
                                    <?php foreach ($my_responds as $r): ?>
                                        <div class="resp-card"
                                            data-role="<?= htmlspecialchars($r['search_role']  ?? '', ENT_QUOTES) ?>"
                                            data-spec="<?= htmlspecialchars($r['search_spec']  ?? '', ENT_QUOTES) ?>"
                                            data-cond="<?= htmlspecialchars($r['conditions']   ?? '', ENT_QUOTES) ?>"
                                            data-date="<?= date('d.m.Y H:i', strtotime($r['created_at'])) ?>"
                                            data-status="<?= htmlspecialchars($r['status']     ?? 'ожидает', ENT_QUOTES) ?>"
                                            data-message="<?= htmlspecialchars($r['message']   ?? '', ENT_QUOTES) ?>"
                                            data-mine="1">
                                            <div class="resp-card-title"><?= htmlspecialchars($r['search_role'] ?? '—') ?></div>
                                            <div class="resp-card-meta">
                                                <?php if ($r['search_spec']): ?><span><?= htmlspecialchars($r['search_spec']) ?></span><?php endif; ?>
                                                <?php if ($r['conditions']): ?><span><?= htmlspecialchars($r['conditions']) ?></span><?php endif; ?>
                                                <span>Отклик: <?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
                                                <span class="rc-status"><?= htmlspecialchars($r['status'] ?? 'ожидает') ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Входящие отклики -->
                            <div id="resp-incoming" class="resp-pane">
                                <?php if (empty($incoming_responds)): ?>
                                    <div class="resp-empty">На ваши заявки пока нет откликов</div>
                                <?php else: ?>
                                    <?php foreach ($incoming_responds as $r): ?>
                                        <div class="resp-card"
                                            data-role="<?= htmlspecialchars($r['search_role']        ?? '', ENT_QUOTES) ?>"
                                            data-spec="<?= htmlspecialchars($r['search_spec']        ?? '', ENT_QUOTES) ?>"
                                            data-cond="<?= htmlspecialchars($r['conditions']         ?? '', ENT_QUOTES) ?>"
                                            data-date="<?= date('d.m.Y H:i', strtotime($r['created_at'])) ?>"
                                            data-status="<?= htmlspecialchars($r['status']           ?? 'новый', ENT_QUOTES) ?>"
                                            data-message="<?= htmlspecialchars($r['message']         ?? '', ENT_QUOTES) ?>"
                                            data-username="<?= htmlspecialchars($r['username']       ?? ('@' . $r['telegram_username']), ENT_QUOTES) ?>"
                                            data-tg="<?= htmlspecialchars($r['telegram_username']    ?? '', ENT_QUOTES) ?>"
                                            data-l4trole="<?= htmlspecialchars($r['l4t_role']        ?? '', ENT_QUOTES) ?>"
                                            data-incoming="1">
                                            <div class="resp-card-title">
                                                <?= htmlspecialchars($r['username'] ?? ('@' . $r['telegram_username'])) ?>
                                                <span style="color:rgba(255,255,255,.35);font-size:.78rem;margin-left:6px;">
                                                    → <?= htmlspecialchars($r['search_role'] ?? '—') ?>
                                                </span>
                                            </div>
                                            <div class="resp-card-meta">
                                                <?php if ($r['search_spec']): ?><span><?= htmlspecialchars($r['search_spec']) ?></span><?php endif; ?>
                                                <?php if ($r['conditions']): ?><span><?= htmlspecialchars($r['conditions']) ?></span><?php endif; ?>
                                                <span><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
                                                <span class="rc-status"><?= htmlspecialchars($r['status'] ?? 'новый') ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        </div><!-- /tab-responses -->

                    </div><!-- /view-create -->

                </div><!-- /content-background -->
            </div><!-- /right-content-view -->

        </div><!-- /view-container -->
    </div><!-- /main-container -->

    <!-- ══ МОДАЛ ════════════════════════════════════════════════════════ -->
    <div class="modal-overlay hidden" id="globalModal">
        <div class="modal-box">
            <span class="modal-close" id="modalClose">✕</span>
            <div class="modal-title" id="modalTitle"></div>
            <div id="modalBody"></div>
            <div class="modal-actions" id="modalActions">
                <button class="modal-btn modal-btn-ghost" id="modalCancel">Отмена</button>
                <button class="modal-btn modal-btn-primary" id="modalSave">Сохранить</button>
            </div>
        </div>
    </div>

    <style>
        .resp-empty {
            color: rgba(255, 255, 255, .35);
            font-size: .85rem;
            padding: 20px 0;
        }
    </style>

    <script>
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
        const USER_ID = <?= (int)($userdata['id'] ?? 0) ?>;
        let expModel = <?= json_encode($l4t_exp) ?>;
        let filesModel = <?= json_encode($l4t_files) ?>;
        let projectsModel = <?= json_encode($l4t_projects) ?>;
        const aboutFull = <?= json_encode($l4t_about) ?>;

        function esc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function apiPost(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload),
            }).then(r => r.json());
        }

        function uploadFile(file) {
            return new Promise(resolve => {
                const reader = new FileReader();
                reader.onload = async e => {
                    const ext = file.name.split('.').pop();
                    const res = await apiPost('/swad/controllers/l4t/l4t_update.php', {
                        type: 'upload',
                        file: e.target.result,
                        ext,
                    }).catch(() => ({}));
                    resolve(res.url || '');
                };
                reader.readAsDataURL(file);
            });
        }

        /* ── Модал ── */
        const Modal = {
            _onSave: null,
            open(title, bodyHTML, onSave, {
                hideSave = false
            } = {}) {
                document.getElementById('modalTitle').textContent = title;
                document.getElementById('modalBody').innerHTML = bodyHTML;
                document.getElementById('modalSave').classList.toggle('hidden', hideSave);
                document.getElementById('globalModal').classList.remove('hidden');
                this._onSave = onSave;
            },
            close() {
                document.getElementById('globalModal').classList.add('hidden');
                document.getElementById('modalBody').innerHTML = '';
                const saveBtn = document.getElementById('modalSave');
                saveBtn.textContent = 'Сохранить';
                saveBtn.disabled = false;
                saveBtn.style.background = '';
                this._onSave = null;
            },
            save() {
                if (this._onSave) this._onSave();
            },
        };

        document.getElementById('modalClose').addEventListener('click', () => Modal.close());
        document.getElementById('modalCancel').addEventListener('click', () => Modal.close());
        document.getElementById('modalSave').addEventListener('click', () => Modal.save());
        document.getElementById('globalModal').addEventListener('click', e => {
            if (e.target === document.getElementById('globalModal')) Modal.close();
        });

        /* ════════════════════════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', () => {

            /* ── Переключение секций ── */
            const views = {
                market: document.getElementById('view-market'),
                create: document.getElementById('view-create'),
                profile: document.querySelector('.profile-page'),
            };
            const buttons = {
                market: document.querySelector('.left-side-button'),
                create: document.querySelector('.left-side-button1'),
                profile: document.getElementById('btn-profile'),
            };

            function showView(name) {
                if (!views[name]) name = 'profile';
                Object.values(views).forEach(v => {
                    if (v) v.style.display = 'none';
                });
                Object.values(buttons).forEach(b => {
                    if (b) b.classList.remove('active');
                });
                if (views[name]) views[name].style.display = 'block';
                if (buttons[name]) buttons[name].classList.add('active');
                localStorage.setItem('activeView', name);
            }

            buttons.market?.addEventListener('click', () => showView('market'));
            buttons.create?.addEventListener('click', () => showView('create'));
            buttons.profile?.addEventListener('click', () => showView('profile'));

            /* ── Вкладки внутри «Создать заявку» ── */
            function showCreateTab(filter) {
                document.querySelectorAll('#view-create .filter-item').forEach(b => b.classList.remove('active'));
                const target = document.querySelector(`#view-create .filter-item[data-filter="${filter}"]`);
                if (target) target.classList.add('active');
                document.getElementById('tab-new').classList.toggle('active', filter === 'new_reqs');
                document.getElementById('tab-my').classList.toggle('active', filter === 'my_reqs');
                document.getElementById('tab-responses').classList.toggle('active', filter === 'responses');
                localStorage.setItem('createSubTab', filter);
            }
            document.querySelectorAll('#view-create .filter-item').forEach(btn => {
                btn.addEventListener('click', () => showCreateTab(btn.dataset.filter));
            });

            /* ── Бейдж откликов ── */
            function updateRespBadge() {
                const total = document.querySelectorAll('#resp-mine .resp-card, #resp-incoming .resp-card').length;
                const badge = document.getElementById('respBadge');
                if (badge) {
                    badge.textContent = total;
                    badge.classList.toggle('zero', total === 0);
                }
            }
            updateRespBadge();

            /* ── Подвкладки откликов ── */
            document.querySelectorAll('.resp-sub-tab').forEach(t => {
                t.addEventListener('click', () => {
                    document.querySelectorAll('.resp-sub-tab').forEach(x => x.classList.remove('active'));
                    t.classList.add('active');
                    const pane = t.dataset.resp;
                    document.getElementById('resp-mine').classList.toggle('active', pane === 'mine');
                    document.getElementById('resp-incoming').classList.toggle('active', pane === 'incoming');
                });
            });

            /* ── Клик по карточке отклика → модал ── */
            document.getElementById('tab-responses')?.addEventListener('click', e => {
                const card = e.target.closest('.resp-card');
                if (!card) return;

                const isMine = !!card.dataset.mine;
                const isIncoming = !!card.dataset.incoming;

                const role = card.dataset.role || '—';
                const spec = card.dataset.spec || '';
                const cond = card.dataset.cond || '';
                const date = card.dataset.date || '';
                const status = card.dataset.status || '';
                const message = card.dataset.message || '';

                function row(label, val) {
                    if (!val) return '';
                    return `<div style="display:flex;gap:10px;margin-bottom:8px;">
                <span style="color:rgba(255,255,255,.4);font-size:.75rem;min-width:90px;padding-top:2px;">${label}</span>
                <span style="color:#e8ddf0;font-size:.88rem;">${esc(val)}</span>
            </div>`;
                }

                let contactBlock = '';
                if (isIncoming) {
                    const username = card.dataset.username || '';
                    const tg = card.dataset.tg || '';
                    const l4tRole = card.dataset.l4trole || '';
                    contactBlock = `
                <div class="contact-block">
                    <div class="contact-block-title">Контакты откликнувшегося</div>
                    ${username ? `<div class="contact-row"><span class="contact-icon">👤</span>
                        <a href="/l4t?username=${esc(username)}" target="_blank">${esc(username)}</a>
                        ${l4tRole ? `<span style="color:rgba(255,255,255,.4);font-size:.78rem;">(${esc(l4tRole)})</span>` : ''}
                    </div>` : ''}
                    ${tg ? `<div class="contact-row"><span class="contact-icon">✈️</span>
                        <a href="https://t.me/${esc(tg)}" target="_blank">@${esc(tg)}</a>
                    </div>` : ''}
                    ${!username && !tg ? '<div style="color:rgba(255,255,255,.35);font-size:.85rem;">Контакты не указаны</div>' : ''}
                </div>`;
                }

                let msgBlock = '';
                if (message) {
                    msgBlock = `
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);">
                    <div style="font-size:.75rem;color:rgba(255,255,255,.4);margin-bottom:6px;">Сопроводительное сообщение</div>
                    <div style="font-size:.88rem;color:#e8ddf0;line-height:1.7;white-space:pre-wrap;">${esc(message)}</div>
                </div>`;
                }

                const bodyHTML = `
            ${row('Вакансия', role)}
            ${row('Уровень',  spec)}
            ${row('Условия',  cond)}
            ${row('Дата отклика', date)}
            ${row('Статус',   status)}
            ${msgBlock}
            ${contactBlock}
        `;

                Modal.open(
                    isMine ? `Мой отклик — ${role}` : `Отклик на заявку — ${role}`,
                    bodyHTML,
                    null, {
                        hideSave: true
                    }
                );
            });

            /* ── Восстановление активного вида ── */
            showView(<?= $isOwner ? "localStorage.getItem('activeView') || 'profile'" : "'profile'" ?>);
            const savedSub = localStorage.getItem('createSubTab');
            const validSubs = ['new_reqs', 'my_reqs', 'responses'];
            showCreateTab(savedSub && validSubs.includes(savedSub) ? savedSub : 'new_reqs');

            /* ── Переключатель студия/пользователь ── */
            const typeToggle = document.getElementById('typeToggle');

            function updateOwner() {
                const isStudio = typeToggle && !typeToggle.checked;
                document.getElementById('owner_type').value = isStudio ? 'studio' : 'user';
                document.getElementById('owner_id').value = isStudio ?
                    <?= isset($user_orgs[0]['id']) ? (int)$user_orgs[0]['id'] : 'null' ?> :
                    <?= isset($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 'null' ?>;
            }
            typeToggle?.addEventListener('change', updateOwner);
            updateOwner();

            /* ── Dirty-state кнопки «✓» ── */
            const okBtn = document.querySelector('.ok-btn');
            let formDirty = false;

            function getFormSnapshot() {
                return {
                    role: document.querySelector('[name="role"]')?.value,
                    spec: document.querySelector('[name="spec"]')?.value,
                    exp: document.querySelector('[name="exp"]')?.value,
                    cond: document.querySelector('[name="cond"]')?.value,
                    goal: document.querySelector('[name="goal"]')?.value,
                    details: document.querySelector('[name="details"]')?.value,
                };
            }
            let formSnapshot = getFormSnapshot();

            function checkDirty() {
                if (!formSnapshot) return;
                const curr = getFormSnapshot();
                const changed = Object.keys(curr).some(k => curr[k] !== formSnapshot[k]);
                if (changed && !formDirty) {
                    formDirty = true;
                    okBtn?.classList.add('dirty');
                }
            }

            function resetDirty(snap) {
                formDirty = false;
                formSnapshot = snap || getFormSnapshot();
                okBtn?.classList.remove('dirty');
            }

            document.querySelectorAll('#tab-new select, #tab-new textarea').forEach(el => {
                el.addEventListener('change', checkDirty);
                el.addEventListener('input', checkDirty);
            });
            document.querySelector('#tab-new form')?.addEventListener('submit', () => {
                resetDirty(null);
                document.getElementById('editingBanner')?.classList.add('hidden');
            });

            /* ── Отмена редактирования ── */
            document.getElementById('cancelEditBtn')?.addEventListener('click', () => {
                document.getElementById('bid_id').value = '';
                document.getElementById('editingBanner')?.classList.add('hidden');
                document.querySelector('[name="role"]').selectedIndex = 0;
                document.querySelector('[name="spec"]').selectedIndex = 0;
                document.querySelector('[name="exp"]').selectedIndex = 0;
                document.querySelector('[name="cond"]').selectedIndex = 0;
                document.querySelector('[name="goal"]').selectedIndex = 0;
                document.querySelector('[name="details"]').value = 'Ищу бойца в команду для крутого проекта...';
                resetDirty(getFormSnapshot());
            });

            /* ── Кнопки «Редактировать» ── */
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    showView('create');
                    showCreateTab('new_reqs');
                    document.getElementById('bid_id').value = btn.dataset.id;
                    document.querySelector('[name="role"]').value = btn.dataset.role;
                    document.querySelector('[name="spec"]').value = btn.dataset.spec;
                    document.querySelector('[name="exp"]').value = btn.dataset.exp;
                    document.querySelector('[name="cond"]').value = btn.dataset.cond;
                    document.querySelector('[name="goal"]').value = btn.dataset.goal;
                    document.querySelector('[name="details"]').value = btn.dataset.details;
                    const banner = document.getElementById('editingBanner');
                    const label = document.getElementById('editingBidLabel');
                    if (banner) banner.classList.remove('hidden');
                    if (label) label.textContent = '#' + btn.dataset.id;
                    resetDirty(getFormSnapshot());
                });
            });

            /* ── Фильтрация «Мои заявки» ── */
            const myBidsSearch = document.getElementById('myBidsSearch');
            const myBidsList = document.getElementById('myBidsList');

            function applyMyBidsFilter() {
                const query = (myBidsSearch?.value || '').toLowerCase().trim();
                const activeTag = document.querySelector('#myBidsFilterTags .my-bids-ftag.active')?.dataset.tag || '';
                myBidsList?.querySelectorAll('.my-bid').forEach(card => {
                    const text = [card.dataset.role, card.dataset.spec, card.dataset.exp,
                        card.dataset.cond, card.dataset.details
                    ].join(' ').toLowerCase();
                    const matchSearch = !query || text.includes(query);
                    const matchTag = !activeTag || [card.dataset.spec, card.dataset.exp, card.dataset.cond]
                        .some(v => (v || '').toLowerCase() === activeTag.toLowerCase());
                    card.style.display = (matchSearch && matchTag) ? '' : 'none';
                });
            }
            myBidsSearch?.addEventListener('input', applyMyBidsFilter);
            document.querySelectorAll('#myBidsFilterTags .my-bids-ftag').forEach(tag => {
                tag.addEventListener('click', () => {
                    document.querySelectorAll('#myBidsFilterTags .my-bids-ftag').forEach(t => t.classList.remove('active'));
                    tag.classList.add('active');
                    applyMyBidsFilter();
                });
            });

            /* ── Фильтрация биржи ── */
            const marketSearch = document.getElementById('marketSearch');

            function applyMarketFilter() {
                const query = (marketSearch?.value || '').toLowerCase().trim();
                const activeTag = document.querySelector('#marketFilterTags .my-bids-ftag.active')?.dataset.tag || '';
                document.querySelectorAll('#market-projects .bid-card-item').forEach(card => {
                    const text = [card.dataset.role, card.dataset.spec, card.dataset.exp,
                        card.dataset.cond, card.dataset.goal, card.dataset.details
                    ].join(' ').toLowerCase();
                    const matchSearch = !query || text.includes(query);
                    const matchTag = !activeTag || [card.dataset.spec, card.dataset.exp, card.dataset.cond]
                        .some(v => (v || '').toLowerCase() === activeTag.toLowerCase());
                    card.style.display = (matchSearch && matchTag) ? '' : 'none';
                });
            }
            marketSearch?.addEventListener('input', applyMarketFilter);
            document.querySelectorAll('#marketFilterTags .my-bids-ftag').forEach(tag => {
                tag.addEventListener('click', () => {
                    document.querySelectorAll('#marketFilterTags .my-bids-ftag').forEach(t => t.classList.remove('active'));
                    tag.classList.add('active');
                    applyMarketFilter();
                });
            });

            /* ── Редактирование роли ── */
            if (IS_OWNER) {
                const roleText = document.querySelector('.row.role .role-text');
                const roleEdit = document.querySelector('.row.role .role-edit');
                if (roleText && roleEdit) {
                    roleText.addEventListener('click', () => {
                        roleText.classList.add('hidden');
                        roleEdit.classList.remove('hidden');
                        roleEdit.focus();
                    });

                    function commitRole() {
                        const val = roleEdit.value.trim();
                        roleText.textContent = val || 'Роль не указана';
                        roleText.classList.remove('hidden');
                        roleEdit.classList.add('hidden');
                        apiPost('/swad/controllers/l4t/update_role.php', {
                                id: USER_ID,
                                role: val
                            })
                            .then(d => {
                                if (!d.success) alert('Роль не сохранилась');
                            });
                    }
                    roleEdit.addEventListener('blur', commitRole);
                    roleEdit.addEventListener('keydown', e => {
                        if (e.key === 'Enter') roleEdit.blur();
                        if (e.key === 'Escape') {
                            roleEdit.value = roleText.textContent.trim();
                            roleEdit.blur();
                        }
                    });
                }
            }

            /* ── Опыт ── */
            const expContainer = document.getElementById('expTags');
            if (expContainer) renderExp();

            function renderExp() {
                expContainer.innerHTML = '';
                expModel.forEach((e, i) => {
                    const tag = document.createElement('div');
                    tag.className = 'exp-tag';
                    if (IS_OWNER) {
                        tag.innerHTML = `
                    <input class="exp-role"  type="text"   maxlength="30" value="${esc(e.role)}"  title="Специальность">
                    <input class="exp-years" type="number" min="0" max="50" value="${parseInt(e.years)||0}" title="Лет опыта">г.
                    <span class="del-btn" data-i="${i}" title="Удалить">×</span>`;
                        const rInp = tag.querySelector('.exp-role');
                        const yInp = tag.querySelector('.exp-years');
                        const idx = i;
                        let timer;

                        function schedSave() {
                            clearTimeout(timer);
                            timer = setTimeout(() => {
                                expModel[idx] = {
                                    role: rInp.value.slice(0, 30),
                                    years: Math.min(50, Math.max(0, parseInt(yInp.value) || 0)),
                                };
                                saveExp(false);
                            }, 500);
                        }
                        rInp.addEventListener('input', schedSave);
                        yInp.addEventListener('input', schedSave);
                        rInp.addEventListener('keydown', ev => {
                            if (ev.key === 'Enter') rInp.blur();
                        });
                        yInp.addEventListener('keydown', ev => {
                            if (ev.key === 'Enter') yInp.blur();
                        });
                    } else {
                        tag.innerHTML = `<span>${esc(e.role)}</span><span>${parseInt(e.years)||0}г.</span>`;
                    }
                    expContainer.appendChild(tag);
                });
                if (IS_OWNER) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'l4t-add-btn';
                    addBtn.textContent = '+ добавить';
                    addBtn.addEventListener('click', () => {
                        expModel.push({
                            role: '',
                            years: 0
                        });
                        renderExp();
                        expContainer.querySelectorAll('.exp-role')[expModel.length - 1]?.focus();
                    });
                    expContainer.appendChild(addBtn);
                    expContainer.addEventListener('click', ev => {
                        if (!ev.target.classList.contains('del-btn')) return;
                        const idx = parseInt(ev.target.dataset.i, 10);
                        if (!isNaN(idx)) {
                            expModel.splice(idx, 1);
                            saveExp(true);
                        }
                    }, {
                        once: true
                    });
                }
            }

            function saveExp(rerender = true) {
                apiPost('/swad/controllers/l4t/update_exp.php', {
                        exp: expModel
                    })
                    .then(d => {
                        if (!d.success) {
                            alert('Опыт не сохранился');
                            return;
                        }
                        if (rerender) renderExp();
                    });
            }

            /* ── Файлы ── */
            document.getElementById('filesAddBtn')?.addEventListener('click', openFilesModal);

            function openFilesModal() {
                function rowHTML(f = {}) {
                    return `<div class="modal-row file-row">
                <select class="l4t-select ftype" style="width:80px;flex:none;">
                    <option value="link" ${(f.type||'link')==='link'?'selected':''}>🔗 ссылка</option>
                    <option value="file" ${f.type==='file'?'selected':''}>📄 файл</option>
                </select>
                <input class="l4t-input fname" placeholder="Название" maxlength="60" value="${esc(f.name||'')}" style="flex:1;">
                <input class="l4t-input fval"  placeholder="URL"      maxlength="500" value="${esc(f.value||'')}" style="flex:2;">
                <span class="modal-del" title="Удалить">✕</span>
            </div>`;
                }
                Modal.open('Доп. данные',
                    `<div id="fileRowsWrap">${filesModel.map(rowHTML).join('')}</div>
             <button class="l4t-add-btn" id="fileAddRow" style="margin-top:8px;">+ строка</button>`,
                    () => {
                        filesModel = [...document.querySelectorAll('.file-row')].map(r => ({
                            type: r.querySelector('.ftype').value,
                            name: r.querySelector('.fname').value.trim().slice(0, 60),
                            value: r.querySelector('.fval').value.trim().slice(0, 500),
                        })).filter(f => f.name || f.value);
                        apiPost('/swad/controllers/l4t/l4t_update.php', {
                                type: 'files',
                                data: filesModel
                            })
                            .then(d => {
                                if (!d.success) {
                                    alert('Не сохранилось');
                                    return;
                                }
                                renderFilesWrap();
                                Modal.close();
                            });
                    });
                document.getElementById('fileAddRow').addEventListener('click', () => {
                    document.getElementById('fileRowsWrap').insertAdjacentHTML('beforeend', rowHTML());
                });
                document.getElementById('fileRowsWrap').addEventListener('click', e => {
                    if (e.target.classList.contains('modal-del')) e.target.closest('.file-row').remove();
                });
            }

            function renderFilesWrap() {
                const wrap = document.getElementById('filesWrap');
                if (!wrap) return;
                wrap.innerHTML = filesModel.map(f => `
            <a class="file-chip" href="${esc(f.value)}" target="_blank" title="${esc(f.name)}">
                <span class="chip-icon">${f.type === 'link' ? '🔗' : '📄'}</span>
                ${esc(f.name.slice(0, 22))}
            </a>`).join('');
                if (IS_OWNER) {
                    const btn = document.createElement('button');
                    btn.className = 'l4t-add-btn';
                    btn.id = 'filesAddBtn';
                    btn.textContent = '+ добавить';
                    btn.addEventListener('click', openFilesModal);
                    wrap.appendChild(btn);
                }
            }

            /* ── Проекты ── */
            document.getElementById('projAddBtn')?.addEventListener('click', () => openProjModal());
            document.getElementById('projGrid')?.addEventListener('click', e => {
                const thumb = e.target.closest('.proj-thumb');
                if (!thumb) return;
                const proj = JSON.parse(thumb.dataset.proj || '{}');
                IS_OWNER ? openProjModal(proj, thumb) : openProjView(proj);
            });

            function openProjView(p) {
                Modal.open(p.title || 'Проект', `
            ${p.cover ? `<div style="width:100%;height:130px;border-radius:7px;background:url(${esc(p.cover)}) center/cover;margin-bottom:14px;"></div>` : ''}
            ${p.role        ? `<p style="margin-bottom:6px;"><span style="color:rgba(255,255,255,.4);font-size:.75rem;">Роль:</span> ${esc(p.role)}</p>` : ''}
            ${p.year        ? `<p style="margin-bottom:6px;"><span style="color:rgba(255,255,255,.4);font-size:.75rem;">Год:</span> ${p.year}</p>` : ''}
            ${p.url         ? `<p style="margin-bottom:10px;"><a href="${esc(p.url)}" target="_blank" style="color:#c32178;">🔗 Открыть проект</a></p>` : ''}
            ${p.description ? `<p style="font-size:.88rem;line-height:1.6;">${esc(p.description)}</p>` : ''}
        `, null, {
                    hideSave: true
                });
            }

            function openProjModal(proj = {}, thumb = null) {
                const isEdit = !!thumb;
                const bodyHTML = `
            <div class="modal-field">
                <label class="modal-label">Название *</label>
                <input class="l4t-input" id="pTitle" maxlength="80" value="${esc(proj.title||'')}" placeholder="Название проекта">
            </div>
            <div class="modal-field">
                <label class="modal-label">Ваша роль в проекте</label>
                <input class="l4t-input" id="pRole" maxlength="60" value="${esc(proj.role||'')}" placeholder="Художник, программист…">
            </div>
            <div class="modal-field">
                <label class="modal-label">Год</label>
                <input class="l4t-input" id="pYear" type="number" min="1990" max="2100" value="${proj.year||''}" placeholder="2024" style="width:120px;">
            </div>
            <div class="modal-field">
                <label class="modal-label">Ссылка на проект</label>
                <input class="l4t-input" id="pUrl" maxlength="500" value="${esc(proj.url||'')}" placeholder="https://…">
            </div>
            <div class="modal-field">
                <label class="modal-label">Обложка</label>
                <label class="upload-btn">📁 Выбрать изображение<input type="file" id="pCoverFile" accept="image/*"></label>
                <div class="cover-preview" id="pCoverPreview" style="${proj.cover?'background-image:url('+esc(proj.cover)+')':''}">
                    ${proj.cover ? '' : 'Нет обложки'}
                </div>
            </div>
            <div class="modal-field">
                <label class="modal-label">Описание (до 500 символов)</label>
                <textarea class="l4t-textarea" id="pDesc" maxlength="500" style="min-height:80px;">${esc(proj.description||'')}</textarea>
                <div class="char-count" id="pDescCount">${(proj.description||'').length} / 500</div>
            </div>
            ${isEdit ? '<button class="modal-btn" id="pDeleteBtn" style="background:rgba(244,67,54,.15);color:#f44336;border:1px solid rgba(244,67,54,.3);margin-top:4px;">Удалить проект</button>' : ''}
        `;
                let pendingCoverUrl = proj.cover || '';
                Modal.open(isEdit ? 'Редактировать проект' : 'Новый проект', bodyHTML, async () => {
                    const title = document.getElementById('pTitle').value.trim();
                    if (!title) {
                        alert('Укажите название');
                        return;
                    }
                    const fileInp = document.getElementById('pCoverFile');
                    if (fileInp.files[0]) {
                        const url = await uploadFile(fileInp.files[0]);
                        if (url) pendingCoverUrl = url;
                    }
                    const updated = {
                        title: title.slice(0, 80),
                        role: document.getElementById('pRole').value.trim().slice(0, 60),
                        year: parseInt(document.getElementById('pYear').value) || 0,
                        url: document.getElementById('pUrl').value.trim().slice(0, 500),
                        cover: pendingCoverUrl,
                        description: document.getElementById('pDesc').value.trim().slice(0, 500),
                    };
                    if (isEdit) {
                        const idx = projectsModel.findIndex(p => p.title === proj.title && p.description === proj.description);
                        if (idx !== -1) projectsModel[idx] = updated;
                        else projectsModel.push(updated);
                    } else {
                        projectsModel.push(updated);
                    }
                    apiPost('/swad/controllers/l4t/l4t_update.php', {
                            type: 'projects',
                            data: projectsModel
                        })
                        .then(d => {
                            if (!d.success) {
                                alert('Не сохранилось');
                                return;
                            }
                            renderProjGrid();
                            Modal.close();
                        });
                });
                document.getElementById('pCoverFile').addEventListener('change', e => {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = ev => {
                        const prev = document.getElementById('pCoverPreview');
                        prev.style.backgroundImage = `url(${ev.target.result})`;
                        prev.textContent = '';
                        pendingCoverUrl = '';
                    };
                    reader.readAsDataURL(file);
                });
                document.getElementById('pDesc').addEventListener('input', () => {
                    document.getElementById('pDescCount').textContent = `${document.getElementById('pDesc').value.length} / 500`;
                });
                document.getElementById('pDeleteBtn')?.addEventListener('click', () => {
                    if (!confirm('Удалить проект?')) return;
                    projectsModel = projectsModel.filter(p => !(p.title === proj.title && p.description === proj.description));
                    apiPost('/swad/controllers/l4t/l4t_update.php', {
                            type: 'projects',
                            data: projectsModel
                        })
                        .then(d => {
                            if (!d.success) {
                                alert('Не удалилось');
                                return;
                            }
                            renderProjGrid();
                            Modal.close();
                        });
                });
            }

            function renderProjGrid() {
                const grid = document.getElementById('projGrid');
                if (!grid) return;
                grid.innerHTML = '';
                projectsModel.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'proj-thumb';
                    div.dataset.proj = JSON.stringify(p);
                    if (p.cover) div.style.backgroundImage = `url(${p.cover})`;
                    div.innerHTML = `<div class="proj-label">${esc(p.title)}</div>`;
                    grid.appendChild(div);
                });
                if (IS_OWNER) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'l4t-add-btn';
                    addBtn.id = 'projAddBtn';
                    addBtn.style.cssText = 'height:72px;width:72px;flex-direction:column;font-size:1.2rem;';
                    addBtn.textContent = '+';
                    addBtn.addEventListener('click', () => openProjModal());
                    grid.appendChild(addBtn);
                }
            }

            /* ── О себе ── */
            document.getElementById('aboutMoreBtn')?.addEventListener('click', () => {
                Modal.open('О себе',
                    `<div style="white-space:pre-wrap;line-height:1.7;font-size:.9rem;color:#e8ddf0;">${esc(aboutFull)}</div>`,
                    null, {
                        hideSave: true
                    });
            });
            document.getElementById('aboutEditBtn')?.addEventListener('click', openAboutModal);

            function openAboutModal() {
                Modal.open('О себе', `
            <div class="modal-field">
                <label class="modal-label">До 10 000 символов</label>
                <textarea class="l4t-textarea" id="aboutTA" maxlength="10000" style="min-height:220px;">${esc(aboutFull)}</textarea>
                <div class="char-count" id="aboutCount">${aboutFull.length} / 10000</div>
            </div>`, () => {
                    const val = document.getElementById('aboutTA').value.slice(0, 10000);
                    apiPost('/swad/controllers/l4t/l4t_update.php', {
                            type: 'about',
                            data: val
                        })
                        .then(d => {
                            if (!d.success) {
                                alert('Не сохранилось');
                                return;
                            }
                            const block = document.querySelector('.about-block');
                            const empty = document.querySelector('.about-empty');
                            const preview = val.slice(0, 200) + (val.length > 200 ? '...' : '');
                            if (block) block.textContent = preview;
                            if (empty && val) empty.replaceWith(Object.assign(document.createElement('div'), {
                                className: 'about-block',
                                textContent: preview
                            }));
                            Modal.close();
                        });
                });
                document.getElementById('aboutTA').addEventListener('input', () => {
                    document.getElementById('aboutCount').textContent = `${document.getElementById('aboutTA').value.length} / 10000`;
                });
            }

            /* ── Открытие заявки + отклик ── */
            document.getElementById('market-projects')?.addEventListener('click', e => {
                const btn = e.target.closest('.respond-btn');
                if (!btn) return;
                const d = btn.dataset;
                const typeLabel = d.type === 'studio' ? 'Студия' : 'Пользователь';
                const typeIcon = d.type === 'studio' ? '🏢' : '👤';

                function row(label, val) {
                    if (!val) return '';
                    return `<div style="display:flex;gap:10px;margin-bottom:8px;">
                <span style="color:rgba(255,255,255,.4);font-size:.75rem;min-width:90px;padding-top:2px;">${label}</span>
                <span style="color:#e8ddf0;font-size:.88rem;">${esc(val)}</span>
            </div>`;
                }

                const bodyHTML = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.08);">
                <div style="width:36px;height:36px;border-radius:8px;background:${d.type==='studio'?'rgba(33,195,120,.15)':'rgba(195,33,120,.15)'};display:flex;align-items:center;justify-content:center;font-size:16px;">${typeIcon}</div>
                <span style="font-size:.78rem;color:rgba(255,255,255,.4);">${typeLabel}</span>
                <span style="margin-left:auto;font-size:.75rem;color:rgba(255,255,255,.3);">${esc(d.date)} · 👁 ${esc(d.views)} · 💬 ${esc(d.responses)}</span>
            </div>
            ${row('Специальность', d.role)}
            ${row('Уровень',       d.spec)}
            ${row('Опыт',          d.exp)}
            ${row('Условия',       d.cond)}
            ${row('Цель',          d.goal)}
            ${d.details ? `
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);">
                <div style="font-size:.75rem;color:rgba(255,255,255,.4);margin-bottom:6px;">Описание</div>
                <div style="font-size:.88rem;color:#e8ddf0;line-height:1.7;white-space:pre-wrap;">${esc(d.details)}</div>
            </div>` : ''}
            <div style="margin-top:18px;">
                <div style="font-size:.75rem;color:rgba(255,255,255,.4);margin-bottom:6px;">Сопроводительное сообщение (необязательно)</div>
                <textarea id="respondMsg" class="l4t-textarea" maxlength="1000"
                          placeholder="Расскажите о себе, своём опыте…"
                          style="min-height:90px;width:100%;box-sizing:border-box;"></textarea>
                <div class="char-count" id="respondMsgCount">0 / 1000</div>
            </div>`;

                Modal.open(`Заявка #${d.bid}`, bodyHTML, () => submitRespond(d.bid));
                document.getElementById('modalSave').textContent = 'Откликнуться';
                setTimeout(() => {
                    document.getElementById('respondMsg')?.addEventListener('input', () => {
                        const len = document.getElementById('respondMsg').value.length;
                        document.getElementById('respondMsgCount').textContent = `${len} / 1000`;
                    });
                }, 50);
            });

            function submitRespond(bidId) {
                const msg = document.getElementById('respondMsg')?.value.trim() || '';
                apiPost('/swad/controllers/l4t/respond_bid.php', {
                        bid_id: bidId,
                        message: msg
                    })
                    .then(d => {
                        if (d.success) {
                            const saveBtn = document.getElementById('modalSave');
                            saveBtn.textContent = '✓ Отклик отправлен';
                            saveBtn.disabled = true;
                            saveBtn.style.background = '#2a7a4b';

                            /* Обновляем счётчик и кнопку в карточке */
                            const bidBtn = document.querySelector(`.respond-btn[data-bid="${bidId}"]`);
                            if (bidBtn) {
                                const statsEl = bidBtn.closest('.bid-card-item')?.querySelector('.bid-stats span:last-child');
                                if (statsEl) {
                                    const curr = parseInt(statsEl.textContent.replace(/\D/g, '')) || 0;
                                    statsEl.textContent = `💬 ${curr + 1}`;
                                }
                                bidBtn.textContent = '✓ Откликнулся';
                                bidBtn.disabled = true;
                                bidBtn.style.opacity = '0.5';
                            }

                            /* Добавляем карточку в «Мои отклики» */
                            const minePane = document.getElementById('resp-mine');
                            const emptyEl = minePane?.querySelector('.resp-empty');
                            if (emptyEl) emptyEl.remove();

                            if (minePane && bidBtn) {
                                const card = document.createElement('div');
                                card.className = 'resp-card';
                                card.dataset.role = bidBtn.dataset.role || '';
                                card.dataset.spec = bidBtn.dataset.spec || '';
                                card.dataset.cond = bidBtn.dataset.cond || '';
                                card.dataset.date = new Date().toLocaleString('ru');
                                card.dataset.status = 'ожидает';
                                card.dataset.message = msg;
                                card.dataset.mine = '1';
                                card.innerHTML = `
                            <div class="resp-card-title">${esc(bidBtn.dataset.role || '—')}</div>
                            <div class="resp-card-meta">
                                ${bidBtn.dataset.spec ? `<span>${esc(bidBtn.dataset.spec)}</span>` : ''}
                                ${bidBtn.dataset.cond ? `<span>${esc(bidBtn.dataset.cond)}</span>` : ''}
                                <span>Отклик: только что</span>
                                <span class="rc-status">ожидает</span>
                            </div>`;
                                minePane.insertBefore(card, minePane.firstChild);
                                updateRespBadge();
                            }
                        } else {
                            alert(d.message || 'Не удалось отправить отклик');
                        }
                    });
            }

        }); // end DOMContentLoaded
    </script>
</body>

</html>