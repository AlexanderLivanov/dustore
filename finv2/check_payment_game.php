<?php

/**
 * check_payment_game.php
 * GET /finv2/check_payment_game.php?payment_id=...
 * Response: { "status": "pending"|"succeeded"|"canceled" }
 *
 * Used by the frontend to poll payment status after redirect.
 */

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

/* ── Fast path: check DB first ── */
$db  = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT status FROM game_orders WHERE payment_id = ? AND user_id = ?");
$stmt->execute([$paymentId, (int)$_SESSION['USERDATA']['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['status'] === 'succeeded') {
    // Webhook already processed this — no need to hit YooKassa API
    echo json_encode(['status' => 'succeeded']);
    exit;
}

/* Also check infrastructure payments */
$stmt2 = $pdo->prepare("SELECT status FROM infra_payments WHERE payment_id = ? AND user_id = ?");
$stmt2->execute([$paymentId, (int)$_SESSION['USERDATA']['id']]);
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($row2 && $row2['status'] === 'succeeded') {
    echo json_encode(['status' => 'succeeded']);
    exit;
}

/* ── Fallback: ask YooKassa ── */
try {
    $client = new Client();
    $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

    $payment = $client->getPaymentInfo($paymentId);
    $status  = $payment->getStatus(); // pending | waiting_for_capture | succeeded | canceled

    // Normalize to what frontend expects
    if ($status === 'succeeded') {
        // Optionally sync DB here if webhook was slow
        if ($row && $row['status'] !== 'succeeded') {
            syncGamePurchaseFromApi($pdo, $paymentId, $payment);
        }
        echo json_encode(['status' => 'succeeded']);
    } elseif ($status === 'canceled') {
        echo json_encode(['status' => 'canceled']);
    } else {
        echo json_encode(['status' => 'pending']);
    }
} catch (Exception $e) {
    error_log('check_payment_game error: ' . $e->getMessage());
    // Don't expose error — just say pending so polling continues
    echo json_encode(['status' => 'pending']);
}

/**
 * Fallback: activate purchase directly from API response
 * (webhook may not have arrived yet)
 */
function syncGamePurchaseFromApi(PDO $pdo, string $paymentId, $payment): void
{
    $metadata = $payment->getMetadata();
    $orderId  = $metadata['order_id'] ?? null;
    $gameId   = $metadata['game_id']  ?? null;
    $userId   = $metadata['user_id']  ?? null;

    if (!$orderId || !$gameId || !$userId) return;

    // Add to library (ignore duplicates)
    $pdo->prepare("
        INSERT IGNORE INTO library (player_id, game_id, purchased_at)
        VALUES (?, ?, NOW())
    ")->execute([(int)$userId, (int)$gameId]);

    $pdo->prepare("UPDATE game_orders SET status = 'succeeded' WHERE id = ?")
        ->execute([(int)$orderId]);
}
