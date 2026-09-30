<?php
require_once('../swad/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Параметры запроса
$limit = max(1, min(100, isset($_GET['limit']) ? (int)$_GET['limit'] : 10));
$offset = max(0, isset($_GET['offset']) ? (int)$_GET['offset'] : 0);
// % и _ — метасимволы LIKE: без экранирования «%» отдавал бы весь каталог
$platform = isset($_GET['platform']) ? mb_substr((string)$_GET['platform'], 0, 32) : '';
$search = isset($_GET['search']) ? mb_substr((string)$_GET['search'], 0, 100) : '';

// Строим SQL динамически
$sql = "SELECT g.*, s.name AS developer_name
        FROM games g
        LEFT JOIN studios s ON g.developer = s.id
        WHERE g.status = 'published' AND (g.hidden IS NULL OR g.hidden = 0)";

$params = [];

// Фильтр по платформе
if ($platform !== '') {
    $sql .= " AND g.platforms LIKE :platform";
    $params[':platform'] = '%' . addcslashes($platform, '%_\\') . '%';
}

// Фильтр по имени игры
if ($search !== '') {
    $sql .= " AND g.name LIKE :search";
    $params[':search'] = '%' . addcslashes($search, '%_\\') . '%';
}

// LIMIT и OFFSET вставляем напрямую
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Прямая ссылка на файл — это и есть «купленная» часть платной игры, наружу её не отдаём
foreach ($games as &$g) { unset($g['game_zip_url']); }
unset($g);
echo json_encode($games, JSON_UNESCAPED_UNICODE);
