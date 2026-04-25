<?php
require_once('../swad/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_GET['passphrase'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No passphrase provided']);
    exit;
}

$pass = intval($_GET['passphrase']);

$db = new Database();
$pdo = $db->connect();

// Получаем данные юзера
$stmt = $pdo->prepare("SELECT * FROM users WHERE passphrase = :pass");
$stmt->execute(['passphrase' => password_hash($pass, PASSWORD_DEFAULT)]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($studio) {
    echo json_encode([
        'id' => $studio['id'],
        'name' => $studio['name'],
        'email' => $studio['contact_email']
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Studio not found']);
}
