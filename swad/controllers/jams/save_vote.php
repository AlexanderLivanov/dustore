<?php
// swad/controllers/jams/save_vote.php
// Отдать / изменить / отменить голос игрока за игру джема.
// Модель: jam_votes (бюджет 10 очков на джем, 0-10 на игру).
// points = 0  -> отмена голоса (удаление строки).
//
// POST JSON: { sprint_id, game_id, points }
// Ответ: { success, remaining_budget, game_points, game_voters, message }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

function v_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$userId) v_out(['success' => false, 'message' => 'Нужна авторизация'], 403);

$in        = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprint_id = (int)($in['sprint_id'] ?? 0);
$game_id   = (int)($in['game_id'] ?? 0);
$points    = (int)($in['points'] ?? 0);

if (!$sprint_id || !$game_id) v_out(['success' => false, 'message' => 'Неполный запрос'], 400);
if ($points < 0 || $points > 10) v_out(['success' => false, 'message' => 'Голосов должно быть 0–10'], 422);

$pdo = (new Database())->connect();

// Джем + окно голосования.
$s = $pdo->prepare("SELECT host_user_id, status, voting_start, voting_end FROM sprints WHERE id = ?");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) v_out(['success' => false, 'message' => 'Джем не найден'], 404);

// Хост джема не голосует.
// if ((int)$sprint['host_user_id'] === $userId) {
//     v_out(['success' => false, 'message' => 'Организатор джема не может голосовать'], 403);
// }

// Окно голосования: если заданы даты — уважаем их.
$now = time();
$vStart = $sprint['voting_start'] ? strtotime($sprint['voting_start']) : null;
$vEnd   = $sprint['voting_end']   ? strtotime($sprint['voting_end'])   : null;
$votingOpen = (!$vStart || $vStart <= $now) && (!$vEnd || $now <= $vEnd);
// $votingOpen = true;
if (!$votingOpen) v_out(['success' => false, 'message' => 'Голосование сейчас закрыто'], 409);

// Игра должна быть в этом джеме и одобрена.
$g = $pdo->prepare("SELECT id FROM games WHERE id = ? AND sprint_id = ? AND (moderation_status = 'approved' OR status = 'published') LIMIT 1");
$g->execute([$game_id, $sprint_id]);
if (!$g->fetchColumn()) v_out(['success' => false, 'message' => 'Игра ещё не прошла проверку — голосование недоступно'], 404);

// Play-to-vote: чтобы отдать голос (>0), игрок должен был открыть игру.
// Отмена (points = 0) гейт не требует.
if ($points > 0) {
    $pl = $pdo->prepare("SELECT first_download_at FROM jam_plays WHERE sprint_id = ? AND user_id = ? AND game_id = ? LIMIT 1");
    $pl->execute([$sprint_id, $userId, $game_id]);
    $playedAt = $pl->fetchColumn();
    if ($playedAt === false) {
        v_out(['success' => false, 'need_play' => true, 'message' => 'Сначала откройте игру, чтобы за неё голосовать'], 409);
    }
    // (опц.) анти-накрутка: минимальная задержка между открытием и голосом. 0 = выключено.
    $MIN_PLAY_SECONDS = 0;
    if ($MIN_PLAY_SECONDS > 0 && (time() - strtotime($playedAt)) < $MIN_PLAY_SECONDS) {
        v_out(['success' => false, 'message' => 'Подождите немного после открытия игры'], 409);
    }
}

// Эксперт джема? Бюджет эксперта = кол-во игр × 10 (можно ставить до 10 каждой).
// Обычный игрок — 10 очков на все игры вместе.
$e = $pdo->prepare("SELECT 1 FROM sprint_experts WHERE sprint_id = ? AND user_id = ? LIMIT 1");
$e->execute([$sprint_id, $userId]);
$isExpert = $e->fetchColumn() ? 1 : 0;

if ($isExpert) {
    $gc = $pdo->prepare("SELECT COUNT(*) FROM games WHERE sprint_id = ? AND (moderation_status = 'approved' OR status = 'published')");
    $gc->execute([$sprint_id]);
    $budget = 10 * max(1, (int)$gc->fetchColumn());
} else {
    $budget = 10;
}

// Бюджет: сумма всех очков голосующего в этом джеме, кроме текущей игры.
$b = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM jam_votes WHERE sprint_id = ? AND user_id = ? AND game_id <> ?");
$b->execute([$sprint_id, $userId, $game_id]);
$usedOther = (int)$b->fetchColumn();

if ($usedOther + $points > $budget) {
    v_out(['success' => false, 'message' => "Превышен бюджет: занято $usedOther из $budget", 'remaining_budget' => $budget - $usedOther], 409);
}

if ($points === 0) {
    // Отмена голоса.
    $pdo->prepare("DELETE FROM jam_votes WHERE sprint_id = ? AND user_id = ? AND game_id = ?")
        ->execute([$sprint_id, $userId, $game_id]);
} else {
    // Апсерт (уникальный ключ sprint_id+user_id+game_id).
    $ip = isset($_SERVER['REMOTE_ADDR']) ? @inet_pton($_SERVER['REMOTE_ADDR']) : null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $pdo->prepare("
        INSERT INTO jam_votes (sprint_id, game_id, user_id, points, weight, is_expert, ip, user_agent, created_at)
        VALUES (:sid, :gid, :uid, :pts, 1.00, :exp, :ip, :ua, NOW())
        ON DUPLICATE KEY UPDATE points = VALUES(points), is_expert = VALUES(is_expert), updated_at = NOW()
    ")->execute([
        'sid' => $sprint_id, 'gid' => $game_id, 'uid' => $userId,
        'pts' => $points, 'exp' => $isExpert, 'ip' => $ip, 'ua' => $ua,
    ]);
}

// Пересчёт: остаток бюджета + агрегат по игре.
$remaining = $budget - ($usedOther + $points);

$agg = $pdo->prepare("SELECT COALESCE(SUM(points),0) AS pts, COUNT(*) AS voters FROM jam_votes WHERE sprint_id = ? AND game_id = ?");
$agg->execute([$sprint_id, $game_id]);
$a = $agg->fetch(PDO::FETCH_ASSOC);

v_out([
    'success'          => true,
    'remaining_budget' => $remaining,
    'my_points'        => $points,
    'game_points'      => (int)$a['pts'],
    'game_voters'      => (int)$a['voters'],
    'message'          => $points === 0 ? 'Голос отменён' : 'Голос учтён',
]);