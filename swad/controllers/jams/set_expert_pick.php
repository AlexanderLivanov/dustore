<?php
// swad/controllers/jams/set_expert_pick.php
// Проставить/снять «Выбор эксперта» для игры джема.
// Ставит админ или одобренный эксперт (в т.ч. за внешних экспертов).
//
// POST JSON: { sprint_id, expert_id, game_id, action: 'add'|'remove' }
// Ответ: { success, picks: [{expert_id, name}], message }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

function p_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId     = (int)($_SESSION['USERDATA']['id'] ?? 0);
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
$isAdmin    = ($globalRole === -1);
if (!$userId) p_out(['success' => false, 'message' => 'Нужна авторизация'], 403);

$pdo = (new Database())->connect();

$in        = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprint_id = (int)($in['sprint_id'] ?? 0);
$expert_id = (int)($in['expert_id'] ?? 0);
$game_id   = (int)($in['game_id'] ?? 0);
$action    = $in['action'] ?? 'add';

if (!$sprint_id || !$expert_id || !$game_id) p_out(['success' => false, 'message' => 'Неполный запрос'], 400);

// Эксперт должен принадлежать этому джему.
$x = $pdo->prepare("SELECT user_id FROM sprint_experts WHERE id = ? AND sprint_id = ? LIMIT 1");
$x->execute([$expert_id, $sprint_id]);
$expertRow = $x->fetch(PDO::FETCH_ASSOC);
if (!$expertRow) p_out(['success' => false, 'message' => 'Эксперт не в этом джеме'], 404);

// Право: админ, платформенный эксперт ИЛИ сам эксперт джема (ставит свой пик).
if (!$isAdmin) {
    $isSelf = ($userId > 0) && ((int)($expertRow['user_id'] ?? 0) === $userId);
    $pe = $pdo->prepare("SELECT 1 FROM experts WHERE user_id = ? AND status = 'approved' LIMIT 1");
    $pe->execute([$userId]);
    if (!$isSelf && !$pe->fetchColumn()) p_out(['success' => false, 'message' => 'Доступ запрещён'], 403);
}

// Игра должна быть в этом джеме.
$g = $pdo->prepare("SELECT 1 FROM games WHERE id = ? AND sprint_id = ? LIMIT 1");
$g->execute([$game_id, $sprint_id]);
if (!$g->fetchColumn()) p_out(['success' => false, 'message' => 'Игра не в этом джеме'], 404);

if ($action === 'remove') {
    $pdo->prepare("DELETE FROM sprint_expert_picks WHERE sprint_id = ? AND expert_id = ? AND game_id = ?")
        ->execute([$sprint_id, $expert_id, $game_id]);
} else {
    $pdo->prepare("
        INSERT IGNORE INTO sprint_expert_picks (sprint_id, expert_id, game_id, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([$sprint_id, $expert_id, $game_id, $userId]);
}

// Текущие пики для игры.
$q = $pdo->prepare("
    SELECT p.expert_id, COALESCE(se.external_name, u.username, 'Эксперт') AS name
    FROM sprint_expert_picks p
    JOIN sprint_experts se ON se.id = p.expert_id
    LEFT JOIN users u ON u.id = se.user_id
    WHERE p.sprint_id = ? AND p.game_id = ?
");
$q->execute([$sprint_id, $game_id]);

p_out(['success' => true, 'picks' => $q->fetchAll(PDO::FETCH_ASSOC), 'message' => $action === 'remove' ? 'Пик снят' : 'Пик добавлен']);