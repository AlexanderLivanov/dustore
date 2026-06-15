<?php
require_once('../../config.php'); // путь относительно /swad/controllers/jams/

header('Content-Type: application/json');

// Разрешаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Получаем сырые данные
$rawInput = file_get_contents('php://input');
$input = [];

// Пытаемся декодировать JSON
if (!empty($rawInput)) {
    $input = json_decode($rawInput, true);
}

// Если JSON не пришёл, пробуем обычный POST (application/x-www-form-urlencoded)
if (empty($input['jam_id']) && isset($_POST['jam_id'])) {
    $input['jam_id'] = $_POST['jam_id'];
}
if (empty($input['user_id']) && isset($_POST['user_id'])) {
    $input['user_id'] = $_POST['user_id'];
}

// Валидация
$jamId  = isset($input['jam_id'])  ? (int)$input['jam_id']  : 0;
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($jamId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'error' => 'jam_id and user_id are required and must be positive integers',
        'received' => ['jam_id' => $jamId, 'user_id' => $userId],
        'raw' => $rawInput
    ]);
    exit;
}

// Подключаем БД
$db = new Database();
$pdo = $db->connect();

try {
    // Проверяем, существует ли уже связь
    $stmt = $pdo->prepare("SELECT id FROM jam_judges WHERE jam_id = ? AND user_id = ?");
    $stmt->execute([$jamId, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Этот пользователь уже является судьёй']);
        exit;
    }

    // Добавляем запись (без created_at, если столбца нет, уберите)
    $stmt = $pdo->prepare("INSERT INTO jam_judges (jam_id, user_id) VALUES (?, ?)");
    $stmt->execute([$jamId, $userId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}