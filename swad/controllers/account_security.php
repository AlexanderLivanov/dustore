<?php
declare(strict_types=1);

/**
 * swad/controllers/account_security.php — привязка почты и смена пароля
 * из вкладки «Безопасность» профиля (player.php). Раньше формы слали POST
 * на /me, и после отправки человека выкидывало на старую страницу аккаунта.
 *
 * POST (fetch), CSRF в заголовке X-CSRF-Token или поле csrf.
 * action = bind_email:      email, password, confirm_password
 * action = change_password: current_password, new_password, confirm_password
 * Ответ ВСЕГДА JSON: { ok: true, message } или { ok: false, error }.
 * Логика и проверки — те же, что были в me.php.
 */

ob_start();

function as_reply(array $d, int $code = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[account_security] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        as_reply(['ok' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
    }
});

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/csrf.php';

if (empty($_SESSION['USERDATA']['id'])) {
    as_reply(['ok' => false, 'error' => 'Требуется авторизация'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    as_reply(['ok' => false, 'error' => 'Неверный метод'], 405);
}
if (!csrf_valid()) {
    as_reply(['ok' => false, 'error' => 'Сессия устарела, обновите страницу'], 403);
}

$user_id   = (int)$_SESSION['USERDATA']['id'];
$curr_user = new User();
$action    = (string)($_POST['action'] ?? '');

/* ── Привязка почты ── */
if ($action === 'bind_email') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        as_reply(['ok' => false, 'error' => 'Некорректный email'], 422);
    }
    if ($password !== $confirm || strlen($password) < 8) {
        as_reply(['ok' => false, 'error' => 'Пароль минимум 8 символов и должен совпадать'], 422);
    }
    if ($curr_user->emailExists($email, $user_id)) {
        as_reply(['ok' => false, 'error' => 'Этот email уже привязан к другому аккаунту'], 409);
    }

    $token = bin2hex(random_bytes(16));
    $curr_user->updateEmailAndPassword($user_id, $email, password_hash($password, PASSWORD_BCRYPT), $token);

    require_once __DIR__ . '/send_email.php';
    sendMail(
        $email,
        "Подтверждение почты — Dustore",
        "Подтвердите привязку почты по ссылке: "
        . "<a href='https://dustore.ru/recovery?token=" . $token . "'>"
        . "https://dustore.ru/recovery?token=" . $token . "</a>"
    );

    $_SESSION['USERDATA']['email'] = $email;
    $_SESSION['USERDATA']['email_verified'] = 0;
    as_reply(['ok' => true, 'message' => 'Почта привязана. Подтвердите email по ссылке из письма — после этого можно входить по паролю.']);
}

/* ── Смена пароля ──
   Текущий пароль обязателен: иначе любой, кто добрался до открытой сессии
   (чужой ноутбук, незакрытая вкладка), менял бы пароль и запирал владельца. */
if ($action === 'change_password') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $st = $curr_user->db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $stored = (string)$st->fetchColumn();

    if ($stored !== '' && !password_verify($current, $stored)) {
        as_reply(['ok' => false, 'error' => 'Текущий пароль указан неверно'], 422);
    }
    if ($new !== $confirm || strlen($new) < 8) {
        as_reply(['ok' => false, 'error' => 'Пароль минимум 8 символов и должен совпадать'], 422);
    }

    $curr_user->updatePassword($user_id, password_hash($new, PASSWORD_BCRYPT));
    as_reply(['ok' => true, 'message' => 'Пароль обновлён']);
}

as_reply(['ok' => false, 'error' => 'Неизвестное действие'], 400);
