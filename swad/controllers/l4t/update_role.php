<?php
session_start();
require_once('../../config.php');

header('Content-Type: application/json');

// Авторизация — в первую очередь
if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'msg' => 'not authenticated']);
    exit;
}

$userId = (int)$_SESSION['USERDATA']['id'];

// Читаем body один раз
$data = json_decode(file_get_contents('php://input'), true);

// id из запроса должен совпадать с сессией
if ((int)($data['id'] ?? 0) !== $userId) {
    echo json_encode(['success' => false, 'msg' => 'forbidden']);
    exit;
}

// strip_tags для хранения; htmlspecialchars — только при выводе в HTML
$role = mb_substr(strip_tags(trim($data['role'] ?? '')), 0, 40);

$db  = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("UPDATE users SET l4t_role = ? WHERE id = ?");
$stmt->execute([$role, $userId]);

$_SESSION['USERDATA']['l4t_role'] = $role;

echo json_encode(['success' => true, 'role' => $role]);
