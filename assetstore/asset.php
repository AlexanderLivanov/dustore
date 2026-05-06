<?php
session_start();
require_once('../swad/config.php');
require_once('../swad/controllers/game.php');

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_GET['id'] ?? 0);
if ($asset_id <= 0) {
    header('Location: /assetstore/');
    exit;
}

/* ── Fetch asset ─────────────────────────────────────────────────────── */
try {
    $stmt = $pdo->prepare("SELECT a.*
                       FROM assets a
                       WHERE a.id = ? AND a.status = 'published'");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $asset = null;
}

if (!$asset) {
    header('Location: /assetstore/');
    exit;
}

/* ── Parse JSON fields ───────────────────────────────────────────────── */
$previews     = json_decode($asset['previews']     ?? '[]', true) ?: [];
$formats      = json_decode($asset['formats']      ?? '[]', true) ?: [];
$engines      = json_decode($asset['engine_compatibility'] ?? '[]', true) ?: [];
$tags         = !empty($asset['tags']) ? array_map('trim', explode(',', $asset['tags'])) : [];
$contents     = json_decode($asset['contents']     ?? '[]', true) ?: []; // list of included files/objects
$model3dUrl   = $asset['model_3d_url'] ?? ''; // .glb or .gltf URL for 3D viewer

/* ── Ownership check ─────────────────────────────────────────────────── */
$isOwned = false;
$purchasedDate = null;
if (!empty($_SESSION['USERDATA']['id'])) {
    $s2 = $pdo->prepare("SELECT date FROM asset_library WHERE player_id=? AND asset_id=? LIMIT 1");
    $s2->execute([$_SESSION['USERDATA']['id'], $asset_id]);
    $row = $s2->fetch(PDO::FETCH_ASSOC);
    $isOwned = (bool)$row;
    if ($isOwned && !empty($row['date'])) {
        $dt = new DateTime($row['date']);
        $m  = [
            'January' => 'января',
            'February' => 'февраля',
            'March' => 'марта',
            'April' => 'апреля',
            'May' => 'мая',
            'June' => 'июня',
            'July' => 'июля',
            'August' => 'августа',
            'September' => 'сентября',
            'October' => 'октября',
            'November' => 'ноября',
            'December' => 'декабря'
        ];
        $purchasedDate = $dt->format('d') . ' ' . $m[$dt->format('F')] . ' ' . $dt->format('Y');
    }
}

/* ── Downloads count ─────────────────────────────────────────────────── */
$dlCount = (int)($asset['downloads_count'] ?? 0);

/* ── Revenue split ───────────────────────────────────────────────────── */
$devSharePct = max(10, min(90, (int)($asset['dev_share'] ?? 70)));
$platSharePct = 100 - $devSharePct;
$price = (float)$asset['price'];
$devEarns  = round($price * $devSharePct  / 100, 2);
$platEarns = round($price * $platSharePct / 100, 2);

/* ── Category map ────────────────────────────────────────────────────── */
$CATS = [
    '3d_model'  => ['label' => '3D Модели',   'emoji' => '🧊'],
    'texture'   => ['label' => 'Текстуры',    'emoji' => '🖼️'],
    'music'     => ['label' => 'Музыка',      'emoji' => '🎵'],
    'sfx'       => ['label' => 'Звуки / SFX', 'emoji' => '🔊'],
    'sprite'    => ['label' => 'Спрайты / 2D', 'emoji' => '🎨'],
    'shader'    => ['label' => 'Шейдеры',     'emoji' => '✨'],
    'font'      => ['label' => 'Шрифты',      'emoji' => '🔤'],
    'script'    => ['label' => 'Скрипты',     'emoji' => '📜'],
    'ui_kit'    => ['label' => 'UI Киты',     'emoji' => '🎛️'],
    'animation' => ['label' => 'Анимации',    'emoji' => '🎬'],
    'vfx'       => ['label' => 'VFX / FX',    'emoji' => '💥'],
    'video'     => ['label' => 'Видео',       'emoji' => '📹'],
];
$cat = $CATS[$asset['category']] ?? ['label' => ucfirst($asset['category']), 'emoji' => '📦'];

$licenseLabels = [
    'cc0' => 'CC0 — Public Domain',
    'cc_by' => 'CC BY 4.0',
    'commercial' => 'Коммерческая',
    'personal' => 'Только личное использование'
];

function fmtBytes($b)
{
    if ($b < 1024) return $b . ' Б';
    if ($b < 1048576) return round($b / 1024, 1) . ' КБ';
    if ($b < 1073741824) return round($b / 1048576, 1) . ' МБ';
    return round($b / 1073741824, 2) . ' ГБ';
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — <?= htmlspecialchars($asset['name']) ?></title>
    <link rel="stylesheet" href="/swad/css/pages.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ═══ TOKENS ════════════════════════════════════════════════════════ */
        :root {
            --pr: #c32178;
            --pr-d: #74155d;
            --pr-glow: rgba(195, 33, 120, .22);
            --dark: #0d0118;
            --surf: rgba(255, 255, 255, .04);
            --surf2: rgba(255, 255, 255, .075);
            --bdr: rgba(255, 255, 255, .09);
            --bdr2: rgba(255, 255, 255, .15);
            --txt: #f0e6ff;
            --muted: rgba(240, 230, 255, .45);
            --success: #00e887;
            --warn: #f59e0b;
            --r: 14px;
            --sw: 320px;
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

        /* ═══ BANNER ════════════════════════════════════════════════════════ */
        .banner {
            width: 100%;
            height: 300px;
            background: linear-gradient(160deg, #1e0240, #0d0118);
            background-image: url('<?= htmlspecialchars($asset['path_to_cover'] ?? '') ?>');
            background-size: cover;
            background-position: center;
            position: relative;
            flex-shrink: 0;
        }

        .banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(13, 1, 24, .35) 0%, var(--dark) 100%)
        }

        @media(max-width:768px) {
            .banner {
                height: 200px
            }
        }

        /* ═══ LAYOUT ════════════════════════════════════════════════════════ */
        .wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px 80px;
            display: grid;
            grid-template-columns: 1fr var(--sw);
            grid-template-areas: "hd hd" "mn sd";
            gap: 0 32px
        }

        @media(max-width:960px) {
            .wrap {
                grid-template-columns: 1fr;
                grid-template-areas: "hd" "sd" "mn"
            }
        }

        /* ═══ HEADER ════════════════════════════════════════════════════════ */
        .ap-hd {
            grid-area: hd;
            padding: 32px 0 28px;
            display: flex;
            gap: 20px;
            align-items: flex-end
        }

        .ap-cover {
            width: 110px;
            height: 110px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid var(--bdr2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, .5);
            flex-shrink: 0
        }

        .ap-title-block {
            flex: 1;
            min-width: 0
        }

        .ap-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            font-size: .78rem;
            color: var(--muted)
        }

        .ap-breadcrumb a {
            color: var(--muted);
            text-decoration: none
        }

        .ap-breadcrumb a:hover {
            color: var(--txt)
        }

        .ap-breadcrumb span {
            opacity: .4
        }

        .ap-cat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: rgba(195, 33, 120, .18);
            border: 1px solid rgba(195, 33, 120, .3);
            color: #e88fc0;
        }

        .ap-title-block h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.5rem, 3.5vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.1;
            margin-bottom: 8px
        }

        .ap-author-line {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .85rem;
            color: var(--muted)
        }

        .ap-author-link {
            color: #e88fc0;
            text-decoration: none
        }

        .ap-author-link:hover {
            text-decoration: underline
        }

        .ap-meta-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px
        }

        .ap-pill {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: .75rem;
            color: var(--muted);
        }

        .ap-pill strong {
            color: var(--txt)
        }

        /* ═══ MAIN CONTENT ══════════════════════════════════════════════════ */
        .ap-main {
            grid-area: mn;
            min-width: 0;
            padding-top: 0
        }

        .ap-section {
            margin-bottom: 36px
        }

        .ap-section-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--bdr);
        }

        .ap-section-title::before {
            content: '';
            display: block;
            width: 3px;
            height: 16px;
            background: var(--pr);
            border-radius: 2px
        }

        /* ═══ 3D VIEWER ═════════════════════════════════════════════════════ */
        .viewer-wrap {
            position: relative;
            width: 100%;
            border-radius: var(--r);
            overflow: hidden;
            background: #0a0018;
            border: 1px solid var(--bdr);
            aspect-ratio: 16/9;
            min-height: 320px;
        }

        #viewer3d {
            width: 100%;
            height: 100%;
            display: block
        }

        .viewer-controls {
            position: absolute;
            bottom: 14px;
            right: 14px;
            display: flex;
            gap: 8px;
        }

        .viewer-btn {
            background: rgba(13, 1, 24, .75);
            border: 1px solid var(--bdr2);
            border-radius: 8px;
            color: var(--txt);
            font-size: .75rem;
            padding: 6px 12px;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: background .18s, border-color .18s;
            font-family: inherit;
        }

        .viewer-btn:hover {
            background: rgba(195, 33, 120, .2);
            border-color: rgba(195, 33, 120, .4)
        }

        .viewer-hint {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(13, 1, 24, .7);
            border: 1px solid var(--bdr);
            border-radius: 8px;
            padding: 7px 12px;
            font-size: .72rem;
            color: var(--muted);
            backdrop-filter: blur(8px);
            pointer-events: none;
            animation: fadeOut 3s forwards 2s;
        }

        @keyframes fadeOut {
            to {
                opacity: 0
            }
        }

        .viewer-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: #0a0018;
            transition: opacity .3s;
        }

        .viewer-loading.hidden {
            opacity: 0;
            pointer-events: none
        }

        .viewer-spinner {
            width: 36px;
            height: 36px;
            border: 2px solid var(--bdr2);
            border-top-color: var(--pr);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .viewer-loading p {
            color: var(--muted);
            font-size: .82rem
        }

        /* No 3D placeholder */
        .no-model {
            width: 100%;
            border-radius: var(--r);
            overflow: hidden;
            background: #0a0018;
            border: 1px solid var(--bdr);
        }

        /* ═══ GALLERY ═══════════════════════════════════════════════════════ */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px
        }

        .gallery-item {
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 16/9;
            cursor: pointer;
            background: var(--surf);
            border: 1px solid var(--bdr);
            transition: border-color .2s, transform .2s;
        }

        .gallery-item:hover {
            border-color: rgba(195, 33, 120, .4);
            transform: scale(1.02)
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 10px;
            padding: 4px
        }

        .tab {
            flex: 1;
            padding: 9px;
            border-radius: 8px;
            border: none;
            background: none;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
            font-family: inherit;
            text-align: center
        }

        .tab.active {
            background: var(--surf2);
            color: var(--txt);
            border: 1px solid var(--bdr2)
        }

        .tab-panel {
            display: none
        }

        .tab-panel.active {
            display: block
        }

        /* ═══ DESCRIPTION ═══════════════════════════════════════════════════ */
        .desc {
            font-size: .9rem;
            line-height: 1.75;
            color: rgba(240, 230, 255, .8)
        }

        .desc p {
            margin-bottom: 12px
        }

        /* Tags */
        .tags-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 12px
        }

        .tag {
            background: var(--surf2);
            border: 1px solid var(--bdr);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: .75rem;
            color: var(--muted)
        }

        /* Contents list */
        .contents-list {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .content-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 9px;
            padding: 10px 14px;
            font-size: .84rem;
        }

        .content-item .ci-icon {
            font-size: 1rem;
            flex-shrink: 0;
            width: 24px;
            text-align: center
        }

        .content-item .ci-name {
            flex: 1;
            font-weight: 600
        }

        .content-item .ci-size {
            color: var(--muted);
            font-size: .75rem;
            font-family: monospace
        }

        .content-item .ci-fmt {
            background: var(--surf2);
            border: 1px solid var(--bdr);
            border-radius: 4px;
            padding: 2px 7px;
            font-size: .65rem;
            font-weight: 700;
            color: var(--muted);
            font-family: monospace;
            text-transform: uppercase;
        }

        /* Specs table */
        .specs {
            width: 100%;
            border-collapse: collapse;
            font-size: .87rem
        }

        .specs tr {
            border-bottom: 1px solid var(--bdr)
        }

        .specs tr:last-child {
            border-bottom: none
        }

        .specs td {
            padding: 10px 14px
        }

        .specs td:first-child {
            color: var(--muted);
            width: 40%;
            font-size: .8rem
        }

        .specs td:last-child {
            color: var(--txt);
            font-weight: 600
        }

        /* Audio player */
        .audio-player {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ap-play {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--pr);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .18s, transform .18s;
            flex-shrink: 0;
        }

        .ap-play:hover {
            background: #d42485;
            transform: scale(1.08)
        }

        .audio-meta {
            flex: 1
        }

        .audio-meta .a-title {
            font-weight: 700;
            font-size: .88rem;
            margin-bottom: 2px
        }

        .audio-meta .a-dur {
            color: var(--muted);
            font-size: .75rem
        }

        .audio-waveform {
            width: 100%;
            height: 36px;
            margin-top: 10px
        }

        /* ═══ SIDEBAR ═══════════════════════════════════════════════════════ */
        .ap-side {
            grid-area: sd;
            padding-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        /* Purchase card */
        .buy-card {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r);
            overflow: hidden;
        }

        .buy-card-header {
            background: linear-gradient(135deg, rgba(195, 33, 120, .15), rgba(116, 21, 93, .1));
            border-bottom: 1px solid var(--bdr);
            padding: 20px 20px 16px;
        }

        .buy-price-label {
            font-size: .74rem;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .05em
        }

        .buy-price {
            font-family: 'Syne', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1
        }

        .buy-price.free {
            color: var(--success)
        }

        .buy-price-sub {
            font-size: .78rem;
            color: var(--muted);
            margin-top: 4px
        }

        .buy-card-body {
            padding: 16px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        /* CTA Button */
        .cta-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s var(--ease);
            font-family: inherit;
            text-decoration: none;
        }

        .cta-btn.primary {
            background: var(--pr);
            color: #fff
        }

        .cta-btn.primary:hover {
            background: #d42485;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(195, 33, 120, .4)
        }

        .cta-btn.free-dl {
            background: rgba(0, 232, 135, .15);
            color: var(--success);
            border: 1px solid rgba(0, 232, 135, .3)
        }

        .cta-btn.free-dl:hover {
            background: rgba(0, 232, 135, .22);
            transform: translateY(-2px)
        }

        .cta-btn.owned {
            background: rgba(0, 232, 135, .1);
            color: var(--success);
            border: 1px solid rgba(0, 232, 135, .25);
            cursor: default
        }

        .cta-btn.owned:hover {
            transform: none;
            box-shadow: none
        }

        .cta-btn-sub {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 11px;
            border-radius: 10px;
            border: 1px solid var(--bdr2);
            background: none;
            color: var(--muted);
            font-size: .82rem;
            cursor: pointer;
            transition: all .18s;
            font-family: inherit;
            text-decoration: none;
            text-align: center;
        }

        .cta-btn-sub:hover {
            border-color: var(--bdr2);
            color: var(--txt);
            background: var(--surf2)
        }

        /* Owned badge */
        .owned-info {
            background: rgba(0, 232, 135, .08);
            border: 1px solid rgba(0, 232, 135, .22);
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .82rem;
        }

        .owned-info .oi-ico {
            font-size: 1.2rem
        }

        .owned-info .oi-date {
            color: var(--muted);
            font-size: .74rem;
            margin-top: 2px
        }

        /* Dev split preview (visible to all on paid assets) */
        .split-preview {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .split-preview .sp-title {
            font-size: .72rem;
            color: var(--muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
            display: flex;
            align-items: center;
            gap: 5px
        }

        .split-bar {
            height: 8px;
            border-radius: 100px;
            background: var(--surf2);
            margin-bottom: 8px;
            overflow: hidden;
            display: flex
        }

        .split-bar .dev {
            background: var(--pr);
            border-radius: 100px 0 0 100px;
            transition: width .6s var(--ease)
        }

        .split-bar .plat {
            background: rgba(255, 255, 255, .12);
            border-radius: 0 100px 100px 0
        }

        .split-labels {
            display: flex;
            justify-content: space-between;
            font-size: .76rem
        }

        .split-labels .sl-dev {
            color: #e88fc0;
            font-weight: 700
        }

        .split-labels .sl-plat {
            color: var(--muted)
        }

        /* Asset info list */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 1px
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid var(--bdr);
            font-size: .82rem;
        }

        .info-row:last-child {
            border-bottom: none
        }

        .info-row .ir-l {
            color: var(--muted)
        }

        .info-row .ir-r {
            font-weight: 600;
            color: var(--txt);
            text-align: right;
            max-width: 60%
        }

        /* Rating widget */
        .rating-big {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 10px;
        }

        .rb-num {
            font-family: 'Syne', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--warn);
            line-height: 1
        }

        .rb-stars {
            font-size: 1rem;
            color: var(--warn)
        }

        .rb-count {
            font-size: .75rem;
            color: var(--muted);
            margin-top: 2px
        }

        /* Author card */
        .author-card {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r);
            padding: 16px 16px 14px;
        }

        .author-card .ac-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px
        }

        .author-card .ac-ava {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--surf2)
        }

        .author-card .ac-name {
            font-weight: 700;
            font-size: .9rem
        }

        .author-card .ac-sub {
            font-size: .74rem;
            color: var(--muted)
        }

        .author-card .ac-stats {
            display: flex;
            gap: 16px
        }

        .author-card .ac-stat-n {
            font-weight: 700;
            font-size: .92rem
        }

        .author-card .ac-stat-l {
            font-size: .7rem;
            color: var(--muted)
        }

        .author-card .ac-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 14px;
            border: 1px solid var(--bdr);
            border-radius: 8px;
            padding: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: .78rem;
            transition: all .18s;
        }

        .author-card .ac-link:hover {
            border-color: var(--bdr2);
            color: var(--txt)
        }

        /* ═══ REVIEWS ═══════════════════════════════════════════════════════ */
        .review-card {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r);
            padding: 16px;
            margin-bottom: 12px;
        }

        .rv-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 10px
        }

        .rv-author {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .rv-ava {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surf2)
        }

        .rv-name {
            font-weight: 700;
            font-size: .85rem
        }

        .rv-stars {
            color: var(--warn);
            font-size: .78rem
        }

        .rv-date {
            font-size: .72rem;
            color: var(--muted)
        }

        .rv-text {
            font-size: .86rem;
            line-height: 1.65;
            color: rgba(240, 230, 255, .8)
        }

        .rv-dev-reply {
            margin-top: 12px;
            background: rgba(195, 33, 120, .08);
            border-left: 3px solid var(--pr);
            border-radius: 0 8px 8px 0;
            padding: 10px 12px;
            font-size: .82rem;
        }

        .rv-dev-badge {
            font-size: .7rem;
            font-weight: 700;
            color: #e88fc0;
            margin-bottom: 5px
        }

        .review-form {
            margin-top: 20px;
            padding: 18px;
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r)
        }

        .review-form h3 {
            font-size: .9rem;
            font-weight: 700;
            margin-bottom: 14px
        }

        .review-form textarea {
            width: 100%;
            padding: 12px;
            background: var(--surf2);
            border: 1px solid var(--bdr2);
            border-radius: 10px;
            color: var(--txt);
            font-family: inherit;
            font-size: .86rem;
            resize: vertical;
            min-height: 90px;
            outline: none;
            transition: border-color .18s;
        }

        .review-form textarea:focus {
            border-color: var(--pr)
        }

        #review-stars span {
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--bdr2);
            transition: color .1s
        }

        #review-stars span.highlighted {
            color: var(--warn)
        }

        /* ═══ LIGHTBOX ══════════════════════════════════════════════════════ */
        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .92);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
            backdrop-filter: blur(4px)
        }

        .lightbox img {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: var(--r);
            box-shadow: 0 24px 80px rgba(0, 0, 0, .8)
        }

        /* ═══ PAYMENT MODAL ═════════════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10000;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(6px);
            padding: 20px;
        }

        .modal.open {
            display: flex
        }

        .modal-box {
            background: #130125;
            border: 1px solid var(--bdr2);
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0, 0, 0, .7), 0 0 0 1px rgba(195, 33, 120, .15);
            animation: slideUp .25s var(--ease);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .modal-top {
            padding: 22px 24px 18px;
            border-bottom: 1px solid var(--bdr);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-top h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800
        }

        .modal-close-x {
            background: none;
            border: none;
            color: var(--muted);
            font-size: 1.4rem;
            cursor: pointer;
            line-height: 1;
            padding: 2px
        }

        .modal-close-x:hover {
            color: var(--txt)
        }

        .modal-body {
            padding: 22px 24px
        }

        /* Asset summary in modal */
        .modal-asset {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px
        }

        .modal-asset img {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid var(--bdr)
        }

        .modal-asset-name {
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: 3px
        }

        .modal-asset-auth {
            font-size: .76rem;
            color: var(--muted)
        }

        /* Price breakdown */
        .pay-breakdown {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 18px
        }

        .pb-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 14px;
            border-bottom: 1px solid var(--bdr);
            font-size: .84rem
        }

        .pb-row:last-child {
            border-bottom: none
        }

        .pb-row.total {
            background: rgba(195, 33, 120, .08);
            font-weight: 700
        }

        .pb-label {
            color: var(--muted)
        }

        .pb-label strong {
            color: var(--txt)
        }

        .pb-val {
            font-weight: 700
        }

        .pb-val.dev {
            color: #e88fc0
        }

        .pb-val.plat {
            color: var(--muted)
        }

        .pb-val.total {
            color: var(--txt);
            font-size: .95rem
        }

        /* Split bar in modal */
        .modal-split-bar {
            height: 6px;
            border-radius: 100px;
            overflow: hidden;
            display: flex;
            margin: -4px 14px 14px
        }

        .modal-split-bar .d {
            background: var(--pr);
            border-radius: 100px 0 0 100px
        }

        .modal-split-bar .p {
            background: rgba(255, 255, 255, .12);
            border-radius: 0 100px 100px 0
        }

        /* Offer link */
        .offer-link {
            font-size: .74rem;
            color: var(--muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            justify-content: center;
            margin-bottom: 14px
        }

        .offer-link:hover {
            color: var(--txt)
        }

        .pay-btn {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: var(--pr);
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
        }

        .pay-btn:hover {
            background: #d42485;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(195, 33, 120, .4)
        }

        .pay-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none
        }

        .pay-secure {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: .72rem;
            color: var(--muted);
            margin-top: 10px
        }

        /* Offer modal */
        .offer-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10100;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .82);
            backdrop-filter: blur(8px);
            padding: 20px;
        }

        .offer-modal.open {
            display: flex
        }

        .offer-box {
            background: #130125;
            border: 1px solid var(--bdr2);
            border-radius: 20px;
            width: 100%;
            max-width: 560px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 28px 28px 32px;
            box-shadow: 0 32px 80px rgba(0, 0, 0, .8);
            animation: slideUp .25s var(--ease);
        }

        .offer-box h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 18px
        }

        .offer-box h3 {
            font-size: .85rem;
            font-weight: 700;
            margin: 16px 0 6px;
            color: #e88fc0
        }

        .offer-box p {
            font-size: .83rem;
            line-height: 1.65;
            color: rgba(240, 230, 255, .75);
            margin-bottom: 8px
        }

        /* btn shared */
        .btn-sm {
            padding: 9px 18px;
            border-radius: 9px;
            border: 1px solid var(--bdr2);
            background: none;
            color: var(--txt);
            font-size: .8rem;
            cursor: pointer;
            transition: all .18s;
            font-family: inherit
        }

        .btn-sm:hover {
            border-color: var(--pr);
            color: #e88fc0
        }

        .btn-sm.primary {
            background: var(--pr);
            border-color: var(--pr);
            color: #fff
        }

        .btn-sm.primary:hover {
            background: #d42485
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px
        }
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>

    <main>
        <!-- Banner -->
        <div class="banner"></div>

        <div class="wrap container">

            <!-- ═══ HEADER ═══════════════════════════════════════════════════ -->
            <header class="ap-hd">
                <img class="ap-cover"
                    src="<?= !empty($asset['path_to_cover']) ? htmlspecialchars($asset['path_to_cover']) : 'https://placehold.co/110x110/160028/c32178?text=' . urlencode($cat['label']) ?>"
                    alt="<?= htmlspecialchars($asset['name']) ?>">
                <div class="ap-title-block">
                    <div class="ap-breadcrumb">
                        <a href="/assetstore/">Ассеты</a>
                        <span>›</span>
                        <a href="/assetstore/?category=<?= $asset['category'] ?>"><?= $cat['label'] ?></a>
                    </div>
                    <div class="ap-cat-badge"><?= $cat['emoji'] ?> <?= $cat['label'] ?></div>
                    <h1><?= htmlspecialchars($asset['name']) ?></h1>
                    <div class="ap-author-line">
                        by <a class="ap-author-link" href="/studio/<?= urlencode($asset['studio_name']) ?>">
                            <?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?>
                        </a>
                        <?php if (!empty($asset['version'])): ?>
                            <span class="ap-pill">v<?= htmlspecialchars($asset['version']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ap-meta-pills">
                        <?php if ($dlCount > 0): ?>
                            <span class="ap-pill">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="7 10 12 15 17 10" />
                                    <line x1="12" y1="15" x2="12" y2="3" />
                                </svg>
                                <strong><?= number_format($dlCount) ?></strong>&nbsp;скачиваний
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($asset['file_size_bytes'])): ?>
                            <span class="ap-pill">
                                📦 <strong><?= fmtBytes($asset['file_size_bytes']) ?></strong>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($asset['avg_rating'])): ?>
                            <span class="ap-pill">
                                ★ <strong><?= round($asset['avg_rating'], 1) ?></strong>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($asset['license'])): ?>
                            <span class="ap-pill">
                                📜 <strong><?= htmlspecialchars($licenseLabels[$asset['license']] ?? $asset['license']) ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- ═══ SIDEBAR ══════════════════════════════════════════════════ -->
            <aside class="ap-side">

                <!-- Buy card -->
                <div class="buy-card">
                    <div class="buy-card-header">
                        <div class="buy-price-label"><?= $price > 0 ? 'Цена' : 'Доступно' ?></div>
                        <div class="buy-price <?= $price == 0 ? 'free' : '' ?>">
                            <?= $price == 0 ? 'Бесплатно' : number_format($price, 0, ',', ' ') . ' ₽' ?>
                        </div>
                        <?php if ($price > 0): ?><div class="buy-price-sub">разовая покупка · навсегда</div><?php endif; ?>
                    </div>
                    <div class="buy-card-body">
                        <?php if ($isOwned): ?>
                            <div class="owned-info">
                                <span class="oi-ico">✅</span>
                                <div>
                                    <div style="font-weight:700;color:var(--success)">Ассет у вас есть</div>
                                    <?php if ($purchasedDate): ?><div class="oi-date">Получен <?= $purchasedDate ?></div><?php endif; ?>
                                </div>
                            </div>
                            <a href="/swad/controllers/download_asset.php?id=<?= $asset_id ?>" class="cta-btn owned">
                                ↓ Скачать снова
                            </a>
                        <?php elseif ($price == 0): ?>
                            <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                                <button class="cta-btn free-dl" onclick="getAssetFree(<?= $asset_id ?>)">
                                    🎁 Получить бесплатно
                                </button>
                            <?php else: ?>
                                <a href="/login" class="cta-btn free-dl">🔑 Войдите чтобы скачать</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                                <button class="cta-btn primary" id="openPayBtn" onclick="openPayModal()">
                                    🛒 Купить — <?= number_format($price, 0, ',', ' ') ?> ₽
                                </button>
                            <?php else: ?>
                                <a href="/login" class="cta-btn primary">🔑 Войти и купить</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!$isOwned): ?>
                            <button class="cta-btn-sub" onclick="toggleWishlist(<?= $asset_id ?>)">
                                ♡ В список желаний
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($price > 0): ?>
                    <!-- Revenue split preview -->
                    <div class="split-preview">
                        <div class="sp-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                            Разделение выручки
                        </div>
                        <div class="split-bar">
                            <div class="dev" style="width:<?= $devSharePct ?>%"></div>
                            <div class="plat" style="width:<?= $platSharePct ?>%"></div>
                        </div>
                        <div class="split-labels">
                            <span class="sl-dev">
                                Разработчик <?= $devSharePct ?>% — <?= number_format($devEarns, 2, ',', ' ') ?> ₽
                            </span>
                            <span class="sl-plat">
                                Платформа <?= $platSharePct ?>%
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Asset info -->
                <div class="buy-card" style="padding:0 0 4px">
                    <div class="info-list" style="padding:4px 16px 8px">
                        <div class="info-row">
                            <span class="ir-l">Категория</span>
                            <span class="ir-r"><?= $cat['emoji'] ?> <?= $cat['label'] ?></span>
                        </div>
                        <?php if (!empty($formats)): ?>
                            <div class="info-row">
                                <span class="ir-l">Форматы</span>
                                <span class="ir-r" style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars(implode(', ', $formats)) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($engines)): ?>
                            <div class="info-row">
                                <span class="ir-l">Совместимость</span>
                                <span class="ir-r"><?= htmlspecialchars(implode(', ', $engines)) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($asset['license'])): ?>
                            <div class="info-row">
                                <span class="ir-l">Лицензия</span>
                                <span class="ir-r"><?= htmlspecialchars($licenseLabels[$asset['license']] ?? $asset['license']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($asset['file_size_bytes'])): ?>
                            <div class="info-row">
                                <span class="ir-l">Размер файла</span>
                                <span class="ir-r"><?= fmtBytes($asset['file_size_bytes']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($asset['version'])): ?>
                            <div class="info-row">
                                <span class="ir-l">Версия</span>
                                <span class="ir-r">v<?= htmlspecialchars($asset['version']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="ir-l">Добавлен</span>
                            <span class="ir-r"><?= date('d.m.Y', strtotime($asset['created_at'] ?? 'now')) ?></span>
                        </div>
                        <?php if (!empty($asset['updated_at'])): ?>
                            <div class="info-row">
                                <span class="ir-l">Обновлён</span>
                                <span class="ir-r"><?= date('d.m.Y', strtotime($asset['updated_at'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="ir-l">Скачиваний</span>
                            <span class="ir-r"><?= number_format($dlCount) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Author card -->
                <div class="author-card">
                    <div class="ac-head">
                        <div class="ac-ava" style="background:var(--surf2);display:flex;align-items:center;justify-content:center;font-size:1.4rem;border-radius:12px;width:44px;height:44px">🎮</div>
                        <div>
                            <div class="ac-name"><?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?></div>
                            <div class="ac-sub">Разработчик ассетов</div>
                        </div>
                    </div>
                    <a href="/studio/<?= urlencode($asset['studio_name']) ?>" class="ac-link">
                        Профиль автора →
                    </a>
                </div>

            </aside>

            <!-- ═══ MAIN CONTENT ══════════════════════════════════════════════ -->
            <section class="ap-main">

                <!-- Tabs -->
                <div class="tabs" role="tablist">
                    <button class="tab active" data-tab="preview">
                        <?= $model3dUrl ? '🧊 3D Просмотр' : '🖼️ Превью' ?>
                    </button>
                    <button class="tab" data-tab="about">📄 Описание</button>
                    <button class="tab" data-tab="contents">📦 Состав</button>
                    <button class="tab" data-tab="tech">⚙️ Характеристики</button>
                    <button class="tab" data-tab="reviews">💬 Отзывы</button>
                </div>

                <!-- TAB: Preview / 3D Viewer -->
                <div class="tab-panel active" id="tab-preview">
                    <?php if (!empty($model3dUrl)): ?>
                        <!-- 3D Model Viewer -->
                        <div class="ap-section">
                            <div class="ap-section-title">3D Просмотр</div>
                            <div class="viewer-wrap">
                                <canvas id="viewer3d"></canvas>
                                <div class="viewer-loading" id="viewerLoading">
                                    <div class="viewer-spinner"></div>
                                    <p>Загрузка 3D модели…</p>
                                </div>
                                <div class="viewer-hint">🖱 Вращайте · Скролл для зума · ПКМ для перемещения</div>
                                <div class="viewer-controls">
                                    <button class="viewer-btn" onclick="resetCamera()">⟳ Сброс</button>
                                    <button class="viewer-btn" onclick="toggleWireframe()">⬡ Каркас</button>
                                    <button class="viewer-btn" onclick="toggleAutoRotate()">▶ Авто</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Gallery -->
                    <?php if (!empty($previews)): ?>
                        <div class="ap-section">
                            <div class="ap-section-title"><?= $model3dUrl ? 'Изображения' : 'Галерея' ?></div>
                            <div class="gallery-grid">
                                <?php foreach ($previews as $img): ?>
                                    <div class="gallery-item" onclick="openLightbox('<?= htmlspecialchars($img) ?>')">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Preview" loading="lazy">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif (empty($model3dUrl)): ?>
                        <!-- No preview: show cover -->
                        <div class="ap-section">
                            <div class="no-model" style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center">
                                <div style="text-align:center;opacity:.4">
                                    <div style="font-size:3rem;margin-bottom:8px"><?= $cat['emoji'] ?></div>
                                    <div style="font-size:.85rem">Превью недоступно</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Audio player for music/sfx -->
                    <?php if (in_array($asset['category'], ['music', 'sfx']) && !empty($asset['preview_audio'])): ?>
                        <div class="ap-section">
                            <div class="ap-section-title">🎵 Аудио-превью</div>
                            <div class="audio-player">
                                <button class="ap-play" id="playBtn" onclick="toggleAudio()">
                                    <svg id="playIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21 5 3" />
                                    </svg>
                                </button>
                                <div class="audio-meta">
                                    <div class="a-title"><?= htmlspecialchars($asset['name']) ?></div>
                                    <div class="a-dur" id="audioDur">0:00 / 0:00</div>
                                    <canvas class="audio-waveform" id="waveformCanvas" height="36"></canvas>
                                </div>
                            </div>
                            <audio id="assetAudio" src="<?= htmlspecialchars($asset['preview_audio']) ?>" preload="metadata"></audio>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB: About -->
                <div class="tab-panel" id="tab-about">
                    <div class="ap-section">
                        <div class="ap-section-title">Описание</div>
                        <div class="desc">
                            <?php if (!empty($asset['description'])): ?>
                                <?= nl2br(htmlspecialchars($asset['description'])) ?>
                            <?php else: ?>
                                <p style="color:var(--muted)">Описание не добавлено.</p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tags)): ?>
                            <div class="tags-row">
                                <?php foreach ($tags as $t): ?>
                                    <a class="tag" href="/assetstore/?q=<?= urlencode($t) ?>">#<?= htmlspecialchars($t) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB: Contents -->
                <div class="tab-panel" id="tab-contents">
                    <div class="ap-section">
                        <div class="ap-section-title">Состав пакета</div>
                        <?php if (!empty($contents)): ?>
                            <div class="contents-list">
                                <?php
                                $extIcons = [
                                    'fbx' => '🧊',
                                    'obj' => '🧊',
                                    'gltf' => '🧊',
                                    'glb' => '🧊',
                                    'png' => '🖼️',
                                    'jpg' => '🖼️',
                                    'jpeg' => '🖼️',
                                    'tga' => '🖼️',
                                    'psd' => '🖼️',
                                    'mp3' => '🎵',
                                    'wav' => '🎵',
                                    'ogg' => '🎵',
                                    'flac' => '🎵',
                                    'ttf' => '🔤',
                                    'otf' => '🔤',
                                    'woff' => '🔤',
                                    'glsl' => '✨',
                                    'hlsl' => '✨',
                                    'shader' => '✨',
                                    'cs' => '📜',
                                    'js' => '📜',
                                    'lua' => '📜',
                                    'gd' => '📜',
                                    'mp4' => '📹',
                                    'webm' => '📹'
                                ];
                                foreach ($contents as $item):
                                    $ext = strtolower(pathinfo($item['name'] ?? '', PATHINFO_EXTENSION));
                                    $ico = $extIcons[$ext] ?? '📄';
                                ?>
                                    <div class="content-item">
                                        <span class="ci-icon"><?= $ico ?></span>
                                        <span class="ci-name"><?= htmlspecialchars($item['name'] ?? '') ?></span>
                                        <?php if (!empty($item['size'])): ?>
                                            <span class="ci-size"><?= fmtBytes($item['size']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($ext): ?>
                                            <span class="ci-fmt"><?= $ext ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--muted);font-size:.88rem">Состав пакета не указан.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB: Tech specs -->
                <div class="tab-panel" id="tab-tech">
                    <div class="ap-section">
                        <div class="ap-section-title">Технические характеристики</div>
                        <table class="specs">
                            <tr>
                                <td>Категория</td>
                                <td><?= $cat['emoji'] ?> <?= $cat['label'] ?></td>
                            </tr>
                            <?php if (!empty($formats)): ?>
                                <tr>
                                    <td>Форматы файлов</td>
                                    <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars(implode(', ', $formats)) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($engines)): ?>
                                <tr>
                                    <td>Совместимые движки</td>
                                    <td><?= htmlspecialchars(implode(', ', $engines)) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['poly_count'])): ?>
                                <tr>
                                    <td>Полигонов</td>
                                    <td><?= number_format($asset['poly_count']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['texture_size'])): ?>
                                <tr>
                                    <td>Разрешение текстур</td>
                                    <td><?= htmlspecialchars($asset['texture_size']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['rigged'])): ?>
                                <tr>
                                    <td>Риг</td>
                                    <td><?= $asset['rigged'] ? 'Да' : 'Нет' ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['animated'])): ?>
                                <tr>
                                    <td>Анимации</td>
                                    <td><?= $asset['animated'] ? 'Да' : 'Нет' ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['file_size_bytes'])): ?>
                                <tr>
                                    <td>Размер архива</td>
                                    <td><?= fmtBytes($asset['file_size_bytes']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($asset['license'])): ?>
                                <tr>
                                    <td>Лицензия</td>
                                    <td><?= htmlspecialchars($licenseLabels[$asset['license']] ?? $asset['license']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td>Опубликован</td>
                                <td><?= date('d.m.Y', strtotime($asset['created_at'] ?? 'now')) ?></td>
                            </tr>
                            <?php if (!empty($asset['updated_at'])): ?>
                                <tr>
                                    <td>Последнее обновление</td>
                                    <td><?= date('d.m.Y', strtotime($asset['updated_at'])) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- TAB: Reviews -->
                <div class="tab-panel" id="tab-reviews">
                    <div class="ap-section">
                        <?php if (!empty($asset['avg_rating'])): ?>
                            <div class="rating-big" style="margin-bottom:18px">
                                <div>
                                    <div class="rb-num"><?= number_format($asset['avg_rating'], 1) ?></div>
                                    <div class="rb-stars">
                                        <?php for ($i = 1; $i <= 5; $i++) echo $i <= $asset['avg_rating'] / 2 ? '★' : '☆'; ?>
                                    </div>
                                    <div class="rb-count">из 10</div>
                                </div>
                                <div style="flex:1">
                                    <!-- Could add bar chart here -->
                                </div>
                            </div>
                        <?php endif; ?>

                        <div id="reviews-container">
                            <p style="color:var(--muted);font-size:.88rem">Загрузка отзывов…</p>
                        </div>

                        <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                            <div class="review-form">
                                <h3>Оставить отзыв</h3>
                                <textarea id="review-text" placeholder="Поделитесь опытом использования этого ассета…"></textarea>
                                <div style="margin:10px 0 14px;display:flex;align-items:center;gap:8px">
                                    <span style="font-size:.82rem;color:var(--muted)">Оценка:</span>
                                    <div id="review-stars"></div>
                                </div>
                                <button class="btn-sm primary" id="submit-review">Отправить отзыв</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <!-- ══ PAYMENT MODAL ══════════════════════════════════════════════════════ -->
    <?php if ($price > 0 && !$isOwned): ?>
        <div class="modal" id="payModal" onclick="if(event.target===this)closePayModal()">
            <div class="modal-box">
                <div class="modal-top">
                    <h2>💳 Оформление покупки</h2>
                    <button class="modal-close-x" onclick="closePayModal()">✕</button>
                </div>
                <div class="modal-body">

                    <!-- Asset summary -->
                    <div class="modal-asset">
                        <img src="<?= !empty($asset['path_to_cover']) ? htmlspecialchars($asset['path_to_cover']) : 'https://via.placeholder.com/56/160028/c32178' ?>"
                            alt="<?= htmlspecialchars($asset['name']) ?>">
                        <div>
                            <div class="modal-asset-name"><?= htmlspecialchars($asset['name']) ?></div>
                            <div class="modal-asset-auth">by <?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?></div>
                        </div>
                    </div>

                    <!-- Breakdown -->
                    <div class="pay-breakdown">
                        <div class="pb-row">
                            <span class="pb-label">Стоимость ассета</span>
                            <span class="pb-val"><?= number_format($price, 2, ',', ' ') ?> ₽</span>
                        </div>
                        <div class="pb-row">
                            <span class="pb-label">
                                <strong>→ Разработчику</strong>&nbsp;(<?= $devSharePct ?>%)
                            </span>
                            <span class="pb-val dev"><?= number_format($devEarns, 2, ',', ' ') ?> ₽</span>
                        </div>
                        <div class="pb-row">
                            <span class="pb-label">→ Платформе&nbsp;(<?= $platSharePct ?>%)</span>
                            <span class="pb-val plat"><?= number_format($platEarns, 2, ',', ' ') ?> ₽</span>
                        </div>
                        <div class="modal-split-bar">
                            <div class="d" style="width:<?= $devSharePct ?>%"></div>
                            <div class="p" style="width:<?= $platSharePct ?>%"></div>
                        </div>
                        <div class="pb-row total">
                            <span class="pb-label"><strong>Итого к оплате</strong></span>
                            <span class="pb-val total"><?= number_format($price, 2, ',', ' ') ?> ₽</span>
                        </div>
                    </div>

                    <a href="#" class="offer-link" onclick="openOfferModal();return false">
                        📄 Публичная оферта продавца
                    </a>

                    <button class="pay-btn" id="payBtn" onclick="submitPayment(<?= $asset_id ?>)">
                        Оплатить <?= number_format($price, 2, ',', ' ') ?> ₽
                    </button>
                    <div class="pay-secure">
                        🔒 Защищённая оплата через ЮКасса
                    </div>
                </div>
            </div>
        </div>

        <!-- Offer modal -->
        <div class="offer-modal" id="offerModal" onclick="if(event.target===this)this.classList.remove('open')">
            <div class="offer-box">
                <h2>Публичная оферта</h2>
                <p><strong>г. <?= htmlspecialchars($asset['city'] ?? 'Москва') ?></strong> &nbsp; <?= date('d.m.Y', strtotime($asset['created_at'] ?? 'now')) ?></p>
                <p><strong><?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?></strong></p>
                <p>ИНН: <?= htmlspecialchars($asset['INN'] ?? '—') ?></p>
                <p>Расчётный счёт: <?= htmlspecialchars($asset['acc_num'] ?? '—') ?></p>
                <p>Банк: <?= htmlspecialchars($asset['bank_name'] ?? '—') ?>, БИК: <?= htmlspecialchars($asset['BIC'] ?? '—') ?></p>
                <h3>1. Предмет оферты</h3>
                <p><?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?> предлагает заключить договор купли-продажи цифрового ассета «<?= htmlspecialchars($asset['name']) ?>».</p>
                <h3>2. Момент заключения договора</h3>
                <p>Акцептом оферты является совершение платежа за товар.</p>
                <h3>3. Цена и расчёты</h3>
                <p>Цена: <?= number_format($price, 2, ',', ' ') ?> ₽. Расчёты через ЮКасса. Из суммы <?= $devSharePct ?>% направляется разработчику, <?= $platSharePct ?>% — платформе.</p>
                <h3>4. Передача товара</h3>
                <p>Доступ к скачиванию предоставляется сразу после подтверждения оплаты.</p>
                <h3>5. Возврат</h3>
                <p>Цифровые товары надлежащего качества возврату не подлежат (ст. 26.1 ЗоЗПП).</p>
                <h3>6. Реквизиты продавца</h3>
                <p><?= htmlspecialchars($asset['studio_display'] ?? $asset['studio_name']) ?>, ИНН: <?= htmlspecialchars($asset['INN'] ?? '—') ?></p>
                <p>Email: <?= htmlspecialchars($asset['contact_email'] ?? '—') ?></p>
                <div style="margin-top:20px;text-align:right">
                    <button class="btn-sm primary" onclick="document.getElementById('offerModal').classList.remove('open')">Закрыть</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php require_once('../swad/static/elements/footer.php'); ?>

    <!-- Three.js for 3D viewer -->
    <?php if (!empty($model3dUrl)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <?php endif; ?>

    <!-- Three.js только если есть модель -->
    <?php if (!empty($model3dUrl)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <?php endif; ?>

    <script>
        /* ── Tabs ── */
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab)?.classList.add('active');
            });
        });

        /* ── Lightbox ── */
        function openLightbox(src) {
            const lb = document.createElement('div');
            lb.className = 'lightbox';
            lb.innerHTML = `<img src="${src}" alt="">`;
            lb.addEventListener('click', () => lb.remove());
            document.addEventListener('keydown', function esc(e) {
                if (e.key === 'Escape') {
                    lb.remove();
                    document.removeEventListener('keydown', esc);
                }
            });
            document.body.appendChild(lb);
        }

        /* ── Payment modal ── */
        function openPayModal() {
            document.getElementById('payModal')?.classList.add('open');
        }

        function closePayModal() {
            document.getElementById('payModal')?.classList.remove('open');
        }

        function openOfferModal() {
            document.getElementById('offerModal')?.classList.add('open');
        }

        function submitPayment(assetId) {
            const btn = document.getElementById('payBtn');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.textContent = 'Обработка…';
            fetch('../swad/controllers/buy_asset.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `asset_id=${assetId}`
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        if (d.payment_url) window.location.href = d.payment_url;
                        else location.reload();
                    } else {
                        alert('Ошибка: ' + (d.error || 'Неизвестная ошибка'));
                        btn.disabled = false;
                        btn.textContent = 'Оплатить <?= number_format($price, 2, ",", " ") ?> ₽';
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = 'Повторить';
                });
        }

        function getAssetFree(id) {
            fetch('../swad/controllers/get_asset_free.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `asset_id=${id}`
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) location.reload();
                    else alert('Ошибка: ' + (d.error || ''));
                });
        }

        function toggleWishlist(id) {
            fetch('../swad/controllers/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `asset_id=${id}`
            }).then(r => r.json()).catch(() => {});
        }

        /* ── Reviews ── */
        (function() {
            const assetId = <?= (int)$asset_id ?>;
            const userId = <?= (int)($_SESSION['USERDATA']['id'] ?? 0) ?>;
            const container = document.getElementById('reviews-container');
            if (!container) return;

            function esc(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            // Грузим отзывы — если контроллера нет, показываем заглушку
            fetch(`../swad/controllers/get_asset_reviews.php?asset_id=${assetId}`, {
                    credentials: 'same-origin'
                })
                .then(r => {
                    if (!r.ok) throw new Error('not found');
                    return r.json();
                })
                .then(data => {
                    const reviews = Array.isArray(data.reviews) ? data.reviews : [];
                    if (!reviews.length) {
                        container.innerHTML = '<p style="color:var(--muted);font-size:.88rem">Отзывов пока нет. Будьте первым!</p>';
                        initStars(10);
                        return;
                    }
                    container.innerHTML = '';
                    reviews.forEach(r => {
                        const d = document.createElement('div');
                        d.className = 'review-card';
                        d.innerHTML = `
                    <div class="rv-head">
                        <div class="rv-author">
                            <img class="rv-ava" src="${esc(r.profile_picture) || '../swad/static/img/logo.svg'}" alt="">
                            <div>
                                <div class="rv-name">${esc(r.username || 'Аноним')}</div>
                                <div class="rv-stars">${'★'.repeat(Math.round(r.rating/2))}${'☆'.repeat(5-Math.round(r.rating/2))} ${r.rating}/10</div>
                            </div>
                        </div>
                        <div class="rv-date">${new Date(r.created_at).toLocaleDateString('ru-RU')}</div>
                    </div>
                    <div class="rv-text">${esc(r.text)}</div>
                    ${r.developer_reply ? `<div class="rv-dev-reply"><div class="rv-dev-badge">✔ Ответ разработчика</div>${esc(r.developer_reply)}</div>` : ''}
                `;
                        container.appendChild(d);
                    });
                    const mine = reviews.find(r => r.user_id == userId);
                    initStars(mine ? mine.rating : 10);
                    if (mine) {
                        const ta = document.getElementById('review-text');
                        if (ta) ta.value = mine.text;
                    }
                })
                .catch(() => {
                    // Контроллер не создан — тихо показываем заглушку
                    container.innerHTML = '<p style="color:var(--muted);font-size:.88rem">Отзывов пока нет.</p>';
                    initStars(10);
                });

            let selRating = 10;

            function initStars(n) {
                selRating = n;
                const wrap = document.getElementById('review-stars');
                if (!wrap) return;
                wrap.innerHTML = '';
                for (let i = 1; i <= 10; i++) {
                    const s = document.createElement('span');
                    s.textContent = '★';
                    s.addEventListener('mouseover', () => hl(i));
                    s.addEventListener('mouseout', () => hl(selRating));
                    s.addEventListener('click', () => {
                        selRating = i;
                        hl(i);
                    });
                    wrap.appendChild(s);
                }
                hl(selRating);
            }

            function hl(n) {
                document.querySelectorAll('#review-stars span')
                    .forEach((s, i) => s.classList.toggle('highlighted', i < n));
            }

            document.addEventListener('click', e => {
                if (e.target.id !== 'submit-review') return;
                const text = document.getElementById('review-text')?.value.trim();
                if (!text) {
                    alert('Введите текст отзыва');
                    return;
                }
                fetch('../swad/controllers/submit_asset_review.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `asset_id=${assetId}&rating=${selRating}&text=${encodeURIComponent(text)}`
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                    else alert('Ошибка: ' + (d.error || d.message || ''));
                }).catch(() => alert('Ошибка отправки'));
            });
        })();

        /* ── Audio player ── */
        (function() {
            const audio = document.getElementById('assetAudio');
            if (!audio) return;
            const dur = document.getElementById('audioDur');
            const icon = document.getElementById('playIcon');

            function fmt(s) {
                return Math.floor(s / 60) + ':' + (Math.floor(s % 60) + '').padStart(2, '0');
            }
            audio.addEventListener('loadedmetadata', () => {
                if (dur) dur.textContent = '0:00 / ' + fmt(audio.duration);
            });
            audio.addEventListener('timeupdate', () => {
                if (dur && audio.duration)
                    dur.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration);
            });
            audio.addEventListener('ended', () => {
                if (icon) icon.innerHTML = '<polygon points="5 3 19 12 5 21 5 3"/>';
            });

            window.toggleAudio = function() {
                if (!icon) return;
                if (audio.paused) {
                    audio.play();
                    icon.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
                } else {
                    audio.pause();
                    icon.innerHTML = '<polygon points="5 3 19 12 5 21 5 3"/>';
                }
            };
        })();

        /* ── 3D Viewer ── */
        <?php if (!empty($model3dUrl)): ?>
                (function() {
                    const MODEL_URL = '<?= htmlspecialchars($model3dUrl) ?>';
                    const canvas = document.getElementById('viewer3d');
                    const loading = document.getElementById('viewerLoading');
                    if (!canvas || typeof THREE === 'undefined') return;

                    const renderer = new THREE.WebGLRenderer({
                        canvas,
                        antialias: true
                    });
                    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                    renderer.setClearColor(0x0a0018, 1);

                    const scene = new THREE.Scene();
                    const camera = new THREE.PerspectiveCamera(45, 2, 0.01, 1000);

                    scene.add(new THREE.AmbientLight(0xffffff, .6));
                    const dir = new THREE.DirectionalLight(0xffffff, 1.2);
                    dir.position.set(5, 8, 5);
                    scene.add(dir);
                    scene.add(new THREE.GridHelper(10, 20, 0x330055, 0x220033));

                    let model = null,
                        wireframe = false,
                        autoRotate = false,
                        mixer = null;
                    const clock = new THREE.Clock();

                    /* Resize */
                    function resize() {
                        const w = canvas.parentElement.clientWidth;
                        const h = canvas.parentElement.clientHeight;
                        renderer.setSize(w, h, false);
                        camera.aspect = w / h;
                        camera.updateProjectionMatrix();
                    }
                    resize();
                    window.addEventListener('resize', resize);

                    /* Camera orbit */
                    let spherical = {
                        theta: .3,
                        phi: 1.2,
                        radius: 3.5
                    };
                    let panOffset = new THREE.Vector3();

                    function updateCamera() {
                        camera.position.set(
                            panOffset.x + spherical.radius * Math.sin(spherical.phi) * Math.sin(spherical.theta),
                            panOffset.y + spherical.radius * Math.cos(spherical.phi),
                            panOffset.z + spherical.radius * Math.sin(spherical.phi) * Math.cos(spherical.theta)
                        );
                        camera.lookAt(panOffset.x, panOffset.y, panOffset.z);
                    }
                    updateCamera();

                    let isDown = false,
                        isPan = false,
                        lx = 0,
                        ly = 0;
                    canvas.addEventListener('pointerdown', e => {
                        isDown = true;
                        isPan = e.button === 2 || e.ctrlKey;
                        lx = e.clientX;
                        ly = e.clientY;
                        canvas.setPointerCapture(e.pointerId);
                    });
                    canvas.addEventListener('pointermove', e => {
                        if (!isDown) return;
                        const dx = e.clientX - lx,
                            dy = e.clientY - ly;
                        lx = e.clientX;
                        ly = e.clientY;
                        if (isPan) {
                            panOffset.x -= dx * .005;
                            panOffset.y += dy * .005;
                        } else {
                            spherical.theta -= dx * .008;
                            spherical.phi = Math.max(.1, Math.min(Math.PI - .1, spherical.phi + dy * .008));
                        }
                        updateCamera();
                    });
                    canvas.addEventListener('pointerup', () => isDown = false);
                    canvas.addEventListener('wheel', e => {
                        spherical.radius = Math.max(.5, Math.min(20, spherical.radius + e.deltaY * .01));
                        updateCamera();
                        e.preventDefault();
                    }, {
                        passive: false
                    });
                    canvas.addEventListener('contextmenu', e => e.preventDefault());

                    window.resetCamera = function() {
                        spherical = {
                            theta: .3,
                            phi: 1.2,
                            radius: 3.5
                        };
                        panOffset.set(0, 0, 0);
                        updateCamera();
                    };
                    window.toggleWireframe = function() {
                        wireframe = !wireframe;
                        if (model) model.traverse(c => {
                            if (c.isMesh && c.material) c.material.wireframe = wireframe;
                        });
                    };
                    window.toggleAutoRotate = function() {
                        autoRotate = !autoRotate;
                    };

                    /* Render loop — паузируется когда вкладка скрыта или юзер не на превью */
                    let rafId = null;
                    let shouldRender = true;

                    function render() {
                        if (!shouldRender) return;
                        rafId = requestAnimationFrame(render);
                        if (mixer) mixer.update(clock.getDelta());
                        if (autoRotate) {
                            spherical.theta += .008;
                            updateCamera();
                        }
                        renderer.render(scene, camera);
                    }

                    function pauseRenderer() {
                        shouldRender = false;
                        cancelAnimationFrame(rafId);
                    }

                    function resumeRenderer() {
                        if (!shouldRender) {
                            shouldRender = true;
                            render();
                        }
                    }

                    document.addEventListener('visibilitychange', () =>
                        document.hidden ? pauseRenderer() : resumeRenderer());

                    // Пауза когда переключаются на другую вкладку страницы
                    document.querySelectorAll('.tab').forEach(tab => {
                        tab.addEventListener('click', function() {
                            this.dataset.tab === 'preview' ? resumeRenderer() : pauseRenderer();
                        });
                    });

                    /* Загрузка модели */
                    const loaderScript = document.createElement('script');
                    loaderScript.src = 'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js';
                    loaderScript.onload = () => {
                        new THREE.GLTFLoader().load(MODEL_URL, gltf => {
                            model = gltf.scene;
                            const box = new THREE.Box3().setFromObject(model);
                            const center = box.getCenter(new THREE.Vector3());
                            const size = box.getSize(new THREE.Vector3());
                            const scale = 2 / Math.max(size.x, size.y, size.z);
                            model.scale.setScalar(scale);
                            model.position.copy(center.multiplyScalar(-scale));
                            scene.add(model);
                            if (gltf.animations.length) {
                                mixer = new THREE.AnimationMixer(model);
                                mixer.clipAction(gltf.animations[0]).play();
                            }
                            if (loading) loading.classList.add('hidden');
                        }, undefined, err => {
                            console.warn('3D model load error:', err);
                            if (loading) loading.innerHTML = '<p style="color:#e88fc0">Не удалось загрузить модель</p>';
                        });
                    };
                    document.head.appendChild(loaderScript);

                    render();
                })();
        <?php endif; ?>
    </script>
</body>

</html>