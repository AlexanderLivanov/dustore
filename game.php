<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$db = new Database();
$pdo = $db->connect();

$game_id = $_GET['name'] ?? '';

if ($game_id <= 0) {
    header('Location: /explore');
    exit();
}

$gameController = new Game();
$game = $gameController->getGameById($game_id);

$stmt = $db->connect()->prepare("SELECT * FROM library WHERE game_id = ?");
$stmt->execute([$game_id]);
$downloaded = count($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $db->connect()->prepare("SELECT * FROM studios WHERE name = ?");
$stmt->execute([$game['studio_name']]);
$studio_payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
$stpd = $studio_payment_data;

if (!$game) {
    header('Location: /explore');
    exit();
}
if (empty($game['status']) || strtolower($game['status']) !== 'published') {
    header('Location: /explore');
    exit();
}

$userRating = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userRating = $gameController->userHasRated($game_id, $_SESSION['USERDATA']['id']) ?? 0;
}

$screenshots  = json_decode($game['screenshots'],  true) ?: [];
$features     = json_decode($game['features'],     true) ?: [];
$requirements = json_decode($game['requirements'], true) ?: [];
$achievements = json_decode($game['achievements'], true) ?: [];
$badges       = !empty($game['badges'])    ? explode(',', $game['badges'])    : [];
$platforms    = !empty($game['platforms']) ? explode(',', $game['platforms']) : [];

/* Ownership */
$isOwned      = false;
$purchasedDate = null;
if (!empty($_SESSION['USERDATA']['id'])) {
    $stmt = $pdo->prepare("SELECT 1, date FROM library WHERE player_id = ? AND game_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['USERDATA']['id'], $game_id]);
    $res = $stmt->fetch();
    $isOwned = (bool)($res['1'] ?? false);
    if ($isOwned && !empty($res['date'])) {
        $dt = new DateTime($res['date']);
        $months = [
            'January' => 'января',
            'February' => 'февраля',
            'March' => 'марта',
            'April' => 'апреля',
            'May' => 'мая',
            'June' => 'июня',
            'July' => 'июля',
            'August' => 'августа',
            'September' => 'сентября',
            'October' => 'октября',
            'November' => 'ноября',
            'December' => 'декабря'
        ];
        $purchasedDate = $dt->format('d') . ' ' . $months[$dt->format('F')] . ' ' . $dt->format('Y');
    }
}

/* User has game for reviews */
$userHasGame = false;
if (!empty($_SESSION['USERDATA']['id'])) {
    $stmt = $pdo->prepare("SELECT id FROM library WHERE player_id = ? AND game_id = ?");
    $stmt->execute([$_SESSION['USERDATA']['id'], $game_id]);
    $userHasGame = (bool)$stmt->fetch();
}

$ratingData = $gameController->getAverageRating($game_id);

$isWeb = ($platforms === ['web']) || (count($platforms) === 1 && trim($platforms[0]) === 'web');

function formatFileSize($bytes)
{
    if ($bytes < 1024)       return $bytes . ' Б';
    if ($bytes < 1048576)    return round($bytes / 1024, 2) . ' КБ';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' МБ';
    return round($bytes / 1073741824, 2) . ' ГБ';
}

$platformLabels = [
    'windows' => 'Windows',
    'linux' => 'Linux',
    'macos' => 'macOS',
    'android' => 'Android',
    'web' => 'Web'
];
$platformStr = implode(', ', array_map(
    fn($p) => $platformLabels[$p] ?? ucfirst($p),
    $platforms
));
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — <?= htmlspecialchars($game['name']) ?></title>
    <link rel="stylesheet" href="/swad/css/gamepage.css">
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <script src="/swad/js/CartManager.js"></script>
    <style>
        /* ═══════════════════════════════════════
       RESET & BASE
    ═══════════════════════════════════════ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        :root {
            --primary: #c32178;
            --primary-d: #74155d;
            --dark: #0d0118;
            --surface: rgba(255, 255, 255, .05);
            --border: rgba(255, 255, 255, .09);
            --text: #f0e6ff;
            --muted: rgba(255, 255, 255, .45);
            --success: #00ff99;
            --radius: 14px;
            --sidebar-w: 320px;
        }

        /* ═══════════════════════════════════════
       BANNER
    ═══════════════════════════════════════ */
        .gp-banner {
            width: 100%;
            height: 320px;
            background-size: cover;
            background-position: center;
            position: relative;
            flex-shrink: 0;
        }

        .gp-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 40%, var(--dark) 100%);
        }

        @media (max-width: 768px) {
            .gp-banner {
                height: 200px;
            }
        }

        /* ═══════════════════════════════════════
       LAYOUT
    ═══════════════════════════════════════ */
        .gp-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 60px;
            display: grid;
            grid-template-columns: 1fr var(--sidebar-w);
            grid-template-rows: auto 1fr;
            gap: 0 32px;
            /* Header spans full width */
            grid-template-areas:
                "header  header"
                "main    side";
        }

        @media (max-width: 900px) {
            .gp-wrap {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "header"
                    "side"
                    "main";
            }
        }

        /* ═══════════════════════════════════════
       GAME HEADER (cover + title + stats)
    ═══════════════════════════════════════ */
        .gp-header {
            grid-area: header;
            display: flex;
            gap: 24px;
            align-items: flex-end;
            padding: 28px 0 32px;
        }

        .gp-cover {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid var(--border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, .5);
        }

        .gp-title-block {
            flex: 1;
            min-width: 0;
        }

        .gp-title-block h1 {
            font-size: clamp(1.4rem, 4vw, 2.2rem);
            font-weight: 800;
            color: #fff;
            letter-spacing: -.02em;
            margin: 0 0 8px;
            line-height: 1.15;
        }

        .gp-donate-link {
            font-size: .85rem;
            color: var(--muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }

        .gp-donate-link:hover {
            color: #fff;
        }

        .gp-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }

        .gp-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            background: rgba(195, 33, 120, .15);
            border: 1px solid rgba(195, 33, 120, .3);
            color: #e88fc0;
        }

        .gp-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .gp-stat {
            text-align: center;
        }

        .gp-stat-val {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .gp-stat-lbl {
            font-size: .72rem;
            color: var(--muted);
            margin-top: 3px;
        }

        @media (max-width: 540px) {
            .gp-header {
                flex-direction: column;
                align-items: flex-start;
                padding-top: 16px;
            }

            .gp-cover {
                width: 90px;
                height: 90px;
            }
        }

        /* ═══════════════════════════════════════
       MAIN CONTENT
    ═══════════════════════════════════════ */
        .gp-main {
            grid-area: main;
            min-width: 0;
        }

        .gp-section {
            margin-bottom: 36px;
        }

        .gp-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        /* Description */
        .gp-description {
            font-size: .95rem;
            color: rgba(255, 255, 255, .7);
            line-height: 1.75;
        }

        /* Features */
        .gp-features {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .gp-feature {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
        }

        .gp-feature-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .gp-feature-title {
            font-weight: 600;
            font-size: .9rem;
            color: #fff;
            margin-bottom: 3px;
        }

        .gp-feature-desc {
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Trailer */
        .gp-trailer {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .gp-trailer iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Screenshots */
        .gp-screenshots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }

        .gp-screenshot {
            aspect-ratio: 16/9;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            cursor: zoom-in;
            border: 1px solid var(--border);
            transition: transform .2s, box-shadow .2s;
        }

        .gp-screenshot:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .4);
        }

        /* Requirements */
        .gp-requirements {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .gp-req {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px 14px;
        }

        .gp-req-label {
            font-size: .75rem;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .gp-req-value {
            font-size: .9rem;
            font-weight: 600;
            color: #fff;
        }

        /* Reviews */
        .review-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 12px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .review-author {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .review-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .review-name {
            font-weight: 600;
            font-size: .9rem;
            color: #fff;
        }

        .review-rating {
            font-size: .8rem;
            color: #fbbf24;
            margin-top: 2px;
        }

        .review-date {
            font-size: .75rem;
            color: var(--muted);
            flex-shrink: 0;
        }

        .review-text {
            font-size: .88rem;
            color: rgba(255, 255, 255, .7);
            line-height: 1.6;
        }

        .review-dev-reply {
            margin-top: 10px;
            padding: 10px 14px;
            background: rgba(116, 21, 93, .12);
            border-left: 3px solid var(--primary-d);
            border-radius: 0 8px 8px 0;
            font-size: .84rem;
            color: rgba(255, 255, 255, .65);
            line-height: 1.55;
        }

        .review-dev-badge {
            font-size: .75rem;
            font-weight: 700;
            color: #e88fc0;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 5px;
        }

        /* Review form */
        .review-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-top: 20px;
        }

        .review-form h3 {
            margin: 0 0 14px;
            font-size: 1rem;
            color: #fff;
        }

        .review-form textarea {
            width: 100%;
            background: rgba(0, 0, 0, .3);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #fff;
            padding: 10px 12px;
            font-size: .9rem;
            resize: vertical;
            min-height: 90px;
            font-family: inherit;
        }

        .review-form textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        #review-stars span {
            font-size: 22px;
            color: rgba(255, 255, 255, .2);
            cursor: pointer;
            transition: color .15s;
        }

        #review-stars span.highlighted,
        #review-stars span:hover {
            color: #fbbf24;
        }

        /* ═══════════════════════════════════════
       SIDEBAR
    ═══════════════════════════════════════ */
        .gp-side {
            grid-area: side;
        }

        .gp-side-inner {
            position: sticky;
            top: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        @media (max-width: 900px) {
            .gp-side-inner {
                position: static;
            }
        }

        /* Purchase card */
        .gp-buy-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
        }

        .gp-price-tag {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.03em;
            margin-bottom: 14px;
        }

        .gp-price-free {
            color: var(--success);
        }

        .gp-owned-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(0, 255, 153, .1);
            border: 1px solid rgba(0, 255, 153, .2);
            color: var(--success);
            font-size: .82rem;
            font-weight: 700;
            margin-bottom: 14px;
        }

        /* Buttons */
        .gp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 20px;
            border-radius: var(--radius);
            font-size: .95rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: transform .15s, box-shadow .15s, background .2s;
            font-family: inherit;
            margin-bottom: 8px;
        }

        .gp-btn:last-child {
            margin-bottom: 0;
        }

        .gp-btn:active {
            transform: scale(.97);
        }

        .gp-btn-primary {
            background: linear-gradient(135deg, #c32178, #74155d);
            color: #fff;
            box-shadow: 0 4px 20px rgba(195, 33, 120, .3);
        }

        .gp-btn-primary:hover {
            box-shadow: 0 6px 28px rgba(195, 33, 120, .5);
        }

        .gp-btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: rgba(255, 255, 255, .7);
        }

        .gp-btn-secondary:hover {
            background: var(--surface);
            color: #fff;
        }

        /* Steam row */
        .gp-steam-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }

        .steam-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            background: linear-gradient(135deg, #0f1c30, #1a5a8a);
            border: none;
            border-radius: var(--radius);
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
            font-family: inherit;
        }

        .steam-btn:hover {
            filter: brightness(1.15);
            transform: translateY(-1px);
        }

        .steam-btn.icon-only {
            flex: 0 0 44px;
            padding: 0;
        }

        .steam-wrapper {
            position: relative;
            display: flex;
        }

        .steam-tooltip {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(10, 5, 20, .95);
            border: 1px solid var(--border);
            color: rgba(255, 255, 255, .85);
            padding: 7px 12px;
            border-radius: 8px;
            font-size: .75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s, transform .15s;
            z-index: 10;
        }

        .steam-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(10, 5, 20, .95);
        }

        .steam-wrapper:hover .steam-tooltip {
            opacity: 1;
            transform: translateX(-50%) translateY(-3px);
        }

        /* Meta row */
        .gp-meta-row {
            font-size: .8rem;
            color: var(--muted);
            line-height: 1.8;
        }

        /* Dev card */
        .gp-dev-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: border-color .2s;
            text-decoration: none;
        }

        .gp-dev-card:hover {
            border-color: rgba(195, 33, 120, .4);
        }

        .gp-dev-icon {
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .gp-dev-name {
            font-weight: 700;
            font-size: .95rem;
            color: #fff;
        }

        .gp-dev-since {
            font-size: .78rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Info card */
        .gp-info-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 16px 20px;
        }

        .gp-info-card h3 {
            font-size: .85rem;
            color: var(--muted);
            margin: 0 0 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .gp-info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            font-size: .85rem;
            border-bottom: 1px solid var(--border);
        }

        .gp-info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .gp-info-label {
            color: var(--muted);
        }

        .gp-info-val {
            color: #fff;
            font-weight: 500;
            text-align: right;
        }

        /* Achievements */
        .gp-achievements {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .gp-achievement {
            background: rgba(0, 0, 0, .2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }

        .gp-achievement-icon {
            font-size: 1.6rem;
        }

        .gp-achievement-name {
            font-size: .75rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ═══════════════════════════════════════
       OFFER MODAL
    ═══════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.is-open {
            display: flex;
        }

        .modal-content {
            background: #180626;
            border: 1px solid var(--border);
            border-radius: 20px;
            max-width: 640px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 32px;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            background: var(--surface);
            border: none;
            border-radius: 50%;
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }

        .offer-content h2 {
            font-size: 1.2rem;
            margin: 0 0 20px;
            color: #fff;
        }

        .offer-content h3 {
            font-size: .9rem;
            color: var(--primary);
            margin: 18px 0 6px;
        }

        .offer-content p {
            font-size: .85rem;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 4px;
        }

        /* ═══════════════════════════════════════
       LIGHTBOX
    ═══════════════════════════════════════ */
        .lightbox {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, .92);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }

        .lightbox img {
            max-width: 92%;
            max-height: 92%;
            object-fit: contain;
            border-radius: 8px;
        }

        /* ═══════════════════════════════════════
       MISC UTILITIES
    ═══════════════════════════════════════ */
        .gp-subscription-note {
            font-size: .8rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 0 0;
        }

        .gp-no-file {
            color: #f59e0b;
            font-size: .85rem;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <!-- Banner -->
        <?php if (!empty($game['banner_url'])): ?>
            <div class="gp-banner" style="background-image: url('<?= htmlspecialchars($game['banner_url']) ?>')"></div>
        <?php endif; ?>

        <div class="gp-wrap">

            <!-- ══ HEADER ══════════════════════════════ -->
            <div class="gp-header">
                <img class="gp-cover"
                    src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/swad/static/img/hg-icon.jpg' ?>"
                    alt="<?= htmlspecialchars($game['name']) ?>">

                <div class="gp-title-block">
                    <h1><?= htmlspecialchars($game['name']) ?></h1>

                    <?php if (!empty($stpd['donate_link'])): ?>
                        <a class="gp-donate-link" href="<?= htmlspecialchars($stpd['donate_link']) ?>" target="_blank">
                            💰 Задонатить разработчику
                        </a>
                    <?php endif; ?>

                    <?php if ($badges): ?>
                        <div class="gp-badges">
                            <?php foreach ($badges as $b): ?>
                                <span class="gp-badge"><?= htmlspecialchars(trim($b)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="gp-stats">
                        <div class="gp-stat">
                            <div class="gp-stat-val"><?= htmlspecialchars($game['GQI']) ?>/100</div>
                            <div class="gp-stat-lbl">GQI</div>
                        </div>
                        <div class="gp-stat">
                            <div class="gp-stat-val"><?= date('d.m.Y', strtotime($game['release_date'])) ?></div>
                            <div class="gp-stat-lbl">Релиз</div>
                        </div>
                        <div class="gp-stat">
                            <?php if ($ratingData['count'] > 0): ?>
                                <div class="gp-stat-val"><?= $ratingData['avg'] ?>/10</div>
                                <div class="gp-stat-lbl">Оценок: <?= $ratingData['count'] ?></div>
                            <?php else: ?>
                                <div class="gp-stat-val">—</div>
                                <div class="gp-stat-lbl">Нет оценок</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ SIDEBAR ══════════════════════════════ -->
            <aside class="gp-side">
                <div class="gp-side-inner">

                    <!-- Purchase card -->
                    <div class="gp-buy-card">

                        <?php if ($game['price'] > 0 && !$isOwned): ?>
                            <!-- ПЛАТНАЯ + НЕ КУПЛЕНА -->
                            <div class="gp-price-tag"><?= number_format($game['price'], 0, ',', ' ') ?> ₽</div>
                            <button class="gp-btn gp-btn-primary" onclick="openPaymentModal()">
                                Купить за <?= number_format($game['price'], 0, ',', ' ') ?> ₽
                            </button>
                            <?php if (!empty($game['in_subscription'])): ?>
                                <p class="gp-subscription-note">✔ Включена в подписку</p>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- КУПЛЕНА или БЕСПЛАТНАЯ -->
                            <?php if ($isOwned): ?>
                                <div class="gp-owned-badge">
                                    ✔ В библиотеке<?= $purchasedDate ? ' c ' . htmlspecialchars($purchasedDate) : '' ?>
                                </div>
                            <?php else: ?>
                                <div class="gp-price-tag gp-price-free">Бесплатно</div>
                            <?php endif; ?>

                            <?php if (!empty($game['game_zip_url'])): ?>
                                <?php if ($isWeb): ?>
                                    <button class="gp-btn gp-btn-primary"
                                        onclick="location.href='/webplayer?id=<?= $game_id ?>'">
                                        ▶ Запустить в браузере
                                    </button>
                                <?php else: ?>
                                    <button class="gp-btn gp-btn-primary"
                                        onclick="location.href='/swad/controllers/download_game.php?game_id=<?= $game_id ?>'">
                                        ⬇ Скачать игру
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="gp-no-file">Файл игры пока не загружен</p>
                            <?php endif; ?>

                            <!-- Steam + Launcher -->
                            <div class="gp-steam-row">
                                <div class="steam-wrapper" style="flex:1;">
                                    <button class="steam-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M16.5 5a4.5 4.5 0 1 1-.653 8.953l-4.347 3.009v.038a3 3 0 0 1-2.824 3H8.5a3 3 0 0 1-2.94-2.402l-2.56-1.098v-3.5l3.51 1.755a2.989 2.989 0 0 1 2.834-.635l2.727-3.818A4.5 4.5 0 0 1 16.5 5z" />
                                            <path d="M15.5 9.5a1 1 0 1 0 2 0 1 1 0 0 0-2 0" fill="currentColor" />
                                        </svg>
                                        Вишлист Steam®
                                    </button>
                                    <div class="steam-tooltip">Добавить в вишлист Steam.<br>Не означает публикацию.</div>
                                </div>
                                <div class="steam-wrapper">
                                    <button class="steam-btn icon-only">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </button>
                                    <div class="steam-tooltip">Добавить в лаунчер Dustore</div>
                                </div>
                            </div>

                            <!-- Size / downloads -->
                            <?php if (!empty($game['game_zip_size'])): ?>
                                <div class="gp-meta-row">
                                    <?php if ($isWeb): ?>
                                        Веб-игра — скачивание не требуется
                                    <?php else: ?>
                                        Размер: <?= formatFileSize((int)$game['game_zip_size']) ?><br>
                                        <?= $game['price'] > 0 ? 'Купили' : 'Скачали' ?>: <?= $downloaded ?> раз(а)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>

                    <!-- Developer card -->
                    <a class="gp-dev-card" href="/d/<?= htmlspecialchars($game['studio_slug']) ?>">
                        <div class="gp-dev-icon">🏢</div>
                        <div>
                            <div class="gp-dev-name"><?= htmlspecialchars($game['studio_name']) ?></div>
                            <div class="gp-dev-since">Основана в <?= date('Y', strtotime($game['studio_founded'])) ?></div>
                        </div>
                    </a>

                    <button class="gp-btn gp-btn-secondary"
                        onclick="location.href='/d/<?= htmlspecialchars($game['studio_slug']) ?>'">
                        Все игры разработчика
                    </button>

                    <button class="gp-btn gp-btn-secondary" onclick="offerModal.classList.add('is-open')">
                        Оферта разработчика
                    </button>

                    <!-- Game info -->
                    <div class="gp-info-card">
                        <h3>Об игре</h3>
                        <div class="gp-info-row">
                            <span class="gp-info-label">Жанр</span>
                            <span class="gp-info-val"><?= htmlspecialchars($game['genre']) ?></span>
                        </div>
                        <div class="gp-info-row">
                            <span class="gp-info-label">Платформы</span>
                            <span class="gp-info-val"><?= htmlspecialchars($platformStr) ?></span>
                        </div>
                        <div class="gp-info-row">
                            <span class="gp-info-label">Языки</span>
                            <span class="gp-info-val"><?= htmlspecialchars($game['languages']) ?></span>
                        </div>
                        <div class="gp-info-row">
                            <span class="gp-info-label">Возраст</span>
                            <span class="gp-info-val"><?= htmlspecialchars($game['age_rating']) ?></span>
                        </div>
                    </div>

                    <!-- Achievements -->
                    <?php if (!empty($achievements)): ?>
                        <div class="gp-info-card">
                            <h3>Достижения</h3>
                            <div class="gp-achievements">
                                <?php foreach ($achievements as $ach): ?>
                                    <div class="gp-achievement">
                                        <div class="gp-achievement-icon"><?= htmlspecialchars($ach['icon']) ?></div>
                                        <div class="gp-achievement-name"><?= htmlspecialchars($ach['title']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </aside>

            <!-- ══ MAIN CONTENT ══════════════════════════ -->
            <div class="gp-main">

                <!-- Description -->
                <div class="gp-section">
                    <div class="gp-description">
                        <?= nl2br(htmlspecialchars($game['description'])) ?>
                    </div>
                </div>

                <!-- Features -->
                <?php if (!empty($features)): ?>
                    <div class="gp-section">
                        <h2 class="gp-section-title">Особенности</h2>
                        <div class="gp-features">
                            <?php foreach ($features as $f): ?>
                                <div class="gp-feature">
                                    <div class="gp-feature-icon"><?= htmlspecialchars($f['icon']) ?></div>
                                    <div>
                                        <div class="gp-feature-title"><?= htmlspecialchars($f['title']) ?></div>
                                        <div class="gp-feature-desc"><?= htmlspecialchars($f['description']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Trailer -->
                <?php if (!empty($game['trailer_url'])): ?>
                    <div class="gp-section">
                        <h2 class="gp-section-title">Трейлер</h2>
                        <div class="gp-trailer">
                            <iframe src="<?= htmlspecialchars($game['trailer_url']) ?>"
                                allowfullscreen allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Screenshots -->
                <?php if (!empty($screenshots)): ?>
                    <div class="gp-section">
                        <h2 class="gp-section-title">Скриншоты</h2>
                        <div class="gp-screenshots">
                            <?php foreach ($screenshots as $s): ?>
                                <div class="gp-screenshot"
                                    style="background-image: url('<?= htmlspecialchars($s['path']) ?>')"
                                    data-fullsize="<?= htmlspecialchars($s['path']) ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System requirements -->
                <?php if (!empty($requirements)): ?>
                    <div class="gp-section">
                        <h2 class="gp-section-title">Системные требования</h2>
                        <div class="gp-requirements">
                            <?php foreach ($requirements as $r): ?>
                                <div class="gp-req">
                                    <div class="gp-req-label"><?= htmlspecialchars($r['label']) ?></div>
                                    <div class="gp-req-value"><?= htmlspecialchars($r['value']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reviews -->
                <div class="gp-section">
                    <h2 class="gp-section-title">Отзывы игроков</h2>
                    <div id="reviews-container">
                        <p style="color:var(--muted); font-size:.9rem;">Загрузка отзывов…</p>
                    </div>

                    <?php if (!empty($_SESSION['USERDATA']['id']) && $userHasGame): ?>
                        <div class="review-form">
                            <h3>Оставить отзыв</h3>
                            <textarea id="review-text" placeholder="Напишите ваш отзыв…"></textarea>
                            <div style="margin:10px 0 14px; display:flex; align-items:center; gap:8px;">
                                <span style="font-size:.85rem; color:var(--muted);">Оценка:</span>
                                <div id="review-stars"></div>
                            </div>
                            <button class="gp-btn gp-btn-primary" id="submit-review" style="width:auto; padding: 10px 24px;">
                                Отправить
                            </button>
                        </div>
                    <?php elseif (!empty($_SESSION['USERDATA']['id'])): ?>
                        <p style="color:#f59e0b; font-size:.85rem; margin-top:12px;">
                            Скачайте или купите игру, чтобы оставить отзыв.
                        </p>
                    <?php else: ?>
                        <p style="color:#f59e0b; font-size:.85rem; margin-top:12px;">
                            <a href="/login" style="color:#e88fc0;">Войдите</a>, чтобы оставить отзыв.
                        </p>
                    <?php endif; ?>
                </div>

            </div><!-- /.gp-main -->

        </div><!-- /.gp-wrap -->
    </main>

    <!-- ══ OFFER MODAL ══════════════════════════ -->
    <div id="offerModal" class="modal" onclick="if(event.target===this)this.classList.remove('is-open')">
        <div class="modal-content">
            <button class="modal-close" onclick="offerModal.classList.remove('is-open')">✕</button>
            <div class="offer-content">
                <h2>Публичная оферта</h2>
                <p><strong>г. <?= htmlspecialchars($stpd['city'] ?? 'Москва') ?></strong> &nbsp;
                    <?= date('d.m.Y', strtotime($game['offer_date'] ?? 'now')) ?></p>
                <p><strong><?= htmlspecialchars($stpd['name'] ?? '') ?></strong></p>
                <p>ИНН: <?= htmlspecialchars($stpd['INN'] ?? '—') ?></p>
                <p>Расчётный счёт: <?= htmlspecialchars($stpd['acc_num'] ?? '—') ?></p>
                <p>Банк: <?= htmlspecialchars($stpd['bank_name'] ?? '—') ?>, БИК: <?= htmlspecialchars($stpd['BIC'] ?? '—') ?></p>

                <h3>1. Предмет оферты</h3>
                <p><?= htmlspecialchars($game['studio_name']) ?> предлагает заключить договор купли-продажи цифрового товара — игры «<?= htmlspecialchars($game['name']) ?>».</p>

                <h3>2. Момент заключения договора</h3>
                <p>Акцептом оферты является совершение платежа за Товар.</p>

                <h3>3. Цена и расчёты</h3>
                <p>Цена указана на странице товара. Расчёты через ЮКасса.</p>

                <h3>4. Передача товара</h3>
                <p>Доступ к скачиванию предоставляется сразу после оплаты.</p>

                <h3>5. Возврат</h3>
                <p>Цифровые товары надлежащего качества возврату не подлежат (ст. 26.1 ЗоЗПП).</p>

                <h3>6. Реквизиты продавца</h3>
                <p><?= htmlspecialchars($game['studio_name']) ?>, ИНН: <?= htmlspecialchars($stpd['INN'] ?? '—') ?></p>
                <p>Email: <?= htmlspecialchars($stpd['contact_email'] ?? '—') ?></p>
            </div>
        </div>
    </div>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <?php if ($game['price'] > 0 && !$isOwned): ?>
        <?php require_once('finv2/payment_modal.php'); ?>
    <?php endif; ?>

    <script>
        /* ── Lightbox ── */
        document.querySelectorAll('.gp-screenshot').forEach(el => {
            el.addEventListener('click', () => {
                const lb = document.createElement('div');
                lb.className = 'lightbox';
                lb.innerHTML = `<img src="${el.dataset.fullsize}" alt="">`;
                lb.addEventListener('click', () => lb.remove());
                document.body.appendChild(lb);
            });
        });

        /* ── Reviews ── */
        (function() {
            const gameId = <?= (int)$game_id ?>;
            const userId = <?= (int)($_SESSION['USERDATA']['id'] ?? 0) ?>;
            const container = document.getElementById('reviews-container');
            if (!container) return;

            function esc(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            fetch(`/swad/controllers/get_reviews.php?game_id=${gameId}`, {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = '<p style="color:var(--muted)">Не удалось загрузить отзывы.</p>';
                        return;
                    }
                    const reviews = Array.isArray(data.reviews) ? data.reviews : [];
                    if (!reviews.length) {
                        container.innerHTML = '<p style="color:var(--muted); font-size:.9rem;">Отзывов пока нет. Будьте первым!</p>';
                    } else {
                        container.innerHTML = '';
                        reviews.forEach(r => {
                            const div = document.createElement('div');
                            div.className = 'review-card';
                            div.innerHTML = `
                        <div class="review-header">
                            <div class="review-author">
                                <img class="review-avatar" src="${esc(r.profile_picture) || '/swad/static/img/logo.svg'}" alt="">
                                <div>
                                    <div class="review-name">${esc(r.username || 'Аноним')}</div>
                                    <div class="review-rating">${'★'.repeat(Math.round(r.rating/2))} ${r.rating}/10</div>
                                </div>
                            </div>
                            <div class="review-date">${new Date(r.created_at).toLocaleDateString('ru-RU')}</div>
                        </div>
                        <div class="review-text">${esc(r.text)}</div>
                        ${r.developer_reply ? `
                            <div class="review-dev-reply">
                                <div class="review-dev-badge">✔ Ответ разработчика</div>
                                ${esc(r.developer_reply)}
                            </div>` : ''}
                    `;
                            container.appendChild(div);
                        });
                    }

                    /* Pre-fill own review */
                    const myReview = reviews.find(r => r.user_id == userId);
                    if (myReview) {
                        const ta = document.getElementById('review-text');
                        if (ta) ta.value = myReview.text;

                        const form = document.querySelector('.review-form');
                        if (form && !document.getElementById('review-id')) {
                            const inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.id = 'review-id';
                            inp.value = myReview.id;
                            form.appendChild(inp);
                        }
                        initStars(myReview.rating);
                    } else {
                        initStars(10);
                    }
                })
                .catch(() => {
                    container.innerHTML = '<p style="color:var(--muted)">Ошибка загрузки отзывов.</p>';
                });

            /* Stars */
            let selectedRating = 10;

            function initStars(initial) {
                selectedRating = initial;
                const wrap = document.getElementById('review-stars');
                if (!wrap) return;
                wrap.innerHTML = '';
                for (let i = 1; i <= 10; i++) {
                    const s = document.createElement('span');
                    s.textContent = '★';
                    s.addEventListener('mouseover', () => highlight(i));
                    s.addEventListener('mouseout', () => highlight(selectedRating));
                    s.addEventListener('click', () => {
                        selectedRating = i;
                        highlight(i);
                    });
                    wrap.appendChild(s);
                }
                highlight(selectedRating);
            }

            function highlight(n) {
                document.querySelectorAll('#review-stars span').forEach((s, i) =>
                    s.classList.toggle('highlighted', i < n));
            }

            /* Submit */
            document.addEventListener('click', e => {
                if (e.target.id !== 'submit-review') return;
                const text = document.getElementById('review-text')?.value.trim();
                if (!text) {
                    alert('Введите текст отзыва');
                    return;
                }
                const rid = document.getElementById('review-id');
                const url = rid?.value ? '/swad/controllers/update_review.php' : '/swad/controllers/submit_review.php';
                const body = rid?.value ?
                    `review_id=${encodeURIComponent(rid.value)}&rating=${selectedRating}&text=${encodeURIComponent(text)}` :
                    `game_id=${gameId}&rating=${selectedRating}&text=${encodeURIComponent(text)}`;
                fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) location.reload();
                        else alert('Ошибка: ' + (d.error || d.message));
                    })
                    .catch(console.error);
            });
        })();

        /**
         * HidL Launcher Button
         * Вставить в g/game.php перед закрывающим </body>
         *
         * Кнопка "Добавить в лаунчер Dustore" (steam-btn icon-only):
         *   1. Проверяет что HidL запущен (GET /ping)
         *   2. Отправляет POST /install с данными игры
         *   3. Показывает статус прямо в тултипе кнопки
         */
        (function() {
            // ── Данные игры из PHP ─────────────────────────────────────────────────
            const GAME_ID = <?= (int)$game_id ?>;
            const GAME_NAME = <?= json_encode($game['name']) ?>;
            const ZIP_URL = <?= json_encode($game['game_zip_url'] ?? '') ?>;

            const HIDL_PORT = 9000;
            const HIDL_BASE = `http://127.0.0.1:${HIDL_PORT}`;

            // ── Найти кнопку "Добавить в лаунчер" ────────────────────────────────
            // Ищем по тексту тултипа (см. steam-tooltip "Добавить в лаунчер Dustore")
            const allWrappers = document.querySelectorAll('.steam-wrapper');
            let launcherWrapper = null;
            allWrappers.forEach(w => {
                const tip = w.querySelector('.steam-tooltip');
                if (tip && tip.textContent.includes('лаунчер Dustore')) {
                    launcherWrapper = w;
                }
            });
            if (!launcherWrapper) return;

            const btn = launcherWrapper.querySelector('button');
            const tooltip = launcherWrapper.querySelector('.steam-tooltip');
            if (!btn || !tooltip) return;

            // ── Состояния кнопки ──────────────────────────────────────────────────
            function setState(state) {
                btn.disabled = false;
                btn.style.filter = '';
                switch (state) {
                    case 'checking':
                        tooltip.textContent = '🔄 Проверяем HidL...';
                        btn.style.filter = 'brightness(0.7)';
                        btn.disabled = true;
                        break;
                    case 'no_launcher':
                        tooltip.innerHTML = '❌ HidL не запущен.<br>Откройте HidL и попробуйте снова.';
                        btn.style.filter = 'brightness(0.5)';
                        break;
                    case 'no_file':
                        tooltip.textContent = '⚠️ У игры нет файла для скачивания';
                        btn.style.filter = 'brightness(0.5)';
                        btn.disabled = true;
                        break;
                    case 'sending':
                        tooltip.textContent = '📤 Отправляем в HidL...';
                        btn.disabled = true;
                        break;
                    case 'success':
                        tooltip.textContent = '✅ Загрузка началась!';
                        btn.style.background = 'linear-gradient(135deg, #006630, #00a050)';
                        break;
                    case 'error':
                        tooltip.textContent = '❌ Ошибка — смотрите окно HidL';
                        break;
                    case 'ready':
                    default:
                        tooltip.textContent = 'Добавить в лаунчер Dustore';
                        break;
                }
            }

            // ── Проверка + загрузка ────────────────────────────────────────────────
            async function install() {
                if (!ZIP_URL) {
                    setState('no_file');
                    return;
                }

                setState('checking');

                // 1. Проверяем что лаунчер запущен
                try {
                    const ping = await fetch(`${HIDL_BASE}/ping`, {
                        mode: 'cors',
                        signal: AbortSignal.timeout(2000),
                    });
                    if (!ping.ok) throw new Error('ping failed');
                } catch {
                    setState('no_launcher');
                    return;
                }

                setState('sending');

                // 2. Отправляем задание на загрузку
                try {
                    const res = await fetch(`${HIDL_BASE}/install`, {
                        method: 'POST',
                        mode: 'cors',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            game_id: GAME_ID,
                            game_name: GAME_NAME,
                            zip_url: ZIP_URL,
                        }),
                    });

                    const data = await res.json();

                    if (data.ok) {
                        setState('success');
                        // Через 4 секунды возвращаем в исходное состояние
                        setTimeout(() => setState('ready'), 4000);
                    } else {
                        setState('error');
                        tooltip.textContent = `❌ ${data.message || 'Ошибка'}`;
                    }
                } catch (e) {
                    setState('error');
                    console.error('[HidL] install error:', e);
                }
            }

            // ── Вешаем обработчик ─────────────────────────────────────────────────
            btn.addEventListener('click', install);
            setState('ready');
        })();
    </script>
</body>

</html>