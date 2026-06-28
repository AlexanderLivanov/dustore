<?php
require_once(__DIR__ . '/../../config.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode(['success'=>false,'message'=>'Не авторизованы']); exit; }
$uid = (int)$_SESSION['USERDATA']['id'];

$db = new Database(); $pdo = $db->connect();
$sprintId = (int)($_GET['sprint_id'] ?? 0);
if (!$sprintId) { echo json_encode(['success'=>false,'message'=>'Нет спринта']); exit; }

$inSprint = $pdo->prepare("SELECT 1 FROM sprint_participants WHERE sprint_id=? AND user_id=?");
$inSprint->execute([$sprintId, $uid]);
$resp = ['success'=>true, 'in_sprint'=>(bool)$inSprint->fetch(), 'has_team'=>false, 'team'=>null, 'members'=>[], 'pending_invites'=>[]];

$mt = $pdo->prepare("SELECT t.* FROM team_members tm JOIN sprint_teams t ON t.id=tm.team_id
                     WHERE tm.sprint_id=? AND tm.user_id=?");
$mt->execute([$sprintId, $uid]);
$team = $mt->fetch(PDO::FETCH_ASSOC);

if ($team) {
    $resp['has_team']   = true;
    $resp['team']       = $team;
    $resp['is_captain'] = ((int)$team['captain_id'] === $uid);

    $mem = $pdo->prepare("SELECT tm.user_id, tm.member_role, tm.joined_at, u.username, u.profile_picture
                          FROM team_members tm JOIN users u ON u.id=tm.user_id
                          WHERE tm.team_id=? ORDER BY tm.member_role='captain' DESC, tm.joined_at ASC");
    $mem->execute([$team['id']]);
    $resp['members'] = $mem->fetchAll(PDO::FETCH_ASSOC);
}

// входящие приглашения этому юзеру по спринту
$inv = $pdo->prepare("SELECT ti.id AS invite_id, ti.team_id, t.team_name, u.username AS inviter
                      FROM team_invites ti JOIN sprint_teams t ON t.id=ti.team_id
                      JOIN users u ON u.id=ti.inviter_id
                      WHERE ti.invitee_id=? AND ti.sprint_id=? AND ti.status='pending'
                      ORDER BY ti.created_at DESC");
$inv->execute([$uid, $sprintId]);
$resp['pending_invites'] = $inv->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($resp);