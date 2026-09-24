<?php
declare(strict_types=1);

/**
 * swad/controllers/l4t/_csrf.php — CSRF для контроллеров L4T.
 *
 * БЫЛО: файл был копией swad/controllers/csrf.php, а контроллеры L4T
 * вызывали csrf_check() и csrf_guard_json(), которых в нём нет.
 * Итог — Fatal error «Call to undefined function» на КАЖДОЙ записи:
 * создание заявки, отклик, сохранение профиля. Клиент получал HTML
 * вместо JSON и молча показывал «не сохранилось».
 *
 * СТАЛО: базовые функции берём из общего хелпера (одна точка правды),
 * здесь только две обёртки, которые ждут контроллеры.
 */

require_once __DIR__ . '/../csrf.php';

if (!function_exists('csrf_check')) {
    /** Для обычных форм (upsert_bid.php): токен в $_POST['csrf'] или заголовке. */
    function csrf_check(): bool
    {
        return csrf_valid();
    }
}

if (!function_exists('csrf_guard_json')) {
    /**
     * Для fetch-эндпоинтов. Токен ищем в заголовке X-CSRF-Token и в теле.
     * Не прошёл — отвечаем JSON'ом 403 и выходим: клиент делает r.json(),
     * и HTML-ошибка сломала бы ему разбор.
     */
    function csrf_guard_json(?array $body): void
    {
        if (csrf_valid($body ?? [])) return;
        http_response_code(403);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'msg' => 'csrf', 'message' => 'Сессия устарела, обновите страницу'],
            JSON_UNESCAPED_UNICODE);
        exit;
    }
}
