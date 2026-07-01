<?php
require_once(__DIR__ . '/../../config.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode(['success'=>false,'message'=>'Не авторизованы']); exit; }
$uid = (int)$_SESSION['USERDATA']['id'];

$db = new Database(); $pdo = $db->connect();
if (!$pdo) { echo json_encode(['success'=>false,'message'=>'БД недоступна']); exit; }

$data       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprintId   = (int)($data['sprint_id'] ?? 0);
$name       = mb_substr(trim($data['team_name'] ?? ''), 0, 120);
$desc       = mb_substr(trim($data['team_desc'] ?? ''), 0, 2000);
$visibility = ($data['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
$limit      = max(2, min(20, (int)($data['team_limit'] ?? 5)));

if (!$sprintId || $name === '') { echo json_encode(['success'=>false,'message'=>'Укажите спринт и название команды']); exit; }

$chk = $pdo->prepare("SELECT 1 FROM sprint_participants WHERE sprint_id=? AND user_id=?");
$chk->execute([$sprintId, $uid]);
if (!$chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Вы не зарегистрированы в этом спринте']); exit; }

$inTeam = $pdo->prepare("SELECT 1 FROM team_members WHERE sprint_id=? AND user_id=?");
$inTeam->execute([$sprintId, $uid]);
if ($inTeam->fetch()) { echo json_encode(['success'=>false,'message'=>'Вы уже состоите в команде на этот спринт']); exit; }

try {
    $pdo->beginTransaction();
    do {
        $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
        $c = $pdo->prepare("SELECT 1 FROM sprint_teams WHERE invite_code=?");
        $c->execute([$code]);
    } while ($c->fetch());

    $pdo->prepare("INSERT INTO sprint_teams (sprint_id,captain_id,team_name,team_desc,visibility,team_limit,invite_code,created_at)
                   VALUES (?,?,?,?,?,?,?,NOW())")
        ->execute([$sprintId,$uid,$name,$desc,$visibility,$limit,$code]);
    $teamId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO team_members (team_id,user_id,sprint_id,member_role,joined_at)
                   VALUES (?,?,?, 'captain', NOW())")
        ->execute([$teamId,$uid,$sprintId]);
    $pdo->commit();
    echo json_encode(['success'=>true,'team_id'=>$teamId,'invite_code'=>$code]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_team: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Ошибка создания команды']);
}