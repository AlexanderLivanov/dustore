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

const ASSET_SCHEMA_VERSION = 4;

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

            /* Уровни лицензии — НАДстройка над assets.price/license, не замена.
               Если у ассета нет строк здесь — он продаётся как раньше, по
               одной цене. Если есть — assets.price синхронизируется на MIN()
               из тарифов (см. asset_save_license_tiers), так что все места,
               которые просто читают assets.price (витрина, manage.php,
               аналитика), продолжают работать без единой правки. */
            "CREATE TABLE IF NOT EXISTS asset_license_tiers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_id INT UNSIGNED NOT NULL,
                tier VARCHAR(16) NOT NULL,
                label VARCHAR(64) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                terms TEXT NULL,
                sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_asset_tier (asset_id, tier),
                KEY ix_asset (asset_id, sort)
            ) $t",

            /* Наборы ассетов. Без собственной модерации — публикует
               владелец студии сам, из своих уже опубликованных ассетов
               (см. assetstore/_bundles.php). Отдельная сущность от assets,
               не строка в ней: у набора нет файла, версий, тегов и т.д. */
            "CREATE TABLE IF NOT EXISTS asset_bundles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                studio_id INT UNSIGNED NOT NULL,
                name VARCHAR(128) NOT NULL,
                description TEXT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                path_to_cover VARCHAR(255) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY ix_studio (studio_id, status)
            ) $t",

            "CREATE TABLE IF NOT EXISTS asset_bundle_items (
                bundle_id INT UNSIGNED NOT NULL,
                asset_id INT UNSIGNED NOT NULL,
                sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (bundle_id, asset_id)
            ) $t",

            /* Отдельно от asset_payments: платёж за набор — один payment_id
               на несколько ассетов сразу, а не на один, так что общая
               (asset_id, tier_id, payment_id) форма asset_payments сюда не
               ложится без искажения смысла существующей таблицы. */
            "CREATE TABLE IF NOT EXISTS asset_bundle_payments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                bundle_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                payment_id VARCHAR(64) NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_bundle_user (bundle_id, user_id),
                KEY ix_payment (payment_id)
            ) $t",
        ];
    }

    /**
     * Есть ли колонка — проверяем через information_schema, а не через
     * "ADD COLUMN IF NOT EXISTS": это синтаксис MariaDB, на обычном MySQL
     * (как выяснилось — на проде) падает с ошибкой синтаксиса. Через
     * information_schema — переносимо на оба движка одинаково.
     */
    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }

    /** Пошаговые ALTER'ы — каждый шаг сперва проверяет через information_schema,
     *  есть ли уже колонка, и только потом делает голый ALTER TABLE ... ADD COLUMN
     *  без IF NOT EXISTS (её нет в стандартном MySQL). */
    private static function upgrade(PDO $pdo, int $from): void
    {
        if ($from < 2) {
            if (!self::columnExists($pdo, 'asset_payments', 'tier_id')) {
                $pdo->exec("ALTER TABLE asset_payments ADD COLUMN tier_id INT UNSIGNED NULL AFTER asset_id");
            }
            if (!self::columnExists($pdo, 'asset_library', 'tier_id')) {
                $pdo->exec("ALTER TABLE asset_library ADD COLUMN tier_id INT UNSIGNED NULL AFTER asset_id");
            }
        }
        if ($from < 4) {
            // studios.display_name читают _acl.php/manage.php/_bundles.php
            // (косметическое имя студии поверх слага name) — на части
            // инсталляций, в т.ч. на проде, колонки не было вовсе.
            if (!self::columnExists($pdo, 'studios', 'display_name')) {
                $pdo->exec("ALTER TABLE studios ADD COLUMN display_name VARCHAR(128) NULL AFTER name");
            }
        }
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
            self::upgrade($pdo, $have);
            $pdo->prepare(
                "INSERT INTO asset_schema_meta (k, v) VALUES ('schema', ?)
                 ON DUPLICATE KEY UPDATE v = VALUES(v)"
            )->execute([(string)ASSET_SCHEMA_VERSION]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['asset_schema'] = ASSET_SCHEMA_VERSION;
        $done = true;
    }
}
