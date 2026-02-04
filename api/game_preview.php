<?php
require_once('../swad/config.php');

$db = new Database();
$pdo = $db->connect();

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT path_to_cover FROM games WHERE id = ?");
$stmt->execute([$id]);

echo json_encode($stmt->fetch());
