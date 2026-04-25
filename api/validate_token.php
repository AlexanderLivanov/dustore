<?php
require_once('../swad/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token_hash'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No token_hash provided']);
    exit;
}

$token_hash = trim($input['token_hash']);

$db = new Database();
$pdo = $db->connect();

// Получаем все студии с их токенами (в api_token хранится ХЕШ)
$stmt = $pdo->prepare("SELECT id, name, api_token FROM studios");
$stmt->execute();
$studios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Сравниваем хеши напрямую (api_token уже хеш)
foreach ($studios as $studio) {
    if ($studio['api_token'] === $token_hash) {
        echo json_encode([
            'status' => 'ok',
            'studio_id' => $studio['id'],
            'studio_name' => $studio['name']
        ]);
        exit;
    }
}

http_response_code(401);
echo json_encode(['error' => 'Invalid token']);
