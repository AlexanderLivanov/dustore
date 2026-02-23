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
    'indie_pro' => 299,
    'indie_disk' => 99
];

if (!isset($plans[$plan])) {
    die('Invalid plan');
}

$amount = $plans[$plan];

$client = new Client();
$client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

$subscriptionId = uniqid('sub_');

$stmt = $pdo->prepare("
    INSERT INTO subscriptions (user_id, plan_code, amount)
    VALUES (?, ?, ?)
");
$stmt->execute([
    $_SESSION['USERDATA']['id'],
    $plan,
    $amount
]);

$subscriptionDbId = $pdo->lastInsertId();

$payment = $client->createPayment(
    [
        'amount' => [
            'value' => number_format($amount, 2, '.', ''),
            'currency' => 'RUB',
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://dustore.ru/finv2/success.php?sub=' . $subscriptionDbId,
        ],
        'capture' => true,
        'description' => 'Подписка: ' . $plan,
        'metadata' => [
            'subscription_id' => $subscriptionDbId
        ]
    ],
    uniqid('', true)
);

$pdo->prepare("
    UPDATE subscriptions SET payment_id=? WHERE id=?
")->execute([
    $payment->getId(),
    $subscriptionDbId
]);

header('Location: ' . $payment->getConfirmation()->getConfirmationUrl());
exit;
