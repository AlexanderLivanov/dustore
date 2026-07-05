<?php
/**
 * media/api.php — единая точка API Dustore.Media
 *
 * POST action=create_post   — публикация поста/статьи (+постановка кросс-постов в outbox)
 * POST action=upload_image  — AJAX-загрузка картинки в S3 (до публикации)
 * POST action=toggle_like   — лайк/анлайк
 * POST action=delete_post   — tombstone-удаление своего поста
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') media_json(['error' => 'Method not allowed'], 405);

$uid = media_user_id();
if ($uid <= 0) media_json(['error' => 'Требуется авторизация'], 401);

$pdo    = media_pdo();
$action = $_POST['action'] ?? '';

switch ($action) {

/* ════════════════════════ СОЗДАНИЕ ПОСТА ═══════════════════════════ */
case 'create_post': {
    $type    = ($_POST['type'] ?? 'post') === 'article' ? 'article' : 'post';
    $title   = mb_substr(trim($_POST['title'] ?? ''), 0, 200);
    $bodyRaw = $_POST['body'] ?? '';
    $body    = media_sanitize_html($bodyRaw);

    if ($type === 'article' && $title === '') media_json(['error' => 'У статьи должен быть заголовок'], 422);
    if ($body === '' && empty($_POST['images']) && empty($_POST['video_url']))
        media_json(['error' => 'Пустой пост'], 422);

    // От лица студии? Проверяем право.
    $studioId = (int)($_POST['studio_id'] ?? 0) ?: null;
    if ($studioId !== null) {
        $allowed = array_column(media_user_studios($pdo, $uid), 'id');
        if (!in_array($studioId, array_map('intval', $allowed), true)) {
            media_json(['error' => 'Нет прав постить от этой студии'], 403);
        }
    }

    // Привязка к игре (девлог)
    $gameId = (int)($_POST['game_id'] ?? 0) ?: null;
    if ($gameId !== null && $studioId !== null) {
        $q = $pdo->prepare("SELECT 1 FROM games WHERE id = ? AND developer = ? LIMIT 1");
        $q->execute([$gameId, $studioId]);
        if (!$q->fetch()) $gameId = null;
    }

    // Вложения
    $attachments = [];

    // Картинки: массив URL, отданных upload_image; принимаем только наш S3
    $images = json_decode($_POST['images'] ?? '[]', true) ?: [];
    foreach (array_slice($images, 0, 10) as $img) {
        $path = is_array($img) ? ($img['path'] ?? '') : (string)$img;
        if (preg_match('~^https://s3\.regru\.cloud/~', $path)) {
            $attachments[] = ['kind' => 'image', 'path' => $path];
        }
    }

    // Видео
    if (!empty($_POST['video_url'])) {
        $v = media_video_embed((string)$_POST['video_url']);
        if ($v) $attachments[] = $v;
        else media_json(['error' => 'Видео: поддерживаются ссылки YouTube, RuTube и VK Video'], 422);
    }

    // Кросс-постинг
    $xTargets = [];
    if (!empty($_POST['crosspost_tg'])) $xTargets[] = 'telegram';
    if (!empty($_POST['crosspost_vk'])) $xTargets[] = 'vk';

    try {
        $pdo->beginTransaction();

        $code = media_short_code($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO media_posts
                (author_user_id, studio_id, game_id, type, title, body, attachments,
                 short_code, status, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())
        ");
        $stmt->execute([
            $uid, $studioId, $gameId, $type,
            $title !== '' ? $title : null,
            $body,
            $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            $code,
        ]);
        $postId = (int)$pdo->lastInsertId();

        // Outbox: кросс-посты встают в очередь АТОМАРНО с публикацией
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

    // Будим воркер уже ПОСЛЕ коммита — иначе он может проснуться раньше, чем строки видны
    if ($xTargets) media_kick_crosspost_worker();

    media_json([
        'success'    => true,
        'post_id'    => $postId,
        'short_code' => $code,
        'url'        => MEDIA_CANON_HOST . '/media/p/' . $code,
        'short_url'  => MEDIA_SHORT_HOST . '/' . $code,
    ]);
}

/* ════════════════════════ ЗАГРУЗКА КАРТИНКИ ════════════════════════ */
case 'upload_image': {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK)
        media_json(['error' => 'Файл не получен'], 422);

    $f = $_FILES['image'];
    if ($f['size'] > 8 * 1024 * 1024) media_json(['error' => 'Максимум 8 МБ'], 422);

    $mime = mime_content_type($f['tmp_name']);
    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime] ?? null;
    if (!$ext) media_json(['error' => 'Только JPG / PNG / WebP / GIF'], 422);

    $key = sprintf('media/%d/%s.%s', $uid, bin2hex(random_bytes(12)), $ext);

    try {
        // CONFIRM: сигнатура твоего S3Uploader (тот же, что в devs/edit.php для скриншотов)
        $s3  = new S3Uploader();
        $url = $s3->upload($f['tmp_name'], $key, $mime);
    } catch (Throwable $e) {
        error_log('media upload_image: ' . $e->getMessage());
        media_json(['error' => 'Ошибка загрузки в хранилище'], 500);
    }

    media_json(['success' => true, 'path' => $url]);
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
            // был лайк — сняли
            $pdo->prepare("UPDATE media_posts SET likes_count = GREATEST(likes_count,1) - 1 WHERE id = ?")
                ->execute([$postId]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO media_likes (post_id, user_id) VALUES (?, ?)")
                ->execute([$postId, $uid]);
            $pdo->prepare("UPDATE media_posts SET likes_count = likes_count + 1 WHERE id = ?")
                ->execute([$postId]);
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

/* ════════════════════════ УДАЛЕНИЕ (tombstone) ═════════════════════ */
case 'delete_post': {
    $postId = (int)($_POST['post_id'] ?? 0);

    $q = $pdo->prepare("SELECT author_user_id, studio_id FROM media_posts WHERE id = ? AND status != 'deleted' LIMIT 1");
    $q->execute([$postId]);
    $post = $q->fetch(PDO::FETCH_ASSOC);
    if (!$post) media_json(['error' => 'Пост не найден'], 404);

    $can = ((int)$post['author_user_id'] === $uid);
    if (!$can && $post['studio_id']) { // владелец/сотрудник студии может удалить пост студии
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
