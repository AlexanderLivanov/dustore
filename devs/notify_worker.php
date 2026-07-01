<?php

/**
 * devs/notify_worker.php
 * Вызывается асинхронно из regorg.php — отправляет email и push.
 * Никогда не вызывается напрямую браузером.
 */

// Только внутренние запросы
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    exit('Forbidden');
}

ignore_user_abort(true);
set_time_limit(60);

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/send_email.php');

$studio_name  = $_POST['studio_name']  ?? '';
$studio_id    = (int)($_POST['studio_id']    ?? 0);
$owner_name   = $_POST['owner_name']   ?? '';
$owner_id     = (int)($_POST['owner_id']     ?? 0);
$owner_email  = $_POST['owner_email']  ?? '';
$tiker        = $_POST['tiker']        ?? '';
$spec         = $_POST['specialization'] ?? '';

if (!$studio_id || !$studio_name) exit('bad params');

// ── Email шаблон ──────────────────────────────────────────────────────────
function buildEmail(string $title, string $bodyHtml, string $btnText = '', string $btnUrl = ''): string
{
    $btn = $btnText ? "<a href=\"{$btnUrl}\" style=\"display:inline-block;padding:14px 28px;background:#c32178;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold;font-size:16px;margin-top:8px;\">{$btnText}</a>" : '';
    $year = date('Y');
    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru"><head><meta charset="UTF-8"><title>{$title}</title></head>
    <body style="margin:0;padding:0;background:#0e0e12;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 15px;">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#14141b;border-radius:16px;overflow:hidden;">
        <tr><td style="padding:36px 30px;text-align:center;">
            <h1 style="color:#fff;margin:0 0 10px;font-size:22px;">Dustore.<span style="color:#c32178;">Devs</span></h1>
            <h2 style="color:#fff;margin:0 0 16px;font-size:18px;">{$title}</h2>
            {$bodyHtml}
            {$btn}
            <p style="color:#9a9ab0;font-size:12px;margin:28px 0 0;">Это письмо отправлено автоматически · Не отвечайте на него</p>
        </td></tr>
        <tr><td style="background:#0f0f15;padding:16px;text-align:center;">
            <p style="color:#6f6f85;font-size:12px;margin:0;">© 2024–{$year} Dustore · <a href="https://t.me/dustore_official" style="color:#c32178;">Telegram</a></p>
        </td></tr>
        </table>
    </td></tr>
    </table>
    </body></html>
    HTML;
}

$sn = htmlspecialchars($studio_name);
$on = htmlspecialchars($owner_name);
$oe = htmlspecialchars($owner_email ?: 'не указан');

// ── Письмо владельцу ──────────────────────────────────────────────────────
if (filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
    $body = "
    <div style='background:#1a0a24;border-radius:12px;padding:18px;margin-bottom:16px;text-align:left;'>
        <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Студия:</span> {$sn}</p>
        <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Тикер:</span> " . htmlspecialchars($tiker) . "</p>
        <p style='color:#b8b8c6;margin:0;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Статус:</span> На модерации</p>
    </div>
    <p style='color:#b8b8c6;font-size:14px;line-height:1.6;'>Мы рассмотрим вашу студию в течение нескольких дней.<br>Пока идёт модерация — вы уже можете заполнить профиль.</p>
    ";
    sendMail(
        $owner_email,
        "Студия «{$sn}» создана и ожидает модерации",
        buildEmail("Студия создана!", $body, 'Перейти в консоль', 'https://dustore.ru/devs/')
    );
}

// ── Письмо администратору ─────────────────────────────────────────────────
$admin_body = "
<div style='background:#1a0a24;border-radius:12px;padding:18px;margin-bottom:16px;text-align:left;'>
    <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Название:</span> {$sn}</p>
    <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Владелец:</span> {$on} (ID: {$owner_id})</p>
    <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Email:</span> {$oe}</p>
    <p style='color:#b8b8c6;margin:0 0 8px;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>Специализация:</span> " . htmlspecialchars($spec) . "</p>
    <p style='color:#b8b8c6;margin:0;font-size:14px;'><span style='color:#c32178;font-weight:bold;'>ID студии:</span> {$studio_id}</p>
</div>
";
sendMail(
    'dusty@dustore.ru',
    "Новая студия на модерации: «{$sn}»",
    buildEmail("Новая студия на модерации", $admin_body, 'Открыть в панели', 'https://dustore.ru/devs/recentorgs')
);

// ── Push всем админам через api/push/index.js (эндпоинт /send) ───────────
$payload = json_encode([
    'title' => "Новая студия: {$sn}",
    'body'  => "Владелец: {$on} · Ожидает модерации",
    'url'   => '/devs/recentorgs',
]);
$ctx = stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
    'content' => $payload,
    'timeout' => 5,
]]);
@file_get_contents('http://localhost:3001/send', false, $ctx);

http_response_code(200);
echo 'ok';
