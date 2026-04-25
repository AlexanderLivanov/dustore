<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$isExpert = (bool) $stmt->fetch();

if (!$isExpert) {
    die('Доступ запрещён');
}

// получаем общее количество модеров
$totalExperts = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();

// игры
$stmt = $pdo->query("
        SELECT 
        g.id,
        g.name,
        g.developer,
        g.created_at,
        g.genre,
        g.platforms,
        g.status,
        g.moderation_status,
        g.GQI,

        COUNT(mr.id) as votes,
        SUM(CASE WHEN mr.score > 51 THEN 1 ELSE 0 END) as positive_votes

    FROM games g
    LEFT JOIN moderation_reviews mr ON mr.game_id = g.id

    WHERE g.moderation_status = 'pending'

    GROUP BY g.id
    ORDER BY g.created_at DESC
");

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);


// счетчики
$pendingExperts = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames = $pdo->query("SELECT COUNT(*) FROM games WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Модерация игр — Dustore</title>
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
        }

        .page-header {
            margin-bottom: 32px;
        }

        .eyebrow {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 8px;
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
        }

        /* Search bar */
        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .search-box {
            flex: 1;
            max-width: 360px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 16px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            transition: border-color .2s;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--accent);
        }

        .search-box::placeholder {
            color: var(--muted);
        }

        /* Game cards grid */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .game-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            text-decoration: none;
            color: var(--text);
            display: flex;
            flex-direction: column;
            transition: all .2s;
            animation: fadeUp .3s ease both;
        }

        .game-card:hover {
            border-color: rgba(74, 222, 128, .3);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .3);
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .game-cover {
            height: 120px;
            background: var(--surface2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border-bottom: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .game-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .game-body {
            padding: 18px;
            flex: 1;
        }

        .game-name {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 4px;
        }

        .game-dev {
            font-size: .82rem;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .game-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }

        .tag {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: .72rem;
            font-weight: 600;
            color: var(--muted);
        }

        .game-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .review-count {
            font-size: .8rem;
            color: var(--muted);
        }

        .gqi-badge {
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .25);
            border-radius: 6px;
            padding: 3px 10px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .82rem;
        }

        .open-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(74, 222, 128, .1);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .25);
            border-radius: 8px;
            padding: 6px 14px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
            margin-top: 12px;
            width: 100%;
            justify-content: center;
        }

        .open-btn:hover {
            background: var(--accent);
            color: #0b0e13;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--muted);
            grid-column: 1/-1;
        }

        .empty-state .ei {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .empty-state h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem;
            color: var(--text);
            margin-bottom: 8px;
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
            <div class="page-header">
                <div class="eyebrow">Модерация</div>
                <h1>Игры на проверке</h1>
            </div>

            <div class="toolbar">
                <input class="search-box" type="text" placeholder="🔍  Поиск по названию или студии..." oninput="filterGames(this.value)">
            </div>

            <div class="games-grid" id="games-grid">
                <?php if (empty($games)): ?>
                    <div class="empty-state">
                        <div class="ei">🎉</div>
                        <h2>Нет игр для проверки</h2>
                        <p>Все игры прошли модерацию</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($games as $i => $g): ?>
                    <div class="game-card" data-search="<?= strtolower(htmlspecialchars($g['name'] . ' ' . $g['developer'])) ?>" style="animation-delay:<?= $i * 0.04 ?>s">
                        <div class="game-cover">🎮</div>
                        <div class="game-body">
                            <div class="game-name"><?= htmlspecialchars($g['name']) ?></div>
                            <div class="game-dev"><?= htmlspecialchars($g['developer']) ?></div>
                            <div class="game-tags">
                                <?php if ($g['genre']): ?><span class="tag"><?= htmlspecialchars($g['genre']) ?></span><?php endif; ?>
                                <?php if ($g['platforms']): ?><span class="tag"><?= htmlspecialchars($g['platforms']) ?></span><?php endif; ?>
                            </div>
                            <div class="game-footer">
                                <span class="review-count">👥 <?= $g['votes'] ?? 0 ?> рецензий</span>
                                <?php if ($g['GQI']): ?>
                                    <span class="gqi-badge">GQI <?= htmlspecialchars($g['GQI'] ?? '—') ?></span>
                                <?php else: ?>
                                    <span style="font-size:.8rem;color:var(--muted)">GQI —</span>
                                <?php endif; ?>
                            </div>
                            <a href="moderation-game?id=<?= $g['id'] ?>" class="open-btn">Открыть →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        function filterGames(q) {
            q = q.toLowerCase();
            document.querySelectorAll('#games-grid .game-card').forEach(card => {
                card.style.display = card.dataset.search.includes(q) ? '' : 'none';
            });
        }
    </script>
</body>

</html>