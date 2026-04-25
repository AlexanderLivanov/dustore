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

$stmt = $pdo->query("
    SELECT e.id AS expert_id, e.status, e.rating, e.votes_count, e.experience, e.motivation, e.created_at,
           u.username, u.first_name, u.last_name, u.email, u.profile_picture
    FROM experts e
    JOIN users u ON e.user_id = u.id
    ORDER BY e.status ASC, e.created_at DESC
");
$experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending  = array_filter($experts, fn($e) => $e['status'] === 'new');
$approved = array_filter($experts, fn($e) => $e['status'] === 'approved');
$rejected = array_filter($experts, fn($e) => $e['status'] === 'rejected');

$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'");
$pendingExperts = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM games WHERE status='pending'");
$pendingGames = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки экспертов — Dustore</title>
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

        /* Sidebar */
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

        /* Main */
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
            font-size: 1.8rem;
            font-weight: 800;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 4px;
            width: fit-content;
        }

        .tab {
            padding: 8px 20px;
            border-radius: 7px;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            color: var(--muted);
            border: none;
            background: transparent;
            font-family: 'DM Sans', sans-serif;
        }

        .tab.active {
            background: var(--surface2);
            color: var(--text);
        }

        .tab-count {
            display: inline-block;
            background: var(--danger);
            color: #fff;
            font-size: .65rem;
            border-radius: 8px;
            padding: 1px 5px;
            margin-left: 5px;
            vertical-align: middle;
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
            border-radius: 14px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: start;
            animation: fadeUp .3s ease both;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .expert-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #232b3a, #1a2030);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        .expert-info {}

        .expert-name {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 3px;
        }

        .expert-email {
            font-size: .82rem;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .expert-fields {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .expert-field {
            font-size: .82rem;
        }

        .expert-field .lbl {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 2px;
        }

        .expert-field .val {
            color: var(--text);
            line-height: 1.4;
            max-width: 260px;
        }

        .expert-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-approve,
        .btn-reject {
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            border: none;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-approve {
            background: rgba(74, 222, 128, .15);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .3);
        }

        .btn-approve:hover {
            background: var(--accent);
            color: #0b0e13;
        }

        .btn-reject {
            background: rgba(248, 113, 113, .1);
            color: var(--danger);
            border: 1px solid rgba(248, 113, 113, .25);
        }

        .btn-reject:hover {
            background: var(--danger);
            color: #fff;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
        }

        .pill-new {
            background: rgba(251, 191, 36, .12);
            color: var(--warning);
        }

        .pill-approved {
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
        }

        .pill-rejected {
            background: rgba(248, 113, 113, .1);
            color: var(--danger);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            font-size: .95rem;
        }

        .empty-state .ei {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }

        /* ── Monthly tab ── */
        .monthly-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .monthly-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .monthly-meta {
            font-size: .85rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .slot-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .slot-bar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .slot-bar-top span {
            font-size: .85rem;
            color: var(--muted);
        }

        .slot-bar-top strong {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            color: var(--text);
        }

        .bar-track {
            height: 6px;
            background: var(--surface2);
            border-radius: 3px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 3px;
            transition: width .6s ease;
        }

        .btn-roll {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #0b0e13;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .88rem;
            border: none;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-roll:hover {
            opacity: .85;
            transform: translateY(-1px);
        }

        .btn-roll:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
        }

        .btn-enqueue {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 700;
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .25);
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-enqueue:hover {
            background: var(--accent);
            color: #0b0e13;
        }

        .btn-enqueue:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .btn-dequeue {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 700;
            background: rgba(248, 113, 113, .08);
            color: var(--danger);
            border: 1px solid rgba(248, 113, 113, .2);
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-dequeue:hover {
            background: var(--danger);
            color: #fff;
        }

        .m-section-title {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            margin: 20px 0 10px;
        }

        .m-row {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 8px;
        }

        .m-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: .9rem;
            color: #0b0e13;
        }

        .av-active {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
        }

        .av-queued {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }

        .av-skipped {
            background: #232b3a;
            color: var(--muted) !important;
        }

        .av-avail {
            background: #1a2030;
            color: var(--muted) !important;
        }

        .m-name {
            font-weight: 500;
            font-size: .9rem;
        }

        .m-email {
            font-size: .78rem;
            color: var(--muted);
        }

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

        .pill-queued {
            background: rgba(251, 191, 36, .12);
            color: var(--warning);
            border: 1px solid rgba(251, 191, 36, .25);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .pill-skipped {
            background: rgba(107, 122, 153, .1);
            color: var(--muted);
            border: 1px solid rgba(107, 122, 153, .2);
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
                <div class="eyebrow">Управление</div>
                <h1>Заявки экспертов</h1>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('new',this)">
                    Новые <span class="tab-count"><?= count($pending) ?></span>
                </button>
                <button class="tab" onclick="switchTab('approved',this)">Одобренные</button>
                <button class="tab" onclick="switchTab('rejected',this)">Отклонённые</button>
                <button class="tab" onclick="switchTab('monthly',this); loadMonthly()">📅 Месяц</button>
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
                            <div class="expert-avatar"><?= mb_strtoupper(mb_substr($exp['username'] ?: $exp['first_name'], 0, 1)) ?></div>
                            <div class="expert-info">
                                <div class="expert-name"><?= htmlspecialchars($exp['username'] ?: $exp['first_name'] . ' ' . $exp['last_name']) ?></div>
                                <div class="expert-email"><?= htmlspecialchars($exp['email']) ?> · <?= date('d.m.Y H:i:s', strtotime($exp['created_at'])) ?></div>
                                <div class="expert-fields">
                                    <div class="expert-field">
                                        <span class="lbl">Опыт</span>
                                        <span class="val"><?= htmlspecialchars(mb_substr($exp['experience'], 0, 180)) ?>...</span>
                                    </div>
                                    <div class="expert-field">
                                        <span class="lbl">Мотивация</span>
                                        <span class="val"><?= htmlspecialchars(mb_substr($exp['motivation'], 0, 180)) ?>...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="expert-actions">
                                <button class="btn-approve" onclick="updateExpert(<?= $exp['expert_id'] ?>,'approve')">✓ Одобрить</button>
                                <button class="btn-reject" onclick="updateExpert(<?= $exp['expert_id'] ?>,'reject')">✕ Отклонить</button>
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
                            <div class="expert-avatar"><?= mb_strtoupper(mb_substr($exp['username'] ?: $exp['first_name'], 0, 1)) ?></div>
                            <div class="expert-info">
                                <div class="expert-name"><?= htmlspecialchars($exp['username'] ?: $exp['first_name'] . ' ' . $exp['last_name']) ?></div>
                                <div class="expert-email"><?= htmlspecialchars($exp['email']) ?></div>
                                <div class="expert-fields">
                                    <div class="expert-field"><span class="lbl">Рейтинг</span><span class="val"><?= round($exp['rating'], 2) ?></span></div>
                                    <div class="expert-field"><span class="lbl">Оценок</span><span class="val"><?= $exp['votes_count'] ?></span></div>
                                </div>
                            </div>
                            <div><span class="status-pill pill-approved">Одобрен</span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- REJECTED -->
            <div class="tab-panel" id="tab-rejected">
                <div class="expert-grid">
                    <?php if (empty($rejected)): ?>
                        <div class="empty-state">
                            <div class="ei">🚫</div>Нет отклонённых
                        </div>
                    <?php endif; ?>
                    <?php foreach ($rejected as $exp): ?>
                        <div class="expert-card">
                            <div class="expert-avatar"><?= mb_strtoupper(mb_substr($exp['username'] ?: $exp['first_name'], 0, 1)) ?></div>
                            <div class="expert-info">
                                <div class="expert-name"><?= htmlspecialchars($exp['username'] ?: $exp['first_name'] . ' ' . $exp['last_name']) ?></div>
                                <div class="expert-email"><?= htmlspecialchars($exp['email']) ?></div>
                            </div>
                            <div><span class="status-pill pill-rejected">Отклонён</span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-panel" id="tab-monthly">
                <div id="monthly-inner">
                    <div class="empty-state">
                        <div class="ei">⏳</div>Загрузка...
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function switchTab(name, btn) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            btn.classList.add('active');
        }

        function updateExpert(id, action) {
            fetch('update-expert.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        action
                    })
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const card = document.getElementById('card-' + id);
                        if (card) card.remove();
                        // Update badge counts
                        const grid = document.querySelector('#tab-new .expert-grid');
                        if (grid && !grid.querySelector('.expert-card')) {
                            grid.innerHTML = '<div class="empty-state"><div class="ei">✅</div>Новых заявок нет</div>';
                        }
                    } else {
                        alert(json.error || 'Ошибка при обновлении');
                    }
                })
                .catch(e => alert('Ошибка: ' + e));
        }


        let monthlyLoaded = false;

        // Вызывается при клике на таб "📅 Месяц"
        function loadMonthly() {
            if (monthlyLoaded) return; // не перезагружать если уже загружено
            monthlyLoaded = true;
            fetchMonthly();
        }

        function fetchMonthly() {
            fetch('monthly-experts.php?action=get')
                .then(r => r.json())
                .then(renderMonthly)
                .catch(() => {
                    document.getElementById('monthly-inner').innerHTML =
                        '<div class="empty-state"><div class="ei">❌</div>Ошибка загрузки</div>';
                });
        }

        function renderMonthly(data) {
            const active = data.queue.filter(r => r.status === 'active');
            const queued = data.queue.filter(r => r.status === 'queued');
            const skipped = data.queue.filter(r => r.status === 'skipped');
            const total = data.queue.length;
            const pct = data.slots ? Math.min(Math.round(total / data.slots * 100), 100) : 0;

            const initials = r => (r.username || r.first_name || '?')[0].toUpperCase();

            const row = (r, avClass, pillHtml, btnHtml) => `
    <div class="m-row">
      <div class="m-avatar ${avClass}">${initials(r)}</div>
      <div style="flex:1;min-width:0">
        <div class="m-name">${r.username || (r.first_name + ' ' + r.last_name)}</div>
        <div class="m-email">${r.email}</div>
      </div>
      ${pillHtml}
      ${btnHtml}
    </div>`;

            let html = `
    <div class="monthly-header">
      <div>
        <div class="monthly-title">📅 Эксперты месяца — ${data.month}</div>
        <div class="monthly-meta">Мест: ${data.slots} · В очереди: ${total}</div>
      </div>
      <button class="btn-roll" onclick="rollMonthly(this)" ${total === 0 ? 'disabled' : ''}>
        🎲 Запустить рандом
      </button>
    </div>
    <div class="slot-bar">
      <div class="slot-bar-top">
        <span>Заполненность очереди</span>
        <strong>${total} / ${data.slots}</strong>
      </div>
      <div class="bar-track">
        <div class="bar-fill" style="width:${pct}%"></div>
      </div>
    </div>`;

            if (active.length) {
                html += `<div class="m-section-title">✅ Активны в этом месяце</div>`;
                active.forEach(r => html += row(r, 'av-active',
                    `<span class="pill-active">Активен</span>`,
                    `<button class="btn-dequeue" onclick="dequeue(${r.expert_id})">Убрать</button>`));
            }

            if (queued.length) {
                html += `<div class="m-section-title">⏳ В очереди (ждут рандома)</div>`;
                queued.forEach(r => html += row(r, 'av-queued',
                    `<span class="pill-queued">В очереди</span>`,
                    `<button class="btn-dequeue" onclick="dequeue(${r.expert_id})">Убрать</button>`));
            }

            if (skipped.length) {
                html += `<div class="m-section-title">🚫 Не прошли рандом</div>`;
                skipped.forEach(r => html += row(r, 'av-skipped',
                    `<span class="pill-skipped">Пропущен</span>`,
                    `<button class="btn-enqueue" onclick="enqueue(${r.expert_id})">Добавить снова</button>`));
            }

            if (data.available && data.available.length) {
                html += `<div class="m-section-title">➕ Доступные (не добавлены)</div>`;
                data.available.forEach(r => {
                    const blocked = r.blocked_prev_month == 1;
                    html += row(r, 'av-avail',
                        blocked ? `<span class="pill-skipped" title="Был активен в прошлом месяце">⏸ Пауза</span>` : ``,
                        blocked ?
                        `<button class="btn-enqueue" disabled style="opacity:.4;cursor:not-allowed">Добавить</button>` :
                        `<button class="btn-enqueue" onclick="enqueue(${r.expert_id})">Добавить</button>`
                    );
                });
            }

            if (!total && (!data.available || !data.available.length)) {
                html += `<div class="empty-state"><div class="ei">👤</div>Нет одобренных экспертов</div>`;
            }

            document.getElementById('monthly-inner').innerHTML = html;
        }

        function enqueue(expertId) {
            const fd = new FormData();
            fd.append('action', 'enqueue');
            fd.append('expert_id', expertId);
            fetch('monthly-experts.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        monthlyLoaded = false;
                        fetchMonthly();
                    } else alert(j.error);
                });
        }

        function dequeue(expertId) {
            const fd = new FormData();
            fd.append('action', 'dequeue');
            fd.append('expert_id', expertId);
            fetch('monthly-experts.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        monthlyLoaded = false;
                        fetchMonthly();
                    } else alert(j.error);
                });
        }

        function rollMonthly(btn) {
            if (!confirm('Запустить рандомный отбор?\nТекущий результат будет сброшен.')) return;
            btn.disabled = true;
            btn.textContent = '⏳ Выбираем...';
            const fd = new FormData();
            fd.append('action', 'roll');
            fetch('monthly-experts.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        monthlyLoaded = false;
                        fetchMonthly();
                    } else {
                        alert(j.error);
                        btn.disabled = false;
                        btn.textContent = '🎲 Запустить рандом';
                    }
                });
        }
    </script>
</body>

</html>