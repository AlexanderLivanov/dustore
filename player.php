<?php
// (c) 19.05.2025 Alexander Livanov
require_once('swad/config.php');
require_once('swad/controllers/user.php');

session_start();

// Получаем username из URL
$username = isset($_GET['username']) ? $_GET['username'] : '';

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

// Получаем игры пользователя
$stmt = $pdo->prepare("
    SELECT g.id, g.name, g.description, g.path_to_cover, g.price, g.GQI, g.release_date
    FROM games g
    WHERE g.developer = :user_id AND g.status = 'published'
    ORDER BY g.created_at DESC
    LIMIT 6
");
$stmt->execute([':user_id' => $user['id']]);
$games = $stmt->fetchAll();

// Получаем отзывы пользователя
$stmt = $pdo->prepare("
    SELECT r.*, g.name as game_title, g.path_to_cover as game_cover
    FROM game_reviews r
    JOIN games g ON g.id = r.game_id
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([':user_id' => $user['id']]);
$reviews = $stmt->fetchAll();

// Проверяем, является ли текущий пользователь владельцем профиля
$is_owner = false;
if (!empty($_SESSION['logged-in']) && !empty($_SESSION['user_id'])) {
    $is_owner = ($_SESSION['user_id'] == $user['id']);
}

$stmt = $pdo->prepare("
SELECT 
    (SELECT COUNT(*) FROM library WHERE player_id = :user_id) as library_count,
    (SELECT COUNT(*) FROM achievements WHERE player_id = :user_id) as achievements_count,
    (SELECT COUNT(*) FROM friends WHERE 
        (player_id = :user_id OR friend_id = :user_id) AND status = 'accepted') as friends_count
");
$stmt->execute([':user_id' => $user['id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?= htmlspecialchars($user['username']) ?> | Dustore</title>
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

        .badge {
            display: inline-block;
            padding: 5px 10px;
            background: var(--primary);
            color: white;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 8px;
            margin-bottom: 8px;
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
        }

        @media (max-width: 600px) {
            .user-stats {
                grid-template-columns: 1fr;
            }

            .games-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <div class="profile-header">
        <div class="container">
            <div class="user-info">
                <img src="<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : '/swad/static/img/logo.svg' ?>"
                    alt="Аватар" class="user-avatar">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <p><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Этот пользователь пока не добавил описание' ?></p>

                <?php if ($is_owner): ?>
                    <a href="/settings/profile" class="edit-profile-btn">Редактировать профиль</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
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
                        <strong>На платформе с:</strong><br>
                        <?= date('d.m.Y', strtotime($user['added'])) ?>
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
                            <!-- <span class="stat-number"><?= (int)$stats['games_count'] ?></span> -->
                            <span class="stat-number"><?= "0" ?></span>
                            <span class="stat-label">Игр</span>
                        </div>
                        <div class="stat-item">
                            <!-- <span class="stat-number"><?= (int)$stats['reviews_count'] ?></span> -->
                            <span class="stat-number"><?= "0" ?></span>
                            <span class="stat-label">Отзывов</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= (int)$stats['friends_count'] ?></span>
                            <span class="stat-label">Друзей</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= (int)$stats['library_count'] ?></span>
                            <span class="stat-label">Игр в библиотеке</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= (int)$stats['achievements_count'] ?></span>
                            <span class="stat-label">Достижений</span>
                        </div>
                    </div>
                </div>

                <?php if ($is_owner): ?>
                    <div class="profile-card">
                        <h3>Быстрые действия</h3>
                        <a href="/devs/create" class="edit-profile-btn">➕ Добавить игру</a>
                        <a href="/settings/profile" class="edit-profile-btn">⚙️ Настройки</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-main">
                <div class="profile-card">
                    <h2 class="section-title">Игры</h2>

                    <?php if (!empty($games)): ?>
                        <div class="games-grid">
                            <?php foreach ($games as $game): ?>
                                <div class="game-card">
                                    <img src="<?= !empty($game['cover_image']) ? htmlspecialchars($game['cover_image']) : '/assets/default-game-cover.png' ?>"
                                        alt="Обложка игры" class="game-cover">
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['title']) ?></h3>
                                        <p class="game-description"><?= htmlspecialchars($game['description']) ?></p>
                                        <div class="game-meta">
                                            <span class="game-rating">★ <?= number_format($game['rating'], 1) ?></span>
                                            <span class="game-price"><?= $game['price'] > 0 ? $game['price'] . ' ₽' : 'Бесплатно' ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($games) >= 6): ?>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="/games?user=<?= $user['id'] ?>" class="edit-profile-btn">Показать все игры</a>
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

                    <?php if (!empty($reviews)): ?>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
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
                                        <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                    </div>
                                    <div class="review-date">
                                        <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Этот пользователь пока не оставил ни одного отзыва :(</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once('swad/static/elements/footer.php'); ?>
</body>

</html>