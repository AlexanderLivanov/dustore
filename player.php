<?php
// (c) 11.12.2025 Alexander Livanov
require_once('swad/config.php');
require_once('swad/controllers/user.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/get_user_activity.php');
require_once('swad/controllers/organization.php');

session_start();

$request_uri = $_SERVER['REQUEST_URI'];
$pattern = '/\/player\/([a-zA-Z0-9_]+)/';

if (preg_match($pattern, $request_uri, $matches)) {
    $username = $matches[1];
} else {
    $path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    if (count($path_parts) >= 2 && $path_parts[0] == 'player') {
        $username = $path_parts[1];
    } else {
        header("HTTP/1.0 404 Not Found");
        die("Пользователь не найден");
    }
}

if (empty($username)) {
    header("HTTP/1.0 404 Not Found");
    die("Пользователь не найден");
}

$database = new Database();
$pdo = $database->connect();

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.telegram_id, u.profile_picture, u.added,
        u.telegram_username, u.city, u.country, u.vk, u.website,
        u.first_name, u.last_name,
        COUNT(DISTINCT g.id) as games_count,
        COUNT(DISTINCT r.id) as reviews_count
    FROM users u
    LEFT JOIN games g ON g.developer = u.id
    LEFT JOIN game_reviews r ON r.user_id = u.id
    WHERE u.username = :username
    GROUP BY u.id
");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    header("HTTP/1.0 404 Not Found");
    die("Пользователь не найден");
}

$is_owner = false;
if (!empty($_SESSION['USERDATA']['id'])) {
    $is_owner = ((int)$_SESSION['USERDATA']['id'] == (int)$user['id']);
}

$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM library WHERE player_id = :user_id) as library_count,
        (SELECT COUNT(*) FROM achievements WHERE player_id = :user_id) as achievements_count,
        (SELECT COUNT(*) FROM game_reviews WHERE user_id = :user_id) as reviews_count,
        (SELECT COUNT(*) FROM friends WHERE 
            (player_id = :user_id OR friend_id = :user_id) AND status = 'accepted') as friends_count
");
$stmt->execute([':user_id' => $user['id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT 
        g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date,
        COALESCE(AVG(r.rating), 0) AS rating,
        MAX(l.date) AS last_added
    FROM library l
    JOIN games g ON g.id = l.game_id
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE l.player_id = :user_id AND l.purchased = 1
    GROUP BY g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date
    ORDER BY last_added DESC
    LIMIT 50
");
$stmt->execute([':user_id' => $user['id']]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT r.*, g.name as game_title, g.path_to_cover as game_cover
    FROM game_reviews r
    JOIN games g ON g.id = r.game_id
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([':user_id' => $user['id']]);
$reviews = $stmt->fetchAll();

$stmt_items = $pdo->prepare("
    SELECT * FROM library 
    WHERE player_id = :user_id 
    ORDER BY rarity DESC, date DESC
");
$stmt_items->execute([':user_id' => $user['id']]);
$all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT gub.*, b.name, b.description, b.icon_url
    FROM given_user_badges gub
    JOIN badges b ON gub.badge_id = b.id
    WHERE gub.user_id = :user_id
    ORDER BY gub.awarded_at DESC
");
$stmt->execute([':user_id' => $user['id']]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$games_collection = [];
$collectibles = [];

foreach ($all_items as $item) {
    if (!empty($item['game_id']) && $item['game_id'] > 0) {
        $stmt_game_info = $pdo->prepare("SELECT name, description, path_to_cover, price FROM games WHERE id = :game_id");
        $stmt_game_info->execute([':game_id' => $item['game_id']]);
        $game_info = $stmt_game_info->fetch(PDO::FETCH_ASSOC);
        if ($game_info) {
            $item['title']       = $game_info['name'];
            $item['description'] = $game_info['description'];
            $item['cover_image'] = $game_info['path_to_cover'];
            $item['price']       = $game_info['price'];
            $item['item_type']   = 'game';
            $games_collection[]  = $item;
        }
    } else {
        $item['item_type']   = 'collectible';
        $item['title']       = $item['title'] ?? 'Коллекционный предмет #' . $item['id'];
        $item['description'] = $item['description'] ?? 'Особый коллекционный предмет';
        $collectibles[]      = $item;
    }
}

// Список друзей
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.first_name, u.last_name, u.profile_picture
    FROM friends f
    JOIN users u ON u.id = CASE WHEN f.player_id = :user_id THEN f.friend_id ELSE f.player_id END
    WHERE (f.player_id = :user_id OR f.friend_id = :user_id) AND f.status = 'accepted'
    ORDER BY u.first_name ASC
");
$stmt->execute([':user_id' => $user['id']]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($is_owner) {
    $curr_user = new User();
    $org       = new Organization();
    $user_data = $_SESSION['USERDATA'];
    $userID    = $user['id'];
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?= htmlspecialchars($user['username']) ?> | Dustore</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="/swad/css/player.css">
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <section class="profile-header">
        <div class="container" style="display: flex; gap: 30px; flex-wrap: wrap;">

            <!-- ══ ЛЕВАЯ КОЛОНКА ══ -->
            <div class="profile-left" style="flex: 0 0 300px;">
                <div class="user-info">
                    <div class="avatar-wrapper">
                        <div class="avatar-frame">
                            <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '/swad/static/img/logo.svg' ?>"
                                alt="Аватар" class="user-avatar">
                        </div>
                        <a href="/me" class="edit-profile-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                            </svg>
                        </a>
                    </div>

                    <?php
                    $status = null;
                    if (!empty($_SESSION['USERDATA']['id']) && !$is_owner) {
                        $u      = new User();
                        $status = $u->getFriendStatus($_SESSION['USERDATA']['id'], $user['id']);
                    }
                    ?>

                    <?php if ($is_owner): ?>
                        <?php
                        $incomingRequests = [];
                        $stmt = $pdo->prepare("
                            SELECT f.id, u.id as user_id, u.username, u.first_name, u.last_name, u.profile_picture
                            FROM friends f
                            JOIN users u ON u.id = f.player_id
                            WHERE f.friend_id = :me AND f.status = 'pending'
                            ORDER BY f.id DESC
                        ");
                        $stmt->execute([':me' => $user['id']]);
                        $incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if (!empty($incomingRequests)): ?>
                            <div class="profile-card" style="border: 1px solid #c32178;">
                                <h3>Входящие заявки в друзья</h3>
                                <?php foreach ($incomingRequests as $req): ?>
                                    <div style="display:flex;align-items:center;gap:12px;margin:10px 0;background:rgba(255,255,255,.05);padding:10px;border-radius:10px;">
                                        <img src="<?= $req['profile_picture'] ?: '/swad/static/img/logo.svg' ?>"
                                            style="width:40px;height:40px;border-radius:50%">
                                        <div style="flex:1">
                                            <a href="/player/<?= htmlspecialchars($req['username']) ?>" style="color:white;text-decoration:none">
                                                <?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?>
                                                (@<?= htmlspecialchars($req['username']) ?>)
                                            </a>
                                        </div>
                                        <button class="acceptFriend" data-user="<?= $req['user_id'] ?>"
                                            style="background:#26072d;color:white;border:none;padding:6px 12px;border-radius:8px;cursor:pointer">
                                            Принять
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif (!empty($_SESSION['USERDATA']['id'])): ?>
                        <?php
                        $btnText   = 'Добавить в друзья';
                        $btnAction = 'send';
                        $disabled  = '';
                        if ($status) {
                            if ($status['status'] === 'pending') {
                                if ($status['player_id'] == $_SESSION['USERDATA']['id']) {
                                    $btnText  = 'Заявка отправлена';
                                    $disabled = 'disabled';
                                } else {
                                    $btnText   = 'Принять заявку';
                                    $btnAction = 'accept';
                                }
                            }
                            if ($status['status'] === 'accepted') {
                                $btnText  = 'В друзьях';
                                $disabled = 'disabled';
                            }
                        }
                        ?>
<<<<<<< HEAD
                        <a href="#" id="friendBtn" class="edit-profile-btn"
                            data-user="<?= $user['id'] ?>" data-action="<?= $btnAction ?>" <?= $disabled ?>>
                            <svg style="vertical-align:middle" xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M13.666 1.429l6.75 3.98l.096 .063l.093 .078l.106 .074a3.22 3.22 0 0 1 1.284 2.39l.005 .204v7.284c0 1.175 -.643 2.256 -1.623 2.793l-6.804 4.302c-.98 .538 -2.166 .538 -3.2 -.032l-6.695 -4.237a3.23 3.23 0 0 1 -1.678 -2.826v-7.285c0 -1.106 .57 -2.128 1.476 -2.705l6.95 -4.098c1 -.552 2.214 -.552 3.24 .015m-1.666 6.571a1 1 0 0 0 -1 1v2h-2a1 1 0 0 0 -.993 .883l-.007 .117a1 1 0 0 0 1 1h2v2a1 1 0 0 0 .883 .993l.117 .007a1 1 0 0 0 1 -1v-2h2a1 1 0 0 0 .993 -.883l.007 -.117a1 1 0 0 0 -1 -1h-2v-2a1 1 0 0 0 -.883 -.993z" />
                            </svg>
                            <span id="friendBtnText"><?= $btnText ?></span>
                        </a>
=======

>>>>>>> 33a8665d34fc503e2ecab96a9008457c467bbab1
                    <?php endif; ?>

                    <!-- Достижения (иконки) -->
                    <div class="achievements-container">
                        <?php foreach ($achievements as $ach): ?>
                            <?php $title = htmlspecialchars($ach['name']); ?>
                            <div class="achievement-icon" title="<?= $title ?>"
                                onclick='showAchievementModal({
                                     title: <?= json_encode($title) ?>,
                                     description: <?= json_encode($ach["description"] ?? "") ?>,
                                     icon: <?= json_encode($ach["icon_url"] ?? "🏆") ?>,
                                     date: <?= json_encode($ach["awarded_at"] ?? "") ?>
                                 })'>
                                <?php if (!empty($ach["icon_url"])): ?>
                                    <img src="<?= htmlspecialchars($ach["icon_url"]) ?>" alt="<?= $title ?>" class="achievement-icon-img">
                                    <?php else: ?>🏆<?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Кнопки вкладок -->
                <div class="tabs">
                    <button class="tab-button active" onclick="switchTab('games')">Коллекция</button>
                    <button class="tab-button" onclick="switchTab('profile')">Профиль</button>
                    <button class="tab-button" onclick="switchTab('collection')">Друзья</button>
                    <button class="tab-button" onclick="switchTab('developer')">Безопасность</button>
                </div>
            </div>

            <!-- ══ ПРАВАЯ КОЛОНКА ══ -->
            <div class="profile-right" style="flex: 1; min-width: 280px; color: white;">
                <div class="profile-name-row">
                    <div>
                        <?php
                        $status_text = '<small style="color:red;font-size:15px;">● Не в сети</small>';
                        if (!empty($_SESSION['USERDATA']['id'])) {
                            $stmt = $pdo->prepare("SELECT current_app, last_seen FROM user_activity WHERE user_id = ? ORDER BY last_seen DESC LIMIT 1");
                            $stmt->execute([$user['id']]);
                            $activity = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($activity && strtotime($activity['last_seen']) + 180 > time()) {
                                $app = htmlspecialchars($activity['current_app'] ?? '');
                                $status_text = $app
                                    ? '<small style="color:lightgreen;font-size:15px;">● В сети (Играет в ' . $app . ')</small>'
                                    : '<small style="color:lightgreen;font-size:15px;">● В сети</small>';
                            }
                        }
                        ?>
                        <h1><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> <?= $status_text ?></h1>
                        <p>
                            <light>На платформе с:</light> <?= date('d.m.Y', strtotime($user['added'])) ?>
                        </p>
                    </div>

                    <?php if (!$is_owner && !empty($_SESSION['USERDATA']['id'])): ?>
                        <?php
                        $btnText     = 'Добавить в друзья';
                        $btnAction   = 'send';
                        $btnDisabled = '';
                        $btnClass    = 'friend-action-btn';
                        if ($status) {
                            if ($status['status'] === 'pending') {
                                if ($status['player_id'] == $_SESSION['USERDATA']['id']) {
                                    $btnText   = 'Отменить заявку';
                                    $btnAction = 'cancel';
                                    $btnClass  = 'friend-action-btn friend-action-btn--muted';
                                } else {
                                    $btnText   = 'Принять заявку';
                                    $btnAction = 'accept';
                                    $btnClass  = 'friend-action-btn friend-action-btn--accept';
                                }
                            }
                            if ($status['status'] === 'accepted') {
                                $btnText   = 'Завершить дружбу';
                                $btnAction = 'remove';
                                $btnClass  = 'friend-action-btn friend-action-btn--remove';
                            }
                        }
                        ?>
                        <button id="friendActionBtn" class="<?= $btnClass ?>"
                            data-user="<?= $user['id'] ?>" data-action="<?= $btnAction ?>" <?= $btnDisabled ?>>
                            <span id="friendActionBtnText"><?= $btnText ?></span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php
                $games_main   = array_slice($games, 0, 6);
                $reviews_main = array_slice($reviews, 0, 3);
                ?>

                <!-- ── Вкладка "Профиль" ── -->
                <div id="tab-profile" class="tab-content">
                    <div class="profile-info-card">
                        <div class="pf-wrap">
                            <!-- Статистика -->
                            <div class="pf-stats-row">
                                <div class="pf-stat">
                                    <span class="pf-stat-num"><?= (int)$stats['library_count'] ?></span>
                                    <span class="pf-stat-lbl">игр</span>
                                </div>
                                <div class="pf-stat-div"></div>
                                <div class="pf-stat">
                                    <span class="pf-stat-num"><?= (int)$stats['friends_count'] ?></span>
                                    <span class="pf-stat-lbl">друзей</span>
                                </div>
                                <div class="pf-stat-div"></div>
                                <div class="pf-stat">
                                    <span class="pf-stat-num"><?= (int)$stats['reviews_count'] ?></span>
                                    <span class="pf-stat-lbl">отзывов</span>
                                </div>
                                <div class="pf-stat-div"></div>
                                <div class="pf-stat">
                                    <span class="pf-stat-num"><?= (int)$stats['achievements_count'] ?></span>
                                    <span class="pf-stat-lbl">достижений</span>
                                </div>
                                <div class="pf-meta">
                                    <?php if (!empty($user['city']) || !empty($user['country'])): ?>
                                        <span class="pf-meta-item">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z" />
                                                <circle cx="12" cy="9" r="2.5" />
                                            </svg>
                                            <?= htmlspecialchars(implode(', ', array_filter([$user['city'], $user['country']]))) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="pf-meta-item">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10" />
                                            <polyline points="12 6 12 12 16 14" />
                                        </svg>
                                        <?= time_ago(getUserLastActivity($user['telegram_id'])) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Соцсети -->
                            <?php if (!empty($user['website']) || !empty($user['vk']) || !empty($user['telegram_username'])): ?>
                                <div class="pf-socials">
                                    <?php if (!empty($user['website'])): ?>
                                        <a href="<?= htmlspecialchars($user['website']) ?>" class="pf-social-btn" target="_blank">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10" />
                                                <line x1="2" y1="12" x2="22" y2="12" />
                                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                            </svg>
                                            Сайт
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($user['vk'])): ?>
                                        <a href="<?= htmlspecialchars($user['vk']) ?>" class="pf-social-btn" target="_blank">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M15.07 2H8.93C3.33 2 2 3.33 2 8.93v6.14C2 20.67 3.33 22 8.93 22h6.14C20.67 22 22 20.67 22 15.07V8.93C22 3.33 20.67 2 15.07 2zm3.08 13.27h-1.56c-.59 0-.77-.47-1.83-1.54-.92-.9-1.33-.9-1.56-.9-.32 0-.41.09-.41.54v1.4c0 .38-.12.61-1.14.61-1.68 0-3.54-1.02-4.85-2.91C5.61 10.3 5 8.42 5 7.97c0-.23.09-.45.54-.45h1.56c.4 0 .56.18.72.63.79 2.29 2.12 4.3 2.67 4.3.2 0 .29-.09.29-.59V9.63c-.06-1.06-.62-1.15-.62-1.53 0-.18.15-.36.38-.36h2.45c.34 0 .45.18.45.56v3c0 .34.15.45.25.45.2 0 .38-.11.76-.49 1.17-1.31 2.01-3.33 2.01-3.33.11-.23.29-.45.69-.45h1.56c.47 0 .58.24.47.56-.2.92-2.12 3.63-2.12 3.63-.17.27-.23.38 0 .68.17.23.72.7 1.08 1.12.67.76 1.18 1.4 1.32 1.84.14.43-.09.65-.52.65z" />
                                            </svg>
                                            ВКонтакте
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($user['telegram_username'])): ?>
                                        <a href="https://t.me/<?= htmlspecialchars($user['telegram_username']) ?>" class="pf-social-btn" target="_blank">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.68 7.92c-.12.56-.46.7-.93.43l-2.58-1.9-1.24 1.2c-.14.13-.26.25-.53.25l.19-2.67 4.84-4.37c.21-.19-.05-.29-.32-.1L7.54 14.44l-2.52-.79c-.55-.17-.56-.55.11-.81l9.85-3.8c.46-.17.86.11.66.76z" />
                                            </svg>
                                            Telegram
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Последние игры -->
                            <?php if (!empty($games_main)): ?>
                                <div class="pf-section">
                                    <div class="pf-section-head">
                                        <span class="pf-section-title">Последние игры</span>
                                        <?php if (count($games) > 6): ?>
                                            <button onclick="switchTab('games')" class="pf-link-btn">Все игры →</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pf-games-scroll">
                                        <?php foreach ($games_main as $game): ?>
                                            <a href="/g/<?= $game['id'] ?>" class="pf-game-thumb">
                                                <div class="pf-game-cover">
                                                    <img src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/assets/default-game-cover.png' ?>" alt="">
                                                    <div class="pf-game-overlay">
                                                        <span class="pf-game-rating">★ <?= number_format($game['rating'], 1) ?></span>
                                                    </div>
                                                </div>
                                                <span class="pf-game-name"><?= htmlspecialchars(mb_strimwidth($game['name'], 0, 18, '…')) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Последние отзывы -->
                            <?php if (!empty($reviews_main)): ?>
                                <div class="pf-section">
                                    <div class="pf-section-head">
                                        <span class="pf-section-title">Последние отзывы</span>
                                        <?php if (count($reviews) > 3): ?>
                                            <button onclick="switchTab('reviews')" class="pf-link-btn">Все отзывы →</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pf-reviews">
                                        <?php foreach ($reviews_main as $review): ?>
                                            <a href="/g/<?= $review['game_id'] ?>" class="pf-review">
                                                <img src="<?= !empty($review['game_cover']) ? htmlspecialchars($review['game_cover']) : '/assets/default-game-cover.png' ?>" class="pf-review-cover" alt="">
                                                <div class="pf-review-body">
                                                    <div class="pf-review-top">
                                                        <span class="pf-review-game"><?= htmlspecialchars($review['game_title']) ?></span>
                                                        <span class="pf-review-score">★ <?= $review['rating'] ?>/10</span>
                                                    </div>
                                                    <p class="pf-review-text"><?= htmlspecialchars(mb_strimwidth($review['text'], 0, 120, '…')) ?></p>
                                                    <span class="pf-review-date"><?= date('d.m.Y', strtotime($review['created_at'])) ?></span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div><!-- /.pf-wrap -->
                    </div><!-- /.profile-info-card -->
                </div>

                <!-- ── Вкладка "Коллекция (Игры)" ── -->
                <div id="tab-games" class="tab-content active">
                    <?php
                    $displayGames = $games;
                    $total = count($games);
                    ?>
                    <div class="profile-card games-profile-card">
                        <div class="games-section-header">
                            <div class="showcase-tabs" id="showcaseTabsGlobal">
                                <button class="showcase-tab active" data-showcase="games">Игры</button>
                                <button class="showcase-tab" data-showcase="achievements">Достижения</button>
                            </div>
                            <div class="games-view-icons">
                                <button class="games-view-icon-btn" data-view="showcase" title="Витрина (сетка)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7" />
                                        <rect x="14" y="3" width="7" height="7" />
                                        <rect x="3" y="14" width="7" height="7" />
                                        <rect x="14" y="14" width="7" height="7" />
                                    </svg>
                                </button>
                                <button class="games-view-icon-btn active" data-view="carousel" title="Карусель (список)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="18" />
                                        <rect x="14" y="3" width="7" height="18" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Режим "Карусель" -->
                        <div class="carousel-3d-container" id="carouselView" style="display: block;">
                            <button class="carousel-3d-btn prev" aria-label="Предыдущие игры">‹</button>
                            <div class="carousel-3d-stage" id="carouselStage">
                                <div class="carousel-3d-track" id="carouselTrack">
                                    <?php if (empty($displayGames)): ?>
                                        <div style="padding: 40px; text-align: center; color: #888;">В библиотеке пока нет игр</div>
                                    <?php else: ?>
                                        <?php foreach ($displayGames as $index => $game): ?>
                                            <div class="carousel-3d-item" data-index="<?= $index ?>">
                                                <a href="/g/<?= $game['id'] ?>" class="game-card-link" style="text-decoration:none;color:inherit;">
                                                    <div class="game-card-vertical">
                                                        <img src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/assets/default-game-cover.png' ?>"
                                                            alt="Обложка игры" class="game-cover-vertical">
                                                        <div class="game-info-vertical">
                                                            <h3 class="game-title-vertical"><?= htmlspecialchars($game['name']) ?></h3>
                                                            <div class="game-meta-vertical">
                                                                <span class="game-rating">★ <?= number_format($game['rating'], 1) ?></span>
                                                                <span class="game-price"><?= $game['price'] > 0 ? $game['price'] . ' ₽' : 'Бесплатно' ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="carousel-3d-btn next" aria-label="Следующие игры">›</button>
                        </div>

                        <!-- Режим "Витрина" -->
                        <div class="showcase-container" id="showcaseView" style="display: none;">
                            <!-- Игры -->
                            <div class="showcase-body" id="showcaseGamesBody">
                                <div class="showcase-shelves" id="showcaseShelves">
                                    <?php
                                    $perRow        = 8;
                                    $showcaseSlots = $games;
                                    $remainder     = count($showcaseSlots) % $perRow;
                                    if ($remainder !== 0) {
                                        $showcaseSlots = array_merge($showcaseSlots, array_fill(0, $perRow - $remainder, null));
                                    }
                                    if (empty($showcaseSlots)) {
                                        $showcaseSlots = array_fill(0, $perRow, null);
                                    }
                                    foreach (array_chunk($showcaseSlots, $perRow) as $rowGames):
                                    ?>
                                        <div class="showcase-shelf-row">
                                            <?php foreach ($rowGames as $sg): ?>
                                                <?php if ($sg): ?>
                                                    <div class="showcase-game-card"
                                                        onclick="window.location.href='/g/<?= $sg['id'] ?>'"
                                                        title="<?= htmlspecialchars($sg['name']) ?>">
                                                        <?php if (!empty($sg['path_to_cover'])): ?>
                                                            <img src="<?= htmlspecialchars($sg['path_to_cover']) ?>" alt="<?= htmlspecialchars($sg['name']) ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="showcase-game-card showcase-game-empty"></div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Достижения -->
                            <div class="showcase-achievements-body" id="showcaseAchievementsBody" style="display: none;">
                                <?php if (empty($achievements)): ?>
                                    <div class="showcase-wip-inner">
                                        <p class="showcase-wip-title">Достижений пока нет</p>
                                        <p class="showcase-wip-sub">Играй, исследуй платформу и зарабатывай награды</p>
                                    </div>
                                <?php else: ?>
                                    <div class="achievements-grid">
                                        <?php foreach ($achievements as $ach): ?>
                                            <?php $title = htmlspecialchars($ach['name']); ?>
                                            <div class="achievement-card"
                                                onclick='showAchievementModal({
                                                     title: <?= json_encode($title) ?>,
                                                     description: <?= json_encode($ach["description"] ?? "") ?>,
                                                     icon: <?= json_encode($ach["icon_url"] ?? "🏆") ?>,
                                                     date: <?= json_encode($ach["awarded_at"] ?? "") ?>
                                                 })'>
                                                <div class="achievement-card-icon">
                                                    <?php if (!empty($ach["icon_url"])): ?>
                                                        <img src="<?= htmlspecialchars($ach["icon_url"]) ?>" alt="<?= $title ?>">
                                                        <?php else: ?>🏆<?php endif; ?>
                                                </div>
                                                <div class="achievement-card-name"><?= $title ?></div>
                                                <?php if (!empty($ach['awarded_at'])): ?>
                                                    <div class="achievement-card-date"><?= date('d.m.Y', strtotime($ach['awarded_at'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Вкладка "Отзывы" ── -->
                <div id="tab-reviews" class="tab-content">
                    <div class="profile-card">
                        <h2 class="section-title">Отзывы пользователя (<?= count($reviews) ?>)</h2>
                        <?php if (!empty($reviews)): ?>
                            <div class="reviews-list">
                                <?php foreach ($reviews as $review): ?>
                                    <a href="/g/<?= $review['game_id'] ?>" class="review-item-link" style="text-decoration:none;color:inherit;">
                                        <div class="review-item">
                                            <div class="review-header">
                                                <img src="<?= !empty($review['game_cover']) ? htmlspecialchars($review['game_cover']) : '/assets/default-game-cover.png' ?>"
                                                    alt="Обложка игры" class="review-game-cover">
                                                <div>
                                                    <div class="review-game-title"><?= htmlspecialchars($review['game_title']) ?></div>
                                                    <div class="review-rating">★ <?= $review['rating'] ?>/10</div>
                                                </div>
                                            </div>
                                            <div class="review-text"><?= nl2br(htmlspecialchars($review['text'])) ?></div>
                                            <div class="review-date"><?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Этот пользователь пока не оставил ни одного отзыва :(</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── Вкладка "Друзья" ── -->
                <div id="tab-collection" class="tab-content">
                    <div class="profile-card">
                        <h2 class="section-title">Друзья (<?= count($friends) ?>)</h2>
                        <?php if (empty($friends)): ?>
                            <div class="empty-state">
                                <p>У пользователя пока нет друзей</p>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <?php foreach ($friends as $friend): ?>
                                    <a href="/player/<?= htmlspecialchars($friend['username']) ?>"
                                        style="display:flex;align-items:center;gap:14px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px 16px;text-decoration:none;color:white;transition:background 0.2s;">
                                        <img src="<?= !empty($friend['profile_picture']) ? htmlspecialchars($friend['profile_picture']) : '/swad/static/img/logo.svg' ?>"
                                            alt="Аватар"
                                            style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.1);">
                                        <div style="flex:1;min-width:0;">
                                            <div style="font-weight:600;font-size:0.97em;">
                                                <?= htmlspecialchars(trim($friend['first_name'] . ' ' . $friend['last_name'])) ?>
                                            </div>
                                            <div style="font-size:0.82em;color:#888;margin-top:2px;">
                                                @<?= htmlspecialchars($friend['username']) ?>
                                            </div>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="9 18 15 12 9 6" />
                                        </svg>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── Вкладка "Безопасность / Разработчик" ── -->
                <?php if ($is_owner): ?>
                    <div id="tab-developer" class="tab-content">
                        <div class="profile-card">
                            <h2 class="section-title">Для разработчиков</h2>
                            <div class="info-grid" style="display:grid;gap:20px;">
                                <div class="info-card" style="background:rgba(255,255,255,0.03);padding:20px;border-radius:10px;">
                                    <?php
                                    if ($curr_user->getUO($userID)) {
                                        echo "<h1>Студия " . $curr_user->getUO($userID)[0]['name'] . "</h1>";
                                        echo "<p><a href='/devs/select' style='color:var(--primary);text-decoration:none;'>>>> Вход в консоль для разработчиков</a></p>";
                                    } else {
                                        echo "<h1>У вас ещё нет аккаунта разработчика</h1>";
                                        echo "<p><a href='/devs/regorg' style='color:var(--primary);text-decoration:none;'>>>>Зарегистрируйте его бесплатно!</a></p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- /.profile-right -->
        </div>
    </section>

    <!-- ══ МОДАЛКА: Достижение ══ -->
    <div class="modal" id="achievementModal"
        style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);backdrop-filter:blur(10px);z-index:1000;align-items:center;justify-content:center;">
        <div class="modal-content"
            style="background:linear-gradient(135deg,#2a3344 0%,#1a1f2e 100%);border-radius:20px;padding:30px;max-width:500px;width:90%;position:relative;box-shadow:0 25px 60px rgba(0,0,0,0.9);border:2px solid rgba(255,215,0,0.3);">
            <button onclick="closeAchievementModal()"
                style="position:absolute;top:15px;right:15px;background:rgba(255,215,0,0.2);border:2px solid #ffd700;color:#ffd700;width:35px;height:35px;border-radius:50%;cursor:pointer;font-size:20px;">&times;</button>
            <div id="achievementModalBody"></div>
        </div>
    </div>

    <!-- ══ МОДАЛКА: Коллекционный предмет ══ -->
    <div class="modal" id="itemModal"
        style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);backdrop-filter:blur(10px);z-index:1000;align-items:center;justify-content:center;">
        <div class="modal-content"
            style="background:linear-gradient(135deg,#2a3344 0%,#1a1f2e 100%);border-radius:20px;padding:40px;max-width:600px;width:90%;position:relative;box-shadow:0 25px 60px rgba(0,0,0,0.9);border:2px solid rgba(0,245,255,0.3);">
            <button onclick="closeItemModal()"
                style="position:absolute;top:20px;right:20px;background:rgba(255,0,110,0.2);border:2px solid #ff006e;color:#ff006e;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:24px;display:flex;align-items:center;justify-content:center;z-index:1001;">&times;</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        // ── Модалка достижений ──────────────────────────────────────
        function showAchievementModal(ach) {
            document.getElementById('achievementModalBody').innerHTML = `
            <div style="text-align:center;">
                <div style="font-size:3em;margin-bottom:15px;">
                    <img src="${ach.icon}" alt="${ach.title}" class="achievement-icon-img">
                </div>
                <h2 style="color:#ffd700;margin-bottom:10px;">${ach.title}</h2>
                <p style="color:#b0b8c1;line-height:1.5;margin-bottom:20px;">${ach.description || ''}</p>
                <div style="font-size:0.9em;color:#888;">Дата получения: ${ach.date || ''}</div>
                <button style="margin-top:20px;padding:10px 20px;border:none;border-radius:10px;background:linear-gradient(135deg,#ffd700,#ffbe0b);color:#000;font-weight:bold;cursor:pointer;"
                        onclick="closeAchievementModal()">Закрыть</button>
            </div>`;
            document.getElementById('achievementModal').style.display = 'flex';
        }

        function closeAchievementModal() {
            document.getElementById('achievementModal').style.display = 'none';
        }
        document.getElementById('achievementModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeAchievementModal();
        });

        // ── Модалка коллекционных предметов ────────────────────────
        function showItemModal(item) {
            const rarityMap = {
                0: {
                    name: 'Обычный',
                    color: '#a0a0a0'
                },
                1: {
                    name: 'Необычный',
                    color: '#00ff00'
                },
                2: {
                    name: 'Редкий',
                    color: '#007bff'
                },
                3: {
                    name: 'Эпический',
                    color: '#800080'
                },
                4: {
                    name: 'Легендарный',
                    color: '#ffd700'
                }
            };
            const r = rarityMap[item.rarity] || rarityMap[0];
            document.getElementById('modalBody').innerHTML = `
            <div style="text-align:center;">
                <div style="font-size:4em;margin-bottom:20px;">🏆</div>
                <h2 style="color:#00f5ff;margin-bottom:10px;font-size:1.8em;">${item.title}</h2>
                <div style="background:${r.color};color:#000;padding:8px 20px;border-radius:20px;display:inline-block;margin-bottom:20px;font-weight:bold;font-size:1.1em;">${r.name}</div>
                <p style="color:#b0b8c1;margin-bottom:20px;line-height:1.6;font-size:1.1em;">${item.description}</p>
                <div style="display:flex;justify-content:space-between;color:#888;font-size:0.9em;margin-top:30px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
                    <div><div style="font-weight:bold;color:#aaa;">Тип</div><div>Коллекционный предмет</div></div>
                    <div><div style="font-weight:bold;color:#aaa;">Добавлено</div><div>${item.date}</div></div>
                </div>
                <button style="width:100%;padding:14px;background:linear-gradient(135deg,#ff006e,#00f5ff);color:#fff;border:none;border-radius:12px;font-size:1.1em;cursor:pointer;margin-top:30px;font-weight:bold;"
                        onclick="closeItemModal()">Закрыть</button>
            </div>`;
            document.getElementById('itemModal').style.display = 'flex';
        }

        function closeItemModal() {
            document.getElementById('itemModal').style.display = 'none';
        }
        document.getElementById('itemModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeItemModal();
        });

        // ── Переключение вкладок ────────────────────────────────────
        function switchTab(tabName) {
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }

        // ── Кнопка "Добавить в друзья" (сайдбар) ───────────────────
        document.getElementById('friendBtn')?.addEventListener('click', async function(e) {
            e.preventDefault();
            const btn = this;
            const text = document.getElementById('friendBtnText');
            if (btn.hasAttribute('disabled')) return;
            const userId = btn.dataset.user;
            const action = btn.dataset.action;
            try {
                const res = await fetch('/api/friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action,
                        user_id: userId
                    })
                });
                const data = await res.json();
                if (!data.success) {
                    console.error(data.error);
                    return;
                }
                if (action === 'send') {
                    text.innerText = 'Заявка отправлена';
                    btn.setAttribute('disabled', 'true');
                }
                if (action === 'accept') {
                    text.innerText = 'В друзьях';
                    btn.setAttribute('disabled', 'true');
                }
            } catch (err) {
                alert('Сеть отвалилась, как и твой энтузиазм');
            }
        });

        // ── Кнопка действия с другом (шапка профиля) ───────────────
        document.getElementById('friendActionBtn')?.addEventListener('click', async function() {
            const btn = this;
            const text = document.getElementById('friendActionBtnText');
            const userId = btn.dataset.user;
            const action = btn.dataset.action;
            if (btn.hasAttribute('disabled')) return;
            btn.setAttribute('disabled', 'true');
            let rawText = '';
            try {
                const res = await fetch('/api/friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action,
                        user_id: userId
                    })
                });
                rawText = await res.text();
                const data = JSON.parse(rawText);
                if (!data.success) {
                    alert('Ошибка: ' + data.error);
                    btn.removeAttribute('disabled');
                    return;
                }
                if (action === 'send') {
                    text.textContent = 'Отменить заявку';
                    btn.dataset.action = 'cancel';
                    btn.className = 'friend-action-btn friend-action-btn--muted';
                    btn.removeAttribute('disabled');
                }
                if (action === 'cancel') {
                    text.textContent = 'Добавить в друзья';
                    btn.dataset.action = 'send';
                    btn.className = 'friend-action-btn';
                    btn.removeAttribute('disabled');
                }
                if (action === 'accept') {
                    text.textContent = 'Завершить дружбу';
                    btn.dataset.action = 'remove';
                    btn.className = 'friend-action-btn friend-action-btn--remove';
                    btn.removeAttribute('disabled');
                }
                if (action === 'remove') {
                    text.textContent = 'Добавить в друзья';
                    btn.dataset.action = 'send';
                    btn.className = 'friend-action-btn';
                    btn.removeAttribute('disabled');
                }
            } catch (err) {
                alert('Ответ сервера: ' + rawText.substring(0, 300));
                btn.removeAttribute('disabled');
            }
        });

        // ── Принять заявку (из списка входящих) ────────────────────
        document.querySelectorAll('.acceptFriend').forEach(btn => {
            btn.addEventListener('click', async function() {
                const uid = this.dataset.user;
                const res = await fetch('/api/friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'accept',
                        user_id: uid
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.innerText = 'В друзьях';
                    this.disabled = true;
                } else {
                    alert(data.error || 'Ошибка');
                }
            });
        });
    </script>

    <script>
        // ── 3D-наклон кнопок вкладок ────────────────────────────────
        (function() {
            const tabButtons = document.querySelectorAll('.profile-left .tab-button');
            if (!tabButtons.length) return;
            tabButtons.forEach(btn => {
                btn.addEventListener('mousemove', e => {
                    const rect = btn.getBoundingClientRect();
                    const nx = ((e.clientX - rect.left) / rect.width) * 2 - 1;
                    const ny = ((e.clientY - rect.top) / rect.height) * 2 - 1;
                    btn.style.transform = `perspective(400px) rotateX(${-8*ny}deg) rotateY(${8*nx}deg) translateY(-3px) scale(1.06)`;
                });
                btn.addEventListener('mouseleave', () => {
                    btn.style.transform = '';
                });
            });
        })();
    </script>

    <script>
        // ── Карусель ────────────────────────────────────────────────
        (function() {
            const track = document.getElementById('carouselTrack');
            if (!track) return;

            const items = Array.from(track.children);
            const itemCount = items.length;
            let currentCenterIdx = 0;

            function updatePositions(centerIndex) {
                currentCenterIdx = ((centerIndex % itemCount) + itemCount) % itemCount;
                items.forEach((item, idx) => {
                    let offset = idx - currentCenterIdx;
                    if (offset > itemCount / 2) offset -= itemCount;
                    if (offset < -itemCount / 2) offset += itemCount;
                    item.setAttribute('data-position', offset);
                });
            }

            updatePositions(0);

            document.querySelector('.carousel-3d-btn.prev')?.addEventListener('click', () => updatePositions(currentCenterIdx - 1));
            document.querySelector('.carousel-3d-btn.next')?.addEventListener('click', () => updatePositions(currentCenterIdx + 1));

            // Колёсико
            const stage = document.getElementById('carouselStage');
            let wheelTimer = null;
            stage.addEventListener('wheel', e => {
                e.preventDefault();
                clearTimeout(wheelTimer);
                wheelTimer = setTimeout(() => {
                    updatePositions(currentCenterIdx + (e.deltaY > 0 || e.deltaX > 0 ? 1 : -1));
                }, 30);
            }, {
                passive: false
            });

            // Drag
            let dragStartX = null;
            let dragMoved = false;
            const THRESHOLD = 50;

            function lockSelect() {
                document.body.style.userSelect = 'none';
            }

            function unlockSelect() {
                document.body.style.userSelect = '';
            }

            function onStart(x) {
                dragStartX = x;
                dragMoved = false;
                lockSelect();
            }

            function onMove(x) {
                if (dragStartX !== null && Math.abs(x - dragStartX) > 5) dragMoved = true;
            }

            function onEnd(x) {
                if (dragStartX === null) return;
                unlockSelect();
                const diff = x - dragStartX;
                if (Math.abs(diff) >= THRESHOLD) updatePositions(currentCenterIdx + (diff < 0 ? 1 : -1));
                dragStartX = null;
            }

            // Блокируем переход по ссылке если это был drag
            stage.addEventListener('click', e => {
                if (dragMoved) e.preventDefault();
            }, true);

            stage.style.cursor = 'grab';
            stage.addEventListener('mousedown', e => {
                stage.style.cursor = 'grabbing';
                onStart(e.clientX);
            });
            window.addEventListener('mousemove', e => onMove(e.clientX));
            window.addEventListener('mouseup', e => {
                stage.style.cursor = 'grab';
                onEnd(e.clientX);
            });

            stage.addEventListener('touchstart', e => onStart(e.touches[0].clientX), {
                passive: true
            });
            stage.addEventListener('touchmove', e => onMove(e.touches[0].clientX), {
                passive: true
            });
            stage.addEventListener('touchend', e => onEnd(e.changedTouches[0].clientX));

            stage.querySelectorAll('img, a').forEach(el => el.setAttribute('draggable', 'false'));
        })();
    </script>

    <script>
        // ── Переключение вид карусель/витрина (с localStorage) ──────
        (function() {
            const STORAGE_KEY = 'games_view_mode';

            function applyView(view) {
                const carousel = document.getElementById('carouselView');
                const showcase = document.getElementById('showcaseView');
                if (!carousel || !showcase) return;
                document.querySelectorAll('.games-view-icon-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
                carousel.style.display = view === 'carousel' ? 'block' : 'none';
                showcase.style.display = view === 'showcase' ? 'block' : 'none';
            }

            applyView(localStorage.getItem(STORAGE_KEY) || 'carousel');

            document.querySelectorAll('.games-view-icon-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    localStorage.setItem(STORAGE_KEY, this.dataset.view);
                    applyView(this.dataset.view);
                });
            });
        })();

        // ── Вкладки внутри витрины (Игры / Достижения) ─────────────
        document.querySelectorAll('.showcase-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.showcase-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                document.getElementById('carouselView').style.display = 'none';
                document.getElementById('showcaseView').style.display = 'block';

                const target = this.dataset.showcase;
                const viewIcons = document.querySelector('.games-view-icons');
                if (viewIcons) viewIcons.style.visibility = target === 'achievements' ? 'hidden' : 'visible';

                if (target === 'games') {
                    document.querySelectorAll('.games-view-icon-btn').forEach(b => b.classList.toggle('active', b.dataset.view === 'showcase'));
                }

                document.getElementById('showcaseGamesBody').style.display = target === 'games' ? 'flex' : 'none';
                document.getElementById('showcaseAchievementsBody').style.display = target === 'achievements' ? 'block' : 'none';
            });
        });
    </script>

<<<<<<< HEAD
=======
<script>
    (function() {
        const track = document.getElementById('carouselTrack');
        if (!track) return;

        const items = Array.from(track.children);
        const totalUnique = <?= $total ?>; // количество уникальных игр
        const itemCount = items.length;

        // Устанавливаем data-position для каждого элемента
        function updatePositions(centerIndex) {
            // centerIndex – абсолютный индекс элемента, который должен быть в центре
            items.forEach((item, idx) => {
                let offset = idx - centerIndex;
                // Нормализуем offset для циклического эффекта (в пределах -floor(total/2) .. +floor(total/2))
                // Но проще использовать offset как есть, но CSS обрабатывает любые значения
                item.setAttribute('data-position', offset);
            });
        }

        // Начальное положение: центральный элемент с индексом 0 (первый)
        updatePositions(0);

        // Обработчики кнопок
        const prevBtn = document.querySelector('.carousel-3d-btn.prev');
        const nextBtn = document.querySelector('.carousel-3d-btn.next');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                // Сдвигаем центр влево (увеличиваем offset для всех)
                // Находим текущий центральный (data-position="0")
                let currentCenterIdx = items.findIndex(item => item.getAttribute('data-position') === '0');
                if (currentCenterIdx === -1) currentCenterIdx = 0;
                let newCenterIdx = (currentCenterIdx - 1 + itemCount) % itemCount;
                updatePositions(newCenterIdx);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                let currentCenterIdx = items.findIndex(item => item.getAttribute('data-position') === '0');
                if (currentCenterIdx === -1) currentCenterIdx = 0;
                let newCenterIdx = (currentCenterIdx + 1) % itemCount;
                updatePositions(newCenterIdx);
            });
        }
    })();
</script>
<script>
// Иконки сетка/список — переключение между каруселью и витриной
(function() {
    const STORAGE_KEY = 'games_view_mode'; // глобальный ключ — работает для всех профилей

    function applyView(view) {
        const carousel = document.getElementById('carouselView');
        const showcase = document.getElementById('showcaseView');
        if (!carousel || !showcase) return;

        document.querySelectorAll('.games-view-icon-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.view === view);
        });

        if (view === 'carousel') {
            carousel.style.display = 'block';
            showcase.style.display = 'none';
        } else {
            carousel.style.display = 'none';
            showcase.style.display = 'block';
        }
    }

    // Восстанавливаем сохранённый режим при загрузке
    const saved = localStorage.getItem(STORAGE_KEY) || 'carousel';
    applyView(saved);

    // Сохраняем при переключении
    document.querySelectorAll('.games-view-icon-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            localStorage.setItem(STORAGE_KEY, view);
            applyView(view);
        });
    });
})();
</script>
<script>
// Переключение вкладок внутри витрины (Игры / Достижения)
document.querySelectorAll('.showcase-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.showcase-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Принудительно переключаемся в режим витрины
        document.getElementById('carouselView').style.display  = 'none';
        document.getElementById('showcaseView').style.display  = 'block';

        const target = this.dataset.showcase;

        // Прячем иконки переключения режима на вкладке "Достижения"
        const viewIcons = document.querySelector('.games-view-icons');
        if (viewIcons) {
            viewIcons.style.visibility = target === 'achievements' ? 'hidden' : 'visible';
        }

        // Обновляем активную иконку только для вкладки игр
        if (target === 'games') {
            document.querySelectorAll('.games-view-icon-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.view === 'showcase');
            });
        }

        document.getElementById('showcaseGamesBody').style.display        = target === 'games'        ? 'flex' : 'none';
        document.getElementById('showcaseAchievementsBody').style.display = target === 'achievements' ? 'block' : 'none';
    });
});
</script>
<script>
// 3D-наклон при наведении — как у кнопок в хедере
(function() {
    const SELECTORS = [
        '#friendActionBtn',
        '.showcase-tab',
        '.profile-left .tab-button',
    ];

    function applyTilt(e) {
        const btn  = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const nx   = ((e.clientX - rect.left)  / rect.width)  * 2 - 1;
        const ny   = ((e.clientY - rect.top)   / rect.height) * 2 - 1;
        const maxAngle  = 15;
        const rotateY   = maxAngle * nx;
        const rotateX   = -maxAngle * ny;
        btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-3px) scale(1.04)`;
    }

    function resetTilt(e) {
        e.currentTarget.style.transform = '';
    }

    SELECTORS.forEach(sel => {
        document.querySelectorAll(sel).forEach(btn => {
            btn.style.transformStyle = 'preserve-3d';
            btn.style.willChange     = 'transform';
            btn.addEventListener('mousemove',  applyTilt);
            btn.addEventListener('mouseleave', resetTilt);
        });
    });
})();
</script>
>>>>>>> 33a8665d34fc503e2ecab96a9008457c467bbab1
</body>

</html>