<?php
// webhook.php — принимает события от MAX, сохраняет сообщения

$STORAGE = __DIR__ . '/messages_store.json';
$SECRET  = 'duststore_webhook_secret'; // можно поменять

// MAX шлёт GET для верификации вебхука
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Просто отвечаем 200 — MAX проверяет доступность
    http_response_code(200);
    echo 'OK';
    exit;
}

// Читаем тело запроса
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(200); // MAX ждёт 200 всегда
    exit;
}

// Логируем для отладки (можно убрать потом)
file_put_contents(__DIR__ . '/webhook_log.txt', date('Y-m-d H:i:s') . ' ' . $raw . "\n", FILE_APPEND);

// Обрабатываем только входящие сообщения
$type = $data['update_type'] ?? $data['type'] ?? '';

if ($type === 'message_created' || $type === 'bot_started') {
    $msg     = $data['message'] ?? $data;
    $sender  = $msg['sender']    ?? [];
    $body    = $msg['body']      ?? [];
    $recipient = $msg['recipient'] ?? [];

    $chat_id   = $recipient['chat_id'] ?? $sender['user_id'] ?? null;
    $user_id   = $sender['user_id']    ?? null;
    $user_name = $sender['name']       ?? $sender['username'] ?? ('User ' . $user_id);
    $MAP_FILE = __DIR__ . '/user_chat_map.json';

    if ($user_id && $chat_id) {
        $map = file_exists($MAP_FILE)
            ? json_decode(file_get_contents($MAP_FILE), true)
            : [];

        $map[$user_id] = $chat_id;

        file_put_contents($MAP_FILE, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $text      = $body['text']         ?? $msg['text'] ?? '';
    $mid       = $body['mid']          ?? $msg['mid']  ?? uniqid('msg_');
    $timestamp = $msg['timestamp']     ?? (time() * 1000);

    if ($chat_id && $text !== '') {
        // Загружаем хранилище
        $store = [];
        if (file_exists($STORAGE)) {
            $store = json_decode(file_get_contents($STORAGE), true) ?: [];
        }

        $key = (string)$chat_id;
        if (!isset($store[$key])) $store[$key] = [];

        // Дедупликация по mid
        $existing_mids = array_column($store[$key], 'id');
        if (!in_array($mid, $existing_mids)) {
            $store[$key][] = [
                'id'        => $mid,
                'text'      => $text,
                'sender'    => $user_name,
                'user_id'   => $user_id,
                'timestamp' => date('c', (int)($timestamp / 1000)),
                'sent'      => false,
            ];

            // Храним последние 200 сообщений на чат
            if (count($store[$key]) > 200) {
                $store[$key] = array_slice($store[$key], -200);
            }

            file_put_contents($STORAGE, json_encode($store, JSON_UNESCAPED_UNICODE));
        }
    }
}

http_response_code(200);
echo 'OK';
