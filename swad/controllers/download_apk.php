<?php
/**
 * swad/controllers/download_apk.php
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/game.php';

$game_id = filter_input(INPUT_GET, 'game_id', FILTER_VALIDATE_INT);
if (!$game_id || $game_id <= 0) { http_response_code(400); exit('Invalid game ID'); }

$db  = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("
    SELECT g.id, g.name, g.platforms, g.game_zip_url, g.status
    FROM games g WHERE g.id = ? AND g.status = 'published'
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) { http_response_code(404); exit('Game not found'); }

// strtolower — иначе 'Android' с большой буквы не матчится
$platforms = array_map(fn($p) => strtolower(trim($p)), explode(',', $game['platforms'] ?? ''));
if (!in_array('android', $platforms)) { http_response_code(403); exit('Not an Android game'); }

$s3_url = $game['game_zip_url'] ?? '';
if (empty($s3_url)) { http_response_code(404); exit('No file'); }

$allowed_host = 's3.regru.cloud';
$parsed = parse_url($s3_url);
if (!isset($parsed['host']) || !str_ends_with($parsed['host'], $allowed_host)) {
    http_response_code(403); exit('Invalid source');
}

$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $game['name']);
$filename  = $safe_name . '-android.apk';

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (ob_get_level()) ob_end_clean();

$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 30,
        'header'  => 'Connection: close',
    ]
]);

$stream = @fopen($s3_url, 'rb', false, $ctx);
if (!$stream) {
    http_response_code(502);
    exit('Storage unavailable');
}

try {
    $pdo->prepare("UPDATE games SET downloads = COALESCE(downloads,0)+1 WHERE id=?")
        ->execute([$game_id]);
} catch (Exception $e) {}

stream_copy_to_stream($stream, fopen('php://output', 'wb'));
fclose($stream);