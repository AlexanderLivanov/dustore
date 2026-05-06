<?php
// ── CSRF Token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/jwt.php');

$db  = new Database();
$pdo = $db->connect();

$login_error    = '';
$register_error = '';

// ── Helpers ───────────────────────────────────────────────────────────────────
function generateFakeTelegram(): int
{
    return -1 * random_int(100000, 999999);
}

function loadSessionUser(array $user): void
{
    $token = authUser($user['telegram_id']);

    $_SESSION['logged-in']   = true;
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['telegram_id'] = $user['telegram_id'];
    $_SESSION['auth_token']  = $token;
    $_SESSION['USERDATA']    = $user;

    setcookie('auth_token', $token, time() + 86400 * 30, '/', '', true, true);
}

function logBotAttempt(string $reason, array $post): void
{
    $line = sprintf(
        "%s | %s | IP: %s | Email: %s\n",
        date('Y-m-d H:i:s'),
        $reason,
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $post['email'] ?? '-'
    );
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
    @file_put_contents($logDir . '/bot_attempts.log', $line, FILE_APPEND | LOCK_EX);
}

// ── Rate limiter (сессионный, по IP) ──────────────────────────────────────────
function checkRateLimit(string $key, int $max, int $window): bool
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $sKey = 'rl_' . $key . '_' . md5($ip);

    if (!isset($_SESSION[$sKey])) {
        $_SESSION[$sKey] = ['count' => 0, 'reset' => time() + $window];
    }
    if (time() > $_SESSION[$sKey]['reset']) {
        $_SESSION[$sKey] = ['count' => 0, 'reset' => time() + $window];
    }
    $_SESSION[$sKey]['count']++;
    return $_SESSION[$sKey]['count'] <= $max;
}

// ════════════════════════════════════════════════════════════════════════════
//  POST handling
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    return;
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($_POST['action'] === 'login') {

    if (!checkRateLimit('login', 10, 300)) {
        http_response_code(429);
        $login_error = '⏳ Слишком много попыток входа. Подождите 5 минут.';
        return;
    }

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $login_error = '❌ Заполните все поля.';
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['password']) && password_verify($pass, $user['password'])) {
        if (!$user['email_verified']) {
            $login_error = '📩 Почта не подтверждена. Проверьте email и папку «Спам».';
        } else {
            loadSessionUser($user);
            $redirectUrl = $_POST['backUrl'] ?? '/';
            if (!str_starts_with($redirectUrl, '/') || str_starts_with($redirectUrl, '//')) {
                $redirectUrl = '/';
            }
            header("Location: $redirectUrl");
            exit;
        }
    } else {
        usleep(random_int(100000, 300000));
        $login_error = '❌ Неверный email или пароль.';
    }
}

// ── REGISTER ──────────────────────────────────────────────────────────────────
if ($_POST['action'] === 'register') {

    // 1. Honeypot
    if (!empty($_POST['website'])) {
        logBotAttempt('HONEYPOT', $_POST);
        $register_error = '🎉 Регистрация успешна!'; // тихий фейк для ботов
        return;
    }

    // 2. Регистрация включена?
    if (!REGISTRATION_ENABLED) {
        http_response_code(403);
        $register_error = '❌ Регистрация временно закрыта.';
        return;
    }

    // 3. Rate limit
    if (!checkRateLimit('register', 3, 600)) {
        http_response_code(429);
        logBotAttempt('RATE_LIMIT', $_POST);
        $register_error = '⏳ Слишком много попыток. Подождите 10 минут.';
        return;
    }

    // 4. Валидация полей (инвайт-код убран)
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = '❌ Введите корректный email.';
        return;
    }
    if (strlen($password) < 8) {
        $register_error = '❌ Пароль должен содержать минимум 8 символов.';
        return;
    }
    if (!empty($username) && !preg_match('/^[A-Za-z](?:[A-Za-z._]*[A-Za-z])?$/', $username)) {
        $register_error = '❌ Имя пользователя содержит недопустимые символы.';
        return;
    }

    // 5. Проверка дубликата email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $register_error = '⚠ Такой email уже зарегистрирован.';
        return;
    }

    // 6. Создание пользователя
    $verifyToken = bin2hex(random_bytes(16));
    $passHash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $firstName = htmlspecialchars(trim($_POST['first_name'] ?? 'Неопознанный'), ENT_QUOTES);
    $lastName  = htmlspecialchars(trim($_POST['last_name']  ?? 'Игрок'),        ENT_QUOTES);
    $country   = htmlspecialchars(trim($_POST['country']    ?? ''),             ENT_QUOTES) ?: null;
    $tgId      = generateFakeTelegram();

    $stmt = $pdo->prepare("
        INSERT INTO users
            (username, email, password, first_name, last_name, country, verification_token, telegram_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $username ?: null,
        $email,
        $passHash,
        $firstName,
        $lastName,
        $country,
        $verifyToken,
        $tgId,
    ]);

    // 7. Письмо с подтверждением
    require_once(__DIR__ . '/send_email.php');
    $verifyLink = 'https://dustore.ru/verify?token=' . $verifyToken;

    $body = "
    <p style='color:#b8b8c6;font-size:14px;line-height:1.6;margin-bottom:20px;'>
        Вы создали аккаунт на Dustore. Нажмите кнопку ниже, чтобы подтвердить email.
    </p>";

    sendMail(
        $email,
        'Подтвердите email — Dustore',
        buildEmail('Добро пожаловать!', $body, 'Подтвердить email', $verifyLink)
    );

    $register_error = '🎉 Регистрация успешна!';
}