<?php
declare(strict_types=1);

/**
 * swad/controllers/_avatars.php — работа с историей аватарок пользователя.
 * ---------------------------------------------------------------------------
 * "Текущая" аватарка — это по-прежнему users.profile_picture (никто из
 * старого кода это не трогает: рендер по всему сайту как читал эту колонку,
 * так и читает). user_avatars — просто журнал загрузок поверх неё, чтобы
 * можно было листать назад и удалять как в Telegram.
 *
 * Требует: AvatarSchema::ensure($pdo) вызван заранее (в каждом из двух
 * новых эндпоинтов и в upload_avatar.php).
 */

/**
 * Список аватарок пользователя, новые сверху.
 * @return array<int, array{id:int, url:string, s3_key:?string, created_at:string, is_current:bool}>
 */
function avatar_history_list(PDO $pdo, int $userId, string $currentUrl): array
{
    $st = $pdo->prepare(
        "SELECT id, url, s3_key, created_at FROM user_avatars WHERE user_id = ? ORDER BY id DESC"
    );
    $st->execute([$userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function ($r) use ($currentUrl) {
        return [
            'id'         => (int)$r['id'],
            'url'        => (string)$r['url'],
            's3_key'     => $r['s3_key'],
            'created_at' => (string)$r['created_at'],
            'is_current' => $currentUrl !== '' && hash_equals($currentUrl, (string)$r['url']),
        ];
    }, $rows);
}

/** Добавить запись в историю после успешной загрузки. Возвращает id новой строки. */
function avatar_history_add(PDO $pdo, int $userId, string $url, ?string $s3Key): int
{
    $st = $pdo->prepare(
        "INSERT INTO user_avatars (user_id, url, s3_key, created_at) VALUES (?, ?, ?, NOW())"
    );
    $st->execute([$userId, $url, $s3Key]);
    return (int)$pdo->lastInsertId();
}

/**
 * Удалить одну аватарку из истории.
 *
 * Если удаляется именно ТЕКУЩАЯ (совпадает с users.profile_picture) —
 * автоматически повышаем следующую по свежести оставшуюся строку в текущие
 * (как в Telegram: убрал активное фото — активным становится следующее по
 * списку). Если оставшихся не осталось — new_current_url = null, вызывающий
 * код сам решает, ставить дефолтную картинку или оставлять профиль без фото.
 *
 * $avatarId — конкретная строка (после явного выбора в браузере истории);
 * если не передан — трактуем как "удали текущую".
 *
 * @return array{deleted: ?array, new_current_url: ?string, promoted: bool}
 */
function avatar_history_delete(PDO $pdo, int $userId, ?int $avatarId, string $currentUrl): array
{
    if ($avatarId !== null) {
        $st = $pdo->prepare(
            "SELECT id, url, s3_key FROM user_avatars WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $st->execute([$avatarId, $userId]);
    } else {
        $st = $pdo->prepare(
            "SELECT id, url, s3_key FROM user_avatars WHERE user_id = ? AND url = ?
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$userId, $currentUrl]);
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['deleted' => null, 'new_current_url' => null, 'promoted' => false];
    }

    $wasCurrent = $currentUrl !== '' && hash_equals($currentUrl, (string)$row['url']);

    $del = $pdo->prepare("DELETE FROM user_avatars WHERE id = ? AND user_id = ?");
    $del->execute([(int)$row['id'], $userId]);

    $newUrl  = null;
    $promoted = false;
    if ($wasCurrent) {
        $next = $pdo->prepare(
            "SELECT url FROM user_avatars WHERE user_id = ? ORDER BY id DESC LIMIT 1"
        );
        $next->execute([$userId]);
        $newUrl   = $next->fetchColumn() ?: null;
        $promoted = true;
    }

    return [
        'deleted'         => [
            'id'     => (int)$row['id'],
            'url'    => (string)$row['url'],
            's3_key' => $row['s3_key'],
        ],
        'new_current_url' => $newUrl,
        'promoted'        => $promoted,
    ];
}
