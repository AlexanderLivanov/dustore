/**
 * l4t/js/l4x.js — поведение страницы L4T.
 *
 * Один делегированный обработчик кликов по [data-act] вместо десятков
 * addEventListener на конкретные кнопки: разметка может перерисовываться
 * (лента, проекты), а обработчик не теряется. Конфиг — window.L4X из PHP.
 */
(function () {
    'use strict';

    var C = window.L4X || {};
    var root = document.getElementById('l4x');
    if (!root) return;

    var $ = function (s, el) { return (el || root).querySelector(s); };
    var $$ = function (s, el) { return Array.prototype.slice.call((el || root).querySelectorAll(s)); };

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function icon(n) { return '<svg class="l4x-ic" aria-hidden="true"><use href="#l4i-' + n + '"/></svg>'; }

    function api(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': C.csrf },
            body: JSON.stringify(Object.assign({}, payload, { csrf: C.csrf }))
        }).then(function (r) { return r.json(); })
          .catch(function () { return { success: false, msg: 'network' }; });
    }
    function save(type, data) { return api('/swad/controllers/l4t/l4t_update.php', { type: type, data: data }); }

    /* ── тост ─────────────────────────────────────────────────────────── */
    var toastEl = document.getElementById('l4xToast'), toastT;
    function toast(text, kind) {
        if (!toastEl) return;
        toastEl.textContent = text;
        toastEl.className = 'l4x-toast is-' + (kind || 'ok');
        toastEl.hidden = false;
        clearTimeout(toastT);
        toastT = setTimeout(function () { toastEl.hidden = true; }, 2800);
    }
    if (C.flash) toast(C.flash[1], C.flash[0]);

    /* ── модалка ──────────────────────────────────────────────────────── */
    var M = {
        el: document.getElementById('l4xModal'),
        onSave: null,
        open: function (title, html, onSave, opt) {
            opt = opt || {};
            $('#modalTitle').textContent = title;
            $('#modalBody').innerHTML = html;
            var sv = $('#modalSave');
            sv.hidden = !onSave;
            sv.disabled = false;
            sv.textContent = opt.saveText || 'Сохранить';
            $('#modalCancel').textContent = onSave ? 'Отмена' : 'Закрыть';
            this.onSave = onSave || null;
            this.el.hidden = false;
            var f = $('#modalBody input, #modalBody textarea');
            if (f && !opt.noFocus) setTimeout(function () { f.focus(); }, 30);
        },
        close: function () { this.el.hidden = true; $('#modalBody').innerHTML = ''; this.onSave = null; },
        busy: function (on, text) { var sv = $('#modalSave'); sv.disabled = on; if (text) sv.textContent = text; }
    };
    $('#modalClose').addEventListener('click', function () { M.close(); });
    $('#modalCancel').addEventListener('click', function () { M.close(); });
    $('#modalSave').addEventListener('click', function () { if (M.onSave) M.onSave(); });
    M.el.addEventListener('click', function (e) { if (e.target === M.el) M.close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!M.el.hidden) M.close(); else closeDrawer();
    });

    /* ── вкладки ──────────────────────────────────────────────────────── */
    function showTab(name) {
        var view = $('[data-view="' + name + '"]');
        if (!view) return;
        $$('.l4x-view').forEach(function (v) { v.classList.toggle('is-on', v === view); });
        $$('.l4x-tab').forEach(function (t) { t.classList.toggle('is-on', t.dataset.tab === name); });
        try {
            var u = new URL(location.href);
            u.searchParams.set('tab', name);
            ['status', 'action', 'source'].forEach(function (k) { u.searchParams.delete(k); });
            history.replaceState(null, '', u);
        } catch (e) {}
    }
    $$('.l4x-tab').forEach(function (t) { t.addEventListener('click', function () { showTab(t.dataset.tab); }); });
    // статус после редиректа upsert_bid больше не нужен в адресе
    if (C.flash) showTab(C.tab);

    /* ── делегирование действий ───────────────────────────────────────── */
    var actions = {};
    root.addEventListener('click', function (e) {
        var el = e.target.closest('[data-act]');
        if (el && actions[el.dataset.act]) { e.preventDefault(); actions[el.dataset.act](el, e); return; }
        var bid = e.target.closest('.l4x-bid');
        if (bid) { openBid(bid); return; }
        var resp = e.target.closest('[data-resp]');
        if (resp) { openResp(resp); return; }
        var todo = e.target.closest('[data-todo]');
        if (todo) openTodo(todo.dataset.todo);
    });
    root.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var bid = e.target.closest('.l4x-bid');
        if (bid) openBid(bid);
        var act = e.target.closest('[data-act][role="button"]');
        if (act && actions[act.dataset.act]) actions[act.dataset.act](act, e);
    });

    actions.share = function (el) {
        var url = el.dataset.url;
        if (navigator.share) { navigator.share({ url: url }).catch(function () {}); return; }
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
            .then(function () { toast('Ссылка скопирована'); })
            .catch(function () { prompt('Ссылка на профиль', url); });
    };

    /* ═══════════════════════ НАСТРОЙКА ПРОФИЛЯ ═══════════════════════ */
    var drawer = document.getElementById('l4xDrawer');
    var prof = Object.assign({}, C.profile || {});
    var draft = null;

    function applyPreview(p) {
        root.style.setProperty('--acc', p.accent || '#c32178');

        var hl = $('#heroHeadline');
        if (hl) hl.innerHTML = p.headline ? esc(p.headline) : '<span class="l4x-muted">Одной строкой — кто вы и что делаете</span>';

        var st = $('#heroStatus');
        if (st) {
            var empty = !p.status_text && !p.status_emoji;
            st.classList.toggle('is-empty', empty);
            $('.l4x-status__emoji', st).textContent = p.status_emoji || '';
            $('.l4x-status__text', st).textContent = p.status_text || (empty ? 'Поставить статус' : '');
        }

        var av = $('#heroAvail');
        if (av) {
            var a = C.availability[p.availability];
            av.className = 'l4x-avail ' + (a ? 'is-' + a.cls : 'is-empty');
            $('span', av).textContent = a ? a.label : '';
        }

        var bn = $('#heroBanner');
        if (bn) bn.style.backgroundImage = p.banner_url ? 'url("' + p.banner_url.replace(/"/g, '') + '")' : '';

        var loc = $('#heroLoc');
        if (loc && p.location) loc.lastChild.textContent = p.location;
    }

    function openDrawer() {
        if (!drawer) return;
        draft = JSON.parse(JSON.stringify(prof));
        $('#dHeadline', drawer).value = draft.headline || '';
        $('#dEmoji', drawer).value = draft.status_emoji || '';
        $('#dStatus', drawer).value = draft.status_text || '';
        $('#dLocation', drawer).value = draft.location || '';
        $$('input[name="dAvail"]', drawer).forEach(function (r) { r.checked = r.value === (draft.availability || ''); });
        $$('#dPins input', drawer).forEach(function (c) { c.checked = (draft.pinned || []).indexOf(c.value) > -1; });
        $$('#dHidden input', drawer).forEach(function (c) { c.checked = (draft.hidden || []).indexOf(c.value) > -1; });
        markAccent(draft.accent);
        $('#dState', drawer).textContent = '';
        drawer.hidden = false;
    }
    function closeDrawer() {
        if (!drawer || drawer.hidden) return;
        drawer.hidden = true;
        applyPreview(prof);          // не сохранили — откатываем живой предпросмотр
    }
    function markAccent(c) {
        $$('#dAccent button', drawer).forEach(function (b) { b.classList.toggle('is-on', b.dataset.c === c); });
        var cc = $('#dAccentCustom', drawer); if (cc && c) cc.value = c;
    }
    function readDrawer() {
        draft.headline = $('#dHeadline', drawer).value.trim();
        draft.status_emoji = $('#dEmoji', drawer).value.trim();
        draft.status_text = $('#dStatus', drawer).value.trim();
        draft.location = $('#dLocation', drawer).value.trim();
        var r = $('input[name="dAvail"]:checked', drawer);
        draft.availability = r ? r.value : '';
        draft.pinned = $$('#dPins input:checked', drawer).map(function (c) { return c.value; });
        draft.hidden = $$('#dHidden input:checked', drawer).map(function (c) { return c.value; });
        applyPreview(draft);
    }

    if (drawer) {
        drawer.addEventListener('click', function (e) {
            if (e.target === drawer) closeDrawer();
            var sw = e.target.closest('#dAccent button');
            if (sw) { draft.accent = sw.dataset.c; markAccent(draft.accent); applyPreview(draft); }
            var pr = e.target.closest('#dPresets button');
            if (pr) { $('#dEmoji', drawer).value = pr.dataset.e; $('#dStatus', drawer).value = pr.dataset.t; readDrawer(); }
        });
        drawer.addEventListener('input', function (e) {
            if (e.target.id === 'dAccentCustom') { draft.accent = e.target.value.toLowerCase(); markAccent(draft.accent); applyPreview(draft); return; }
            readDrawer();
        });
        drawer.addEventListener('change', function (e) {
            if (e.target.closest('#dPins') && $$('#dPins input:checked', drawer).length > 3) {
                e.target.checked = false; toast('Закрепить можно до трёх', 'err');
            }
            readDrawer();
        });
    }

    function profilePayload(p) {
        return {
            headline: p.headline || '', status_emoji: p.status_emoji || '', status_text: p.status_text || '',
            availability: p.availability || '', location: p.location || '', banner_url: p.banner_url || '',
            accent: p.accent || '', pinned_badges: p.pinned || [], hidden_blocks: p.hidden || []
        };
    }

    actions.customize = function () { openDrawer(); };
    actions['drawer-close'] = function () { closeDrawer(); };
    actions['drawer-save'] = function (btn) {
        readDrawer();
        btn.disabled = true;
        var role = $('#dRole', drawer).value.trim();
        var jobs = [save('profile', profilePayload(draft))];
        if (role !== (C.role || '')) jobs.push(save('role', role));
        Promise.all(jobs).then(function (res) {
            btn.disabled = false;
            if (res.every(function (r) { return r.success; })) {
                var pinsChanged = JSON.stringify(prof.pinned || []) !== JSON.stringify(draft.pinned || []);
                prof = JSON.parse(JSON.stringify(draft));
                C.role = role;
                drawer.hidden = true;
                toast('Профиль сохранён');
                // закреплённые бейджи и роль рисует сервер — проще перезагрузить
                if (pinsChanged || jobs.length > 1) setTimeout(function () { location.reload(); }, 500);
            } else {
                $('#dState', drawer).textContent = 'Не сохранилось — попробуйте ещё раз';
            }
        });
    };

    /* ── обложка ──────────────────────────────────────────────────────── */
    var bannerFile = document.getElementById('bannerFile');
    actions.banner = function () { bannerFile && bannerFile.click(); };
    actions['banner-clear'] = function () {
        var target = drawer && !drawer.hidden ? draft : prof;
        target.banner_url = '';
        applyPreview(target);
        if (target === prof) save('profile', profilePayload(prof));
    };
    if (bannerFile) bannerFile.addEventListener('change', function () {
        var f = bannerFile.files[0]; bannerFile.value = '';
        if (!f) return;
        if (f.size > 8 * 1024 * 1024) { toast('Файл больше 8 МБ', 'err'); return; }
        toast('Загружаем обложку…', 'mute');
        uploadImage(f).then(function (url) {
            if (!url) { toast('Не удалось загрузить', 'err'); return; }
            var inDrawer = drawer && !drawer.hidden;
            var target = inDrawer ? draft : prof;
            target.banner_url = url;
            applyPreview(target);
            if (!inDrawer) save('profile', profilePayload(prof)).then(function (r) {
                toast(r.success ? 'Обложка обновлена' : 'Не сохранилось', r.success ? 'ok' : 'err');
            });
            else toast('Обложка загружена — не забудьте сохранить');
        });
    });

    function uploadImage(file) {
        return new Promise(function (resolve) {
            var rd = new FileReader();
            rd.onload = function () {
                api('/swad/controllers/l4t/l4t_update.php', { type: 'upload', file: rd.result })
                    .then(function (r) { resolve(r.success ? r.url : ''); });
            };
            rd.onerror = function () { resolve(''); };
            rd.readAsDataURL(file);
        });
    }

    /* ── аватар ───────────────────────────────────────────────────────── */
    var avatarFile = document.getElementById('avatarFile');
    actions.avatar = function () { avatarFile && avatarFile.click(); };
    if (avatarFile) avatarFile.addEventListener('change', function () {
        var f = avatarFile.files[0]; avatarFile.value = '';
        if (!f) return;
        if (f.size > 2 * 1024 * 1024) { toast('Аватар — до 2 МБ', 'err'); return; }
        var box = $('.l4x-hero__ava'); box.classList.add('is-loading');
        var fd = new FormData(); fd.append('avatar', f); fd.append('csrf', C.csrf);
        fetch('/swad/controllers/upload_avatar.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': C.csrf } })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                box.classList.remove('is-loading');
                if (!r.success) { toast(r.error || 'Не удалось загрузить', 'err'); return; }
                var img = $('#heroAva'); img.src = r.url; img.hidden = false;
                var ph = $('.l4x-hero__ava-ph'); if (ph) ph.remove();
                toast('Аватар обновлён');
            })
            .catch(function () { box.classList.remove('is-loading'); toast('Ошибка сети', 'err'); });
    });

    /* ═══════════════════════ БЛОКИ ПРОФИЛЯ ═══════════════════════ */

    actions['about-more'] = function () {
        M.open('О себе', '<div class="l4x-prose">' + esc(C.about) + '</div>', null);
    };

    actions.about = function () {
        M.open('О себе',
            '<textarea class="l4x-input" id="mAbout" maxlength="10000" rows="12" placeholder="Что умеете, над чем работали, что ищете">' + esc(C.about) + '</textarea>' +
            '<div class="l4x-count" id="mAboutN">' + C.about.length + ' / 10000</div>',
            function () {
                var v = $('#mAbout').value.slice(0, 10000);
                M.busy(true);
                save('about', v).then(function (r) {
                    M.busy(false);
                    if (!r.success) { toast('Не сохранилось', 'err'); return; }
                    C.about = v;
                    var box = $('#aboutText');
                    box.classList.remove('is-long');
                    box.innerHTML = v ? esc(v).replace(/\n/g, '<br>') : '<span class="l4x-muted">Пока пусто</span>';
                    var more = $('[data-act="about-more"]'); if (more) more.remove();
                    M.close(); toast('Сохранено');
                });
            });
        $('#mAbout').addEventListener('input', function () { $('#mAboutN').textContent = this.value.length + ' / 10000'; });
    };

    actions.exp = function () {
        function row(e) {
            e = e || {};
            return '<div class="l4x-edit-row" data-row><input class="l4x-input" data-f="role" maxlength="30" placeholder="Unity, 2D-анимация, геймдизайн…" value="' + esc(e.role) + '">' +
                '<input class="l4x-input yrs" data-f="years" type="number" min="0" max="60" placeholder="лет" value="' + (parseInt(e.years, 10) || '') + '">' +
                '<button class="l4x-link" data-del title="Удалить">' + icon('close') + '</button></div>';
        }
        M.open('Опыт', '<div id="mRows">' + (C.exp.length ? C.exp.map(row).join('') : row()) + '</div>' +
            '<button class="l4x-link" id="mAdd">' + icon('plus') + 'Добавить строку</button>', function () {
            var list = $$('#mRows [data-row]').map(function (r) {
                return { role: $('[data-f="role"]', r).value.trim(), years: parseInt($('[data-f="years"]', r).value, 10) || 0 };
            }).filter(function (e) { return e.role; });
            M.busy(true);
            save('exp', list).then(function (r) {
                M.busy(false);
                if (!r.success) { toast('Не сохранилось', 'err'); return; }
                C.exp = r.data || list;
                $('#expList').innerHTML = C.exp.length ? C.exp.map(function (e) {
                    return '<span class="l4x-exp__item pix">' + esc(e.role) + '<b>' + (parseInt(e.years, 10) || 0) + ' г.</b></span>';
                }).join('') : '<span class="l4x-muted">Не указан</span>';
                M.close(); toast('Сохранено');
            });
        });
        wireRows(row);
    };

    actions.files = function () {
        function row(f) {
            f = f || {};
            return '<div class="l4x-edit-row" data-row><select class="l4x-input" data-f="type"><option value="link"' + (f.type !== 'file' ? ' selected' : '') + '>Ссылка</option><option value="file"' + (f.type === 'file' ? ' selected' : '') + '>Резюме/файл</option></select>' +
                '<input class="l4x-input" data-f="name" maxlength="60" placeholder="Название" value="' + esc(f.name) + '">' +
                '<input class="l4x-input" data-f="value" maxlength="500" placeholder="https://" value="' + esc(f.value) + '">' +
                '<button class="l4x-link" data-del>' + icon('close') + '</button></div>';
        }
        M.open('Ссылки и файлы', '<div id="mRows">' + (C.files.length ? C.files.map(row).join('') : row()) + '</div>' +
            '<button class="l4x-link" id="mAdd">' + icon('plus') + 'Добавить строку</button>' +
            '<p class="l4x-hint" style="margin:0">Только https-ссылки: портфолио, GitHub, ArtStation, резюме в облаке.</p>', function () {
            var list = $$('#mRows [data-row]').map(function (r) {
                return { type: $('[data-f="type"]', r).value, name: $('[data-f="name"]', r).value.trim(), value: $('[data-f="value"]', r).value.trim() };
            }).filter(function (f) { return f.value; });
            M.busy(true);
            save('files', list).then(function (r) {
                M.busy(false);
                if (!r.success) { toast('Не сохранилось', 'err'); return; }
                C.files = r.data || [];
                if (C.files.length < list.length) toast('Часть ссылок отброшена: нужен https://', 'err'); else toast('Сохранено');
                $('#filesList').innerHTML = C.files.length ? C.files.map(function (f) {
                    return '<a class="l4x-chip l4x-chip--link" href="' + esc(f.value) + '" target="_blank" rel="noopener nofollow">' + icon(f.type === 'file' ? 'file' : 'link') + esc(String(f.name).slice(0, 28)) + '</a>';
                }).join('') : '<span class="l4x-muted">Нет ссылок</span>';
                M.close();
            });
        });
        wireRows(row);
    };

    function wireRows(row) {
        $('#mAdd').addEventListener('click', function () { $('#mRows').insertAdjacentHTML('beforeend', row()); });
        $('#mRows').addEventListener('click', function (e) {
            if (e.target.closest('[data-del]')) e.target.closest('[data-row]').remove();
        });
    }

    /* ── проекты ─────────────────────────────────────────────────────── */
    function renderProjects() {
        var g = $('#projGrid');
        g.innerHTML = C.projects.length ? C.projects.map(function (p, i) {
            return '<button class="l4x-proj pix" data-act="project" data-i="' + i + '">' +
                '<span class="l4x-proj__cover"' + (p.cover ? ' style="background-image:url(\'' + esc(p.cover) + '\')"' : '') + '></span>' +
                '<span class="l4x-proj__title">' + esc(p.title) + '</span>' +
                '<span class="l4x-proj__meta">' + esc([p.role, p.year || ''].filter(Boolean).join(' · ')) + '</span></button>';
        }).join('') : '<span class="l4x-muted">Портфолио пустое</span>';
    }

    actions['project-new'] = function () { editProject(-1); };
    actions.project = function (el) {
        var i = parseInt(el.dataset.i, 10), p = C.projects[i];
        if (!p) return;
        if (C.isOwner) { editProject(i); return; }
        M.open(p.title || 'Проект',
            (p.cover ? '<div class="l4x-cover-pick" style="background-image:url(\'' + esc(p.cover) + '\');cursor:default"></div>' : '') +
            '<dl class="l4x-kv">' + (p.role ? '<dt>Роль</dt><dd>' + esc(p.role) + '</dd>' : '') + (p.year ? '<dt>Год</dt><dd>' + esc(p.year) + '</dd>' : '') + '</dl>' +
            (p.description ? '<div class="l4x-prose">' + esc(p.description) + '</div>' : '') +
            (p.url ? '<a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="' + esc(p.url) + '" target="_blank" rel="noopener nofollow">' + icon('link') + 'Открыть проект</a>' : ''), null);
    };

    function editProject(i) {
        var p = i > -1 ? C.projects[i] : {};
        var cover = p.cover || '';
        M.open(i > -1 ? 'Проект' : 'Новый проект',
            '<label class="l4x-field"><span class="l4x-field__label">Название *</span><input class="l4x-input" id="pT" maxlength="80" value="' + esc(p.title) + '"></label>' +
            '<div class="l4x-edit-row"><label class="l4x-field" style="flex:1"><span class="l4x-field__label">Ваша роль</span><input class="l4x-input" id="pR" maxlength="60" value="' + esc(p.role) + '"></label>' +
            '<label class="l4x-field" style="width:110px"><span class="l4x-field__label">Год</span><input class="l4x-input" id="pY" type="number" min="1990" max="2100" value="' + (p.year || '') + '"></label></div>' +
            '<label class="l4x-field"><span class="l4x-field__label">Ссылка * (https)</span><input class="l4x-input" id="pU" maxlength="500" placeholder="https://" value="' + esc(p.url) + '"></label>' +
            '<div class="l4x-field"><span class="l4x-field__label">Обложка</span><div class="l4x-cover-pick" id="pC"' + (cover ? ' style="background-image:url(\'' + esc(cover) + '\')"' : '') + '>' + (cover ? '' : 'Нажмите, чтобы выбрать') + '</div><input type="file" id="pF" accept="image/*" hidden></div>' +
            '<label class="l4x-field"><span class="l4x-field__label">Описание</span><textarea class="l4x-input" id="pD" maxlength="500" rows="4">' + esc(p.description) + '</textarea></label>' +
            (i > -1 ? '<button class="l4x-link l4x-danger" id="pDel">' + icon('close') + 'Удалить проект</button>' : ''),
            function () {
                var item = {
                    title: $('#pT').value.trim(), role: $('#pR').value.trim(), year: parseInt($('#pY').value, 10) || 0,
                    url: $('#pU').value.trim(), cover: cover, description: $('#pD').value.trim()
                };
                if (!item.title) { toast('Нужно название', 'err'); return; }
                if (!/^https?:\/\//i.test(item.url)) { toast('Ссылка должна начинаться с https://', 'err'); return; }
                var next = C.projects.slice();
                if (i > -1) next[i] = item; else next.push(item);
                persistProjects(next);
            });
        $('#pC').addEventListener('click', function () { $('#pF').click(); });
        $('#pF').addEventListener('change', function () {
            var f = this.files[0]; if (!f) return;
            $('#pC').textContent = 'Загружаем…';
            M.busy(true);
            uploadImage(f).then(function (url) {
                M.busy(false);
                if (!url) { $('#pC').textContent = 'Не удалось загрузить'; return; }
                cover = url; $('#pC').textContent = ''; $('#pC').style.backgroundImage = 'url("' + url + '")';
            });
        });
        var del = $('#pDel');
        if (del) del.addEventListener('click', function () {
            if (!confirm('Удалить проект?')) return;
            persistProjects(C.projects.filter(function (_, k) { return k !== i; }));
        });
    }

    function persistProjects(list) {
        M.busy(true);
        save('projects', list).then(function (r) {
            M.busy(false);
            if (!r.success) { toast('Не сохранилось', 'err'); return; }
            C.projects = r.data || list;
            renderProjects(); M.close(); toast('Сохранено');
        });
    }

    function openTodo(k) {
        var map = { avatar: 'avatar', about: 'about', exp: 'exp', projects: 'project-new', links: 'files' };
        if (map[k]) actions[map[k]]($('[data-act="' + map[k] + '"]') || root);
        else openDrawer();
    }

    /* ═══════════════════════ БИРЖА ═══════════════════════ */
    var feed = { q: '', tag: '', kind: '', offset: $$('#feed .l4x-bid').length, busy: false };
    var feedEl = $('#feed'), moreBtn = $('#feedMore'), countEl = $('#feedCount');

    function word(n) {
        var d = n % 10, h = n % 100;
        if (d === 1 && h !== 11) return 'заявка';
        if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return 'заявки';
        return 'заявок';
    }
    if (countEl) { var n0 = parseInt(countEl.textContent.replace(/\D/g, ''), 10) || 0; countEl.textContent = n0 + ' ' + word(n0); }

    function loadFeed(append) {
        if (feed.busy || !feedEl) return;
        feed.busy = true;
        if (moreBtn) moreBtn.disabled = true;
        var qs = new URLSearchParams({ q: feed.q, tag: feed.tag, kind: feed.kind, offset: append ? feed.offset : 0 });
        fetch('/l4t/api/feed.php?' + qs, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (!r.ok) throw new Error();
                if (append) feedEl.insertAdjacentHTML('beforeend', r.html);
                else feedEl.innerHTML = r.html || '<div class="l4x-empty">Ничего не нашлось. Попробуйте снять фильтр.</div>';
                feed.offset = (append ? feed.offset : 0) + r.shown;
                if (countEl) countEl.textContent = r.total + ' ' + word(r.total);
                if (moreBtn) moreBtn.hidden = !r.has_more;
            })
            .catch(function () { if (!append) feedEl.innerHTML = '<div class="l4x-empty">Не удалось загрузить ленту</div>'; })
            .then(function () { feed.busy = false; if (moreBtn) moreBtn.disabled = false; });
    }

    var qEl = $('#feedQ'), qT, peopleMode = false;
    if (qEl) qEl.addEventListener('input', function () {
        clearTimeout(qT);
        qT = setTimeout(function () {   // дебаунс: не бьём БД на каждую букву
            feed.q = qEl.value.trim();
            if (peopleMode) loadPeople(); else loadFeed(false);
        }, 250);
    });

    /* ── режим «Специалисты»: поиск людей по навыку ─────────────────── */
    var peopleEl = $('#people'), pSkill = $('#peopleSkill'), pSave = $('#peopleSave');
    function setPeopleMode(on) {
        peopleMode = on;
        if (peopleEl) peopleEl.hidden = !on;
        if (feedEl) feedEl.hidden = on;
        if (moreBtn) moreBtn.hidden = on || moreBtn.hidden;
        if (pSkill) pSkill.hidden = !on;
        if (pSave) pSave.hidden = !on;
        var tags = $('#feedTags'); if (tags) tags.hidden = on;
        if (qEl) qEl.placeholder = on ? 'Имя, роль, строка о себе…' : 'Роль, движок, условия…';
    }
    function loadPeople() {
        if (!peopleEl) return;
        var qs = new URLSearchParams({ op: 'people', skill: pSkill ? pSkill.value : '', q: qEl ? qEl.value.trim() : '' });
        fetch('/l4t/api/action.php?' + qs, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
            var items = r.items || [];
            if (countEl) countEl.textContent = items.length + ' чел.';
            peopleEl.innerHTML = items.length ? items.map(function (u) {
                var av = C.availability[u.availability];
                return '<a class="l4x-bid l4x-person" href="/l4t/' + encodeURIComponent(u.handle) + '">' +
                    '<div class="l4x-bid__top"><span class="l4x-bid__who">' + (u.avatar ? '<img src="' + esc(u.avatar) + '" alt="">' : '<span class="l4x-bid__ph">' + icon('user') + '</span>') + esc(u.name) + '</span>' +
                    (av ? '<span class="l4x-avail is-' + av.cls + '"><i></i>' + esc(av.label) + '</span>' : '') + '</div>' +
                    '<h3 class="l4x-bid__role">' + esc(u.role || 'Роль не указана') + '</h3>' +
                    (u.headline ? '<p class="l4x-bid__desc">' + esc(u.headline) + '</p>' : '') +
                    '<div class="l4x-bid__tags">' + (u.skills || []).map(function (s) { return '<span class="l4x-chip">' + esc((C.skills[s] || {}).name || s) + '</span>'; }).join('') + '</div></a>';
            }).join('') : '<div class="l4x-empty">Никого не нашлось. Сохраните поиск — пришлём уведомление, когда такой человек появится.</div>';
        });
    }
    if (pSkill) pSkill.addEventListener('change', loadPeople);
    if (pSave) pSave.addEventListener('click', function () {
        api('/l4t/api/action.php', { op: 'search_save', skill: pSkill.value, q: qEl ? qEl.value.trim() : '' }).then(function (r) {
            toast(r.ok ? 'Поиск сохранён — пришлём, когда кто-то появится' : (r.error || 'Не сохранилось'), r.ok ? 'ok' : 'err');
        });
    });
    $$('#feedKind button').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('#feedKind button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            var people = b.dataset.kind === 'people';
            setPeopleMode(people);
            if (people) { loadPeople(); return; }
            feed.kind = b.dataset.kind; loadFeed(false);
        });
    });
    $$('#feedTags .l4x-chip').forEach(function (t) {
        t.addEventListener('click', function () {
            var on = !t.classList.contains('is-on');
            $$('#feedTags .l4x-chip').forEach(function (x) { x.classList.remove('is-on'); });
            t.classList.toggle('is-on', on);
            feed.tag = on ? t.dataset.tag : ''; loadFeed(false);
        });
    });
    if (moreBtn) moreBtn.addEventListener('click', function () { loadFeed(true); });

    function kv(pairs) {
        return '<dl class="l4x-kv">' + pairs.filter(function (p) { return p[1]; }).map(function (p) {
            return '<dt>' + p[0] + '</dt><dd>' + esc(p[1]) + '</dd>';
        }).join('') + '</dl>';
    }

    function openBid(card) {
        var d; try { d = JSON.parse(card.dataset.bid); } catch (e) { return; }
        try {
            var body = JSON.stringify({ bid_id: d.id });
            if (!(navigator.sendBeacon && navigator.sendBeacon('/l4t/api/bid_view.php', new Blob([body], { type: 'application/json' }))))
                fetch('/l4t/api/bid_view.php', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' });
        } catch (e) {}

        var author = d.author ? '<a class="l4x-studio" href="/l4t/' + encodeURIComponent(d.author.handle) + '">' +
            '<span class="l4x-studio__ic pix">' + (d.author.avatar ? '<img src="' + esc(d.author.avatar) + '" alt="" style="width:100%;height:100%;object-fit:cover">' : esc((d.author.name || '?').replace('@', '').charAt(0).toUpperCase())) + '</span>' +
            '<span class="l4x-studio__body"><b>' + esc(d.author.name) + '</b><span class="l4x-muted">' + (d.type === 'studio' ? 'от студии' : 'автор заявки') + ' · ' + esc(d.date) + '</span></span>' + icon('arrow') + '</a>' : '';

        var html = author +
            kv([['Уровень', d.spec], ['Опыт', d.exp], ['Условия', d.cond], ['Цель', d.goal], ['Статистика', d.views + ' просмотров · ' + d.responses + ' откликов']]) +
            (d.details ? '<div class="l4x-sep"><div class="l4x-prose">' + esc(d.details) + '</div></div>' : '');

        var canRespond = C.loggedIn && !d.mine;
        if (canRespond) {
            html += '<div class="l4x-sep"><label class="l4x-field"><span class="l4x-field__label">Сопроводительное сообщение</span>' +
                '<textarea class="l4x-input" id="mResp" maxlength="1000" rows="4" placeholder="Коротко о себе и почему вам интересно"></textarea></label>' +
                '<div class="l4x-count" id="mRespN">0 / 1000</div></div>';
        } else if (!C.loggedIn) {
            html += '<div class="l4x-sep"><a class="l4x-btn l4x-btn--acc" href="/login?backUrl=/l4t/">Войдите, чтобы откликнуться</a></div>';
        }

        M.open(d.role || 'Заявка', html, canRespond ? function () { respond(d, card); } : null, { saveText: 'Откликнуться', noFocus: true });
        var ta = $('#mResp');
        if (ta) ta.addEventListener('input', function () { $('#mRespN').textContent = ta.value.length + ' / 1000'; });
    }

    function respond(d, card) {
        var msg = ($('#mResp') || {}).value || '';
        if (!msg.trim()) { toast('Напишите пару слов о себе', 'err'); return; }
        M.busy(true, 'Отправляем…');
        api('/swad/controllers/l4t/respond_bid.php', { bid_id: d.id, message: msg.trim() }).then(function (r) {
            if (!r.success) { M.busy(false, 'Откликнуться'); toast(r.message || 'Не удалось отправить', 'err'); return; }
            M.close(); toast('Отклик отправлен');
            d.responses++; d.mine = true;   // повторно не откликнуться
            card.dataset.bid = JSON.stringify(d);
            var s = card.querySelectorAll('.l4x-bid__foot span')[1];
            if (s) s.lastChild.textContent = d.responses;
        });
    }

    function openResp(el) {
        var d; try { d = JSON.parse(el.dataset.resp); } catch (e) { return; }
        var html = kv([['Заявка', d.role], ['Уровень', d.spec], ['Условия', d.cond], ['Дата', d.date], ['Статус', d.status]]);
        if (d.message) html += '<div class="l4x-sep"><div class="l4x-field__label" style="margin-bottom:6px">Сообщение</div><div class="l4x-prose">' + esc(d.message) + '</div></div>';
        if (d.kind === 'incoming') {
            html += '<div class="l4x-sep l4x-row" style="flex-wrap:wrap">' +
                (d.who ? '<a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/l4t/' + encodeURIComponent(d.who.handle) + '">' + icon('user') + 'Профиль' + (d.l4trole ? ' · ' + esc(d.l4trole) : '') + '</a>' : '') +
                (d.tg ? '<a class="l4x-btn l4x-btn--acc l4x-btn--sm" href="https://t.me/' + esc(d.tg) + '" target="_blank" rel="noopener">' + icon('telegram') + '@' + esc(d.tg) + '</a>' : '') +
                (!d.who && !d.tg ? '<span class="l4x-muted">Контакты не указаны</span>' : '') + '</div>';
            html += '<div class="l4x-sep"><div class="l4x-field__label" style="margin-bottom:8px">Решение — автор отклика получит уведомление</div><div class="l4x-seg" id="respStatus">' +
                (C.statuses || []).map(function (s) {
                    return '<button data-s="' + esc(s) + '" class="' + (s === d.status ? 'is-on' : '') + '">' + esc(s) + '</button>';
                }).join('') + '</div><p class="l4x-hint">«В команде» добавит проект в титры обоим и откроет возможность написать рекомендации.</p></div>';
        }
        M.open(d.kind === 'incoming' ? 'Отклик: ' + (d.who ? d.who.name : '') : 'Мой отклик', html, null);
        var seg = $('#respStatus');
        if (seg) seg.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return;
            api('/l4t/api/action.php', { op: 'respond_status', id: d.id, status: b.dataset.s }).then(function (r) {
                if (!r.ok) { toast(r.error || 'Не сохранилось', 'err'); return; }
                $$('#respStatus button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                d.status = b.dataset.s; el.dataset.resp = JSON.stringify(d);
                var chip = el.querySelector('.l4x-chip'); if (chip) chip.textContent = d.status;
                toast('Статус: ' + d.status);
            });
        });
    }

    /* ═══════════════════════ МОИ ЗАЯВКИ ═══════════════════════ */
    var form = $('#bidForm');
    if (form) {
        var f = function (id) { return $('#' + id, form); };
        var studioSel = f('f_owner_id');
        $$('input[name="owner_type"]', form).forEach(function (r) {
            r.addEventListener('change', function () { if (studioSel) studioSel.hidden = r.value !== 'studio' || !r.checked; });
        });
        var cnt = function () { f('f_count').textContent = f('f_details').value.length + ' / 5000'; };
        f('f_details').addEventListener('input', cnt); cnt();

        function setSelect(sel, v) {
            if (!v) return;
            if (![].some.call(sel.options, function (o) { return o.value === v; })) sel.add(new Option(v, v));
            sel.value = v;
        }
        actions['edit-bid'] = function (el) {
            var d = JSON.parse(el.dataset.bid);
            f('f_bid_id').value = d.id;
            f('f_role').value = d.role; f('f_spec').value = d.spec; f('f_cond').value = d.cond; f('f_details').value = d.details;
            setSelect(f('f_exp'), d.exp); setSelect(f('f_goal'), d.goal);
            var r = $('input[name="owner_type"][value="' + (d.owner_type === 'studio' ? 'studio' : 'user') + '"]', form);
            if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
            if (studioSel && d.owner_type === 'studio') studioSel.value = d.owner_id;
            $$('#f_skills input', form).forEach(function (c) { c.checked = (d.skills || []).indexOf(c.value) > -1; });
            $('#bidFormTitle').innerHTML = icon('edit') + 'Заявка #' + d.id;
            $('#bidSubmitText').textContent = 'Сохранить';
            $('#bidCancel').hidden = false;
            cnt();
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            f('f_role').focus({ preventScroll: true });
        };
        $('#bidCancel').addEventListener('click', function () {
            form.reset(); f('f_bid_id').value = '';
            $('#bidFormTitle').innerHTML = icon('plus') + 'Новая заявка';
            $('#bidSubmitText').textContent = 'Опубликовать';
            $('#bidCancel').hidden = true;
            if (studioSel) studioSel.hidden = true;
            cnt();
        });
        form.addEventListener('change', function (e) {
            if (e.target.name === 'skills[]' && $$('#f_skills input:checked', form).length > 8) {
                e.target.checked = false; toast('Не больше 8 навыков', 'err');
            }
        });
        form.addEventListener('submit', function (e) {
            if (!f('f_role').value.trim()) { e.preventDefault(); toast('Укажите, кого ищете', 'err'); f('f_role').focus(); }
        });
    }

    /* ═══════════════════════ ДЖЕМЫ ═══════════════════════ */
    var JAM = C.jam;

    function attachAutocomplete(input, sprintId) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;flex:1';
        input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
        var dd = document.createElement('div');
        dd.style.cssText = 'position:absolute;left:0;right:0;top:100%;z-index:5;background:#240b30;box-shadow:inset 0 0 0 1px var(--line);max-height:200px;overflow:auto;display:none';
        wrap.appendChild(dd);
        var t;
        input.addEventListener('input', function () {
            var q = input.value.trim(); clearTimeout(t);
            if (q.length < 4) { dd.style.display = 'none'; return; }
            t = setTimeout(function () {
                fetch('/swad/controllers/jams/search_participants.php?sprint_id=' + sprintId + '&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (list) {
                        dd.innerHTML = list.length ? list.map(function (u) {
                            var h = u.username || u.telegram_username || '';
                            return '<div data-u="' + esc(h) + '" style="padding:8px 12px;cursor:pointer;font-size:13px">' + esc(u.username || '@' + u.telegram_username) + '</div>';
                        }).join('') : '<div style="padding:8px 12px;color:var(--muted);font-size:13px">Никого не найдено</div>';
                        dd.style.display = 'block';
                    });
            }, 250);
        });
        dd.addEventListener('click', function (e) { var i = e.target.closest('[data-u]'); if (i) { input.value = i.dataset.u; dd.style.display = 'none'; } });
    }

    function inviteRow(teamId, sprintId) {
        var inp = $('#invNick'); if (!inp) return;
        attachAutocomplete(inp, sprintId);
        $('#invBtn').addEventListener('click', function () {
            var nick = inp.value.trim(); if (!nick) return;
            api('/swad/controllers/jams/invite_member.php', { team_id: teamId, username: nick }).then(function (d) {
                var r = $('#invResult');
                r.textContent = d.message || (d.success ? 'Приглашение отправлено' : 'Ошибка');
                r.style.color = d.success ? 'var(--up)' : 'var(--down)';
                if (d.success) inp.value = '';
            });
        });
    }
    var inviteHtml = '<div class="l4x-edit-row"><input class="l4x-input" id="invNick" placeholder="Ник участника (от 4 символов)" autocomplete="off">' +
        '<button class="l4x-btn l4x-btn--acc l4x-btn--sm" id="invBtn">Пригласить</button></div><div id="invResult" class="l4x-hint" style="margin:0"></div>';

    function openTeamModal() {
        if (!JAM) return;
        fetch('/swad/controllers/jams/get_team.php?sprint_id=' + JAM.id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (s) {
                if (!s.success) { toast(s.message || 'Ошибка', 'err'); return; }
                if (!s.in_sprint) {
                    M.open('Команда — ' + JAM.title, '<p class="l4x-muted" style="margin:0">Чтобы собрать команду, сначала зарегистрируйтесь на джем.</p>' +
                        '<a class="l4x-btn l4x-btn--acc" href="/jams?sprint=' + JAM.id + '">Открыть джем</a>', null);
                    return;
                }
                if (s.has_team) {
                    var t = s.team;
                    M.open('Моя команда',
                        '<div><b>' + esc(t.team_name) + '</b>' + (t.team_desc ? '<div class="l4x-muted">' + esc(t.team_desc) + '</div>' : '') +
                        '<div class="l4x-hint" style="margin-top:4px">' + s.members.length + ' / ' + t.team_limit + ' · ' + (t.visibility === 'private' ? 'приватная' : 'публичная') + '</div></div>' +
                        '<div class="l4x-sep">' + s.members.map(function (m) {
                            return '<a class="l4x-studio" href="/l4t/' + encodeURIComponent(m.username) + '"><span class="l4x-studio__ic pix">' + (m.member_role === 'captain' ? '★' : esc((m.username || '?').charAt(0).toUpperCase())) + '</span><span class="l4x-studio__body"><b>' + esc(m.username) + '</b><span class="l4x-muted">' + (m.member_role === 'captain' ? 'капитан' : 'участник') + '</span></span></a>';
                        }).join('') + '</div>' + (s.is_captain ? '<div class="l4x-sep">' + inviteHtml + '</div>' : ''), null);
                    if (s.is_captain) inviteRow(t.id, t.sprint_id);
                    return;
                }
                M.open('Создать команду — ' + JAM.title,
                    '<label class="l4x-field"><span class="l4x-field__label">Название *</span><input class="l4x-input" id="tName" maxlength="120" placeholder="Pixel Wizards"></label>' +
                    '<label class="l4x-field"><span class="l4x-field__label">Описание</span><textarea class="l4x-input" id="tDesc" maxlength="2000" rows="3" placeholder="Кого ищете, что за идея"></textarea></label>' +
                    '<div class="l4x-edit-row"><label class="l4x-field" style="flex:1"><span class="l4x-field__label">Видимость</span><select class="l4x-input" id="tVis" style="width:100%"><option value="public">Публичная</option><option value="private">Приватная</option></select></label>' +
                    '<label class="l4x-field" style="width:110px"><span class="l4x-field__label">Лимит</span><input class="l4x-input" id="tLimit" type="number" min="2" max="20" value="5"></label></div>',
                    function () {
                        var name = $('#tName').value.trim();
                        if (!name) { toast('Введите название', 'err'); return; }
                        M.busy(true);
                        api('/swad/controllers/jams/create_team.php', {
                            sprint_id: JAM.id, team_name: name, team_desc: $('#tDesc').value.trim(),
                            visibility: $('#tVis').value, team_limit: parseInt($('#tLimit').value, 10) || 5
                        }).then(function (d) {
                            M.busy(false);
                            if (!d.success) { toast(d.message || 'Ошибка', 'err'); return; }
                            M.open('Команда создана', '<p class="l4x-muted" style="margin:0">Пригласите участников по нику — им придёт уведомление.</p>' + inviteHtml, null);
                            inviteRow(d.team_id, JAM.id);
                        });
                    }, { saveText: 'Создать' });
            })
            .catch(function () { toast('Не удалось загрузить команду', 'err'); });
    }

    if (JAM && C.jamAction === 'create_team') openTeamModal();
    if (JAM && C.jamAction === 'jam' && form) {
        M.open('Заявка на джем «' + JAM.title + '»',
            (JAM.description ? '<div class="l4x-prose l4x-muted">' + esc(JAM.description) + '</div>' : '') +
            '<label class="l4x-field"><span class="l4x-field__label">Кого ищете (необязательно)</span><textarea class="l4x-input" id="jMsg" rows="3"></textarea></label>',
            function () {
                var msg = $('#jMsg').value.trim();
                showTab('bids');
                $('#f_goal', form).value = 'Найти человека в команду';
                $('#f_details', form).value = 'Собираю команду для участия в джеме «' + JAM.title + '».\n\n' +
                    (msg ? msg + '\n\n' : '') + 'Ссылка на джем: https://dustore.ru/jams/' + JAM.id;
                if (!$('input[name="jam_id"]', form)) form.insertAdjacentHTML('afterbegin', '<input type="hidden" name="jam_id" value="' + JAM.id + '">');
                M.close();
                $('#f_role', form).focus();
            }, { saveText: 'Перейти к заявке' });
    }

    /* Внутреннее API для l4x-more.js: те же модалка, тост и делегирование. */
    window.L4XUI = { C: C, root: root, $: $, $$: $$, esc: esc, icon: icon, api: api, save: save, toast: toast, M: M,
                     actions: actions, showTab: showTab, uploadImage: uploadImage, openDrawer: openDrawer };
})();
