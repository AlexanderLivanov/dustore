<?php
// ── CSRF Token ──────────────────────────────────────────────────────────────
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

// ── Dependencies ─────────────────────────────────────────────────────────────
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/jwt.php');

$db  = new Database();
$pdo = $db->connect();

$login_error    = "";
$register_error = "";

// ── Helpers ──────────────────────────────────────────────────────────────────
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
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    @file_put_contents($logDir . '/bot_attempts.log', $line, FILE_APPEND | LOCK_EX);
}

// ── Invite code validation ──────────────────────────────
if (!defined('INVITE_CODES')) {
    define('INVITE_CODES', [
        'SHOW-MUST-GOON',
    ]);
}

function isValidInviteCode(string $code): bool
{
    // Структурная проверка формата XXXX-XXXX-XXXX
    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', strtoupper($code))) {
        return false;
    }
    // Проверка по списку
    return in_array(strtoupper($code), INVITE_CODES, true);
}

// ── Rate limiter (сессионный, по IP) ─────────────────────────────────────────
function checkRateLimit(string $key, int $max, int $window): bool
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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

// ── LOGIN ────────────────────────────────────────────────────────────────────
if ($_POST['action'] === 'login') {

    // Rate limit: 10 попыток за 5 минут
    if (!checkRateLimit('login', 10, 300)) {
        http_response_code(429);
        $login_error = "⏳ Слишком много попыток входа. Подождите 5 минут.";
        return;
    }

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $login_error = "❌ Заполните все поля.";
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['password']) && password_verify($pass, $user['password'])) {
        if (!$user['email_verified']) {
            $login_error = "📩 Почта не подтверждена. Проверьте email и папку «Спам».";
        } else {
            loadSessionUser($user);
            $redirectUrl = $_POST['backUrl'] ?? '/';
            // Защита от open redirect
            if (!str_starts_with($redirectUrl, '/') || str_starts_with($redirectUrl, '//')) {
                $redirectUrl = '/';
            }
            header("Location: $redirectUrl");
            exit;
        }
    } else {
        // Намеренная задержка — защита от тайминг-атак
        usleep(random_int(100000, 300000));
        $login_error = "❌ Неверный email или пароль.";
    }
}

// ── REGISTER ─────────────────────────────────────────────────────────────────
if ($_POST['action'] === 'register') {

    // 1. Honeypot — поле «website» должно быть пустым
    if (!empty($_POST['website'])) {
        logBotAttempt('HONEYPOT', $_POST);
        $register_error = "🎉 Регистрация успешна!"; // ботам — тихий фейк
        return;
    }

    // 2. Регистрация включена?
    if (!REGISTRATION_ENABLED) {
        http_response_code(403);
        $register_error = "❌ Регистрация временно закрыта.";
        return;
    }

    // 3. Rate limit: 3 регистрации за 10 минут с одного IP
    if (!checkRateLimit('register', 3, 600)) {
        http_response_code(429);
        logBotAttempt('RATE_LIMIT', $_POST);
        $register_error = "⏳ Слишком много попыток. Подождите 10 минут.";
        return;
    }

    // 4. Проверка кода приглашения
    $inviteCode = trim($_POST['invite_code'] ?? '');
    if (!isValidInviteCode($inviteCode)) {
        $register_error = "❌ Неверный или недействительный код приглашения.";
        return;
    }

    // 5. Валидация полей
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "❌ Введите корректный email.";
        return;
    }
    if (strlen($password) < 8) {
        $register_error = "❌ Пароль должен содержать минимум 8 символов.";
        return;
    }
    if (!empty($username) && !preg_match('/^[A-Za-z](?:[A-Za-z._]*[A-Za-z])?$/', $username)) {
        $register_error = "❌ Имя пользователя содержит недопустимые символы.";
        return;
    }

    // 6. Проверка дубликата email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $register_error = "⚠ Такой email уже зарегистрирован.";
        return;
    }

    // 7. Создание пользователя
    $verifyToken = bin2hex(random_bytes(16));
    $passHash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $firstName = htmlspecialchars(trim($_POST['first_name'] ?? 'Неопознанный'), ENT_QUOTES);
    $lastName  = htmlspecialchars(trim($_POST['last_name']  ?? 'Игрок'),        ENT_QUOTES);
    $country   = htmlspecialchars(trim($_POST['country']    ?? ''),             ENT_QUOTES) ?: null;
    $tgId      = generateFakeTelegram();

    $stmt = $pdo->prepare("
        INSERT INTO users
            (username, email, password, first_name, last_name, country, verification_token, telegram_id)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
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

    // 8. Письмо с подтверждением
    require_once(__DIR__ . '/send_email.php');
    $verifyLink = 'https://dustore.ru/verify?token=' . $verifyToken;
    $mailBody   = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Подтверждение почты</title></head>
<body style="margin:0;padding:0;background:#0a0a0f;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 15px;">
<table width="560" cellpadding="0" cellspacing="0"
       style="background:#111118;border-radius:20px;overflow:hidden;border:1px solid rgba(195,33,120,0.2);">
<tr><td style="height:3px;background:linear-gradient(90deg,transparent,#c32178,#e8279a,transparent);"></td></tr>
<tr><td style="padding:36px 36px 28px;text-align:center;">
  <div style="font-size:36px;margin-bottom:12px;">🎮</div>
  <h1 style="color:#fff;margin:0 0 8px;font-size:24px;font-family:Arial,sans-serif;">
    Добро пожаловать в <span style="color:#c32178;">Dustore</span>
  </h1>
  <p style="color:#6b6b80;font-size:14px;margin:0 0 28px;">Платформа для разработчиков и игроков</p>
  <a href="{$verifyLink}"
     style="display:inline-block;padding:14px 32px;
            background:linear-gradient(135deg,#c32178,#e8279a);
            color:#fff;text-decoration:none;border-radius:12px;
            font-weight:bold;font-size:15px;letter-spacing:0.5px;">
    Подтвердить почту →
  </a>
  <p style="color:#6b6b80;font-size:12px;margin:24px 0 0;">
    Если кнопка не работает, скопируйте ссылку:<br>
    <a href="{$verifyLink}" style="color:#c32178;word-break:break-all;">{$verifyLink}</a>
  </p>
  <p style="color:#6b6b80;font-size:12px;margin:16px 0 0;">
    Если вы не регистрировались — просто проигнорируйте это письмо.
  </p>
</td></tr>
<tr><td style="background:#0a0a0f;padding:16px;text-align:center;">
  <p style="color:#3a3a50;font-size:11px;margin:0;">
    © 2024–{$year} Dustore · <a href="https://t.me/dustore_official" style="color:#c32178;">Telegram</a>
  </p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $year = date('Y');
    $mailBody = str_replace('{$year}', $year, $mailBody);

    sendMail($email, "Подтвердите вашу почту — Dustore", $mailBody, "");

    // Инвалидировать использованный код (опционально — раскомментировать если нужно)
    // invalidateInviteCode($inviteCode);

    $register_error = "🎉 Регистрация успешна!";
}