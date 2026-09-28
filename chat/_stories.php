<?php
declare(strict_types=1);

/**
 * chat/_stories.php — «истории»: эфемерные фото/текстовые карточки на 24 часа.
 * ---------------------------------------------------------------------------
 * Видимость: друзья (таблица friends, status='accepted') + сам автор — тот же
 * круг, что уже используют другие части сайта (см. Fx::friendIds() в
 * swad/fx/core.php), продублировано здесь напрямую, чтобы chat/ не тянул
 * зависимость на swad/fx/*.
 *
 * Истечение — без крон-джобы: строки с expires_at <= NOW() подчищаются лениво,
 * при каждом обращении к ленте (chat_stories_cleanup), тем же приёмом, что и
 * остальной self-installing код в chat/. Фото при этом реально удаляется из S3
 * (chat_delete_key из _files.php) — иначе «эфемерность» была бы фикцией: строка
 * пропадает из БД, а сам файл годами лежит в бакете по прямой ссылке.
 *
 * Схема — тот же приём, что в chat/_blocks.php и chat/_reactions.php:
 * CREATE TABLE IF NOT EXISTS, положительный результат кэшируем в сессии.
 */

const CHAT_STORY_TTL         = 24 * 3600;
const CHAT_STORY_MAX_ACTIVE  = 20;             // активных историй на человека одновременно
const CHAT_STORY_TEXT_MAX    = 280;
const CHAT_STORY_PHOTO_MAX   = 8 * 1024 * 1024; // 8 МБ до перекодирования
const CHAT_STORY_BG = ['#c3217a', '#7d3ac1', '#1c8f6b', '#c17a1c', '#2d5fc1', '#c13030'];

function chat_stories_ensure(PDO $db): void
{
    static $done = false;
    if ($done) return;
    if (($_SESSION['chat_stories_schema'] ?? false) === true) { $done = true; return; }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS chat_stories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            kind VARCHAR(8) NOT NULL DEFAULT 'photo',
            media_url VARCHAR(500) NULL,
            media_key VARCHAR(255) NULL,
            bg VARCHAR(16) NULL,
            text VARCHAR(280) NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_user (user_id, expires_at),
            KEY ix_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS chat_story_views (
            story_id BIGINT UNSIGNED NOT NULL,
            viewer_id INT UNSIGNED NOT NULL,
            viewed_at DATETIME NOT NULL,
            PRIMARY KEY (story_id, viewer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $_SESSION['chat_stories_schema'] = true;
    $done = true;
}

/** Просроченные истории: чистим строки и реально удаляем фото из S3. */
function chat_stories_cleanup(PDO $db): void
{
    $st = $db->query("SELECT id, media_key FROM chat_stories WHERE expires_at <= NOW()");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!$rows) return;
    foreach ($rows as $r) if (!empty($r['media_key'])) chat_delete_key($r['media_key']);
    $db->exec("DELETE FROM chat_stories WHERE expires_at <= NOW()");
}

function chat_story_friend_ids(PDO $db, int $uid): array
{
    $st = $db->prepare("SELECT player_id, friend_id FROM friends WHERE status = 'accepted' AND (player_id = ? OR friend_id = ?)");
    $st->execute([$uid, $uid]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[] = (int)$r['player_id'] === $uid ? (int)$r['friend_id'] : (int)$r['player_id'];
    return array_values(array_unique($out));
}

function chat_story_insert(PDO $db, int $uid, string $kind, ?string $mediaUrl, ?string $mediaKey, ?string $bg, ?string $text): array
{
    $st = $db->prepare("SELECT COUNT(*) FROM chat_stories WHERE user_id = ? AND expires_at > NOW()");
    $st->execute([$uid]);
    if ((int)$st->fetchColumn() >= CHAT_STORY_MAX_ACTIVE) return ['ok' => false, 'error' => 'limit'];

    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d H:i:s', time() + CHAT_STORY_TTL);
    $db->prepare(
        "INSERT INTO chat_stories (user_id, kind, media_url, media_key, bg, text, created_at, expires_at) VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$uid, $kind, $mediaUrl, $mediaKey, $bg, $text, $now, $exp]);
    return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'expires_at' => $exp];
}

function chat_story_create_text(PDO $db, int $uid, string $text, string $bg): array
{
    $text = trim($text);
    if ($text === '') return ['ok' => false, 'error' => 'empty'];
    if (mb_strlen($text) > CHAT_STORY_TEXT_MAX) return ['ok' => false, 'error' => 'too_long'];
    if (!in_array($bg, CHAT_STORY_BG, true)) $bg = CHAT_STORY_BG[0];
    return chat_story_insert($db, $uid, 'text', null, null, $bg, $text);
}

function chat_story_create_photo(PDO $db, int $uid, array $upload, ?string $caption): array
{
    if (mb_strlen((string)$caption) > CHAT_STORY_TEXT_MAX) $caption = mb_substr((string)$caption, 0, CHAT_STORY_TEXT_MAX);
    $up = chat_story_handle_photo_upload($upload);
    if (!$up['ok']) return $up;
    return chat_story_insert($db, $uid, 'photo', $up['url'], $up['key'], null, $caption !== '' ? $caption : null);
}

/** Перекодирует загруженное фото через GD (снимает EXIF/полиглоты, ограничивает размер) и кладёт в S3 публично-читаемым. */
function chat_story_handle_photo_upload(array $f): array
{
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return ['ok' => false, 'error' => 'no_file'];
    if ((int)$f['size'] > CHAT_STORY_PHOTO_MAX) return ['ok' => false, 'error' => 'too_big'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return ['ok' => false, 'error' => 'bad_type'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || ($info['mime'] ?? '') !== $mime) return ['ok' => false, 'error' => 'bad_type'];

    $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$img) return ['ok' => false, 'error' => 'bad_type'];
    $w = imagesx($img); $h = imagesy($img);
    $max = 1600; // сторис смотрят на весь экран телефона — больше не нужно, только вес
    if ($w > $max || $h > $max) {
        $k = $max / max($w, $h);
        $res = imagescale($img, (int)round($w * $k), (int)round($h * $k));
        if ($res) { imagedestroy($img); $img = $res; $w = imagesx($img); $h = imagesy($img); }
    }
    // всегда отдаём JPEG: прозрачность фото-сторис не нужна, а один формат проще держать
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, (int)imagecolorallocate($flat, 20, 4, 29));
    imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'story');
    $ok = imagejpeg($flat, $tmp, 86);
    imagedestroy($flat);
    if (!$ok) { @unlink($tmp); return ['ok' => false, 'error' => 'storage']; }

    $key = chat_new_key('story.jpg');
    try {
        $res = chat_s3()->putObject([
            'Bucket' => chat_bucket(), 'Key' => $key, 'SourceFile' => $tmp,
            'ContentType' => 'image/jpeg', 'ACL' => 'public-read',
        ]);
        @unlink($tmp);
        return ['ok' => true, 'url' => (string)$res['ObjectURL'], 'key' => $key, 'w' => $w, 'h' => $h];
    } catch (Throwable $e) {
        @unlink($tmp);
        error_log('[chat_stories] s3 put: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'storage'];
    }
}

function chat_story_view(PDO $db, int $storyId, int $viewerId): void
{
    $db->prepare("INSERT IGNORE INTO chat_story_views (story_id, viewer_id, viewed_at) VALUES (?,?,NOW())")->execute([$storyId, $viewerId]);
}

/** Список просмотревших свою историю (для автора — «кто посмотрел»). */
function chat_story_viewers(PDO $db, int $storyId, int $ownerId): array
{
    $own = $db->prepare("SELECT user_id FROM chat_stories WHERE id = ?"); $own->execute([$storyId]);
    if ((int)$own->fetchColumn() !== $ownerId) return [];
    $st = $db->prepare("SELECT u.id, u.username, u.first_name, u.last_name, u.profile_picture
                         FROM chat_story_views v JOIN users u ON u.id = v.viewer_id
                         WHERE v.story_id = ? ORDER BY v.viewed_at DESC");
    $st->execute([$storyId]);
    return array_map('user_card_from_row', $st->fetchAll(PDO::FETCH_ASSOC));
}

function chat_story_delete(PDO $db, int $storyId, int $uid): bool
{
    $st = $db->prepare("SELECT media_key FROM chat_stories WHERE id = ? AND user_id = ?");
    $st->execute([$storyId, $uid]);
    $key = $st->fetchColumn();
    if ($key === false) return false;
    $db->prepare("DELETE FROM chat_stories WHERE id = ? AND user_id = ?")->execute([$storyId, $uid]);
    if ($key) chat_delete_key((string)$key);
    return true;
}

/**
 * Лента групп историй для зрителя: свои + друзья с активными историями.
 * Порядок: своя группа всегда первая, затем непросмотренные (по свежести),
 * затем уже просмотренные. Внутри группы истории — по возрастанию времени
 * (смотрят по порядку публикации, как везде).
 */
function chat_stories_feed(PDO $db, int $viewer): array
{
    chat_stories_cleanup($db);
    $ids = chat_story_friend_ids($db, $viewer);
    $ids[] = $viewer;
    $ids = array_values(array_unique($ids));
    if (!$ids) return [];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare(
        "SELECT s.*, (v.viewer_id IS NOT NULL) AS seen FROM chat_stories s
         LEFT JOIN chat_story_views v ON v.story_id = s.id AND v.viewer_id = ?
         WHERE s.user_id IN ($in) AND s.expires_at > NOW()
         ORDER BY s.user_id, s.created_at ASC"
    );
    $st->execute(array_merge([$viewer], $ids));

    $byUser = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $uid = (int)$r['user_id'];
        $byUser[$uid]['stories'][] = [
            'id' => (int)$r['id'], 'kind' => $r['kind'], 'media_url' => $r['media_url'],
            'bg' => $r['bg'], 'text' => $r['text'], 'created_at' => $r['created_at'],
            'seen' => (bool)$r['seen'], 'mine' => $uid === $viewer,
        ];
        $byUser[$uid]['has_unseen'] = ($byUser[$uid]['has_unseen'] ?? false) || !$r['seen'];
        $byUser[$uid]['last_at'] = $r['created_at'];
    }
    // своя ячейка — всегда в ленте, даже без активных историй: это точка входа
    // «добавить историю» (плюсик), как «Ваша история» в Instagram/Telegram.
    if (!isset($byUser[$viewer])) $byUser[$viewer] = ['stories' => [], 'has_unseen' => false, 'last_at' => ''];

    $users = [];
    $uin = implode(',', array_fill(0, count($byUser), '?'));
    $uq = $db->prepare("SELECT id, username, first_name, last_name, profile_picture FROM users WHERE id IN ($uin)");
    $uq->execute(array_keys($byUser));
    foreach ($uq->fetchAll(PDO::FETCH_ASSOC) as $r) $users[(int)$r['id']] = user_card_from_row($r);

    $groups = [];
    foreach ($byUser as $uid => $g) {
        if (!isset($users[$uid])) continue;
        $groups[] = [
            'user' => $users[$uid], 'is_me' => $uid === $viewer,
            'has_unseen' => $g['has_unseen'], 'last_at' => $g['last_at'], 'stories' => $g['stories'],
        ];
    }
    usort($groups, static function ($a, $b) {
        if ($a['is_me'] !== $b['is_me']) return $a['is_me'] ? -1 : 1;
        if ($a['has_unseen'] !== $b['has_unseen']) return $a['has_unseen'] ? -1 : 1;
        return strcmp($b['last_at'], $a['last_at']);
    });
    return $groups;
}
