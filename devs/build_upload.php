<?php
// devs/build_upload.php — multipart-загрузка билда напрямую в S3.
// JSON-роутер: action = init | sign | complete | abort.
// Авторизация по членству в staff (telegram_id) + проверка, что ключ
// принадлежит именно этому проекту (защита от подмены Key).
//
// Поток на клиенте:
//   1) init   -> {upload_id, key, part_size}
//   2) sign   -> presigned URL на каждую часть; браузер PUT'ит части в S3,
//                читает заголовок ETag из ответа
//   3) complete -> отдаёт список {PartNumber, ETag}; сервер финализирует,
//                  пишет games.game_zip_url и ставит VT в очередь
//   (abort — если пользователь отменил/ошибка)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3_multipart.php');

header('Content-Type: application/json; charset=utf-8');

function bu_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bu_out(['success' => false, 'message' => 'Только POST'], 405);

$tgid       = (string)($_SESSION['USERDATA']['telegram_id'] ?? '');
$action     = $_POST['action'] ?? '';
$project_id = (int)($_POST['project_id'] ?? 0);

if ($tgid === '')  bu_out(['success' => false, 'message' => 'Нужна авторизация'], 403);
if (!$project_id)  bu_out(['success' => false, 'message' => 'Не указан проект'], 400);

$conn = (new Database())->connect();

// Проект + студия-владелец.
$g = $conn->prepare("SELECT id, developer FROM games WHERE id = ? LIMIT 1");
$g->execute([$project_id]);
$game = $g->fetch(PDO::FETCH_ASSOC);
if (!$game) bu_out(['success' => false, 'message' => 'Проект не найден'], 404);

// Авторизация: юзер состоит в студии-владельце.
$auth = $conn->prepare("SELECT 1 FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
$auth->execute([(int)$tgid, (int)$game['developer']]);
if (!$auth->fetchColumn()) bu_out(['success' => false, 'message' => 'Нет доступа к этому проекту'], 403);

// Детерминированный префикс ключа — не зависит от имени студии, легко проверяется.
$prefix = 'builds/studio-' . (int)$game['developer'] . '/game-' . (int)$game['id'] . '/';

$mp = new S3Multipart();

try {
    switch ($action) {

        case 'init': {
            $file_name = (string)($_POST['file_name'] ?? 'build.zip');
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) ?: 'zip';
            if (!in_array($ext, ['zip', 'rar', '7z', 'apk'], true)) {
                bu_out(['success' => false, 'message' => 'Допустимы .zip, .rar, .7z, .apk'], 422);
            }
            $ctypes = ['zip' => 'application/zip', 'rar' => 'application/vnd.rar',
                       '7z' => 'application/x-7z-compressed', 'apk' => 'application/vnd.android.package-archive'];
            $key = $prefix . 'build-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $uploadId = $mp->initiate($key, $ctypes[$ext] ?? 'application/octet-stream');
            bu_out([
                'success'   => true,
                'upload_id' => $uploadId,
                'key'       => $key,
                'part_size' => S3Multipart::PART_SIZE,
            ]);
        }

        case 'sign': {
            $key       = (string)($_POST['key'] ?? '');
            $uploadId  = (string)($_POST['upload_id'] ?? '');
            $partsCsv  = (string)($_POST['part_numbers'] ?? '');
            if ($key === '' || $uploadId === '' || $partsCsv === '') bu_out(['success' => false, 'message' => 'Неполный запрос'], 400);
            if (strpos($key, $prefix) !== 0) bu_out(['success' => false, 'message' => 'Ключ не принадлежит проекту'], 403);

            $urls = [];
            foreach (array_filter(array_map('intval', explode(',', $partsCsv))) as $n) {
                if ($n < 1 || $n > 10000) continue;
                $urls[$n] = $mp->presignPart($key, $uploadId, $n);
            }
            bu_out(['success' => true, 'urls' => $urls]);
        }

        case 'complete': {
            $key      = (string)($_POST['key'] ?? '');
            $uploadId = (string)($_POST['upload_id'] ?? '');
            $partsRaw = (string)($_POST['parts'] ?? '');
            $size     = (int)($_POST['file_size'] ?? 0);
            if ($key === '' || $uploadId === '' || $partsRaw === '') bu_out(['success' => false, 'message' => 'Неполный запрос'], 400);
            if (strpos($key, $prefix) !== 0) bu_out(['success' => false, 'message' => 'Ключ не принадлежит проекту'], 403);

            $parts = json_decode($partsRaw, true);
            if (!is_array($parts) || !$parts) bu_out(['success' => false, 'message' => 'Пустой список частей'], 400);

            // нормализуем к [{PartNumber, ETag}]
            $norm = [];
            foreach ($parts as $p) {
                $pn = (int)($p['PartNumber'] ?? $p['partNumber'] ?? 0);
                $et = (string)($p['ETag'] ?? $p['etag'] ?? '');
                if ($pn && $et) $norm[] = ['PartNumber' => $pn, 'ETag' => $et];
            }
            if (!$norm) bu_out(['success' => false, 'message' => 'Части без ETag'], 400);

            $url = $mp->complete($key, $uploadId, $norm);

            // Обновляем проект + ставим VT-скан в очередь (сбрасываем прежний вердикт).
            $conn->prepare("
                UPDATE games
                SET game_zip_url = :url, game_zip_size = :sz,
                    vt_sha256 = NULL, vt_status = 'queued', vt_report_url = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute(['url' => $url, 'sz' => $size, 'id' => $project_id]);

            bu_out(['success' => true, 'url' => $url, 'size' => $size]);
        }

        case 'abort': {
            $key      = (string)($_POST['key'] ?? '');
            $uploadId = (string)($_POST['upload_id'] ?? '');
            if ($key === '' || $uploadId === '') bu_out(['success' => false, 'message' => 'Неполный запрос'], 400);
            if (strpos($key, $prefix) !== 0) bu_out(['success' => false, 'message' => 'Ключ не принадлежит проекту'], 403);
            $mp->abort($key, $uploadId);
            bu_out(['success' => true]);
        }

        default:
            bu_out(['success' => false, 'message' => 'Неизвестное действие'], 400);
    }
} catch (Throwable $e) {
    error_log('build_upload ' . $action . ': ' . $e->getMessage());
    bu_out(['success' => false, 'message' => 'Ошибка S3: ' . $e->getMessage()], 500);
}