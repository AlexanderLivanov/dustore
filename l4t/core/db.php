<?php

if (!file_exists(__DIR__ . '/../.env')) {
    http_response_code(500);
    exit('ENV file not found');
}

$env = parse_ini_file(__DIR__ . '/../.env');
// print_r($env);

$dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}";

try {
    $pdo = new PDO(
        $dsn,
        $env['DB_USER'],
        $env['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('DB connection failed');
}
