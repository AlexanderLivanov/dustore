<?php
session_start();
require_once('../../config.php');

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$role = trim($data['role'] ?? '');
$role = mb_substr($role, 0, 40);
$role = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

$userId = (int)$data['id'];

$stmt = $pdo->prepare("UPDATE users SET l4t_role = ? WHERE id = ?");
$stmt->execute([$role, $userId]);

// ОБНОВЛЯЕМ СЕССИЮ, ИНАЧЕ ПОСЛЕ F5 БУДЕТ СТАРЬЁ
if (isset($_SESSION['USERDATA'])) {
    $_SESSION['USERDATA']['l4t_role'] = $role;
}

echo json_encode([
    "success" => true,
    "role" => $role
]);
