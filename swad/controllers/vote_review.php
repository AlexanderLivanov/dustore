<?php
// swad/controllers/vote_review.php
// Тоггл лайка/дизлайка на отзыв. POST: review_id, value (1|-1).
// Ответ: {ok, likes, dislikes, my_vote}.

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$userId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$userId) { echo json_encode(['ok' => false, 'message' => 'Войдите, чтобы голосовать']); exit; }

$reviewId = (int)($_POST['review_id'] ?? 0);
$value    = (int)($_POST['value'] ?? 0);
if (!$reviewId || !in_array($value, [1, -1], true)) {
    echo json_encode(['ok' => false, 'message' => 'invalid']); exit;
}

$pdo = (new Database())->connect();

try {
    // текущий голос
    $st = $pdo->prepare("SELECT value FROM review_votes WHERE review_id = ? AND user_id = ?");
    $st->execute([$reviewId, $userId]);
    $cur = $st->fetchColumn();

    if ($cur === false) {
        // нет голоса → ставим
        $pdo->prepare("INSERT INTO review_votes (review_id, user_id, value) VALUES (?, ?, ?)")
            ->execute([$reviewId, $userId, $value]);
        $myVote = $value;
    } elseif ((int)$cur === $value) {
        // тот же голос → снимаем (тоггл)
        $pdo->prepare("DELETE FROM review_votes WHERE review_id = ? AND user_id = ?")
            ->execute([$reviewId, $userId]);
        $myVote = 0;
    } else {
        // другой голос → переключаем
        $pdo->prepare("UPDATE review_votes SET value = ? WHERE review_id = ? AND user_id = ?")
            ->execute([$value, $reviewId, $userId]);
        $myVote = $value;
    }

    $likes = (int)$pdo->query("SELECT COUNT(*) FROM review_votes WHERE review_id = {$reviewId} AND value = 1")->fetchColumn();
    $dislikes = (int)$pdo->query("SELECT COUNT(*) FROM review_votes WHERE review_id = {$reviewId} AND value = -1")->fetchColumn();

    echo json_encode(['ok' => true, 'likes' => $likes, 'dislikes' => $dislikes, 'my_vote' => $myVote]);
} catch (Throwable $e) {
    error_log('[vote_review] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'server']);
}