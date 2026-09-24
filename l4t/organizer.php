<?php
/**
 * l4t/organizer.php — инструменты организатора:
 *   1) мероприятия и сканер пропусков на входе;
 *   2) очереди джемов и автосборка команд.
 *
 * Сканер работает в браузере телефона: BarcodeDetector там, где он есть
 * (Chrome/Android), и jsQR как запасной путь (iOS Safari). Приложение не нужно.
 */

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/l4t/_csrf.php';
require_once __DIR__ . '/lib/extras.php';
require_once __DIR__ . '/lib/match.php';
require_once __DIR__ . '/lib/icons.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$me = (int)($_SESSION['USERDATA']['id'] ?? 0);
if (!$me) { header('Location: /login?backUrl=/l4t/organizer.php'); exit; }
$isAdmin = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;

$db   = new Database();
$main = $db->connect();
$l4t  = $db->connect('desl4t');
$X    = new L4TX($main, $l4t);
$Mt   = new L4TMatch($X, $main);
$CSRF = csrf_token();

$events = $X->hostEvents($me);
$scanId = (int)($_GET['scan'] ?? 0);
$scanEv = null;
foreach ($events as $e) if ((int)$e['id'] === $scanId) $scanEv = $e;

/* Джемы, где я организатор (админ видит все активные). */
$jams = array_values(array_filter($Mt->activeJams(), fn($s) => $isAdmin || (int)($s['host_user_id'] ?? 0) === $me));
foreach ($jams as &$j) {
    $j['queue'] = $X->has('jam_queue') ? $Mt->queue((int)$j['id']) : [];
}
unset($j);

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../swad/static/elements/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/l4t/css/l4x.css') ?>">
<script>document.documentElement.classList.add('l4x-dark'); document.title = 'Организатор — L4T';</script>
<?php l4x_sprite(); ?>
<style>
    .l4x .org-scan { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; }
    @media (max-width: 900px) { .l4x .org-scan { grid-template-columns: 1fr; } }
    .l4x .org-video { position: relative; background: #000; aspect-ratio: 3 / 4; max-height: 70vh; width: 100%; overflow: hidden; }
    .l4x .org-video video { width: 100%; height: 100%; object-fit: cover; }
    .l4x .org-video::after { content: ''; position: absolute; inset: 18%; box-shadow: 0 0 0 999px rgba(0,0,0,.35); outline: 2px solid var(--acc); }
    .l4x .org-res { padding: 20px; text-align: center; min-height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; }
    .l4x .org-res.ok { background: rgba(46,230,168,.1); box-shadow: inset 0 0 0 2px var(--up); }
    .l4x .org-res.err { background: rgba(255,95,122,.1); box-shadow: inset 0 0 0 2px var(--down); }
    .l4x .org-res img, .l4x .org-res .ph { width: 96px; height: 96px; object-fit: cover; background: var(--acc-soft); display: grid; place-items: center; font: 800 40px 'Syne'; }
    .l4x .org-res b { font: 800 22px 'Syne'; }
    .l4x .org-q { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
    .l4x .org-teams { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-top: 12px; }
</style>

<div class="l4x" id="l4x">
    <header class="l4x-intro" style="margin-bottom:20px">
        <div><div class="l4x-eyebrow"><a href="/l4t/?tab=network" style="text-decoration:none">← L4T</a> · организатор</div>
            <h1 style="font-size:clamp(28px,5vw,42px)"><?= $scanEv ? 'Вход: ' . $h($scanEv['title']) : 'Мероприятия и джемы' ?></h1></div>
    </header>

<?php if ($scanEv): ?>
    <div class="org-scan">
        <div class="l4x-card pix" style="padding:0;overflow:hidden">
            <div class="org-video pix"><video id="cam" playsinline muted></video></div>
        </div>
        <div class="l4x-col">
            <div class="l4x-card pix org-res" id="res"><span class="l4x-muted">Наведите камеру на пропуск гостя<br>(L4T → QR → «Пропуск»)</span></div>
            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2>Отмечено</h2><span class="l4x-mono" id="cnt"><?= (int)$scanEv['checkins'] ?></span></div>
                <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/l4t/organizer.php">← Ко всем мероприятиям</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="l4x-grid">
        <div class="l4x-col">
            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('pin') ?>Мои мероприятия</h2></div>
                <?php if (!$events): ?><p class="l4x-muted">Пока нет. Создайте первое — справа.</p><?php endif; ?>
                <div class="l4x-mylist">
                    <?php foreach ($events as $e): $live = strtotime((string)$e['ends_at']) > time(); ?>
                        <div class="l4x-my pix <?= $live ? '' : 'is-off' ?>">
                            <div class="l4x-my__main"><b><?= $h($e['title']) ?></b>
                                <span class="l4x-muted"><?= date('d.m.Y H:i', strtotime((string)$e['starts_at'])) ?><?= $e['place'] ? ' · ' . $h($e['place']) : '' ?> · отмечено <?= (int)$e['checkins'] ?></span></div>
                            <?php if ($live): ?><a class="l4x-btn l4x-btn--acc l4x-btn--sm" href="?scan=<?= (int)$e['id'] ?>"><?= l4x_icon('camera') ?>Сканер</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('users') ?>Очереди джемов</h2></div>
                <?php if (!$jams): ?><p class="l4x-muted" style="margin:0">Здесь появятся активные джемы, где вы организатор. Участники без команды встают в очередь из вкладки «Для тебя».</p><?php endif; ?>
                <?php foreach ($jams as $j):
                    $waiting = array_values(array_filter($j['queue'], fn($q) => $q['team_id'] === null));
                    $byG = []; foreach ($waiting as $q) $byG[$q['grp']] = ($byG[$q['grp']] ?? 0) + 1; ?>
                    <div class="l4x-sub" style="margin-top:0;border-top:0;padding-top:0;margin-bottom:18px" data-jam="<?= (int)$j['id'] ?>">
                        <div class="l4x-sub__head"><h3 style="font-family:Syne;font-size:16px;letter-spacing:0;text-transform:none;color:var(--fg)"><?= $h($j['title']) ?></h3>
                            <span class="l4x-muted"><?= count($waiting) ?> в очереди</span></div>
                        <div class="org-q">
                            <?php foreach (L4TX::GROUPS as $g => $gl): ?><span class="l4x-chip"><?= $h($gl) ?>: <?= (int)($byG[$g] ?? 0) ?></span><?php endforeach; ?>
                        </div>
                        <div class="l4x-row">
                            <select class="l4x-input l4x-input--inline" data-size><option value="3">по 3</option><option value="4" selected>по 4</option><option value="5">по 5</option></select>
                            <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-form="<?= (int)$j['id'] ?>" <?= count($waiting) < 2 ? 'disabled' : '' ?>>Собрать команды</button>
                        </div>
                        <div class="org-teams" data-out></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="l4x-col l4x-col--side">
            <form class="l4x-card pix l4x-form" id="evForm">
                <div class="l4x-card__head"><h2><?= l4x_icon('plus') ?>Новое мероприятие</h2></div>
                <label class="l4x-field"><span class="l4x-field__label">Название *</span><input class="l4x-input" name="title" maxlength="120" required placeholder="Инди-митап #3"></label>
                <label class="l4x-field"><span class="l4x-field__label">Место</span><input class="l4x-input" name="place" maxlength="120" placeholder="Москва, коворкинг…"></label>
                <label class="l4x-field"><span class="l4x-field__label">Начало *</span><input class="l4x-input" name="starts_at" type="datetime-local" required></label>
                <label class="l4x-field"><span class="l4x-field__label">Конец *</span><input class="l4x-input" name="ends_at" type="datetime-local" required></label>
                <button class="l4x-btn l4x-btn--acc" type="submit">Создать</button>
                <p class="l4x-hint" style="margin:0">Гости, отмеченные на входе, получают в профиль запись «Был на …», а знакомства на мероприятии запоминают, где вы встретились.</p>
            </form>
        </aside>
    </div>
<?php endif; ?>
    <div class="l4x-toast" id="l4xToast" hidden></div>
</div>

<script>
(function () {
    var CSRF = <?= json_encode($CSRF) ?>;
    function act(op, data) {
        return fetch('/l4t/api/action.php', { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({ op: op, csrf: CSRF }, data)) }).then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: 'Нет сети' }; });
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    var t = document.getElementById('l4xToast');
    function toast(m, k) { t.textContent = m; t.className = 'l4x-toast is-' + (k || 'ok'); t.hidden = false; setTimeout(function () { t.hidden = true; }, 2600); }

    var f = document.getElementById('evForm');
    if (f) f.addEventListener('submit', function (e) {
        e.preventDefault();
        act('event_create', { title: f.title.value, place: f.place.value, starts_at: f.starts_at.value, ends_at: f.ends_at.value })
            .then(function (r) { if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; } location.href = '?scan=' + r.id; });
    });

    document.querySelectorAll('[data-form]').forEach(function (b) {
        b.addEventListener('click', function () {
            var box = b.closest('[data-jam]');
            if (!confirm('Собрать команды из очереди? Участники получат уведомления.')) return;
            b.disabled = true;
            act('teams_form', { sprint_id: b.dataset.form, size: box.querySelector('[data-size]').value }).then(function (r) {
                if (!r.ok) { toast(r.error || 'Ошибка', 'err'); b.disabled = false; return; }
                box.querySelector('[data-out]').innerHTML = r.teams.map(function (tm) {
                    return '<div class="l4x-my pix"><div class="l4x-my__main"><b>' + esc(tm.name) + '</b><span class="l4x-muted">' + tm.members.length + ' чел.</span></div></div>';
                }).join('');
                toast('Собрано команд: ' + r.teams.length);
            });
        });
    });

    /* ── сканер ─────────────────────────────────────────────────────── */
    var video = document.getElementById('cam');
    if (!video) return;
    var EVENT = <?= (int)$scanId ?>, res = document.getElementById('res'), cnt = document.getElementById('cnt');
    var last = '', lastAt = 0, busy = false, detector = null, canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });

    function beep(ok) {
        try {
            var a = new (window.AudioContext || window.webkitAudioContext)(), o = a.createOscillator(), g = a.createGain();
            o.frequency.value = ok ? 880 : 220; g.gain.value = .08; o.connect(g); g.connect(a.destination); o.start(); o.stop(a.currentTime + .15);
        } catch (e) {}
        if (navigator.vibrate) navigator.vibrate(ok ? 60 : [60, 60, 60]);
    }
    function handle(text) {
        if (!text || busy || (text === last && Date.now() - lastAt < 4000)) return;
        last = text; lastAt = Date.now();
        if (text.indexOf('DSTR1.') !== 0) { res.className = 'l4x-card pix org-res err'; res.innerHTML = '<b>Это не пропуск L4T</b><span class="l4x-muted">Попросите открыть QR → «Пропуск»</span>'; beep(false); return; }
        busy = true;
        act('checkin', { event_id: EVENT, token: text }).then(function (r) {
            busy = false;
            if (!r.ok) { res.className = 'l4x-card pix org-res err'; res.innerHTML = '<b>Не пускаем</b><span>' + esc(r.error) + '</span>'; beep(false); return; }
            var g = r.guest;
            res.className = 'l4x-card pix org-res ok';
            res.innerHTML = (g.avatar ? '<img src="' + esc(g.avatar) + '" alt="">' : '<span class="ph">' + esc((g.name || '?').replace('@', '').charAt(0).toUpperCase()) + '</span>') +
                '<b>' + esc(g.name) + '</b><span class="l4x-muted">' + esc(g.role || '') + '</span>' +
                (g.repeat ? '<span class="l4x-chip">уже отмечен ранее</span>' : '<span class="l4x-verified">✓ добро пожаловать</span>');
            if (!g.repeat && cnt) cnt.textContent = +cnt.textContent + 1;
            beep(true);
        });
    }
    function loop() {
        if (video.readyState >= 2) {
            if (detector) {
                detector.detect(video).then(function (c) { if (c[0]) handle(c[0].rawValue); }).catch(function () {});
            } else if (window.jsQR) {
                var w = canvas.width = video.videoWidth / 2 | 0, hgt = canvas.height = video.videoHeight / 2 | 0;
                ctx.drawImage(video, 0, 0, w, hgt);
                var code = jsQR(ctx.getImageData(0, 0, w, hgt).data, w, hgt, { inversionAttempts: 'dontInvert' });
                if (code) handle(code.data);
            }
        }
        setTimeout(loop, 250);
    }
    function start() {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
            video.srcObject = s; video.play(); loop();
        }).catch(function () { res.className = 'l4x-card pix org-res err'; res.innerHTML = '<b>Нет доступа к камере</b><span class="l4x-muted">Разрешите камеру в браузере. Нужен HTTPS.</span>'; });
    }
    if ('BarcodeDetector' in window) {
        BarcodeDetector.getSupportedFormats().then(function (f) {
            if (f.indexOf('qr_code') > -1) detector = new BarcodeDetector({ formats: ['qr_code'] });
            if (!detector) loadJsQr(); else start();
        }).catch(loadJsQr);
    } else loadJsQr();
    function loadJsQr() {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        s.onload = start; s.onerror = start;
        document.head.appendChild(s);
    }
})();
</script>
<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>
