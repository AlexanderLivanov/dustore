<?php
require_once('../config.php');
session_start();

$game_id = (int)($_GET['game_id'] ?? 0);
if ($game_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid game_id']);
    exit;
}

$db = new Database();
$pdo = $db->connect();
$stmt = $pdo->prepare("SELECT game_zip_url FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if ($game && !empty($game['game_zip_url'])) {
    echo json_encode(['game_zip_url' => $game['game_zip_url']]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Game URL not found']);
}