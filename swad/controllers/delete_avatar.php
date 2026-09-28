<?php
declare(strict_types=1);

/**
 * swad/controllers/delete_avatar.php — POST.
 * Удаляет одну аватарку из истории пользователя (свою, только свою).
 *
 * POST avatar_id (опционально) — конкретная строка из user_avatars,
 * которую сейчас показывает листалка. Без него — трактуем как "удали
 * текущую" (users.profile_picture).
 *
 * Если удалили именно текущую — следующая по свежести оставшаяся становится
 * новой текущей (users.profile_picture обновляется, сессия тоже); если
 * история опустела — profile_picture становится NULL, фронт откатывается
 * на дефолтную картинку сам (как и при полном отсутствии аватарки везде
 * по сайту).
 *
 * Тот же ob_start()+shutdown-catch+reply() каркас, что в upload_avatar.php —
 * чтобы любой фатал по пути превращался в читаемый JSON, а не в пустое тело.
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
        error_log('[delete_avatar] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        reply(['success' => false, 'error' => 'Внутренняя ошибка сервера (код: fatal)']);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/_avatar_schema.php';
require_once __DIR__ . '/_avatars.php';

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    reply(['success' => false, 'error' => 'Требуется авторизация']);
}
$user_id = (int)$_SESSION['USERDATA']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reply(['success' => false, 'error' => 'Неверный метод']);
}

if (!csrf_valid()) {
    http_response_code(403);
    reply(['success' => false, 'error' => 'Сессия устарела, обновите страницу']);
}

$avatarId = isset($_POST['avatar_id']) && $_POST['avatar_id'] !== ''
    ? (int)$_POST['avatar_id']
    : null;

try {
    $pdo = (new Database())->connect();
    AvatarSchema::ensure($pdo);

    $st = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $currentUrl = (string)($st->fetchColumn() ?: '');

    $result = avatar_history_delete($pdo, $user_id, $avatarId, $currentUrl);

    if (!$result['deleted']) {
        reply(['success' => false, 'error' => 'Аватарка не найдена']);
    }

    if ($result['promoted']) {
        $newUrl = $result['new_current_url']; // может быть null — история опустела
        $pdo->prepare("UPDATE users SET profile_picture = ?, updated = NOW() WHERE id = ?")
            ->execute([$newUrl, $user_id]);
        $_SESSION['USERDATA']['profile_picture'] = $newUrl;
    }

    // Чистка в S3 — best-effort и не блокирующая: строка из истории уже
    // удалена из БД в любом случае, файл в бакете нам не критичен (как и
    // в upload_avatar.php, реальные ключи/бакет тут недоступны в тестовом
    // окружении — эта ветка просто не должна валить остальной ответ).
    $s3Key = $result['deleted']['s3_key'] ?? null;
    $target = $s3Key ?: $result['deleted']['url'];
    if ($target && defined('AWS_S3_KEY') && defined('AWS_S3_SECRET')
        && defined('AWS_S3_REGION') && defined('AWS_S3_ENDPOINT')
        && defined('AWS_S3_BUCKET_USERCONTENT')) {
        try {
            require_once __DIR__ . '/s3.php';
            (new S3Uploader())->deleteFile($target);
        } catch (Throwable $e) {
            error_log('[delete_avatar] s3 cleanup failed: ' . $e->getMessage());
        }
    }

    reply([
        'success'  => true,
        'promoted' => $result['promoted'],
        'new_url'  => $result['new_current_url'],
    ]);
} catch (Throwable $e) {
    error_log('[delete_avatar] ' . get_class($e) . ': ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Не удалось удалить аватарку']);
}
