<?php
// upload_zip_progress.php - упрощенная версия с реальными этапами
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/s3.php');
require_once('../swad/controllers/user.php');

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

function sendStage($percent, $message)
{
    echo json_encode(['stage' => $percent, 'message' => $message]);
    flush();
    usleep(500000); // 0.5 сек задержка между этапами для демонстрации
}

try {
    sendStage(10, 'Проверка авторизации');

    if (!isset($_SESSION['STUDIODATA']['id'])) {
        throw new Exception('Not authorized');
    }

    sendStage(20, 'Проверка файла');
    // ... остальная логика с вызовами sendStage()

    sendStage(100, 'Завершено');
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
