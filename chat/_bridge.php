<?php
declare(strict_types=1);

/**
 * Общий секрет локальных мостов Node <-> PHP.
 * Читаем из файла (SAPI-независимо), фолбэк — переменная окружения.
 *   1) файл BRIDGE_SECRET_FILE (или /etc/dustore/bridge.secret)
 *   2) env BRIDGE_SECRET
 */
function bridge_secret(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $path = getenv('BRIDGE_SECRET_FILE') ?: '/etc/dustore/bridge.secret';
    if (is_file($path)) {
        $v = trim((string)@file_get_contents($path));
        if ($v !== '') { $cached = $v; return $cached; }
    }
    $cached = (string)(getenv('BRIDGE_SECRET') ?: '');
    return $cached;
}