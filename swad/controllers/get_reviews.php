<?php
session_start();
require_once('../config.php');
require_once('game.php');

header('Content-Type: application/json');

$game_id = $_GET['game_id'] ?? 0;
if ($game_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID игры']);
    exit;
}

$gameController = new Game();
$reviews = $gameController->getReviews($game_id); // массив отзывов: ['username','avatar','rating','text','date']

echo json_encode(['success' => true, 'reviews' => $reviews]);
