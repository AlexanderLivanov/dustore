<?php
session_start();
require_once('swad/config.php');

$db = new Database();
$pdo = $db->connect();

/* Страница показывает СВОЮ коллекцию.
   Было: $user_id = $_GET['user_id'] без единой проверки — любой мог
   подставить чужой id и посмотреть чью угодно библиотеку и предметы.
   Инъекции там не было (запрос параметризован), но приватность утекала.
   Чужие профили живут на /player/<username>, где показывается только
   то, что задумано публичным. */
$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: /login');
    exit;
}

// Запрос для получения информации о пользователе
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt_user->execute([':user_id' => $user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Если пользователь не найден, показываем заглушку
if (!$user) {
    $user = [
        'id' => 0,
        'first_name' => 'Гость',
        'last_name' => '',
        'username' => 'guest',
        'profile_picture' => '/swad/static/img/logo.svg'
    ];
}

/* Предметы пользователя + данные игр ОДНИМ запросом.
   Было: SELECT * FROM library, потом в цикле по каждой строке отдельный
   SELECT ... FROM games. У человека со ста играми — сто запросов на
   загрузку страницы. LEFT JOIN, потому что коллекционные предметы
   (game_id = 0 или NULL) игрой не подкреплены и должны остаться в выдаче. */
$stmt_items = $pdo->prepare("
    SELECT l.*,
           g.name            AS g_name,
           g.description     AS g_description,
           g.path_to_cover   AS g_cover,
           g.price           AS g_price
      FROM library l
      LEFT JOIN games g ON g.id = l.game_id
     WHERE l.player_id = :user_id
     ORDER BY l.rarity DESC, l.date DESC
     LIMIT 500
");
$stmt_items->execute([':user_id' => $user_id]);
$all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Разделяем предметы на игры и коллекционные предметы
$games = [];
$collectibles = [];

foreach ($all_items as $item) {
    if (!empty($item['game_id']) && $item['game_id'] > 0) {
        // Игра могла быть удалена — тогда JOIN отдаст NULL, и в выдачу
        // такую строку не берём (прежний код вёл себя так же)
        if ($item['g_name'] !== null) {
            $item['title']       = $item['g_name'];
            $item['description'] = $item['g_description'];
            $item['cover_image'] = $item['g_cover'];
            $item['price']       = $item['g_price'];
            $item['item_type']   = 'game';
            $games[] = $item;
        }
    } else {
        // Это коллекционный предмет
        // Предположим, что у коллекционных предметов есть свои поля в той же таблице
        $item['item_type'] = 'collectible';
        // Если нет отдельной таблицы, используем существующие поля
        if (empty($item['title'])) {
            $item['title'] = 'Коллекционный предмет #' . $item['id'];
        }
        if (empty($item['description'])) {
            $item['description'] = 'Особый коллекционный предмет';
        }
        $collectibles[] = $item;
    }
}

// Если нет поля item_type, используем альтернативную логику:
// $games = array_filter($all_items, fn($item) => $item['game_id'] > 0);
// $collectibles = array_filter($all_items, fn($item) => empty($item['game_id']) || $item['game_id'] == 0);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Коллекция <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> — Dustore</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0e27;
            min-height: 100vh;
            color: #fff;
        }

        main {
            padding: 40px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .user-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(255, 0, 110, 0.1), rgba(0, 245, 255, 0.1));
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 4px solid #00f5ff;
            box-shadow: 0 0 30px rgba(0, 245, 255, 0.5);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff006e, #00f5ff, #ffbe0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 150px;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #00f5ff;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }

        /* Стили для полок */
        .shelf-container {
            margin-top: 50px;
        }

        .shelf-title {
            font-size: 2em;
            margin-bottom: 20px;
            color: #ffbe0b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .shelf {
            margin-bottom: 80px;
            position: relative;
        }

        .shelf-bar {
            height: 15px;
            background: linear-gradient(180deg, #3d4a5c 0%, #2a3344 50%, #1a1f2e 100%);
            border-radius: 8px;
            margin-bottom: 40px;
            position: relative;
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.8),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 2px rgba(0, 0, 0, 0.5);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
            padding: 0 10px;
        }

        /* Карточка предмета */
        .item-card {
            width: 200px;
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
        }

        .item-rarity {
            font-size: 0.85em;
            opacity: 0.9;
            padding: 3px 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.6);
            display: inline-block;
            margin-top: 5px;
        }

        /* Эффекты эпичности на основе rarity (0-4) */
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

        /* Специальные эффекты (можно хранить в purchased или отдельном поле) */
        .item-card[data-effect="polychrome"] .item-cover {
            background: linear-gradient(135deg, #ff006e 0%, #00f5ff 25%, #ffbe0b 50%, #8338ec 75%, #ff006e 100%);
            background-size: 400% 400%;
            animation: polyShift 4s ease infinite;
        }

        .item-card[data-effect="holographic"] .item-cover::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(115deg,
                    rgba(255, 0, 150, 0.3),
                    rgba(0, 200, 255, 0.3),
                    rgba(255, 255, 0, 0.25),
                    rgba(0, 255, 200, 0.3),
                    rgba(255, 0, 150, 0.3));
            background-size: 400% 400%;
            mix-blend-mode: screen;
            animation: holoFlow 8s ease-in-out infinite;
        }

        .item-card[data-effect="negative"] .item-cover {
            filter: invert(1);
        }

        @keyframes polyShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes holoFlow {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #2a3344 0%, #1a1f2e 100%);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            position: relative;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9);
            animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2px solid rgba(0, 245, 255, 0.3);
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 0, 110, 0.2);
            border: 2px solid #ff006e;
            color: #ff006e;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 1001;
        }

        .close-btn:hover {
            background: #ff006e;
            color: #fff;
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 0 20px rgba(255, 0, 110, 0.6);
        }

        .no-items {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 2px dashed rgba(255, 255, 255, 0.1);
            grid-column: 1 / -1;
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
        }

        .rarity-text {
            text-transform: uppercase;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <div class="container">
            <!-- Заголовок пользователя -->
            <div class="user-header">
                <div class="user-avatar">
                    <img src="<?= htmlspecialchars($user['profile_picture'] ?? '/swad/static/img/default-avatar.png') ?>"
                        alt="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                </div>
                <h1 class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                <p>@<?= htmlspecialchars($user['username'] ?? 'user') ?></p>

                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-value"><?= count($games) ?></div>
                        <div class="stat-label">Игр в коллекции</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?= count($collectibles) ?></div>
                        <div class="stat-label">Коллекционных предметов</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?= count($all_items) ?></div>
                        <div class="stat-label">Всего предметов</div>
                    </div>
                </div>
            </div>

            <!-- Полка с играми -->
            <div class="shelf-container">
                <h2 class="shelf-title">🎮 Игры</h2>
                <div class="shelf">
                    <div class="shelf-bar"></div>
                    <div class="cards-grid">
                        <?php if (empty($games)): ?>
                            <div class="no-items">
                                <p>Игр пока нет в коллекции</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($games as $item): ?>
                                <?php
                                $rarity = $item['rarity'] ?? 0;
                                $effect = $item['purchased'] ?? ''; // Используем поле purchased для спецэффектов
                                $cover = $item['cover_image'] ?? '/swad/static/img/default-game.jpg';
                                $title = $item['title'] ?? 'Игра #' . $item['id'];
                                $description = $item['description'] ?? 'Игра из вашей коллекции';
                                $purchase_date = $item['date'] ?? $item['purchased'] ?? date('Y-m-d');
                                ?>
                                <div class="item-card"
                                    data-id="<?= $item['id'] ?>"
                                    data-rarity="<?= $rarity ?>"
                                    data-effect="<?= htmlspecialchars($effect) ?>"
                                    data-type="game"
                                    data-title="<?= htmlspecialchars($title) ?>"
                                    data-description="<?= htmlspecialchars($description) ?>"
                                    data-date="<?= htmlspecialchars($purchase_date) ?>"
                                    data-game-id="<?= $item['game_id'] ?>">
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

                <!-- Полка с коллекционными предметами -->
                <h2 class="shelf-title">🏆 Коллекционные предметы</h2>
                <div class="shelf">
                    <div class="shelf-bar"></div>
                    <div class="cards-grid">
                        <?php if (empty($collectibles)): ?>
                            <div class="no-items">
                                <p>Коллекционных предметов пока нет</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($collectibles as $item): ?>
                                <?php
                                $rarity = $item['rarity'] ?? 0;
                                $effect = $item['purchased'] ?? ''; // Используем поле purchased для спецэффектов
                                $cover = '/swad/static/img/default-collectible.jpg'; // Заглушка для коллекционных предметов
                                $title = $item['title'] ?? 'Коллекционный предмет #' . $item['id'];
                                $description = $item['description'] ?? 'Особый коллекционный предмет';
                                $purchase_date = $item['date'] ?? $item['purchased'] ?? date('Y-m-d');
                                ?>
                                <div class="item-card"
                                    data-id="<?= $item['id'] ?>"
                                    data-rarity="<?= $rarity ?>"
                                    data-effect="<?= htmlspecialchars($effect) ?>"
                                    data-type="collectible"
                                    data-title="<?= htmlspecialchars($title) ?>"
                                    data-description="<?= htmlspecialchars($description) ?>"
                                    data-date="<?= htmlspecialchars($purchase_date) ?>">
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
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <!-- Модальное окно -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeBtn">&times;</button>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        const cards = document.querySelectorAll('.item-card');
        const modal = document.getElementById('modal');
        const modalBody = document.getElementById('modalBody');
        const closeBtn = document.getElementById('closeBtn');

        // Маппинг редкости для отображения
        const rarityMap = {
            0: {
                name: 'Обычная',
                color: '#a0a0a0'
            },
            1: {
                name: 'Необычная',
                color: '#00ff00'
            },
            2: {
                name: 'Редкая',
                color: '#007bff'
            },
            3: {
                name: 'Эпическая',
                color: '#800080'
            },
            4: {
                name: 'Легендарная',
                color: '#ffd700'
            }
        };

        // Эффект слежения за курсором для полихромных карточек
        cards.forEach(card => {
            const effect = card.getAttribute('data-effect');

            if (effect && effect.includes('polychrome')) {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const rotateX = (centerY - y) / 8;
                    const rotateY = (x - centerX) / 8;

                    const angle = Math.atan2(rotateY, rotateX) * (180 / Math.PI);

                    const cover = card.querySelector('.item-cover');
                    const originalBg = cover.style.backgroundImage;
                    const bgColor = `linear-gradient(${angle}deg, #ff006e 0%, #00f5ff 25%, #ffbe0b 50%, #8338ec 75%, #ff006e 100%)`;

                    cover.style.backgroundImage = `${bgColor}, ${originalBg}`;

                    card.style.transform = `
                        rotateX(${rotateX}deg) 
                        rotateY(${rotateY}deg) 
                        scale(1.05)
                        translateZ(20px)
                    `;
                });

                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'rotateX(0) rotateY(0) scale(1) translateZ(0)';
                    const cover = card.querySelector('.item-cover');
                    const originalBg = cover.getAttribute('style')?.match(/background-image: url\(['"](.*?)['"]\)/);
                    if (originalBg && originalBg[1]) {
                        cover.style.backgroundImage = `url('${originalBg[1]}')`;
                    }
                });
            } else {
                // Для остальных карточек простое наведение
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-10px) scale(1.02)';
                });

                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            }

            // Открытие модального окна
            card.addEventListener('click', () => {
                const title = card.getAttribute('data-title');
                const description = card.getAttribute('data-description');
                const rarity = parseInt(card.getAttribute('data-rarity'));
                const type = card.getAttribute('data-type');
                const date = card.getAttribute('data-date');
                const gameId = card.getAttribute('data-game-id');
                const effect = card.getAttribute('data-effect');

                const rarityInfo = rarityMap[rarity] || rarityMap[0];

                let effectInfo = '';
                if (effect) {
                    effectInfo = `
                        <div style="margin: 15px 0; padding: 10px; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                            <strong>Спецэффект:</strong> ${effect}
                        </div>
                    `;
                }

                modalBody.innerHTML = `
                    <div style="text-align: center;">
                        <div style="font-size: 4em; margin-bottom: 20px;">
                            ${type === 'game' ? '🎮' : '🏆'}
                        </div>
                        <h2 style="color: #00f5ff; margin-bottom: 10px; font-size: 1.8em;">${title}</h2>
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
                        ${effectInfo}
                        <p style="color: #b0b8c1; margin-bottom: 20px; line-height: 1.6; font-size: 1.1em;">
                            ${description}
                        </p>
                        <div style="display: flex; justify-content: space-between; color: #888; font-size: 0.9em; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <div>
                                <div style="font-weight: bold; color: #aaa;">Тип</div>
                                <div>${type === 'game' ? 'Игра' : 'Коллекционный предмет'}</div>
                            </div>
                            <div>
                                <div style="font-weight: bold; color: #aaa;">Добавлено</div>
                                <div>${date}</div>
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
                                onclick="modal.classList.remove('active')">
                            Закрыть
                        </button>
                    </div>
                `;

                modal.classList.add('active');
            });
        });

        // Закрытие модального окна
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Анимация появления карточек
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.item-card');
            cards.forEach((card, index) => {
                card.style.opacity = "0";
                card.style.transform = "translateY(30px)";
                setTimeout(() => {
                    card.style.transition = "0.5s ease";
                    card.style.opacity = "1";
                    card.style.transform = "translateY(0)";
                }, index * 50);
            });
        });
    </script>
</body>

</html>