<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/organization.php');
require_once('../swad/controllers/user.php');

$curr_user = new User();
$db = new Database();

if ($curr_user->checkAuth() > 0) {
    echo ("<script>window.location.replace('../login');</script>");
}

$user_id = $curr_user->getID($curr_user->auth());

// $user_orgs = $curr_user->getUserOrgs($user_id);
$user_orgs = $curr_user->getUO($user_id);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore.Devs - Выберите студию</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link rel="stylesheet" href="/swad/css/studioselect.css">
</head>

<body>
    <div class="console-container">
        <h1 class="title">Выберите студию</h1>

        <?php foreach ($user_orgs as $org): ?>
            <ul class="studio-list">
                <?php if ($org['status'] == 'pending'): ?>
                    <li class="studio-item" style="cursor: not-allowed;">
                        <i class="material-icons studio-icon">schedule</i>
                        <span class="studio-name"><?= $org['name'] ?><i style="color: crimson; font-size: 11pt;"> На проверке...</i></span>
                        <br>
                    </li>
                <?php endif;

                if ($org['status'] == 'active'): ?>
                    <li class="studio-item">
                        <form action="set_studio.php" method="post" style="all: unset;">
                            <input type="hidden" name="studio_id" value="<?= $org['id'] ?>">
                            <button type="submit" style="all: unset; width: 100%; cursor: pointer;">
                                <i class="material-icons studio-icon">business</i>
                                <span class="studio-name"><?= $org['name'] ?></span>
                                <span style="color: #5f6368; margin: 5px; padding: 5px;">
                                    <!-- TODO: исправить это -->
                                    <!-- <?= $curr_user->printUserPrivileges($org['user_role']) ?> -->
                                </span>
                            </button>
                        </form>
                    </li>
                <?php endif; ?>

                <?php if ($org['status'] == 'suspended'): ?>
                    <li class="studio-item" style="cursor: not-allowed;">
                        <i class="material-icons studio-icon">error</i>
                        <span class="studio-name"><?= $org['name'] ?><i style="color: crimson; font-size: 11pt;"> Приостановлено по причине: <?= $org['ban_reason'] ?></i></span>
                        <br>
                    </li>
                <?php endif; ?>
            </ul>

        <?php endforeach; ?>
        <?php if (count($user_orgs) >= 1): ?>
            <p style="color: #5f6368;">Вы не можете зарегистрировать больше одной студии!</p>
        <?php endif; ?>

        <?php if (count($user_orgs) < 1): ?>
            <div class="empty-state">
                <i class="material-icons" style="font-size: 48px; margin-bottom: 16px;">business</i>
                <p>У вас пока нет ни одной студии</p>
            </div>

            <button class="button" onclick="location.href='regorg'">
                <i class="material-icons">add</i>
                Зарегистрировать новую студию
            </button>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 50px">
            <h3>Для получения уведомлений достаточно <br> один раз запустить бота</h3>
            <img src="/swad/static/img/dusty_tg_qr.png" alt="" width="33%">
        </div>
    </div>

</body>

</html>