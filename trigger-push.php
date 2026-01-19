<?php
$data = json_encode([
    "title" => "Событие",
    "body"  => "Через PHP",
    "url"   => "/"
]);

$ch = curl_init("http://127.0.0.1:3001/send-push");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_RETURNTRANSFER => true
]);

curl_exec($ch);
curl_close($ch);
