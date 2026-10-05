<?php
// /source.json (см. RewriteRule в .htaccess) — AltStore-источник, собранный из БД.
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/ios_source.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('Access-Control-Allow-Origin: *');
try {
    $src = ios_build_source((new Database())->connect());
} catch (Throwable $e) {
    error_log('source.json: ' . $e->getMessage());
    http_response_code(500);
    exit('{"error":"source unavailable"}');
}
echo json_encode($src, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
