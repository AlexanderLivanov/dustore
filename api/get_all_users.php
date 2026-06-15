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

try {
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo json_encode([]); 
        exit;
    }

    $result = [];
    foreach ($users as $user) {
        $displayName = !empty($user['username']) ? $user['username'] : $user['telegram_username'];
        $result[] = [
            'id'   => (int)$user['id'],
            'username' => $displayName
        ];
    }

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}