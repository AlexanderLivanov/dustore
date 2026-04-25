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

$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'");
$pendingExperts = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM games WHERE status='pending'");
$pendingGames = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'");
$totalExperts = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления — Dustore</title>
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

        /* SIDEBAR */
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
            position: relative;
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
            min-width: 20px;
            text-align: center;
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
            flex-shrink: 0;
        }

        .user-name {
            font-size: .85rem;
            font-weight: 500;
        }

        .user-role {
            font-size: .72rem;
            color: var(--muted);
        }

        /* MAIN */
        main {
            flex: 1;
            overflow: auto;
        }

        .main-inner {
            padding: 40px;
            max-width: 960px;
        }

        .page-header {
            margin-bottom: 36px;
        }

        .page-header .eyebrow {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .page-header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -.5px;
        }

        .page-header p {
            color: var(--muted);
            font-size: .95rem;
            margin-top: 6px;
        }

        /* STAT CARDS */
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 36px;
        }

        @media(max-width:700px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            transition: border-color .2s;
        }

        .card:hover {
            border-color: rgba(74, 222, 128, .3);
        }

        .card .card-label {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .card .card-value {
            font-family: 'Syne', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--text);
        }

        .card .card-sub {
            font-size: .82rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .card-accent .card-value {
            color: var(--accent);
        }

        .card-warn .card-value {
            color: var(--warning);
        }

        .card-cyan .card-value {
            color: var(--accent2);
        }

        /* QUICK LINKS */
        .quick {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media(max-width:600px) {
            .quick {
                grid-template-columns: 1fr;
            }
        }

        .quick-link {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all .2s;
        }

        .quick-link:hover {
            border-color: rgba(74, 222, 128, .3);
            transform: translateY(-2px);
        }

        .quick-link .ql-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .ql-green {
            background: rgba(74, 222, 128, .12);
        }

        .ql-blue {
            background: rgba(34, 211, 238, .12);
        }

        .quick-link h3 {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .quick-link p {
            font-size: .82rem;
            color: var(--muted);
        }
    </style>
</head>

<body>

    <aside>
        <div class="logo">Dustore <span>Admin Panel</span></div>

        <div class="nav-section">Меню</div>
        <a href="index" class="active">🏠 Главная</a>
        <a href="expert-requests">
            👤 Заявки экспертов
            <?php if ($pendingExperts > 0): ?><span class="badge"><?= $pendingExperts ?></span><?php endif; ?>
        </a>
        <a href="moderation">
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
                <div class="eyebrow">Обзор</div>
                <h1>Добро пожаловать, <?= htmlspecialchars($_SESSION['USERDATA']['username']) ?>!</h1>
                <p>Используйте панель для управления заявками экспертов и модерации игр.</p>
            </div>

            <div class="cards">
                <div class="card card-warn">
                    <div class="card-label">Заявок экспертов</div>
                    <div class="card-value"><?= $pendingExperts ?></div>
                    <div class="card-sub">ожидают рассмотрения</div>
                </div>
                <div class="card card-cyan">
                    <div class="card-label">Игр на проверке</div>
                    <div class="card-value"><?= $pendingGames ?></div>
                    <div class="card-sub">требуют модерации</div>
                </div>
                <div class="card card-accent">
                    <div class="card-label">Активных экспертов</div>
                    <div class="card-value"><?= $totalExperts ?></div>
                    <div class="card-sub">одобренных участников</div>
                </div>
            </div>

            <div class="quick">
                <a href="expert-requests" class="quick-link">
                    <div class="ql-icon ql-green">👤</div>
                    <div>
                        <h3>Заявки экспертов</h3>
                        <p>Просмотр, одобрение и отклонение новых заявок на участие в программе</p>
                    </div>
                </a>
                <a href="moderation" class="quick-link">
                    <div class="ql-icon ql-blue">🎮</div>
                    <div>
                        <h3>Модерация игр</h3>
                        <p>Проверка игр, ожидающих экспертной оценки и публикации</p>
                    </div>
                </a>
            </div>

        </div>
    </main>

</body>

</html>