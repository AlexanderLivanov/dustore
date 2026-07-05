<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// localhost-эндпоинт: заставляем Database выбрать PRODUCTION-креды, а не LOCAL
$_SERVER['HTTP_HOST'] = 'dustore.ru';

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_bridge.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('{"ok":false}'); }

$secret = $_GET['secret'] ?? '';
$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $in = json_decode(file_get_contents('php://input'), true) ?: []; $secret = $in['secret'] ?? $secret; }
if (!hash_equals(bridge_secret(), (string)$secret)) { http_response_code(403); exit('{"ok":false}'); }

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
    else $db->prepare("UPDATE push_outbox SET attempts=attempts+1, status=IF(attempts+1>=3,'failed','pending') WHERE id=?")->execute([$id]);
    exit('{"ok":true}');
}

// выдать pending с подписками
$jobs = $db->query("SELECT id, user_id, title, body, url FROM push_outbox
                     WHERE status='pending' ORDER BY id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
$subStmt = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id=?");
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
echo json_encode(['ok' => true, 'jobs' => $out], JSON_UNESCAPED_UNICODE);