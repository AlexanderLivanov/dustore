<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$isExpert = (bool) $stmt->fetch();
if (!$isExpert) die('Доступ запрещён');

$gameId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$gameId) die('Игра не найдена');

$stmt = $pdo->prepare("
    SELECT g.*, s.name AS studio_name, s.tiker, s.avatar_link,
           s.website AS studio_website, s.country, s.city, s.team_size, s.specialization
    FROM games g
    LEFT JOIN studios s ON g.developer = s.id
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die('Игра не найдена');

$features     = json_decode($game['features']     ?? '[]', true) ?: [];
$screenshots  = json_decode($game['screenshots']  ?? '[]', true) ?: [];
$requirements = json_decode($game['requirements'] ?? '[]', true) ?: [];

$stmt = $pdo->prepare("
    SELECT mr.id, mr.score, mr.comment AS review,
           mr.gameplay_score, mr.visual_score, mr.stability,
           mr.originality, mr.sound_score, mr.content_depth,
           u.username, e.rating AS expert_weight
    FROM moderation_reviews mr
    JOIN experts e ON e.id = mr.expert_id
    JOIN users u ON u.id = e.user_id
    WHERE mr.game_id = ?
");
$stmt->execute([$gameId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$expert = $stmt->fetch();
if (!$expert) die('no access');
$expertId = $expert['id'];

$stmt = $pdo->prepare("
    SELECT score, comment, gameplay_score, visual_score, stability, originality, sound_score, content_depth
    FROM moderation_reviews WHERE game_id=? AND expert_id=?
");
$stmt->execute([$gameId, $expertId]);
$myReview    = $stmt->fetch(PDO::FETCH_ASSOC);
$hasMyReview = (bool)$myReview;

$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT expert_id) FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$reviewCount = (int)$stmt->fetchColumn();

$needVotes = max(1, (int)ceil($totalExperts * 0.51));
$progress  = min(100, round($reviewCount / $needVotes * 100));

// Агрегированные оценки по критериям
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(score),1) AS avg_score,
           ROUND(AVG(gameplay_score),1) AS avg_gameplay,
           ROUND(AVG(visual_score),1)   AS avg_visual,
           ROUND(AVG(stability),1)      AS avg_stability,
           ROUND(AVG(originality),1)    AS avg_originality,
           ROUND(AVG(sound_score),1)    AS avg_sound,
           ROUND(AVG(content_depth),1)  AS avg_content
    FROM moderation_reviews WHERE game_id=?
");
$stmt->execute([$gameId]);
$avgScores = $stmt->fetch(PDO::FETCH_ASSOC);

$pendingExperts = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames   = $pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['name']) ?> — Экспертная модерация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0e13;
            --surface: #131720;
            --surface2: #1a2030;
            --border: #232b3a;
            --accent: #4ade80;
            --accent2: #22d3ee;
            --text: #e8edf5;
            --muted: #6b7a99;
            --danger: #f87171;
            --warning: #fbbf24;
            --sidebar: 240px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        aside {
            width: var(--sidebar); background: var(--surface); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0;
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .logo { padding: 28px 24px 20px; font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--accent); letter-spacing: -.5px; border-bottom: 1px solid var(--border); margin-bottom: 8px; }
        .logo span { color: var(--muted); font-size: .7rem; font-weight: 400; display: block; letter-spacing: .5px; text-transform: uppercase; margin-top: 2px; }
        .nav-section { padding: 12px 16px 4px; font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); }
        aside a { display: flex; align-items: center; gap: 10px; margin: 2px 8px; padding: 10px 16px; border-radius: 8px; color: var(--muted); font-size: .9rem; font-weight: 500; text-decoration: none; transition: all .18s; }
        aside a:hover { background: var(--surface2); color: var(--text); }
        aside a.active { background: rgba(74,222,128,.1); color: var(--accent); }
        aside a .badge { margin-left: auto; background: var(--danger); color: #fff; font-size: .7rem; font-weight: 700; border-radius: 12px; padding: 2px 7px; }
        .sidebar-footer { margin-top: auto; padding: 16px; border-top: 1px solid var(--border); }
        .user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--surface2); border-radius: 10px; }
        .avatar { width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-weight: 800; font-size: .85rem; color: #0b0e13; }

        main { flex: 1; overflow: auto; }
        .main-inner { padding: 40px; max-width: 920px; }

        .back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); text-decoration: none; font-size: .85rem; margin-bottom: 24px; transition: color .2s; }
        .back-link:hover { color: var(--text); }

        /* ── Game header ── */
        .game-header {
            background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
            overflow: hidden; margin-bottom: 24px; display: grid; grid-template-columns: 280px 1fr;
        }
        .game-cover-col {
            position: relative; cursor: pointer; overflow: hidden;
            background: #0d1117;
        }
        .game-cover-col img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s; }
        .game-cover-col:hover img { transform: scale(1.03); }
        .cover-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0);
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
        }
        .game-cover-col:hover .cover-overlay { background: rgba(0,0,0,.4); }
        .cover-overlay-icon { color: #fff; font-size: 2rem; opacity: 0; transition: opacity .2s; }
        .game-cover-col:hover .cover-overlay-icon { opacity: 1; }
        .game-info-col { padding: 24px; border-left: 1px solid var(--border); }
        .game-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .game-studio { font-size: .88rem; color: var(--muted); margin-bottom: 16px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; margin-bottom: 16px; }
        .meta-row .lbl { font-size: .68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 2px; }
        .meta-row .val { font-size: .88rem; color: var(--text); }
        .mod-status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px; font-size: .78rem; font-weight: 700;
            background: rgba(251,191,36,.12); color: var(--warning); border: 1px solid rgba(251,191,36,.25);
        }

        /* ── Скриншоты ── */
        .scr-strip { display: flex; gap: 8px; overflow-x: auto; padding: 16px 24px; border-top: 1px solid var(--border); scrollbar-width: thin; }
        .scr-strip::-webkit-scrollbar { height: 4px; }
        .scr-strip::-webkit-scrollbar-track { background: transparent; }
        .scr-strip::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
        .scr-thumb {
            flex-shrink: 0; width: 160px; height: 90px; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 2px solid transparent; transition: border-color .2s, transform .2s;
            background: var(--surface2);
        }
        .scr-thumb:hover { border-color: var(--accent2); transform: translateY(-2px); }
        .scr-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* ── Лайтбокс ── */
        .lightbox {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.92); align-items: center; justify-content: center;
            backdrop-filter: blur(6px);
        }
        .lightbox.open { display: flex; }
        .lightbox-img { max-width: 90vw; max-height: 88vh; border-radius: 10px; box-shadow: 0 24px 80px rgba(0,0,0,.8); }
        .lightbox-close {
            position: fixed; top: 20px; right: 24px; color: #fff; font-size: 2rem;
            cursor: pointer; opacity: .7; transition: opacity .2s; line-height: 1;
            background: none; border: none;
        }
        .lightbox-close:hover { opacity: 1; }
        .lightbox-nav {
            position: fixed; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,.1); border: none; color: #fff;
            font-size: 1.8rem; cursor: pointer; padding: 12px 16px;
            border-radius: 8px; transition: background .2s; backdrop-filter: blur(4px);
        }
        .lightbox-nav:hover { background: rgba(255,255,255,.2); }
        .lightbox-prev { left: 20px; }
        .lightbox-next { right: 20px; }
        .lightbox-counter { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,.6); font-size: .85rem; }

        /* ── Прогресс ── */
        .progress-section { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .progress-header h3 { font-family: 'Syne', sans-serif; font-weight: 700; }
        .progress-header span { font-size: .85rem; color: var(--muted); }
        .progress-bar { height: 8px; background: var(--surface2); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent2)); border-radius: 4px; transition: width .8s ease; }

        /* ── Билд ── */
        .build-block { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }

        /* ── Форма оценки ── */
        .form-section { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-bottom: 24px; }
        .section-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 20px; }

        /* ── Критерии — профессиональный вид ── */
        .criteria-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        .criterion-row {
            display: grid; grid-template-columns: 1fr 200px;
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        .criterion-row:last-child { border-bottom: none; }
        .criterion-row:nth-child(odd) { background: rgba(255,255,255,.01); }
        .criterion-row:hover { background: rgba(255,255,255,.03); }
        .criterion-label {
            padding: 14px 16px; display: flex; flex-direction: column; justify-content: center;
            border-right: 1px solid var(--border);
        }
        .criterion-name { font-size: .88rem; font-weight: 600; color: var(--text); margin-bottom: 2px; }
        .criterion-desc { font-size: .72rem; color: var(--muted); line-height: 1.4; }
        .criterion-input { padding: 10px 14px; display: flex; align-items: center; }
        .criterion-input select {
            width: 100%; background: transparent; border: 1px solid var(--border);
            border-radius: 6px; padding: 7px 10px; color: var(--text); font-size: .82rem;
            cursor: pointer; outline: none; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s;
        }
        .criterion-input select:focus { border-color: var(--accent2); }

        /* Средние оценки (для чтения) */
        .avg-bar-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.04); }
        .avg-bar-row:last-child { border-bottom: none; }
        .avg-label { font-size: .8rem; color: var(--muted); width: 140px; flex-shrink: 0; }
        .avg-bar-track { flex: 1; height: 5px; background: var(--surface2); border-radius: 3px; overflow: hidden; }
        .avg-bar-fill { height: 100%; border-radius: 3px; transition: width .6s; }
        .avg-val { font-size: .82rem; font-weight: 700; width: 28px; text-align: right; flex-shrink: 0; }

        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .field label { font-size: .75rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); }
        .field input, .field textarea { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: .9rem; transition: border-color .2s; }
        .field input:focus, .field textarea:focus { outline: none; border-color: var(--accent); }
        .field textarea { min-height: 100px; resize: vertical; }

        input[type=range] { -webkit-appearance: none; width: 100%; height: 6px; border-radius: 3px; background: var(--surface2); outline: none; }
        input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 20px; height: 20px; border-radius: 50%; background: var(--accent); cursor: pointer; border: 3px solid var(--bg); box-shadow: 0 0 0 2px var(--accent); }
        input[type=range]::-moz-range-thumb { width: 20px; height: 20px; border-radius: 50%; background: var(--accent); cursor: pointer; border: 3px solid var(--bg); }

        .verdict-btn { user-select: none; }
        .btn-submit { background: var(--accent); color: #0b0e13; border: none; border-radius: 10px; padding: 13px 28px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; cursor: pointer; transition: all .2s; width: 100%; }
        .btn-submit:hover { background: #22c55e; transform: translateY(-1px); }

        .form-section textarea { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: .9rem; width: 100%; transition: border-color .2s; resize: vertical; }
        .form-section textarea:focus { outline: none; border-color: var(--accent); }

        /* ── Рецензии ── */
        .review-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 12px; }
        select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%236b7a99'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 30px !important; }
    </style>
</head>
<body>

<aside>
    <div class="logo">Dustore <span>Expert Panel</span></div>
    <div class="nav-section">Меню</div>
    <a href="index">🏠 Главная</a>
    <a href="expert-requests">
        👤 Заявки экспертов
        <?php if ($pendingExperts > 0): ?><span class="badge"><?= $pendingExperts ?></span><?php endif; ?>
    </a>
    <a href="moderation" class="active">
        🎮 Модерация игр
        <?php if ($pendingGames > 0): ?><span class="badge"><?= $pendingGames ?></span><?php endif; ?>
    </a>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['USERDATA']['username'], 0, 1)) ?></div>
            <div>
                <div style="font-size:.85rem;font-weight:500;"><?= htmlspecialchars($_SESSION['USERDATA']['username']) ?></div>
                <div style="font-size:.72rem;color:var(--muted);">Эксперт</div>
            </div>
        </div>
        <a href="logout" style="color:var(--danger);margin-top:8px;padding:8px 12px;font-size:.85rem;display:block;">Выйти →</a>
    </div>
</aside>

<!-- Лайтбокс -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightboxBtn()">×</button>
    <button class="lightbox-nav lightbox-prev" onclick="lightboxNav(-1);event.stopPropagation()">‹</button>
    <img class="lightbox-img" id="lightbox-img" src="" alt="">
    <button class="lightbox-nav lightbox-next" onclick="lightboxNav(1);event.stopPropagation()">›</button>
    <div class="lightbox-counter" id="lightbox-counter"></div>
</div>

<main>
<div class="main-inner">
    <a href="moderation" class="back-link">← Назад к списку</a>

    <!-- ═══ ШАПКА ИГРЫ ═══ -->
    <div class="game-header" style="margin-bottom:24px;">

        <!-- Обложка — кликабельна -->
        <div class="game-cover-col" onclick="openLightboxSingle('<?= htmlspecialchars($game['path_to_cover'] ?? '') ?>')"
             style="<?= empty($game['path_to_cover']) ? 'display:flex;align-items:center;justify-content:center;font-size:3rem;' : '' ?>">
            <?php if (!empty($game['path_to_cover'])): ?>
                <img src="<?= htmlspecialchars($game['path_to_cover']) ?>" alt="Cover">
                <div class="cover-overlay">
                    <span class="cover-overlay-icon">⤢</span>
                </div>
            <?php else: ?>
                🎮
            <?php endif; ?>
        </div>

        <!-- Инфо -->
        <div class="game-info-col">
            <div class="game-title"><?= htmlspecialchars($game['name']) ?></div>
            <div class="game-studio">
                <?= htmlspecialchars($game['studio_name'] ?? 'Студия не указана') ?>
                <?php if (!empty($game['tiker'])): ?> · <?= htmlspecialchars($game['tiker']) ?><?php endif; ?>
            </div>

            <div class="meta-grid">
                <div class="meta-row">
                    <div class="lbl">Жанр</div>
                    <div class="val"><?= htmlspecialchars($game['genre'] ?? '—') ?></div>
                </div>
                <div class="meta-row">
                    <div class="lbl">Платформы</div>
                    <div class="val"><?= htmlspecialchars($game['platforms'] ?? '—') ?></div>
                </div>
                <div class="meta-row">
                    <div class="lbl">Дата релиза</div>
                    <div class="val"><?= htmlspecialchars($game['release_date'] ?? '—') ?></div>
                </div>
                <div class="meta-row">
                    <div class="lbl">Возраст</div>
                    <div class="val"><?= htmlspecialchars($game['age_rating'] ?? '—') ?></div>
                </div>
                <div class="meta-row">
                    <div class="lbl">Языки</div>
                    <div class="val"><?= htmlspecialchars($game['languages'] ?? '—') ?></div>
                </div>
                <div class="meta-row">
                    <div class="lbl">Цена</div>
                    <div class="val"><?= $game['price'] ? $game['price'] . ' ₽' : 'Бесплатно' ?></div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="mod-status-chip">⏳ На модерации</span>
                <?php if ($game['GQI']): ?>
                <span style="background:rgba(74,222,128,.1);color:var(--accent);border:1px solid rgba(74,222,128,.2);
                             padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:700;">
                    GQI <?= $game['GQI'] ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($game['trailer_url'])): ?>
                <a href="<?= htmlspecialchars($game['trailer_url']) ?>" target="_blank"
                   style="background:rgba(34,211,238,.1);color:var(--accent2);border:1px solid rgba(34,211,238,.2);
                          padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;">
                    ▶ Трейлер
                </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($game['short_description'])): ?>
            <div style="margin-top:14px;font-size:.85rem;color:var(--muted);line-height:1.6;border-top:1px solid var(--border);padding-top:14px;">
                <?= htmlspecialchars($game['short_description']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ СКРИНШОТЫ (один раз, лайтбокс) ═══ -->
    <?php if (!empty($screenshots)): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;
                margin-bottom:24px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;">
                Скриншоты
            </div>
            <div style="font-size:.78rem;color:var(--muted);"><?= count($screenshots) ?> шт · нажмите для просмотра</div>
        </div>
        <div class="scr-strip">
            <?php foreach ($screenshots as $i => $s): ?>
            <div class="scr-thumb" onclick="openLightbox(<?= $i ?>)">
                <img src="<?= htmlspecialchars($s['path'] ?? '') ?>" alt="screenshot <?= $i+1 ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ ОПИСАНИЕ ═══ -->
    <?php if (!empty($game['description'])): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;
                padding:24px;margin-bottom:24px;">
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;margin-bottom:12px;">
            Описание
        </div>
        <div style="font-size:.9rem;color:var(--muted);line-height:1.7;">
            <?= nl2br(htmlspecialchars($game['description'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ ФИЧИ + ТРЕБОВАНИЯ ═══ -->
    <?php if (!empty($features) || !empty($requirements)): ?>
    <div style="display:grid;grid-template-columns:<?= (!empty($features) && !empty($requirements)) ? '1fr 1fr' : '1fr' ?>;gap:16px;margin-bottom:24px;">
        <?php if (!empty($features)): ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;">
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;margin-bottom:12px;">Особенности</div>
            <?php foreach ($features as $f): ?>
            <div style="display:flex;gap:8px;margin-bottom:8px;font-size:.85rem;">
                <span style="color:var(--accent);flex-shrink:0;"><?= htmlspecialchars($f['icon'] ?? '·') ?></span>
                <div>
                    <span style="font-weight:600;"><?= htmlspecialchars($f['title'] ?? '') ?></span>
                    <?php if (!empty($f['description'])): ?>
                    <span style="color:var(--muted);"> — <?= htmlspecialchars($f['description']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($requirements)): ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;">
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;margin-bottom:12px;">Системные требования</div>
            <?php foreach ($requirements as $r): ?>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.85rem;">
                <span style="color:var(--muted);"><?= htmlspecialchars($r['label'] ?? '') ?></span>
                <span><?= htmlspecialchars($r['value'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ БИЛД ═══ -->
    <?php if (!empty($game['game_zip_url'])): ?>
    <?php
    $isChunked = str_ends_with((string)$game['game_zip_url'], 'manifest.json');
    $sizeMb    = !empty($game['game_zip_size']) ? round($game['game_zip_size'] / 1048576, 1) . ' МБ' : '';
    ?>
    <div class="build-block" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:4px;">📦 Файл игры</div>
                <div style="font-size:.82rem;color:var(--muted);">
                    <?= $isChunked ? "Chunked upload · $sizeMb" : "ZIP · $sizeMb" ?>
                    <?php if ($isChunked): ?>
                    <span style="margin-left:8px;font-size:.75rem;color:var(--warning);">
                        ℹ Скачайте manifest.json и соберите чанки вручную
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= htmlspecialchars($game['game_zip_url']) ?>" target="_blank"
               <?= !$isChunked ? 'download' : '' ?>
               style="display:inline-flex;align-items:center;gap:8px;
                      background:<?= $isChunked ? 'var(--surface2)' : 'linear-gradient(135deg,rgba(74,222,128,.15),rgba(34,211,238,.1))' ?>;
                      border:1px solid <?= $isChunked ? 'var(--border)' : 'rgba(74,222,128,.3)' ?>;
                      border-radius:10px;padding:11px 20px;
                      color:<?= $isChunked ? 'var(--accent2)' : 'var(--accent)' ?>;
                      text-decoration:none;font-weight:700;font-size:.9rem;">
                <?= $isChunked ? '📄 manifest.json' : '⬇ Скачать билд' ?>
                <?= $sizeMb ? "($sizeMb)" : '' ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ ПРОГРЕСС ГОЛОСОВАНИЯ ═══ -->
    <div class="progress-section">
        <div class="progress-header">
            <h3>Прогресс голосования</h3>
            <span><?= $reviewCount ?> из <?= $needVotes ?> голосов (<?= $progress ?>%)</span>
        </div>
        <div class="progress-bar" style="margin-bottom:16px;">
            <div class="progress-fill" style="width:<?= $progress ?>%"></div>
        </div>

        <?php if (!empty($avgScores) && $reviewCount > 0): ?>
        <div style="border-top:1px solid var(--border);padding-top:16px;">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:12px;">
                Средние оценки экспертов
            </div>
            <?php
            $avgRows = [
                ['Геймплей',       $avgScores['avg_gameplay'],   '#22d3ee'],
                ['Визуал',         $avgScores['avg_visual'],     '#a78bfa'],
                ['Стабильность',   $avgScores['avg_stability'],  '#4ade80'],
                ['Оригинальность', $avgScores['avg_originality'],'#fbbf24'],
                ['Звук',           $avgScores['avg_sound'],      '#fb923c'],
                ['Глубина',        $avgScores['avg_content'],    '#f472b6'],
            ];
            foreach ($avgRows as [$lbl, $val, $col]):
                $pct = $val > 0 ? round($val * 10) : 0;
            ?>
            <div class="avg-bar-row">
                <div class="avg-label"><?= $lbl ?></div>
                <div class="avg-bar-track">
                    <div class="avg-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                </div>
                <div class="avg-val" style="color:<?= $val > 0 ? $col : 'var(--muted)' ?>;">
                    <?= $val > 0 ? $val : '—' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ ФОРМА ОЦЕНКИ ═══ -->
    <?php if ($expertId && $game['moderation_status'] === 'pending'): ?>
    <div class="form-section" id="review-form-section">
        <div class="section-title">
            <?= $hasMyReview ? '✏️ Редактировать оценку' : '⭐ Оценить игру' ?>
            <?php if ($hasMyReview): ?>
            <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-left:8px;">
                · голос учтён, можно изменить
            </span>
            <?php endif; ?>
        </div>

        <form method="post" action="submit-moderation?id=<?= $gameId ?>">

            <!-- Общая оценка -->
            <div style="margin-bottom:28px;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                    <label style="font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);">
                        Общая оценка
                    </label>
                    <div style="display:flex;align-items:baseline;gap:6px;">
                        <span id="score-display" style="font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;color:var(--accent);line-height:1;">
                            <?= $hasMyReview ? $myReview['score'] : 50 ?>
                        </span>
                        <span style="color:var(--muted);font-size:1rem;">/ 100</span>
                    </div>
                </div>
                <input type="range" name="score" id="score-slider" min="0" max="100" step="1"
                       value="<?= $hasMyReview ? $myReview['score'] : 50 ?>"
                       style="width:100%;accent-color:var(--accent);cursor:pointer;height:6px;"
                       oninput="updateScore(this.value)">
                <div style="display:flex;justify-content:space-between;margin-top:8px;">
                    <?php foreach ([[0,'0','#f87171','Провал'],[25,'25','#fb923c','Слабо'],[51,'51','#fbbf24','Порог'],[75,'75','#a3e635','Хорошо'],[100,'100','#4ade80','Отлично']] as [$v,$n,$c,$l]): ?>
                    <div style="text-align:center;cursor:pointer;" onclick="setScore(<?= $v ?>)">
                        <div style="font-size:.7rem;font-weight:700;color:<?= $c ?>;"><?= $n ?></div>
                        <div style="font-size:.65rem;color:var(--muted);"><?= $l ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="score-hint" style="margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;font-weight:500;transition:all .2s;"></div>
            </div>

            <!-- Детальные критерии — табличный вид -->
            <div style="margin-bottom:20px;">
                <div style="font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
                            color:var(--muted);margin-bottom:10px;">
                    Детальная оценка по критериям
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#4a5568;">· учитывается в расчёте GQI</span>
                </div>

                <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                    <?php
                    $criteria = [
                        ['gameplay_score', 'Геймплей',      'Насколько механики продуманы? Есть ли баланс, реиграбельность, отзывчивость управления?'],
                        ['visual_score',   'Визуал',         'Арт-стиль, графика, UI, анимации — воспринимается ли визуально целостно?'],
                        ['stability',      'Стабильность',   'Баги, вылеты, просадки FPS, технические проблемы при прохождении.'],
                        ['originality',    'Оригинальность', 'Насколько идея и реализация выделяется на фоне существующих игр?'],
                        ['sound_score',    'Звук и музыка',  'Качество OST, звуковых эффектов, общая звуковая атмосфера.'],
                        ['content_depth',  'Глубина',        'Объём контента, продолжительность, есть ли чем заняться после первого прохождения?'],
                    ];
                    foreach ($criteria as $idx => [$fname, $flabel, $fdesc]):
                        $savedVal = $hasMyReview ? ($myReview[$fname] ?? 0) : 0;
                    ?>
                    <div style="display:grid;grid-template-columns:1fr auto;
                                border-bottom:<?= $idx < count($criteria)-1 ? '1px solid var(--border)' : 'none' ?>;
                                transition:background .15s;"
                         onmouseover="this.style.background='rgba(255,255,255,.02)'"
                         onmouseout="this.style.background=''">
                        <div style="padding:14px 16px;border-right:1px solid var(--border);">
                            <div style="font-size:.88rem;font-weight:600;color:var(--text);margin-bottom:3px;">
                                <?= $flabel ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--muted);line-height:1.5;">
                                <?= $fdesc ?>
                            </div>
                        </div>
                        <div style="padding:10px 16px;display:flex;align-items:center;min-width:180px;">
                            <select name="<?= $fname ?>"
                                    style="width:100%;background:var(--surface2);border:1px solid var(--border);
                                           border-radius:6px;padding:8px 10px;color:var(--text);
                                           font-size:.82rem;cursor:pointer;outline:none;
                                           font-family:'DM Sans',sans-serif;transition:border-color .2s;"
                                    onfocus="this.style.borderColor='var(--accent2)'"
                                    onblur="this.style.borderColor='var(--border)'">
                                <?php foreach ([
                                    0  => '— пропустить',
                                    1  => '1 — Критично плохо',
                                    2  => '2 — Очень плохо',
                                    3  => '3 — Плохо',
                                    4  => '4 — Ниже среднего',
                                    5  => '5 — Среднее',
                                    6  => '6 — Выше среднего',
                                    7  => '7 — Хорошо',
                                    8  => '8 — Очень хорошо',
                                    9  => '9 — Отлично',
                                    10 => '10 — Эталон',
                                ] as $v => $l): ?>
                                <option value="<?= $v ?>"<?= $savedVal == $v ? ' selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Рецензия -->
            <div class="field" style="margin-bottom:20px;">
                <label>
                    Рецензия
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);">
                        · анонимна до завершения голосования
                    </span>
                </label>
                <textarea name="review" rows="5" required
                    placeholder="Опишите впечатления развёрнуто. Что сделано хорошо? Что критично улучшить? Есть ли аудитория у этой игры?"
                    style="min-height:120px;"><?= htmlspecialchars($myReview['comment'] ?? '') ?></textarea>
                <div style="font-size:.72rem;color:var(--muted);">Минимум 20 символов</div>
            </div>

            <!-- Вердикт -->
            <div style="display:flex;gap:10px;margin-bottom:20px;">
                <label style="flex:1;cursor:pointer;">
                    <input type="radio" name="verdict" value="recommend" style="display:none;" id="v-yes"
                        <?= (!$hasMyReview || ($myReview['score'] ?? 0) > 51) ? 'checked' : '' ?>>
                    <div class="verdict-btn" data-for="v-yes"
                         style="padding:13px 16px;border-radius:10px;border:2px solid;text-align:center;
                                font-weight:600;font-size:.9rem;transition:all .2s;
                                border-color:<?= (!$hasMyReview || ($myReview['score']??0) > 51) ? 'var(--accent)' : 'var(--border)' ?>;
                                background:<?= (!$hasMyReview || ($myReview['score']??0) > 51) ? 'rgba(74,222,128,.1)' : 'transparent' ?>;
                                color:<?= (!$hasMyReview || ($myReview['score']??0) > 51) ? 'var(--accent)' : 'var(--muted)' ?>;">
                        👍 Рекомендую к публикации
                    </div>
                </label>
                <label style="flex:1;cursor:pointer;">
                    <input type="radio" name="verdict" value="reject" style="display:none;" id="v-no"
                        <?= ($hasMyReview && ($myReview['score']??0) <= 51) ? 'checked' : '' ?>>
                    <div class="verdict-btn" data-for="v-no"
                         style="padding:13px 16px;border-radius:10px;border:2px solid;text-align:center;
                                font-weight:600;font-size:.9rem;transition:all .2s;
                                border-color:<?= ($hasMyReview && ($myReview['score']??0) <= 51) ? '#f87171' : 'var(--border)' ?>;
                                background:<?= ($hasMyReview && ($myReview['score']??0) <= 51) ? 'rgba(248,113,113,.1)' : 'transparent' ?>;
                                color:<?= ($hasMyReview && ($myReview['score']??0) <= 51) ? '#f87171' : 'var(--muted)' ?>;">
                        👎 Не рекомендую
                    </div>
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <?= $hasMyReview ? '💾 Обновить оценку' : '✅ Отправить оценку' ?>
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ═══ РЕЦЕНЗИИ ═══ -->
    <?php if ($reviews): ?>
    <div style="margin-bottom:24px;">
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:14px;">
            Рецензии (<?= count($reviews) ?>)
        </div>
        <?php foreach ($reviews as $r):
            $isMe     = ($r['username'] === $_SESSION['USERDATA']['username']);
            $positive = $r['score'] > 51;
        ?>
        <div class="review-card" style="<?= $isMe ? 'border:2px solid var(--accent2);background:rgba(34,211,238,.04);' : '' ?>">
            <?php if ($isMe): ?>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                <span style="font-size:.75rem;font-weight:700;background:rgba(34,211,238,.15);color:var(--accent2);padding:3px 10px;border-radius:4px;">★ ВАШ ОТЗЫВ</span>
                <a href="#review-form-section" style="font-size:.75rem;color:var(--accent2);text-decoration:none;">изменить ↑</a>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <div style="font-size:1.3rem;font-weight:800;color:<?= $positive ? 'var(--accent)' : '#f87171' ?>;font-family:monospace;">
                    <?= $r['score'] ?>
                </div>
                <div style="font-size:.82rem;color:<?= $positive ? 'var(--accent)' : '#f87171' ?>;font-weight:600;">
                    <?= $positive ? '👍 Рекомендует' : '👎 Против' ?>
                </div>
            </div>
            <?php if ($isMe || $game['moderation_status'] !== 'pending'): ?>
            <div style="font-size:.9rem;color:var(--muted);line-height:1.6;font-style:italic;
                        border-left:3px solid var(--border);padding-left:12px;
                        <?= $isMe ? 'color:var(--text);' : '' ?>">
                "<?= htmlspecialchars($r['review']) ?>"
            </div>
            <?php else: ?>
            <div style="font-size:.82rem;color:var(--muted);font-style:italic;">
                🔒 Рецензия скрыта до завершения голосования
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</main>

<script>
// ── Лайтбокс ──────────────────────────────────────────────────────────────
const LB_IMAGES = <?= json_encode(array_values(array_map(fn($s) => $s['path'] ?? '', $screenshots))) ?>;
let lbIndex = 0;

function openLightbox(idx) {
    lbIndex = idx;
    document.getElementById('lightbox-img').src = LB_IMAGES[lbIndex];
    document.getElementById('lightbox-counter').textContent = (lbIndex + 1) + ' / ' + LB_IMAGES.length;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function openLightboxSingle(src) {
    if (!src) return;
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox-counter').textContent = '';
    document.querySelector('.lightbox-prev').style.display = 'none';
    document.querySelector('.lightbox-next').style.display = 'none';
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox(e) {
    if (e.target === document.getElementById('lightbox')) closeLightboxBtn();
}

function closeLightboxBtn() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
    document.querySelector('.lightbox-prev').style.display = '';
    document.querySelector('.lightbox-next').style.display = '';
}

function lightboxNav(dir) {
    if (!LB_IMAGES.length) return;
    lbIndex = (lbIndex + dir + LB_IMAGES.length) % LB_IMAGES.length;
    document.getElementById('lightbox-img').src = LB_IMAGES[lbIndex];
    document.getElementById('lightbox-counter').textContent = (lbIndex + 1) + ' / ' + LB_IMAGES.length;
}

document.addEventListener('keydown', function(e) {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'Escape') closeLightboxBtn();
    if (e.key === 'ArrowRight') lightboxNav(1);
    if (e.key === 'ArrowLeft')  lightboxNav(-1);
});

// ── Слайдер оценки ────────────────────────────────────────────────────────
const SCORE_HINTS = [
    [0,  15,  '#f87171', '😱 Провал — игра не соответствует минимальным стандартам'],
    [16, 30,  '#fb923c', '😞 Слабо — серьёзные проблемы, мешающие игровому опыту'],
    [31, 50,  '#fbbf24', '😐 Ниже среднего — потенциал есть, но недостатков слишком много'],
    [51, 51,  '#facc15', '⚖️ Граница — ровно на пороге (51 = голос «за»)'],
    [52, 65,  '#a3e635', '🙂 Приемлемо — можно публиковать, есть что улучшить'],
    [66, 80,  '#4ade80', '😊 Хорошая игра — заслуживает внимания игроков'],
    [81, 90,  '#34d399', '🎮 Отличная — яркий представитель жанра'],
    [91, 100, '#22d3ee', '🏆 Шедевр — рекомендуем всем без исключения'],
];

function updateScore(val) {
    val = parseInt(val);
    document.getElementById('score-display').textContent = val;
    const hint = SCORE_HINTS.find(([lo, hi]) => val >= lo && val <= hi);
    const [,, color, text] = hint || [0, 0, '#4ade80', ''];
    document.getElementById('score-display').style.color = color;
    document.getElementById('score-slider').style.setProperty('accent-color', color);
    const el = document.getElementById('score-hint');
    el.textContent = text;
    el.style.background = color + '18';
    el.style.color = color;
    el.style.border = '1px solid ' + color + '44';
    const yes = document.getElementById('v-yes');
    const no  = document.getElementById('v-no');
    yes.checked = val > 51;
    no.checked  = val <= 51;
    updateVerdictBtns();
}

function setScore(val) {
    document.getElementById('score-slider').value = val;
    updateScore(val);
}

function updateVerdictBtns() {
    document.querySelectorAll('.verdict-btn').forEach(btn => {
        const radio = document.getElementById(btn.dataset.for);
        if (radio.checked) {
            const isYes = radio.value === 'recommend';
            btn.style.borderColor = isYes ? 'var(--accent)' : '#f87171';
            btn.style.background  = isYes ? 'rgba(74,222,128,.1)' : 'rgba(248,113,113,.1)';
            btn.style.color       = isYes ? 'var(--accent)' : '#f87171';
        } else {
            btn.style.borderColor = 'var(--border)';
            btn.style.background  = 'transparent';
            btn.style.color       = 'var(--muted)';
        }
    });
}

document.querySelectorAll('input[name=verdict]').forEach(r => r.addEventListener('change', updateVerdictBtns));
updateScore(document.getElementById('score-slider').value);
</script>
</body>
</html>