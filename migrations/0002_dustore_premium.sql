-- Dustore Premium: срок действия статуса у пользователя + журнал выдач.
-- Идемпотентно: колонка и индекс добавляются только если их ещё нет
-- (ADD COLUMN IF NOT EXISTS — только MariaDB, поэтому через information_schema).
-- Время хранится по Москве (APP_TZ), значение пишет PHP, а не NOW().

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'premium_until');
SET @sql := IF(@has_col = 0,
    'ALTER TABLE users ADD COLUMN premium_until DATETIME NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_premium_until');
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE users ADD INDEX idx_premium_until (premium_until)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Журнал: кто, кому, что и до какого числа выдал/снял.
CREATE TABLE IF NOT EXISTS premium_grants (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    granted_by INT NOT NULL,
    action     VARCHAR(8) NOT NULL DEFAULT 'grant',  -- grant | revoke
    until      DATETIME NULL,                        -- итоговый срок после операции (NULL при revoke)
    note       VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_user (user_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
