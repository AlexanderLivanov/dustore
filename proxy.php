<?php

$url = $_POST['url'] ?? '';

if (!$url) {
    http_response_code(400);
    exit('No URL');
}

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0'
]);

$html = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(500);
    exit(curl_error($ch));
}

curl_close($ch);

echo $html;
