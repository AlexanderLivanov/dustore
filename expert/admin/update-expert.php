<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$expertId = isset($input['id']) ? (int)$input['id'] : 0;
$action = $input['action'] ?? '';

if (!$expertId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

// Обновляем статус эксперта
$stmt = $pdo->prepare("UPDATE experts SET status=?, updated_at=NOW() WHERE id=?");
$success = $stmt->execute([$action === 'approve' ? 'approved' : 'rejected', $expertId]);

if ($success) {
    echo json_encode([
        'success' => true,
        'expert_id' => $expertId,
        'new_status' => $action === 'approve' ? 'approved' : 'rejected'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при обновлении эксперта']);
}
