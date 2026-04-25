<?php
session_start();
require_once('../../config.php');

$db = new Database();
$pdo = $db->connect("desl4t");

$owner_type = $_POST['owner_type'];
$owner_id   = $_POST['owner_id'];
$role       = $_POST['role'];
$spec       = $_POST['spec'];
$exp        = $_POST['exp'];
$cond       = $_POST['cond'];
$goal       = $_POST['goal'];
$details    = $_POST['details'];


if (empty($_SESSION['USERDATA']['id'])) {
    die(json_encode(["success" => false, "msg" => "not auth"]));
}

$stmt = $pdo->prepare("
INSERT INTO bids
(bidder_id, owner_type, owner_id,
 search_role, search_spec,
 experience, conditions,
 goal, details, stage)

VALUES
(?, ?, ?,
 ?, ?,
 ?, ?,
 ?, ?, 'open')
");

$stmt->execute([
    $_SESSION['USERDATA']['id'],

    $owner_type,
    $owner_id,

    $role,
    $spec,

    $exp,
    $cond,

    $goal,
    $details
]);

echo json_encode([
    "success" => true,
    "id" => $pdo->lastInsertId()
]);

header("Location: /l4t?created=1");
exit;