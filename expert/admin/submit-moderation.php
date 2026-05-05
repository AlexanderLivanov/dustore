<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

function checkModeration($pdo, $gameId)
{

    $stmt = $pdo->prepare("SELECT created_at, moderation_status FROM games WHERE id=?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();

    if (!$game || $game['moderation_status'] !== 'pending') return;

    $created = strtotime($game['created_at']);
    $expired = (time() - $created) >= 172800;

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(score > 51) as positive
        FROM moderation_reviews
        WHERE game_id=?
    ");
    $stmt->execute([$gameId]);
    $votes = $stmt->fetch();

    $stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'");
    $totalExperts = $stmt->fetchColumn();

    $positivePercent = $totalExperts > 0
        ? ($votes['positive'] / $totalExperts) * 100
        : 0;

    if ($positivePercent >= 51) {
        try {
        $stmt = $pdo->prepare("UPDATE games SET moderation_status='approved' WHERE id=?");
        $stmt->execute([$gameId]);
        echo "UPDATE OK, affected: " . $stmt->rowCount();
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
    }
    } elseif ($expired) {
        $pdo->prepare("UPDATE games SET moderation_status='rejected' WHERE id=?")
            ->execute([$gameId]);
    }
}

$db = new Database();
$pdo = $db->connect();

$gameId = (int)$_GET['id'];
$userId = $_SESSION['USERDATA']['id'];

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$userId]);
$expert = $stmt->fetch();

if (!$expert) die('no access');

$expertId = $expert['id'];

$score = (int)$_POST['score'];
$comment = $_POST['review'] ?? '';

$stmt = $pdo->prepare("
    INSERT INTO moderation_reviews (game_id, expert_id, score, comment)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        comment = VALUES(comment)
");

$stmt->execute([$gameId, $expertId, $score, $comment]);
checkModeration($pdo, $gameId);
header("Location: moderation-game?id=" . $gameId);
exit;
