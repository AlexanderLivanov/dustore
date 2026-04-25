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
$studio = $stmt->fetchAll(PDO::FETCH_ASSOC);

// print_r($studio);

if ($studio) {
    // foreach($studio as $st){
    //     echo json_encode([
    //         'id' => $st['id'],
    //         'name' => $st['name'],
    //         'status' => $st['status']
    //     ]);
    // }
    echo json_encode($studio);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Studio not found']);
}
