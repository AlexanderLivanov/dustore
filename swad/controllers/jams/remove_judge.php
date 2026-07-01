<?php
require_once('../../config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['jam_id']) || empty($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'jam_id and user_id are required']);
    exit;
}

$jamId  = (int)$input['jam_id'];
$userId = (int)$input['user_id'];

$db = new Database();
$pdo = $db->connect();

try {
    $stmt = $pdo->prepare("DELETE FROM jam_judges WHERE jam_id = ? AND user_id = ?");
    $stmt->execute([$jamId, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Запись не найдена']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}