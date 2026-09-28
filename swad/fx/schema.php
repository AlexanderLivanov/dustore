<?php
declare(strict_types=1);

/**
 * swad/fx/schema.php — схема «Fid Core» (стены, посты, обсуждения, DustHunt).
 *
 * БД общая с v3 (Nuxt), поэтому:
 *   • только добавляем: CREATE TABLE IF NOT EXISTS, ничего не дропаем и не переименовываем;
 *   • все таблицы с префиксом fx_ — коллизий с чужими нет;
 *   • внешних ключей нет (v3 может чистить users/games своими путями);
 *   • JSON — в LONGTEXT (на MariaDB и MySQL 5.7 нет одинакового JSON-типа).
 *
 * Установка ленивая: ensure() дёргает один SELECT из fx_meta; если версия ниже —
 * прогоняет DDL. Повторный прогон безопасен. Миграцию руками запускать не нужно.
 */

const FX_SCHEMA_VERSION = 2;

final class FxSchema
{
    /** @return string[] */
    public static function ddl(): array
    {
        $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [

        /* ── посты: ОДНА таблица на все стены ─────────────────────────────
         * wall_type: media | user | game | studio
         * channel:   wall (стена/лента) | forum (обсуждения игры) | devlog (девлог студии)
         * in_media:  1 → попадает в общую ленту /fid («Лента»). Записи на стенах игроков
         *            туда попасть не могут — флаг для них принудительно 0 в FxPosts::create().
         * as_studio_id: пост от имени студии (аватар студии, подпись роли автора).
         */
        "CREATE TABLE IF NOT EXISTS fx_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wall_type VARCHAR(8) NOT NULL DEFAULT 'media',
            wall_id INT UNSIGNED NOT NULL DEFAULT 0,
            channel VARCHAR(8) NOT NULL DEFAULT 'wall',
            author_id INT UNSIGNED NOT NULL,
            as_studio_id INT UNSIGNED NULL,
            role_label VARCHAR(48) NULL,
            kind VARCHAR(8) NOT NULL DEFAULT 'post',
            title VARCHAR(200) NULL,
            body MEDIUMTEXT NULL,
            article MEDIUMTEXT NULL,
            media LONGTEXT NULL,
            poll LONGTEXT NULL,
            tags VARCHAR(255) NULL,
            game_id INT UNSIGNED NULL,
            in_media TINYINT(1) NOT NULL DEFAULT 0,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            n_up INT UNSIGNED NOT NULL DEFAULT 0,
            n_down INT UNSIGNED NOT NULL DEFAULT 0,
            n_cm INT UNSIGNED NOT NULL DEFAULT 0,
            n_views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            edited_at DATETIME NULL,
            last_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_wall (wall_type, wall_id, channel, status, pinned, last_at),
            KEY ix_media (in_media, status, created_at),
            KEY ix_author (author_id, created_at),
            KEY ix_studio (as_studio_id, status, created_at),
            KEY ix_game (game_id, status, created_at)
        ) $t",

        /* реакция: одна на пользователя и пост; kind — имя иконки из наборов RX_UP / RX_DOWN */
        "CREATE TABLE IF NOT EXISTS fx_reactions (
            post_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            kind VARCHAR(12) NOT NULL,
            side CHAR(1) NOT NULL DEFAULT 'u',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (post_id, user_id),
            KEY ix_user (user_id, created_at)
        ) $t",

        /* комментарии — дерево через parent_id; depth хранится, чтобы не считать рекурсией */
        "CREATE TABLE IF NOT EXISTS fx_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
            author_id INT UNSIGNED NOT NULL,
            as_studio_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            n_like INT UNSIGNED NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            edited_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY ix_post (post_id, parent_id, created_at),
            KEY ix_author (author_id, created_at)
        ) $t",

        "CREATE TABLE IF NOT EXISTS fx_comment_likes (
            comment_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (comment_id, user_id)
        ) $t",

        "CREATE TABLE IF NOT EXISTS fx_poll_votes (
            post_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            opt TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (post_id, user_id, opt)
        ) $t",

        /* подписки на студии и игры (дружба — отдельно, в существующей таблице friends) */
        "CREATE TABLE IF NOT EXISTS fx_follows (
            user_id INT UNSIGNED NOT NULL,
            target_type VARCHAR(8) NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, target_type, target_id),
            KEY ix_target (target_type, target_id)
        ) $t",

        "CREATE TABLE IF NOT EXISTS fx_reports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            target_type VARCHAR(8) NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            reporter_id INT UNSIGNED NOT NULL,
            reason VARCHAR(24) NOT NULL DEFAULT 'other',
            note VARCHAR(255) NULL,
            status TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_once (target_type, target_id, reporter_id),
            KEY ix_status (status, created_at)
        ) $t",

        /* «модератор игры» — НЕ модератор платформы: права только на стену и обсуждения этой игры */
        "CREATE TABLE IF NOT EXISTS fx_game_mods (
            game_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            granted_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (game_id, user_id),
            KEY ix_user (user_id)
        ) $t",

        /* DustHunt: задание от студии на конкретной игре */
        "CREATE TABLE IF NOT EXISTS fx_hunts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            game_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            rules TEXT NULL,
            prize VARCHAR(200) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            max_players INT UNSIGNED NULL,
            status VARCHAR(8) NOT NULL DEFAULT 'live',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_game (game_id, status, ends_at)
        ) $t",

        "CREATE TABLE IF NOT EXISTS fx_hunt_players (
            hunt_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            joined_at DATETIME NOT NULL,
            PRIMARY KEY (hunt_id, user_id),
            KEY ix_user (user_id)
        ) $t",

        /* монетизация студии: кнопка «Задонатить» и (позже) «Закрытая комната» */
        "CREATE TABLE IF NOT EXISTS fx_studio_monet (
            studio_id INT UNSIGNED NOT NULL,
            donate_on TINYINT(1) NOT NULL DEFAULT 1,
            donate_url VARCHAR(255) NULL,
            donate_note VARCHAR(160) NULL,
            room_on TINYINT(1) NOT NULL DEFAULT 0,
            room_title VARCHAR(80) NULL,
            room_desc VARCHAR(400) NULL,
            room_price INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (studio_id)
        ) $t",

        /* подпись роли сотрудника студии под его постами в девлоге */
        "CREATE TABLE IF NOT EXISTS fx_studio_titles (
            studio_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(48) NOT NULL,
            PRIMARY KEY (studio_id, user_id)
        ) $t",

        /* ссылки обратной связи студии (Telegram, Discord, форма, e-mail…) */
        "CREATE TABLE IF NOT EXISTS fx_studio_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            studio_id INT UNSIGNED NOT NULL,
            label VARCHAR(48) NOT NULL,
            url VARCHAR(255) NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT 'link',
            sort TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_studio (studio_id, sort)
        ) $t",

        /* просмотры страниц студии/игры/игрока по дням — для «аналитики» в боковых блоках */
        "CREATE TABLE IF NOT EXISTS fx_views_daily (
            subject VARCHAR(8) NOT NULL,
            subject_id INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            n INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (subject, subject_id, day)
        ) $t",

        "CREATE TABLE IF NOT EXISTS fx_meta (
            k VARCHAR(32) NOT NULL,
            v VARCHAR(255) NOT NULL,
            PRIMARY KEY (k)
        ) $t",
        ];
    }

    /** Одна лёгкая проверка на запрос; в сессии кэшируем, чтобы не стучаться каждый раз. */
    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        if (session_status() === PHP_SESSION_ACTIVE
            && ($_SESSION['fx_schema'] ?? 0) === FX_SCHEMA_VERSION) {
            $done = true;
            return;
        }

        $have = 0;
        try {
            $q = $pdo->query("SELECT v FROM fx_meta WHERE k = 'schema'");
            $have = (int)($q ? $q->fetchColumn() : 0);
        } catch (Throwable $e) {
            $have = 0;   // таблицы ещё нет — установим ниже
        }

        if ($have < FX_SCHEMA_VERSION) {
            foreach (self::ddl() as $sql) $pdo->exec($sql);
            self::upgrade($pdo, $have);
            $st = $pdo->prepare(
                "INSERT INTO fx_meta (k, v) VALUES ('schema', ?)
                 ON DUPLICATE KEY UPDATE v = VALUES(v)"
            );
            $st->execute([(string)FX_SCHEMA_VERSION]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['fx_schema'] = FX_SCHEMA_VERSION;
        $done = true;
    }

    /**
     * Шаги обновления с версии $from. Только добавление колонок/индексов — через
     * информационную схему, потому что ADD COLUMN IF NOT EXISTS есть только в MariaDB.
     */
    private static function upgrade(PDO $pdo, int $from): void
    {
        // пример на будущее:
        // if ($from < 2) self::addColumn($pdo, 'fx_posts', 'lang', "VARCHAR(4) NULL");
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
}
