<?php
// 01.09.2025 (c) Alexander Livanov
require_once 'user.php';
require_once '../config.php';

$db = new Database();
$pdo = $db->connect();
$curr_user = new User();

// Проверяем авторизацию
if ($curr_user->checkAuth() > 0) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if (!isset($_SESSION['USERDATA']['telegram_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit;
}

$userID = $_SESSION['USERDATA']['telegram_id'];

try {
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE users SET last_activity = :last_activity WHERE telegram_id = :user_id");
    $stmt->bindParam(':last_activity', $currentTime);
    $stmt->bindParam(':user_id', $userID, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->execute()) {
        $_SESSION['USERDATA']['last_activity'] = $currentTime;

        echo json_encode(['success' => true, 'last_activity' => $currentTime]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
} catch (PDOException $e) {
    echo ("Error updating user activity: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>