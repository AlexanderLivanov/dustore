-- sql/migrations/006_desl4t.sql
-- Накатывать в базу `desl4t`. Только новые таблицы, существующие не трогаются.
USE desl4t;
SET NAMES utf8mb4;   -- без этого консольный клиент в latin1 зальёт кириллицу кракозябрами

-- Витрина профиля L4T (статус, обложка, акцент, закреплённые бейджи).
CREATE TABLE IF NOT EXISTS profiles (
    user_id        INT           NOT NULL PRIMARY KEY,
    headline       VARCHAR(120)  NULL,     -- строка под ником
    status_emoji   VARCHAR(16)   NULL,
    status_text    VARCHAR(80)   NULL,
    availability   VARCHAR(16)   NULL,     -- open | hiring | busy | closed
    location       VARCHAR(80)   NULL,
    banner_url     VARCHAR(500)  NULL,
    accent         CHAR(7)       NULL,     -- #rrggbb
    pinned_badges  VARCHAR(255)  NULL,     -- коды бейджей через запятую, до 3
    hidden_blocks  VARCHAR(255)  NULL,     -- блоки, скрытые от гостей
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Уникальные просмотры профиля: один зритель — один раз в сутки.
CREATE TABLE IF NOT EXISTS profile_views (
    profile_id  INT       NOT NULL,
    day         DATE      NOT NULL,
    viewer_key  CHAR(32)  NOT NULL,
    PRIMARY KEY (profile_id, day, viewer_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Уникальные просмотры заявок (дедуп для счётчика bids.views).
CREATE TABLE IF NOT EXISTS bid_views (
    bid_id      INT       NOT NULL,
    day         DATE      NOT NULL,
    viewer_key  CHAR(32)  NOT NULL,
    PRIMARY KEY (bid_id, day, viewer_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Проверка: у bids должна быть колонка views (её инкрементит l4t/api/bid_view.php).
-- Если запрос ниже вернёт 0 — раскомментируй ALTER.
SELECT COUNT(*) AS bids_has_views FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'desl4t' AND TABLE_NAME = 'bids' AND COLUMN_NAME = 'views';
-- ALTER TABLE bids ADD COLUMN views INT NOT NULL DEFAULT 0;
