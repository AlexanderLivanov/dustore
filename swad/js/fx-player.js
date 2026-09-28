/* ======================================================================
   swad/js/fx-player.js — поведение страницы игрока (/player/<ник>):
   кнопка дружбы, «Принять» во входящих заявках, поиск по друзьям, формы «Аккаунт», подтверждение выхода.
   Работает поверх fx.js (тосты — FxUI.toast); токен — window.FX.csrf.
   ====================================================================== */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const CSRF = (window.FX && window.FX.csrf) || '';
    const toast = (m, bad) => (window.FxUI && window.FxUI.toast ? window.FxUI.toast(m, bad) : alert(m));

    async function friends(action, userId) {
        const res = await fetch('/api/friends.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: new URLSearchParams({ action, user_id: userId, csrf: CSRF }),
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Ответ сервера: ' + raw.slice(0, 160)); }
        if (!data.success) throw new Error(data.error || 'Ошибка');
        return data;
    }

    /* ---- кнопка дружбы в баннере ---- */
    const NEXT = {
        send: ['cancel', 'Отменить заявку'],
        cancel: ['send', 'Добавить в друзья'],
        accept: ['remove', 'Завершить дружбу'],
        remove: ['send', 'Добавить в друзья'],
    };
    const fbtn = $('#friendActionBtn');
    if (fbtn) fbtn.addEventListener('click', async () => {
        if (fbtn.disabled) return;
        const action = fbtn.dataset.action;
        fbtn.disabled = true;
        try {
            await friends(action, fbtn.dataset.user);
            const [na, nt] = NEXT[action] || NEXT.cancel;
            fbtn.dataset.action = na;
            $('#friendActionBtnText').textContent = nt;
            /* «Написать» доступно только друзьям: дружба начинается после accept и заканчивается на remove */
            const msg = $('#friendMsgBtn');
            if (msg) msg.classList.toggle('is-hidden', na !== 'remove');
        } catch (e) { toast(e.message, true); }
        fbtn.disabled = false;
    });

    /* ---- принять входящую заявку ---- */
    $$('[data-friend-accept]').forEach(b => b.addEventListener('click', async () => {
        b.disabled = true;
        try {
            await friends('accept', b.dataset.user);
            b.classList.add('on');
            b.textContent = 'В друзьях';
        } catch (e) { toast(e.message, true); b.disabled = false; }
    }));

    /* ---- поиск по друзьям: фильтруем уже отрисованный список ---- */
    const input = $('#friendsSearch');
    if (input) {
        const wrap = $('#friendsSearchWrap'), clear = $('#friendsSearchClear'), empty = $('#friendsEmpty');
        const rows = $$('#friendsList .fx-frow');
        const apply = () => {
            const q = input.value.toLowerCase().trim();
            let shown = 0;
            rows.forEach(r => { const hit = !q || (r.dataset.search || '').includes(q); r.hidden = !hit; if (hit) shown++; });
            wrap.classList.toggle('has-value', q !== '');
            if (empty) empty.hidden = shown !== 0;
        };
        input.addEventListener('input', apply);
        input.addEventListener('keydown', e => { if (e.key === 'Escape') { input.value = ''; apply(); } });
        clear.addEventListener('click', () => { input.value = ''; apply(); input.focus(); });
    }

    /* ---- формы «Аккаунт»: отправка без перехода, ответ — прямо в форме ---- */
    $$('[data-account-form]').forEach(form => {
        const msg = $('.fx-msg', form), btn = $('button', form);
        form.addEventListener('submit', async e => {
            e.preventDefault();
            btn.disabled = true;
            msg.className = 'fx-msg';
            msg.textContent = '';
            let data;
            try {
                // getAttribute, а не form.action: в форме есть поле name="action" (DOM clobbering)
                const res = await fetch(form.getAttribute('action'), { method: 'POST', headers: { 'X-CSRF-Token': form.elements.csrf.value }, body: new FormData(form) });
                data = await res.json();
            } catch (err) {
                data = { ok: false, error: 'Нет связи с сервером, попробуйте ещё раз' };
            }
            msg.className = 'fx-msg ' + (data.ok ? 'is-ok' : 'is-error');
            msg.textContent = data.ok ? data.message : (data.error || 'Не получилось');
            if (data.ok) $$('input[type="password"]', form).forEach(i => { i.value = ''; });
            btn.disabled = false;
        });
    });

    /* ---- подтверждение действий (выход из аккаунта) ---- */
    $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
})();
