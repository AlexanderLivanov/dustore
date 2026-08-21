<?php
// jams/results.php — итоговая статистика джема: что популярно и понравилось.
// Байес считаем в PHP (без зависимости от вью). Доступ — админам джема.
require_once('../swad/config.php');
session_start();

$db = (new Database())->connect();
if (!$db) die('Ошибка БД');

$allowedAdmins = ['TheCreator', 'asfasgag', 'Eshward_Williams', 'testuser'];
$username   = $_SESSION['USERDATA']['username'] ?? '';
$globalRole = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
if (!in_array($username, $allowedAdmins) && $globalRole !== -1) die('У вас нет доступа');

$sprint_id = (int)($_GET['id'] ?? 0);
if (!$sprint_id) die('Не указан джем (?id=)');

$sp = $db->prepare("SELECT id, title, status, voting_start, voting_end FROM sprints WHERE id = ?");
$sp->execute([$sprint_id]);
$sprint = $sp->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Джем не найден');

$COND = "g.sprint_id = :sid AND (g.moderation_status = 'approved' OR g.status = 'published')";

// Агрегаты по играм.
$rows = $db->prepare("
    SELECT g.id, g.name, COALESCE(NULLIF(TRIM(g.genre),''),'Не указан') AS genre,
           COALESCE(SUM(v.points),0) AS points, COUNT(v.id) AS voters
    FROM games g
    LEFT JOIN jam_votes v ON v.game_id = g.id AND v.sprint_id = :sid
    WHERE $COND
    GROUP BY g.id, g.name, genre
");
$rows->execute(['sid' => $sprint_id]);
$games = $rows->fetchAll(PDO::FETCH_ASSOC);

// Открытия (play-to-vote) по играм.
$opens = [];
$op = $db->prepare("SELECT game_id, COUNT(*) c FROM jam_plays WHERE sprint_id = ? GROUP BY game_id");
$op->execute([$sprint_id]);
foreach ($op->fetchAll(PDO::FETCH_ASSOC) as $r) $opens[(int)$r['game_id']] = (int)$r['c'];

// Пики экспертов по играм.
$pickCount = []; $pickNames = [];
try {
    $pk = $db->prepare("
        SELECT p.game_id, COALESCE(se.external_name, u.username, 'Эксперт') AS name
        FROM sprint_expert_picks p
        JOIN sprint_experts se ON se.id = p.expert_id
        LEFT JOIN users u ON u.id = se.user_id
        WHERE p.sprint_id = ?");
    $pk->execute([$sprint_id]);
    foreach ($pk->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $gid = (int)$r['game_id'];
        $pickCount[$gid] = ($pickCount[$gid] ?? 0) + 1;
        $pickNames[$gid][] = $r['name'];
    }
} catch (Throwable $e) {}

// Глобальные суммы + байес.
$totalPoints = array_sum(array_map(fn($g) => (int)$g['points'], $games));
$totalVoters = array_sum(array_map(fn($g) => (int)$g['voters'], $games));
$uniqVoters  = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM jam_votes WHERE sprint_id = " . (int)$sprint_id)->fetchColumn();
$mean = $totalVoters > 0 ? $totalPoints / $totalVoters : 0;
$C = 8; // сила приора байеса

foreach ($games as &$g) {
    $g['id']      = (int)$g['id'];
    $g['points']  = (int)$g['points'];
    $g['voters']  = (int)$g['voters'];
    $g['avg']     = $g['voters'] > 0 ? round($g['points'] / $g['voters'], 2) : 0;
    $g['bayes']   = round(($C * $mean + $g['points']) / ($C + $g['voters']), 2);
    $g['opens']   = $opens[$g['id']] ?? 0;
    $g['conv']    = $g['opens'] > 0 ? round($g['voters'] / $g['opens'] * 100) : 0;
    $g['picks']   = $pickCount[$g['id']] ?? 0;
    $g['pnames']  = $pickNames[$g['id']] ?? [];
}
unset($g);

// Рейтинг по байесу.
$byBayes = $games;
usort($byBayes, fn($a, $b) => $b['bayes'] <=> $a['bayes'] ?: $b['points'] <=> $a['points']);

// Популярность по жанрам/идеям.
$genres = [];
foreach ($games as $g) {
    $k = $g['genre'];
    if (!isset($genres[$k])) $genres[$k] = ['games' => 0, 'points' => 0, 'voters' => 0];
    $genres[$k]['games']++; $genres[$k]['points'] += $g['points']; $genres[$k]['voters'] += $g['voters'];
}
uasort($genres, fn($a, $b) => $b['points'] <=> $a['points']);
$maxGenrePts = max(1, max(array_map(fn($x) => $x['points'], $genres ?: [['points' => 1]])));

// Распределение баллов (1..10).
$hist = array_fill(1, 10, 0);
$hr = $db->prepare("SELECT points, COUNT(*) c FROM jam_votes WHERE sprint_id = ? AND points BETWEEN 1 AND 10 GROUP BY points");
$hr->execute([$sprint_id]);
foreach ($hr->fetchAll(PDO::FETCH_ASSOC) as $r) $hist[(int)$r['points']] = (int)$r['c'];
$maxHist = max(1, max($hist));

// Топы.
$topByPoints = $games; usort($topByPoints, fn($a, $b) => $b['points'] <=> $a['points']);
$topByOpens  = $games; usort($topByOpens,  fn($a, $b) => $b['opens']  <=> $a['opens']);
$coverage = $uniqVoters > 0 ? round($totalVoters / $uniqVoters, 1) : 0;

require_once('../swad/static/elements/header.php');
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
$medal = ['🥇', '🥈', '🥉'];
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Итоги — <?= h($sprint['title']) ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
    *{box-sizing:border-box;margin:0;padding:0;}
    body{background:#0d0414;font-family:'Manrope',sans-serif;color:#e8ddf0;}
    .header{padding:13px 26px;display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);}
    .logo{font-weight:800;} .logo .brand{color:#c32178;}
    .nav-btn{padding:7px 14px;border-radius:7px;background:rgba(255,255,255,.05);color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;font-weight:600;}
    .nav-btn:hover{background:rgba(255,255,255,.1);color:#fff;}
    .container{max-width:1200px;margin:0 auto;padding:28px 24px;}
    .page-title{font-size:22px;font-weight:800;margin-bottom:4px;}
    .page-sub{color:rgba(255,255,255,.4);font-size:13px;margin-bottom:22px;}
    .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px;}
    .stat-card{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px;}
    .sc-val{font-size:22px;font-weight:800;} .sc-lbl{font-size:11px;color:rgba(255,255,255,.35);margin-top:2px;}
    .card{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:18px;margin-bottom:18px;}
    .card-title{font-size:14px;font-weight:700;margin-bottom:14px;}
    .podium{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .pod{background:rgba(195,33,120,.08);border:1px solid rgba(195,33,120,.25);border-radius:14px;padding:16px;text-align:center;}
    .pod .m{font-size:34px;} .pod .n{font-size:16px;font-weight:800;margin:6px 0 2px;}
    .pod .s{font-size:12px;color:rgba(255,255,255,.5);}
    table{width:100%;border-collapse:collapse;}
    thead th{padding:9px 12px;text-align:left;font-size:10px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.07);}
    tbody td{padding:9px 12px;font-size:12px;color:rgba(255,255,255,.75);border-bottom:1px solid rgba(255,255,255,.04);}
    tbody tr:hover{background:rgba(195,33,120,.05);}
    .rank{font-weight:800;color:#c32178;width:34px;}
    .pill{border-radius:20px;padding:2px 9px;font-size:10px;font-weight:700;background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3);}
    .two{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    @media(max-width:820px){.two{grid-template-columns:1fr;} .stats-row{grid-template-columns:repeat(2,1fr);}}
    .row-bar{display:flex;align-items:center;gap:10px;margin-bottom:9px;}
    .row-bar .lbl{width:120px;font-size:12px;color:#e8ddf0;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .row-bar .track{flex:1;height:12px;background:rgba(255,255,255,.06);border-radius:6px;overflow:hidden;}
    .row-bar .fill{height:100%;background:linear-gradient(90deg,#c32178,#9e1a66);border-radius:6px;}
    .row-bar .val{width:90px;text-align:right;font-size:12px;color:rgba(255,255,255,.5);flex-shrink:0;}
    .bars{display:flex;align-items:flex-end;gap:6px;min-height:120px;}
    .bar-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:4px;}
    .bar{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,rgba(195,33,120,.85),rgba(195,33,120,.3));min-height:3px;}
    .bar-lbl{font-size:10px;color:rgba(255,255,255,.4);}
    .muted{color:rgba(255,255,255,.4);}
</style></head><body>
<header class="header">
    <div class="logo">🏆 <span class="brand">Dustore</span> / Итоги джема</div>
    <div style="display:flex;gap:8px;">
        <a class="nav-btn" href="/jams/admin.php?id=<?= (int)$sprint_id ?>">← В админку</a>
        <a class="nav-btn" href="/jams/admin_votes.php?id=<?= (int)$sprint_id ?>">Журнал голосов</a>
    </div>
</header>
<div class="container">
    <div class="page-title">Итоги: <?= h($sprint['title']) ?></div>
    <div class="page-sub">Что понравилось и зашло по результатам голосования.</div>

    <?php if ($totalVoters === 0): ?>
        <div class="card muted" style="text-align:center;padding:40px;">Голосов ещё нет — статистика появится, когда игроки начнут голосовать.</div>
    <?php else: ?>

    <div class="stats-row">
        <div class="stat-card"><div class="sc-val"><?= count($games) ?></div><div class="sc-lbl">Игр в зачёте</div></div>
        <div class="stat-card"><div class="sc-val"><?= $uniqVoters ?></div><div class="sc-lbl">Проголосовало</div></div>
        <div class="stat-card"><div class="sc-val"><?= $totalVoters ?></div><div class="sc-lbl">Всего голосов</div></div>
        <div class="stat-card"><div class="sc-val"><?= $totalPoints ?></div><div class="sc-lbl">Очков роздано</div></div>
        <div class="stat-card"><div class="sc-val"><?= $coverage ?></div><div class="sc-lbl">Игр на игрока</div></div>
    </div>

    <!-- Пьедестал -->
    <div class="card">
        <div class="card-title">🏆 Победители (по байесовскому среднему)</div>
        <div class="podium">
            <?php for ($i = 0; $i < 3; $i++): $g = $byBayes[$i] ?? null; if (!$g) continue; ?>
                <div class="pod">
                    <div class="m"><?= $medal[$i] ?></div>
                    <div class="n"><?= h($g['name']) ?></div>
                    <div class="s"><?= $g['points'] ?> очк. · <?= $g['voters'] ?> голос. · байес <?= $g['bayes'] ?></div>
                    <?php if ($g['picks']): ?><div style="margin-top:6px;"><span class="pill">🏅 выбор эксперта</span></div><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Полный рейтинг -->
    <div class="card">
        <div class="card-title">📊 Полный рейтинг</div>
        <table>
            <thead><tr><th>#</th><th>Игра</th><th>Жанр</th><th>Очки</th><th>Голосов</th><th>Средний</th><th>Байес</th><th>Открытий</th><th>Конверсия</th><th>Эксперты</th></tr></thead>
            <tbody>
                <?php foreach ($byBayes as $i => $g): ?>
                    <tr>
                        <td class="rank"><?= $i + 1 ?></td>
                        <td><?= h($g['name']) ?></td>
                        <td class="muted"><?= h($g['genre']) ?></td>
                        <td><strong><?= $g['points'] ?></strong></td>
                        <td><?= $g['voters'] ?></td>
                        <td><?= $g['avg'] ?></td>
                        <td><?= $g['bayes'] ?></td>
                        <td class="muted"><?= $g['opens'] ?></td>
                        <td class="muted"><?= $g['opens'] ? $g['conv'] . '%' : '—' ?></td>
                        <td><?= $g['picks'] ? '🏅 ' . h(implode(', ', $g['pnames'])) : '<span class="muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="muted" style="font-size:11px;margin-top:10px;">Байес сглаживает перекос «мало голосов — высокий средний». Конверсия = проголосовавших / открывших.</div>
    </div>

    <div class="two">
        <!-- Жанры / идеи -->
        <div class="card">
            <div class="card-title">💡 Что зашло: популярность по жанрам</div>
            <?php foreach ($genres as $name => $gd): ?>
                <div class="row-bar">
                    <span class="lbl"><?= h($name) ?></span>
                    <span class="track"><span class="fill" style="width:<?= round($gd['points'] / $maxGenrePts * 100) ?>%;"></span></span>
                    <span class="val"><?= $gd['points'] ?> очк · <?= $gd['games'] ?> игр</span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Распределение баллов -->
        <div class="card">
            <div class="card-title">🎚 Как раздавали баллы</div>
            <div class="bars">
                <?php for ($v = 1; $v <= 10; $v++): ?>
                    <div class="bar-wrap">
                        <div class="bar" style="height:<?= max(3, (int)round($hist[$v] / $maxHist * 100)) ?>px;" title="<?= $hist[$v] ?> голос. по <?= $v ?>"></div>
                        <div class="bar-lbl"><?= $v ?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="muted" style="font-size:11px;margin-top:8px;">Сколько раз ставили каждую оценку (1–10).</div>
        </div>
    </div>

    <!-- Самые запускаемые + эксперты vs народ -->
    <div class="two">
        <div class="card">
            <div class="card-title">👀 Самые запускаемые</div>
            <table>
                <thead><tr><th>Игра</th><th>Открытий</th><th>Голосов</th><th>Конв.</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($topByOpens, 0, 8) as $g): if (!$g['opens']) continue; ?>
                        <tr><td><?= h($g['name']) ?></td><td><?= $g['opens'] ?></td><td><?= $g['voters'] ?></td><td class="muted"><?= $g['conv'] ?>%</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-title">🏅 Эксперты vs игроки</div>
            <?php $expPicked = array_values(array_filter($byBayes, fn($g) => $g['picks'] > 0)); ?>
            <?php if (!$expPicked): ?>
                <div class="muted" style="padding:12px 0;">Эксперты пока не отметили выбор.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Игра эксперта</th><th>Место у народа</th><th>Очки</th></tr></thead>
                    <tbody>
                        <?php foreach ($expPicked as $g):
                            $rank = array_search($g['id'], array_map(fn($x) => $x['id'], $byBayes)) + 1; ?>
                            <tr><td><?= h($g['name']) ?> <span class="pill">🏅</span></td><td>#<?= $rank ?></td><td><?= $g['points'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="muted" style="font-size:11px;margin-top:8px;">Совпадает ли вкус экспертов с народным голосованием.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>
</body></html>