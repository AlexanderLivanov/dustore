<?php
session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/send_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: expert-apply");
    exit;
}

// Только авторизованные пользователи с email
if (!isset($_SESSION['USERDATA'])) {
    header("Location: /login");
    exit;
}

$user = $_SESSION['USERDATA'];

if (empty($user['email'])) {
    die("Для подачи заявки необходим email в профиле.");
}

$userId     = (int)$user['id'];
$experience = trim($_POST['experience'] ?? '');
$motivation = trim($_POST['motivation'] ?? '');

if (!$experience || !$motivation) {
    die("Все поля обязательны.");
}

$db  = new Database();
$pdo = $db->connect();

// Проверяем, не подавал ли уже заявку
$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    header("Location: expert-apply");
    exit;
}

// Вставляем новую заявку
$stmt = $pdo->prepare("
    INSERT INTO experts (user_id, status, experience, motivation)
    VALUES (?, 'new', ?, ?)
");
$stmt->execute([$userId, $experience, $motivation]);

sendMail("expert@dustore.ru", "Получена новая заявка в Эксперты", '
                <!DOCTYPE html>
                <html lang="ru">
                <head>
                <meta charset="UTF-8">
                <title>Новая заявка в Эксперты</title>
                </head>
                <body style="margin:0;padding:0;background-color:#0e0e12;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                <td align="center" style="padding:40px 15px;">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
                <tr>
                <td style="padding:30px;text-align:center;">

                <h1 style="color:#ffffff;margin:0 0 10px;font-size:26px;">
                Получена новая заявка в Dustore.<span style="color:#c32178;">Expert</span>
                </h1>

                <p style="color:#b8b8c6;font-size:15px;margin:0 0 25px;">
                Платформа для модераторов
                </p>

                <a href="https://dustore.ru/expert/admin"
                style="display:inline-block;padding:14px 28px;
                background:#c32178;color:#ffffff;
                text-decoration:none;border-radius:12px;
                font-weight:bold;font-size:16px;">
                Перейти на Платформу
                </a>

                <p style="color:#9a9ab0;font-size:13px;margin:30px 0 0;">
                Это письмо предназначено определённому лицу или ограниченному кругу лиц. Пожалуйста, не пересылайте его.
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
                ', "");

header("Location: thanks");
exit;
