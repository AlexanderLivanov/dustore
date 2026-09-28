<?php
declare(strict_types=1);

/**
 * swad/controllers/_avatar_schema.php — самоустанавливающаяся схема истории аватарок.
 * ---------------------------------------------------------------------------
 * Тот же приём, что в swad/fx/schema.php (FxSchema) и assetstore/_schema.php
 * (AssetSchema): CREATE TABLE IF NOT EXISTS + версия в счётчике, ensure()
 * дешёво кэшируется в сессии. Только добавляем — ничего не дропаем.
 *
 * ВАЖНО (наступили на грабли ровно с этим на проде — см. hotfix в assetstore):
 * "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" — синтаксис MariaDB, на обычном
 * MySQL это syntax error. Любой будущий upgrade()-шаг обязан идти через
 * information_schema (hasColumn/addColumn ниже), как уже сделано в
 * swad/fx/schema.php.
 */

const AVATAR_SCHEMA_VERSION = 1;

final class AvatarSchema
{
    /** @return string[] */
    public static function ddl(): array
    {
        $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            /* История аватарок — одна строка на каждую загрузку. "Текущая" —
               это users.profile_picture (её не трогаем, не дублируем схему);
               строка тут не удаляется при смене текущей, только когда её
               явно удаляют — так можно листать назад, как в Telegram. */
            "CREATE TABLE IF NOT EXISTS user_avatars (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                url VARCHAR(500) NOT NULL,
                s3_key VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_user (user_id, id)
            ) $t",
        ];
    }

    /** Шаги обновления с версии $from — на будущее, пока пусто. */
    private static function upgrade(PDO $pdo, int $from): void
    {
        // if ($from < 2) self::addColumn($pdo, 'user_avatars', 'source', "VARCHAR(16) NULL");
    }

    public static function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    public static function addColumn(PDO $pdo, string $table, string $col, string $ddl): void
    {
        if (!self::hasColumn($pdo, $table, $col)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
        }
    }

    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        if (session_status() === PHP_SESSION_ACTIVE
            && ($_SESSION['avatar_schema'] ?? 0) === AVATAR_SCHEMA_VERSION) {
            $done = true;
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS avatar_schema_meta (
                k VARCHAR(32) NOT NULL,
                v VARCHAR(16) NOT NULL,
                PRIMARY KEY (k)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $have = 0;
        try {
            $q = $pdo->query("SELECT v FROM avatar_schema_meta WHERE k = 'schema'");
            $have = (int)($q ? $q->fetchColumn() : 0);
        } catch (Throwable $e) {
            $have = 0;
        }

        if ($have < AVATAR_SCHEMA_VERSION) {
            foreach (self::ddl() as $sql) $pdo->exec($sql);
            self::upgrade($pdo, $have);
            $pdo->prepare(
                "INSERT INTO avatar_schema_meta (k, v) VALUES ('schema', ?)
                 ON DUPLICATE KEY UPDATE v = VALUES(v)"
            )->execute([(string)AVATAR_SCHEMA_VERSION]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['avatar_schema'] = AVATAR_SCHEMA_VERSION;
        $done = true;
    }
}
