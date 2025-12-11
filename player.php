<?php
// (c) 11.12.2025 Alexander Livanov
require_once('swad/config.php');
require_once('swad/controllers/user.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/get_user_activity.php');
require_once('swad/controllers/organization.php');

session_start();

// Получаем username из URL пути (domain.ru/player/<username>)
$request_uri = $_SERVER['REQUEST_URI'];
$pattern = '/\/player\/([a-zA-Z0-9_]+)/';

if (preg_match($pattern, $request_uri, $matches)) {
    $username = $matches[1];
} else {
    // Альтернативный вариант для других форматов URL
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

// Подключаемся к базе данных
$database = new Database();
$pdo = $database->connect();

// Получаем данные пользователя
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

// Проверяем, является ли текущий пользователь владельцем профиля
$is_owner = false;
if (!empty($_SESSION['USERDATA']['id'])) {
    if ((int)$_SESSION['USERDATA']['id'] == (int)$user['id']) {
        $is_owner = true;
    } else {
        $is_owner = false;
    }
}

// Получаем статистику
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

// Получаем данные для вкладки "Игры"
$stmt = $pdo->prepare("
    SELECT 
        g.id,
        g.name,
        g.description,
        g.path_to_cover,
        g.price,
        g.GQI,
        g.release_date,
        COALESCE(AVG(r.rating), 0) AS rating,
        MAX(l.date) AS last_added
    FROM library l
    JOIN games g ON g.id = l.game_id
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE l.player_id = :user_id AND l.purchased = 1
    GROUP BY 
        g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date
    ORDER BY last_added DESC
    LIMIT 50
");
$stmt->execute([':user_id' => $user['id']]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем отзывы пользователя
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

// Получаем коллекцию пользователя (игры + коллекционные предметы)
$stmt_items = $pdo->prepare("
    SELECT * FROM library 
    WHERE player_id = :user_id 
    ORDER BY rarity DESC, date DESC
");
$stmt_items->execute([':user_id' => $user['id']]);
$all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Разделяем предметы на игры и коллекционные предметы
$games_collection = [];
$collectibles = [];

foreach ($all_items as $item) {
    if (!empty($item['game_id']) && $item['game_id'] > 0) {
        // Это игра - получаем дополнительную информацию об игре
        $stmt_game_info = $pdo->prepare("
            SELECT name, description, path_to_cover, price 
            FROM games 
            WHERE id = :game_id
        ");
        $stmt_game_info->execute([':game_id' => $item['game_id']]);
        $game_info = $stmt_game_info->fetch(PDO::FETCH_ASSOC);

        if ($game_info) {
            $item['title'] = $game_info['name'];
            $item['description'] = $game_info['description'];
            $item['cover_image'] = $game_info['path_to_cover'];
            $item['price'] = $game_info['price'];
            $item['item_type'] = 'game';
            $games_collection[] = $item;
        }
    } else {
        // Это коллекционный предмет
        $item['item_type'] = 'collectible';
        if (empty($item['title'])) {
            $item['title'] = 'Коллекционный предмет #' . $item['id'];
        }
        if (empty($item['description'])) {
            $item['description'] = 'Особый коллекционный предмет';
        }
        $collectibles[] = $item;
    }
}

// Для вкладки разработчика
if ($is_owner) {
    $curr_user = new User();
    $org = new Organization();
    $user_data = $_SESSION['USERDATA'];
    $userID = $user['id'];
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?= htmlspecialchars($user['username']) ?> | Dustore</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <style>
        :root {
            --primary: #c32178;
            --secondary: #74155d;
            --dark: #14041d;
            --light: #f8f9fa;
            --gradient: linear-gradient(180deg, #14041d, #400c4a, #74155d, #c32178);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--dark);
            color: var(--light);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .profile-header {
            background: var(--gradient);
            padding: 60px 0 30px;
            position: relative;
            margin-bottom: 40px;
        }

        .profile-content {
            display: flex;
            gap: 40px;
            margin-bottom: 50px;
        }

        .profile-sidebar {
            flex: 0 0 300px;
        }

        .profile-main {
            flex: 1;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .user-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            margin-bottom: 20px;
        }

        .user-info h1 {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: white;
        }

        .user-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #aaa;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }

        .social-link {
            display: inline-block;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            color: #ddd;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--primary);
            color: white;
        }

        .section-title {
            font-family: 'PixelizerBold', 'Gill Sans', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        /* Стили для вкладок */
        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            color: #aaa;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .tab-button:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .tab-button.active {
            background: var(--primary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Стили для коллекции */
        .shelf-container {
            margin-top: 20px;
        }

        .shelf-title {
            font-size: 1.8em;
            margin: 30px 0 20px;
            color: #ffbe0b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .shelf {
            margin-bottom: 50px;
            position: relative;
        }

        .shelf-bar {
            height: 10px;
            background: linear-gradient(180deg, #3d4a5c 0%, #2a3344 50%, #1a1f2e 100%);
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
        }

        .collection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 0 10px;
        }

        .item-card {
            width: 100%;
            height: 285px;
            cursor: pointer;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7);
            border-radius: 12px;
            overflow: hidden;
        }

        .item-card:hover {
            transform: rotateX(10deg) rotateY(-10deg) translateY(-5px) scale(1.05);
            box-shadow: 0 20px 45px rgba(255, 0, 110, 0.4);
        }

        .item-cover {
            width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .item-content {
            position: relative;
            z-index: 10;
            pointer-events: none;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
        }

        .item-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: block;
        }

        .item-title {
            font-size: 1.1em;
            margin-bottom: 5px;
            font-weight: bold;
            color: white;
        }

        .item-rarity {
            font-size: 0.85em;
            opacity: 0.9;
            padding: 3px 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.6);
            display: inline-block;
            margin-top: 5px;
            color: white;
        }

        .item-card[data-rarity="0"] .item-cover {
            border: 2px solid #a0a0a0;
            box-shadow: inset 0 0 10px rgba(160, 160, 160, 0.3);
        }

        .item-card[data-rarity="1"] .item-cover {
            border: 2px solid #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }

        .item-card[data-rarity="2"] .item-cover {
            border: 2px solid #007bff;
            box-shadow: 0 0 25px rgba(0, 123, 255, 0.4);
        }

        .item-card[data-rarity="3"] .item-cover {
            border: 2px solid #800080;
            box-shadow: 0 0 30px rgba(128, 0, 128, 0.5);
        }

        .item-card[data-rarity="4"] .item-cover {
            border: 2px solid #ffd700;
            box-shadow: 0 0 35px rgba(255, 215, 0, 0.6);
        }

        .item-purchase-info {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 0.8em;
            background: rgba(0, 0, 0, 0.7);
            padding: 3px 8px;
            border-radius: 10px;
            opacity: 0.8;
            color: white;
        }

        /* Остальные стили */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .game-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .game-card:hover {
            transform: translateY(-5px);
        }

        .game-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .game-info {
            padding: 15px;
        }

        .game-title {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: white;
        }

        .game-description {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .game-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .game-rating {
            color: #ffc107;
            font-weight: bold;
        }

        .game-price {
            color: var(--primary);
            font-weight: bold;
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .review-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 20px;
        }

        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .review-game-cover {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }

        .review-game-title {
            font-weight: bold;
            color: white;
        }

        .review-rating {
            color: #ffc107;
            margin-left: auto;
        }

        .review-text {
            color: #ddd;
            line-height: 1.5;
        }

        .review-date {
            font-size: 0.8rem;
            color: #888;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .edit-profile-btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .edit-profile-btn:hover {
            background: #e62e8a;
            transform: translateY(-2px);
        }

        .avatar-wrapper {
            position: relative;
            width: 200px;
            height: 200px;
            margin-bottom: 20px;
        }

        .avatar-frame {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .frame-image {
            position: absolute;
            top: -50px;
            left: -45px;
            width: 150%;
            height: 150%;
            z-index: 0;
        }

        .avatar-wrapper .user-avatar {
            position: absolute;
            top: 25px;
            left: 25px;
            width: 150px;
            height: 150px;
            z-index: 1;
        }

        @media (max-width: 900px) {
            .profile-content {
                flex-direction: column;
            }

            .profile-sidebar {
                flex: 1;
            }

            .user-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab-button {
                flex: 0 0 50%;
            }
        }

        @media (max-width: 600px) {
            .user-stats {
                grid-template-columns: 1fr;
            }

            .games-grid,
            .collection-grid {
                grid-template-columns: 1fr;
            }

            .tab-button {
                flex: 0 0 100%;
            }
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <div class="profile-header">
        <div class="container">
            <div class="user-info">
                <div class="avatar-wrapper">
                    <div class="avatar-frame">
                        <img src="/swad/static/img/venok_ng.svg" class="frame-image" alt="">
                        <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '/swad/static/img/logo.svg' ?>"
                            alt="Аватар" class="user-avatar">
                    </div>
                </div>
                <h1><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                <p>@<?= htmlspecialchars($user['username']) ?></p>

                <?php if ($is_owner): ?>
                    <a href="/me" class="edit-profile-btn">Редактировать профиль</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Вкладки -->
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('games')">
                Игры
            </button>
            <button class="tab-button" onclick="switchTab('profile')">
                Профиль
            </button>
            <button class="tab-button" onclick="switchTab('reviews')">
                Отзывы
            </button>
            <button class="tab-button" onclick="switchTab('collection')">
                Коллекция
            </button>
            <?php if ($is_owner): ?>
                <button class="tab-button" onclick="switchTab('developer')">
                    Для разработчиков
                </button>
            <?php endif; ?>
        </div>

        <!-- Содержимое вкладок -->
        <div id="tab-profile" class="tab-content">
            <div class="profile-content">
                <div class="profile-sidebar">
                    <div class="profile-card">
                        <h3>Информация</h3>

                        <?php if (!empty($user['country']) || !empty($user['city'])): ?>
                            <p>
                                <strong>Местоположение:</strong><br>
                                <?= !empty($user['city']) ? htmlspecialchars($user['city']) : '' ?>
                                <?= !empty($user['country']) ? ', ' . htmlspecialchars($user['country']) : '' ?>
                            </p>
                        <?php endif; ?>
                        <p>
                            <strong>На платформе с:</strong>
                            <?= date('d.m.Y', strtotime($user['added'])) ?>
                        </p>

                        <p>
                            <strong>Был(а):</strong>
                            <?= time_ago(getUserLastActivity($user['telegram_id'])) ?>
                        </p>

                        <div class="social-links">
                            <?php if (!empty($user['website'])): ?>
                                <a href="<?= htmlspecialchars($user['website']) ?>" class="social-link" target="_blank">🌐 Сайт</a>
                            <?php endif; ?>

                            <?php if (!empty($user['vk'])): ?>
                                <a href="<?= htmlspecialchars($user['vk']) ?>" class="social-link" target="_blank">ВК</a>
                            <?php endif; ?>

                            <?php if (!empty($user['telegram_username'])): ?>
                                <a href="https://t.me/<?= htmlspecialchars($user['telegram_username']) ?>" class="social-link" target="_blank">Telegram</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-card">
                        <h3>Статистика</h3>
                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$stats['reviews_count'] ?></span>
                                <span class="stat-label">Отзывов:</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$stats['friends_count'] ?></span>
                                <span class="stat-label">Друзей:</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$stats['library_count'] ?></span>
                                <span class="stat-label">Игр в библиотеке:</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$stats['achievements_count'] ?></span>
                                <span class="stat-label">Достижений:</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-main">
                    <div class="profile-card">
                        <h2 class="section-title">Игры в Коллекции пользователя</h2>

                        <?php
                        // Получаем последние 6 игр для главной вкладки
                        $games_main = array_slice($games, 0, 6);
                        ?>

                        <?php if (!empty($games_main)): ?>
                            <div class="games-grid">
                                <?php foreach ($games_main as $game): ?>
                                    <a href="/g/<?= $game['id'] ?>" class="game-card-link" style="text-decoration: none; color: inherit;">
                                        <div class="game-card">
                                            <img src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/assets/default-game-cover.png' ?>"
                                                alt="Обложка игры" class="game-cover">
                                            <div class="game-info">
                                                <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                                <p class="game-description"><?= htmlspecialchars($game['description']) ?></p>
                                                <div class="game-meta">
                                                    <span class="game-rating">★ <?= number_format($game['rating'], 1) ?></span>
                                                    <span class="game-price"><?= $game['price'] > 0 ? $game['price'] . ' ₽' : 'Бесплатно' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($games) > 6): ?>
                                <div style="text-align: center; margin-top: 20px;">
                                    <button onclick="switchTab('games')" class="edit-profile-btn">Показать все игры</button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Этот пользователь пока не сыграл ни в одну игру :(</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-card">
                        <h2 class="section-title">Последние отзывы</h2>

                        <?php
                        // Получаем последние 3 отзыва для главной вкладки
                        $reviews_main = array_slice($reviews, 0, 3);
                        ?>

                        <?php if (!empty($reviews_main)): ?>
                            <div class="reviews-list">
                                <?php foreach ($reviews_main as $review): ?>
                                    <a href="/g/<?= $review['game_id'] ?>" class="review-item-link" style="text-decoration: none; color: inherit;">
                                        <div class="review-item">
                                            <div class="review-header">
                                                <img src="<?= !empty($review['game_cover']) ? htmlspecialchars($review['game_cover']) : '/assets/default-game-cover.png' ?>"
                                                    alt="Обложка игры" class="review-game-cover">
                                                <div>
                                                    <div class="review-game-title"><?= htmlspecialchars($review['game_title']) ?></div>
                                                    <div class="review-rating">★ <?= $review['rating'] ?>/10</div>
                                                </div>
                                            </div>
                                            <div class="review-text">
                                                <?= nl2br(htmlspecialchars($review['text'])) ?>
                                            </div>
                                            <div class="review-date">
                                                <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($reviews) > 3): ?>
                                <div style="text-align: center; margin-top: 20px;">
                                    <button onclick="switchTab('reviews')" class="edit-profile-btn">Показать все отзывы</button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Этот пользователь пока не оставил ни одного отзыва :(</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-games" class="tab-content active">
            <div class="profile-card">
                <h2 class="section-title">Игры в коллекции (<?= count($games) ?>)</h2>

                <?php if (!empty($games)): ?>
                    <div class="games-grid">
                        <?php foreach ($games as $game): ?>
                            <a href="/g/<?= $game['id'] ?>" class="game-card-link" style="text-decoration: none; color: inherit;">
                                <div class="game-card">
                                    <img src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/assets/default-game-cover.png' ?>"
                                        alt="Обложка игры" class="game-cover">
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                        <p class="game-description"><?= htmlspecialchars($game['description']) ?></p>
                                        <div class="game-meta">
                                            <span class="game-rating">★ <?= number_format($game['rating'], 1) ?></span>
                                            <span class="game-price"><?= $game['price'] > 0 ? $game['price'] . ' ₽' : 'Бесплатно' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Этот пользователь пока не сыграл ни в одну игру :(</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-reviews" class="tab-content">
            <div class="profile-card">
                <h2 class="section-title">Отзывы пользователя (<?= count($reviews) ?>)</h2>

                <?php if (!empty($reviews)): ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <a href="/g/<?= $review['game_id'] ?>" class="review-item-link" style="text-decoration: none; color: inherit;">
                                <div class="review-item">
                                    <div class="review-header">
                                        <img src="<?= !empty($review['game_cover']) ? htmlspecialchars($review['game_cover']) : '/assets/default-game-cover.png' ?>"
                                            alt="Обложка игры" class="review-game-cover">
                                        <div>
                                            <div class="review-game-title"><?= htmlspecialchars($review['game_title']) ?></div>
                                            <div class="review-rating">★ <?= $review['rating'] ?>/10</div>
                                        </div>
                                    </div>
                                    <div class="review-text">
                                        <?= nl2br(htmlspecialchars($review['text'])) ?>
                                    </div>
                                    <div class="review-date">
                                        <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                    </div>
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

        <div id="tab-collection" class="tab-content">
            <div class="profile-card">
                <h2 class="section-title">Коллекция пользователя</h2>

                <div class="shelf-container">
                    <h3 class="shelf-title">🎮 Игры (<?= count($games_collection) ?>)</h3>
                    <div class="shelf">
                        <div class="shelf-bar"></div>
                        <div class="collection-grid">
                            <?php if (empty($games_collection)): ?>
                                <div class="empty-state">
                                    <p>Игр пока нет в коллекции</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($games_collection as $item): ?>
                                    <?php
                                    $rarity = $item['rarity'] ?? 0;
                                    $cover = $item['cover_image'] ?? '/swad/static/img/default-game.jpg';
                                    $title = $item['title'] ?? 'Игра #' . $item['id'];
                                    $purchase_date = $item['date'] ?? $item['purchased'] ?? date('Y-m-d');
                                    ?>
                                    <div class="item-card"
                                        data-rarity="<?= $rarity ?>"
                                        onclick="window.location.href='/g/<?= $item['game_id'] ?>'">
                                        <div class="item-cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');">
                                            <div class="item-content">
                                                <span class="item-icon">🎮</span>
                                                <div class="item-title"><?= htmlspecialchars(mb_strimwidth($title, 0, 30, '...')) ?></div>
                                                <div class="item-rarity">
                                                    <?=
                                                    match ($rarity) {
                                                        0 => 'Обычная',
                                                        1 => 'Необычная',
                                                        2 => 'Редкая',
                                                        3 => 'Эпическая',
                                                        4 => 'Легендарная',
                                                        default => 'Обычная'
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="item-purchase-info">
                                                <?= date('d.m.Y', strtotime($purchase_date)) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="shelf-title">🏆 Коллекционные предметы (<?= count($collectibles) ?>)</h3>
                    <div class="shelf">
                        <div class="shelf-bar"></div>
                        <div class="collection-grid">
                            <?php if (empty($collectibles)): ?>
                                <div class="empty-state">
                                    <p>Коллекционных предметов пока нет</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($collectibles as $item): ?>
                                    <?php
                                    $rarity = $item['rarity'] ?? 0;
                                    $cover = '/swad/static/img/default-collectible.jpg';
                                    $title = $item['title'] ?? 'Коллекционный предмет #' . $item['id'];
                                    $purchase_date = $item['date'] ?? $item['purchased'] ?? date('Y-m-d');
                                    ?>
                                    <div class="item-card"
                                        data-rarity="<?= $rarity ?>"
                                        onclick="showItemModal(<?= htmlspecialchars(json_encode($item)) ?>)">
                                        <div class="item-cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');">
                                            <div class="item-content">
                                                <span class="item-icon">🏆</span>
                                                <div class="item-title"><?= htmlspecialchars(mb_strimwidth($title, 0, 30, '...')) ?></div>
                                                <div class="item-rarity">
                                                    <?=
                                                    match ($rarity) {
                                                        0 => 'Обычный',
                                                        1 => 'Необычный',
                                                        2 => 'Редкий',
                                                        3 => 'Эпический',
                                                        4 => 'Легендарный',
                                                        default => 'Обычный'
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="item-purchase-info">
                                                <?= date('d.m.Y', strtotime($purchase_date)) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_owner): ?>
            <div id="tab-developer" class="tab-content">
                <div class="profile-card">
                    <h2 class="section-title">Для разработчиков</h2>

                    <div class="info-grid" style="display: grid; gap: 20px;">
                        <div class="info-card" style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 10px;">
                            <h3>
                                <?php
                                if ($curr_user->getUO($userID)) {
                                    echo ("<h1>Студия " . $curr_user->getUO($userID)[0]['name'] . "</h1>");
                                    echo ("<p><a href='/devs/select' style='color: var(--primary); text-decoration: none;'>>>> Вход в консоль для разработчиков</a></p>");
                                } else {
                                    echo ("<h1>У вас ещё нет аккаунта разработчика</h1>");
                                    echo ("<p><a href='/devs/regorg' style='color: var(--primary); text-decoration: none;'>>>>Зарегистрируйте его бесплатно!</a></p>");
                                }
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <!-- Модальное окно для коллекционных предметов -->
    <div class="modal" id="itemModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(135deg, #2a3344 0%, #1a1f2e 100%); border-radius: 20px; padding: 40px; max-width: 600px; width: 90%; position: relative; box-shadow: 0 25px 60px rgba(0,0,0,0.9); border: 2px solid rgba(0, 245, 255, 0.3);">
            <button class="close-btn" onclick="closeItemModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(255, 0, 110, 0.2); border: 2px solid #ff006e; color: #ff006e; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 24px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; z-index: 1001;">&times;</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        // Функция переключения вкладок
        function switchTab(tabName) {
            // Убираем активный класс со всех кнопок и контента
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Добавляем активный класс к выбранной вкладке
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }

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

            const rarityInfo = rarityMap[item.rarity] || rarityMap[0];

            const modal = document.getElementById('itemModal');
            const modalBody = document.getElementById('modalBody');

            modalBody.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 4em; margin-bottom: 20px;">
                        🏆
                    </div>
                    <h2 style="color: #00f5ff; margin-bottom: 10px; font-size: 1.8em;">${item.title}</h2>
                    <div style="background: ${rarityInfo.color}; 
                          color: #000; 
                          padding: 8px 20px; 
                          border-radius: 20px; 
                          display: inline-block;
                          margin-bottom: 20px;
                          font-weight: bold;
                          font-size: 1.1em;">
                        ${rarityInfo.name}
                    </div>
                    <p style="color: #b0b8c1; margin-bottom: 20px; line-height: 1.6; font-size: 1.1em;">
                        ${item.description}
                    </p>
                    <div style="display: flex; justify-content: space-between; color: #888; font-size: 0.9em; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <div>
                            <div style="font-weight: bold; color: #aaa;">Тип</div>
                            <div>Коллекционный предмет</div>
                        </div>
                        <div>
                            <div style="font-weight: bold; color: #aaa;">Добавлено</div>
                            <div>${item.date}</div>
                        </div>
                    </div>
                    <button style="width: 100%; 
                            padding: 14px; 
                            background: linear-gradient(135deg, #ff006e, #00f5ff); 
                            color: #fff; 
                            border: none; 
                            border-radius: 12px; 
                            font-size: 1.1em; 
                            cursor: pointer; 
                            margin-top: 30px;
                            transition: all 0.3s ease;
                            font-weight: bold;"
                            onclick="closeItemModal()">
                        Закрыть
                    </button>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeItemModal() {
            document.getElementById('itemModal').style.display = 'none';
        }

        // Закрытие модального окна при клике вне его
        document.getElementById('itemModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeItemModal();
            }
        });
    </script>
</body>

</html>