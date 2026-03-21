<?php
// 20.03.2026 (c) Alexander Livanov 
// auth.php для проверки токена студии при загрузке билдов игры
header('Content-Type: application/json');

require_once __DIR__ . '/../swad/config.php'; // PDO

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['token'])) {
    http_response_code(400);
    echo json_encode(["error" => "token required"]);
    exit;
}

$token = $input['token'];

$stmt = $pdo->prepare("SELECT id, name FROM studios WHERE token = ?");
$stmt->execute([$token]);
$studio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$studio) {
    http_response_code(403);
    echo json_encode(["error" => "invalid token"]);
    exit;
}

echo json_encode([
    "access_key" => "TB8O2A0SWFE7Y43RNAK4",
    "secret_key" => "vG2cBoa14ZaDFALjDL1YtgG0ScuAesrDMXpiZQ1Y",
    "endpoint"   => "https://s3.regru.cloud",
    "bucket"     => "dustore.private.games",
    "prefix"     => "studio_" . $studio["id"], // важно
]);
