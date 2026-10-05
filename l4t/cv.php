<?php
/**
 * l4t/cv.php — резюме одной страницей.
 *
 *   /l4t/<username>/cv   — публичное
 *   /l4t/cv/<token>      — именная ссылка (share_links): считает открытия,
 *                          отзывается владельцем
 *
 * Отдельная страница без шапки сайта и со светлой печатной вёрсткой:
 * Ctrl+P → «Сохранить как PDF» даёт нормальный документ. Генерировать PDF
 * на сервере незачем — браузер делает это лучше и бесплатно.
 */

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/lib/profile.php';
require_once __DIR__ . '/lib/extras.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db   = new Database();
$main = $db->connect();
$l4t  = null;
try { $l4t = $db->connect('desl4t') ?: null; } catch (Throwable $e) {}
$X  = new L4TX($main, $l4t);
$me = (int)($_SESSION['USERDATA']['id'] ?? 0);

$uid = 0; $viaLink = false;
if (!empty($_GET['t'])) {
    $uid = (int)$X->openShareLink((string)$_GET['t'], $me);
    $viaLink = true;
} elseif (!empty($_GET['username'])) {
    $u = $X->row($main, "SELECT id FROM users WHERE username = ? OR telegram_username = ? LIMIT 1", [(string)$_GET['username'], ltrim((string)$_GET['username'], '@')]);
    $uid = (int)($u['id'] ?? 0);
}

$user = $uid ? $X->row($main, "SELECT * FROM users WHERE id = ?", [$uid]) : null;
if (!$user) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Резюме недоступно</title><body style="font:16px system-ui;padding:60px;text-align:center">'
       . ($viaLink ? 'Ссылка отозвана или не существует.' : 'Пользователь не найден.') . ' <a href="/l4t/">L4T</a></body>';
    exit;
}

$P       = (new L4TProfile($main, $l4t, $user, false))->load();
$pr      = $P->profile;
$skills  = $X->userSkills($uid);
$all     = $X->skills();
$exp     = $X->experience($uid);
$credits = $X->credits($uid);
$recs    = array_slice($X->recommendations($uid, false), 0, 2);
$about   = (string)($user['l4t_about'] ?? '');
$projs   = json_decode((string)($user['l4t_projects'] ?? '[]'), true) ?: [];
$files   = json_decode((string)($user['l4t_files'] ?? '[]'), true) ?: [];
$name    = $user['username'] ?: '@' . $user['telegram_username'];
$handle  = (string)($user['username'] ?: $user['telegram_username']);
$url     = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dustore.ru') . '/l4t/' . rawurlencode($handle);
$h       = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$ym      = function (?string $v): string {
    if (!$v) return '';
    return (['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'][(int)substr($v, 5, 2) - 1] ?? '') . ' ' . substr($v, 0, 4);
};
$lvl = [1 => 'базовый', 2 => 'уверенный', 3 => 'эксперт'];
$modes = array_intersect_key(L4TX::WORK_MODES, array_flip($pr['work_modes']));
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?= $viaLink ? 'noindex' : 'index' ?>">
<title><?= $h($name) ?> — резюме · Dustore L4T</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
    :root { --acc: <?= $h($pr['accent']) ?>; --ink: #1b1022; --muted: #6d6275; --line: #e6e0ea; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #efeaf2; color: var(--ink); font: 14px/1.55 'Inter', system-ui, sans-serif; }
    .page { width: 210mm; min-height: 297mm; margin: 24px auto; background: #fff; padding: 16mm 16mm 14mm; box-shadow: 0 20px 60px rgba(40, 10, 50, .12); }
    header { display: grid; grid-template-columns: 84px 1fr 96px; gap: 18px; align-items: center; padding-bottom: 14px; border-bottom: 3px solid var(--acc); }
    .ava { width: 84px; height: 84px; object-fit: cover; background: #f1e8f4; display: grid; place-items: center; font: 800 36px 'Syne'; color: var(--acc); }
    h1 { font: 800 30px/1.05 'Syne', sans-serif; margin: 0; letter-spacing: -.02em; }
    .role { font-size: 15px; font-weight: 600; margin-top: 4px; }
    .meta { color: var(--muted); font-size: 12.5px; margin-top: 4px; }
    .qr svg { width: 96px; height: 96px; display: block; }
    .qr small { display: block; text-align: center; font: 500 9px 'JetBrains Mono'; color: var(--muted); margin-top: 3px; }
    .cols { display: grid; grid-template-columns: 1fr 62mm; gap: 10mm; margin-top: 12px; }
    h2 { font: 700 11px 'JetBrains Mono', monospace; letter-spacing: .14em; text-transform: uppercase; color: var(--acc); margin: 16px 0 8px; }
    p { margin: 0 0 8px; }
    .item { margin-bottom: 10px; break-inside: avoid; }
    .item b { font-size: 14px; }
    .item .sub { color: var(--muted); font-size: 12.5px; }
    .ok { color: #0a8a5e; font: 600 10px 'JetBrains Mono'; margin-left: 6px; }
    .skill { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0; border-bottom: 1px dotted var(--line); }
    .skill span { color: var(--muted); font-size: 11.5px; }
    .chips span { display: inline-block; font-size: 11.5px; padding: 2px 8px; margin: 0 4px 4px 0; background: #f4eef6; }
    blockquote { margin: 0 0 10px; padding-left: 10px; border-left: 2px solid var(--acc); font-size: 12.5px; color: #3b2f42; break-inside: avoid; }
    blockquote cite { display: block; font-style: normal; color: var(--muted); font-size: 11px; margin-top: 3px; }
    a { color: inherit; }
    footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; display: flex; justify-content: space-between; }
    .bar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #1b1022; }
    .bar button, .bar a { font: 600 13px 'Inter'; padding: 8px 14px; border: 0; background: var(--acc); color: #fff; text-decoration: none; cursor: pointer; }
    .bar a { background: #3a2744; }
    @media (max-width: 820px) { .page { width: auto; min-height: 0; margin: 0; padding: 20px; } .cols { grid-template-columns: 1fr; } header { grid-template-columns: 64px 1fr; } .qr { display: none; } .ava { width: 64px; height: 64px; } }
    @media print { body { background: #fff; } .page { margin: 0; box-shadow: none; width: auto; min-height: 0; } .bar { display: none; } @page { size: A4; margin: 0; } }
</style>
</head>
<body>
<div class="bar"><button onclick="print()">Сохранить в PDF / печать</button><a href="<?= $h($url) ?>">Полный профиль</a></div>
<div class="page">
    <header>
        <?php if (!empty($user['profile_picture'])): ?><img class="ava" src="<?= $h($user['profile_picture']) ?>" alt="">
        <?php else: ?><div class="ava"><?= $h(mb_strtoupper(mb_substr(ltrim($name, '@'), 0, 1))) ?></div><?php endif; ?>
        <div>
            <h1><?= $h($name) ?></h1>
            <div class="role"><?= $h($user['l4t_role'] ?? '') ?><?= $pr['headline'] !== '' ? ' — ' . $h($pr['headline']) : '' ?></div>
            <div class="meta">
                <?= $pr['location'] !== '' ? $h($pr['location']) . ' · ' : '' ?>
                <?= !empty($user['telegram_username']) ? 't.me/' . $h($user['telegram_username']) . ' · ' : '' ?>
                <?= $h(preg_replace('~^https://~', '', $url)) ?>
            </div>
            <?php if ($modes || $pr['rate'] !== ''): ?>
                <div class="meta"><?= $h(implode(' · ', $modes)) ?><?= $pr['rate'] !== '' ? ($modes ? ' · ' : '') . $h($pr['rate']) : '' ?></div>
            <?php endif; ?>
        </div>
        <div class="qr" id="qr" data-url="<?= $h($url) ?>"><small>профиль на Dustore</small></div>
    </header>

    <div class="cols">
        <main>
            <?php if ($about !== ''): ?><h2>О себе</h2><p><?= nl2br($h(mb_substr($about, 0, 900))) ?><?= mb_strlen($about) > 900 ? '…' : '' ?></p><?php endif; ?>

            <?php if ($exp): ?>
                <h2>Опыт</h2>
                <?php foreach ($exp as $e): ?>
                    <div class="item">
                        <b><?= $h($e['title']) ?></b> · <?= $h($e['org_name']) ?><?php if ($e['status'] === 'verified'): ?><span class="ok">✓ подтверждено студией</span><?php endif; ?>
                        <div class="sub"><?= $h($ym($e['start_ym'])) ?><?= $e['start_ym'] ? ' — ' . ($e['end_ym'] ? $h($ym($e['end_ym'])) : 'по сей день') : '' ?></div>
                        <?php if (!empty($e['description'])): ?><div><?= nl2br($h($e['description'])) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($credits): ?>
                <h2>Титры</h2>
                <?php foreach (array_slice($credits, 0, 12) as $c): ?>
                    <div class="item"><b><?= $h($c['title']) ?></b><?php if ((int)$c['verified']): ?><span class="ok">✓</span><?php endif; ?>
                        <div class="sub"><?= $h(trim(($c['role'] ?? '') . ($c['year'] ? ' · ' . (int)$c['year'] : ''), ' ·')) ?></div></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($projs): ?>
                <h2>Проекты</h2>
                <?php foreach ($projs as $p): ?>
                    <div class="item"><b><?= $h($p['title'] ?? '') ?></b><?= !empty($p['url']) ? ' — <a href="' . $h($p['url']) . '">' . $h(preg_replace('~^https?://~', '', (string)$p['url'])) . '</a>' : '' ?>
                        <div class="sub"><?= $h(trim(($p['role'] ?? '') . (!empty($p['year']) ? ' · ' . (int)$p['year'] : ''), ' ·')) ?></div>
                        <?php if (!empty($p['description'])): ?><div><?= $h($p['description']) ?></div><?php endif; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <aside>
            <?php if ($skills): ?>
                <h2>Навыки</h2>
                <?php foreach ($skills as $s => $l): if (!isset($all[$s])) continue; ?>
                    <div class="skill"><?= $h($all[$s]['name']) ?><span><?= $lvl[$l] ?></span></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php $exps = json_decode((string)($user['l4t_exp'] ?? '[]'), true) ?: []; if ($exps): ?>
                <h2>Стаж</h2>
                <div class="chips"><?php foreach ($exps as $e): ?><span><?= $h($e['role'] ?? '') ?> — <?= (int)($e['years'] ?? 0) ?> г.</span><?php endforeach; ?></div>
            <?php endif; ?>

            <?php if ($recs): ?>
                <h2>Рекомендации</h2>
                <?php foreach ($recs as $r): ?>
                    <blockquote><?= $h(mb_substr((string)$r['text'], 0, 260)) ?><?= mb_strlen((string)$r['text']) > 260 ? '…' : '' ?>
                        <cite>— <?= $h($r['author']['name'] ?? '') ?>, <?= $h($r['context_label']) ?></cite></blockquote>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($files): ?>
                <h2>Ссылки</h2>
                <?php foreach ($files as $f): ?><div class="skill"><a href="<?= $h($f['value']) ?>"><?= $h($f['name']) ?></a></div><?php endforeach; ?>
            <?php endif; ?>
        </aside>
    </div>

    <footer><span>Резюме собрано на Dustore L4T · опыт и титры с ✓ подтверждены данными платформы</span><span><?= date('d.m.Y') ?></span></footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
(function () {
    var el = document.getElementById('qr');
    if (!el || !window.qrcode) return;
    var q = qrcode(0, 'M'); q.addData(el.dataset.url); q.make();
    var n = q.getModuleCount(), d = '';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += 'M' + c + ' ' + r + 'h1v1h-1z';
    el.insertAdjacentHTML('afterbegin', '<svg viewBox="0 0 ' + n + ' ' + n + '" shape-rendering="crispEdges"><path d="' + d + '" fill="#1b1022"/></svg>');
})();
</script>
</body>
</html>
