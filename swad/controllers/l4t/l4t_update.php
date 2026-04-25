<?php
session_start();
require_once('../../config.php');

header('Content-Type: application/json');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'msg' => 'not authenticated']);
    exit;
}

$userId = (int)$_SESSION['USERDATA']['id'];

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['type'])) {
    echo json_encode(['success' => false, 'msg' => 'no type']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

switch ($data['type']) {

    // ── ФАЙЛЫ / ССЫЛКИ ────────────────────────────────────────
    case 'files':
        $files = array_slice($data['data'] ?? [], 0, 20);
        $clean = [];
        foreach ($files as $f) {
            $type  = in_array($f['type'] ?? '', ['link', 'file']) ? $f['type'] : 'link';
            $name  = mb_substr(strip_tags((string)($f['name']  ?? '')), 0, 60);
            $value = mb_substr((string)($f['value'] ?? ''), 0, 500);
            if ($name === '' && $value === '') continue;
            $clean[] = ['type' => $type, 'name' => $name, 'value' => $value];
        }
        $pdo->prepare("UPDATE users SET l4t_files = ? WHERE id = ?")
            ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $userId]);
        $_SESSION['USERDATA']['l4t_files'] = json_encode($clean, JSON_UNESCAPED_UNICODE);
        echo json_encode(['success' => true]);
        break;

    // ── ПРОЕКТЫ ───────────────────────────────────────────────
    case 'projects':
        $projects = array_slice($data['data'] ?? [], 0, 12);
        $clean = [];
        foreach ($projects as $p) {
            $title = mb_substr(strip_tags((string)($p['title'] ?? '')), 0, 80);
            if ($title === '') continue;
            $clean[] = [
                'title'       => $title,
                'role'        => mb_substr(strip_tags((string)($p['role']        ?? '')), 0, 60),
                'year'        => (int)($p['year'] ?? 0),
                'url'         => mb_substr((string)($p['url']         ?? ''), 0, 500),
                'cover'       => mb_substr((string)($p['cover']       ?? ''), 0, 500),
                'description' => mb_substr(strip_tags((string)($p['description'] ?? '')), 0, 500),
            ];
        }
        $pdo->prepare("UPDATE users SET l4t_projects = ? WHERE id = ?")
            ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $userId]);
        $_SESSION['USERDATA']['l4t_projects'] = json_encode($clean, JSON_UNESCAPED_UNICODE);
        echo json_encode(['success' => true]);
        break;

    // ── О СЕБЕ ────────────────────────────────────────────────
    case 'about':
        $about = mb_substr(strip_tags((string)($data['data'] ?? '')), 0, 10000);
        $pdo->prepare("UPDATE users SET l4t_about = ? WHERE id = ?")
            ->execute([$about, $userId]);
        $_SESSION['USERDATA']['l4t_about'] = $about;
        echo json_encode(['success' => true]);
        break;

    // ── ЗАГРУЗКА ФАЙЛА НА S3 ──────────────────────────────────
    case 'upload':
        require_once(__DIR__ . '/../s3.php');
        $b64 = $data['file'] ?? '';
        $ext = preg_replace('/[^a-z0-9]/i', '', $data['ext'] ?? 'jpg');
        if (!$b64) {
            echo json_encode(['success' => false, 'msg' => 'no file']);
            break;
        }

        $decoded = base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $b64));
        if (!$decoded) {
            echo json_encode(['success' => false, 'msg' => 'bad base64']);
            break;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'l4t_');
        file_put_contents($tmp, $decoded);

        $s3  = new S3Uploader();
        $key = "l4t/u{$userId}_" . time() . ".{$ext}";
        $url = $s3->uploadFile($tmp, $key);
        unlink($tmp);

        echo json_encode(['success' => (bool)$url, 'url' => $url ?: '']);
        break;

    default:
        echo json_encode(['success' => false, 'msg' => 'unknown type']);
}
