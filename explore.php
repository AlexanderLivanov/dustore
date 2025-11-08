<?php session_start(); ?>
<?php
require_once('swad/config.php');
require_once('swad/controllers/game.php');

$gameController = new Game();
$games = $gameController->getLatestGames(20);

$games = array_filter($games, function ($game) {
    // print_r($game);
    return isset($game['status']) && strtolower($game['status']) === 'published';
});
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
                <p>Исследуйте лучшие игры от независимых разработчиков</p>
            </div>
        </section>

        <section class="games-list">
            <div class="container">
                <div class="games-controls">
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
                                <div class="game-image">
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
                                    <p class="game-developer">От <?= htmlspecialchars($game['studio_name']) ?></p>
                                    <div class="game-footer">
                                        <?php if ($game['GQI'] > 0): ?>
                                            <div class="game-rating">★ <?= number_format($game['GQI'], 0) ?></div>
                                        <?php endif; ?>
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
        </section>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>

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
    </script>
</body>

</html>