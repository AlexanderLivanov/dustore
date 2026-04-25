<?php
// 17.09.2025 (c) Alexander Livanov
require_once 'user.php';
require_once 'swad/config.php';

function getUserLastActivity($userId) {
    $db = new Database();
    $pdo = $db->connect();
    $sessionTimeout = 300;

    try {
        $sql = "SELECT updated FROM users WHERE telegram_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $lastActivity = $result['updated'];
            return $lastActivity;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Ошибка проверки активности: " . $e->getMessage());
        return false;
    }
}