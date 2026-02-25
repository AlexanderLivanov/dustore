<?php
session_start();
require_once('../../config.php');

header('Content-Type: application/json');

// Авторизация — до любых других операций
if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'msg' => 'not authenticated']);
    exit;
}

$userId = (int)$_SESSION['USERDATA']['id'];

// Читаем body ОДИН раз
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Если в запросе передан id — проверяем совпадение с сессией
if (isset($data['id']) && (int)$data['id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'msg' => 'forbidden']);
    exit;
}

$exp   = $data['exp'] ?? [];
$clean = [];

foreach ($exp as $e) {
    if (!isset($e['role'], $e['years'])) continue;

    $clean[] = [
        'role'  => mb_substr(strip_tags((string)$e['role']), 0, 30),
        'years' => min(50, max(0, (int)$e['years'])),
    ];
}

$db  = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("UPDATE users SET l4t_exp = ? WHERE id = ?");
$stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $userId]);

$_SESSION['USERDATA']['l4t_exp'] = json_encode($clean, JSON_UNESCAPED_UNICODE);

echo json_encode(['success' => true]);
