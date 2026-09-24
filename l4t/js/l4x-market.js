/**
 * l4t/js/l4x-market.js — рынок L4T: котировки, стакан, лента сделок,
 * позиции «Мне нужно / Я могу», совпадения и прямые предложения.
 *
 * Данные — L4X.market (первый рендер без запроса) + опрос API раз в 20 с,
 * только пока вкладка видима: скрытая вкладка не должна дёргать сервер.
 */
(function () {
    'use strict';
    var U = window.L4XUI;
    if (!U) return;
    var C = U.C, MK = C.market, M = U.M, $ = U.$, $$ = U.$$, esc = U.esc, icon = U.icon, toast = U.toast, A = U.actions;
    var API = '/l4t/api/action.php';

    function act(op, data) { return U.api(API, Object.assign({ op: op }, data || {})); }
    function get(op, params) {
        return fetch(API + '?' + new URLSearchParams(Object.assign({ op: op }, params || {})), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
    }
    function k(v) { return v == null ? '—' : (v >= 1000 ? (Math.round(v / 100) / 10).toString().replace('.', ',').replace(/,0$/, '') + 'k' : String(v)); }
    function reload(q) { setTimeout(function () { location.href = '/l4t/?tab=bids' + (q || ''); }, 500); }

    /* ═════════════════════════════ СТАКАН ═════════════════════════════ */
    var root = $('#mk');
    var state = { skill: null, quotes: MK ? MK.quotes : [], book: null, q: '' };

    if (root && MK) {
        $$('#mkPane button').forEach(function (b) {
            b.addEventListener('click', function () {
                $$('#mkPane button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                $('#mkBookPane').hidden = b.dataset.pane !== 'book';
                $('#mkFeedPane').hidden = b.dataset.pane !== 'feed';
            });
        });
        $('#mkQ').addEventListener('input', function () { state.q = this.value.trim().toLowerCase(); renderQuotes(); });

        var fromHash = (location.hash.match(/skill=([\w-]+)/) || [])[1];
        var saved = null; try { saved = localStorage.getItem('l4x.skill'); } catch (e) {}
        renderQuotes();
        renderTape(MK.tape);
        var first = fromHash || saved || (state.quotes[0] && state.quotes[0].slug);
        if (first) selectSkill(first);

        setInterval(function () {
            if (document.hidden || !root.offsetParent) return;       // вкладка скрыта — не опрашиваем
            get('quotes').then(function (r) {
                if (!r.ok) return;
                state.quotes = r.quotes; renderQuotes(); renderTape(r.tape);
                bump('#mkNeeds', r.summary.needs); bump('#mkOffers', r.summary.offers); bump('#mkDeals', r.summary.deals7);
            });
            if (state.skill) loadBook(state.skill, true);
        }, 20000);
    }

    function bump(sel, v) {
        var el = $(sel); if (!el || +el.textContent === v) return;
        el.textContent = v; el.classList.remove('mk-flash'); void el.offsetWidth; el.classList.add('mk-flash');
    }

    function spreadHtml(q) {
        if (q.spread == null) return '<span class="mk-sp mk-sp--none">—</span>';
        if (q.spread <= 0) return '<span class="mk-sp mk-sp--cross" title="Лучший бюджет выше лучшей цены — сделки возможны">сход.</span>';
        return '<span class="mk-sp" title="Разрыв между лучшей ценой и лучшим бюджетом">+' + k(q.spread) + '</span>';
    }

    function renderQuotes() {
        var box = $('#mkQuotes'); if (!box) return;
        var list = state.quotes.filter(function (q) { return !state.q || q.name.toLowerCase().indexOf(state.q) > -1 || q.slug.indexOf(state.q) > -1; });
        var lastRole = null;
        box.innerHTML = list.length ? list.map(function (q) {
            var sep = (lastRole === true && q.kind !== 'role') ? '<div class="mk-qsep">Движки и инструменты</div>' : '';
            lastRole = q.kind === 'role';
            var tot = q.needs + q.offers, nw = tot ? Math.round(q.needs / tot * 100) : 50;
            var tag = q.balance > .33 ? '<em class="mk-def" title="Спроса заметно больше, чем предложения">дефицит</em>'
                    : q.balance < -.33 ? '<em class="mk-sur" title="Предложения больше, чем спроса">избыток</em>' : '';
            return sep + '<button class="mk-q ' + (q.slug === state.skill ? 'is-on' : '') + '" data-skill="' + q.slug + '">' +
                '<span class="mk-q__name g-' + q.grp + '">' + esc(q.name) + tag + '</span>' +
                '<span class="mk-q__n mk-need">' + q.needs + '</span><span class="mk-q__n mk-offer">' + q.offers + '</span>' + spreadHtml(q) +
                '<i class="mk-q__bal"><b style="width:' + nw + '%"></b></i></button>';
        }).join('') : '<div class="l4x-muted" style="padding:14px;font-size:13px">На рынке пока пусто по этому запросу.</div>';
    }
    if (root) $('#mkQuotes').addEventListener('click', function (e) {
        var b = e.target.closest('[data-skill]'); if (b) selectSkill(b.dataset.skill);
    });

    function selectSkill(slug) {
        state.skill = slug;
        try { localStorage.setItem('l4x.skill', slug); } catch (e) {}
        try { history.replaceState(null, '', location.pathname + location.search + '#skill=' + slug); } catch (e) {}
        renderQuotes();
        loadBook(slug, false);
    }

    function loadBook(slug, quiet) {
        if (!quiet) $('#mkBook').classList.add('is-loading');
        get('book', { skill: slug }).then(function (r) {
            $('#mkBook').classList.remove('is-loading');
            if (!r.ok || state.skill !== slug) return;
            state.book = r.book; renderBook(r.book);
        });
    }

    /* Уровень стакана: цена, число позиций, полоса накопленной глубины.
       Спрос растёт влево от центра, предложение — вправо, как в биржевом стакане. */
    function levelRow(l, side, max, cross) {
        var w = Math.round(l.depth / max * 100);
        return '<div class="mk-lv mk-lv--' + side + (cross ? ' is-cross' : '') + '" data-orders=\'' + esc(JSON.stringify(l.orders.map(function (o) { return o.id; }))) + '\'>' +
            '<i class="mk-lv__depth" style="width:' + w + '%"></i>' +
            '<span class="mk-lv__price">' + k(l.price) + ' ₽</span><span class="mk-lv__cnt">' + l.count + '</span></div>';
    }
    function orderRow(o) {
        return '<button class="mk-ord" data-side="' + o.side + '" data-id="' + o.id + '">' +
            '<span class="mk-ord__t">' + esc(o.title) + (o.mine ? ' <em class="mk-mine">ваше</em>' : '') + '</span>' +
            '<span class="mk-ord__m">' + esc(o.user.name) + ' · ' + esc((MK.kinds[o.kind] || 'любая работа').toLowerCase()) + ' · ' + esc(o.age) + '</span>' +
            '<span class="mk-ord__p">' + esc(o.price_label) + '</span></button>';
    }

    var orderIndex = {};
    function renderBook(b) {
        orderIndex = {};
        [b.need, b.offer].forEach(function (s) {
            s.levels.forEach(function (l) { l.orders.forEach(function (o) { orderIndex[o.side + o.id] = o; }); });
            s.other.concat(s.nego).forEach(function (o) { orderIndex[o.side + o.id] = o; });
        });
        var sp = b.spread == null ? '<span class="mk-badge">нет встречных цен</span>'
            : b.crossed ? '<span class="mk-badge mk-badge--cross">сходится · бюджет выше цены на ' + k(-b.spread) + ' ₽</span>'
            : '<span class="mk-badge mk-badge--gap">спред ' + k(b.spread) + ' ₽</span>';
        var maxN = b.need.levels.length, maxO = b.offer.levels.length, rows = Math.max(maxN, maxO);
        var ladder = '';
        for (var i = 0; i < rows; i++) {
            var n = b.need.levels[i], o = b.offer.levels[i];
            ladder += '<div class="mk-row">' +
                (n ? levelRow(n, 'need', b.max_depth, b.offer.best != null && n.price >= b.offer.best) : '<div class="mk-lv mk-lv--empty"></div>') +
                (o ? levelRow(o, 'offer', b.max_depth, b.need.best != null && o.price <= b.need.best) : '<div class="mk-lv mk-lv--empty"></div>') + '</div>';
        }
        function list(arr, empty) { return arr.length ? arr.map(orderRow).join('') : '<div class="l4x-muted mk-none">' + empty + '</div>'; }

        $('#mkBook').innerHTML =
            '<div class="mk-book__head"><div><h2>' + esc(b.name) + '</h2>' +
            '<div class="mk-book__best"><span class="mk-need">лучший спрос <b>' + (b.need.best != null ? k(b.need.best) + ' ₽' : '—') + '</b></span>' +
            '<span class="mk-offer">лучшее предложение <b>' + (b.offer.best != null ? k(b.offer.best) + ' ₽' : '—') + '</b></span></div></div>' + sp + '</div>' +
            '<div class="mk-cols"><span class="mk-need">Спрос · ' + b.need.total + '</span><span class="mk-offer">Предложение · ' + b.offer.total + '</span></div>' +
            (rows ? '<div class="mk-ladder">' + ladder + '</div>' : '<div class="l4x-muted mk-none">Денежных позиций по этому навыку пока нет.</div>') +
            '<div class="mk-detail" id="mkDetail" hidden></div>' +
            '<div class="mk-sub"><h3>За долю, бесплатно, на джем</h3><div class="mk-two">' +
            '<div><span class="mk-two__h mk-need">Спрос</span>' + list(b.need.other, 'нет спроса') + '</div><div><span class="mk-two__h mk-offer">Предложение</span>' + list(b.offer.other, 'нет предложений') + '</div></div></div>' +
            ((b.need.nego.length || b.offer.nego.length) ? '<div class="mk-sub"><h3>Цена по договорённости</h3><div class="mk-two">' +
            '<div><span class="mk-two__h mk-need">Спрос</span>' + list(b.need.nego, '—') + '</div><div><span class="mk-two__h mk-offer">Предложение</span>' + list(b.offer.nego, '—') + '</div></div></div>' : '') +
            '<p class="l4x-hint">Слева заказчики — сверху самый щедрый бюджет. Справа исполнители — сверху самая доступная цена. Полоса — сколько позиций на этом уровне и лучше. Подсвеченные уровни «пересекаются»: там бюджет покрывает цену.</p>';
    }

    if (root) $('#mkBook').addEventListener('click', function (e) {
        var lv = e.target.closest('.mk-lv[data-orders]');
        if (lv) {
            var side = lv.classList.contains('mk-lv--need') ? 'need' : 'offer';
            var ids = JSON.parse(lv.dataset.orders), box = $('#mkDetail');
            $$('.mk-lv.is-open').forEach(function (x) { if (x !== lv) x.classList.remove('is-open'); });
            var open = lv.classList.toggle('is-open');
            box.hidden = !open;
            box.innerHTML = open ? ids.map(function (id) { return orderRow(orderIndex[side + id]); }).join('') : '';
            if (open) box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            return;
        }
        var ord = e.target.closest('.mk-ord');
        if (ord) openOrder(orderIndex[ord.dataset.side + ord.dataset.id]);
    });

    function renderTape(t) {
        var box = $('#mkTape'); if (!box) return;
        box.innerHTML = t && t.length ? t.map(function (d) {
            return '<div class="mk-tick"><b>' + esc(d.skill) + '</b><span>' + esc(d.kind) + '</span><span class="mk-tick__p">' + esc(d.price) + '</span><span class="l4x-muted">' + esc(d.ago) + '</span></div>';
        }).join('') : '<div class="l4x-muted" style="font-size:13px">Сделок пока не было. Первая — ваша?</div>';
    }

    /* ═════════════════════════════ МОДАЛКА ПОЗИЦИИ ═════════════════════════════ */
    function openOrder(o) {
        if (!o) return;
        var isNeed = o.side === 'need';
        if (!isNeed) get('offer_view', { id: o.id });
        var sideSub = isNeed ? 'Спрос · заявка' : 'Предложение · исполнитель';   // GET: без CSRF, считается раз в сессию
        var meta = [
            ['Цена', o.price_label],
            ['Формат', isNeed ? MK.kinds[o.kind] : (o.kind === 'any' ? 'любая работа' : MK.kinds[o.kind])],
            isNeed ? ['Срок', o.days ? o.days + ' дн.' : ''] : ['Свободен', o.from ? 'с ' + o.from.split('-').reverse().slice(0, 2).join('.') : 'сейчас'],
            isNeed ? ['Условия', o.extra] : ['Часов в неделю', o.hours || ''],
            ['Навыки', (o.skills || []).join(', ')]
        ];
        var html = '<a class="l4x-studio" href="/l4t/' + encodeURIComponent(o.user.handle) + '" target="_blank">' +
            '<span class="l4x-studio__ic pix">' + (o.user.avatar ? '<img src="' + esc(o.user.avatar) + '" alt="" style="width:100%;height:100%;object-fit:cover">' : esc((o.user.name || '?').replace('@', '').charAt(0).toUpperCase())) + '</span>' +
            '<span class="l4x-studio__body"><b>' + esc(o.user.name) + '</b><span class="l4x-muted">' + (isNeed ? 'заказчик' : 'исполнитель') + (o.user.role ? ' · ' + esc(o.user.role) : '') + ' · ' + esc(o.age) + ' назад</span></span>' + icon('arrow') + '</a>' +
            '<dl class="l4x-kv">' + meta.filter(function (m) { return m[1]; }).map(function (m) { return '<dt>' + m[0] + '</dt><dd>' + esc(m[1]) + '</dd>'; }).join('') + '</dl>' +
            (o.details ? '<div class="l4x-sep"><div class="l4x-prose">' + esc(o.details) + '</div></div>' : '');

        if (o.mine) {
            U.panel.open({ title: o.title, sub: sideSub, html: html + '<div class="l4x-sep l4x-muted">Это ваша позиция. Кандидаты по ней — во вкладке «Мои позиции».</div>' });
            return;
        }
        if (!C.loggedIn) {
            U.panel.open({ title: o.title, sub: sideSub, html: html + '<div class="l4x-sep"><a class="l4x-btn l4x-btn--acc" href="/login?backUrl=/l4t/">Войдите, чтобы договориться</a></div>' });
            return;
        }
        html += '<div class="l4x-sep" id="mkDeal"><span class="l4x-muted">Загружаем ваши позиции…</span></div>';
        U.panel.open({ title: o.title, sub: sideSub, html: html });

        get('my_orders').then(function (r) {
            var mine = isNeed ? (r.offers || []) : (r.needs || []);
            var box = $('#mkDeal'); if (!box) return;
            var out = '';
            if (mine.length) {
                out += '<label class="l4x-field"><span class="l4x-field__label">' + (isNeed ? 'Предложить себя — из вашего предложения' : 'Предложить задачу — из вашей заявки') + '</span>' +
                    '<div class="l4x-edit-row"><select class="l4x-input" id="mkMine">' + mine.map(function (m) { return '<option value="' + m.id + '">' + esc(m.title) + '</option>'; }).join('') + '</select>' +
                    '<button class="l4x-btn l4x-btn--acc l4x-btn--sm" id="mkPropose">' + icon('send') + 'Предложить</button></div></label>' +
                    '<p class="l4x-hint" style="margin:0">Ваша сторона сразу скажет «да». Если вторая ответит тем же — сделка, контакты откроются обоим.</p>';
            } else {
                out += '<p class="l4x-hint" style="margin:0 0 10px">' + (isNeed ? 'Выставьте своё предложение «Я могу» — и сможете предлагать себя в один клик.' : 'Разместите заявку «Мне нужно» — и сможете предлагать задачи исполнителям.') + '</p>' +
                    '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="pos-new-go" data-side="' + (isNeed ? 'offer' : 'need') + '">' + icon('plus') + (isNeed ? 'Я могу' : 'Мне нужно') + '</button>';
            }
            if (isNeed) {
                out += '<div class="l4x-sep"><label class="l4x-field"><span class="l4x-field__label">…или обычный отклик с сообщением</span>' +
                    '<textarea class="l4x-input" id="mkMsg" rows="3" maxlength="1000" placeholder="Коротко о себе и почему интересно"></textarea></label>' +
                    '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" id="mkRespond" style="margin-top:8px">' + icon('inbox') + 'Откликнуться</button></div>';
            }
            box.innerHTML = out;
            var pb = $('#mkPropose');
            if (pb) pb.addEventListener('click', function () {
                pb.disabled = true;
                var mineId = +$('#mkMine').value;
                act('propose', isNeed ? { bid_id: o.id, offer_id: mineId } : { bid_id: mineId, offer_id: o.id }).then(function (r) {
                    pb.disabled = false;
                    if (!r.ok) { toast(r.error || 'Не получилось', 'err'); return; }
                    U.panel.close(); toast(r.deal ? 'Сделка! Контакты — в «Мои позиции»' : 'Предложение отправлено — ждём ответ');
                });
            });
            var rb = $('#mkRespond');
            if (rb) rb.addEventListener('click', function () {
                var msg = $('#mkMsg').value.trim();
                if (!msg) { toast('Напишите пару слов о себе', 'err'); return; }
                U.api('/swad/controllers/l4t/respond_bid.php', { bid_id: o.id, message: msg }).then(function (r) {
                    if (!r.success) { toast(r.message || 'Не удалось', 'err'); return; }
                    U.panel.close(); toast('Отклик отправлен');
                });
            });
        });
    }

    A['propose-to-offer'] = function (el) {
        openOrder({ side: 'offer', id: +el.dataset.id, title: el.dataset.title, user: { name: el.dataset.owner || '', handle: C.handle, avatar: '' },
                    price_label: '', kind: 'any', skills: [], age: '', mine: false });
    };

    /* ═════════════════════════════ МОИ ПОЗИЦИИ ═════════════════════════════ */
    $$('#posPanes button').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('#posPanes button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            $$('.mk-pane').forEach(function (p) { p.hidden = p.dataset.pane !== b.dataset.pane; });
            try { var u = new URL(location.href); u.searchParams.set('pane', b.dataset.pane); history.replaceState(null, '', u); } catch (e) {}
        });
    });

    /* Форма спроса: оплата управляет полями бюджета, формат — старым полем goal. */
    var bf = $('#bidForm');
    if (bf && $('#f_kind')) {
        var goalText = { task: 'Разовая работа', team: 'Найти человека в команду', jam: 'Найти человека в команду', consult: 'Консультация' };
        function syncNeed() {
            var pay = ($('input[name="pay_type"]:checked', bf) || {}).value;
            $('#f_budget').hidden = pay !== 'money';
            $('#f_goal').value = goalText[$('#f_kind').value] || 'Разовая работа';
        }
        bf.addEventListener('change', syncNeed); syncNeed();
        var origEdit = A['edit-bid'];
        A['edit-bid'] = function (el) {
            origEdit(el);
            var d = JSON.parse(el.dataset.bid);
            $('#f_kind').value = d.kind || 'task';
            var r = $('input[name="pay_type"][value="' + (d.pay_type || 'money') + '"]', bf); if (r) r.checked = true;
            $('#f_bmin').value = d.budget_min || ''; $('#f_bmax').value = d.budget_max || ''; $('#f_days').value = d.duration_days || '';
            syncNeed();
            $$('#posPanes button[data-pane="need"]').forEach(function (b) { b.click(); });
        };
    }
    A['need-stage'] = function (el) {
        act('need_stage', { id: el.dataset.id, stage: el.dataset.stage }).then(function (r) {
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            toast(el.dataset.stage === 'active' ? 'Заявка снова на рынке' : 'Заявка снята с рынка'); reload('&pane=need');
        });
    };

    /* Форма предложения. */
    var of = $('#offerForm');
    if (of) {
        function syncOffer() { $('[data-price]', of).hidden = (($('input[name="pay_type"]:checked', of) || {}).value !== 'money'); }
        of.addEventListener('change', function (e) {
            syncOffer();
            if (e.target.name === 'skills' && $$('input[name="skills"]:checked', of).length > 8) { e.target.checked = false; toast('Не больше 8 навыков', 'err'); }
        });
        // новая форма — подставляем навыки из профиля: чаще всего человек продаёт именно их
        $$('input[name="skills"][data-mine]', of).forEach(function (c) { c.checked = true; });
        syncOffer();
        of.addEventListener('submit', function (e) {
            e.preventDefault();
            var d = { id: of.id.value, title: of.title.value, kind: of.kind.value, available_from: of.available_from.value,
                      pay_type: ($('input[name="pay_type"]:checked', of) || {}).value, price_min: of.price_min.value, price_max: of.price_max.value,
                      hours_week: of.hours_week.value, details: of.details.value,
                      lifetime_days: ($('input[name="lifetime_days"]:checked', of) || {}).value,
                      skills: $$('input[name="skills"]:checked', of).map(function (c) { return c.value; }) };
            var btn = $('button[type="submit"]', of); btn.disabled = true;
            act('offer_save', d).then(function (r) {
                btn.disabled = false;
                if (!r.ok) { toast(r.error || 'Не сохранилось', 'err'); return; }
                toast('Предложение на рынке — подбираем заявки…'); reload('&pos=offer-' + (r.id || d.id));
            });
        });
        $('#offerCancel').addEventListener('click', function () {
            of.reset(); of.id.value = ''; $('#offerCancel').hidden = true;
            $('#offerFormTitle').innerHTML = icon('plus') + 'Новое предложение'; $('#offerSubmitText').textContent = 'Выставить'; syncOffer();
        });
        A['offer-edit'] = function (el) {
            var o = JSON.parse(el.dataset.offer);
            of.id.value = o.id; of.title.value = o.title; of.kind.value = o.kind; of.available_from.value = o.from || '';
            var r = $('input[name="pay_type"][value="' + o.pay + '"]', of); if (r) r.checked = true;
            of.price_min.value = o.lo || ''; of.price_max.value = o.hi || ''; of.hours_week.value = o.hours || ''; of.details.value = o.details || '';
            $$('input[name="skills"]', of).forEach(function (c) { c.checked = o.skills.indexOf(c.value) > -1; });
            $('#offerCancel').hidden = false; $('#offerFormTitle').innerHTML = icon('edit') + 'Предложение #' + o.id; $('#offerSubmitText').textContent = 'Сохранить';
            syncOffer(); of.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };
    }
    A['offer-stage'] = function (el) {
        if (el.dataset.stage === 'closed' && !confirm('Снять предложение с рынка совсем?')) return;
        act('offer_stage', { id: el.dataset.id, stage: el.dataset.stage }).then(function (r) {
            if (!r.ok) { toast(r.error || 'Ошибка', 'err'); return; }
            toast({ active: 'Снова на рынке', paused: 'На паузе', closed: 'Снято' }[el.dataset.stage]); reload('&pane=offer');
        });
    };

    /* Совпадения. */
    function answer(el, yes) {
        el.disabled = true;
        act('match_answer', { id: el.dataset.id, yes: yes ? 1 : 0 }).then(function (r) {
            if (!r.ok) { el.disabled = false; toast(r.error || 'Ошибка', 'err'); return; }
            toast(r.deal ? 'Сделка! Контакты открыты' : (yes ? 'Отмечено — ждём другую сторону' : 'Скрыто'));
            reload('&pane=matches');
        });
    }
    A['match-yes'] = function (el) { answer(el, true); };
    A['match-no'] = function (el) { answer(el, false); };
})();
