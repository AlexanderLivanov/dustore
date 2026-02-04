<?php

require_once('../../swad/config.php');

$db = new Database();

$token = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_DESKTOP_TOKEN'] ?? '';

$q = $db->connect()->prepare("
    SELECT u.id, u.username, u.telegram_username
    FROM desktop_tokens dt
    JOIN users u ON u.id = dt.user_id
    WHERE dt.token = ?
");

$q->execute([$token]);

$user = $q->fetch();

if ($user) {
    echo json_encode([
        "ok" => true,
        "user_id" => $user['id'],
        "username" => $user['username'],
        "tg_username" => $user['telegram_username']
    ]);
} else {
    echo json_encode(["ok" => false]);
}
