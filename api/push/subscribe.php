<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/swad/config.php';

$user_id = $_SESSION['USERDATA']['id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$endpoint = $data['endpoint'];
$p256dh   = $data['keys']['p256dh'];
$auth     = $data['keys']['auth'];

$db = new Database();
$stmt = $db->connect()->prepare("
INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
VALUES (?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
");

$stmt->execute([
    $user_id,
    $endpoint,
    $p256dh,
    $auth,
    $_SERVER['HTTP_USER_AGENT']
]);

echo json_encode(["ok" => true]);
