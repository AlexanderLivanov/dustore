<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db  = new Database();
$pdo = $db->connect();

$userId     = $_SESSION['USERDATA']['id'] ?? 0;
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
$isAdmin    = ($globalRole === -1);

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$userId]);
$expertRow = $stmt->fetch();
if (!$expertRow && !$isAdmin) die('Доступ запрещён');
$expertId = $expertRow['id'] ?? null;

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

// Проверяем какие колонки реально существуют (защита от незапущенной миграции)
$existingCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM moderation_reviews")->fetchAll(PDO::FETCH_COLUMN);
    $existingCols = array_flip($cols);
} catch (Exception $e) {
}

$critCols = ['gameplay_score', 'visual_score', 'stability', 'originality', 'sound_score', 'content_depth'];
$critSelect = '';
foreach ($critCols as $col) {
    $critSelect .= isset($existingCols[$col]) ? ", $col" : ", NULL AS $col";
}

// Моя рецензия
$myReview    = null;
$hasMyReview = false;
if ($expertId) {
    $stmt = $pdo->prepare("
        SELECT score, comment, verdict $critSelect
        FROM moderation_reviews WHERE game_id=? AND expert_id=?
    ");
    $stmt->execute([$gameId, $expertId]);
    $myReview    = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasMyReview = (bool)$myReview;
}

// Все рецензии
$stmt = $pdo->prepare("
    SELECT mr.id, mr.score, mr.comment AS review, mr.verdict,
           u.username, e.rating AS expert_weight, e.id AS eid
    FROM moderation_reviews mr
    JOIN experts e ON e.id = mr.expert_id
    JOIN users u ON u.id = e.user_id
    WHERE mr.game_id = ?
    ORDER BY mr.id DESC
");
$stmt->execute([$gameId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT expert_id) FROM moderation_reviews WHERE game_id=?");
$stmt->execute([$gameId]);
$reviewCount = (int)$stmt->fetchColumn();

$needVotes = max(1, (int)ceil($totalExperts * 0.51));
$progress  = min(100, round($reviewCount / $needVotes * 100));

// Агрегат (только score, т.к. детальных критериев нет)
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(score),1) AS avg_score,
           SUM(score > 51)     AS positive,
           SUM(score <= 51)    AS negative
    FROM moderation_reviews WHERE game_id=?
");
$stmt->execute([$gameId]);
$avgScores = $stmt->fetch(PDO::FETCH_ASSOC);

$pendingExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames   = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['name']) ?> — Экспертная модерация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        aside {
            width: var(--sidebar);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .logo {
            padding: 28px 24px 20px;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--accent);
            letter-spacing: -.5px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }

        .logo span {
            color: var(--muted);
            font-size: .7rem;
            font-weight: 400;
            display: block;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .nav-section {
            padding: 12px 16px 4px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
        }

        aside a {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 2px 8px;
            padding: 10px 16px;
            border-radius: 8px;
            color: var(--muted);
            font-size: .9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all .18s;
        }

        aside a:hover {
            background: var(--surface2);
            color: var(--text);
        }

        aside a.active {
            background: rgba(74, 222, 128, .1);
            color: var(--accent);
        }

        aside a .badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            border-radius: 12px;
            padding: 2px 7px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--surface2);
            border-radius: 10px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: .85rem;
            color: #0b0e13;
        }

        main {
            flex: 1;
            overflow: auto;
        }

        .main-inner {
            padding: 40px;
            max-width: 920px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--muted);
            text-decoration: none;
            font-size: .85rem;
            margin-bottom: 24px;
            transition: color .2s;
        }

        .back-link:hover {
            color: var(--text);
        }

        .game-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: 280px 1fr;
        }

        .game-cover-col {
            position: relative;
            cursor: pointer;
            overflow: hidden;
            background: #0d1117;
            min-height: 180px;
        }

        .game-cover-col img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .3s;
        }

        .game-cover-col:hover img {
            transform: scale(1.03);
        }

        .cover-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .2s;
        }

        .game-cover-col:hover .cover-overlay {
            background: rgba(0, 0, 0, .4);
        }

        .cover-overlay-icon {
            color: #fff;
            font-size: 2rem;
            opacity: 0;
            transition: opacity .2s;
        }

        .game-cover-col:hover .cover-overlay-icon {
            opacity: 1;
        }

        .game-info-col {
            padding: 24px;
            border-left: 1px solid var(--border);
        }

        .game-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            margin-bottom: 4px;
        }

        .game-studio {
            font-size: .88rem;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            margin-bottom: 16px;
        }

        .meta-row .lbl {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .meta-row .val {
            font-size: .88rem;
            color: var(--text);
        }

        .mod-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
            background: rgba(251, 191, 36, .12);
            color: var(--warning);
            border: 1px solid rgba(251, 191, 36, .25);
        }

        .scr-strip {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            scrollbar-width: thin;
        }

        .scr-strip::-webkit-scrollbar {
            height: 4px;
        }

        .scr-strip::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .scr-thumb {
            flex-shrink: 0;
            width: 160px;
            height: 90px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color .2s, transform .2s;
            background: var(--surface2);
        }

        .scr-thumb:hover {
            border-color: var(--accent2);
            transform: translateY(-2px);
        }

        .scr-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, .92);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
        }

        .lightbox.open {
            display: flex;
        }

        .lightbox-img {
            max-width: 90vw;
            max-height: 88vh;
            border-radius: 10px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .8);
        }

        .lightbox-close {
            position: fixed;
            top: 20px;
            right: 24px;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            opacity: .7;
            transition: opacity .2s;
            background: none;
            border: none;
        }

        .lightbox-close:hover {
            opacity: 1;
        }

        .lightbox-nav {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, .1);
            border: none;
            color: #fff;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 8px;
            transition: background .2s;
        }

        .lightbox-nav:hover {
            background: rgba(255, 255, 255, .2);
        }

        .lightbox-prev {
            left: 20px;
        }

        .lightbox-next {
            right: 20px;
        }

        .lightbox-counter {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, .6);
            font-size: .85rem;
        }

        .progress-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .progress-header h3 {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
        }

        .progress-header span {
            font-size: .85rem;
            color: var(--muted);
        }

        .progress-bar {
            height: 8px;
            background: var(--surface2);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 4px;
            transition: width .8s ease;
        }

        .form-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .review-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 10px;
        }

        /* Tooltips */
        .tip-wrap {
            position: relative;
        }

        .tip-box {
            display: none;
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 13px;
            font-size: .76rem;
            color: var(--muted);
            white-space: nowrap;
            z-index: 20;
            line-height: 1.55;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .4);
        }

        .tip-box::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: var(--surface2);
        }

        .tip-wrap:hover .tip-box {
            display: block;
        }
    </style>
</head>

<body>

    <aside>
        <div class="logo">Dustore <span><?= $isAdmin ? 'Admin Panel' : 'Expert Panel' ?></span></div>
        <div class="nav-section">Меню</div>
        <a href="index">🏠 Главная</a>
        <?php if ($isAdmin): ?>
            <a href="expert-requests">
                👤 Заявки экспертов
                <?php if ($pendingExperts > 0): ?><span class="badge"><?= $pendingExperts ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <a href="moderation" class="active">
            🎮 Модерация игр
            <?php if ($pendingGames > 0): ?><span class="badge"><?= $pendingGames ?></span><?php endif; ?>
        </a>
        <a href="all-reviews">📊 Все оценки</a>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['USERDATA']['username'], 0, 1)) ?></div>
                <div>
                    <div style="font-size:.85rem;font-weight:500;"><?= htmlspecialchars($_SESSION['USERDATA']['username']) ?></div>
                    <div style="font-size:.72rem;color:var(--muted);"><?= $isAdmin ? 'Администратор' : 'Эксперт' ?></div>
                </div>
            </div>
            <a href="logout" style="color:var(--danger);margin-top:8px;padding:8px 12px;font-size:.85rem;display:block;">Выйти →</a>
        </div>
    </aside>

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

            <!-- ШАПКА -->
            <div class="game-header" style="margin-bottom:24px;">
                <div class="game-cover-col" onclick="openLightboxSingle('<?= htmlspecialchars($game['path_to_cover'] ?? '') ?>')"
                    style="<?= empty($game['path_to_cover']) ? 'display:flex;align-items:center;justify-content:center;font-size:3rem;' : '' ?>">
                    <?php if (!empty($game['path_to_cover'])): ?>
                        <img src="<?= htmlspecialchars($game['path_to_cover']) ?>" alt="Cover">
                        <div class="cover-overlay"><span class="cover-overlay-icon">⤢</span></div>
                        <?php else: ?>🎮<?php endif; ?>
                </div>
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
                        <div style="margin-top:14px;font-size:.85rem;color:var(--muted);line-height:1.6;
                        border-top:1px solid var(--border);padding-top:14px;">
                            <?= htmlspecialchars($game['short_description']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- СКРИНШОТЫ -->
            <?php if (!empty($screenshots)): ?>
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;margin-bottom:24px;overflow:hidden;">
                    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;">Скриншоты</div>
                        <div style="font-size:.78rem;color:var(--muted);"><?= count($screenshots) ?> шт · нажмите для просмотра</div>
                    </div>
                    <div class="scr-strip">
                        <?php foreach ($screenshots as $i => $s): ?>
                            <div class="scr-thumb" onclick="openLightbox(<?= $i ?>)">
                                <img src="<?= htmlspecialchars($s['path'] ?? '') ?>" alt="screenshot <?= $i + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ОПИСАНИЕ -->
            <?php if (!empty($game['description'])): ?>
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:24px;">
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;margin-bottom:12px;">Описание</div>
                    <div style="font-size:.9rem;color:var(--muted);line-height:1.7;"><?= nl2br(htmlspecialchars($game['description'])) ?></div>
                </div>
            <?php endif; ?>

            <!-- ФИЧИ + ТРЕБОВАНИЯ -->
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

            <!-- БИЛД -->
            <?php if (!empty($game['game_zip_url'])): ?>
                <?php
                $isChunked = str_ends_with((string)$game['game_zip_url'], 'manifest.json');
                $sizeMb    = !empty($game['game_zip_size']) ? round($game['game_zip_size'] / 1048576, 1) . ' МБ' : '';
                ?>
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <div>
                            <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:4px;">📦 Файл игры</div>
                            <div style="font-size:.82rem;color:var(--muted);">
                                <?= $isChunked ? "Chunked upload · $sizeMb" : "ZIP · $sizeMb" ?>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($game['game_zip_url']) ?>" target="_blank" <?= !$isChunked ? 'download' : '' ?>
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

            <!-- ПРОГРЕСС -->
            <div class="progress-section">
                <div class="progress-header">
                    <h3>Прогресс голосования</h3>
                    <span><?= $reviewCount ?> из <?= $needVotes ?> голосов (<?= $progress ?>%)</span>
                </div>
                <div class="progress-bar" style="margin-bottom:16px;">
                    <div class="progress-fill" style="width:<?= $progress ?>%"></div>
                </div>
                <?php if ($reviewCount > 0 && $avgScores): ?>
                    <div style="display:flex;gap:20px;border-top:1px solid var(--border);padding-top:14px;flex-wrap:wrap;">
                        <div style="text-align:center;">
                            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.8rem;color:var(--accent);">
                                <?= $avgScores['avg_score'] ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--muted);">средний балл</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.8rem;color:#4ade80;">
                                <?= (int)$avgScores['positive'] ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--muted);">👍 за</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.8rem;color:#f87171;">
                                <?= (int)$avgScores['negative'] ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--muted);">👎 против</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ФОРМА ОЦЕНКИ -->
            <?php if ($expertId && $game['moderation_status'] === 'pending'): ?>
                <div class="form-section" id="review-form-section">
                    <div class="section-title">
                        <?= $hasMyReview ? '✏️ Редактировать оценку' : '⭐ Оценить игру' ?>
                        <?php if ($hasMyReview): ?>
                            <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-left:8px;">· голос учтён, можно изменить</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="submit-moderation?id=<?= $gameId ?>" id="review-form" novalidate>

                        <!-- ЧЕКЛИСТ -->
                        <div style="margin-bottom:24px;">
                            <div style="font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;">
                                📋 Чеклист соответствия требованиям
                            </div>
                            <details style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:10px;">
                                <summary style="padding:13px 16px;cursor:pointer;font-size:.88rem;font-weight:600;
                                   display:flex;align-items:center;gap:8px;list-style:none;user-select:none;">
                                    <span class="material-icons" style="font-size:16px;color:var(--muted);">checklist</span>
                                    Проверить игру по критериям перед оценкой
                                    <span id="cl-counter" style="margin-left:auto;font-size:.73rem;font-weight:700;
                                                     background:rgba(195,33,120,.15);color:#c32178;padding:2px 10px;border-radius:10px;">
                                        0 / 10
                                    </span>
                                </summary>
                                <div style="padding:0 16px 16px;border-top:1px solid var(--border);">
                                    <div style="font-size:.78rem;color:var(--muted);padding:10px 0 14px;line-height:1.5;">
                                        Убедитесь, что игра соответствует базовым требованиям.
                                        Это <strong style="color:var(--text);">не блокирует отправку</strong> — помогает структурировать мнение.
                                    </div>
                                    <?php
                                    $checklist = [
                                        'Игра запускается и проходима хотя бы в основном контенте',
                                        'Название и описание заполнены, нет спама или плейсхолдеров',
                                        'Загружена обложка корректного качества',
                                        'Присутствует минимум 3 скриншота из реального геймплея',
                                        'Возрастной рейтинг соответствует содержанию игры',
                                        'Нет явных нарушений авторских прав (ассеты, музыка, персонажи)',
                                        'Игра работает без критических вылетов в первые 5 минут',
                                        'Управление объяснено или интуитивно понятно',
                                        'Нет оскорбительного или незадекларированного контента 18+',
                                        'Файл игры соответствует заявленной платформе',
                                    ];
                                    foreach ($checklist as $i => $label): ?>
                                        <label class="cl-row" style="display:flex;align-items:flex-start;gap:12px;
                               padding:10px 12px;border-radius:8px;cursor:pointer;transition:background .15s;
                               <?= $i > 0 ? 'border-top:1px solid rgba(255,255,255,.04);' : '' ?>">
                                            <input type="checkbox" name="checklist[]" value="<?= $i ?>" class="cl-inp" style="display:none;">
                                            <div class="cl-box" style="width:20px;height:20px;border-radius:5px;flex-shrink:0;margin-top:1px;
                                                        border:2px solid var(--border);display:flex;align-items:center;
                                                        justify-content:center;transition:all .15s;">
                                                <span class="material-icons cl-mark" style="font-size:13px;color:var(--accent);display:none;">check</span>
                                            </div>
                                            <span style="font-size:.85rem;line-height:1.5;"><?= htmlspecialchars($label) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        </div>

                        <!-- ОБЩАЯ ОЦЕНКА -->
                        <div style="margin-bottom:28px;">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                                <label style="font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);">
                                    Общая оценка <span style="color:var(--danger);">*</span>
                                </label>
                                <div style="display:flex;align-items:baseline;gap:6px;">
                                    <span id="score-display" style="font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;line-height:1;color:var(--muted);">—</span>
                                    <span style="color:var(--muted);font-size:1rem;">/ 100</span>
                                </div>
                            </div>
                            <input type="hidden" name="score" id="score-value" value="<?= $hasMyReview ? (int)$myReview['score'] : '' ?>">

                            <div style="position:relative;height:48px;margin-bottom:8px;">
                                <div id="sl-track" style="position:absolute;top:50%;transform:translateY(-50%);
                                               width:100%;height:8px;border-radius:4px;background:var(--border);cursor:pointer;"></div>
                                <div id="sl-fill" style="position:absolute;top:50%;transform:translateY(-50%);
                                              height:8px;border-radius:4px;width:0;background:var(--muted);pointer-events:none;"></div>
                                <div id="sl-thumb" style="position:absolute;top:50%;transform:translate(-50%,-50%);
                                               width:22px;height:22px;border-radius:50%;background:var(--muted);
                                               border:3px solid #0b0e13;cursor:grab;display:none;
                                               box-shadow:0 2px 8px rgba(0,0,0,.4);"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                                <?php foreach ([[0, '#f87171', 'Провал'], [25, '#fb923c', 'Слабо'], [51, '#fbbf24', 'Порог'], [75, '#a3e635', 'Хорошо'], [100, '#4ade80', 'Отлично']] as [$v, $c, $l]): ?>
                                    <div style="text-align:center;cursor:pointer;padding:4px 6px;border-radius:6px;" onclick="setScore(<?= $v ?>)">
                                        <div style="font-size:.7rem;font-weight:700;color:<?= $c ?>;"><?= $v ?></div>
                                        <div style="font-size:.62rem;color:var(--muted);"><?= $l ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="score-hint" style="margin-top:10px;padding:9px 14px;border-radius:8px;font-size:.83rem;font-weight:500;display:none;"></div>
                            <div id="score-err" style="margin-top:8px;font-size:.78rem;color:var(--danger);display:none;">⚠ Укажите общую оценку</div>
                        </div>

                        <!-- РЕЦЕНЗИЯ -->
                        <div style="margin-bottom:24px;">
                            <label style="display:block;font-size:.75rem;font-weight:700;letter-spacing:.5px;
                              text-transform:uppercase;color:var(--muted);margin-bottom:8px;">
                                Рецензия <span style="color:var(--danger);">*</span>
                                <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#4a5568;margin-left:6px;">· анонимна до завершения голосования</span>
                            </label>
                            <textarea name="review" id="review-text" rows="5" required minlength="40"
                                placeholder="Опишите впечатления развёрнуто. Что сделано хорошо? Что критично улучшить? Есть ли аудитория у этой игры?"
                                style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;
                                 padding:12px 16px;color:var(--text);font-family:'DM Sans',sans-serif;
                                 font-size:.9rem;line-height:1.6;resize:vertical;min-height:120px;outline:none;"
                                oninput="checkReady()"><?= htmlspecialchars($myReview['comment'] ?? '') ?></textarea>
                            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                                <div style="font-size:.72rem;color:var(--muted);">Минимум 40 символов</div>
                                <div id="char-count" style="font-size:.72rem;color:var(--muted);">
                                    <?= mb_strlen($myReview['comment'] ?? '') ?> симв.
                                </div>
                            </div>
                        </div>

                        <!-- ВЕРДИКТ -->
                        <input type="hidden" name="verdict" id="verdict-value" value="<?= htmlspecialchars($myReview['verdict'] ?? '') ?>">
                        <div style="font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;">
                            Вердикт <span style="color:var(--danger);">*</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:8px;">
                            <div class="tip-wrap">
                                <button type="button" class="vbtn" id="v-recommend" data-v="recommend"
                                    onclick="setVerdict('recommend')"
                                    style="width:100%;padding:14px 10px;border-radius:10px;border:2px solid var(--border);
                                   background:transparent;color:var(--muted);cursor:pointer;
                                   font-family:'DM Sans',sans-serif;font-weight:600;font-size:.85rem;
                                   text-align:center;transition:all .2s;line-height:1.3;
                                   <?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'recommend') ? 'border-color:#4ade80;background:rgba(74,222,128,.1);color:#4ade80;' : '' ?>">
                                    👍<br>Рекомендую
                                </button>
                                <div class="tip-box">
                                    Игра готова к публикации.<br>
                                    Голос засчитывается <strong style="color:#4ade80;">«за»</strong>.
                                </div>
                            </div>
                            <div class="tip-wrap">
                                <button type="button" class="vbtn" id="v-revision" data-v="revision"
                                    onclick="setVerdict('revision')"
                                    style="width:100%;padding:14px 10px;border-radius:10px;border:2px solid var(--border);
                                   background:transparent;color:var(--muted);cursor:pointer;
                                   font-family:'DM Sans',sans-serif;font-weight:600;font-size:.85rem;
                                   text-align:center;transition:all .2s;line-height:1.3;
                                   <?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'revision') ? 'border-color:#fbbf24;background:rgba(251,191,36,.1);color:#fbbf24;' : '' ?>">
                                    🔄<br>На доработку
                                </button>
                                <div class="tip-box">
                                    Игра перспективна, но сырая.<br>
                                    Голос засчитывается <strong style="color:#fbbf24;">«против»</strong>.<br>
                                    Разработчик видит, что нужна доработка.
                                </div>
                            </div>
                            <div class="tip-wrap">
                                <button type="button" class="vbtn" id="v-reject" data-v="reject"
                                    onclick="setVerdict('reject')"
                                    style="width:100%;padding:14px 10px;border-radius:10px;border:2px solid var(--border);
                                   background:transparent;color:var(--muted);cursor:pointer;
                                   font-family:'DM Sans',sans-serif;font-weight:600;font-size:.85rem;
                                   text-align:center;transition:all .2s;line-height:1.3;
                                   <?= ($hasMyReview && ($myReview['verdict'] ?? '') === 'reject') ? 'border-color:#f87171;background:rgba(248,113,113,.1);color:#f87171;' : '' ?>">
                                    👎<br>Не рекомендую
                                </button>
                                <div class="tip-box">
                                    Игра не соответствует стандартам.<br>
                                    Голос засчитывается <strong style="color:#f87171;">«против»</strong>.
                                </div>
                            </div>
                        </div>

                        <div id="verdict-err" style="font-size:.78rem;color:var(--danger);display:none;margin-bottom:12px;">⚠ Выберите вердикт</div>

                        <button type="submit" id="submit-btn"
                            style="display:flex;align-items:center;justify-content:center;gap:8px;
                           width:100%;padding:14px;border-radius:10px;border:none;
                           background:var(--muted);color:#0b0e13;font-family:'Syne',sans-serif;
                           font-weight:700;font-size:.95rem;cursor:not-allowed;
                           transition:all .2s;margin-top:8px;opacity:.5;" disabled>
                            <span class="material-icons" style="font-size:18px;">send</span>
                            <?= $hasMyReview ? 'Обновить оценку' : 'Отправить оценку' ?>
                        </button>
                    </form>
                </div>
            <?php elseif ($expertId && $game['moderation_status'] !== 'pending'): ?>
                <div style="padding:20px;background:rgba(107,122,153,.08);border:1px solid var(--border);
                border-radius:12px;text-align:center;color:var(--muted);font-size:.88rem;margin-bottom:24px;">
                    Голосование завершено. Оценки больше не принимаются.
                </div>
            <?php endif; ?>

            <!-- РЕЦЕНЗИИ -->
            <?php if ($reviews): ?>
                <div style="margin-bottom:24px;">
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:14px;">
                        Рецензии (<?= count($reviews) ?>)
                    </div>
                    <?php foreach ($reviews as $r):
                        $isMe = ($expertId && $r['eid'] == $expertId);
                        $v    = $r['verdict'] ?? ($r['score'] > 51 ? 'recommend' : 'reject');
                        $vMap = [
                            'recommend' => ['👍 Рекомендует',    '#4ade80'],
                            'revision'  => ['🔄 На доработку',  '#fbbf24'],
                            'reject'    => ['👎 Не рекомендует', '#f87171'],
                        ];
                        [$vlbl, $vcol] = $vMap[$v] ?? $vMap['reject'];
                    ?>
                        <div class="review-card" style="<?= $isMe ? 'border:2px solid var(--accent2);background:rgba(34,211,238,.04);' : '' ?>">
                            <?php if ($isMe): ?>
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                                    <span style="font-size:.75rem;font-weight:700;background:rgba(34,211,238,.15);color:var(--accent2);padding:3px 10px;border-radius:4px;">★ ВАШ ОТЗЫВ</span>
                                    <a href="#review-form-section" style="font-size:.75rem;color:var(--accent2);text-decoration:none;">изменить ↑</a>
                                </div>
                            <?php endif; ?>
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                <div style="font-size:1.3rem;font-weight:800;color:<?= $vcol ?>;font-family:monospace;"><?= $r['score'] ?></div>
                                <div style="font-size:.82rem;color:<?= $vcol ?>;font-weight:600;"><?= $vlbl ?></div>
                            </div>
                            <?php if ($isMe || $game['moderation_status'] !== 'pending'): ?>
                                <div style="font-size:.9rem;color:<?= $isMe ? 'var(--text)' : 'var(--muted)' ?>;line-height:1.6;
                        font-style:italic;border-left:3px solid var(--border);padding-left:12px;">
                                    "<?= htmlspecialchars($r['review']) ?>"
                                </div>
                            <?php else: ?>
                                <div style="font-size:.82rem;color:var(--muted);font-style:italic;">🔒 Рецензия скрыта до завершения голосования</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
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
        document.addEventListener('keydown', e => {
            if (!document.getElementById('lightbox').classList.contains('open')) return;
            if (e.key === 'Escape') closeLightboxBtn();
            if (e.key === 'ArrowRight') lightboxNav(1);
            if (e.key === 'ArrowLeft') lightboxNav(-1);
        });

        // Чеклист
        document.querySelectorAll('.cl-row').forEach(row => {
            const inp = row.querySelector('.cl-inp'),
                box = row.querySelector('.cl-box'),
                mark = row.querySelector('.cl-mark');
            const sync = () => {
                box.style.background = inp.checked ? 'rgba(74,222,128,.15)' : 'transparent';
                box.style.borderColor = inp.checked ? 'var(--accent)' : 'var(--border)';
                mark.style.display = inp.checked ? 'block' : 'none';
                const n = document.querySelectorAll('.cl-inp:checked').length;
                const ctr = document.getElementById('cl-counter');
                ctr.textContent = n + ' / 10';
                ctr.style.color = n === 10 ? '#4ade80' : (n >= 7 ? '#fbbf24' : '#c32178');
                ctr.style.background = n === 10 ? 'rgba(74,222,128,.12)' : 'rgba(195,33,120,.15)';
            };
            row.addEventListener('click', () => {
                inp.checked = !inp.checked;
                sync();
            });
            if (inp.checked) sync();
        });

        // Слайдер
        const track = document.getElementById('sl-track'),
            fill = document.getElementById('sl-fill'),
            thumb = document.getElementById('sl-thumb'),
            disp = document.getElementById('score-display'),
            hidden = document.getElementById('score-value');
        let dragging = false,
            scoreSet = <?= $hasMyReview ? 'true' : 'false' ?>;

        function scoreColor(v) {
            if (v <= 25) return '#f87171';
            if (v <= 51) return '#fbbf24';
            if (v <= 75) return '#a3e635';
            return '#4ade80';
        }

        function setScore(v) {
            v = Math.max(0, Math.min(100, Math.round(v)));
            scoreSet = true;
            hidden.value = v;
            const p = v / 100;
            fill.style.width = (p * 100) + '%';
            fill.style.background = scoreColor(v);
            thumb.style.left = (p * track.offsetWidth) + 'px';
            thumb.style.background = scoreColor(v);
            thumb.style.display = 'block';
            disp.textContent = v;
            disp.style.color = scoreColor(v);
            const hints = [
                [0, 'Игра не работает или содержит критические проблемы.'],
                [26, 'Значительные проблемы, мешающие игровому опыту.'],
                [51, 'Граница — ниже этого значения голос считается «против».'],
                [66, 'Хорошая игра, заслуживает публикации.'],
                [81, 'Выдающееся качество для инди-проекта.']
            ];
            let ht = hints[0][1];
            for (const [thr, txt] of hints)
                if (v >= thr) ht = txt;
            const hint = document.getElementById('score-hint');
            const c = scoreColor(v);
            hint.style.cssText = `background:${c}18;border:1px solid ${c}44;color:${c};display:block;padding:9px 14px;border-radius:8px;font-size:.83rem;font-weight:500;margin-top:10px;`;
            hint.textContent = ht;
            document.getElementById('score-err').style.display = 'none';
            checkReady();
        }

        function posFromEvent(e) {
            const rect = track.getBoundingClientRect();
            const cx = e.touches ? e.touches[0].clientX : e.clientX;
            return Math.max(0, Math.min(1, (cx - rect.left) / rect.width));
        }
        track.addEventListener('mousedown', e => {
            dragging = true;
            setScore(Math.round(posFromEvent(e) * 100));
        });
        track.addEventListener('touchstart', e => {
            dragging = true;
            setScore(Math.round(posFromEvent(e) * 100));
        }, {
            passive: true
        });
        document.addEventListener('mousemove', e => {
            if (dragging) setScore(Math.round(posFromEvent(e) * 100));
        });
        document.addEventListener('touchmove', e => {
            if (dragging) setScore(Math.round(posFromEvent(e) * 100));
        }, {
            passive: true
        });
        document.addEventListener('mouseup', () => dragging = false);
        document.addEventListener('touchend', () => dragging = false);
        <?php if ($hasMyReview): ?>setTimeout(() => setScore(<?= (int)$myReview['score'] ?>), 30);
        <?php endif; ?>

        // Вердикт
        const VCOLORS = {
            recommend: {
                border: '#4ade80',
                bg: 'rgba(74,222,128,.1)',
                color: '#4ade80'
            },
            revision: {
                border: '#fbbf24',
                bg: 'rgba(251,191,36,.1)',
                color: '#fbbf24'
            },
            reject: {
                border: '#f87171',
                bg: 'rgba(248,113,113,.1)',
                color: '#f87171'
            }
        };

        function setVerdict(v) {
            document.getElementById('verdict-value').value = v;
            document.querySelectorAll('.vbtn').forEach(btn => {
                const bv = btn.dataset.v;
                if (bv === v) {
                    btn.style.borderColor = VCOLORS[bv].border;
                    btn.style.background = VCOLORS[bv].bg;
                    btn.style.color = VCOLORS[bv].color;
                } else {
                    btn.style.borderColor = 'var(--border)';
                    btn.style.background = 'transparent';
                    btn.style.color = 'var(--muted)';
                }
            });
            document.getElementById('verdict-err').style.display = 'none';
            checkReady();
        }

        // Кнопка
        function checkReady() {
            const s = scoreSet && hidden.value !== '';
            const v = document.getElementById('verdict-value').value !== '';
            const t = (document.getElementById('review-text')?.value.length ?? 0) >= 40;
            document.getElementById('char-count').textContent = (document.getElementById('review-text')?.value.length ?? 0) + ' симв.';
            const ok = s && v && t;
            const btn = document.getElementById('submit-btn');
            if (!btn) return;
            btn.disabled = !ok;
            btn.style.opacity = ok ? '1' : '.5';
            btn.style.background = ok ? 'var(--accent)' : 'var(--muted)';
            btn.style.cursor = ok ? 'pointer' : 'not-allowed';
        }
        document.getElementById('review-text')?.addEventListener('input', checkReady);
        document.getElementById('review-form')?.addEventListener('submit', e => {
            let ok = true;
            if (!hidden.value) {
                document.getElementById('score-err').style.display = 'block';
                ok = false;
            }
            if (!document.getElementById('verdict-value').value) {
                document.getElementById('verdict-err').style.display = 'block';
                ok = false;
            }
            if (!ok) {
                e.preventDefault();
                document.getElementById('score-err').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
        checkReady();
    </script>
</body>

</html>