<?php
declare(strict_types=1);
/** chat/_vapid.php — публичный VAPID-ключ для подписки на Web Push (чат, мобилка). */

if (!function_exists('vapid_public_key')) {
/**
 * Публичный VAPID-ключ. Он ПУБЛИЧНЫЙ по определению — браузер получает его
 * при подписке, — но хардкодить его в разметке всё равно не стоит: при
 * ротации ключей пришлось бы править файл.
 *
 * Ищем по очереди: переменная окружения -> /etc/dustore/push.env (тот же
 * файл, что читает systemd-юнит воркера) -> константа из swad/config.php.
 * Приватный ключ здесь не нужен и не читается.
 */
function vapid_public_key(): string {
    $v = getenv('VAPID_PUBLIC');
    if ($v) return trim($v);

    $path = getenv('PUSH_ENV_FILE') ?: '/etc/dustore/push.env';
    if (is_readable($path)) {
        // parse_ini_file споткнётся о строки без кавычек со спецсимволами,
        // поэтому разбираем сами — формат KEY=value
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') continue;
            [$k, $val] = array_pad(explode('=', $line, 2), 2, '');
            if (in_array(trim($k), ['VAPID_PUBLIC', 'VAPID_PUBLIC_KEY'], true)) {
                return trim($val, " \t\"'");
            }
        }
    }

    if (defined('VAPID_PUBLIC_KEY')) return (string)VAPID_PUBLIC_KEY;
    return '';
}
}
