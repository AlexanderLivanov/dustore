-- 005_game_builds.sql
-- Отдельный билд на каждую выбранную платформу вместо одного game_zip_url на всю игру.
-- Полностью аддитивно: ничего в games не меняется и не удаляется.
--
-- games.game_zip_url / games.game_zip_size остаются как есть — это "текущий активный
-- билд" для трёх существующих читателей (download_game.php, download_apk.php,
-- webplayer.php), которые ничего не знают про платформы и продолжат работать
-- без изменений. build_upload.php теперь параллельно с записью в game_builds
-- зеркалит апload и в games (последний загруженный билд — активный, как и было
-- до этой фичи). Настоящая раздача разных файлов разным платформам одновременно —
-- следующий шаг, отдельно от этой миграции.

CREATE TABLE IF NOT EXISTS game_builds (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    game_id     INT UNSIGNED NOT NULL,
    platform    VARCHAR(16)  NOT NULL,   -- 'Windows','macOS','Linux','Android','iOS','Web'
    build_url   VARCHAR(512) NULL,
    build_size  BIGINT UNSIGNED NULL,
    icon_url    VARCHAR(512) NULL,       -- только Android/iOS: иконка под конкретную платформу (опционально, иначе используется games.icon_url)
    permissions TEXT         NULL,       -- только Android/iOS: запрашиваемые разрешения, свободный текст/список
    screenshots JSON         NULL,       -- только Android/iOS: вертикальные скриншоты, формат [{"id":"...","path":"..."}]
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_game_platform (game_id, platform),
    KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
