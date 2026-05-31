<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

// ── Только для администраторов ────────────────────────────────────────────
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
if ($globalRole !== -1 && $globalRole !== 3) {
    header('Location: /expert/admin/index');
    exit;
}

$db  = new Database();
$pdo = $db->connect();

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $expertId  = (int)($_POST['expert_id'] ?? 0);

    if ($action === 'approve' && $expertId) {
        $pdo->prepare("UPDATE experts SET status='approved', updated_at=NOW() WHERE id=?")
            ->execute([$expertId]);
    } elseif ($action === 'reject' && $expertId) {
        $pdo->prepare("UPDATE experts SET status='rejected', updated_at=NOW() WHERE id=?")
            ->execute([$expertId]);
    }

    header('Location: expert-requests');
    exit;
}

// Загружаем данные
$pending = $pdo->query("
    SELECT e.id AS expert_id, e.experience, e.motivation, e.created_at,
           u.username, u.email, u.profile_picture
    FROM experts e
    JOIN users u ON u.id = e.user_id
    WHERE e.status = 'new'
    ORDER BY e.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$approved = $pdo->query("
    SELECT e.id AS expert_id, e.rating, e.created_at, e.updated_at,
           u.username, u.email, u.profile_picture
    FROM experts e
    JOIN users u ON u.id = e.user_id
    WHERE e.status = 'approved'
    ORDER BY e.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$rejected = $pdo->query("
    SELECT e.id AS expert_id, e.created_at, u.username
    FROM experts e
    JOIN users u ON u.id = e.user_id
    WHERE e.status = 'rejected'
    ORDER BY e.updated_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$pendingExperts = count($pending);
$pendingGames   = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки экспертов — Admin Panel</title>
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
            font-weight: 600;
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 4px;
            width: fit-content;
        }

        .tab {
            padding: 8px 20px;
            border-radius: 9px;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 600;
            color: var(--muted);
            background: none;
            border: none;
            transition: all .2s;
            font-family: 'DM Sans', sans-serif;
        }

        .tab.active {
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
        }

        .tab-count {
            background: var(--danger);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 1px 6px;
            margin-left: 5px;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* Expert cards */
        .expert-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .expert-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .expert-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
        }

        .expert-name {
            font-weight: 700;
            font-size: .95rem;
            margin-bottom: 2px;
        }

        .expert-email {
            font-size: .78rem;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .expert-fields {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .expert-field .lbl {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .expert-field .val {
            font-size: .85rem;
            color: var(--text);
            line-height: 1.5;
        }

        .expert-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .btn-approve {
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .82rem;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-approve:hover {
            background: rgba(74, 222, 128, .25);
        }

        .btn-reject {
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: rgba(248, 113, 113, .08);
            color: var(--danger);
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .82rem;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-reject:hover {
            background: rgba(248, 113, 113, .2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state .ei {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        /* Approved pills */
        .pill-active {
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .25);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    <aside>
        <div class="logo">Dustore <span>Admin Panel</span></div>
        <div class="nav-section">Меню</div>
        <a href="index">🏠 Главная</a>
        <a href="expert-requests" class="active">
            👤 Заявки экспертов
            <?php if ($pendingExperts > 0): ?><span class="badge"><?= $pendingExperts ?></span><?php endif; ?>
        </a>
        <a href="moderation">
            🎮 Модерация игр
            <?php if ($pendingGames > 0): ?><span class="badge"><?= $pendingGames ?></span><?php endif; ?>
        </a>
        <a href="all-reviews">📊 Все оценки</a>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['USERDATA']['username'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['USERDATA']['username']) ?></div>
                    <div class="user-role">Администратор</div>
                </div>
            </div>
            <a href="logout" style="color:var(--danger);margin-top:8px;padding:8px 12px;font-size:.85rem;display:block;">Выйти →</a>
        </div>
    </aside>

    <main>
        <div class="main-inner">
            <div class="page-header">
                <div class="eyebrow">Управление</div>
                <h1>Заявки экспертов</h1>
                <p style="color:var(--muted);font-size:.88rem;margin-top:6px;">
                    Страница доступна только администраторам. Пользователи голосуют за экспертов на отдельной странице выборов.
                </p>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('new',this)">
                    Новые <?php if (count($pending) > 0): ?><span class="tab-count"><?= count($pending) ?></span><?php endif; ?>
                </button>
                <button class="tab" onclick="switchTab('approved',this)">
                    Одобренные <span style="font-size:.75rem;color:var(--muted);">(<?= count($approved) ?>)</span>
                </button>
                <button class="tab" onclick="switchTab('rejected',this)">Отклонённые</button>
            </div>

            <!-- NEW -->
            <div class="tab-panel active" id="tab-new">
                <div class="expert-grid">
                    <?php if (empty($pending)): ?>
                        <div class="empty-state">
                            <div class="ei">✅</div>Новых заявок нет
                        </div>
                    <?php endif; ?>
                    <?php foreach ($pending as $exp): ?>
                        <div class="expert-card" id="card-<?= $exp['expert_id'] ?>">
                            <div class="expert-avatar">
                                <?= mb_strtoupper(mb_substr($exp['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="expert-info" style="flex:1;min-width:0;">
                                <div class="expert-name"><?= htmlspecialchars($exp['username']) ?></div>
                                <div class="expert-email">
                                    <?= htmlspecialchars($exp['email']) ?> ·
                                    <?= date('d.m.Y H:i', strtotime($exp['created_at'])) ?>
                                </div>
                                <div class="expert-fields">
                                    <div class="expert-field">
                                        <div class="lbl">Опыт</div>
                                        <div class="val"><?= htmlspecialchars(mb_substr($exp['experience'] ?? '', 0, 250)) ?></div>
                                    </div>
                                    <div class="expert-field">
                                        <div class="lbl">Мотивация</div>
                                        <div class="val"><?= htmlspecialchars(mb_substr($exp['motivation'] ?? '', 0, 250)) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="expert-actions">
                                <button class="btn-approve" onclick="updateExpert(<?= $exp['expert_id'] ?>,'approve')">
                                    ✓ Одобрить
                                </button>
                                <button class="btn-reject" onclick="updateExpert(<?= $exp['expert_id'] ?>,'reject')">
                                    ✕ Отклонить
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- APPROVED -->
            <div class="tab-panel" id="tab-approved">
                <div class="expert-grid">
                    <?php if (empty($approved)): ?>
                        <div class="empty-state">
                            <div class="ei">👤</div>Нет одобренных
                        </div>
                    <?php endif; ?>
                    <?php foreach ($approved as $exp): ?>
                        <div class="expert-card">
                            <div class="expert-avatar">
                                <?= mb_strtoupper(mb_substr($exp['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div style="flex:1;">
                                <div class="expert-name"><?= htmlspecialchars($exp['username']) ?></div>
                                <div class="expert-email"><?= htmlspecialchars($exp['email']) ?></div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                                <?php if ($exp['rating']): ?>
                                    <span style="font-size:.82rem;color:var(--warning);">★ <?= (int)$exp['rating'] ?></span>
                                <?php endif; ?>
                                <span class="pill-active">Активен</span>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="expert_id" value="<?= $exp['expert_id'] ?>">
                                    <button type="submit" class="btn-reject" style="padding:5px 12px;font-size:.75rem;"
                                        onclick="return confirm('Отозвать статус эксперта?')">
                                        Отозвать
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- REJECTED -->
            <div class="tab-panel" id="tab-rejected">
                <?php if (empty($rejected)): ?>
                    <div class="empty-state">
                        <div class="ei">🗑</div>Нет отклонённых
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($rejected as $exp): ?>
                            <span style="background:var(--surface);border:1px solid var(--border);
                         padding:5px 14px;border-radius:8px;font-size:.82rem;color:var(--muted);">
                                <?= htmlspecialchars($exp['username']) ?>
                                <span style="font-size:.72rem;color:var(--border);margin-left:6px;">
                                    <?= date('d.m.Y', strtotime($exp['created_at'])) ?>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        function switchTab(name, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + name).classList.add('active');
        }

        function updateExpert(id, action) {
            const label = action === 'approve' ? 'одобрить' : 'отклонить';
            if (!confirm('Вы уверены, что хотите ' + label + ' этого эксперта?')) return;
            fetch('expert-requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=' + action + '&expert_id=' + id
            }).then(() => {
                const card = document.getElementById('card-' + id);
                if (card) {
                    card.style.transition = 'opacity .3s, transform .3s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        card.remove();
                    }, 300);
                }
            });
        }
    </script>
</body>

</html>