<?php
session_start();
require_once('../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['id'] ?? 0);
$user_id  = (int)$_SESSION['USERDATA']['id'];
$isAdmin  = false;

// Check admin or studio owner
$u = $pdo->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$u->execute([$user_id]);
$ur = $u->fetch(PDO::FETCH_ASSOC);
$isAdmin = ($ur['role'] ?? '') === 'admin';

// Get studio
$studioStmt = $pdo->prepare("SELECT name FROM studios WHERE owner_id=? LIMIT 1");
$studioStmt->execute([$user_id]);
$studio = $studioStmt->fetch(PDO::FETCH_ASSOC);

// Fetch asset
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id=? LIMIT 1");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$asset) {
    header('Location: /assetstore/');
    exit;
}

// Check ownership (admin OR studio owner)
if (!$isAdmin && (!$studio || $asset['studio_name'] !== $studio['name'])) {
    http_response_code(403);
    die('Нет доступа');
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = max(0, (float)($_POST['price'] ?? 0));
        $license     = trim($_POST['license'] ?? 'commercial');
        $version     = trim($_POST['version'] ?? '1.0');
        $tags        = trim($_POST['tags'] ?? '');
        $dev_share   = max(10, min(90, (int)($_POST['dev_share'] ?? 70)));
        $formats     = array_values(array_filter($_POST['formats'] ?? []));
        $engines     = array_values(array_filter($_POST['engines'] ?? []));
        $poly_count  = !empty($_POST['poly_count']) ? (int)$_POST['poly_count'] : null;
        $texture_size = !empty($_POST['texture_size']) ? trim($_POST['texture_size']) : null;
        $rigged      = isset($_POST['rigged']) ? 1 : 0;
        $animated    = isset($_POST['animated']) ? 1 : 0;
        $status      = $isAdmin ? trim($_POST['status'] ?? 'draft') : $asset['status'];

        if (!$name || !$description) throw new Exception('Заполните название и описание');

        // Handle new cover
        $cover_path = $asset['path_to_cover'];
        if (!empty($_FILES['cover']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) throw new Exception('Недопустимый формат обложки');
            $fname = uniqid('cover_') . '.' . $ext;
            $dir = '../uploads/assets/covers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['cover']['tmp_name'], $dir . $fname);
            // Delete old cover
            if ($cover_path && file_exists('../' . $cover_path)) unlink('../' . $cover_path);
            $cover_path = '/uploads/assets/covers/' . $fname;
        }

        // Handle new asset file
        $file_size = $asset['file_size_bytes'];
        if (!empty($_FILES['asset_file']['tmp_name'])) {
            $fname = uniqid('asset_') . '_' . basename($_FILES['asset_file']['name']);
            $dir = '../uploads/assets/files/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['asset_file']['tmp_name'], $dir . $fname);
            $file_size = $_FILES['asset_file']['size'];
        }

        // Contents
        $contents_raw = trim($_POST['contents_list'] ?? '');
        $contents = [];
        foreach (array_filter(explode("\n", $contents_raw)) as $line) {
            $line = trim($line);
            if ($line) $contents[] = ['name' => $line, 'size' => 0];
        }

        $pdo->prepare("UPDATE assets SET
            name=?, description=?, price=?, license=?, version=?, tags=?, dev_share=?,
            formats=?, engine_compatibility=?, poly_count=?, texture_size=?,
            rigged=?, animated=?, path_to_cover=?, file_size_bytes=?,
            contents=?, status=?, updated_at=NOW()
            WHERE id=?")->execute([
            $name,
            $description,
            $price,
            $license,
            $version,
            $tags,
            $dev_share,
            json_encode($formats, JSON_UNESCAPED_UNICODE),
            json_encode($engines, JSON_UNESCAPED_UNICODE),
            $poly_count,
            $texture_size,
            $rigged,
            $animated,
            $cover_path,
            $file_size,
            json_encode($contents, JSON_UNESCAPED_UNICODE),
            $status,
            $asset_id
        ]);

        // Refresh
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$formats  = json_decode($asset['formats'] ?? '[]', true) ?: [];
$engines  = json_decode($asset['engine_compatibility'] ?? '[]', true) ?: [];
$contents = json_decode($asset['contents'] ?? '[]', true) ?: [];
$contents_text = implode("\n", array_map(fn($c) => $c['name'] ?? '', $contents));

$CATS = [
    '3d_model' => ['label' => '3D Модели', 'emoji' => '🧊'],
    'texture' => ['label' => 'Текстуры', 'emoji' => '🖼️'],
    'music' => ['label' => 'Музыка', 'emoji' => '🎵'],
    'sfx' => ['label' => 'SFX', 'emoji' => '🔊'],
    'sprite' => ['label' => 'Спрайты', 'emoji' => '🎨'],
    'shader' => ['label' => 'Шейдеры', 'emoji' => '✨'],
    'font' => ['label' => 'Шрифты', 'emoji' => '🔤'],
    'script' => ['label' => 'Скрипты', 'emoji' => '📜'],
    'ui_kit' => ['label' => 'UI Киты', 'emoji' => '🎛️'],
    'animation' => ['label' => 'Анимации', 'emoji' => '🎬'],
    'vfx' => ['label' => 'VFX', 'emoji' => '💥'],
    'video' => ['label' => 'Видео', 'emoji' => '📹']
];
$cat = $CATS[$asset['category']] ?? ['label' => ucfirst($asset['category']), 'emoji' => '📦'];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать: <?= htmlspecialchars($asset['name']) ?></title>
    <link rel="stylesheet" href="../swad/css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #c32178;
    --secondary: #74155d;
    --dark: #170423;
    --light: #f8f9fa;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    background: linear-gradient(180deg, #0f0a20, #240038, #780066);
    color: #f0e6ff;
    font-family: 'Inter', system-ui, sans-serif;
    min-height: 100vh;
}
body.moonlight-theme {
    background-image: url("/swad/static/img/Moonlight_pict.jpeg");
    background-size: cover;
    background-repeat: no-repeat;
    background-attachment: fixed;
    background-position: center 35%;
}
.container {
    max-width: 960px;
    margin: 0 auto;
    padding: 0 24px;
}
/* hero */
.upload-hero, .edit-hero {
    padding: 40px 0 32px;
    position: relative;
}
.upload-hero .container, .edit-hero .container {
    background: linear-gradient(180deg, rgba(0,0,0,0.2), rgba(0,0,0,0.3), rgba(0,0,0,0.4));
    border-radius: 15px;
    backdrop-filter: blur(20px);
    box-shadow: 0px 0px 1px 1px rgba(255,255,255,0.15);
    padding: 30px 30px 20px;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(195,33,120,0.12);
    border: 1px solid rgba(195,33,120,0.25);
    border-radius: 100px;
    padding: 4px 14px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #e88fc0;
    margin-bottom: 14px;
}
.upload-hero h1, .edit-hero h1 {
    font-family: 'Syne', sans-serif;
    font-size: clamp(1.7rem, 3vw, 2.6rem);
    font-weight: 800;
    letter-spacing: -.03em;
    margin-bottom: 8px;
}
.upload-hero h1 em, .edit-hero h1 em { color: var(--primary); font-style:normal; }
.upload-hero p, .edit-hero p {
    color: rgba(255,255,255,0.4);
    font-size: .9rem;
    max-width: 480px;
}
.studio-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 100px;
    padding: 6px 14px;
    font-size: .8rem;
}
.studio-badge span { color: rgba(255,255,255,0.4); }
.studio-badge strong { color: #e88fc0; }

/* steps bar */
.steps-bar {
    background: rgba(13,1,24,0.9);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 0;
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(12px);
}
.steps-inner {
    display: flex;
    align-items: stretch;
    max-width: 960px;
    margin: 0 auto;
    padding: 0 24px;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px 16px 0;
    font-size: .8rem;
    color: rgba(255,255,255,0.4);
    transition: color .2s;
    flex-shrink: 0;
    position: relative;
    cursor: default;
}
.step-item::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: transparent;
    transition: background .2s;
}
.step-item.active { color: #fff; }
.step-item.active::after { background: var(--primary); }
.step-item.done { color: #2ecc71; }
.step-item.done::after { background: #2ecc71; }
.step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: 700;
    flex-shrink: 0;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    transition: all .2s;
}
.step-item.active .step-num {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.step-item.done .step-num {
    background: rgba(46,204,113,0.2);
    border-color: rgba(46,204,113,0.4);
    color: #2ecc71;
}
.step-sep {
    flex: 1;
    border: none;
    border-top: 1px dashed rgba(255,255,255,0.08);
    margin: 0 8px;
}

.upload-layout, .edit-body {
    max-width: 960px;
    margin: 0 auto;
    padding: 32px 24px 80px;
}
.step-panel { display: none; }
.step-panel.active { display: block; }

.sec-title {
    font-family: 'Syne', sans-serif;
    font-size: .95rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 20px;
}
.sec-title::before {
    content: '';
    display: block;
    width: 3px;
    height: 15px;
    background: var(--primary);
    border-radius: 2px;
}

.cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 32px;
}
.cat-card {
    background: rgba(255,255,255,0.05);
    border: 2px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 20px 14px 16px;
    cursor: pointer;
    transition: all .2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
}
.cat-card:hover {
    border-color: rgba(195,33,120,0.35);
    background: rgba(195,33,120,0.06);
}
.cat-card.selected {
    border-color: var(--primary);
    background: rgba(195,33,120,0.12);
    box-shadow: 0 0 0 3px rgba(195,33,120,0.2);
}
.cat-card .cat-ico { font-size: 2rem; line-height:1; }
.cat-card .cat-name { font-size: .8rem; font-weight:700; color:#fff; }
.cat-card .cat-hint { font-size: .68rem; color:rgba(255,255,255,0.4); line-height:1.4; margin-top:2px; }
.cat-card.selected .cat-name { color:#e88fc0; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.form-grid.full { grid-template-columns: 1fr; }
.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.field label {
    font-size: .78rem;
    font-weight: 600;
    color: rgba(255,255,255,0.4);
}
.field label em { color: var(--primary); font-style:normal; }
.field input, .field textarea, .field select {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9px;
    color: #fff;
    font-family: inherit;
    font-size: .88rem;
    padding: 11px 14px;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
    width: 100%;
}
.field textarea { resize: vertical; min-height: 100px; line-height:1.6; }
.field input:focus, .field textarea:focus, .field select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(195,33,120,0.2);
}
.field select option { background: #1c0923; }
.field-hint { font-size: .72rem; color:rgba(255,255,255,0.3); margin-top:2px; }

.check-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.check-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: .78rem;
    color: rgba(255,255,255,0.4);
    transition: all .15s;
}
.check-pill:hover {
    border-color: rgba(255,255,255,0.15);
    color: #fff;
}
.check-pill input { display: none; }
.check-pill.checked {
    background: rgba(195,33,120,0.15);
    border-color: rgba(195,33,120,0.35);
    color: #e88fc0;
}
.check-pill .cp-box {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid currentColor;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .6rem;
}
.check-pill.checked .cp-box::after { content: '✓'; }

.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9px;
}
.toggle-label { font-size: .84rem; }
.toggle-label small {
    display: block;
    font-size: .72rem;
    color: rgba(255,255,255,0.4);
    margin-top: 2px;
}
.toggle-sw {
    position: relative;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}
.toggle-sw input { opacity:0; width:0; height:0; position:absolute; }
.toggle-track {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 100px;
    cursor: pointer;
    transition: background .2s;
}
.toggle-sw input:checked + .toggle-track {
    background: var(--primary);
    border-color: var(--primary);
}
.toggle-track::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
}
.toggle-sw input:checked + .toggle-track::after { transform: translateX(18px); }

.cat-fields { display: none; }
.cat-fields.visible { display: block; }
.cat-fields-inner {
    background: rgba(195,33,120,0.04);
    border: 1px solid rgba(195,33,120,0.15);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.cat-fields-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #e88fc0;
    margin-bottom: 16px;
}
.cat-fields-label .cat-ico-sm { font-size: 1rem; }

.dropzone {
    border: 2px dashed rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 40px 24px;
    text-align: center;
    cursor: pointer;
    transition: all .2s ease;
    position: relative;
    background: rgba(255,255,255,0.05);
}
.dropzone:hover, .dropzone.drag-over {
    border-color: var(--primary);
    background: rgba(195,33,120,0.06);
}
.dropzone input[type=file] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.dz-ico { font-size: 2.5rem; margin-bottom:12px; opacity:.6; }
.dz-title { font-weight:700; font-size:.95rem; margin-bottom:4px; }
.dz-sub { font-size:.78rem; color:rgba(255,255,255,0.4); }
.dz-formats {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: center;
    margin-top: 12px;
}
.dz-fmt {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    padding: 2px 7px;
    font-size: .65rem;
    font-family: monospace;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
}
.dz-selected {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(46,204,113,0.08);
    border: 1px solid rgba(46,204,113,0.2);
    border-radius: 9px;
    margin-top: 10px;
    font-size: .84rem;
}
.dz-selected.show { display: flex; }
.dz-selected .dz-fname { font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dz-selected .dz-fsize { color:rgba(255,255,255,0.4); font-size:.75rem; flex-shrink:0; }
.dz-selected .dz-clear {
    background:none; border:none; color:rgba(255,255,255,0.4); cursor:pointer; font-size:1rem; flex-shrink:0;
}
.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
    margin-top: 12px;
}
.preview-item {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 8px;
    overflow: hidden;
    background: rgba(255,255,255,0.05);
}
.preview-item img { width:100%; height:100%; object-fit:cover; display:block; }
.preview-item .rm {
    position: absolute;
    top: 4px; right: 4px;
    width: 20px; height:20px;
    border-radius: 50%;
    background: rgba(0,0,0,0.8);
    border: none;
    color: #fff;
    font-size: .7rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.price-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.split-slider-wrap { margin-top:8px; }
.split-bar-ui {
    height: 10px;
    border-radius: 100px;
    overflow: hidden;
    display: flex;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.05);
}
.split-bar-ui .dev { background: var(--primary); transition: width .1s; }
.split-bar-ui .plat { background: rgba(255,255,255,0.12); }
.split-info {
    display: flex;
    justify-content: space-between;
    font-size: .76rem;
}
.split-info .si-dev { color: #e88fc0; font-weight:700; }
.split-info .si-plat { color: rgba(255,255,255,0.4); }
.split-input {
    width: 100%;
    accent-color: var(--primary);
    margin-top: 4px;
}

.btn-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    flex-wrap: wrap;
}
.btn-next {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 13px 28px;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    border: none;
    font-size: .92rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.btn-next:hover {
    background: #d42485;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(195,33,120,0.4);
}
.btn-next:disabled { opacity: .4; cursor: not-allowed; transform: none; box-shadow:none; }
.btn-back {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 13px 20px;
    border-radius: 10px;
    background: none;
    color: rgba(255,255,255,0.4);
    border: 1px solid rgba(255,255,255,0.08);
    font-size: .88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.btn-back:hover {
    border-color: rgba(255,255,255,0.15);
    color: #fff;
}
.btn-submit {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    border: none;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
    box-shadow: 0 4px 20px rgba(195,33,120,0.3);
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(195,33,120,0.5);
}
.btn-submit:disabled { opacity:.5; cursor:not-allowed; transform:none; }

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: .87rem;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.alert.err {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    color: #fca5a5;
}
.alert.ok {
    background: rgba(46,204,113,0.1);
    border: 1px solid rgba(46,204,113,0.25);
    color: #2ecc71;
}
.draft-info {
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .82rem;
    color: rgba(245,158,11,0.9);
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 20px;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 6px;
    padding: 3px 9px;
    font-size: .7rem;
    font-weight: 700;
}
.status-published {
    background: rgba(46,204,113,0.15);
    color: #2ecc71;
    border: 1px solid rgba(46,204,113,0.3);
}
.status-draft {
    background: rgba(245,158,11,0.15);
    color: #f59e0b;
    border: 1px solid rgba(245,158,11,0.3);
}
.status-rejected {
    background: rgba(239,68,68,0.12);
    color: #f87171;
    border: 1px solid rgba(239,68,68,0.3);
}
.btn-save {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 12px 28px;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    border: none;
    font-size: .92rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.btn-save:hover {
    background: #d42485;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(195,33,120,0.4);
}
.btn-cancel {
    padding: 12px 20px;
    border-radius: 10px;
    background: none;
    color: rgba(255,255,255,0.4);
    border: 1px solid rgba(255,255,255,0.08);
    font-size: .88rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .18s;
    display: inline-flex;
    align-items: center;
}
.btn-cancel:hover {
    border-color: rgba(255,255,255,0.15);
    color: #fff;
}
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>

    <main>
        <div class="edit-hero">
            <div class="container">
                <div class="hero-eyebrow">✏️ Редактирование</div>
                <h1><?= htmlspecialchars($asset['name']) ?></h1>
                <p>
                    <?= $cat['emoji'] ?> <?= $cat['label'] ?> ·
                    by <?= htmlspecialchars($asset['studio_name']) ?> ·
                    <span class="status-pill status-<?= $asset['status'] ?>">
                        <?= ['published' => '✓ Опубликован', 'draft' => '⏳ Черновик', 'rejected' => '✕ Отклонён'][$asset['status']] ?? $asset['status'] ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="edit-body">
            <div class="container">

                <?php if ($success): ?>
                    <div class="alert ok">✓ Изменения сохранены</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert err">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <!-- Basic info -->
                    <div class="section">
                        <div class="sec-title">Основная информация</div>
                        <div class="form-grid full" style="margin-bottom:12px">
                            <div class="field">
                                <label>Название <em>*</em></label>
                                <input type="text" name="name" value="<?= htmlspecialchars($asset['name']) ?>" maxlength="200" required>
                            </div>
                        </div>
                        <div class="form-grid full" style="margin-bottom:12px">
                            <div class="field">
                                <label>Описание <em>*</em></label>
                                <textarea name="description" rows="5"><?= htmlspecialchars($asset['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label>Версия</label>
                                <input type="text" name="version" value="<?= htmlspecialchars($asset['version'] ?? '1.0') ?>">
                            </div>
                            <div class="field">
                                <label>Теги (через запятую)</label>
                                <input type="text" name="tags" value="<?= htmlspecialchars($asset['tags'] ?? '') ?>" placeholder="pbr, seamless, 4k">
                            </div>
                        </div>
                    </div>

                    <!-- Compat -->
                    <div class="section">
                        <div class="sec-title">Совместимость с движками</div>
                        <div class="check-group">
                            <?php foreach (['Unity', 'Godot', 'Unreal Engine', 'GameMaker', 'Defold', 'Любой'] as $eng): ?>
                                <label class="check-pill <?= in_array($eng, $engines) ? 'checked' : '' ?>">
                                    <input type="checkbox" name="engines[]" value="<?= $eng ?>" <?= in_array($eng, $engines) ? 'checked' : '' ?>>
                                    <span class="cp-box"></span><?= $eng ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Category-specific -->
                    <?php if (in_array($asset['category'], ['3d_model', 'animation'])): ?>
                        <div class="section">
                            <div class="sec-title">Параметры модели</div>
                            <div class="form-grid" style="margin-bottom:12px">
                                <div class="field">
                                    <label>Полигонов</label>
                                    <input type="number" name="poly_count" value="<?= $asset['poly_count'] ?>" min="0">
                                </div>
                                <div class="field">
                                    <label>Разрешение текстур</label>
                                    <select name="texture_size">
                                        <?php foreach (['', '512x512', '1024x1024', '2048x2048', '4096x4096', '8192x8192'] as $ts): ?>
                                            <option value="<?= $ts ?>" <?= $asset['texture_size'] === $ts ? 'selected' : '' ?>><?= $ts ?: '-' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <div class="toggle-row">
                                    <div>Риг <small style="color:var(--muted);display:block;font-size:.72rem">Скелет для анимации</small></div>
                                    <label class="toggle-sw"><input type="checkbox" name="rigged" <?= $asset['rigged'] ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                                </div>
                                <div class="toggle-row">
                                    <div>Анимации <small style="color:var(--muted);display:block;font-size:.72rem">В комплекте</small></div>
                                    <label class="toggle-sw"><input type="checkbox" name="animated" <?= $asset['animated'] ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($asset['category'], ['texture'])): ?>
                        <div class="section">
                            <div class="sec-title">Параметры текстуры</div>
                            <div class="form-grid">
                                <div class="field">
                                    <label>Разрешение</label>
                                    <select name="texture_size">
                                        <?php foreach (['', '512x512', '1024x1024', '2048x2048', '4096x4096', '8192x8192'] as $ts): ?>
                                            <option value="<?= $ts ?>" <?= $asset['texture_size'] === $ts ? 'selected' : '' ?>><?= $ts ?: '-' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Formats -->
                    <div class="section">
                        <div class="sec-title">Форматы файлов</div>
                        <?php
                        $allFormats = [
                            'fbx',
                            'obj',
                            'gltf',
                            'glb',
                            'blend',
                            'png',
                            'jpg',
                            'tga',
                            'exr',
                            'psd',
                            'mp3',
                            'wav',
                            'ogg',
                            'flac',
                            'ttf',
                            'otf',
                            'woff',
                            'woff2',
                            'cs',
                            'gd',
                            'lua',
                            'unitypackage',
                            'gdshader',
                            'hlsl',
                            'glsl',
                            'zip',
                            'mp4',
                            'webm',
                            'svg',
                            'ai'
                        ];
                        ?>
                        <div class="check-group">
                            <?php foreach ($allFormats as $f): ?>
                                <label class="check-pill <?= in_array($f, $formats) ? 'checked' : '' ?>">
                                    <input type="checkbox" name="formats[]" value="<?= $f ?>" <?= in_array($f, $formats) ? 'checked' : '' ?>>
                                    <span class="cp-box"></span><?= strtoupper($f) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- License -->
                    <div class="section">
                        <div class="sec-title">Лицензия и цена</div>
                        <div class="form-grid">
                            <div class="field">
                                <label>Лицензия</label>
                                <select name="license">
                                    <option value="commercial" <?= $asset['license'] === 'commercial' ? 'selected' : '' ?>>Коммерческая</option>
                                    <option value="cc0" <?= $asset['license'] === 'cc0' ? 'selected' : '' ?>>CC0 — Public Domain</option>
                                    <option value="cc_by" <?= $asset['license'] === 'cc_by' ? 'selected' : '' ?>>CC BY 4.0</option>
                                    <option value="personal" <?= $asset['license'] === 'personal' ? 'selected' : '' ?>>Только личное</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Цена (₽) · 0 = бесплатно</label>
                                <input type="number" name="price" id="priceInput" value="<?= $asset['price'] ?>" min="0" step="10" oninput="updateSplit()">
                            </div>
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label>Ваша доля: <span id="devPct"><?= $asset['dev_share'] ?? 70 ?></span>%</label>
                            <div class="split-wrap">
                                <div class="split-track">
                                    <div class="split-dev" id="splitDev" style="width:<?= $asset['dev_share'] ?? 70 ?>%"></div>
                                    <div class="split-plat" style="width:<?= 100 - ($asset['dev_share'] ?? 70) ?>%"></div>
                                </div>
                                <div class="split-labels">
                                    <span class="dev">Вы: <strong id="devAmt">0 ₽</strong></span>
                                    <span class="plat">Платформа: <strong id="platAmt">0 ₽</strong></span>
                                </div>
                                <input type="range" name="dev_share" id="splitRange" min="50" max="90" value="<?= $asset['dev_share'] ?? 70 ?>"
                                    oninput="updateSplit()" style="width:100%;accent-color:var(--pr);margin-top:6px">
                            </div>
                        </div>
                    </div>

                    <?php if ($isAdmin): ?>
                        <!-- Admin: status control -->
                        <div class="section">
                            <div class="sec-title">🛡️ Статус (только для модератора)</div>
                            <div class="form-grid">
                                <div class="field">
                                    <label>Статус публикации</label>
                                    <select name="status">
                                        <option value="draft" <?= $asset['status'] === 'draft' ? 'selected' : '' ?>>⏳ Черновик</option>
                                        <option value="published" <?= $asset['status'] === 'published' ? 'selected' : '' ?>>✓ Опубликован</option>
                                        <option value="rejected" <?= $asset['status'] === 'rejected' ? 'selected' : '' ?>>✕ Отклонён</option>
                                        <option value="archived" <?= $asset['status'] === 'archived' ? 'selected' : '' ?>>📦 Архив</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Cover -->
                    <div class="section">
                        <div class="sec-title">Обложка</div>
                        <?php if (!empty($asset['path_to_cover'])): ?>
                            <div class="cover-current">
                                <img src="<?= htmlspecialchars($asset['path_to_cover']) ?>" alt="Текущая обложка">
                            </div>
                        <?php endif; ?>
                        <div class="dropzone" id="dz-cover">
                            <input type="file" name="cover" accept="image/*" onchange="onCoverChange(this)">
                            <div>🖼️ Загрузить новую обложку</div>
                            <div class="dz-hint">JPG, PNG, WebP · рек. 800×450 px</div>
                        </div>
                        <div id="cover-preview" style="display:none;margin-top:8px">
                            <img id="cover-img" style="width:100%;max-height:180px;object-fit:cover;border-radius:10px;border:1px solid var(--bdr)">
                        </div>
                    </div>

                    <!-- Asset file -->
                    <div class="section">
                        <div class="sec-title">Файл ассета</div>
                        <?php if (!empty($asset['file_size_bytes'])): ?>
                            <p style="font-size:.82rem;color:var(--muted);margin-bottom:10px">
                                Текущий файл: <strong style="color:var(--txt)"><?= round($asset['file_size_bytes'] / 1048576, 1) ?> МБ</strong>
                            </p>
                        <?php endif; ?>
                        <div class="dropzone">
                            <input type="file" name="asset_file" onchange="onFileChange(this)">
                            <div>📦 Загрузить новый файл ассета</div>
                            <div class="dz-hint">ZIP, UnityPackage или любой другой формат</div>
                        </div>
                        <div id="file-info" style="display:none;margin-top:8px;font-size:.82rem;color:var(--success)"></div>
                    </div>

                    <!-- Contents -->
                    <div class="section">
                        <div class="sec-title">Состав пакета</div>
                        <div class="field">
                            <textarea name="contents_list" rows="6" placeholder="mesh.fbx&#10;textures/albedo_4k.png&#10;README.txt"><?= htmlspecialchars($contents_text) ?></textarea>
                            <div class="field-hint">Каждый файл или папка — с новой строки</div>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn-save">💾 Сохранить изменения</button>
                        <a href="/assetstore/asset.php?id=<?= $asset_id ?>" class="btn-cancel">← Назад к ассету</a>
                        <?php if ($isAdmin): ?>
                            <a href="/assetstore/admin.php?tab=moderation" class="btn-cancel" style="margin-left:auto">🛡️ В админку</a>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <?php require_once('../swad/static/elements/footer.php'); ?>
    <script>
        /* Checkboxes */
        document.querySelectorAll('.check-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const inp = pill.querySelector('input');
                if (!inp) return;
                setTimeout(() => pill.classList.toggle('checked', inp.checked), 0);
            });
        });

        /* Split */
        function updateSplit() {
            const price = parseFloat(document.getElementById('priceInput')?.value) || 0;
            const pct = parseInt(document.getElementById('splitRange')?.value) || 70;
            const devEl = document.getElementById('splitDev');
            if (devEl) {
                devEl.style.width = pct + '%';
                devEl.nextElementSibling.style.width = (100 - pct) + '%';
            }
            const pctEl = document.getElementById('devPct');
            if (pctEl) pctEl.textContent = pct;
            const devAmt = document.getElementById('devAmt');
            const platAmt = document.getElementById('platAmt');
            if (devAmt) devAmt.textContent = Math.round(price * pct / 100) + ' ₽';
            if (platAmt) platAmt.textContent = Math.round(price * (100 - pct) / 100) + ' ₽';
        }
        updateSplit();

        /* Cover preview */
        function onCoverChange(inp) {
            if (!inp.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('cover-img').src = e.target.result;
                document.getElementById('cover-preview').style.display = 'block';
            };
            reader.readAsDataURL(inp.files[0]);
        }

        /* File info */
        function onFileChange(inp) {
            if (!inp.files[0]) return;
            const f = inp.files[0];
            const el = document.getElementById('file-info');
            if (el) {
                el.textContent = `✓ Выбран: ${f.name} (${(f.size/1048576).toFixed(1)} МБ)`;
                el.style.display = 'block';
            }
        }

        /* Dropzone drag */
        document.querySelectorAll('.dropzone').forEach(dz => {
            dz.addEventListener('dragover', e => {
                e.preventDefault();
                dz.style.borderColor = 'var(--pr)';
            });
            dz.addEventListener('dragleave', () => dz.style.borderColor = '');
            dz.addEventListener('drop', e => {
                e.preventDefault();
                dz.style.borderColor = '';
            });
        });
    </script>
</body>

</html>