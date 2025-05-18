<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Главная</title>
    <link rel="shortcut icon" href="swad/static/img/logo.svg" type="image/x-icon">
    <meta name="theme-color" content="#14041d">
    <meta name="description" content="Dustore.ru - новая игровая платформа! Скачивайте новинки инди-разработчиков.">
    <meta name="robots" content="index,follow">
    <meta name="generator" content="SWAD Framework">
    <meta name="google" content="notranslate">
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>
    <div class="main-container">
        <div class="search-field-container">
            <h1 id="game-search-header">Let's play!</h1>
            <input type="text" id="game-search-input" style="caret-shape: block;" placeholder="Игра, жанр, разработчик...">
            <input type="submit" id="game-search-submit" value="Поиск">
            <br>
            <!-- <a href="#">Фильтры...</a> -->
        </div>
        <div class="game-cards-container">
            <div class="game-cards-promo">
                <div class="game-card-promo-container">
                    <img src="" alt="" class="game-card-image">
                    <!-- TODO:  сделать баннеры и ленту -->
                </div>
            </div>
            <div class="game-cards-popular">

            </div>
        </div>

    </div>
    <?php require_once('swad/static/elements/footer.php'); ?>
</body>

</html>