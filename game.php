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

$userRating = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    $userId = $_SESSION['USERDATA']['id'];
    $userRating = $gameController->userHasRated($game_id, $userId) ?? 0;
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
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .lightbox .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            color: white;
            cursor: pointer;
            user-select: none;
            padding: 0 15px;
            z-index: 10001;
        }

        .lightbox .arrow-left {
            left: 10px;
        }

        .lightbox .arrow-right {
            right: 10px;
        }

        .review-form {
            margin-top: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .review-form h2 {
            margin-bottom: 10px;
            font-size: 1.5rem;
            color: #fff;
        }

        .review-form textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.4);
            color: #fff;
            padding: 10px;
            font-size: 1rem;
            resize: vertical;
        }

        .review-form select {
            border-radius: 10px;
            padding: 5px 10px;
            background: rgba(0, 0, 0, 0.4);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .review-form button {
            align-self: flex-start;
            padding: 8px 20px;
            border: none;
            border-radius: 10px;
            background-color: #74155d;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .review-form button:hover {
            background-color: #14041d;
            color: #fff;
        }

        #review-stars span {
            font-size: 24px;
            color: #666;
            cursor: pointer;
            margin-right: 5px;
            transition: color 0.2s;
        }

        #review-stars span:hover,
        #review-stars span.highlighted {
            color: #ffcc00;
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

                        <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                            <div class="rating-section" style="margin-top: 20px;">
                                <h2>Ваша оценка игре</h2>
                                <div id="rating-stars" data-game-id="<?= $game_id ?>" data-user-rating="<?= $userRating ?>"></div>
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

                        <div class="reviews-section">
                            <h2>Отзывы игроков</h2>
                            <div id="reviews-container">
                                <!-- Здесь JS добавляет отзывы -->
                                <p>Загрузка отзывов...</p>
                            </div>
                            <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                                <div class="review-form" style="margin-top: 30px;">
                                    <h2>Оставить отзыв</h2>
                                    <textarea id="review-text" placeholder="Напишите ваш отзыв... Мат, спам и оскорбления будут удалены модераторами" rows="4"></textarea>
                                    <div style="margin-top:10px;">
                                        <label>Ваша оценка: </label>
                                        <div id="review-stars" style="display:inline-block;"></div>
                                    </div>
                                    <button class="btn" style="margin-top:10px;" id="submit-review">Отправить</button>
                                </div>
                            <?php endif; ?>

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

        document.addEventListener('DOMContentLoaded', () => {
            const gameId = <?= $game_id ?>;
            const reviewsContainer = document.getElementById('reviews-container');

            fetch(`/swad/controllers/get_reviews.php?game_id=${gameId}`)
                .then(res => res.json())
                .then(data => {
                    const reviewsContainer = document.getElementById('reviews-container');

                    if (!data.success) {
                        reviewsContainer.innerHTML = '<p>Не удалось загрузить отзывы.</p>';
                        return;
                    }

                    const reviews = data.reviews;
                    if (reviews.length === 0) {
                        reviewsContainer.innerHTML = '<p>Отзывы пока отсутствуют. Будьте первым!</p>';
                    } else {
                        reviewsContainer.innerHTML = '';
                        let userHasReview = false;

                        reviews.forEach(review => {
                            if (review.user_id == <?= $_SESSION['USERDATA']['id'] ?? 0 ?>) {
                                userHasReview = true;
                            }
                            const div = document.createElement('div');
                            div.className = 'review-card';
                            div.innerHTML = `
                    <div class="review-header">
                        <div class="review-author">
                            <div class="author-avatar">
                                <img style="width: 100%; border-radius: 10000px;" src="${review.profile_picture || '/swad/static/img/logo_new.png'}" alt="${review.username}">
                            </div>
                            <div>
                                <h3>${review.username || "Аноним"}</h3>
                                <div>★ ${review.rating}</div>
                            </div>
                        </div>
                        <div class="review-date">${new Date(review.created_at).toLocaleString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}</div>
                    </div>
                    <p>${review.text}</p>
                `;
                            reviewsContainer.appendChild(div);
                        });

                        // После загрузки отзывов
                        if (userHasReview) {
                            const form = document.querySelector('.review-form');
                            if (form) {
                                const textarea = form.querySelector('#review-text');
                                const stars = form.querySelectorAll('#review-stars span');
                                const button = form.querySelector('#submit-review');

                                if (textarea) textarea.disabled = true;
                                stars.forEach(s => s.style.pointerEvents = 'none');
                                if (button) button.disabled = true;

                                const notice = document.createElement('p');
                                notice.textContent = 'Спасибо! Ваш отзыв принят.';
                                notice.style.color = '#ffcc00';
                                form.innerHTML = '';
                                form.appendChild(notice);
                            }
                        }

                    }
                });

        });

        document.addEventListener('DOMContentLoaded', () => {
            const reviewStars = document.getElementById('review-stars');
            const submitBtn = document.getElementById('submit-review');
            let selectedRating = 10; // по умолчанию

            if (reviewStars) {
                // Генерируем звёздочки
                for (let i = 1; i <= 10; i++) {
                    const star = document.createElement('span');
                    star.textContent = '★';
                    star.dataset.value = i;
                    star.addEventListener('mouseover', () => highlightStars(i));
                    star.addEventListener('mouseout', () => highlightStars(selectedRating));
                    star.addEventListener('click', () => {
                        selectedRating = i;
                        highlightStars(selectedRating);
                    });
                    reviewStars.appendChild(star);
                }
                highlightStars(selectedRating);

                function highlightStars(n) {
                    Array.from(reviewStars.children).forEach((s, idx) => {
                        s.classList.toggle('highlighted', idx < n);
                    });
                }
            }

            if (submitBtn) {
                submitBtn.addEventListener('click', () => {
                    const text = document.getElementById('review-text').value.trim();
                    if (!text) {
                        alert('Введите текст отзыва!');
                        return;
                    }

                    fetch('/swad/controllers/submit_review.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `game_id=<?= $game_id ?>&rating=${selectedRating}&text=${encodeURIComponent(text)}`
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Добавляем отзыв сразу в контейнер
                                const reviewsContainer = document.getElementById('reviews-container');
                                const div = document.createElement('div');
                                div.className = 'review-card';
                                const now = new Date();
                                const reviewDate = new Date(review.created_at.replace(' ', 'T'));
                                const dateStr = reviewDate.toLocaleString('ru-RU', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                                div.innerHTML = `
                        <div class="review-header">
                            <div class="review-author">
                                <div class="author-avatar">
                                    <img style="width: 100%; border: 10000px;" src="<?= !empty($_SESSION['USERDATA']['']) ? $_SESSION['USERDATA']['avatar'] : 'swad/static/img/logo_new.png' ?>" alt="<?= $_SESSION['USERDATA']['profile_picture'] ?>">
                                </div>
                                <div>
                                    <h3><?= $_SESSION['USERDATA']['username'] ?></h3>
                                    <div>★ ${selectedRating}</div>
                                </div>
                            </div>
                            <<div class="review-date">${dateStr}</div>

                        </div>
                        <p>${text}</p>
                    `;
                                if (reviewsContainer.querySelector('p')?.textContent.includes('Отзывы пока отсутствуют')) {
                                    reviewsContainer.innerHTML = '';
                                }
                                reviewsContainer.prepend(div);

                                // Очистка формы
                                document.getElementById('review-text').value = '';
                                selectedRating = 10;
                                highlightStars(selectedRating);
                            } else {
                                alert('Ошибка: ' + data.error);
                            }
                        })
                        .catch(err => console.error(err));
                });
            }
        });
    </script>
</body>

</html>