<?php
// ============================================================
// DB.PHP — единая точка подключения к БД через PDO
// ============================================================
// Почему PDO, а не mysqli?
//  - Единый API для разных СУБД
//  - Prepared statements как первый класс (защита от SQL-инъекций)
//  - Исключения вместо проверки return-кодов
// ============================================================

function db(): PDO
{
    // Статическая переменная = одно подключение на весь запрос
    // (паттерн Singleton в упрощённом виде)
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = app_config();
    $c = $config['db'];

    // Подключение через unix-сокет (если задан) или через host:port
    if (!empty($c['socket'])) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
            $c['socket'], $c['name'], $c['charset']);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['name'], $c['charset']);
    }

    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            // Бросать исключения при ошибках SQL
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Возвращать ассоциативные массивы по умолчанию
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // КРИТИЧНО: отключаем эмуляцию prepared statements,
            // чтобы использовались настоящие серверные prepared statements
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Ошибка подключения к базе данных',
            'detail' => ($config['debug'] ?? false) ? $e->getMessage() : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $pdo;
}
