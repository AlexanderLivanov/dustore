<?php
// upload_zip.php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/s3.php');
require_once('../swad/controllers/user.php');

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Быстрая проверка авторизации
if (!isset($_SESSION['STUDIODATA']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

try {
    // Получаем данные
    $project_id = (int)$_POST['project_id'];
    $project_name = $_POST['project_name'] ?? '';

    if ($project_id <= 0) {
        throw new Exception('Invalid project ID');
    }

    // Проверяем файл
    if (!isset($_FILES['game_zip']) || $_FILES['game_zip']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    $file = $_FILES['game_zip'];

    // Проверяем тип файла
    $mime = mime_content_type($file['tmp_name']);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($mime !== 'application/zip' && $extension !== 'zip') {
        throw new Exception('Only ZIP files are allowed');
    }

    // Получаем информацию о проекте
    $db = new Database();
    $stmt = $db->connect()->prepare("SELECT * FROM games WHERE id = ? AND developer = ?");
    $stmt->execute([$project_id, $_SESSION['STUDIODATA']['id']]);
    $project_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($project_info)) {
        throw new Exception('Project not found');
    }

    // Получаем информацию об организации
    $curr_user = new User();
    $org_info = $curr_user->getOrgData($_SESSION['studio_id']);
    $org_name = $org_info['name'];

    // Удаляем старый ZIP
    $s3Uploader = new S3Uploader();
    $current_zip_url = $project_info['game_zip_url'] ?? '';

    if (!empty($current_zip_url)) {
        $s3Uploader->deleteFile($current_zip_url);
    }

    // Генерируем новый путь
    $safe_org_name = preg_replace('/[^a-z0-9]/i', '-', $org_name);
    $safe_project_name = preg_replace('/[^a-z0-9]/i', '-', $project_name);
    $zip_path = "{$safe_org_name}/{$safe_project_name}/game-" . uniqid() . ".zip";

    // Загружаем новый ZIP
    $uploaded_zip = $s3Uploader->uploadFile($file['tmp_name'], $zip_path);

    if (!$uploaded_zip) {
        throw new Exception('Failed to upload to S3 storage');
    }

    // Обновляем БД
    $sql = "UPDATE games SET game_zip_url = :zip_url, game_zip_size = :zip_size WHERE id = :id";
    $stmt = $db->connect()->prepare($sql);
    $zip_size = $file['size'];
    $stmt->bindParam(':zip_url', $uploaded_zip);
    $stmt->bindParam(':zip_size', $zip_size);
    $stmt->bindParam(':id', $project_id);

    if (!$stmt->execute()) {
        throw new Exception('Database update failed');
    }

    echo json_encode([
        'success' => true,
        'message' => 'ZIP archive successfully uploaded',
        'url' => $uploaded_zip
    ]);
} catch (Exception $e) {
    error_log("ZIP Upload Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
