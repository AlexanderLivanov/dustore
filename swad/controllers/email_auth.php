<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/jwt.php'); 

$db = new Database();
$pdo = $db->connect();

$login_error = "";
$register_error = "";

function generateFakeTelegram()
{
    return -1 * random_int(100000, 999999);
}

function loadSessionUser($user)
{
    // Создаём JWT токен для пользователя
    $token = authUser($user['telegram_id']);

    $_SESSION['logged-in'] = true;
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['telegram_id'] = $user['telegram_id'];
    $_SESSION['auth_token'] = $token;
    $_SESSION['USERDATA']  = $user;

    // Устанавливаем cookie с токеном (30 дней)
    setcookie('auth_token', $token, time() + 86400 * 30, '/', '', true, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'login') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['password']) && password_verify($_POST['password'], $user['password'])) {
            if (!$user['email_verified']) {
                $login_error = "📩 Почта не подтверждена. Проверьте email и папку «Спам».";
            } else {
                loadSessionUser($user);
                $redirectUrl = $_POST['backUrl'] ?? '/';
                header("Location: $redirectUrl");
            }
        } else {
            $login_error = "❌ Неверный email или пароль!";
        }
    }

    if ($_POST['action'] === 'register') {

        // Проверка дубликата email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $register_error = "⚠ Такой email уже зарегистрирован!";
        } else {

            $token = bin2hex(random_bytes(16));
            $pass_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

            $first   = $_POST['first_name'] ?? "Неопознанный";
            $last    = $_POST['last_name'] ?? "Игрок";
            $country = $_POST['country'] ?? null;
            $city    = $_POST['city'] ?? null;
            $website = $_POST['website'] ?? null;

            $tg_id = generateFakeTelegram();

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, country, city, website, verification_token, telegram_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $pass_hash,
                $first,
                $last,
                $country,
                $city,
                $website,
                $token,
                $tg_id
            ]);

            // отправка письма
            require_once(__DIR__ . '/send_email.php');
            $verifyLink = 'https://dustore.ru/verify?token=' . $token;
            $mail_body = '
                <!DOCTYPE html>
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <title>Подтверждение почты</title>
                </head>
                <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                <td align="center" style="padding:40px 15px;">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
                <tr>
                <td style="padding:30px;text-align:center;">

                <h1 style="color:#ffffff;margin:0 0 10px;font-size:26px;">
                Добро пожаловать в <span style="color:#c32178;">Dustore</span>
                </h1>

                <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                Платформа для разработчиков и игроков
                </p>

                <a href="' . $verifyLink . '"
                style="display:inline-block;padding:14px 28px;
                background:#c32178;color:#ffffff;
                text-decoration:none;border-radius:12px;
                font-weight:bold;font-size:16px;">
                Подтвердить почту
                </a>

                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Если кнопка не работает, скопируйте ссылку:
                <br>
                <a href="' . $verifyLink . '" style="color:#c32178;word-break:break-all;">
                ' . $verifyLink . '
                </a>
                </p>

                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Если вы не регистрировались на Платформе, то проигнорируйте данное письмо. Отвечать на это письмо не нужно: оно всё равно до
                нас не дойдёт.
                </p>

                </td>
                </tr>

                <tr>
                <td style="background:#0f0f15;padding:20px;text-align:center;">
                <p style="color:#6f6f85;font-size:12px;margin:0;">
                © 2024-' . date('Y') . ' Dustore · Все права защищены · <a href="https://t.me/dustore_official">Наш Telegram</a>
                </p>
                </td>
                </tr>

                </table>

                </td>
                </tr>
                </table>
                </body>
                </html>
                ';

            sendMail($_POST['email'], "Подтвердите вашу почту", $mail_body, "");

            $register_error = "🎉 Регистрация успешна!";
        }
    }
}
