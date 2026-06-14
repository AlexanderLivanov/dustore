<?php
session_start();
header('Content-Type: application/json');
require_once('../../swad/config.php');

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['asset_id'] ?? 0);
if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.profile_picture
        FROM asset_reviews r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.asset_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$asset_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'reviews' => $reviews]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
