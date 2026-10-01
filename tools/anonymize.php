<?php
/**
 * Обезличивание локальной копии прод-базы: php tools/anonymize.php
 * Подключение — те же DB_* переменные, что у migrate.php. Колонки, которых нет
 * в таблице, пропускаются, так что скрипт переживает изменения схемы.
 * ВНИМАНИЕ: запускать только на ЛОКАЛЬНОЙ базе (защита ниже).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$env = fn($k, $d) => getenv($k) !== false ? getenv($k) : $d;
$host = $env('DB_HOST', '127.0.0.1');
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) { fwrite(STDERR, "Отказ: хост $host не локальный.\n"); exit(1); }
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $env('DB_PORT', '3306'), $env('DB_NAME', 'dustore_local')),
    $env('DB_USER', 'root'), $env('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/* таблица => [колонка => SQL-выражение]. Пароль у всех: "test1234" не нужен —
   хэш заменяется заведомо невалидным, входить надо через сброс/вручную. */
$rules = [
    'users' => [
        'email'               => "CONCAT('user', id, '@example.test')",
        'password'            => "'!'",
        'password_hash'       => "'!'",
        'telegram_username'   => "CONCAT('tg_', id)",
        'verification_token'  => 'NULL',
        'reset_token'         => 'NULL',
        'reset_token_expires' => 'NULL',
        'passphrase'          => 'NULL',
    ],
];
$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
foreach ($rules as $table => $cols) {
    $have = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $have->execute([$db, $table]);
    $have = $have->fetchAll(PDO::FETCH_COLUMN);
    $set = [];
    foreach ($cols as $c => $expr) if (in_array($c, $have, true)) $set[] = "`$c` = $expr";
    if ($set) { $n = $pdo->exec("UPDATE `$table` SET " . implode(', ', $set)); echo "$table: обезличено строк $n\n"; }
}
// сессии и подписки на пуши — не нужны локально
foreach (['sessions', 'push_subscriptions'] as $t) {
    try { $pdo->exec("TRUNCATE `$t`"); echo "$t: очищено\n"; } catch (Throwable $e) {}
}
