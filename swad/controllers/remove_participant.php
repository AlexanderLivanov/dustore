<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();
$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));
$data = json_decode(file_get_contents('php://input'), true);
$sprint_id = (int)($data['sprint_id']??0);
$user_id = (int)($data['user_id']??0);
$db = (new Database())->connect();
$check = $db->prepare("SELECT host_user_id FROM sprints WHERE id=?");
$check->execute([$sprint_id]);
if($check->fetchColumn()!=$userId) die(json_encode(['success'=>false,'message'=>'Нет прав']));
$db->prepare("DELETE FROM sprint_participants WHERE sprint_id=? AND user_id=?")->execute([$sprint_id,$user_id]);
echo json_encode(['success'=>true]);