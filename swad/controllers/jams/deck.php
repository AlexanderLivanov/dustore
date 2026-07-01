<?php
/**
 * swad/controllers/jams/deck.php
 * Колода соло-участников спринта для капитана команды.
 *
 * GET ?sprint_id=N
 * Возвращает:
 *   { eligible:false, reason:'...' }                       — если не капитан
 *   { eligible:true, team_id, slots_left, deck:[ {..} ] }  — карты соло-участников
 *
 * "Соло" = НЕТ строки в team_members (participant_type ненадёжен).
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userId   = $_SESSION['USERDATA']['id'] ?? 0;
$sprintId = (int)($_GET['sprint_id'] ?? 0);

if (!$userId || !$sprintId) {
    echo json_encode(['eligible' => false, 'reason' => 'not_authenticated']);
    exit;
}

$db = (new Database())->connect();
if (!$db) { echo json_encode(['eligible' => false, 'reason' => 'db_error']); exit; }

/* 1. Капитан ли пользователь в этом спринте? */
$capStmt = $db->prepare("
    SELECT t.id AS team_id, t.team_limit
    FROM team_members tm
    JOIN sprint_teams t ON t.id = tm.team_id
    WHERE tm.sprint_id = ? AND tm.user_id = ? AND tm.member_role = 'captain'
    LIMIT 1
");
$capStmt->execute([$sprintId, $userId]);
$team = $capStmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    echo json_encode(['eligible' => false, 'reason' => 'not_captain']);
    exit;
}

$teamId    = (int)$team['team_id'];
$teamLimit = (int)$team['team_limit'];

/* 2. Сколько уже в команде → сколько слотов осталось */
$cntStmt = $db->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
$cntStmt->execute([$teamId]);
$current   = (int)$cntStmt->fetchColumn();
$slotsLeft = max(0, $teamLimit - $current);

/* 3. Соло-участники спринта: есть в sprint_participants, нет ни в одной команде этого спринта.
      Исключаем самого капитана. Случайный порядок. */
$deckStmt = $db->prepare("
    SELECT sp.user_id,
           sp.alias,
           sp.city,
           sp.extra_info,
           sp.links,
           u.username,
           u.telegram_username
    FROM sprint_participants sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.sprint_id = ?
      AND sp.user_id <> ?
      AND NOT EXISTS (
          SELECT 1 FROM team_members tm
          WHERE tm.sprint_id = sp.sprint_id AND tm.user_id = sp.user_id
      )
    ORDER BY RAND()
");
$deckStmt->execute([$sprintId, $userId]);
$deck = $deckStmt->fetchAll(PDO::FETCH_ASSOC);

/* Уже приглашённые этим капитаном (pending) — пометим, чтобы фронт мог не подсовывать повторно */
$invStmt = $db->prepare("
    SELECT invitee_id FROM team_invites
    WHERE team_id = ? AND status = 'pending'
");
$invStmt->execute([$teamId]);
$pending = array_map('intval', $invStmt->fetchAll(PDO::FETCH_COLUMN));

foreach ($deck as &$card) {
    $card['user_id']        = (int)$card['user_id'];
    $card['already_invited'] = in_array($card['user_id'], $pending, true);
    // handle для invite_member.php (зовёт по username или telegram_username)
    $card['handle'] = $card['username'] ?: $card['telegram_username'] ?: '';
}
unset($card);

echo json_encode([
    'eligible'   => true,
    'team_id'    => $teamId,
    'slots_left' => $slotsLeft,
    'deck'       => $deck,
]);