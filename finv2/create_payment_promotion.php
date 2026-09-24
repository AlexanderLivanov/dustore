<?php

/**
 * finv2/create_payment_promotion.php
 * GET /finv2/create_payment_promotion.php?promo_id=123
 * Создаёт платёж ЮKassa за уже забронированный (pending_payment) слот продвижения
 * и редиректит на страницу оплаты. По образцу finv2/create_infra_payment.php —
 * там тот же кейс «оплата услуги», а не покупка игры (payment_subject: service).
 */

require_once('../vendor/autoload.php');
require_once('../swad/config.php');

use YooKassa\Client;

session_start();

if (empty($_SESSION['USERDATA']) || empty($_SESSION['studio_id'])) {
    header('Location: /login?backUrl=/devs/promotion');
    exit();
}

$studio_id = (int)$_SESSION['studio_id'];
$promoId   = (int)($_GET['promo_id'] ?? 0);
$userEmail = $_SESSION['USERDATA']['email'] ?? null;

if (!$promoId) {
    die('promo_id не передан');
}
if (empty($userEmail)) {
    die('Для оплаты нужен email в профиле — иначе ЮKassa не сможет выставить чек. Заполните профиль и вернитесь.');
}

$db  = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("
    SELECT gp.*, g.name AS game_name
    FROM game_promotions gp
    JOIN games g ON g.id = gp.game_id
    WHERE gp.id = ? AND gp.studio_id = ? AND gp.status = 'pending_payment'
    LIMIT 1
");
$stmt->execute([$promoId, $studio_id]);
$promo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$promo) {
    die('Бронь не найдена, уже оплачена или истекла — вернитесь в /devs/promotion и попробуйте снова.');
}

$amountFormatted = number_format((float)$promo['price'], 2, '.', '');

$client = new Client();
$client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);

try {
    $payment = $client->createPayment(
        [
            'amount' => [
                'value'    => $amountFormatted,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => 'https://dustore.ru/finv2/success_promotion.php?id=' . $promo['id'],
            ],
            'capture'     => true,
            'description' => 'Продвижение игры «' . $promo['game_name'] . '» на ' . date('d.m.Y', strtotime($promo['slot_date'])),
            'metadata'    => [
                'promotion_id' => $promo['id'],
                'game_id'      => $promo['game_id'],
                'studio_id'    => $studio_id,
                'type'         => 'game_promotion',
            ],
            'receipt' => [
                'customer' => ['email' => $userEmail],
                'items' => [[
                    'description'     => 'Услуга продвижения игры на Dustore — 1 сутки',
                    'quantity'        => 1,
                    'amount'          => ['value' => $amountFormatted, 'currency' => 'RUB'],
                    'vat_code'        => 1, // ⚠️ проверьте актуальный код НДС для вашей схемы налогообложения
                    'payment_subject' => 'service',
                    'payment_mode'    => 'full_payment',
                ]],
            ],
        ],
        uniqid('promo_', true)
    );
} catch (Exception $e) {
    error_log('create_payment_promotion error: ' . $e->getMessage());
    die('Ошибка платёжной системы. Попробуйте позже.');
}

$paymentId = $payment->getId();

$pdo->prepare("UPDATE game_promotions SET payment_id = ? WHERE id = ?")
    ->execute([$paymentId, $promo['id']]);

header('Location: ' . $payment->getConfirmation()->getConfirmationUrl());
exit();
