<?php
declare(strict_types=1);

/**
 * l4t/api/bid_view.php — просмотр заявки (открыли карточку).
 *
 * bids.views существовал, но никто его не инкрементил — везде висел 0.
 * Дедуп: один зритель — один просмотр заявки в сутки (bid_views, PK).
 * Счётчик в bids растёт только если INSERT реально вставил строку.
 * Автор свои заявки не накручивает. Без CSRF: вызов через sendBeacon,
 * а худшее, что можно сделать подделкой, — +1 просмотр раз в сутки.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../swad/config.php';

$body  = json_decode((string)file_get_contents('php://input'), true) ?: [];
$bidId = (int)($body['bid_id'] ?? $_GET['bid_id'] ?? 0);
if ($bidId <= 0) { echo '{"ok":false}'; exit; }

$uid  = !empty($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 0;
$anon = preg_match('/^[0-9a-f-]{36}$/i', $_COOKIE['dstr_aid'] ?? '') ? $_COOKIE['dstr_aid'] : session_id();
$key  = md5($uid ? 'u' . $uid : 'a' . $anon);

try {
    $pdo = (new Database())->connect('desl4t');
    $own = $pdo->prepare("SELECT bidder_id FROM bids WHERE id = ?");
    $own->execute([$bidId]);
    $owner = $own->fetchColumn();
    if ($owner === false || (int)$owner === $uid) { echo '{"ok":true}'; exit; }

    $ins = $pdo->prepare("INSERT IGNORE INTO bid_views (bid_id, day, viewer_key) VALUES (?, CURDATE(), ?)");
    $ins->execute([$bidId, $key]);
    if ($ins->rowCount() > 0) {
        $pdo->prepare("UPDATE bids SET views = views + 1 WHERE id = ?")->execute([$bidId]);
    }
    echo '{"ok":true}';
} catch (Throwable $e) {
    error_log('[l4t/bid_view] ' . $e->getMessage());
    echo '{"ok":false}';
}
