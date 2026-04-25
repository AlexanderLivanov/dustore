<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../swad/config.php'; // подключение к БД

$db = new Database();
$pdo = $db->connect();

$headers = getallheaders();
$token = $headers['X-Desktop-Token'] ?? null;

if (!$token) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No token"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$app = $data['app'] ?? null;

// Проверяем токен в desktop_tokens
$stmt = $pdo->prepare("SELECT user_id FROM desktop_tokens WHERE token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Invalid token"]);
    exit;
}

// Сохраняем активность
$stmt = $pdo->prepare("
    INSERT INTO user_activity (user_id, token, current_app, last_seen)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        current_app = VALUES(current_app),
        last_seen = NOW()
");
$stmt->execute([$user['user_id'], $token, $app]);

echo json_encode(["ok" => true]);
