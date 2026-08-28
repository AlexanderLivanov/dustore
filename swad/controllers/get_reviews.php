<?php
// swad/controllers/get_reviews.php
// Отзывы игроков по игре + ответ студии + голоса за отзыв.
//
// GET ?game_id=<int>
// Ответ: { success: true, reviews: [ {
//     id, user_id, username, profile_picture, rating, text, created_at,
//     developer_reply, developer_reply_at, developer_reply_by,
//     likes, dislikes, my_vote
// } ] }
//
// Ответы студий лежат в отдельной таблице review_replies (review_id, studio_id, text).
// В game_reviews колонки developer_reply нет — фронт получает её отсюда.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function gr_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit();
}

$game_id = (int)($_GET['game_id'] ?? 0);
if ($game_id <= 0) gr_out(['success' => false, 'message' => 'Не указана игра'], 400);

$viewerId = (int)($_SESSION['USERDATA']['id'] ?? 0);

try {
    $pdo = (new Database())->connect();
    if (!$pdo) gr_out(['success' => false, 'message' => 'БД недоступна'], 500);

    $sql = "
        SELECT
            r.id,
            r.user_id,
            r.rating,
            r.text,
            r.created_at,
            u.username,
            u.profile_picture,

            /* Ответ студии: берём последний, если их вдруг несколько.
               Обычный LEFT JOIN размножил бы отзыв на число ответов. */
            rr.text        AS developer_reply,
            rr.created_at  AS developer_reply_at,
            s.name         AS developer_reply_by,

            COALESCE(v.likes, 0)    AS likes,
            COALESCE(v.dislikes, 0) AS dislikes,
            COALESCE(mv.value, 0)   AS my_vote

        FROM game_reviews r
        JOIN users u ON u.id = r.user_id

        LEFT JOIN review_replies rr
               ON rr.id = (
                    SELECT rr2.id FROM review_replies rr2
                    WHERE rr2.review_id = r.id
                    ORDER BY rr2.created_at DESC, rr2.id DESC
                    LIMIT 1
               )
        LEFT JOIN studios s ON s.id = rr.studio_id

        LEFT JOIN (
            SELECT review_id,
                   SUM(value = 1)  AS likes,
                   SUM(value = -1) AS dislikes
            FROM review_votes
            GROUP BY review_id
        ) v ON v.review_id = r.id

        LEFT JOIN review_votes mv
               ON mv.review_id = r.id AND mv.user_id = :viewer

        WHERE r.game_id = :gid
        ORDER BY r.created_at DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute(['gid' => $game_id, 'viewer' => $viewerId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $reviews = [];
    foreach ($rows as $r) {
        $reviews[] = [
            'id'                 => (int)$r['id'],
            'user_id'            => (int)$r['user_id'],
            'username'           => $r['username'],
            'profile_picture'    => $r['profile_picture'] ?: null,
            'rating'             => (int)$r['rating'],
            'text'               => $r['text'],
            'created_at'         => $r['created_at'],
            'developer_reply'    => $r['developer_reply'] ?: null,
            'developer_reply_at' => $r['developer_reply_at'] ?: null,
            'developer_reply_by' => $r['developer_reply_by'] ?: null,
            'likes'              => (int)$r['likes'],
            'dislikes'           => (int)$r['dislikes'],
            'my_vote'            => (int)$r['my_vote'],
        ];
    }

    gr_out(['success' => true, 'reviews' => $reviews]);

} catch (Throwable $e) {
    error_log('[get_reviews] game_id=' . $game_id . ': ' . $e->getMessage());
    gr_out(['success' => false, 'message' => 'Не удалось загрузить отзывы'], 500);
}