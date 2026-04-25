<?php
session_start();

require_once __DIR__ . '/swad/config.php';

$db = new Database();
$pdo = $db->connect();

$status  = 'error';
$message = 'Неверная или устаревшая ссылка подтверждения.';

if (!empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("
        SELECT id, email_verified 
        FROM users 
        WHERE verification_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        if ($user['email_verified']) {
            $status  = 'already';
            $message = 'Почта уже подтверждена.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email_verified = 1,
                    verification_token = NULL
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            $status  = 'success';
            $message = 'Почта успешно подтверждена!';
        }
    }
}

// редирект через 4 секунды
header("Refresh: 4; url=/login");
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Подтверждение почты</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            background: #0e0e12;
            font-family: Arial, Helvetica, sans-serif;
            color: #fff;
        }

        .box {
            max-width: 520px;
            margin: 100px auto;
            background: #14141b;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .success {
            color: #2ecc71;
        }

        .error {
            color: #e74c3c;
        }

        .already {
            color: #f1c40f;
        }

        p {
            color: #b8b8c6;
        }

        small {
            display: block;
            margin-top: 20px;
            color: #6f6f85;
        }
    </style>
</head>

<body>

    <div class="box">
        <div class="icon
        <?= $status === 'success' ? 'success' : ($status === 'already' ? 'already' : 'error') ?>">
            <?= $status === 'success' ? '✔' : ($status === 'already' ? '⚠' : '✖') ?>
        </div>

        <h2><?= htmlspecialchars($message) ?></h2>

        <p>
            Вы будете перенаправлены на страницу входа через несколько секунд.
        </p>

        <small>
            Если редирект не сработал —
            <a href="/login?method=email" style="color:#c32178;">нажмите сюда</a>
        </small>
    </div>

</body>

</html>