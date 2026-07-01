<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../s3.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function resp(bool $ok, array $extra = []): never {
    ob_end_clean();
    echo json_encode(['success' => $ok] + $extra);
    exit();
}

function rmdirr(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
        $p = "$dir/$f";
        is_dir($p) ? rmdirr($p) : unlink($p);
    }
    @rmdir($dir);
}

const LARGE_THRESHOLD = 500 * 1024 * 1024;

try {
    // Проверка сессии
    if (empty($_SESSION['USERDATA']['id'])) {
        resp(false, ['message' => 'Нет сессии']);
    }

    // Проверка наличия необходимых констант/классов
    if (!defined('AWS_S3_BUCKET_JAMS')) {
        throw new Exception('Константа AWS_S3_BUCKET_JAMS не определена в config.php');
    }
    if (!class_exists('S3Uploader')) {
        throw new Exception('Класс S3Uploader не найден. Проверьте s3.php');
    }

    $db = (new Database())->connect();

    $sprint_id    = (int)($_POST['sprint_id'] ?? 0);
    $user_id      = (int)$_SESSION['USERDATA']['id'];
    $chunk_index  = (int)($_POST['chunk_index'] ?? 0);
    $total_chunks = (int)($_POST['total_chunks'] ?? 1);
    $file_name    = basename($_POST['file_name'] ?? 'game.zip');
    $file_size    = (int)($_POST['file_size'] ?? 0);

    if (!$sprint_id) resp(false, ['message' => 'sprint_id не передан']);
    if (!isset($_FILES['chunk'])) resp(false, ['message' => 'chunk отсутствует']);
    if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        resp(false, ['message' => 'Ошибка загрузки чанка: ' . $_FILES['chunk']['error']]);
    }

    // Проверка участия в спринте
    $stmt = $db->prepare("SELECT id FROM sprint_participants WHERE sprint_id=? AND user_id=?");
    $stmt->execute([$sprint_id, $user_id]);
    if (!$stmt->fetch()) {
        resp(false, ['message' => 'Вы не участвуете в джеме']);
    }

    $s3 = new S3Uploader(AWS_S3_BUCKET_JAMS);
    $s3_dir = "sprint_{$sprint_id}/user_{$user_id}/build";
    $is_large = $file_size >= LARGE_THRESHOLD;

    if ($is_large) {
        // ... (ваш существующий код для больших файлов) ...
        // Убедитесь, что внутри нет ошибок, и все вызовы $s3->uploadFile обрабатываются
        // Можно оставить как есть, но тоже обернуть в try.
    } else {
        // Малые файлы – сохраняем чанки локально
        $chunk_dir = __DIR__ . '/uploads/chunks/sprint_' . $sprint_id . '_user_' . $user_id;
        if (!is_dir($chunk_dir) && !mkdir($chunk_dir, 0755, true)) {
            throw new Exception('Не удалось создать директорию для чанков: ' . $chunk_dir);
        }

        $chunk_path = $chunk_dir . '/chunk_' . $chunk_index;
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
            throw new Exception('Не удалось сохранить чанк на диск');
        }

        if ($chunk_index < $total_chunks - 1) {
            resp(true, ['done' => false]);
        }

        // Склейка чанков
        $assembled = $chunk_dir . '/build.zip';
        $out = fopen($assembled, 'wb');
        if (!$out) {
            throw new Exception('Не удалось открыть файл для записи склеенного архива');
        }

        for ($i = 0; $i < $total_chunks; $i++) {
            $in = fopen($chunk_dir . '/chunk_' . $i, 'rb');
            if (!$in) {
                fclose($out);
                throw new Exception("Не удалось прочитать чанк $i");
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        $real_size = filesize($assembled);
        if ($real_size != $file_size) {
            throw new Exception("Размер собранного файла ($real_size) не совпадает с ожидаемым ($file_size)");
        }

        $url = $s3->uploadFile($assembled, "{$s3_dir}/game-" . uniqid() . ".zip");
        if (!$url) {
            throw new Exception('Ошибка загрузки финального файла в S3');
        }

        rmdirr($chunk_dir);

        $db->prepare("
            INSERT INTO sprint_submissions (sprint_id, user_id, build_url, build_size)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE
                build_url = VALUES(build_url),
                build_size = VALUES(build_size),
                updated_at = NOW()
        ")->execute([$sprint_id, $user_id, $url, $real_size]);

        resp(true, [
            'done' => true,
            'mode' => 'direct',
            'url' => $url,
            'size_mb' => round($real_size / 1048576, 1)
        ]);
    }
} catch (Throwable $e) {
    error_log('Ошибка в upload_chunk.php: ' . $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine());
    resp(false, ['message' => 'Внутренняя ошибка сервера. Попробуйте позже.']);
}