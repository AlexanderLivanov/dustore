<?php
require '../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$token = $_GET['token'];
$data = json_decode(base64_decode($token), true);
$user_id = $data['id'];

$games = $pdo->query("
SELECT g.* FROM library l
JOIN games g ON g.id=l.game_id
WHERE l.user_id=$user_id
")->fetchAll();

echo json_encode($games);
