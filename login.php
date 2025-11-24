<?php
session_start();

if (isset($_SESSION['logged-in']) && $_SESSION['logged-in'] == TRUE) {
    die(header('Location: me'));
}

if ($_SERVER['HTTP_HOST'] == 'dustore.ru') {
    define('BOT_USERNAME', 'dustore_auth_bot');
} else if ($_SERVER['HTTP_HOST'] == '127.0.0.1') {
    define('BOT_USERNAME', 'dustore_auth_local_bot');
}

// обработка входа/регистрации
require_once('swad/controllers/email_auth.php');
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link rel="stylesheet" href="swad/css/login.css">
</head>

<body>
    <div class="container">

        <div class="toggle-container">
            <button class="toggle-btn active" onclick="showForm('emailLogin')">Вход по Email</button>
            <button class="toggle-btn" onclick="showForm('login')">Telegram</button>
            <button class="toggle-btn" onclick="showForm('emailRegister')">Регистрация</button>
        </div>

        <!-- Вход по email -->
        <form id="emailLoginForm" class="form-container active" method="POST">
            <?php if (!empty($login_error)) echo "<div class='error-message'>$login_error</div>"; ?>

            <input type="hidden" name="action" value="login">

            <div class="form-group"><input type="email" name="email" placeholder="Email" required></div>
            <div class="form-group"><input type="password" name="password" placeholder="Пароль" required></div>

            <button type="submit">Войти</button>
        </form>

        <!-- Вход через Телеграм -->
        <form id="loginForm" class="form-container">
            <p>Вы можете войти через Telegram и использовать этот аккаунт в экосистеме DustEcoSystem.</p>
            <div class="form-group" style="text-align: center; margin-top: 35px;">
                <script async src="https://telegram.org/js/telegram-widget.js"
                    data-telegram-login="<?= BOT_USERNAME ?>"
                    data-size="large"
                    data-auth-url="swad/controllers/auth.php"></script>
            </div>
        </form>

        <!-- Регистрация -->
        <form id="emailRegisterForm" class="form-container" method="POST">
            <?php if (!empty($register_error)) echo "<div class='error-message'>$register_error</div>"; ?>

            <input type="hidden" name="action" value="register">

            <div class="form-group"><input type="text" name="username" placeholder="Имя пользователя" required></div>
            <div class="form-group"><input type="email" name="email" placeholder="Email" required></div>
            <div class="form-group"><input type="password" name="password" placeholder="Пароль" required></div>

            <div class="dropdown">
                <p onclick="toggleExtra()">Дополнительные данные ▼</p>
                <div id="extraFields" style="display: none;">
                    Имя
                    <div class="form-group"><input type="text" name="first_name" placeholder="Имя" value="Неопознанный"></div>
                    Фамилия
                    <div class="form-group"><input type="text" name="last_name" placeholder="Фамилия" value="Игрок"></div>
                    <div class="form-group"><input type="text" name="country" placeholder="Страна"></div>
                    <div class="form-group"><input type="text" name="city" placeholder="Город"></div>
                    <div class="form-group"><input type="text" name="website" placeholder="Сайт (необязательно)"></div>
                </div>
            </div>

            <button type="submit">Зарегистрироваться</button>
        </form>

    </div>

    <script>
        function showForm(formType) {
            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
            document.getElementById(formType + 'Form').classList.add('active');
        }

        function toggleExtra() {
            const box = document.getElementById('extraFields');
            box.style.display = box.style.display === "none" ? "block" : "none";
        }

        // Если ?method=email
        document.addEventListener('DOMContentLoaded', () => {
            const query = new URLSearchParams(window.location.search);
            if (query.get('method') === 'email') showForm('emailLogin');
        });
    </script>

</body>

</html>