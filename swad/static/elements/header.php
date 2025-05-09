<?php
session_start();
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');

$curr_user = new User();
$db = new Database();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="swad/css/header.css">
</head>

<body>
    <div class="header">
        <div class="section left-section">
            <div>
                <button id="burger" class="button" style="padding: 0;"><svg height="48" id="svg8" version="1.1" viewBox="0 0 12.7 12.7" width="48" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg"><g id="layer1" transform="translate(0,-284.29998)"><path d="m 2.8222223,287.1222 v 1.41111 h 7.0555558 v -1.41111 z m 0,2.82222 v 1.41112 h 7.0555558 v -1.41112 z m 0,2.82223 v 1.41111 h 7.0555558 v -1.41111 z" id="rect4487" style="opacity:1;vector-effect:none;fill:#000000;fill-opacity:1;stroke:none;stroke-width:0.07055555;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1"/></g></svg></button>
            </div>
            <div class="buttons-left">
                <button class="button">Игры</button>
                <button class="button">Приложения</button>
                <button class="button">Ассеты</button>
                <button class="button">О платформе</button>
            </div>
        </div>
        <div class="section center-section">
            <div class="image">
                <img src="/swad/static/img/logo_low.png" alt="">
            </div>
        </div>
        <div class="section right-section">
            <div class="buttons-right">
                <button class="button">0</button>
                <button class="button" onclick="location.href='/login'">
                    <?php if (empty($_SESSION['telegram_id'])) {
                        echo ("Войти в аккаунт");
                    } else {
                        echo ($curr_user->getUsername($_SESSION['telegram_id']) . " - 100₽");
                    }
                    ?>
                </button>
            </div>
        </div>
    </div>
</body>

</html>