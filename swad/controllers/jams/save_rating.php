<?php
require_once '../../config.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['USERDATA']['id'];
$input = json_decode(file_get_contents('php://input'), true);
$sprint_id = (int)($input['sprint_id'] ?? 0);
$submission_id = (int)($input['submission_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');

if (!$sprint_id || !$submission_id || $rating < 0 || $rating > 10) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

$db = (new Database())->connect();
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
    exit;
}

try {
    $db->beginTransaction();

    // Запрет для хоста
    $stmt = $db->prepare("SELECT host_user_id FROM sprints WHERE id = ?");
    $stmt->execute([$sprint_id]);
    if ($stmt->fetchColumn() == $userId) {
        throw new Exception('Хост не может оценивать');
    }

    // Убедимся, что запись бюджета существует
    $stmt = $db->prepare("
        INSERT IGNORE INTO sprint_vote_budgets (sprint_id, user_id, total_budget, used_budget)
        VALUES (?, ?, 10, 0)
    ");
    $stmt->execute([$sprint_id, $userId]);

    // Заблокируем строку бюджета
    $stmt = $db->prepare("SELECT used_budget FROM sprint_vote_budgets WHERE sprint_id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$sprint_id, $userId]);
    $usedBudget = (int)$stmt->fetchColumn();

    // Получим старую оценку
    $stmt = $db->prepare("SELECT rating FROM sprint_ratings WHERE sprint_id = ? AND submission_id = ? AND user_id = ?");
    $stmt->execute([$sprint_id, $submission_id, $userId]);
    $oldRating = (int)$stmt->fetchColumn();

    $delta = $rating - $oldRating;
    $newUsed = $usedBudget + $delta;
    if ($newUsed < 0 || $newUsed > 10) {
        throw new Exception('Превышен лимит голосов (максимум 10)');
    }

    // Сохраняем оценку
    $stmt = $db->prepare("
        INSERT INTO sprint_ratings (sprint_id, submission_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            comment = VALUES(comment),
            updated_at = NOW()
    ");
    $stmt->execute([$sprint_id, $submission_id, $userId, $rating, $comment]);

    // Обновляем бюджет
    $stmt = $db->prepare("UPDATE sprint_vote_budgets SET used_budget = ? WHERE sprint_id = ? AND user_id = ?");
    $stmt->execute([$newUsed, $sprint_id, $userId]);

    // Статистика для работы
    $stmt = $db->prepare("
        SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as votes_count
        FROM sprint_ratings WHERE submission_id = ?
    ");
    $stmt->execute([$submission_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $db->commit();

    echo json_encode([
        'success' => true,
        'avg_rating' => $stats['avg_rating'] ?? null,
        'votes_count' => $stats['votes_count'] ?? 0,
        'remaining_budget' => 10 - $newUsed
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}