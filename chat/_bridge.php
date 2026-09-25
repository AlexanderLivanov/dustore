<?php
declare(strict_types=1);
require_once __DIR__ . '/_push_config.php';

/**
 * Общий секрет локальных мостов Node <-> PHP.
 *   1) файл BRIDGE_SECRET_FILE (или /etc/dustore/bridge.secret)
 *   2) env BRIDGE_SECRET / BRIDGE_SECRET в push-конфиге
 *   3) если нигде не задан — выводится из приватного VAPID-ключа. Он есть
 *      у обеих сторон и нигде не светится, так что отдельный секрет можно
 *      вообще не заводить (pwa/push-worker.js считает так же).
 */
function bridge_secret(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $path = getenv('BRIDGE_SECRET_FILE') ?: '/etc/dustore/bridge.secret';
    if (is_file($path)) {
        $v = trim((string)@file_get_contents($path));
        if ($v !== '') return $cached = $v;
    }
    if ($v = (string)(getenv('BRIDGE_SECRET') ?: push_config()['bridge'])) return $cached = $v;
    $priv = push_config()['private'];
    return $cached = $priv !== '' ? hash('sha256', 'dustore-bridge:' . $priv) : '';
}
