<?php
require_once('../../config.php'); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (empty($_GET['jam_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'jam_id is required']);
    exit;
}

$jamId = (int)$_GET['jam_id'];

$db = new Database();
$pdo = $db->connect();

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username
        FROM jam_judges jj
        JOIN users u ON u.id = jj.user_id
        WHERE jj.jam_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$jamId]);
    $judges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($judges);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}