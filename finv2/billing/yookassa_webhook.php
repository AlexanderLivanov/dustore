<?php
// file_put_contents(__DIR__ . '/webhook_log.txt', file_get_contents('php://input') . PHP_EOL, FILE_APPEND);
require '../../vendor/autoload.php';
require_once('../../swad/config.php');

$source = file_get_contents('php://input');
$data = json_decode($source, true);

if (($data['event'] ?? '') !== 'payment.succeeded') {
    http_response_code(200);
    exit;
}

$payment = $data['object'];
$subscriptionId = $payment['metadata']['subscription_id'] ?? null;

if (!$subscriptionId) {
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id=?");
$stmt->execute([$subscriptionId]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription || $subscription['status'] === 'active') {
    exit;
}

$expires = date('Y-m-d H:i:s', strtotime('+1 month'));

$pdo->prepare("
    UPDATE subscriptions
    SET status='active',
        started_at=NOW(),
        expires_at=?,
        updated_at=NOW()
    WHERE id=?
")->execute([$expires, $subscriptionId]);

http_response_code(200);
