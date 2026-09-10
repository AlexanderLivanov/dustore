<?php
declare(strict_types=1);

/**
 * swad/controllers/explore_games.php — выдача витрины.
 *
 * Было: getLatestGames(99999) тянул ВСЮ таблицу в PHP, дальше фильтрация
 * через array_filter и сортировка через usort — в памяти, на каждый запрос.
 * Постраничность на клиенте это бы не вылечила: база и PHP всё равно
 * перемалывали бы весь каталог на каждую подгрузку, а отдавали бы кусок.
 *
 * Стало: фильтры, сортировка и LIMIT/OFFSET живут в SQL (Game::queryGames),
 * а этот файл — тонкая обёртка, которая приводит параметры к нужному виду
 * и раскладывает ответ.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/game.php');

const EXPLORE_PAGE = 48;

$gameController = new Game();

$adult  = !empty($_GET['adult']);
$offset = max(0, (int)($_GET['offset'] ?? 0));

$filters = [
    'genre'      => trim((string)($_GET['genre'] ?? '')) ?: null,
    'adult'      => $adult,
    'sort'       => (string)($_GET['sort'] ?? 'popularity'),
    'dir'        => (string)($_GET['dir'] ?? 'desc'),
    'price_type' => (string)($_GET['price_type'] ?? 'all'),
    'price_max'  => (int)($_GET['price_max'] ?? 5000),
    'q'          => trim((string)($_GET['q'] ?? '')),
    'limit'      => EXPLORE_PAGE,
    'offset'     => $offset,
];

/**
 * Скриншоты в games.screenshots лежат как JSON: [{"path":"..."}, ...].
 * Наружу отдаём плоский список путей — объекты клиенту не нужны.
 */
function shot_paths($raw, int $max = 5): array
{
    $arr = json_decode((string)$raw, true);
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $s) {
        $p = trim((string)(is_array($s) ? ($s['path'] ?? '') : $s));
        if ($p !== '') $out[] = $p;
        if (count($out) >= $max) break;
    }
    return $out;
}

try {
    $page = $gameController->queryGames($filters);
} catch (PDOException $e) {
    error_log('[explore_games] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['games' => [], 'genres' => [], 'error' => 'db'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = [];
foreach ($page['items'] as $game) {
    $desc = trim((string)($game['short_description'] ?? ''));
    if ($desc === '') $desc = trim(strip_tags((string)($game['description'] ?? '')));

    $result[] = [
        'id'            => (int)$game['id'],
        'name'          => $game['name'],
        'path_to_cover' => $game['path_to_cover'] ?? '',
        'price'         => (float)($game['price'] ?? 0),
        'downloads'     => (int)($game['downloads'] ?? 0),
        'release_date'  => $game['release_date'] ?? '',
        'updated_at'    => $game['updated_at'] ?? '',
        'age_rating'    => (int)($game['age_rating'] ?? 0),
        'genre'         => $game['genre'] ?? '',
        'studio_name'   => $game['studio_name'] ?? '',
        'description'   => mb_substr($desc, 0, 220),
        'screenshots'   => shot_paths($game['screenshots'] ?? ''),
        'avg_rating'    => $game['avg_rating'] !== null ? (float)$game['avg_rating'] : null,
        'reviews_count' => (int)($game['reviews_count'] ?? 0),
    ];
}

$shown = $offset + count($result);

echo json_encode([
    /* Эхо применённых фильтров. Нужно ровно для одного: открыть вкладку
       Network, посмотреть ответ и сразу увидеть, что сервер РЕАЛЬНО получил.
       Без этого приходится гадать, дошёл ли параметр до запроса. */
    'applied'  => [
        'q'          => $filters['q'],
        'genre'      => $filters['genre'],
        'adult'      => $adult ? 1 : 0,
        'sort'       => $filters['sort'],
        'dir'        => $filters['dir'],
        'price_type' => $filters['price_type'],
    ],
    'games'    => $result,
    /* Список жанров нужен только при первой загрузке страницы фильтра.
       На подгрузке следующей порции он не меняется — не гоняем лишний
       запрос и не раздуваем ответ. */
    'genres'   => $offset === 0 ? $gameController->collectGenres($adult) : null,
    'total'    => $page['total'],
    'offset'   => $offset,
    'shown'    => count($result),
    'has_more' => $shown < $page['total'],
], JSON_UNESCAPED_UNICODE);