<?php
    // (c) 19.05.2025 Alexander Livanov
    require_once('../swad/controllers/organization.php');
    require_once('../swad/config.php');
    session_start();

    $database = new Database();
    $pdo = $database->connect();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute([':telegram_id' => $_SESSION['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Пользователь с telegram_id = {$_SESSION['id']} не найден!");
    }

    if (empty($_SESSION['logged-in'])) {
    die(header('Location: login'));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute([':telegram_id' => $_SESSION['id']]);

    if (!$stmt->fetch()) {
        throw new Exception("Пользователь не найден. Нельзя создать организацию.");
    }
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=dustore', 'root', '');

        $org = new Organization(
            $_POST['org_name'],
            $user['id'],
            explode(',', $_POST['members'])
        );

        if ($org->save($pdo)) {
            $success = "Студия создана! Сейчас вы будете перенаправлены в консоль разработчика!";
        }
    } catch (Exception $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Создать студию</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        button {
            background: #2196F3;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <h1>Регистрация студии</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="org_name">Название студии:</label>
            <input type="text"
                id="org_name"
                name="org_name"
                required
                placeholder="Введите название (только буквы и цифры)">
        </div>

        <div class="form-group">
            <label for="members">ID сотрудников (через запятую):</label>
            <input type="text"
                id="members"
                name="members"
                placeholder="Пример: 123,456,789">
        </div>

        <button type="submit">🚀 Создать студию</button>
    </form>

    <div style="margin-top: 2rem; color: #666;">
        <h3>Инструкция:</h3>
        <ul>
            <li>Название должно содержать только английские буквы, цифры и дефисы</li>
            <li>ID сотрудников можно найти в их профилях</li>
            <li>После создания вы получите доступ к конфигурационному файлу</li>
        </ul>
    </div>
</body>

</html>