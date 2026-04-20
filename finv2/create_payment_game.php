<?php

require_once('../vendor/autoload.php');
require_once('../swad/config.php');

use YooKassa\Client;

header('Content-Type: application/json');

session_start();

/* ── Auth ── */
if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

$userId = (int) $_SESSION['USERDATA']['id'];
$userEmail = $_SESSION['USERDATA']['email'] ?? null;

/* ── Input ── */
$body   = json_decode(file_get_contents('php://input'), true);
// $gameId = (int)($body['game_id'] ?? 0);
$gameId = 1;

if ($gameId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный game_id']);
    exit;
}

/* ── DB ── */
$db  = new Database();
$pdo = $db->connect();

/* ── Get game ── */
$stmt = $pdo->prepare("SELECT id, name, price FROM games WHERE id = ? AND status = 'published'");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    http_response_code(404);
    echo json_encode(['error' => 'Игра не найдена']);
    exit;
}

if ((float)$game['price'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Игра бесплатная']);
    exit;
}

/* ── Check: already owns the game ── */
$stmt = $pdo->prepare("SELECT id FROM library WHERE player_id = ? AND game_id = ?");
$stmt->execute([$userId, $gameId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Игра уже есть в вашей библиотеке']);
    exit;
}

/* ── Email required for receipt ── */
if (empty($userEmail)) {
    http_response_code(422);
    echo json_encode(['error' => 'Email обязателен. Укажите его в профиле.']);
    exit;
}

$amount = number_format((float)$game['price'], 2, '.', '');

/* ── Create pending order ── */
$stmt = $pdo->prepare("
    INSERT INTO game_orders (user_id, game_id, amount, status, created_at)
    VALUES (?, ?, ?, 'pending', NOW())
");
$stmt->execute([$userId, $gameId, $game['price']]);
$orderId = $pdo->lastInsertId();

/* ── YooKassa ── */
$client = new Client();
$client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

$returnUrl = 'https://dustore.ru/finv2/success_game.php?order=' . $orderId;

try {
    $payment = $client->createPayment(
        [
            'amount' => [
                'value'    => $amount,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => $returnUrl,
            ],
            'capture'     => true,
            'description' => 'Покупка игры: ' . $game['name'],
            'metadata'    => [
                'order_id' => $orderId,
                'game_id'  => $gameId,
                'user_id'  => $userId,
                'type'     => 'game_purchase',
            ],
            'receipt' => [
                'customer' => [
                    'email' => $userEmail,
                ],
                'items' => [
                    [
                        'description'    => 'Игра: ' . $game['name'],
                        'quantity'       => 1,
                        'amount'         => [
                            'value'    => $amount,
                            'currency' => 'RUB',
                        ],
                        'vat_code'       => 1,   // 1 = без НДС; уточните у своего бухгалтера
                        'payment_subject' => 'intellectual_activity',
                        'payment_mode'   => 'full_payment',
                    ],
                ],
            ],
        ],
        uniqid('game_', true)
    );
} catch (Exception $e) {
    error_log('YooKassa create_payment_game error: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['error' => 'Ошибка платёжной системы. Попробуйте позже.']);
    exit;
}

$paymentId = $payment->getId();

/* ── Save payment_id to order ── */
$pdo->prepare("UPDATE game_orders SET payment_id = ? WHERE id = ?")
    ->execute([$paymentId, $orderId]);

echo json_encode([
    'payment_id'  => $paymentId,
    'payment_url' => $payment->getConfirmation()->getConfirmationUrl(),
    'order_id'    => $orderId,
]);
