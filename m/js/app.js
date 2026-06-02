/* Dustore Mobile PWA — app.js */

/* ── SW registration ───────────────────────────────────── */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/m/sw.js', { scope: '/m/' }).catch(function () { });
    });
}

/* ── Install prompt ────────────────────────────────────── */
var _dip = null;
window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    _dip = e;
    var b = document.getElementById('install-banner');
    if (b) b.style.display = 'flex';
});

function triggerInstall() {
    if (_dip) {
        _dip.prompt();
        _dip.userChoice.then(function (r) {
            if (r.outcome === 'accepted') {
                var b = document.getElementById('install-banner');
                if (b) b.style.display = 'none';
                showToast('Dustore добавлен на экран!');
            }
            _dip = null;
        });
    } else {
        showIOSSheet();
    }
}

function showIOSSheet() {
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (!isIOS) return;
    var el = document.createElement('div');
    el.id = '_ios_sheet';
    el.innerHTML = '<div onclick="closeIOSSheet()" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:299;backdrop-filter:blur(4px)"></div>'
        + '<div style="position:fixed;bottom:0;left:0;right:0;z-index:300;background:#230e2d;border-radius:20px 20px 0 0;padding:8px 20px calc(24px + env(safe-area-inset-bottom));border-top:1px solid rgba(255,255,255,.1)">'
        + '<div style="width:36px;height:4px;background:rgba(255,255,255,.2);border-radius:2px;margin:0 auto 16px"></div>'
        + '<div style="font-size:17px;font-weight:700;color:#fff;text-align:center;margin-bottom:20px">Добавить на экран «Домой»</div>'
        + '<div style="font-size:14px;color:rgba(255,255,255,.7);margin-bottom:12px">1. Нажми <b style="color:#fff">Поделиться</b> (кнопка снизу)</div>'
        + '<div style="font-size:14px;color:rgba(255,255,255,.7);margin-bottom:12px">2. Прокрути и выбери <b style="color:#fff">«На экран Домой»</b></div>'
        + '<div style="font-size:14px;color:rgba(255,255,255,.7);margin-bottom:20px">3. Нажми <b style="color:#fff">«Добавить»</b></div>'
        + '<button onclick="closeIOSSheet()" style="width:100%;background:#c32178;color:#fff;border:none;padding:13px;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">Понятно</button>'
        + '</div>';
    document.body.appendChild(el);
}
function closeIOSSheet() {
    var el = document.getElementById('_ios_sheet');
    if (el) el.remove();
}

/* ── Desktop redirect ──────────────────────────────────── */
function goDesktop() {
    var exp = new Date(Date.now() + 30 * 24 * 3600 * 1000).toUTCString();
    document.cookie = 'prefer_desktop=1;path=/;expires=' + exp + ';SameSite=Lax';
    window.location.href = '/';
}

/* ── Toast ─────────────────────────────────────────────── */
var _tt = null;
function showToast(msg, dur) {
    dur = dur || 2400;
    var t = document.getElementById('_toast');
    if (!t) {
        t = document.createElement('div');
        t.id = '_toast';
        t.className = 'toast';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.display = 'block';
    t.style.animation = 'none';
    void t.offsetWidth;
    t.style.animation = 'toastIn .2s ease both';
    clearTimeout(_tt);
    _tt = setTimeout(function () { t.style.display = 'none'; }, dur);
}

/* ── Wishlist ───────────────────────────────────────────── */
function toggleWishlist(btn, gameId) {
    var active = btn.classList.toggle('active');
    var icon = btn.querySelector('i');
    if (icon) icon.className = active ? 'ti ti-heart-filled' : 'ti ti-heart';

    fetch('/swad/controllers/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ game_id: gameId, action: active ? 'add' : 'remove' })
    }).catch(function () {
        btn.classList.toggle('active');
        showToast('Ошибка. Попробуйте ещё раз.');
    });

    showToast(active ? 'Добавлено в вишлист' : 'Удалено из вишлиста');
}

/* ── Description toggle ────────────────────────────────── */
function toggleDesc(btn) {
    var desc = document.getElementById('gp-desc');
    if (!desc) return;
    var collapsed = desc.classList.toggle('collapsed');
    btn.textContent = collapsed ? 'Показать полностью' : 'Свернуть';
}

/* ── Library tabs ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    /* Tabs */
    document.querySelectorAll('.lib-tab, .library-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var group = tab.closest('.lib-header, .library-header');
            if (group) {
                group.querySelectorAll('.lib-tab, .library-tab').forEach(function (t) { t.classList.remove('active'); });
            }
            tab.classList.add('active');
            var target = tab.dataset.tab;
            document.querySelectorAll('.library-pane').forEach(function (p) {
                p.style.display = p.dataset.pane === target ? 'block' : 'none';
            });
        });
    });

    /* Page animation */
    var main = document.getElementById('main-content');
    if (main) main.classList.add('page-enter');
});