<?php
// swad/controllers/jams/save_vote.php
// Отдать / изменить / отменить голос игрока за игру джема.
// Модель: jam_votes (бюджет 10 очков на джем, 0-10 на игру).
// points = 0  -> отмена голоса (удаление строки).
//
// POST JSON: { sprint_id, game_id, points }
// Ответ: { success, remaining_budget, my_points, game_points, game_voters, message }
//
// Конфигурируется через config.php (обе константы опциональны):
//   define('JAM_VOTING_FORCE_OPEN', true);  // игнорировать окно голосования (отладка!)
//   define('JAM_EXPERT_WEIGHT', 1.00);      // вес экспертного голоса в jam_votes.weight

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

if (!$sprint_id || !$game_id)    v_out(['success' => false, 'message' => 'Неполный запрос'], 400);
if ($points < 0 || $points > 10) v_out(['success' => false, 'message' => 'Голосов должно быть 0–10'], 422);

$pdo = (new Database())->connect();
if (!$pdo) v_out(['success' => false, 'message' => 'БД недоступна'], 500);

/* ─────────────── Джем и окно голосования ─────────────── */
$s = $pdo->prepare("SELECT host_user_id, status, voting_start, voting_end FROM sprints WHERE id = ?");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) v_out(['success' => false, 'message' => 'Джем не найден'], 404);

date_default_timezone_set('Europe/Moscow');
$now    = time();
$vStart = $sprint['voting_start'] ? strtotime($sprint['voting_start']) : null;
$vEnd   = $sprint['voting_end']   ? strtotime($sprint['voting_end'])   : null;

$forceOpen  = defined('JAM_VOTING_FORCE_OPEN') && JAM_VOTING_FORCE_OPEN;
$votingOpen = $forceOpen || ((!$vStart || $vStart <= $now) && (!$vEnd || $now <= $vEnd));

if (!$votingOpen) {
    $msg = ($vStart && $now < $vStart)
        ? 'Голосование ещё не началось'
        : 'Голосование завершено';
    v_out(['success' => false, 'message' => $msg], 409);
}

// Организатор джема не голосует.
// if ((int)$sprint['host_user_id'] === $userId && !$forceOpen) {
//     v_out(['success' => false, 'message' => 'Организатор джема не может голосовать'], 403);
// }

/* ─────────────── Игра должна быть в этом джеме и допущена ─────────────── */
$g = $pdo->prepare("
    SELECT g.id, s.owner_id
    FROM games g
    LEFT JOIN studios s ON s.id = g.developer
    WHERE g.id = ? AND g.sprint_id = ?
      AND (g.moderation_status = 'approved' OR g.status = 'published')
    LIMIT 1");
$g->execute([$game_id, $sprint_id]);
$gameRow = $g->fetch(PDO::FETCH_ASSOC);
if (!$gameRow) {
    v_out(['success' => false, 'message' => 'Игра ещё не прошла проверку — голосование недоступно'], 404);
}
$ownerId = (int)($gameRow['owner_id'] ?? 0);

/* ─────────────── Нельзя голосовать за свою работу ───────────────
   Проверяем автора и всех, кто с ним в одной команде этого джема.
   Отмена голоса (points = 0) остаётся доступной всегда. */
if ($points > 0 && $ownerId > 0) {
    $isOwn = ($ownerId === $userId);
    if (!$isOwn) {
        $tm = $pdo->prepare("
            SELECT 1 FROM team_members a
            JOIN team_members b ON b.team_id = a.team_id
            WHERE a.sprint_id = ? AND a.user_id = ? AND b.user_id = ?
            LIMIT 1");
        $tm->execute([$sprint_id, $ownerId, $userId]);
        $isOwn = (bool)$tm->fetchColumn();
    }
    if ($isOwn) {
        v_out(['success' => false, 'message' => 'За свою работу голосовать нельзя'], 403);
    }
}

/* ─────────────── Play-to-vote ───────────────
   Право голоса даёт факт скачивания билда: jam_plays пишется на сервере
   (download_game.php / deplex-установщик / webplayer), а не по клику в браузере. */
$playedAt = null;
if ($points > 0) {
    $pl = $pdo->prepare("SELECT first_download_at FROM jam_plays
                         WHERE sprint_id = ? AND user_id = ? AND game_id = ? LIMIT 1");
    $pl->execute([$sprint_id, $userId, $game_id]);
    $playedAt = $pl->fetchColumn();
    if ($playedAt === false) {
        v_out(['success' => false, 'need_play' => true,
               'message' => 'Сначала скачайте игру, чтобы за неё голосовать'], 409);
    }
    // Анти-накрутка: минимальная пауза между скачиванием и голосом. 0 = выключено.
    $MIN_PLAY_SECONDS = 0;
    if ($MIN_PLAY_SECONDS > 0 && ($now - strtotime((string)$playedAt)) < $MIN_PLAY_SECONDS) {
        v_out(['success' => false, 'message' => 'Подождите немного после скачивания игры'], 409);
    }
}

/* ─────────────── Бюджет ───────────────
   Игрок  — 10 очков на все игры джема вместе.
   Эксперт — 10 очков на каждую игру (то есть 10 × число допущенных работ). */
$e = $pdo->prepare("SELECT 1 FROM sprint_experts WHERE sprint_id = ? AND user_id = ? LIMIT 1");
$e->execute([$sprint_id, $userId]);
$isExpert = $e->fetchColumn() ? 1 : 0;

if ($isExpert) {
    $gc = $pdo->prepare("SELECT COUNT(*) FROM games
                         WHERE sprint_id = ? AND (moderation_status = 'approved' OR status = 'published')");
    $gc->execute([$sprint_id]);
    $budget = 10 * max(1, (int)$gc->fetchColumn());
} else {
    $budget = 10;
}

$weight = $isExpert && defined('JAM_EXPERT_WEIGHT') ? (float)JAM_EXPERT_WEIGHT : 1.00;

/* ─────────────── Проверка бюджета и запись — под сериализующим замком ───────────────
   Без него параллельные запросы читают один и тот же остаток и все проходят
   проверку: 10 одновременных запросов дают 100 очков вместо 10.
   GET_LOCK живёт в рамках соединения, у каждого PHP-запроса оно своё. */
$lockKey = "jamvote_{$sprint_id}_{$userId}";
$lk = $pdo->prepare("SELECT GET_LOCK(?, 5)");
$lk->execute([$lockKey]);
if ((int)$lk->fetchColumn() !== 1) {
    v_out(['success' => false, 'message' => 'Слишком много запросов подряд, попробуйте ещё раз'], 429);
}

$fail = null;

$b = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM jam_votes
                    WHERE sprint_id = ? AND user_id = ? AND game_id <> ?");
$b->execute([$sprint_id, $userId, $game_id]);
$usedOther = (int)$b->fetchColumn();

if ($usedOther + $points > $budget) {
    $fail = [
        'success'          => false,
        'message'          => "Превышен бюджет: занято $usedOther из $budget",
        'remaining_budget' => $budget - $usedOther,
    ];
} elseif ($points === 0) {
    $pdo->prepare("DELETE FROM jam_votes WHERE sprint_id = ? AND user_id = ? AND game_id = ?")
        ->execute([$sprint_id, $userId, $game_id]);
} else {
    $pdo->prepare("
        INSERT INTO jam_votes
            (sprint_id, game_id, user_id, points, weight, is_expert,
             ip, user_agent, downloaded_at, created_at)
        VALUES
            (:sid, :gid, :uid, :pts, :w, :exp,
             INET6_ATON(:ip), :ua, :dl, NOW())
        ON DUPLICATE KEY UPDATE
            points        = VALUES(points),
            weight        = VALUES(weight),
            is_expert     = VALUES(is_expert),
            downloaded_at = VALUES(downloaded_at),
            updated_at    = NOW()
    ")->execute([
        'sid' => $sprint_id,
        'gid' => $game_id,
        'uid' => $userId,
        'pts' => $points,
        'w'   => $weight,
        'exp' => $isExpert,
        'ip'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'dl'  => $playedAt ?: null,
    ]);
}

$pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockKey]);

if ($fail) v_out($fail, 409);

/* ─────────────── Пересчёт для UI ─────────────── */
$remaining = $budget - ($usedOther + $points);

$agg = $pdo->prepare("SELECT COALESCE(SUM(points),0) AS pts, COUNT(*) AS voters
                      FROM jam_votes WHERE sprint_id = ? AND game_id = ?");
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