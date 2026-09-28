<?php
declare(strict_types=1);

/**
 * swad/controllers/avatar_history.php — GET.
 * Отдаёт историю аватарок текущего пользователя для листалки в pe-avatar.
 * Публичного просмотра чужой истории здесь нет — это self-service своей
 * учётки внутри уже открытого модального окна редактирования профиля,
 * не отдельная страница на профиле другого юзера.
 */

ob_start();

function reply(array $d): void
{
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[avatar_history] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        reply(['success' => false, 'error' => 'Внутренняя ошибка сервера (код: fatal)']);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/_avatar_schema.php';
require_once __DIR__ . '/_avatars.php';

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    reply(['success' => false, 'error' => 'Требуется авторизация']);
}
$user_id = (int)$_SESSION['USERDATA']['id'];

try {
    $pdo = (new Database())->connect();
    AvatarSchema::ensure($pdo);

    $st = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $currentUrl = (string)($st->fetchColumn() ?: '');

    $avatars = avatar_history_list($pdo, $user_id, $currentUrl);

    reply(['success' => true, 'avatars' => $avatars, 'current_url' => $currentUrl ?: null]);
} catch (Throwable $e) {
    error_log('[avatar_history] ' . get_class($e) . ': ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Не удалось загрузить историю']);
}
