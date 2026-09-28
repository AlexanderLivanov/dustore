<?php
declare(strict_types=1);

/**
 * chat/_blocks.php — чёрный список в личных чатах.
 * ---------------------------------------------------------------------------
 * Один раз заблокировали (в любую сторону) — обе стороны больше не могут
 * писать друг другу новые сообщения (как в Telegram: композер выключается
 * у обоих), но история переписки никуда не девается и остаётся видна.
 * Разблокировка снимает ограничение полностью.
 *
 * Действует только для личных диалогов (type='dm'). Чаты со студией — это
 * канал поддержки/обращений, у них другая природа, блокировка туда не лезет.
 *
 * Схема — не по классовому FxSchema-образцу, а в стиле уже существующих
 * chat_v2()/chat_v3() из api.php: простая функция, CREATE TABLE IF NOT
 * EXISTS (это стандартный SQL, портируется на MySQL и MariaDB одинаково —
 * в отличие от ADD COLUMN IF NOT EXISTS, который MariaDB-only и уже один раз
 * уронил прод на assetstore), положительный результат кэшируем в сессии.
 */

function chat_blocks_ensure(PDO $db): void
{
    static $done = false;
    if ($done) return;
    if (($_SESSION['chat_blocks_schema'] ?? false) === true) { $done = true; return; }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS chat_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            blocker_id INT UNSIGNED NOT NULL,
            blocked_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pair (blocker_id, blocked_id),
            KEY ix_blocked (blocked_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $_SESSION['chat_blocks_schema'] = true;
    $done = true;
}

function chat_block_user(PDO $db, int $blocker, int $blocked): void
{
    $db->prepare("INSERT IGNORE INTO chat_blocks (blocker_id, blocked_id, created_at) VALUES (?, ?, NOW())")
       ->execute([$blocker, $blocked]);
}

function chat_unblock_user(PDO $db, int $blocker, int $blocked): void
{
    $db->prepare("DELETE FROM chat_blocks WHERE blocker_id = ? AND blocked_id = ?")
       ->execute([$blocker, $blocked]);
}

/**
 * @return array{a_blocked_b: bool, b_blocked_a: bool}
 */
function chat_block_status(PDO $db, int $a, int $b): array
{
    $st = $db->prepare(
        "SELECT blocker_id, blocked_id FROM chat_blocks
          WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)"
    );
    $st->execute([$a, $b, $b, $a]);

    $aBlockedB = false;
    $bBlockedA = false;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((int)$r['blocker_id'] === $a && (int)$r['blocked_id'] === $b) $aBlockedB = true;
        if ((int)$r['blocker_id'] === $b && (int)$r['blocked_id'] === $a) $bBlockedA = true;
    }
    return ['a_blocked_b' => $aBlockedB, 'b_blocked_a' => $bBlockedA];
}

/** true, если хотя бы один из двух заблокировал другого — гейт на отправку. */
function chat_is_blocked_pair(PDO $db, int $a, int $b): bool
{
    $s = chat_block_status($db, $a, $b);
    return $s['a_blocked_b'] || $s['b_blocked_a'];
}
