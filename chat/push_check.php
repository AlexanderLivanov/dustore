<?php
declare(strict_types=1);
/**
 * chat/push_check.php — «почему не приходят пуши?» за 10 секунд.
 * Цепочка из пяти звеньев, рвётся обычно одно — страница показывает какое:
 *   1. VAPID-ключ найден (иначе браузер не может подписаться)
 *   2. у тебя есть подписка (кнопка «Включить уведомления» сработала)
 *   3. Node-воркер жив и забирает очередь
 *   4. очередь push_outbox не копится
 *   5. push-сервис принял (статус sent в таблице) — дальше дело телефона
 * Детали конфигурации видит только админ.
 */
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_push_config.php';
require_once __DIR__ . '/push_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['USERDATA']) && !empty($_COOKIE['auth_token'])) {
    try { require_once __DIR__ . '/../swad/controllers/user.php'; (new User())->checkAuth(); } catch (Throwable $e) { }
}
$uid = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$uid) { header('Location: /m/login?back=/chat/push_check.php'); exit; }
$admin = (int)($_SESSION['USERDATA']['global_role'] ?? 0) === -1;

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sent = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['test'] ?? '') === '1') {
    $sent = push_enqueue_user($db, $uid, 'Проверка уведомлений', 'Если вы это видите — пуши работают 🎉', '/m/profile');
}

$cfg   = push_config();
$beat  = @filemtime(sys_get_temp_dir() . '/dustore_push_worker.beat') ?: 0;
$alive = $beat && time() - $beat < 15;
$subs  = $db->prepare("SELECT endpoint, created_at FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC");
$subs->execute([$uid]); $subs = $subs->fetchAll(PDO::FETCH_ASSOC);
$jobs  = $db->prepare("SELECT id, title, status, attempts, created_at FROM push_outbox WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$jobs->execute([$uid]); $jobs = $jobs->fetchAll(PDO::FETCH_ASSOC);
$pending = (int)$db->query("SELECT COUNT(*) FROM push_outbox WHERE status = 'pending'")->fetchColumn();

function row(bool $ok, string $title, string $text): void {
    echo '<div class="r ' . ($ok ? 'ok' : 'bad') . '"><b>' . ($ok ? '✓' : '✗') . '</b><div><strong>' . $title . '</strong><p>' . $text . '</p></div></div>';
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Проверка пушей — Dustore</title>
<style>
body{margin:0;padding:20px 16px 40px;background:#0d0118;color:#f6ecf9;font:15px/1.5 -apple-system,system-ui,sans-serif}
main{max-width:620px;margin:0 auto}h1{font-size:24px;margin:0 0 16px}
.r{display:flex;gap:12px;padding:14px;margin-bottom:10px;border-radius:14px;background:rgba(255,255,255,.05)}
.r>b{flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center}
.ok>b{background:rgba(74,222,154,.18);color:#4ade9a}.bad>b{background:rgba(255,107,138,.18);color:#ff6b8a}
.r p{margin:2px 0 0;color:rgba(246,236,249,.65);font-size:14px}code{background:rgba(255,255,255,.08);padding:1px 6px;border-radius:6px;font-size:13px}
button{height:46px;padding:0 20px;border:0;border-radius:12px;background:linear-gradient(135deg,#e6379a,#c32178 45%,#74155d);color:#fff;font:700 15px system-ui}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}td{padding:6px 4px;border-top:1px solid rgba(255,255,255,.08)}
.note{padding:12px 14px;border-radius:12px;background:rgba(74,222,154,.1);margin-bottom:12px}
</style></head><body><main>
<h1>Проверка push-уведомлений</h1>
<?php if ($sent !== null): ?><div class="note"><?= $sent ? "Задача #$sent в очереди — пуш должен прийти в течение пары секунд." : 'Не поставлено: у вас нет ни одной подписки (шаг 2).' ?></div><?php endif; ?>
<?php
row($cfg['public'] !== '', '1. VAPID-ключ',
    $cfg['public'] !== '' ? 'Найден' . ($admin ? ' в <code>' . $h($cfg['source']) . '</code>' . ($cfg['private'] ? '' : ' — но без приватного ключа, воркер не сможет подписывать') : '')
                          : 'Не найден. Создайте файл <code>dustore-push.env</code> в папке НАД htdocs (или используйте ключи из <code>api/push/.env</code>): VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT. Ключи: <code>npx web-push generate-vapid-keys</code>');
row((bool)$subs, '2. Подписки этого аккаунта',
    $subs ? count($subs) . ' устр.: ' . $h(implode(', ', array_unique(array_map(fn($s) => parse_url($s['endpoint'], PHP_URL_HOST), $subs))))
          . ' <br>(web.push.apple.com — iPhone, fcm.googleapis.com — Chrome/Android)'
          : 'Нет. Профиль в /m → «Уведомления» (на iPhone — только из приложения на экране «Домой», iOS 16.4+)');
row($alive, '3. Node-воркер',
    $alive ? 'Жив, забирал очередь ' . (time() - $beat) . ' с назад'
           : ($beat ? 'Молчит уже ' . (time() - $beat) . ' с' : 'Ни разу не заходил') . ' — запустите: <code>cd pwa && npm i web-push && node push-worker.js</code>'
           . ($admin ? '<br>Локальная копия: <code>set OUTBOX_URL=http://localhost/chat/push_outbox.php</code> перед запуском' : ''));
row($pending < 20, '4. Очередь', "Ожидают отправки: $pending" . ($pending >= 20 ? ' — копится, воркер не справляется или не запущен' : ''));
?>
<form method="post"><input type="hidden" name="test" value="1"><button>Отправить тестовый пуш себе</button></form>
<?php if ($jobs): ?>
<table><tr><td><b>#</b></td><td><b>Уведомление</b></td><td><b>Статус</b></td><td><b>Когда</b></td></tr>
<?php foreach ($jobs as $j): ?><tr><td><?= (int)$j['id'] ?></td><td><?= $h($j['title']) ?></td>
<td><?= $j['status'] === 'sent' ? '✓ отправлено' : ($j['status'] === 'failed' ? '✗ не принято' : '… ждёт воркер') ?><?= (int)$j['attempts'] ? ' (' . (int)$j['attempts'] . ' попыт.)' : '' ?></td>
<td><?= $h(substr((string)$j['created_at'], 5, 11)) ?></td></tr><?php endforeach; ?>
</table>
<p style="color:rgba(246,236,249,.5);font-size:13px">«✗ не принято» — push-сервис отказал: чаще всего подписка сделана на другой VAPID-ключ. Выключите и включите уведомления заново — подписка пересоздастся.</p>
<?php endif; ?>
</main></body></html>
