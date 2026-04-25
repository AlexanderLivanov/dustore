<?php
session_start();

require_once __DIR__ . '/swad/config.php';

$db  = new Database();
$pdo = $db->connect();

if (empty($_GET['token'])) {
    header('Location: /');
    exit;
}

$token = $_GET['token'];

$stmt = $pdo->prepare("
    SELECT id 
    FROM users 
    WHERE verification_token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET 
        verification_token = NULL,
        email_verified = 1
    WHERE id = ?
");
$stmt->execute([$user['id']]);

$_SESSION['PASSWORD_RECOVERY_USER'] = $user['id'];

header('Location: /');
exit;
