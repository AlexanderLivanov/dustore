<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Креды БД Database выбирает по Host запроса, как для любой страницы. Воркер ходит
// по имени сайта (https://dustore.ru, соединение через 127.0.0.1), поэтому Host
// здесь настоящий: на проде — dustore.ru, на локальной копии — localhost.
// Раньше Host подменялся, а для локалки был ?site= из PUSH_SITE, — и забытый в
// шелле PUSH_SITE=127.0.0.1 молча переключал боевой воркер на LOCAL-креды
// (root без пароля → «Access denied for user 'root'»).

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_bridge.php';
require_once __DIR__ . '/_vapid.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('{"ok":false}'); }

$secret = $_GET['secret'] ?? '';
$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $in = json_decode(file_get_contents('php://input'), true) ?: []; $secret = $in['secret'] ?? $secret; }
if (bridge_secret() === '') { http_response_code(503); exit('{"ok":false,"error":"no_config"}'); }   // пустой секрет = открытая дверь
if (!hash_equals(bridge_secret(), (string)$secret)) { http_response_code(403); exit('{"ok":false}'); }
// пульс воркера — по нему /chat/push_check.php понимает, что Node-процесс жив
@touch(sys_get_temp_dir() . '/dustore_push_worker.beat');

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// удалить протухшие подписки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($in['expired']) && is_array($in['expired'])) {
    $ids = array_map('intval', $in['expired']);
    $in2 = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM push_subscriptions WHERE id IN ($in2)")->execute($ids);
    exit('{"ok":true}');
}
// ack задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($in['ack'])) {
    $id = (int)$in['ack'];
    $status = ($in['status'] ?? '') === 'sent' ? 'sent' : 'failed';
    if ($status === 'sent') $db->prepare("UPDATE push_outbox SET status='sent' WHERE id=?")->execute([$id]);
    else $db->prepare("UPDATE push_outbox SET attempts=attempts+1, status=IF(attempts>=3,'failed','pending') WHERE id=?")->execute([$id]);
    exit('{"ok":true}');
}

// Протухшее не шлём. Если воркер лежал (а он лежал неделями), очередь копит
// сотни задач, и на подъёме человеку прилетела бы пачка пушей про давно
// прочитанные сообщения. Они и так есть в ленте «Уведомления».
const PUSH_STALE_MIN = 60;
$db->exec("UPDATE push_outbox SET status='failed'
            WHERE status='pending' AND created_at < NOW() - INTERVAL " . PUSH_STALE_MIN . " MINUTE");

// выдать pending с подписками
$jobs = $db->query("SELECT id, user_id, title, body, url FROM push_outbox
                     WHERE status='pending' ORDER BY id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
// По одной строке на устройство: без UNIQUE по endpoint каждый заход в чат
// добавлял копию, и телефон получал один пуш N раз
$subStmt = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions
                          WHERE id IN (SELECT MAX(id) FROM push_subscriptions WHERE user_id=? GROUP BY endpoint)");
foreach ($jobs as $j) {
    $subStmt->execute([(int)$j['user_id']]);
    $subs = array_map(fn($s) => [
        'id' => (int)$s['id'],
        'endpoint' => $s['endpoint'],
        'keys' => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']],
    ], $subStmt->fetchAll(PDO::FETCH_ASSOC));
    $out[] = [
        'id' => (int)$j['id'],
        'payload' => ['title' => $j['title'], 'body' => $j['body'], 'url' => $j['url']],
        'subscriptions' => $subs,
    ];
}
// vapid — ключ, который сайт раздаёт браузерам. Воркер сверяет его со своим:
// если они разные, каждая отправка кончается 403, а снаружи это выглядит как
// «пуши просто не приходят».
echo json_encode(['ok' => true, 'vapid' => vapid_public_key(), 'jobs' => $out], JSON_UNESCAPED_UNICODE);