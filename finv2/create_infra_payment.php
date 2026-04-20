<?php

/**
 * create_infra_payment.php
 * POST /finv2/create_infra_payment.php
 * Body: { "amount": 50, "game_id": 42 }
 * Response: { "payment_id": "...", "payment_url": "..." }
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

$userId    = (int) $_SESSION['USERDATA']['id'];
$userEmail = $_SESSION['USERDATA']['email'] ?? null;

$body   = json_decode(file_get_contents('php://input'), true);
$amount = (int)($body['amount'] ?? 0);
$gameId = (int)($body['game_id'] ?? 0);

// Clamp: min 10₽, max 50 000₽
$amount = max(10, min(50000, $amount));

if (empty($userEmail)) {
    http_response_code(422);
    echo json_encode(['error' => 'Email обязателен для оформления чека']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

/* Create pending record */
$stmt = $pdo->prepare("
    INSERT INTO infra_payments (user_id, game_id, amount, status, created_at)
    VALUES (?, ?, ?, 'pending', NOW())
");
$stmt->execute([$userId, $gameId ?: null, $amount]);
$infraId = $pdo->lastInsertId();

$amountFormatted = number_format($amount, 2, '.', '');

try {
    $client = new Client();
    $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

    $payment = $client->createPayment(
        [
            'amount' => [
                'value'    => $amountFormatted,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => 'https://dustore.ru/finv2/success_infra.php?id=' . $infraId,
            ],
            'capture'     => true,
            'description' => 'Инфраструктурный налог — поддержка Dustore',
            'metadata'    => [
                'infra_id' => $infraId,
                'user_id'  => $userId,
                'game_id'  => $gameId,
                'type'     => 'infra_payment',
            ],
            'receipt' => [
                'customer' => ['email' => $userEmail],
                'items' => [[
                    'description'     => 'Добровольный взнос: поддержка платформы Dustore',
                    'quantity'        => 1,
                    'amount'          => ['value' => $amountFormatted, 'currency' => 'RUB'],
                    'vat_code'        => 1,
                    'payment_subject' => 'service',
                    'payment_mode'    => 'full_payment',
                ]],
            ],
        ],
        uniqid('infra_', true)
    );
} catch (Exception $e) {
    error_log('create_infra_payment error: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'Ошибка платёжной системы']);
    exit;
}

$paymentId = $payment->getId();

$pdo->prepare("UPDATE infra_payments SET payment_id = ? WHERE id = ?")
    ->execute([$paymentId, $infraId]);

echo json_encode([
    'payment_id'  => $paymentId,
    'payment_url' => $payment->getConfirmation()->getConfirmationUrl(),
]);
