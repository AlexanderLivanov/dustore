-- Системные требования по платформам: JSON [{key,label,value}] в game_builds.
-- Идемпотентно (ADD COLUMN IF NOT EXISTS — только MariaDB, поэтому через information_schema).
-- То же самое делает самоустановка ensure_game_builds_table() — миграция нужна для прода/CLI.

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_builds' AND COLUMN_NAME = 'requirements');
SET @sql := IF(@has_col = 0,
    'ALTER TABLE game_builds ADD COLUMN requirements TEXT NULL AFTER permissions',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
