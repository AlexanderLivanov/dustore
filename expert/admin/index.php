<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';

$db = new Database();
$pdo = $db->connect();

$stmt = $pdo->prepare("SELECT id FROM experts WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['USERDATA']['id']]);
$isExpert = (bool) $stmt->fetch();
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
$isAdmin = ($globalRole === -1);
if (!$isExpert && !$isAdmin) { die('Доступ запрещён'); }

$pendingExperts = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='new'")->fetchColumn();
$pendingGames   = (int)$pdo->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
$totalExperts   = (int)$pdo->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();

// Разбивка по статусам модерации.
$statusCounts = ['pending'=>0,'approved'=>0,'rejected'=>0,'revision'=>0];
foreach ($pdo->query("SELECT moderation_status s, COUNT(*) c FROM games WHERE moderation_status IN ('pending','approved','rejected','revision') GROUP BY moderation_status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $statusCounts[$r['s']] = (int)$r['c'];
}

// Игры «пришли на модерацию» по дням (14 дней).
$byDay = [];
for ($i = 13; $i >= 0; $i--) $byDay[date('Y-m-d', strtotime("-$i days"))] = 0;
$rows = $pdo->query("
    SELECT DATE(COALESCE(moderation_submitted_at, created_at)) d, COUNT(*) c
    FROM games
    WHERE moderation_status IN ('pending','approved','rejected','revision')
      AND COALESCE(moderation_submitted_at, created_at) >= (CURDATE() - INTERVAL 13 DAY)
    GROUP BY d
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) if (isset($byDay[$r['d']])) $byDay[$r['d']] = (int)$r['c'];
$maxDay = max(1, max($byDay));
$arrived14 = array_sum($byDay);

$uname = $_SESSION['USERDATA']['username'] ?? '';
$active_page = 'index';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления — Dustore</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0b0e13;--surface:#131720;--surface2:#1a2030;--border:#232b3a;--accent:#4ade80;--accent2:#22d3ee;--text:#e8edf5;--muted:#6b7a99;--danger:#f87171;--warning:#fbbf24;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
        main{flex:1;overflow:auto;}
        .main-inner{padding:40px;max-width:1000px;}
        .page-header{margin-bottom:32px;}
        .eyebrow{font-size:.75rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;}
        .page-header h1{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;letter-spacing:-.5px;}
        .page-header p{color:var(--muted);font-size:.95rem;margin-top:6px;}
        .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;}
        @media(max-width:700px){.cards{grid-template-columns:1fr;}}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px;}
        .card-label{font-size:.72rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;}
        .card-value{font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;}
        .card-sub{font-size:.8rem;color:var(--muted);margin-top:4px;}
        .card-warn .card-value{color:var(--warning);} .card-cyan .card-value{color:var(--accent2);} .card-accent .card-value{color:var(--accent);}
        .chart-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px;margin-bottom:20px;}
        .chart-h{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:4px;}
        .chart-sub{font-size:.82rem;color:var(--muted);margin-bottom:18px;}
        .bars{display:flex;align-items:flex-end;gap:6px;min-height:150px;}
        .bar-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:5px;}
        .bar{width:100%;border-radius:5px 5px 0 0;background:linear-gradient(180deg,rgba(74,222,128,.85),rgba(74,222,128,.3));min-height:3px;position:relative;}
        .bar:hover{background:linear-gradient(180deg,#4ade80,rgba(74,222,128,.5));}
        .bar-lbl{font-size:.66rem;color:var(--muted);}
        .breakdown{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:20px;}
        @media(max-width:700px){.breakdown{grid-template-columns:repeat(2,1fr);}}
        .bd{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;text-align:center;}
        .bd .n{font-family:'Syne',sans-serif;font-weight:800;font-size:1.6rem;}
        .bd .l{font-size:.72rem;color:var(--muted);margin-top:2px;}
        .quick{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        @media(max-width:600px){.quick{grid-template-columns:1fr;}}
        .quick-link{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px;text-decoration:none;color:var(--text);display:flex;align-items:center;gap:16px;transition:.2s;}
        .quick-link:hover{border-color:rgba(74,222,128,.3);transform:translateY(-2px);}
        .ql-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
        .ql-green{background:rgba(74,222,128,.12);} .ql-blue{background:rgba(34,211,238,.12);} .ql-pink{background:rgba(244,114,182,.12);}
        .quick-link h3{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:4px;} .quick-link p{font-size:.82rem;color:var(--muted);}
    </style>
</head>
<body>

<?php require __DIR__ . '/_sidebar.php'; ?>

<main>
    <div class="main-inner">
        <div class="page-header">
            <div class="eyebrow">Обзор</div>
            <h1>Добро пожаловать, <?= htmlspecialchars($uname) ?>!</h1>
            <p>Заявки экспертов, модерация игр и статистика проверки.</p>
        </div>

        <div class="cards">
            <div class="card card-warn"><div class="card-label">Заявок экспертов</div><div class="card-value"><?= $pendingExperts ?></div><div class="card-sub">ожидают рассмотрения</div></div>
            <div class="card card-cyan"><div class="card-label">Игр на проверке</div><div class="card-value"><?= $pendingGames ?></div><div class="card-sub">требуют модерации</div></div>
            <div class="card card-accent"><div class="card-label">Активных экспертов</div><div class="card-value"><?= $totalExperts ?></div><div class="card-sub">одобренных участников</div></div>
        </div>

        <!-- График -->
        <div class="chart-card">
            <div class="chart-h">📊 Игры на модерации по дням</div>
            <div class="chart-sub">За 14 дней пришло на проверку: <b style="color:var(--text)"><?= $arrived14 ?></b></div>
            <?php if ($arrived14 === 0): ?>
                <div style="color:var(--muted);text-align:center;padding:20px;">За последние 14 дней игр на модерацию не поступало</div>
            <?php else: ?>
            <div class="bars">
                <?php foreach ($byDay as $day => $cnt): ?>
                    <div class="bar-wrap">
                        <div class="bar" style="height:<?= max(3, (int)round($cnt / $maxDay * 120)) ?>px;" title="<?= $cnt ?> игр · <?= date('d.m', strtotime($day)) ?>"></div>
                        <div class="bar-lbl"><?= date('d.m', strtotime($day)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="breakdown">
                <div class="bd"><div class="n" style="color:var(--warning)"><?= $statusCounts['pending'] ?></div><div class="l">⏳ На проверке</div></div>
                <div class="bd"><div class="n" style="color:var(--accent)"><?= $statusCounts['approved'] ?></div><div class="l">✅ Прошло</div></div>
                <div class="bd"><div class="n" style="color:#fb923c"><?= $statusCounts['revision'] ?></div><div class="l">🔄 На доработку</div></div>
                <div class="bd"><div class="n" style="color:var(--danger)"><?= $statusCounts['rejected'] ?></div><div class="l">❌ Отклонено</div></div>
            </div>
        </div>

        <div class="quick">
            <?php if ($isAdmin): ?>
            <a href="expert-requests" class="quick-link"><div class="ql-icon ql-green">👤</div><div><h3>Заявки экспертов</h3><p>Одобрение и отклонение новых заявок</p></div></a>
            <?php endif; ?>
            <a href="moderation" class="quick-link"><div class="ql-icon ql-blue">🎮</div><div><h3>Модерация игр</h3><p>Проверка игр, ожидающих оценки</p></div></a>
            <a href="all-reviews" class="quick-link"><div class="ql-icon ql-pink">📊</div><div><h3>Все оценки</h3><p>Аналитика по рецензиям и вердиктам</p></div></a>
        </div>
    </div>
</main>
</body>
</html>