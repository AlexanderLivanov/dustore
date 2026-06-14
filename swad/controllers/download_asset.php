<?php
// download_asset.php
session_start();
require_once('../../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['id'] ?? 0);
$user_id  = (int)$_SESSION['USERDATA']['id'];

// Проверяем владение
$stmt = $pdo->prepare("SELECT al.id FROM asset_library al WHERE al.player_id = ? AND al.asset_id = ? LIMIT 1");
$stmt->execute([$user_id, $asset_id]);
if (!$stmt->fetch()) {
    http_response_code(403);
    die('Нет доступа к этому ассету');
}

// Получаем путь к файлу
$asset = $pdo->prepare("SELECT name, asset_file_path FROM assets WHERE id = ? LIMIT 1");
$asset->execute([$asset_id]);
$row = $asset->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['asset_file_path'])) {
    die('Файл не найден');
}

$file = realpath(__DIR__ . '/../../' . ltrim($row['asset_file_path'], '/'));

if (!$file || !file_exists($file)) {
    die('Файл не найден на сервере');
}

// Увеличиваем счётчик
$pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
    ->execute([$asset_id]);

// Отдаём файл
$filename = basename($file);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
exit;
