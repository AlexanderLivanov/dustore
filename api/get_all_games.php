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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$platform = isset($_GET['platform']) ? $_GET['platform'] : 'Android';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM games 
        WHERE status='published'
        AND platforms LIKE :platform
        AND name LIKE :search
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':platform', "%$platform%");
$stmt->bindValue(':search', "%$search%");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($games);