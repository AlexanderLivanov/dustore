-- 006_activity_l4t_profile.sql
-- Полностью аддитивно: только новые таблицы, существующие не трогаем
-- (users общая с v3 — туда ничего не добавляем).
--
-- Порядок раскатки:
--   1. Часть A — в базе `dustore`.
--   2. Часть B — в базе `desl4t`.
--   3. php sql/migrations/006_backfill_activity.php   (разово, из CLI)
--   4. Деплой кода (activity.php начнёт писать user_daily_activity).
-- Код устойчив к любому порядку: пока таблиц нет, GPI показывает
-- «аналитика собирается», профиль — прочерки, хартбит молча пропускает запись.


-- ═════════════════════════════════════════════════════════════════════
-- A. База `dustore`
-- ═════════════════════════════════════════════════════════════════════

-- Один ряд = один пользователь в один день.
-- Почему не лог событий: для DAU/WAU/MAU, retention D1/D7/D30 и
-- «вернувшихся» нужен ровно факт «был в этот день». Лог хитов на каждую
-- минуту хартбита — это ~1440 строк на юзера в сутки ради одного бита.
-- Здесь — одна строка, обновляемая через ON DUPLICATE KEY UPDATE.
--
-- sessions: новая сессия начинается, если с last_seen прошло > 30 минут
-- (стандартное определение GA/Amplitude). Считается прямо в UPSERT.
CREATE TABLE IF NOT EXISTS user_daily_activity (
    user_id     INT          NOT NULL,
    day         DATE         NOT NULL,
    hits        INT UNSIGNED NOT NULL DEFAULT 1,
    sessions    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    first_seen  DATETIME     NOT NULL,
    last_seen   DATETIME     NOT NULL,
    source      ENUM('live','backfill') NOT NULL DEFAULT 'live',
    PRIMARY KEY (user_id, day),
    KEY idx_day (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Кэш рассчитанного индекса по дням. Не источник правды (всё
-- пересчитывается из сырых таблиц), а «замороженная» история:
-- если кто-то удалит старые отзывы, прошлые значения GPI не поплывут.
CREATE TABLE IF NOT EXISTS gpi_daily (
    day         DATE          NOT NULL PRIMARY KEY,
    gpi         DECIMAL(8,1)  NOT NULL,
    pillars     TEXT          NULL,          -- JSON {players:1012.3, content:...}
    breadth     DECIMAL(5,1)  NULL,          -- % метрик в росте
    computed_at DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═════════════════════════════════════════════════════════════════════
-- B. База `desl4t`
-- ═════════════════════════════════════════════════════════════════════

-- Витрина профиля L4T. Отдельная таблица в базе L4T, а не колонки
-- в users: users делят v2 и v3, её раздувать не хотим.
-- Старые l4t_about / l4t_exp / l4t_files / l4t_projects остаются в users
-- как были — переносить нечего и незачем.
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
-- viewer_key = md5('u'.user_id) либо md5('a'.anon_cookie) — сырые
-- идентификаторы не храним. Владелец свой профиль не накручивает.
CREATE TABLE IF NOT EXISTS profile_views (
    profile_id  INT       NOT NULL,
    day         DATE      NOT NULL,
    viewer_key  CHAR(32)  NOT NULL,
    PRIMARY KEY (profile_id, day, viewer_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Просмотры заявок. bids.views существовал, но его никто не инкрементил
-- (ни одного `views = views + 1` в проекте) — поэтому везде висел 0.
-- Дедуп — как у профиля.
CREATE TABLE IF NOT EXISTS bid_views (
    bid_id      INT       NOT NULL,
    day         DATE      NOT NULL,
    viewer_key  CHAR(32)  NOT NULL,
    PRIMARY KEY (bid_id, day, viewer_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
