<?php
session_start();
require_once('../swad/config.php');

$db = new Database();
$pdo = $db->connect();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$roleFilter = $_GET['role'] ?? '';

$sql = "
SELECT bids.*, users.username
FROM bids
LEFT JOIN users ON users.id = bids.bidder_id
WHERE (stage='open' OR stage=0)
";

$params = [];

if ($roleFilter) {
    $sql .= " AND search_role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bids = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "SELECT COUNT(*) FROM bids WHERE (stage='open' OR stage=0)";
$countParams = [];
if ($roleFilter) {
    $countSql .= " AND search_role = ?";
    $countParams[] = $roleFilter;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Биржа заявок</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #0b0c10;
            --surface: #111318;
            --surface2: #181a21;
            --border: #22242e;
            --border2: #2e3040;
            --accent: #7b5cf0;
            --accent2: #a07cff;
            --accent-glow: rgba(123, 92, 240, .18);
            --text: #e8eaf0;
            --muted: #7a7d8f;
            --muted2: #4e5060;
            --green: #3ecf8e;
            --mono: 'IBM Plex Mono', monospace;
            --sans: 'Manrope', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
            padding: 32px 24px;
            gap: 24px;
            flex: 1;
            position: relative;
        }

        /* ── LEFT COLUMN ── */
        .left-col {
            flex: 1;
            min-width: 0;
            transition: all .3s ease;
        }

        /* ── HEADER ── */
        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.5px;
            color: var(--text);
        }

        .page-title span {
            color: var(--accent2);
        }

        .total-badge {
            background: var(--surface2);
            border: 1px solid var(--border2);
            color: var(--muted);
            font-family: var(--mono);
            font-size: 12px;
            padding: 3px 9px;
            border-radius: 20px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-label {
            font-size: 12px;
            color: var(--muted);
            font-family: var(--mono);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-right: 4px;
        }

        .role-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--muted);
            font-family: var(--sans);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 13px;
            border-radius: 20px;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }

        .role-btn:hover {
            border-color: var(--border2);
            color: var(--text);
        }

        .role-btn.active {
            background: var(--accent-glow);
            border-color: var(--accent);
            color: var(--accent2);
        }

        /* ── BID LIST ── */
        .bid-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* ── BID ITEM ── */
        .bid-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 18px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: start;
            gap: 12px;
            cursor: pointer;
            transition: border-color .15s, background .15s, transform .1s;
            position: relative;
            overflow: hidden;
        }

        .bid-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: transparent;
            border-radius: 10px 0 0 10px;
            transition: background .15s;
        }

        .bid-item:hover {
            border-color: var(--border2);
            background: var(--surface2);
        }

        .bid-item:hover::before,
        .bid-item.active::before {
            background: var(--accent);
        }

        .bid-item.active {
            border-color: var(--accent);
            background: var(--surface2);
        }

        /* role + spec */
        .bid-head {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .bid-role {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .bid-spec-tag {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--accent2);
            background: var(--accent-glow);
            border: 1px solid rgba(123, 92, 240, .3);
            padding: 2px 7px;
            border-radius: 4px;
        }

        .bid-goal {
            font-size: 13px;
            color: var(--text);
            font-weight: 600;
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .bid-snippet {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* right side */
        .bid-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            min-width: 120px;
        }

        .bid-user {
            font-size: 12px;
            color: var(--muted);
        }

        .bid-user strong {
            color: var(--text);
            font-weight: 600;
        }

        .bid-exp-badge {
            font-size: 11px;
            font-family: var(--mono);
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--green);
            padding: 3px 8px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .bid-cond {
            font-size: 11px;
            color: var(--muted);
            text-align: right;
            max-width: 130px;
            line-height: 1.4;
        }

        .bid-stats {
            display: flex;
            gap: 8px;
            font-size: 11px;
            color: var(--muted2);
            font-family: var(--mono);
        }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            gap: 6px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .page-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-family: var(--mono);
            transition: all .15s;
        }

        .page-btn:hover {
            border-color: var(--border2);
            color: var(--text);
        }

        .page-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        /* ── PREVIEW PANEL ── */
        .preview-panel {
            width: 0;
            overflow: hidden;
            opacity: 0;
            flex-shrink: 0;
            transition: width .3s cubic-bezier(.4, 0, .2, 1), opacity .25s ease;
            position: sticky;
            top: 32px;
            align-self: flex-start;
            max-height: calc(100vh - 64px);
        }

        .preview-panel.open {
            width: 380px;
            opacity: 1;
            overflow: visible;
        }

        .preview-inner {
            width: 380px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 64px);
        }

        /* panel header */
        .pv-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
            flex-shrink: 0;
        }

        .pv-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .pv-role {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }

        .pv-close {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--muted);
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            transition: all .15s;
        }

        .pv-close:hover {
            color: var(--text);
            border-color: var(--border2);
        }

        .pv-spec {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--accent2);
            margin-top: 6px;
        }

        /* panel body */
        .pv-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .pv-body::-webkit-scrollbar {
            width: 4px;
        }

        .pv-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .pv-body::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 2px;
        }

        .pv-section-label {
            font-family: var(--mono);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted2);
            margin-bottom: 6px;
        }

        .pv-goal-text {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.5;
        }

        .pv-details-text {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .pv-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .pv-info-cell {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
        }

        .pv-info-val {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-top: 3px;
        }

        .pv-divider {
            height: 1px;
            background: var(--border);
        }

        /* author row */
        .pv-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pv-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #3ecf8e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .pv-author-name {
            font-size: 14px;
            font-weight: 700;
        }

        .pv-author-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* stats row */
        .pv-stats {
            display: flex;
            gap: 16px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
        }

        .pv-stat {
            text-align: center;
            flex: 1;
        }

        .pv-stat-val {
            font-family: var(--mono);
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .pv-stat-lbl {
            font-size: 10px;
            color: var(--muted2);
            margin-top: 2px;
        }

        /* panel footer */
        .pv-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface2);
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-respond {
            flex: 1;
            background: var(--accent);
            border: none;
            color: #fff;
            font-family: var(--sans);
            font-size: 13px;
            font-weight: 700;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background .15s, transform .1s;
            letter-spacing: .02em;
        }

        .btn-respond:hover {
            background: var(--accent2);
        }

        .btn-respond:active {
            transform: scale(.97);
        }

        .btn-open {
            background: transparent;
            border: 1px solid var(--border2);
            color: var(--muted);
            font-family: var(--sans);
            font-size: 12px;
            font-weight: 600;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }

        .btn-open:hover {
            color: var(--text);
            border-color: var(--accent);
        }

        /* empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state svg {
            opacity: .3;
            margin-bottom: 16px;
        }

        /* respond toast */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-left: 3px solid var(--green);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            opacity: 0;
            pointer-events: none;
            transition: all .3s;
            z-index: 1000;
            white-space: nowrap;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        @media (max-width: 760px) {
            .layout {
                padding: 16px;
            }

            .preview-panel.open {
                position: fixed;
                inset: 0;
                width: 100% !important;
                max-height: 100%;
                z-index: 100;
                background: rgba(0, 0, 0, .7);
                display: flex;
                align-items: flex-end;
                border-radius: 0;
                top: 0;
                left: 0;
                opacity: 1;
            }

            .preview-inner {
                width: 100%;
                max-height: 90vh;
                border-radius: 16px 16px 0 0;
            }
        }
    </style>
</head>

<body>

    <div class="layout">

        <!-- ── LEFT: LIST ── -->
        <div class="left-col" id="leftCol">

            <div class="page-header">
                <h1 class="page-title">Биржа <span>заявок</span></h1>
                <span class="total-badge"><?= $total ?> активных</span>
            </div>

            <div class="filter-bar">
                <span class="filter-label">Роль:</span>
                <button class="role-btn <?= !$roleFilter ? 'active' : '' ?>"
                    onclick="setRole('')">Все</button>
                <button class="role-btn <?= $roleFilter == 'Unity программист' ? 'active' : '' ?>"
                    onclick="setRole('Unity программист')">Unity программист</button>
                <button class="role-btn <?= $roleFilter == 'Геймдизайнер' ? 'active' : '' ?>"
                    onclick="setRole('Геймдизайнер')">Геймдизайнер</button>
                <button class="role-btn <?= $roleFilter == 'CGI художник' ? 'active' : '' ?>"
                    onclick="setRole('CGI художник')">CGI художник</button>
            </div>

            <div class="bid-list" id="bidList">

                <?php if (empty($bids)): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p>Нет активных заявок</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bids as $bid): ?>
                        <div class="bid-item"
                            id="bid-<?= (int)$bid['id'] ?>"
                            onclick="openPreview(<?= htmlspecialchars(json_encode($bid), ENT_QUOTES) ?>)">
                            <div class="bid-left-content">
                                <div class="bid-head">
                                    <span class="bid-role"><?= htmlspecialchars($bid['search_role']) ?></span>
                                    <?php if ($bid['search_spec']): ?>
                                        <span class="bid-spec-tag"><?= htmlspecialchars($bid['search_spec']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bid-goal"><?= htmlspecialchars($bid['goal']) ?></div>
                                <div class="bid-snippet"><?= htmlspecialchars(mb_substr($bid['details'], 0, 160)) ?>…</div>
                            </div>

                            <div class="bid-meta">
                                <div class="bid-user">
                                    <strong><?= htmlspecialchars($bid['username'] ?? 'user ' . $bid['bidder_id']) ?></strong>
                                </div>
                                <?php if ($bid['experience']): ?>
                                    <div class="bid-exp-badge"><?= htmlspecialchars($bid['experience']) ?></div>
                                <?php endif; ?>
                                <?php if ($bid['conditions']): ?>
                                    <div class="bid-cond"><?= htmlspecialchars(mb_substr($bid['conditions'], 0, 40)) ?></div>
                                <?php endif; ?>
                                <div class="bid-stats">
                                    <span>👁 <?= (int)$bid['views'] ?></span>
                                    <span>💬 <?= (int)$bid['responses'] ?></span>
                                    <span>⭐ <?= (int)$bid['favorites'] ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div><!-- /bid-list -->

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="page-btn <?= $i == $page ? 'active' : '' ?>"
                            href="?page=<?= $i ?><?= $roleFilter ? '&role=' . urlencode($roleFilter) : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div><!-- /left-col -->

        <!-- ── RIGHT: PREVIEW PANEL ── -->
        <div class="preview-panel" id="previewPanel">
            <div class="preview-inner">

                <div class="pv-header">
                    <div class="pv-top">
                        <div>
                            <div class="pv-role" id="pvRole">—</div>
                            <div class="pv-spec" id="pvSpec"></div>
                        </div>
                        <button class="pv-close" onclick="closePreview()">✕</button>
                    </div>
                </div>

                <div class="pv-body">

                    <div>
                        <div class="pv-section-label">Цель</div>
                        <div class="pv-goal-text" id="pvGoal">—</div>
                    </div>

                    <div>
                        <div class="pv-section-label">Подробности</div>
                        <div class="pv-details-text" id="pvDetails">—</div>
                    </div>

                    <div class="pv-divider"></div>

                    <div class="pv-info-grid">
                        <div class="pv-info-cell">
                            <div class="pv-section-label">Опыт</div>
                            <div class="pv-info-val" id="pvExp">—</div>
                        </div>
                        <div class="pv-info-cell">
                            <div class="pv-section-label">Условия</div>
                            <div class="pv-info-val" id="pvCond">—</div>
                        </div>
                    </div>

                    <div class="pv-divider"></div>

                    <div>
                        <div class="pv-section-label">Автор</div>
                        <div class="pv-author">
                            <div class="pv-avatar" id="pvAvatar">?</div>
                            <div>
                                <div class="pv-author-name" id="pvAuthorName">—</div>
                                <div class="pv-author-sub" id="pvAuthorSub">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="pv-stats">
                        <div class="pv-stat">
                            <div class="pv-stat-val" id="pvViews">0</div>
                            <div class="pv-stat-lbl">просмотры</div>
                        </div>
                        <div class="pv-stat">
                            <div class="pv-stat-val" id="pvResps">0</div>
                            <div class="pv-stat-lbl">отклики</div>
                        </div>
                        <div class="pv-stat">
                            <div class="pv-stat-val" id="pvFavs">0</div>
                            <div class="pv-stat-lbl">избранное</div>
                        </div>
                    </div>

                </div><!-- /pv-body -->

                <div class="pv-footer">
                    <button class="btn-respond" id="pvRespondBtn">Откликнуться</button>
                    <button class="btn-open" id="pvOpenBtn">Открыть →</button>
                </div>

            </div>
        </div><!-- /preview-panel -->

    </div><!-- /layout -->

    <!-- TOAST -->
    <div class="toast" id="toast"></div>

    <script>
        let activeBidId = null;

        function openPreview(bid) {
            // highlight active row
            document.querySelectorAll('.bid-item').forEach(el => el.classList.remove('active'));
            const row = document.getElementById('bid-' + bid.id);
            if (row) row.classList.add('active');

            // populate panel
            document.getElementById('pvRole').textContent = bid.search_role || '—';
            document.getElementById('pvSpec').textContent = bid.search_spec || '';
            document.getElementById('pvGoal').textContent = bid.goal || '—';
            document.getElementById('pvDetails').textContent = bid.details || '—';
            document.getElementById('pvExp').textContent = bid.experience || '—';
            document.getElementById('pvCond').textContent = bid.conditions || '—';

            const username = bid.username || ('user ' + bid.bidder_id);
            document.getElementById('pvAuthorName').textContent = username;
            document.getElementById('pvAvatar').textContent = username.charAt(0).toUpperCase();

            // created_at formatting
            let sub = '';
            if (bid.created_at) {
                try {
                    const d = new Date(bid.created_at);
                    sub = d.toLocaleDateString('ru-RU', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                } catch (e) {
                    sub = bid.created_at;
                }
            }
            document.getElementById('pvAuthorSub').textContent = sub || 'Участник';

            document.getElementById('pvViews').textContent = bid.views || 0;
            document.getElementById('pvResps').textContent = bid.responses || 0;
            document.getElementById('pvFavs').textContent = bid.favorites || 0;

            activeBidId = bid.id;
            document.getElementById('pvRespondBtn').onclick = () => sendRespond(bid.id);
            document.getElementById('pvOpenBtn').onclick = () => location.href = 'bid.php?id=' + bid.id;

            document.getElementById('previewPanel').classList.add('open');
        }

        function closePreview() {
            document.getElementById('previewPanel').classList.remove('open');
            document.querySelectorAll('.bid-item').forEach(el => el.classList.remove('active'));
            activeBidId = null;
        }

        function sendRespond(id) {
            fetch('respond.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        bid_id: id
                    })
                })
                .then(r => r.json())
                .then(d => showToast(d.success ? '✓ Отклик отправлен' : '✗ Ошибка при отклике'))
                .catch(() => showToast('✗ Ошибка соединения'));
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2800);
        }

        function setRole(role) {
            const url = new URL(window.location);
            if (role) url.searchParams.set('role', role);
            else url.searchParams.delete('role');
            url.searchParams.delete('page');
            window.location = url;
        }

        // close on backdrop click (mobile)
        document.getElementById('previewPanel').addEventListener('click', function(e) {
            if (e.target === this) closePreview();
        });

        // close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closePreview();
        });
    </script>

</body>

</html>