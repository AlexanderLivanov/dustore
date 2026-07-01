<?php
/**
 * devs/upload_media.php
 * AJAX-endpoint: загрузка/удаление скриншотов на S3.
 * Структура скриншота в БД: {"id":"uniqid","path":"https://..."}
 */
ob_start();
ini_set('display_errors', '0');
set_time_limit(60);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function resp(bool $ok, array $extra = []): never {
    ob_end_clean();
    echo json_encode(['success' => $ok] + $extra);
    exit();
}

if (empty($_SESSION['USERDATA'])) resp(false, ['message' => 'Нет сессии']);

$project_id = (int)($_POST['project_id'] ?? 0);
$type       = $_POST['type'] ?? '';
$studio_id  = (int)($_SESSION['studio_id'] ?? 0);
if (!$project_id) resp(false, ['message' => 'project_id не передан']);

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/s3.php';

$db   = (new Database())->connect();
$stmt = $db->prepare("SELECT name, screenshots FROM games WHERE id = ? AND developer = ?");
$stmt->execute([$project_id, $studio_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) resp(false, ['message' => 'Проект не найден']);

$org   = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
$gname = preg_replace('/[^a-z0-9]/i', '-', $game['name']);
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