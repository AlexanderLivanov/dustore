<?php
/**
 * media/api.php — единая точка API Dustore.Media (v2)
 *
 * POST action=create_post     — публикация (+outbox кросс-постов)
 * POST action=upload_image    — загрузка картинки-вложения (галерея)
 * POST action=sun_upload      — загрузка из SunEditor (paste/drag-drop), его формат ответа
 * POST action=toggle_like     — лайк/анлайк
 * POST action=add_comment     — комментарий
 * POST action=delete_comment  — tombstone-удаление комментария
 * POST action=delete_post     — tombstone-удаление поста
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../swad/controllers/s3.php';   // S3Uploader::uploadFile($tmp, $key) → url

if ($_SERVER['REQUEST_METHOD'] !== 'POST') media_json(['error' => 'Method not allowed'], 405);

$uid    = media_user_id();
$pdo    = media_pdo();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($uid <= 0) {
    // SunEditor ждёт свой формат ошибки
    if ($action === 'sun_upload') { echo json_encode(['errorMessage' => 'Требуется авторизация']); exit; }
    media_json(['error' => 'Требуется авторизация'], 401);
}

/* ── общий загрузчик картинки в S3: валидация → uploadFile → url ── */
function media_store_image(array $f, int $uid): array {
    if ($f['error'] !== UPLOAD_ERR_OK)      return [null, 'Файл не получен'];
    if ($f['size'] > 8 * 1024 * 1024)       return [null, 'Максимум 8 МБ'];
    $mime = mime_content_type($f['tmp_name']);
    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime] ?? null;
    if (!$ext)                              return [null, 'Только JPG / PNG / WebP / GIF'];

    try {
        $s3  = new S3Uploader();
        $url = $s3->uploadFile($f['tmp_name'], sprintf('media/%d/%s.%s', $uid, bin2hex(random_bytes(12)), $ext));
        if (!$url) return [null, 'Ошибка загрузки в хранилище'];
        return [$url, null];
    } catch (Throwable $e) {
        error_log('media_store_image: ' . $e->getMessage());
        return [null, 'Ошибка загрузки в хранилище'];
    }
}

switch ($action) {

/* ════════════════════════ СОЗДАНИЕ ПОСТА ═══════════════════════════ */
case 'create_post': {
    $type    = ($_POST['type'] ?? 'post') === 'article' ? 'article' : 'post';
    $title   = mb_substr(trim($_POST['title'] ?? ''), 0, 200);
    $body    = media_sanitize_html($_POST['body'] ?? '');
    if ($type === 'article' && $title === '') media_json(['error' => 'У статьи должен быть заголовок'], 422);
    if ($body === '' && empty($_POST['images']) && empty($_POST['video_url']))
        media_json(['error' => 'Пустой пост'], 422);

    $studioId = (int)($_POST['studio_id'] ?? 0) ?: null;
    if ($studioId !== null) {
        $allowed = array_map('intval', array_column(media_user_studios($pdo, $uid), 'id'));
        if (!in_array($studioId, $allowed, true)) media_json(['error' => 'Нет прав постить от этой студии'], 403);
    }

    $gameId = (int)($_POST['game_id'] ?? 0) ?: null;
    if ($gameId !== null && $studioId !== null) {
        $q = $pdo->prepare("SELECT 1 FROM games WHERE id = ? AND developer = ? LIMIT 1");
        $q->execute([$gameId, $studioId]);
        if (!$q->fetch()) $gameId = null;
    }

    $attachments = [];
    $images = json_decode($_POST['images'] ?? '[]', true) ?: [];
    foreach (array_slice($images, 0, 10) as $img) {
        $path = is_array($img) ? ($img['path'] ?? '') : (string)$img;
        if (media_is_our_asset($path)) {                    )
            $attachments[] = ['kind' => 'image', 'path' => $path];
        }
    }
    if (!empty($_POST['video_url'])) {
        $v = media_video_embed((string)$_POST['video_url']);
        if ($v) $attachments[] = $v;
        else media_json(['error' => 'Видео: поддерживаются ссылки YouTube, RuTube и VK Video'], 422);
    }

    $xTargets = [];
    if (!empty($_POST['crosspost_tg'])) $xTargets[] = 'telegram';
    if (!empty($_POST['crosspost_vk'])) $xTargets[] = 'vk';

    try {
        $pdo->beginTransaction();
        $code = media_short_code($pdo);
        $pdo->prepare("
            INSERT INTO media_posts
                (author_user_id, studio_id, game_id, type, title, body, attachments,
                 short_code, status, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())
        ")->execute([
            $uid, $studioId, $gameId, $type,
            $title !== '' ? $title : null,
            $body,
            $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            $code,
        ]);
        $postId = (int)$pdo->lastInsertId();

        if ($xTargets) {
            $ins = $pdo->prepare("INSERT IGNORE INTO media_crossposts (post_id, target) VALUES (?, ?)");
            foreach ($xTargets as $t) $ins->execute([$postId, $t]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('media create_post: ' . $e->getMessage());
        media_json(['error' => 'Ошибка публикации'], 500);
    }

    if ($xTargets) media_kick_crosspost_worker();

    media_json(['success' => true, 'post_id' => $postId, 'short_code' => $code,
                'url' => media_post_url($code)]);
}

/* ════════════════════════ ЗАГРУЗКИ КАРТИНОК ════════════════════════ */
case 'upload_image': {   // вложения-галерея
    if (empty($_FILES['image'])) media_json(['error' => 'Файл не получен'], 422);
    [$url, $err] = media_store_image($_FILES['image'], $uid);
    if ($err) media_json(['error' => $err], 422);
    media_json(['success' => true, 'path' => $url]);
}

case 'sun_upload': {     // SunEditor: paste из буфера / drag-drop прямо в текст
    header('Content-Type: application/json; charset=utf-8');
    $f = $_FILES['file-0'] ?? (is_array($_FILES) ? reset($_FILES) : null);
    if (!$f) { echo json_encode(['errorMessage' => 'Файл не получен']); exit; }
    [$url, $err] = media_store_image($f, $uid);
    if ($err) { echo json_encode(['errorMessage' => $err]); exit; }
    // Формат ответа, который SunEditor понимает нативно
    echo json_encode(['result' => [[
        'url'  => $url,
        'name' => $f['name'] ?? 'image',
        'size' => (int)($f['size'] ?? 0),
    ]]]);
    exit;
}

/* ════════════════════════ ЛАЙК / АНЛАЙК ════════════════════════════ */
case 'toggle_like': {
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0) media_json(['error' => 'post_id?'], 422);

    $q = $pdo->prepare("SELECT id FROM media_posts WHERE id = ? AND status = 'published' LIMIT 1");
    $q->execute([$postId]);
    if (!$q->fetch()) media_json(['error' => 'Пост не найден'], 404);

    try {
        $pdo->beginTransaction();
        $del = $pdo->prepare("DELETE FROM media_likes WHERE post_id = ? AND user_id = ?");
        $del->execute([$postId, $uid]);
        if ($del->rowCount() > 0) {
            $pdo->prepare("UPDATE media_posts SET likes_count = GREATEST(likes_count,1) - 1 WHERE id = ?")->execute([$postId]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO media_likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $uid]);
            $pdo->prepare("UPDATE media_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$postId]);
            $liked = true;
        }
        $cnt = $pdo->prepare("SELECT likes_count FROM media_posts WHERE id = ?");
        $cnt->execute([$postId]);
        $likes = (int)$cnt->fetchColumn();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('media toggle_like: ' . $e->getMessage());
        media_json(['error' => 'Ошибка'], 500);
    }
    media_json(['success' => true, 'liked' => $liked, 'likes' => $likes]);
}

/* ════════════════════════ КОММЕНТАРИИ ══════════════════════════════ */
case 'add_comment': {
    $postId = (int)($_POST['post_id'] ?? 0);
    $body   = trim($_POST['body'] ?? '');
    if ($postId <= 0)                 media_json(['error' => 'post_id?'], 422);
    if ($body === '')                 media_json(['error' => 'Пустой комментарий'], 422);
    if (mb_strlen($body) > 2000)      media_json(['error' => 'Максимум 2000 символов'], 422);

    $q = $pdo->prepare("SELECT id FROM media_posts WHERE id = ? AND status = 'published' LIMIT 1");
    $q->execute([$postId]);
    if (!$q->fetch()) media_json(['error' => 'Пост не найден'], 404);

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO media_comments (post_id, user_id, body) VALUES (?, ?, ?)")
            ->execute([$postId, $uid, $body]);
        $cid = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE media_posts SET comments_count = comments_count + 1 WHERE id = ?")->execute([$postId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('media add_comment: ' . $e->getMessage());
        media_json(['error' => 'Ошибка'], 500);
    }

    $u = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $u->execute([$uid]);
    $me = $u->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'Вы', 'profile_picture' => null];

    media_json(['success' => true, 'comment' => [
        'id'         => $cid,
        'body'       => $body,
        'username'   => $me['username'],
        'avatar'     => $me['profile_picture'],
        'created_at' => date('Y-m-d H:i:s'),
        'can_delete' => true,
    ]]);
}

case 'delete_comment': {
    $cid = (int)($_POST['comment_id'] ?? 0);
    $q = $pdo->prepare("
        SELECT c.id, c.user_id, c.post_id, p.author_user_id, p.studio_id
        FROM media_comments c JOIN media_posts p ON p.id = c.post_id
        WHERE c.id = ? AND c.status = 'published' LIMIT 1
    ");
    $q->execute([$cid]);
    $c = $q->fetch(PDO::FETCH_ASSOC);
    if (!$c) media_json(['error' => 'Комментарий не найден'], 404);

    // Право: автор комментария или владелец поста (включая студийные посты)
    $can = ((int)$c['user_id'] === $uid) || ((int)$c['author_user_id'] === $uid);
    if (!$can && $c['studio_id']) {
        $ids = array_map('intval', array_column(media_user_studios($pdo, $uid), 'id'));
        $can = in_array((int)$c['studio_id'], $ids, true);
    }
    if (!$can) media_json(['error' => 'Нет прав'], 403);

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE media_comments SET status = 'deleted' WHERE id = ?")->execute([$cid]);
        $pdo->prepare("UPDATE media_posts SET comments_count = GREATEST(comments_count,1) - 1 WHERE id = ?")
            ->execute([(int)$c['post_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        media_json(['error' => 'Ошибка'], 500);
    }
    media_json(['success' => true]);
}

/* ════════════════════════ УДАЛЕНИЕ ПОСТА ═══════════════════════════ */
case 'delete_post': {
    $postId = (int)($_POST['post_id'] ?? 0);
    $q = $pdo->prepare("SELECT author_user_id, studio_id FROM media_posts WHERE id = ? AND status != 'deleted' LIMIT 1");
    $q->execute([$postId]);
    $post = $q->fetch(PDO::FETCH_ASSOC);
    if (!$post) media_json(['error' => 'Пост не найден'], 404);

    $can = ((int)$post['author_user_id'] === $uid);
    if (!$can && $post['studio_id']) {
        $ids = array_map('intval', array_column(media_user_studios($pdo, $uid), 'id'));
        $can = in_array((int)$post['studio_id'], $ids, true);
    }
    if (!$can) media_json(['error' => 'Нет прав'], 403);

    $pdo->prepare("UPDATE media_posts SET status = 'deleted' WHERE id = ?")->execute([$postId]);
    media_json(['success' => true]);
}

default:
    media_json(['error' => 'Unknown action'], 400);
}