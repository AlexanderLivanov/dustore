<?php
session_start();
require_once('../config.php');
require_once('game.php');
require_once('send_email.php');

header('Content-Type: application/json');

if (empty($_SESSION['USERDATA'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

sendMail("sashalivanov2007@gmail.com", "Ваша игра получила отзыв", $mail_body);


$gameId = $_POST['game_id'] ?? 0;
$rating = $_POST['rating'] ?? 0;
$userId = $_SESSION['USERDATA']['id'];
$developer_mail = $_POST['devEmail'] ?? null;

$mail_body = '<!DOCTYPE html>
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <title>Игра получила новый отзыв</title>
                </head>
                <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                <td align="center" style="padding:40px 15px;">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
                <tr>
                <td style="padding:30px;text-align:center;">

                <h1 style="color:#ffffff;margin:0 0 10px;font-size:26px;">
                <span style="color:#c32178;">Dustore</span>
                </h1>

                <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                Платформа для разработчиков и игроков
                </p>

                <p style="color:#b8b8c6;font-size:20px;margin:0 0 30px;">Ваша игра получила новый отзыв!</p>
                <a href="https://dustore.ru/g/' . $gameId . '"
                style="display:inline-block;padding:14px 28px;
                background:#c32178;color:#ffffff;
                text-decoration:none;border-radius:12px;
                font-weight:bold;font-size:16px;">
                Посмотреть на странице игры >>>
                </a>

                <p style="color:#b8b8c6;font-size:15px;margin:0 0 10px;">Вы также можете ответить на отзыв в консоли разработчика</p>
                <a href="https://dustore.ru/devs/replies"
                style="display:inline-block;padding:14px 28px;
                color:#ffffff;
                text-decoration:none;
                font-weight:bold;font-size:16px;">
                https://dustore.ru/devs/replies
                </a>


                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Если вы не регистрировались на Платформе, то проигнорируйте данное письмо. Отвечать на это письмо не нужно: оно всё равно до
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
                ';
// print_r($_SESSION);

if (!is_numeric ($gameId) && ($gameId <= 0 || $rating < 1 || $rating > 10)) {
    echo json_encode(['error' => 'invalid_data']);
    exit;
}

$gameController = new Game();
$gameController->addRating($gameId, $userId, $rating);
sendMail($developer_mail, "Ваша игра получила отзыв", $mail_body);

$newRating = $gameController->getAverageRating($gameId);
echo json_encode(['success' => true, 'avg' => $newRating['avg'], 'count' => $newRating['count']]);
