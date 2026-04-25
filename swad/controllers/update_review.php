<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'error' => 'auth_required']);
    exit;
}

$user_id = (int)$_SESSION['USERDATA']['id'];
$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$text = trim($_POST['text'] ?? '');

if ($review_id <= 0 || $rating < 1 || $rating > 10 || $text === '') {
    echo json_encode(['success' => false, 'error' => 'invalid_data']);
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Проверяем, что отзыв принадлежит пользователю
$stmt = $pdo->prepare("SELECT id FROM game_reviews WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$review_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'no_access']);
    exit;
}

// Обновляем
$upd = $pdo->prepare("UPDATE game_reviews SET rating = ?, text = ?, updated_at = NOW() WHERE id = ?");
$upd->execute([$rating, $text, $review_id]);

echo json_encode(['success' => true]);
