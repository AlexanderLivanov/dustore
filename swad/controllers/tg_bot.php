<?php
// require_once("../config.php");
// ── Прокси-конфигурация ───────────────────────────────────────────────────
// Эстония, SOCKS5. Обновить при смене прокси.
define('TG_PROXY_HOST', '212.102.146.114');
define('TG_PROXY_PORT', 9288);
define('TG_PROXY_USER', 't25kLy');
define('TG_PROXY_PASS', 'UJ0MmV');

/**
 * Общая функция curl для Telegram API через SOCKS5-прокси.
 * Все запросы к api.telegram.org идут через Эстонию.
 */
function tg_curl_exec(string $url, array $data): ?array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,

        // SOCKS5 прокси
        CURLOPT_PROXY          => TG_PROXY_HOST . ':' . TG_PROXY_PORT,
        CURLOPT_PROXYTYPE      => CURLPROXY_SOCKS5_HOSTNAME,
        CURLOPT_PROXYUSERPWD   => TG_PROXY_USER . ':' . TG_PROXY_PASS,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('[TG BOT] CURL ERROR: ' . $err);
        return null;
    }

    curl_close($ch);

    return json_decode($response, true);
}

/**
 * Отправка личного сообщения пользователю по telegram_id.
 */
function send_private_message(int $uid, string $message): string
{

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id'    => $uid,
        'text'       => $message,
        'parse_mode' => 'HTML',
    ];

    $result = tg_curl_exec($url, $data);

    if ($result === null) {
        return "CURL ERROR: не удалось подключиться через прокси";
    }

    if ($result['ok'] ?? false) {
        return "Сообщение успешно отправлено!";
    }

    return "Ошибка отправки: " . ($result['description'] ?? 'неизвестная ошибка');
}

/**
 * Отправка сообщения в группу/канал.
 * $keyboard_flag — показывать ли кнопку "Открыть страницу"
 * $link          — URL для кнопки (если keyboard_flag = true)
 */
function send_group_message(int|string $group_id, string $message, bool $keyboard_flag, string $link): string
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id'                  => $group_id,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($keyboard_flag && $link !== '') {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Открыть страницу', 'url' => $link]
                ]
            ]
        ];
        $data['reply_markup'] = json_encode($keyboard);
    }

    $result = tg_curl_exec($url, $data);

    if ($result === null) {
        return "CURL ERROR: не удалось подключиться через прокси";
    }

    if ($result['ok'] ?? false) {
        return "Сообщение успешно отправлено!";
    }

    return "Ошибка: " . ($result['description'] ?? 'неизвестная ошибка');
}
