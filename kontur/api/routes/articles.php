<?php
// ============================================================
// ROUTES/ARTICLES.PHP — статьи, оценки, модерация
// ============================================================

function handle_articles(string $method, array $parts): void
{
    $id     = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    $action = $parts[2] ?? null;

    // /articles/{id}/rate
    if ($id !== null && $action === 'rate' && $method === 'POST') {
        article_rate($id); return;
    }
    // /articles/{id}/moderate
    if ($id !== null && $action === 'moderate' && $method === 'POST') {
        article_moderate($id); return;
    }
    // /articles/{id}
    if ($id !== null && $action === null) {
        match ($method) {
            'GET'    => article_get($id),
            'PATCH'  => article_edit($id),
            'DELETE' => article_delete($id),
            default  => json_error('Метод не поддерживается', 405),
        };
        return;
    }
    // /articles
    if ($id === null) {
        match ($method) {
            'GET'  => articles_list(),
            'POST' => article_create(),
            default => json_error('Метод не поддерживается', 405),
        };
        return;
    }

    json_error('Маршрут не найден', 404);
}

// ── Список статей ────────────────────────────────────────
// GET /articles?status=approved&tag=ОБЪ
// Гость видит только approved. Модератор/админ — любой статус.
function articles_list(): void
{
    $user = current_user();
    $canSeeAll = $user && in_array($user['role'], ['moderator', 'admin'], true);

    $status = $_GET['status'] ?? 'approved';
    $tag    = $_GET['tag']    ?? null;

    // Гостю/юзеру нельзя запрашивать pending/rejected
    if (!$canSeeAll && $status !== 'approved') {
        json_error('Недостаточно прав для просмотра этого статуса', 403);
    }

    $sql = 'SELECT a.id, a.title, a.tag, a.tc, a.status, a.author_id,
                   u.username AS author,
                   DATE_FORMAT(a.created_at, "%d.%m.%Y") AS date,
                   COUNT(r.id)       AS ratings_count,
                   ROUND(AVG(r.rating), 1) AS avg_rating
            FROM articles a
            LEFT JOIN users u   ON u.id = a.author_id
            LEFT JOIN ratings r ON r.article_id = a.id
            WHERE a.status = ?';
    $params = [$status];

    if ($tag !== null) {
        $sql .= ' AND a.tag = ?';
        $params[] = $tag;
    }
    $sql .= ' GROUP BY a.id ORDER BY a.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Приводим типы (в SQL приходят строки)
    foreach ($rows as &$r) {
        $r['id']            = (int) $r['id'];
        $r['ratings_count'] = (int) $r['ratings_count'];
        $r['avg_rating']    = $r['avg_rating'] !== null ? (float) $r['avg_rating'] : 0;
    }

    json_response(['articles' => $rows]);
}

// ── Одна статья (с телом и моей оценкой) ─────────────────
function article_get(int $id): void
{
    $user = current_user();

    $stmt = db()->prepare(
        'SELECT a.id, a.title, a.tag, a.tc, a.body, a.status, a.author_id,
                u.username AS author,
                DATE_FORMAT(a.created_at, "%d.%m.%Y") AS date,
                COUNT(r.id) AS ratings_count,
                ROUND(AVG(r.rating), 1) AS avg_rating
         FROM articles a
         LEFT JOIN users u   ON u.id = a.author_id
         LEFT JOIN ratings r ON r.article_id = a.id
         WHERE a.id = ?
         GROUP BY a.id'
    );
    $stmt->execute([$id]);
    $article = $stmt->fetch();

    if (!$article) {
        json_error('Статья не найдена', 404);
    }

    // Гость/юзер не видит не-approved чужие статьи
    $canSeeAll = $user && in_array($user['role'], ['moderator', 'admin'], true);
    $isOwner   = $user && (int)$article['author_id'] === (int)$user['id'];
    if ($article['status'] !== 'approved' && !$canSeeAll && !$isOwner) {
        json_error('Статья не найдена', 404);
    }

    $article['id']            = (int) $article['id'];
    $article['ratings_count'] = (int) $article['ratings_count'];
    $article['avg_rating']    = $article['avg_rating'] !== null ? (float) $article['avg_rating'] : 0;

    // Моя оценка (если авторизован)
    $myRating = null;
    if ($user) {
        $rs = db()->prepare('SELECT rating FROM ratings WHERE article_id = ? AND user_id = ?');
        $rs->execute([$id, $user['id']]);
        $row = $rs->fetch();
        $myRating = $row ? (int) $row['rating'] : null;
    }
    $article['my_rating'] = $myRating;

    json_response(['article' => $article]);
}

// ── Создать статью (требует авторизации) ─────────────────
function article_create(): void
{
    $user = require_auth();
    $data = read_json_body();

    $title = require_field($data, 'title', 255);
    $body  = require_field($data, 'body', 65000);
    $tag   = require_field($data, 'tag', 8);

    $allowed = ['ОБЪ' => 'obj', 'СУЩ' => 'sus', 'ЭКСП' => 'exp', 'ИСТ' => 'his', 'ФАН' => 'fan'];
    if (!isset($allowed[$tag])) {
        json_error('Недопустимый тип материала', 422);
    }
    $tc = $allowed[$tag];

    // Все новые материалы — в статусе pending (на модерацию)
    $stmt = db()->prepare(
        'INSERT INTO articles (title, tag, tc, body, status, author_id)
         VALUES (?, ?, ?, ?, "pending", ?)'
    );
    $stmt->execute([$title, $tag, $tc, $body, $user['id']]);
    $newId = (int) db()->lastInsertId();

    // Увеличиваем нужный счётчик подач (для званий)
    $col = ($tag === 'ФАН') ? 'subs_fan' : 'subs_articles';
    db()->prepare("UPDATE users SET {$col} = {$col} + 1 WHERE id = ?")
        ->execute([$user['id']]);

    json_response(['id' => $newId, 'status' => 'pending'], 201);
}

// ── Оценить статью ───────────────────────────────────────
function article_rate(int $id): void
{
    $user = require_auth();
    $data = read_json_body();

    $rating = (int) ($data['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        json_error('Оценка должна быть от 1 до 5', 422);
    }

    // Только одобренные статьи можно оценивать
    $chk = db()->prepare('SELECT status FROM articles WHERE id = ?');
    $chk->execute([$id]);
    $a = $chk->fetch();
    if (!$a) json_error('Статья не найдена', 404);
    if ($a['status'] !== 'approved') json_error('Нельзя оценить неопубликованную статью', 403);

    // UPSERT: одна оценка на пользователя. При повторе — обновляем.
    $stmt = db()->prepare(
        'INSERT INTO ratings (article_id, user_id, rating)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating)'
    );
    $stmt->execute([$id, $user['id'], $rating]);

    // Возвращаем свежий средний балл
    $avg = db()->prepare(
        'SELECT COUNT(*) AS cnt, ROUND(AVG(rating),1) AS avg FROM ratings WHERE article_id = ?'
    );
    $avg->execute([$id]);
    $stats = $avg->fetch();

    json_response([
        'ok' => true,
        'my_rating'     => $rating,
        'avg_rating'    => (float) $stats['avg'],
        'ratings_count' => (int) $stats['cnt'],
    ]);
}

// ── Модерация: approve / reject ──────────────────────────
function article_moderate(int $id): void
{
    require_moderator();
    $data = read_json_body();
    $action = $data['action'] ?? '';

    $newStatus = match ($action) {
        'approve' => 'approved',
        'reject'  => 'rejected',
        default   => null,
    };
    if ($newStatus === null) {
        json_error('action должно быть approve или reject', 422);
    }

    $stmt = db()->prepare('UPDATE articles SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);

    if ($stmt->rowCount() === 0) {
        // Либо статья не найдена, либо статус уже был таким
        $chk = db()->prepare('SELECT id FROM articles WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) json_error('Статья не найдена', 404);
    }

    json_response(['ok' => true, 'status' => $newStatus]);
}

// ── Редактирование (модератор/админ) ─────────────────────
function article_edit(int $id): void
{
    require_moderator();
    $data = read_json_body();

    $fields = [];
    $params = [];
    if (isset($data['title'])) {
        $t = trim((string)$data['title']);
        if ($t === '' || mb_strlen($t) > 255) json_error('Некорректный заголовок', 422);
        $fields[] = 'title = ?'; $params[] = $t;
    }
    if (isset($data['body'])) {
        $b = trim((string)$data['body']);
        if ($b === '') json_error('Тело не может быть пустым', 422);
        $fields[] = 'body = ?'; $params[] = $b;
    }
    if (!$fields) json_error('Нечего обновлять', 422);

    $params[] = $id;
    $sql = 'UPDATE articles SET ' . implode(', ', $fields) . ' WHERE id = ?';
    db()->prepare($sql)->execute($params);

    json_response(['ok' => true]);
}

// ── Удаление (только админ) ──────────────────────────────
function article_delete(int $id): void
{
    require_admin();
    $stmt = db()->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        json_error('Статья не найдена', 404);
    }
    json_response(['ok' => true]);
}
