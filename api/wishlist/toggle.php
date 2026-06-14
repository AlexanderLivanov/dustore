<?php
/**
 * /api/wishlist/toggle.php
 * POST { game_id: int }
 * → { ok: true, action: 'added'|'removed', total: int }
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../swad/config.php';

// Только авторизованные
if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$game_id = (int)($body['game_id'] ?? 0);
$user_id = (int)$_SESSION['USERDATA']['id'];

if (!$game_id) {
    echo json_encode(['ok' => false, 'message' => 'Invalid game_id']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

// Проверяем что игра существует и анонс включён
$game = $pdo->prepare("SELECT id FROM games WHERE id = ? AND announce_enabled = 1");
$game->execute([$game_id]);
if (!$game->fetch()) {
    echo json_encode(['ok' => false, 'message' => 'Game not found or announce disabled']);
    exit;
}

// Проверяем текущий статус вишлиста
$check = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND game_id = ?");
$check->execute([$user_id, $game_id]);
$exists = $check->fetch();

if ($exists) {
    // Убираем из вишлиста
    $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND game_id = ?")
        ->execute([$user_id, $game_id]);
    $action = 'removed';
} else {
    // Добавляем в вишлист
    $pdo->prepare("INSERT INTO wishlists (user_id, game_id) VALUES (?, ?)")
        ->execute([$user_id, $game_id]);
    $action = 'added';
}

// Возвращаем актуальный счётчик
$total = (int)$pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE game_id = ?")
                   ->execute([$game_id]) 
         ? $pdo->query("SELECT COUNT(*) FROM wishlists WHERE game_id = $game_id")->fetchColumn()
         : 0;

echo json_encode([
    'ok'     => true,
    'action' => $action,
    'total'  => $total,
]);