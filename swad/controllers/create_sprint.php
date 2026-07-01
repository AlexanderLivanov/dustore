<?php
// Включаем буферизацию, чтобы перехватить любой вывод
ob_start();

// Функция для безопасного возврата JSON
function return_json($data) {
    global $ob_content;
    $ob_content = ob_get_clean();
    if (!empty($ob_content)) {
        // Логируем лишний вывод
        error_log("Unexpected output before JSON: " . $ob_content);
    }
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Перехват ошибок
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    return_json(['success' => false, 'message' => "PHP Error: $errstr in $errfile:$errline"]);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
        return_json(['success' => false, 'message' => "Fatal error: {$error['message']}"]);
    }
});

try {
    require_once '../config.php';
    require_once 's3.php';

    $dbInstance = new Database();
    $conn = $dbInstance->connect();
    if (!$conn) {
        return_json(['success' => false, 'message' => 'Ошибка подключения к БД']);
    }

    session_start();
    if (empty($_SESSION['USERDATA']['id'])) {
        return_json(['success' => false, 'message' => 'Авторизуйтесь']);
    }
    $host_user_id = $_SESSION['USERDATA']['id'];

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $theme = trim($_POST['theme'] ?? '');
    $start_at = $_POST['start_at'] ?? '';
    $duration_value = (int)($_POST['duration_value'] ?? 1);
    $duration_unit = $_POST['duration_unit'] ?? 'days';
    $max_participants = (int)($_POST['max_participants'] ?? 100);
    $prizes = json_decode($_POST['prizes'] ?? '[]', true);
    $experts = json_decode($_POST['experts'] ?? '[]', true);

    if (!$title || !$description || !$start_at) {
        return_json(['success' => false, 'message' => 'Заполните название, описание и дату старта']);
    }

    $logo_url = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploader = new S3Uploader();
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $logo_url = $uploader->uploadFile($_FILES['logo']['tmp_name'], 'sprints/' . uniqid('', true) . '.' . $ext);
        if (!$logo_url) {
            return_json(['success' => false, 'message' => 'Ошибка загрузки логотипа']);
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO sprints (
            title, description, logo_url, theme, host_user_id, 
            start_at, duration_value, duration_unit, max_participants, 
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'registration', NOW())
    ");
    $stmt->execute([
        $title, $description, $logo_url, $theme, $host_user_id,
        $start_at, $duration_value, $duration_unit, $max_participants
    ]);
    $sprint_id = $conn->lastInsertId();

    if (!empty($prizes)) {
        $pStmt = $conn->prepare("INSERT INTO sprint_prizes (sprint_id, place_num, reward) VALUES (?, ?, ?)");
        foreach ($prizes as $idx => $p) {
            if (!empty($p['reward'])) {
                $pStmt->execute([$sprint_id, $idx + 1, $p['reward']]);
            }
        }
    }

    if (!empty($experts)) {
        $eStmt = $conn->prepare("INSERT INTO sprint_experts (sprint_id, user_id) VALUES (?, ?)");
        foreach ($experts as $uid) {
            if (!empty($uid)) {
                $eStmt->execute([$sprint_id, (int)$uid]);
            }
        }
    }

    return_json(['success' => true, 'sprint_id' => $sprint_id]);
} catch (Exception $e) {
    return_json(['success' => false, 'message' => 'Исключение: ' . $e->getMessage()]);
}