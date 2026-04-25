<?php
require '../../vendor/autoload.php';
require_once('../../swad/config.php');

$db = new Database();
$pdo = $db->connect();

use YooKassa\Client;

session_start();

if (empty($_SESSION['USERDATA'])) {
    header('Location: /login?backUrl=/finv2');
    exit;
}

$plan = $_GET['plan'] ?? null;

$plans = [
    'indie_pro' => 13.37,
    'indie_disk' => 99
];

if (!isset($plans[$plan])) {
    die('Invalid plan');
}

$amount = $plans[$plan];

$client = new Client();
$client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

// Insert subscription with status 'pending'
$stmt = $pdo->prepare("
    INSERT INTO subscriptions (user_id, plan_code, amount, status)
    VALUES (?, ?, ?, 'pending')
");
$stmt->execute([
    $_SESSION['USERDATA']['id'],
    $plan,
    $amount
]);

$subscriptionDbId = $pdo->lastInsertId();

// Get user email – required for receipt
$userEmail = $_SESSION['USERDATA']['email'] ?? null;
if (!$userEmail) {
    die('User email is required for receipt. Please update your profile.');
}

// Create payment with receipt
$payment = $client->createPayment(
    [
        'amount' => [
            'value' => number_format($amount, 2, '.', ''),
            'currency' => 'RUB',
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://dustore.ru/finv2/success.php?sub=' . $subscriptionDbId . '&payment={payment_id}',
        ],
        'capture' => true,
        'description' => 'Подписка: ' . $plan,
        'metadata' => [
            'subscription_id' => $subscriptionDbId,
            'plan_code' => $plan
        ],
        'receipt' => [
            'customer' => [
                'email' => $userEmail,
            ],
            'items' => [
                [
                    'description' => 'Подписка: ' . $plan,
                    'quantity' => 1,
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''),
                        'currency' => 'RUB',
                    ],
                    'vat_code' => 1,   // ⚠️ REPLACE with your actual VAT code (1=0%, 5=20%)
                    'payment_subject' => 'service',
                    'payment_mode' => 'full_payment',
                ]
            ]
        ]
    ],
    uniqid('', true)
);

$paymentId = $payment->getId();

// Update subscription with payment_id
$pdo->prepare("UPDATE subscriptions SET payment_id = ? WHERE id = ?")
    ->execute([$paymentId, $subscriptionDbId]);

// Build the correct return URL with the real payment_id
$returnUrl = 'https://dustore.ru/finv2/success.php?sub=' . $subscriptionDbId . '&payment=' . $paymentId;

// Redirect to YooKassa payment page
header('Location: ' . $payment->getConfirmation()->getConfirmationUrl());
exit;
