<?php
declare(strict_types=1);

/**
 * l4t/api/feed.php — догрузка и фильтрация ленты биржи.
 *
 * Этого файла не было: index.php уже звал /l4t/api/feed.php, получал 404,
 * r.json() падал — поиск, теги и «Показать ещё» не работали вообще.
 *
 * Отдаёт готовый HTML тем же партиалом _bid_card.php, что и первая страница.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../lib/feed.php';
require_once __DIR__ . '/../lib/icons.php';

try {
    $db   = new Database();
    $l4t  = $db->connect('desl4t');
    $main = $db->connect();

    $res = l4x_feed($l4t, [
        'q'      => (string)($_GET['q'] ?? ''),
        'tag'    => (string)($_GET['tag'] ?? ''),
        'kind'   => (string)($_GET['kind'] ?? ''),
        'offset' => (int)($_GET['offset'] ?? 0),
    ]);
    $authors = l4x_authors($main, array_column($res['rows'], 'bidder_id'));

    ob_start();
    foreach ($res['rows'] as $bid) require __DIR__ . '/../_bid_card.php';
    $html = ob_get_clean();

    $offset = max(0, (int)($_GET['offset'] ?? 0));
    echo json_encode([
        'ok'       => true,
        'html'     => $html,
        'shown'    => count($res['rows']),
        'total'    => $res['total'],
        'has_more' => $offset + count($res['rows']) < $res['total'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[l4t/feed] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
