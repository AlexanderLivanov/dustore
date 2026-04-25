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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$platform = isset($_GET['platform']) ? $_GET['platform'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Строим SQL динамически
$sql = "SELECT g.*, s.name AS developer_name
        FROM games g
        LEFT JOIN studios s ON g.developer = s.id
        WHERE g.status = 'published'";

$params = [];

// Фильтр по платформе
if ($platform !== '') {
    $sql .= " AND g.platforms LIKE :platform";
    $params[':platform'] = "%$platform%";
}

// Фильтр по имени игры
if ($search !== '') {
    $sql .= " AND g.name LIKE :search";
    $params[':search'] = "%$search%";
}

// LIMIT и OFFSET вставляем напрямую
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($games, JSON_UNESCAPED_UNICODE);
