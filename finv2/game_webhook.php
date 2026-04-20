<?php

/**
 * game_webhook.php
 * Настройте URL в личном кабинете ЮКасса:
 *   https://dustore.ru/finv2/game_webhook.php
 *
 * Обрабатывает событие payment.succeeded для покупок игр
 * и добавляет игру в библиотеку пользователя.
 */

require_once('../vendor/autoload.php');
require_once('../swad/config.php');
require_once('../swad/controllers/send_email.php');

use YooKassa\Client;

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Always respond 200 to prevent YooKassa retries on logic errors
http_response_code(200);
header('Content-Type: application/json');

/* ── Only handle payment.succeeded ── */
if (($data['event'] ?? '') !== 'payment.succeeded') {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$payment  = $data['object'];
$meta     = $payment['metadata'] ?? [];
$type     = $meta['type']     ?? '';
$orderId  = (int)($meta['order_id'] ?? 0);
$gameId   = (int)($meta['game_id']  ?? 0);
$userId   = (int)($meta['user_id']  ?? 0);

/* ── Route by payment type ── */
if ($type === 'infra_payment') {
    handleInfraPayment($payment, $meta);
} elseif ($type === 'game_purchase') {
    handleGamePurchase($payment, $orderId, $gameId, $userId);
} else {
    // Unknown type — ignore silently
    echo json_encode(['ok' => true, 'skipped' => 'unknown_type']);
}

exit;

/* ═══════════════════════════════════════════
   Handle game purchase
═══════════════════════════════════════════ */
function handleGamePurchase($payment, int $orderId, int $gameId, int $userId): void
{
    global $data;

    if (!$orderId || !$gameId || !$userId) {
        error_log('game_webhook: missing metadata in payment ' . ($payment['id'] ?? '?'));
        echo json_encode(['ok' => false, 'error' => 'missing metadata']);
        return;
    }

    $db  = new Database();
    $pdo = $db->connect();

    /* Idempotency check */
    $stmt = $pdo->prepare("SELECT status FROM game_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("game_webhook: order $orderId not found");
        echo json_encode(['ok' => false, 'error' => 'order not found']);
        return;
    }

    if ($order['status'] === 'succeeded') {
        echo json_encode(['ok' => true, 'idempotent' => true]);
        return;
    }

    /* ── Add game to library ── */
    $pdo->prepare("
        INSERT IGNORE INTO library (player_id, game_id, purchased_at)
        VALUES (?, ?, NOW())
    ")->execute([$userId, $gameId]);

    /* ── Update order status ── */
    $pdo->prepare("
        UPDATE game_orders
        SET status = 'succeeded', completed_at = NOW()
        WHERE id = ?
    ")->execute([$orderId]);

    /* ── Get game name & user email for receipt email ── */
    $gameRow = $pdo->prepare("SELECT name FROM games WHERE id = ?");
    $gameRow->execute([$gameId]);
    $gameName = $gameRow->fetchColumn() ?: 'Игра';

    $userRow = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $userRow->execute([$userId]);
    $user = $userRow->fetch(PDO::FETCH_ASSOC);

    if (!empty($user['email'])) {
        $amount = $payment['amount']['value'] ?? '?';
        sendMail(
            $user['email'],
            'Покупка игры «' . $gameName . '» прошла успешно',
            buildGamePurchaseEmail($user['username'] ?? 'Игрок', $gameName, $amount),
            ''
        );
    }

    echo json_encode(['ok' => true]);
}

/* ═══════════════════════════════════════════
   Handle infrastructure payment
═══════════════════════════════════════════ */
function handleInfraPayment($payment, array $meta): void
{
    $paymentId = $payment['id'];
    $userId    = (int)($meta['user_id'] ?? 0);

    $db  = new Database();
    $pdo = $db->connect();

    /* Idempotency */
    $stmt = $pdo->prepare("SELECT id FROM infra_payments WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => true, 'idempotent' => true]);
        return;
    }

    $pdo->prepare("
        UPDATE infra_payments SET status = 'succeeded', completed_at = NOW()
        WHERE payment_id = ?
    ")->execute([$paymentId]);

    echo json_encode(['ok' => true]);
}

/* ═══════════════════════════════════════════
   Email template
═══════════════════════════════════════════ */
function buildGamePurchaseEmail(string $username, string $gameName, string $amount): string
{
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Покупка игры</title></head>
<body style="margin:0;padding:0;background:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 15px;">
<table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
<tr><td style="padding:30px;text-align:center;">

<h1 style="color:#ffffff;margin:0 0 10px;font-size:24px;">
    Игра добавлена в библиотеку!
</h1>
<p style="color:#b8b8c6;font-size:15px;margin:0 0 20px;">
    Привет, {$username}! Покупка прошла успешно.
</p>

<table width="100%" cellpadding="0" cellspacing="0"
    style="background:rgba(255,255,255,.05);border-radius:12px;padding:20px;margin-bottom:20px;">
<tr>
    <td style="color:#9a9ab0;font-size:14px;padding:6px 0;">Игра</td>
    <td align="right" style="color:#fff;font-size:14px;padding:6px 0;">{$gameName}</td>
</tr>
<tr>
    <td style="color:#9a9ab0;font-size:14px;padding:6px 0;">Сумма</td>
    <td align="right" style="color:#fff;font-size:14px;padding:6px 0;">{$amount} ₽</td>
</tr>
</table>

<a href="https://dustore.ru/library"
    style="display:inline-block;padding:14px 28px;background:#c32178;color:#fff;
    text-decoration:none;border-radius:12px;font-weight:bold;font-size:16px;">
    Открыть библиотеку
</a>

</td></tr>
<tr>
<td style="background:#0f0f15;padding:20px;text-align:center;">
<p style="color:#6f6f85;font-size:12px;margin:0;">
    © 2024–{$year} Dustore · Не отвечайте на это письмо.
</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}
