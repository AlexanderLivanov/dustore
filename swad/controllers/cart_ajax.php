<?php
require_once('../config.php');
require_once 'user.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();

    $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : null;
    $method = isset($_POST['method']) ? $_POST['method'] : null;

    if ($game_id && $method) {
        $result = $user->updateUserCart($game_id, $method);
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Missing parameters'
        ]);
    }
    exit;
}

