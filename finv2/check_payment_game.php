<?php

require_once('../vendor/autoload.php');
require_once('../swad/config.php');

use YooKassa\Client;

header('Content-Type: application/json');

session_start();

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

$paymentId = trim($_GET['payment_id'] ?? '');

if (empty($paymentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_id обязателен']);
    exit;
}

/* ── DB ── */
$db  = new Database();
$pdo = $db->connect();

/* ── Fast path: DB check ── */
$stmt = $pdo->prepare("
    SELECT id, status 
    FROM game_orders 
    WHERE payment_id = ? AND user_id = ?
");
$stmt->execute([$paymentId, (int)$_SESSION['USERDATA']['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['status'] === 'succeeded') {
    echo json_encode(['status' => 'succeeded']);
    exit;
}

/* ── Infra payments check ── */
$stmt2 = $pdo->prepare("
    SELECT status 
    FROM infra_payments 
    WHERE payment_id = ? AND user_id = ?
");
$stmt2->execute([$paymentId, (int)$_SESSION['USERDATA']['id']]);
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($row2 && $row2['status'] === 'succeeded') {
    echo json_encode(['status' => 'succeeded']);
    exit;
}

/* ── Fallback: YooKassa API ── */
try {
    $client = new Client();
    $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

    $payment = $client->getPaymentInfo($paymentId);
    $status  = $payment->getStatus();

    if ($status === 'succeeded') {

        // 🔥 ВАЖНО: всегда синхронизируем БД
        syncGamePurchaseFromApi($pdo, $paymentId, $payment);

        echo json_encode(['status' => 'succeeded']);
    } elseif ($status === 'canceled') {
        echo json_encode(['status' => 'canceled']);
    } else {
        echo json_encode(['status' => 'pending']);
    }

} catch (Exception $e) {
    error_log('check_payment_game error: ' . $e->getMessage());
    echo json_encode(['status' => 'pending']);
}


/**
 * Синхронизация покупки напрямую из API
 */
function syncGamePurchaseFromApi(PDO $pdo, string $paymentId, $payment): void
{
    $metadata = $payment->getMetadata();

    $orderId = $metadata['order_id'] ?? null;
    $gameId  = $metadata['game_id']  ?? null;
    $userId  = $metadata['user_id']  ?? null;

    if (!$orderId || !$gameId || !$userId) return;

    /* ── Добавляем игру ── */
    $pdo->prepare("
        INSERT IGNORE INTO library (player_id, game_id, purchased, date)
        VALUES (?, ?, 1, NOW())
    ")->execute([(int)$userId, (int)$gameId]);

    /* ── Обновляем заказ ── */
    $pdo->prepare("
        UPDATE game_orders 
        SET status = 'succeeded', completed_at = NOW()
        WHERE payment_id = ?
    ")->execute([$paymentId]);
}