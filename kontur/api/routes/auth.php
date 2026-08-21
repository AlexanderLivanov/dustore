<?php
// ============================================================
// ROUTES/AUTH.PHP — регистрация, вход, выход, профиль
// ============================================================

function handle_auth(string $method, array $parts): void
{
    $action = $parts[1] ?? '';

    match ("$method $action") {
        'POST register' => auth_register(),
        'POST login'    => auth_login(),
        'POST logout'   => auth_logout(),
        'GET me'        => auth_me(),
        default         => json_error('Неизвестный метод авторизации', 404),
    };
}

// ── Регистрация ──────────────────────────────────────────
function auth_register(): void
{
    $data = read_json_body();
    $username = require_field($data, 'username', 24);
    $password = require_field($data, 'password', 72); // bcrypt лимит 72 байта

    // Валидация имени: буквы/цифры/подчёркивание, 3-24 символа
    if (!preg_match('/^[\p{L}0-9_]{3,24}$/u', $username)) {
        json_error('Имя: 3–24 символа, только буквы, цифры и _', 422);
    }
    if (mb_strlen($password) < 6) {
        json_error('Пароль должен быть не короче 6 символов', 422);
    }

    // Проверка уникальности
    $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_error('Это имя уже занято', 409);
    }

    // Хешируем пароль (никогда не храним в открытом виде!)
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
    );
    // Новичок всегда получает роль user. Роль нельзя выбрать при регистрации.
    $stmt->execute([$username, $hash, 'user']);
    $uid = (int) db()->lastInsertId();

    // Сразу логиним
    start_session();
    session_regenerate_id(true); // защита от session fixation
    $_SESSION['uid'] = $uid;

    json_response([
        'user' => [
            'id' => $uid, 'username' => $username, 'role' => 'user',
            'subs_articles' => 0, 'subs_fan' => 0, 'faction' => 'none',
        ],
    ], 201);
}

// ── Вход ─────────────────────────────────────────────────
function auth_login(): void
{
    $data = read_json_body();
    $username = require_field($data, 'username', 24);
    $password = require_field($data, 'password', 72);

    $stmt = db()->prepare(
        'SELECT id, username, password_hash, role, subs_articles, subs_fan, faction
         FROM users WHERE username = ?'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // ВАЖНО: одинаковое сообщение для «нет пользователя» и «неверный пароль» —
    // чтобы не давать подсказок атакующему (user enumeration).
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Неверное имя пользователя или пароль', 401);
    }

    // Если алгоритм хеширования устарел — обновим прозрачно
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([$newHash, $user['id']]);
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];

    unset($user['password_hash']); // не отдаём хеш клиенту
    json_response(['user' => $user]);
}

// ── Выход ────────────────────────────────────────────────
function auth_logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    json_response(['ok' => true]);
}

// ── Текущий профиль ──────────────────────────────────────
function auth_me(): void
{
    $user = current_user();
    json_response(['user' => $user]); // null если гость
}
