<?php
session_start();
require_once('../../swad/config.php');

// Функция для кодирования URL-пути (чтобы кириллица не ломала cURL)
function encodeUrlPath($url) {
    $parts = parse_url($url);
    if (!$parts) return $url;
    
    if (isset($parts['path'])) {
        $pathParts = explode('/', $parts['path']);
        $encodedParts = array_map(function($segment) {
            // Не кодируем пустые сегменты, но для остальных применяем rawurlencode
            return $segment === '' ? '' : rawurlencode($segment);
        }, $pathParts);
        $parts['path'] = implode('/', $encodedParts);
    }
    
    if (isset($parts['query'])) {
        parse_str($parts['query'], $queryArray);
        $parts['query'] = http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
    }
    
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host     = $parts['host'] ?? '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path     = $parts['path'] ?? '';
    $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    
    return $scheme . $host . $port . $path . $query . $fragment;
}

// Проверка авторизации
if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['id'] ?? 0);
$user_id  = (int)$_SESSION['USERDATA']['id'];

// Проверяем доступ
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
    http_response_code(404);
    die('Файл не найден');
}

$file_path = $row['asset_file_path'];
$asset_name = $row['name'] ?? 'file';

// Увеличиваем счётчик
$pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
    ->execute([$asset_id]);

// Определяем имя файла для скачивания
$filename = basename(parse_url($file_path, PHP_URL_PATH));
if (empty($filename)) {
    $filename = $asset_name . '.zip';
}
$filename = preg_replace('/\?.*/', '', $filename);

// Проверяем, является ли путь URL
if (strpos($file_path, 'http://') === 0 || strpos($file_path, 'https://') === 0) {
    // Кодируем URL перед отправкой в cURL
    $encoded_url = encodeUrlPath($file_path);
    
    $ch = curl_init($encoded_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Dustore/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || $content === false) {
        // Если не удалось получить через cURL – пробуем редирект как запасной вариант
        header('Location: ' . $file_path);
        exit;
    }
    
    // Определяем MIME-тип
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    $mimeMap = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'flac' => 'audio/flac',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'zip'  => 'application/zip',
        'rar'  => 'application/x-rar-compressed',
        '7z'   => 'application/x-7z-compressed',
        'pdf'  => 'application/pdf',
        'fbx'  => 'application/octet-stream',
        'obj'  => 'application/octet-stream',
        'gltf' => 'model/gltf+json',
        'glb'  => 'model/gltf-binary',
        'blend'=> 'application/octet-stream',
        'unitypackage' => 'application/octet-stream',
    ];
    if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];
    
    // Отдаём файл
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    echo $content;
    exit;
}

// Локальный файл
$local_path = realpath(__DIR__ . '/../../' . ltrim($file_path, '/'));
if (!$local_path || !file_exists($local_path)) {
    http_response_code(404);
    die('Файл не найден на сервере');
}

$filename = basename($local_path);
$mime = mime_content_type($local_path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($local_path));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
readfile($local_path);
exit;