<?php
require_once(__DIR__ . '/../../config.php');
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) { echo json_encode([]); exit; }

$db = new Database(); $pdo = $db->connect();
$sprintId = (int)($_GET['sprint_id'] ?? 0);
$q        = trim($_GET['q'] ?? '');

if (!$sprintId || mb_strlen($q) < 4) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.telegram_username, u.profile_picture
    FROM sprint_participants sp
    JOIN users u ON u.id = sp.user_id
    WHERE sp.sprint_id = :sid
      AND (u.username LIKE :like OR u.telegram_username LIKE :like2)
      AND u.id NOT IN (SELECT user_id FROM team_members WHERE sprint_id = :sid2)
    ORDER BY (u.username LIKE :prefix OR u.telegram_username LIKE :prefix2) DESC, u.username ASC
    LIMIT 8
");
$stmt->execute([
    ':sid'     => $sprintId,
    ':sid2'    => $sprintId,
    ':like'    => '%' . $q . '%',
    ':like2'   => '%' . $q . '%',
    ':prefix'  => $q . '%',
    ':prefix2' => $q . '%',
]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));