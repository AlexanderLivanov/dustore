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
            --pr: #c32178;
            --dark: #0d0118;
            --surf: rgba(255, 255, 255, .04);
            --surf2: rgba(255, 255, 255, .075);
            --bdr: rgba(255, 255, 255, .09);
            --bdr2: rgba(255, 255, 255, .15);
            --txt: #f0e6ff;
            --muted: rgba(240, 230, 255, .45);
            --success: #00e887;
            --warn: #f59e0b;
            --r: 12px;
            --ease: cubic-bezier(.4, 0, .2, 1)
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            background: var(--dark);
            color: var(--txt);
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 24px
        }

        .edit-hero {
            background: linear-gradient(160deg, #1e0240, #0d0118);
            border-bottom: 1px solid var(--bdr);
            padding: 32px 0
        }

        .edit-hero .container {
            position: relative
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(195, 33, 120, .12);
            border: 1px solid rgba(195, 33, 120, .25);
            border-radius: 100px;
            padding: 4px 14px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #e88fc0;
            margin-bottom: 12px
        }

        .edit-hero h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -.02em;
            margin-bottom: 6px
        }

        .edit-hero p {
            color: var(--muted);
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 6px;
            padding: 3px 9px;
            font-size: .7rem;
            font-weight: 700
        }

        .status-published {
            background: rgba(0, 232, 135, .15);
            color: var(--success);
            border: 1px solid rgba(0, 232, 135, .3)
        }

        .status-draft {
            background: rgba(245, 158, 11, .15);
            color: var(--warn);
            border: 1px solid rgba(245, 158, 11, .3)
        }

        .status-rejected {
            background: rgba(239, 68, 68, .12);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, .3)
        }

        .edit-body {
            padding: 32px 0 80px
        }

        .sec-title {
            font-family: 'Syne', sans-serif;
            font-size: .88rem;
            font-weight: 800;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--bdr);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .sec-title::before {
            content: '';
            display: block;
            width: 3px;
            height: 14px;
            background: var(--pr);
            border-radius: 2px
        }

        .section {
            margin-bottom: 28px
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        .form-grid.full {
            grid-template-columns: 1fr
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .field label {
            font-size: .76rem;
            font-weight: 600;
            color: var(--muted)
        }

        .field label em {
            color: var(--pr);
            font-style: normal
        }

        .field input,
        .field textarea,
        .field select {
            background: var(--surf2);
            border: 1px solid var(--bdr2);
            border-radius: 9px;
            color: var(--txt);
            font-family: inherit;
            font-size: .86rem;
            padding: 10px 13px;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
            width: 100%;
        }

        .field textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.6
        }

        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            border-color: var(--pr);
            box-shadow: 0 0 0 3px rgba(195, 33, 120, .2)
        }

        .field select option {
            background: #130125
        }

        .field-hint {
            font-size: .7rem;
            color: var(--muted)
        }

        .check-group {
            display: flex;
            flex-wrap: wrap;
            gap: 7px
        }

        .check-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 8px;
            padding: 5px 11px;
            font-size: .76rem;
            color: var(--muted);
            transition: all .15s;
            user-select: none
        }

        .check-pill:hover {
            border-color: var(--bdr2);
            color: var(--txt)
        }

        .check-pill input {
            display: none
        }

        .check-pill.checked {
            background: rgba(195, 33, 120, .15);
            border-color: rgba(195, 33, 120, .35);
            color: #e88fc0
        }

        .check-pill .cp-box {
            width: 13px;
            height: 13px;
            border-radius: 3px;
            border: 1px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .55rem
        }

        .check-pill.checked .cp-box::after {
            content: '✓'
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 13px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 9px
        }

        .toggle-sw {
            position: relative;
            width: 40px;
            height: 22px;
            flex-shrink: 0
        }

        .toggle-sw input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute
        }

        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--surf2);
            border: 1px solid var(--bdr2);
            border-radius: 100px;
            cursor: pointer;
            transition: background .2s
        }

        .toggle-sw input:checked+.toggle-track {
            background: var(--pr);
            border-color: var(--pr)
        }

        .toggle-track::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            transition: transform .2s
        }

        .toggle-sw input:checked+.toggle-track::after {
            transform: translateX(18px)
        }

        /* Cover preview */
        .cover-current {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--bdr);
            margin-bottom: 10px
        }

        .cover-current img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            display: block
        }

        .dropzone {
            border: 2px dashed var(--bdr2);
            border-radius: var(--r);
            padding: 28px;
            text-align: center;
            cursor: pointer;
            position: relative;
            background: var(--surf);
            transition: all .2s
        }

        .dropzone:hover {
            border-color: var(--pr);
            background: rgba(195, 33, 120, .06)
        }

        .dropzone input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%
        }

        .dz-hint {
            font-size: .78rem;
            color: var(--muted);
            margin-top: 4px
        }

        /* Split */
        .split-wrap {
            margin-top: 6px
        }

        .split-track {
            height: 8px;
            border-radius: 100px;
            overflow: hidden;
            display: flex;
            margin-bottom: 6px;
            background: var(--surf2)
        }

        .split-dev {
            background: var(--pr);
            transition: width .1s
        }

        .split-plat {
            background: rgba(255, 255, 255, .12)
        }

        .split-labels {
            display: flex;
            justify-content: space-between;
            font-size: .74rem
        }

        .split-labels .dev {
            color: #e88fc0;
            font-weight: 700
        }

        .split-labels .plat {
            color: var(--muted)
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: .85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px
        }

        .alert.err {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .25);
            color: #fca5a5
        }

        .alert.ok {
            background: rgba(0, 232, 135, .1);
            border: 1px solid rgba(0, 232, 135, .25);
            color: var(--success)
        }

        /* Buttons */
        .btn-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px
        }

        .btn-save {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 12px 28px;
            border-radius: 10px;
            background: var(--pr);
            color: #fff;
            border: none;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit
        }

        .btn-save:hover {
            background: #d42485;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(195, 33, 120, .4)
        }

        .btn-cancel {
            padding: 12px 20px;
            border-radius: 10px;
            background: none;
            color: var(--muted);
            border: 1px solid var(--bdr);
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .18s;
            display: inline-flex;
            align-items: center
        }

        .btn-cancel:hover {
            border-color: var(--bdr2);
            color: var(--txt)
        }

        @media(max-width:640px) {
            .form-grid {
                grid-template-columns: 1fr
            }
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