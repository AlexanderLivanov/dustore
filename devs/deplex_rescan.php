<?php
/**
 * deplex_rescan.php — ставит последний билд игры на повторную проверку антивирусом.
 * Вызывается кнопкой «Перепроверить» из виджета (fetch POST). Только для админа/эксперта.
 * Положить в /devs/ (или поправить путь fetch в deplex_scan_widget.php).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/deplex_web.php');

$db   = new Database();
$conn = $db->connect();

$gameId = (int) ($_POST['game_id'] ?? 0);
if (!$gameId) {
    echo json_encode(['ok' => false, 'error' => 'нет game_id']);
    exit;
}

// --- авторизация: админ ИЛИ одобренный эксперт (поправь под свою модель) ---
$uid     = (int) ($_SESSION['USERDATA']['id'] ?? 0);
$isAdmin = ((int) ($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;

$isExpert = false;
if ($uid && !$isAdmin) {
    try {
        $e = $conn->prepare("SELECT 1 FROM experts WHERE user_id = ? AND status = 'approved' LIMIT 1");
        $e->execute([$uid]);
        $isExpert = (bool) $e->fetch();
    } catch (Throwable $ex) {
        $isExpert = false;
    }
}

if (!$isAdmin && !$isExpert) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'недостаточно прав']);
    exit;
}

echo deplex_requeue_scan($conn, $gameId)
    ? json_encode(['ok' => true])
    : json_encode(['ok' => false, 'error' => 'у игры нет deplex-билда']);