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

try {
    // 1. Проверяем, существует ли заявка и активна ли она
    $stmt = $pdo->prepare("SELECT id, stage FROM bids WHERE id = ?");
    $stmt->execute([$bid_id]);
    $bid = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bid) {
        echo json_encode(['success' => false, 'message' => 'Заявка не найдена']);
        exit;
    }

    if ($bid['stage'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Заявка уже закрыта или неактивна']);
        exit;
    }

    // 2. Защита от дублей
    $chk = $pdo->prepare("SELECT id FROM responds WHERE bid_id = ? AND user_id = ?");
    $chk->execute([$bid_id, $user_id]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Вы уже откликались на эту заявку']);
        exit;
    }

    // 3. Вставляем отклик со статусом 'ожидает' (или 'pending' — выберите подходящий)
    $pdo->prepare("INSERT INTO responds (bid_id, user_id, message, status, created_at)
                   VALUES (?, ?, ?, 'ожидает', NOW())")
        ->execute([$bid_id, $user_id, $message]);

    $respond_id = $pdo->lastInsertId();

    // 4. Увеличиваем счётчик откликов в заявке
    $pdo->prepare("UPDATE bids SET responses = responses + 1 WHERE id = ?")
        ->execute([$bid_id]);

    // 5. Возвращаем успех с id созданного отклика
    echo json_encode([
        'success'    => true,
        'respond_id' => $respond_id,
        'message'    => 'Отклик успешно отправлен'
    ]);

} catch (PDOException $e) {
    error_log('Ошибка отклика: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Произошла ошибка при отправке отклика'
    ]);
}