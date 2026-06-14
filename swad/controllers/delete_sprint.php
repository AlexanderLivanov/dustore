<?php
require_once '../config.php';
header('Content-Type: application/json');
session_start();

$userId = $_SESSION['USERDATA']['id'] ?? 0;
if (!$userId) die(json_encode(['success' => false, 'message' => 'Не авторизован']));

$data = json_decode(file_get_contents('php://input'), true);
$sprint_id = (int)($data['sprint_id'] ?? 0);

$db = (new Database())->connect();
$check = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
$check->execute([$sprint_id]);
if ($check->fetchColumn() != $userId) die(json_encode(['success' => false, 'message' => 'Нет прав']));

// Удаление каскадно сработает благодаря FOREIGN KEY (если есть), иначе удаляем вручную
$db->beginTransaction();
try {
    $db->prepare("DELETE FROM sprint_participants WHERE sprint_id = ?")->execute([$sprint_id]);
    $db->prepare("DELETE FROM sprint_prizes WHERE sprint_id = ?")->execute([$sprint_id]);
    $db->prepare("DELETE FROM sprint_experts WHERE sprint_id = ?")->execute([$sprint_id]);
    $db->prepare("DELETE FROM sprint_announcements WHERE sprint_id = ?")->execute([$sprint_id]);
    $db->prepare("DELETE FROM sprint_submissions WHERE sprint_id = ?")->execute([$sprint_id]);
    $db->prepare("DELETE FROM sprints WHERE id = ?")->execute([$sprint_id]);
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}