<?php
require_once '../config.php';

session_start();

if (empty($_SESSION['USERDATA']['id'])) {
    die('Не авторизован');
}

$userId = $_SESSION['USERDATA']['id'];
$sprintId = (int)$_POST['sprint_id'];

$game_title = trim($_POST['game_title']);

if (!$game_title) {
    die('Название игры обязательно');
}

$db = (new Database())->connect();

$stmt = $db->prepare("
    INSERT INTO sprint_submissions
    (
        sprint_id,
        user_id,
        title,
        description
    )
    VALUES
    (
        ?, ?, ?, ?
    )
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        description = VALUES(description),
        updated_at = NOW()
");

$stmt->execute([
    $sprintId,
    $userId,
    $game_title,
    $_POST['description'] ?? ''
]);

header('Location: /jams/participant.php?sprint_id=' . $sprintId);
exit;