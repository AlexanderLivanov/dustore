<?php
require_once('../../config.php');
session_start();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION['USERDATA']['id'])) {
    echo json_encode(["success" => false, "msg" => "no auth"]);
    exit;
}

$userId = $_SESSION['USERDATA']['id'];

$exp = $data['exp'] ?? [];

// жёсткая фильтрация, без твоих ебаных XSS
$clean = [];
foreach ($exp as $e) {
    $clean[] = [
        "role"  => mb_substr(strip_tags($e['role']), 0, 30),
        "years" => min(50, max(0, (int)$e['years']))
    ];
}

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("UPDATE users SET l4t_exp = ? WHERE id = ?");
$stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $userId]);

echo json_encode(["success" => true]);
