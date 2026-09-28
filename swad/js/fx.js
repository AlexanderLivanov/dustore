/* ======================================================================
   Fid Core — клиент. Разметку карточек НЕ строит: её присылает api/fx.php готовым HTML.
   Здесь только поведение: реакции, комментарии-ветки, опросы, композер, ленты, вкладки, модалки.

   Подключение на странице:
     window.FX = { api: '/api/fx.php', csrf: '<?= csrf_token() ?>', viewer: 12 };
     <script src="/swad/js/fx.js" defer></script>
   Все обработчики висят на document и срабатывают только внутри .fx.
   ====================================================================== */
(function () {
    'use strict';
    if (window.__fxLoaded) return;
    window.__fxLoaded = true;

    const CFG = Object.assign({ api: '/api/fx.php', csrf: '', viewer: 0 }, window.FX || {});
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const root = () => $('.fx');

    const ERR = {
        auth: 'Войдите, чтобы это сделать', forbidden: 'Недостаточно прав', not_found: 'Уже удалено или не существует',
        rate: 'Слишком часто. Подождите минуту', empty: 'Напишите хоть что-нибудь', too_long: 'Слишком длинно',
        title_required: 'У темы должно быть название', bad_poll: 'В опросе нужно от 2 до 6 вариантов', bad_media: 'Не удалось прикрепить фото',
        locked: 'Ветка закрыта', voted: 'Вы уже голосовали', ended: 'Опрос завершён', pin_limit: 'Можно закрепить не больше трёх',
        too_big: 'Файл больше 6 МБ', bad_type: 'Только JPG, PNG, WebP или GIF', csrf: 'Сессия устарела — обновите страницу',
        not_live: 'DustHunt сейчас не идёт', full: 'Мест больше нет', server_error: 'Что-то пошло не так. Попробуйте ещё раз',
        storage: 'Не удалось сохранить файл', network: 'Нет связи с сервером'
    };

    /* ---------------------------------------------------------------- утилиты */
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function ic(name, cls) { return '<svg class="ic ' + (cls || '') + '" aria-hidden="true"><use href="#fxi-' + name + '"/></svg>'; }
    function nfmt(n) {
        n = +n || 0;
        if (n < 1000) return String(n);
        const f = (v) => v.toFixed(1).replace(/\.0$/, '').replace('.', ',');
        return n < 1e6 ? f(n / 1000) + ' тыс.' : f(n / 1e6) + ' млн';
    }

    async function api(action, data, method) {
        method = method || 'POST';
        let res;
        try {
            if (method === 'GET') {
                const q = new URLSearchParams(Object.assign({ action }, CFG.m ? { m: 1 } : {}, data || {}));
                res = await fetch(CFG.api + '?' + q.toString(), { credentials: 'same-origin' });
            } else {
                const isForm = data instanceof FormData;
                if (isForm) { data.append('action', action); data.append('csrf', CFG.csrf); if (CFG.m) data.append('m', '1'); }
                res = await fetch(CFG.api, {
                    method: 'POST', credentials: 'same-origin',
                    headers: Object.assign({ 'X-CSRF-Token': CFG.csrf }, isForm ? {} : { 'Content-Type': 'application/json' }),
                    body: isForm ? data : JSON.stringify(Object.assign({ action, csrf: CFG.csrf }, CFG.m ? { m: 1 } : {}, data || {}))
                });
            }
        } catch (e) { throw { code: 'network' }; }
        let j = null;
        try { j = await res.json(); } catch (e) { /* не JSON */ }
        if (!j || !j.ok) throw { code: (j && j.error) || 'server_error', status: res.status };
        return j;
    }

    let toastT;
    function toast(msg, isErr) {
        let t = $('.fx-toast');
        if (!t) { t = document.createElement('div'); t.className = 'fx-toast'; t.setAttribute('role', 'status'); (root() || document.body).appendChild(t); }
        t.textContent = msg; t.classList.toggle('err', !!isErr);
        requestAnimationFrame(() => t.classList.add('show'));
        clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), 2600);
    }
    function fail(e) {
        if (e && e.code === 'auth') { toast(ERR.auth, true); return; }
        toast(ERR[e && e.code] || ERR.server_error, true);
    }

    /* ---------------------------------------------------------------- строительные блоки: меню / оверлей / модалка / лайтбокс */
    function ensureUI() {
        const r = root();
        if (!r || $('.fx-scrim', r)) return;
        r.insertAdjacentHTML('beforeend',
            '<div class="fx-scrim"></div><div class="fx-menu" role="menu"></div><div class="fx-mnbox"></div>' +
            '<div class="fx-lightbox"><img alt=""></div>' +
            '<div class="fx-ov" aria-hidden="true"><div class="fx-ov__scrim" data-fx="ov-close"></div><div class="fx-ov__sheet">' +
            '<header class="fx-ov__bar"><button type="button" class="fx-ov__close" data-fx="ov-close" aria-label="Закрыть">' + ic('close') + '</button>' +
            '<div><div class="fx-ov__title"></div><div class="fx-ov__sub"></div></div></header><div class="fx-progress"></div>' +
            '<div class="fx-ov__scroll"><div class="fx-ov__inner"></div></div><div class="fx-ov__foot"></div></div></div>' +
            '<div class="fx-modal"><div class="fx-modal__scrim" data-fx="modal-close"></div><div class="fx-modal__card">' +
            '<button type="button" class="fx-modal__x" data-fx="modal-close" aria-label="Закрыть">' + ic('close') + '</button><div class="fx-modal__body"></div></div></div>');
    }

    /* меню */
    const menu = { el: null, scrim: null };
    function openMenu(anchor, items) {
        ensureUI();
        menu.el = $('.fx-menu'); menu.scrim = $('.fx-scrim');
        menu.el.innerHTML = '';
        items.forEach(it => {
            if (it === '-') { menu.el.appendChild(document.createElement('hr')); return; }
            const b = document.createElement(it.href ? 'a' : 'button');
            if (it.href) { b.href = it.href; if (it.blank) { b.target = '_blank'; b.rel = 'noopener'; } } else b.type = 'button';
            b.className = it.danger ? 'danger' : '';
            b.innerHTML = ic(it.icon || 'dots') + '<span>' + esc(it.label) + '</span>';
            if (it.run) b.addEventListener('click', ev => { ev.stopPropagation(); if (it.confirm && !b.dataset.sure) { b.dataset.sure = 1; b.querySelector('span').textContent = 'Нажмите ещё раз'; setTimeout(() => { delete b.dataset.sure; b.querySelector('span').textContent = it.label; }, 3000); return; } closeMenu(); it.run(); });
            menu.el.appendChild(b);
        });
        const r = anchor.getBoundingClientRect(), mobile = window.innerWidth <= 900;
        if (!mobile) {
            menu.el.style.left = Math.max(8, Math.min(window.innerWidth - 230, r.right - 220)) + 'px';
            menu.el.style.top = Math.min(window.innerHeight - 20 - items.length * 40, r.bottom + 6) + 'px';
        }
        menu.el.classList.add('open'); menu.scrim.classList.add('open');
    }
    function closeMenu() { if (menu.el) { menu.el.classList.remove('open'); menu.scrim.classList.remove('open'); } }

    /* оверлей поста */
    const ov = { open: false, id: 0, parent: null };
    async function openPost(id, focus) {
        ensureUI();
        const el = $('.fx-ov'), inner = $('.fx-ov__inner'), foot = $('.fx-ov__foot');
        ov.id = id; ov.parent = null;
        inner.innerHTML = '<div class="fx-ov__load">Загрузка…</div>'; foot.innerHTML = '';
        $('.fx-ov__title').textContent = ''; $('.fx-ov__sub').textContent = '';
        el.classList.add('open'); el.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => el.classList.add('in'));
        document.documentElement.style.overflow = 'hidden';
        history.replaceState(history.state, '', location.href);
        try {
            const j = await api('post', { id }, 'GET');
            if (ov.id !== id) return;
            inner.innerHTML = j.html; foot.innerHTML = j.reply;
            $('.fx-ov__title').textContent = j.title; $('.fx-ov__sub').textContent = j.sub;
            const sc = $('.fx-ov__scroll'); sc.scrollTop = 0;
            if (focus === 'comments') { const c = $('#fx-cmts', inner); if (c) sc.scrollTop = c.offsetTop - 60; }
        } catch (e) { fail(e); closeOverlay(); }
    }
    function closeOverlay() {
        const el = $('.fx-ov'); if (!el) return;
        el.classList.remove('in'); el.setAttribute('aria-hidden', 'true');
        setTimeout(() => { el.classList.remove('open'); $('.fx-ov__inner').innerHTML = ''; }, 260);
        document.documentElement.style.overflow = ''; ov.id = 0;
    }

    /* модалка */
    async function openModal(html) { ensureUI(); $('.fx-modal__body').innerHTML = html; $('.fx-modal').classList.add('open'); document.documentElement.style.overflow = 'hidden'; }
    function closeModal() { const m = $('.fx-modal'); if (m) { m.classList.remove('open'); document.documentElement.style.overflow = ''; } }

    /* ---------------------------------------------------------------- реакции */
    function paintReactions(post, res) {
        $$('.fx-rxg', post).forEach(g => {
            const side = g.dataset.side, main = $('.fx-rx__main', g), names = $$('.fx-rxo', g).map(b => b.dataset.e);
            const count = side === 'up' ? res.up : res.down, on = res.mine && names.includes(res.mine);
            $('.fx-rx__n', main).textContent = nfmt(count); main.dataset.count = count;
            main.classList.toggle('on', !!on);
            main.dataset.e = on ? res.mine : g.dataset.def;
            $('use', main).setAttribute('href', '#fxi-' + (on ? res.mine : g.dataset.def));
            g.classList.remove('open');
        });
    }
    async function react(post, kind) {
        const id = +post.dataset.id;
        const upM = $('.fx-rxg--up .fx-rx__main', post), dnM = $('.fx-rxg--down .fx-rx__main', post);
        const snap = { up: +upM.dataset.count, down: +dnM.dataset.count, mine: upM.classList.contains('on') ? upM.dataset.e : (dnM.classList.contains('on') ? dnM.dataset.e : null) };
        paintReactions(post, optimistic(post, kind, snap));   // мгновенный отклик до ответа сервера
        try { paintReactions(post, await api('react', { id, kind })); }
        catch (e) { paintReactions(post, snap); fail(e); }
    }
    function optimistic(post, kind, s) {
        const inUp = k => !!$('.fx-rxg--up .fx-rxo[data-e="' + k + '"]', post);
        let u = s.up, d = s.down;
        if (s.mine === kind) { if (inUp(kind)) u--; else d--; return { up: u, down: d, mine: null }; }
        if (s.mine) { if (inUp(s.mine)) u--; else d--; }
        if (inUp(kind)) u++; else d++;
        return { up: u, down: d, mine: kind };
    }

    /* ---------------------------------------------------------------- ленты и вкладки */
    async function loadFeed(box, cursor, replace) {
        const cfg = JSON.parse(box.dataset.feed || '{}');
        if (cursor) cfg.cursor = cursor;
        const j = await api('feed', cfg, 'GET');
        const more = $('.fx-more', box); if (more) more.remove();
        if (replace) box.innerHTML = '';
        if (j.html.trim() === '' && !cursor) {
            const t = $('template[data-empty="' + (j.empty || '') + '"]', box) || $('template[data-empty]', box); box.dataset.loaded = '1';
            box.insertAdjacentHTML('beforeend', t ? t.innerHTML : '');
            return;
        }
        box.insertAdjacentHTML('beforeend', j.html);
        if (j.next) box.insertAdjacentHTML('beforeend', '<button type="button" class="fx-more" data-fx="more" data-next="' + esc(j.next) + '">Показать ещё</button>');
        box.dataset.loaded = '1';
    }
    function initFeeds() {
        $$('.fx-feed[data-feed]').forEach(box => {
            if (box.dataset.loaded === '1' || box.closest('[hidden]')) return;
            if (box.dataset.ssr) { box.dataset.loaded = '1'; return; }
            loadFeed(box, null, true).catch(fail);
        });
    }
    function showTab(tabs, name, push) {
        const scope = tabs.closest('.fx-main') || tabs.parentElement;
        $$('.fx-tab', tabs).forEach(t => t.classList.toggle('on', t.dataset.pane === name));
        $$('.fx-pane', scope).forEach(p => { p.hidden = p.dataset.pane !== name; });
        if (push) { const u = new URL(location.href); u.searchParams.set('tab', name); history.replaceState(null, '', u); }
        initFeeds();
    }

    /* ---------------------------------------------------------------- комментарии */
    function setCounters(root_, n) { $$('.fx-cm-n', root_).forEach(e => { e.textContent = nfmt(n); }); }
    function syncCard(id, n) {
        $$('.fx-post[data-id="' + id + '"] .fx-actions .fx-cm-n, .fx-thr[data-id="' + id + '"] .fx-thr__n .fx-cm-n').forEach(e => { e.textContent = nfmt(n); });
    }
    async function submitComment(form) {
        const input = $('input[name=body]', form), body = input.value.trim(); if (!body) return;
        const postId = +form.dataset.post, btn = $('.fx-send', form); btn.disabled = true;
        const inOv = !!form.closest('.fx-ov'), box = inOv ? $('#fx-cmts') : form.closest('.fx-thr__b').querySelector('.fx-cmts');
        try {
            const j = await api('comment', { post_id: postId, body, parent: form.dataset.parent || null, head: inOv ? 1 : 0, sort: 'new' });
            box.innerHTML = j.html; input.value = ''; clearReply(form);
            syncCard(postId, j.count); if (inOv) setCounters(box, j.count);
            const fresh = $('.fx-cmt[data-id="' + j.id + '"]', box); if (fresh) { fresh.classList.add('just'); fresh.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        } catch (e) { fail(e); } finally { btn.disabled = false; }
    }
    function clearReply(form) { delete form.dataset.parent; const t = $('.fx-reply__to', form); if (t) t.classList.add('hidden'); $('input[name=body]', form).placeholder = 'Написать ответ…'; }

    /* ---------------------------------------------------------------- меню поста */
    function postMenu(el, anchor) {
        const can = JSON.parse(el.dataset.can || '{}'), id = +el.dataset.id, isThr = el.classList.contains('fx-thr');
        const items = [];
        items.push({ icon: 'link', label: 'Скопировать ссылку', run: () => copyLink(id) });
        if (can.pin) items.push({ icon: 'pin', label: el.classList.contains('is-pinned') ? 'Открепить' : 'Закрепить', run: () => togglePin(el, id) });
        if (can.lock) items.push({ icon: 'lock', label: el.classList.contains('is-locked') ? 'Открыть ветку' : 'Закрыть ветку', run: () => toggleLock(el, id) });
        if (can.edit && !isThr) items.push({ icon: 'edit', label: 'Редактировать', run: () => startEdit(el, id) });
        if (can.report) items.push({ icon: 'flag', label: 'Пожаловаться', run: () => api('report', { id, type: 'post', reason: 'other' }).then(() => toast('Жалоба отправлена')).catch(fail) });
        if (can.delete) { items.push('-'); items.push({ icon: 'trash', label: 'Удалить', danger: true, confirm: true, run: () => del(el, id) }); }
        openMenu(anchor, items);
    }
    function copyLink(id) {
        const url = location.origin + '/fid/post/' + id;
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(() => toast('Ссылка скопирована'), () => toast(url));
    }
    async function del(el, id) {
        try { await api('delete', { id }); el.classList.add('is-gone'); setTimeout(() => el.remove(), 250); toast('Удалено'); if (ov.id === id) closeOverlay(); } catch (e) { fail(e); }
    }
    async function togglePin(el, id) {
        const on = !el.classList.contains('is-pinned');
        try { await api('pin', { id, on: on ? 1 : 0 }); el.classList.toggle('is-pinned', on); toast(on ? 'Закреплено' : 'Откреплено'); } catch (e) { fail(e); }
    }
    async function toggleLock(el, id) {
        const on = !el.classList.contains('is-locked');
        try { await api('lock', { id, on: on ? 1 : 0 }); el.classList.toggle('is-locked', on); toast(on ? 'Ветка закрыта' : 'Ветка открыта'); } catch (e) { fail(e); }
    }
    function startEdit(el, id) {
        const txt = $('.fx-body .fx-text', el); if (!txt || $('.fx-edit', el)) return;
        const raw = txt.innerText.trim();
        const box = document.createElement('div'); box.className = 'fx-edit';
        box.innerHTML = '<textarea class="fx-cc__field" rows="3" maxlength="5000"></textarea><div class="fx-hm__foot" style="gap:8px;margin-top:8px"><button type="button" class="fx-btn fx-btn--ghost" data-x>Отмена</button><button type="button" class="fx-btn" data-ok>Сохранить</button></div>';
        $('textarea', box).value = raw; txt.style.display = 'none'; txt.after(box); $('textarea', box).focus();
        $('[data-x]', box).onclick = () => { box.remove(); txt.style.display = ''; };
        $('[data-ok]', box).onclick = async () => {
            try { const j = await api('edit', { id, body: $('textarea', box).value }); const t = document.createElement('div'); t.innerHTML = j.html; const n = t.firstElementChild; if (n) el.replaceWith(n); toast('Сохранено'); } catch (e) { fail(e); }
        };
    }

    /* ---------------------------------------------------------------- композер */
    const MENTION_ICON = { user: 'user', game: 'gamepad', studio: 'studio' };
    function initComposer(form) {
        if (form.dataset.ready) return; form.dataset.ready = 1;
        const cfg = JSON.parse(form.dataset.cfg || '{}'), ta = $('.fx-cc__field', form), title = $('.fx-cc__title', form),
            submit = $('.fx-cc__submit', form), count = $('.fx-cc__count', form), thumbs = $('[data-thumbs]', form);
        const st = { media: [], mentions: [], as: 0, busy: 0 };
        form._fx = st;

        const fit = () => { ta.style.height = 'auto'; ta.style.height = Math.min(320, ta.scrollHeight) + 'px'; };
        const pollOn = () => { const p = $('[data-pollb]', form); return p && !p.classList.contains('hidden'); };
        const pollOpts = () => pollOn() ? $$('input', $('[data-pollb]', form)).map(i => i.value.trim()).filter(Boolean) : [];
        const refresh = () => {
            const len = ta.value.length; count.textContent = len ? len + ' / 5000' : ''; count.classList.toggle('warn', len > 4500);
            const ok = (ta.value.trim() || st.media.length || pollOpts().length >= 2) && (!cfg.title || (title && title.value.trim().length >= 3)) && !st.busy;
            submit.disabled = !ok; fit();
        };
        ta.addEventListener('input', () => { refresh(); mentionScan(ta, st); }); ta.addEventListener('keydown', e => mentionKey(e, ta, st));
        ta.addEventListener('blur', () => setTimeout(closeMention, 150));
        if (title) title.addEventListener('input', refresh);
        form.addEventListener('input', e => { if (e.target.closest('[data-pollb]')) refresh(); });
        form.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !submit.disabled) { e.preventDefault(); form.requestSubmit(); } });

        form._addFiles = async (files) => {
            const room = 10 - st.media.length; files = Array.from(files).slice(0, room); if (!files.length) return;
            thumbs.classList.remove('hidden');
            for (const f of files) {
                const t = document.createElement('div'); t.className = 'fx-thumb up'; t.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')';
                thumbs.appendChild(t); st.busy++; refresh();
                try {
                    const fd = new FormData(); fd.append('file', f); const j = await api('upload', fd);
                    const m = { u: j.u, w: j.w, h: j.h }; st.media.push(m); t.classList.remove('up');
                    t.innerHTML = '<button type="button" aria-label="Убрать">' + ic('close', 'ic--sm') + '</button>';
                    $('button', t).onclick = () => { st.media = st.media.filter(x => x !== m); t.remove(); if (!st.media.length) thumbs.classList.add('hidden'); refresh(); };
                } catch (e) { t.remove(); fail(e); } finally { st.busy--; refresh(); }
            }
            if (!thumbs.children.length) thumbs.classList.add('hidden');
        };
        form.addEventListener('paste', e => { const fs = Array.from(e.clipboardData && e.clipboardData.files || []).filter(f => /^image\//.test(f.type)); if (fs.length) { e.preventDefault(); form._addFiles(fs); } });
        const fi = $('[data-file]', form); if (fi) fi.addEventListener('change', () => { form._addFiles(fi.files); fi.value = ''; });

        form.addEventListener('submit', async e => {
            e.preventDefault(); if (submit.disabled) return;
            let body = ta.value.trim();
            st.mentions.forEach(m => { body = body.replace(new RegExp('@' + m.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\w@\\[])'), '@[' + m.type + ':' + m.id + '|' + m.name + ']'); });
            const data = { wall_type: cfg.wall_type, wall_id: cfg.wall_id, channel: cfg.channel, body, media: st.media, title: title ? title.value.trim() : '' };
            const opts = pollOpts(); if (opts.length >= 2) data.poll = { opts, days: +$('[name=days]', form).value };
            if (cfg.wall_type === 'studio') data.as_studio_id = cfg.wall_id;
            else if (st.as) data.as_studio_id = st.as;
            const cross = $('[name=crosspost]', form); if (cross && cross.checked && !$('[data-cross]', form).classList.contains('hidden')) data.crosspost = 1;
            st.busy++; submit.disabled = true;
            try {
                const j = await api('create', data);
                const feed = (form.dataset.target && $(form.dataset.target)) || form.closest('.fx-pane')?.querySelector('.fx-feed') || $('.fx-pane:not([hidden]) .fx-feed') || $('.fx-feed');
                if (feed && j.html) { const emp = $('.fx-empty', feed); if (emp) emp.remove(); const first = $('.fx-post:not(.is-pinned), .fx-thr:not(.is-pinned)', feed); (first || feed).insertAdjacentHTML(first ? 'beforebegin' : 'afterbegin', j.html); }
                ta.value = ''; if (title) title.value = ''; st.media = []; st.mentions = []; thumbs.innerHTML = ''; thumbs.classList.add('hidden');
                const p = $('[data-pollb]', form); if (p) { p.classList.add('hidden'); $$('input', p).forEach(i => i.value = ''); }
                toast(cfg.channel === 'forum' ? 'Тема создана' : 'Опубликовано');
            } catch (er) { fail(er); } finally { st.busy--; refresh(); }
        });
        refresh();
    }

    /* @-упоминания: в тексте остаётся «@Имя», а в @[тип:id|Имя] превращается при отправке */
    const mn = { on: false, list: [], sel: 0, ta: null, st: null, from: 0, t: 0 };
    function mentionScan(ta, st) {
        const pos = ta.selectionStart, m = /(^|\s)@([^\s@]{0,30})$/.exec(ta.value.slice(0, pos));
        if (!m) { closeMention(); return; }
        mn.ta = ta; mn.st = st; mn.from = pos - m[2].length - 1;
        clearTimeout(mn.t);
        if (m[2] === '') { showMention([], 'Начните вводить имя, игру или студию'); return; }
        mn.t = setTimeout(async () => { try { const j = await api('mentions', { q: m[2] }, 'GET'); showMention(j.items); } catch (e) { closeMention(); } }, 140);
    }
    function showMention(items, hint) {
        ensureUI(); const box = $('.fx-mnbox'); mn.list = items; mn.sel = 0; mn.on = true;
        box.innerHTML = items.length ? items.map((it, i) => '<button type="button" data-i="' + i + '" class="' + (i ? '' : 'sel') + '">' +
            (it.img ? '<img src="' + esc(it.img) + '" alt="">' : ic(MENTION_ICON[it.type])) + '<span>' + esc(it.name) + '</span><small>' + esc(it.note) + '</small></button>').join('')
            : '<div class="fx-note" style="padding:10px">' + esc(hint || 'Ничего не найдено') + '</div>';
        const r = mn.ta.getBoundingClientRect(); box.style.left = Math.min(window.innerWidth - 285, r.left + 10) + 'px'; box.style.top = Math.min(window.innerHeight - 270, r.bottom - 4) + 'px';
        box.classList.add('open');
    }
    function closeMention() { mn.on = false; const b = $('.fx-mnbox'); if (b) b.classList.remove('open'); }
    function pickMention(i) {
        const it = mn.list[i]; if (!it) return; const ta = mn.ta, pos = ta.selectionStart;
        ta.value = ta.value.slice(0, mn.from) + '@' + it.name + ' ' + ta.value.slice(pos);
        ta.selectionStart = ta.selectionEnd = mn.from + it.name.length + 2;
        mn.st.mentions.push({ type: it.type, id: it.id, name: it.name }); closeMention(); ta.dispatchEvent(new Event('input')); ta.focus();
    }
    function mentionKey(e, ta) {
        if (!mn.on || !mn.list.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); mn.sel = (mn.sel + (e.key === 'ArrowDown' ? 1 : mn.list.length - 1)) % mn.list.length; $$('.fx-mnbox button').forEach((b, i) => b.classList.toggle('sel', i === mn.sel)); }
        else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pickMention(mn.sel); }
        else if (e.key === 'Escape') closeMention();
    }

    /* ---------------------------------------------------------------- делегирование кликов */
    document.addEventListener('mousedown', e => { const b = e.target.closest('.fx-mnbox button'); if (b) { e.preventDefault(); pickMention(+b.dataset.i); } });

    document.addEventListener('click', async e => {
        const t = e.target.closest('[data-fx]'); if (!t || !t.closest('.fx')) return;
        const act = t.dataset.fx, post = t.closest('.fx-post'), thr = t.closest('.fx-thr');
        switch (act) {
            case 'menu': e.preventDefault(); e.stopPropagation(); postMenu(post || thr, t); break;
            case 'react': {
                e.preventDefault(); if (!CFG.viewer) return toast(ERR.auth, true);
                react(post, t.dataset.e); break;
            }
            case 'open': e.preventDefault(); if (t.closest('.fx-ov')) { const c = $('#fx-cmts'); if (c) $('.fx-ov__scroll').scrollTo({ top: c.offsetTop - 60, behavior: 'smooth' }); break; } if (post) openPost(+post.dataset.id, t.dataset.focus); break;
            case 'ov-close': closeOverlay(); break;
            case 'modal-close': closeModal(); break;
            case 'share': { const id = post ? +post.dataset.id : ov.id; if (navigator.share) navigator.share({ url: location.origin + '/fid/post/' + id }).catch(() => { }); else copyLink(id); break; }
            case 'zoom': { ensureUI(); const lb = $('.fx-lightbox'); $('img', lb).src = t.currentSrc || t.src; lb.classList.add('open'); break; }
            case 'follow': {
                if (!CFG.viewer) return toast(ERR.auth, true);
                try {
                    const j = await api('follow', { type: t.dataset.t, id: +t.dataset.id });
                    $$('.fx-follow[data-t="' + t.dataset.t + '"][data-id="' + t.dataset.id + '"], .fx-subscribe[data-t="' + t.dataset.t + '"][data-id="' + t.dataset.id + '"]').forEach(b => {
                        b.classList.toggle('on', j.on);
                        if (b.classList.contains('fx-follow')) b.innerHTML = j.on ? ic('check', 'ic--sm') + ' Подписаны' : 'Подписаться';
                        else b.innerHTML = j.on ? ic('check', 'ic--sm') + ' Вы подписаны' : ic('plus', 'ic--sm') + ' Подписаться';
                    });
                    $$('[data-followers="' + t.dataset.t + ':' + t.dataset.id + '"]').forEach(n => { n.textContent = nfmt(j.count); });
                    toast(j.on ? 'Вы подписались' : 'Подписка отменена');
                } catch (er) { fail(er); }
                break;
            }
            case 'vote': {
                if (!CFG.viewer) return toast(ERR.auth, true);
                try { const j = await api('vote', { id: +post.dataset.id, opt: +t.dataset.i }); const wrap = t.closest('[data-poll]'); wrap.outerHTML = j.html; requestAnimationFrame(() => $$('.fx-poll.voted .fx-option', post).forEach(o => { })); } catch (er) { fail(er); }
                break;
            }
            case 'more': { t.classList.add('loading'); try { await loadFeed(t.closest('.fx-feed'), t.dataset.next); } catch (er) { t.classList.remove('loading'); fail(er); } break; }
            case 'thread': {
                if (e.target.closest('.fx-dots')) break;
                const body = $('.fx-thr__b', thr), open = thr.classList.toggle('is-open');
                body.hidden = !open;
                if (open && !body.dataset.loaded) { body.innerHTML = '<div class="fx-note">Загрузка…</div>'; try { const j = await api('thread', { id: +thr.dataset.id }, 'GET'); body.innerHTML = j.html; body.dataset.loaded = 1; } catch (er) { fail(er); thr.classList.remove('is-open'); body.hidden = true; } }
                break;
            }
            case 'clike': { if (!CFG.viewer) return toast(ERR.auth, true); const c = t.closest('.fx-cmt'); try { const j = await api('comment_like', { id: +c.dataset.id }); t.classList.toggle('on', j.on); $('span', t).textContent = j.n || ''; } catch (er) { fail(er); } break; }
            case 'cdel': { const c = t.closest('.fx-cmt'); if (!t.dataset.sure) { t.dataset.sure = 1; t.textContent = 'Точно?'; setTimeout(() => { delete t.dataset.sure; t.textContent = 'Удалить'; }, 3000); break; } try { await api('comment_delete', { id: +c.dataset.id }); c.remove(); } catch (er) { fail(er); } break; }
            case 'reply': {
                const form = ($('.fx-ov.open') ? $('.fx-ov__foot .fx-reply') : t.closest('.fx-thr__b')?.querySelector('.fx-reply')); if (!form) break;
                form.dataset.parent = t.closest('.fx-cmt').dataset.id; const to = $('.fx-reply__to', form); to.classList.remove('hidden'); $('span', to).textContent = 'Ответ для ' + t.dataset.name;
                const inp = $('input[name=body]', form); inp.placeholder = 'Ответ для ' + t.dataset.name + '…'; inp.focus(); break;
            }
            case 'cancel-reply': clearReply(t.closest('.fx-reply')); break;
            case 'collapse': t.closest('.fx-cmt').classList.toggle('collapsed'); break;
            case 'sort': { try { const j = await api('comments', { id: ov.id, sort: t.dataset.sort }, 'GET'); $('#fx-cmts').innerHTML = j.html; } catch (er) { fail(er); } break; }
            case 'photo': { const f = $('[data-file]', t.closest('form')); if (f) f.click(); break; }
            case 'poll-on': { const p = $('[data-pollb]', t.closest('form')); p.classList.remove('hidden'); $('input', p).focus(); t.closest('form').dispatchEvent(new Event('input')); break; }
            case 'poll-off': { const p = t.closest('[data-pollb]'); p.classList.add('hidden'); t.closest('form').dispatchEvent(new Event('input')); break; }
            case 'poll-add': { const p = t.closest('[data-pollb]'), n = $$('input', p).length; if (n < 6) { const i = document.createElement('input'); i.maxLength = 80; i.placeholder = 'Вариант ' + (n + 1); t.before(i); i.focus(); } break; }
            case 'article': document.dispatchEvent(new CustomEvent('fx:article', { detail: JSON.parse(t.closest('form').dataset.cfg || '{}') })); break;
            case 'as-pick': {
                const form = t.closest('form'), cfg = JSON.parse(form.dataset.cfg || '{}'), st = form._fx;
                const me = { id: 0, name: $('.fx-cc__asname', form).dataset.me || $('.fx-cc__asname', form).textContent, type: 'user' };
                $('.fx-cc__asname', form).dataset.me = me.name;
                const items = [me].concat(cfg.as || []).map(o => ({ icon: o.type === 'studio' ? 'studio' : 'user', label: o.name, run: () => { st.as = o.id; $('.fx-cc__asname', form).textContent = o.name; const cr = $('[data-cross]', form); if (cr) cr.classList.toggle('hidden', !o.id); } }));
                openMenu(t, items); break;
            }
            case 'hunt-open': {
                e.preventDefault(); openModal('<div class="fx-hm__ld">Загрузка…</div>');
                try { const j = await api('hunt', { id: +t.dataset.id }, 'GET'); $('.fx-modal__body').innerHTML = j.html; } catch (er) { fail(er); closeModal(); }
                break;
            }
            case 'hunt-join': case 'hunt-leave': {
                if (!CFG.viewer) return toast(ERR.auth, true);
                try { const j = await api(act === 'hunt-join' ? 'hunt_join' : 'hunt_leave', { id: +t.dataset.id }); $('.fx-modal__body').innerHTML = j.html; const w = $('.fx-hunt[data-id="' + t.dataset.id + '"]'); if (w && j.widget) { const tmp = document.createElement('div'); tmp.innerHTML = j.widget; w.replaceWith(tmp.firstElementChild); } toast(act === 'hunt-join' ? 'Вы участвуете в DustHunt' : 'Вы вышли из DustHunt'); } catch (er) { fail(er); }
                break;
            }
            case 'awards': {
                const list = JSON.parse(t.dataset.list || '[]');
                openModal('<h2 class="fx-hm__t">Награды</h2><div class="fx-awardlist">' + list.map(a => '<div><span class="fx-award"><span' + (a.img ? ' style="background-image:url(\'' + esc(a.img) + '\')"' : '') + '>' + (a.img ? '' : ic('award')) + '</span></span><span><b>' + esc(a.name) + '</b><small>' + esc(a.desc) + '</small></span></div>').join('') + '</div>');
                break;
            }
            case 'tpl': { const tp = $(t.dataset.tpl); if (tp) openModal(tp.innerHTML); break; }   // модалка из <template id=…> на странице
            case 'tab': showTab(t.closest('.fx-tabs') || $('.fx-tabs'), t.dataset.pane, true); break;
            case 'scroll': { const el = $(t.dataset.to); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); break; }
        }
    });

    document.addEventListener('click', e => {
        if (e.target.closest('.fx-scrim')) closeMenu();
        if (e.target.closest('.fx-lightbox')) $('.fx-lightbox').classList.remove('open');
        if (!e.target.closest('.fx-rxg')) $$('.fx-rxg.open').forEach(g => g.classList.remove('open'));
        if (!e.target.closest('.fx-menu') && !e.target.closest('[data-fx=menu],[data-fx=as-pick]')) closeMenu();
    });

    document.addEventListener('submit', e => {
        const f = e.target.closest('[data-fx-form=comment]'); if (f) { e.preventDefault(); submitComment(f); }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { if ($('.fx-lightbox.open')) $('.fx-lightbox').classList.remove('open'); else if ($('.fx-modal.open')) closeModal(); else if ($('.fx-ov.open')) closeOverlay(); else closeMenu(); }
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.fx-thr__h')) { e.preventDefault(); e.target.click(); }
    });

    /* long-press по реакции на тач-экранах, двойной тап по фото — быстрый лайк */
    let lp = 0;
    document.addEventListener('touchstart', e => {
        const b = e.target.closest('.fx-rx__main'); if (!b) return;
        lp = setTimeout(() => { b.closest('.fx-rxg').classList.add('open'); b.dataset.lp = 1; }, 380);
    }, { passive: true });
    ['touchend', 'touchmove', 'touchcancel'].forEach(ev => document.addEventListener(ev, () => clearTimeout(lp), { passive: true }));
    document.addEventListener('click', e => { const b = e.target.closest('.fx-rx__main'); if (b && b.dataset.lp) { delete b.dataset.lp; e.stopImmediatePropagation(); e.preventDefault(); } }, true);

    let lastTap = 0;
    document.addEventListener('dblclick', e => {
        const m = e.target.closest('.fx-media[data-dbl]'); if (!m || !CFG.viewer) return;
        const post = m.closest('.fx-post'), up = $('.fx-rxg--up .fx-rx__main', post), d = $('.fx-dbl', m);
        d.classList.remove('go'); void d.offsetWidth; d.classList.add('go');
        if (!up.classList.contains('on')) react(post, up.closest('.fx-rxg').dataset.def);
    });
    document.addEventListener('touchend', e => {
        const m = e.target.closest('.fx-media[data-dbl]'); if (!m) return;
        const now = Date.now(); if (now - lastTap < 300) { e.preventDefault(); m.dispatchEvent(new MouseEvent('dblclick', { bubbles: true })); } lastTap = now;
    });

    /* прогресс чтения статьи */
    document.addEventListener('scroll', e => {
        const sc = e.target; if (!sc.classList || !sc.classList.contains('fx-ov__scroll')) return;
        const bar = $('.fx-progress'); if (bar) bar.style.width = Math.min(100, sc.scrollTop / Math.max(1, sc.scrollHeight - sc.clientHeight) * 100) + '%';
    }, true);

    /* ---------------------------------------------------------------- старт */
    function stickSide() {                      // боковая колонка «липнет» только если целиком помещается в окно
        $$('.fx-side').forEach(el => { el.classList.remove('is-stick'); if (window.innerWidth > 900 && el.offsetHeight < window.innerHeight - 40) el.classList.add('is-stick'); });
    }
    function init() {
        ensureUI();
        stickSide(); window.addEventListener('resize', stickSide); window.addEventListener('load', stickSide);
        $$('form[data-fx-form=post]').forEach(initComposer);
        $$('.fx-tabs').forEach(tabs => {
            const want = new URL(location.href).searchParams.get('tab');
            if (want && $('.fx-tab[data-pane="' + want + '"]', tabs)) showTab(tabs, want, false);
        });
        initFeeds();
        const openId = +(document.body.dataset.fxOpen || (root() && root().dataset.open) || 0);
        if (openId) openPost(openId);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.FxUI = { toast, api, openPost, openModal, closeModal, openMenu, initComposer, loadFeed, ic, nfmt };
})();
