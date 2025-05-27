<?php
// (c) 19.05.2025 Alexander Livanov
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');
require_once('../swad/controllers/organization.php');

session_start();

// Проверяем авторизован ли пользователь
$curr_user = new User();
if (empty($_SESSION['logged-in']) or $curr_user->checkAuth() > 0) {
    echo ("<script>window.location.replace('../login');</script>");
    exit;
}

// Подключаемся и получаем id текущего пользователя (Обычный ID, а не telegram_id!)
$database = new Database();
$pdo = $database->connect();
$telegram_id = $_SESSION['telegram_id'];
$stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = :telegram_id");
$stmt->execute([':telegram_id' => $_SESSION['telegram_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("Пользователь с telegram_id = {$_SESSION['telegram_id']} не найден!");
}

// Еще раз проверям авторизацию (хз зачем)
if (empty($_SESSION['logged-in'])) {
    die(header('Location: ../login'));
}


// POST запрос на регистрацию
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $org = new Organization(
            $_POST['org_name'],
            $user['id'],
            $_POST['description'],
            $_POST['vk_link'],
            $_POST['tg_link']
        );

        if ($org->save($pdo)) {
            $newOrgId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO user_organization 
                (user_id, organization_id, role_id, status, vk_link, tg_link) 
                VALUES (:user_id, :org_id, :role_id, 'pending', :vk_link, :tg_link)
            ");
            $stmt->execute([
                'user_id' => $user['id'],
                'org_id' => $newOrgId,
                'role_id' => 2,
                ':vk_link' => $_POST['vk_link'],
                ':tg_link' => $_POST['tg_link']
            ]);

            $pdo->commit();
            $_SESSION['studio_id'] = $newOrgId;
            header("Location: /devs/select");
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
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
    <h1 style="text-align: center;">Dustore.Devs</h1>
    <h2 style="text-align: center;">Регистрация студии</h2>

    <?php if (isset($error)): ?>
        <div class=" alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <h3>Основная информация:</h3>
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
        <i style="color: #333;">Скоро будет возможность добавлять картинки</i>
        <p>&nbsp;</p>
        <h3>Следующие поля необходимы для модерации</h3>
        <div class="form-group">
            <label for="org_name">Ссылка на ВК группу вашей студии:</label>
            <input type="text"
                id="vk_link"
                name="vk_link"
                required
                placeholder="Обязательно с https://vk.com/. Например, https://vk.com/dgscorp"
                maxlength="50">
        </div>

        <div class="form-group">
            <label for="org_name">Ссылка на Telegram канал:</label>
            <input type="text"
                id="tg_link"
                name="tg_link"
                required
                placeholder="Обязательно с https://t.me/. Например, https://t.me/dustore_official"
                maxlength="50">
        </div>

        <button type="submit">🚀 Создать студию</button>
    </form>

</body>

</html>