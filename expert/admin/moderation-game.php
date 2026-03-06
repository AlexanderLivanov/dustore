<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$isExpert = (bool) $stmt->fetch();

if(!$isExpert){
    die('Доступ запрещён');
}

$gameId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$gameId) die('Игра не найдена');

$stmt = $pdo->prepare("SELECT * FROM games WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die('Игра не найдена');

$stmt = $pdo->prepare("
    SELECT r.*, u.username, e.rating AS expert_weight
    FROM reviews r
    JOIN experts e ON r.expert_id = e.id
    JOIN users u ON e.user_id = u.id
    WHERE r.game_id=?
    ORDER BY r.created_at DESC
");
$stmt->execute([$gameId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avgBugs = $avgGameplay = $avgGraphics = 0;
if ($reviews) {
    foreach ($reviews as $r) {
        $avgBugs     += $r['bugs'];
        $avgGameplay += $r['gameplay'];
        $avgGraphics += $r['graphics'];
    }
    $avgBugs     /= count($reviews);
    $avgGameplay /= count($reviews);
    $avgGraphics /= count($reviews);
}

$stmt = $pdo->prepare("SELECT id, name FROM games WHERE genre=? AND id<>? LIMIT 3");
$stmt->execute([$game['genre'], $gameId]);
$similarGames = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expertId = $_SESSION['USERDATA']['id'] ?? null;

$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'");
$pendingExperts = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM games WHERE status='pending'");
$pendingGames = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'");
$totalExperts = $stmt->fetchColumn();
$progress = $totalExperts ? round(count($reviews) / max($totalExperts * 0.51, 1) * 100) : 0;
$progress = min($progress, 100);
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
                        <img src="<?= htmlspecialchars($game['path_to_cover']) ?>" alt="Cover">
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
                        <div class="val"><?= htmlspecialchars($game['developer']) ?></div>
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
                </div>
                <?php if (!empty($game['short_description'])): ?>
                    <div class="game-desc"><?= htmlspecialchars($game['short_description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($game['trailer_url'])): ?>
                    <div style="padding:0 24px 20px;">
                        <a href="<?= htmlspecialchars($game['trailer_url']) ?>" target="_blank"
                            style="color:var(--accent2);font-size:.88rem;text-decoration:none;">▶ Смотреть трейлер →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Progress -->
            <div class="progress-section">
                <div class="progress-header">
                    <h3>Прогресс голосования</h3>
                    <span><?= count($reviews) ?> из <?= ceil($totalExperts * 0.51) ?> голосов (51%)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $progress ?>%"></div>
                </div>
                <?php if ($reviews): ?>
                    <div class="score-grid">
                        <div class="score-item">
                            <div class="s-lbl">Баги</div>
                            <div class="s-val s-bugs"><?= round($avgBugs, 1) ?></div>
                        </div>
                        <div class="score-item">
                            <div class="s-lbl">Геймплей</div>
                            <div class="s-val s-play"><?= round($avgGameplay, 1) ?></div>
                        </div>
                        <div class="score-item">
                            <div class="s-lbl">Графика</div>
                            <div class="s-val s-gfx"><?= round($avgGraphics, 1) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:.9rem">Рецензий пока нет</p>
                <?php endif; ?>
            </div>

            <!-- Review Form -->
            <?php if ($expertId && $game['status'] != 'published'): ?>
                <div class="form-section">
                    <div class="section-title">Оставить оценку</div>
                    <form method="post" action="submit-review?id=<?= $gameId ?>">
                        <div class="score-inputs">
                            <div class="field">
                                <label>Оценка (0–10)</label>
                                <input type="number" name="score" min="0" max="10" required placeholder="0">
                            </div>
                            <div class="field">
                                <label>Баги</label>
                                <input type="number" name="bugs" min="0" max="10" required placeholder="0">
                            </div>
                            <div class="field">
                                <label>Геймплей</label>
                                <input type="number" name="gameplay" min="0" max="10" required placeholder="0">
                            </div>
                            <div class="field">
                                <label>Графика</label>
                                <input type="number" name="graphics" min="0" max="10" required placeholder="0">
                            </div>
                        </div>
                        <div class="field">
                            <label>Анонимная рецензия</label>
                            <textarea name="review" required placeholder="Опишите своё мнение об игре..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Отправить оценку →</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Reviews -->
            <?php if ($reviews): ?>
                <div class="reviews-section">
                    <div class="section-title" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;margin-bottom:16px;">Рецензии (<?= count($reviews) ?>)</div>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-card">
                            <div class="review-scores">
                                <div class="rs">Оценка: <strong><?= $r['score'] ?></strong></div>
                                <div class="rs">Баги: <strong><?= $r['bugs'] ?></strong></div>
                                <div class="rs">Геймплей: <strong><?= $r['gameplay'] ?></strong></div>
                                <div class="rs">Графика: <strong><?= $r['graphics'] ?></strong></div>
                            </div>
                            <?php if ($game['status'] == 'published'): ?>
                                <div class="review-text">"<?= htmlspecialchars($r['review']) ?>"</div>
                            <?php else: ?>
                                <div style="font-size:.82rem;color:var(--muted);font-style:italic">Рецензии анонимны до публикации игры</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Similar -->
            <?php if ($similarGames): ?>
                <div>
                    <div class="section-title" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:12px;color:var(--muted)">Похожие игры</div>
                    <div class="similar-list">
                        <?php foreach ($similarGames as $sg): ?>
                            <a href="moderation-game?id=<?= $sg['id'] ?>" class="similar-link"><?= htmlspecialchars($sg['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

</body>

</html>