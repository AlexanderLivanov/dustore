<?php
// devs/build_upload.php — серверная загрузка билда: браузер шлёт чанки сюда,
// PHP склеивает их в ОДИН файл и кладёт одним объектом в S3 (без CORS, без manifest).
// Сохраняет плюсы: один файл на выходе, лок приёма по джему, постановка VT в очередь.
//
// С мультиплатформенными билдами (game_builds): каждая платформа грузится своим
// вызовом с platform=Windows|macOS|Linux|Android|iOS|Web и попадает СВОЕЙ строкой
// в game_builds (по одной на платформу, апсертом). Публичные раздатчики
// (download_game.php, download_apk.php, webplayer.php) платформы не знают и
// продолжают читать games.game_zip_url/game_zip_size — поэтому каждый успешный
// апload ЗЕРКАЛИТСЯ и туда же, как и раньше: последний загруженный билд остаётся
// «активным» для скачивания/веб-плеера, независимо от того, для какой платформы
// его загрузили. Раздача разных файлов разным платформам одновременно — отдельная
// следующая итерация (нужно трогать все три раздатчика + воркер VT-скана).
//
// POST (multipart): chunk, chunk_index, total_chunks, file_name, file_size, project_id, platform
// Ответ: { success, done, url?, size_mb?, platform?, message? }

const BU_PLATFORMS = ['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Web'];

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
$platform     = (string)($_POST['platform'] ?? '');

if (!$project_id)             bu_out(['success' => false, 'message' => 'project_id не передан']);
if (!in_array($platform, BU_PLATFORMS, true)) bu_out(['success' => false, 'message' => 'Некорректная платформа']);
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

// Временная папка под чанки проекта — своя на каждую платформу, чтобы параллельная
// загрузка в соседнюю вкладку (или повтор после обрыва связи) не мешала этой.
$dir = __DIR__ . '/uploads/chunks/pid_' . $project_id . '_' . strtolower($platform);
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

// Один объект в S3, путь включает платформу.
$key = 'builds/studio-' . (int)$game['developer'] . '/game-' . (int)$game['id'] . '/' . strtolower($platform) . '/build-' . bin2hex(random_bytes(6)) . '.' . $ext;
$s3  = new S3Uploader();
$url = $s3->uploadFile($assembled, $key);
bu_rmdir($dir);

if (!$url) bu_out(['success' => false, 'message' => 'S3 не принял файл — проверьте error_log Apache']);

// Старый билд ЭТОЙ платформы с S3 удалим (если был и отличается).
$oldBuild = $conn->prepare("SELECT build_url FROM game_builds WHERE game_id = ? AND platform = ? LIMIT 1");
$oldBuild->execute([$project_id, $platform]);
$oldPlatformUrl = $oldBuild->fetchColumn();
if ($oldPlatformUrl && $oldPlatformUrl !== $url) {
    try { $s3->deleteFile($oldPlatformUrl); } catch (\Throwable $e) { error_log('old build delete: ' . $e->getMessage()); }
}

// game_builds — своя строка на платформу, апсерт.
$conn->prepare("
    INSERT INTO game_builds (game_id, platform, build_url, build_size)
    VALUES (:gid, :pl, :url, :sz)
    ON DUPLICATE KEY UPDATE build_url = VALUES(build_url), build_size = VALUES(build_size), updated_at = NOW()
")->execute(['gid' => $project_id, 'pl' => $platform, 'url' => $url, 'sz' => $real_size]);

// Зеркалим в games — это то, что реально отдают download_game.php / download_apk.php /
// webplayer.php, они про платформы ничего не знают. Последний загруженный билд (с
// любой вкладки) становится активным для скачивания — ровно то же поведение, что
// было и до мультиплатформенности, когда билд был вообще один.
$oldLegacyUrl = $game['game_zip_url'] ?? '';
$conn->prepare("
    UPDATE games
    SET game_zip_url = :url, game_zip_size = :sz,
        vt_sha256 = NULL, vt_status = 'queued', vt_report_url = NULL,
        updated_at = NOW()
    WHERE id = :id
")->execute(['url' => $url, 'sz' => $real_size, 'id' => $project_id]);
if ($oldLegacyUrl && $oldLegacyUrl !== $url && $oldLegacyUrl !== $oldPlatformUrl) {
    try { $s3->deleteFile($oldLegacyUrl); } catch (\Throwable $e) { error_log('old legacy build delete: ' . $e->getMessage()); }
}

bu_out(['success' => true, 'done' => true, 'url' => $url, 'platform' => $platform, 'size_mb' => round($real_size / 1048576, 1)]);