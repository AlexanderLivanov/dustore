<?php
declare(strict_types=1);

/**
 * l4t/api/feed.php — лента заявок: поиск, фильтр по тегу, подгрузка.
 *
 * Зачем понадобился. Раньше index.php рендерил ВСЕ активные заявки в разметку,
 * а поиск и теги прятали лишние карточки через card.style.display в JS.
 * Пока заявок десятки — терпимо; на тысяче страница становится неподъёмной,
 * и поставить LIMIT было нельзя: сервер отдал бы кусок, а фильтр по нему
 * отвечал бы «ничего не найдено» там, где совпадение есть на второй странице.
 *
 * Теперь фильтрует БД, а сюда приезжает готовый кусок разметки. Карточку
 * рисует общий партиал l4t/_bid_card.php — тот же, что и на первой странице,
 * поэтому дублировать десяток data-атрибутов в JS не нужно.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/db.php';

const FEED_PAGE = 20;

$q      = trim((string)($_GET['q'] ?? ''));
$tag    = trim((string)($_GET['tag'] ?? ''));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$jamId  = (int)($_GET['jam_id'] ?? 0);

$where  = ["stage = 'active'"];
$params = [];

if ($q !== '') {
    // % и _ — метасимволы LIKE; без экранирования запрос из одного «%»
    // вернул бы всю таблицу
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(search_role LIKE ? OR search_spec LIKE ? OR experience LIKE ?
                 OR conditions LIKE ? OR goal LIKE ? OR details LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

/* Тег — это точное значение одной из трёх колонок, ровно как их собирает
   список кнопок над лентой. */
if ($tag !== '') {
    $where[] = '(search_spec = ? OR experience = ? OR conditions = ?)';
    array_push($params, $tag, $tag, $tag);
}

if ($jamId > 0) { $where[] = 'jam_id = ?'; $params[] = $jamId; }

$whereSql = 'WHERE ' . implode(' AND ', $where);

try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM bids $whereSql");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    // LIMIT/OFFSET подставляем числами: они уже приведены к int и зажаты,
    // а при ATTR_EMULATE_PREPARES=false MySQL не принимает их строками
    $lim = FEED_PAGE;
    $st  = $pdo->prepare("SELECT * FROM bids $whereSql
                           ORDER BY created_at DESC, id DESC
                           LIMIT $lim OFFSET $offset");
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (PDOException $e) {
    error_log('[l4t/feed] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Собираем HTML тем же партиалом, что и index.php */
ob_start();
foreach ($rows as $bid) require __DIR__ . '/../_bid_card.php';
$html = ob_get_clean();

echo json_encode([
    'ok'       => true,
    'html'     => $html,
    'total'    => $total,
    'offset'   => $offset,
    'shown'    => count($rows),
    'has_more' => ($offset + count($rows)) < $total,
], JSON_UNESCAPED_UNICODE);
