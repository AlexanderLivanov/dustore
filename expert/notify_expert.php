<?php

/**
 * expert/notify_expert.php — фоновая отправка письма о новой заявке.
 * Вызывается асинхронно из submit-expert.php.
 */

// Только внутренние запросы
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit;
}

ignore_user_abort(true);
set_time_limit(60);

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/send_email.php';

$username = $_POST['username'] ?? 'Пользователь';
$email    = $_POST['email']    ?? '';
$user_id  = (int)($_POST['user_id'] ?? 0);
$year     = date('Y');

$html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0e0e12;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 15px;">
<table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
<tr><td style="padding:30px;text-align:center;">
  <h1 style="color:#fff;margin:0 0 10px;font-size:24px;">
    Новая заявка в Dustore.<span style="color:#c32178;">Expert</span>
  </h1>
  <p style="color:#b8b8c6;font-size:15px;margin:0 0 8px;">
    От пользователя: <strong style="color:#fff;">$username</strong> (ID: $user_id)
  </p>
  <p style="color:#b8b8c6;font-size:14px;margin:0 0 25px;">Email: $email</p>
  <a href="https://dustore.ru/devs/experts?tab=applications"
     style="display:inline-block;padding:14px 28px;background:#c32178;color:#fff;
            text-decoration:none;border-radius:12px;font-weight:bold;font-size:16px;">
    Рассмотреть заявку
  </a>
  <p style="color:#9a9ab0;font-size:12px;margin:24px 0 0;">
    Это автоматическое уведомление · Не отвечайте на него
  </p>
</td></tr>
<tr><td style="background:#0f0f15;padding:16px;text-align:center;">
  <p style="color:#6f6f85;font-size:12px;margin:0;">
    &copy; 2024&ndash;$year Dustore &middot; 
    <a href="https://t.me/dustore_official" style="color:#c32178;">Telegram</a>
  </p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

sendMail("expert@dustore.ru", "Новая заявка в Эксперты от @{$username}", $html, "");

http_response_code(200);
echo "ok";
