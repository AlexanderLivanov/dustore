<?php
ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);
set_time_limit(0);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function resp(bool $ok, array $extra=[]): never {
    ob_end_clean();
    echo json_encode(['success'=>$ok] + $extra);
    exit();
}

function rmdirr(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
        $p = "$dir/$f";
        is_dir($p) ? rmdirr($p) : unlink($p);
    }
    @rmdir($dir);
}

const LARGE_THRESHOLD = 500 * 1024 * 1024;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../s3.php';

$db = (new Database())->connect();

if (empty($_SESSION['USERDATA']['id'])) {
    resp(false,['message'=>'Нет сессии']);
}

$sprint_id    = (int)($_POST['sprint_id'] ?? 0);
$user_id      = (int)$_SESSION['USERDATA']['id'];
$chunk_index  = (int)($_POST['chunk_index'] ?? 0);
$total_chunks = (int)($_POST['total_chunks'] ?? 1);
$file_name    = basename($_POST['file_name'] ?? 'game.zip');
$file_size    = (int)($_POST['file_size'] ?? 0);

if (!$sprint_id) resp(false,['message'=>'sprint_id не передан']);
if (!isset($_FILES['chunk'])) resp(false,['message'=>'chunk отсутствует']);

$stmt = $db->prepare("
    SELECT id
    FROM sprint_participants
    WHERE sprint_id=? AND user_id=?
");
$stmt->execute([$sprint_id,$user_id]);

if (!$stmt->fetch()) {
    resp(false,['message'=>'Вы не участвуете в джеме']);
}

$s3 = new S3Uploader(AWS_S3_BUCKET_JAMS);

$s3_dir = "sprint_{$sprint_id}/user_{$user_id}/build";

$is_large = $file_size >= LARGE_THRESHOLD;

if ($is_large) {

    $sess_key = "jam_upload_{$sprint_id}_{$user_id}";

    if ($chunk_index === 0) {
        $_SESSION[$sess_key] = [
            'chunks' => [],
            'file_name' => $file_name,
            'file_size' => $file_size
        ];
    }

    $tmp = $_FILES['chunk']['tmp_name'];

    $chunk_filename = sprintf('chunk_%04d.bin', $chunk_index);
    $chunk_key = "{$s3_dir}/chunks/{$chunk_filename}";

    $chunk_url = $s3->uploadFile($tmp, $chunk_key);

    if (!$chunk_url) {
        resp(false,['message'=>'Ошибка загрузки чанка']);
    }

    $_SESSION[$sess_key]['chunks'][$chunk_index] = [
        'index' => $chunk_index,
        'filename' => $chunk_filename,
        's3_key' => $chunk_key,
        'size' => filesize($tmp),
        'sha256' => hash_file('sha256', $tmp)
    ];

    if ($chunk_index < $total_chunks - 1) {
        resp(true,['done'=>false]);
    }

    $chunks = $_SESSION[$sess_key]['chunks'];
    ksort($chunks);

    $manifest = [
        'version' => 1,
        'type' => 'chunked',
        'created_at' => gmdate('c'),
        'original_filename' => $file_name,
        'total_size' => $file_size,
        'chunk_count' => count($chunks),
        'chunks' => array_values($chunks)
    ];

    $tmp_manifest = tempnam(sys_get_temp_dir(),'manifest');
    file_put_contents(
        $tmp_manifest,
        json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
    );

    $manifest_url = $s3->uploadFile(
        $tmp_manifest,
        "{$s3_dir}/manifest.json"
    );

    @unlink($tmp_manifest);

    $db->prepare("
        INSERT INTO sprint_submissions
        (sprint_id,user_id,build_url,build_size)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE
            build_url=VALUES(build_url),
            build_size=VALUES(build_size),
            updated_at=NOW()
    ")->execute([
        $sprint_id,
        $user_id,
        $manifest_url,
        $file_size
    ]);

    unset($_SESSION[$sess_key]);

    resp(true,[
        'done'=>true,
        'mode'=>'chunked',
        'url'=>$manifest_url,
        'size_mb'=>round($file_size/1048576,1)
    ]);
}

$chunk_dir = __DIR__ . '/uploads/chunks/sprint_' . $sprint_id . '_user_' . $user_id;

if (!is_dir($chunk_dir)) {
    mkdir($chunk_dir,0755,true);
}

move_uploaded_file(
    $_FILES['chunk']['tmp_name'],
    $chunk_dir . '/chunk_' . $chunk_index
);

if ($chunk_index < $total_chunks - 1) {
    resp(true,['done'=>false]);
}

$assembled = $chunk_dir . '/build.zip';

$out = fopen($assembled,'wb');

for ($i=0;$i<$total_chunks;$i++) {
    $in = fopen($chunk_dir . '/chunk_' . $i,'rb');
    stream_copy_to_stream($in,$out);
    fclose($in);
}

fclose($out);

$real_size = filesize($assembled);

$url = $s3->uploadFile(
    $assembled,
    "{$s3_dir}/game-" . uniqid() . ".zip"
);

rmdirr($chunk_dir);

$db->prepare("
    INSERT INTO sprint_submissions
    (sprint_id,user_id,build_url,build_size)
    VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE
        build_url=VALUES(build_url),
        build_size=VALUES(build_size),
        updated_at=NOW()
")->execute([
    $sprint_id,
    $user_id,
    $url,
    $real_size
]);

resp(true,[
    'done'=>true,
    'mode'=>'direct',
    'url'=>$url,
    'size_mb'=>round($real_size/1048576,1)
]);
