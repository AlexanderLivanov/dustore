<?php
/* ВРЕМЕННЫЙ ДИАГНОСТ ЧАСОВЫХ ПОЯСОВ. Удалить после проверки.
   Открыть: /swad/controllers/_check_time.php
   Доступ: только global_role = -1 (root). */

session_start();
require_once('../config.php');
if (!defined('ONLINE_WINDOW_MIN')) define('ONLINE_WINDOW_MIN', 15);

$role = $_SESSION['USERDATA']['global_role'] ?? null;
if ((int)$role !== -1) {
    http_response_code(403);
    exit('403');
}

header('Content-Type: text/plain; charset=utf-8');
$pdo = (new Database())->connect();

function h(string $t): void
{
    echo "\n" . str_repeat('─', 64) . "\n$t\n" . str_repeat('─', 64) . "\n";
}
function safe(callable $f)
{
    try {
        return $f();
    } catch (Throwable $e) {
        return 'ОШИБКА: ' . $e->getMessage();
    }
}

h('1. Часы PHP и MySQL — должны совпасть');
$php_now = date('Y-m-d H:i:s');
$my = safe(fn() => $pdo->query("
    SELECT NOW() now_, UTC_TIMESTAMP() utc_,
           @@session.time_zone sess_tz, @@global.time_zone glob_tz
")->fetch(PDO::FETCH_ASSOC));

if (!defined('APP_TZ')) {
    echo "❌ config.php на этом хосте СТАРЫЙ — константы APP_TZ нет.\n";
    echo "   Залей swad/config.php, иначе часовые пояса не выровнены.\n\n";
} else {
    echo "APP_TZ (config)   : " . APP_TZ . " (" . app_tz_offset() . ")\n";
}
echo "PHP date.timezone : " . ini_get('date.timezone') . "  [дефолт из php.ini]\n";
echo "PHP  NOW          : $php_now\n";
if (is_array($my)) {
    echo "MySQL NOW()       : {$my['now_']}\n";
    echo "MySQL UTC_TIMESTAMP: {$my['utc_']}\n";
    echo "MySQL session tz  : {$my['sess_tz']}   global tz: {$my['glob_tz']}\n";
    $drift = (strtotime($my['now_']) - strtotime($php_now)) / 3600;
    printf("\n>>> РАСХОЖДЕНИЕ PHP vs MySQL: %+.2f ч  %s\n",
        $drift, abs($drift) < 0.02 ? '✅ ОК' : '❌ ЧАСЫ РАЗЪЕХАЛИСЬ');
}

h('2. Онлайн при разных окнах (было 185 мин — костыль под рассинхрон)');
foreach ([5, ONLINE_WINDOW_MIN, 60, 185] as $w) {
    $c = safe(fn() => $pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL $w MINUTE")->fetchColumn());
    printf("  %-4s мин : %s\n", $w, $c);
}
echo "\nЕсли 5 и 185 дают резко разные числа — в last_activity ещё лежат\n";
echo "записи, сделанные по старым (сдвинутым) часам. Само рассосётся,\n";
echo "как только пользователи снова походят по сайту.\n";

h('3. Свежесть last_activity: насколько записи «в будущем»/«в прошлом»');
print_r(safe(fn() => $pdo->query("
    SELECT TIMESTAMPDIFF(MINUTE, last_activity, NOW()) AS minutes_ago, COUNT(*) AS users
    FROM users
    WHERE last_activity >= NOW() - INTERVAL 1 DAY
       OR last_activity >  NOW()
    GROUP BY minutes_ago
    ORDER BY minutes_ago
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC)));
echo "Отрицательные minutes_ago = записи из будущего — верный признак\n";
echo "того, что писали часами, которые впереди читающих.\n";

h('4. История онлайна — ищем шов на стыке старых и новых часов');
print_r(safe(fn() => $pdo->query("
    SELECT ts, online_count FROM users_online_history ORDER BY ts DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC)));
echo "Пик за всё время: " . safe(fn() => $pdo->query("SELECT MAX(online_count) FROM users_online_history")->fetchColumn()) . "\n";

h('5. Если в истории виден шов — разовая правка старых строк');
echo <<<SQL
Строки, записанные до фикса, лежат по старым часам. Сдвинуть их можно так
(СНАЧАЛА бэкап, ПОТОМ подставь свой сдвиг и момент выката):

  -- проверить, что сдвиг не создаст дублей по первичному ключу ts:
  SELECT COUNT(*) FROM users_online_history a
  JOIN users_online_history b
    ON b.ts = a.ts + INTERVAL 3 HOUR
  WHERE a.ts < '2026-08-15 00:00:00';
  -- должно быть 0

  UPDATE users_online_history
     SET ts = ts + INTERVAL 3 HOUR
   WHERE ts < '2026-08-15 00:00:00';

Если строк мало и история не критична — проще ничего не трогать
и дать графику накопиться заново.

SQL;

echo "\n>>> Удали этот файл после проверки.\n";
