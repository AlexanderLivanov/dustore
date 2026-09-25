<?php
declare(strict_types=1);
/**
 * m/api/wishlist.php — сердечко на странице игры. POST game_id, on=1|0.
 *
 * Не toggle, а явное состояние (on=1/0): двойной тап или повтор запроса
 * после обрыва сети не перевернёт сердечко обратно. Идемпотентность — это
 * про такие мелочи. (Старая мобилка слала в /swad/controllers/wishlist.php,
 * а это вишлист АССЕТОВ — игры туда не попадали вовсе.)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../lib.php';
m_restore_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = (int)($_SESSION['USERDATA']['id'] ?? 0);
session_write_close();
if ($uid <= 0) { http_response_code(401); exit(json_encode(['ok' => false, 'error' => 'auth'])); }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false])); }

$gid = (int)($_POST['game_id'] ?? 0);
$on  = !empty($_POST['on']);
$db  = (new Database())->connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $db->prepare("SELECT 1 FROM games WHERE id = ? AND status = 'published' LIMIT 1");
$st->execute([$gid]);
if (!$st->fetchColumn()) exit(json_encode(['ok' => false, 'error' => 'not_found']));

if ($on) {
    $ex = $db->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND game_id = ? LIMIT 1");
    $ex->execute([$uid, $gid]);
    if (!$ex->fetchColumn()) $db->prepare("INSERT INTO wishlists (user_id, game_id) VALUES (?, ?)")->execute([$uid, $gid]);
} else {
    $db->prepare("DELETE FROM wishlists WHERE user_id = ? AND game_id = ?")->execute([$uid, $gid]);
}
echo json_encode(['ok' => true, 'on' => $on]);
