/**
 * l4t/js/l4x-pos.js — боковая панель, «Мои позиции», колода «Для тебя», модерация.
 *
 * Панель вместо модалки: создание, правка и просмотр идут в широкой панели
 * справа (75% экрана, на телефоне — весь экран). Страница под ней не теряет
 * контекст: закрыл — ты там же, где был.
 */
(function () {
    'use strict';
    var U = window.L4XUI;
    if (!U) return;
    var C = U.C, $ = U.$, $$ = U.$$, esc = U.esc, icon = U.icon, toast = U.toast, A = U.actions;
    var API = '/l4t/api/action.php';
    var MK = C.market || {};

    function act(op, data) { return U.api(API, Object.assign({ op: op }, data || {})); }
    function get(op, params) {
        return fetch(API + '?' + new URLSearchParams(Object.assign({ op: op }, params || {})), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); }).catch(function () { return { ok: false, error: 'Нет связи' }; });
    }
    function ava(u, cls) {
        var letter = esc(((u && u.name) || '?').replace('@', '').charAt(0).toUpperCase());
        return '<span class="' + (cls || 'ws-ava') + '">' + (u && u.avatar ? '<img src="' + esc(u.avatar) + '" alt="">' : letter) + '</span>';
    }
    function fitChip(f) { return f ? '<span class="fit fit--' + f[0] + '">' + esc(f[1]) + '</span>' : ''; }
    function whyList(w, limit) {
        if (!w || !w.length) return '';
        return '<ul class="why">' + w.slice(0, limit || 99).map(function (x) {
            return '<li class="why--' + x[0] + '">' + (x[0] === 'ok' ? icon('check') : '<i>!</i>') + '<span>' + esc(x[1]) + '</span></li>';
        }).join('') + '</ul>';
    }
    function chips(list) { return (list || []).length ? '<div class="ws-chips">' + list.map(function (s) { return '<span>' + esc(s) + '</span>'; }).join('') + '</div>' : ''; }
    function busy(el, on) { if (el && el.tagName === 'BUTTON') el.disabled = on; }

    /* ═════════════════════════════ ПАНЕЛЬ ═════════════════════════════ */
    var P = (function () {
        var el = document.createElement('div');
        el.className = 'l4x-panel';
        el.hidden = true;
        el.innerHTML = '<div class="l4x-panel__scrim" data-act="panel-close"></div>' +
            '<aside class="l4x-panel__box pix" role="dialog" aria-modal="true">' +
            '<header class="l4x-panel__head"><div><div class="l4x-panel__sub"></div><h2 class="l4x-panel__title"></h2></div>' +
            '<button class="l4x-panel__x" data-act="panel-close" title="Закрыть (Esc)">' + icon('close') + '</button></header>' +
            '<div class="l4x-panel__body"></div><footer class="l4x-panel__foot" hidden></footer></aside>';
        U.root.appendChild(el);
        var body = el.querySelector('.l4x-panel__body'), foot = el.querySelector('.l4x-panel__foot'), onClose = null;

        function open(o) {
            if (!el.hidden && onClose) { var f = onClose; onClose = null; f(); }
            el.querySelector('.l4x-panel__title').textContent = o.title || '';
            el.querySelector('.l4x-panel__sub').innerHTML = o.sub || '';
            body.innerHTML = '';
            if (o.node) body.appendChild(o.node); else body.innerHTML = o.html || '';
            foot.innerHTML = o.foot || ''; foot.hidden = !o.foot;
            onClose = o.onClose || null;
            el.hidden = false;
            document.documentElement.classList.add('l4x-locked');
            requestAnimationFrame(function () { el.classList.add('is-open'); });
            body.scrollTop = 0;
        }
        function close() {
            if (el.hidden) return;
            el.classList.remove('is-open');
            document.documentElement.classList.remove('l4x-locked');
            var f = onClose; onClose = null;
            setTimeout(function () { el.hidden = true; if (f) f(); }, 180);
        }
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !el.hidden) close(); });
        return { open: open, close: close, body: body, foot: foot, el: el, isOpen: function () { return !el.hidden; } };
    })();
    U.panel = P;
    A['panel-close'] = function () { P.close(); };

    /* ═════════════════════════════ МОИ ПОЗИЦИИ ═════════════════════════════ */
    var ws = $('#ws');
    var W = { items: (C.positions || {}).items || [], invites: (C.positions || {}).invites || [], sel: null, view: null, idx: {} };
    var formsHome = $('#wsForms');

    var REASON = { owner: 'снята вами', expired: 'время вышло', admin: 'скрыта модератором', deleted: 'удалена' };

    function renderList() {
        var box = $('#wsList'); if (!box) return;
        function item(p) {
            var key = p.side + '-' + p.id;
            var sub = p.live
                ? '<span class="ws-timer ws-timer--' + (p.left ? p.left[0] : 'ok') + '">' + icon('clock') + esc(p.left ? p.left[1] : 'без срока') + '</span>'
                : '<span class="ws-off">' + esc(REASON[p.reason] || 'снята') + '</span>';
            var cnt = p.count ? '<span class="ws-cnt">' + p.count + ' ' + plural(p.count, 'кандидат', 'кандидата', 'кандидатов') + '</span>' : '';
            return '<button class="ws-item ' + (p.live ? '' : 'is-off ') + (W.sel === key ? 'is-on' : '') + '" data-pos="' + key + '">' +
                '<span class="ws-item__t">' + esc(p.title) + '</span><span class="ws-item__m">' + sub + cnt + '</span>' +
                (p.attention ? '<b class="l4x-badge" title="Ждут вашего ответа">' + p.attention + '</b>' : '') + '</button>';
        }
        var need = W.items.filter(function (p) { return p.side === 'need' && p.live; });
        var offer = W.items.filter(function (p) { return p.side === 'offer' && p.live; });
        var off = W.items.filter(function (p) { return !p.live; });
        var html = '';
        if (W.invites.length) {
            html += '<div class="ws-grp ws-grp--inv"><h3>' + icon('send') + 'Вас пригласили</h3>' + W.invites.map(function (v) {
                var key = 'inv-' + v.bid_id;
                return '<button class="ws-item ' + (W.sel === key ? 'is-on' : '') + '" data-pos="' + key + '"><span class="ws-item__t">' + esc(v.need.title) + '</span>' +
                    '<span class="ws-item__m">' + esc(v.user.name) + ' · ' + esc(v.ago) + ' назад</span><b class="l4x-badge">!</b></button>';
            }).join('') + '</div>';
        }
        html += '<div class="ws-grp"><h3><i class="ws-dot ws-dot--need"></i>Мне нужно</h3>' + (need.length ? need.map(item).join('') : '<p class="ws-none">Нет активных заявок</p>') + '</div>';
        html += '<div class="ws-grp"><h3><i class="ws-dot ws-dot--offer"></i>Я могу</h3>' + (offer.length ? offer.map(item).join('') : '<p class="ws-none">Нет активных предложений</p>') + '</div>';
        if (off.length) html += '<details class="ws-grp ws-grp--off" ' + (off.some(function (p) { return W.sel === p.side + '-' + p.id; }) ? 'open' : '') + '><summary>Сняты с рынка · ' + off.length + '</summary>' + off.map(item).join('') + '</details>';
        box.innerHTML = html;
    }
    function plural(n, a, b, c) { n = Math.abs(n) % 100; var n1 = n % 10; if (n > 10 && n < 20) return c; if (n1 > 1 && n1 < 5) return b; return n1 === 1 ? a : c; }

    function refreshList() {
        return get('pos_list').then(function (r) { if (r.ok) { W.items = r.items; W.invites = r.invites; renderList(); } });
    }

    function select(key, scroll) {
        W.sel = key; renderList();
        try { var u = new URL(location.href); u.searchParams.set('pos', key); u.searchParams.delete('status'); history.replaceState(null, '', u); } catch (e) {}
        var main = $('#wsMain');
        if (scroll && window.matchMedia('(max-width: 900px)').matches) main.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (key.indexOf('inv-') === 0) {
            var inv = W.invites.filter(function (v) { return 'inv-' + v.bid_id === key; })[0];
            if (inv) renderInvite(inv); else main.innerHTML = '<div class="l4x-empty">Приглашение уже неактуально.</div>';
            return;
        }
        var m = key.match(/^(need|offer)-(\d+)$/); if (!m) return;
        main.classList.add('is-loading');
        get('pos_view', { side: m[1], id: m[2] }).then(function (d) {
            main.classList.remove('is-loading');
            if (W.sel !== key) return;
            if (!d.ok) { main.innerHTML = '<div class="l4x-empty">' + esc(d.error || 'Не удалось загрузить') + '</div>'; return; }
            W.view = d; renderMain(d);
        });
    }
    function reloadView() { if (W.sel) select(W.sel); refreshList(); }

    if (ws) {
        $('#wsList').addEventListener('click', function (e) { var b = e.target.closest('[data-pos]'); if (b) select(b.dataset.pos, true); });
        renderList();
        var want = ws.dataset.pos;
        var first = W.items.filter(function (p) { return p.live && p.attention; })[0] || W.items.filter(function (p) { return p.live; })[0];
        if (want) select(want);
        else if (W.invites.length) select('inv-' + W.invites[0].bid_id);
        else if (first) select(first.side + '-' + first.id);
        if (C.jamAction === 'create_bid') setTimeout(function () { A['pos-new']({ dataset: { side: 'need' } }); }, 50);
    }

    /* ── правая часть: выбранная позиция ─────────────────────────────── */
    function renderMain(d) {
        var main = $('#wsMain');
        var isNeed = d.side === 'need', c = d.card || {};
        W.idx = {};
        var meta = [c.price_label, isNeed ? (MK.kinds || {})[c.kind] : (c.kind === 'any' ? 'любая работа' : (MK.kinds || {})[c.kind]),
                    isNeed && c.days ? '~' + c.days + ' дн. работы' : '', 'создана ' + d.created, d.views ? d.views + ' просмотров' : ''].filter(Boolean);

        var timer = d.live
            ? '<span class="ws-timer ws-timer--' + (d.left ? d.left[0] : 'ok') + '">' + icon('clock') + 'На рынке: ' + esc(d.left ? d.left[1] : 'без срока') + '</span>' +
              '<button class="l4x-link" data-act="pos-extend-menu">Продлить</button>'
            : '<span class="ws-off">' + esc(REASON[d.reason] || 'снята') + '</span>';
        var extendRow = '<div class="ws-extend" id="wsExtend" ' + (d.live ? 'hidden' : '') + '><span>' + (d.live ? 'Продлить от сегодня на' : 'Вернуть на рынок на') + '</span>' +
            Object.keys(MK.lifetimes || { 1: '1 день', 7: '7 дней', 14: '14 дней', 30: 'месяц' }).map(function (k) {
                return '<button class="l4x-chip" data-act="pos-extend" data-days="' + k + '">' + esc((MK.lifetimes || {})[k] || k + ' дн') + '</button>';
            }).join('') + '</div>';

        var banner = '';
        if (d.reason === 'admin') banner = '<div class="ws-banner ws-banner--err">' + icon('shield') + '<span><b>Модератор снял позицию с рынка.</b> Причина: ' + esc(d.mod_reason || '—') + '. Исправьте и создайте новую.</span></div>';
        else if (d.reason === 'expired') banner = '<div class="ws-banner">' + icon('clock') + '<span><b>Время вышло — позиция снята с рынка.</b> Если ещё актуально, верните её одной кнопкой ниже.</span></div>';

        var head = '<header class="ws-head">' +
            '<div class="ws-head__top"><span class="mk-tag mk-tag--' + d.side + '">' + (isNeed ? 'Мне нужно' : 'Я могу') + '</span>' + timer + '</div>' +
            '<h2>' + esc(d.title) + '</h2>' +
            '<div class="ws-head__meta">' + meta.map(esc).join(' · ') + '</div>' + chips(c.skills) +
            '<div class="ws-head__act">' +
            '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="pos-edit">' + icon('edit') + 'Изменить</button>' +
            (d.live ? '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="pos-close">' + icon('close') + 'Снять с рынка</button>' : '') +
            (d.reason !== 'admin' && !d.live ? '<button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="pos-extend-menu">' + icon('arrow') + 'Вернуть на рынок</button>' : '') +
            '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm l4x-danger" data-act="pos-delete">Удалить</button></div>' +
            extendRow + '</header>' + banner;

        main.innerHTML = head + (isNeed ? needBody(d) : offerBody(d));
    }

    /* Группы кандидатов под заявку. Порядок — по срочности для автора. */
    function needBody(d) {
        var L = d.lists || {}, all = [];
        (L.market || []).forEach(function (c) { c.src = 'market'; all.push(c); });
        (L.responds || []).forEach(function (c) { c.src = 'respond'; all.push(c); });
        (L.people || []).forEach(function (c) { c.src = 'people'; all.push(c); });
        all.forEach(function (c, i) {
            c.key = 'c' + i; W.idx[c.key] = c;
            var rs = c.respond ? c.respond.status : '';
            c.bucket = (c.state === 'deal' || c.state === 'accepted' || rs === 'принят' || rs === 'в команде') ? 'deal'
                : (c.state === 'wants' || (c.src === 'respond' && rs === 'ожидает') || (c.src === 'market' && c.respond && rs === 'ожидает' && c.state === 'new')) ? 'todo'
                : (c.state === 'declined' || rs === 'отклонён') ? 'gone'
                : c.state === 'invited' ? 'wait'
                : c.src === 'people' ? 'people' : 'fit';
        });
        var groups = [
            ['todo', 'Хотят к вам — ответьте', 'Эти люди сами предложили себя или откликнулись.'],
            ['fit', 'Подходят — пригласите', 'Выставили на рынок предложение, которое закрывает вашу заявку.'],
            ['people', 'Могут подойти', 'Нужные навыки и статус «ищу проект», но своего предложения нет. Приглашение придёт им уведомлением.'],
            ['wait', 'Вы пригласили — ждём ответ', ''],
            ['deal', 'Договорились', 'Контакты открыты. Напишите человеку и обсудите детали.']
        ];
        var total = all.filter(function (c) { return c.bucket !== 'gone'; }).length;
        var html = '<div class="ws-sum">' + (total
            ? '<b>' + total + '</b> ' + plural(total, 'человек подходит', 'человека подходят', 'человек подходят') + ' под заявку'
            : (d.live ? 'Пока никого. Мы продолжаем искать и пришлём уведомление, как только появится подходящий человек.' : 'Позиция не на рынке — новые кандидаты не подбираются.')) + '</div>';
        groups.forEach(function (g) {
            var list = all.filter(function (c) { return c.bucket === g[0]; });
            if (!list.length) return;
            html += '<section class="ws-sec ws-sec--' + g[0] + '"><h3>' + esc(g[1]) + ' <span class="l4x-muted">' + list.length + '</span></h3>' +
                (g[2] ? '<p class="ws-sec__hint">' + esc(g[2]) + '</p>' : '') + '<div class="ws-cands">' + list.map(function (c) { return cand(c, d); }).join('') + '</div></section>';
        });
        var gone = all.filter(function (c) { return c.bucket === 'gone'; });
        if (gone.length) html += '<details class="ws-sec ws-sec--gone"><summary>Отказались или вы отклонили · ' + gone.length + '</summary><div class="ws-cands">' + gone.map(function (c) { return cand(c, d); }).join('') + '</div></details>';
        return html;
    }

    function offerBody(d) {
        var all = (d.lists || {}).needs || [];
        all.forEach(function (c, i) {
            c.key = 'c' + i; c.src = 'need'; W.idx[c.key] = c;
            c.bucket = c.state === 'deal' ? 'deal' : c.state === 'wants' ? 'todo' : c.state === 'invited' ? 'wait' : c.state === 'declined' ? 'gone' : 'fit';
        });
        var groups = [
            ['todo', 'Вас зовут — ответьте', 'Заказчики выбрали вас под свою задачу.'],
            ['fit', 'Подходящие заявки', 'Вы закрываете то, что им нужно. Предложите себя — это один клик.'],
            ['wait', 'Вы предложили себя — ждём ответ', ''],
            ['deal', 'Договорились', 'Контакты открыты.']
        ];
        var total = all.filter(function (c) { return c.bucket !== 'gone'; }).length;
        var html = '<div class="ws-sum">' + (total ? '<b>' + total + '</b> ' + plural(total, 'заявка подходит', 'заявки подходят', 'заявок подходят') + ' под ваше предложение'
            : 'Подходящих заявок пока нет. Как только появятся — пришлём уведомление.') + '</div>';
        groups.forEach(function (g) {
            var list = all.filter(function (c) { return c.bucket === g[0]; });
            if (!list.length) return;
            html += '<section class="ws-sec ws-sec--' + g[0] + '"><h3>' + esc(g[1]) + ' <span class="l4x-muted">' + list.length + '</span></h3>' +
                (g[2] ? '<p class="ws-sec__hint">' + esc(g[2]) + '</p>' : '') + '<div class="ws-cands">' + list.map(function (c) { return cand(c, d); }).join('') + '</div></section>';
        });
        return html;
    }

    /* Кнопки под кандидатом — зависят от того, на каком шаге диалог. */
    function candActions(c, full) {
        var k = ' data-k="' + c.key + '"';
        var tg = c.tg ? '<a class="l4x-btn l4x-btn--acc l4x-btn--sm" href="https://t.me/' + esc(c.tg) + '" target="_blank" rel="noopener">' + icon('telegram') + 'Написать</a>' : '';
        var more = full ? '' : '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="cand-open"' + k + '>Подробнее</button>';
        switch (c.bucket) {
            case 'deal': return '<span class="l4x-verified">' + icon('check') + 'договорились</span>' + tg + more;
            case 'wait': return '<span class="ws-state">' + icon('clock') + (c.src === 'need' ? 'Вы предложили себя' : 'Вы пригласили') + ' — ждём ответ</span>' + more;
            case 'gone': return '<span class="ws-state">' + (c.state === 'declined' ? 'Отказался(ась)' : 'Отклонено') + '</span>' + more;
            case 'todo':
                return '<button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="cand-yes"' + k + '>' + icon('check') + (c.src === 'respond' ? 'Принять' : 'Договориться') + '</button>' +
                       '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="cand-no"' + k + '>Отказать</button>' + more;
            default:
                return '<button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="cand-invite"' + k + '>' + icon('send') + (c.src === 'need' ? 'Предложить себя' : 'Пригласить') + '</button>' +
                       (c.src !== 'people' ? '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="cand-no"' + k + '>' + (c.src === 'need' ? 'Не интересно' : 'Не подходит') + '</button>' : '') + more;
        }
    }

    function cand(c, d) {
        var u = c.user || {}, o = c.offer, n = c.need;
        var price = o ? o.price_label : (n ? n.price_label : '');
        var sub = c.src === 'need' ? (n ? (MK.kinds || {})[n.kind] + ' · ' + esc(u.name) : '') : esc(u.headline || u.role || 'Специалист');
        return '<article class="ws-cand pix">' +
            '<div class="ws-cand__who">' + ava(u) + '<div class="ws-cand__id">' +
                '<b>' + esc(c.src === 'need' ? n.title : u.name) + '</b><span class="l4x-muted">' + sub + '</span></div>' +
                '<div class="ws-cand__right">' + fitChip(c.fit) + (price ? '<span class="ws-price">' + esc(price) + '</span>' : '') + '</div></div>' +
            (o ? '<div class="ws-cand__offer">«' + esc(o.title) + '»</div>' : '') +
            whyList(c.why, 4) +
            (c.respond && c.respond.message ? '<blockquote class="ws-msg">' + esc(c.respond.message.slice(0, 220)) + '</blockquote>' : '') +
            '<div class="ws-cand__act">' + candActions(c) + '</div></article>';
    }

    /* Подробно о человеке (или заявке) — в панели, не в крошечной модалке. */
    A['cand-open'] = function (el) {
        var c = W.idx[el.dataset.k]; if (!c) return;
        var u = c.user || {}, o = c.offer, n = c.need, html;
        if (c.src === 'need') {
            html = needCardHtml(n, c.why, c.fit);
        } else {
            html = '<div class="pn-grid"><div class="pn-main">' +
                '<div class="pn-person">' + ava(u, 'pn-ava') + '<div><h3>' + esc(u.name) + '</h3><div class="l4x-muted">' + esc(u.headline || u.role || '') + '</div>' +
                (u.handle ? '<a class="l4x-link" href="/l4t/' + encodeURIComponent(u.handle) + '" target="_blank">' + icon('user') + 'Полный профиль, проекты и рекомендации</a>' : '') + '</div></div>' +
                '<h4>Почему подходит</h4>' + fitChip(c.fit) + whyList(c.why) +
                (c.respond && c.respond.message ? '<h4>Сообщение к отклику</h4><blockquote class="ws-msg">' + esc(c.respond.message) + '</blockquote>' : '') +
                (o ? '<h4>Что предлагает</h4><div class="pn-offer pix"><b>' + esc(o.title) + '</b>' +
                    '<dl class="l4x-kv"><dt>Цена</dt><dd>' + esc(o.price_label) + '</dd><dt>Формат</dt><dd>' + esc(o.kind === 'any' ? 'любая работа' : (MK.kinds || {})[o.kind] || '') + '</dd>' +
                    '<dt>Свободен</dt><dd>' + esc(o.from ? 'с ' + o.from.split('-').reverse().slice(0, 2).join('.') : 'сейчас') + '</dd>' +
                    (o.hours ? '<dt>Часов в неделю</dt><dd>' + o.hours + '</dd>' : '') + '</dl>' +
                    (o.details ? '<div class="l4x-prose">' + esc(o.details) + '</div>' : '') + '</div>'
                  : '<p class="l4x-hint">Своё предложение на рынок этот человек не выставлял — условия обсудите после приглашения.</p>') +
                '</div><aside class="pn-side"><h4>Навыки</h4>' + (chips((u.skills || []).length ? u.skills : (o ? o.skills : [])) || '<span class="l4x-muted">не указаны</span>') + '</aside></div>';
        }
        P.open({ title: c.src === 'need' ? n.title : u.name, sub: c.src === 'need' ? 'Заявка' : 'Кандидат', html: html,
                 foot: '<div class="ws-cand__act">' + candActions(c, true) + '</div>' });
    };

    function needCardHtml(n, why, fit) {
        var u = n.user || {};
        var kv = [['Оплата', n.price_label], ['Формат', (MK.kinds || {})[n.kind]], ['Срок работы', n.days ? n.days + ' дн.' : ''], ['Условия', n.extra], ['Опубликована', n.age ? n.age + ' назад' : '']]
            .filter(function (x) { return x[1]; });
        return '<div class="pn-grid"><div class="pn-main">' +
            (why ? '<h4>Чем вам подходит</h4>' + fitChip(fit) + whyList(why) : '') +
            '<dl class="l4x-kv">' + kv.map(function (x) { return '<dt>' + x[0] + '</dt><dd>' + esc(x[1]) + '</dd>'; }).join('') + '</dl>' +
            (n.details ? '<h4>Подробности</h4><div class="l4x-prose">' + esc(n.details) + '</div>' : '') + '</div>' +
            '<aside class="pn-side"><h4>Заказчик</h4><div class="pn-person pn-person--sm">' + ava(u, 'pn-ava') + '<div><b>' + esc(u.name) + '</b><div class="l4x-muted">' + esc(u.role || '') + '</div>' +
            (u.handle ? '<a class="l4x-link" href="/l4t/' + encodeURIComponent(u.handle) + '" target="_blank">Профиль</a>' : '') + '</div></div>' +
            '<h4>Навыки</h4>' + (chips(n.skills) || '<span class="l4x-muted">—</span>') + '</aside></div>';
    }

    function after(r, okText) {
        if (!r.ok) { toast(r.error || 'Не получилось', 'err'); return false; }
        toast(r.deal ? 'Договорились! Контакты открыты' : okText);
        P.close(); reloadView();
        return true;
    }
    A['cand-invite'] = function (el) {
        var c = W.idx[el.dataset.k], d = W.view; if (!c || !d) return;
        busy(el, true);
        var p = c.src === 'people' ? act('invite', { bid_id: d.id, user_id: c.uid })
              : c.src === 'need' ? act('propose', { bid_id: c.need.id, offer_id: d.id })
              : act('propose', { bid_id: d.id, offer_id: c.offer.id });
        p.then(function (r) { busy(el, false); after(r, c.src === 'need' ? 'Предложили себя — ждём ответ заказчика' : 'Приглашение отправлено'); });
    };
    A['cand-yes'] = function (el) {
        var c = W.idx[el.dataset.k]; if (!c) return;
        busy(el, true);
        var p = c.src === 'respond' || (!c.match_id && c.respond) ? act('respond_accept', { id: c.respond.id }) : act('match_answer', { id: c.match_id, yes: 1 });
        p.then(function (r) { busy(el, false); after(r, 'Принято — контакты открыты'); });
    };
    A['cand-no'] = function (el) {
        var c = W.idx[el.dataset.k], d = W.view; if (!c || !d) return;
        busy(el, true);
        var p;
        if (c.src === 'respond') p = act('respond_hide', { id: c.respond.id });
        else if (c.bucket === 'todo' && c.match_id) p = act('match_answer', { id: c.match_id, yes: 0 });
        else p = c.src === 'need' ? act('cand_hide', { bid_id: c.need.id, offer_id: d.id }) : act('cand_hide', { bid_id: d.id, offer_id: c.offer.id });
        p.then(function (r) { busy(el, false); after(r, 'Скрыто — больше не покажем'); });
    };

    /* Приглашение мне: заявка + чем я подхожу + ответ. */
    function renderInvite(v) {
        $('#wsMain').innerHTML = '<header class="ws-head"><div class="ws-head__top"><span class="mk-tag mk-tag--need">Вас пригласили</span></div>' +
            '<h2>' + esc(v.need.title) + '</h2><div class="ws-head__meta">' + esc(v.user.name) + ' зовёт вас · ' + esc(v.ago) + ' назад</div></header>' +
            needCardHtml(v.need, v.why, null) +
            '<div class="ws-cand__act ws-inv-act"><button class="l4x-btn l4x-btn--acc" data-act="inv-yes" data-bid="' + v.bid_id + '">' + icon('check') + 'Принять — открыть контакты</button>' +
            '<button class="l4x-btn l4x-btn--ghost" data-act="inv-no" data-bid="' + v.bid_id + '">Отказаться</button></div>';
    }
    function invAnswer(el, yes) {
        busy(el, true);
        act('invite_answer', { bid_id: el.dataset.bid, yes: yes ? 1 : 0 }).then(function (r) {
            busy(el, false);
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            toast(yes ? 'Отлично! Заказчик получил ваши контакты' : 'Отказ отправлен');
            W.sel = null; refreshList().then(function () { $('#wsMain').innerHTML = '<div class="l4x-empty">' + (yes ? 'Готово. Отклик — во вкладке «Отклики».' : 'Приглашение отклонено.') + '</div>'; });
        });
    }
    A['inv-yes'] = function (el) { invAnswer(el, true); };
    A['inv-no'] = function (el) { invAnswer(el, false); };

    /* ── таймер, снятие, удаление ─────────────────────────────────────── */
    A['pos-extend-menu'] = function () { var e = $('#wsExtend'); if (e) e.hidden = !e.hidden; };
    A['pos-extend'] = function (el) {
        var d = W.view; if (!d) return;
        act('pos_extend', { side: d.side, id: d.id, days: el.dataset.days }).then(function (r) {
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            toast('На рынке ещё ' + el.textContent); reloadView();
        });
    };
    A['pos-close'] = function () {
        var d = W.view; if (!d) return;
        act('pos_close', { side: d.side, id: d.id }).then(function (r) {
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            toast('Снято с рынка. Вернуть можно в любой момент'); reloadView();
        });
    };
    A['pos-delete'] = function () {
        var d = W.view; if (!d) return;
        P.open({ title: 'Удалить «' + d.title + '»?', sub: d.side === 'need' ? 'Заявка' : 'Предложение',
            html: '<p>Позиция исчезнет из вашего списка и с рынка. Уже заключённые договорённости и отклики сохранятся.</p><p class="l4x-hint">Если просто неактуально сейчас — лучше «Снять с рынка»: потом вернёте одной кнопкой.</p>',
            foot: '<button class="l4x-btn l4x-btn--ghost" data-act="panel-close">Отмена</button><button class="l4x-btn l4x-btn--acc l4x-danger-bg" data-act="pos-delete-yes">Удалить</button>' });
    };
    A['pos-delete-yes'] = function (el) {
        var d = W.view; busy(el, true);
        act('pos_delete', { side: d.side, id: d.id }).then(function (r) {
            busy(el, false);
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            P.close(); toast('Удалено'); W.sel = null; W.view = null;
            $('#wsMain').innerHTML = '<div class="l4x-empty">Позиция удалена.</div>'; refreshList();
        });
    };

    /* ── создание и правка — формы переезжают в панель ─────────────── */
    function formInPanel(side, title, sub) {
        var node = $(side === 'need' ? '#bidForm' : '#offerForm');
        if (!node) return;
        P.open({ title: title, sub: sub, node: node,
                 foot: '<button class="l4x-btn l4x-btn--ghost" data-act="panel-close">Отмена</button>' +
                       '<button class="l4x-btn l4x-btn--acc" data-act="panel-submit" data-form="' + node.id + '">' + icon('check') + (title.indexOf('Изменить') === 0 ? 'Сохранить' : 'Опубликовать') + '</button>',
                 onClose: function () { formsHome.appendChild(node); } });
        var first = node.querySelector('input[name="role"], input[name="title"]');
        if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 220);
    }
    A['panel-submit'] = function (el) {
        var f = document.getElementById(el.dataset.form); if (!f) return;
        if (f.requestSubmit) f.requestSubmit(); else f.dispatchEvent(new Event('submit', { cancelable: true }));
    };
    A['pos-new'] = function (el) {
        var side = el.dataset.side;
        var cancel = $(side === 'need' ? '#bidCancel' : '#offerCancel');
        if (cancel) cancel.click();                                    // сбросить прошлую правку
        if (side === 'offer') { var of = $('#offerForm'); $$('input[name="skills"][data-mine]', of).forEach(function (c) { c.checked = true; }); of.dispatchEvent(new Event('change')); }
        formInPanel(side, side === 'need' ? (C.jamAction === 'create_bid' ? 'Заявка на джем' : 'Кто вам нужен?') : 'Что вы можете?',
                    side === 'need' ? 'Новая заявка · Мне нужно' : 'Новое предложение · Я могу');
    };
    A['pos-edit'] = function () {
        var d = W.view; if (!d) return;
        var e = d.edit, node;
        if (d.side === 'need') {
            A['edit-bid']({ dataset: { bid: JSON.stringify(e) } });
            node = $('#bidForm');
        } else {
            A['offer-edit']({ dataset: { offer: JSON.stringify(e) } });
            node = $('#offerForm');
        }
        var r = node.querySelector('input[name="lifetime_days"][value="' + (e.lifetime_days || 30) + '"]'); if (r) r.checked = true;
        formInPanel(d.side, 'Изменить: ' + d.title, 'Сохранение перезапустит таймер на выбранный срок');
    };

    /* ═════════════════════════════ «ДЛЯ ТЕБЯ»: КОЛОДА ═════════════════════════════ */
    var sw = $('#sw');
    var S = { items: [], loaded: false, busy: false, lastSkip: null };

    function loadDeck() {
        if (!sw || S.busy) return;
        S.busy = true;
        get('deck').then(function (r) {
            S.busy = false; S.loaded = true;
            if (!r.ok) { $('#swStack').innerHTML = '<div class="sw-empty">' + esc(r.error || 'Не удалось загрузить') + '</div>'; return; }
            S.items = r.items; S.offers = r.offers || []; renderDeck();
        });
    }
    function deckCard(it, i) {
        var n = it.need, u = it.user || {};
        return '<article class="sw-card pix" data-i="' + i + '" style="--d:' + i + '">' +
            '<div class="sw-card__lbl sw-card__lbl--skip">Пропустить</div><div class="sw-card__lbl sw-card__lbl--go">Откликнуться</div>' +
            '<div class="sw-card__top">' + fitChip(it.fit) + '<span class="l4x-chip">' + esc((MK.kinds || {})[n.kind] || '') + '</span>' +
                (it.expires ? '<span class="ws-timer ws-timer--' + it.expires[0] + '">' + icon('clock') + esc(it.expires[1]) + '</span>' : '') + '</div>' +
            '<h3>' + esc(n.title) + '</h3>' +
            '<div class="sw-card__price">' + esc(n.price_label) + (n.days ? ' · ~' + n.days + ' дн.' : '') + '</div>' +
            '<div class="sw-card__who">' + ava(u) + '<span><b>' + esc(u.name) + '</b><span class="l4x-muted">' + esc(u.role || 'заказчик') + ' · ' + esc(n.age) + ' назад</span></span></div>' +
            chips(n.skills.slice(0, 6)) + whyList(it.why, 3) +
            (n.details ? '<p class="sw-card__txt">' + esc(n.details.slice(0, 260)) + (n.details.length > 260 ? '…' : '') + '</p>' : '') +
            (it.offer_title ? '<div class="sw-card__from">Отклик уйдёт от вашего предложения «' + esc(it.offer_title) + '»</div>' : '') +
            '</article>';
    }
    function renderDeck() {
        var st = $('#swStack');
        if (!S.items.length) {
            st.innerHTML = '<div class="sw-empty"><b>Вы посмотрели всё подходящее.</b><span>Новые заявки появятся — пришлём уведомление. А пока можно заглянуть в стакан или выставить своё предложение «Я могу».</span>' +
                '<div><button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-tab-go="market">Открыть рынок</button> <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="pos-new-go" data-side="offer">Я могу…</button></div></div>';
            $('#swLeft').textContent = '';
            $$('.sw-btn', sw).forEach(function (b) { b.disabled = true; });
            return;
        }
        $$('.sw-btn', sw).forEach(function (b) { b.disabled = false; });
        st.innerHTML = S.items.slice(0, 3).map(deckCard).reverse().join('');
        $('#swLeft').textContent = 'В колоде: ' + S.items.length;
        bindDrag($('.sw-card[data-i="0"]', st));
    }
    sw && sw.addEventListener('click', function (e) {
        var g = e.target.closest('[data-tab-go]'); if (g) U.showTab(g.dataset.tabGo);
    });
    A['pos-new-go'] = function (el) { U.showTab('bids'); A['pos-new'](el); };

    function bindDrag(card) {
        if (!card) return;
        var x0 = 0, y0 = 0, dx = 0, dragging = false;
        card.addEventListener('pointerdown', function (e) {
            if (e.button !== 0 || e.target.closest('a,button')) return;
            dragging = true; x0 = e.clientX; y0 = e.clientY; dx = 0;
            card.setPointerCapture(e.pointerId); card.classList.add('is-drag');
        });
        card.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            dx = e.clientX - x0;
            var dy = (e.clientY - y0) * 0.2;
            card.style.transform = 'translate(' + dx + 'px,' + dy + 'px) rotate(' + (dx / 22) + 'deg)';
            card.style.setProperty('--skip', Math.max(0, Math.min(1, -dx / 110)));
            card.style.setProperty('--go', Math.max(0, Math.min(1, dx / 110)));
        });
        function end() {
            if (!dragging) return;
            dragging = false; card.classList.remove('is-drag');
            if (dx > 110) decide('apply');
            else if (dx < -110) decide('skip');
            else { card.style.transform = ''; card.style.setProperty('--skip', 0); card.style.setProperty('--go', 0); }
        }
        card.addEventListener('pointerup', end);
        card.addEventListener('pointercancel', end);
    }

    function decide(dir, msg, offerId) {
        var it = S.items[0]; if (!it) return;
        var card = $('.sw-card[data-i="0"]', sw);
        if (card) { card.classList.add(dir === 'apply' ? 'is-out-r' : 'is-out-l'); }
        S.items.shift();
        var oid = offerId != null ? offerId : (it.offer_id || '');
        act('swipe', { bid_id: it.bid_id, dir: dir, message: msg || '', offer_id: oid }).then(function (r) {
            if (!r.ok) { toast(r.error || 'Не получилось', 'err'); return; }
            if (dir === 'apply') toast(r.deal ? 'Договорились! Контакты — в «Мои позиции»' : 'Отклик отправлен');
        });
        S.lastSkip = dir === 'skip' ? it : null;
        $('#swUndo').hidden = !S.lastSkip;
        setTimeout(function () { renderDeck(); if (S.items.length < 3 && S.loaded) topUp(); }, 220);
    }
    function topUp() {
        get('deck').then(function (r) {
            if (!r.ok) return;
            var have = {}; S.items.forEach(function (x) { have[x.bid_id] = 1; });
            var add = r.items.filter(function (x) { return !have[x.bid_id]; });
            if (add.length) { var wasEmpty = !S.items.length; S.items = S.items.concat(add); if (wasEmpty) renderDeck(); else $('#swLeft').textContent = 'В колоде: ' + S.items.length; }
        });
    }
    A['sw-skip'] = function () { decide('skip'); };
    A['sw-apply'] = function () { decide('apply'); };
    A['sw-undo'] = function () {
        var it = S.lastSkip; if (!it) return;
        act('swipe_undo', { bid_id: it.bid_id }).then(function () {
            S.items.unshift(it); S.lastSkip = null; $('#swUndo').hidden = true; renderDeck();
        });
    };
    A['sw-info'] = function () {
        var it = S.items[0]; if (!it) return;
        var offers = S.offers || [];
        var from = offers.length
            ? '<label class="l4x-field"><span class="l4x-field__label">От какого предложения</span><select class="l4x-input" id="swFrom"><option value="0">Без предложения — просто отклик</option>' +
              offers.map(function (o) { return '<option value="' + o.id + '" ' + (o.id === it.offer_id ? 'selected' : '') + '>' + esc(o.title) + '</option>'; }).join('') + '</select></label>'
            : '';
        P.open({ title: it.need.title, sub: 'Заявка · ' + esc(it.user.name),
            html: needCardHtml(it.need, it.why, it.fit) + '<div class="l4x-sep"><h4>Ваш отклик</h4>' + from +
                  '<label class="l4x-field"><span class="l4x-field__label">Пара слов заказчику (необязательно)</span><textarea class="l4x-input" id="swMsg" rows="3" maxlength="1000" placeholder="Почему вам интересно и что уже делали похожего"></textarea></label></div>',
            foot: '<button class="l4x-btn l4x-btn--ghost" data-act="sw-info-skip">Пропустить</button><button class="l4x-btn l4x-btn--acc" data-act="sw-info-go">' + icon('check') + 'Откликнуться</button>' });
    };
    A['sw-info-go'] = function () {
        var m = $('#swMsg') ? $('#swMsg').value : '', f = $('#swFrom');
        P.close(); decide('apply', m, f ? +f.value : undefined);
    };
    A['sw-info-skip'] = function () { P.close(); decide('skip'); };

    if (sw) {
        var fyTab = $('.l4x-tab[data-tab="foryou"]');
        if (C.tab === 'foryou') loadDeck();
        if (fyTab) fyTab.addEventListener('click', function () { if (!S.loaded) loadDeck(); });
        document.addEventListener('keydown', function (e) {
            if (!sw.offsetParent || P.isOpen() || /INPUT|TEXTAREA|SELECT/.test((e.target || {}).tagName || '')) return;
            if (e.key === 'ArrowLeft') { e.preventDefault(); decide('skip'); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); decide('apply'); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); A['sw-info'](); }
        });
    }

    /* ═════════════════════════════ МОДЕРАЦИЯ ═════════════════════════════ */
    var mod = $('[data-view="mod"]');
    var MD = { filter: 'all', q: '', items: [], loaded: false, t: null };
    function loadMod() {
        MD.loaded = true;
        get('mod_list', { filter: MD.filter, q: MD.q }).then(function (r) {
            if (!r.ok) { $('#modList').innerHTML = '<div class="l4x-empty">' + esc(r.error || 'Ошибка') + '</div>'; return; }
            MD.items = r.items; renderMod();
        });
    }
    function renderMod() {
        $('#modCount').textContent = MD.items.length;
        $('#modList').innerHTML = MD.items.length ? MD.items.map(function (p, i) {
            var st = p.close_reason === 'admin' ? 'скрыта: ' + (p.mod_reason || '') : p.close_reason === 'deleted' ? 'удалена' + (p.mod_reason ? ': ' + p.mod_reason : '') : (p.left ? p.left[1] : 'без срока');
            return '<div class="mod-row pix"><span class="mk-tag mk-tag--' + p.side + '">' + (p.side === 'need' ? 'спрос' : 'предл.') + '</span>' +
                '<div class="mod-row__main"><b>' + esc(p.title) + '</b><span class="l4x-muted">' + esc(p.user) + ' · создана ' + esc(p.age) + ' назад · ' + esc(st) + '</span></div>' +
                (MD.filter === 'hidden'
                    ? '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="mod-do" data-i="' + i + '" data-a="restore">Вернуть</button>'
                    : '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="mod-open" data-i="' + i + '" data-a="hide">Скрыть</button>' +
                      '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm l4x-danger" data-act="mod-open" data-i="' + i + '" data-a="delete">Удалить</button>') + '</div>';
        }).join('') : '<div class="l4x-empty">Пусто.</div>';
    }
    if (mod) {
        $$('#modFilter button').forEach(function (b) {
            b.addEventListener('click', function () {
                $$('#modFilter button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                MD.filter = b.dataset.f; loadMod();
            });
        });
        $('#modQ').addEventListener('input', function () { var v = this.value.trim(); clearTimeout(MD.t); MD.t = setTimeout(function () { MD.q = v; loadMod(); }, 300); });
        var mt = $('.l4x-tab[data-tab="mod"]');
        if (C.tab === 'mod') loadMod();
        if (mt) mt.addEventListener('click', function () { if (!MD.loaded) loadMod(); });
    }
    A['mod-open'] = function (el) {
        var p = MD.items[+el.dataset.i], a = el.dataset.a; if (!p) return;
        var R = C.modReasons || {};
        P.open({ title: (a === 'hide' ? 'Скрыть: ' : 'Удалить: ') + p.title, sub: 'Модерация · автор ' + esc(p.user),
            html: '<p>' + (a === 'hide' ? 'Позиция уйдёт с рынка. Автор получит уведомление с причиной и увидит её в «Мои позиции».' : 'Позиция исчезнет с рынка и из списка автора. Автор получит уведомление с причиной.') + '</p>' +
                  '<div class="l4x-field"><span class="l4x-field__label">Причина — её увидит автор</span><div class="l4x-tags" id="modPresets">' +
                  Object.keys(R).map(function (k) { return '<button class="l4x-chip" data-r="' + esc(R[k]) + '">' + esc(R[k]) + '</button>'; }).join('') + '</div>' +
                  '<textarea class="l4x-input" id="modReason" rows="3" maxlength="300" placeholder="Например: заявка висит 3 месяца, автор не отвечает на отклики"></textarea></div>',
            foot: '<button class="l4x-btn l4x-btn--ghost" data-act="panel-close">Отмена</button><button class="l4x-btn l4x-btn--acc ' + (a === 'delete' ? 'l4x-danger-bg' : '') + '" data-act="mod-do" data-i="' + el.dataset.i + '" data-a="' + a + '">' + (a === 'hide' ? 'Скрыть' : 'Удалить') + '</button>' });
        $('#modPresets').addEventListener('click', function (e) {
            var b = e.target.closest('[data-r]'); if (!b) return;
            var t = $('#modReason'); t.value = b.dataset.r + (t.value && t.value.indexOf(b.dataset.r) !== 0 ? '. ' + t.value : ''); t.focus();
        });
    };
    A['mod-do'] = function (el) {
        var p = MD.items[+el.dataset.i]; if (!p) return;
        var reason = $('#modReason') ? $('#modReason').value.trim() : '';
        busy(el, true);
        act('moderate', { side: p.side, id: p.id, action: el.dataset.a, reason: reason }).then(function (r) {
            busy(el, false);
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            P.close(); toast({ hide: 'Скрыто, автор уведомлён', delete: 'Удалено, автор уведомлён', restore: 'Возвращено на рынок' }[el.dataset.a]); loadMod();
        });
    };
})();
