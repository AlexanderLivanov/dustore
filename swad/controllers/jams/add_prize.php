<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();
$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));
$data = json_decode(file_get_contents('php://input'), true);
$sprint_id = (int)($data['sprint_id']??0);
$db = (new Database())->connect();
$db->prepare("INSERT INTO sprint_prizes (sprint_id,place_num,reward) SELECT ?, COALESCE(MAX(place_num),0)+1, '' FROM sprint_prizes WHERE sprint_id=?")->execute([$sprint_id,$sprint_id]);
echo json_encode(['success'=>true]);