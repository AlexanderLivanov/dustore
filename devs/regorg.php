<?php
// (c) 19.05.2025 Alexander Livanov
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/organization.php');

session_start();

$curr_user = new User();
if (empty($_SESSION['logged-in']) or $curr_user->checkAuth() > 0) {
    echo ("<script>window.location.replace('login');</script>");
}

$database = new Database();
$pdo = $database->connect();
$telegram_id = $_SESSION['telegram_id'];
$stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
$stmt->execute([':telegram_id' => $_SESSION['telegram_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("Пользователь с telegram_id = {$_SESSION['telegram_id']} не найден!");
}

if (empty($_SESSION['logged-in'])) {
    die(header('Location: login'));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute([':telegram_id' => $_SESSION['telegram_id']]);

    if (!$stmt->fetch()) {
        throw new Exception("Пользователь не найден. Нельзя создать организацию.");
    }
    try {
        $pdo = new Database();

        $org = new Organization(
            $_POST['org_name'],
            $user['id'],
            $_POST['description']
        );

        if ($org->save($pdo->connect())) {
            $success = "Студия создана! Сейчас вы будете перенаправлены в консоль разработчика!";
            echo ("<script>window.location.replace('/devs/');</script>");
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
                placeholder="Введите название (только буквы и цифры), до 20 символов"
                maxlength="20">
        </div>

        <div class="form-group">
            <label for="description">Описание студии:</label>
            <textarea type="text"
                id="description"
                name="description"
                required
                placeholder="Введите описание, до 500 символов"
                maxlength="500" style="height: 100px;"></textarea>
        </div>

        <button type="submit">🚀 Создать студию</button>
    </form>

</body>

</html>