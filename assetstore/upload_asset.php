<?php
session_start();
require_once('../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

// Get current user's studio
$stmt = $pdo->prepare("SELECT * FROM studios WHERE owner_id = ? LIMIT 1");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$studio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$studio) {
    header('Location: /devs/create-studio');
    exit;
}

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = max(0, (float)($_POST['price'] ?? 0));
        $license     = trim($_POST['license'] ?? 'commercial');
        $version     = trim($_POST['version'] ?? '1.0');
        $tags        = trim($_POST['tags'] ?? '');
        $dev_share   = max(10, min(90, (int)($_POST['dev_share'] ?? 70)));

        // Formats from checkboxes
        $formats = array_values(array_filter($_POST['formats'] ?? []));

        // Engine compatibility
        $engines = array_values(array_filter($_POST['engines'] ?? []));

        // Category-specific
        $poly_count    = !empty($_POST['poly_count'])    ? (int)$_POST['poly_count']    : null;
        $texture_size  = !empty($_POST['texture_size'])  ? trim($_POST['texture_size'])  : null;
        $rigged        = isset($_POST['rigged'])   ? 1 : 0;
        $animated      = isset($_POST['animated']) ? 1 : 0;

        // Contents JSON (parsed from textarea rows)
        $contents_raw = trim($_POST['contents_list'] ?? '');
        $contents = [];
        foreach (array_filter(explode("\n", $contents_raw)) as $line) {
            $line = trim($line);
            if ($line) $contents[] = ['name' => $line, 'size' => 0];
        }

        // Handle cover upload
        $cover_path = '';
        if (!empty($_FILES['cover']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed)) throw new Exception('Недопустимый формат обложки');
            $fname = uniqid('cover_') . '.' . $ext;
            $dir   = '../uploads/assets/covers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['cover']['tmp_name'], $dir . $fname);
            $cover_path = '/uploads/assets/covers/' . $fname;
        }

        // Handle preview images
        $previews = [];
        if (!empty($_FILES['previews']['tmp_name'])) {
            foreach ($_FILES['previews']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $ext  = strtolower(pathinfo($_FILES['previews']['name'][$i], PATHINFO_EXTENSION));
                $fname = uniqid('prev_') . '.' . $ext;
                $dir   = '../uploads/assets/previews/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                move_uploaded_file($tmp, $dir . $fname);
                $previews[] = '/uploads/assets/previews/' . $fname;
            }
        }

        // Handle asset file
        $file_size = 0;
        if (!empty($_FILES['asset_file']['tmp_name'])) {
            $fname = uniqid('asset_') . '_' . basename($_FILES['asset_file']['name']);
            $dir   = '../uploads/assets/files/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['asset_file']['tmp_name'], $dir . $fname);
            $file_size = $_FILES['asset_file']['size'];
        }

        if (!$name || !$category || !$description) {
            throw new Exception('Заполните обязательные поля: название, категория, описание');
        }

        $stmt = $pdo->prepare("INSERT INTO assets
            (name, description, category, formats, price, studio_name, path_to_cover,
             previews, file_size_bytes, license, engine_compatibility, version, status,
             downloads_count, avg_rating, tags, contents, dev_share,
             poly_count, texture_size, rigged, animated, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'draft',0,0,?,?,?,?,?,?,?,NOW())");

        $stmt->execute([
            $name,
            $description,
            $category,
            json_encode($formats, JSON_UNESCAPED_UNICODE),
            $price,
            $studio['name'],
            $cover_path,
            json_encode($previews, JSON_UNESCAPED_UNICODE),
            $file_size,
            $license,
            json_encode($engines, JSON_UNESCAPED_UNICODE),
            $version,
            $tags,
            json_encode($contents, JSON_UNESCAPED_UNICODE),
            $dev_share,
            $poly_count,
            $texture_size,
            $rigged,
            $animated
        ]);

        $new_id = $pdo->lastInsertId();
        $success = $new_id;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузить ассет — Dustore</title>
    <link rel="stylesheet" href="../swad/css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pr: #c32178;
            --pr-d: #74155d;
            --pr-glow: rgba(195, 33, 120, .2);
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
            --ease: cubic-bezier(.4, 0, .2, 1);
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

        /* ── Page header ── */
        .upload-hero {
            background: linear-gradient(160deg, #1e0240 0%, #0d0118 50%, #1a0035 100%);
            border-bottom: 1px solid var(--bdr);
            padding: 40px 0 32px;
            position: relative;
            overflow: hidden;
        }

        .upload-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 15% 60%, rgba(195, 33, 120, .13) 0%, transparent 55%)
        }

        .upload-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(195, 33, 120, .05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(195, 33, 120, .05) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: linear-gradient(to bottom, transparent, black 40%, black 60%, transparent)
        }

        .upload-hero .container {
            position: relative;
            z-index: 1
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
            margin-bottom: 14px;
        }

        .upload-hero h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.7rem, 3vw, 2.6rem);
            font-weight: 800;
            letter-spacing: -.03em;
            margin-bottom: 8px
        }

        .upload-hero h1 em {
            color: var(--pr);
            font-style: normal
        }

        .upload-hero p {
            color: var(--muted);
            font-size: .9rem;
            max-width: 480px
        }

        .studio-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: .8rem;
        }

        .studio-badge span {
            color: var(--muted)
        }

        .studio-badge strong {
            color: #e88fc0
        }

        /* ── Steps indicator ── */
        .steps-bar {
            background: rgba(13, 1, 24, .9);
            border-bottom: 1px solid var(--bdr);
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
            color: var(--muted);
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

        .step-item.active {
            color: var(--txt)
        }

        .step-item.active::after {
            background: var(--pr)
        }

        .step-item.done {
            color: var(--success)
        }

        .step-item.done::after {
            background: var(--success)
        }

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
            background: var(--surf);
            border: 1px solid var(--bdr);
            transition: all .2s;
        }

        .step-item.active .step-num {
            background: var(--pr);
            border-color: var(--pr);
            color: #fff
        }

        .step-item.done .step-num {
            background: rgba(0, 232, 135, .2);
            border-color: rgba(0, 232, 135, .4);
            color: var(--success)
        }

        .step-sep {
            flex: 1;
            border: none;
            border-top: 1px dashed var(--bdr);
            margin: 0 8px
        }

        /* ── Layout ── */
        .upload-layout {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 24px 80px
        }

        /* ── Step panels ── */
        .step-panel {
            display: none
        }

        .step-panel.active {
            display: block
        }

        /* ── Section titles ── */
        .sec-title {
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--bdr);
            margin-bottom: 20px;
        }

        .sec-title::before {
            content: '';
            display: block;
            width: 3px;
            height: 15px;
            background: var(--pr);
            border-radius: 2px
        }

        /* ── STEP 1: Category grid ── */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 32px;
        }

        .cat-card {
            background: var(--surf);
            border: 2px solid var(--bdr);
            border-radius: var(--r);
            padding: 20px 14px 16px;
            cursor: pointer;
            transition: all .2s var(--ease);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
            user-select: none;
        }

        .cat-card:hover {
            border-color: rgba(195, 33, 120, .35);
            background: rgba(195, 33, 120, .06)
        }

        .cat-card.selected {
            border-color: var(--pr);
            background: rgba(195, 33, 120, .12);
            box-shadow: 0 0 0 3px var(--pr-glow);
        }

        .cat-card .cat-ico {
            font-size: 2rem;
            line-height: 1
        }

        .cat-card .cat-name {
            font-size: .8rem;
            font-weight: 700;
            color: var(--txt)
        }

        .cat-card .cat-hint {
            font-size: .68rem;
            color: var(--muted);
            line-height: 1.4;
            margin-top: 2px
        }

        .cat-card.selected .cat-name {
            color: #e88fc0
        }

        /* ── STEP 2: Form fields ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px
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
            font-size: .78rem;
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
            font-size: .88rem;
            padding: 11px 14px;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
            width: 100%;
        }

        .field textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6
        }

        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            border-color: var(--pr);
            box-shadow: 0 0 0 3px var(--pr-glow);
        }

        .field select option {
            background: #130125
        }

        .field input[type=number]::-webkit-inner-spin-button {
            opacity: .5
        }

        .field-hint {
            font-size: .72rem;
            color: var(--muted);
            margin-top: 2px
        }

        /* Checkboxes group */
        .check-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px
        }

        .check-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: .78rem;
            color: var(--muted);
            transition: all .15s;
            user-select: none;
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

        .check-pill.checked .cp-box::after {
            content: '✓'
        }

        /* Toggle switch */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 9px
        }

        .toggle-label {
            font-size: .84rem
        }

        .toggle-label small {
            display: block;
            font-size: .72rem;
            color: var(--muted);
            margin-top: 2px
        }

        .toggle-sw {
            position: relative;
            width: 42px;
            height: 24px;
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
            transition: background .2s;
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
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            transition: transform .2s;
        }

        .toggle-sw input:checked+.toggle-track::after {
            transform: translateX(18px)
        }

        /* Category-specific blocks */
        .cat-fields {
            display: none
        }

        .cat-fields.visible {
            display: block
        }

        .cat-fields-inner {
            background: rgba(195, 33, 120, .04);
            border: 1px solid rgba(195, 33, 120, .15);
            border-radius: var(--r);
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

        .cat-fields-label .cat-ico-sm {
            font-size: 1rem
        }

        /* ── STEP 3: Files & Pricing ── */
        /* Drop zone */
        .dropzone {
            border: 2px dashed var(--bdr2);
            border-radius: var(--r);
            padding: 40px 24px;
            text-align: center;
            cursor: pointer;
            transition: all .2s var(--ease);
            position: relative;
            background: var(--surf);
        }

        .dropzone:hover,
        .dropzone.drag-over {
            border-color: var(--pr);
            background: rgba(195, 33, 120, .06);
        }

        .dropzone input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .dz-ico {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: .6
        }

        .dz-title {
            font-weight: 700;
            font-size: .95rem;
            margin-bottom: 4px
        }

        .dz-sub {
            font-size: .78rem;
            color: var(--muted)
        }

        .dz-formats {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
            margin-top: 12px;
        }

        .dz-fmt {
            background: var(--surf2);
            border: 1px solid var(--bdr);
            border-radius: 4px;
            padding: 2px 7px;
            font-size: .65rem;
            font-family: monospace;
            text-transform: uppercase;
            color: var(--muted);
        }

        .dz-selected {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: rgba(0, 232, 135, .08);
            border: 1px solid rgba(0, 232, 135, .2);
            border-radius: 9px;
            margin-top: 10px;
            font-size: .84rem;
        }

        .dz-selected.show {
            display: flex
        }

        .dz-selected .dz-fname {
            font-weight: 600;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .dz-selected .dz-fsize {
            color: var(--muted);
            font-size: .75rem;
            flex-shrink: 0
        }

        .dz-selected .dz-clear {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Image previews */
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 12px
        }

        .preview-item {
            position: relative;
            aspect-ratio: 16/9;
            border-radius: 8px;
            overflow: hidden;
            background: var(--surf)
        }

        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .preview-item .rm {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(13, 1, 24, .8);
            border: none;
            color: var(--txt);
            font-size: .7rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Pricing */
        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px
        }

        .split-slider-wrap {
            margin-top: 8px
        }

        .split-bar-ui {
            height: 10px;
            border-radius: 100px;
            overflow: hidden;
            display: flex;
            margin-bottom: 8px;
            background: var(--surf2)
        }

        .split-bar-ui .dev {
            background: var(--pr);
            transition: width .1s
        }

        .split-bar-ui .plat {
            background: rgba(255, 255, 255, .12)
        }

        .split-info {
            display: flex;
            justify-content: space-between;
            font-size: .76rem
        }

        .split-info .si-dev {
            color: #e88fc0;
            font-weight: 700
        }

        .split-info .si-plat {
            color: var(--muted)
        }

        .split-input {
            width: 100%;
            accent-color: var(--pr);
            margin-top: 4px
        }

        /* Buttons */
        .btn-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            flex-wrap: wrap
        }

        .btn-next {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            border-radius: 10px;
            background: var(--pr);
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
            box-shadow: 0 8px 24px rgba(195, 33, 120, .4)
        }

        .btn-next:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none
        }

        .btn-back {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 13px 20px;
            border-radius: 10px;
            background: none;
            color: var(--muted);
            border: 1px solid var(--bdr);
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
        }

        .btn-back:hover {
            border-color: var(--bdr2);
            color: var(--txt)
        }

        .btn-submit {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--pr), var(--pr-d));
            color: #fff;
            border: none;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
            box-shadow: 0 4px 20px rgba(195, 33, 120, .3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(195, 33, 120, .5)
        }

        .btn-submit:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none
        }

        /* Validation error */
        .field-err {
            font-size: .72rem;
            color: #f87171;
            margin-top: 2px;
            display: none
        }

        .field.has-err input,
        .field.has-err textarea,
        .field.has-err select {
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, .2)
        }

        .field.has-err .field-err {
            display: block
        }

        /* Alert */
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
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .25);
            color: #fca5a5
        }

        .alert.ok {
            background: rgba(0, 232, 135, .1);
            border: 1px solid rgba(0, 232, 135, .25);
            color: var(--success)
        }

        /* Success screen */
        .success-screen {
            text-align: center;
            padding: 60px 20px
        }

        .success-ico {
            font-size: 4rem;
            margin-bottom: 20px
        }

        .success-screen h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 10px
        }

        .success-screen p {
            color: var(--muted);
            max-width: 420px;
            margin: 0 auto 28px;
            line-height: 1.65
        }

        .success-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap
        }

        .s-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .s-btn.primary {
            background: var(--pr);
            color: #fff;
            border: none
        }

        .s-btn.primary:hover {
            background: #d42485
        }

        .s-btn.outline {
            background: none;
            color: var(--muted);
            border: 1px solid var(--bdr)
        }

        .s-btn.outline:hover {
            border-color: var(--bdr2);
            color: var(--txt)
        }

        /* Draft badge */
        .draft-info {
            background: rgba(245, 158, 11, .08);
            border: 1px solid rgba(245, 158, 11, .2);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: .82rem;
            color: rgba(245, 158, 11, .9);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 20px;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px
        }

        @media(max-width:640px) {
            .form-grid {
                grid-template-columns: 1fr
            }

            .price-row {
                grid-template-columns: 1fr
            }

            .cat-grid {
                grid-template-columns: repeat(3, 1fr)
            }

            .steps-inner {
                gap: 0;
                overflow-x: auto
            }

            .step-item {
                padding: 14px 12px 14px 0;
                font-size: .72rem
            }

            .step-sep {
                min-width: 16px
            }
        }
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>

    <main>
        <!-- ── Hero ── -->
        <section class="upload-hero">
            <div class="container">
                <div class="hero-eyebrow">📤 Загрузка ассета</div>
                <h1>Поделись своим<br><em>творчеством</em></h1>
                <p>Загрузи ассет и дай другим разработчикам сэкономить время. Зарабатывай с каждой загрузки.</p>
                <div class="studio-badge">
                    <span>Публикуется от имени</span>
                    <strong><?= htmlspecialchars($studio['display_name'] ?? $studio['name']) ?></strong>
                </div>
            </div>
        </section>

        <!-- ── Steps bar ── -->
        <div class="steps-bar">
            <div class="steps-inner">
                <div class="step-item active" id="si-1">
                    <div class="step-num">1</div>
                    <span>Тип ассета</span>
                </div>
                <hr class="step-sep">
                <div class="step-item" id="si-2">
                    <div class="step-num">2</div>
                    <span>Описание</span>
                </div>
                <hr class="step-sep">
                <div class="step-item" id="si-3">
                    <div class="step-num">3</div>
                    <span>Файлы и цена</span>
                </div>
            </div>
        </div>

        <div class="upload-layout">

            <?php if ($success): ?>
                <!-- ── SUCCESS ── -->
                <div class="success-screen">
                    <div class="success-ico">🎉</div>
                    <h2>Ассет загружен!</h2>
                    <p>Ассет сохранён как черновик. После проверки модератором он появится в каталоге.</p>
                    <div class="success-btns">
                        <a href="/assetstore/asset.php?id=<?= $success ?>" class="s-btn primary">👁 Предпросмотр</a>
                        <a href="/assetstore/upload_asset.php" class="s-btn outline">+ Ещё ассет</a>
                        <a href="/devs" class="s-btn outline">В консоль</a>
                    </div>
                    <div class="draft-info">
                        ⏳ Статус: <strong>Черновик (draft)</strong> — ассет скрыт от пользователей до одобрения.
                        Обычно проверка занимает 1–2 рабочих дня.
                    </div>
                </div>

            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert err">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="uploadForm" novalidate>

                    <!-- ══════════════════════════════════════════════
     STEP 1 — Категория
════════════════════════════════════════════════ -->
                    <div class="step-panel active" id="panel-1">
                        <div class="sec-title">Выберите тип ассета</div>

                        <div class="cat-grid">
                            <?php
                            $cats = [
                                '3d_model'  => ['🧊', '3D Модель',   'FBX, OBJ, GLTF, Blend'],
                                'texture'   => ['🖼️', 'Текстура',    'PNG, TGA, EXR, PSD'],
                                'music'     => ['🎵', 'Музыка',      'MP3, WAV, OGG, FLAC'],
                                'sfx'       => ['🔊', 'Звук / SFX',  'WAV, OGG, MP3'],
                                'sprite'    => ['🎨', 'Спрайт / 2D', 'PNG, Aseprite, SVG'],
                                'shader'    => ['✨', 'Шейдер',      'GLSL, HLSL, GDShader'],
                                'font'      => ['🔤', 'Шрифт',       'TTF, OTF, WOFF'],
                                'script'    => ['📜', 'Скрипт',      'C#, GDScript, Lua'],
                                'ui_kit'    => ['🎛️', 'UI Кит',      'PSD, Figma, SVG, PNG'],
                                'animation' => ['🎬', 'Анимация',    'FBX, BVH, GLTF'],
                                'vfx'       => ['💥', 'VFX / FX',    'Unity pkg, Niagara'],
                                'video'     => ['📹', 'Видео',       'MP4, WebM, MOV'],
                            ];
                            foreach ($cats as $key => [$ico, $name, $hint]):
                            ?>
                                <div class="cat-card" data-cat="<?= $key ?>" onclick="selectCat('<?= $key ?>')">
                                    <div class="cat-ico"><?= $ico ?></div>
                                    <div class="cat-name"><?= $name ?></div>
                                    <div class="cat-hint"><?= $hint ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="category" id="categoryInput">

                        <div class="btn-row">
                            <button type="button" class="btn-next" id="btn1next" disabled onclick="goStep(2)">
                                Далее — Описание →
                            </button>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════
     STEP 2 — Описание + категорийные поля
════════════════════════════════════════════════ -->
                    <div class="step-panel" id="panel-2">

                        <!-- Базовые поля -->
                        <div class="sec-title">Основная информация</div>
                        <div class="form-grid full">
                            <div class="field" id="f-name">
                                <label>Название <em>*</em></label>
                                <input type="text" name="name" placeholder="Например: Sci-Fi Crate Pack — 6 Variations" maxlength="200">
                                <div class="field-err">Введите название ассета</div>
                            </div>
                        </div>
                        <div class="form-grid full" style="margin-top:12px">
                            <div class="field" id="f-desc">
                                <label>Описание <em>*</em></label>
                                <textarea name="description" rows="5" placeholder="Подробно опишите ассет: что входит, для каких проектов подходит, особенности..."></textarea>
                                <div class="field-err">Добавьте описание</div>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top:12px">
                            <div class="field">
                                <label>Версия</label>
                                <input type="text" name="version" value="1.0" placeholder="1.0">
                            </div>
                            <div class="field">
                                <label>Теги <span style="color:var(--muted);font-weight:400">(через запятую)</span></label>
                                <input type="text" name="tags" placeholder="pbr, seamless, sci-fi, 4k">
                                <div class="field-hint">Помогают найти ассет в поиске</div>
                            </div>
                        </div>

                        <!-- Совместимость с движками -->
                        <div class="sec-title" style="margin-top:28px">Совместимость</div>
                        <div class="field">
                            <label>Игровые движки</label>
                            <div class="check-group" id="engines-group">
                                <?php foreach (['Unity', 'Godot', 'Unreal Engine', 'GameMaker', 'Defold', 'Любой'] as $eng): ?>
                                    <label class="check-pill">
                                        <input type="checkbox" name="engines[]" value="<?= $eng ?>">
                                        <span class="cp-box"></span>
                                        <?= $eng ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ── КАТЕГОРИЙНЫЕ ПОЛЯ (ветвление) ── -->

                        <!-- 3D MODEL -->
                        <div class="cat-fields" id="cf-3d_model">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🧊</span> Параметры 3D модели</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Количество полигонов</label>
                                        <input type="number" name="poly_count" placeholder="18 000" min="0">
                                    </div>
                                    <div class="field">
                                        <label>Разрешение текстур</label>
                                        <select name="texture_size">
                                            <option value="">— не указано —</option>
                                            <option>512x512</option>
                                            <option>1024x1024</option>
                                            <option>2048x2048</option>
                                            <option>4096x4096</option>
                                            <option>8192x8192</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Риг <small>Скелет для анимации</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged"><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">Анимации <small>Анимации в комплекте</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="animated"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['fbx', 'obj', 'gltf', 'glb', 'blend', 'ma', 'max', 'c4d', 'dae'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TEXTURE -->
                        <div class="cat-fields" id="cf-texture">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🖼️</span> Параметры текстуры</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Разрешение</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>512x512</option>
                                            <option>1024x1024</option>
                                            <option>2048x2048</option>
                                            <option>4096x4096</option>
                                            <option>8192x8192</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Вариаций в паке</label>
                                        <input type="number" name="poly_count" placeholder="1" min="1">
                                        <div class="field-hint">Количество уникальных вариаций</div>
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Seamless <small>Без видимых швов при тайлинге</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged"><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">PBR-карты <small>Albedo, Normal, Roughness, AO...</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="animated" checked><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Включённые PBR-карты</label>
                                    <div class="check-group">
                                        <?php foreach (['Albedo', 'Normal', 'Roughness', 'Metallic', 'AO', 'Displacement', 'Emissive', 'Opacity'] as $m): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= strtolower($m) ?>">
                                                <span class="cp-box"></span><?= $m ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['png', 'jpg', 'tga', 'exr', 'psd', 'tiff', 'bmp'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MUSIC -->
                        <div class="cat-fields" id="cf-music">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🎵</span> Параметры музыки</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Количество треков</label>
                                        <input type="number" name="poly_count" placeholder="8" min="1">
                                    </div>
                                    <div class="field">
                                        <label>Жанр</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Ambient</option>
                                            <option>Action / Battle</option>
                                            <option>Horror</option>
                                            <option>Fantasy</option>
                                            <option>Sci-Fi / Electronic</option>
                                            <option>Casual / Cozy</option>
                                            <option>Cinematic</option>
                                            <option>Retro / Chiptune</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Loop-версии <small>Бесшовные версии для игры</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged" checked><span class="toggle-track"></span></label>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">Stems / дорожки <small>Отдельные инструментальные дорожки</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="animated"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['mp3', 'wav', 'ogg', 'flac', 'aiff', 'aac'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SFX -->
                        <div class="cat-fields" id="cf-sfx">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🔊</span> Параметры SFX</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Количество звуков</label>
                                        <input type="number" name="poly_count" placeholder="50" min="1">
                                    </div>
                                    <div class="field">
                                        <label>Тип звуков</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Оружие / Combat</option>
                                            <option>UI / Interface</option>
                                            <option>Природа / Ambient</option>
                                            <option>Транспорт</option>
                                            <option>Магия / Sci-Fi</option>
                                            <option>Персонаж / Footsteps</option>
                                            <option>Взрывы</option>
                                            <option>Разное</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Без фонового шума <small>Чистые звуки, 48kHz+</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged" checked><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['wav', 'ogg', 'mp3', 'flac', 'aiff'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SPRITE -->
                        <div class="cat-fields" id="cf-sprite">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🎨</span> Параметры спрайтов / 2D</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Размер спрайта</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>16x16</option>
                                            <option>32x32</option>
                                            <option>48x48</option>
                                            <option>64x64</option>
                                            <option>128x128</option>
                                            <option>256x256</option>
                                            <option>Разный</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Направления анимации</label>
                                        <select name="poly_count">
                                            <option value="0">— не применимо —</option>
                                            <option value="4">4 стороны</option>
                                            <option value="8">8 сторон</option>
                                            <option value="1">Одно направление</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Spritesheet <small>Анимации собраны в один файл</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged" checked><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['png', 'svg', 'aseprite', 'gif', 'webp', 'psd'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SHADER -->
                        <div class="cat-fields" id="cf-shader">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">✨</span> Параметры шейдера</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Целевой движок</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Unity URP</option>
                                            <option>Unity HDRP</option>
                                            <option>Unity Built-in</option>
                                            <option>Godot 4</option>
                                            <option>Unreal Engine 5</option>
                                            <option>Three.js / WebGL</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Тип шейдера</label>
                                        <select name="poly_count">
                                            <option value="0">— выберите —</option>
                                            <option value="1">Surface / Lit</option>
                                            <option value="2">Unlit</option>
                                            <option value="3">Post-process</option>
                                            <option value="4">Particle</option>
                                            <option value="5">Compute</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['unitypackage', 'gdshader', 'hlsl', 'glsl', 'shadergraph', 'usf', 'zip'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FONT -->
                        <div class="cat-fields" id="cf-font">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🔤</span> Параметры шрифта</div>
                                <div class="field">
                                    <label>Наборы символов</label>
                                    <div class="check-group">
                                        <?php foreach (['Латиница', 'Кириллица', 'Цифры и знаки', 'Расширенная латиница', 'Иконки / Символы'] as $cs): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= urlencode($cs) ?>">
                                                <span class="cp-box"></span><?= $cs ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Начертания в комплекте</label>
                                    <div class="check-group">
                                        <?php foreach (['Regular', 'Bold', 'Italic', 'Bold Italic', 'Light', 'Thin', 'Black'] as $st): ?>
                                            <label class="check-pill"><input type="checkbox" name="engines[]" value="<?= $st ?>">
                                                <span class="cp-box"></span><?= $st ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['ttf', 'otf', 'woff', 'woff2'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SCRIPT -->
                        <div class="cat-fields" id="cf-script">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">📜</span> Параметры скрипта</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Язык программирования</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>C# (Unity)</option>
                                            <option>GDScript (Godot)</option>
                                            <option>Lua</option>
                                            <option>JavaScript / TypeScript</option>
                                            <option>Python</option>
                                            <option>C++</option>
                                            <option>Rust</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Тип системы</label>
                                        <select name="poly_count">
                                            <option value="0">— выберите —</option>
                                            <option value="1">Инвентарь</option>
                                            <option value="2">Диалоги</option>
                                            <option value="3">AI / Поведение</option>
                                            <option value="4">Сохранение / Загрузка</option>
                                            <option value="5">UI / HUD</option>
                                            <option value="6">Физика</option>
                                            <option value="7">Сеть / Мультиплеер</option>
                                            <option value="8">Процедурная генерация</option>
                                            <option value="9">Другое</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['unitypackage', 'zip', 'cs', 'gd', 'lua', 'js', 'ts', 'py'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- UI KIT -->
                        <div class="cat-fields" id="cf-ui_kit">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🎛️</span> Параметры UI кита</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Стиль</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Casual / Mobile</option>
                                            <option>Fantasy / RPG</option>
                                            <option>Sci-Fi</option>
                                            <option>Minimal / Flat</option>
                                            <option>Horror / Dark</option>
                                            <option>Pixel Art</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Элементов в паке</label>
                                        <input type="number" name="poly_count" placeholder="200" min="1">
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['png', 'svg', 'psd', 'figma', 'sketch', 'xd', 'ai'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ANIMATION -->
                        <div class="cat-fields" id="cf-animation">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">🎬</span> Параметры анимации</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Количество анимаций</label>
                                        <input type="number" name="poly_count" placeholder="24" min="1">
                                    </div>
                                    <div class="field">
                                        <label>Тип рига</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Unity Humanoid</option>
                                            <option>Unreal Mannequin</option>
                                            <option>Custom Rig</option>
                                            <option>BVH / Motion Capture</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Root Motion <small>Анимация с перемещением корня</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['fbx', 'gltf', 'glb', 'bvh', 'anim', 'dae'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VFX -->
                        <div class="cat-fields" id="cf-vfx">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">💥</span> Параметры VFX</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Тип системы частиц</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>Unity Particle System</option>
                                            <option>Unity VFX Graph</option>
                                            <option>Unreal Niagara</option>
                                            <option>Unreal Cascade</option>
                                            <option>Godot Particles</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Количество эффектов</label>
                                        <input type="number" name="poly_count" placeholder="15" min="1">
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['unitypackage', 'uasset', 'zip', 'vfx', 'prefab'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VIDEO -->
                        <div class="cat-fields" id="cf-video">
                            <div class="cat-fields-inner" style="margin-top:24px">
                                <div class="cat-fields-label"><span class="cat-ico-sm">📹</span> Параметры видео</div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Разрешение</label>
                                        <select name="texture_size">
                                            <option value="">— выберите —</option>
                                            <option>1280x720 (HD)</option>
                                            <option>1920x1080 (FHD)</option>
                                            <option>2560x1440 (2K)</option>
                                            <option>3840x2160 (4K)</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Количество клипов</label>
                                        <input type="number" name="poly_count" placeholder="10" min="1">
                                    </div>
                                </div>
                                <div style="margin-top:12px">
                                    <div class="toggle-row">
                                        <div class="toggle-label">Прозрачный фон (Alpha) <small>Видео с альфа-каналом</small></div>
                                        <label class="toggle-sw"><input type="checkbox" name="rigged"><span class="toggle-track"></span></label>
                                    </div>
                                </div>
                                <div class="field" style="margin-top:12px">
                                    <label>Форматы файлов</label>
                                    <div class="check-group">
                                        <?php foreach (['mp4', 'webm', 'mov', 'avi', 'prores'] as $f): ?>
                                            <label class="check-pill"><input type="checkbox" name="formats[]" value="<?= $f ?>">
                                                <span class="cp-box"></span><?= strtoupper($f) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Лицензия -->
                        <div class="sec-title" style="margin-top:28px">Лицензия</div>
                        <div class="form-grid">
                            <div class="field">
                                <label>Тип лицензии <em>*</em></label>
                                <select name="license">
                                    <option value="commercial">Коммерческая — платное использование OK</option>
                                    <option value="cc0">CC0 — Public Domain, без ограничений</option>
                                    <option value="cc_by">CC BY 4.0 — с указанием авторства</option>
                                    <option value="personal">Только личное использование</option>
                                </select>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn-back" onclick="goStep(1)">← Назад</button>
                            <button type="button" class="btn-next" onclick="goStep(3)">Далее — Файлы и цена →</button>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════
     STEP 3 — Файлы и цена
════════════════════════════════════════════════ -->
                    <div class="step-panel" id="panel-3">

                        <!-- Обложка -->
                        <div class="sec-title">Обложка</div>
                        <div class="dropzone" id="dz-cover">
                            <input type="file" name="cover" accept="image/*" onchange="onCoverChange(this)">
                            <div class="dz-ico">🖼️</div>
                            <div class="dz-title">Обложка ассета</div>
                            <div class="dz-sub">Рекомендуем 800×450 px · JPG, PNG, WebP</div>
                        </div>
                        <div class="dz-selected" id="cover-selected">
                            <span>🖼️</span>
                            <span class="dz-fname" id="cover-name"></span>
                            <span class="dz-fsize" id="cover-size"></span>
                            <button type="button" class="dz-clear" onclick="clearCover()">✕</button>
                        </div>
                        <div id="cover-preview" style="margin-top:10px;display:none">
                            <img id="cover-img" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid var(--bdr)" alt="Cover preview">
                        </div>

                        <!-- Скриншоты -->
                        <div class="sec-title" style="margin-top:28px">Изображения / Скриншоты</div>
                        <div class="dropzone" id="dz-prev">
                            <input type="file" name="previews[]" accept="image/*" multiple onchange="onPreviewsChange(this)">
                            <div class="dz-ico">📸</div>
                            <div class="dz-title">Галерея ассета</div>
                            <div class="dz-sub">До 8 изображений · JPG, PNG</div>
                        </div>
                        <div class="preview-grid" id="preview-grid"></div>

                        <!-- Файл ассета -->
                        <div class="sec-title" style="margin-top:28px">Файл ассета</div>
                        <div class="dropzone" id="dz-file">
                            <input type="file" name="asset_file" onchange="onAssetFileChange(this)">
                            <div class="dz-ico" id="dz-file-ico">📦</div>
                            <div class="dz-title">Загрузить файл ассета</div>
                            <div class="dz-sub">ZIP, RAR, UnityPackage или любой другой формат</div>
                            <div class="dz-formats" id="dz-file-formats"></div>
                        </div>
                        <div class="dz-selected" id="file-selected">
                            <span>📦</span>
                            <span class="dz-fname" id="file-name"></span>
                            <span class="dz-fsize" id="file-size"></span>
                            <button type="button" class="dz-clear" onclick="clearAssetFile()">✕</button>
                        </div>

                        <!-- Состав пакета -->
                        <div class="field" style="margin-top:20px">
                            <label>Состав пакета <span style="font-weight:400;color:var(--muted)">(каждый файл/папка с новой строки)</span></label>
                            <textarea name="contents_list" rows="5"
                                placeholder="mesh.fbx&#10;textures/albedo_4k.png&#10;textures/normal_4k.png&#10;textures/roughness_4k.png&#10;README.txt"></textarea>
                            <div class="field-hint">Поможет покупателям понять что внутри до скачивания</div>
                        </div>

                        <!-- Цена -->
                        <div class="sec-title" style="margin-top:28px">Цена и монетизация</div>
                        <div class="price-row">
                            <div class="field">
                                <label>Цена</label>
                                <input type="number" name="price" id="priceInput" min="0" step="10" value="0"
                                    placeholder="0 = бесплатно" oninput="updateSplit()">
                                <div class="field-hint">0 = бесплатно · Минимум 99 ₽ для платных</div>
                            </div>
                            <div class="field">
                                <label>Ваша доля <span id="devPct">70</span>%</label>
                                <div class="split-slider-wrap">
                                    <div class="split-bar-ui">
                                        <div class="dev" id="splitDev" style="width:70%"></div>
                                        <div class="plat" id="splitPlat" style="width:30%"></div>
                                    </div>
                                    <div class="split-info">
                                        <span class="si-dev">Вы получите: <strong id="devAmt">0 ₽</strong></span>
                                        <span class="si-plat">Платформа: <strong id="platAmt">0 ₽</strong></span>
                                    </div>
                                    <input type="range" class="split-input" name="dev_share" id="splitRange"
                                        min="50" max="90" value="70" oninput="updateSplit()">
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="dev_share" id="devShareHidden" value="70">

                        <div class="btn-row">
                            <button type="button" class="btn-back" onclick="goStep(2)">← Назад</button>
                            <button type="submit" class="btn-submit" id="submitBtn">
                                📤 Загрузить ассет (черновик)
                            </button>
                        </div>

                        <div class="draft-info" style="margin-top:16px">
                            ℹ️ Ассет будет сохранён как <strong>черновик</strong> и появится в каталоге после проверки модератором.
                        </div>
                    </div>

                </form>
            <?php endif; ?>

        </div><!-- /.upload-layout -->
    </main>

    <?php require_once('../swad/static/elements/footer.php'); ?>

    <script>
        let currentStep = 1;
        let selectedCat = null;

        const CAT_FORMATS = {
            '3d_model': ['ZIP', 'FBX', 'OBJ', 'GLTF', 'GLB', 'BLEND'],
            'texture': ['ZIP', 'PNG', 'TGA', 'EXR', 'PSD'],
            'music': ['ZIP', 'MP3', 'WAV', 'OGG', 'FLAC'],
            'sfx': ['ZIP', 'WAV', 'MP3', 'OGG'],
            'sprite': ['ZIP', 'PNG', 'ASEPRITE', 'GIF'],
            'shader': ['ZIP', 'UNITYPACKAGE', 'GDSHADER', 'HLSL', 'GLSL'],
            'font': ['ZIP', 'TTF', 'OTF', 'WOFF', 'WOFF2'],
            'script': ['ZIP', 'UNITYPACKAGE', 'CS', 'GD', 'LUA'],
            'ui_kit': ['ZIP', 'PSD', 'FIGMA', 'SVG', 'PNG'],
            'animation': ['ZIP', 'FBX', 'GLTF', 'BVH'],
            'vfx': ['ZIP', 'UNITYPACKAGE', 'UASSET'],
            'video': ['MP4', 'WEBM', 'MOV', 'ZIP'],
        };

        function selectCat(key) {
            selectedCat = key;

            const inp = document.getElementById('categoryInput');
            if (inp) inp.value = key;

            document.querySelectorAll('.cat-card').forEach(c =>
                c.classList.toggle('selected', c.dataset.cat === key));

            document.querySelectorAll('.cat-fields').forEach(f => f.classList.remove('visible'));
            const cf = document.getElementById('cf-' + key);
            if (cf) cf.classList.add('visible');

            const fmtsEl = document.getElementById('dz-file-formats');
            if (fmtsEl) {
                const fmts = CAT_FORMATS[key] || [];
                fmtsEl.innerHTML = fmts.map(f => `<span class="dz-fmt">${f}</span>`).join('');
            }

            const btn = document.getElementById('btn1next');
            if (btn) btn.disabled = false;
        }

        function validateStep2() {
            let ok = true;

            const nameEl = document.querySelector('input[name="name"]');
            const descEl = document.querySelector('textarea[name="description"]');
            const fName = document.getElementById('f-name');
            const fDesc = document.getElementById('f-desc');

            if (fName) fName.classList.remove('has-err');
            if (fDesc) fDesc.classList.remove('has-err');

            if (!nameEl || !nameEl.value.trim()) {
                if (fName) fName.classList.add('has-err');
                ok = false;
            }
            if (!descEl || !descEl.value.trim()) {
                if (fDesc) fDesc.classList.add('has-err');
                ok = false;
            }

            if (!ok) window.scrollTo({
                top: 200,
                behavior: 'smooth'
            });
            return ok;
        }

        function goStep(n) {
            if (n === 2 && !selectedCat) {
                alert('Выберите тип ассета');
                return;
            }
            if (n === 3 && !validateStep2()) return;

            currentStep = n;

            document.querySelectorAll('.step-panel').forEach((p, i) =>
                p.classList.toggle('active', i + 1 === n));

            [1, 2, 3].forEach(i => {
                const si = document.getElementById('si-' + i);
                if (!si) return;
                si.classList.remove('active', 'done');
                if (i === n) si.classList.add('active');
                else if (i < n) si.classList.add('done');
            });

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        /* ── Checkbox pills ── */
        document.querySelectorAll('.check-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const inp = pill.querySelector('input');
                if (!inp) return;
                setTimeout(() => pill.classList.toggle('checked', inp.checked), 0);
            });
        });

        /* ── File size formatter ── */
        function fmtSize(b) {
            if (b < 1024) return b + ' Б';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' КБ';
            if (b < 1073741824) return (b / 1048576).toFixed(1) + ' МБ';
            return (b / 1073741824).toFixed(2) + ' ГБ';
        }

        /* ── Cover ── */
        function onCoverChange(inp) {
            if (!inp.files[0]) return;
            const f = inp.files[0];
            const nameEl = document.getElementById('cover-name');
            const sizeEl = document.getElementById('cover-size');
            const selEl = document.getElementById('cover-selected');
            const prevEl = document.getElementById('cover-preview');
            const imgEl = document.getElementById('cover-img');
            if (nameEl) nameEl.textContent = f.name;
            if (sizeEl) sizeEl.textContent = fmtSize(f.size);
            if (selEl) selEl.classList.add('show');
            if (imgEl && prevEl) {
                const reader = new FileReader();
                reader.onload = e => {
                    imgEl.src = e.target.result;
                    prevEl.style.display = 'block';
                };
                reader.readAsDataURL(f);
            }
        }

        function clearCover() {
            const inp = document.querySelector('[name="cover"]');
            if (inp) inp.value = '';
            const selEl = document.getElementById('cover-selected');
            const prevEl = document.getElementById('cover-preview');
            if (selEl) selEl.classList.remove('show');
            if (prevEl) prevEl.style.display = 'none';
        }

        /* ── Previews ── */
        function onPreviewsChange(inp) {
            const grid = document.getElementById('preview-grid');
            if (!grid) return;
            Array.from(inp.files).slice(0, 8).forEach(f => {
                const reader = new FileReader();
                reader.onload = e => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" class="rm" onclick="this.parentElement.remove()">✕</button>`;
                    grid.appendChild(div);
                };
                reader.readAsDataURL(f);
            });
        }

        /* ── Asset file ── */
        function onAssetFileChange(inp) {
            if (!inp.files[0]) return;
            const f = inp.files[0];
            const nameEl = document.getElementById('file-name');
            const sizeEl = document.getElementById('file-size');
            const selEl = document.getElementById('file-selected');
            if (nameEl) nameEl.textContent = f.name;
            if (sizeEl) sizeEl.textContent = fmtSize(f.size);
            if (selEl) selEl.classList.add('show');
        }

        function clearAssetFile() {
            const inp = document.querySelector('[name="asset_file"]');
            if (inp) inp.value = '';
            const selEl = document.getElementById('file-selected');
            if (selEl) selEl.classList.remove('show');
        }

        /* ── Dropzone drag styling ── */
        document.querySelectorAll('.dropzone').forEach(dz => {
            dz.addEventListener('dragover', e => {
                e.preventDefault();
                dz.classList.add('drag-over');
            });
            dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
            dz.addEventListener('drop', e => {
                e.preventDefault();
                dz.classList.remove('drag-over');
            });
        });

        /* ── Revenue split ── */
        function updateSplit() {
            const priceEl = document.getElementById('priceInput');
            const rangeEl = document.getElementById('splitRange');
            if (!priceEl || !rangeEl) return;

            const price = parseFloat(priceEl.value) || 0;
            const pct = parseInt(rangeEl.value) || 70;

            const hiddenEl = document.getElementById('devShareHidden');
            const pctEl = document.getElementById('devPct');
            const devEl = document.getElementById('splitDev');
            const platEl = document.getElementById('splitPlat');
            const devAmt = document.getElementById('devAmt');
            const platAmt = document.getElementById('platAmt');

            if (hiddenEl) hiddenEl.value = pct;
            if (pctEl) pctEl.textContent = pct;
            if (devEl) devEl.style.width = pct + '%';
            if (platEl) platEl.style.width = (100 - pct) + '%';
            if (devAmt) devAmt.textContent = Math.round(price * pct / 100) + ' ₽';
            if (platAmt) platAmt.textContent = Math.round(price * (100 - pct) / 100) + ' ₽';
        }

        /* ── Submit ── */
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = '⏳ Загружаем…';
                }
            });
        }
    </script>
</body>

</html>