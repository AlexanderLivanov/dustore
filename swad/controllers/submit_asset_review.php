<?php
session_start();
header('Content-Type: application/json');
require_once('../../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_POST['asset_id'] ?? 0);
$rating   = max(1, min(10, intval($_POST['rating'] ?? 5)));
$text     = trim($_POST['text'] ?? '');
$user_id  = (int)$_SESSION['USERDATA']['id'];

if ($asset_id <= 0 || strlen($text) < 5) {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
    exit;
}

try {
    // Upsert — один отзыв на ассет
    $stmt = $pdo->prepare("
        INSERT INTO asset_reviews (asset_id, user_id, rating, text, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), text = VALUES(text), created_at = NOW()
    ");
    $stmt->execute([$asset_id, $user_id, $rating, $text]);

    // Пересчитать avg_rating
    $pdo->prepare("
        UPDATE assets SET avg_rating = (
            SELECT AVG(rating) FROM asset_reviews WHERE asset_id = ?
        ) WHERE id = ?
    ")->execute([$asset_id, $asset_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
