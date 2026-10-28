-- Монетизация студий: анкета (ИП/ООО/самозанятый) → проверка администратором → приём платежей
-- через агрегатор разработчика; инфраструктурный сбор списывается с виртуального баланса студии.
-- Только новые таблицы, CREATE TABLE IF NOT EXISTS — повторный запуск безопасен.
-- То же создаёт самоустановка mon_schema() в swad/controllers/mon/lib.php.

-- Профиль монетизации студии: одобренные данные и настройки
CREATE TABLE IF NOT EXISTS mon_profiles (
    studio_id        INT NOT NULL PRIMARY KEY,
    legal_type       VARCHAR(8)  NULL,                       -- ip | ooo | npd
    status           VARCHAR(16) NOT NULL DEFAULT 'draft',    -- draft | pending | active | rejected
    legal            MEDIUMTEXT  NULL,                        -- JSON одобренных данных компании/лица
    tax              TEXT        NULL,                        -- JSON {system, vat}
    bank             TEXT        NULL,                        -- JSON одобренных реквизитов для выплат
    payments_enabled TINYINT(1)  NOT NULL DEFAULT 0,          -- включает администратор
    agg              VARCHAR(16) NULL,                        -- yookassa | tbank | robokassa | mtspay
    agg_test         TEXT        NULL,                        -- тестовые ключи, зашифрованы (enc1:…)
    agg_test_ok      DATETIME    NULL,                        -- когда прошла тестовая оплата
    agg_live         TEXT        NULL,                        -- боевые ключи, зашифрованы
    agg_live_ok      DATETIME    NULL,                        -- когда боевые ключи прошли проверку
    send_receipt     TINYINT(1)  NOT NULL DEFAULT 1,          -- передавать чек 54-ФЗ агрегатору
    fee_payer        VARCHAR(8)  NOT NULL DEFAULT 'player',   -- player | dev
    fee_pct          DECIMAL(4,1) NOT NULL DEFAULT 7.0,       -- 5…50
    fee_off          TINYINT(1)  NOT NULL DEFAULT 0,          -- можно, пока доход за месяц < 10 000 ₽
    tax_on           TINYINT(1)  NOT NULL DEFAULT 0,          -- прибавлять налог продавца к цене
    tax_pct          DECIMAL(4,1) NOT NULL DEFAULT 6.0,       -- 4…22
    offer_version    VARCHAR(16) NULL,
    offer_at         DATETIME    NULL,
    activated_at     DATETIME    NULL,
    created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Заявки на проверку: первичная анкета и каждое изменение реквизитов (история не удаляется)
CREATE TABLE IF NOT EXISTS mon_requests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    studio_id     INT NOT NULL,
    kind          VARCHAR(16) NOT NULL,                       -- onboarding | bank
    payload       MEDIUMTEXT  NOT NULL,                       -- JSON заявки
    status        VARCHAR(16) NOT NULL DEFAULT 'pending',     -- pending | approved | rejected | withdrawn
    comment       TEXT        NULL,                           -- причина отказа
    user_id       INT NULL,
    offer_version VARCHAR(16) NULL,
    offer_hash    CHAR(64)    NULL,                           -- sha256 текста принятого договора
    ip            VARCHAR(45) NULL,
    reviewed_by   INT NULL,
    reviewed_at   DATETIME NULL,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_studio (studio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Каждая оплата через агрегатор: покупки игр и тестовые оплаты
CREATE TABLE IF NOT EXISTS mon_payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    studio_id   INT NOT NULL,
    game_id     INT NULL,
    user_id     INT NULL,
    order_id    INT NULL,                                     -- game_orders.id
    purpose     VARCHAR(16) NOT NULL,                         -- game | agg_test
    agg         VARCHAR(16) NOT NULL,
    mode        VARCHAR(8)  NOT NULL,                         -- test | live
    external_id VARCHAR(64) NULL,                             -- id платежа у агрегатора
    status      VARCHAR(16) NOT NULL DEFAULT 'new',           -- new | pending | succeeded | canceled | refunded
    amount      DECIMAL(12,2) NOT NULL,                       -- сколько заплатил игрок
    base_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    fee         DECIMAL(12,2) NOT NULL DEFAULT 0,             -- инфраструктурный сбор
    fee_payer   VARCHAR(8)  NULL,
    tax_add     DECIMAL(12,2) NOT NULL DEFAULT 0,             -- надбавка на налог продавца
    confirm_url VARCHAR(1024) NULL,
    raw         MEDIUMTEXT NULL,                              -- последний ответ API агрегатора
    paid_at     DATETIME NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ext (agg, external_id),
    KEY idx_studio (studio_id, status),
    KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Виртуальный счёт студии: бонус (не выводится) и пополнения; сбор списывается отсюда.
-- ref уникален — повторный вебхук не спишет сбор дважды.
CREATE TABLE IF NOT EXISTS mon_ledger (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    studio_id  INT NOT NULL,
    bucket     VARCHAR(8)  NOT NULL,                          -- bonus | cash
    amount     DECIMAL(12,2) NOT NULL,                        -- + пополнение, − списание
    kind       VARCHAR(16) NOT NULL,                          -- bonus | topup | fee | adjust
    ref        VARCHAR(64) NOT NULL,
    payment_id INT UNSIGNED NULL,
    note       VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ref (ref),
    KEY idx_studio (studio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
