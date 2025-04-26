<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <style>
        .error {
            color: red;
        }
    </style>
</head>

<body>
    <h1>Вход</h1>

    <?php if (isset($_GET['error'])): ?>
        <p class="error">Неверные учетные данные</p>
    <?php endif; ?>

    <form action="swad/controllers/login.php" method="POST">
        <div>
            <label for="username">Имя пользователя:</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div>
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">Войти</button>
    </form>

    <p>Еще нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
</body>

</html>