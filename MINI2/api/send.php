<?php
/**
 * send.php
 * 1. Форматирует to:from:text → отправляет получателю через MAX API
 * 2. Сохраняет копию в sent_log.json — чтобы отправитель видел свои исходящие
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

const MAX_TOKEN = 'f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8';
const MAX_API   = 'https://platform-api.max.ru';
const SENT_LOG  = __DIR__ . '/sent_log.json';
const LOG_LOCK  = __DIR__ . '/sent_log.lock';

$data = json_decode(file_get_contents('php://input'), true);
$to   = trim($data['to']   ?? '');
$from = trim($data['from'] ?? '');
$text = trim($data['text'] ?? '');

if (!$to || !$from || $text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'to, from, text — обязательны']);
    exit;
}

$MAP_FILE = __DIR__ . '/user_chat_map.json';
$map = file_exists($MAP_FILE)
    ? json_decode(file_get_contents($MAP_FILE), true)
    : [];

if (!isset($map[$to])) {
    http_response_code(400);
    echo json_encode(['error' => 'chat_id неизвестен для user ' . $to]);
    exit;
}

$chat_id = $map[$to];

// ── Отправляем через MAX API ──
$payload = json_encode(['text' => "$to:$from:$text"], JSON_UNESCAPED_UNICODE);

$ch = curl_init(MAX_API . '/messages?chat_id=' . urlencode($chat_id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Authorization: ' . MAX_TOKEN, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 10,
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'cURL: ' . $err]);
    exit;
}

$resp = json_decode($body, true);

// ── Сохраняем в sent_log.json (только при успехе 2xx) ──
if ($status >= 200 && $status < 300) {
    $mid = $resp['message']['body']['mid'] ?? $resp['mid'] ?? ('s_' . uniqid());

    $entry = [
        'id'        => (string)$mid,
        'to'        => $to,
        'from'      => $from,
        'text'      => $text,
        'timestamp' => date('c'),
        'direction' => 'out',
    ];

    $fp = fopen(LOG_LOCK, 'w');
    if (flock($fp, LOCK_EX)) {
        $log = file_exists(SENT_LOG) ? (json_decode(file_get_contents(SENT_LOG), true) ?: []) : [];

        if (!isset($log[$from]))      $log[$from]      = [];
        if (!isset($log[$from][$to])) $log[$from][$to] = [];

        $existing = array_column($log[$from][$to], 'id');
        if (!in_array($entry['id'], $existing)) {
            $log[$from][$to][] = $entry;
            if (count($log[$from][$to]) > 500)
                $log[$from][$to] = array_slice($log[$from][$to], -500);
            file_put_contents(SENT_LOG, json_encode($log, JSON_UNESCAPED_UNICODE));
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

http_response_code($status);
file_put_contents(
    __DIR__ . '/send_log.txt',
    date('c') . " STATUS:$status BODY:$body\n",
    FILE_APPEND
);
echo $body;
