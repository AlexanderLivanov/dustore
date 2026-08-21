<?php
// ============================================================
// INDEX.PHP — единая точка входа API (front controller)
// ============================================================
// Все запросы к /api/* попадают сюда (через .htaccess rewrite).
// Здесь мы разбираем метод + путь и вызываем нужный маршрут.
// ============================================================

declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

setup_cors();

// ── Разбор пути ──────────────────────────────────────────
// Пример: /api/articles/5/rate  → ['articles','5','rate']
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = rawurldecode($uri);

// Отрезаем префикс /api
$path = preg_replace('#^.*?/api/?#', '', $uri);
$parts = array_values(array_filter(explode('/', trim($path, '/')), fn($p) => $p !== ''));

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = $parts[0] ?? '';

// ── Маршрутизация по ресурсам ────────────────────────────
try {
    switch ($resource) {
        case 'auth':
            require __DIR__ . '/routes/auth.php';
            handle_auth($method, $parts);
            break;

        case 'articles':
            require __DIR__ . '/routes/articles.php';
            handle_articles($method, $parts);
            break;

        case 'sections':
            require __DIR__ . '/routes/sections.php';
            handle_sections($method, $parts);
            break;

        case '':
            json_response([
                'name'    => 'K.O.N.T.U.R. API',
                'version' => '1.0',
                'status'  => 'online',
            ]);
            break;

        default:
            json_error('Ресурс не найден: ' . $resource, 404);
    }
} catch (Throwable $e) {
    $config = app_config();
    json_error(
        'Внутренняя ошибка сервера',
        500,
        ($config['debug'] ?? false) ? $e->getMessage() : null
    );
}
