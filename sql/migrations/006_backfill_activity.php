<?php
declare(strict_types=1);

/**
 * sql/migrations/006_backfill_activity.php — восстановление истории активности.
 *
 * Запуск (разово, после 006_activity_l4t_profile.sql):
 *     php sql/migrations/006_backfill_activity.php
 *
 * ИДЕЯ. Хартбит начнёт писать user_daily_activity только после деплоя, и
 * retention D30 стал бы честным лишь через два месяца. Но пользователь,
 * оставивший отзыв 12 марта, 12 марта точно был на сайте. Каждое действие
 * с датой — след активности. Собираем их из всех таблиц в «дни присутствия».
 *
 * Это НИЖНЯЯ ОЦЕНКА: тихие визиты без действий следов не оставили, поэтому
 * исторический retention занижен. Строки помечаются source='backfill',
 * и GPI это учитывает (см. swad/controllers/gpi.php).
 *
 * Скрипт идемпотентен (INSERT IGNORE): повторный запуск ничего не сломает.
 * Источники проверяются по information_schema — если таблицы/колонки нет,
 * источник пропускается, а не роняет весь бэкфилл.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'dustore.ru';
require_once __DIR__ . '/../../swad/config.php';

$pdo = (new Database())->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* [таблица, колонка пользователя, колонка даты] */
$sources = [
    ['users',               'id',        'added'],
    ['users',               'id',        'last_activity'],
    ['game_reviews',        'user_id',   'created_at'],
    ['asset_reviews',       'user_id',   'created_at'],
    ['library',             'player_id', 'date'],
    ['wishlists',           'user_id',   'created_at'],
    ['friends',             'player_id', 'created_at'],
    ['game_orders',         'user_id',   'created_at'],
    ['asset_payments',      'user_id',   'created_at'],
    ['analytics_events',    'user_id',   'created_at'],
    ['sprint_participants', 'user_id',   'joined_at'],
    ['sprint_teams',        'captain_id','created_at'],
    ['game_change_log',     'user_id',   'created_at'],
    ['jam_plays',           'user_id',   'first_download_at'],
];

$cols = [];
foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE()") as $r) {
    $cols[$r['t']][$r['c']] = true;
}

if (empty($cols['user_daily_activity'])) {
    fwrite(STDERR, "Нет таблицы user_daily_activity — сначала накатите 006_activity_l4t_profile.sql\n");
    exit(1);
}

$total = 0;
foreach ($sources as [$t, $u, $d]) {
    if (empty($cols[$t][$u]) || empty($cols[$t][$d])) {
        echo str_pad("$t.$d", 36) . "пропуск (нет колонки)\n";
        continue;
    }
    $sql = "INSERT IGNORE INTO user_daily_activity
                (user_id, day, hits, sessions, first_seen, last_seen, source)
            SELECT `$u`, DATE(`$d`), 1, 1, MIN(`$d`), MAX(`$d`), 'backfill'
              FROM `$t`
             WHERE `$u` IS NOT NULL AND `$u` > 0 AND `$d` IS NOT NULL
               AND `$d` > '2000-01-01' AND `$d` <= NOW()
             GROUP BY `$u`, DATE(`$d`)";
    $n = $pdo->exec($sql);
    $total += (int)$n;
    echo str_pad("$t.$d", 36) . "+$n\n";
}

echo "Готово: добавлено $total дней присутствия.\n";
