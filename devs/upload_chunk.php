<?php
/**
 * devs/upload_chunk.php
 *
 * Два режима в зависимости от размера файла:
 *
 * SMALL (< 500 МБ):
 *   Чанки по 5 МБ → временная папка → сборка → один ZIP на S3
 *   DB: game_zip_url = URL зипа, game_zip_manifest = NULL
 *
 * LARGE (≥ 500 МБ):
 *   Чанки по 50 МБ → каждый сразу на S3 как chunk_NNNN.bin + SHA256
 *   Последний чанк → manifest.json на S3
 *   DB: game_zip_url = URL манифеста, game_zip_manifest = 'chunked'
 */

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal: ' . $err['message']
                       . ' @ ' . basename($err['file']) . ':' . $err['line'],
        ]);
    }
});

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

/* ── helpers ──────────────────────────────────────────────────────────── */
function resp(bool $ok, array $extra = []): never {
    ob_end_clean();
    echo json_encode(['success' => $ok] + $extra);
    exit();
}

function rmdirr(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
        $p = "$dir/$f";
        is_dir($p) ? rmdirr($p) : unlink($p);
    }
    rmdir($dir);
}

const LARGE_THRESHOLD = 500 * 1024 * 1024; // 500 МБ

/* ── Auth + параметры ─────────────────────────────────────────────────── */
if (empty($_SESSION['USERDATA'])) resp(false, ['message' => 'Нет сессии — обновите страницу']);

$project_id   = (int)($_POST['project_id']   ?? 0);
$chunk_index  = (int)($_POST['chunk_index']  ?? 0);
$total_chunks = (int)($_POST['total_chunks'] ?? 1);
$file_name    = basename($_POST['file_name'] ?? 'game.zip');
$file_size    = (int)($_POST['file_size']    ?? 0);
$file_type    = $_POST['file_type'] ?? 'zip';   // 'zip' | 'apk'
$studio_id    = (int)($_SESSION['studio_id'] ?? 0);
$is_apk       = ($file_type === 'apk');
// APK всегда прямая загрузка (SMALL MODE), игнорируем порог размера
$is_large     = !$is_apk && $file_size >= LARGE_THRESHOLD;

if (!$project_id)             resp(false, ['message' => 'project_id не передан']);
if (!isset($_FILES['chunk'])) resp(false, ['message' => 'Поле chunk не найдено в запросе']);

$uerr = $_FILES['chunk']['error'];
if ($uerr !== UPLOAD_ERR_OK) {
    $map = [
        1 => 'Чанк > upload_max_filesize. Откройте C:\xampp\php\php.ini → upload_max_filesize=55M, post_max_size=60M → рестарт Apache',
        3 => 'Чанк загружен частично — нестабильное соединение, попробуйте снова',
        6 => 'PHP не может найти системную tmp папку',
        7 => 'Нет прав на запись во временную папку',
    ];
    resp(false, ['message' => $map[$uerr] ?? "PHP upload error: {$uerr}"]);
}

/* ── Подключаем зависимости ───────────────────────────────────────────── */
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    // Пробуем альтернативные пути
    $alternatives = [
        __DIR__ . '/../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    foreach ($alternatives as $alt) {
        if (file_exists($alt)) { $autoload = $alt; break; }
    }
    if (!file_exists($autoload)) {
        resp(false, ['message' => 'vendor/autoload.php не найден. Пути проверены: ' . __DIR__ . '/../vendor/']);
    }
}
require_once $autoload;
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/s3.php';

// Проверяем константы S3
foreach (['AWS_S3_KEY','AWS_S3_SECRET','AWS_S3_REGION','AWS_S3_ENDPOINT','AWS_S3_BUCKET_USERCONTENT'] as $c) {
    if (!defined($c) || !constant($c)) resp(false, ['message' => "Константа {$c} не задана в config.php"]);
}

$s3 = new S3Uploader();

/* ── БД: проверяем проект ─────────────────────────────────────────────── */
try {
    $db   = (new Database())->connect();
    $stmt = $db->prepare("SELECT developer, name FROM games WHERE id=? AND developer=?");
    $stmt->execute([$project_id, $studio_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    resp(false, ['message' => 'Ошибка БД: ' . $e->getMessage()]);
}
if (!$game) resp(false, ['message' => "Проект #{$project_id} не найден или не принадлежит вашей студии"]);

$org    = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
$gname  = preg_replace('/[^a-z0-9]/i', '-', $game['name']);
$s3_dir = "{$org}/{$gname}";

/* ════════════════════════════════════════════════════════════════════════
   LARGE MODE (≥ 500 МБ): каждый чанк сразу на S3
   ════════════════════════════════════════════════════════════════════════ */
if ($is_large) {
    $sess_key = "upload_large_{$project_id}";

    // Инициализируем состояние на первом чанке
    if ($chunk_index === 0) {
        $_SESSION[$sess_key] = [
            'chunks'    => [],
            'file_name' => $file_name,
            'file_size' => $file_size,
            's3_dir'    => $s3_dir,
        ];
    }

    if (empty($_SESSION[$sess_key])) {
        resp(false, ['message' => 'Сессия загрузки потеряна — начните заново (не обновляйте страницу во время загрузки)']);
    }

    // Читаем чанк во временный файл для хеширования
    $tmp = $_FILES['chunk']['tmp_name'];

    // SHA256 чанка
    $sha256 = hash_file('sha256', $tmp);
    $chunk_size = filesize($tmp);

    // Имя файла чанка на S3
    $chunk_filename = sprintf('chunk_%04d.bin', $chunk_index);
    $s3_chunk_key   = "{$s3_dir}/chunks/{$chunk_filename}";

    // Загружаем чанк на S3
    $chunk_url = $s3->uploadFile($tmp, $s3_chunk_key);
    if (!$chunk_url) {
        resp(false, ['message' => "Не удалось загрузить чанк {$chunk_index} на S3. Проверьте error_log Apache."]);
    }

    // Записываем в сессию
    $_SESSION[$sess_key]['chunks'][$chunk_index] = [
        'index'    => $chunk_index,
        'filename' => $chunk_filename,
        's3_key'   => $s3_chunk_key,
        'size'     => $chunk_size,
        'sha256'   => $sha256,
    ];
    session_write_close();

    // Не последний чанк
    if ($chunk_index < $total_chunks - 1) {
        resp(true, [
            'done'   => false,
            'chunk'  => $chunk_index,
            'sha256' => $sha256,
        ]);
    }

    // ── Последний чанк: собираем манифест и заливаем на S3 ───────────────
    session_start();
    $state  = $_SESSION[$sess_key];
    $chunks = $state['chunks'];
    ksort($chunks); // сортируем по индексу

    $manifest = [
        'version'           => 1,
        'type'              => 'chunked',
        'game_id'           => $project_id,
        'created_at'        => gmdate('c'),
        'original_filename' => $state['file_name'],
        'total_size'        => $state['file_size'],
        'chunk_count'       => count($chunks),
        'chunks'            => array_values($chunks),
    ];

    $manifest_json    = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $manifest_tmp     = tempnam(sys_get_temp_dir(), 'manifest_');
    file_put_contents($manifest_tmp, $manifest_json);

    $manifest_s3_key = "{$s3_dir}/manifest.json";
    $manifest_url    = $s3->uploadFile($manifest_tmp, $manifest_s3_key);
    @unlink($manifest_tmp);

    if (!$manifest_url) {
        resp(false, ['message' => 'Не удалось загрузить manifest.json на S3']);
    }

    // Удаляем старый ZIP/манифест если был
    $old = $db->prepare("SELECT game_zip_url FROM games WHERE id=?");
    $old->execute([$project_id]);
    $old_url = $old->fetchColumn();
    if ($old_url) {
        try { $s3->deleteFile($old_url); } catch (Exception $e) {}
    }

    // Сохраняем в БД
    $db->prepare("
        UPDATE games
        SET game_zip_url=?, game_zip_size=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$manifest_url, $file_size, $project_id]);

    // Чистим сессию
    unset($_SESSION[$sess_key]);
    session_write_close();

    resp(true, [
        'done'         => true,
        'mode'         => 'chunked',
        'manifest_url' => $manifest_url,
        'chunk_count'  => count($chunks),
        'size_mb'      => round($file_size / 1048576, 1),
    ]);
}

/* ════════════════════════════════════════════════════════════════════════
   SMALL MODE (< 500 МБ): собираем локально → один ZIP на S3
   ════════════════════════════════════════════════════════════════════════ */
$chunk_dir = __DIR__ . '/uploads/chunks/pid_' . $project_id;

if (!is_dir($chunk_dir) && !mkdir($chunk_dir, 0755, true)) {
    resp(false, ['message' => "Не удалось создать папку {$chunk_dir}. Создайте вручную: devs\\uploads\\chunks\\"]);
}

$dest = $chunk_dir . '/chunk_' . $chunk_index;
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $dest)) {
    resp(false, ['message' => "move_uploaded_file failed для чанка {$chunk_index}"]);
}

// Не последний
if ($chunk_index < $total_chunks - 1) {
    resp(true, ['done' => false, 'chunk' => $chunk_index]);
}

// Проверяем все части
for ($i = 0; $i < $total_chunks; $i++) {
    if (!file_exists($chunk_dir . '/chunk_' . $i)) {
        resp(false, ['message' => "Отсутствует чанк {$i} — начните загрузку заново"]);
    }
}

// Собираем
$assembled = $chunk_dir . '/assembled.zip';
$out = fopen($assembled, 'wb');
if (!$out) resp(false, ['message' => "Не удалось создать assembled.zip"]);

for ($i = 0; $i < $total_chunks; $i++) {
    $part = $chunk_dir . '/chunk_' . $i;
    $in   = fopen($part, 'rb');
    stream_copy_to_stream($in, $out);
    fclose($in);
    unlink($part);
}
fclose($out);

$real_size = filesize($assembled);

// ZIP-валидация (APK пропускаем)
if (!$is_apk) {
    $fh    = fopen($assembled, 'rb');
    $magic = fread($fh, 4);
    fclose($fh);
    if (substr($magic, 0, 2) !== 'PK') {
        @unlink($assembled);
        resp(false, ['message' => 'Файл не является ZIP (magic: ' . bin2hex($magic) . '). Попробуйте снова.']);
    }
}

// Загружаем на S3
$old = $db->prepare("SELECT game_zip_url FROM games WHERE id=?");
$old->execute([$project_id]);
$old_url = $old->fetchColumn();
if ($old_url) {
    try { $s3->deleteFile($old_url); } catch (Exception $e) {}
}

$ext    = $is_apk ? '.apk' : '.zip';
$s3_key = "{$s3_dir}/game-" . uniqid() . $ext;
$url    = $s3->uploadFile($assembled, $s3_key);
rmdirr($chunk_dir);

if (!$url) {
    resp(false, ['message' => 'S3::uploadFile() вернул false. Проверьте error_log Apache.']);
}

$db->prepare("UPDATE games SET game_zip_url=?, game_zip_size=?, updated_at=NOW() WHERE id=?")
   ->execute([$url, $real_size, $project_id]);

resp(true, [
    'done'    => true,
    'mode'    => $is_apk ? 'apk' : 'direct',
    'url'     => $url,
    'size_mb' => round($real_size / 1048576, 1),
]);