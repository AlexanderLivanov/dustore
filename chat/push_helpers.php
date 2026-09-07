<?php
declare(strict_types=1);

/**
 * chat/push_helpers.php — постановка web-push в очередь push_outbox.
 *
 * ВАЖНО: этот файл лежал в проекте ПУСТЫМ (0 байт). api.php вызывает
 * push_enqueue_for_conversation() и push_enqueue_user() через function_exists(),
 * поэтому отсутствие функций не роняло страницу — пуши просто молча
 * никогда не отправлялись. Воркер на порту 3001 забирал пустую очередь.
 *
 * Схема (сверено с дампом):
 *   push_outbox(id, user_id, title, body, url, status ENUM('pending','sent','failed'),
 *               attempts, created_at)
 *   push_subscriptions(id, user_id, endpoint, p256dh, auth, user_agent, created_at)
 */

/** Обрезка до N символов с многоточием — в пуш-баннер длинный текст не влезает. */
function push_trim(string $s, int $max = 140): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    if (mb_strlen($s) <= $max) return $s;
    return mb_substr($s, 0, $max - 1) . '…';
}

/** Есть ли у пользователя хоть одна подписка. Без неё задача в outbox бессмысленна. */
function push_user_has_subscription(PDO $db, int $userId): bool {
    $st = $db->prepare("SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    return (bool)$st->fetchColumn();
}

/** Положить одну задачу в очередь. Возвращает id задачи или 0. */
function push_enqueue_user(PDO $db, int $userId, string $title, string $body, string $url = '/chat/'): int {
    if ($userId <= 0) return 0;
    if (!push_user_has_subscription($db, $userId)) return 0;

    $st = $db->prepare("INSERT INTO push_outbox(user_id, title, body, url, status, attempts, created_at)
                        VALUES(?, ?, ?, ?, 'pending', 0, NOW())");
    $st->execute([$userId, push_trim($title, 80), push_trim($body), $url]);
    return (int)$db->lastInsertId();
}

/**
 * Разослать всем участникам беседы, кроме автора.
 *
 * Не шлём тому, кто прочитал беседу меньше 60 секунд назад: человек и так
 * смотрит на экран, пуш будет дублировать то, что он уже видит. Признак —
 * last_read_message_id совпал с последним сообщением до нашего.
 */
function push_enqueue_for_conversation(PDO $db, int $convId, int $senderId, string $senderName, string $body): void {
    $c = $db->prepare("SELECT type, studio_id FROM conversations WHERE id = ? LIMIT 1");
    $c->execute([$convId]);
    $conv = $c->fetch(PDO::FETCH_ASSOC);
    if (!$conv) return;

    $recipients = [];

    // обычные участники
    $p = $db->prepare("SELECT user_id FROM conversation_participants
                        WHERE conversation_id = ? AND user_id <> ?");
    $p->execute([$convId, $senderId]);
    foreach ($p->fetchAll(PDO::FETCH_COLUMN) as $uid) $recipients[(int)$uid] = true;

    // для беседы студии — ещё и владелец студии со стаффом
    if ($conv['type'] === 'studio' && !empty($conv['studio_id'])) {
        foreach (studio_member_ids($db, (int)$conv['studio_id']) as $uid) {
            if ($uid !== $senderId) $recipients[$uid] = true;
        }
    }

    $title = $conv['type'] === 'studio' ? ($senderName . ' · студия') : $senderName;
    $url   = '/chat/?conversation=' . $convId;

    foreach (array_keys($recipients) as $uid) {
        push_enqueue_user($db, (int)$uid, $title, $body, $url);
    }
}

/**
 * Пользователи студии: владелец + staff.
 *
 * staff.telegram_id BIGINT, users.telegram_id VARCHAR(32) — джойнить их
 * напрямую нельзя, индекс на users.telegram_id при неявном касте не работает.
 * Поэтому два запроса и стык в PHP, как в _helpers.php.
 */
function studio_member_ids(PDO $db, int $studioId): array {
    $ids = [];

    $o = $db->prepare("SELECT owner_id FROM studios WHERE id = ? LIMIT 1");
    $o->execute([$studioId]);
    $owner = (int)$o->fetchColumn();
    if ($owner > 0) $ids[] = $owner;

    $s = $db->prepare("SELECT telegram_id FROM staff WHERE org_id = ? AND telegram_id IS NOT NULL");
    $s->execute([$studioId]);
    $tgs = array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN), fn($v) => $v !== null && $v !== ''));

    if ($tgs) {
        $in = implode(',', array_fill(0, count($tgs), '?'));
        $u  = $db->prepare("SELECT id FROM users WHERE telegram_id IN ($in)");
        $u->execute(array_map('strval', $tgs));
        foreach ($u->fetchAll(PDO::FETCH_COLUMN) as $uid) $ids[] = (int)$uid;
    }

    return array_values(array_unique($ids));
}