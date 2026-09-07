<?php
declare(strict_types=1);

/**
 * l4t/api/getall.php — лента заявок для клиента.
 *
 * Было три проблемы:
 *   1. SELECT * — наружу уезжали все столбцы таблицы, включая bidder_id
 *      и всё, что появится в ней завтра. Публичный endpoint не должен
 *      зависеть от того, что кто-то добавил в таблицу колонку с контактами.
 *   2. Ни LIMIT, ни OFFSET. Один запрос выгружал всю таблицу целиком
 *      и сериализовал её в JSON_PRETTY_PRINT — с ростом биржи это
 *      мегабайты на каждый вызов.
 *   3. Ответ собирался под старую схему (title/description/author/comments),
 *      причём author и comments были закомментированы — значит схема уже
 *      уехала, а endpoint остался на прежней.
 *
 * Набор полей ниже взят из запросов в l4t/index.php — эти колонки заведомо
 * существуют, страница на них рендерится. См. заметку в ответе про то,
 * какие поля надо подтвердить по SHOW CREATE TABLE.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/db.php';

/* ── Параметры ──────────────────────────────────────────────────────────── */
$limit  = (int)($_GET['limit']  ?? 30);
$limit  = max(1, min(100, $limit));               // потолок, чтобы limit=999999 не прошёл
$offset = max(0, (int)($_GET['offset'] ?? 0));

$stage  = (string)($_GET['stage'] ?? 'active');
if (!in_array($stage, ['active', 'closed', 'draft', 'all'], true)) $stage = 'active';

$jamId  = (int)($_GET['jam_id'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));

/* ── Условия ────────────────────────────────────────────────────────────── */
$where  = [];
$params = [];

if ($stage !== 'all') { $where[] = 'stage = ?';  $params[] = $stage; }
if ($jamId > 0)       { $where[] = 'jam_id = ?'; $params[] = $jamId; }

if ($q !== '' && mb_strlen($q) >= 2) {
    // % и _ — метасимволы LIKE, без экранирования запрос «%» вернул бы всё
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(search_role LIKE ? OR search_spec LIKE ? OR goal LIKE ? OR details LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    /* ── Всего (для пагинации на клиенте) ───────────────────────────────── */
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM bids $whereSql");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    /* ── Страница ───────────────────────────────────────────────────────── */
    /* LIMIT/OFFSET подставляем целыми числами напрямую: они уже приведены
       к int и зажаты диапазоном выше. Биндить их плейсхолдерами при
       ATTR_EMULATE_PREPARES=false нельзя — MySQL не принимает строки в LIMIT. */
    $sql = "SELECT id, owner_type, bidder_id, jam_id, stage,
                   search_role, search_spec, experience, conditions, goal, details,
                   created_at
              FROM bids
              $whereSql
             ORDER BY created_at DESC, id DESC
             LIMIT $limit OFFSET $offset";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (PDOException $e) {
    error_log('[l4t/getall] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Ответ ──────────────────────────────────────────────────────────────── */
$items = array_map(static function (array $r): array {
    return [
        'id'         => (int)$r['id'],
        'owner_type' => $r['owner_type'],
        'jam_id'     => $r['jam_id'] !== null ? (int)$r['jam_id'] : null,
        'stage'      => $r['stage'],
        'role'       => $r['search_role'],
        'spec'       => $r['search_spec'],
        'experience' => $r['experience'],
        'conditions' => $r['conditions'],
        'goal'       => $r['goal'],
        // Детали режем: в ленте нужен превью-текст, а не полотно на 10 КБ
        'details'    => mb_substr((string)$r['details'], 0, 300),
        'date'       => $r['created_at'],
    ];
}, $rows);

echo json_encode([
    'ok'     => true,
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'items'  => $items,
], JSON_UNESCAPED_UNICODE);