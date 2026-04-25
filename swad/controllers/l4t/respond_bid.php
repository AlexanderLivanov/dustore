<?php
require_once('../../../swad/config.php');
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$bid_id  = (int)($data['bid_id']  ?? 0);
$message = mb_substr(trim($data['message'] ?? ''), 0, 1000);
$user_id = (int)$_SESSION['USERDATA']['id'];

if (!$bid_id) {
    echo json_encode(['success' => false, 'message' => 'Нет bid_id']);
    exit;
}

$db  = new Database();
$pdo = $db->connect('desl4t');

// Защита от дублей
$chk = $pdo->prepare("SELECT id FROM responds WHERE bid_id = ? AND user_id = ?");
$chk->execute([$bid_id, $user_id]);
if ($chk->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Вы уже откликались на эту заявку']);
    exit;
}

$pdo->prepare("INSERT INTO responds (bid_id, user_id, message, status, created_at)
               VALUES (?, ?, ?, 'новый', NOW())")
    ->execute([$bid_id, $user_id, $message]);

// Увеличиваем счётчик откликов в заявке
$pdo->prepare("UPDATE bids SET responses = responses + 1 WHERE id = ?")
    ->execute([$bid_id]);

echo json_encode(['success' => true]);
