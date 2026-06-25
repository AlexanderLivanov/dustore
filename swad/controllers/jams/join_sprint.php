<?php

require_once '../../config.php';

header('Content-Type: application/json');
session_start();

// ---- Проверяем авторизацию ----
$user_id = 0;

// 1. Если есть USERDATA (обычный вход)
if (!empty($_SESSION['USERDATA']['id'])) {
    $user_id = (int)$_SESSION['USERDATA']['id'];
}
// 2. Если пользователь зашёл через Telegram (есть telegram_id)
elseif (!empty($_SESSION['telegram_id'])) {
    $dbInst = new Database();
    $conn = $dbInst->connect();
    if ($conn) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$_SESSION['telegram_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $user_id = (int)$row['id'];
            // Дополнительно загружаем USERDATA для единообразия
            $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt2->execute([$user_id]);
            $_SESSION['USERDATA'] = $stmt2->fetch(PDO::FETCH_ASSOC);
        }
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Авторизуйтесь']);
    exit;
}

// ---- Основной код ----
$sprint_id = (int)($_POST['sprint_id'] ?? 0);

if (!$sprint_id) {
    echo json_encode(['success' => false, 'message' => 'Не указан ID спринта']);
    exit;
}

$dbInstance = new Database();
$conn = $dbInstance->connect();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
    exit;
}

// Проверяем существование спринта
$stmt = $conn->prepare("SELECT id, max_participants FROM sprints WHERE id = ?");
$stmt->execute([$sprint_id]);
$sprint = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sprint) {
    echo json_encode(['success' => false, 'message' => 'Спринт не найден']);
    exit;
}

// Уже участвует?
$stmt = $conn->prepare("SELECT id FROM sprint_participants WHERE sprint_id = ? AND user_id = ?");
$stmt->execute([$sprint_id, $user_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Вы уже участвуете']);
    exit;
}

// Проверка лимита
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM sprint_participants WHERE sprint_id = ?");
$stmt->execute([$sprint_id]);
$current = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
if ($current >= $sprint['max_participants']) {
    echo json_encode(['success' => false, 'message' => 'Лимит участников достигнут']);
    exit;
}

// Получаем данные из формы
$participant_type = $_POST['participant_type'] ?? 'solo';
$alias = trim($_POST['alias'] ?? '');
$city = trim($_POST['city'] ?? '');
$extra_info = trim($_POST['extra_info'] ?? '');
$links = trim($_POST['links'] ?? '');

// Если псевдоним не передан, используем username пользователя
if (empty($alias)) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $alias = $stmt->fetchColumn() ?: 'Участник';
}

// Добавляем участника с дополнительными полями
$stmt = $conn->prepare("
    INSERT INTO sprint_participants 
    (sprint_id, user_id, joined_at, participant_type, alias, city, extra_info, links) 
    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
");
$stmt->execute([
    $sprint_id,
    $user_id,
    $participant_type,
    $alias,
    $city,
    $extra_info,
    $links
]);

$newCount = $current + 1;
echo json_encode(['success' => true, 'new_count' => $newCount]);