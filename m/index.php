<?php
/**
 * m/index.php — роутер мобильного PWA
 *
 * Маршруты:
 *   /m/              → home
 *   /m/catalog       → catalog
 *   /m/game/123      → game
 *   /m/library       → library
 *   /m/profile       → profile
 *   /m/search        → search
 *   /m/developer/42  → developer (по ID студии)
 *   /m/dev/tiker     → developer (по тикеру)
 */

if (session_status() === PHP_SESSION_NONE) session_start();
// error_log('SESSION DUMP: ' . json_encode(array_keys($_SESSION ?? [])));
require_once __DIR__ . '/../swad/config.php';

$raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = trim($raw, '/');
$parts = explode('/', $uri);
array_shift($parts); // убираем 'm'

$page  = $parts[0] ?? 'home';
$param = $parts[1] ?? null;

// Алиас /m/dev/X → developer
if ($page === 'dev') {
    $page = 'developer';
}

$allowed = ['home','catalog','game','library','profile','search','developer','sprints','/login'];if (!in_array($page, $allowed, true)) {
    header('Location: /m/', true, 302);
    exit;
}

$db   = (new Database())->connect();
$user = $_SESSION['USERDATA'] ?? null;

$view = __DIR__ . "/views/{$page}.php";
if (!file_exists($view)) {
    $view = __DIR__ . '/views/home.php';
    $page = 'home';
}

require __DIR__ . '/layout/shell.php';