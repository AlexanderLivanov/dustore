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
    SELECT 
        g.*,
        s.name AS studio_name,
        s.tiker,
        s.avatar_link,
        s.website AS studio_website,
        s.country,
        s.city,
        s.team_size,
        s.specialization
    FROM games g
    LEFT JOIN studios s ON g.developer = s.id
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die('Игра не найдена');

$features = json_decode($game['features'] ?? '[]', true);
$screenshots = json_decode($game['screenshots'] ?? '[]', true);
$requirements = json_decode($game['requirements'] ?? '[]', true);

$stmt = $pdo->prepare("
    SELECT mr.id, mr.score, mr.comment AS review,
           mr.score AS bugs, mr.score AS gameplay, mr.score AS graphics,
           u.username, e.rating AS expert_weight
    FROM moderation_reviews mr
    JOIN experts e ON e.id = mr.expert_id
    JOIN users u ON u.id = e.user_id
    WHERE mr.game_id=?
");
$stmt->execute([$gameId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id=? AND status='approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$expert = $stmt->fetch();

if (!$expert) die('no access');

$expertId = $expert['id'];

// Моя рецензия (если уже оставлял)
$stmt = $pdo->prepare("SELECT score, comment FROM moderation_reviews WHERE game_id=? AND expert_id=?");
$stmt->execute([$gameId, $expertId]);
$myReview = $stmt->fetch(PDO::FETCH_ASSOC);
$hasMyReview = (bool)$myReview;

$avgBugs = $avgGameplay = $avgGraphics = 0;
if ($reviews) {
    foreach ($reviews as $r) {
        $avgBugs += $r['bugs'];
        $avgGameplay += $r['gameplay'];
        $avgGraphics += $r['graphics'];
    }
    $avgBugs /= count($reviews);
    $avgGameplay /= count($reviews);
    $avgGraphics /= count($reviews);
}

$totalExperts = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
// $progress = $totalExperts ? round(count($reviews) / max($totalExperts * 0.51, 1) * 100) : 0;
// $progress = min($progress, 100);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT expert_id) 
    FROM moderation_reviews 
    WHERE game_id=?
");
$stmt->execute([$gameId]);
$reviewCount = $stmt->fetchColumn();

$needVotes = max(1, ceil($totalExperts * 0.51));
$progress = min(100, round($reviewCount / $needVotes * 100));

$pendingExperts = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames = $pdo->query("SELECT COUNT(*) FROM games WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['name']) ?> — Dustore</title>
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

        .user-name {
            font-size: .85rem;
            font-weight: 500;
        }

        .user-role {
            font-size: .72rem;
            color: var(--muted);
        }

        main {
            flex: 1;
            overflow: auto;
        }

        .main-inner {
            padding: 40px;
            max-width: 900px;
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

        /* Game header */
        .game-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .game-cover-area {
            height: 180px;
            background: linear-gradient(135deg, #131720, #1a2030);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .game-cover-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-overlay {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(11, 14, 19, .8);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 14px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .8rem;
        }

        .game-meta {
            padding: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        @media(max-width:700px) {
            .game-meta {
                grid-template-columns: 1fr;
            }
        }

        .meta-item .lbl {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .meta-item .val {
            font-size: .95rem;
            color: var(--text);
        }

        .game-desc {
            padding: 0 24px 24px;
            color: var(--muted);
            font-size: .9rem;
            line-height: 1.6;
        }

        /* Progress */
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
            margin-bottom: 16px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 4px;
            transition: width .8s ease;
        }

        .score-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .score-item {
            background: var(--surface2);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .score-item .s-lbl {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .score-item .s-val {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
        }

        .s-bugs {
            color: #f87171;
        }

        .s-play {
            color: var(--accent2);
        }

        .s-gfx {
            color: var(--warning);
        }

        /* Review form */
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

        .score-inputs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        @media(max-width:600px) {
            .score-inputs {
                grid-template-columns: 1fr 1fr;
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }

        .field label {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field input,
        .field textarea {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            transition: border-color .2s;
        }

        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .field textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn-submit {
            background: var(--accent);
            color: #0b0e13;
            border: none;
            border-radius: 10px;
            padding: 13px 28px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            transition: all .2s;
            width: 100%;
        }

        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: var(--surface2);
            outline: none;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            border: 3px solid var(--bg);
            box-shadow: 0 0 0 2px var(--accent);
        }

        input[type=range]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            border: 3px solid var(--bg);
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%236b7a99'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px !important;
        }

        .verdict-btn {
            user-select: none;
        }

        .form-section textarea {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            width: 100%;
            transition: border-color .2s;
            resize: vertical;
        }

        .form-section textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .btn-submit:hover {
            background: #22c55e;
            transform: translateY(-1px);
        }

        /* Reviews */
        .reviews-section {
            margin-bottom: 24px;
        }

        .review-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 12px;
        }

        .review-scores {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .rs {
            font-size: .82rem;
            color: var(--muted);
        }

        .rs strong {
            color: var(--text);
        }

        .review-text {
            font-size: .9rem;
            color: var(--muted);
            line-height: 1.6;
            font-style: italic;
        }

        /* Similar */
        .similar-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .similar-link {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text);
            font-size: .88rem;
            font-weight: 500;
            transition: all .2s;
        }

        .similar-link:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .block {
            background: #131720;
            border: 1px solid #232b3a;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px
        }

        h2 {
            margin-bottom: 10px
        }

        img {
            max-width: 100%;
            border-radius: 8px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px
        }
    </style>
</head>

<body>

    <aside>
        <div class="logo">Dustore <span>Admin Panel</span></div>
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
                    <div class="user-name"><?= htmlspecialchars($_SESSION['USERDATA']['username']) ?></div>
                    <div class="user-role"><?= $_SESSION['USERDATA']['global_role'] == -1 ? 'Администратор' : 'Модератор' ?></div>
                </div>
            </div>
            <a href="logout" style="color:var(--danger);margin-top:8px;padding:8px 12px;font-size:.85rem;">Выйти →</a>
        </div>
    </aside>

    <main>
        <div class="main-inner">
            <a href="moderation" class="back-link">← Назад к списку</a>

            <!-- Game Header -->
            <div class="game-header">
                <div class="game-cover-area">
                    <?php if (!empty($game['path_to_cover'])): ?>
                        <img onclick="window.open('<?= htmlspecialchars($game['path_to_cover']) ?>', '_blank')" style="cursor: pointer;" src="<?= htmlspecialchars($game['path_to_cover']) ?>" alt="Cover">
                    <?php else: ?>
                        🎮
                    <?php endif; ?>
                    <div class="status-overlay"><?= htmlspecialchars($game['status']) ?></div>
                </div>
                <div class="game-meta">
                    <div class="meta-item">
                        <div class="lbl">Название</div>
                        <div class="val"><?= htmlspecialchars($game['name']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">Студия</div>
                        <div class="val">
                            <?= htmlspecialchars($game['studio_name'] ?? '—') ?>
                            <?php if (!empty($game['tiker'])): ?>
                                (<?= htmlspecialchars($game['tiker']) ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">Жанр</div>
                        <div class="val"><?= htmlspecialchars($game['genre'] ?? '—') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">Платформы</div>
                        <div class="val"><?= htmlspecialchars($game['platforms'] ?? '—') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">Дата выхода</div>
                        <div class="val"><?= htmlspecialchars($game['release_date'] ?? '—') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">GQI</div>
                        <div class="val" style="font-family:'Syne',sans-serif;font-weight:800;color:var(--accent)"><?= $game['GQI'] ?? '—' ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">
                            Возрастной рейтинг
                        </div>
                        <div class="val">
                            <?= $game['age_rating'] ?>+
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">
                            Языки
                        </div>
                        <div class="val">
                            <?= $game['languages'] ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">
                            Цена
                        </div>
                        <div class="val">
                            <?= $game['price'] ?: 'Бесплатно' ?>₽
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="lbl">
                            GQI (Game Quality Index)
                        </div>
                        <div class="val">
                            <?= $game['GQI'] ?> из 100
                        </div>
                    </div>

                </div>
                <?php if (!empty($game['studio_name'])): ?>
                    <div style="padding:24px;border-top:1px solid var(--border);">
                        <div class="section-title">Студия</div>

                        <div style="display:flex;gap:16px;align-items:center;">
                            <?php if (!empty($game['studio_avatar'])): ?>
                                <img src="<?= htmlspecialchars($game['studio_avatar']) ?>"
                                    style="width:60px;height:60px;border-radius:12px;object-fit:cover;">
                            <?php endif; ?>

                            <div>
                                <div style="font-weight:700;">
                                    <?= htmlspecialchars($game['studio_name']) ?>
                                    <?php if (!empty($game['tiker'])): ?>
                                        (<?= htmlspecialchars($game['tiker']) ?>)
                                    <?php endif; ?>
                                </div>

                                <div style="font-size:.85rem;color:var(--muted);">
                                    <?= htmlspecialchars(($game['country'] ?? '') . ' ' . ($game['city'] ?? '')) ?>
                                </div>

                                <?php if (!empty($game['team_size'])): ?>
                                    <div style="font-size:.8rem;color:var(--muted);">
                                        Команда: <?= $game['team_size'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($game['specialization'])): ?>
                            <div style="margin-top:10px;font-size:.85rem;color:var(--muted);">
                                <?= htmlspecialchars($game['specialization']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($game['studio_website'])): ?>
                            <div style="margin-top:8px;">
                                <a href="<?= htmlspecialchars($game['studio_website']) ?>" target="_blank" style="color:var(--accent2)">
                                    Сайт студии →
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($game['trailer_url'])): ?>
                    <div style="padding:0 24px 20px;">
                        <a href="<?= htmlspecialchars($game['trailer_url']) ?>" target="_blank"
                            style="color:var(--accent2);font-size:.88rem;text-decoration:none;">▶ Смотреть трейлер →</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($screenshots)): ?>
                    <div style="padding:24px;">
                        <div class="section-title">Скриншоты</div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <?php foreach ($screenshots as $s): ?>
                                <img src="<?= htmlspecialchars($s['path'] ?? '') ?>"
                                    style="width:160px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($game['short_description'])): ?>
                    <div class="game-desc"><?= htmlspecialchars($game['short_description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($game['full_description'])): ?>
                    <div class="game-desc" style="border-top:1px solid var(--border);margin-top:10px;padding-top:20px;">
                        <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:10px;color:var(--text)">
                            Полное описание
                        </div>
                        <?= nl2br(htmlspecialchars($game['full_description'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="block">
                <h2>Полное описание</h2>
                <p><?= nl2br(htmlspecialchars($game['description'])) ?></p>
            </div>

            <?php if ($features): ?>
                <div class="block">
                    <h2>Фичи</h2>
                    <ul>
                        <?php foreach ($features as $f): ?>
                            <li><?= htmlspecialchars($f['title'] ?? '') ?> — <?= htmlspecialchars($f['desc'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($screenshots): ?>
                <div class="block">
                    <h2>Скриншоты</h2>
                    <div class="grid">
                        <?php foreach ($screenshots as $s): ?>
                            <img src="<?= htmlspecialchars($s['path'] ?? '') ?>">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($requirements): ?>
                <div class="block">
                    <h2>Системные требования</h2>
                    <ul>
                        <?php foreach ($requirements as $r): ?>
                            <li><?= htmlspecialchars($r['label'] ?? '') ?> — <?= htmlspecialchars($r['value'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>


            <div class="block">
                <h2>Прогресс модерации</h2>
                <p><?= count($reviews) ?> / <?= ceil($totalExperts * 0.51) ?></p>
                <p><?= $progress ?>%</p>
            </div>

            <div class="block">
                <h2>Оценки</h2>
                <p>Баги: <?= round($avgBugs, 1) ?></p>
                <p>Геймплей: <?= round($avgGameplay, 1) ?></p>
                <p>Графика: <?= round($avgGraphics, 1) ?></p>
            </div>

            <!-- Progress -->
            <div class="progress-section">
                <div class="progress-header">
                    <h3>Прогресс голосования</h3>
                    <span><?= $reviewCount ?> из <?= $needVotes ?> голосов (<?= $progress ?>%)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $progress ?>%"></div>
                </div>

            </div>

            <!-- ═══ ФОРМА ОЦЕНКИ ═══ -->
            <?php if ($expertId && $game['moderation_status'] === 'pending'): ?>
                <div class="form-section" id="review-form-section">
                    <div class="section-title" style="display:flex;align-items:center;gap:10px;">
                        <?= $hasMyReview ? '✏️ Ваша оценка (можно изменить)' : '⭐ Оценить игру' ?>
                        <?php if ($hasMyReview): ?>
                            <span style="font-size:.75rem;font-weight:400;color:var(--muted);margin-left:4px;">
                                · оценка учтена в голосовании
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="submit-moderation?id=<?= $gameId ?>">

                        <!-- Слайдер оценки -->
                        <div style="margin-bottom:24px;">
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

                            <input type="range" name="score" id="score-slider"
                                min="0" max="100" step="1"
                                value="<?= $hasMyReview ? $myReview['score'] : 50 ?>"
                                style="width:100%;accent-color:var(--accent);cursor:pointer;height:6px;"
                                oninput="updateScore(this.value)">

                            <!-- Метки шкалы -->
                            <div style="display:flex;justify-content:space-between;margin-top:8px;">
                                <?php foreach (
                                    [
                                        [0,  '0',   '#f87171', 'Ужасно'],
                                        [25, '25',  '#fb923c', 'Плохо'],
                                        [51, '51',  '#fbbf24', 'Проходимо'],
                                        [75, '75',  '#a3e635', 'Хорошо'],
                                        [100, '100', '#4ade80', 'Отлично'],
                                    ] as [$val, $num, $clr, $lbl]
                                ): ?>
                                    <div style="text-align:center;cursor:pointer;" onclick="setScore(<?= $val ?>)">
                                        <div style="font-size:.7rem;font-weight:700;color:<?= $clr ?>;"><?= $num ?></div>
                                        <div style="font-size:.65rem;color:var(--muted);"><?= $lbl ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Динамическая подсказка -->
                            <div id="score-hint" style="margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;font-weight:500;transition:all .2s;"></div>
                        </div>

                        <!-- Критерии -->
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
                            <?php foreach (
                                [
                                    ['gameplay_score', 'Геймплей',  'Насколько интересно играть? Механики, баланс, реиграбельность.'],
                                    ['visual_score',   'Визуал',    'Графика, арт-стиль, UI — насколько приятно смотреть.'],
                                    ['stability',      'Стабильность', 'Баги, вылеты, производительность.'],
                                ] as [$fname, $flabel, $fdesc]
                            ): ?>
                                <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:14px;">
                                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;"><?= $flabel ?></div>
                                    <select name="<?= $fname ?>"
                                        style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:7px 10px;color:var(--text);font-size:.85rem;cursor:pointer;outline:none;">
                                        <?php foreach ([0 => '—', 1 => '1 · Очень плохо', 2 => '2 · Плохо', 3 => '3 · Ниже среднего', 4 => '4 · Среднее', 5 => '5 · Выше среднего', 6 => '6 · Хорошо', 7 => '7 · Очень хорошо', 8 => '8 · Отлично', 9 => '9 · Превосходно', 10 => '10 · Шедевр'] as $v => $l): ?>
                                            <option value="<?= $v ?>"><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div style="font-size:.7rem;color:var(--muted);margin-top:6px;line-height:1.4;"><?= $fdesc ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Текст рецензии -->
                        <div class="field">
                            <label>
                                Рецензия
                                <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);">
                                    · анонимна до публикации игры
                                </span>
                            </label>
                            <textarea name="review" rows="5" required
                                placeholder="Опишите впечатления от игры. Что понравилось? Что стоит улучшить? Порекомендуете ли другим игрокам?"
                                style="min-height:120px;"><?= htmlspecialchars($myReview['comment'] ?? '') ?></textarea>
                            <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">
                                Минимум 20 символов · рецензия станет видна разработчику сразу
                            </div>
                        </div>

                        <!-- Вердикт -->
                        <div style="display:flex;gap:10px;margin-bottom:20px;">
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="verdict" value="recommend" style="display:none;" id="v-yes"
                                    <?= (!$hasMyReview || $myReview['score'] > 51) ? 'checked' : '' ?>>
                                <div class="verdict-btn" data-for="v-yes"
                                    style="padding:12px;border-radius:10px;border:2px solid;text-align:center;font-weight:600;font-size:.9rem;transition:all .2s;
                                        border-color:<?= (!$hasMyReview || $myReview['score'] > 51) ? 'var(--accent)' : 'var(--border)' ?>;
                                        background:<?= (!$hasMyReview || $myReview['score'] > 51) ? 'rgba(74,222,128,.1)' : 'transparent' ?>;
                                        color:<?= (!$hasMyReview || $myReview['score'] > 51) ? 'var(--accent)' : 'var(--muted)' ?>;">
                                    👍 Рекомендую публикацию
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;">
                                <input type="radio" name="verdict" value="reject" style="display:none;" id="v-no"
                                    <?= ($hasMyReview && $myReview['score'] <= 51) ? 'checked' : '' ?>>
                                <div class="verdict-btn" data-for="v-no"
                                    style="padding:12px;border-radius:10px;border:2px solid;text-align:center;font-weight:600;font-size:.9rem;transition:all .2s;
                                        border-color:<?= ($hasMyReview && $myReview['score'] <= 51) ? '#f87171' : 'var(--border)' ?>;
                                        background:<?= ($hasMyReview && $myReview['score'] <= 51) ? 'rgba(248,113,113,.1)' : 'transparent' ?>;
                                        color:<?= ($hasMyReview && $myReview['score'] <= 51) ? '#f87171' : 'var(--muted)' ?>;">
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
                <div class="reviews-section">
                    <div class="section-title" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;margin-bottom:16px;">
                        Рецензии (<?= count($reviews) ?>)
                    </div>
                    <?php foreach ($reviews as $r):
                        $isMe = ($r['expert_weight'] && $myReview && (int)$r['score'] === (int)$myReview['score']);
                        // Точнее определяем свою - сравниваем username
                        $isMe = ($r['username'] === $_SESSION['USERDATA']['username']);
                        $positive = $r['score'] > 51;
                    ?>
                        <div class="review-card" style="<?= $isMe ? 'border:2px solid var(--accent2);background:rgba(34,211,238,.05);' : '' ?>">
                            <?php if ($isMe): ?>
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                                    <span style="font-size:.75rem;font-weight:700;background:rgba(34,211,238,.15);color:var(--accent2);padding:3px 10px;border-radius:4px;letter-spacing:.5px;">
                                        ★ ВАШ ОТЗЫВ
                                    </span>
                                    <a href="#review-form-section" style="font-size:.75rem;color:var(--accent2);text-decoration:none;">изменить ↑</a>
                                </div>
                            <?php endif; ?>

                            <div class="review-scores">
                                <div class="rs">
                                    Оценка: <strong style="color:<?= $positive ? 'var(--accent)' : '#f87171' ?>;font-size:1.1rem;">
                                        <?= $r['score'] ?>/100
                                    </strong>
                                    <span style="font-size:.75rem;margin-left:4px;<?= $positive ? 'color:var(--accent)' : 'color:#f87171' ?>">
                                        <?= $positive ? '👍' : '👎' ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($isMe || $game['moderation_status'] !== 'pending'): ?>
                                <div class="review-text" style="<?= $isMe ? 'color:var(--text);' : '' ?>">
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

            <!-- Similar -->
            <!-- <?php if ($similarGames): ?>
                <div>
                    <div class="section-title" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:12px;color:var(--muted)">Похожие игры</div>
                    <div class="similar-list">
                        <?php foreach ($similarGames as $sg): ?>
                            <a href="moderation-game?id=<?= $sg['id'] ?>" class="similar-link"><?= htmlspecialchars($sg['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?> -->

        </div>
    </main>

    <script>
        const SCORE_HINTS = [
            [0, 15, '#f87171', '😱 Ужасно — игра не соответствует минимальным стандартам качества'],
            [16, 30, '#fb923c', '😞 Плохо — серьёзные проблемы, мешающие игровому опыту'],
            [31, 50, '#fbbf24', '😐 Ниже среднего — есть потенциал, но слишком много недостатков'],
            [51, 51, '#facc15', '⚖️ Граница — ровно на пороге публикации (51 = голос «за»)'],
            [52, 65, '#a3e635', '🙂 Приемлемо — можно публиковать, но есть что улучшить'],
            [66, 80, '#4ade80', '😊 Хорошая игра — заслуживает внимания игроков'],
            [81, 90, '#34d399', '🎮 Отличная — яркий представитель своего жанра'],
            [91, 100, '#22d3ee', '🏆 Шедевр — рекомендуем всем без исключения'],
        ];

        function updateScore(val) {
            val = parseInt(val);
            document.getElementById('score-display').textContent = val;

            // Цвет числа
            const hint = SCORE_HINTS.find(([lo, hi]) => val >= lo && val <= hi);
            const [, , color, text] = hint || [0, 0, '#4ade80', ''];
            document.getElementById('score-display').style.color = color;
            document.getElementById('score-slider').style.setProperty('accent-color', color);

            // Подсказка
            const el = document.getElementById('score-hint');
            el.textContent = text;
            el.style.background = color + '18';
            el.style.color = color;
            el.style.border = '1px solid ' + color + '44';

            // Автовыбор вердикта
            const yes = document.getElementById('v-yes');
            const no = document.getElementById('v-no');
            yes.checked = val > 51;
            no.checked = val <= 51;
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
                    btn.style.background = isYes ? 'rgba(74,222,128,.1)' : 'rgba(248,113,113,.1)';
                    btn.style.color = isYes ? 'var(--accent)' : '#f87171';
                } else {
                    btn.style.borderColor = 'var(--border)';
                    btn.style.background = 'transparent';
                    btn.style.color = 'var(--muted)';
                }
            });
        }

        document.querySelectorAll('input[name=verdict]').forEach(r =>
            r.addEventListener('change', updateVerdictBtns)
        );

        // Инит
        updateScore(document.getElementById('score-slider').value);
    </script>
</body>

</html>