<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();
$games = $gameController->getLatestGames(20);

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
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <section class="games-header">
            <div class="container">
                <h1>Откройте для себя новый мир!</h1>
                <p>Исследуйте лучшие игры от независимых инди-разработчиков</p>
            </div>
        </section>

        <section class="games-list">
            <div class="container">
                <?php if (isset($_GET['adult']) && $_GET['adult'] == 1): ?>
                    <div class="warning-adult">
                        Внимание! Данный раздел содержит игры, предназначенные только для пользователей старше 18 лет
                        в соответствии с законодательством РФ.
                    </div>
                <?php endif; ?>
                <div class="games-controls">
                    <div class="controls-left">
                        <a href="?adult=0" class="btn-filter <?= (!isset($_GET['adult']) || $_GET['adult'] == 0) ? 'active' : '' ?>">
                            Все игры
                        </a>
                        <a href="?adult=1" class="btn-filter <?= (isset($_GET['adult']) && $_GET['adult'] == 1) ? 'active' : '' ?>">
                            18+
                        </a>
                    </div>
                    <div class="search-bar">
                        <span class="search-icon">🔍</span>
                        <input type="text" placeholder="Введите название игры или тикер разработчика...">
                    </div>
                </div>

                <div class="games-grid">
                    <?php if (empty($games)): ?>
                        <div class="no-games-message">
                            <p>Игры еще не добавлены в каталог</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($games as $game):
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
                                        alt="<?= htmlspecialchars($game['name']) ?>">
                                    <?php if ($badge): ?>
                                        <div class="game-badge <?= $badgeClass ?>"><?= $badge ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="game-info">
                                    <h3 class="game-title"><?= htmlspecialchars($game['name']) ?></h3>
                                    <p><?= mb_substr(htmlspecialchars($game['description']), 0, 150, 'UTF-8') . '...' ?></p>
                                    <br>
                                    <p class="game-developer">От <?= htmlspecialchars($game['studio_name']) ?></p>
                                    <div class="game-footer">
                                        <?php if ($game['GQI'] > 0): ?>
                                            <div class="game-rating">GQI:  <?= number_format($game['GQI'], 0) ?></div>
                                        <?php endif; ?>
                                        <?php
                                        $avg_rating = $gameController->getAverageRating($game['id'])['avg'];
                                        // print_r($avg_rating);
                                        $total_reviews = count($gameController->getReviews($game['id']));
                                        $total_downloads = $gameController->getTotalDownloads($game['id']);
                                        ?>
                                        <?php if ($game['GQI'] > 0): ?>
                                            <div class="game-rating">★ <?= $avg_rating . "/10" ?></div>
                                        <?php endif; ?>
                                        <?php if ($avg_rating > 0): ?>
                                            <div class="game-rating"><?= $game['age_rating'] ?></div>
                                        <?php endif; ?>
                                        <div class="game-price <?= ($game['price'] == 0) ? 'free' : '' ?>">
                                            <?= $price ?>
                                        </div>
                                    </div>
                                    <br>
                                    <h6>
                                        <?php echo 'Отзывов: ' . $total_reviews;
                                        echo '<br>';
                                        echo 'Скачали: ' . $total_downloads . ' раз(а)'; ?>
                                    </h6>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    card.style.transform = 'translateY(0)';
                }, 100);
            });

            // Поиск по играм
            const searchInput = document.querySelector('.search-bar input');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const gameCards = document.querySelectorAll('.game-card');

                gameCards.forEach(card => {
                    const title = card.querySelector('.game-title').textContent.toLowerCase();
                    const developer = card.querySelector('.game-developer').textContent.toLowerCase();

                    if (title.includes(searchTerm) || developer.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const isAdultSection = urlParams.get('adult') == 1;

            if (isAdultSection && !sessionStorage.getItem('adultConfirmed')) {
                const modal = document.getElementById('adultModal');
                const btn = document.getElementById('adultConfirmBtn');

                modal.style.display = 'flex';

                // запрет закрытия ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true);

                // запрет закрытия кликом вне
                modal.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                btn.addEventListener('click', function() {
                    sessionStorage.setItem('adultConfirmed', 'true');
                    modal.style.display = 'none';

                    // убираем размытие с картинок
                    document.querySelectorAll('.blur-adult').forEach(img => {
                        img.classList.remove('blur-adult');
                    });
                });
            }


        });
    </script>
</body>

</html>