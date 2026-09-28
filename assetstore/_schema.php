<?php
/**
 * assetstore/_schema.php — самоустанавливающаяся схема ассетстора.
 * ---------------------------------------------------------------------------
 * Тот же приём, что в swad/fx/schema.php (FxSchema): CREATE TABLE IF NOT
 * EXISTS + номер версии в счётчике, чтобы DDL не гонялся на каждый запрос.
 * Только добавляем — ничего не дропаем и не переименовываем.
 *
 * Смысл: новые таблицы под фичи ассетстора (версии, бандлы, лицензии...)
 * появляются на живом сервере САМИ при первом обращении — никому не нужно
 * руками накатывать .sql через phpMyAdmin. Подключается один раз, из
 * assetstore/_acl.php::acl_ctx(), через который проходят все страницы.
 */

declare(strict_types=1);

const ASSET_SCHEMA_VERSION = 1;

final class AssetSchema
{
    /** @return string[] */
    public static function ddl(): array
    {
        $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [

            /* История версий ассета — одна строка на каждый реальный бамп
               version. Дубли того же номера подряд отсекает asset_record_version(). */
            "CREATE TABLE IF NOT EXISTS asset_versions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_id INT UNSIGNED NOT NULL,
                version VARCHAR(20) NOT NULL,
                changelog TEXT NULL,
                actor_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_asset (asset_id, id)
            ) $t",
        ];
    }

    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        if (session_status() === PHP_SESSION_ACTIVE
            && ($_SESSION['asset_schema'] ?? 0) === ASSET_SCHEMA_VERSION) {
            $done = true;
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS asset_schema_meta (
                k VARCHAR(32) NOT NULL,
                v VARCHAR(16) NOT NULL,
                PRIMARY KEY (k)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $have = 0;
        try {
            $q = $pdo->query("SELECT v FROM asset_schema_meta WHERE k = 'schema'");
            $have = (int)($q ? $q->fetchColumn() : 0);
        } catch (Throwable $e) {
            $have = 0;
        }

        if ($have < ASSET_SCHEMA_VERSION) {
            foreach (self::ddl() as $sql) $pdo->exec($sql);
            $pdo->prepare(
                "INSERT INTO asset_schema_meta (k, v) VALUES ('schema', ?)
                 ON DUPLICATE KEY UPDATE v = VALUES(v)"
            )->execute([(string)ASSET_SCHEMA_VERSION]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['asset_schema'] = ASSET_SCHEMA_VERSION;
        $done = true;
    }
}
