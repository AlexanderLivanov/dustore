<?php
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');

$curr_user = new User();

// $curr_user->createUser("leo", "admin@admin.com", "123");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tiny5&display=swap');

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-left: 15px;
            padding-right: 15px;
            height: 80px;
            background-color: #14041d;
            backdrop-filter: blur(1px);
            /* position: sticky; */
            top: 0;
            z-index: 10;
            /* background-color: white; */

        }

        .section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .left-section,
        .right-section {
            justify-content: center;
        }

        .buttons-left,
        .buttons-right {
            display: flex;
            gap: 10px;
        }

        .button {
            padding: 10px;
            background-color: white;
            color: #14041d;
            border: none;
            cursor: pointer;
            box-shadow: 5px 5px 10px rgba(255, 255, 255, 1);
            font-family: 'Tiny5', 'Gill Sans';
            transition: all .2s;

        }

        .button:hover {
            background-color: #c32178;
            color: white;
            font-family: 'Tiny5', 'Gill Sans';
            transition: all .2s;
            font-size: 12pt;
            padding: 10px;
            border: none;
        }

        .image img {
            width: auto;
            padding: 4px;
            height: 55px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 1);
            transition: all .2s;
            border-radius: 5px;
        }

        .image img:hover {
            width: auto;
            padding: 10px;
            height: 55px;
            transition: all .2s;
            box-shadow: 0 0 20px rgba(255, 255, 255, 1);
            filter: contrast(200%);
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="section left-section">
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
                <button class="button"><?= $curr_user->getUsername(1); ?> - 100₽</button>
            </div>
        </div>
    </div>
</body>

</html>