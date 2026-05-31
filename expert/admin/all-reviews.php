<?php
/*
 * expert/admin/all-reviews.php
 * Просмотр всех оценок модерации — для экспертов и администраторов.
 * Эксперты видят агрегированные данные, админы — полные рецензии с авторами.
 */

session_start();
require_once __DIR__ . '/../../swad/config.php';

$db  = new Database();
$pdo = $db->connect();

// Аутентификация
if (empty($_SESSION['USERDATA'])) {
    header('Location: /expert/login');
    exit;
}
$userId      = (int)$_SESSION['USERDATA']['id'];
$globalRole  = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
$isAdmin     = ($globalRole === -1);

// Проверяем, что пользователь — эксперт или админ
$expert = null;
if (!$isAdmin) {
    $st = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
    $st->execute([$userId]);
    $expert = $st->fetch();
    if (!$expert) {
        header('Location: /expert/');
        exit;
    }
}
$expertId = $expert['id'] ?? null;

// Фильтры
$filterStatus = $_GET['status'] ?? 'all'; // all, pending, approved, rejected, revision
$filterGame   = (int)($_GET['game_id'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// Статусы
$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'revision'];
if (!in_array($filterStatus, $allowedStatuses)) $filterStatus = 'all';

// Счётчики в сайдбаре
$pendingExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames   = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();

// Построение WHERE для статуса и игры (используем плейсхолдеры для безопасности)
$whereStatus = '';
$whereParams = [];
if ($filterStatus !== 'all') {
    $whereStatus = "AND g.moderation_status = :status";
    $whereParams[':status'] = $filterStatus;
}
if ($filterGame > 0) {
    $whereStatus .= " AND g.id = :game_id";
    $whereParams[':game_id'] = $filterGame;
}

// Основной запрос (исправлен: убрано WHERE review_count > 0, добавлено HAVING)
$sql = "
    SELECT
        g.id, g.name, g.moderation_status, g.GQI,
        g.path_to_cover, g.updated_at,
        s.name AS studio_name,
        COUNT(DISTINCT mr.id)           AS review_count,
        ROUND(AVG(mr.score))            AS avg_score,
        SUM(mr.score > 51)              AS positive,
        SUM(mr.score <= 51)             AS negative,
        SUM(mr.verdict = 'revision')    AS revision_count
    FROM games g
    LEFT JOIN studios s ON s.id = g.developer
    LEFT JOIN moderation_reviews mr ON mr.game_id = g.id
    WHERE 1=1 $whereStatus
    GROUP BY g.id
    HAVING COUNT(mr.id) > 0 OR g.moderation_status = 'pending'
    ORDER BY g.updated_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($whereParams as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Счётчик для пагинации
$countSql = "
    SELECT COUNT(DISTINCT g.id)
    FROM games g
    LEFT JOIN moderation_reviews mr ON mr.game_id = g.id
    WHERE (mr.id IS NOT NULL OR g.moderation_status = 'pending')
    $whereStatus
";
$countStmt = $pdo->prepare($countSql);
foreach ($whereParams as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$totalGamesRow = $countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalGamesRow / $perPage));

// Детальные рецензии выбранной игры
$selectedGame  = null;
$detailReviews = [];
if ($filterGame > 0) {
    $sg = $pdo->prepare("
        SELECT g.*, s.name AS studio_name
        FROM games g
        LEFT JOIN studios s ON s.id = g.developer
        WHERE g.id = ?
    ");
    $sg->execute([$filterGame]);
    $selectedGame = $sg->fetch(PDO::FETCH_ASSOC);

    // Рецензии — детальные
    $dr = $pdo->prepare("
        SELECT
            mr.*,
            " . ($isAdmin ? "u.username, u.profile_picture," : "'—' AS username, NULL AS profile_picture,") . "
            e.rating AS expert_rating
        FROM moderation_reviews mr
        JOIN experts e ON e.id = mr.expert_id
        " . ($isAdmin ? "JOIN users u ON u.id = e.user_id" : "") . "
        WHERE mr.game_id = ?
        ORDER BY mr.id DESC
    ");
    $dr->execute([$filterGame]);
    $detailReviews = $dr->fetchAll(PDO::FETCH_ASSOC);

    // Агрегат для выбранной игры
    $agg = $pdo->prepare("
        SELECT
            ROUND(AVG(score))           AS avg_score,
            ROUND(AVG(gameplay_score))  AS avg_gameplay,
            ROUND(AVG(visual_score))    AS avg_visual,
            ROUND(AVG(stability))       AS avg_stability,
            ROUND(AVG(originality))     AS avg_originality,
            ROUND(AVG(sound_score))     AS avg_sound,
            ROUND(AVG(content_depth))   AS avg_content,
            COUNT(*)                    AS cnt,
            SUM(score > 51)             AS positive,
            SUM(score <= 51)            AS negative,
            SUM(verdict = 'revision')   AS revision
        FROM moderation_reviews WHERE game_id = ?
    ");
    $agg->execute([$filterGame]);
    $aggData = $agg->fetch(PDO::FETCH_ASSOC);

    $totalExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
    $needVotes    = max(1, (int)ceil($totalExperts * 0.51));
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Все оценки — <?= $isAdmin ? 'Admin' : 'Expert' ?> Panel</title>
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
            --p: #c32178;
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
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: all .2s;
        }

        aside a:hover {
            background: var(--surface2);
            color: var(--text);
        }

        aside a.active {
            background: rgba(74, 222, 128, .1);
            color: var(--accent);
        }

        .badge {
            background: var(--danger);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: auto;
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
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--p);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .9rem;
            color: #fff;
        }

        .user-name {
            font-size: .85rem;
            font-weight: 600;
        }

        .user-role {
            font-size: .72rem;
            color: var(--muted);
        }

        main {
            flex: 1;
            overflow: hidden;
        }

        .main-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 28px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .eyebrow {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--p);
            margin-bottom: 8px;
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
        }

        /* ── Фильтры ── */
        .filters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-tab {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
            transition: all .2s;
            cursor: pointer;
        }

        .filter-tab.active {
            background: rgba(195, 33, 120, .12);
            color: var(--p);
            border-color: rgba(195, 33, 120, .3);
        }

        /* ── Список игр ── */
        .games-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 24px;
        }

        .game-row {
            display: grid;
            grid-template-columns: 48px 1fr auto auto auto;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
            transition: all .2s;
        }

        .game-row:hover {
            border-color: rgba(195, 33, 120, .3);
            transform: translateY(-1px);
        }

        .game-row.selected {
            border-color: var(--p);
            background: rgba(195, 33, 120, .05);
        }

        .game-cover-sm {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--surface2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .game-cover-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .game-name {
            font-weight: 600;
            font-size: .92rem;
            margin-bottom: 2px;
        }

        .game-studio {
            font-size: .78rem;
            color: var(--muted);
        }

        .status-pill {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
            text-align: center;
        }

        .stat-col {
            text-align: center;
        }

        .stat-col .num {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .stat-col .lbl {
            font-size: .7rem;
            color: var(--muted);
        }

        /* ── Детальная панель ── */
        .detail-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .detail-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 16px;
        }

        /* GQI */
        .gqi-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 8px;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
        }

        /* Bars */
        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .bar-label {
            font-size: .8rem;
            color: var(--muted);
            width: 130px;
            flex-shrink: 0;
        }

        .bar-track {
            flex: 1;
            height: 6px;
            background: var(--surface2);
            border-radius: 3px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: 3px;
        }

        .bar-val {
            font-size: .8rem;
            font-weight: 700;
            width: 28px;
            text-align: right;
        }

        /* Рецензия */
        .review-card {
            padding: 16px 18px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .review-verdict-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: .74rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .review-body {
            font-size: .88rem;
            color: var(--muted);
            line-height: 1.7;
        }

        /* Критерии в рецензии */
        .mini-criteria {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .mini-crit {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: .74rem;
            font-weight: 600;
            background: var(--bg);
            border: 1px solid var(--border);
        }

        /* Пагинация */
        .pagination {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
        }

        .pagination a:hover {
            color: var(--text);
        }

        .pagination .current {
            background: rgba(195, 33, 120, .12);
            color: var(--p);
            border-color: rgba(195, 33, 120, .3);
        }

        @media (max-width:900px) {
            body {
                flex-direction: column;
            }

            aside {
                width: 100%;
                min-height: auto;
                height: auto;
                position: static;
            }
        }
    </style>
</head>

<body>

    <aside>
        <div class="logo">
            Dustore
            <span><?= $isAdmin ? 'Admin Panel' : 'Expert Panel' ?></span>
        </div>
        <div class="nav-section">Меню</div>
        <a href="index">🏠 Главная</a>
        <?php if ($isAdmin): ?>
            <a href="expert-requests">
                👤 Заявки экспертов
                <?php if ($pendingExperts > 0): ?><span class="badge"><?= $pendingExperts ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <a href="moderation">
            🎮 Модерация игр
            <?php if ($pendingGames > 0): ?><span class="badge"><?= $pendingGames ?></span><?php endif; ?>
        </a>
        <a href="all-reviews" class="active">📊 Все оценки</a>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['USERDATA']['username'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['USERDATA']['username']) ?></div>
                    <div class="user-role"><?= $isAdmin ? 'Администратор' : 'Эксперт' ?></div>
                </div>
            </div>
            <a href="logout" style="color:var(--danger);margin-top:8px;padding:8px 12px;font-size:.85rem;display:block;">Выйти →</a>
        </div>
    </aside>

    <main>
        <div class="main-inner">
            <div class="page-header">
                <div class="eyebrow">Аналитика</div>
                <h1>Все оценки модерации</h1>
                <?php if (!$isAdmin): ?>
                    <p style="font-size:.85rem;color:var(--muted);margin-top:8px;">
                        Авторы рецензий скрыты — видны только агрегированные данные.
                        <?php if ($expertId): ?>
                            Ваши голоса <span style="color:var(--accent2);">выделены</span>.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:.85rem;color:var(--muted);margin-top:8px;">
                        Вы видите полные рецензии с именами экспертов — это видно только администраторам.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ── Фильтры ── -->
            <div class="filters">
                <?php
                $statuses = ['all' => 'Все', 'pending' => '⏳ На проверке', 'approved' => '✅ Одобрены', 'rejected' => '❌ Отклонены', 'revision' => '🔄 Доработка'];
                foreach ($statuses as $k => $v): ?>
                    <a href="?status=<?= $k ?><?= $filterGame ? '&game_id=' . $filterGame : '' ?>"
                        class="filter-tab <?= $filterStatus === $k ? 'active' : '' ?>">
                        <?= $v ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div style="display:grid; grid-template-columns:<?= $filterGame ? '320px 1fr' : '1fr' ?>; gap:20px; align-items:start;">

                <!-- ── Список игр ── -->
                <div>
                    <?php if (empty($games)): ?>
                        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
                            <div style="font-size:3rem;margin-bottom:12px;">📭</div>
                            <div>Нет игр с оценками</div>
                        </div>
                    <?php else: ?>
                        <div class="games-grid">
                            <?php foreach ($games as $g):
                                $mod = $g['moderation_status'];
                                $statusMap = [
                                    'pending'  => ['⏳ Идёт голосование', '#fbbf24', 'rgba(251,191,36,.1)'],
                                    'approved' => ['✅ Одобрена',          '#4ade80', 'rgba(74,222,128,.1)'],
                                    'rejected' => ['❌ Отклонена',         '#f87171', 'rgba(248,113,113,.1)'],
                                    'revision' => ['🔄 На доработке',     '#fb923c', 'rgba(251,146,60,.1)'],
                                ];
                                [$statusLbl, $statusColor, $statusBg] = $statusMap[$mod] ?? ['—', 'var(--muted)', 'transparent'];
                                $isSelected = ($g['id'] === $filterGame);
                                $gqiVal = (int)$g['GQI'];
                                $gqiColor = $gqiVal >= 75 ? '#4ade80' : ($gqiVal >= 50 ? '#fbbf24' : ($gqiVal > 0 ? '#f87171' : 'var(--muted)'));
                            ?>
                                <a href="?status=<?= $filterStatus ?>&game_id=<?= $g['id'] ?>"
                                    class="game-row <?= $isSelected ? 'selected' : '' ?>">
                                    <div class="game-cover-sm">
                                        <?php if (!empty($g['path_to_cover'])): ?>
                                            <img src="<?= htmlspecialchars($g['path_to_cover']) ?>" alt="">
                                            <?php else: ?>🎮<?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="game-name"><?= htmlspecialchars($g['name']) ?></div>
                                        <div class="game-studio"><?= htmlspecialchars($g['studio_name'] ?? '—') ?></div>
                                    </div>
                                    <div class="stat-col">
                                        <div class="num" style="color:<?= $gqiColor ?>;"><?= $gqiVal ?: '—' ?></div>
                                        <div class="lbl">GQI</div>
                                    </div>
                                    <div class="stat-col">
                                        <div class="num"><?= (int)$g['review_count'] ?></div>
                                        <div class="lbl">голосов</div>
                                    </div>
                                    <div>
                                        <span class="status-pill" style="background:<?= $statusBg ?>;color:<?= $statusColor ?>;">
                                            <?= $statusLbl ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Пагинация -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p === $page): ?>
                                        <span class="current"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="?status=<?= $filterStatus ?>&page=<?= $p ?><?= $filterGame ? '&game_id=' . $filterGame : '' ?>"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ── Детальная панель ── -->
                <?php if ($filterGame && $selectedGame): ?>
                    <div>
                        <!-- Шапка игры -->
                        <div class="detail-panel">
                            <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:20px;">
                                <?php if (!empty($selectedGame['path_to_cover'])): ?>
                                    <img src="<?= htmlspecialchars($selectedGame['path_to_cover']) ?>"
                                        style="width:72px;height:72px;border-radius:10px;object-fit:cover;flex-shrink:0;" alt="">
                                <?php endif; ?>
                                <div>
                                    <div class="detail-title" style="margin-bottom:4px;"><?= htmlspecialchars($selectedGame['name']) ?></div>
                                    <div style="font-size:.82rem;color:var(--muted);"><?= htmlspecialchars($selectedGame['studio_name'] ?? '—') ?></div>
                                </div>
                                <?php if (!empty($aggData) && $aggData['cnt'] > 0): ?>
                                    <?php
                                    $gqiVal   = (int)$selectedGame['GQI'];
                                    $gqiColor = $gqiVal >= 75 ? '#4ade80' : ($gqiVal >= 50 ? '#fbbf24' : ($gqiVal > 0 ? '#f87171' : 'var(--muted)'));
                                    ?>
                                    <div style="margin-left:auto;text-align:center;">
                                        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:2.5rem;color:<?= $gqiColor ?>;line-height:1;">
                                            <?= $gqiVal ?: '—' ?>
                                        </div>
                                        <div style="font-size:.72rem;color:var(--muted);">GQI</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($aggData) && $aggData['cnt'] > 0): ?>
                                <!-- Прогресс -->
                                <?php
                                $pos  = (int)$aggData['positive'];
                                $neg  = (int)$aggData['negative'];
                                $rev  = (int)$aggData['revision'];
                                $tot  = (int)$aggData['cnt'];
                                $need = $needVotes ?? 1;
                                $pct  = min(100, round($pos / $need * 100));
                                ?>
                                <div style="margin-bottom:20px;">
                                    <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);margin-bottom:6px;">
                                        <span><?= $pos ?> за · <?= $neg ?> против<?= $rev > 0 ? " · $rev на доработку" : '' ?></span>
                                        <span>нужно <?= $need ?> голосов «за»</span>
                                    </div>
                                    <div style="height:8px;background:var(--surface2);border-radius:4px;overflow:hidden;display:flex;">
                                        <?php if ($pos > 0): ?>
                                            <div style="width:<?= round($pos / $tot * 100) ?>%;background:var(--accent);border-radius:4px 0 0 4px;"></div>
                                        <?php endif; ?>
                                        <?php if ($rev > 0): ?>
                                            <div style="width:<?= round($rev / $tot * 100) ?>%;background:#fb923c;"></div>
                                        <?php endif; ?>
                                        <?php if ($neg > 0): ?>
                                            <div style="width:<?= round($neg / $tot * 100) ?>%;background:var(--danger);border-radius:0 4px 4px 0;"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top:4px;font-size:.72rem;color:var(--muted);"><?= $pct ?>% от порога публикации</div>
                                </div>

                                <!-- Детальные критерии -->
                                <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:10px;">Средние по критериям</div>
                                <?php
                                $critDisplay = [
                                    ['avg_gameplay',    '🎮 Геймплей',      '#22d3ee'],
                                    ['avg_visual',      '🎨 Визуал',         '#a78bfa'],
                                    ['avg_stability',   '🔧 Стабильность',  '#4ade80'],
                                    ['avg_originality', '💡 Оригинальность', '#fbbf24'],
                                    ['avg_sound',       '🎵 Звук',           '#fb923c'],
                                    ['avg_content',     '📖 Глубина',        '#f472b6'],
                                ];
                                foreach ($critDisplay as [$key, $label, $col]):
                                    $val = (int)($aggData[$key] ?? 0);
                                    $pct2 = $val * 10;
                                ?>
                                    <div class="bar-row">
                                        <div class="bar-label"><?= $label ?></div>
                                        <div class="bar-track">
                                            <div class="bar-fill" style="width:<?= $pct2 ?>%;background:<?= $col ?>;"></div>
                                        </div>
                                        <div class="bar-val" style="color:<?= $val > 0 ? $col : 'var(--muted)' ?>;"><?= $val ?: '—' ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Рецензии -->
                        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;margin-bottom:14px;">
                            Рецензии (<?= count($detailReviews) ?>)
                            <?php if (!$isAdmin): ?>
                                <span style="font-size:.72rem;font-weight:400;color:var(--muted);margin-left:6px;">авторы скрыты</span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($detailReviews)): ?>
                            <div style="text-align:center;padding:40px;color:var(--muted);font-size:.88rem;">
                                Рецензий пока нет
                            </div>
                        <?php endif; ?>

                        <?php foreach ($detailReviews as $r):
                            $v = $r['verdict'] ?? ($r['score'] > 51 ? 'recommend' : 'reject');
                            $verdictMap = [
                                'recommend' => ['👍 Рекомендует',    '#4ade80', 'rgba(74,222,128,.1)'],
                                'revision'  => ['🔄 На доработку',  '#fbbf24', 'rgba(251,191,36,.1)'],
                                'reject'    => ['👎 Не рекомендует', '#f87171', 'rgba(248,113,113,.1)'],
                            ];
                            [$vlbl, $vcol, $vbg] = $verdictMap[$v] ?? $verdictMap['reject'];
                            $isMyReview = ($expertId && $r['expert_id'] === $expertId);
                        ?>
                            <div class="review-card" style="<?= $isMyReview ? 'border-color:rgba(34,211,238,.3);' : '' ?>">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <?php if ($isAdmin): ?>
                                            <div style="
                            width:28px;height:28px;border-radius:50%;background:var(--p);
                            display:flex;align-items:center;justify-content:center;
                            font-family:'Syne',sans-serif;font-weight:700;font-size:.75rem;color:#fff;
                        "><?= mb_strtoupper(mb_substr($r['username'], 0, 1)) ?></div>
                                            <div>
                                                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($r['username']) ?></div>
                                                <div style="font-size:.72rem;color:var(--muted);">Рейтинг: <?= (int)$r['expert_rating'] ?></div>
                                            </div>
                                        <?php elseif ($isMyReview): ?>
                                            <span style="font-size:.75rem;font-weight:700;background:rgba(34,211,238,.12);color:var(--accent2);padding:3px 10px;border-radius:4px;">★ Ваша оценка</span>
                                        <?php else: ?>
                                            <span style="font-size:.8rem;color:var(--muted);">Эксперт #<?= $r['expert_id'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span class="review-verdict-badge" style="background:<?= $vbg ?>;color:<?= $vcol ?>;"><?= $vlbl ?></span>
                                        <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;color:<?= $vcol ?>;"><?= (int)$r['score'] ?></span>
                                    </div>
                                </div>

                                <!-- Мини-критерии -->
                                <?php
                                $miniCrits = [
                                    'score' => ['🎮', '#22d3ee'],
                                    'visual_score'   => ['🎨', '#a78bfa'],
                                    'stability'      => ['🔧', '#4ade80'],
                                    'originality'    => ['💡', '#fbbf24'],
                                    'sound_score'    => ['🎵', '#fb923c'],
                                    'content_depth'  => ['📖', '#f472b6'],
                                ];
                                $hasCrits = false;
                                foreach ($miniCrits as $field => $d) {
                                    if (!empty($r[$field])) {
                                        $hasCrits = true;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($hasCrits): ?>
                                    <div class="mini-criteria">
                                        <?php foreach ($miniCrits as $field => [$ic, $col]): if (empty($r[$field])) continue; ?>
                                            <span class="mini-crit" style="color:<?= $col ?>;">
                                                <?= $ic ?> <?= (int)$r[$field] ?>/10
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($r['comment'])): ?>
                                    <div class="review-body" style="border-left:3px solid var(--border);padding-left:12px;margin-top:12px;font-style:italic;">
                                        <?= nl2br(htmlspecialchars($r['comment'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

</body>

</html>