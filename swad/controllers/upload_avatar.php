<?php
session_start();
require_once('../config.php');
require_once('../controllers/user.php');
require_once __DIR__ .'/../../vendor/autoload.php';

$curr_user = new User();
$user_id = $_SESSION['USERDATA']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    if ($file['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки']);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Неподдерживаемый тип файла']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Файл слишком большой']);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = 'avatars/' . $user_id . '_' . time() . '.' . $ext;

    // Загрузка в S3
    $s3 = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => AWS_S3_REGION,
        'endpoint' => AWS_S3_ENDPOINT,
        'credentials' => [
            'key' => AWS_S3_KEY,
            'secret' => AWS_S3_SECRET,
        ],
    ]);

    try {
        $result = $s3->putObject([
            'Bucket' => AWS_S3_BUCKET_USERCONTENT,
            'Key' => $newFileName,
            'SourceFile' => $file['tmp_name'],
            'ACL' => 'public-read',
            'ContentType' => $file['type']
        ]);

        $url = $result['ObjectURL'];

        // Обновляем в БД
        $db = new Database();
        $stmt = $db->connect()->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->execute([$url, $user_id]);

        $_SESSION['USERDATA']['profile_picture'] = $url;

        echo json_encode(['success' => true, 'url' => $url]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
