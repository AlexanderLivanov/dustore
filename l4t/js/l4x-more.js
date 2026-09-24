/**
 * l4t/js/l4x-more.js — вторая волна L4T: навыки, опыт, титры, рекомендации,
 * QR (визитка / резюме / пропуск), знакомства, ссылки на резюме, очередь джема.
 * Работает поверх l4x.js через window.L4XUI — та же модалка и делегирование.
 */
(function () {
    'use strict';
    var U = window.L4XUI;
    if (!U) return;
    var C = U.C, M = U.M, $ = U.$, $$ = U.$$, esc = U.esc, icon = U.icon, toast = U.toast, A = U.actions;

    function act(op, data) { return U.api('/l4t/api/action.php', Object.assign({ op: op }, data || {})); }
    function done(r, okText, reload) {
        if (!r.ok) { toast(r.error || 'Не сохранилось', 'err'); M.busy(false); return false; }
        if (okText) toast(okText);
        if (reload) setTimeout(function () { location.reload(); }, 450);
        return true;
    }

    /* ═════════════════════════════ НАВЫКИ ═════════════════════════════ */
    A.skills = function () {
        var sel = Object.assign({}, C.uSkills || {});
        var byGrp = {};
        Object.keys(C.skills).forEach(function (slug) { var s = C.skills[slug]; (byGrp[s.grp] = byGrp[s.grp] || []).push(slug); });
        function chip(slug) {
            var l = sel[slug] || 0;
            return '<button type="button" class="l4x-skpick l' + l + '" data-s="' + slug + '">' + esc(C.skills[slug].name) +
                '<i class="l4x-lvl l' + l + '"><b></b><b></b><b></b></i></button>';
        }
        M.open('Навыки',
            '<p class="l4x-hint" style="margin:0">Клик — добавить, повторные клики меняют уровень: учусь → уверенно → эксперт → убрать. До 15 навыков.</p>' +
            Object.keys(C.groups).map(function (g) {
                return '<div class="l4x-pick__grp"><span>' + esc(C.groups[g]) + '</span>' + (byGrp[g] || []).map(chip).join('') + '</div>';
            }).join(''),
            function () {
                M.busy(true);
                act('skills_save', { skills: sel }).then(function (r) { if (done(r, 'Навыки сохранены', true)) M.close(); });
            }, { noFocus: true });
        $('#modalBody').addEventListener('click', function (e) {
            var b = e.target.closest('.l4x-skpick'); if (!b) return;
            var s = b.dataset.s, l = ((sel[s] || 0) + 1) % 4;
            if (l && !sel[s] && Object.keys(sel).length >= 15) { toast('Не больше 15 навыков', 'err'); return; }
            if (l) sel[s] = l; else delete sel[s];
            b.outerHTML = chip(s);
        });
    };

    /* ═════════════════════════════ ОПЫТ ═════════════════════════════ */
    function expForm(e) {
        e = e || {};
        var st = C.studios || [];
        return '<label class="l4x-field"><span class="l4x-field__label">Студия на Dustore — тогда опыт подтвердит её владелец</span>' +
            '<select class="l4x-input" id="xStudio"><option value="">— внешняя компания —</option>' +
            st.map(function (s) { return '<option value="' + s.id + '"' + (String(e.studio_id) === String(s.id) ? ' selected' : '') + '>' + esc(s.name) + '</option>'; }).join('') +
            '<option value="other"' + (e.studio_id && !st.some(function (s) { return String(s.id) === String(e.studio_id); }) ? ' selected' : '') + '>Другая студия Dustore (по ID)…</option></select></label>' +
            '<input class="l4x-input" id="xStudioId" type="number" placeholder="ID студии" hidden value="' + (e.studio_id || '') + '">' +
            '<label class="l4x-field" id="xOrgWrap"><span class="l4x-field__label">Компания *</span><input class="l4x-input" id="xOrg" maxlength="120" value="' + esc(e.org_name) + '"></label>' +
            '<label class="l4x-field"><span class="l4x-field__label">Должность *</span><input class="l4x-input" id="xTitle" maxlength="120" placeholder="Технический художник" value="' + esc(e.title) + '"></label>' +
            '<div class="l4x-edit-row"><label class="l4x-field" style="flex:1"><span class="l4x-field__label">С</span><input class="l4x-input" id="xStart" type="month" value="' + esc(e.start_ym || '') + '"></label>' +
            '<label class="l4x-field" style="flex:1"><span class="l4x-field__label">По</span><input class="l4x-input" id="xEnd" type="month" value="' + esc(e.end_ym || '') + '"' + (e.id && !e.end_ym ? ' disabled' : '') + '></label></div>' +
            '<div class="l4x-checks"><label><input type="checkbox" id="xNow"' + (e.id && !e.end_ym ? ' checked' : '') + '><span>Работаю сейчас</span></label></div>' +
            '<label class="l4x-field"><span class="l4x-field__label">Что делали</span><textarea class="l4x-input" id="xDesc" maxlength="1000" rows="4">' + esc(e.description || '') + '</textarea></label>' +
            (e.id ? '<button class="l4x-link l4x-danger" id="xDel">' + icon('close') + 'Удалить запись</button>' : '');
    }
    function openExp(e) {
        M.open(e && e.id ? 'Опыт' : 'Новый опыт', expForm(e), function () {
            var sv = $('#xStudio').value;
            var payload = {
                id: e && e.id || 0,
                studio_id: sv === 'other' ? $('#xStudioId').value : sv,
                org_name: $('#xOrg').value, title: $('#xTitle').value,
                start_ym: $('#xStart').value, end_ym: $('#xEnd').value, current: $('#xNow').checked ? 1 : 0,
                description: $('#xDesc').value
            };
            M.busy(true);
            act('exp_save', payload).then(function (r) {
                if (done(r, r.status === 'pending' ? 'Сохранено — ждёт подтверждения владельца студии' : 'Сохранено', true)) M.close();
            });
        });
        function sync() {
            var sv = $('#xStudio').value;
            $('#xStudioId').hidden = sv !== 'other';
            $('#xOrgWrap').hidden = sv !== '';
        }
        $('#xStudio').addEventListener('change', sync); sync();
        $('#xNow').addEventListener('change', function () { $('#xEnd').disabled = this.checked; });
        var del = $('#xDel');
        if (del) del.addEventListener('click', function () {
            if (!confirm('Удалить запись об опыте?')) return;
            act('exp_delete', { id: e.id }).then(function (r) { if (done(r, 'Удалено', true)) M.close(); });
        });
    }
    A['exp-new'] = function (el) {
        openExp(el && el.dataset && el.dataset.studio ? { studio_id: el.dataset.studio, org_name: el.dataset.name } : {});
    };
    A['exp-edit'] = function (el) { openExp(JSON.parse(el.dataset.exp)); };
    A['exp-verify'] = function (el) {
        act('exp_verify', { id: el.dataset.id, approve: el.dataset.ok === '1' ? 1 : 0 }).then(function (r) {
            if (done(r, el.dataset.ok === '1' ? 'Подтверждено' : 'Отклонено')) {
                var row = el.closest('.l4x-verify'); if (row) row.remove();
            }
        });
    };

    /* ═════════════════════════════ ТИТРЫ ═════════════════════════════ */
    A['credit-new'] = function () {
        var gameId = 0;
        M.open('Титр',
            '<label class="l4x-field"><span class="l4x-field__label">Игра на Dustore или любой проект *</span>' +
            '<input class="l4x-input" id="cT" maxlength="160" placeholder="Начните вводить название" autocomplete="off"></label><div class="l4x-ac" id="cAc"></div>' +
            '<div class="l4x-edit-row"><label class="l4x-field" style="flex:1"><span class="l4x-field__label">Ваша роль</span><input class="l4x-input" id="cR" maxlength="80" placeholder="Художник окружения"></label>' +
            '<label class="l4x-field" style="width:110px"><span class="l4x-field__label">Год</span><input class="l4x-input" id="cY" type="number" min="1990" max="2100"></label></div>' +
            '<p class="l4x-hint" style="margin:0">Ручные титры помечаются как «со слов». Титры из данных Dustore — с галочкой.</p>',
            function () {
                M.busy(true);
                act('credit_add', { title: $('#cT').value, game_id: gameId, role: $('#cR').value, year: $('#cY').value })
                    .then(function (r) { if (done(r, 'Добавлено', true)) M.close(); });
            });
        var t;
        $('#cT').addEventListener('input', function () {
            gameId = 0; clearTimeout(t);
            var q = this.value.trim();
            t = setTimeout(function () {
                if (q.length < 2) { $('#cAc').innerHTML = ''; return; }
                fetch('/l4t/api/action.php?op=games_search&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        $('#cAc').innerHTML = (r.items || []).map(function (g) { return '<button type="button" data-g="' + g.id + '">' + icon('gamepad') + esc(g.name) + '</button>'; }).join('');
                    });
            }, 200);
        });
        $('#cAc').addEventListener('click', function (e) {
            var b = e.target.closest('[data-g]'); if (!b) return;
            gameId = +b.dataset.g; $('#cT').value = b.textContent; this.innerHTML = '';
        });
    };
    A['credit-hide'] = function (el) {
        act('credit_hide', { id: el.dataset.id, hide: +el.dataset.hide }).then(function (r) { done(r, null, true); });
    };

    /* ═════════════════════════════ РЕКОМЕНДАЦИИ ═════════════════════════════ */
    A['rec-write'] = function (el) {
        var picked = [];
        M.open('Рекомендация: ' + el.dataset.name,
            '<p class="l4x-hint" style="margin:0">Конкретика ценнее похвалы: что человек сделал, как с ним работалось, в чём он силён.</p>' +
            '<textarea class="l4x-input" id="rT" rows="6" maxlength="1500" placeholder="Собрали прототип за 48 часов — вся механика стрельбы на нём…"></textarea>' +
            '<div class="l4x-count" id="rN">0 / 1500</div>' +
            '<div class="l4x-field"><span class="l4x-field__label">Главное, в чём силён (до двух)</span><div class="l4x-pick__grp" id="rS">' +
            Object.keys(C.skills).map(function (s) { return '<button type="button" class="l4x-skpick" data-s="' + s + '">' + esc(C.skills[s].name) + '</button>'; }).join('') + '</div></div>' +
            '<div class="l4x-checks"><label><input type="checkbox" id="rA" checked><span>Поработал(а) бы с ним снова</span></label>' +
            '<span class="l4x-hint">Этот ответ видит только статистика: наружу выходит доля «да», и только когда ответов три и больше.</span></div>',
            function () {
                M.busy(true);
                act('rec_save', { target_id: el.dataset.id, text: $('#rT').value, skills: picked, again: $('#rA').checked ? 1 : 0 })
                    .then(function (r) { if (done(r, 'Рекомендация отправлена', true)) M.close(); });
            }, { saveText: 'Отправить' });
        $('#rT').addEventListener('input', function () { $('#rN').textContent = this.value.length + ' / 1500'; });
        $('#rS').addEventListener('click', function (e) {
            var b = e.target.closest('.l4x-skpick'); if (!b) return;
            var i = picked.indexOf(b.dataset.s);
            if (i > -1) picked.splice(i, 1); else if (picked.length < 2) picked.push(b.dataset.s); else { toast('Максимум два', 'err'); return; }
            b.classList.toggle('l2', i === -1);
        });
    };
    A['rec-hide'] = function (el) {
        act('rec_hide', { id: el.dataset.id, hide: +el.dataset.hide }).then(function (r) { done(r, null, true); });
    };

    /* ═════════════════════════════ КАК СО МНОЙ РАБОТАТЬ ═════════════════════════════ */
    A.work = function () {
        var p = C.profile || {};
        var tzs = []; for (var h = -12; h <= 14; h++) tzs.push(h * 60);
        [210, 270, 330, 345, 390, 570].forEach(function (m) { tzs.push(m); }); tzs.sort(function (a, b) { return a - b; });
        function tzl(m) { var s = m < 0 ? '−' : '+', a = Math.abs(m); return 'UTC' + s + Math.floor(a / 60) + (a % 60 ? ':' + ('0' + a % 60).slice(-2) : ''); }
        var cur = p.tz == null ? -new Date().getTimezoneOffset() : p.tz;
        M.open('Как со мной работать',
            '<div class="l4x-field"><span class="l4x-field__label">Формат</span><div class="l4x-checks" id="wM">' +
            Object.keys(C.workModes).map(function (k) { return '<label><input type="checkbox" value="' + k + '"' + ((p.work_modes || []).indexOf(k) > -1 ? ' checked' : '') + '><span>' + esc(C.workModes[k]) + '</span></label>'; }).join('') + '</div></div>' +
            '<div class="l4x-edit-row"><label class="l4x-field" style="flex:1"><span class="l4x-field__label">Ставка</span><input class="l4x-input" id="wR" maxlength="60" placeholder="от 1500 ₽/час, обсуждается" value="' + esc(p.rate) + '"></label>' +
            '<label class="l4x-field" style="width:140px"><span class="l4x-field__label">Часовой пояс</span><select class="l4x-input" id="wT">' +
            tzs.map(function (m) { return '<option value="' + m + '"' + (m === cur ? ' selected' : '') + '>' + tzl(m) + '</option>'; }).join('') + '</select></label></div>' +
            '<label class="l4x-field"><span class="l4x-field__label">Коротко о том, как со мной работать</span><textarea class="l4x-input" id="wX" rows="6" maxlength="3000" placeholder="Отвечаю вечером по МСК. Люблю чёткие ТЗ и созвоны раз в неделю. Не беру проекты без дедлайна.">' + esc(p.manual) + '</textarea></label>',
            function () {
                M.busy(true);
                act('profile_extra', {
                    work_modes: $$('#wM input:checked').map(function (c) { return c.value; }),
                    rate: $('#wR').value, tz: $('#wT').value, manual: $('#wX').value
                }).then(function (r) { if (done(r, 'Сохранено', true)) M.close(); });
            });
    };
    A['avail-renew'] = function (el) {
        act('avail_renew').then(function (r) { if (done(r, 'Продлено на 30 дней')) el.closest('.l4x-card').remove(); });
    };

    /* ═════════════════════════════ QR ═════════════════════════════ */
    var qrLib = null;
    function loadQr() {
        if (window.qrcode) return Promise.resolve(window.qrcode);
        if (qrLib) return qrLib;
        qrLib = new Promise(function (ok, fail) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
            s.onload = function () { ok(window.qrcode); }; s.onerror = fail;
            document.head.appendChild(s);
        });
        return qrLib;
    }
    /* QR рисуем квадратами в SVG — пиксельная эстетика платформы бесплатно.
       Тёмные модули на светлом: сканеры плохо читают инверсию. */
    function qrSvg(text, px) {
        var q = window.qrcode(0, 'M'); q.addData(text); q.make();
        var n = q.getModuleCount(), b = 3, size = n + b * 2, d = '';
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += 'M' + (c + b) + ' ' + (r + b) + 'h1v1h-1z';
        return '<svg class="l4x-qr" viewBox="0 0 ' + size + ' ' + size + '" width="' + (px || 240) + '" height="' + (px || 240) + '" shape-rendering="crispEdges">' +
            '<rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#14041d"/></svg>';
    }
    function downloadPng(svgEl, name) {
        var img = new Image(), xml = new XMLSerializer().serializeToString(svgEl);
        img.onload = function () {
            var c = document.createElement('canvas'); c.width = c.height = 1024;
            var g = c.getContext('2d'); g.imageSmoothingEnabled = false; g.drawImage(img, 0, 0, 1024, 1024);
            var a = document.createElement('a'); a.download = name + '.png'; a.href = c.toDataURL('image/png'); a.click();
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    }

    var passTimer = null;
    function stopPass() { clearTimeout(passTimer); passTimer = null; }

    A.qr = function (el) {
        var mode = el.dataset.mode || 'url';
        var base = C.host + '/l4t/' + encodeURIComponent(C.handle);
        var tabs = mode === 'card' ? [
            ['card', 'Визитка', base + '?via=qr', 'Отсканируют — откроется ваш профиль с кнопкой «Добавить в знакомства».'],
            ['cv', 'Резюме', base + '/cv', 'Одна страница с навыками, опытом и титрами. Для печати и для HR.'],
            ['pass', 'Пропуск', null, 'Для входа на мероприятия. Код меняется каждые 30 секунд — скриншот не сработает.']
        ] : [['url', el.dataset.label || 'QR', el.dataset.url, '']];

        M.open(mode === 'card' ? 'Мой QR' : (el.dataset.label || 'QR'),
            (tabs.length > 1 ? '<div class="l4x-seg" id="qrTabs">' + tabs.map(function (t, i) { return '<button data-i="' + i + '" class="' + (i ? '' : 'is-on') + '">' + t[1] + '</button>'; }).join('') + '</div>' : '') +
            '<div class="l4x-qrbox" id="qrBox"><span class="l4x-muted">Загружаем…</span></div>' +
            '<div class="l4x-qrttl" id="qrTtl" hidden><i></i></div>' +
            '<p class="l4x-hint" id="qrHint" style="margin:0;text-align:center"></p>' +
            '<div class="l4x-row" style="justify-content:center" id="qrActs">' +
            '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" id="qrPng">' + icon('image') + 'PNG</button>' +
            '<button class="l4x-btn l4x-btn--ghost l4x-btn--sm" id="qrCopy">' + icon('link') + 'Ссылка</button></div>',
            null, { noFocus: true });

        var cur = 0;
        function show(i) {
            stopPass(); cur = i;
            var t = tabs[i];
            $('#qrHint').textContent = t[3];
            $('#qrActs').hidden = t[0] === 'pass';
            $('#qrTtl').hidden = t[0] !== 'pass';
            if (t[0] !== 'pass') { $('#qrBox').innerHTML = qrSvg(t[2], 260); return; }
            (function tick() {
                act('pass').then(function (r) {
                    if (!r.ok || cur !== i || M.el.hidden) return;
                    $('#qrBox').innerHTML = qrSvg(r.token, 260);
                    var bar = $('#qrTtl i');
                    bar.style.transition = 'none'; bar.style.width = (r.ttl / 30 * 100) + '%';
                    requestAnimationFrame(function () { bar.style.transition = 'width ' + r.ttl + 's linear'; bar.style.width = '0%'; });
                    passTimer = setTimeout(tick, r.ttl * 1000 + 150);
                });
            })();
        }
        loadQr().then(function () { show(0); }).catch(function () { $('#qrBox').innerHTML = '<span class="l4x-muted">Не удалось загрузить генератор QR</span>'; });

        var tb = $('#qrTabs');
        if (tb) tb.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return;
            $$('#qrTabs button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            show(+b.dataset.i);
        });
        $('#qrPng').addEventListener('click', function () { var s = $('#qrBox svg'); if (s) downloadPng(s, 'dustore-' + (C.handle || 'qr') + '-' + tabs[cur][0]); });
        $('#qrCopy').addEventListener('click', function () { copy(tabs[cur][2]); });
    };
    // модалку закрыли — пропуск больше не обновляем
    new MutationObserver(function () { if (M.el.hidden) stopPass(); }).observe(M.el, { attributes: true, attributeFilter: ['hidden'] });

    function copy(url) {
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
            .then(function () { toast('Ссылка скопирована'); })
            .catch(function () { prompt('Ссылка', url); });
    }
    A.copy = function (el) { copy(el.dataset.url); };

    /* ═════════════════════════════ СВЯЗИ ═════════════════════════════ */
    A['contact-add'] = function (el) {
        el.disabled = true;
        act('contact_add', { user_id: el.dataset.id }).then(function (r) {
            if (!done(r)) { el.disabled = false; return; }
            toast(r.event ? 'Знакомство сохранено: «' + r.event.title + '»' : 'Добавлено в знакомства');
            el.outerHTML = '<a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/l4t/?tab=network">В знакомствах</a>';
        });
    };
    U.root.addEventListener('change', function (e) {
        var n = e.target.closest('[data-note]'); if (!n) return;
        act('contact_note', { user_id: n.dataset.note, note: n.value }).then(function (r) { if (r.ok) toast('Заметка сохранена'); });
    });
    var lf = $('#linkForm');
    if (lf) lf.addEventListener('submit', function (e) {
        e.preventDefault();
        act('link_create', { label: lf.label.value }).then(function (r) {
            if (!done(r)) return;
            var url = C.host + '/l4t/cv/' + r.token;
            copy(url);
            setTimeout(function () { location.reload(); }, 700);
        });
    });
    A['link-revoke'] = function (el) {
        if (!confirm('Отозвать ссылку? По ней больше нельзя будет открыть резюме.')) return;
        act('link_revoke', { id: el.dataset.id }).then(function (r) { done(r, 'Отозвана', true); });
    };
    A['search-delete'] = function (el) {
        act('search_delete', { id: el.dataset.id }).then(function (r) { if (done(r)) el.closest('.l4x-my').remove(); });
    };

    /* ═════════════════════════════ ОЧЕРЕДЬ ДЖЕМА ═════════════════════════════ */
    A['queue-join'] = function (el) {
        var mine = C.uSkills || {}, best = null, w = 0;
        Object.keys(mine).forEach(function (s) { var k = C.skills[s]; if (k && k.kind === 'role' && mine[s] > w) { w = mine[s]; best = k.grp; } });
        M.open('Очередь: «' + el.dataset.title + '»',
            '<div class="l4x-field"><span class="l4x-field__label">Ваша роль в команде</span><div class="l4x-radios" id="qG">' +
            Object.keys(C.groups).map(function (g) { return '<label><input type="radio" name="qG" value="' + g + '"' + (g === (best || 'code') ? ' checked' : '') + '><span>' + esc(C.groups[g]) + '</span></label>'; }).join('') + '</div></div>' +
            '<label class="l4x-field"><span class="l4x-field__label">Пара слов команде (необязательно)</span><input class="l4x-input" id="qN" maxlength="200" placeholder="Godot, 2D, могу вечерами"></label>' +
            '<p class="l4x-hint" style="margin:0">Организатор соберёт команды так, чтобы роли не повторялись, а часовые пояса были рядом. Вам придёт уведомление.</p>',
            function () {
                M.busy(true);
                act('queue_join', { sprint_id: el.dataset.sprint, grp: ($('input[name="qG"]:checked') || {}).value, tz: -new Date().getTimezoneOffset(), note: $('#qN').value })
                    .then(function (r) { if (done(r, 'Вы в очереди', true)) M.close(); });
            }, { saveText: 'Встать в очередь' });
    };
    A['queue-leave'] = function (el) {
        act('queue_leave', { sprint_id: el.dataset.sprint }).then(function (r) { done(r, 'Вы вышли из очереди', true); });
    };
})();
