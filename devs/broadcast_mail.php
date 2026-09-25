<?php
declare(strict_types=1);
/**
 * devs/broadcast_mail.php — запуск почтовой части рассылки (логика — devs/broadcast_lib.php).
 *
 *   • из сайта: loopback_fire (127.0.0.1 + секрет моста) с {"id": N} — сразу
 *     после создания рассылки и из «пинка» в chat/push_outbox.php;
 *   • из cron, если воркера пушей нет: продолжит всё, чему пора (строка ниже).
 */
// cron (/etc/cron.d/dustore-mail):  */10 * * * *  www-data  php /var/www/html/dustore.ru/devs/broadcast_mail.php
$cli = PHP_SAPI === 'cli';
if ($cli) {
    $_SERVER['HTTP_HOST'] = 'dustore.ru';          // Database выбирает креды по хосту
} elseif (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); exit;
}

ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/send_email.php';
require_once __DIR__ . '/broadcast_lib.php';

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($cli) {
    bc_mail_tick($db, function (int $id) use ($db) { echo "#{$id}: " . bc_mail_run($db, $id) . "\n"; }, false);
    exit;
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (bridge_secret() === '' || !hash_equals(bridge_secret(), (string)($in['secret'] ?? ''))) { http_response_code(403); exit; }
bc_mail_run($db, (int)($in['id'] ?? 0));
