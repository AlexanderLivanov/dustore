<?php
/**
 * expert/admin/update-content-rating.php
 * POST — эксперт корректирует маркировку контента игры
 */
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db  = new Database();
$pdo = $db->connect();

// Проверяем эксперта
$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$_SESSION['USERDATA']['id'] ?? 0]);
if (!$stmt->fetch()) { http_response_code(403); die('no access'); }

$gameId = (int)($_GET['id'] ?? 0);
if (!$gameId) { header('Location: /expert/admin'); exit(); }

$allowed = ['violence','blood','language','fear','gambling','drugs',
            'discrimination','sexual','nudity','online','purchases','flashing'];

$descriptors = array_values(array_filter($_POST['descriptors'] ?? [], fn($d) => in_array($d, $allowed)));

$pdo->prepare("UPDATE games SET content_descriptors=?, content_rating_verified=1 WHERE id=?")
    ->execute([json_encode($descriptors), $gameId]);

header("Location: moderation-game?id=$gameId&content_saved=1");
exit();