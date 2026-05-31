<?php
session_start();
require_once __DIR__ . '/../swad/config.php';

$db  = new Database();
$pdo = $db->connect();

$userId   = $_SESSION['USERDATA']['id'] ?? null;
$isAuthed = (bool)$userId;

// ── Активные выборы ───────────────────────────────────────────────────────
$election = $pdo->query("
    SELECT * FROM expert_elections
    WHERE status = 'active'
      AND NOW() BETWEEN start_date AND end_date
    ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$electionId = $election ? (int)$election['id'] : 0;

// ── AJAX: проголосовать ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$isAuthed) {
        echo json_encode(['ok' => false, 'msg' => 'Войдите чтобы голосовать']);
        exit;
    }
    if (!$electionId) {
        echo json_encode(['ok' => false, 'msg' => 'Выборы не активны']);
        exit;
    }

    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    if (!$candidateId) {
        echo json_encode(['ok' => false, 'msg' => 'invalid']);
        exit;
    }

    // Убедимся что кандидат существует и в статусе 'new'
    $stmt = $pdo->prepare("SELECT id, user_id FROM experts WHERE id=? AND status='new'");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch();
    if (!$candidate) {
        echo json_encode(['ok' => false, 'msg' => 'Кандидат не найден']);
        exit;
    }

    // Нельзя голосовать за себя
    if ($candidate['user_id'] == $userId) {
        echo json_encode(['ok' => false, 'msg' => 'Нельзя голосовать за себя']);
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'vote') {
        try {
            $pdo->prepare("
                INSERT INTO expert_votes (election_id, voter_id, candidate_id)
                VALUES (?, ?, ?)
            ")->execute([$electionId, $userId, $candidateId]);
            // Обновляем счётчик
            $pdo->prepare("UPDATE experts SET votes_count = votes_count + 1 WHERE id=?")->execute([$candidateId]);
            echo json_encode(['ok' => true, 'action' => 'voted']);
        } catch (PDOException $e) {
            // UNIQUE violation — уже голосовал, отзываем голос
            $pdo->prepare("DELETE FROM expert_votes WHERE election_id=? AND voter_id=? AND candidate_id=?")->execute([$electionId, $userId, $candidateId]);
            $pdo->prepare("UPDATE experts SET votes_count = GREATEST(0, votes_count - 1) WHERE id=?")->execute([$candidateId]);
            echo json_encode(['ok' => true, 'action' => 'unvoted']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'unknown action']);
    exit;
}

// ── Кандидаты ─────────────────────────────────────────────────────────────
$candidates = $pdo->query("
    SELECT e.id AS expert_id, e.user_id, e.votes_count, e.created_at,
           e.experience, e.motivation,
           u.username, u.profile_picture, u.global_role
    FROM experts e
    JOIN users u ON u.id = e.user_id
    WHERE e.status = 'new'
    ORDER BY e.votes_count DESC, e.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Мои голоса
$myVotes = [];
if ($isAuthed && $electionId) {
    $stmt = $pdo->prepare("SELECT candidate_id FROM expert_votes WHERE election_id=? AND voter_id=?");
    $stmt->execute([$electionId, $userId]);
    $myVotes = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Итоги прошлых выборов (последние завершённые)
$pastElection = $pdo->query("
    SELECT * FROM expert_elections WHERE status='completed' ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$approvedExperts = $pdo->query("
    SELECT e.id, e.votes_count, e.rating, u.username, u.profile_picture
    FROM experts e JOIN users u ON u.id = e.user_id
    WHERE e.status = 'approved'
    ORDER BY e.rating DESC, e.votes_count DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$totalExperts    = count($approvedExperts);
$totalCandidates = count($candidates);
$maxVotes        = $candidates ? max(array_column($candidates, 'votes_count')) : 1;
$maxVotes        = max($maxVotes, 1);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Выборы экспертов — Dustore</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --p: #c32178;
            --p2: #e0268a;
            --surf: #12101a;
            --surf2: #1a1724;
            --surf3: #221f2e;
            --border: #2a263a;
            --border2: #332f44;
            --text: #e4dff5;
            --muted: #7a7090;
            --muted2: #554e70;
            --green: #4ade80;
            --amber: #fbbf24;
            --red: #f87171;
            --cyan: #22d3ee;
            --r: 14px;
            --tr: .2s cubic-bezier(.4, 0, .2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--surf);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5
        }

        a {
            color: inherit;
            text-decoration: none
        }

        /* ── Layout ── */
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 24px
        }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, #1a1030 0%, #0e0a1a 50%, #0a0d1e 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 52px 48px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 80% at 80% 50%, rgba(195, 33, 120, .12) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(195, 33, 120, .15);
            border: 1px solid rgba(195, 33, 120, .3);
            color: var(--p2);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 20px;
        }

        .hero-badge .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--p2);
            animation: pulse 1.6s ease-in-out infinite
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1)
            }

            50% {
                opacity: .4;
                transform: scale(.7)
            }
        }

        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 14px;
            letter-spacing: -.03em
        }

        .hero h1 em {
            font-style: normal;
            color: var(--p2)
        }

        .hero p {
            font-size: 1.05rem;
            color: var(--muted);
            max-width: 520px;
            line-height: 1.65
        }

        .hero-stats {
            display: flex;
            gap: 32px;
            margin-top: 32px;
            flex-wrap: wrap
        }

        .hero-stat {
            text-align: center
        }

        .hero-stat .n {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            font-variant-numeric: tabular-nums
        }

        .hero-stat .l {
            font-size: .78rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-top: 2px
        }

        /* ── No election banner ── */
        .no-election {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 48px 32px;
            text-align: center;
            margin-bottom: 32px;
        }

        .no-election .icon {
            font-size: 48px;
            color: var(--muted2);
            margin-bottom: 16px
        }

        .no-election h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 8px
        }

        .no-election p {
            color: var(--muted);
            font-size: .95rem
        }

        /* ── Section head ── */
        .sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px
        }

        .sec-title {
            font-size: 1.25rem;
            font-weight: 700
        }

        .sec-count {
            font-size: .85rem;
            color: var(--muted);
            background: var(--surf3);
            border: 1px solid var(--border);
            padding: 3px 12px;
            border-radius: 999px
        }

        /* ── Candidate cards ── */
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-bottom: 48px
        }

        .card {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 24px;
            transition: border-color var(--tr), transform var(--tr);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .card:hover {
            border-color: var(--border2);
            transform: translateY(-2px)
        }

        .card.voted {
            border-color: rgba(195, 33, 120, .4);
            background: rgba(195, 33, 120, .04)
        }

        .cand-header {
            display: flex;
            align-items: center;
            gap: 14px
        }

        .cand-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surf3);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--muted2);
            flex-shrink: 0;
            overflow: hidden;
        }

        .cand-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .cand-name {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 2px
        }

        .cand-since {
            font-size: .78rem;
            color: var(--muted)
        }

        .cand-field {
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.5
        }

        .cand-field strong {
            color: var(--text);
            display: block;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px
        }

        .cand-text {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            color: var(--muted);
            font-size: .88rem;
            line-height: 1.55;
        }

        .cand-text.expanded {
            -webkit-line-clamp: unset;
            overflow: visible
        }

        .expand-btn {
            background: none;
            border: none;
            color: var(--p2);
            font-size: .8rem;
            cursor: pointer;
            padding: 0;
            margin-top: -4px;
            text-align: left;
        }

        /* ── Progress bar ── */
        .vote-bar-wrap {
            background: var(--surf3);
            border-radius: 999px;
            height: 6px;
            overflow: hidden
        }

        .vote-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--p), var(--p2));
            border-radius: 999px;
            transition: width .4s ease;
            min-width: 0
        }

        /* ── Vote button ── */
        .vote-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--surf3);
            border: 1px solid var(--border2);
            color: var(--text);
            border-radius: 10px;
            padding: 11px 18px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--tr);
            width: 100%;
        }

        .vote-btn:hover {
            background: rgba(195, 33, 120, .12);
            border-color: rgba(195, 33, 120, .4);
            color: var(--p2)
        }

        .vote-btn.active {
            background: rgba(195, 33, 120, .15);
            border-color: rgba(195, 33, 120, .5);
            color: var(--p2)
        }

        .vote-btn:disabled {
            opacity: .4;
            cursor: not-allowed
        }

        .vote-btn .mi {
            font-size: 1.1rem
        }

        /* ── Auth nudge ── */
        .auth-nudge {
            background: rgba(195, 33, 120, .08);
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
            font-size: .88rem;
            color: var(--muted);
        }

        .auth-nudge a {
            color: var(--p2);
            font-weight: 600
        }

        /* ── No candidates ── */
        .empty {
            text-align: center;
            padding: 64px 32px;
            color: var(--muted);
            background: var(--surf2);
            border: 1px dashed var(--border);
            border-radius: var(--r);
            margin-bottom: 40px;
        }

        .empty .mi {
            font-size: 3rem;
            margin-bottom: 12px;
            display: block;
            color: var(--muted2)
        }

        /* ── Experts panel ── */
        .experts-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 48px
        }

        .expert-card {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ex-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--surf3);
            border: 1px solid var(--border);
        }

        .ex-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .ex-name {
            font-size: .88rem;
            font-weight: 600;
            margin-bottom: 2px
        }

        .ex-rating {
            font-size: .75rem;
            color: var(--amber);
            display: flex;
            align-items: center;
            gap: 3px
        }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #1e1b2e;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 24px;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
            opacity: 0;
            transition: all .3s ease;
            pointer-events: none;
            white-space: nowrap;
            z-index: 999;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .4);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0)
        }

        .toast.ok {
            border-color: rgba(74, 222, 128, .4);
            color: var(--green)
        }

        .toast.err {
            border-color: rgba(248, 113, 113, .4);
            color: var(--red)
        }

        /* ── Timeline ── */
        .timeline {
            background: var(--surf2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 20px 24px;
            margin-bottom: 40px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap
        }

        .tl-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .88rem;
            color: var(--muted)
        }

        .tl-item strong {
            color: var(--text)
        }

        .tl-sep {
            color: var(--muted2);
            font-size: 1.2rem
        }

        @media(max-width:600px) {
            .hero {
                padding: 32px 20px
            }

            .candidates-grid {
                grid-template-columns: 1fr
            }

            .experts-list {
                grid-template-columns: repeat(2, 1fr)
            }
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- ── Hero ─────────────────────────────────────────────────────────── -->
        <section class="hero">
            <?php if ($election): ?>
                <div class="hero-badge">
                    <span class="dot"></span>
                    Выборы идут
                </div>
            <?php endif; ?>
            <h1>Выберите<br><em>экспертов</em> платформы</h1>
            <p>Эксперты — независимые рецензенты, которые проверяют игры перед публикацией. Ваш голос определяет кто войдёт в команду.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="n"><?= $totalExperts ?></div>
                    <div class="l">Экспертов</div>
                </div>
                <div class="hero-stat">
                    <div class="n"><?= $totalCandidates ?></div>
                    <div class="l">Кандидатов</div>
                </div>
                <?php if ($election): ?>
                    <div class="hero-stat">
                        <div class="n"><?= ceil((strtotime($election['end_date']) - time()) / 3600) ?>ч</div>
                        <div class="l">До конца</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($election): ?>
            <!-- ── Тайминг выборов ──────────────────────────────────────────────── -->
            <div class="timeline">
                <div class="tl-item">
                    <span class="material-icons-round" style="font-size:1rem;color:var(--green)">play_circle</span>
                    Начало: <strong><?= date('d.m.Y H:i', strtotime($election['start_date'])) ?></strong>
                </div>
                <div class="tl-sep">→</div>
                <div class="tl-item">
                    <span class="material-icons-round" style="font-size:1rem;color:var(--red)">stop_circle</span>
                    Конец: <strong><?= date('d.m.Y H:i', strtotime($election['end_date'])) ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="no-election">
                <div class="material-icons-round icon">how_to_vote</div>
                <h2>Выборы сейчас не проводятся</h2>
                <p>Следите за анонсами — следующий раунд выборов откроется совсем скоро.<br>Пока что можно посмотреть на действующих экспертов ниже.</p>
            </div>
        <?php endif; ?>

        <!-- ── Кандидаты ────────────────────────────────────────────────────── -->
        <?php if ($electionId): ?>
            <div class="sec-head">
                <div class="sec-title">Кандидаты</div>
                <?php if ($totalCandidates): ?>
                    <div class="sec-count"><?= $totalCandidates ?> в списке</div>
                <?php endif; ?>
            </div>

            <?php if ($candidates): ?>
                <div class="candidates-grid" id="candidates-grid">
                    <?php foreach ($candidates as $c):
                        $voted   = isset($myVotes[$c['expert_id']]);
                        $barPct  = $maxVotes > 0 ? round($c['votes_count'] / $maxVotes * 100) : 0;
                        $avatar  = $c['profile_picture'] ?: '';
                        $initials = mb_strtoupper(mb_substr($c['username'], 0, 1));
                    ?>
                        <div class="card <?= $voted ? 'voted' : '' ?>" id="card-<?= $c['expert_id'] ?>">
                            <div class="cand-header">
                                <div class="cand-avatar">
                                    <?php if ($avatar): ?>
                                        <img src="<?= htmlspecialchars($avatar) ?>" alt="">
                                    <?php else: ?>
                                        <?= $initials ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="cand-name"><?= htmlspecialchars($c['username']) ?></div>
                                    <div class="cand-since">
                                        Заявка <?= date('d.m.Y', strtotime($c['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($c['experience']): ?>
                                <div class="cand-field">
                                    <strong>Опыт</strong>
                                    <div class="cand-text" id="exp-<?= $c['expert_id'] ?>">
                                        <?= htmlspecialchars($c['experience']) ?>
                                    </div>
                                    <?php if (mb_strlen($c['experience']) > 120): ?>
                                        <button class="expand-btn" onclick="toggleExpand('exp-<?= $c['expert_id'] ?>', this)">Читать далее</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($c['motivation']): ?>
                                <div class="cand-field">
                                    <strong>Мотивация</strong>
                                    <div class="cand-text" id="mot-<?= $c['expert_id'] ?>">
                                        <?= htmlspecialchars($c['motivation']) ?>
                                    </div>
                                    <?php if (mb_strlen($c['motivation']) > 120): ?>
                                        <button class="expand-btn" onclick="toggleExpand('mot-<?= $c['expert_id'] ?>', this)">Читать далее</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Прогресс голосов -->
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--muted);margin-bottom:6px;">
                                    <span id="cnt-<?= $c['expert_id'] ?>"><?= $c['votes_count'] ?> голос<?= $c['votes_count'] == 1 ? '' : ($c['votes_count'] < 5 ? 'а' : 'ов') ?></span>
                                    <span><?= $barPct ?>%</span>
                                </div>
                                <div class="vote-bar-wrap">
                                    <div class="vote-bar" id="bar-<?= $c['expert_id'] ?>" style="width:<?= $barPct ?>%"></div>
                                </div>
                            </div>

                            <!-- Кнопка голосования -->
                            <?php if ($isAuthed): ?>
                                <?php if ($c['user_id'] == $userId): ?>
                                    <button class="vote-btn" disabled>
                                        <span class="material-icons-round mi">person</span>
                                        Это вы
                                    </button>
                                <?php else: ?>
                                    <button class="vote-btn <?= $voted ? 'active' : '' ?>"
                                        id="btn-<?= $c['expert_id'] ?>"
                                        onclick="vote(<?= $c['expert_id'] ?>, this)">
                                        <span class="material-icons-round mi"><?= $voted ? 'how_to_vote' : 'how_to_vote' ?></span>
                                        <span id="btn-text-<?= $c['expert_id'] ?>"><?= $voted ? 'Отозвать голос' : 'Проголосовать' ?></span>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="auth-nudge">
                                    <a href="/login">Войдите</a>, чтобы голосовать
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="empty">
                    <span class="material-icons-round">inbox</span>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:8px;">Кандидатов пока нет</div>
                    <div>Никто не подал заявку на участие в этом раунде выборов.</div>
                    <?php if ($isAuthed): ?>
                        <div style="margin-top:16px;">
                            <a href="/expert/expert-apply" style="color:var(--p2);font-weight:600;">Подать заявку самому →</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; // candidates 
            ?>
        <?php endif; // electionId 
        ?>

        <!-- ── Действующие эксперты ─────────────────────────────────────────── -->
        <?php if ($approvedExperts): ?>
            <div class="sec-head" style="margin-top:8px">
                <div class="sec-title">Действующие эксперты</div>
                <div class="sec-count"><?= $totalExperts ?></div>
            </div>
            <div class="experts-list">
                <?php foreach ($approvedExperts as $ex): ?>
                    <div class="expert-card">
                        <div class="ex-avatar">
                            <?php if ($ex['profile_picture']): ?>
                                <img src="<?= htmlspecialchars($ex['profile_picture']) ?>" alt="">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--muted2);">
                                    <?= mb_strtoupper(mb_substr($ex['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="ex-name"><?= htmlspecialchars($ex['username']) ?></div>
                            <div class="ex-rating">
                                <span class="material-icons-round" style="font-size:.9rem">star</span>
                                <?= number_format((float)$ex['rating'], 1) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="toast" id="toast"></div>

    <script>
        function showToast(msg, type = 'ok') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast show ' + type;
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.className = 'toast', 2800);
        }

        function toggleExpand(id, btn) {
            const el = document.getElementById(id);
            el.classList.toggle('expanded');
            btn.textContent = el.classList.contains('expanded') ? 'Свернуть' : 'Читать далее';
        }

        function vote(candidateId, btn) {
            btn.disabled = true;
            fetch(location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=vote&candidate_id=${candidateId}`
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        showToast(data.msg, 'err');
                        btn.disabled = false;
                        return;
                    }

                    const card = document.getElementById('card-' + candidateId);
                    const cntEl = document.getElementById('cnt-' + candidateId);
                    const barEl = document.getElementById('bar-' + candidateId);
                    const textEl = document.getElementById('btn-text-' + candidateId);

                    // Считаем новый счётчик
                    let cnt = parseInt(cntEl.textContent) || 0;
                    if (data.action === 'voted') {
                        cnt++;
                        btn.classList.add('active');
                        card.classList.add('voted');
                        textEl.textContent = 'Отозвать голос';
                        showToast('Голос отдан!', 'ok');
                    } else {
                        cnt = Math.max(0, cnt - 1);
                        btn.classList.remove('active');
                        card.classList.remove('voted');
                        textEl.textContent = 'Проголосовать';
                        showToast('Голос отозван', 'ok');
                    }

                    // Обновляем отображение
                    const suffix = cnt === 1 ? '' : cnt < 5 ? 'а' : 'ов';
                    cntEl.textContent = `${cnt} голос${suffix}`;

                    // Пересчитываем бар относительно максимума среди всех карточек
                    const allCnts = [...document.querySelectorAll('[id^="cnt-"]')]
                        .map(el => parseInt(el.textContent) || 0);
                    const maxVal = Math.max(...allCnts, 1);
                    document.querySelectorAll('[id^="bar-"]').forEach(bar => {
                        const cardId = bar.id.replace('bar-', '');
                        const cardCnt = parseInt(document.getElementById('cnt-' + cardId)?.textContent) || 0;
                        bar.style.width = Math.round(cardCnt / maxVal * 100) + '%';
                    });

                    btn.disabled = false;
                })
                .catch(() => {
                    showToast('Ошибка соединения', 'err');
                    btn.disabled = false;
                });
        }
    </script>
</body>

</html>