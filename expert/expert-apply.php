<?php
session_start();
require_once __DIR__ . '/../swad/config.php';

$db = new Database();
$pdo = $db->connect();

// Check if user is logged in
$user = $_SESSION['USERDATA'] ?? null;
if (!$user) {
    header("Location: /login");
    exit;
}

// Check if user has email
if (empty($user['email'])) {
    $noEmail = true;
}

// Check if user already has a pending or approved application
$stmt = $pdo->prepare("SELECT status FROM experts WHERE user_id = ?");
$stmt->execute([$user['id']]);
$existingApp = $stmt->fetch(PDO::FETCH_ASSOC);

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'");
$totalRequests = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM experts");
$allRequests = $stmt->fetchColumn();

$nextStart = strtotime('2026-03-01 12:00:00');
$nextEnd   = strtotime('2026-03-31 23:59:59');
$now       = time();
$timeToStart = max(0, $nextStart - $now);
$timeToEnd   = max(0, $nextEnd   - $now);
$days = floor($timeToEnd / 86400);
$hours = floor(($timeToEnd % 86400) / 3600);
$minutes = floor(($timeToEnd % 3600) / 60);
$seconds = $timeToEnd % 60;


$isOpen = ($now >= $nextStart && $now <= $nextEnd);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стать экспертом — Dustore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
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
            overflow-x: hidden;
        }

        /* Background mesh */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 40% at 20% 10%, rgba(74, 222, 128, .06) 0%, transparent 70%),
                radial-gradient(ellipse 50% 50% at 80% 80%, rgba(34, 211, 238, .05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── NAV ── */
        nav {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            height: 64px;
            background: rgba(11, 14, 19, .85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .nav-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -.5px;
            color: var(--accent);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 8px;
        }

        .nav-links a {
            font-size: .875rem;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            transition: color .2s, background .2s;
        }

        .nav-links a:hover {
            color: var(--text);
            background: var(--surface2);
        }

        /* ── LAYOUT ── */
        .page {
            position: relative;
            z-index: 1;
            max-width: 740px;
            margin: 0 auto;
            padding: 60px 24px 80px;
        }

        .eyebrow {
            font-family: 'Syne', sans-serif;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 18px;
        }

        .subtitle {
            font-size: 1rem;
            color: var(--muted);
            line-height: 1.6;
            max-width: 540px;
            margin-bottom: 40px;
        }

        /* ── STATS BAR ── */
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 16px;
            font-size: .875rem;
        }

        .stat-chip .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            flex-shrink: 0;
        }

        .stat-chip .dot.orange {
            background: var(--warning);
            box-shadow: 0 0 8px var(--warning);
        }

        .stat-chip .dot.cyan {
            background: var(--accent2);
            box-shadow: 0 0 8px var(--accent2);
        }

        .stat-chip strong {
            color: var(--text);
        }

        .stat-chip span {
            color: var(--muted);
        }

        /* ── STATUS CARDS (pending / no email) ── */
        .status-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            animation: fadeUp .5s ease both;
        }

        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 24px;
        }

        .status-icon.pending {
            background: rgba(251, 191, 36, .12);
        }

        .status-icon.approved {
            background: rgba(74, 222, 128, .12);
        }

        .status-icon.rejected {
            background: rgba(248, 113, 113, .12);
        }

        .status-icon.info {
            background: rgba(34, 211, 238, .12);
        }

        .status-card h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .status-card p {
            color: var(--muted);
            line-height: 1.6;
            max-width: 380px;
            margin: 0 auto;
        }

        /* ── FORM ── */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            animation: fadeUp .5s ease both;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media(max-width:600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
        }

        .field label {
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field input,
        .field textarea {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            transition: border-color .2s, box-shadow .2s;
            resize: vertical;
        }

        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(74, 222, 128, .12);
        }

        .field input[readonly] {
            opacity: .55;
            cursor: not-allowed;
        }

        .field textarea {
            min-height: 110px;
        }

        .hint {
            font-size: .78rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 28px 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all .2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: #0b0e13;
            width: 100%;
            justify-content: center;
            font-size: 1rem;
            padding: 15px;
        }

        .btn-primary:hover {
            background: #22c55e;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, .25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* closed state */
        .closed-badge {
            display: inline-block;
            background: rgba(248, 113, 113, .12);
            color: var(--danger);
            border: 1px solid rgba(248, 113, 113, .25);
            border-radius: 6px;
            padding: 4px 12px;
            font-size: .8rem;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }

        .open-badge {
            display: inline-block;
            background: rgba(74, 222, 128, .12);
            color: var(--accent);
            border: 1px solid rgba(74, 222, 128, .25);
            border-radius: 6px;
            padding: 4px 12px;
            font-size: .8rem;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <nav>
        <a href="/" class="nav-logo">Dustore</a>
        <div class="nav-links">
            <a href="/">Главная</a>
            <?php if ($user): ?>
                <a href="/me">@<?= htmlspecialchars($user['username'] ?? 'Профиль') ?></a>
            <?php else: ?>
                <a href="/login">Войти</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="page">
        <div class="eyebrow">Экспертная программа</div>
        <h1>Стать экспертом<br>Dustore</h1>
        <p class="subtitle">Эксперты оценивают indie-игры по чек-листу и формируют рейтинги. Ваше мнение влияет на то, какие игры увидит сообщество.</p>

        <!-- Stats -->
        <div class="stats-bar">
            <div class="stat-chip">
                <span class="dot"></span>
                <strong><?= $allRequests ?></strong>
                <span>заявок подано</span>
            </div>
            <div class="stat-chip">
                <span class="dot"></span>
                <strong><?= $totalRequests ?></strong>
                <span>заявок на рассмотрении</span>
            </div>
            <?php if ($isOpen): ?>
                <div class="stat-chip">
                    <span class="dot orange"></span>
                    <strong>Набор открыт</strong>
                    <span>ещё <?= ("$days дней $hours часов $minutes минут $seconds секунд") ?></span>
                </div>
            <?php elseif ($timeToStart > 0): ?>
                <div class="stat-chip">
                    <span class="dot cyan"></span>
                    <strong>Набор откроется</strong>
                    <span>через <?= gmdate("H:i:s", $timeToStart) ?></span>
                </div>
            <?php else: ?>
                <div class="stat-chip">
                    <span class="dot" style="background:var(--danger)"></span>
                    <strong>Набор закрыт</strong>
                    <span>следите за анонсами</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($noEmail)): ?>
            <!-- No email -->
            <div class="status-card">
                <div class="status-icon info">📧</div>
                <h2>Необходим email</h2>
                <p>Для подачи заявки на эксперта необходимо указать email в профиле, в разделе "Безопасность".</p>
                <br>
                <a href="/me" class="btn btn-primary" style="max-width:240px;margin:0 auto;">Перейти в профиль</a>
            </div>

        <?php elseif ($existingApp): ?>
            <!-- Already applied -->
            <?php
            $s = $existingApp['status'];
            $icons = ['new' => '⏳', 'approved' => '✅', 'rejected' => '❌'];
            $titles = ['new' => 'Заявка на рассмотрении', 'approved' => 'Вы одобрены!', 'rejected' => 'Заявка отклонена'];
            $texts  = [
                'new'      => 'Ваша заявка принята и ожидает рассмотрения администраторами. Мы уведомим вас по email.',
                'approved' => 'Поздравляем! Теперь вы официальный эксперт Dustore. Войдите в панель для начала работы.',
                'rejected' => 'К сожалению, ваша заявка была отклонена. Вы можете подать новую заявку в следующий набор.',
            ];
            ?>
            <div class="status-card">
                <div class="status-icon <?= $s === 'new' ? 'pending' : $s ?>">
                    <?= $icons[$s] ?? '❓' ?>
                </div>
                <h2><?= $titles[$s] ?? 'Статус неизвестен' ?></h2>
                <p><?= $texts[$s] ?? '' ?></p>
                <?php if ($s === 'approved'): ?>
                    <br>
                    <a href="/expert/admin" class="btn btn-primary" style="max-width:240px;margin:0 auto;">Открыть панель эксперта</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Application form -->
            <div class="form-card">
                <form method="post" action="submit-expert.php">

                    <div class="form-row">
                        <div class="field">
                            <label>Никнейм</label>
                            <input type="text" name="nickname" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                            <span class="hint">Берётся из вашего профиля</span>
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                            <span class="hint">Берётся из вашего профиля</span>
                        </div>
                    </div>

                    <div class="field">
                        <label>Ваш игровой опыт</label>
                        <textarea name="experience" required placeholder="Расскажите, сколько лет вы играете, в какие жанры, с какими платформами работали..."></textarea>
                    </div>

                    <div class="field">
                        <label>Мотивация</label>
                        <textarea name="motivation" required placeholder="Почему вы хотите стать экспертом и как планируете оценивать игры?"></textarea>
                    </div>

                    <div class="field">
                        <label>Профиль / Портфолио <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0">(необязательно)</span></label>
                        <input type="url" name="profile" placeholder="https://...">
                        <span class="hint">Ссылка на соцсети, Steam, itch.io и т.д.</span>
                    </div>

                    <hr class="divider">

                    <button type="submit" class="btn btn-primary" <?= !$isOpen ? 'disabled title="Набор закрыт"' : '' ?>>
                        <?= $isOpen ? 'Отправить заявку →' : 'Набор закрыт' ?>
                    </button>

                </form>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>