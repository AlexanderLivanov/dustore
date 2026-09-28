/* ======================================================================
   Fid Core — редактор статей.
   Открывается событием  document.dispatchEvent(new CustomEvent('fx:article', {detail: cfg}))
   (его шлёт кнопка «Статья» в композере; cfg = data-cfg формы: wall_type, wall_id, channel, as[]).
   Публикует через api/fx.php?action=create (kind=article). HTML статьи всё равно
   чистится на сервере белым списком (FxSanitize), тут — только удобство набора.

   Подключать после fx.js:  <script src="/swad/js/fx-editor.js" defer></script>
   ====================================================================== */
(function () {
    'use strict';
    if (window.__fxEditor) return; window.__fxEditor = true;

    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const UI = () => window.FxUI;
    const ic = (n, c) => UI().ic(n, c);

    const TOOLS = [
        ['h2', 'H2', 'Подзаголовок'], ['h3', 'H3', 'Малый подзаголовок'], '|',
        ['b', 'bold', 'Жирный'], ['i', 'italic', 'Курсив'], ['code', 'code', 'Код'], '|',
        ['quote', 'quote', 'Цитата'], ['ul', 'list', 'Список'], ['ol', 'list-ol', 'Нумерованный список'], '|',
        ['link', 'link', 'Ссылка'], ['img', 'image', 'Картинка'], ['hr', 'minus', 'Разделитель'],
    ];

    let el = null, wys, h1, st, cfg, saveT, range = null;

    function draftKey() { return 'fx_article_draft:' + (cfg.wall_type || 'media') + ':' + (cfg.wall_id || 0); }
    function readDraft() { try { return JSON.parse(localStorage.getItem(draftKey()) || 'null'); } catch (e) { return null; } }
    function writeDraft(d) { try { d ? localStorage.setItem(draftKey(), JSON.stringify(d)) : localStorage.removeItem(draftKey()); } catch (e) { /* приватный режим */ } }

    function build() {
        const root = $('.fx'); if (!root) return null;
        const wrap = document.createElement('div');
        wrap.className = 'fx-ed'; wrap.setAttribute('role', 'dialog'); wrap.setAttribute('aria-modal', 'true');
        wrap.innerHTML =
            '<header class="fx-ed__bar">' +
            '<button type="button" class="fx-ed__ib" data-ed="close" aria-label="Закрыть">' + ic('arrow-lt') + '</button>' +
            '<div class="fx-ed__who"><b>Новая статья</b><span data-saved>черновик не сохранён</span></div>' +
            '<button type="button" class="fx-ed__ib fx-ed__opts" data-ed="side" aria-label="Параметры">' + ic('settings') + '</button>' +
            '<button type="button" class="fx-btn" data-ed="publish" disabled>Опубликовать</button></header>' +
            '<div class="fx-ed__body"><main class="fx-ed__main">' +
            '<div class="fx-ed__tools" data-tools></div>' +
            '<div class="fx-ed__link hidden" data-linkbar><input type="url" placeholder="https://…" aria-label="Адрес ссылки"><button type="button" class="fx-btn" data-ed="link-ok">Готово</button><button type="button" class="fx-ed__ib" data-ed="link-x" aria-label="Отмена">' + ic('close', 'ic--sm') + '</button></div>' +
            '<div class="fx-ed__paper"><textarea class="fx-ed__h1" rows="1" maxlength="200" placeholder="Заголовок статьи"></textarea>' +
            '<div class="fx-ed__wys" contenteditable="true" spellcheck="true" data-ph="Пишите здесь. Выделите текст — и оформите его кнопками сверху."></div></div>' +
            '<div class="fx-ed__stats" data-stats></div><input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden data-ed-file></main>' +
            '<aside class="fx-ed__side"><div class="fx-ed__grip" data-ed="side"></div>' +
            '<label class="fx-ed__f">Публикуем от</label><select class="fx-ed__inp" data-as></select>' +
            '<label class="fx-ed__f">Обложка</label><div class="fx-ed__cover" data-cover><button type="button" data-ed="cover">' + ic('image') + '<span>Загрузить обложку</span><small>JPG, PNG, WebP · до 6 МБ</small></button></div>' +
            '<label class="fx-ed__f">Теги <small>через запятую</small></label><input class="fx-ed__inp" data-tags maxlength="120" placeholder="разработка, левелдизайн">' +
            '<label class="fx-sw fx-ed__cross hidden" data-cross><input type="checkbox" name="crosspost" value="1"><span></span>Показать в общей ленте</label>' +
            '<p class="fx-note">Статья появится в ленте карточкой с заголовком и началом текста; целиком откроется по клику.</p></aside></div>';
        root.appendChild(wrap);

        const tools = $('[data-tools]', wrap);
        TOOLS.forEach(t => {
            if (t === '|') { tools.insertAdjacentHTML('beforeend', '<i class="fx-ed__sep"></i>'); return; }
            const b = document.createElement('button');
            b.type = 'button'; b.dataset.cmd = t[0]; b.title = t[2]; b.setAttribute('aria-label', t[2]);
            b.innerHTML = /^H\d$/.test(t[1]) ? '<b>' + t[1] + '</b>' : ic(t[1]);
            b.addEventListener('mousedown', e => e.preventDefault());   // не терять выделение в тексте
            b.addEventListener('click', () => exec(t[0]));
            tools.appendChild(b);
        });
        return wrap;
    }

    /* ------------------------------------------------------------------ команды */
    function saveRange() { const s = getSelection(); range = s.rangeCount ? s.getRangeAt(0).cloneRange() : null; }
    function restoreRange() { if (!range) return; const s = getSelection(); s.removeAllRanges(); s.addRange(range); }
    function inWys() { const s = getSelection(); return s.rangeCount && wys.contains(s.anchorNode); }

    function block(tag) {
        const cur = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
        document.execCommand('formatBlock', false, cur === tag ? 'p' : tag);
    }

    function exec(cmd) {
        wys.focus();
        if (!inWys() && range) restoreRange();
        switch (cmd) {
            case 'h2': block('h2'); break;
            case 'h3': block('h3'); break;
            case 'b': document.execCommand('bold'); break;
            case 'i': document.execCommand('italic'); break;
            case 'code': {
                const t = getSelection().toString();
                if (t) document.execCommand('insertHTML', false, '<code>' + t.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])) + '</code>&#8203;');
                break;
            }
            case 'quote': block('blockquote'); break;
            case 'ul': document.execCommand('insertUnorderedList'); break;
            case 'ol': document.execCommand('insertOrderedList'); break;
            case 'hr': document.execCommand('insertHorizontalRule'); break;
            case 'link': {
                saveRange(); const bar = $('[data-linkbar]', el); bar.classList.remove('hidden'); $('input', bar).value = ''; $('input', bar).focus(); return;
            }
            case 'img': saveRange(); $('[data-ed-file]', el).click(); return;
        }
        changed();
    }

    async function uploadFile(f) {
        const fd = new FormData(); fd.append('file', f);
        return UI().api('upload', fd);
    }

    /* ------------------------------------------------------------------ состояние */
    function words() { return (wys.innerText.trim().match(/[\p{L}\p{N}]+/gu) || []).length; }

    function changed() {
        if (st.done) return;
        if (wys.firstChild && wys.firstChild.nodeType === 3 && inWys()) document.execCommand('formatBlock', false, 'p');   // голый текст → <p>
        const w = words(), ok = h1.value.trim().length >= 3 && w >= 20;
        $('[data-ed=publish]', el).disabled = !ok || st.busy;
        $('[data-stats]', el).textContent = w + ' ' + plural(w, 'слово', 'слова', 'слов') + ' · чтение ≈ ' + Math.max(1, Math.round(w / 180)) + ' мин' + (ok ? '' : ' · нужен заголовок и хотя бы 20 слов');
        h1.style.height = 'auto'; h1.style.height = h1.scrollHeight + 'px';
        clearTimeout(saveT); saveT = setTimeout(save, 700);
    }
    function plural(n, a, b, c) { n = Math.abs(n) % 100; const m = n % 10; return n > 10 && n < 20 ? c : m > 1 && m < 5 ? b : m === 1 ? a : c; }

    function save() {
        if (!h1.value.trim() && !wys.innerText.trim()) { writeDraft(null); $('[data-saved]', el).textContent = 'черновик не сохранён'; return; }
        writeDraft({ title: h1.value, html: wys.innerHTML, tags: $('[data-tags]', el).value, cover: st.cover, as: st.as, t: Date.now() });
        const d = new Date(); $('[data-saved]', el).textContent = 'черновик сохранён в этом браузере · ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function paintCover() {
        const box = $('[data-cover]', el);
        if (!st.cover) { box.innerHTML = '<button type="button" data-ed="cover">' + ic('image') + '<span>Загрузить обложку</span><small>JPG, PNG, WebP · до 6 МБ</small></button>'; return; }
        box.innerHTML = '<div class="fx-ed__coverimg" style="background-image:url(\'' + st.cover.u + '\')"><button type="button" data-ed="cover-x" aria-label="Убрать обложку">' + ic('close', 'ic--sm') + '</button></div>';
    }

    /* ------------------------------------------------------------------ открыть / закрыть */
    function open(detail) {
        cfg = detail || {};
        if (!el || !el.isConnected) el = build();
        if (!el) return;
        wys = $('.fx-ed__wys', el); h1 = $('.fx-ed__h1', el);
        st = { cover: null, as: 0, busy: false, done: false };
        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { }

        const sel = $('[data-as]', el);
        const opts = cfg.wall_type === 'studio' ? [] : [{ id: 0, name: 'От своего имени' }].concat((cfg.as || []).map(a => ({ id: a.id, name: 'От студии «' + a.name + '»' })));
        sel.innerHTML = opts.map(o => '<option value="' + o.id + '">' + o.name.replace(/[<>&"]/g, '') + '</option>').join('');
        sel.closest('.fx-ed__side').querySelector('label.fx-ed__f').classList.toggle('hidden', !opts.length);
        sel.classList.toggle('hidden', !opts.length);
        $('[data-cross]', el).classList.toggle('hidden', cfg.wall_type !== 'studio');   // девлог студии → можно продублировать в общую ленту
        $('[data-cross] input', el).checked = false;

        const d = readDraft();
        h1.value = d ? d.title : ''; wys.innerHTML = d ? d.html : ''; $('[data-tags]', el).value = d ? d.tags || '' : '';
        st.cover = d ? d.cover || null : null; st.as = d ? +d.as || 0 : 0; sel.value = String(st.as); paintCover();
        if (d && (d.title || d.html)) UI().toast('Черновик восстановлен');

        el.classList.add('open'); requestAnimationFrame(() => el.classList.add('in'));
        document.documentElement.style.overflow = 'hidden';
        changed(); (h1.value ? wys : h1).focus();
    }

    function close(force) {
        if (!el) return;
        if (!force && !st.done && (h1.value.trim() || wys.innerText.trim())) save();
        el.classList.remove('in', 'side-open');
        setTimeout(() => el.classList.remove('open'), 220);
        document.documentElement.style.overflow = '';
    }

    /* ------------------------------------------------------------------ публикация */
    const INLINE = /^(B|I|EM|STRONG|A|CODE|BR|SPAN)$/;
    function cleanHtml() {
        const c = wys.cloneNode(true);
        // «голые» куски текста верхнего уровня → абзацы (иначе сервер склеит их без разрывов)
        let run = [];
        const flush = () => { if (!run.length) return; const p = document.createElement('p'); run[0].before(p); run.forEach(n => p.appendChild(n)); run = []; };
        Array.from(c.childNodes).forEach(n => { if (n.nodeType === 3 || (n.nodeType === 1 && INLINE.test(n.tagName))) run.push(n); else flush(); });
        flush();
        $$('[contenteditable],[style],[class]', c).forEach(n => { n.removeAttribute('contenteditable'); n.removeAttribute('style'); n.removeAttribute('class'); });
        $$('div', c).forEach(d => { const p = document.createElement('p'); p.innerHTML = d.innerHTML; d.replaceWith(p); });
        c.innerHTML = c.innerHTML.replace(/​/g, '');
        return c.innerHTML.trim();
    }

    async function publish() {
        const btn = $('[data-ed=publish]', el); st.busy = true; btn.disabled = true; btn.textContent = 'Публикуем…';
        const data = {
            wall_type: cfg.wall_type || 'media', wall_id: cfg.wall_id || 0, channel: cfg.channel || 'wall', kind: 'article',
            title: h1.value.trim(), article: cleanHtml(), tags: $('[data-tags]', el).value, media: st.cover ? [st.cover] : [],
        };
        if (cfg.wall_type === 'studio') data.as_studio_id = cfg.wall_id; else if (st.as) data.as_studio_id = st.as;
        if ($('[data-cross] input', el).checked && !$('[data-cross]', el).classList.contains('hidden')) data.crosspost = 1;
        try {
            const j = await UI().api('create', data);
            st.done = true; clearTimeout(saveT); writeDraft(null); close(true);
            const feed = $('.fx-pane:not([hidden]) .fx-feed') || $('.fx-feed');
            if (feed && j.html) { const emp = $('.fx-empty', feed); if (emp) emp.remove(); const first = $('.fx-post:not(.is-pinned)', feed); (first || feed).insertAdjacentHTML(first ? 'beforebegin' : 'afterbegin', j.html); }
            UI().toast('Статья опубликована');
        } catch (e) { UI().toast(({ empty: 'Добавьте заголовок и текст', too_long: 'Статья слишком длинная', rate: 'Слишком часто. Подождите минуту', forbidden: 'Недостаточно прав', bad_media: 'Не удалось прикрепить обложку' })[e.code] || 'Не удалось опубликовать. Черновик сохранён', true); }
        finally { st.busy = false; btn.textContent = 'Опубликовать'; if (!st.done) changed(); }
    }

    /* ------------------------------------------------------------------ события */
    document.addEventListener('fx:article', e => open(e.detail));

    document.addEventListener('click', async e => {
        const t = e.target.closest('[data-ed]'); if (!t || !el || !el.contains(t)) return;
        switch (t.dataset.ed) {
            case 'close': close(); break;
            case 'side': el.classList.toggle('side-open'); break;
            case 'publish': publish(); break;
            case 'cover': { const f = document.createElement('input'); f.type = 'file'; f.accept = 'image/jpeg,image/png,image/webp,image/gif'; f.onchange = async () => { if (!f.files[0]) return; try { const j = await uploadFile(f.files[0]); st.cover = { u: j.u, w: j.w, h: j.h }; paintCover(); save(); } catch (er) { UI().toast(({ too_big: 'Файл больше 6 МБ', bad_type: 'Только JPG, PNG, WebP или GIF' })[er.code] || 'Не удалось загрузить', true); } }; f.click(); break; }
            case 'cover-x': st.cover = null; paintCover(); save(); break;
            case 'link-x': $('[data-linkbar]', el).classList.add('hidden'); wys.focus(); break;
            case 'link-ok': {
                const v = $('[data-linkbar] input', el).value.trim();
                $('[data-linkbar]', el).classList.add('hidden'); wys.focus(); restoreRange();
                if (/^https?:\/\//i.test(v)) { if (getSelection().isCollapsed) document.execCommand('insertHTML', false, '<a href="' + v.replace(/"/g, '&quot;') + '">' + v.replace(/[<>&]/g, '') + '</a>'); else document.execCommand('createLink', false, v); changed(); }
                break;
            }
        }
    });

    document.addEventListener('input', e => {
        if (!el || !el.contains(e.target)) return;
        if (e.target.matches('[data-as]')) { st.as = +e.target.value; save(); return; }
        changed();
    });
    document.addEventListener('change', async e => {
        if (!el || !e.target.matches('[data-ed-file]')) return;
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        try { const j = await uploadFile(f); wys.focus(); restoreRange(); document.execCommand('insertHTML', false, '<img src="' + j.u + '" alt=""><p><br></p>'); changed(); }
        catch (er) { UI().toast(({ too_big: 'Файл больше 6 МБ', bad_type: 'Только JPG, PNG, WebP или GIF' })[er.code] || 'Не удалось загрузить', true); }
    });
    document.addEventListener('paste', e => {                      // вставляем чистый текст: чужая вёрстка в статью не нужна
        if (!el || !e.target.closest || !e.target.closest('.fx-ed__wys')) return;
        e.preventDefault();
        document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
    });
    document.addEventListener('keydown', e => {
        if (!el || !el.classList.contains('open')) return;
        if (e.key === 'Escape' && !document.querySelector('.fx-modal.open')) { e.stopPropagation(); close(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !$('[data-ed=publish]', el).disabled) publish();
        if ((e.ctrlKey || e.metaKey) && ['b', 'i'].includes(e.key.toLowerCase()) && e.target.closest('.fx-ed__wys')) { e.preventDefault(); exec(e.key.toLowerCase()); }
        if (e.key === 'Enter' && e.target.matches('[data-linkbar] input')) { e.preventDefault(); $('[data-ed=link-ok]', el).click(); }
        if (e.key === 'Enter' && e.target === h1) { e.preventDefault(); wys.focus(); }
    }, true);
    document.addEventListener('selectionchange', () => { if (el && el.classList.contains('open') && inWys()) saveRange(); });
    window.addEventListener('beforeunload', () => { if (el && el.classList.contains('open') && !st.done) save(); });
})();
