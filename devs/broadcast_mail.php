<?php
declare(strict_types=1);
/**
 * devs/broadcast_mail.php — фоновая отправка писем рассылки из devs/notifications.php.
 * Зовётся только сайтом у самого себя (loopback_fire): 127.0.0.1 + секрет моста.
 * Прогресс пишет в broadcasts.email_sent / email_fail, страница его показывает.
 *
 * Правила, чтобы тысячи писем не вешали сервер и не жгли почтовый ящик:
 *   • одно SMTP-соединение на всю рассылку, таймаут 15 с (mailer_batch);
 *   • пауза между письмами — хостинг ограничивает частоту отправки;
 *   • каждые 10 писем проверяем кнопку «Остановить» (broadcasts.email_stop);
 *   • 10 ошибок подряд — это уже не отдельные плохие адреса, а отказ сервера
 *     (лимит, блокировка ящика, неверный пароль): останавливаемся сами и
 *     пишем текст ошибки в broadcasts.email_error, чтобы его было видно на странице.
 */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }

ignore_user_abort(true);
set_time_limit(0);

const BC_MAIL_DELAY_MS   = 1000;   // пауза между письмами; лимиты своего SMTP уточни у хостинга
const BC_MAIL_MAX_FAILS  = 10;     // подряд
const BC_MAIL_CHECK_EACH = 10;     // как часто смотреть на кнопку «Остановить»

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../chat/_bridge.php';
require_once __DIR__ . '/../swad/controllers/send_email.php';

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (bridge_secret() === '' || !hash_equals(bridge_secret(), (string)($in['secret'] ?? ''))) { http_response_code(403); exit; }

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND email_done = 0 AND email_stop = 0");
$st->execute([(int)($in['id'] ?? 0)]);
$b = $st->fetch(PDO::FETCH_ASSOC);
if (!$b) exit;
$bid = (int)$b['id'];

$finish = function (?string $error = null) use ($db, $bid): void {
    $db->prepare("UPDATE broadcasts SET email_done = 1, email_error = COALESCE(?, email_error) WHERE id = ?")
       ->execute([$error !== null ? mb_substr($error, 0, 250) : null, $bid]);
};

$ids = array_map('intval', json_decode($b['recipients'], true) ?: []);
if (!$ids) { $finish(); exit; }

$ph = implode(',', array_fill(0, count($ids), '?'));
$q = $db->prepare("SELECT id, email FROM users WHERE id IN ($ph) AND email LIKE '%@%'");
$q->execute($ids);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$url  = (string)($b['url'] ?? '');
$link = $url === '' ? '' : ($url[0] === '/' ? 'https://dustore.ru' . $url : $url);
$html = buildEmail(
    htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'),
    '<p style="color:#cfcfe0;font-size:15px;line-height:1.6;margin:0 0 16px;">' . nl2br(htmlspecialchars($b['body'], ENT_QUOTES, 'UTF-8')) . '</p>',
    $link !== '' ? 'Открыть' : '',
    htmlspecialchars($link, ENT_QUOTES, 'UTF-8')
);

$inc   = $db->prepare("UPDATE broadcasts SET email_sent = email_sent + ?, email_fail = email_fail + ?, email_error = COALESCE(?, email_error) WHERE id = ?");
$stopQ = $db->prepare("SELECT email_stop FROM broadcasts WHERE id = ?");

try {
    $mail = mailer_batch();
} catch (Throwable $e) {
    $finish('Почта не настроена: ' . $e->getMessage());
    exit;
}

$streak = 0;
foreach ($rows as $i => $u) {
    if ($i % BC_MAIL_CHECK_EACH === 0) {
        $stopQ->execute([$bid]);
        if ((int)$stopQ->fetchColumn() === 1) break;          // нажали «Остановить»
    }
    $err = mailer_send($mail, (string)$u['email'], (string)$b['title'], $html);
    if ($err !== null) error_log("[broadcast_mail #{$bid}] {$u['email']}: {$err}");
    $inc->execute([$err === null ? 1 : 0, $err === null ? 0 : 1, $err, $bid]);

    $streak = $err === null ? 0 : $streak + 1;
    if ($streak >= BC_MAIL_MAX_FAILS) {
        $mail->smtpClose();
        $finish("Остановлено: {$streak} ошибок подряд. Последняя: {$err}");
        exit;
    }
    usleep(BC_MAIL_DELAY_MS * 1000);
}
$mail->smtpClose();
$finish();
