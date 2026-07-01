<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();

$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success'=>false,'message'=>'Не авторизован']));

$data = json_decode(file_get_contents('php://input'), true);
$prize_id = (int)($data['prize_id'] ?? 0);
$reward = trim($data['reward'] ?? '');
$special_nomination = trim($data['special_nomination'] ?? '');
$issued_by = trim($data['issued_by'] ?? '');

if (!$prize_id) die(json_encode(['success'=>false,'message'=>'ID приза не указан']));

$db = (new Database())->connect();
$stmt = $db->prepare("UPDATE sprint_prizes SET reward=?, special_nomination=?, issued_by=? WHERE id=?");
$stmt->execute([$reward, $special_nomination, $issued_by, $prize_id]);

echo json_encode(['success'=>true]);
?>