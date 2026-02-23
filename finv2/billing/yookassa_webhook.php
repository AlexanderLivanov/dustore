<?php
// file_put_contents(__DIR__ . '/webhook_log.txt', file_get_contents('php://input') . PHP_EOL, FILE_APPEND);
session_start();
require '../../vendor/autoload.php';
require_once('../../swad/config.php');
require_once('../../swad/controllers/send_email.php');

$source = file_get_contents('php://input');
$data = json_decode($source, true);

// print_r($_SESSION['USERDATA']);


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

if(!empty($_SESSION['USERDATA']['email'])){
    sendMail($_SESSION['USERDATA']['email'], "Вы успешно приобрели подписку", '
                <!DOCTYPE html>
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <title>Благодарим за покупку</title>
                </head>
                <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                <td align="center" style="padding:40px 15px;">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
                <tr>
                <td style="padding:30px;text-align:center;">

                <h1 style="color:#ffffff;margin:0 0 10px;font-size:26px;">
                Спасибо за вклад в развитие <span style="color:#c32178;">Dustore</span>
                </h1>

                <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                Платформа для разработчиков и игроков
                </p>

                <a href="https://dustore.ru/finv2"
                style="display:inline-block;padding:14px 28px;
                background:#c32178;color:#ffffff;
                text-decoration:none;border-radius:12px;
                font-weight:bold;font-size:16px;">
                Управление подпиской
                </a>

                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Если кнопка не работает, скопируйте ссылку:
                <br>
                <a href="https://dustore.ru/finv2" style="color:#c32178;word-break:break-all;">
                https://dustore.ru/finv2
                </a>
                </p>

                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Если вы не знаете, что такое Dustore или не покупали подписку, то проигнорируйте данное письмо. Отвечать на это письмо не нужно: оно всё равно до
                нас не дойдёт.
                </p>

                </td>
                </tr>

                <tr>
                <td style="background:#0f0f15;padding:20px;text-align:center;">
                <p style="color:#6f6f85;font-size:12px;margin:0;">
                © 2024-' . date('Y') . ' Dustore · Все права защищены · <a href="https://t.me/dustore_official">Наш Telegram</a>
                </p>
                </td>
                </tr>

                </table>

                </td>
                </tr>
                </table>
                </body>
                </html>
                ');
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
