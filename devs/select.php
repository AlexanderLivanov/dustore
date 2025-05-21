<?php
    session_start();
    require_once('../swad/config.php');
    require_once('../swad/controllers/organization.php');
    require_once('../swad/controllers/user.php');

    $curr_user = new User();
    $db = new Database();

    if (empty($_SESSION['logged-in']) or $curr_user->checkAuth() > 0) {
        echo ("<script>window.location.replace('../login');</script>");
    }

    $user_id = $db->Select(
    "SELECT id FROM `users`
                    WHERE `telegram_id` = :id",
    [
        'id' => $_SESSION['telegram_id']
    ]);

    $user_orgs = $db->Select(
    "SELECT name FROM `organizations`
                        WHERE `owner_id` = :id",
    [
        'id' => $user_id[0][0]
    ]);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore.Devs - Выберите студию</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .console-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin: 16px;
            padding: 32px;
        }

        .title {
            font-size: 24px;
            color: #202124;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .studio-list {
            list-style: none;
            margin: 24px 0;
        }

        .studio-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .studio-item:hover {
            background: #f8f9fa;
        }

        .studio-icon {
            margin-right: 16px;
            color: #5f6368;
        }

        .studio-name {
            font-size: 16px;
            color: #202124;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #5f6368;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            text-transform: uppercase;
            background: #1a73e8;
            color: white;
            text-decoration: none;
            margin-top: 24px;
            width: 100%;
        }

        .button:hover {
            background: #1557b0;
        }

        .button .material-icons {
            margin-right: 8px;
            font-size: 18px;
        }

        @media (max-width: 480px) {
            .console-container {
                padding: 24px;
                margin: 8px;
            }

            .title {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="console-container">
        <h1 class="title">Выберите студию</h1>

        <?php foreach($user_orgs as $org): ?>
        <ul class="studio-list">
            <li class="studio-item">
                <i class="material-icons studio-icon">business</i>
                <span class="studio-name"><?= $org[0] ?></span>
            </li>
        </ul>
        <?php endforeach; ?>

        <?php if(count($user_orgs) < 1): ?>
        <div class="empty-state">
            <i class="material-icons" style="font-size: 48px; margin-bottom: 16px;">business</i>
            <p>У вас пока нет ни одной студии</p>
        </div>
        <?php endif; ?>


        <button class="button" onclick="location.href='regorg'">
            <i class="material-icons">add</i>
            Зарегистрировать новую студию
        </button>
    </div>
</body>

</html>