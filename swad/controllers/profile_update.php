<?php
declare(strict_types=1);

/**
 * swad/controllers/profile_update.php — сохранение полей профиля из окна
 * «Изменить профиль» (swad/static/elements/profile_edit.php).
 *
 * POST (fetch), CSRF в заголовке X-CSRF-Token. Поля: username, city, country, vk, website.
 * Ответ ВСЕГДА JSON: { ok: true, username, profile_url } или
 *                    { ok: false, error, errors: { поле: текст } }.
 *
 * Аватарка грузится отдельно — swad/controllers/upload_avatar.php.
 */

ob_start();

function pu_reply(array $d, int $code = 200): void
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
        error_log('[profile_update] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        pu_reply(['ok' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
    }
});

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/csrf.php';

if (empty($_SESSION['USERDATA']['id'])) {
    pu_reply(['ok' => false, 'error' => 'Требуется авторизация'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pu_reply(['ok' => false, 'error' => 'Неверный метод'], 405);
}
if (!csrf_valid()) {
    pu_reply(['ok' => false, 'error' => 'Сессия устарела, обновите страницу'], 403);
}

$user_id = (int)$_SESSION['USERDATA']['id'];
$current = (string)($_SESSION['USERDATA']['username'] ?? '');
$errors  = [];

/* Одна строка, без переносов и лишних пробелов, не длиннее $max */
function pu_line(string $v, int $max): string
{
    $v = trim(preg_replace('/\s+/u', ' ', strip_tags($v)) ?? '');
    return mb_substr($v, 0, $max);
}

/* Ссылка: пустая — ок; без схемы — дописываем https://. Только http(s),
   иначе в href профиля можно было бы подсунуть javascript: */
function pu_url(string $v): ?string
{
    $v = trim($v);
    if ($v === '') return '';
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $v)) $v = 'https://' . $v;
    if (!preg_match('~^https?://~i', $v) || !filter_var($v, FILTER_VALIDATE_URL) || mb_strlen($v) > 255) {
        return null;
    }
    return $v;
}

/* ── Ник ── */
$username = trim((string)($_POST['username'] ?? ''));
if ($username === '') {
    $errors['username'] = 'Ник обязателен';
} elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
    $errors['username'] = 'От 3 до 32 символов: латиница, цифры и _';
}

$curr_user = new User();
if (!isset($errors['username']) && strcasecmp($username, $current) !== 0 && $curr_user->checkUsernameExists($username)) {
    $errors['username'] = 'Этот ник уже занят';
}

/* ── Город и страна ── */
$city    = pu_line((string)($_POST['city'] ?? ''), 64);
$country = pu_line((string)($_POST['country'] ?? ''), 64);

/* ── VK: принимаем и ссылку, и просто «id123» / «@name» ── */
$vkRaw = trim((string)($_POST['vk'] ?? ''));
if ($vkRaw !== '' && preg_match('~^@?([a-zA-Z0-9_.]{2,64})$~', $vkRaw, $m)) {
    $vkRaw = 'https://vk.com/' . $m[1];
}
$vk = pu_url($vkRaw);
if ($vk === null) $errors['vk'] = 'Не похоже на ссылку ВКонтакте';

$website = pu_url((string)($_POST['website'] ?? ''));
if ($website === null) $errors['website'] = 'Нужна ссылка вида https://example.com';

if ($errors) {
    pu_reply(['ok' => false, 'error' => 'Проверьте выделенные поля', 'errors' => $errors], 422);
}

try {
    $pdo = (new Database())->connect();
    $pdo->prepare("
        UPDATE users
           SET username = ?, city = ?, country = ?, vk = ?, website = ?, updated = NOW()
         WHERE id = ?
    ")->execute([
        $username,
        $city !== '' ? $city : null,
        $country !== '' ? $country : null,
        $vk !== '' ? $vk : null,
        $website !== '' ? $website : null,
        $user_id,
    ]);
} catch (PDOException $e) {
    // 23000 — гонка: ник заняли между проверкой и записью
    if ($e->getCode() === '23000') {
        pu_reply(['ok' => false, 'error' => 'Этот ник уже занят', 'errors' => ['username' => 'Этот ник уже занят']], 409);
    }
    error_log('[profile_update] ' . $e->getMessage());
    pu_reply(['ok' => false, 'error' => 'Не удалось сохранить, попробуйте ещё раз'], 500);
}

$_SESSION['USERDATA']['username'] = $username;
$_SESSION['USERDATA']['city']     = $city;
$_SESSION['USERDATA']['country']  = $country;
$_SESSION['USERDATA']['vk']       = $vk;
$_SESSION['USERDATA']['website']  = $website;

pu_reply(['ok' => true, 'username' => $username, 'profile_url' => '/player/' . rawurlencode($username)]);
