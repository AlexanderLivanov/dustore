<?php
/**
 * Самоустановка таблицы game_builds (билды по платформам: файл, иконка,
 * скриншоты, разрешения). Без неё devs/edit.php, build_upload.php и
 * upload_media.php падают с «Table game_builds doesn't exist» на свежей базе.
 * Идемпотентно: CREATE TABLE IF NOT EXISTS, повторный вызов за запрос — no-op.
 * Если таблица уже есть — только добавляет недостающую колонку requirements.
 */
function ensure_game_builds_table(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if (!$db->query("SHOW TABLES LIKE 'game_builds'")->fetchColumn()) $db->exec("
            CREATE TABLE IF NOT EXISTS game_builds (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                game_id     INT NOT NULL,
                platform    VARCHAR(32) NOT NULL,
                build_url   VARCHAR(1024) NULL,
                build_size  BIGINT UNSIGNED NULL,
                icon_url    VARCHAR(1024) NULL,
                screenshots MEDIUMTEXT NULL,
                permissions TEXT NULL,
                requirements TEXT NULL,
                created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_game_platform (game_id, platform),
                KEY idx_game (game_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Системные требования по платформам (миграция 0003) — догоняем старые базы
        if (!$db->query("SHOW COLUMNS FROM game_builds LIKE 'requirements'")->fetchColumn()) {
            $db->exec("ALTER TABLE game_builds ADD COLUMN requirements TEXT NULL AFTER permissions");
        }
    } catch (Throwable $e) {
        error_log('ensure_game_builds_table: ' . $e->getMessage());
    }
}
