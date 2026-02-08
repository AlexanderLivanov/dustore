<?php
require __DIR__ . '/vendor/autoload.php';
use YooKassa\Client;

$client = new Client();
$client->setAuth('1269518', 'test_8yQULEAvhaHVZVnKAjjvIwUSjeBs2zK4PWJCyEqoGdA');
$payment = $client->createPayment(
    array(
        'amount' => array(
            'value' => 100.0,
            'currency' => 'RUB',
        ),
        'confirmation' => array(
            'type' => 'redirect',
            'return_url' => 'https://www.example.com/return_url',
        ),
        'capture' => true,
        'description' => 'Заказ №1',
    ),
    uniqid('', true)
);
