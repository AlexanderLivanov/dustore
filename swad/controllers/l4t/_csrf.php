<?php
declare(strict_types=1);

/**
 * swad/controllers/csrf.php — общий CSRF-хелпер платформы.
 *
 * Токен один на сессию. Он не уходит в URL и не попадает в Referer,
 * поэтому переживать его ротацию на каждый запрос смысла нет.
 *
 * Сравнение через hash_equals: обычный === выходит на первом несовпавшем
 * байте, и по времени ответа токен подбирается посимвольно.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Готовое скрытое поле для формы. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="'
             . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_valid')) {
    /**
     * Токен ищем в POST-поле, заголовке X-CSRF-Token и в JSON-теле —
     * чтобы годилось и для обычных форм, и для fetch.
     */
    function csrf_valid(?array $jsonBody = null): bool
    {
        $sent = $_POST['csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($jsonBody['csrf'] ?? '');

        $have = $_SESSION['csrf'] ?? '';
        return $have !== '' && is_string($sent) && hash_equals($have, $sent);
    }
}