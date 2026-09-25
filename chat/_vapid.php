<?php
declare(strict_types=1);
/** chat/_vapid.php — публичный VAPID-ключ для подписки на Web Push (чат, мобилка). */
require_once __DIR__ . '/_push_config.php';

if (!function_exists('vapid_public_key')) {
    function vapid_public_key(): string { return push_config()['public']; }
}
