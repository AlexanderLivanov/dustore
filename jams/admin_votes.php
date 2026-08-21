<?php
// jams/admin_votes.php — полная аналитика голосов джема для админки.
// Кто, за что, сколько и когда проголосовал + лидерборд (байес) + пики экспертов.
require_once('../swad/config.php');
session_start();

$db = (new Database())->connect();
if (!$db) die('Ошибка БД');

$allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];
$username   = $_SESSION['USERDATA']['username'] ?? '';
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
if (!in_array($username, $allowedAdmins) && $globalRole !== -1) die('У вас нет доступа к админке');

$sprint_id = (int)($_GET['id'] ?? 0);
if (!$sprint_id) die('Не указан джем (?id=)');

$s = $db->prepare("SELECT id, title, status, voting_start, voting_end FROM sprints WHERE id = ?");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Джем не найден');

// Сводка.
$sum = $db->prepare("SELECT COUNT(*) AS rows_n, COUNT(DISTINCT user_id) AS voters, COALESCE(SUM(points),0) AS pts FROM jam_votes WHERE sprint_id = ?");
$sum->execute([$sprint_id]);
$summary = $sum->fetch(PDO::FETCH_ASSOC);
$gamesN = (int)$db->query("SELECT COUNT(*) FROM games WHERE sprint_id = " . (int)$sprint_id . " AND moderation_status = 'approved'")->fetchColumn();

// Лидерборд (байесовское среднее из вью) + агрегаты.
$lb = $db->prepare("
    SELECT g.id, g.name,
           COALESCE(l.votes_n,0)        AS voters,
           COALESCE(l.points_weighted,0) AS points,
           l.bayes_score
    FROM games g
    LEFT JOIN v_jam_leaderboard l ON l.game_id = g.id AND l.sprint_id = :sid
    WHERE g.sprint_id = :sid
    ORDER BY l.bayes_score DESC, points DESC
");
$lb->execute(['sid' => $sprint_id]);
$leaderboard = $lb->fetchAll(PDO::FETCH_ASSOC);

// Полный журнал голосов.
$log = $db->prepare("
    SELECT v.points, v.is_expert, v.ip, v.created_at, v.updated_at,
           u.username, g.name AS game_name
    FROM jam_votes v
    JOIN users u ON u.id = v.user_id
    JOIN games g ON g.id = v.game_id
    WHERE v.sprint_id = ?
    ORDER BY v.created_at DESC
");
$log->execute([$sprint_id]);
$votes = $log->fetchAll(PDO::FETCH_ASSOC);

// Пики экспертов.
$pk = $db->prepare("
    SELECT g.name AS game_name, COALESCE(se.external_name, u.username, 'Эксперт') AS expert
    FROM sprint_expert_picks p
    JOIN games g ON g.id = p.game_id
    JOIN sprint_experts se ON se.id = p.expert_id
    LEFT JOIN users u ON u.id = se.user_id
    WHERE p.sprint_id = ?
    ORDER BY g.name
");
$pk->execute([$sprint_id]);
$picks = $pk->fetchAll(PDO::FETCH_ASSOC);

// Голоса по дням (14 дней).
$byDay = [];
for ($i = 13; $i >= 0; $i--) $byDay[date('Y-m-d', strtotime("-$i days"))] = 0;
$dRows = $db->prepare("SELECT DATE(created_at) d, COUNT(*) c FROM jam_votes WHERE sprint_id = ? GROUP BY DATE(created_at)");
$dRows->execute([$sprint_id]);
foreach ($dRows->fetchAll(PDO::FETCH_ASSOC) as $r) if (isset($byDay[$r['d']])) $byDay[$r['d']] = (int)$r['c'];
$maxDay = max(1, max($byDay));

require_once('../swad/static/elements/header.php');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Аналитика голосов — <?= h($sprint['title']) ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;}
    body{background:#0d0414;font-family:'Manrope',sans-serif;color:#e8ddf0;}
    .header{padding:13px 26px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);}
    .logo{font-weight:800;} .logo .brand{color:#c32178;}
    .nav-btn{padding:7px 14px;border-radius:7px;background:rgba(255,255,255,.05);color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;font-weight:600;}
    .nav-btn:hover{background:rgba(255,255,255,.1);color:#fff;}
    .container{max-width:1200px;margin:0 auto;padding:28px 24px;}
    .page-title{font-size:22px;font-weight:800;margin-bottom:4px;}
    .page-sub{color:rgba(255,255,255,.4);font-size:13px;margin-bottom:22px;}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;}
    .stat-card{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:16px;}
    .sc-val{font-size:24px;font-weight:800;} .sc-lbl{font-size:11px;color:rgba(255,255,255,.35);margin-top:2px;}
    .card{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:18px;margin-bottom:18px;}
    .card-title{font-size:14px;font-weight:700;margin-bottom:14px;}
    table{width:100%;border-collapse:collapse;}
    thead th{padding:9px 12px;text-align:left;font-size:10px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.07);}
    tbody td{padding:9px 12px;font-size:12px;color:rgba(255,255,255,.75);border-bottom:1px solid rgba(255,255,255,.04);}
    tbody tr:hover{background:rgba(195,33,120,.05);}
    .rank{font-weight:800;color:#c32178;width:34px;}
    .pill{border-radius:20px;padding:2px 9px;font-size:10px;font-weight:700;}
    .pill-exp{background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3);}
    .tbl-search{width:100%;max-width:320px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:8px 12px;color:#e8ddf0;font-size:12px;outline:none;margin-bottom:12px;}
    .tbl-search:focus{border-color:#c32178;}
    .bars{display:flex;align-items:flex-end;gap:6px;min-height:124px;}
    .bar-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:4px;}
    .bar{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,rgba(195,33,120,.85),rgba(195,33,120,.3));min-height:3px;}
    .bar-lbl{font-size:9px;color:rgba(255,255,255,.35);}
    .muted{color:rgba(255,255,255,.4);}
</style></head><body>
<header class="header">
    <div class="logo">🎮 <span class="brand">Dustore</span> / Аналитика голосов</div>
    <div style="display:flex;gap:8px;">
        <a class="nav-btn" href="/jams/admin.php?id=<?= (int)$sprint_id ?>">← В админку</a>
        <a class="nav-btn" href="/jams/vote.php?id=<?= (int)$sprint_id ?>" target="_blank">Страница голосования ↗</a>
    </div>
</header>
<div class="container">
    <div class="page-title">Голоса — <?= h($sprint['title']) ?></div>
    <div class="page-sub">Полный журнал: кто, за что, сколько и когда проголосовал.</div>

    <div class="stats-row">
        <div class="stat-card"><div class="sc-val"><?= (int)$summary['rows_n'] ?></div><div class="sc-lbl">Всего голосов</div></div>
        <div class="stat-card"><div class="sc-val"><?= (int)$summary['voters'] ?></div><div class="sc-lbl">Проголосовало игроков</div></div>
        <div class="stat-card"><div class="sc-val"><?= (int)$summary['pts'] ?></div><div class="sc-lbl">Очков распределено</div></div>
        <div class="stat-card"><div class="sc-val"><?= $gamesN ?></div><div class="sc-lbl">Игр на голосовании</div></div>
    </div>

    <div class="card">
        <div class="card-title">📈 Голоса по дням (14 дней)</div>
        <?php if ((int)$summary['rows_n'] === 0): ?>
            <div class="muted" style="padding:24px;text-align:center;">Голосов ещё нет</div>
        <?php else: ?>
        <div class="bars">
            <?php foreach ($byDay as $day => $cnt): ?>
                <div class="bar-wrap">
                    <div class="bar" style="height:<?= max(3, (int)round($cnt / $maxDay * 100)) ?>px;" title="<?= $cnt ?> голос. · <?= date('d.m', strtotime($day)) ?>"></div>
                    <div class="bar-lbl"><?= date('d.m', strtotime($day)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">🏆 Лидерборд (байесовское среднее)</div>
        <table>
            <thead><tr><th>#</th><th>Игра</th><th>Очки</th><th>Голосов</th><th>Байес</th></tr></thead>
            <tbody>
                <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="5" class="muted" style="text-align:center;padding:24px;">Голосов ещё нет</td></tr>
                <?php else: foreach ($leaderboard as $i => $r): ?>
                    <tr>
                        <td class="rank"><?= $i + 1 ?></td>
                        <td><?= h($r['name']) ?></td>
                        <td><strong><?= (int)$r['points'] ?></strong></td>
                        <td><?= (int)$r['voters'] ?></td>
                        <td><?= $r['bayes_score'] !== null ? round($r['bayes_score'], 2) : '—' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($picks): ?>
    <div class="card">
        <div class="card-title">🏅 Выбор экспертов</div>
        <table>
            <thead><tr><th>Игра</th><th>Эксперт</th></tr></thead>
            <tbody>
                <?php foreach ($picks as $p): ?>
                    <tr><td><?= h($p['game_name']) ?></td><td><span class="pill pill-exp"><?= h($p['expert']) ?></span></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">🗳 Журнал голосов (<?= count($votes) ?>)</div>
        <input class="tbl-search" placeholder="Поиск по игроку или игре..." oninput="filterLog(this.value)">
        <table>
            <thead><tr><th>Время</th><th>Игрок</th><th>Игра</th><th>Очки</th><th>Роль</th><th>IP</th></tr></thead>
            <tbody id="log-tbody">
                <?php if (empty($votes)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:24px;">Голосов ещё нет</td></tr>
                <?php else: foreach ($votes as $v):
                    $ipStr = !empty($v['ip']) ? @inet_ntop($v['ip']) : '';
                    $changed = $v['updated_at'] && $v['created_at'] && $v['updated_at'] !== $v['created_at'];
                ?>
                    <tr data-search="<?= h(mb_strtolower($v['username'] . ' ' . $v['game_name'])) ?>">
                        <td class="muted"><?= date('d.m.Y H:i', strtotime($v['created_at'])) ?><?= $changed ? ' <span class="muted" title="изменён ' . h(date('d.m H:i', strtotime($v['updated_at']))) . '">✎</span>' : '' ?></td>
                        <td><?= h($v['username']) ?></td>
                        <td><?= h($v['game_name']) ?></td>
                        <td><strong style="color:#c32178;"><?= (int)$v['points'] ?></strong></td>
                        <td><?= $v['is_expert'] ? '<span class="pill pill-exp">эксперт</span>' : '<span class="muted">игрок</span>' ?></td>
                        <td class="muted"><?= h($ipStr) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function filterLog(q) {
    q = (q || '').toLowerCase();
    document.querySelectorAll('#log-tbody tr').forEach(tr => {
        const s = tr.dataset.search || '';
        tr.style.display = s.includes(q) ? '' : 'none';
    });
}
</script>
</body></html>