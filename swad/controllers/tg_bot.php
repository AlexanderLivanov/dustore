<?php

function send_private_message($uid, $message)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => $uid,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return "CURL ERROR: " . $err;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($responseData && $responseData['ok']) {
        return "Сообщение успешно отправлено!";
    }

    return "Ошибка отправки: " . ($responseData['description'] ?? 'неизвестная ошибка');
}


function send_group_message($group_id, $message, $keyboard_flag, $link)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => $group_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    if ($keyboard_flag && $link !== "") {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Открыть страницу', 'url' => $link]
                ]
            ]
        ];

        $data['reply_markup'] = json_encode($keyboard);
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,

        CURLOPT_PROXY => "213.165.37.31:1080",
        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return "CURL ERROR: " . $err;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!$result) {
        return "INVALID RESPONSE: " . $response;
    }

    return "Ошибка: " . ($result['description'] ?? 'неизвестная ошибка');
}
