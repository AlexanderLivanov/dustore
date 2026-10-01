-- Билды по платформам (файл, иконка, скриншоты, разрешения).
CREATE TABLE IF NOT EXISTS game_builds (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    game_id     INT NOT NULL,
    platform    VARCHAR(32) NOT NULL,
    build_url   VARCHAR(1024) NULL,
    build_size  BIGINT UNSIGNED NULL,
    icon_url    VARCHAR(1024) NULL,
    screenshots MEDIUMTEXT NULL,
    permissions TEXT NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_game_platform (game_id, platform),
    KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
