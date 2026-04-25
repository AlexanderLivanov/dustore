<?php
// 01.09.2025 (c) Alexander Livanov
require_once 'user.php';
require_once '../config.php';

$db = new Database();
$pdo = $db->connect();
$curr_user = new User();

// Получаем количество онлайн
$online_count = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM users
    WHERE last_activity >= NOW() - INTERVAL 5 MINUTE
")->fetchColumn();

// Округляем до часа
$hour = date('Y-m-d H:00:00');

// Записываем в таблицу
$stmt = $pdo->prepare("
    INSERT INTO users_online_history (ts, online_count)
    VALUES (:ts, :count)
    ON DUPLICATE KEY UPDATE online_count = :count
");

$stmt->execute([
    ':ts' => $hour,
    ':count' => $online_count
]);

// Проверяем авторизацию
if ($curr_user->checkAuth() > 0) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if (!isset($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit;
}

$userID = $_SESSION['USERDATA']['id'];

try {
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE users SET last_activity = :last_activity WHERE id = :user_id");
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

function updateDailyStats($pdo)
{
    $today = date('Y-m-d');

    $exists = $pdo->prepare("SELECT id FROM daily_stats WHERE date = ?");
    $exists->execute([$today]);
    if ($exists->rowCount() <= 0) {
        $users_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $studios_total = $pdo->query("SELECT COUNT(*) FROM studios")->fetchColumn();
        $games_total = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
        $published_total = $pdo->query("SELECT COUNT(*) FROM games WHERE status='published'")->fetchColumn();

        $users_new = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(added) = '$today'")->fetchColumn();
        $studios_new = $pdo->query("SELECT COUNT(*) FROM studios WHERE DATE(created_at) = '$today'")->fetchColumn();
        $games_new = $pdo->query("SELECT COUNT(*) FROM games WHERE DATE(created_at) = '$today'")->fetchColumn();
        $published_new = $pdo->query("SELECT COUNT(*) FROM games WHERE status='published' AND DATE(created_at)='$today'")->fetchColumn();

        $insert = $pdo->prepare("
            INSERT INTO daily_stats (
                date,
                users_total, users_new,
                studios_total, studios_new,
                games_total, games_new,
                published_total, published_new
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $today,
            $users_total,
            $users_new,
            $studios_total,
            $studios_new,
            $games_total,
            $games_new,
            $published_total,
            $published_new
        ]);
    }
}

updateDailyStats($pdo);
