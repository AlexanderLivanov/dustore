<?php
/**
 * l4t/index.php — L4T: профиль как главная + биржа + мои заявки + отклики.
 *
 * Маршруты (см. .htaccess):
 *   /l4t/                 — свой профиль (или биржа для гостя)
 *   /l4t/<username>       — чужой профиль
 *   /l4t/?tab=market|bids|responses|profile
 *   /l4t/?action=create_bid&source=jam&jam_id=N  — заявка под джем
 *   /l4t/?action=create_team&jam_id=N            — команда на джем
 *
 * Порядок важен: ВСЯ работа с БД и куками — до header.php, потому что он
 * печатает <html>. Раньше страница сама печатала <!DOCTYPE>, а header.php —
 * второй, и браузер получал два документа в одном.
 */

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/user.php';
require_once __DIR__ . '/../swad/controllers/l4t/_csrf.php';
require_once __DIR__ . '/../swad/controllers/analytics.php';
require_once __DIR__ . '/lib/profile.php';
require_once __DIR__ . '/lib/feed.php';
require_once __DIR__ . '/lib/icons.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$CSRF  = csrf_token();
$db    = new Database();
$pdo   = $db->connect();
$l4tdb = null;
try { $l4tdb = $db->connect('desl4t') ?: null; } catch (Throwable $e) { $l4tdb = null; }

$me   = !empty($_SESSION['USERDATA']['id']) ? (int)$_SESSION['USERDATA']['id'] : 0;
$anon = $me ? '' : Analytics::anonId();          // кука — до вывода

/* ── Чей профиль ──────────────────────────────────────────────────────── */
$userdata = [];
$notFound = false;
$handle   = trim((string)($_GET['username'] ?? ''));
if ($handle !== '') {
    $st = $pdo->prepare("SELECT * FROM users WHERE username = ? OR telegram_username = ? LIMIT 1");
    $st->execute([$handle, ltrim($handle, '@')]);
    $userdata = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $notFound = !$userdata;
} elseif ($me) {
    // Свежая строка из БД, а не $_SESSION: сессия отстаёт после правок с других страниц.
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$me]);
    $userdata = $st->fetch(PDO::FETCH_ASSOC) ?: $_SESSION['USERDATA'];
}

$hasProfile = !empty($userdata['id']);
$isOwner    = $hasProfile && $me === (int)$userdata['id'];

$P = null;
if ($hasProfile) {
    L4TProfile::trackView($l4tdb, (int)$userdata['id'], $me ?: null, $anon);
    $P = (new L4TProfile($pdo, $l4tdb, $userdata, $isOwner))->load();
}

/* ── Джем-режим ───────────────────────────────────────────────────────── */
$action  = (string)($_GET['action'] ?? '');
$jamData = null;
$jamId   = (int)($_GET['jam_id'] ?? $_GET['sprint_id'] ?? 0);
if ($jamId) {
    $st = $pdo->prepare("SELECT id, title, description FROM sprints WHERE id = ? LIMIT 1");
    $st->execute([$jamId]);
    $jamData = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$jamBid = $jamData && $action === 'create_bid';

/* ── Вкладки ──────────────────────────────────────────────────────────── */
$tabs = [];
if ($hasProfile) $tabs['profile'] = ['Профиль', 'user'];
$tabs['market'] = ['Биржа', 'grid'];
if ($isOwner) {
    $tabs['bids']      = ['Мои заявки', 'briefcase'];
    $tabs['responses'] = ['Отклики', 'inbox'];
}
$tab = (string)($_GET['tab'] ?? '');
if ($tab === 'my') $tab = 'bids';                          // upsert_bid.php редиректит на tab=my
if ($jamBid) $tab = 'bids';
if (!isset($tabs[$tab])) $tab = $hasProfile ? 'profile' : 'market';

$flash = [
    'created'         => ['ok',  'Заявка опубликована'],
    'updated'         => ['ok',  'Заявка обновлена'],
    'nothing_changed' => ['mute','Изменений нет'],
    'no_role'         => ['err', 'Укажите, кого ищете'],
    'csrf'            => ['err', 'Сессия устарела — обновите страницу'],
    'auth'            => ['err', 'Нужно войти в аккаунт'],
    'not_your_studio' => ['err', 'Нельзя публиковать от имени чужой студии'],
    'error'           => ['err', 'Не удалось сохранить заявку'],
][(string)($_GET['status'] ?? '')] ?? null;

/* ── Биржа: первая страница ───────────────────────────────────────────── */
$feed = ['rows' => [], 'total' => 0];
$feedTags = [];
$authors = [];
if ($l4tdb) {
    try {
        $feed = l4x_feed($l4tdb, []);
        $feedTags = l4x_feed_tags($l4tdb);
    } catch (Throwable $e) {
        error_log('[l4t/index] feed: ' . $e->getMessage());
    }
}

/* ── Мои заявки и отклики ─────────────────────────────────────────────── */
$myBids = []; $myResponds = []; $incoming = [];
if ($isOwner && $l4tdb) {
    try {
        $st = $l4tdb->prepare("SELECT * FROM bids WHERE bidder_id = ? ORDER BY created_at DESC LIMIT 200");
        $st->execute([$me]);
        $myBids = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $l4tdb->prepare("SELECT r.*, b.search_role, b.search_spec, b.conditions, b.bidder_id
                                 FROM responds r LEFT JOIN bids b ON b.id = r.bid_id
                                WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 200");
        $st->execute([$me]);
        $myResponds = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $l4tdb->prepare("SELECT r.*, b.search_role, b.search_spec, b.conditions
                                 FROM responds r JOIN bids b ON b.id = r.bid_id
                                WHERE b.bidder_id = ? ORDER BY r.created_at DESC LIMIT 200");
        $st->execute([$me]);
        $incoming = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Не глушим молча: «откликов нет» при упавшем запросе — враньё пользователю.
        error_log('[l4t/index] responds: ' . $e->getMessage());
    }
}

$authors = l4x_authors($pdo, array_merge(
    array_column($feed['rows'], 'bidder_id'),
    array_column($myResponds, 'bidder_id'),
    array_column($incoming, 'user_id'),
    $P ? array_column($P->userBids, 'bidder_id') : []
));

/* Контакты откликнувшихся: telegram_username + роль, одним запросом. */
$respContacts = [];
if ($incoming) {
    $ids = array_values(array_unique(array_map('intval', array_column($incoming, 'user_id'))));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = $pdo->prepare("SELECT id, telegram_username, l4t_role FROM users WHERE id IN ($in)");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) $respContacts[(int)$u['id']] = $u;
    } catch (Throwable $e) {}
}

/* ── Хелперы вывода ───────────────────────────────────────────────────── */
$h   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$num = fn($n) => number_format((float)$n, 0, ',', ' ');
$rub = fn($n) => number_format((float)$n, 0, ',', ' ') . ' ₽';
$delta = function (float $cur, float $prev): ?array {
    if ($prev <= 0) return $cur > 0 ? ['up', 'новое'] : null;
    $d = round(($cur / $prev - 1) * 100);
    return [$d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'), ($d > 0 ? '+' : '') . $d . '%'];
};
$spark = function (array $v): string {
    if (count($v) < 2 || max($v) == 0) return '';
    $max = max($v); $n = count($v) - 1;
    $d = '';
    foreach ($v as $i => $y) $d .= ($i ? 'L' : 'M') . round($i / $n * 100, 2) . ',' . round(26 - $y / $max * 24, 2);
    return '<svg class="l4x-spark" viewBox="0 0 100 28" preserveAspectRatio="none"><path d="' . $d . '"/></svg>';
};

$displayName = $hasProfile ? ($userdata['username'] ?: '@' . $userdata['telegram_username']) : '';
$accent      = $P ? $P->profile['accent'] : L4TProfile::ACCENTS[0];
$pageTitle   = $hasProfile ? "$displayName — L4T" : 'L4T — биржа команд Dustore';

require_once __DIR__ . '/../swad/static/elements/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/l4t/css/l4x.css') ?>">
<script>document.documentElement.classList.add('l4x-dark'); document.title = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE) ?>;</script>

<?php l4x_sprite(); ?>

<div class="l4x" id="l4x" style="--acc: <?= $h($accent) ?>">

<?php if ($notFound): ?>
    <div class="l4x-empty-page">
        <h1>Профиль не найден</h1>
        <p>Пользователя «<?= $h($handle) ?>» нет на Dustore.</p>
        <a class="l4x-btn" href="/l4t/">Открыть биржу</a>
    </div>
<?php else: ?>

    <?php /* ═════════════════════════ HERO ═════════════════════════ */ ?>
    <?php if ($hasProfile): $pr = $P->profile; $pinnable = $P->pinnable(); ?>
        <header class="l4x-hero">
            <div class="l4x-hero__banner" id="heroBanner"
                 <?= $pr['banner_url'] ? 'style="background-image:url(\'' . $h($pr['banner_url']) . '\')"' : '' ?>>
                <?php if ($isOwner): ?>
                    <button class="l4x-hero__banner-btn" data-act="banner"><?= l4x_icon('image') ?>Обложка</button>
                <?php endif; ?>
            </div>

            <div class="l4x-hero__body">
                <div class="l4x-hero__ava pix <?= $isOwner ? 'is-editable' : '' ?>" <?= $isOwner ? 'data-act="avatar" role="button" tabindex="0" title="Сменить аватар"' : '' ?>>
                    <?php if (!empty($userdata['profile_picture'])): ?>
                        <img id="heroAva" src="<?= $h($userdata['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <img id="heroAva" alt="" hidden><span class="l4x-hero__ava-ph"><?= $h(mb_strtoupper(mb_substr(ltrim($displayName, '@'), 0, 1))) ?></span>
                    <?php endif; ?>
                    <?php if ($isOwner): ?><span class="l4x-hero__ava-edit"><?= l4x_icon('camera') ?></span><?php endif; ?>
                </div>

                <div class="l4x-hero__id">
                    <div class="l4x-hero__nameline">
                        <h1 class="l4x-hero__name"><?= $h($displayName) ?></h1>
                        <?php foreach ($pr['pinned'] as $code): if (!isset($pinnable[$code])) continue; $b = $pinnable[$code]; ?>
                            <span class="l4x-pin tier-<?= (int)$b['tier'] ?>" title="<?= $h($b['title']) ?>">
                                <?= $b['img'] ? '<img src="' . $h($b['img']) . '" alt="">' : l4x_icon($b['icon']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="l4x-hero__headline" id="heroHeadline"><?= $pr['headline'] !== '' ? $h($pr['headline']) : ($isOwner ? '<span class="l4x-muted">Одной строкой — кто вы и что делаете</span>' : '') ?></div>
                    <div class="l4x-hero__meta">
                        <?php if (!empty($userdata['l4t_role'])): ?><span><?= l4x_icon('briefcase') ?><?= $h($userdata['l4t_role']) ?></span><?php endif; ?>
                        <?php if ($pr['location'] !== ''): ?><span id="heroLoc"><?= l4x_icon('pin') ?><?= $h($pr['location']) ?></span><?php endif; ?>
                        <?php if (!empty($userdata['added'])): ?><span><?= l4x_icon('calendar') ?>с <?= date('m.Y', strtotime((string)$userdata['added'])) ?></span><?php endif; ?>
                    </div>
                    <div class="l4x-hero__chips">
                        <span class="l4x-status <?= ($pr['status_text'] === '' && $pr['status_emoji'] === '') ? 'is-empty' : '' ?>" id="heroStatus" <?= $isOwner ? 'data-act="customize" role="button" tabindex="0"' : '' ?>>
                            <span class="l4x-status__emoji"><?= $h($pr['status_emoji']) ?></span><span class="l4x-status__text"><?= $pr['status_text'] !== '' ? $h($pr['status_text']) : ($isOwner ? 'Поставить статус' : '') ?></span>
                        </span>
                        <?php $av = $pr['availability']; ?>
                        <span class="l4x-avail <?= $av ? 'is-' . L4TProfile::AVAILABILITY[$av][1] : 'is-empty' ?>" id="heroAvail">
                            <i></i><span><?= $av ? $h(L4TProfile::AVAILABILITY[$av][0]) : '' ?></span>
                        </span>
                    </div>
                </div>

                <div class="l4x-hero__actions">
                    <?php if ($isOwner): ?>
                        <button class="l4x-btn l4x-btn--acc" data-act="customize"><?= l4x_icon('settings') ?>Настроить профиль</button>
                    <?php elseif (!empty($userdata['telegram_username'])): ?>
                        <a class="l4x-btn l4x-btn--acc" href="https://t.me/<?= $h($userdata['telegram_username']) ?>" target="_blank" rel="noopener"><?= l4x_icon('telegram') ?>Написать</a>
                    <?php endif; ?>
                    <button class="l4x-btn l4x-btn--ghost" data-act="share" data-url="https://<?= $h($_SERVER['HTTP_HOST'] ?? 'dustore.ru') ?>/l4t/<?= $h($userdata['username'] ?: $userdata['telegram_username']) ?>" title="Скопировать ссылку"><?= l4x_icon('share') ?></button>
                </div>
            </div>
        </header>
    <?php else: ?>
        <header class="l4x-intro">
            <div>
                <div class="l4x-eyebrow">Dustore · L4T</div>
                <h1>Найди людей<br>для своей игры</h1>
                <p>Биржа команд: заявки от разработчиков и студий, отклики, сборка команд на джемы.</p>
            </div>
            <a class="l4x-btn l4x-btn--acc" href="/login?backUrl=/l4t/"><?= l4x_icon('user') ?>Войти и создать профиль</a>
        </header>
    <?php endif; ?>

    <?php /* ═════════════════════════ ВКЛАДКИ ═════════════════════════ */ ?>
    <nav class="l4x-tabs" role="tablist">
        <?php foreach ($tabs as $k => [$label, $ic]): ?>
            <button class="l4x-tab <?= $k === $tab ? 'is-on' : '' ?>" data-tab="<?= $k ?>" role="tab">
                <?= l4x_icon($ic) ?><?= $h($label) ?>
                <?php if ($k === 'responses' && $incoming): ?><span class="l4x-badge"><?= count($incoming) ?></span><?php endif; ?>
                <?php if ($k === 'bids' && $myBids): ?><span class="l4x-badge l4x-badge--mute"><?= count($myBids) ?></span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </nav>

    <?php /* ═════════════════════════ ПРОФИЛЬ ═════════════════════════ */ ?>
    <?php if ($hasProfile): ?>
    <section class="l4x-view <?= $tab === 'profile' ? 'is-on' : '' ?>" data-view="profile">
        <div class="l4x-grid">
            <div class="l4x-col">

                <?php if (!$P->hidden('stats')): $vd = $delta($P->views['d30'], $P->views['prev30']); ?>
                <div class="l4x-kpis">
                    <div class="l4x-kpi pix">
                        <div class="l4x-kpi__label"><?= l4x_icon('eye') ?>Просмотры профиля</div>
                        <div class="l4x-kpi__val"><?= $num($P->views['d30']) ?></div>
                        <div class="l4x-kpi__foot">
                            <span class="l4x-delta <?= $vd[0] ?? 'flat' ?>"><?= $vd[1] ?? '—' ?></span><span class="l4x-muted">30 дней · всего <?= $num($P->views['total']) ?></span>
                            <?= $spark($P->views['series']) ?>
                        </div>
                    </div>
                    <div class="l4x-kpi pix">
                        <div class="l4x-kpi__label"><?= l4x_icon('briefcase') ?>Заявки</div>
                        <div class="l4x-kpi__val"><?= $num($P->l4t['bids_active']) ?><small> / <?= $num($P->l4t['bids_total']) ?></small></div>
                        <div class="l4x-kpi__foot"><span class="l4x-muted">активных · <?= $num($P->l4t['bid_views']) ?> просмотров</span></div>
                    </div>
                    <div class="l4x-kpi pix">
                        <div class="l4x-kpi__label"><?= l4x_icon('inbox') ?>Отклики получены</div>
                        <div class="l4x-kpi__val"><?= $num($P->l4t['resp_in']) ?></div>
                        <div class="l4x-kpi__foot"><span class="l4x-muted"><?= $P->l4t['resp_in_30'] ? '+' . $num($P->l4t['resp_in_30']) . ' за 30 дней' : 'за 30 дней — нет' ?></span></div>
                    </div>
                    <div class="l4x-kpi pix">
                        <div class="l4x-kpi__label"><?= l4x_icon('send') ?>Отклики отправлены</div>
                        <div class="l4x-kpi__val"><?= $num($P->l4t['resp_sent']) ?></div>
                        <div class="l4x-kpi__foot"><span class="l4x-muted">на чужие заявки</span></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$P->hidden('activity')): $A = $P->activity; ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head">
                        <h2><?= l4x_icon('calendar') ?>Активность</h2>
                        <?php if ($A['tracked']): ?>
                            <span class="l4x-muted"><?= $num($A['days']) ?> активных дней · серия <?= (int)$A['streak'] ?> · рекорд <?= (int)$A['best'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$A['tracked']): ?>
                        <div class="l4x-collecting pix">Календарь активности собирается — данные появятся после обновления платформы.</div>
                    <?php else:
                        $weeks = 26;
                        $start = strtotime('monday this week', strtotime('-' . ($weeks - 1) . ' week'));
                        $lvl = fn(int $x) => $x <= 0 ? 0 : ($x <= 5 ? 1 : ($x <= 20 ? 2 : ($x <= 60 ? 3 : 4)));
                        $mnames = ['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
                    ?>
                        <div class="l4x-heat" style="--weeks: <?= $weeks ?>">
                            <div class="l4x-heat__months">
                                <?php
                                    /* Подпись месяца — на первой неделе, где он начался. Первую колонку
                                       подписываем, только если до смены месяца ≥3 недель, иначе подписи слипаются. */
                                    $lastM = -1;
                                    for ($w = 0; $w < $weeks; $w++):
                                        $m = (int)date('n', $start + $w * 7 * 86400) - 1;
                                        $show = $m !== $lastM && ($w > 0 || (int)date('n', $start + 21 * 86400) - 1 === $m);
                                ?><span><?= $show ? $mnames[$m] : '' ?></span><?php $lastM = $m; endfor; ?>
                            </div>
                            <div class="l4x-heat__grid">
                                <?php for ($w = 0; $w < $weeks; $w++): for ($d = 0; $d < 7; $d++):
                                    $t = $start + ($w * 7 + $d) * 86400;
                                    $key = date('Y-m-d', $t);
                                    $hits = $A['map'][$key] ?? 0;
                                    $future = $t > time();
                                ?><i class="l<?= $future ? 'x' : $lvl($hits) ?>" title="<?= date('d.m', $t) ?><?= $hits ? ' · ' . $hits . ' мин' : '' ?>"></i><?php endfor; endfor; ?>
                            </div>
                            <div class="l4x-heat__legend"><span class="l4x-muted">меньше</span><i class="l0"></i><i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><span class="l4x-muted">больше</span></div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php
                    $about = (string)($userdata['l4t_about'] ?? '');
                    $exp   = json_decode((string)($userdata['l4t_exp'] ?? '[]'), true) ?: [];
                    $files = json_decode((string)($userdata['l4t_files'] ?? '[]'), true) ?: [];
                    $projs = json_decode((string)($userdata['l4t_projects'] ?? '[]'), true) ?: [];
                ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head">
                        <h2><?= l4x_icon('user') ?>О себе</h2>
                        <?php if ($isOwner): ?><button class="l4x-link" data-act="about"><?= l4x_icon('edit') ?>Изменить</button><?php endif; ?>
                    </div>
                    <div class="l4x-about <?= mb_strlen($about) > 420 ? 'is-long' : '' ?>" id="aboutText"><?= $about !== '' ? nl2br($h($about)) : '<span class="l4x-muted">' . ($isOwner ? 'Расскажите, что умеете и что ищете. Это первое, что читают на бирже.' : 'Пока пусто') . '</span>' ?></div>
                    <?php if (mb_strlen($about) > 420): ?><button class="l4x-link" data-act="about-more">Читать полностью</button><?php endif; ?>

                    <div class="l4x-sub">
                        <div class="l4x-sub__head"><h3>Опыт</h3><?php if ($isOwner): ?><button class="l4x-link" data-act="exp"><?= l4x_icon('edit') ?>Изменить</button><?php endif; ?></div>
                        <div class="l4x-exp" id="expList">
                            <?php if (!$exp): ?><span class="l4x-muted">Не указан</span><?php endif; ?>
                            <?php foreach ($exp as $e): ?>
                                <span class="l4x-exp__item pix"><?= $h($e['role'] ?? '') ?><b><?= (int)($e['years'] ?? 0) ?> г.</b></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="l4x-sub">
                        <div class="l4x-sub__head"><h3>Ссылки и файлы</h3><?php if ($isOwner): ?><button class="l4x-link" data-act="files"><?= l4x_icon('edit') ?>Изменить</button><?php endif; ?></div>
                        <div class="l4x-links" id="filesList">
                            <?php if (!$files): ?><span class="l4x-muted">Нет ссылок</span><?php endif; ?>
                            <?php foreach ($files as $f): ?>
                                <a class="l4x-chip l4x-chip--link" href="<?= $h($f['value'] ?? '#') ?>" target="_blank" rel="noopener nofollow"><?= l4x_icon(($f['type'] ?? '') === 'file' ? 'file' : 'link') ?><?= $h(mb_substr((string)($f['name'] ?? ''), 0, 28)) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="l4x-card pix">
                    <div class="l4x-card__head">
                        <h2><?= l4x_icon('gamepad') ?>Проекты</h2>
                        <?php if ($isOwner): ?><button class="l4x-link" data-act="project-new"><?= l4x_icon('plus') ?>Добавить</button><?php endif; ?>
                    </div>
                    <div class="l4x-projects" id="projGrid">
                        <?php if (!$projs): ?><span class="l4x-muted">Портфолио пустое</span><?php endif; ?>
                        <?php foreach ($projs as $i => $p): ?>
                            <button class="l4x-proj pix" data-act="project" data-i="<?= $i ?>">
                                <span class="l4x-proj__cover" <?= !empty($p['cover']) ? 'style="background-image:url(\'' . $h($p['cover']) . '\')"' : '' ?>></span>
                                <span class="l4x-proj__title"><?= $h($p['title'] ?? '') ?></span>
                                <span class="l4x-proj__meta"><?= $h(trim(($p['role'] ?? '') . (!empty($p['year']) ? ' · ' . (int)$p['year'] : ''), ' ·')) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!$isOwner && $P->userBids): ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2><?= l4x_icon('briefcase') ?>Заявки пользователя</h2></div>
                    <div class="l4x-feed l4x-feed--compact">
                        <?php foreach ($P->userBids as $bid) require __DIR__ . '/_bid_card.php'; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <aside class="l4x-col l4x-col--side">

                <?php if ($isOwner && $P->completeness['pct'] < 100): $C = $P->completeness; ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2>Профиль заполнен</h2><span class="l4x-mono"><?= $C['pct'] ?>%</span></div>
                    <div class="l4x-meter"><i style="width: <?= $C['pct'] ?>%"></i></div>
                    <ul class="l4x-todo">
                        <?php foreach (array_slice($C['todo'], 0, 4) as $t): ?>
                            <li data-todo="<?= $h($t['key']) ?>"><?= l4x_icon('plus') ?><?= $h($t['label']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="l4x-hint">Заполненные профили получают в разы больше откликов — по ним видно, с кем имеешь дело.</p>
                </div>
                <?php endif; ?>

                <?php if ($isOwner && $P->revenue): $R = $P->revenue; $rd = $delta($R['d30'], $R['prev30']); ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2><?= l4x_icon('coin') ?>Выручка</h2><span class="l4x-private"><?= l4x_icon('lock') ?>видно только вам</span></div>
                    <?php if (!$R['has_studio']): ?>
                        <p class="l4x-hint">Выручка считается по играм и ассетам ваших студий. <a href="/devs/">Создать студию</a></p>
                    <?php else: ?>
                        <div class="l4x-rev">
                            <div class="l4x-rev__big"><?= $rub($R['d30']) ?></div>
                            <div class="l4x-rev__sub"><span class="l4x-delta <?= $rd[0] ?? 'flat' ?>"><?= $rd[1] ?? '—' ?></span> за 30 дней к прошлым 30</div>
                        </div>
                        <?php $mx = max(1, max($R['months'])); ?>
                        <div class="l4x-bars">
                            <?php foreach ($R['months'] as $ym => $v): ?>
                                <div class="l4x-bars__col" title="<?= $h($ym) ?> — <?= $rub($v) ?>">
                                    <i style="height: <?= max(2, round($v / $mx * 100)) ?>%"></i>
                                    <span><?= $h(['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'][(int)substr($ym, 5, 2) - 1]) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <dl class="l4x-dl">
                            <div><dt>Всего</dt><dd><?= $rub($R['total']) ?></dd></div>
                            <div><dt>Игры</dt><dd><?= $rub($R['games']) ?></dd></div>
                            <div><dt>Ассеты</dt><dd><?= $rub($R['assets']) ?></dd></div>
                            <div><dt>Оплат</dt><dd><?= $num($R['orders']) ?></dd></div>
                            <?php if ($R['top']): ?><div><dt>Лидер</dt><dd><?= $h(mb_substr($R['top'][0], 0, 22)) ?></dd></div><?php endif; ?>
                        </dl>
                        <p class="l4x-hint">Валовая: все успешные оплаты, до комиссии платформы.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2><?= l4x_icon('trophy') ?>Бейджи и достижения</h2></div>
                    <?php if ($P->badges): ?>
                        <div class="l4x-badges">
                            <?php foreach ($P->badges as $b): ?>
                                <div class="l4x-bdg is-platform pix" title="<?= $h($b['desc']) ?>">
                                    <span class="l4x-bdg__ic"><?= $b['img'] ? '<img src="' . $h($b['img']) . '" alt="">' : l4x_icon('shield') ?></span>
                                    <span class="l4x-bdg__t"><?= $h($b['title']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="l4x-achs">
                        <?php foreach ($P->achievements as $a):
                            if (!$isOwner && !$a['tier']) continue; ?>
                            <div class="l4x-ach tier-<?= $a['tier'] ?>" title="<?= $h($a['desc']) ?>">
                                <span class="l4x-ach__ic pix"><?= l4x_icon($a['icon']) ?></span>
                                <span class="l4x-ach__body">
                                    <span class="l4x-ach__t"><?= $h($a['title']) ?><?php if ($a['tier']): ?><em><?= ['', 'I', 'II', 'III'][$a['tier']] ?></em><?php endif; ?></span>
                                    <span class="l4x-ach__d"><?= $h($a['desc']) ?> · <?= $num($a['value']) ?><?= $a['next'] ? ' / ' . $num($a['next']) : '' ?></span>
                                    <?php if ($a['next']): ?><span class="l4x-meter l4x-meter--thin"><i style="width: <?= $a['pct'] ?>%"></i></span><?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$isOwner && !array_filter($P->achievements, fn($a) => $a['tier'])): ?>
                            <span class="l4x-muted">Пока без достижений</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($P->studios): ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2><?= l4x_icon('studio') ?>Студии</h2></div>
                    <?php foreach ($P->studios as $s): ?>
                        <a class="l4x-studio" href="/d/<?= $h($s['tiker'] ?? '') ?>" target="_blank">
                            <span class="l4x-studio__ic pix"><?= $h(mb_strtoupper(mb_substr((string)$s['name'], 0, 1))) ?></span>
                            <span class="l4x-studio__body">
                                <b><?= $h($s['name']) ?></b>
                                <span class="l4x-muted"><?= $h($s['role']) ?> · <?= (int)$s['games'] ?> игр · <?= (int)$s['staff'] + 1 ?> чел.</span>
                            </span>
                            <?= l4x_icon('arrow') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$P->hidden('platform')): $c = $P->counters; ?>
                <div class="l4x-card pix">
                    <div class="l4x-card__head"><h2><?= l4x_icon('chart') ?>На платформе</h2></div>
                    <dl class="l4x-dl l4x-dl--grid">
                        <div><dt>Игр в библиотеке</dt><dd><?= $num($c['library']) ?></dd></div>
                        <div><dt>Отзывов</dt><dd><?= $num($c['reviews']) ?></dd></div>
                        <div><dt>Джемов</dt><dd><?= $num($c['jams']) ?></dd></div>
                        <div><dt>Команд собрано</dt><dd><?= $num($c['teams']) ?></dd></div>
                        <div><dt>Друзей</dt><dd><?= $num($c['friends']) ?></dd></div>
                        <div><dt>Релизов</dt><dd><?= $num($c['releases']) ?></dd></div>
                    </dl>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
    <?php endif; ?>

    <?php /* ═════════════════════════ БИРЖА ═════════════════════════ */ ?>
    <section class="l4x-view <?= $tab === 'market' ? 'is-on' : '' ?>" data-view="market">
        <div class="l4x-toolbar">
            <label class="l4x-search pix"><?= l4x_icon('search') ?><input type="search" id="feedQ" placeholder="Роль, движок, условия…" autocomplete="off"></label>
            <div class="l4x-seg" id="feedKind">
                <button class="is-on" data-kind="">Все</button>
                <button data-kind="user">Люди</button>
                <button data-kind="studio">Студии</button>
                <button data-kind="jam">Джемы</button>
            </div>
            <span class="l4x-muted l4x-toolbar__count" id="feedCount"><?= $num($feed['total']) ?></span>
        </div>
        <?php if ($feedTags): ?>
            <div class="l4x-tags" id="feedTags">
                <?php foreach ($feedTags as $t): ?><button class="l4x-chip" data-tag="<?= $h($t) ?>"><?= $h($t) ?></button><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="l4x-feed" id="feed">
            <?php if (!$feed['rows']): ?><div class="l4x-empty">Активных заявок пока нет. <?= $isOwner ? 'Создайте первую во вкладке «Мои заявки».' : '' ?></div><?php endif; ?>
            <?php foreach ($feed['rows'] as $bid) require __DIR__ . '/_bid_card.php'; ?>
        </div>
        <div class="l4x-more"><button class="l4x-btn l4x-btn--ghost" id="feedMore" <?= count($feed['rows']) < $feed['total'] ? '' : 'hidden' ?>>Показать ещё</button></div>
    </section>

    <?php /* ═════════════════════════ МОИ ЗАЯВКИ ═════════════════════════ */ ?>
    <?php if ($isOwner):
        $ownStudios = $P->studios;
        $jamText = $jamBid ? "Собираю команду для участия в джеме «{$jamData['title']}».\n\nТребуются:\n- \n\nСсылка на джем: https://dustore.ru/jams/{$jamData['id']}" : '';
    ?>
    <section class="l4x-view <?= $tab === 'bids' ? 'is-on' : '' ?>" data-view="bids">
        <div class="l4x-grid l4x-grid--form">
            <form class="l4x-card pix l4x-form" id="bidForm" action="/swad/controllers/l4t/upsert_bid.php" method="POST">
                <div class="l4x-card__head">
                    <h2 id="bidFormTitle"><?= l4x_icon('plus') ?><?= $jamBid ? 'Заявка на джем' : 'Новая заявка' ?></h2>
                    <button type="button" class="l4x-link" id="bidCancel" hidden><?= l4x_icon('close') ?>Отменить правку</button>
                </div>
                <input type="hidden" name="csrf" value="<?= $h($CSRF) ?>">
                <input type="hidden" name="bid_id" id="f_bid_id">
                <?php if ($jamBid): ?><input type="hidden" name="jam_id" value="<?= (int)$jamData['id'] ?>"><?php endif; ?>

                <div class="l4x-field">
                    <span class="l4x-field__label">Публикую как</span>
                    <div class="l4x-seg l4x-seg--form">
                        <label><input type="radio" name="owner_type" value="user" checked><span><?= l4x_icon('user') ?><?= $h($displayName) ?></span></label>
                        <?php if ($ownStudios): ?><label><input type="radio" name="owner_type" value="studio"><span><?= l4x_icon('studio') ?>Студия</span></label><?php endif; ?>
                    </div>
                    <?php if ($ownStudios): ?>
                        <select name="owner_id" class="l4x-input" id="f_owner_id" hidden>
                            <?php foreach ($ownStudios as $s): ?><option value="<?= (int)$s['id'] ?>"><?= $h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="l4x-form__grid">
                    <label class="l4x-field"><span class="l4x-field__label">Кого ищу *</span>
                        <input class="l4x-input" name="role" id="f_role" maxlength="100" required placeholder="Unity-программист, 3D-художник…"></label>
                    <label class="l4x-field"><span class="l4x-field__label">Уровень</span>
                        <input class="l4x-input" name="spec" id="f_spec" maxlength="100" placeholder="Junior, Middle, Lead…"></label>
                    <label class="l4x-field"><span class="l4x-field__label">Опыт</span>
                        <select class="l4x-input" name="exp" id="f_exp"><option>до 1 года</option><option>1–3 года</option><option>3–5 лет</option><option>5+ лет</option></select></label>
                    <label class="l4x-field"><span class="l4x-field__label">Условия</span>
                        <input class="l4x-input" name="cond" id="f_cond" maxlength="100" placeholder="Удалёнка, доля, оплата за результат…"></label>
                </div>
                <label class="l4x-field"><span class="l4x-field__label">Цель</span>
                    <select class="l4x-input" name="goal" id="f_goal"><option>Найти человека в команду</option><option>Консультация</option><option>Разовая работа</option></select></label>
                <label class="l4x-field"><span class="l4x-field__label">Подробности</span>
                    <textarea class="l4x-input" name="details" id="f_details" rows="7" maxlength="5000" placeholder="О проекте, задачах и о том, почему к вам стоит прийти"><?= $h($jamText) ?></textarea></label>
                <div class="l4x-form__foot">
                    <span class="l4x-muted" id="f_count">0 / 5000</span>
                    <button class="l4x-btn l4x-btn--acc" type="submit"><?= l4x_icon('check') ?><span id="bidSubmitText">Опубликовать</span></button>
                </div>
            </form>

            <div class="l4x-col">
                <div class="l4x-card__head l4x-card__head--bare"><h2>Созданные заявки</h2><span class="l4x-muted"><?= count($myBids) ?></span></div>
                <?php if (!$myBids): ?><div class="l4x-empty">Вы ещё не создавали заявок.</div><?php endif; ?>
                <div class="l4x-mylist">
                    <?php foreach ($myBids as $b): ?>
                        <div class="l4x-my pix">
                            <div class="l4x-my__main">
                                <b><?= $h($b['search_role']) ?></b>
                                <span class="l4x-muted"><?= date('d.m.Y', strtotime((string)$b['created_at'])) ?> · <?= $b['stage'] === 'active' ? 'активна' : $h($b['stage']) ?></span>
                            </div>
                            <span class="l4x-my__stat"><?= l4x_icon('eye') ?><?= (int)($b['views'] ?? 0) ?></span>
                            <span class="l4x-my__stat"><?= l4x_icon('inbox') ?><?= (int)($b['responses'] ?? 0) ?></span>
                            <button class="l4x-link" data-act="edit-bid" data-bid="<?= $h(json_encode([
                                'id' => (int)$b['id'], 'role' => $b['search_role'], 'spec' => $b['search_spec'] ?? '',
                                'exp' => $b['experience'] ?? '', 'cond' => $b['conditions'] ?? '', 'goal' => $b['goal'] ?? '',
                                'details' => $b['details'] ?? '', 'owner_type' => $b['owner_type'] ?? 'user', 'owner_id' => (int)($b['owner_id'] ?? 0),
                            ], JSON_UNESCAPED_UNICODE)) ?>"><?= l4x_icon('edit') ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <?php /* ═════════════════════════ ОТКЛИКИ ═════════════════════════ */ ?>
    <section class="l4x-view <?= $tab === 'responses' ? 'is-on' : '' ?>" data-view="responses">
        <div class="l4x-grid l4x-grid--even">
            <?php foreach ([['incoming', 'На мои заявки', $incoming], ['mine', 'Мои отклики', $myResponds]] as [$kind, $title, $list]): ?>
                <div class="l4x-col">
                    <div class="l4x-card__head l4x-card__head--bare"><h2><?= $title ?></h2><span class="l4x-muted"><?= count($list) ?></span></div>
                    <?php if (!$list): ?><div class="l4x-empty"><?= $kind === 'incoming' ? 'На ваши заявки пока не откликались.' : 'Вы ещё не откликались на заявки.' ?></div><?php endif; ?>
                    <?php foreach ($list as $r):
                        $who = $kind === 'incoming' ? ($authors[(int)$r['user_id']] ?? null) : ($authors[(int)($r['bidder_id'] ?? 0)] ?? null);
                        $ct  = $kind === 'incoming' ? ($respContacts[(int)$r['user_id']] ?? []) : [];
                        $payload = [
                            'kind' => $kind, 'role' => $r['search_role'] ?? '—', 'spec' => $r['search_spec'] ?? '', 'cond' => $r['conditions'] ?? '',
                            'date' => date('d.m.Y H:i', strtotime((string)$r['created_at'])), 'status' => $r['status'] ?? '',
                            'message' => $r['message'] ?? '', 'who' => $who, 'tg' => $ct['telegram_username'] ?? '', 'l4trole' => $ct['l4t_role'] ?? '',
                        ];
                    ?>
                        <button class="l4x-resp pix" data-resp="<?= $h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                            <span class="l4x-resp__ava"><?= !empty($who['avatar']) ? '<img src="' . $h($who['avatar']) . '" alt="">' : l4x_icon('user') ?></span>
                            <span class="l4x-resp__body">
                                <b><?= $kind === 'incoming' ? $h($who['name'] ?? 'Пользователь') : $h($r['search_role'] ?? '—') ?></b>
                                <span class="l4x-muted"><?= $kind === 'incoming' ? '→ ' . $h($r['search_role'] ?? '—') : $h($who['name'] ?? '') ?> · <?= date('d.m', strtotime((string)$r['created_at'])) ?></span>
                                <?php if (!empty($r['message'])): ?><span class="l4x-resp__msg"><?= $h(mb_substr((string)$r['message'], 0, 110)) ?></span><?php endif; ?>
                            </span>
                            <span class="l4x-chip"><?= $h($r['status'] ?? '') ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

<?php endif; /* notFound */ ?>

    <?php /* ═════════════════════════ МОДАЛКА И ПАНЕЛЬ ═════════════════════════ */ ?>
    <div class="l4x-modal" id="l4xModal" hidden>
        <div class="l4x-modal__box pix" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <button class="l4x-modal__x" id="modalClose" aria-label="Закрыть"><?= l4x_icon('close') ?></button>
            <h3 class="l4x-modal__title" id="modalTitle"></h3>
            <div class="l4x-modal__body" id="modalBody"></div>
            <div class="l4x-modal__actions">
                <button class="l4x-btn l4x-btn--ghost" id="modalCancel">Отмена</button>
                <button class="l4x-btn l4x-btn--acc" id="modalSave">Сохранить</button>
            </div>
        </div>
    </div>

    <?php if ($isOwner): ?>
    <div class="l4x-drawer" id="l4xDrawer" hidden>
        <div class="l4x-drawer__panel" role="dialog" aria-label="Настройка профиля">
            <div class="l4x-drawer__head">
                <h3>Настройка профиля</h3>
                <button class="l4x-modal__x" data-act="drawer-close" aria-label="Закрыть"><?= l4x_icon('close') ?></button>
            </div>
            <div class="l4x-drawer__body">
                <div class="l4x-field">
                    <span class="l4x-field__label">Обложка</span>
                    <div class="l4x-row">
                        <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="banner"><?= l4x_icon('image') ?>Загрузить</button>
                        <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="banner-clear">Убрать</button>
                    </div>
                </div>
                <div class="l4x-field">
                    <span class="l4x-field__label">Акцентный цвет</span>
                    <div class="l4x-swatches" id="dAccent">
                        <?php foreach (L4TProfile::ACCENTS as $c): ?><button style="--c: <?= $c ?>" data-c="<?= $c ?>" aria-label="<?= $c ?>"></button><?php endforeach; ?>
                        <label class="l4x-swatches__custom" title="Свой цвет"><input type="color" id="dAccentCustom"></label>
                    </div>
                </div>
                <label class="l4x-field"><span class="l4x-field__label">Строка под ником</span>
                    <input class="l4x-input" id="dHeadline" maxlength="120" placeholder="Технический художник, делаю стилизованный 3D"></label>
                <div class="l4x-field">
                    <span class="l4x-field__label">Статус</span>
                    <div class="l4x-row">
                        <input class="l4x-input l4x-input--emoji" id="dEmoji" maxlength="4" placeholder="🎮">
                        <input class="l4x-input" id="dStatus" maxlength="80" placeholder="Что сейчас делаете">
                    </div>
                    <div class="l4x-presets" id="dPresets">
                        <button data-e="🎮" data-t="Делаю игру">🎮 Делаю игру</button>
                        <button data-e="🔥" data-t="Готовлюсь к джему">🔥 К джему</button>
                        <button data-e="🎨" data-t="Рисую">🎨 Рисую</button>
                        <button data-e="🧪" data-t="Ищу тестеров">🧪 Ищу тестеров</button>
                        <button data-e="🌙" data-t="В отпуске">🌙 В отпуске</button>
                    </div>
                </div>
                <div class="l4x-field">
                    <span class="l4x-field__label">Готовность к работе</span>
                    <div class="l4x-radios" id="dAvail">
                        <?php foreach (L4TProfile::AVAILABILITY as $k => [$label, $cls]): ?>
                            <label><input type="radio" name="dAvail" value="<?= $k ?>"><span class="l4x-avail is-<?= $cls ?>"><i></i><?= $h($label) ?></span></label>
                        <?php endforeach; ?>
                        <label><input type="radio" name="dAvail" value=""><span class="l4x-avail is-empty"><i></i>Не показывать</span></label>
                    </div>
                </div>
                <label class="l4x-field"><span class="l4x-field__label">Город</span>
                    <input class="l4x-input" id="dLocation" maxlength="80" placeholder="Москва / удалённо"></label>
                <label class="l4x-field"><span class="l4x-field__label">Роль в L4T</span>
                    <input class="l4x-input" id="dRole" maxlength="40" value="<?= $h($userdata['l4t_role'] ?? '') ?>" placeholder="Программист, художник…"></label>
                <div class="l4x-field">
                    <span class="l4x-field__label">Закрепить рядом с ником (до 3)</span>
                    <div class="l4x-pins" id="dPins">
                        <?php if (!$pinnable): ?><span class="l4x-muted">Откройте достижения — и сможете их закрепить.</span><?php endif; ?>
                        <?php foreach ($pinnable as $code => $b): ?>
                            <label><input type="checkbox" value="<?= $h($code) ?>"><span class="tier-<?= (int)$b['tier'] ?>"><?= $b['img'] ? '<img src="' . $h($b['img']) . '" alt="">' : l4x_icon($b['icon']) ?><?= $h($b['title']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="l4x-field">
                    <span class="l4x-field__label">Скрыть от гостей</span>
                    <div class="l4x-checks" id="dHidden">
                        <?php foreach (L4TProfile::HIDEABLE as $k => $label): ?>
                            <label><input type="checkbox" value="<?= $k ?>"><span><?= $h($label) ?></span></label>
                        <?php endforeach; ?>
                        <span class="l4x-hint">Выручка гостям не показывается никогда.</span>
                    </div>
                </div>
            </div>
            <div class="l4x-drawer__foot">
                <span class="l4x-muted" id="dState"></span>
                <button class="l4x-btn l4x-btn--acc" data-act="drawer-save"><?= l4x_icon('check') ?>Сохранить</button>
            </div>
        </div>
    </div>
    <input type="file" id="bannerFile" accept="image/jpeg,image/png,image/webp" hidden>
    <input type="file" id="avatarFile" accept="image/jpeg,image/png,image/webp" hidden>
    <?php endif; ?>

    <div class="l4x-toast" id="l4xToast" hidden></div>
</div>

<script>
window.L4X = <?= json_encode([
    'csrf'      => $CSRF,
    'tab'       => $tab,
    'isOwner'   => $isOwner,
    'loggedIn'  => (bool)$me,
    'userId'    => $hasProfile ? (int)$userdata['id'] : 0,
    'flash'     => $flash,
    'profile'   => $P ? $P->profile : null,
    'role'      => $userdata['l4t_role'] ?? '',
    'about'     => (string)($userdata['l4t_about'] ?? ''),
    'exp'       => json_decode((string)($userdata['l4t_exp'] ?? '[]'), true) ?: [],
    'files'     => json_decode((string)($userdata['l4t_files'] ?? '[]'), true) ?: [],
    'projects'  => json_decode((string)($userdata['l4t_projects'] ?? '[]'), true) ?: [],
    'availability' => array_map(fn($a) => ['label' => $a[0], 'cls' => $a[1]], L4TProfile::AVAILABILITY),
    'jam'       => $jamData,
    'jamAction' => $action,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= asset_url('/l4t/js/l4x.js') ?>" defer></script>

<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>
