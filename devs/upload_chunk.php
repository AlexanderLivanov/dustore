<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/s3.php');
require_once('../swad/controllers/user.php');

header('Content-Type: application/json');
set_time_limit(300);

class ChunkedUpload
{
    private $uploadDir;
    private $db;
    private $s3;

    public function __construct()
    {
        $this->uploadDir = sys_get_temp_dir() . '/chunked_uploads/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        $this->db = new Database();
        $this->s3 = new S3Uploader();
    }

    public function handleRequest()
    {
        if (!isset($_SESSION['STUDIODATA']['id'])) {
            return $this->error('Not authorized');
        }

        if (isset($_POST['finalize'])) {
            return $this->finalizeUpload();
        } else if (isset($_POST['get_status'])) {
            return $this->getUploadStatus();
        } else {
            return $this->uploadChunk();
        }
    }

    private function uploadChunk()
    {
        $projectId = (int)$_POST['project_id'];
        $chunkIndex = (int)$_POST['chunk_index'];
        $totalChunks = (int)$_POST['total_chunks'];
        $fileName = $_POST['file_name'];
        $fileSize = (int)$_POST['file_size'];

        // Валидация
        if ($projectId <= 0 || $chunkIndex < 0 || $totalChunks <= 0) {
            return $this->error('Invalid parameters');
        }

        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('Chunk upload failed');
        }

        // Проверяем права на проект
        if (!$this->validateProjectAccess($projectId)) {
            return $this->error('Project access denied');
        }

        // Сохраняем чанк
        $chunkFile = $this->getChunkFilePath($fileName, $chunkIndex);
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
            return $this->error('Failed to save chunk');
        }

        // Сохраняем метаданные
        $this->saveUploadMetadata($fileName, $chunkIndex, $totalChunks, $projectId);

        return $this->success('Chunk uploaded', [
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'progress' => round(($chunkIndex + 1) / $totalChunks * 100)
        ]);
    }

    private function getUploadStatus()
    {
        $projectId = (int)$_POST['project_id'];
        $fileName = $_POST['file_name'];

        $key = "upload_{$projectId}_" . md5($fileName);
        $metaFile = $this->uploadDir . $key . '.meta';

        if (file_exists($metaFile)) {
            $metadata = json_decode(file_get_contents($metaFile), true);
            $uploadedChunks = 0;

            // Считаем загруженные чанки
            for ($i = 0; $i < $metadata['totalChunks']; $i++) {
                if (file_exists($this->getChunkFilePath($fileName, $i))) {
                    $uploadedChunks++;
                }
            }

            return $this->success('Upload status', [
                'uploaded_chunks' => $uploadedChunks,
                'total_chunks' => $metadata['totalChunks'],
                'file_name' => $metadata['fileName']
            ]);
        }

        return $this->success('No upload found', ['uploaded_chunks' => 0]);
    }

    private function finalizeUpload()
    {
        $projectId = (int)$_POST['project_id'];
        $fileName = $_POST['file_name'];
        $fileSize = (int)$_POST['file_size'];
        $totalChunks = (int)$_POST['total_chunks'];

        // Проверяем, все ли чанки загружены
        if (!$this->verifyAllChunks($fileName, $totalChunks)) {
            return $this->error('Not all chunks uploaded');
        }

        // Собираем файл из чанков
        $finalFile = $this->assembleFile($fileName, $totalChunks);
        if (!$finalFile) {
            return $this->error('Failed to assemble file');
        }

        // Загружаем в S3
        $s3Url = $this->uploadToS3($finalFile, $fileName, $projectId);
        if (!$s3Url) {
            unlink($finalFile);
            return $this->error('Failed to upload to S3');
        }

        // Обновляем БД
        if (!$this->updateDatabase($projectId, $s3Url, $fileSize)) {
            unlink($finalFile);
            return $this->error('Database update failed');
        }

        // Очищаем временные файлы
        $this->cleanup($fileName, $totalChunks);
        unlink($finalFile);

        return $this->success('File uploaded successfully', ['url' => $s3Url]);
    }

    private function assembleFile($fileName, $totalChunks)
    {
        $finalPath = $this->uploadDir . uniqid() . '_' . $fileName;
        $finalHandle = fopen($finalPath, 'wb');

        if (!$finalHandle) {
            return false;
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $this->getChunkFilePath($fileName, $i);
            if (!file_exists($chunkFile)) {
                fclose($finalHandle);
                unlink($finalPath);
                return false;
            }

            $chunkHandle = fopen($chunkFile, 'rb');
            stream_copy_to_stream($chunkHandle, $finalHandle);
            fclose($chunkHandle);
        }

        fclose($finalHandle);
        return $finalPath;
    }

    private function uploadToS3($filePath, $fileName, $projectId)
    {
        // Получаем информацию об организации
        $curr_user = new User();
        $org_info = $curr_user->getOrgData($_SESSION['studio_id']);
        $org_name = $org_info['name'];

        $safe_org_name = preg_replace('/[^a-z0-9]/i', '-', $org_name);
        $safe_file_name = preg_replace('/[^a-z0-9.-]/i', '-', $fileName);
        $s3_path = "{$safe_org_name}/chunked_uploads/{$safe_file_name}-" . uniqid();

        return $this->s3->uploadFile($filePath, $s3_path);
    }

    private function getChunkFilePath($fileName, $chunkIndex)
    {
        $sessionId = session_id();
        $safeName = md5($fileName . $sessionId);
        return $this->uploadDir . "{$safeName}_{$chunkIndex}.part";
    }

    private function saveUploadMetadata($fileName, $chunkIndex, $totalChunks, $projectId)
    {
        $key = "upload_{$projectId}_" . md5($fileName);
        $metadata = [
            'fileName' => $fileName,
            'chunkIndex' => $chunkIndex,
            'totalChunks' => $totalChunks,
            'projectId' => $projectId,
            'lastUpdate' => time()
        ];

        file_put_contents($this->uploadDir . $key . '.meta', json_encode($metadata));
    }

    private function verifyAllChunks($fileName, $totalChunks)
    {
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($this->getChunkFilePath($fileName, $i))) {
                return false;
            }
        }
        return true;
    }

    private function cleanup($fileName, $totalChunks)
    {
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $this->getChunkFilePath($fileName, $i);
            if (file_exists($chunkFile)) {
                unlink($chunkFile);
            }
        }

        // Удаляем метаданные
        $projectId = (int)$_POST['project_id'];
        $key = "upload_{$projectId}_" . md5($fileName);
        $metaFile = $this->uploadDir . $key . '.meta';
        if (file_exists($metaFile)) {
            unlink($metaFile);
        }
    }

    private function validateProjectAccess($projectId)
    {
        $stmt = $this->db->connect()->prepare(
            "SELECT id FROM games WHERE id = ? AND developer = ?"
        );
        $stmt->execute([$projectId, $_SESSION['STUDIODATA']['id']]);
        return (bool)$stmt->fetch();
    }

    private function updateDatabase($projectId, $s3Url, $fileSize)
    {
        // Удаляем старый файл
        $stmt = $this->db->connect()->prepare(
            "SELECT game_zip_url FROM games WHERE id = ?"
        );
        $stmt->execute([$projectId]);
        $oldFile = $stmt->fetchColumn();

        if ($oldFile) {
            $this->s3->deleteFile($oldFile);
        }

        // Обновляем запись
        $stmt = $this->db->connect()->prepare(
            "UPDATE games SET game_zip_url = ?, game_zip_size = ? WHERE id = ?"
        );
        return $stmt->execute([$s3Url, $fileSize, $projectId]);
    }

    private function success($message, $data = [])
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message
        ], $data));
    }

    private function error($message)
    {
        return json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
}

// Обработка запроса
try {
    $uploader = new ChunkedUpload();
    echo $uploader->handleRequest();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
