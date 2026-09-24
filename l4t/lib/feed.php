<?php
declare(strict_types=1);

/**
 * l4t/lib/feed.php — выборка ленты биржи. Одна функция на index.php
 * (первая страница) и api/feed.php (подгрузка/поиск): фильтры не могут
 * разъехаться между сервером и «догрузкой».
 */

const L4X_FEED_PAGE = 20;

/** Есть ли колонка в desl4t.bids — миграции на проде могут быть не накатаны. */
function l4x_has_col(PDO $l4t, string $col): bool
{
    static $cols = null;
    if ($cols === null) {
        try { $cols = array_flip($l4t->query("SHOW COLUMNS FROM bids")->fetchAll(PDO::FETCH_COLUMN)); }
        catch (Throwable $e) { $cols = []; }
    }
    return isset($cols[$col]);
}

/**
 * @param array{q?:string, tag?:string, kind?:string, offset?:int} $f
 * @return array{rows: array, total: int}
 */
function l4x_feed(PDO $l4t, array $f): array
{
    $where  = ["stage = 'active'"];
    $params = [];
    // таймер: истёкшие снимает L4TMarket::sweep(), но между проходами не показываем их тоже
    if (l4x_has_col($l4t, 'expires_at')) $where[] = '(expires_at IS NULL OR expires_at > NOW())';

    $q = trim((string)($f['q'] ?? ''));
    if (mb_strlen($q) >= 2) {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(search_role LIKE ? OR search_spec LIKE ? OR conditions LIKE ? OR goal LIKE ? OR details LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $tag = trim((string)($f['tag'] ?? ''));
    if ($tag !== '') {
        $where[] = '(search_spec = ? OR experience = ? OR conditions = ?)';
        array_push($params, $tag, $tag, $tag);
    }

    $kind = (string)($f['kind'] ?? '');
    if ($kind === 'studio' || $kind === 'user') { $where[] = 'owner_type = ?'; $params[] = $kind; }
    if ($kind === 'jam') $where[] = 'jam_id IS NOT NULL AND jam_id > 0';

    $w = implode(' AND ', $where);
    $offset = max(0, (int)($f['offset'] ?? 0));

    $cnt = $l4t->prepare("SELECT COUNT(*) FROM bids WHERE $w");
    $cnt->execute($params);

    /* LIMIT/OFFSET — уже int, биндить нельзя (MySQL не принимает строки в LIMIT). */
    $st = $l4t->prepare("SELECT * FROM bids WHERE $w ORDER BY created_at DESC, id DESC
                          LIMIT " . L4X_FEED_PAGE . " OFFSET $offset");
    $st->execute($params);

    return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => (int)$cnt->fetchColumn()];
}

/** Теги фильтра — из ВСЕХ активных заявок, а не из отрисованной страницы. */
function l4x_feed_tags(PDO $l4t, int $limit = 24): array
{
    $tags = [];
    try {
        foreach (['search_spec', 'experience', 'conditions'] as $col) {
            $r = $l4t->query("SELECT $col v, COUNT(*) c FROM bids
                               WHERE stage = 'active' AND $col IS NOT NULL AND $col <> ''
                               GROUP BY $col");
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $v = trim((string)$row['v']);
                if ($v !== '') $tags[$v] = ($tags[$v] ?? 0) + (int)$row['c'];
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    arsort($tags);
    return array_slice(array_keys($tags), 0, $limit);
}

/** Авторы заявок одним запросом в основную базу. */
function l4x_authors(PDO $main, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    try {
        $st = $main->prepare("SELECT id, username, telegram_username, profile_picture FROM users WHERE id IN ($in)");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $handle = $u['username'] ?: $u['telegram_username'];
            $out[(int)$u['id']] = [
                'name'   => $u['username'] ?: ('@' . $u['telegram_username']),
                'avatar' => (string)($u['profile_picture'] ?? ''),
                'handle' => (string)$handle,
            ];
        }
    } catch (Throwable $e) { /* без авторов карточка всё равно рисуется */ }
    return $out;
}
