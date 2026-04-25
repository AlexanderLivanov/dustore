<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
require_once('/swad/config.php');

session_write_close(); // чтоб не блокировать сессии

$uid = $_SESSION['USERDATA']['id'] ?? 0;

$db = new Database();
$pdo = $db->connect();

// Каждый 2 секунды проверяем новые уведомления
while (true) {
    $stmt = $pdo->prepare("SELECT id, title, message FROM notifications 
                           WHERE user_id = ? AND is_sent = 0
                           ORDER BY id ASC LIMIT 1");
    $stmt->execute([$uid]);
    $n = $stmt->fetch();

    if ($n) {
        echo "data: " . json_encode($n) . "\n\n";
        ob_flush();
        flush();

        // помечаем уведомление как отправленное
        $pdo->prepare("UPDATE notifications SET is_sent = 1 WHERE id = ?")
            ->execute([$n['id']]);
    }

    sleep(2);
}
