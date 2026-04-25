<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/user.php');

header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$curr_user = new User();
$curr_user->checkAuth();

$input = json_decode(file_get_contents('php://input'), true);
$review_id = isset($input['review_id']) ? (int)$input['review_id'] : 0;
$reply = trim($input['reply'] ?? '');

// $review_id = 23;
// $reply = "text";

if ($review_id <= 0 || $reply === '') {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

$studio_id = (int)$_SESSION['studio_id'];

// ВАЖНО: проверим, что отзыв относится к игре этой студии
$sql = "
  SELECT r.id, g.developer
  FROM game_reviews r
  JOIN games g ON g.id = r.game_id
  WHERE r.id = ?
  LIMIT 1
";

$stmt = $db->connect()->prepare($sql);
$stmt->execute([$review_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || (int)$row['developer'] !== $studio_id) {
    echo json_encode(['success' => false, 'message' => 'Нет доступа']);
    exit;
}

$check = $db->connect()->prepare("SELECT id FROM review_replies WHERE review_id = ? AND studio_id = ? LIMIT 1");
$check->execute([$review_id, $studio_id]);
$exists = $check->fetch(PDO::FETCH_ASSOC);

if ($exists) {
    $upd = $db->connect()->prepare("UPDATE review_replies SET text = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$reply, $exists['id']]);
} else {
    $ins = $db->connect()->prepare("INSERT INTO review_replies (review_id, studio_id, text, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $ins->execute([$review_id, $studio_id, $reply]);
}

echo json_encode(['success' => true, 'message' => 'OK']);
