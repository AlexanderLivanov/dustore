<?php
declare(strict_types=1);

/**
 * l4t/core/db.php — подключение к базе desl4t.
 *
 * Было: собственный слой на parse_ini_file('../.env'). Два минуса.
 *   1. Файл с паролями лежал в корне сайта и отдавался по HTTP
 *      (см. комментарий в .htaccess). Теперь он не нужен вовсе.
 *   2. Второй параллельный способ ходить в БД: index.php уже использует
 *      Database::connect('desl4t'), а api-эндпоинты — свой $pdo из .env.
 *      Две точки правды разъезжаются при первой же смене пароля.
 *
 * Стало: та же фабрика, что и во всём проекте. Никаких кредов в l4t.
 *
 * Совместимость: файл по-прежнему определяет глобальный $pdo, поэтому
 * подключающие его скрипты (getall.php и остальные) править не нужно.
 */

require_once __DIR__ . '/../../swad/config.php';

if (!class_exists('Database')) {
    http_response_code(500);
    exit('DB layer not available');
}

try {
    $pdo = (new Database())->connect('desl4t');
    if (!$pdo) throw new RuntimeException('connect returned falsy');

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Эмуляция выключена: с ней LIMIT/OFFSET уезжают в запрос строками.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) {
    error_log('[l4t] DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    exit('DB connection failed');
}