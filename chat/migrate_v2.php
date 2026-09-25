<?php
declare(strict_types=1);
/**
 * chat/migrate_v2.php — миграция чата v2 (файлы, ответы, пины, настройки звука)
 *                       и v3 (статус «доставлено», обои бесед).
 *
 * Только аддитивно (правило общей БД v2/v3): новые таблицы и nullable-колонки,
 * никаких переименований и удалений. Идемпотентно: перед каждым шагом смотрим
 * information_schema, повторный запуск ничего не ломает.
 *
 * Запуск:
 *   - в браузере под админом (global_role = -1): /chat/migrate_v2.php
 *   - на сервере: php chat/migrate_v2.php
 */

$cli = PHP_SAPI === 'cli';
if ($cli) $_SERVER['HTTP_HOST'] = $argv[1] ?? 'dustore.ru';

require_once __DIR__ . '/../swad/config.php';
if (!$cli) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: text/plain; charset=utf-8');
    if ((int)($_SESSION['USERDATA']['global_role'] ?? 0) !== -1) { http_response_code(403); exit("Только для администратора\n"); }
}

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();

function has_table(PDO $db, string $s, string $t): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $q->execute([$s, $t]); return (bool)$q->fetchColumn();
}
function has_column(PDO $db, string $s, string $t, string $c): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$s, $t, $c]); return (bool)$q->fetchColumn();
}
function step(string $name, bool $done, callable $run): void {
    if ($done) { echo "  = {$name}: уже есть\n"; return; }
    $run(); echo "  + {$name}: создано\n";
}

echo "Миграция чата v2 → {$schema}\n";

step('таблица chat_files', has_table($db, $schema, 'chat_files'), fn() => $db->exec("
    CREATE TABLE chat_files (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        owner_id   INT NOT NULL,
        kind       ENUM('image','file','sound') NOT NULL DEFAULT 'file',
        status     ENUM('pending','ready') NOT NULL DEFAULT 'pending',
        s3_key     VARCHAR(255) NOT NULL,
        thumb_key  VARCHAR(255) NULL,
        name       VARCHAR(255) NOT NULL,
        mime       VARCHAR(100) NOT NULL,
        size       INT UNSIGNED NOT NULL,
        width      SMALLINT UNSIGNED NULL,
        height     SMALLINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_owner (owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

step('messages.reply_to_id', has_column($db, $schema, 'messages', 'reply_to_id'),
    fn() => $db->exec("ALTER TABLE messages ADD COLUMN reply_to_id INT NULL"));
step('messages.file_id', has_column($db, $schema, 'messages', 'file_id'),
    fn() => $db->exec("ALTER TABLE messages ADD COLUMN file_id INT UNSIGNED NULL"));

step('таблица conversation_pins', has_table($db, $schema, 'conversation_pins'), fn() => $db->exec("
    CREATE TABLE conversation_pins (
        conversation_id INT NOT NULL,
        message_id      INT NOT NULL,
        pinned_by       INT NOT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (conversation_id, message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

step('таблица chat_user_settings', has_table($db, $schema, 'chat_user_settings'), fn() => $db->exec("
    CREATE TABLE chat_user_settings (
        user_id       INT NOT NULL PRIMARY KEY,
        sound         VARCHAR(32) NOT NULL DEFAULT 'dust',
        sound_file_id INT UNSIGNED NULL,
        volume        TINYINT UNSIGNED NOT NULL DEFAULT 70,
        updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

/* ── v3: «доставлено» ─────────────────────────────────────────────
   Отдельный указатель рядом с last_read: до какого id сообщения клиент
   собеседника уже ДОКАЧАЛ беседу (открыт сайт, список, хедер) — даже если
   сам диалог не открыт. Для студийных бесед — общий на всю команду студии. */
step('conversation_participants.last_delivered_message_id',
    has_column($db, $schema, 'conversation_participants', 'last_delivered_message_id'),
    fn() => $db->exec("ALTER TABLE conversation_participants ADD COLUMN last_delivered_message_id INT NULL"));
step('conversations.studio_last_delivered_id',
    has_column($db, $schema, 'conversations', 'studio_last_delivered_id'),
    fn() => $db->exec("ALTER TABLE conversations ADD COLUMN studio_last_delivered_id INT NULL"));

/* ── v3: обои ─────────────────────────────────────────────────────
   user_id = 0 — общие обои беседы (видят оба), user_id = N — личные,
   перекрывают общие только для N. preset='none' в личной записи —
   «у меня без обоев», даже если общие есть. */
step('таблица conversation_wallpapers', has_table($db, $schema, 'conversation_wallpapers'), fn() => $db->exec("
    CREATE TABLE conversation_wallpapers (
        conversation_id INT NOT NULL,
        user_id         INT NOT NULL DEFAULT 0,
        preset          VARCHAR(32) NULL,
        file_id         INT UNSIGNED NULL,
        dim             TINYINT UNSIGNED NOT NULL DEFAULT 0,
        set_by          INT NOT NULL,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (conversation_id, user_id),
        KEY idx_file (file_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));

// кэш «v2/v3 включён» живёт в сессии — сбросим, чтобы фичи включились сразу
if (!$cli) unset($_SESSION['chat_v2'], $_SESSION['chat_v3']);
echo "Готово.\n";
