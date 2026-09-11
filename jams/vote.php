<?php
/**
 * jams/vote.php — голосование джемов (games + jam_votes).
 *   ?id отсутствует  -> список джемов
 *   ?id=<sprint_id>  -> сетка работ джема с голосованием
 *
 * Правки против прошлой версии — см. комментарии по месту, коротко:
 *   - $isHost вычисляется (раньше был захардкожен в false, и организатор
 *     мог голосовать в собственном джеме);
 *   - автор не может голосовать за свою работу;
 *   - поимённый список голосов больше не показывается во время голосования;
 *   - прогресс, фильтры, поиск, оценка в один клик.
 */

date_default_timezone_set('Europe/Moscow');
require_once('../swad/config.php');
session_start();
/* Токен нужен save_vote.php и set_expert_pick.php. Эти два файла надо
   выкатывать ВМЕСТЕ с этим: сначала контроллеры, потом страница — иначе
   между заливками голосование будет отвечать «сессия устарела». */
require_once('../swad/controllers/csrf.php');

/* ─────────────────────────────  НАСТРОЙКИ ВИДИМОСТИ  ─────────────────────────────
 * Кто и когда видит результаты. Держим здесь, чтобы не искать по коду.
 *
 * SHOW_VOTER_NAMES — показывать, КТО сколько поставил. Раньше стояло «всегда».
 * Во время открытого голосования это плохо: участники видят оценки друг друга,
 * подстраиваются под большинство и мстят за низкий балл. Полный журнал и так
 * доступен в админке (jams/admin_votes.php), так что здесь он лишний.
 *
 * SHOW_TOTALS_DURING_VOTING — показывать суммы очков, пока голосование идёт.
 * Оставлено включённым, чтобы не менять привычное поведение, но если хочешь
 * убрать эффект «все голосуют за лидера» — поставь false. */
const SHOW_VOTER_NAMES            = 'after_close';   // 'never' | 'after_close' | 'always'
const SHOW_TOTALS_DURING_VOTING   = true;

if (empty($_SESSION['USERDATA']['id'])) { header('Location: /login'); exit; }
$userId    = (int)$_SESSION['USERDATA']['id'];
$sprint_id = (int)($_GET['id'] ?? $_GET['sprint_id'] ?? 0);

$pdo = (new Database())->connect();
if (!$pdo) die('Ошибка БД');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Заглушка обложки — инлайновый SVG, не зависит от внешних сервисов. */
$COVER_FALLBACK = 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180">'
    . '<rect width="320" height="180" fill="#160620"/>'
    . '<path d="M132 76h56v32h-56z" fill="none" stroke="#5d4a6b" stroke-width="3"/>'
    . '<circle cx="146" cy="88" r="5" fill="#5d4a6b"/>'
    . '</svg>'
);

/* ═════════════════════════════  РЕЖИМ: СПИСОК ДЖЕМОВ  ═════════════════════════════ */
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
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Голосование джемов — Dustore</title>
        <link rel="stylesheet" href="/swad/css/vote.css">
    </head>
    <body>
    <main class="jv-wrap">
        <div class="jv-shell">
            <div class="jv-head">
                <div>
                    <h1 class="jv-title">Голосование джемов</h1>
                    <div class="jv-sub">Выберите джем, чтобы оценить работы участников.</div>
                </div>
                <a href="/jams" class="jv-back">← К джемам</a>
            </div>

            <div class="jv-bar">
                <div class="jv-controls">
                    <div class="jv-search" id="jamSearchBar">
                        <span class="jv-search-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                        <input type="text" id="jamSearch" placeholder="Поиск джема..." autocomplete="off">
                        <button type="button" class="jv-search-clear" id="jamSearchClear" aria-label="Очистить">&times;</button>
                    </div>
                </div>
            </div>

            <div class="jv-count" id="jamCount"><?= count($jams) ?></div>

            <div class="jv-jam-grid" id="jamGrid">
                <?php if (empty($jams)): ?>
                    <div class="jv-empty"><strong>Пока пусто</strong>Нет джемов на голосовании</div>
                <?php else: foreach ($jams as $j):
                    $now = time();
                    $vs = $j['voting_start'] ? strtotime($j['voting_start']) : null;
                    $ve = $j['voting_end']   ? strtotime($j['voting_end'])   : null;
                    if ($vs && $ve && $now >= $vs && $now <= $ve)      { $lbl = 'Идёт голосование'; $cls = 'live'; }
                    elseif ($ve && $now > $ve)                          { $lbl = 'Завершён';         $cls = 'over'; }
                    elseif ($vs && $now < $vs)                          { $lbl = 'Скоро';            $cls = ''; }
                    else { $lbl = $j['status'] === 'ongoing' ? 'Идёт' : 'Завершён'; $cls = $j['status'] === 'finished' ? 'over' : ''; }
                ?>
                    <a class="jv-jam" href="/jams/vote.php?id=<?= (int)$j['id'] ?>" data-title="<?= h(mb_strtolower($j['title'])) ?>">
                        <div class="jv-jam-logo">
                            <img src="<?= h($j['logo_url'] ?: $COVER_FALLBACK) ?>" alt="" loading="lazy" decoding="async">
                        </div>
                        <div class="jv-jam-body">
                            <div class="jv-jam-name"><?= h($j['title']) ?></div>
                            <div class="jv-jam-meta">
                                <span class="jv-badge <?= $cls ?>"><?= $lbl ?></span>
                                <span><?= (int)$j['games_n'] ?> работ</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>

    <script>
    (function () {
        'use strict';
        const input = document.getElementById('jamSearch');
        const clear = document.getElementById('jamSearchClear');
        const bar   = document.getElementById('jamSearchBar');
        const count = document.getElementById('jamCount');
        const cards = Array.from(document.querySelectorAll('.jv-jam'));
        const plural = n => { const d=n%10,h=n%100;
            if (d===1&&h!==11) return 'джем'; if (d>=2&&d<=4&&(h<12||h>14)) return 'джема'; return 'джемов'; };

        function apply() {
            const q = input.value.toLowerCase().trim();
            let n = 0;
            cards.forEach(c => {
                const hit = !q || (c.dataset.title || '').includes(q);
                c.hidden = !hit;
                if (hit) n++;
            });
            bar.classList.toggle('has-value', q !== '');
            count.textContent = n + ' ' + plural(n);
        }
        input.addEventListener('input', apply);
        clear.addEventListener('click', () => { input.value = ''; apply(); input.focus(); });
        apply();

        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            let active = null;
            document.addEventListener('mousemove', e => {
                const el = e.target.closest('.jv-jam');
                if (el !== active && active) { active.style.transform = ''; active = null; }
                if (!el) return;
                active = el;
                const hw = el.offsetWidth / 2, hh = el.offsetHeight / 2;
                if (!hw || !hh) return;
                const r = el.getBoundingClientRect();
                const lift = el.style.transform ? -6 : 0;
                const nx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / hw));
                const ny = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2 - lift)) / hh));
                el.style.transform = 'perspective(800px) rotateX(' + (-10 * ny).toFixed(2) + 'deg) rotateY('
                    + (10 * nx).toFixed(2) + 'deg) translateY(-6px) scale(1.02)';
            });
        }
    })();
    </script>
    </body>
    </html>
    <?php
    exit;
}

/* ═════════════════════════════  РЕЖИМ: РАБОТЫ ДЖЕМА  ═════════════════════════════ */
$s = $pdo->prepare("SELECT id, title, status, host_user_id, jam_end, voting_start, voting_end FROM sprints WHERE id = ?");
$s->execute([$sprint_id]);
$sprint = $s->fetch(PDO::FETCH_ASSOC);
if (!$sprint) die('Джем не найден');

$now    = time();
$vStart = $sprint['voting_start'] ? strtotime($sprint['voting_start']) : null;
$vEnd   = $sprint['voting_end']   ? strtotime($sprint['voting_end'])   : null;

/* Раньше здесь стояло $isHost = false; — переменная объявлялась, но никогда
   не вычислялась, хотя host_user_id уже был в выборке. В итоге организатор
   джема мог голосовать за работы в собственном джеме. */
$isHost = ((int)$sprint['host_user_id'] === $userId);

$votingOpen = (!$vStart || $vStart <= $now) && (!$vEnd || $now <= $vEnd);
$revealed   = !$vStart || $vStart <= $now;
$votingOver = $vEnd && $now > $vEnd;
$canVote    = !$isHost && $votingOpen;

$showTotals = SHOW_TOTALS_DURING_VOTING || !$votingOpen;
$showNames  = SHOW_VOTER_NAMES === 'always'
           || (SHOW_VOTER_NAMES === 'after_close' && !$votingOpen);

$es = $pdo->prepare("SELECT id FROM sprint_experts WHERE sprint_id = ? AND user_id = ? LIMIT 1");
$es->execute([$sprint_id, $userId]);
$myExpertId = $es->fetchColumn() ?: null;
$iAmExpert  = (bool)$myExpertId;

/* Свои студии — чтобы автор не голосовал за собственную работу.
   Покрывает владельца студии; участники команды через staff сюда не попадают,
   для них нужен отдельный проход (см. заметку в ответе). */
$myStudios = [];
$ms = $pdo->prepare("SELECT id FROM studios WHERE owner_id = ?");
$ms->execute([$userId]);
foreach ($ms->fetchAll(PDO::FETCH_COLUMN) as $sid) $myStudios[(int)$sid] = true;

/* Порядок работ — свой у каждого пользователя, но стабильный между заходами:
   иначе первые карточки собирали бы больше голосов просто за счёт позиции. */
$cookieName = 'dustore_jam_seed_' . $sprint_id;
if (empty($_COOKIE[$cookieName]) || !preg_match('/^[a-f0-9]{32}$/', $_COOKIE[$cookieName])) {
    $seed = bin2hex(random_bytes(16));
    setcookie($cookieName, $seed, [
        'expires'  => time() + 60*60*24*365, 'path' => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    $_COOKIE[$cookieName] = $seed;
} else {
    $seed = $_COOKIE[$cookieName];
}
/* substr(...,0,8) даёт до 0xFFFFFFFF — влезает в signed BIGINT, RAND() доволен. */
$orderSeed = (int)hexdec(substr($seed, 0, 8));

$q = $pdo->prepare("
    SELECT g.id, g.name, g.short_description, g.icon_url, g.path_to_cover, g.developer,
           g.vt_status, g.vt_report_url, g.moderation_status, g.status,
           COALESCE((SELECT SUM(points) FROM jam_votes v WHERE v.game_id = g.id AND v.sprint_id = :sid), 0) AS total_points,
           (SELECT COUNT(*) FROM jam_votes v WHERE v.game_id = g.id AND v.sprint_id = :sid) AS voters,
           (SELECT points FROM jam_votes v WHERE v.game_id = g.id AND v.user_id = :uid AND v.sprint_id = :sid) AS my_points
    FROM games g WHERE g.sprint_id = :sid ORDER BY RAND(:seed)
");
$q->bindValue(':sid',  $sprint_id, PDO::PARAM_INT);
$q->bindValue(':uid',  $userId,    PDO::PARAM_INT);
$q->bindValue(':seed', $orderSeed, PDO::PARAM_INT);
$q->execute();
$games = $q->fetchAll(PDO::FETCH_ASSOC);

$gamesLive = $gamesPending = [];
foreach ($games as $g) {
    (($g['moderation_status'] === 'approved') || ($g['status'] === 'published'))
        ? $gamesLive[] = $g : $gamesPending[] = $g;
}

$b = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM jam_votes WHERE sprint_id = ? AND user_id = ?");
$b->execute([$sprint_id, $userId]);
$usedSum   = (int)$b->fetchColumn();
$budget    = $iAmExpert ? 10 * max(1, count($gamesLive)) : 10;
$remaining = $budget - $usedSum;

$played = [];
$pl = $pdo->prepare("SELECT game_id FROM jam_plays WHERE sprint_id = ? AND user_id = ?");
$pl->execute([$sprint_id, $userId]);
foreach ($pl->fetchAll(PDO::FETCH_COLUMN) as $gp) $played[(int)$gp] = true;

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
    foreach ($pk->fetchAll(PDO::FETCH_ASSOC) as $row) $picks[(int)$row['game_id']][] = $row['name'];
} catch (Exception $e) { /* таблицы ещё нет — просто без плашек */ }

$myPicks = [];
if ($iAmExpert) {
    try {
        $mp = $pdo->prepare("SELECT game_id FROM sprint_expert_picks WHERE sprint_id = ? AND expert_id = ?");
        $mp->execute([$sprint_id, $myExpertId]);
        foreach ($mp->fetchAll(PDO::FETCH_COLUMN) as $g2) $myPicks[(int)$g2] = true;
    } catch (Exception $e) {}
}

$allVotes = [];
if ($showNames) {
    $av = $pdo->prepare("
        SELECT v.game_id, v.points, v.is_expert, u.username
        FROM jam_votes v JOIN users u ON u.id = v.user_id
        WHERE v.sprint_id = ? ORDER BY v.points DESC
    ");
    $av->execute([$sprint_id]);
    foreach ($av->fetchAll(PDO::FETCH_ASSOC) as $row) $allVotes[(int)$row['game_id']][] = $row;
}

/* Прогресс: сколько работ уже оценено ненулевым баллом. */
$ratedCount = 0;
foreach ($gamesLive as $g) if ((int)$g['my_points'] > 0) $ratedCount++;
$liveCount = count($gamesLive);

require_once('../swad/static/elements/header.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Оценка — <?= h($sprint['title']) ?></title>
    <link rel="stylesheet" href="/swad/css/vote.css">
</head>
<body>
<main class="jv-wrap">
    <div class="jv-shell">

        <div class="jv-head">
            <div>
                <h1 class="jv-title">Оцените работы</h1>
                <div class="jv-sub"><?= h($sprint['title']) ?><?php if ($vEnd): ?> · голосование до <?= date('d.m.Y H:i', $vEnd) ?><?php endif; ?></div>
            </div>
            <a href="/jams/participant.php?sprint_id=<?= (int)$sprint_id ?>" class="jv-back">← К странице джема</a>
        </div>

        <div class="jv-rules" id="rules">
            <button type="button" class="jv-rules-btn" id="rulesBtn" aria-expanded="false">
                Как устроено голосование
                <svg class="jv-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="jv-rules-body">
                <p><b>Участники:</b> 10 баллов на все работы — распределяйте как считаете нужным.</p>
                <p><b>Эксперты:</b> по 10 баллов на каждую работу.</p>
                <p><b>Сначала откройте работу</b> — оценка разблокируется после этого.</p>
                <p><b>Оценку можно менять</b> до конца голосования: нажмите другую цифру, 0 — снять голос.</p>
                <p><b>За свою работу голосовать нельзя.</b></p>
            </div>
        </div>

        <?php if (!$revealed): ?>
            <div class="jv-empty" style="padding:80px 20px;">
                <strong>Работы откроются <?= date('d.m.Y H:i', $vStart) ?></strong>
                Список и голосование станут доступны в момент старта.
            </div>
        <?php else: ?>

        <div class="jv-bar">
            <div class="jv-stats">
                <?php if ($isHost): ?>
                    <div class="jv-note">Вы организатор джема — голосовать нельзя, результаты видны.</div>
                <?php elseif (!$votingOpen): ?>
                    <div class="jv-note"><?= $votingOver ? 'Голосование завершено.' : 'Голосование ещё не открыто.' ?> Результаты ниже.</div>
                <?php else: ?>
                    <div class="jv-stat">
                        <span class="jv-stat-label">Осталось баллов</span>
                        <span class="jv-stat-value"><b id="remaining"><?= $remaining ?></b> из <?= $budget ?><?= $iAmExpert ? ' · эксперт' : '' ?></span>
                        <span class="jv-meter"><i id="budgetMeter" style="width:<?= $budget ? max(0, min(100, round($remaining / $budget * 100))) : 0 ?>%"></i></span>
                    </div>
                    <div class="jv-stat">
                        <span class="jv-stat-label">Оценено работ</span>
                        <span class="jv-stat-value"><b id="ratedN"><?= $ratedCount ?></b> из <?= $liveCount ?></span>
                        <span class="jv-meter <?= $ratedCount >= $liveCount && $liveCount ? 'done' : '' ?>" id="rateMeterWrap"><i id="rateMeter" style="width:<?= $liveCount ? round($ratedCount / $liveCount * 100) : 0 ?>%"></i></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="jv-controls">
                <div class="jv-seg" id="filters">
                    <button type="button" class="jv-chip-btn active" data-filter="all">Все<span class="jv-n" data-n="all"></span></button>
                    <button type="button" class="jv-chip-btn" data-filter="unrated">Не оценённые<span class="jv-n" data-n="unrated"></span></button>
                    <button type="button" class="jv-chip-btn" data-filter="rated">Оценённые<span class="jv-n" data-n="rated"></span></button>
                    <button type="button" class="jv-chip-btn" data-filter="unopened">Не открытые<span class="jv-n" data-n="unopened"></span></button>
                </div>
                <div class="jv-search" id="searchBar">
                    <span class="jv-search-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                    <input type="text" id="gameSearch" placeholder="Поиск работы..." autocomplete="off">
                    <button type="button" class="jv-search-clear" id="searchClear" aria-label="Очистить">&times;</button>
                </div>
            </div>
        </div>

        <div class="jv-count" id="resultCount"></div>

        <div class="jv-grid" id="gamesGrid">
            <?php if (empty($gamesLive)): ?>
                <div class="jv-empty"><strong>Проверенных работ пока нет</strong><?= $gamesPending ? 'Смотрите раздел «на проверке» ниже' : 'Загляните позже' ?></div>
            <?php else: foreach ($gamesLive as $g):
                $gid     = (int)$g['id'];
                $cover   = $g['icon_url'] ?: ($g['path_to_cover'] ?: $COVER_FALLBACK);
                $my      = $g['my_points'] !== null ? (int)$g['my_points'] : 0;
                $isMine  = !empty($g['developer']) && isset($myStudios[(int)$g['developer']]);
                $opened  = isset($played[$gid]) || $my > 0;
                $canRate = $canVote && !$isMine;
            ?>
            <article class="jv-card<?= $my > 0 ? ' rated' : '' ?>"
                     id="card-<?= $gid ?>"
                     data-name="<?= h(mb_strtolower($g['name'])) ?>"
                     data-rated="<?= $my > 0 ? '1' : '0' ?>"
                     data-opened="<?= $opened ? '1' : '0' ?>">

                <a class="jv-cover" href="/g/<?= $gid ?>" target="_blank" rel="noopener" data-open="<?= $gid ?>">
                    <img src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async">
                    <div class="jv-tags">
                        <?php if ($isMine): ?><span class="jv-tag mine">ваша работа</span><?php endif; ?>
                        <?php if (!empty($g['vt_report_url'])): ?>
                            <span class="jv-tag ok">проверено</span>
                        <?php endif; ?>
                        <?php if (!empty($picks[$gid])): ?><span class="jv-tag gold">выбор эксперта</span><?php endif; ?>
                    </div>
                </a>

                <div class="jv-body">
                    <a class="jv-name" href="/g/<?= $gid ?>" target="_blank" rel="noopener" data-open="<?= $gid ?>"><?= h($g['name']) ?></a>
                    <?php if (!empty($g['short_description'])): ?>
                        <div class="jv-desc"><?= h($g['short_description']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($picks[$gid])): ?>
                        <div class="jv-desc" style="color:var(--jv-gold)">Выбор экспертов: <?= h(implode(', ', $picks[$gid])) ?></div>
                    <?php endif; ?>

                    <?php if ($iAmExpert && !$isMine): $mine = isset($myPicks[$gid]); ?>
                        <button type="button" class="jv-chip-btn <?= $mine ? 'active' : '' ?>" id="pick-<?= $gid ?>"
                                data-pick="<?= $gid ?>" style="align-self:flex-start"><?= $mine ? '★ ваш выбор' : '☆ отметить как выбор' ?></button>
                    <?php endif; ?>

                    <?php if ($showTotals): ?>
                    <div class="jv-agg">
                        <span><b id="pts-<?= $gid ?>"><?= (int)$g['total_points'] ?></b> очк. · <span id="vtr-<?= $gid ?>"><?= (int)$g['voters'] ?></span> голос.</span>
                        <?php if ($showNames && !empty($allVotes[$gid])): ?>
                            <button type="button" class="jv-link" data-votes="<?= $gid ?>">кто голосовал</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($showNames && !empty($allVotes[$gid])): ?>
                        <div class="jv-votes" id="av-<?= $gid ?>">
                            <?php foreach ($allVotes[$gid] as $v): ?>
                                <div class="jv-vrow"><span><?= h($v['username']) ?><?= $v['is_expert'] ? ' 🏅' : '' ?></span><span><?= (int)$v['points'] ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <div class="jv-rate">
                        <a class="jv-play<?= $opened ? ' done' : '' ?>" id="play-<?= $gid ?>"
                           href="/g/<?= $gid ?>" target="_blank" rel="noopener" data-open="<?= $gid ?>">
                            <?= $opened ? 'Открыть ещё раз ↗' : 'Открыть работу ↗' ?>
                        </a>

                        <?php if ($isMine): ?>
                            <div class="jv-hint" style="color:var(--jv-muted)">Своя работа — оценка недоступна.</div>
                        <?php elseif ($canRate): ?>
                            <div class="jv-scale<?= $opened ? '' : ' locked' ?>" id="scale-<?= $gid ?>" role="group" aria-label="Оценка">
                                <?php for ($v = 0; $v <= 10; $v++): ?>
                                    <button type="button" class="jv-pt<?= $v === $my ? ' on' : '' ?>"
                                            data-vote="<?= $gid ?>" data-points="<?= $v ?>"
                                            <?= $opened ? '' : 'disabled' ?>><?= $v ?></button>
                                <?php endfor; ?>
                            </div>
                            <div class="jv-hint" id="hint-<?= $gid ?>" style="<?= $opened ? 'display:none' : '' ?>">Откройте работу, чтобы оценить</div>
                            <div class="jv-mine" id="my-<?= $gid ?>" style="<?= $my ? '' : 'display:none' ?>">ваша оценка: <span><?= $my ?></span></div>
                        <?php elseif ($my): ?>
                            <div class="jv-mine">ваша оценка: <?= $my ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; endif; ?>
        </div>

        <?php if (!empty($gamesPending)): ?>
        <div class="jv-section">
            <div class="jv-section-title">На проверке экспертами</div>
            <div class="jv-section-sub">Работы прикреплены к джему, но ещё не прошли модерацию. Голосование откроется после одобрения.</div>
            <div class="jv-grid">
                <?php foreach ($gamesPending as $g): $gid = (int)$g['id']; $cover = $g['icon_url'] ?: ($g['path_to_cover'] ?: $COVER_FALLBACK); ?>
                <article class="jv-card pending">
                    <div class="jv-cover">
                        <img src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async">
                        <div class="jv-tags"><span class="jv-tag gold">на проверке</span></div>
                    </div>
                    <div class="jv-body">
                        <div class="jv-name"><?= h($g['name']) ?></div>
                        <?php if (!empty($g['short_description'])): ?><div class="jv-desc"><?= h($g['short_description']) ?></div><?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<div class="jv-toast" id="toast" role="status" aria-live="polite"></div>

<script>
(function () {
    'use strict';

    const SPRINT_ID    = <?= (int)$sprint_id ?>;
    const CSRF         = <?= json_encode(csrf_token()) ?>;
    const MY_EXPERT_ID = <?= (int)($myExpertId ?? 0) ?>;
    const LIVE_COUNT   = <?= (int)$liveCount ?>;
    const BUDGET       = <?= (int)$budget ?>;

    const grid  = document.getElementById('gamesGrid');
    const toast = document.getElementById('toast');
    let toastTimer;

    function showToast(msg, isErr) {
        toast.textContent = msg;
        toast.classList.toggle('err', !!isErr);
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
    }


    const rules = document.getElementById('rules');
    const rulesBtn = document.getElementById('rulesBtn');
    if (rules && rulesBtn) {
        rulesBtn.addEventListener('click', () => {
            const open = rules.classList.toggle('open');
            rulesBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.jv-card'));


    const searchInput = document.getElementById('gameSearch');
    const searchBar   = document.getElementById('searchBar');
    const searchClear = document.getElementById('searchClear');
    const countEl     = document.getElementById('resultCount');
    const filtersWrap = document.getElementById('filters');
    let filter = 'all';

    const plural = n => { const d = n % 10, h = n % 100;
        if (d === 1 && h !== 11) return 'работа';
        if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return 'работы';
        return 'работ'; };

    function matchFilter(card) {
        if (filter === 'unrated')  return card.dataset.rated  !== '1';
        if (filter === 'rated')    return card.dataset.rated  === '1';
        if (filter === 'unopened') return card.dataset.opened !== '1';
        return true;
    }

    function refreshCounts() {
        if (!filtersWrap) return;
        const n = { all: cards.length, unrated: 0, rated: 0, unopened: 0 };
        cards.forEach(c => {
            if (c.dataset.rated === '1') n.rated++; else n.unrated++;
            if (c.dataset.opened !== '1') n.unopened++;
        });
        filtersWrap.querySelectorAll('[data-n]').forEach(el => {
            el.textContent = n[el.dataset.n];
        });
    }

    function apply() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        let shown = 0;
        cards.forEach(c => {
            const hit = (!q || (c.dataset.name || '').includes(q)) && matchFilter(c);
            c.hidden = !hit;
            if (hit) shown++;
        });
        if (searchBar) searchBar.classList.toggle('has-value', q !== '');
        if (countEl) {
            countEl.textContent = shown === cards.length
                ? shown + ' ' + plural(shown)
                : shown + ' ' + plural(shown) + ' из ' + cards.length;
        }
    }

    if (searchInput) {
        let t;
        searchInput.addEventListener('input', () => { clearTimeout(t); t = setTimeout(apply, 110); });
        searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') { searchInput.value = ''; apply(); } });
    }
    if (searchClear) searchClear.addEventListener('click', () => { searchInput.value = ''; apply(); searchInput.focus(); });

    if (filtersWrap) {
        filtersWrap.addEventListener('click', e => {
            const btn = e.target.closest('[data-filter]');
            if (!btn) return;
            filter = btn.dataset.filter;
            filtersWrap.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('active', b === btn));
            apply();
        });
    }


    document.addEventListener('click', e => {
        const link = e.target.closest('[data-open]');
        if (!link) return;
        const gid = parseInt(link.dataset.open, 10);
        fetch('/swad/controllers/jams/jam_play.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid, csrf: CSRF })
        }).catch(() => {});
        unlock(gid);
    });

    function unlock(gid) {
        const card = document.getElementById('card-' + gid);
        if (!card || card.dataset.opened === '1') return;
        card.dataset.opened = '1';
        const scale = document.getElementById('scale-' + gid);
        if (scale) {
            scale.classList.remove('locked');
            scale.querySelectorAll('.jv-pt').forEach(b => { b.disabled = false; });
        }
        const hint = document.getElementById('hint-' + gid);
        if (hint) hint.style.display = 'none';
        const play = document.getElementById('play-' + gid);
        if (play) { play.classList.add('done'); play.textContent = 'Открыть ещё раз ↗'; }
        refreshCounts();
        apply();
    }


    let busy = false;
    grid.addEventListener('click', async e => {
        const btn = e.target.closest('[data-vote]');
        if (!btn || btn.disabled || busy) return;

        const gid    = parseInt(btn.dataset.vote, 10);
        const points = parseInt(btn.dataset.points, 10);
        const scale  = document.getElementById('scale-' + gid);
        const prev   = scale.querySelector('.jv-pt.on');

        busy = true;
        scale.querySelectorAll('.jv-pt').forEach(b => b.classList.toggle('on', b === btn));

        try {
            const r = await fetch('/swad/controllers/jams/save_vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid, points, csrf: CSRF })
            }).then(r => r.json());

            if (!r.success) {
                // откатываем подсветку, чтобы UI не врал про сохранённый балл
                scale.querySelectorAll('.jv-pt').forEach(b => b.classList.toggle('on', b === prev));
                showToast(r.message || 'Не удалось сохранить', true);
                return;
            }
            afterVote(gid, r);
            showToast(points === 0 ? 'Голос снят' : ('Оценка ' + points + ' сохранена'));
        } catch (err) {
            scale.querySelectorAll('.jv-pt').forEach(b => b.classList.toggle('on', b === prev));
            showToast('Сеть недоступна', true);
        } finally {
            busy = false;
        }
    });

    function afterVote(gid, d) {
        const rem = document.getElementById('remaining');
        if (rem && d.remaining_budget !== undefined) {
            rem.textContent = d.remaining_budget;
            const meter = document.getElementById('budgetMeter');
            if (meter && BUDGET) meter.style.width = Math.max(0, Math.min(100, d.remaining_budget / BUDGET * 100)) + '%';
        }
        const pts = document.getElementById('pts-' + gid); if (pts && d.game_points !== undefined) pts.textContent = d.game_points;
        const vtr = document.getElementById('vtr-' + gid); if (vtr && d.game_voters !== undefined) vtr.textContent = d.game_voters;

        const card = document.getElementById('card-' + gid);
        const has  = !!d.my_points;
        card.dataset.rated = has ? '1' : '0';
        card.classList.toggle('rated', has);

        const mine = document.getElementById('my-' + gid);
        if (mine) {
            mine.style.display = has ? '' : 'none';
            const span = mine.querySelector('span');
            if (span) span.textContent = d.my_points;
        }

        const ratedN = document.getElementById('ratedN');
        if (ratedN) {
            const n = cards.filter(c => c.dataset.rated === '1').length;
            ratedN.textContent = n;
            const m = document.getElementById('rateMeter');
            if (m && LIVE_COUNT) m.style.width = (n / LIVE_COUNT * 100) + '%';
            const wrap = document.getElementById('rateMeterWrap');
            if (wrap) wrap.classList.toggle('done', n >= LIVE_COUNT && LIVE_COUNT > 0);
        }
        refreshCounts();
        apply();
    }


    grid.addEventListener('click', async e => {
        const btn = e.target.closest('[data-pick]');
        if (!btn) return;
        const gid = parseInt(btn.dataset.pick, 10);
        const on  = btn.classList.contains('active');
        try {
            const r = await fetch('/swad/controllers/jams/set_expert_pick.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ sprint_id: SPRINT_ID, game_id: gid, expert_id: MY_EXPERT_ID,
                                       action: on ? 'remove' : 'add', csrf: CSRF })
            }).then(r => r.json());
            if (!r.success) { showToast(r.message || 'Ошибка', true); return; }
            btn.classList.toggle('active', !on);
            btn.textContent = on ? '☆ отметить как выбор' : '★ ваш выбор';
            showToast(r.message || (on ? 'Отметка снята' : 'Отмечено'));
        } catch (err) { showToast('Сеть недоступна', true); }
    });


    grid.addEventListener('click', e => {
        const btn = e.target.closest('[data-votes]');
        if (!btn) return;
        const box = document.getElementById('av-' + btn.dataset.votes);
        if (!box) return;
        const open = box.classList.toggle('open');
        btn.textContent = open ? 'скрыть' : 'кто голосовал';
    });

    refreshCounts();
    apply();
})();
</script>
</body>
</html>