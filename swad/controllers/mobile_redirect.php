<?php

/**
 * swad/controllers/mobile_redirect.php
 *
 * Подключение:
 *   require_once __DIR__ . '/swad/controllers/mobile_redirect.php';
 *   mobile_redirect_if_needed();  // вызвать до любого вывода
 *
 * Алгоритм:
 *   1. Уже на /m/* → пропустить
 *   2. Cookie prefer_desktop → пропустить (пользователь сам выбрал)
 *   3. Cookie pwa_standalone → пропустить (открыто как PWA-приложение)
 *   4. User-Agent или Sec-CH-UA-Mobile → редирект 302 на /m/*
 */

function is_mobile_ua(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    /* Sec-CH-UA-Mobile — Client Hints, современный стандарт */
    if (
        isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])
        && $_SERVER['HTTP_SEC_CH_UA_MOBILE'] === '?1'
    ) {
        return true;
    }

    /* Классический UA-matching */
    return (bool) preg_match(
        '/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i',
        $ua
    );
}

function map_to_mobile_route(string $path): string
{
    /* /g/123           → /m/game/123 */
    if (preg_match('#^/g/(\d+)#', $path, $m)) {
        return "/m/game/{$m[1]}";
    }

    /* /profile/...  или /player/... → /m/profile */
    if (preg_match('#^/(profile|player)#', $path)) {
        return '/m/profile';
    }

    /* /library       → /m/library */
    if (str_starts_with($path, '/library')) {
        return '/m/library';
    }

    /* /catalog или /games → /m/catalog */
    if (preg_match('#^/(catalog|games)#', $path)) {
        return '/m/catalog';
    }

    /* Всё остальное → /m/ */
    return '/m/';
}

function mobile_redirect_if_needed(): void
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';

    /* 1. Уже на мобильной версии */
    if (str_starts_with($path, '/m/') || $path === '/m') {
        return;
    }

    /* 2. Не трогаем: API, ассеты, devs, admin */
    $skip_prefixes = ['/swad/', '/devs/', '/admin/', '/api/', '/altstore/'];
    foreach ($skip_prefixes as $prefix) {
        if (str_starts_with($path, $prefix)) return;
    }

    /* 3. Явный выбор десктопа */
    if (!empty($_COOKIE['prefer_desktop'])) {
        return;
    }

    /* 4. PWA standalone mode */
    if (!empty($_COOKIE['pwa_standalone'])) {
        return;
    }

    /* 5. Проверяем UA */
    if (!is_mobile_ua()) {
        return;
    }

    /* 6. Редирект */
    $mobile_path = map_to_mobile_route($path);

    /* Передаём GET-параметры если они были */
    $qs = parse_url($uri, PHP_URL_QUERY);
    if ($qs) {
        $mobile_path .= '?' . $qs;
    }

    header('Location: ' . $mobile_path, true, 302);
    exit;
}
