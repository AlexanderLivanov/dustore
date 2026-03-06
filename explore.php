<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();
$games = $gameController->getLatestGames();


$games = array_filter($games, function ($game) {
    return isset($game['status']) && strtolower($game['status']) === 'published';
});

$adultSection = isset($_GET['adult']) && $_GET['adult'] == 1;

if ($adultSection) {
    $games = array_filter($games, function ($game) {
        return isset($game['age_rating']) && intval($game['age_rating']) >= 18;
    });
} else {
    $games = array_filter($games, function ($game) {
        return !isset($game['age_rating']) || intval($game['age_rating']) < 18;
    });
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Каталог игр</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <?php require_once('swad/controllers/ymcounter.php'); ?>
    <!-- Yandex.RTB -->
    <script>
        window.yaContextCb = window.yaContextCb || []
    </script>
    <script src="https://yandex.ru/ads/system/context.js" async></script>
</head>
<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="games-list">
            <div class="container">
                <?php if (isset($_GET['adult']) && $_GET['adult'] == 1): ?>
                    <div class="warning-adult">
                        Внимание! Данный раздел содержит игры, предназначенные только для пользователей старше 18 лет
                        в соответствии с законодательством РФ.
                    </div>
                <?php endif; ?>

                <!-- Поиск теперь сверху -->
                <div class="search-wrapper">
                    <div class="search-bar">
                        <span class="search-icon"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg></span>
                        <input type="text" placeholder="Введите название игры или тикер разработчика...">
                    </div>
                </div>

                <!-- Ряд с фильтрами и сеткой -->
                <div class="content-row">
                    <div class="games-controls">
                        <div class="controls-left">
                            <a href="?adult=0" class="btn-filter <?= (!isset($_GET['adult']) || $_GET['adult'] == 0) ? 'active' : '' ?>">
                                Все игры
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                18+
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Экшен
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                RPG
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Стратегии
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Приключения
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Симуляторы
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                RPG
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Визуальные новеллы
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Инди
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Настольные
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Roguelite
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Хоррор-игры
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Выживание
                            </a>
                            <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                                Экономические
                            </a>
                        </div>
                    </div>

                    <div class="games-grid">
                        <?php if (empty($games)): ?>
                            <div class="no-games-message">
                                <p>Игры еще не добавлены в каталог</p>
                            </div>
                        <?php else: ?>
                            <?php
                            $maxTiles = 10000;
                            $i = 0;
                            foreach ($games as $game):
                                if ($i >= $maxTiles) break;
                                $i++;

                                // Каждая 6-я плитка — реклама
                                if ($i % 6 === 0): ?>
                                    <div class="game-card ad-card">
                                        <div class="game-image">
                                            <img src="/images/ad-banner.jpg" alt="Реклама">
                                        </div>
                                        <div class="game-info">
                                            <!-- Yandex.RTB R-A-18474572-3 -->
                                            <div id="yandex_rtb_R-A-18474572-3"></div>
                                            <script>
                                                window.yaContextCb.push(() => {
                                                    Ya.Context.AdvManager.render({
                                                        "blockId": "R-A-18474572-3",
                                                        "renderTo": "yandex_rtb_R-A-18474572-3"
                                                    })
                                                })
                                            </script>
                                        </div>
                                    </div>
                                    <?php continue; ?>
                                <?php endif;

                                $badge = '';
                                $badgeClass = '';
                                if ($game['price'] == 0) {
                                    $badge = 'Бесплатно';
                                    $badgeClass = 'free';
                                } elseif ((time() - strtotime($game['release_date'])) < (30 * 24 * 60 * 60)) {
                                    $badge = 'Новинка';
                                }

                                $price = ($game['price'] == 0)
                                    ? 'Бесплатно'
                                    : number_format($game['price'], 0, ',', ' ') . ' ₽';
                                ?>
                                <div class="game-card" onclick="window.location.href='/g/<?= $game['id'] ?>';">
                                    <div class="game-image <?= ($adultSection && $game['age_rating'] >= 18) ? 'blur-adult' : '' ?>">
                                        <img src="<?= !empty($game['path_to_cover'])
                                            ? htmlspecialchars($game['path_to_cover'])
                                            : 'https://via.placeholder.com/400x225/74155d/ffffff?text=No+Image' ?>"
                                            alt="<?= htmlspecialchars($game['name']) ?>"
                                            style="mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 1) 60%, rgba(0, 0, 0, 0) 100%);">
                                        <?php if ($badge): ?>
                                            <div class="game-badge <?= $badgeClass ?>"><?= $badge ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="game-info">
                                        <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                        <div class="game-footer">
                                            <div class="game-price <?= ($game['price'] == 0) ? 'free' : '' ?>">
                                                <?= $price ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <div id="adultModal" class="adult-modal">
        <div class="adult-modal-content">
            <h2>Подтверждение возраста</h2>
            <p>Данный раздел содержит материалы только для пользователей старше 18 лет. Также игры в этом разделе могут содержать контент, который запрещён законодательством РФ. Платформа ни в коем случае такое не пропагандирует.</p>
            <button id="adultConfirmBtn">Мне есть 18 лет</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const gameCards = document.querySelectorAll('.game-card');
            gameCards.forEach((card, index) => {
                card.style.transitionDelay = `${index * 0.05}s`;
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = '';
                }, 100);
            });

            const searchInput = document.querySelector('.search-bar input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const gameCards = document.querySelectorAll('.game-card:not(.ad-card)'); // рекламу не скрываем
                    gameCards.forEach(card => {
                        const title = card.querySelector('.game-title')?.textContent.toLowerCase() || '';
                        const developer = card.querySelector('.game-developer')?.textContent.toLowerCase() || '';
                        if (title.includes(searchTerm) || developer.includes(searchTerm)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }

            const urlParams = new URLSearchParams(window.location.search);
            const isAdultSection = urlParams.get('adult') == 1;
            if (isAdultSection && !sessionStorage.getItem('adultConfirmed')) {
                const modal = document.getElementById('adultModal');
                const btn = document.getElementById('adultConfirmBtn');
                modal.style.display = 'flex';

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true);

                modal.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                btn.addEventListener('click', function() {
                    sessionStorage.setItem('adultConfirmed', 'true');
                    modal.style.display = 'none';
                    document.querySelectorAll('.blur-adult').forEach(img => {
                        img.classList.remove('blur-adult');
                    });
                });
            }
        });
    </script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const grid = document.querySelector('.games-grid');
        if (!grid) return;

        let activeCard = null;

        // Сброс трансформации карточки
        function resetTilt(card) {
            card.style.transform = '';
        }

        // Обработчик движения мыши внутри grid
        grid.addEventListener('mousemove', (e) => {
            const card = e.target.closest('.game-card');
            if (!card || card.classList.contains('ad-card')) return;

            // Если перешли на новую карточку, сбрасываем старую
            if (activeCard !== card) {
                if (activeCard) resetTilt(activeCard);
                activeCard = card;
            }

            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            // Нормализация координат в диапазон -1..1
            const nx = (x / rect.width) * 2 - 1;
            const ny = (y / rect.height) * 2 - 1;

            const maxAngle = 12; // максимальный угол наклона
            const rotateY = maxAngle * nx;
            const rotateX = -maxAngle * ny;

            const dx = nx * 50; // диапазон от -50% до 50%
            card.style.setProperty('--dx', `${dx}%`);

            // Применяем наклон + лёгкий подъём и масштабирование
            card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
        });

        // Когда мышь покидает grid (ушла за пределы)
        grid.addEventListener('mouseleave', () => {
            if (activeCard) {
                resetTilt(activeCard);
                activeCard = null;
            }
        });

        // Когда мышь уходит с карточки на пустую область
        grid.addEventListener('mouseout', (e) => {
            const card = e.target.closest('.game-card');
            if (card && !card.contains(e.relatedTarget)) {
                resetTilt(card);
                if (activeCard === card) activeCard = null;
            }
        });
    });
</script>
</body>
</html>