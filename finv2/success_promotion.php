<?php

/**
 * finv2/success_promotion.php
 * Возврат с оплаты ЮKassa за слот продвижения. По образцу finv2/success_infra.php:
 * синхронный статус-чек через API на случай, если вебхук ещё не долетел.
 */

require_once('../vendor/autoload.php');
require_once('../swad/config.php');

use YooKassa\Client;

$db  = new Database();
$pdo = $db->connect();

session_start();

$promoId   = (int)($_GET['id']      ?? 0);
$paymentId = trim($_GET['payment']  ?? '');

$promo = null;
if ($promoId) {
    $stmt = $pdo->prepare("
        SELECT gp.*, g.name AS game_name, g.path_to_cover
        FROM game_promotions gp JOIN games g ON g.id = gp.game_id
        WHERE gp.id = ?
    ");
    $stmt->execute([$promoId]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$promo && $paymentId) {
    $stmt = $pdo->prepare("
        SELECT gp.*, g.name AS game_name, g.path_to_cover
        FROM game_promotions gp JOIN games g ON g.id = gp.game_id
        WHERE gp.payment_id = ?
    ");
    $stmt->execute([$paymentId]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
}

$paymentVerified = false;

if ($promo && !empty($promo['payment_id'])) {
    try {
        $client = new Client();
        $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);
        $paymentInfo = $client->getPaymentInfo($promo['payment_id']);

        if ($paymentInfo->getStatus() === 'succeeded') {
            $paymentVerified = true;

            if ($promo['status'] !== 'active') {
                $pdo->prepare("UPDATE game_promotions SET status = 'active', paid_at = NOW() WHERE id = ?")
                    ->execute([$promo['id']]);
                $promo['status'] = 'active';
            }
        }
    } catch (Exception $e) {
        error_log('success_promotion.php error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата продвижения — Dustore</title>
    <style>
        body {
            margin: 0;
            background: #07070e;
            color: #f2f2f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        .card {
            max-width: 420px;
            text-align: center;
        }

        .icon {
            font-size: 44px;
            margin-bottom: 16px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 10px;
        }

        p {
            color: #9c9cb6;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 24px;
        }

        a.btn {
            display: inline-block;
            padding: 12px 26px;
            border-radius: 999px;
            background: linear-gradient(135deg, #9b6cff, #22d3ee);
            color: #08080f;
            font-weight: 800;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php if ($promo && ($paymentVerified || $promo['status'] === 'active')): ?>
            <div class="icon">✅</div>
            <h1>Слот забронирован</h1>
            <p>
                «<?= htmlspecialchars($promo['game_name']) ?>» выйдет в промо-баннер
                <?= date('d.m.Y', strtotime($promo['slot_date'])) ?> в 12:00 и провисит там ровно сутки.
            </p>
        <?php elseif ($promo): ?>
            <div class="icon">⏳</div>
            <h1>Платёж обрабатывается</h1>
            <p>Ещё не пришло подтверждение от ЮKassa — обычно занимает несколько секунд.
                Обновите страницу через минуту или проверьте статус в панели «Продвижение».</p>
        <?php else: ?>
            <div class="icon">⚠️</div>
            <h1>Бронь не найдена</h1>
            <p>Что-то пошло не так со ссылкой возврата. Проверьте статус в панели разработчика.</p>
        <?php endif; ?>
        <a class="btn" href="/devs/promotion">В панель продвижения →</a>
    </div>
</body>

</html>