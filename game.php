<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/game.php');

// Получаем ID игры из URL
$game_id = $_GET['name'] ?? '';

// Если ID не указан или невалиден - редирект
if ($game_id <= 0) {
    header('Location: /explore');
    exit();
}

// Получаем информацию об игре
$gameController = new Game();
$game = $gameController->getGameById($game_id);

if (!$game) {
    header('Location: /explore');
    exit();
}

if (empty($game['status']) || strtolower($game['status']) !== 'published') {
    header('Location: /explore');
    exit();
}

$screenshots = json_decode($game['screenshots'], true) ?: [];

// Получаем особенности
$features = json_decode($game['features'], true) ?: [];

// Получаем системные требования
$requirements = json_decode($game['requirements'], true) ?: [];

// Получаем достижения
$achievements = json_decode($game['achievements'], true) ?: [];

// Получаем бейджи
$badges = !empty($game['badges']) ? explode(',', $game['badges']) : [];

// Получаем платформы
$platforms = !empty($game['platforms']) ? explode(',', $game['platforms']) : [];

function formatFileSize($bytes)
{
    if ($bytes < 1024) {
        return $bytes . ' Б';
    } elseif ($bytes < 1048576) { // 1024 * 1024
        return round($bytes / 1024, 2) . ' КБ';
    } elseif ($bytes < 1073741824) { // 1024^3
        return round($bytes / 1048576, 2) . ' МБ';
    } else {
        return round($bytes / 1073741824, 2) . ' ГБ';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - <?= htmlspecialchars($game['name']) ?></title>
    <link rel="stylesheet" href="/swad/css/gamepage.css">
    <!-- TODO: GAME's icon -->
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <style>
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
    </style>
    <script src="/swad/js/CartManager.js"></script>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="game-hero">
            <!-- Баннер игры -->
            <div class="game-banner" style="background-image: url('<?= !empty($game['banner_url']) ? htmlspecialchars($game['banner_url']) : '' ?>')"></div>

            <div class="container">
                <div class="game-content">
                    <div class="game-main">
                        <div class="game-header">
                            <div class="game-logo">
                                <!-- Обложка игры -->
                                <img class="game-logo" src="<?= !empty($game['path_to_cover']) ? htmlspecialchars($game['path_to_cover']) : '/swad/static/img/hg-icon.jpg' ?>" alt="<?= htmlspecialchars($game['name']) ?>">
                            </div>
                            <div class="game-info-header">
                                <h1><?= htmlspecialchars($game['name']) ?></h1>

                                <!-- Бейджи игры -->
                                <div class="game-badges">
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="game-badge"><?= htmlspecialchars(trim($badge)) ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Статистика игры -->
                                <div class="game-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= htmlspecialchars($game['GQI']) ?>/100</div>
                                        <div class="stat-label">GQI</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= date('d.m.Y', strtotime($game['release_date'])) ?></div>
                                        <div class="stat-label">Дата выпуска</div>
                                    </div>
                                    <?php
                                    $ratingData = $gameController->getAverageRating($game_id);
                                    ?>
                                    <div class="stat-item">
                                        <?php if ($ratingData['count'] > 0): ?>
                                            <div class="stat-value"><?= $ratingData['avg'] ?>/10 </div>
                                            <div class="stat-label">Оценили: <?= $ratingData['count'] ?></div>
                                        <?php else: ?>
                                            <div class="stat-value">???</div>
                                            <div class="stat-label">Ещё нет оценок. Будьте первыми.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Описание игры -->
                        <div class="game-description">
                            <p><?= nl2br(htmlspecialchars($game['description'])) ?></p>
                        </div>

                        <?php if (!empty($_SESSION['USERDATA'])): ?>
                            <div class="rating-section" style="margin-top: 20px;">
                                <h2>Ваша оценка игре</h2>
                                <div id="rating-stars" data-game-id="<?= $game_id ?>"></div>
                            </div>
                        <?php else: ?>
                            <div class="rating-section" style="margin-top: 20px; opacity: 0.6;">
                                <h2>Оценить игру</h2>
                                <p>Войдите в аккаунт, чтобы поставить оценку.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Особенности игры -->
                        <?php if (!empty($features)): ?>
                            <div class="game-features">
                                <h2>Особенности игры</h2>
                                <div class="features-list">
                                    <?php foreach ($features as $feature): ?>
                                        <div class="feature-item">
                                            <div class="feature-icon"><?= htmlspecialchars($feature['icon']) ?></div>
                                            <div>
                                                <h3><?= htmlspecialchars($feature['title']) ?></h3>
                                                <p><?= htmlspecialchars($feature['description']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Трейлер игры -->
                        <?php if (!empty($game['trailer_url'])): ?>
                            <h2>Трейлер</h2>
                            <div class="trailer-container">
                                <iframe src="<?= htmlspecialchars($game['trailer_url']) ?>" width="640" height="360" frameborder="0" allowfullscreen="1" allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>
                            </div>
                        <?php endif; ?>

                        <!-- Скриншоты игры -->
                        <?php if (!empty($screenshots)): ?>
                            <h2>Скриншоты</h2>
                            <div class="screenshots-grid">
                                <?php foreach ($screenshots as $screenshot): ?>
                                    <div class="screenshot"
                                        style="background: url('<?= htmlspecialchars($screenshot['path']) ?>') no-repeat center center / cover;"
                                        data-fullsize="<?= htmlspecialchars($screenshot['path']) ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Системные требования -->
                        <?php if (!empty($requirements)): ?>
                            <div class="system-requirements">
                                <h2>Системные требования</h2>
                                <div class="requirements-grid">
                                    <?php foreach ($requirements as $requirement): ?>
                                        <div class="requirement-item">
                                            <div class="requirement-label"><?= htmlspecialchars($requirement['label']) ?></div>
                                            <div class="requirement-value"><?= htmlspecialchars($requirement['value']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Отзывы (статичные) -->
                        <div class="reviews-section">
                            <h2>Отзывы игроков</h2>
                            <h3>Отзывы пока не работают</h3>
                            <!-- <div class="review-card">
                                <div class="review-header">
                                    <div class="review-author">
                                        <div class="author-avatar"></div>
                                        <div>
                                            <h3>Игрок123</h3>
                                            <div>★ 10</div>
                                        </div>
                                    </div>
                                    <div class="review-date">19.06.2025</div>
                                </div>
                                <p>Невероятная игра! Сюжет затягивает с первых минут, графика на высоте, а саундтрек просто бомбический. Потратил уже 10 часов и не могу оторваться.</p>
                            </div> -->
                        </div>
                    </div>

                    <div class="game-sidebar">
                        <div class="purchase-section">
                            <?php if ($game['price'] > 0): ?>
                                <div class="game-price"><?= number_format($game['price'], 0, ',', ' ') ?> ₽</div>
                                <h3 style="color: coral;">Оплата и покупка игры пока не работают!</h3>
                                <p>Мы всё ещё разрабатываем Платформу...</p>
                                <br>
                                <div class="cart-controls" id="cart-controls-<?= $game_id ?>">
                                    <!-- Будет заполнено JavaScript -->
                                </div>

                                <button class="btn" style="width: 100%; margin-bottom: 15px;" onclick="location.href='/checkout'">Купить сейчас</button>

                                <div style="margin-top: 20px; font-size: 0.9rem; opacity: 0.8;">
                                    <?php if ($game['in_subscription']): ?>
                                        <p>✔️ Есть в подписке</p>
                                    <?php endif; ?>
                                    <p>✔️ Высокий рейтинг</p>
                                </div>

                            <?php else: ?>
                                <!-- Бесплатная игра -->
                                <div style="text-align: center;">
                                    <div class="game-price" style="font-size: 1.4rem; color: #00ff99; margin-bottom: 10px;">
                                        Бесплатно
                                    </div>

                                    <?php if (!empty($game['game_zip_url'])): ?>
                                        <button class="btn" style="width: 100%; margin-bottom: 10px;"
                                            onclick="window.location.href='<?= htmlspecialchars($game['game_zip_url']) ?>'">
                                            Скачать игру
                                        </button>
                                        <?php if (!empty($game['game_zip_size'])): ?>
                                            <div style="font-size: 0.9rem; opacity: 0.8;">
                                                Размер: <?= htmlspecialchars(formatFileSize((int)$game['game_zip_size'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p style="color: orange;">Файл игры пока не загружен</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>


                        <!-- Информация о разработчике -->
                        <div class="developer-info">
                            <div class="developer-logo">🏢</div>
                            <div>
                                <h3><?= htmlspecialchars($game['studio_name']) ?></h3>
                                <p>Основана в <?= date('Y', strtotime($game['studio_founded'])) ?></p>
                            </div>
                        </div>

                        <button class="btn btn-secondary" style="width: 100%; margin-bottom: 20px;" onclick="location.href='/d/<?= htmlspecialchars($game['studio_slug']) ?>'">
                            Все игры разработчика
                        </button>

                        <!-- Дополнительная информация -->
                        <div style="background: rgba(255,255,255,0.1); border-radius: 15px; padding: 20px;">
                            <h3>Информация о игре</h3>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Жанры:</span>
                                    <span><?= htmlspecialchars($game['genre']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Платформы:</span>
                                    <span>
                                        <?php
                                        $platform_names = [];
                                        foreach ($platforms as $platform) {
                                            switch ($platform) {
                                                case 'windows':
                                                    $platform_names[] = 'Windows';
                                                    break;
                                                case 'linux':
                                                    $platform_names[] = 'Linux';
                                                    break;
                                                case 'macos':
                                                    $platform_names[] = 'MacOS';
                                                    break;
                                                case 'android':
                                                    $platform_names[] = 'Android';
                                                    break;
                                                case 'web':
                                                    $platform_names[] = 'Web';
                                                    break;
                                                default:
                                                    $platform_names[] = ucfirst($platform);
                                            }
                                        }
                                        echo htmlspecialchars(implode(', ', $platform_names));
                                        ?>
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span>Языки:</span>
                                    <span><?= htmlspecialchars($game['languages']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                                    <span>Возрастной рейтинг:</span>
                                    <span><?= htmlspecialchars($game['age_rating']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Достижения -->
                        <?php if (!empty($achievements)): ?>
                            <div style="margin-top: 30px; background: rgba(255,255,255,0.05); border-radius: 15px; padding: 20px;">
                                <h3>Достижения</h3>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px;">
                                    <?php foreach ($achievements as $achievement): ?>
                                        <div style="text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                            <div style="font-size: 2rem;"><?= htmlspecialchars($achievement['icon']) ?></div>
                                            <div style="font-size: 0.9rem;"><?= htmlspecialchars($achievement['title']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация элементов при загрузке
            const animateElements = [
                ...document.querySelectorAll('.game-logo, .stat-item'),
                ...document.querySelectorAll('.screenshot'),
                ...document.querySelectorAll('.feature-item'),
                ...document.querySelectorAll('.review-card')
            ];

            animateElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `all 0.5s ease ${index * 0.1}s`;

                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100);
            });

            // Лайтбокс для скриншотов
            const screenshots = document.querySelectorAll('.screenshot');
            screenshots.forEach(screenshot => {
                screenshot.addEventListener('click', function() {
                    const fullsizeUrl = this.dataset.fullsize;
                    const lightbox = document.createElement('div');
                    lightbox.className = 'lightbox';
                    lightbox.innerHTML = `<img src="${fullsizeUrl}" alt="Full size screenshot">`;

                    lightbox.addEventListener('click', function() {
                        document.body.removeChild(lightbox);
                    });

                    document.body.appendChild(lightbox);
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const gameId = <?= $game_id ?>;
            window.gameCartManager = new GameCartManager(gameId);
        });

        document.addEventListener('DOMContentLoaded', () => {
            const ratingContainer = document.getElementById('rating-stars');
            if (!ratingContainer) return;

            const gameId = ratingContainer.dataset.gameId;
            for (let i = 1; i <= 10; i++) {
                const star = document.createElement('span');
                star.textContent = '★';
                star.style.cursor = 'pointer';
                star.style.fontSize = '24px';
                star.style.color = '#666';
                star.dataset.value = i;
                star.addEventListener('mouseover', () => highlightStars(i));
                star.addEventListener('mouseout', resetStars);
                star.addEventListener('click', () => submitRating(i));
                ratingContainer.appendChild(star);
            }

            function highlightStars(n) {
                document.querySelectorAll('#rating-stars span').forEach((s, idx) => {
                    s.style.color = idx < n ? '#ffcc00' : '#666';
                });
            }

            function resetStars() {
                document.querySelectorAll('#rating-stars span').forEach(s => s.style.color = '#666');
            }

            function submitRating(rating) {
                fetch('/swad/controllers/rate_game.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `game_id=${gameId}&rating=${rating}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // alert(`Спасибо! Ваша оценка: ${rating}`);
                            location.reload();
                        } else {
                            alert('Ошибка: ' + data.error);
                        }
                    });
            }
        });
    </script>
</body>

</html>