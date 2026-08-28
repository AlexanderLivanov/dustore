<?php
session_start();
require_once('../config.php');
require_once('user.php');

/* Раньше для анонима сюда шёл $_COOKIE['temp_id'] — случайное
 * ОТРИЦАТЕЛЬНОЕ число, которое header.php выдаёт каждому браузеру.
 * updateUserItems() писал его в library.player_id как «владельца»:
 * каждый безкуковый заход (краулер, режим инкогнито) накручивал
 * счётчик скачиваний. Именно он и ломал сортировку «Популярные».
 * Аноним библиотеки не имеет — писать нечего. */
$game_id = (int)($_GET['game_id'] ?? 0);
$user_id = (int)($_SESSION['USERDATA']['id'] ?? 0);
downloadGame($game_id, $user_id > 0 ? $user_id : null);

function downloadGame($game_id, $user_id){
    $db = new Database();
    $pdo = $db->connect();

    if ($game_id <= 0) {
        header('Location: /explore');
        exit();
    }

    if ($user_id !== null) {
        $curr_user = new User();
        $curr_user->updateUserItems($user_id, $game_id);
    }

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

