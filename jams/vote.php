<?php
// jams/vote.php — голосование джема на консольной модели (games + jam_votes).
//   ?id отсутствует  -> список джемов
//   ?id=<sprint_id>  -> сетка одобренных игр джема с голосованием
require_once('../swad/config.php');
session_start();

if (empty($_SESSION['USERDATA']['id'])) { header('Location: /login'); exit; }
$userId    = (int)$_SESSION['USERDATA']['id'];
$sprint_id = (int)($_GET['id'] ?? $_GET['sprint_id'] ?? 0);

$pdo = (new Database())->connect();
if (!$pdo) die('Ошибка БД');

/* ─────────────────────────────  РЕЖИМ: СПИСОК ДЖЕМОВ  ───────────────────────────── */
if (!$sprint_id) {
    $jams = $pdo->query("
        SELECT s.id, s.title, s.status, s.logo_url, s.voting_start, s.voting_end,
               (SELECT COUNT(*) FROM games g WHERE g.sprint_id = s.id AND g.moderation_status = 'approved') AS games_n
        FROM sprints s
        WHERE s.status IN ('ongoing','finished') OR s.voting_start IS NOT NULL
        ORDER BY s.jam_start DESC, s.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    require_once('../swad/static/elements/header.php');
    ?>
    <!DOCTYPE html><html lang="ru"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Голосование джемов — Dustore</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
            *{margin:0;padding:0;box-sizing:border-box;}
            body{background:#0d0414;font-family:'Manrope',sans-serif;color:#e8ddf0;}
            .header{padding:13px 26px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);}
            .logo{font-weight:800;font-size:18px;} .logo .brand{color:#c32178;}
            .container{max-width:1100px;margin:0 auto;padding:32px 24px;}
            .page-title{font-size:24px;font-weight:800;margin-bottom:8px;}
            .page-sub{color:rgba(255,255,255,.4);margin-bottom:32px;}
            .jam-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
            .jam-card{background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden;
                text-decoration:none;color:#e8ddf0;transition:.2s;display:block;}
            .jam-card:hover{transform:translateY(-4px);border-color:rgba(195,33,120,.4);box-shadow:0 12px 28px rgba(195,33,120,.15);}
            .jam-logo{height:140px;background:linear-gradient(135deg,#1a0a24,#0d0414);display:flex;align-items:center;justify-content:center;overflow:hidden;}
            .jam-logo img{width:100%;height:100%;object-fit:cover;}
            .jam-body{padding:16px;}
            .jam-name{font-size:18px;font-weight:700;margin-bottom:6px;}
            .jam-meta{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:rgba(255,255,255,.5);}
            .chip{background:rgba(195,33,120,.15);border:1px solid rgba(195,33,120,.3);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;}
        </style>
    </head><body>
    <header class="header"><div class="logo"><span class="brand">Dustore</span> / Голосование</div>
        <a href="/jams" style="color:rgba(255,255,255,.5);text-decoration:none;">← К джемам</a></header>
    <div class="container">
        <div class="page-title">Голосование джемов</div>
        <div class="page-sub">Выберите джем, чтобы оценить работы участников.</div>
        <div class="jam-grid">
            <?php if (empty($jams)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:60px;color:rgba(255,255,255,.4);">Пока нет джемов на голосовании</div>
            <?php else: foreach ($jams as $j):
                $statusMap = ['ongoing'=>'Идёт','finished'=>'Завершён','registration'=>'Регистрация','draft'=>'Черновик'];
            ?>
                <a class="jam-card" href="/jams/vote.php?id=<?= (int)$j['id'] ?>">
                    <div class="jam-logo">
                        <?php if (!empty($j['logo_url'])): ?><img src="<?= htmlspecialchars($j['logo_url']) ?>" alt=""><?php else: ?><span style="font-size:48px;">🏆</span><?php endif; ?>
                    </div>
                    <div class="jam-body">
                        <div class="jam-name"><?= htmlspecialchars($j['title']) ?></div>
                        <div class="jam-meta">
                            <span class="chip"><?= $statusMap[$j['status']] ?? htmlspecialchars($j['status']) ?></span>
                            <span><?= (int)$j['games_n'] ?> игр</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

/* ─────────────────────────────  РЕЖИМ: СЕТКА ИГР ДЖЕМА  ───────────────────────────── */
$s = $pdo->prepare("SELECT id, title, status, host_user_id, jam_end, voting_start, voting_end FROM sprints WHERE id = ?");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Джем не найден');

// $isHost = ((int)$sprint['host_user_id'] === $userId);
$isHost = false;
$now = time();
$jamEnd = $sprint['jam_end']      ? strtotime($sprint['jam_end'])      : null;
$vStart = $sprint['voting_start'] ? strtotime($sprint['voting_start']) : null;
$vEnd   = $sprint['voting_end']   ? strtotime($sprint['voting_end'])   : null;
$votingOpen = (!$vStart || $vStart <= $now) && (!$vEnd || $now <= $vEnd);
// $votingOpen = true;
$canVote = !$isHost && $votingOpen;

// Роли, видящие все голоса. Пока — все.
$canSeeAllVotes = true;

// Работы открываются в момент закрытия приёма (jam_end) — вне зависимости от даты модерации.
$revealed = !$jamEnd || $jamEnd <= $now;

// Эксперт джема? (для бейджа «мой выбор» и расширенного бюджета)
$es = $pdo->prepare("SELECT id FROM sprint_experts WHERE sprint_id = ? AND user_id = ? LIMIT 1");
$es->execute([$sprint_id, $userId]);
$myExpertId = $es->fetchColumn() ?: null;
$iAmExpert  = (bool)$myExpertId;

// Все игры джема (одобренные + ещё на проверке). Порядок случайный, но стабильный для игрока.
$games = $pdo->prepare("
    SELECT g.id, g.name, g.short_description, g.icon_url, g.path_to_cover,
           g.vt_status, g.vt_report_url, g.moderation_status, g.status,
           COALESCE((SELECT SUM(points) FROM jam_votes v WHERE v.game_id = g.id AND v.sprint_id = :sid),0) AS total_points,
           (SELECT COUNT(*) FROM jam_votes v WHERE v.game_id = g.id AND v.sprint_id = :sid) AS voters,
           (SELECT points FROM jam_votes v WHERE v.game_id = g.id AND v.user_id = :uid) AS my_points
    FROM games g
    WHERE g.sprint_id = :sid
    ORDER BY RAND(:seed)
");
$games->execute(['sid' => $sprint_id, 'uid' => $userId, 'seed' => $userId]);
$games = $games->fetchAll(PDO::FETCH_ASSOC);

// Голосуемые = прошли модерацию ИЛИ уже опубликованы. Остальные — «на проверке».
$gamesLive = []; $gamesPending = [];
foreach ($games as $g) {
    (($g['moderation_status'] === 'approved') || ($g['status'] === 'published'))
        ? $gamesLive[] = $g : $gamesPending[] = $g;
}

// Бюджет: игрок — 10 очков на все игры; эксперт — по 10 на каждую (кол-во игр × 10).
$b = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM jam_votes WHERE sprint_id = ? AND user_id = ?");
$b->execute([$sprint_id, $userId]);
$usedSum   = (int)$b->fetchColumn();
$budget    = $iAmExpert ? 10 * max(1, count($gamesLive)) : 10;
$remaining = $budget - $usedSum;

// Play-to-vote: какие игры игрок уже открывал.
$played = [];
$pl = $pdo->prepare("SELECT game_id FROM jam_plays WHERE sprint_id = ? AND user_id = ?");
$pl->execute([$sprint_id, $userId]);
foreach ($pl->fetchAll(PDO::FETCH_COLUMN) as $gp) $played[(int)$gp] = true;

// Пики экспертов: game_id -> [имена].
$picks = [];
try {
    $pk = $pdo->prepare("
        SELECT p.game_id, COALESCE(se.external_name, u.username, 'Эксперт') AS name
        FROM sprint_expert_picks p
        JOIN sprint_experts se ON se.id = p.expert_id
        LEFT JOIN users u ON u.id = se.user_id
        WHERE p.sprint_id = ?
    ");
    $pk->execute([$sprint_id]);
    foreach ($pk->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $picks[(int)$row['game_id']][] = $row['name'];
    }
} catch (Exception $e) { /* таблицы ещё нет — просто без плашек */ }

// Мои пики (если я эксперт) — какие игры я уже отметил.
$myPicks = [];
if ($iAmExpert) {
    try {
        $mp = $pdo->prepare("SELECT game_id FROM sprint_expert_picks WHERE sprint_id = ? AND expert_id = ?");
        $mp->execute([$sprint_id, $myExpertId]);
        foreach ($mp->fetchAll(PDO::FETCH_COLUMN) as $g2) $myPicks[(int)$g2] = true;
    } catch (Exception $e) {}
}

// Все голоса (для тех, кому можно): game_id -> [ [username, points, is_expert] ].
$allVotes = [];
if ($canSeeAllVotes) {
    $av = $pdo->prepare("
        SELECT v.game_id, v.points, v.is_expert, u.username
        FROM jam_votes v JOIN users u ON u.id = v.user_id
        WHERE v.sprint_id = ? ORDER BY v.points DESC
    ");
    $av->execute([$sprint_id]);
    foreach ($av->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $allVotes[(int)$row['game_id']][] = $row;
    }
}

require_once('../swad/static/elements/header.php');
?>
<!DOCTYPE html><html lang="ru"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Оценка — <?= htmlspecialchars($sprint['title']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0d0414;font-family:'Manrope',sans-serif;color:#e8ddf0;}
        .header{padding:13px 26px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);}
        .logo{font-weight:800;font-size:18px;} .logo .brand{color:#c32178;}
        .container{max-width:1200px;margin:0 auto;padding:32px 24px;}
        .page-title{font-size:24px;font-weight:800;margin-bottom:8px;}
        .page-sub{color:rgba(255,255,255,.4);margin-bottom:20px;}
        .budget-box{position:sticky;top:12px;z-index:50;background:rgba(195,33,120,.15);border:1px solid rgba(195,33,120,.3);
            border-radius:16px;padding:12px 20px;display:inline-block;margin-bottom:24px;}
        .games-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;}
        .game-card{background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden;transition:.2s;display:flex;flex-direction:column;}
        .game-card:hover{border-color:rgba(195,33,120,.4);}
        .cover{height:150px;background:linear-gradient(135deg,#1a0a24,#0d0414);display:flex;align-items:center;justify-content:center;overflow:hidden;text-decoration:none;position:relative;}
        .cover img{width:100%;height:100%;object-fit:cover;}
        .vt-badge{position:absolute;top:8px;right:8px;font-size:10px;padding:3px 8px;border-radius:20px;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.2);}
        .info{padding:16px;flex:1;display:flex;flex-direction:column;gap:8px;}
        .title-row{display:flex;justify-content:space-between;align-items:baseline;gap:8px;}
        .g-title{font-size:18px;font-weight:700;text-decoration:none;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .g-title:hover{color:#c32178;}
        .g-desc{font-size:12px;color:rgba(255,255,255,.45);line-height:1.5;max-height:36px;overflow:hidden;}
        .expert-badges{display:flex;flex-wrap:wrap;gap:6px;}
        .expert-badge{font-size:11px;font-weight:700;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:20px;padding:3px 10px;}
        .agg{font-size:12px;color:rgba(255,255,255,.5);display:flex;justify-content:space-between;align-items:center;}
        .link-all{background:none;border:none;color:#c32178;font-size:12px;cursor:pointer;padding:0;}
        .all-votes{display:none;margin-top:6px;background:rgba(0,0,0,.3);border-radius:10px;padding:8px 12px;max-height:160px;overflow-y:auto;}
        .all-votes.open{display:block;}
        .av-row{display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid rgba(255,255,255,.05);}
        .vote-row{display:flex;align-items:center;gap:8px;margin-top:auto;padding-top:8px;border-top:1px solid rgba(255,255,255,.07);}
        .vote-row select{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#e8ddf0;padding:7px 10px;font-size:13px;}
        .btn{border:none;border-radius:8px;padding:8px 14px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;}
        .btn-p{background:#c32178;color:#fff;} .btn-p:hover{background:#9e1a66;}
        .btn-g{background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);}
        .my-badge{font-size:12px;color:#c32178;font-weight:700;}
        .open-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.6);text-decoration:none;}
        .open-link:hover{color:#fff;}
        .toast{position:fixed;bottom:24px;right:24px;background:#160822;border:1px solid #c32178;border-radius:12px;padding:12px 18px;opacity:0;transition:.2s;z-index:1100;}
        .toast.show{opacity:1;}
    </style>
</head><body>

<div class="container">
    <a href="/jams/participant.php?sprint_id=<?= (int)$sprint_id ?>" style="display:inline-block;margin-bottom:16px;color:#c32178;text-decoration:none;">← К странице джема</a>
    <div class="page-title">Оцените игры</div>
    <div class="page-sub"><?= htmlspecialchars($sprint['title']) ?> — <?= $iAmExpert ? 'вы эксперт: до 10 баллов каждой игре' : 'распределите до 10 голосов между играми (0–10 на игру)' ?>. Голос можно менять и отменять до конца голосования.</div>

    <?php if ($isHost): ?>
        <div class="budget-box" style="background:rgba(107,122,153,.12);border-color:rgba(107,122,153,.3);">Вы организатор джема — голосовать нельзя, но видны результаты.</div>
    <?php elseif (!$votingOpen): ?>
        <div class="budget-box" style="background:rgba(107,122,153,.12);border-color:rgba(107,122,153,.3);">Голосование закрыто. Результаты ниже.</div>
    <?php elseif ($revealed): ?>
        <div class="budget-box">Осталось баллов: <strong id="remaining"><?= $remaining ?></strong> / <?= $budget ?><?= $iAmExpert ? ' · режим эксперта' : '' ?></div>
    <?php endif; ?>

    <?php if ($revealed): ?>
        <div style="text-align:center;padding:80px 20px;color:rgba(255,255,255,.55);">
            <div style="font-size:44px;margin-bottom:10px;">🔒</div>
            <div style="font-size:18px;font-weight:800;margin-bottom:6px;">Работы откроются <?= date('d.m.Y H:i', $jamEnd) ?></div>
            <div style="font-size:13px;">Список игр и голосование станут доступны в момент старта.</div>
        </div>
    <?php else: ?>

    <div class="games-grid">
        <?php if (empty($gamesLive)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:60px;color:rgba(255,255,255,.4);">Проверенных работ пока нет<?= $gamesPending ? ' — см. раздел «на проверке» ниже' : '' ?></div>
        <?php else: foreach ($gamesLive as $g):
            $gid = (int)$g['id'];
            $cover = $g['icon_url'] ?: ($g['path_to_cover'] ?: '');
            $my = $g['my_points'] !== null ? (int)$g['my_points'] : null;
        ?>
        <div class="game-card" id="card-<?= $gid ?>" data-points="<?= $my ?? 0 ?>">
            <a class="cover" href="/g/<?= $gid ?>" target="_blank" rel="noopener" onclick="markPlayed(<?= $gid ?>)">
                <?php if ($cover): ?><img src="<?= htmlspecialchars($cover) ?>" alt=""><?php else: ?><span style="font-size:48px;">🎮</span><?php endif; ?>
                <?php if (!empty($g['vt_report_url'])): ?>
                    <a class="vt-badge" href="<?= htmlspecialchars($g['vt_report_url']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();" title="Отчёт проверки на вирусы">🛡 проверено</a>
                <?php endif; ?>
            </a>
            <div class="info">
                <div class="title-row">
                    <a class="g-title" href="/g/<?= $gid ?>" target="_blank" rel="noopener" onclick="markPlayed(<?= $gid ?>)"><?= htmlspecialchars($g['name']) ?></a>
                    <a class="open-link" href="/g/<?= $gid ?>" target="_blank" rel="noopener" onclick="markPlayed(<?= $gid ?>)">открыть ↗</a>
                </div>
                <?php if (!empty($g['short_description'])): ?><div class="g-desc"><?= htmlspecialchars($g['short_description']) ?></div><?php endif; ?>

                <?php if (!empty($picks[$gid])): ?>
                    <div class="expert-badges">
                        <?php foreach ($picks[$gid] as $name): ?>
                            <span class="expert-badge">🏅 Выбор эксперта: <?= htmlspecialchars($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($iAmExpert): $mine = isset($myPicks[$gid]); ?>
                    <button class="btn <?= $mine ? 'btn-p' : 'btn-g' ?>" id="pick-<?= $gid ?>" onclick="togglePick(<?= $gid ?>)" style="font-size:12px;padding:6px 12px;align-self:flex-start;">
                        <?= $mine ? '★ Ваш выбор эксперта' : '☆ Отметить как выбор' ?>
                    </button>
                <?php endif; ?>

                <div class="agg">
                    <span><strong id="pts-<?= $gid ?>"><?= (int)$g['total_points'] ?></strong> очков · <span id="vtr-<?= $gid ?>"><?= (int)$g['voters'] ?></span> голос.</span>
                    <?php if ($canSeeAllVotes && !empty($allVotes[$gid])): ?>
                        <button class="link-all" onclick="toggleVotes(<?= $gid ?>)">показать голоса</button>
                    <?php endif; ?>
                </div>

                <?php if ($canSeeAllVotes && !empty($allVotes[$gid])): ?>
                    <div class="all-votes" id="av-<?= $gid ?>">
                        <?php foreach ($allVotes[$gid] as $v): ?>
                            <div class="av-row">
                                <span><?= htmlspecialchars($v['username']) ?><?= $v['is_expert'] ? ' 🏅' : '' ?></span>
                                <span><?= (int)$v['points'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canVote): $isPlayed = isset($played[$gid]) || $my; ?>
                <div class="vote-row" id="vote-row-<?= $gid ?>" data-played="<?= $isPlayed ? '1' : '0' ?>">
                    <select id="sel-<?= $gid ?>"<?= $isPlayed ? '' : ' disabled' ?>>
                        <?php for ($v = 0; $v <= 10; $v++): ?>
                            <option value="<?= $v ?>"<?= ($my ?? 0) === $v ? ' selected' : '' ?>><?= $v ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-p" id="btn-save-<?= $gid ?>" onclick="saveVote(<?= $gid ?>)"<?= $isPlayed ? '' : ' disabled style="opacity:.5;cursor:not-allowed;"' ?>>Отдать</button>
                    <button class="btn btn-g" id="btn-cancel-<?= $gid ?>" onclick="cancelVote(<?= $gid ?>)" style="<?= $my ? '' : 'display:none;' ?>">Отменить</button>
                    <span class="my-badge" id="my-<?= $gid ?>"><?= $my ? "вы: $my" : '' ?></span>
                    <span id="hint-<?= $gid ?>" style="<?= $isPlayed ? 'display:none;' : '' ?>font-size:11px;color:#fbbf24;">← откройте игру, чтобы голосовать</span>
                </div>
                <?php elseif ($my): ?>
                    <div class="vote-row"><span class="my-badge">вы отдали: <?= $my ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if (!empty($gamesPending)): ?>
    <div style="margin-top:36px;">
        <div style="font-size:16px;font-weight:800;margin-bottom:4px;">⏳ На проверке экспертами</div>
        <div style="font-size:12px;color:rgba(255,255,255,.4);margin-bottom:16px;">Эти работы прикреплены к джему, но ещё не прошли модерацию. Голосование за них откроется после одобрения.</div>
        <div class="games-grid">
            <?php foreach ($gamesPending as $g):
                $gid = (int)$g['id'];
                $cover = $g['icon_url'] ?: ($g['path_to_cover'] ?: '');
            ?>
            <div class="game-card" style="opacity:.7;">
                <div class="cover">
                    <?php if ($cover): ?><img src="<?= htmlspecialchars($cover) ?>" alt="" style="filter:grayscale(.4);"><?php else: ?><span style="font-size:48px;">🎮</span><?php endif; ?>
                    <span class="vt-badge" style="background:rgba(251,191,36,.18);border-color:rgba(251,191,36,.4);color:#fbbf24;">на проверке</span>
                </div>
                <div class="info">
                    <div class="g-title" style="color:#fff;"><?= htmlspecialchars($g['name']) ?></div>
                    <?php if (!empty($g['short_description'])): ?><div class="g-desc"><?= htmlspecialchars($g['short_description']) ?></div><?php endif; ?>
                    <div class="agg"><span style="color:#fbbf24;">⏳ Ожидает одобрения экспертов</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; /* конец гейта показа работ */ ?>
</div>

<div id="toast" class="toast"></div>

<script>
const SPRINT_ID = <?= (int)$sprint_id ?>;
const MY_EXPERT_ID = <?= (int)($myExpertId ?? 0) ?>;

async function togglePick(gid) {
    const btn = document.getElementById('pick-' + gid);
    const on = btn.classList.contains('btn-p');
    const r = await fetch('/swad/controllers/jams/set_expert_pick.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid, expert_id: MY_EXPERT_ID, action: on ? 'remove' : 'add' })
    }).then(r => r.json());
    if (!r.success) { showToast(r.message || 'Ошибка', true); return; }
    if (on) { btn.classList.replace('btn-p', 'btn-g'); btn.textContent = '☆ Отметить как выбор'; }
    else    { btn.classList.replace('btn-g', 'btn-p'); btn.textContent = '★ Ваш выбор эксперта'; }
    showToast(r.message);
}

function showToast(msg, err) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.borderColor = err ? '#f44336' : '#c32178';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function toggleVotes(gid) { document.getElementById('av-' + gid).classList.toggle('open'); }

async function send(gid, points) {
    const resp = await fetch('/swad/controllers/jams/save_vote.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid, points })
    });
    return resp.json();
}

async function saveVote(gid) {
    const points = parseInt(document.getElementById('sel-' + gid).value) || 0;
    const d = await send(gid, points);
    if (!d.success) { showToast(d.message || 'Ошибка', true); return; }
    afterVote(gid, d);
    showToast(d.message);
}

async function cancelVote(gid) {
    const d = await send(gid, 0);
    if (!d.success) { showToast(d.message || 'Ошибка', true); return; }
    const sel = document.getElementById('sel-' + gid); if (sel) sel.value = 0;
    afterVote(gid, d);
    showToast('Голос отменён');
}

function afterVote(gid, d) {
    const rem = document.getElementById('remaining'); if (rem) rem.textContent = d.remaining_budget;
    document.getElementById('pts-' + gid).textContent = d.game_points;
    document.getElementById('vtr-' + gid).textContent = d.game_voters;
    const my = document.getElementById('my-' + gid);
    if (my) my.textContent = d.my_points ? ('вы: ' + d.my_points) : '';
    const cancel = document.getElementById('btn-cancel-' + gid);
    if (cancel) cancel.style.display = d.my_points ? '' : 'none';
}

// Play-to-vote: игрок открыл игру -> фиксируем и разблокируем голосование по ней.
async function markPlayed(gid) {
    try {
        await fetch('/swad/controllers/jams/jam_play.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid })
        });
    } catch (e) {}
    unlockVote(gid);
}
function unlockVote(gid) {
    const row = document.getElementById('vote-row-' + gid);
    if (!row || row.dataset.played === '1') return;
    row.dataset.played = '1';
    const sel = document.getElementById('sel-' + gid); if (sel) sel.disabled = false;
    const btn = document.getElementById('btn-save-' + gid); if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.style.cursor = ''; }
    const hint = document.getElementById('hint-' + gid); if (hint) hint.style.display = 'none';
}
</script>
</body></html>