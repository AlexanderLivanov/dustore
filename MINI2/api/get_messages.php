<?php
file_put_contents(
    __DIR__ . '/get_log.txt',
    date('c') . " " . json_encode($_GET) . "\n",
    FILE_APPEND
);
/**
 * get_messages.php
 *
 * Возвращает переписку между my_id и peer_id.
 *
 * Входящие: читаем из chat_id (может быть МОЙ или ПИРА — не важно)
 *   → ищем сообщения вида "my_id:peer_id:текст"  (peer написал мне)
 *
 * Исходящие: из sent_log.json на сервере
 *   → записи [my_id][peer_id][]
 *
 * Параметры:
 *   my_id    — мой MAX user_id
 *   chat_id  — chat_id диалога с ботом (мой ИЛИ собеседника — кто хост)
 *   peer_id  — user_id собеседника
 *   count    — сколько брать из MAX (default 100)
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

const MAX_TOKEN = 'f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8';
const MAX_API   = 'https://platform-api.max.ru';
const SENT_LOG  = __DIR__ . '/sent_log.json';

$my_id   = trim($_GET['my_id']   ?? '');
$chat_id = trim($_GET['chat_id'] ?? '');
$peer_id = trim($_GET['peer_id'] ?? '');
$count   = max(1, min(100, (int)($_GET['count'] ?? 100)));

if (!$my_id || !$chat_id || !$peer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'my_id, chat_id, peer_id — обязательны']);
    exit;
}

// ═══════════════════════════════════════
// 1. Входящие из MAX API
// ═══════════════════════════════════════
$ch = curl_init(MAX_API . '/messages?' . http_build_query([
    'chat_id' => $chat_id,
    'count'   => $count,
]));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: ' . MAX_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$incoming = [];

if ($status === 200) {
    foreach ((json_decode($body, true)['messages'] ?? []) as $m) {
        $text = $m['body']['text'] ?? '';
        $mid  = (string)($m['body']['mid'] ?? $m['mid'] ?? '');
        $ts   = $m['timestamp'] ?? null;
        $tsStr = $ts ? date('c', (int)($ts / 1000)) : date('c');

        if (!$text || !$mid) continue;

        // Протокол: to:from:текст
        // Нас интересует: to=my_id, from=peer_id  (peer пишет мне)
        if (!preg_match('/^(\d+):(\d+):(.+)$/su', $text, $x)) continue;

        if ($x[1] !== $my_id || $x[2] !== $peer_id) continue;

        $incoming[] = [
            'id'        => $mid,
            'text'      => $x[3],
            'timestamp' => $tsStr,
            'direction' => 'in',
        ];
    }
}

// ═══════════════════════════════════════
// 2. Исходящие из sent_log.json
// ═══════════════════════════════════════
$outgoing = [];
if (file_exists(SENT_LOG)) {
    $log = json_decode(file_get_contents(SENT_LOG), true) ?: [];
    foreach ($log[$my_id][$peer_id] ?? [] as $m) {
        $outgoing[] = [
            'id'        => (string)$m['id'],
            'text'      => $m['text'],
            'timestamp' => $m['timestamp'],
            'direction' => 'out',
        ];
    }
}

// ═══════════════════════════════════════
// 3. Мёрж + дедупликация + сортировка
// ═══════════════════════════════════════
$seen = [];
$result = [];
foreach (array_merge($incoming, $outgoing) as $m) {
    if (!isset($seen[$m['id']])) {
        $seen[$m['id']] = true;
        $result[] = $m;
    }
}
usort($result, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

echo json_encode(['messages' => $result, 'count' => count($result)], JSON_UNESCAPED_UNICODE);
