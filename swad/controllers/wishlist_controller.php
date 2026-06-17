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
$user_id  = (int)$_SESSION['USERDATA']['id'];

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset_id']);
    exit;
}

try {
    $exists = $pdo->prepare("SELECT id FROM asset_wishlist WHERE player_id = ? AND asset_id = ? LIMIT 1");
    $exists->execute([$user_id, $asset_id]);

    if ($exists->fetch()) {
        // Удаляем из вишлиста
        $pdo->prepare("DELETE FROM asset_wishlist WHERE player_id = ? AND asset_id = ?")
            ->execute([$user_id, $asset_id]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        // Добавляем в вишлист
        $pdo->prepare("INSERT INTO asset_wishlist (player_id, asset_id, added_at) VALUES (?, ?, NOW())")
            ->execute([$user_id, $asset_id]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
