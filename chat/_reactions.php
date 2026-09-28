<?php
declare(strict_types=1);

/**
 * chat/_reactions.php — реакции на сообщения (эмодзи).
 * ---------------------------------------------------------------------------
 * Одна реакция на человека на сообщение (как в WhatsApp): выбрал новый
 * эмодзи поверх своего старого — реакция заменяется; выбрал тот же самый —
 * снимается. UNIQUE(message_id,user_id) и делает это естественно через
 * INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * Схема — тот же приём, что в chat/_blocks.php: CREATE TABLE IF NOT EXISTS,
 * положительный результат кэшируем в сессии.
 */

function chat_reactions_ensure(PDO $db): void
{
    static $done = false;
    if ($done) return;
    if (($_SESSION['chat_reactions_schema'] ?? false) === true) { $done = true; return; }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS message_reactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            emoji VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_msg_user (message_id, user_id),
            KEY ix_msg (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $_SESSION['chat_reactions_schema'] = true;
    $done = true;
}

/** Поставить/сменить/снять реакцию. Возвращает true, если реакция теперь стоит (false — сняли). */
function chat_toggle_reaction(PDO $db, int $messageId, int $userId, string $emoji): bool
{
    $st = $db->prepare("SELECT emoji FROM message_reactions WHERE message_id=? AND user_id=?");
    $st->execute([$messageId, $userId]);
    $existing = $st->fetchColumn();

    if ($existing !== false && $existing === $emoji) {
        $db->prepare("DELETE FROM message_reactions WHERE message_id=? AND user_id=?")->execute([$messageId, $userId]);
        return false;
    }
    $db->prepare(
        "INSERT INTO message_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = VALUES(created_at)"
    )->execute([$messageId, $userId, $emoji]);
    return true;
}

/**
 * Агрегаты реакций для пачки сообщений разом (не по одному запросу на сообщение).
 * @return array<int, array<int, array{emoji:string,count:int,mine:bool}>> — message_id => список
 */
function chat_reactions_for(PDO $db, array $messageIds, int $myId): array
{
    $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
    if (!$messageIds) return [];
    $in = implode(',', array_fill(0, count($messageIds), '?'));
    $st = $db->prepare("SELECT message_id, emoji, user_id FROM message_reactions WHERE message_id IN ($in)");
    $st->execute($messageIds);

    $byMsg = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mid = (int)$r['message_id']; $emoji = (string)$r['emoji']; $uid = (int)$r['user_id'];
        if (!isset($byMsg[$mid][$emoji])) $byMsg[$mid][$emoji] = ['emoji' => $emoji, 'count' => 0, 'mine' => false];
        $byMsg[$mid][$emoji]['count']++;
        if ($uid === $myId) $byMsg[$mid][$emoji]['mine'] = true;
    }
    foreach ($byMsg as $mid => $emojis) {
        $list = array_values($emojis);
        usort($list, static fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['emoji'], $b['emoji']));
        $byMsg[$mid] = $list;
    }
    return $byMsg;
}
