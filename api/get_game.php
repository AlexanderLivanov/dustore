<?php
require_once('../swad/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No game ID provided']);
    exit;
}

$game_id = $_GET['id']; // Может быть строкой

$db = new Database();
$pdo = $db->connect();

// Получаем данные игры
$stmt = $pdo->prepare("SELECT * FROM games WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if ($game) {
    // Парсим screenshots если это JSON строка
    if (isset($game['screenshots']) && is_string($game['screenshots'])) {
        $game['screenshots'] = json_decode($game['screenshots'], true);
    }

    // Парсим другие JSON поля если есть
    if (isset($game['system_requirements']) && is_string($game['system_requirements'])) {
        $game['system_requirements'] = json_decode($game['system_requirements'], true);
    }

    echo json_encode($game);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Game not found']);
}
