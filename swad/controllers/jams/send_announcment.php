<?php
/**
 * swad/controllers/jams/send_announcement.php
 * Создаёт объявление спринта. Принимает JSON: { sprint_id, title, body }.
 * Доступ — только админам (тот же список, что в admin.php).
 */

header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../config.php');

if (session_status() === PHP_SESSION_NONE) session_start();

function out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

$allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];
$username = $_SESSION['USERDATA']['username'] ?? '';
if (!in_array($username, $allowedAdmins, true)) {
    http_response_code(403);
    out(false, 'Нет доступа');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$sprintId = (int)($data['sprint_id'] ?? 0);
$title    = trim($data['title'] ?? '');
$body     = trim($data['body'] ?? '');

if (!$sprintId)            out(false, 'Не указан спринт');
if ($title === '')        out(false, 'Заголовок обязателен');
if (mb_strlen($title) > 255) out(false, 'Заголовок слишком длинный (макс. 255)');

$db = (new Database())->connect();
if (!$db) out(false, 'Ошибка БД');

try {
    $stmt = $db->prepare("
        INSERT INTO sprint_announcements (sprint_id, title, content, is_new, created_at)
        VALUES (?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$sprintId, $title, $body]);
    $annId = (int)$db->lastInsertId();
} catch (Exception $e) {
    out(false, 'Не удалось сохранить объявление');
}

/* Опционально: уведомить участников через NotificationCenter, если он есть.
   Не блокируем ответ, если класса нет. */
$notified = 0;
$ncPath = __DIR__ . '/NotificationCenter.php';
if (file_exists($ncPath)) {
    try {
        require_once($ncPath);
        $ids = $db->prepare("SELECT user_id FROM sprint_participants WHERE sprint_id = ?");
        $ids->execute([$sprintId]);
        $userIds = $ids->fetchAll(PDO::FETCH_COLUMN);
        if ($userIds && class_exists('NotificationCenter')) {
            $nc = new NotificationCenter();
            $nc->sendNotifications(
                $userIds,
                '📢 ' . $title,
                $body,
                '/jams/participant.php?sprint_id=' . $sprintId . '#announcements'
            );
            $notified = count($userIds);
        }
    } catch (Exception $e) {
        // тихо игнорируем — объявление уже создано
    }
}

out(true, 'Объявление отправлено', ['announcement_id' => $annId, 'notified' => $notified]);