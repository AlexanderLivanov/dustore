<?php
/**
 * find_chat.php
 * Возвращает реальный chat_id диалога бота с пользователем по его user_id.
 * Клиент вызывает один раз при настройке.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

const MAX_TOKEN = 'f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8';
const MAX_API   = 'https://platform-api.max.ru';

$user_id = trim($_GET['user_id'] ?? '');
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

function maxGet($endpoint) {
    $ch = curl_init(MAX_API . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . MAX_TOKEN],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($body, true)];
}

// Перебираем чаты бота, ищем диалог с нужным user_id
$marker = null;
$found  = null;

for ($page = 0; $page < 20; $page++) {
    $qs = $marker ? "?count=50&marker=$marker" : "?count=50";
    $r  = maxGet("/chats$qs");

    if ($r['status'] !== 200) break;

    $chats  = $r['data']['chats']  ?? [];
    $marker = $r['data']['marker'] ?? null;

    foreach ($chats as $chat) {
        // Только личные диалоги
        if (($chat['type'] ?? '') !== 'dialog') continue;

        $participants = $chat['participants'] ?? [];
        foreach ($participants as $p) {
            if ((string)($p['user_id'] ?? '') === (string)$user_id) {
                $found = [
                    'chat_id'   => $chat['chat_id'],
                    'user_id'   => $user_id,
                    'chat_title'=> $chat['title'] ?? null,
                ];
                break 2;
            }
        }
    }

    if (!$marker) break;
}

if ($found) {
    echo json_encode($found);
} else {
    // Fallback: попробуем напрямую /messages?user_id= — если у MAX есть такой роут для GET
    http_response_code(404);
    echo json_encode([
        'error'   => 'chat not found',
        'hint'    => 'Убедитесь, что пользователь писал боту хотя бы раз',
        'user_id' => $user_id,
    ]);
}
