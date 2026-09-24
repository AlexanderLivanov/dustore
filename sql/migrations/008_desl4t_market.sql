-- 008_desl4t_market.sql — L4T как двусторонний рынок: спрос (bids) и предложение (offers).
-- Накатывать в `desl4t` после 007:  mysql --force desl4t < 008_desl4t_market.sql
-- (--force: при повторном прогоне ALTER-ы упадут с 1060 «колонка уже есть» — это нормально).
USE desl4t;
SET NAMES utf8mb4;

-- ═════════════════════════════ СПРОС = существующие bids ═════════════════════════════
-- Заявку не переносим в новую таблицу: на bids уже завязаны отклики, лента, GPI,
-- титры. Добавляем ей «цену» — без неё нет стакана.
--   kind      task | team | jam | consult     — что это за работа
--   pay_type  money | share | free             — чем платят
--   budget_*  рубли; для fixed-бюджета min = max
--   duration_days — срок работы, expires_at — когда заявка сама снимется с рынка
ALTER TABLE bids ADD COLUMN kind          VARCHAR(8)  NULL;
ALTER TABLE bids ADD COLUMN pay_type      VARCHAR(8)  NULL;
ALTER TABLE bids ADD COLUMN budget_min    INT         NULL;
ALTER TABLE bids ADD COLUMN budget_max    INT         NULL;
ALTER TABLE bids ADD COLUMN duration_days SMALLINT    NULL;
ALTER TABLE bids ADD COLUMN expires_at    DATE        NULL;

-- ═════════════════════════════ ПРЕДЛОЖЕНИЕ ═════════════════════════════
-- «Я могу»: специалист выставляет свою способность выполнить работу.
-- Один человек может держать несколько предложений: «Unity-задачи за деньги»
-- и «в команду на джем бесплатно» — это разные позиции на рынке.
CREATE TABLE IF NOT EXISTS offers (
    id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    title          VARCHAR(120) NOT NULL,          -- «Unity / C# — мультиплеер»
    kind           VARCHAR(8)   NOT NULL DEFAULT 'any',  -- task | team | jam | consult | any
    pay_type       VARCHAR(8)   NOT NULL DEFAULT 'money',
    price_min      INT          NULL,              -- рубли за задачу
    price_max      INT          NULL,              -- NULL = «от price_min»
    available_from DATE         NULL,              -- NULL = свободен сейчас
    hours_week     SMALLINT     NULL,
    details        VARCHAR(2000) NULL,
    stage          VARCHAR(8)   NOT NULL DEFAULT 'active',   -- active | paused | closed
    views          INT          NOT NULL DEFAULT 0,
    expires_at     DATE         NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_stage (stage, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_skills (
    offer_id INT               NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (offer_id, skill_id),
    KEY idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════ СВЕДЕНИЕ СТОРОН ═════════════════════════════
-- Пара «спрос × предложение», которую нашёл движок или предложила одна из сторон.
-- Сделка = обе стороны сказали «да» (как исполнение встречных заявок на бирже).
-- При сделке создаётся строка responds со статусом «принят»/«в команде» —
-- так сделка сразу попадает в титры, рекомендации и GPI «время до команды».
CREATE TABLE IF NOT EXISTS matches (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bid_id      INT          NOT NULL,
    offer_id    INT          NOT NULL,
    score       TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0..100
    reasons     VARCHAR(255) NULL,                      -- «Unity, C# · бюджет покрывает цену · свободен сейчас»
    initiator   VARCHAR(8)   NOT NULL DEFAULT 'engine', -- engine | need | offer
    need_state  VARCHAR(4)   NOT NULL DEFAULT 'new',    -- new | yes | no
    offer_state VARCHAR(4)   NOT NULL DEFAULT 'new',
    respond_id  INT          NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dealt_at    DATETIME     NULL,
    UNIQUE KEY uq_pair (bid_id, offer_id),
    KEY idx_offer (offer_id),
    KEY idx_deal (dealt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Существующие активные заявки: помечаем «задачей за деньги без бюджета»
-- и даём месяц жизни, чтобы старьё не висело в стакане вечно.
UPDATE bids SET kind = IF(jam_id IS NOT NULL AND jam_id > 0, 'jam',
                        IF(goal LIKE '%команд%', 'team', IF(goal LIKE '%онсульт%', 'consult', 'task')))
 WHERE kind IS NULL;
UPDATE bids SET pay_type = IF(conditions LIKE '%дол%', 'share', IF(kind = 'jam', 'free', 'money')) WHERE pay_type IS NULL;
UPDATE bids SET expires_at = GREATEST(CURDATE() + INTERVAL 14 DAY, DATE(created_at) + INTERVAL 30 DAY)
 WHERE expires_at IS NULL AND stage = 'active';
