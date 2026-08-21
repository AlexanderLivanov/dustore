<?php
// expert/admin/update-review-1h.php — правка СВОЕЙ рецензии в течение часа после отправки.
// POST JSON: { review_id, score, comment, verdict }
// Ответ: { success, message }

session_start();
require_once __DIR__ . '/../../swad/config.php';
header('Content-Type: application/json; charset=utf-8');

function r_out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit(); }

$userId  = (int)($_SESSION['USERDATA']['id'] ?? 0);
$isAdmin = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
if (!$userId) r_out(['success' => false, 'message' => 'Нужна авторизация'], 403);

$pdo = (new Database())->connect();

// Мой expert_id.
$e = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved' LIMIT 1");
$e->execute([$userId]);
$expertId = $e->fetchColumn();
if (!$expertId) r_out(['success' => false, 'message' => 'Только эксперты'], 403);

$in       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$reviewId = (int)($in['review_id'] ?? 0);
$score    = (int)($in['score'] ?? -1);
$comment  = trim((string)($in['comment'] ?? ''));
$verdict  = $in['verdict'] ?? '';

if (!$reviewId) r_out(['success' => false, 'message' => 'Не указана рецензия'], 400);
if ($score < 0 || $score > 100) r_out(['success' => false, 'message' => 'Оценка 0–100'], 422);
if (mb_strlen($comment) < 40) r_out(['success' => false, 'message' => 'Рецензия минимум 40 символов'], 422);
if (!in_array($verdict, ['recommend', 'revision', 'reject'], true)) r_out(['success' => false, 'message' => 'Выберите вердикт'], 422);

// Рецензия должна быть моя и не старше часа.
$r = $pdo->prepare("SELECT expert_id, game_id, created_at FROM moderation_reviews WHERE id = ? LIMIT 1");
$r->execute([$reviewId]);
$row = $r->fetch(PDO::FETCH_ASSOC);
if (!$row) r_out(['success' => false, 'message' => 'Рецензия не найдена'], 404);
if ((int)$row['expert_id'] !== (int)$expertId) r_out(['success' => false, 'message' => 'Это не ваша рецензия'], 403);
if (empty($row['created_at']) || (time() - strtotime($row['created_at'])) >= 3600) {
    r_out(['success' => false, 'message' => 'Прошёл час — редактирование недоступно'], 409);
}

$pdo->prepare("UPDATE moderation_reviews SET score = ?, comment = ?, verdict = ?, updated_at = NOW() WHERE id = ?")
    ->execute([$score, $comment, $verdict, $reviewId]);

// Если игра ещё на модерации — пересчитаем вердикт (как в submit-moderation).
$gameId = (int)$row['game_id'];
$gs = $pdo->prepare("SELECT moderation_status FROM games WHERE id = ?");
$gs->execute([$gameId]);
if ($gs->fetchColumn() === 'pending') {
    $totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
    $need = max(1, (int)ceil($totalExperts * 0.51));
    $c = $pdo->prepare("SELECT COUNT(DISTINCT expert_id) voted, SUM(verdict='recommend') rec, SUM(verdict='revision') rev, SUM(verdict='reject') rej FROM moderation_reviews WHERE game_id = ?");
    $c->execute([$gameId]);
    $cc = $c->fetch(PDO::FETCH_ASSOC);
    $recommend = (int)$cc['rec']; $revision = (int)$cc['rev']; $reject = (int)$cc['rej']; $voted = (int)$cc['voted'];
    if ($recommend >= $need) {
        $pdo->prepare("UPDATE games SET moderation_status='approved', updated_at=NOW() WHERE id=?")->execute([$gameId]);
    } elseif ($voted >= $totalExperts) {
        $newStatus = ($revision >= $reject) ? 'revision' : 'rejected';
        $revUntil = $newStatus === 'revision' ? date('Y-m-d H:i:s', time() + 12 * 3600) : null;
        $pdo->prepare("UPDATE games SET moderation_status=?, revision_until=?, updated_at=NOW() WHERE id=?")->execute([$newStatus, $revUntil, $gameId]);
    }
}

r_out(['success' => true, 'message' => 'Рецензия обновлена']);