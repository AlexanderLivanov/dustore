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

// Получаем список игр студии
$stmt = $pdo->prepare("SELECT id, name, description FROM games WHERE studio_id = :studio_id");
$stmt->execute(['studio_id' => $studio_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($games !== false) {
    echo json_encode($games);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch games']);
}
