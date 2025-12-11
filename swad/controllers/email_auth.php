<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/jwt.php'); // ВАЖНО: подключаем JWT

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
                // $login_error = "⛔ Email не подтверждён! Проверьте почту.";
                loadSessionUser($user);
                die(header("Location: /player/" . $_SESSION['USERDATA']['username']));
            } else {
                loadSessionUser($user);
                die(header("Location: /player/" . $_SESSION['USERDATA']['username']));
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
            // require_once(__DIR__ . '/send_email.php');
            // sendVerificationEmail($_POST['email'], $token);

            $register_error = "🎉 Регистрация успешна!";
        }
    }
}
