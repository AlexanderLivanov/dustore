<?php
session_start();
require_once('../swad/config.php');

$db  = new Database();
$pdo = $db->connect();

/* ── GET params ──────────────────────────────────────────────────────── */
$selCategory = isset($_GET['category']) ? trim($_GET['category']) : null;
$selPrice    = isset($_GET['price'])    ? trim($_GET['price'])    : null;
$selEngine   = isset($_GET['engine'])   ? trim($_GET['engine'])   : null;
$selLicense  = isset($_GET['license'])  ? trim($_GET['license'])  : null;
$selSort     = isset($_GET['sort'])     ? trim($_GET['sort'])     : 'newest';
$searchQ     = isset($_GET['q'])        ? trim($_GET['q'])        : '';

/* ── Build SQL ───────────────────────────────────────────────────────── */
$where  = ["a.status = 'published'"];
$params = [];

if ($selCategory) {
    $where[] = "a.category = ?";
    $params[] = $selCategory;
}
if ($selPrice === 'free')       $where[] = "a.price = 0";
elseif ($selPrice === 'paid')   $where[] = "a.price > 0";
if ($selEngine) {
    $where[] = "JSON_CONTAINS(a.engine_compatibility, JSON_QUOTE(?))";
    $params[] = $selEngine;
}
if ($selLicense) {
    $where[] = "a.license = ?";
    $params[] = $selLicense;
}
if ($searchQ) {
    $where[] = "(a.name LIKE ? OR a.studio_name LIKE ? OR a.tags LIKE ?)";
    $like = "%{$searchQ}%";
    $params = array_merge($params, [$like, $like, $like]);
}

$sortMap = [
    'newest'     => 'a.created_at DESC',
    'popular'    => 'a.downloads_count DESC',
    'price_asc'  => 'a.price ASC',
    'price_desc' => 'a.price DESC',
    'rating'     => 'a.avg_rating DESC',
];
$orderBy  = $sortMap[$selSort] ?? 'a.created_at DESC';
$whereStr = 'WHERE ' . implode(' AND ', $where);

try {
    $stmt = $pdo->prepare("SELECT a.* FROM assets a {$whereStr} ORDER BY {$orderBy}");
    $stmt->execute($params);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assets = [];
    error_log('Asset store error: ' . $e->getMessage()); // в лог, не на экран
}

try {
    $stats = $pdo->query("SELECT COUNT(*) AS total, SUM(price=0) AS fc, SUM(price>0) AS pc,
                          COUNT(DISTINCT studio_name) AS authors
                          FROM assets WHERE status='published'")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'fc' => 0, 'pc' => 0, 'authors' => 0];
}

/* ── Wishlist count для текущего юзера ─────────────────────────────── */
$wishlistCount = 0;
$libraryCount  = 0;
if (!empty($_SESSION['USERDATA']['id'])) {
    try {
        $uid = (int)$_SESSION['USERDATA']['id'];
        $wishlistCount = $pdo->prepare("SELECT COUNT(*) FROM asset_wishlist WHERE player_id=?");
        $wishlistCount->execute([$uid]);
        $wishlistCount = (int)$wishlistCount->fetchColumn();

        $libStmt = $pdo->prepare("SELECT COUNT(*) FROM asset_library WHERE player_id=?");
        $libStmt->execute([$uid]);
        $libraryCount = (int)$libStmt->fetchColumn();
    } catch (Exception $e) {
    }
}

/* ── Category map ────────────────────────────────────────────────────── */
$CATS = [
    '3d_model'  => ['label' => '3D Модели',    'emoji' => '🧊', 'color' => '99,102,241'],
    'texture'   => ['label' => 'Текстуры',     'emoji' => '🖼️',  'color' => '234,88,12'],
    'music'     => ['label' => 'Музыка',       'emoji' => '🎵',  'color' => '16,185,129'],
    'sfx'       => ['label' => 'Звуки / SFX',  'emoji' => '🔊',  'color' => '20,184,166'],
    'sprite'    => ['label' => 'Спрайты / 2D', 'emoji' => '🎨',  'color' => '245,158,11'],
    'shader'    => ['label' => 'Шейдеры',      'emoji' => '✨',  'color' => '139,92,246'],
    'font'      => ['label' => 'Шрифты',       'emoji' => '🔤',  'color' => '236,72,153'],
    'script'    => ['label' => 'Скрипты',      'emoji' => '📜',  'color' => '59,130,246'],
    'ui_kit'    => ['label' => 'UI Киты',      'emoji' => '🎛️',  'color' => '16,185,129'],
    'animation' => ['label' => 'Анимации',     'emoji' => '🎬',  'color' => '239,68,68'],
    'vfx'       => ['label' => 'VFX / FX',     'emoji' => '💥',  'color' => '195,33,120'],
    'video'     => ['label' => 'Видео',        'emoji' => '📹',  'color' => '107,114,128'],
];
$ENGINES  = ['Unity', 'Godot', 'Unreal Engine', 'GameMaker', 'Defold', 'Любой'];
$LICENSES = [
    'cc0'        => 'CC0 (Public Domain)',
    'cc_by'      => 'CC BY',
    'commercial' => 'Коммерческая',
    'personal'   => 'Личное использование',
];

function buildUrl($mergeParams)
{
    $p = array_merge($_GET, $mergeParams);
    foreach ($p as $k => $v) if ($v === null) unset($p[$k]);
    unset($p['page']);
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore Assets — База ассетов</title>
    <link rel="stylesheet" href="../swad/css/pages.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #c32178;
    --secondary: #74155d;
    --dark: #170423;
    --light: #f8f9fa;
    --gradient: linear-gradient(180deg, #0f0a21);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(180deg, #0f0a20, #240038, #780066);
    color: var(--light);
    font-family: 'Inter', system-ui, sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
}

body.moonlight-theme {
    background-image: url("/swad/static/img/Moonlight_pict.jpeg");
    background-size: cover;
    background-repeat: no-repeat;
    background-attachment: fixed;
    background-position: center 35%;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 24px;
}

/* ===== HERO ===== */
.ah {
    padding: 52px 0 44px;
    position: relative;
    overflow: hidden;
}

.ah .container {
    background: linear-gradient(180deg, rgba(0,0,0,0.2), rgba(0,0,0,0.3), rgba(0,0,0,0.4));
    border-radius: 15px;
    backdrop-filter: blur(20px);
    box-shadow: 0px 0px 1px 1px rgba(255,255,255,0.15);
    padding: 30px 30px 20px;
}

.ah-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(195,33,120,0.13);
    border: 1px solid rgba(195,33,120,0.28);
    border-radius: 100px;
    padding: 5px 16px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #e88fc0;
    margin-bottom: 18px;
}

.ah h1 {
    font-family: 'Syne', sans-serif;
    font-size: clamp(2rem, 4.5vw, 3.4rem);
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1.08;
    margin-bottom: 14px;
}

.ah h1 em { color: var(--primary); font-style: normal; }

.ah p {
    color: rgba(255,255,255,0.6);
    max-width: 500px;
    font-size: .98rem;
    line-height: 1.65;
    margin-bottom: 32px;
}

.ah-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}

.ah-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 10px;
    font-size: .82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s ease;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.8);
    cursor: pointer;
}
.ah-btn:hover {
    border-color: var(--primary);
    background: rgba(195,33,120,0.12);
    color: #e88fc0;
}
.ah-btn.primary {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.ah-btn.primary:hover {
    background: #d42485;
    border-color: #d42485;
}
.ah-btn .badge {
    background: rgba(255,255,255,0.2);
    border-radius: 100px;
    padding: 1px 7px;
    font-size: .68rem;
    min-width: 20px;
    text-align: center;
}
.ah-btn.primary .badge { background: rgba(0,0,0,0.2); }

.ah-cats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 36px;
}
.ah-cat {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 100px;
    padding: 6px 14px;
    font-size: .78rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: all .2s ease;
    cursor: pointer;
}
.ah-cat:hover, .ah-cat.active {
    background: rgba(195,33,120,0.15);
    border-color: rgba(195,33,120,0.35);
    color: #e88fc0;
}

.ah-stats {
    display: flex;
    gap: 0;
    flex-wrap: wrap;
}
.ah-stat {
    padding: 0 28px 0 0;
    border-right: 1px solid rgba(255,255,255,0.08);
    margin-right: 28px;
}
.ah-stat:last-child { border: none; margin: 0; padding: 0; }
.ah-stat-n {
    font-family: 'Syne', sans-serif;
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}
.ah-stat-l {
    font-size: .75rem;
    color: rgba(255,255,255,0.4);
    margin-top: 3px;
}

/* ===== STICKY SEARCH ===== */
.search-zone {
    background: rgba(13,1,24,0.9);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding: 18px 0;
    position: sticky;
    top: 0;
    z-index: 200;
    backdrop-filter: blur(12px);
}
.search-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}
.s-bar {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 0 16px;
    transition: border-color .2s, box-shadow .2s;
}
.s-bar:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(195,33,120,0.2);
}
.s-bar svg { color: rgba(255,255,255,0.4); flex-shrink: 0; }
.s-bar input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    padding: 13px 0;
    font-size: .95rem;
    color: #fff;
    font-family: inherit;
}
.s-bar input::placeholder { color: rgba(255,255,255,0.4); }
.s-kbd {
    display: flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0;
    font-size: .68rem;
    color: rgba(255,255,255,0.3);
    font-family: monospace;
}
.s-kbd kbd {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    padding: 2px 5px;
}

.sort-sel {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    color: #fff;
    font-size: .82rem;
    padding: 10px 14px;
    cursor: pointer;
    outline: none;
    transition: border-color .2s;
    font-family: inherit;
    flex-shrink: 0;
}
.sort-sel:focus { border-color: var(--primary); }

/* ===== LAYOUT ===== */
.al {
    max-width: 1400px;
    margin: 0 auto;
    padding: 28px 24px 80px;
    display: grid;
    grid-template-columns: 268px 1fr;
    gap: 28px;
    align-items: start;
}
.sidebar {
    position: sticky;
    top: 74px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* ===== SIDEBAR ===== */
.fg {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    overflow: hidden;
}
.fg-h {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 16px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: color .15s;
}
.fg-h:hover { color: #fff; }
.fg-h.open { border-bottom: 1px solid rgba(255,255,255,0.08); }
.fg-h .ch {
    transition: transform .2s;
    opacity: .5;
}
.fg-h.open .ch { transform: rotate(180deg); }

.fg-b {
    padding: 8px 8px 10px;
    display: none;
}
.fg-b.open { display: block; }

.fb {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    border: none;
    background: none;
    color: rgba(255,255,255,0.7);
    font-size: .84rem;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, color .15s;
    text-align: left;
    font-family: inherit;
}
.fb:hover { background: rgba(255,255,255,0.08); }
.fb.active {
    background: rgba(195,33,120,0.18);
    color: #e88fc0;
}
.fb .em {
    font-size: .9rem;
    line-height: 1;
    flex-shrink: 0;
    width: 18px;
    text-align: center;
}
.fb .ct {
    margin-left: auto;
    font-size: .68rem;
    color: rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.07);
    border-radius: 100px;
    padding: 2px 7px;
    min-width: 24px;
    text-align: center;
}
.fb.active .ct {
    background: rgba(195,33,120,0.25);
    color: #e88fc0;
}
.fdiv {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 5px 8px;
}
.reset-btn {
    display: block;
    width: 100%;
    padding: 10px;
    background: none;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    color: rgba(255,255,255,0.4);
    font-size: .8rem;
    cursor: pointer;
    transition: all .18s;
    text-align: center;
    font-family: inherit;
    margin-top: 4px;
    text-decoration: none;
}
.reset-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* ===== MAIN CONTENT ===== */
.amain { min-width: 0; }
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    gap: 12px;
    flex-wrap: wrap;
}
.t-count {
    font-size: .85rem;
    color: rgba(255,255,255,0.4);
}
.t-count strong { color: #fff; }

.chips {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(195,33,120,0.13);
    border: 1px solid rgba(195,33,120,0.28);
    border-radius: 100px;
    padding: 4px 12px;
    font-size: .78rem;
    color: #e88fc0;
    text-decoration: none;
}
.chip-x { opacity: .55; font-size: .65rem; }
.chip:hover .chip-x { opacity: 1; }

/* ===== ASSET GRID ===== */
.ag {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 16px;
}

/* ===== ASSET CARD ===== */
.ac {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition: transform 0.01s ease-out, box-shadow 0.3s ease;
    transform-style: preserve-3d;
    will-change: transform;
    box-shadow: 0px 1px 20px 0px rgba(255,255,255,0.04);
    position: relative;
}
.ac:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}
.ac::after {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: linear-gradient(
        115deg,
        transparent 0%,
        rgba(255,255,255,0.1) 20%,
        rgba(255,255,255,0.5) 40%,
        rgba(255,255,255,0.1) 60%,
        transparent 80%
    );
    background-size: 200% 100%;
    background-position: calc(50% + var(--dx, 0%)) 0;
    opacity: 0;
    transition: opacity 0.2s;
    pointer-events: none;
    z-index: 2;
    mix-blend-mode: overlay;
}
.ac:hover::after { opacity: 1; }
.ac::before {
    content: "";
    position: absolute;
    inset: 0;
    padding: 1px;
    border-radius: inherit;
    background: linear-gradient(to top, rgba(255,255,255,0.1) 20%, rgba(255,255,0,0) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    z-index: 10;
}

.ac-thumb {
    position: relative;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: rgba(0,0,0,0.2);
    flex-shrink: 0;
}
.ac-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    padding: 5px 5px 0px 5px;
    border-radius: 20px 20px 0px 0px;
    background: rgba(255,255,255,0.02);
    display: block;
}
.ac-thumb-ov {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.8) 100%);
    opacity: 0;
    transition: opacity .3s;
}
.ac:hover .ac-thumb-ov { opacity: 1; }
.ac-hover-cta {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .3s;
}
.ac:hover .ac-hover-cta { opacity: 1; }
.ac-hover-cta span {
    background: rgba(195,33,120,0.88);
    border-radius: 8px;
    padding: 7px 18px;
    font-size: .78rem;
    font-weight: 700;
    color: #fff;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.15);
}

.ac-cat {
    position: absolute;
    top: 9px;
    left: 9px;
    display: flex;
    align-items: center;
    gap: 4px;
    border-radius: 6px;
    padding: 3px 9px;
    font-size: .68rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
}
.ac-price {
    position: absolute;
    top: 9px;
    right: 9px;
    border-radius: 6px;
    padding: 3px 9px;
    font-size: .7rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
}
.ac-price.free {
    background: rgba(46,204,113,0.18);
    color: #2ecc71;
    border: 1px solid rgba(46,204,113,0.3);
}
.ac-price.paid {
    background: rgba(0,0,0,0.72);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.14);
}
.ac-new {
    position: absolute;
    bottom: 9px;
    left: 9px;
    background: rgba(195,33,120,0.85);
    color: #fff;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .04em;
    backdrop-filter: blur(6px);
}

/* Category colors */
.cb-3d_model { background: rgba(99,102,241,0.7); color: #c7d2fe; }
.cb-texture   { background: rgba(234,88,12,0.7); color: #fed7aa; }
.cb-music     { background: rgba(16,185,129,0.7); color: #a7f3d0; }
.cb-sfx       { background: rgba(20,184,166,0.7); color: #99f6e4; }
.cb-sprite    { background: rgba(245,158,11,0.7); color: #fde68a; }
.cb-shader    { background: rgba(139,92,246,0.7); color: #ddd6fe; }
.cb-font      { background: rgba(236,72,153,0.7); color: #fbcfe8; }
.cb-script    { background: rgba(59,130,246,0.7); color: #bfdbfe; }
.cb-ui_kit    { background: rgba(16,185,129,0.7); color: #a7f3d0; }
.cb-animation { background: rgba(239,68,68,0.7); color: #fecaca; }
.cb-vfx       { background: rgba(195,33,120,0.7); color: #f5b8da; }
.cb-video     { background: rgba(107,114,128,0.7); color: #e5e7eb; }

.ac-body {
    padding: 13px 13px 10px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ac-name {
    font-size: .87rem;
    font-weight: 700;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ac-author {
    font-size: .74rem;
    color: rgba(255,255,255,0.4);
}
.ac-pills {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: auto;
    padding-top: 6px;
}
.ac-pill {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    padding: 2px 7px;
    font-size: .65rem;
    font-weight: 600;
    color: rgba(255,255,255,0.4);
    font-family: 'Courier New', monospace;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.ac-rating {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: .72rem;
    color: #f59e0b;
    margin-left: auto;
    flex-shrink: 0;
}
.ac-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 13px 13px;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.ac-p {
    font-size: .92rem;
    font-weight: 800;
}
.ac-p.free { color: #2ecc71; }
.ac-dl-btn {
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 7px;
    padding: 6px 15px;
    font-size: .76rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .18s, transform .15s;
    font-family: inherit;
    text-decoration: none;
    display: inline-block;
}
.ac-dl-btn:hover {
    background: #d42485;
    transform: translateY(-1px);
}

/* ===== EMPTY ===== */
.empty {
    grid-column: 1/-1;
    text-align: center;
    padding: 80px 20px;
}
.empty-ico {
    font-size: 3.5rem;
    opacity: .35;
    margin-bottom: 16px;
}
.empty h3 { margin-bottom: 8px; }
.empty p {
    color: rgba(255,255,255,0.4);
    font-size: .9rem;
}

/* ===== MOBILE ===== */
.mob-bar {
    display: none;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.mob-filter-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 9px 14px;
    color: #fff;
    font-size: .84rem;
    cursor: pointer;
    transition: border-color .18s;
    font-family: inherit;
}
.mob-filter-btn:hover { border-color: var(--primary); }

.drawer-wrap {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9000;
}
.drawer-wrap.open { display: block; }
.drawer-bd {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px);
}
.drawer {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: min(310px, 88vw);
    background: #1c0923;
    border-right: 1px solid rgba(255,255,255,0.08);
    overflow-y: auto;
    padding: 20px 14px 40px;
    transform: translateX(-100%);
    transition: transform .3s ease;
}
.drawer-wrap.open .drawer { transform: translateX(0); }
.drawer-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}
.drawer-top h3 {
    font-size: .95rem;
    font-weight: 700;
}
.drawer-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.4);
    font-size: 1.3rem;
    cursor: pointer;
    line-height: 1;
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.ac.animate-in {
    animation: fadeInUp 0.4s ease forwards;
    opacity: 0;
}

/* ===== ADAPTIVE ===== */
@media (max-width: 1060px) {
    .al { grid-template-columns: 1fr; }
    .sidebar { display: none; }
    .mob-bar { display: flex; }
}
@media (max-width: 768px) {
    .container { padding: 0 10px; }
    .ah .container { padding: 20px 16px; }
    .ah { padding: 30px 0; }
    .search-zone { padding: 10px 0; }
    .search-inner { flex-wrap: wrap; }
    .sort-sel { flex: 1; }
    .al { padding: 14px 0 60px; }
    .ag { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .ac-thumb { height: 110px; }
    .ac-name { font-size: 0.9rem; }
    .ac-foot { padding: 8px 10px 10px; }
    .ac-dl-btn { font-size: .7rem; padding: 4px 10px; }
}
@media (max-width: 480px) {
    .ag { grid-template-columns: 1fr; }
}
@media (min-width: 769px) {
    .mob-bar { display: none; }
}
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>

    <main>

        <!-- ══ HERO ══════════════════════════════════════════════════════════════ -->
        <section class="ah">
            <div class="container">
                <div class="ah-eyebrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    Dustore Assets — ассеты от разрабов разрабам
                </div>
                <h1>База ассетов<br>для <em>разработчиков</em></h1>
                <p>Текстуры, 3D-модели, музыка, шейдеры, VFX — всё что нужно для создания игры мечты. Публикуй свои ассеты и зарабатывай.</p>

                <!-- Кнопки действий -->
                <div class="ah-actions">
                    <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                        <a href="/assetstore/my_assets.php?tab=library" class="ah-btn">
                            📦 Библиотека
                            <?php if ($libraryCount > 0): ?>
                                <span class="badge"><?= $libraryCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/assetstore/my_assets.php?tab=wishlist" class="ah-btn">
                            ♡ Вишлист
                            <?php if ($wishlistCount > 0): ?>
                                <span class="badge"><?= $wishlistCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/assetstore/upload_asset.php" class="ah-btn primary">
                            + Загрузить ассет
                        </a>
                    <?php else: ?>
                        <a href="/login" class="ah-btn">🔑 Войти чтобы сохранять ассеты</a>
                        <a href="/register" class="ah-btn primary">+ Стать автором</a>
                    <?php endif; ?>
                </div>

                <!-- Category pills -->
                <div class="ah-cats">
                    <?php foreach ($CATS as $k => $c): ?>
                        <a href="<?= buildUrl(['category' => $k, 'page' => null]) ?>" class="ah-cat <?= $selCategory === $k ? 'active' : '' ?>">
                            <?= $c['emoji'] ?> <?= $c['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Stats -->
                <div class="ah-stats">
                    <div class="ah-stat">
                        <div class="ah-stat-n"><?= number_format($stats['total'] ?? 0) ?></div>
                        <div class="ah-stat-l">ассетов в базе</div>
                    </div>
                    <div class="ah-stat">
                        <div class="ah-stat-n"><?= number_format($stats['fc'] ?? 0) ?></div>
                        <div class="ah-stat-l">бесплатных</div>
                    </div>
                    <div class="ah-stat">
                        <div class="ah-stat-n"><?= number_format($stats['authors'] ?? 0) ?></div>
                        <div class="ah-stat-l">авторов</div>
                    </div>
                    <div class="ah-stat">
                        <div class="ah-stat-n"><?= count($CATS) ?></div>
                        <div class="ah-stat-l">категорий</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ STICKY SEARCH ══════════════════════════════════════════════════════ -->
        <div class="search-zone">
            <div class="container">
                <div class="search-inner">
                    <form method="GET" action="" style="flex:1;display:flex;gap:12px;align-items:center">
                        <?php foreach ($_GET as $k => $v): if (in_array($k, ['q', 'sort'])) continue; ?>
                            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                        <?php endforeach; ?>
                        <div class="s-bar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                            <input type="text" name="q" id="searchInput"
                                placeholder="Поиск по названию, тегам, автору..."
                                value="<?= htmlspecialchars($searchQ) ?>" autocomplete="off">
                            <div class="s-kbd"><kbd>⌘</kbd><kbd>K</kbd></div>
                        </div>
                        <select name="sort" class="sort-sel" onchange="this.form.submit()">
                            <option value="newest" <?= $selSort === 'newest'    ? 'selected' : '' ?>>Новые</option>
                            <option value="popular" <?= $selSort === 'popular'   ? 'selected' : '' ?>>Популярные</option>
                            <option value="rating" <?= $selSort === 'rating'    ? 'selected' : '' ?>>По рейтингу</option>
                            <option value="price_asc" <?= $selSort === 'price_asc' ? 'selected' : '' ?>>Цена ↑</option>
                            <option value="price_desc" <?= $selSort === 'price_desc' ? 'selected' : '' ?>>Цена ↓</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- ══ LAYOUT ══════════════════════════════════════════════════════════════ -->
        <div class="al">

            <!-- Sidebar HTML (shared between desktop и mobile drawer) -->
            <?php ob_start(); ?>
            <div class="fg">
                <div class="fg-h open" data-t="fb-cat">Категория
                    <svg class="ch" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="fg-b open" id="fb-cat">
                    <a href="<?= buildUrl(['category' => null]) ?>" class="fb <?= !$selCategory ? 'active' : '' ?>"><span class="em">🗂️</span> Все категории</a>
                    <div class="fdiv"></div>
                    <?php foreach ($CATS as $k => $c): ?>
                        <a href="<?= buildUrl(['category' => $k]) ?>" class="fb <?= $selCategory === $k ? 'active' : '' ?>">
                            <span class="em"><?= $c['emoji'] ?></span> <?= $c['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fg">
                <div class="fg-h open" data-t="fb-price">Цена
                    <svg class="ch" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="fg-b open" id="fb-price">
                    <a href="<?= buildUrl(['price' => null]) ?>" class="fb <?= !$selPrice ? 'active' : '' ?>"><span class="em">💰</span> Все</a>
                    <a href="<?= buildUrl(['price' => 'free']) ?>" class="fb <?= $selPrice === 'free' ? 'active' : '' ?>">
                        <span class="em">🎁</span> Бесплатные <span class="ct"><?= number_format($stats['fc'] ?? 0) ?></span>
                    </a>
                    <a href="<?= buildUrl(['price' => 'paid']) ?>" class="fb <?= $selPrice === 'paid' ? 'active' : '' ?>">
                        <span class="em">💳</span> Платные <span class="ct"><?= number_format($stats['pc'] ?? 0) ?></span>
                    </a>
                </div>
            </div>

            <div class="fg">
                <div class="fg-h open" data-t="fb-eng">Движок
                    <svg class="ch" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="fg-b open" id="fb-eng">
                    <a href="<?= buildUrl(['engine' => null]) ?>" class="fb <?= !$selEngine ? 'active' : '' ?>"><span class="em">🎮</span> Любой</a>
                    <?php foreach ($ENGINES as $eng): ?>
                        <a href="<?= buildUrl(['engine' => $eng]) ?>" class="fb <?= $selEngine === $eng ? 'active' : '' ?>">
                            <span class="em">▸</span> <?= htmlspecialchars($eng) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="fg">
                <div class="fg-h" data-t="fb-lic">Лицензия
                    <svg class="ch" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="fg-b" id="fb-lic">
                    <a href="<?= buildUrl(['license' => null]) ?>" class="fb <?= !$selLicense ? 'active' : '' ?>"><span class="em">📋</span> Любая</a>
                    <?php foreach ($LICENSES as $k => $l): ?>
                        <a href="<?= buildUrl(['license' => $k]) ?>" class="fb <?= $selLicense === $k ? 'active' : '' ?>">
                            <span class="em">▸</span> <?= htmlspecialchars($l) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($selCategory || $selPrice || $selEngine || $selLicense || $searchQ): ?>
                <a href="?" class="reset-btn">✕ Сбросить фильтры</a>
            <?php endif; ?>

            <!-- Быстрые ссылки в сайдбаре -->
            <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                <div style="display:flex;flex-direction:column;gap:4px;margin-top:4px">
                    <a href="/assetstore/my_assets.php?tab=library" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;background:var(--surf);border:1px solid var(--bdr);color:var(--muted);text-decoration:none;font-size:.8rem;transition:all .18s"
                        onmouseover="this.style.borderColor='var(--pr)';this.style.color='#e88fc0'"
                        onmouseout="this.style.borderColor='var(--bdr)';this.style.color='var(--muted)'">
                        📦 Моя библиотека <?php if ($libraryCount > 0): ?><span style="margin-left:auto;background:var(--surf2);border-radius:100px;padding:1px 7px;font-size:.68rem"><?= $libraryCount ?></span><?php endif; ?>
                    </a>
                    <a href="/assetstore/my_assets.php?tab=wishlist" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;background:var(--surf);border:1px solid var(--bdr);color:var(--muted);text-decoration:none;font-size:.8rem;transition:all .18s"
                        onmouseover="this.style.borderColor='var(--pr)';this.style.color='#e88fc0'"
                        onmouseout="this.style.borderColor='var(--bdr)';this.style.color='var(--muted)'">
                        ♡ Список желаний <?php if ($wishlistCount > 0): ?><span style="margin-left:auto;background:var(--surf2);border-radius:100px;padding:1px 7px;font-size:.68rem"><?= $wishlistCount ?></span><?php endif; ?>
                    </a>
                    <a href="/assetstore/upload_asset.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;background:rgba(195,33,120,.12);border:1px solid rgba(195,33,120,.25);color:#e88fc0;text-decoration:none;font-size:.8rem;font-weight:700;transition:all .18s"
                        onmouseover="this.style.background='rgba(195,33,120,.2)'"
                        onmouseout="this.style.background='rgba(195,33,120,.12)'">
                        + Загрузить ассет
                    </a>
                </div>
            <?php endif; ?>

            <?php $sidebarHtml = ob_get_clean(); ?>

            <aside class="sidebar"><?= $sidebarHtml ?></aside>

            <!-- MAIN -->
            <section class="amain">

                <div class="mob-bar">
                    <button class="mob-filter-btn" id="mobFilterBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" y1="6" x2="20" y2="6" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                            <line x1="11" y1="18" x2="13" y2="18" />
                        </svg>
                        Фильтры
                        <?php if ($selCategory || $selPrice || $selEngine || $selLicense): ?>
                            <span style="background:var(--pr);border-radius:100px;padding:1px 7px;font-size:.66rem;color:#fff">!</span>
                        <?php endif; ?>
                    </button>
                    <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                        <a href="/assetstore/my_assets.php?tab=library" style="display:flex;align-items:center;gap:6px;padding:9px 13px;border-radius:var(--r);background:var(--surf);border:1px solid var(--bdr);color:var(--muted);text-decoration:none;font-size:.8rem">
                            📦 <?= $libraryCount ?>
                        </a>
                        <a href="/assetstore/my_assets.php?tab=wishlist" style="display:flex;align-items:center;gap:6px;padding:9px 13px;border-radius:var(--r);background:var(--surf);border:1px solid var(--bdr);color:var(--muted);text-decoration:none;font-size:.8rem">
                            ♡ <?= $wishlistCount ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Active filter chips -->
                <?php if ($selCategory || $selPrice || $selEngine || $selLicense || $searchQ): ?>
                    <div class="chips">
                        <?php if ($selCategory && isset($CATS[$selCategory])): ?>
                            <a class="chip" href="<?= buildUrl(['category' => null]) ?>"><?= $CATS[$selCategory]['emoji'] ?> <?= $CATS[$selCategory]['label'] ?> <span class="chip-x">✕</span></a>
                        <?php endif; ?>
                        <?php if ($selPrice): ?>
                            <a class="chip" href="<?= buildUrl(['price' => null]) ?>"><?= $selPrice === 'free' ? '🎁 Бесплатные' : '💳 Платные' ?> <span class="chip-x">✕</span></a>
                        <?php endif; ?>
                        <?php if ($selEngine): ?>
                            <a class="chip" href="<?= buildUrl(['engine' => null]) ?>">🎮 <?= htmlspecialchars($selEngine) ?> <span class="chip-x">✕</span></a>
                        <?php endif; ?>
                        <?php if ($selLicense && isset($LICENSES[$selLicense])): ?>
                            <a class="chip" href="<?= buildUrl(['license' => null]) ?>">📜 <?= htmlspecialchars($LICENSES[$selLicense]) ?> <span class="chip-x">✕</span></a>
                        <?php endif; ?>
                        <?php if ($searchQ): ?>
                            <a class="chip" href="<?= buildUrl(['q' => null]) ?>">🔍 «<?= htmlspecialchars($searchQ) ?>» <span class="chip-x">✕</span></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="toolbar">
                    <div class="t-count">Найдено: <strong><?= count($assets) ?></strong> ассетов</div>
                </div>

                <div class="ag" id="assetsGrid">
                    <?php if (empty($assets)): ?>
                        <div class="empty">
                            <div class="empty-ico">🗂️</div>
                            <h3>Ничего не найдено</h3>
                            <p>Попробуйте изменить фильтры или поисковый запрос</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assets as $a):
                            $cat    = $CATS[$a['category']] ?? ['label' => ucfirst($a['category']), 'emoji' => '📦', 'color' => '107,114,128'];
                            $isFree = ($a['price'] == 0);
                            $priceL = $isFree ? 'Бесплатно' : number_format($a['price'], 0, ',', ' ') . ' ₽';
                            $fmts   = json_decode($a['formats'] ?? '[]', true) ?: [];
                            $rating = round($a['avg_rating'] ?? 0, 1);
                            $isNew  = (time() - strtotime($a['created_at'] ?? 'now')) < 30 * 86400;
                            $cover  = !empty($a['path_to_cover']) ? htmlspecialchars($a['path_to_cover'])
                                : 'https://placehold.co/480x270/160028/c32178?text=' . urlencode($cat['label']);
                        ?>
                            <a class="ac" href="/assetstore/asset.php?id=<?= $a['id'] ?>"
                                data-name="<?= htmlspecialchars(strtolower($a['name'])) ?>"
                                data-author="<?= htmlspecialchars(strtolower($a['studio_name'])) ?>">
                                <div class="ac-thumb">
                                    <img src="<?= $cover ?>" alt="<?= htmlspecialchars($a['name']) ?>" loading="lazy">
                                    <div class="ac-thumb-ov"></div>
                                    <div class="ac-hover-cta"><span>👁 Открыть</span></div>
                                    <div class="ac-cat cb-<?= $a['category'] ?>"><?= $cat['emoji'] ?> <?= $cat['label'] ?></div>
                                    <div class="ac-price <?= $isFree ? 'free' : 'paid' ?>"><?= $priceL ?></div>
                                    <?php if ($isNew): ?><div class="ac-new">НОВИНКА</div><?php endif; ?>
                                </div>
                                <div class="ac-body">
                                    <div class="ac-name"><?= htmlspecialchars($a['name']) ?></div>
                                    <div class="ac-author">by <?= htmlspecialchars($a['studio_name']) ?></div>
                                    <div class="ac-pills">
                                        <?php foreach (array_slice($fmts, 0, 3) as $f): ?>
                                            <span class="ac-pill"><?= htmlspecialchars($f) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($rating > 0): ?><span class="ac-rating">★ <?= $rating ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="ac-foot">
                                    <div class="ac-p <?= $isFree ? 'free' : '' ?>"><?= $priceL ?></div>
                                    <span class="ac-dl-btn"><?= $isFree ? '↓ Получить' : '🛒 Купить' ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Mobile drawer -->
        <div class="drawer-wrap" id="drawerWrap">
            <div class="drawer-bd" id="drawerBd"></div>
            <div class="drawer">
                <div class="drawer-top">
                    <h3>🗂️ Фильтры</h3>
                    <button class="drawer-close" id="drawerClose">✕</button>
                </div>
                <?= $sidebarHtml ?>
            </div>
        </div>

    </main>

    <?php require_once('../swad/static/elements/footer.php'); ?>
    <script>
        /* ── Sidebar accordion ── */
        document.querySelectorAll('.fg-h').forEach(h => {
            h.addEventListener('click', function() {
                const b = document.getElementById(this.dataset.t);
                if (!b) return;
                const open = b.classList.toggle('open');
                this.classList.toggle('open', open);
            });
        });

        /* ── Mobile drawer ── */
        (function() {
            const btn = document.getElementById('mobFilterBtn');
            const wrap = document.getElementById('drawerWrap');
            const bd = document.getElementById('drawerBd');
            const close = document.getElementById('drawerClose');
            if (!btn || !wrap) return;
            btn.addEventListener('click', () => wrap.classList.add('open'));
            bd?.addEventListener('click', () => wrap.classList.remove('open'));
            close?.addEventListener('click', () => wrap.classList.remove('open'));
            wrap.querySelectorAll('.fg-h').forEach(h => {
                h.addEventListener('click', function() {
                    const b = document.getElementById(this.dataset.t);
                    if (!b) return;
                    b.classList.toggle('open');
                    this.classList.toggle('open', b.classList.contains('open'));
                });
            });
        })();

        /* ── Live search ── */
        (function() {
            const inp = document.getElementById('searchInput');
            if (!inp) return;
            inp.addEventListener('input', function() {
                const q = this.value.toLowerCase().trim();
                document.querySelectorAll('.ac').forEach(c => {
                    const n = c.dataset.name || '';
                    const a = c.dataset.author || '';
                    c.style.display = (!q || n.includes(q) || a.includes(q)) ? '' : 'none';
                });
            });
        })();

        /* ── 3D tilt on cards ── */
        (function() {
            const grid = document.getElementById('assetsGrid');
            if (!grid) return;
            let active = null;
            grid.addEventListener('mousemove', e => {
                const c = e.target.closest('.ac');
                if (!c) return;
                if (active !== c) {
                    if (active) active.style.transform = '';
                    active = c;
                }
                const r = c.getBoundingClientRect();
                const nx = ((e.clientX - r.left) / r.width) * 2 - 1;
                const ny = ((e.clientY - r.top) / r.height) * 2 - 1;
                c.style.transform = `perspective(700px) rotateX(${-9*ny}deg) rotateY(${9*nx}deg) translateY(-5px) scale(1.018)`;
            });
            grid.addEventListener('mouseleave', () => {
                if (active) {
                    active.style.transform = '';
                    active = null;
                }
            });
            grid.addEventListener('mouseout', e => {
                const c = e.target.closest('.ac');
                if (c && !c.contains(e.relatedTarget)) {
                    c.style.transform = '';
                    if (active === c) active = null;
                }
            });
        })();

        /* ── Cmd+K ── */
        document.addEventListener('keydown', e => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }
        });
    </script>
</body>

</html>