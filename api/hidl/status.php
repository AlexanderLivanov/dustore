<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../swad/config.php'; // подключение к БД

$db = new Database();
$pdo = $db->connect();

// Получение всех статусов
$stmt = $pdo->query("
    SELECT u.username, ua.current_app, ua.last_seen
    FROM users u
    LEFT JOIN user_activity ua ON ua.user_id = u.id
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($users as $u) {
    $last = strtotime($u['last_seen']);
    $status = (time() - $last > 30) ? "offline" : ($u['current_app'] ? "playing " . $u['current_app'] : "online");
    $result[] = [
        "username" => $u['username'],
        "status" => $status
    ];
}

echo json_encode($result);
