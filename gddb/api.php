<?php
require_once __DIR__ . '/lib/core.php';

$action = $_REQUEST['action'] ?? '';
$in = [];
if (($_SERVER['CONTENT_TYPE'] ?? '') && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
}
$in += $_POST + $_GET;

function need(array $in, string $k)
{
    if (!isset($in[$k]) || $in[$k] === '') json_out(['error' => "нет параметра $k"], 400);
    return $in[$k];
}

/** Ревизия пишется не чаще раза в 10 минут — иначе история превратится в шум. */
function maybe_snapshot(array $old, string $newBody, string $note = ''): bool
{
    if ($old['body_md'] === $newBody && $note === '') return false;
    if ($note === '') {
        $st = db()->prepare(
            'SELECT created_at FROM revisions WHERE article_id = ?
             ORDER BY created_at DESC LIMIT 1'
        );
        $st->execute([$old['id']]);
        $last = $st->fetch();
        if ($last && strtotime($last['created_at']) > time() - 600) return false;
    }
    db()->prepare('INSERT INTO revisions (article_id, title, body_md, note) VALUES (?, ?, ?, ?)')
        ->execute([$old['id'], $old['title'], $old['body_md'], $note]);
    return true;
}

function tag_ids(array $names): array
{
    $ids = [];
    foreach ($names as $name) {
        $name = trim(ltrim((string)$name, '#'));
        if ($name === '') continue;
        $slug = slugify($name);
        $st = db()->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        if ($row = $st->fetch()) { $ids[] = (int)$row['id']; continue; }
        db()->prepare('INSERT INTO tags (slug, title) VALUES (?, ?)')->execute([$slug, $name]);
        $ids[] = (int)db()->lastInsertId();
    }
    return array_values(array_unique($ids));
}

function links_payload(int $id): array
{
    return ['out' => article_links_out($id), 'in' => article_links_in($id)];
}

try {
    switch ($action) {

        case 'create': {
            $title = trim((string)need($in, 'title'));
            $sec = isset($in['section_id']) && $in['section_id'] !== '' ? (int)$in['section_id'] : null;
            $slug = unique_slug($title);
            db()->prepare(
                "INSERT INTO articles (slug, title, section_id, body_md, status)
                 VALUES (?, ?, ?, ?, 'draft')"
            )->execute([$slug, $title, $sec, NOTE_TEMPLATE]);
            json_out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'slug' => $slug]);
        }

        case 'save': {
            $id = (int)need($in, 'id');
            $st = db()->prepare('SELECT * FROM articles WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch();
            if (!$old) json_out(['error' => 'заметка не найдена'], 404);

            $title = trim((string)($in['title'] ?? $old['title']));
            if ($title === '') $title = $old['title'];
            $body = (string)($in['body_md'] ?? $old['body_md']);
            $summary = mb_substr(trim((string)($in['summary'] ?? $old['summary'])), 0, 400, 'UTF-8');
            $status = $in['status'] ?? $old['status'];
            if (!isset(STATUS_LABELS[$status])) $status = $old['status'];
            // Заготовку, которую начали писать, нет смысла держать заготовкой.
            if ($status === 'stub' && trim($body) !== trim(NOTE_TEMPLATE) && trim($body) !== '') $status = 'draft';
            $conf = (int)($in['confidence'] ?? $old['confidence']);
            if ($conf < 0 || $conf > 3) $conf = (int)$old['confidence'];
            $sec = array_key_exists('section_id', $in)
                ? ($in['section_id'] === '' || $in['section_id'] === null ? null : (int)$in['section_id'])
                : ($old['section_id'] === null ? null : (int)$old['section_id']);

            $snap = maybe_snapshot($old, $body, (string)($in['snapshot_note'] ?? ''));

            $slug = $title !== $old['title'] ? unique_slug($title, $id) : $old['slug'];

            db()->prepare(
                'UPDATE articles SET title = ?, slug = ?, summary = ?, body_md = ?,
                 status = ?, confidence = ?, section_id = ? WHERE id = ?'
            )->execute([$title, $slug, $summary, $body, $status, $conf, $sec, $id]);

            if (array_key_exists('tags', $in)) {
                $names = is_array($in['tags']) ? $in['tags'] : explode(',', (string)$in['tags']);
                $ids = tag_ids($names);
                db()->prepare('DELETE FROM article_tags WHERE article_id = ?')->execute([$id]);
                foreach ($ids as $tid) {
                    db()->prepare('INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)')
                        ->execute([$id, $tid]);
                }
            }

            $created = sync_wiki_links($id, $body);

            json_out([
                'ok' => true, 'slug' => $slug, 'status' => $status,
                'snapshot' => $snap, 'created_stubs' => $created,
                'links' => links_payload($id),
                'tags' => array_column(article_tags($id), 'title'),
                'saved_at' => date('H:i:s'),
            ]);
        }

        case 'preview': {
            $body = (string)($in['body_md'] ?? '');
            json_out(['html' => md_render($body, wiki_linkmap($body)), 'outline' => md_outline($body)]);
        }

        case 'link_add': {
            $from = (int)need($in, 'from');
            $type = (string)need($in, 'type');
            if (!isset(LINK_LABELS[$type])) json_out(['error' => 'неизвестный тип связи'], 400);
            $to = (int)need($in, 'to');
            if ($to === $from) json_out(['error' => 'заметка не может ссылаться на себя'], 400);
            db()->prepare('DELETE FROM article_links WHERE from_id = ? AND to_id = ? AND auto = 1')
                ->execute([$from, $to]);
            db()->prepare('INSERT IGNORE INTO article_links (from_id, to_id, type, auto) VALUES (?, ?, ?, 0)')
                ->execute([$from, $to, $type]);
            json_out(['ok' => true, 'links' => links_payload($from)]);
        }

        case 'link_del': {
            $from = (int)need($in, 'from');
            db()->prepare('DELETE FROM article_links WHERE from_id = ? AND to_id = ? AND type = ?')
                ->execute([$from, (int)need($in, 'to'), (string)need($in, 'type')]);
            json_out(['ok' => true, 'links' => links_payload($from)]);
        }

        case 'search': {
            $q = trim((string)($in['q'] ?? ''));
            $not = (int)($in['not'] ?? 0);
            $st = db()->prepare(
                'SELECT id, slug, title, status FROM articles
                 WHERE (title LIKE ? OR slug LIKE ?) AND id <> ?
                 ORDER BY (title = ?) DESC, title LIMIT 12'
            );
            $st->execute(["%$q%", "%$q%", $not, $q]);
            json_out(['items' => $st->fetchAll()]);
        }

        case 'question_add': {
            $id = (int)need($in, 'article_id');
            $text = mb_substr(trim((string)need($in, 'text')), 0, 500, 'UTF-8');
            db()->prepare('INSERT INTO questions (article_id, text) VALUES (?, ?)')->execute([$id, $text]);
            json_out(['ok' => true, 'questions' => article_questions($id)]);
        }

        case 'question_toggle': {
            $qid = (int)need($in, 'id');
            $st = db()->prepare('SELECT article_id, status FROM questions WHERE id = ?');
            $st->execute([$qid]);
            $q = $st->fetch();
            if (!$q) json_out(['error' => 'вопрос не найден'], 404);
            $new = $q['status'] === 'open' ? 'answered' : 'open';
            db()->prepare('UPDATE questions SET status = ? WHERE id = ?')->execute([$new, $qid]);
            json_out(['ok' => true, 'questions' => article_questions((int)$q['article_id'])]);
        }

        case 'question_del': {
            $qid = (int)need($in, 'id');
            $st = db()->prepare('SELECT article_id FROM questions WHERE id = ?');
            $st->execute([$qid]);
            $q = $st->fetch();
            if (!$q) json_out(['error' => 'вопрос не найден'], 404);
            db()->prepare('DELETE FROM questions WHERE id = ?')->execute([$qid]);
            json_out(['ok' => true, 'questions' => article_questions((int)$q['article_id'])]);
        }

        case 'revisions': {
            $id = (int)need($in, 'id');
            $st = db()->prepare(
                'SELECT id, title, note, created_at, CHAR_LENGTH(body_md) AS len
                 FROM revisions WHERE article_id = ? ORDER BY created_at DESC LIMIT 50'
            );
            $st->execute([$id]);
            json_out(['items' => $st->fetchAll()]);
        }

        case 'revision_get': {
            $rid = (int)need($in, 'id');
            $st = db()->prepare('SELECT * FROM revisions WHERE id = ?');
            $st->execute([$rid]);
            json_out(['item' => $st->fetch() ?: null]);
        }

        case 'delete': {
            $id = (int)need($in, 'id');
            db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
            json_out(['ok' => true]);
        }

        default:
            json_out(['error' => 'неизвестное действие'], 400);
    }
} catch (Throwable $ex) {
    json_out(['error' => $ex->getMessage()], 500);
}
