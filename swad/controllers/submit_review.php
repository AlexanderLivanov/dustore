<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once('../config.php');
require_once('game.php');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Вы не авторизованы']);
    exit();
}

$userId = $_SESSION['USERDATA']['id'];
$gameId = $_POST['game_id'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$text = trim($_POST['text'] ?? '');

if (!$gameId || !$rating || !$text) {
    echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    exit();
}

$gameController = new Game();

// Проверяем, есть ли уже отзыв от пользователя
if ($gameController->userHasReview($gameId, $userId)) {
    echo json_encode(['success' => false, 'error' => 'Вы уже оставили отзыв']);
    exit();
}

// Отправляем отзыв
$result = $gameController->submitReview($gameId, $userId, $rating, $text);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось добавить отзыв']);
}
