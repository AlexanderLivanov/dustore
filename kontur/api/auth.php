<?php
// ============================================================
// AUTH.PHP — сессии и контроль доступа (RBAC)
// ============================================================
// Ключевая идея: роль пользователя берётся ИЗ СЕССИИ, которая
// заполняется только при успешном логине из БД. Клиент физически
// не может подделать роль — он не управляет содержимым $_SESSION.
// ============================================================

/**
 * Инициализация безопасной сессии.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,           // до закрытия браузера
        'path'     => '/',
        'httponly' => true,        // JS не может прочитать cookie (защита от XSS-кражи)
        'samesite' => 'Lax',       // защита от CSRF на основных сценариях
        // 'secure' => true,       // включите на HTTPS!
    ]);
    session_start();
}

/**
 * Текущий пользователь или null (гость).
 * Возвращает безопасный набор полей (без хеша пароля).
 */
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }

    // Каждый запрос перечитываем актуальные данные из БД —
    // так изменение роли/счётчиков сразу применяется.
    $stmt = db()->prepare(
        'SELECT id, username, role, subs_articles, subs_fan, faction
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['uid']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Требовать авторизацию. Иначе 401.
 */
function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        json_error('Требуется авторизация', 401);
    }
    return $user;
}

/**
 * Требовать одну из ролей. Иначе 403.
 */
function require_role(array $roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        json_error('Недостаточно прав', 403);
    }
    return $user;
}

/** Модератор или админ. */
function require_moderator(): array
{
    return require_role(['moderator', 'admin']);
}

/** Только админ. */
function require_admin(): array
{
    return require_role(['admin']);
}
