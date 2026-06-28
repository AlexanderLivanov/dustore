<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../NotificationCenter.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode(['success'=>false,'message'=>'Не авторизованы']); exit; }
$uid = (int)$_SESSION['USERDATA']['id'];

$db = new Database(); $pdo = $db->connect();
$data       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$teamId     = (int)($data['team_id'] ?? 0);
$inviteeRaw = trim($data['username'] ?? '');
$inviteeId  = (int)($data['user_id'] ?? 0);

if (!$teamId || (!$inviteeRaw && !$inviteeId)) { echo json_encode(['success'=>false,'message'=>'Нет данных']); exit; }

$t = $pdo->prepare("SELECT * FROM sprint_teams WHERE id=?");
$t->execute([$teamId]);
$team = $t->fetch(PDO::FETCH_ASSOC);
if (!$team) { echo json_encode(['success'=>false,'message'=>'Команда не найдена']); exit; }
if ((int)$team['captain_id'] !== $uid) { echo json_encode(['success'=>false,'message'=>'Приглашать может только капитан']); exit; }

if (!$inviteeId) {
    $h = ltrim($inviteeRaw, '@');
    $u = $pdo->prepare("SELECT id FROM users WHERE username = ? OR telegram_username = ? LIMIT 1");
    $u->execute([$h, $h]);
    $inviteeId = (int)($u->fetchColumn() ?: 0);
}
if (!$inviteeId)        { echo json_encode(['success'=>false,'message'=>'Пользователь не найден']); exit; }
if ($inviteeId === $uid){ echo json_encode(['success'=>false,'message'=>'Нельзя пригласить себя']); exit; }

$pc = $pdo->prepare("SELECT 1 FROM sprint_participants WHERE sprint_id=? AND user_id=?");
$pc->execute([$team['sprint_id'], $inviteeId]);
if (!$pc->fetch()) { echo json_encode(['success'=>false,'message'=>'Этот пользователь не участвует в спринте']); exit; }

$im = $pdo->prepare("SELECT 1 FROM team_members WHERE sprint_id=? AND user_id=?");
$im->execute([$team['sprint_id'], $inviteeId]);
if ($im->fetch()) { echo json_encode(['success'=>false,'message'=>'Пользователь уже в команде']); exit; }

$cnt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id=?");
$cnt->execute([$teamId]);
if ((int)$cnt->fetchColumn() >= (int)$team['team_limit']) { echo json_encode(['success'=>false,'message'=>'Команда заполнена']); exit; }

$pdo->prepare("INSERT INTO team_invites (team_id,sprint_id,inviter_id,invitee_id,status,created_at)
               VALUES (?,?,?,?, 'pending', NOW())
               ON DUPLICATE KEY UPDATE status='pending', inviter_id=VALUES(inviter_id), created_at=NOW(), responded_at=NULL")
    ->execute([$teamId, $team['sprint_id'], $uid, $inviteeId]);

try {
    $nc = new NotificationCenter();
    $nc->sendNotifications(
        [$inviteeId],
        'Приглашение в команду',
        'Вас зовут в команду «'.$team['team_name'].'» на спринт.',
        '/jams/participant.php?sprint_id='.$team['sprint_id'].'#invites'
    );
} catch (Throwable $e) { error_log('invite notify: '.$e->getMessage()); }

echo json_encode(['success'=>true,'message'=>'Приглашение отправлено','invitee_id'=>$inviteeId]);