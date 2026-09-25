<?php
declare(strict_types=1);

/**
 * swad/controllers/loopback.php — запрос сайта к самому себе («fire-and-forget»).
 *
 * Фоновые скрипты (expert/notify_expert.php, devs/notify_worker.php,
 * chat/push_outbox.php) пускают только с REMOTE_ADDR 127.0.0.1. Раньше к ним
 * стучались на 127.0.0.1:80 с «Host: localhost» — но на петле по умолчанию
 * отвечает ДРУГОЙ сайт сервера (default vhost), и запросы молча уходили в 404.
 *
 * Теперь адрес — боевое имя (https://dustore.ru), а соединение прибито к петле:
 * Apache выбирает vhost dustore.ru по SNI и Host, PHP видит REMOTE_ADDR 127.0.0.1.
 * Сертификат не проверяем: соединение физически не покидает машину, а
 * origin-сертификат (Cloudflare и т.п.) проверку по имени и не прошёл бы.
 *
 * Переопределение: env LOOPBACK_URL (например http://localhost для XAMPP).
 */

if (!function_exists('loopback_fire')) {

function loopback_base(): array {
    $u = parse_url(getenv('LOOPBACK_URL') ?: 'https://dustore.ru');
    $tls = ($u['scheme'] ?? 'https') === 'https';
    return [
        'tls'  => $tls,
        'host' => $u['host'] ?? 'dustore.ru',
        'port' => (int)($u['port'] ?? ($tls ? 443 : 80)),
    ];
}

/** Отправить POST и не ждать ответа. true — запрос ушёл в сокет. */
function loopback_fire(string $path, string $body, string $contentType = 'application/x-www-form-urlencoded'): bool {
    $b = loopback_base();
    $ctx = stream_context_create(['ssl' => [
        'peer_name'         => $b['host'],     // SNI → нужный vhost
        'verify_peer'       => false,
        'verify_peer_name'  => false,
    ]]);
    $sock = @stream_socket_client(
        ($b['tls'] ? 'ssl' : 'tcp') . "://127.0.0.1:{$b['port']}",
        $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$sock) {
        error_log("[loopback] {$path}: {$errstr} ({$errno})");
        return false;
    }
    // Пишем блокирующе: запрос маленький, а неблокирующий fwrite поверх TLS
    // мог не дописать его до fclose. Ответ не читаем — скрипт на той стороне
    // живёт с ignore_user_abort(true).
    fwrite($sock, "POST {$path} HTTP/1.1\r\n"
        . "Host: {$b['host']}\r\n"
        . "Content-Type: {$contentType}\r\n"
        . 'Content-Length: ' . strlen($body) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $body);
    fclose($sock);
    return true;
}

}
