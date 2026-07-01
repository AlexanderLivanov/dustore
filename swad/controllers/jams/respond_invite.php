<?php
require_once(__DIR__ . '/../../config.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode(['success'=>false,'message'=>'Не авторизованы']); exit; }
$uid = (int)$_SESSION['USERDATA']['id'];

$db = new Database(); $pdo = $db->connect();
$data     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$inviteId = (int)($data['invite_id'] ?? 0);
$action   = ($data['action'] ?? '') === 'accept' ? 'accept' : 'decline';
if (!$inviteId) { echo json_encode(['success'=>false,'message'=>'Нет приглашения']); exit; }

$i = $pdo->prepare("SELECT * FROM team_invites WHERE id=? AND invitee_id=? AND status='pending'");
$i->execute([$inviteId, $uid]);
$inv = $i->fetch(PDO::FETCH_ASSOC);
if (!$inv) { echo json_encode(['success'=>false,'message'=>'Приглашение не найдено или уже обработано']); exit; }

if ($action === 'decline') {
    $pdo->prepare("UPDATE team_invites SET status='declined', responded_at=NOW() WHERE id=?")->execute([$inviteId]);
    echo json_encode(['success'=>true,'status'=>'declined']); exit;
}

try {
    $pdo->beginTransaction();
    // блокируем строку участия, чтобы два одновременных accept не пробили лимит
    $im = $pdo->prepare("SELECT 1 FROM team_members WHERE sprint_id=? AND user_id=? FOR UPDATE");
    $im->execute([$inv['sprint_id'], $uid]);
    if ($im->fetch()) { $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>'Вы уже в команде на этот спринт']); exit; }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id=?");
    $cnt->execute([$inv['team_id']]);
    $lim = $pdo->prepare("SELECT team_limit FROM sprint_teams WHERE id=?");
    $lim->execute([$inv['team_id']]);
    if ((int)$cnt->fetchColumn() >= (int)$lim->fetchColumn()) {
        $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>'Команда уже заполнена']); exit;
    }

    $pdo->prepare("INSERT INTO team_members (team_id,user_id,sprint_id,member_role,joined_at)
                   VALUES (?,?,?, 'member', NOW())")
        ->execute([$inv['team_id'], $uid, $inv['sprint_id']]);
    $pdo->prepare("UPDATE team_invites SET status='accepted', responded_at=NOW() WHERE id=?")->execute([$inviteId]);
    // гасим остальные pending-инвайты этого юзера на этот спринт
    $pdo->prepare("UPDATE team_invites SET status='declined', responded_at=NOW()
                   WHERE invitee_id=? AND sprint_id=? AND status='pending' AND id<>?")
        ->execute([$uid, $inv['sprint_id'], $inviteId]);
    $pdo->commit();
    echo json_encode(['success'=>true,'status'=>'accepted','team_id'=>$inv['team_id']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('respond_invite: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Ошибка вступления']);
}