<?php
require_once('swad/static/elements/header.php');
require_once('swad/controllers/time.php');
require_once('swad/controllers/user.php');
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - Мой аккаунт</title>
    <link rel="stylesheet" href="swad/css/userprofile.css">
</head>

<body>
    <?php
    if (empty($_SESSION['logged-in'])) {
        die(header('Location: login'));
    }

    $user_data = $db->Select(
        "SELECT *
                FROM `users`
                    WHERE `telegram_id` = :id",
        [
            'id' => $_SESSION['telegram_id']
        ]
    );

    $firstName        = $user_data[0]['first_name'];
    $lastName         = $user_data[0]['last_name'];
    $profilePicture   = $user_data[0]['profile_picture'];
    $telegramID       = $user_data[0]['telegram_id'];
    $telegramUsername = $user_data[0]['telegram_username'];
    $userID           = $user_data[0]['id'];
    $added            = $user_data[0]['added'];
    $updated          = $user_data[0]['updated'];
    ?>

    <div class="profile-container">
        <div class="profile-header">
            <?php if (!is_null($profilePicture)): ?>
                <img src="<?= $profilePicture ?>?v=<?= time() ?>"
                    class="profile-picture"
                    alt="Аватар">
            <?php endif; ?>
            <div>
                <h1><?= $firstName . (!is_null($lastName) ? ' ' . $lastName : '') ?></h1>
                <?php if (!is_null($telegramUsername)): ?>
                    <p>@<?= $telegramUsername ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="switchTab(event, 'profile')">Профиль</button>
            <button class="tab-button" onclick="switchTab(event, 'security')">Безопасность</button>
            <button class="tab-button" onclick="switchTab(event, 'activity')">Активность</button>
        </div>

        <div id="profile" class="tab-content active">
            <div class="info-grid">
                <div class="info-card">
                    <h3>Основная информация</h3>
                    <p>Имя пользователя: <?= $firstName ?>
                        <?php if (!is_null($lastName)): ?>
                            <?= $lastName ?>
                        <?php endif; ?>
                    </p>
                    <p title="<?= $added; ?>">Присоединился к проекту: <?= time_ago($added); ?></p>
                    <p title="<?= $updated; ?>">Последний вход: <?= time_ago($updated); ?></p>
                </div>

                <div class=" info-card">
                    <h3>Информация об аккаунте</h3>
                    <p>Telegram ID: <?= $telegramID ?></p>
                    <?php if (!is_null($telegramUsername)): ?>
                        <p>Username: <a href="https://t.me/<?= $telegramUsername ?>">@<?= $telegramUsername ?></a></p>
                    <?php endif; ?>
                    <p>Тип учётной записи: <?= $curr_user->printUserPrivileges($telegramID); ?></p>
                    <p><a href="/dev/">Вход в консоль для разработчиков</a></p>
                </div>
            </div>
        </div>

        <div id="security" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <h3>Безопасность аккаунта</h3>
                    <!-- <p>Двухфакторная аутентификация: отключена</p> -->
                </div>
            </div>
        </div>

        <div id="activity" class="tab-content">
            <div class="info-grid">
                <div class="info-card">
                    <h3>Последние действия</h3>
                    <p>Нет последних действий</p>
                </div>
            </div>
        </div>
    </div>
    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        function switchTab(event, tabName) {
            // Убираем активные классы
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // Добавляем активные классы
            event.currentTarget.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</body>

</html>