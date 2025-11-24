<?php
session_start();
require_once('../config.php');
require_once('user.php');

if (empty($_SESSION['USERDATA']['id'])) {
    $game_id = (int)($_GET['game_id'] ?? 0);
    $user_id = $_COOKIE['temp_id'];
    downloadGame($game_id, $user_id);
}else{
    $game_id = (int)($_GET['game_id'] ?? 0);
    $user_id = $_SESSION['USERDATA']['id'];
    downloadGame($game_id, $user_id);
}

function downloadGame($game_id, $user_id){
    $db = new Database();
    $pdo = $db->connect();

    if ($game_id <= 0) {
        header('Location: /explore');
        exit();
    }

    $curr_user = new User();
    $curr_user->updateUserItems($user_id, $game_id);

    $stmt = $pdo->prepare("SELECT game_zip_url FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($game && !empty($game['game_zip_url'])) {
        header("Location: " . $game['game_zip_url']);
        exit();
    }

    header("Location: /g/$game_id");
    exit();
}

