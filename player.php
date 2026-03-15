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
    <link rel="stylesheet" href="/swad/css/player.css">
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <section class="profile-header">
        <div class="container" style="display: flex; gap: 30px; flex-wrap: wrap;">
            <!-- Левая колонка: информация о пользователе + кнопки вкладок -->
            <div class="profile-left" style="flex: 0 0 300px;">
                <div class="user-info">
                    <div class="avatar-wrapper">
                        <div class="avatar-frame">
                            <!-- <img src="/swad/static/img/venok_ng.svg" class="frame-image" alt=""> -->
                            <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : '/swad/static/img/logo.svg' ?>"
                                alt="Аватар" class="user-avatar">
                        </div>
                        <a href="/me" class="edit-profile-btn"><svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg></a>
                    </div>

                    <?php
                    $status = null;

                    if (!empty($_SESSION['USERDATA']['id']) && !$is_owner) {
                        $u = new User();
                        $status = $u->getFriendStatus(
                            $_SESSION['USERDATA']['id'],
                            $user['id']
                        );
                    }

                    ?>

                    <?php if ($is_owner): ?>
                        <?php
                        $incomingRequests = [];

                        if ($is_owner) {
                            $stmt = $pdo->prepare("
                                SELECT f.id, u.id as user_id, u.username,
                                    u.first_name, u.last_name, u.profile_picture
                                FROM friends f
                                JOIN users u ON u.id = f.player_id
                                WHERE f.friend_id = :me
                                AND f.status = 'pending'
                                ORDER BY f.id DESC
                            ");

                            $stmt->execute([':me' => $user['id']]);
                            $incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }

                        // print_r($incomingRequests);
                        // echo $_SESSION['USERDATA']['id'];

                        ?>

                        <?php if ($is_owner && !empty($incomingRequests)): ?>

                            <div class="profile-card" style="border: 1px solid #c32178;">
                                <h3>Входящие заявки в друзья</h3>

                                <?php foreach ($incomingRequests as $req): ?>

                                    <div style="
                                            display:flex;
                                            align-items:center;
                                            gap:12px;
                                            margin:10px 0;
                                            background:rgba(255,255,255,.05);
                                            padding:10px;
                                            border-radius:10px;
                                        ">

                                        <img src="<?= $req['profile_picture'] ?: '/swad/static/img/logo.svg' ?>"
                                            style="width:40px;height:40px;border-radius:50%">

                                        <div style="flex:1">
                                            <a href="/player/<?= htmlspecialchars($req['username']) ?>"
                                                style="color:white;text-decoration:none">
                                                <?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?>
                                                (@<?= htmlspecialchars($req['username']) ?>)
                                            </a>
                                        </div>

                                        <button class="acceptFriend"
                                            data-user="<?= $req['user_id'] ?>"
                                            style="background:#26072d;color:white;
                                                    border:none;padding:6px 12px;
                                                    border-radius:8px;cursor:pointer">
                                            Принять
                                        </button>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>
                    <?php elseif (!empty($_SESSION['USERDATA']['id'])): ?>
                        <?php
                        $btnText = 'Добавить в друзья';
                        $btnAction = 'send';
                        $disabled = '';
                        if ($status) {
                            if ($status['status'] === 'pending') {
                                if ($status['player_id'] == $_SESSION['USERDATA']['id']) {
                                    $btnText = 'Заявка отправлена';
                                    $disabled = 'disabled';
                                } else {
                                    $btnText = 'Принять заявку';
                                    $btnAction = 'accept';
                                }
                            }

                            if ($status['status'] === 'accepted') {
                                $btnText = 'В друзьях';
                                $disabled = 'disabled';
                            }
                        }
                        ?>

                        <a href="#"
                            id="friendBtn"
                            class="edit-profile-btn"
                            data-user="<?= $user['id'] ?>"
                            data-action="<?= $btnAction ?>"
                            <?= $disabled ?>>

                            <svg style="vertical-align: middle;"
                                xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16" viewBox="0 0 24 24"
                                fill="currentColor">
                                <path d="M13.666 1.429l6.75 3.98l.096 .063l.093 .078l.106 .074a3.22 3.22 0 0 1 1.284 2.39l.005 .204v7.284c0 1.175 -.643 2.256 -1.623 2.793l-6.804 4.302c-.98 .538 -2.166 .538 -3.2 -.032l-6.695 -4.237a3.23 3.23 0 0 1 -1.678 -2.826v-7.285c0 -1.106 .57 -2.128 1.476 -2.705l6.95 -4.098c1 -.552 2.214 -.552 3.24 .015m-1.666 6.571a1 1 0 0 0 -1 1v2h-2a1 1 0 0 0 -.993 .883l-.007 .117a1 1 0 0 0 1 1h2v2a1 1 0 0 0 .883 .993l.117 .007a1 1 0 0 0 1 -1v-2h2a1 1 0 0 0 .993 -.883l.007 -.117a1 1 0 0 0 -1 -1h-2v-2a1 1 0 0 0 -.883 -.993z" />
                            </svg>

                            <span id="friendBtnText"><?= $btnText ?></span>
                        </a>

                    <?php endif; ?>

                    <!-- Достижения (иконки) -->
                    <div class="achievements-container">
                        <?php foreach ($achievements as $ach): ?>
                            <?php
                            $title = htmlspecialchars($ach['name']);
                            $icon = htmlspecialchars($ach['icon_url'] ?? '🏆');
                            ?>
                            <div class="achievement-icon"
                                title="<?= htmlspecialchars($title) ?>"
                                onclick='showAchievementModal({
                                        title: <?= json_encode($title) ?>,
                                        description: <?= json_encode($ach["description"] ?? "") ?>,
                                        icon: <?= json_encode($ach["icon_url"] ?? "🏆") ?>,
                                        date: <?= json_encode($ach["awarded_at"] ?? "") ?>
                                    })'>
                                <?php if (!empty($ach["icon_url"])): ?>
                                    <img src="<?= htmlspecialchars($ach["icon_url"]) ?>" alt="<?= htmlspecialchars($title) ?>" class="achievement-icon-img">
                                <?php else: ?>
                                    🏆
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Кнопки вкладок (теперь в левой колонке) -->
                <div class="tabs";>
                    <button class="tab-button active" onclick="switchTab('games')">Коллекция</button>
                    <button class="tab-button" onclick="switchTab('profile')">Профиль</button>
                    <!--<button class="tab-button" onclick="switchTab('reviews')">Отзывы</button>-->
                    <button class="tab-button" onclick="switchTab('collection')">Друзья</button>
                    <button class="tab-button" onclick="switchTab('developer')">Безопасность</button>
                </div>
            </div>

            <!-- Правая колонка: содержимое вкладок -->
            <div class="profile-right" style="flex: 1; min-width: 280px; color: white;">
                <!-- Вкладка "Профиль" (информация и статистика) -->
                    <h1><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                    <p>
                        <light>На платформе с:</light>
                        <?= date('d.m.Y', strtotime($user['added'])) ?>
                    </p>
                   <!--<p>@<?= htmlspecialchars($user['username']) ?></p>-->
                    <!--<h2><a style="color: white;" href="/l4t/<?= $username ?>">Профиль на L4T-->
                            <!--<svg style="vertical-align: middle;"
                                xmlns="http://www.w3.org/2000/svg"
                                width="32"
                                height="32"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#fff"
                                stroke-width="1"
                                stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                                <path d="M11 13l9 -9" />
                                <path d="M15 4h5v5" />
                            </svg>-->
                        </a></h2>
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


                <!-- Вкладка "Игры" -->
                <div id="tab-games" class="tab-content active">
                    <?php
                    // ВРЕМЕННО: создаём тестовые карточки, если игр нет (для предпросмотра карусели)
                    if (empty($games)) {
                        $games = [];
                        for ($i = 1; $i <= 7; $i++) {
                            $games[] = [
                                'id' => $i,
                                'name' => 'Тестовая игра ' . $i,
                                'description' => 'Описание тестовой игры для предпросмотра карусели',
                                'path_to_cover' => '/assets/default-game-cover.png',
                                'rating' => 4.5,
                                'price' => 0
                            ];
                        }
                    }
                    ?>

                    <div class="profile-card" style="overflow: visible; padding: 20px 10px;">
                        <h2 class="section-title">Коллекция игр (<?= count($games) ?>)</h2>

                        <?php if (!empty($games)): ?>
                            <div class="carousel-container">
                                <button class="carousel-btn prev" aria-label="Предыдущие игры">‹</button>
                                <div class="carousel-track-wrap">
                                    <div class="carousel-track" id="gameCarouselTrack">
                                        <?php foreach ($games as $index => $game): ?>
                                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
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
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button class="carousel-btn next" aria-label="Следующие игры">›</button>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Этот пользователь пока не сыграл ни в одну игру :(</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Вкладка "Отзывы" -->
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

                <!-- Вкладка "Коллекция" -->
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

                <!-- Вкладка "Для разработчиков" (только для владельца) -->
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
        </div>
    </section>

    <!-- Модальное окно для ачивок -->
    <div class="modal" id="achievementModal" style="display: none; position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: linear-gradient(135deg, #2a3344 0%, #1a1f2e 100%); border-radius: 20px; padding: 30px; max-width: 500px; width: 90%; position: relative; box-shadow: 0 25px 60px rgba(0,0,0,0.9); border: 2px solid rgba(255, 215, 0, 0.3);">
            <button class="close-btn" onclick="closeAchievementModal()" style="position: absolute; top: 15px; right: 15px; background: rgba(255, 215, 0, 0.2); border: 2px solid #ffd700; color: #ffd700; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 20px;">&times;</button>
            <div id="achievementModalBody"></div>
        </div>
    </div>

    <script>
        function showAchievementModal(ach) {
            const modal = document.getElementById('achievementModal');
            const body = document.getElementById('achievementModalBody');

            body.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 3em; margin-bottom: 15px;"><img src="${ach.icon}" alt="<?= htmlspecialchars($title) ?>" class="achievement-icon-img"></div>
                    <h2 style="color: #ffd700; margin-bottom: 10px;">${ach.title}</h2>
                    <p style="color: #b0b8c1; line-height: 1.5; margin-bottom: 20px;">${ach.description ?? ''}</p>
                    <div style="font-size: 0.9em; color: #888;">
                        Дата получения: ${ach.date ?? ''}
                    </div>
                    <button style="margin-top: 20px; padding: 10px 20px; border:none; border-radius:10px; background: linear-gradient(135deg,#ffd700,#ffbe0b); color:#000; font-weight:bold; cursor:pointer;" onclick="closeAchievementModal()">
                        Закрыть
                    </button>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeAchievementModal() {
            document.getElementById('achievementModal').style.display = 'none';
        }

        document.getElementById('achievementModal').addEventListener('click', function(e) {
            if (e.target === this) closeAchievementModal();
        });
    </script>

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
                        action: action,
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
                    alert(data.error || 'Чёт пошло по пизде');
                }
            });
        });
    </script>
<script>
    (function() {
        // Выбираем кнопки вкладок в левой колонке
        const tabButtons = document.querySelectorAll('.profile-left .tab-button');
        if (!tabButtons.length) return;

        function resetTilt(btn) {
            btn.style.transform = '';
        }

        function handleMouseMove(e) {
            const btn = e.currentTarget;
            const rect = btn.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            // Нормализация координат в диапазон -1..1
            const nx = (x / rect.width) * 2 - 1;
            const ny = (y / rect.height) * 2 - 1;

            const maxAngle = 8;               // мягкий наклон
            const rotateY = maxAngle * nx;
            const rotateX = -maxAngle * ny;

            const translateY = -3;             // подъём
            const scale = 1.06;                 // лёгкое увеличение

            btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
        }

        function handleMouseLeave(e) {
            resetTilt(e.currentTarget);
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('mousemove', handleMouseMove);
            btn.addEventListener('mouseleave', handleMouseLeave);
        });
    })();
</script>

<script>
    (function() {
        const track = document.getElementById('gameCarouselTrack');
        if (!track) return;

        const items = Array.from(track.children);
        const prevBtn = document.querySelector('.carousel-btn.prev');
        const nextBtn = document.querySelector('.carousel-btn.next');
        const itemWidth = items[0]?.getBoundingClientRect().width || 240;
        const gap = 20; // должно совпадать с gap в CSS

        let currentIndex = 0; // индекс активного элемента

        // Функция обновления классов и позиции
        function updateCarousel(index) {
            // Ограничиваем индекс
            if (index < 0) index = 0;
            if (index >= items.length) index = items.length - 1;

            currentIndex = index;

            // Сдвигаем трек так, чтобы активный элемент был по центру
            const trackWidth = track.offsetWidth;
            const containerWidth = track.parentElement.offsetWidth;
            const targetX = -(currentIndex * (itemWidth + gap)) + (containerWidth / 2) - (itemWidth / 2);
            track.style.transform = `translateX(${targetX}px)`;

            // Обновляем класс active
            items.forEach((item, i) => {
                item.classList.toggle('active', i === currentIndex);
            });
        }

        // Обработчики кнопок
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                updateCarousel(currentIndex - 1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                updateCarousel(currentIndex + 1);
            });
        }

        // Инициализация
        updateCarousel(0);

        // При изменении размера окна пересчитываем
        window.addEventListener('resize', () => {
            // Пересчитываем ширину элемента (может измениться при адаптиве)
            // Для простоты просто переинициализируем с текущим индексом
            updateCarousel(currentIndex);
        });
    })();
</script>

</body>

</html>