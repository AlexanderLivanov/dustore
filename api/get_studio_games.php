<?php
require_once('../swad/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_GET['studio_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No studio ID provided']);
    exit;
}

$studio_id = intval($_GET['studio_id']);

$db = new Database();
$pdo = $db->connect();

// Получаем данные студии
$stmt = $pdo->prepare("SELECT * FROM games WHERE developer = :id");
$stmt->execute(['id' => $studio_id]);
$studio = $stmt->fetch(PDO::FETCH_ASSOC);

if ($studio) {
    echo json_encode([
        'id' => $studio['id'],
        'name' => $studio['name'],
        'status' => $studio['status']
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Studio not found']);
}
