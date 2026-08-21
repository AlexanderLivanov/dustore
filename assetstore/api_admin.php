<?php
/**
 * assetstore/api_admin.php
 * ---------------------------------------------------------------------------
 * Все мутации админки ассетов. Строго POST + JSON + CSRF.
 *
 * actions:
 *   transition  {id, to, note?}
 *   quick_edit  {id, price?, version?, tags?, dev_share?, featured?, moderator_note?}
 *   bulk        {ids[], op}          op: submit|publish|reject|hide|delete|restore
 *   log         {id}                 -> история изменений
 *   purge       {id}                 -> ТОЛЬКО root, необратимо
 */

declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function (Throwable $e) {
    if (ob_get_length()) ob_clean();
    error_log('[api_admin] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'where' => basename($e->getFile()) . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_acl.php';

function jout(array $d, int $code = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function jerr(string $msg, int $code = 400): void
{
    jout(['ok' => false, 'error' => $msg], $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('method_not_allowed', 405);

$db  = new Database();
$pdo = $db->connect();
if (!$pdo) jerr('db_unavailable', 500);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ctx = acl_ctx($pdo);
if (empty($ctx['uid'])) jerr('not_auth', 401);

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '', true);
if (!is_array($in)) $in = $_POST;

if (!acl_csrf_check($in['csrf'] ?? null)) jerr('bad_csrf', 403);

$action = (string)($in['action'] ?? '');

/* ── helpers ─────────────────────────────────────────────────────────────── */

function fetch_asset(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM assets WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    return $a ?: null;
}

/**
 * Единственное место, где меняется assets.status.
 * Возвращает ['ok'=>bool, 'error'=>?string]
 */
function do_transition(PDO $pdo, array $ctx, array $asset, string $to, ?string $note = null): array
{
    $from = (string)$asset['status'];
    if ($from === $to) return ['ok' => true, 'noop' => true];

    if (!in_array($to, acl_transitions($ctx, $asset), true)) {
        return ['ok' => false, 'error' => "переход {$from} → {$to} недоступен"];
    }
    // Отклонение без причины — это чёрный ящик для разработчика.
    if ($to === 'rejected' && trim((string)$note) === '') {
        return ['ok' => false, 'error' => 'при отклонении причина обязательна'];
    }

    $sets   = ['status = ?'];
    $params = [$to];

    if ($to === 'pending')   { $sets[] = 'submitted_at = NOW()'; }
    if ($to === 'published') { $sets[] = 'published_at = COALESCE(published_at, NOW())'; }
    if ($to === 'deleted')   { $sets[] = 'deleted_at = NOW()'; }
    if ($from === 'deleted') { $sets[] = 'deleted_at = NULL'; }
    if ($note !== null && $note !== '') { $sets[] = 'moderator_note = ?'; $params[] = $note; }

    $params[] = (int)$asset['id'];
    $sql = "UPDATE assets SET " . implode(', ', $sets) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);

    acl_log($pdo, $ctx, (int)$asset['id'], 'transition', $from, $to, $note);

    return ['ok' => true, 'from' => $from, 'to' => $to];
}

/* ── actions ─────────────────────────────────────────────────────────────── */

switch ($action) {

    /* ───────────────────────────────────────────────── */
    case 'transition': {
        $id = (int)($in['id'] ?? 0);
        $to = (string)($in['to'] ?? '');
        $note = isset($in['note']) ? trim((string)$in['note']) : null;

        $asset = fetch_asset($pdo, $id);
        if (!$asset) jerr('asset_not_found', 404);
        if (!acl_can($ctx, $asset, 'view')) jerr('forbidden', 403);

        $r = do_transition($pdo, $ctx, $asset, $to, $note);
        if (!$r['ok']) jerr($r['error'], 422);

        $meta = asset_status_meta($to);
        jout(['ok' => true, 'status' => $to, 'label' => $meta[0], 'color' => $meta[1]]);
    }

    /* ───────────────────────────────────────────────── */
    case 'quick_edit': {
        $id = (int)($in['id'] ?? 0);
        $asset = fetch_asset($pdo, $id);
        if (!$asset) jerr('asset_not_found', 404);
        if (!acl_can($ctx, $asset, 'edit')) jerr('forbidden', 403);

        // Только НЕматериальные поля — они не требуют перемодерации.
        $sets = [];
        $params = [];
        $diff = [];

        if (array_key_exists('price', $in)) {
            $v = max(0, round((float)$in['price'], 2));
            $sets[] = 'price = ?'; $params[] = $v;
            $diff['price'] = [$asset['price'], $v];
        }
        if (array_key_exists('version', $in)) {
            $v = mb_substr(trim((string)$in['version']), 0, 20);
            $sets[] = 'version = ?'; $params[] = $v;
            $diff['version'] = [$asset['version'], $v];
        }
        if (array_key_exists('tags', $in)) {
            $tags = array_slice(array_filter(array_map(
                static fn($t) => mb_substr(trim($t), 0, 30),
                explode(',', (string)$in['tags'])
            )), 0, 15);
            $v = implode(',', $tags);
            $sets[] = 'tags = ?'; $params[] = $v;
            $diff['tags'] = [$asset['tags'], $v];
        }
        if (array_key_exists('dev_share', $in)) {
            $v = max(10, min(90, (int)$in['dev_share']));
            $sets[] = 'dev_share = ?'; $params[] = $v;
            $diff['dev_share'] = [$asset['dev_share'], $v];
        }
        // Только staff:
        if (array_key_exists('featured', $in) && acl_can($ctx, $asset, 'feature')) {
            $v = !empty($in['featured']) ? 1 : 0;
            $sets[] = 'featured = ?'; $params[] = $v;
            $diff['featured'] = [$asset['featured'] ?? 0, $v];
        }
        if (array_key_exists('moderator_note', $in) && !empty($ctx['is_staff'])) {
            $v = mb_substr(trim((string)$in['moderator_note']), 0, 2000);
            $sets[] = 'moderator_note = ?'; $params[] = $v;
            $diff['moderator_note'] = ['…', $v];
        }

        if (!$sets) jerr('nothing_to_update', 422);

        $params[] = $id;
        $pdo->prepare("UPDATE assets SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        acl_log($pdo, $ctx, $id, 'quick_edit', null, null, null, $diff);

        jout(['ok' => true, 'changed' => array_keys($diff)]);
    }

    /* ───────────────────────────────────────────────── */
    case 'bulk': {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['ids'] ?? [])))));
        $op  = (string)($in['op'] ?? '');
        $note = isset($in['note']) ? trim((string)$in['note']) : null;
        if (!$ids) jerr('no_ids', 422);
        if (count($ids) > 200) jerr('too_many', 422);

        $map = [
            'submit'  => 'pending',
            'publish' => 'published',
            'reject'  => 'rejected',
            'hide'    => 'hidden',
            'delete'  => 'deleted',
            'restore' => 'draft',
        ];
        if (!isset($map[$op])) jerr('bad_op', 422);
        $to = $map[$op];

        $done = []; $failed = [];
        // Транзакция намеренно НЕ на всю пачку: одна невалидная строка
        // не должна откатывать 199 валидных. Каждая — атомарна сама по себе.
        foreach ($ids as $id) {
            $asset = fetch_asset($pdo, $id);
            if (!$asset || !acl_can($ctx, $asset, 'view')) { $failed[$id] = 'forbidden'; continue; }
            $r = do_transition($pdo, $ctx, $asset, $to, $note);
            if ($r['ok']) $done[] = $id; else $failed[$id] = $r['error'];
        }
        jout(['ok' => true, 'done' => $done, 'failed' => $failed, 'status' => $to]);
    }

    /* ───────────────────────────────────────────────── */
    case 'log': {
        $id = (int)($in['id'] ?? 0);
        $asset = fetch_asset($pdo, $id);
        if (!$asset) jerr('asset_not_found', 404);
        if (!acl_can($ctx, $asset, 'view_log')) jerr('forbidden', 403);

        $st = $pdo->prepare(
            "SELECT l.*, u.username, u.first_name
               FROM asset_moderation_log l
          LEFT JOIN users u ON u.id = l.actor_id
              WHERE l.asset_id = ?
           ORDER BY l.id DESC
              LIMIT 100"
        );
        $st->execute([$id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Разработчику не показываем IP и роль модератора.
        if (empty($ctx['is_staff'])) {
            foreach ($rows as &$r) { unset($r['ip'], $r['actor_role']); }
            unset($r);
        }
        jout(['ok' => true, 'rows' => $rows]);
    }

    /* ───────────────────────────────────────────────── */
    case 'purge': {
        if (empty($ctx['is_root'])) jerr('forbidden', 403);
        $id = (int)($in['id'] ?? 0);
        $asset = fetch_asset($pdo, $id);
        if (!$asset) jerr('asset_not_found', 404);
        if ($asset['status'] !== 'deleted') jerr('purge_requires_deleted_state', 422);

        // Лог пишем ДО удаления — иначе следов не останется вовсе.
        acl_log($pdo, $ctx, $id, 'purge', $asset['status'], null,
                (string)($in['note'] ?? ''), ['snapshot' => $asset]);

        $pdo->prepare("DELETE FROM assets WHERE id = ?")->execute([$id]);
        jout(['ok' => true, 'purged' => $id]);
    }
}

jerr('unknown_action', 400);