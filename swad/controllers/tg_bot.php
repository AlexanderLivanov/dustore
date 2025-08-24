<?php
function send_private_message($uid, $message){
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => $uid,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    if ($responseData && $responseData['ok']) {
        $result = "Сообщение успешно отправлено!";
    } else {
        $result = "Ошибка отправки: " . ($responseData['description'] ?? 'неизвестная ошибка');
    }

    return $result;
}

function send_group_message($group_id, $message){
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Открыть панель управления', 'url' => 'https://dustore.ru/devs/recentorgs']
            ]
        ]
    ];

    $data = [
        'chat_id' => $group_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($keyboard),
        'disable_web_page_preview' => True
    ];

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($result['ok']) {
        $res = "Уведомление отправлено в группу!";
    } else {
        $res = "Ошибка: " . $result['description'];
    }

    return $res;
}
?>