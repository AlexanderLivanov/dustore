/* ======================================================================
   Dustore.Fid — интерактив ленты
   Всё на делегировании событий: посты можно догружать динамически,
   ничего переподписывать не нужно.
   ====================================================================== */
(() => {
'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const isTouch = matchMedia('(hover:none)').matches;
const buzz = ms => navigator.vibrate && navigator.vibrate(ms);

/** Иконка из спрайта — для узлов, которые строит JS. */
const svg = (n, cls = '') => `<svg class="ic${cls ? ' ' + cls : ''}" aria-hidden="true"><use href="#i-${n}"/></svg>`;

const nfmt = n => n < 1000 ? String(n)
    : n < 1e6 ? (n / 1e3).toFixed(1).replace('.0', '').replace('.', ',') + ' тыс.'
              : (n / 1e6).toFixed(1).replace('.0', '').replace('.', ',') + ' млн';

let tt;
function toast(t) {
    const x = $('#toast');
    x.textContent = t; x.classList.add('show');
    clearTimeout(tt); tt = setTimeout(() => x.classList.remove('show'), 1900);
}

/* ======================================================================
   1. ЛЕНТА: подписки, шеринг, опросы, клипы, галереи
   ====================================================================== */
document.addEventListener('click', e => {
    const t = e.target;

    const fb = t.closest('[data-follow]');
    if (fb) {
        fb.classList.toggle('on'); buzz(10);
        fb.innerHTML = fb.classList.contains('on') ? svg('check') + ' Вы подписаны' : 'Подписаться';
        return;
    }

    const sb = t.closest('[data-share]');
    if (sb) {
        const post = sb.closest('.post');
        const data = { title: 'Dustore.Fid', text: post.querySelector('h2')?.textContent || '', url: location.href + '#p' + post.dataset.id };
        if (navigator.share) navigator.share(data).catch(() => {});
        else { navigator.clipboard?.writeText(data.url); toast('Ссылка скопирована'); }
        return;
    }

    if (t.closest('[data-menu]')) { openMenu(t.closest('[data-menu]')); return; }

    const opt = t.closest('.option');
    if (opt) {
        const poll = opt.closest('[data-poll]');
        poll.classList.add('voted');
        $$('.option', poll).forEach(o => { o.querySelector('i').style.width = o.dataset.pct + '%'; });
        opt.style.borderColor = 'var(--accent)';
        buzz(12); toast('Голос принят — ' + opt.dataset.pct + '%');
        return;
    }

    const pl = t.closest('.play');
    if (pl) {
        const on = pl.querySelector('use').getAttribute('href') === '#i-play';
        pl.querySelector('use').setAttribute('href', on ? '#i-pause' : '#i-play');
        const bar = pl.parentElement.querySelector('.clip__bar i');
        bar.style.transition = on ? 'width 42s linear' : 'none';
        bar.style.width = on ? '100%' : bar.offsetWidth + 'px';
        return;
    }

    const fl = t.closest('[data-file]');
    if (fl) { toast('Загрузка: ' + fl.dataset.file); return; }

    const gb = t.closest('[data-play]');
    if (gb) { e.preventDefault(); toast('Запускаем ' + gb.dataset.play); return; }

    const mn = t.closest('.mn');
    if (mn) { e.preventDefault(); const [k, id] = mn.dataset.m.split(':'); toast(({ game: 'Игра', user: 'Профиль', studio: 'Студия', space: 'Space' }[k] || '') + ': ' + mn.textContent.trim()); return; }

    if (t.closest('[data-comments]')) { openOverlay(t.closest('.post'), 'comments'); return; }
    if (t.closest('[data-read]'))     { openOverlay(t.closest('.post'), 'read'); return; }
    if (t.closest('.tag')) { toast('Тег: ' + t.textContent); return; }

    const hg = t.closest('.hunt__go');
    if (hg) { toast('Задание принято — прогресс появится в профиле'); buzz(12); return; }
    const sp = t.closest('.sp');
    if (sp) { toast('Открываем Space: ' + sp.querySelector('b').textContent); return; }
});

/* карусель: подсветка точек */
document.addEventListener('scroll', e => {
    const c = e.target;
    if (!c.classList || !c.classList.contains('carousel')) return;
    const i = Math.round(c.scrollLeft / c.clientWidth);
    $$('.dotsnav i', c.parentElement).forEach((d, k) => d.classList.toggle('on', k === i));
}, true);

/* ======================================================================
   2. РЕАКЦИИ
   ====================================================================== */
function setIcon(btn, name) { btn.querySelector('use').setAttribute('href', '#i-' + name); }
function getIcon(btn) { return btn.querySelector('use').getAttribute('href').replace('#i-', ''); }

function setReaction(group, name) {
    const def  = group.dataset.def;
    const btn  = group.querySelector('.rx__main');
    const num  = btn.querySelector('.rx__n');
    let count  = +btn.dataset.count;
    const was  = btn.classList.contains('on');
    const same = was && getIcon(btn) === name;

    if (same) { btn.classList.remove('on'); setIcon(btn, def); count--; }
    else      { if (!was) count++; btn.classList.add('on'); setIcon(btn, name); }

    btn.dataset.count = count;
    num.textContent = nfmt(count);
    btn.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.25)' }, { transform: 'scale(1)' }], 260);
    buzz(8);

    /* реакция одного знака исключает противоположную */
    const other = group.parentElement.querySelector(group.dataset.side === 'up' ? '.rxg--down' : '.rxg--up');
    const ob = other?.querySelector('.rx__main');
    if (!same && ob?.classList.contains('on')) {
        ob.classList.remove('on');
        ob.dataset.count = +ob.dataset.count - 1;
        ob.querySelector('.rx__n').textContent = nfmt(+ob.dataset.count);
        setIcon(ob, other.dataset.def);
    }
}

document.addEventListener('click', e => {
    const opt = e.target.closest('.rxo');
    if (opt) {
        const g = opt.closest('.rxg');
        setReaction(g, opt.dataset.e);
        g.classList.remove('open'); delete g.dataset.suppress;
        return;
    }
    const main = e.target.closest('.rx__main');
    if (main) {
        const g = main.closest('.rxg');
        if (g.dataset.suppress) { delete g.dataset.suppress; return; }
        setReaction(g, g.dataset.def);
    }
});

/* long-press на тач-устройствах раскрывает набор реакций */
let lpTimer;
document.addEventListener('touchstart', e => {
    const main = e.target.closest('.rx__main');
    if (!main) return;
    const g = main.closest('.rxg');
    lpTimer = setTimeout(() => {
        $$('.rxg.open').forEach(x => x.classList.remove('open'));
        g.classList.add('open');
        g.dataset.suppress = '1';
        buzz(15);
    }, 350);
}, { passive: true });
['touchend', 'touchmove', 'touchcancel'].forEach(ev =>
    document.addEventListener(ev, () => clearTimeout(lpTimer), { passive: true }));
document.addEventListener('touchstart', e => {
    if (!e.target.closest('.rxg')) $$('.rxg.open').forEach(x => x.classList.remove('open'));
}, { passive: true });

/* двойной тап по медиа = лайк */
let lastTap = 0, lastEl = null;
document.addEventListener('pointerup', e => {
    const m = e.target.closest('[data-dbl]');
    if (!m) return;
    const now = Date.now();
    if (now - lastTap < 320 && lastEl === m) {
        const heart = m.querySelector('.dbl');
        heart.classList.remove('go'); void heart.offsetWidth; heart.classList.add('go');
        const g = m.closest('.post').querySelector('.rxg--up');
        if (!g.querySelector('.rx__main').classList.contains('on')) setReaction(g, g.dataset.def);
        lastTap = 0;
    } else { lastTap = now; lastEl = m; }
});

/* ======================================================================
   3. МЕНЮ / BOTTOM SHEET
   ====================================================================== */
const menu = $('#menu'), scrim = $('#scrim');
const MENU_ITEMS = [
    ['bell-off', 'Не показывать такое'],
    ['link',     'Скопировать ссылку'],
    ['eye-off',  'Скрыть посты автора'],
    ['sep'],
    ['flag',     'Пожаловаться', 'danger'],
];
function openMenu(btn) {
    menu.innerHTML = MENU_ITEMS.map(i => i[0] === 'sep' ? '<hr>' :
        `<button class="${i[2] || ''}" data-label="${i[1]}">${svg(i[0])}${i[1]}</button>`).join('');
    if (!isTouch) {
        const r = btn.getBoundingClientRect();
        menu.style.top = Math.min(r.bottom + 8, innerHeight - 220) + 'px';
        menu.style.left = Math.max(12, r.right - 210) + 'px';
    }
    menu.classList.add('open'); scrim.classList.add('open'); buzz(8);
}
function closeMenu() { menu.classList.remove('open'); scrim.classList.remove('open'); }
scrim.addEventListener('click', closeMenu);
menu.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    closeMenu();
    toast(b.dataset.label === 'Пожаловаться' ? 'Жалоба отправлена модераторам' : b.dataset.label + ' — готово');
});

/* ======================================================================
   4. РАЗДЕЛЫ И ТАБЫ
   Сайдбар, нижняя навигация и табы управляют одним состоянием — view.
   ====================================================================== */
const ink = $('.tabs__ink'), tabsEl = $('.tabs');
const TABBED = ['feed', 'popular'];
let view = 'feed';

function moveInk(tab) { if (tab) { ink.style.width = tab.offsetWidth + 'px'; ink.style.transform = `translateX(${tab.offsetLeft}px)`; } }

function setView(name) {
    view = name;
    $$('[data-view]').forEach(a => a.classList.toggle('active', a.dataset.view === name));
    const tabbed = TABBED.includes(name);
    tabsEl.classList.toggle('hidden', !tabbed);
    $$('[data-pane]').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== name));
    if (tabbed) { $$('.tab').forEach(x => x.classList.toggle('active', x.dataset.tab === name)); moveInk($('.tab.active')); }
    if (name === 'drafts') renderDrafts();
    scrollTo({ top: 0, behavior: 'smooth' });
    buzz(8);
}
document.addEventListener('click', e => {
    const a = e.target.closest('[data-view]');
    if (a) { e.preventDefault(); setView(a.dataset.view); }
    if (e.target.closest('[data-opendrafts]')) { setView('drafts'); }
});
$$('.tab').forEach(t => t.addEventListener('click', () => setView(t.dataset.tab)));
moveInk($('.tab.active'));
addEventListener('resize', () => moveInk($('.tab.active')));

/* свайп между вкладками + pull-to-refresh */
const feeds = $('.feeds'), ptr = $('[data-ptr]');
let sx = 0, sy = 0, axis = null;
feeds.addEventListener('touchstart', e => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; axis = null; }, { passive: true });
feeds.addEventListener('touchmove', e => {
    const dx = e.touches[0].clientX - sx, dy = e.touches[0].clientY - sy;
    if (!axis) {
        if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
        axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
    }
    if (axis === 'y' && dy > 0 && scrollY === 0 && !e.target.closest('.carousel')) {
        ptr.style.height = Math.min(dy / 2.2, 64) + 'px';
    }
}, { passive: true });
feeds.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - sx;
    if (axis === 'x' && Math.abs(dx) > 60 && TABBED.includes(view) && !e.target.closest('.carousel')) {
        const i = TABBED.indexOf(view) + (dx < 0 ? 1 : -1);
        if (TABBED[i]) setView(TABBED[i]);
    }
    if (parseFloat(ptr.style.height) > 44) { toast('Лента обновлена'); buzz(14); }
    ptr.style.height = '';
}, { passive: true });

/* ======================================================================
   5. ЧЕРНОВИКИ — вьюха (хранилище живёт в editor.js)
   ====================================================================== */
function renderDrafts() {
    const box = $('[data-draftlist]');
    const list = window.dfidDrafts ? window.dfidDrafts.list() : [];
    box.innerHTML = list.length ? list.map(d => `
        <div class="draft" data-draft="${d.id}">
            <span class="draft__ic">${svg('article')}</span>
            <div class="draft__b">
                <div class="draft__t">${d.titleHTML || d.title || 'Без заголовка'}</div>
                <div class="draft__m"><span>${svg('clock', 'ic--sm')} ${d.ago}</span><span>${d.words} слов</span>
                    ${d.game ? `<span>${svg('gamepad', 'ic--sm')} ${d.game}</span>` : ''}</div>
            </div>
            <button class="draft__del" data-draftdel="${d.id}" aria-label="Удалить">${svg('trash')}</button>
        </div>`).join('')
        : `<div class="empty">${svg('bookmark')}Черновиков пока нет.<br>Начните писать — сохранится автоматически.</div>`;
    updateDraftCount(list.length);
}
function updateDraftCount(n) { $$('[data-draftcount]').forEach(el => el.textContent = n); }

document.addEventListener('click', e => {
    const del = e.target.closest('[data-draftdel]');
    if (del) { e.stopPropagation(); window.dfidDrafts?.remove(del.dataset.draftdel); renderDrafts(); toast('Черновик удалён'); return; }
    const d = e.target.closest('[data-draft]');
    if (d) window.dfidEditor?.openDraft(d.dataset.draft);
});

/* ======================================================================
   6. ФУЛЛСКРИН: пост с комментариями и режим чтения
   ====================================================================== */
const ov = $('#ov'), ovInner = $('.ov__inner'), ovScroll = $('.ov__scroll');
const ovTitle = $('.ov__title'), ovSub = $('.ov__sub');
const railUp = $('.rail__up'), railCmts = $('.rail__cmts'), railViews = $('.rail__views');
const progress = $('.progress'), badge = $('.rail__badge');
let replyTo = null;

function buildInner(post, mode) {
    const clone = post.cloneNode(true);
    clone.querySelector('.excerpt')?.classList.remove('excerpt');
    clone.querySelector('[data-read]')?.remove();
    const cbtn = clone.querySelector('[data-comments]');
    if (cbtn) { cbtn.setAttribute('data-scrollcmts', ''); cbtn.removeAttribute('data-comments'); }

    const tpl = post.querySelector('.post__full');
    if (mode === 'read' && tpl) {
        const body = clone.querySelector('.body');
        const tags = body.querySelector('.tags');
        const reader = document.createElement('div');
        reader.className = 'reader';
        reader.innerHTML = `<h1>${post.querySelector('h2')?.innerHTML || ''}</h1>` + tpl.innerHTML;
        if (tags) reader.appendChild(tags);
        body.replaceWith(reader);

        const meta = document.createElement('div');
        meta.className = 'reader__meta';
        meta.innerHTML = `<span>${post.querySelector('.meta')?.textContent.trim() || ''}</span>`;
        reader.before(meta);
    }
    clone.querySelector('template')?.remove();
    return clone;
}

function openOverlay(post, mode) {
    ovInner.innerHTML = '';
    ovInner.appendChild(buildInner(post, mode));
    ovInner.appendChild(commentsBlock(post));

    ovTitle.textContent = post.querySelector('.author__name')?.textContent || '';
    ovSub.textContent = mode === 'read' ? 'Статья' : 'Пост и обсуждение';
    railViews.innerHTML = svg('eye', 'ic--sm') + ' ' + (post.querySelector('.views')?.textContent.trim() || '');
    badge.textContent = post.querySelector('[data-comments] span')?.textContent || '';

    ov.classList.add('open');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => ov.classList.add('in'));
    ovScroll.scrollTop = 0;
    try { history.pushState({ ov: 1 }, ''); } catch (_) {}
}

function closeOverlay() {
    ov.classList.remove('in');
    setTimeout(() => { ov.classList.remove('open'); ovInner.innerHTML = ''; }, 260);
    document.body.style.overflow = '';
    $('.ov__sheet').style.transform = '';
    setReplyTo(null);
}
document.addEventListener('click', e => {
    if (e.target.closest('[data-ovclose]')) {
        try { history.state?.ov ? history.back() : closeOverlay(); } catch (_) { closeOverlay(); }
    }
    if (e.target.closest('[data-scrollcmts]')) $('.cmts', ovInner)?.scrollIntoView({ behavior: 'smooth' });
});
addEventListener('popstate', () => { if (ov.classList.contains('open')) closeOverlay(); });
addEventListener('keydown', e => {
    if (e.key === 'Escape') { if (menu.classList.contains('open')) closeMenu(); else if (ov.classList.contains('open')) closeOverlay(); }
});

ovScroll.addEventListener('scroll', () => {
    const max = ovScroll.scrollHeight - ovScroll.clientHeight;
    progress.style.width = (max > 0 ? ovScroll.scrollTop / max * 100 : 0) + '%';
    railUp.classList.toggle('show', ovScroll.scrollTop > 400);
});
railUp.addEventListener('click', () => ovScroll.scrollTo({ top: 0, behavior: 'smooth' }));
railCmts.addEventListener('click', () => $('.cmts', ovInner)?.scrollIntoView({ behavior: 'smooth' }));

/* свайп вниз — закрыть */
const sheet = $('.ov__sheet');
let dy0 = 0, dragging = false;
sheet.addEventListener('touchstart', e => {
    if (!isTouch) return;
    if (!e.target.closest('.ov__bar, .ov__grip') && ovScroll.scrollTop > 0) return;
    dy0 = e.touches[0].clientY; dragging = true;
    sheet.classList.add('dragging');
}, { passive: true });
sheet.addEventListener('touchmove', e => {
    if (!dragging) return;
    const d = e.touches[0].clientY - dy0;
    if (d > 0) sheet.style.transform = `translateY(${d}px)`;
}, { passive: true });
sheet.addEventListener('touchend', e => {
    if (!dragging) return;
    dragging = false; sheet.classList.remove('dragging');
    if (e.changedTouches[0].clientY - dy0 > 110) { buzz(10); closeOverlay(); }
    else sheet.style.transform = '';
}, { passive: true });

/* ======================================================================
   7. КОММЕНТАРИИ (ветки)
   ====================================================================== */
const NAMES = ['Марк', 'Лиса', 'kettlehead', 'Оля П.', 'grumpy_dev', 'Тимур', 'nullptr', 'Соня', 'Вадим', 'pixelmoth'];
const TEXTS = [
    'Вот это поворот, я думал будет наоборот.',
    'Поддерживаю, лифт правда лучшее место на карте.',
    'А можно подробнее про телеметрию? Какой инструмент?',
    'Пожалуйста, не патчите это никогда.',
    'Скинул друзьям, все в шоке.',
    'Не согласен: это ломает темп уровня.',
    'Играю с релиза, впервые вижу такое.',
    'Сделайте там ещё автомат с кофе.',
];
const rnd = seed => () => (seed = seed * 16807 % 2147483647) / 2147483647;

function mockTree(id, n, depth, r) {
    const out = [];
    for (let i = 0; i < n; i++) {
        const kids = depth < 2 && r() > .55 ? mockTree(id, 1 + Math.floor(r() * 2), depth + 1, r) : [];
        out.push({
            id: id * 100 + Math.floor(r() * 9999),
            name: NAMES[Math.floor(r() * NAMES.length)],
            channel: r() > .85, verified: r() > .88, op: depth === 0 && i === 0,
            time: ['2 мин', '17 мин', '1 ч', '3 ч', 'вчера'][Math.floor(r() * 5)],
            text: TEXTS[Math.floor(r() * TEXTS.length)],
            up: Math.floor(r() * 240), kids,
        });
    }
    return out;
}

function cmtHTML(c) {
    const kids = c.kids.length
        ? `<div class="cmt__expand">Показать ответы (${c.kids.length})</div>
           <div class="cmt__kids"><div class="cmt__thread" title="Свернуть ветку"></div>
             ${c.kids.map(cmtHTML).join('')}</div>` : '';
    return `<div class="cmt${c.channel ? ' cmt--ch' : ''}" data-cid="${c.id}">
        <div class="cmt__av">${c.name[0].toUpperCase()}</div>
        <div class="cmt__b">
          <div class="cmt__top">${c.name}
            ${c.verified ? `<span class="vf vf--official">${svg('check')}</span>` : ''}
            ${c.op ? '<span class="cmt__op">автор</span>' : ''}
            <span class="cmt__time">${c.time}</span>
          </div>
          <div class="cmt__txt">${c.text}</div>
          <div class="cmt__acts">
            <button data-clike data-n="${c.up}">${svg('heart', 'ic--sm')} <span>${c.up}</span></button>
            <button data-creply data-name="${c.name}">Ответить</button>
          </div>
          ${kids}
        </div>
      </div>`;
}

function commentsBlock(post) {
    const id = +post.dataset.id;
    const total = post.querySelector('[data-comments] span')?.textContent || '';
    const tree = mockTree(id, 4, 0, rnd(id * 7919 + 13));
    const el = document.createElement('section');
    el.className = 'cmts';
    el.innerHTML = `<div class="cmts__head"><h3>Комментарии</h3>
        <span style="color:var(--muted);font-size:12px">${total}</span>
        <button class="cmts__sort">Сначала популярные ${svg('chev-down', 'ic--sm')}</button></div>
        ${tree.map(cmtHTML).join('')}`;
    return el;
}

document.addEventListener('click', e => {
    const th = e.target.closest('.cmt__thread');
    if (th) { th.closest('.cmt').classList.add('collapsed'); buzz(6); return; }

    const ex = e.target.closest('.cmt__expand');
    if (ex) { ex.closest('.cmt').classList.remove('collapsed'); return; }

    const cl = e.target.closest('[data-clike]');
    if (cl) {
        const on = cl.classList.toggle('on');
        const n = +cl.dataset.n + (on ? 1 : 0);
        cl.querySelector('span').textContent = n;
        cl.querySelector('use').setAttribute('href', '#i-heart');
        buzz(6); return;
    }

    const rp = e.target.closest('[data-creply]');
    if (rp) { setReplyTo(rp.dataset.name); return; }

    if (e.target.closest('.cmts__sort')) toast('Сортировка: сначала новые');
});

const composer = $('[data-composer]'), replyBar = $('.composer__reply');
function setReplyTo(name) {
    replyTo = name;
    replyBar.classList.toggle('hidden', !name);
    if (name) { replyBar.firstElementChild.textContent = 'Ответ для ' + name; $('input', composer).focus(); }
}
$('[data-cancelreply]').addEventListener('click', () => setReplyTo(null));

composer.addEventListener('submit', e => {
    e.preventDefault();
    const inp = $('input', composer);
    const txt = inp.value.trim(); if (!txt) return;
    const html = cmtHTML({ id: Date.now(), name: 'Вы', channel: false, verified: false, op: false,
                           time: 'только что', text: txt, up: 0, kids: [] });
    const box = $('.cmts', ovInner);
    if (replyTo) {
        const target = [...$$('.cmt__b > .cmt__top', box)].find(t => t.textContent.trim().startsWith(replyTo));
        const holder = target?.parentElement.querySelector('.cmt__kids');
        if (holder) holder.insertAdjacentHTML('beforeend', html);
        else target?.parentElement.insertAdjacentHTML('beforeend',
            `<div class="cmt__kids"><div class="cmt__thread"></div>${html}</div>`);
    } else box.insertAdjacentHTML('beforeend', html);
    inp.value = ''; setReplyTo(null); buzz(12); toast('Комментарий отправлен');
    box.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

window.dfid = { toast, svg, setView, renderDrafts, updateDraftCount, openOverlay };
})();