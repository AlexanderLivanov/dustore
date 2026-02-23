<?php
session_start();
require_once('../../config.php');

$db = new Database();
$pdo = $db->connect("desl4t");
$data = $_POST;

if (empty($_SESSION['USERDATA']['id'])) {
    die("не авторизован, герой");
}
if (!empty($data['bid_id'])) {

    // UPDATE
    $stmt = $pdo->prepare("
        UPDATE bids SET
            search_role = ?,
            search_spec = ?,
            experience = ?,
            conditions = ?,
            goal = ?,
            details = ?
        WHERE id = ? AND owner_id = ?
    ");

    $stmt->execute([
        $data['role'],
        $data['spec'],
        $data['exp'],
        $data['cond'],
        $data['goal'],
        $data['details'],
        $data['bid_id'],
        $_SESSION['USERDATA']['id']
    ]);
} else {

    // CREATE
    $stmt = $pdo->prepare("
        INSERT INTO bids
        (bidder_id, owner_type, search_role, search_spec, experience, conditions, goal, details, created_at)
        VALUES (?,?,?,?,?,?,?, ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['USERDATA']['id'],
        $data['owner_type'],
        $data['role'],
        $data['spec'],
        $data['exp'],
        $data['cond'],
        $data['goal'],
        $data['details']
    ]);
}

echo ("<script>alert('Ваша заявка успешно создана!')</script>");
header("Location: /l4t");
