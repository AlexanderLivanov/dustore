<?php
require_once(__DIR__ . '/../../config.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode(['success'=>false,'message'=>'Не авторизованы']); exit; }
$uid = (int)$_SESSION['USERDATA']['id'];

$db = new Database(); $pdo = $db->connect();
$data     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprintId = (int)($data['sprint_id'] ?? 0);
if (!$sprintId) { echo json_encode(['success'=>false,'message'=>'Нет спринта']); exit; }

$m = $pdo->prepare("SELECT * FROM team_members WHERE sprint_id=? AND user_id=?");
$m->execute([$sprintId, $uid]);
$mem = $m->fetch(PDO::FETCH_ASSOC);
if (!$mem) { echo json_encode(['success'=>false,'message'=>'Вы не в команде']); exit; }
$teamId = (int)$mem['team_id'];

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$mem['id']]);

    $rest = $pdo->prepare("SELECT id,user_id FROM team_members WHERE team_id=? ORDER BY joined_at ASC LIMIT 1");
    $rest->execute([$teamId]);
    $next = $rest->fetch(PDO::FETCH_ASSOC);

    if (!$next) {
        $pdo->prepare("DELETE FROM sprint_teams WHERE id=?")->execute([$teamId]);
        $pdo->prepare("UPDATE team_invites SET status='cancelled', responded_at=NOW() WHERE team_id=? AND status='pending'")->execute([$teamId]);
    } elseif ($mem['member_role'] === 'captain') {
        $pdo->prepare("UPDATE team_members SET member_role='captain' WHERE id=?")->execute([$next['id']]);
        $pdo->prepare("UPDATE sprint_teams SET captain_id=? WHERE id=?")->execute([$next['user_id'], $teamId]);
    }
    $pdo->commit();
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('leave_team: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Ошибка выхода']);
}