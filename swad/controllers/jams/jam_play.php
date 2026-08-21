<?php
// swad/controllers/jams/jam_play.php
// Фиксирует, что игрок открыл/скачал игру джема (play-to-vote).
// Вызывается при переходе на страницу игры из карточки голосования.
//
// POST JSON: { sprint_id, game_id, source? }
// Ответ: { success }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Нужна авторизация']); exit; }

$in        = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$sprint_id = (int)($in['sprint_id'] ?? 0);
$game_id   = (int)($in['game_id'] ?? 0);
$source    = ($in['source'] ?? 'download') === 'browser' ? 'browser' : 'download';
if (!$sprint_id || !$game_id) { echo json_encode(['success' => false, 'message' => 'Неполный запрос']); exit; }

$pdo = (new Database())->connect();

// Игра должна быть одобрена и в этом джеме.
$g = $pdo->prepare("SELECT 1 FROM games WHERE id = ? AND sprint_id = ? AND (moderation_status = 'approved' OR status = 'published') LIMIT 1");
$g->execute([$game_id, $sprint_id]);
if (!$g->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'Игра недоступна']); exit; }

$ip = isset($_SERVER['REMOTE_ADDR']) ? @inet_pton($_SERVER['REMOTE_ADDR']) : null;

// Первое открытие фиксируем, повторные — не трогаем (uniq ключ sprint+user+game).
$pdo->prepare("
    INSERT INTO jam_plays (sprint_id, game_id, user_id, source, first_download_at, ip)
    VALUES (:sid, :gid, :uid, :src, NOW(), :ip)
    ON DUPLICATE KEY UPDATE play_seconds = play_seconds
")->execute(['sid' => $sprint_id, 'gid' => $game_id, 'uid' => $userId, 'src' => $source, 'ip' => $ip]);

echo json_encode(['success' => true]);