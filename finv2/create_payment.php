<?php

$TERMINAL_KEY = '';
$PASSWORD = '';

$data = json_decode(file_get_contents('php://input'), true);

// $productId = (int)$data['product_id'];
$productId = 1;


$prices = [
    1 => 19900, 
    2 => 49900
];

if (!isset($prices[$productId])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

$amount = $prices[$productId];
$orderId = uniqid('order_');

$payload = [
    'TerminalKey' => $TERMINAL_KEY,
    'Amount' => $amount,
    'OrderId' => $orderId,
    'Description' => 'Оплата заказа #' . $orderId,
    'SuccessURL' => 'http://127.0.0.1/finv2/success',
    'FailURL' => 'http://127.0.0.1/finv2/fail',
];

// Подпись
$payload['Token'] = generateToken($payload, $PASSWORD);

$response = sendRequest(
    'https://securepay.tinkoff.ru/v2/Init',
    $payload
);

// file_put_contents('debug.txt', json_encode($payload, JSON_PRETTY_PRINT));


header('Content-Type: application/json');
echo $response;


/**
 * ===== helpers =====
 */

function generateToken(array $params, string $password): string
{
    $params['Password'] = $password;
    ksort($params);

    return hash('sha256', implode('', $params));
}

function sendRequest(string $url, array $data): string
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
