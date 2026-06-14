<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();
$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));
$data = json_decode(file_get_contents('php://input'), true);
$sprint_id = (int)($data['sprint_id']??0);
$title = $data['title']??'';
$body = $data['body']??'';
if(!$title) die(json_encode(['success'=>false,'message'=>'Введите заголовок']));
$db = (new Database())->connect();
$db->prepare("INSERT INTO sprint_announcements (sprint_id,title,content,is_new,created_at) VALUES (?,?,?,1,NOW())")->execute([$sprint_id,$title,$body]);
echo json_encode(['success'=>true]);