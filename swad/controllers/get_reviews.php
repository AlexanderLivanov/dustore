<?php
// swad/controllers/get_reviews.php
session_start();
require_once('../config.php');
require_once('game.php');

header('Content-Type: application/json; charset=utf-8');

$gameId = (int)($_GET['game_id'] ?? 0);
if ($gameId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID игры']);
    exit;
}

$uid = (int)($_SESSION['USERDATA']['id'] ?? 0);

$gameController = new Game();
// getReviews должен вернуть по отзыву как минимум: id, user_id, username,
// profile_picture, rating, text, created_at, developer_reply
$reviews = $gameController->getReviewsArray($gameId) ?: [];

// ── Обогащаем голосами (лайки / дизлайки / мой голос) одним проходом ──
// Устойчиво: если review_votes недоступна, отзывы всё равно отдаются со счётчиками 0.
if ($reviews) {
    $agg  = [];  // review_id => ['likes'=>x, 'dislikes'=>y]
    $mine = [];  // review_id => value (-1|1)

    try {
        $pdo = (new Database())->connect();

        $ids = array_values(array_filter(array_map(
            fn($r) => (int)($r['id'] ?? 0),
            $reviews
        )));

        if ($ids) {
            $in = implode(',', $ids); // безопасно: только целые из intval выше

            foreach ($pdo->query("
                SELECT review_id,
                       SUM(value = 1)  AS likes,
                       SUM(value = -1) AS dislikes
                FROM review_votes
                WHERE review_id IN ($in)
                GROUP BY review_id
            ") as $row) {
                $agg[(int)$row['review_id']] = [
                    'likes'    => (int)$row['likes'],
                    'dislikes' => (int)$row['dislikes'],
                ];
            }

            if ($uid) {
                $st = $pdo->prepare("SELECT review_id, value FROM review_votes WHERE user_id = ? AND review_id IN ($in)");
                $st->execute([$uid]);
                foreach ($st as $row) {
                    $mine[(int)$row['review_id']] = (int)$row['value'];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[get_reviews] votes enrichment failed: ' . $e->getMessage());
    }

    foreach ($reviews as &$r) {
        $rid = (int)($r['id'] ?? 0);
        $r['likes']    = $agg[$rid]['likes']    ?? 0;
        $r['dislikes'] = $agg[$rid]['dislikes'] ?? 0;
        $r['my_vote']  = $mine[$rid] ?? 0;
    }
    unset($r);
}

echo json_encode(['success' => true, 'reviews' => $reviews], JSON_UNESCAPED_UNICODE);