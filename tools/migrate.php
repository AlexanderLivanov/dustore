<?php
/**
 * Накатчик миграций: php tools/migrate.php [--baseline] [--status]
 *
 * Берёт migrations/*.sql по порядку имён и применяет те, которых ещё нет в
 * таблице schema_migrations. Подключение — переменные окружения (по умолчанию
 * локальный XAMPP):  DB_HOST DB_PORT DB_NAME DB_USER DB_PASS
 *
 *   --status    показать, что применено и что ждёт
 *   --baseline  пометить ВСЕ текущие файлы применёнными, ничего не выполняя —
 *               для базы (прод), где эти изменения уже сделаны вручную.
 *
 * Правила: миграция — только аддитивная (CREATE TABLE IF NOT EXISTS,
 * ADD COLUMN), применённый файл не редактируем — новое изменение = новый файл.
 * Только CLI: из браузера не запускается.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$env = fn($k, $d) => getenv($k) !== false ? getenv($k) : $d;
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $env('DB_HOST', '127.0.0.1'), $env('DB_PORT', '3306'), $env('DB_NAME', 'dustore'));
try {
    $pdo = new PDO($dsn, $env('DB_USER', 'root'), $env('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { fwrite(STDERR, "Нет подключения: {$e->getMessage()}\n"); exit(1); }

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    name VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$done  = $pdo->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files);
$todo  = array_filter($files, fn($f) => !in_array(basename($f), $done, true));

if (in_array('--status', $argv, true)) {
    foreach ($files as $f) echo (in_array(basename($f), $done, true) ? '[x] ' : '[ ] ') . basename($f) . "\n";
    exit(0);
}

$baseline = in_array('--baseline', $argv, true);
foreach ($todo as $f) {
    $name = basename($f);
    try {
        if (!$baseline) $pdo->exec(file_get_contents($f));
        $pdo->prepare("INSERT INTO schema_migrations (name) VALUES (?)")->execute([$name]);
        echo ($baseline ? 'baseline ' : 'applied  ') . $name . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "ОШИБКА в $name: {$e->getMessage()}\nОстановлено, дальше не применял.\n");
        exit(1);
    }
}
echo $todo ? "Готово.\n" : "Всё актуально.\n";
