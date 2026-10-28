<?php
/**
 * finv2/mon_hook.php — уведомления агрегаторов об оплатах через ключи студии.
 * URL: /finv2/mon_hook.php?agg=yookassa|tbank&s=<studio_id>&k=<подпись mon_hook_sig>
 *
 * Телу уведомления не верим: берём из него только id платежа и спрашиваем статус у агрегатора
 * ключами студии (mon_sync). У Т-Кассы дополнительно сверяем подпись Token паролем терминала.
 * Ответ: ЮKassa ждёт HTTP 200, Т-Касса — текст OK (иначе будет повторять уведомление).
 */
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/mon/pay.php';

$agg    = (string)($_GET['agg'] ?? '');
$studio = (int)($_GET['s'] ?? 0);
$ok     = fn() => exit($agg === 'tbank' ? 'OK' : '');

if (!$studio || !hash_equals(mon_hook_sig($studio), (string)($_GET['k'] ?? ''))) { http_response_code(403); exit(); }
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) { http_response_code(400); exit(); }

$db = (new Database())->connect();
mon_schema($db);

$extId = $agg === 'yookassa' ? (string)($body['object']['id'] ?? '') : (string)($body['PaymentId'] ?? '');
$st = $db->prepare("SELECT * FROM mon_payments WHERE agg = ? AND external_id = ? AND studio_id = ?");
$st->execute([$agg, $extId, $studio]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) $ok();   // не наш платёж — подтверждаем, чтобы агрегатор не повторял

if ($agg === 'tbank') {
    try {
        [, $creds] = mon_creds(mon_profile($db, $studio), $row['mode']);
        if (!hash_equals(mon_tb_token($body, $creds['password']), (string)($body['Token'] ?? ''))) { http_response_code(403); exit(); }
    } catch (Throwable $e) {
        error_log('mon_hook tbank: ' . $e->getMessage());
        http_response_code(500);
        exit();
    }
}

mon_sync($db, $row);
$ok();
