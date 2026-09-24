<?php
/**
 * devs/upload_media.php
 * AJAX-endpoint: загрузка/удаление скриншотов на S3, плюс (с мультиплатформенными
 * билдами) — иконка, вертикальные скриншоты и разрешения для конкретной мобильной
 * платформы (Android/iOS), которые живут в game_builds, а не в games.
 * Структура скриншота в БД: {"id":"uniqid","path":"https://..."}
 */
ob_start();
ini_set('display_errors', '0');
set_time_limit(60);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

const UM_MOBILE_PLATFORMS = ['Android', 'iOS'];

function resp(bool $ok, array $extra = []): never {
    ob_end_clean();
    echo json_encode(['success' => $ok] + $extra);
    exit();
}

if (empty($_SESSION['USERDATA'])) resp(false, ['message' => 'Нет сессии']);

$project_id = (int)($_POST['project_id'] ?? 0);
$type       = $_POST['type'] ?? '';
$studio_id  = (int)($_SESSION['studio_id'] ?? 0);
$platform   = (string)($_POST['platform'] ?? ''); // непусто только для мобильных под-загрузок
if (!$project_id) resp(false, ['message' => 'project_id не передан']);
if ($platform !== '' && !in_array($platform, UM_MOBILE_PLATFORMS, true)) {
    resp(false, ['message' => 'Некорректная платформа']);
}

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/s3.php';

$db   = (new Database())->connect();
$stmt = $db->prepare("SELECT name, screenshots FROM games WHERE id = ? AND developer = ?");
$stmt->execute([$project_id, $studio_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) resp(false, ['message' => 'Проект не найден']);

$org   = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
$gname = preg_replace('/[^a-z0-9]/i', '-', $game['name']);

/**
 * Гарантирует, что для (game_id, platform) есть строка в game_builds (может не
 * быть — например, скриншоты для Android загружают ДО первого билд-файла), и
 * возвращает её.
 */
function um_ensure_build_row(PDO $db, int $gameId, string $platform): array {
    $stmt = $db->prepare("SELECT * FROM game_builds WHERE game_id = ? AND platform = ? LIMIT 1");
    $stmt->execute([$gameId, $platform]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    $db->prepare("INSERT INTO game_builds (game_id, platform) VALUES (?, ?)")->execute([$gameId, $platform]);
    $stmt->execute([$gameId, $platform]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ═══════════════════════════════════════════════════════════════════════
   Платформенные (мобильные) под-ресурсы — скриншоты/иконка/разрешения
   конкретного билда в game_builds. Ветвимся здесь ДО общей логики ниже,
   которая работает с games.screenshots (десктопные/общие скриншоты).
   ═══════════════════════════════════════════════════════════════════════ */
if ($platform !== '') {
    $build = um_ensure_build_row($db, $project_id, $platform);
    $buildScreenshots = json_decode($build['screenshots'] ?? '[]', true) ?: [];

    if ($type === 'build_screenshot') {
        if (count($buildScreenshots) >= 10) resp(false, ['message' => 'Максимум 10 скриншотов']);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
            resp(false, ['message' => 'Файл не получен (error=' . ($_FILES['file']['error'] ?? '?') . ')']);

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif']))
            resp(false, ['message' => 'Допустимые форматы: jpg, png, webp, gif']);
        if ($file['size'] > 15 * 1024 * 1024)
            resp(false, ['message' => 'Максимум 15 МБ на скриншот']);

        $uniq = uniqid();
        $key  = "{$org}/{$gname}/screenshots/" . strtolower($platform) . "-{$uniq}.{$ext}";
        try {
            $url = (new S3Uploader())->uploadFile($file['tmp_name'], $key);
        } catch (Exception $e) {
            resp(false, ['message' => 'S3 ошибка: ' . $e->getMessage()]);
        }
        if (!$url) resp(false, ['message' => 'S3 вернул false — проверьте error_log']);

        $buildScreenshots[] = ['id' => $uniq, 'path' => $url];
        $db->prepare("UPDATE game_builds SET screenshots = ?, updated_at = NOW() WHERE id = ?")
           ->execute([json_encode($buildScreenshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $build['id']]);

        resp(true, ['url' => $url, 'screenshots' => $buildScreenshots]);
    }

    if ($type === 'delete_build_screenshot') {
        $url = $_POST['url'] ?? '';
        try { (new S3Uploader())->deleteFile($url); } catch (Exception $e) { error_log($e->getMessage()); }
        $buildScreenshots = array_values(array_filter($buildScreenshots, fn($s) => ($s['path'] ?? '') !== $url));
        $db->prepare("UPDATE game_builds SET screenshots = ?, updated_at = NOW() WHERE id = ?")
           ->execute([json_encode($buildScreenshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $build['id']]);
        resp(true, ['screenshots' => $buildScreenshots]);
    }

    if ($type === 'build_icon') {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
            resp(false, ['message' => 'Файл не получен (error=' . ($_FILES['file']['error'] ?? '?') . ')']);

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            resp(false, ['message' => 'Допустимые форматы: jpg, png, webp']);
        if ($file['size'] > 5 * 1024 * 1024)
            resp(false, ['message' => 'Максимум 5 МБ для иконки']);

        $key = "{$org}/{$gname}/icon-" . strtolower($platform) . '-' . uniqid() . ".{$ext}";
        try {
            $url = (new S3Uploader())->uploadFile($file['tmp_name'], $key);
        } catch (Exception $e) {
            resp(false, ['message' => 'S3 ошибка: ' . $e->getMessage()]);
        }
        if (!$url) resp(false, ['message' => 'S3 вернул false — проверьте error_log']);

        $oldIcon = $build['icon_url'] ?? '';
        if ($oldIcon && $oldIcon !== $url) {
            try { (new S3Uploader())->deleteFile($oldIcon); } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $db->prepare("UPDATE game_builds SET icon_url = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$url, $build['id']]);

        resp(true, ['url' => $url]);
    }

    if ($type === 'permissions') {
        $text = trim((string)($_POST['text'] ?? ''));
        if (mb_strlen($text) > 4000) resp(false, ['message' => 'Слишком длинный список разрешений']);
        $db->prepare("UPDATE game_builds SET permissions = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$text !== '' ? $text : null, $build['id']]);
        resp(true);
    }

    resp(false, ['message' => 'Неизвестный type для платформы: ' . htmlspecialchars($type)]);
}

/* ═══════════════════════════════════════════════════════════════════════
   Общие (не привязанные к платформе) скриншоты игры — как было раньше.
   ═══════════════════════════════════════════════════════════════════════ */
$screenshots = json_decode($game['screenshots'] ?? '[]', true) ?: [];

/* ── Удаление ─────────────────────────────────────────────────────────── */
if ($type === 'delete_screenshot') {
    $url = $_POST['url'] ?? '';
    try { (new S3Uploader())->deleteFile($url); } catch (Exception $e) { error_log($e->getMessage()); }
    $screenshots = array_values(array_filter($screenshots, fn($s) => ($s['path'] ?? '') !== $url));
    $db->prepare("UPDATE games SET screenshots = ?, updated_at = NOW() WHERE id = ?")
       ->execute([json_encode($screenshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $project_id]);
    resp(true, ['screenshots' => $screenshots]);
}

/* ── Загрузка скриншота ───────────────────────────────────────────────── */
if ($type === 'screenshot') {
    if (count($screenshots) >= 10) resp(false, ['message' => 'Максимум 10 скриншотов']);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
        resp(false, ['message' => 'Файл не получен (error=' . ($_FILES['file']['error'] ?? '?') . ')']);

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif']))
        resp(false, ['message' => 'Допустимые форматы: jpg, png, webp, gif']);
    if ($file['size'] > 15 * 1024 * 1024)
        resp(false, ['message' => 'Максимум 15 МБ на скриншот']);

    $uniq = uniqid();
    $key  = "{$org}/{$gname}/screenshots/screenshot-{$uniq}.{$ext}";
    try {
        $url = (new S3Uploader())->uploadFile($file['tmp_name'], $key);
    } catch (Exception $e) {
        resp(false, ['message' => 'S3 ошибка: ' . $e->getMessage()]);
    }
    if (!$url) resp(false, ['message' => 'S3 вернул false — проверьте error_log']);

    // Структура совместима с БД: {id, path}
    $screenshots[] = ['id' => $uniq, 'path' => $url];

    $db->prepare("UPDATE games SET screenshots = ?, updated_at = NOW() WHERE id = ?")
       ->execute([json_encode($screenshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $project_id]);

    resp(true, ['url' => $url, 'screenshots' => $screenshots]);
}

resp(false, ['message' => 'Неизвестный type: ' . htmlspecialchars($type)]);
