<?php
// devs/build_upload.php — серверная загрузка билда: браузер шлёт чанки сюда,
// PHP склеивает их в ОДИН файл и кладёт одним объектом в S3 (без CORS, без manifest).
// Сохраняет плюсы: один .zip на выходе, лок приёма по джему, постановка VT в очередь.
//
// POST (multipart): chunk, chunk_index, total_chunks, file_name, file_size, project_id
// Ответ: { success, done, url?, size_mb?, message? }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3.php');

ini_set('display_errors', '0');
set_time_limit(0);
ignore_user_abort(true);
header('Content-Type: application/json; charset=utf-8');

function bu_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit();
}
function bu_rmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
        $p = "$dir/$f"; is_dir($p) ? bu_rmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

if (empty($_SESSION['USERDATA'])) bu_out(['success' => false, 'message' => 'Нет сессии — обновите страницу'], 403);

$project_id   = (int)($_POST['project_id']   ?? 0);
$chunk_index  = (int)($_POST['chunk_index']  ?? 0);
$total_chunks = max(1, (int)($_POST['total_chunks'] ?? 1));
$file_name    = basename((string)($_POST['file_name'] ?? 'game.zip'));
$file_size    = (int)($_POST['file_size'] ?? 0);
$studio_id    = (int)($_SESSION['studio_id'] ?? 0);

if (!$project_id)             bu_out(['success' => false, 'message' => 'project_id не передан']);
if (!isset($_FILES['chunk'])) bu_out(['success' => false, 'message' => 'Чанк не получен']);
if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $map = [1 => 'Чанк больше upload_max_filesize (увеличь в php.ini до ~10M)', 3 => 'Чанк загружен частично — плохое соединение', 7 => 'Нет прав на запись во временную папку'];
    bu_out(['success' => false, 'message' => $map[$_FILES['chunk']['error']] ?? ('PHP upload error ' . $_FILES['chunk']['error'])]);
}

$conn = (new Database())->connect();

// Проект должен принадлежать студии.
$g = $conn->prepare("SELECT id, name, developer, sprint_id, revision_until, game_zip_url FROM games WHERE id = ? AND developer = ? LIMIT 1");
$g->execute([$project_id, $studio_id]);
$game = $g->fetch(PDO::FETCH_ASSOC);
if (!$game) bu_out(['success' => false, 'message' => "Проект #{$project_id} не найден или не принадлежит вашей студии"], 404);

// Лок приёма по джему проверяем один раз, на первом чанке.
if ($chunk_index === 0 && !empty($game['sprint_id'])) {
    $js = $conn->prepare("SELECT jam_end FROM sprints WHERE id = ? LIMIT 1");
    $js->execute([(int)$game['sprint_id']]);
    $jamEnd = $js->fetchColumn();
    $closed = $jamEnd && strtotime($jamEnd) <= time();
    $revisionOpen = !empty($game['revision_until']) && strtotime($game['revision_until']) > time();
    if ($closed && !$revisionOpen) {
        bu_out(['success' => false, 'message' => 'Приём билдов для этого джема закрыт (кроме окна права на ошибку).'], 409);
    }
}

// Временная папка под чанки проекта.
$dir = __DIR__ . '/uploads/chunks/pid_' . $project_id;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    bu_out(['success' => false, 'message' => "Не удалось создать папку {$dir}"]);
}
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $dir . '/chunk_' . $chunk_index)) {
    bu_out(['success' => false, 'message' => "Не удалось сохранить чанк {$chunk_index}"]);
}

// Не последний чанк — ждём остальные.
if ($chunk_index < $total_chunks - 1) {
    bu_out(['success' => true, 'done' => false, 'chunk' => $chunk_index]);
}

// Последний чанк: проверяем все части и склеиваем в один файл.
for ($i = 0; $i < $total_chunks; $i++) {
    if (!file_exists($dir . '/chunk_' . $i)) {
        bu_rmdir($dir);
        bu_out(['success' => false, 'message' => "Потерян чанк {$i} — начните загрузку заново"]);
    }
}
$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) ?: 'zip';
if (!in_array($ext, ['zip', 'rar', '7z', 'apk'], true)) $ext = 'zip';

$assembled = $dir . '/assembled.' . $ext;
$out = fopen($assembled, 'wb');
if (!$out) { bu_rmdir($dir); bu_out(['success' => false, 'message' => 'Не удалось создать файл сборки']); }
for ($i = 0; $i < $total_chunks; $i++) {
    $in = fopen($dir . '/chunk_' . $i, 'rb');
    if ($in) { stream_copy_to_stream($in, $out); fclose($in); }
    @unlink($dir . '/chunk_' . $i);
}
fclose($out);
$real_size = filesize($assembled);

// Один объект в S3.
$key = 'builds/studio-' . (int)$game['developer'] . '/game-' . (int)$game['id'] . '/build-' . bin2hex(random_bytes(6)) . '.' . $ext;
$s3  = new S3Uploader();
$url = $s3->uploadFile($assembled, $key);
bu_rmdir($dir);

if (!$url) bu_out(['success' => false, 'message' => 'S3 не принял файл — проверьте error_log Apache']);

// Старый билд с S3 удалим (если был и отличается).
$oldUrl = $game['game_zip_url'] ?? '';
if ($oldUrl && $oldUrl !== $url) {
    try { $s3->deleteFile($oldUrl); } catch (\Throwable $e) { error_log('old build delete: ' . $e->getMessage()); }
}

// Обновляем проект + ставим VT-скан в очередь.
$conn->prepare("
    UPDATE games
    SET game_zip_url = :url, game_zip_size = :sz,
        vt_sha256 = NULL, vt_status = 'queued', vt_report_url = NULL,
        updated_at = NOW()
    WHERE id = :id
")->execute(['url' => $url, 'sz' => $real_size, 'id' => $project_id]);

$conn->prepare("UPDATE games SET vt_status='queued' WHERE id=?")->execute([$game_id]);

bu_out(['success' => true, 'done' => true, 'url' => $url, 'size_mb' => round($real_size / 1048576, 1)]);