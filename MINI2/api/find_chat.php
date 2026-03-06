<?php

$log = __DIR__ . "/webhook_log.txt";
$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(["error" => "user_id required"]);
    exit;
}

if (!file_exists($log)) {
    echo json_encode(["error" => "log not found"]);
    exit;
}

$lines = file($log);

foreach (array_reverse($lines) as $line) {

    $json = json_decode(substr($line, strpos($line, '{')), true);

    if (!$json) continue;

    $sender = $json['message']['sender']['user_id'] ?? null;
    $chatId = $json['message']['recipient']['chat_id'] ?? null;

    if ($sender == $userId) {
        echo json_encode([
            "user_id" => $sender,
            "chat_id" => $chatId
        ]);
        exit;
    }
}

echo json_encode(["error" => "not found"]);
