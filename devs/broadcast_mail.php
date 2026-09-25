<?php
declare(strict_types=1);
/**
 * devs/broadcast_mail.php — фоновая отправка писем рассылки из devs/notifications.php.
 * Зовётся только сайтом у самого себя (loopback_fire): 127.0.0.1 + секрет моста.
 * Прогресс пишет в broadcasts.email_sent / email_fail, страница его показывает.
 */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }

ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../chat/_bridge.php';
require_once __DIR__ . '/../swad/controllers/send_email.php';

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (bridge_secret() === '' || !hash_equals(bridge_secret(), (string)($in['secret'] ?? ''))) { http_response_code(403); exit; }

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND email_done = 0");
$st->execute([(int)($in['id'] ?? 0)]);
$b = $st->fetch(PDO::FETCH_ASSOC);
if (!$b) exit;

$ids = array_map('intval', json_decode($b['recipients'], true) ?: []);
if (!$ids) { $db->prepare("UPDATE broadcasts SET email_done = 1 WHERE id = ?")->execute([$b['id']]); exit; }

$ph = implode(",", array_fill(0, count($ids), "?"));
$q = $db->prepare("SELECT id, email FROM users WHERE id IN ($ph) AND email LIKE '%@%'");
$q->execute($ids);

$url  = (string)($b['url'] ?? '');
$link = $url === '' ? '' : ($url[0] === '/' ? 'https://dustore.ru' . $url : $url);
$html = buildEmail(
    htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'),
    '<p style="color:#cfcfe0;font-size:15px;line-height:1.6;margin:0 0 16px;">' . nl2br(htmlspecialchars($b['body'], ENT_QUOTES, 'UTF-8')) . '</p>',
    $link !== '' ? 'Открыть' : '',
    htmlspecialchars($link, ENT_QUOTES, 'UTF-8')
);

$inc = $db->prepare("UPDATE broadcasts SET email_sent = email_sent + ?, email_fail = email_fail + ? WHERE id = ?");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $ok = false;
    try { $ok = sendMail($u['email'], $b['title'], $html); } catch (Throwable $e) { error_log('[broadcast_mail] ' . $e->getMessage()); }
    $inc->execute([$ok ? 1 : 0, $ok ? 0 : 1, $b['id']]);
}
$db->prepare("UPDATE broadcasts SET email_done = 1 WHERE id = ?")->execute([$b['id']]);
