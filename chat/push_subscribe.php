<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$_SERVER['HTTP_HOST'] = 'dustore.ru';

require_once __DIR__ . '/../swad/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['USERDATA'])) { http_response_code(401); exit('{"ok":false}'); }
$myId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if ($myId <= 0) { http_response_code(401); exit('{"ok":false}'); }

$sub = json_decode(file_get_contents('php://input'), true) ?: [];
$endpoint = (string)($sub['endpoint'] ?? '');
$p256dh   = (string)($sub['keys']['p256dh'] ?? '');
$auth     = (string)($sub['keys']['auth'] ?? '');
if ($endpoint === '' || $p256dh === '' || $auth === '') exit('{"ok":false,"error":"bad_sub"}');

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// upsert по endpoint (одно устройство = один endpoint)
$db->prepare("INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth)
              VALUES(?,?,?,?)
              ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), p256dh=VALUES(p256dh), auth=VALUES(auth)")
   ->execute([$myId, $endpoint, $p256dh, $auth]);

echo '{"ok":true}';