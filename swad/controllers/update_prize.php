<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();
$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));
$data = json_decode(file_get_contents('php://input'), true);
$prize_id = (int)($data['prize_id']??0);
$reward = $data['reward']??'';
$db = (new Database())->connect();
$db->prepare("UPDATE sprint_prizes SET reward=? WHERE id=?")->execute([$reward,$prize_id]);
echo json_encode(['success'=>true]);