<?php
session_start();
require_once('../config.php');
require_once('game.php');

header('Content-Type: application/json');

if (empty($_SESSION['USERDATA'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$gameId = $_POST['game_id'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$userId = $_SESSION['USERDATA']['id'];
// print_r($_SESSION);

if ($gameId <= 0 || $rating < 1 || $rating > 10) {
    echo json_encode(['error' => 'invalid_data']);
    exit;
}

$gameController = new Game();
$gameController->addRating($gameId, $userId, $rating);

$newRating = $gameController->getAverageRating($gameId);
echo json_encode(['success' => true, 'avg' => $newRating['avg'], 'count' => $newRating['count']]);
