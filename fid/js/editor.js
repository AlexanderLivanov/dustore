/* ======================================================================
   Dustore.Fid — композер, @упоминания, черновики и редактор статьи
   Источник истины — Markdown. Визуальный режим это ПРЕДСТАВЛЕНИЕ над ним.
   Упоминание хранится как @[тип:id|Имя] — переименование сущности
   не ломает ссылку.
   ====================================================================== */
(() => {
'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const toast = t => window.dfid ? window.dfid.toast(t) : console.log(t);
const svg = (n, c = '') => `<svg class="ic${c ? ' ' + c : ''}" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const buzz = ms => navigator.vibrate && navigator.vibrate(ms);
const DATA = window.DFID || { targets: [], entities: {}, mentionIcon: {} };
const MICON = DATA.mentionIcon;

const LONG_WORDS = 120;
const MENTION_RE = /@\[(\w+):([\w\-]+)\|([^\]]+)\]/g;

const S = {
    id: null,
    target: DATA.targets[0]?.id || 'me',
    title: '', md: '', tags: '', card: 'big', cover: '',
    slug: '', seoTitle: '', seoDesc: '',
    plats: {}, plat: 'tg', mode: 'wys',
};

/* ======================================================================
   1. MARKDOWN ⇄ HTML
   ====================================================================== */
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');

const mentionHTML = (t, id, name, editable) =>
    `<a class="mn mn--${t}" data-m="${t}:${id}"${editable ? ' contenteditable="false"' : ''}>` +
    svg(MICON[t] || 'user') + `<span>${name}</span></a>`;

function inlineMd(s, editable) {
    return esc(s)
        .replace(MENTION_RE, (_, t, id, n) => mentionHTML(t, id, n, editable))
        .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img alt="$1" src="$2">')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
        .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
        .replace(/(^|[\s(])\*([^*]+)\*/g, '$1<i>$2</i>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
}

function mdToHtml(md, editable) {
    const out = []; let list = null;
    const flush = () => { if (list) { out.push('</' + list + '>'); list = null; } };
    md.split('\n').forEach(line => {
        const t = line.trim(); let m;
        if (!t) return flush();
        if ((m = t.match(/^###\s+(.*)/)))   { flush(); return out.push('<h3>' + inlineMd(m[1], editable) + '</h3>'); }
        if ((m = t.match(/^##\s+(.*)/)))    { flush(); return out.push('<h2>' + inlineMd(m[1], editable) + '</h2>'); }
        if (/^(-{3,}|\*{3,})$/.test(t))     { flush(); return out.push('<hr>'); }
        if ((m = t.match(/^>\s?(.*)/)))     { flush(); return out.push('<blockquote>' + inlineMd(m[1], editable) + '</blockquote>'); }
        if ((m = t.match(/^[-*]\s+(.*)/)))  { if (list !== 'ul') { flush(); out.push('<ul>'); list = 'ul'; } return out.push('<li>' + inlineMd(m[1], editable) + '</li>'); }
        if ((m = t.match(/^\d+\.\s+(.*)/))) { if (list !== 'ol') { flush(); out.push('<ol>'); list = 'ol'; } return out.push('<li>' + inlineMd(m[1], editable) + '</li>'); }
        flush(); out.push('<p>' + inlineMd(t, editable) + '</p>');
    });
    flush();
    return out.join('');
}

function htmlToMd(root) {
    const inl = n => [...n.childNodes].map(c => {
        if (c.nodeType === 3) return c.nodeValue;
        if (c.classList?.contains('mn')) {
            const [t, id] = (c.dataset.m || 'user:x').split(':');
            return `@[${t}:${id}|${c.querySelector('span')?.textContent || ''}]`;
        }
        switch (c.tagName) {
            case 'B': case 'STRONG': return '**' + inl(c) + '**';
            case 'I': case 'EM':     return '*' + inl(c) + '*';
            case 'CODE':             return '`' + inl(c) + '`';
            case 'A':                return '[' + inl(c) + '](' + (c.getAttribute('href') || '') + ')';
            case 'IMG':              return '![' + (c.alt || '') + '](' + (c.getAttribute('src') || '') + ')';
            case 'BR':               return '\n';
            default:                 return inl(c);
        }
    }).join('');

    const out = [];
    [...root.childNodes].forEach(n => {
        if (n.nodeType === 3) { const t = n.nodeValue.trim(); if (t) out.push(t); return; }
        switch (n.tagName) {
            case 'H1': case 'H2':  out.push('## ' + inl(n)); break;
            case 'H3': case 'H4':  out.push('### ' + inl(n)); break;
            case 'BLOCKQUOTE':     out.push(inl(n).split('\n').map(l => '> ' + l).join('\n')); break;
            case 'HR':             out.push('---'); break;
            case 'UL':             out.push([...n.children].map(li => '- ' + inl(li)).join('\n')); break;
            case 'OL':             out.push([...n.children].map((li, i) => (i + 1) + '. ' + inl(li)).join('\n')); break;
            case 'IMG':            out.push('![' + (n.alt || '') + '](' + (n.getAttribute('src') || '') + ')'); break;
            default:               out.push(inl(n));
        }
    });
    return out.map(s => s.trim()).filter(Boolean).join('\n\n');
}

const stripMd = md => md
    .replace(MENTION_RE, '$3')
    .replace(/^#{2,3}\s+(.*)$/gm, (_, t) => t.toUpperCase())
    .replace(/^>\s?/gm, '« ').replace(/^[-*]\s+/gm, '• ')
    .replace(/!\[[^\]]*\]\([^)]+\)/g, '')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1 — $2')
    .replace(/\*\*([^*]+)\*\*/g, '$1').replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1').replace(/^-{3,}$/gm, '—————')
    .replace(/\n{3,}/g, '\n\n').trim();

const mdToTg = md => md
    .replace(MENTION_RE, '$3')
    .replace(/^#{2,3}\s+(.*)$/gm, '<b>$1</b>')
    .replace(/^>\s?(.*)$/gm, '<blockquote>$1</blockquote>')
    .replace(/^[-*]\s+/gm, '• ')
    .replace(/!\[[^\]]*\]\([^)]+\)/g, '')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[\s(])\*([^*]+)\*/g, '$1<i>$2</i>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\n{3,}/g, '\n\n').trim();

/** Первая упомянутая игра — она и станет баннером поста. */
function findGame(text) {
    const m = /@\[game:([\w\-]+)\|([^\]]+)\]/u.exec(text);
    if (!m) return null;
    return (DATA.entities.game || []).find(g => g.id === m[1]) || { id: m[1], name: m[2] };
}

/* ======================================================================
   2. @АВТОКОМПЛИТ
   Работает и в textarea, и в contenteditable — разница только в том,
   как прочитать текст перед кареткой и как вставить результат.
   ====================================================================== */
const mnbox = $('#mnbox');
let mnCtx = null, mnItems = [], mnSel = 0;

const TYPE_LABEL = { game: 'Игры', user: 'Люди', studio: 'Студии', space: 'Spaces' };

function searchEntities(q) {
    const out = [];
    const needle = q.toLowerCase();
    ['game', 'user', 'studio', 'space'].forEach(type => {
        (DATA.entities[type] || []).forEach(en => {
            if (!needle || en.name.toLowerCase().includes(needle) || en.id.includes(needle))
                out.push({ type, ...en });
        });
    });
    return out.slice(0, 8);
}

function renderMnbox() {
    let last = null;
    mnbox.innerHTML = mnItems.map((it, i) => {
        const head = it.type !== last ? `<div class="mnbox__h">${TYPE_LABEL[it.type]}</div>` : '';
        last = it.type;
        return head + `<button class="${i === mnSel ? 'sel' : ''}" data-i="${i}">
            ${svg(MICON[it.type] || 'user')}<span>${it.name}</span>
            ${it.rating ? `<small>★ ${String(it.rating).replace('.', ',')}</small>` : ''}</button>`;
    }).join('');
}

function closeMn() { mnbox.classList.remove('open'); mnCtx = null; }

function openMn(ctx, q, rect) {
    mnItems = searchEntities(q);
    if (!mnItems.length) return closeMn();
    mnCtx = ctx; mnSel = 0;
    renderMnbox();
    mnbox.style.left = Math.max(10, Math.min(rect.left, innerWidth - 282)) + 'px';
    const below = rect.bottom + 6;
    mnbox.classList.add('open');
    mnbox.style.top = (below + mnbox.offsetHeight > innerHeight - 10 ? rect.top - mnbox.offsetHeight - 6 : below) + 'px';
}

/** Ищем незавершённое @слово прямо перед кареткой. */
function detectQuery(text, caret) {
    const before = text.slice(0, caret);
    const m = /(^|[\s(])@([\wА-Яа-яЁё.\-]{0,24})$/u.exec(before);
    return m ? { q: m[2], start: caret - m[2].length - 1 } : null;
}

function caretRectTextarea(ta) {
    /* приблизительно: коробка под полем — предсказуемо и не прыгает */
    const r = ta.getBoundingClientRect();
    return { left: r.left + 6, top: r.top, bottom: Math.min(r.bottom, innerHeight - 40) };
}

function hookTextarea(ta, onInsert) {
    ta.addEventListener('input', () => {
        const d = detectQuery(ta.value, ta.selectionStart);
        if (!d) return closeMn();
        openMn({ el: ta, kind: 'ta', ...d }, d.q, caretRectTextarea(ta));
    });
    ta.addEventListener('blur', () => setTimeout(closeMn, 150));
    ta._onInsert = onInsert;
}

function insertMention(it) {
    if (!mnCtx) return;
    const token = `@[${it.type}:${it.id}|${it.name}]`;

    if (mnCtx.kind === 'ta') {
        const ta = mnCtx.el, v = ta.value;
        const end = ta.selectionStart;
        ta.value = v.slice(0, mnCtx.start) + token + ' ' + v.slice(end);
        const pos = mnCtx.start + token.length + 1;
        ta.focus(); ta.setSelectionRange(pos, pos);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
        /* contenteditable: удаляем набранное "@q" и ставим чип-узел */
        const sel = getSelection(), range = sel.getRangeAt(0);
        const node = range.startContainer;
        const off = range.startOffset;
        range.setStart(node, Math.max(0, off - mnCtx.q.length - 1));
        range.deleteContents();
        const wrap = document.createElement('span');
        wrap.innerHTML = mentionHTML(it.type, it.id, it.name, true) + '&nbsp;';
        const frag = document.createDocumentFragment();
        while (wrap.firstChild) frag.appendChild(wrap.firstChild);
        const lastNode = frag.lastChild;
        range.insertNode(frag);
        range.setStartAfter(lastNode); range.collapse(true);
        sel.removeAllRanges(); sel.addRange(range);
        wys.dispatchEvent(new Event('input', { bubbles: true }));
    }
    closeMn(); buzz(8);
}

mnbox.addEventListener('mousedown', e => {
    e.preventDefault();
    const b = e.target.closest('button'); if (b) insertMention(mnItems[+b.dataset.i]);
});

addEventListener('keydown', e => {
    if (!mnbox.classList.contains('open')) return;
    if (e.key === 'ArrowDown') { mnSel = (mnSel + 1) % mnItems.length; renderMnbox(); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { mnSel = (mnSel - 1 + mnItems.length) % mnItems.length; renderMnbox(); e.preventDefault(); }
    else if (e.key === 'Enter' || e.key === 'Tab') { insertMention(mnItems[mnSel]); e.preventDefault(); }
    else if (e.key === 'Escape') closeMn();
}, true);

/* ======================================================================
   3. ЧЕРНОВИКИ В COOKIE
   Кука мала (~4 КБ), поэтому в ней лежит индекс и тела, пока влезают;
   вытесненные тела уходят в localStorage. Точка замены на API — одна.
   ====================================================================== */
const CK = 'dfid_drafts', CK_MAX = 3500;

const readCk = n => (document.cookie.match(new RegExp('(^|; )' + n + '=([^;]*)')) || [])[2];
const writeCk = (n, v) => { document.cookie = `${n}=${encodeURIComponent(v)};path=/;max-age=${90 * 86400};samesite=lax`; };

const Drafts = {
    all() { try { return JSON.parse(decodeURIComponent(readCk(CK) || '[]')); } catch (_) { return []; } },

    persist(arr) {
        arr.sort((a, b) => b.updated - a.updated);
        const copy = arr.map(d => ({ ...d }));
        let s = JSON.stringify(copy);
        /* тела вытесняем с самых старых, пока кука не влезет */
        for (let i = copy.length - 1; i >= 0 && encodeURIComponent(s).length > CK_MAX; i--) {
            if (copy[i].body != null) {
                try { localStorage.setItem('dfid_body_' + copy[i].id, copy[i].body); } catch (_) {}
                delete copy[i].body; copy[i].ext = 1;
                s = JSON.stringify(copy);
            }
        }
        writeCk(CK, s);
        return encodeURIComponent(s).length <= CK_MAX;
    },

    save(state) {
        const arr = this.all();
        const id = state.id || Date.now().toString(36);
        const g = findGame(state.title + ' ' + state.md);
        const rec = {
            id, title: state.title, updated: Date.now(),
            words: (state.md.trim().match(/[^\s]+/g) || []).length,
            game: g ? g.name : '',
            body: JSON.stringify({ md: state.md, target: state.target, tags: state.tags, card: state.card,
                                   slug: state.slug, seoTitle: state.seoTitle, seoDesc: state.seoDesc }),
        };
        const i = arr.findIndex(d => d.id === id);
        if (i >= 0) arr[i] = rec; else arr.unshift(rec);
        return { id, inCookie: this.persist(arr.slice(0, 20)) };
    },

    body(id) {
        const d = this.all().find(x => x.id === id);
        if (!d) return null;
        if (d.body != null) return JSON.parse(d.body);
        try { return JSON.parse(localStorage.getItem('dfid_body_' + id) || 'null'); } catch (_) { return null; }
    },

    remove(id) {
        this.persist(this.all().filter(d => d.id !== id));
        try { localStorage.removeItem('dfid_body_' + id); } catch (_) {}
    },

    list() {
        const ago = t => {
            const m = Math.round((Date.now() - t) / 60000);
            if (m < 1) return 'только что';
            if (m < 60) return m + ' мин назад';
            if (m < 1440) return Math.round(m / 60) + ' ч назад';
            return Math.round(m / 1440) + ' дн назад';
        };
        return this.all().map(d => ({
            ...d, ago: ago(d.updated),
            /* в списке упоминания показываем чипами, а не сырым @[...] */
            titleHTML: esc(d.title || '').replace(MENTION_RE, (_, t, id, n) => mentionHTML(t, id, n, false)),
        }));
    },
};
window.dfidDrafts = Drafts;

/* ======================================================================
   4. КОМПОЗЕР В ЛЕНТЕ
   ====================================================================== */
const cc = $('#composer');
const ccField = $('.cc__field', cc), ccSuggest = $('[data-suggest]', cc);
const ccCount = $('.cc__count', cc), ccSubmit = $('.cc__submit', cc), ccGameTag = $('[data-gametag]', cc);

const wordCount = s => (s.trim().match(/[^\s]+/g) || []).length;

function ccSync() {
    ccField.style.height = 'auto';
    ccField.style.height = Math.min(ccField.scrollHeight, 320) + 'px';

    const v = ccField.value, w = wordCount(v);
    ccCount.textContent = v.length || '0';
    ccCount.classList.toggle('warn', v.length > 900);
    ccSubmit.disabled = !v.trim();

    const g = findGame(v);
    ccGameTag.classList.toggle('hidden', !g);
    if (g) ccGameTag.querySelector('b').textContent = g.name;

    const long = w >= LONG_WORDS || v.length > 800 || (v.match(/\n\s*\n/g) || []).length >= 3;
    ccSuggest.classList.toggle('hidden', !long);
    if (long) $('.cc__words', ccSuggest).textContent = w;
}
ccField.addEventListener('input', ccSync);
hookTextarea(ccField);

ccSubmit.addEventListener('click', () => {
    toast('Пост опубликован на стене: ' + targetById(S.target).name);
    ccField.value = ''; ccSync(); buzz(12);
});

/* выбор адресата */
const picker = document.createElement('div');
picker.className = 'menu'; picker.id = 'picker';
document.body.appendChild(picker);
const scrim = $('#scrim');
const targetById = id => DATA.targets.find(t => t.id === id) || DATA.targets[0] || { name: '—' };

cc.addEventListener('click', e => {
    const p = e.target.closest('[data-pick]'); if (!p) return;
    picker.innerHTML = DATA.targets.map(t =>
        `<button data-v="${t.id}">${svg(t.type === 'user' ? 'user' : t.type === 'studio' ? 'studio' : 'grid')}
          <span>${t.name}<br><small style="color:var(--muted);font-size:11px">${t.role}</small></span></button>`).join('');
    if (!matchMedia('(hover:none)').matches) {
        const r = p.getBoundingClientRect();
        picker.style.top = (r.bottom + 8) + 'px';
        picker.style.left = Math.max(12, Math.min(r.left, innerWidth - 250)) + 'px';
    }
    picker.classList.add('open'); scrim.classList.add('open'); buzz(8);
});
const closePicker = () => { picker.classList.remove('open'); scrim.classList.remove('open'); };
scrim.addEventListener('click', closePicker);
picker.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    S.target = b.dataset.v;
    $('.cc__tname', cc).textContent = targetById(S.target).name;
    $$('[data-edtarget] button').forEach(x => x.classList.toggle('on', x.dataset.tid === S.target));
    closePicker();
});

/* ======================================================================
   5. РЕДАКТОР СТАТЬИ
   ====================================================================== */
const ed = $('#ed');
const edTitle = $('[data-edtitle]'), wys = $('[data-wys]'), mdArea = $('[data-md-area]');
const stats = $('[data-stats]'), saved = $('[data-saved]'), autogame = $('[data-autogame]');
wys.dataset.ph = 'Начните писать. ## — подзаголовок, > — цитата, @ — упоминание.';

function resetDraft() { Object.assign(S, { id: null, title: '', md: '', tags: '', cover: '', slug: '', seoTitle: '', seoDesc: '' }); }

function openEditor(seedText, fresh) {
    if (fresh) resetDraft();
    if (seedText != null && seedText.trim()) {
        const lines = seedText.trim().split('\n');
        if (lines.length > 1 && lines[0].length < 90) { S.title = lines.shift().trim(); S.md = lines.join('\n').trim(); }
        else S.md = seedText.trim();
    }
    edTitle.value = S.title;
    $('[data-edtags]').value = S.tags;
    setMode(S.mode, true);
    ed.classList.add('open');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => { ed.classList.add('in'); grow(edTitle); });
    syncAll();
}
function closeEditor() { ed.classList.remove('in', 'open', 'side-open'); document.body.style.overflow = ''; }

document.addEventListener('click', e => {
    const te = e.target.closest('[data-toeditor]');
    if (te) {
        const fresh = te.classList.contains('new') || te.classList.contains('plus');
        openEditor(fresh ? null : ccField.value, fresh);
        return;
    }
    if (e.target.closest('[data-edclose]')) {
        if (S.title.trim() || S.md.trim()) { save(); toast('Черновик сохранён'); }
        closeEditor(); window.dfid?.renderDrafts(); return;
    }
    if (e.target.closest('[data-edopts]')) { ed.classList.toggle('side-open'); buzz(8); return; }
    if (e.target.closest('[data-opendrafts]') && ed.classList.contains('open')) { save(); closeEditor(); }
});
addEventListener('keydown', e => { if (e.key === 'Escape' && ed.classList.contains('open') && !mnbox.classList.contains('open')) { save(); closeEditor(); } });

function openDraft(id) {
    const b = Drafts.body(id);
    if (!b) return toast('Черновик не найден');
    const meta = Drafts.all().find(d => d.id === id);
    Object.assign(S, b, { id, title: meta.title });
    S.mode = 'wys';
    openEditor(null, false);
    toast('Черновик открыт');
}

/* --- режимы --- */
function pullMd() { return S.mode === 'md' ? mdArea.value : htmlToMd(wys); }
function setMode(mode, initial) {
    if (!initial) S.md = pullMd();
    S.mode = mode;
    $$('[data-mode]').forEach(b => b.classList.toggle('on', b.dataset.mode === mode));
    wys.classList.toggle('hidden', mode !== 'wys');
    mdArea.classList.toggle('hidden', mode !== 'md');
    if (mode === 'wys') wys.innerHTML = mdToHtml(S.md, true);
    else { mdArea.value = S.md; grow(mdArea); }
}
$$('[data-mode]').forEach(b => b.addEventListener('click', () => { setMode(b.dataset.mode); buzz(8); }));

/* --- панель инструментов --- */
const MD_WRAP = {
    b: ['**', '**'], i: ['*', '*'], code: ['`', '`'],
    h2: ['## ', ''], h3: ['### ', ''], quote: ['> ', ''],
    ul: ['- ', ''], ol: ['1. ', ''], hr: ['\n---\n', ''],
    link: ['[', '](https://)'], img: ['![подпись](', ')'], at: ['@', ''],
};
const EXEC = { b: 'bold', i: 'italic', h2: ['formatBlock', 'h2'], h3: ['formatBlock', 'h3'],
               quote: ['formatBlock', 'blockquote'], ul: 'insertUnorderedList', ol: 'insertOrderedList',
               hr: 'insertHorizontalRule' };

const toolbar = $('[data-toolbar]');
toolbar.addEventListener('mousedown', e => e.preventDefault());
toolbar.addEventListener('click', e => {
    const b = e.target.closest('[data-md]'); if (!b) return;
    const k = b.dataset.md;

    if (S.mode === 'md') {
        const [l, r] = MD_WRAP[k];
        const s = mdArea.selectionStart, en = mdArea.selectionEnd, v = mdArea.value;
        mdArea.value = v.slice(0, s) + l + v.slice(s, en) + r + v.slice(en);
        mdArea.focus(); mdArea.setSelectionRange(s + l.length, en + l.length);
        if (k === 'at') mdArea.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
        wys.focus();
        if (k === 'at')        document.execCommand('insertText', false, '@');
        else if (k === 'link') document.execCommand('createLink', false, 'https://');
        else if (k === 'img')  document.execCommand('insertHTML', false, '<div class="ph"></div>');
        else if (k === 'code') document.execCommand('insertHTML', false, '<code>' + (getSelection().toString() || 'код') + '</code>');
        else if (Array.isArray(EXEC[k])) document.execCommand(EXEC[k][0], false, EXEC[k][1]);
        else document.execCommand(EXEC[k]);
    }
    syncAll();
});

/* @ в визуальном режиме */
wys.addEventListener('input', () => {
    const sel = getSelection();
    if (sel.rangeCount) {
        const r = sel.getRangeAt(0);
        if (r.startContainer.nodeType === 3) {
            const d = detectQuery(r.startContainer.nodeValue, r.startOffset);
            if (d) { const rect = r.getBoundingClientRect(); openMn({ kind: 'ce', q: d.q }, d.q, rect.width ? rect : wys.getBoundingClientRect()); }
            else closeMn();
        }
    }
    syncAll();
});
hookTextarea(mdArea);
hookTextarea(edTitle);

/* ======================================================================
   6. ПАРАМЕТРЫ
   ====================================================================== */
$$('.ed__tabs button').forEach(b => b.addEventListener('click', () => {
    $$('.ed__tabs button').forEach(x => x.classList.toggle('on', x === b));
    $$('[data-pane-body]').forEach(p => p.classList.toggle('hidden', p.dataset.paneBody !== b.dataset.pane));
}));
$$('[data-edtarget] button').forEach(b => b.addEventListener('click', () => {
    S.target = b.dataset.tid;
    $$('[data-edtarget] button').forEach(x => x.classList.toggle('on', x === b));
    $('.cc__tname', cc).textContent = targetById(S.target).name;
}));
$$('[data-edcard] button').forEach(b => b.addEventListener('click', () => {
    S.card = b.dataset.card;
    $$('[data-edcard] button').forEach(x => x.classList.toggle('on', x === b));
    renderCardPreview();
}));

const drop = $('[data-drop]');
drop.addEventListener('click', () => {
    const i = document.createElement('input'); i.type = 'file'; i.accept = 'image/*';
    i.onchange = () => i.files[0] && readCover(i.files[0]);
    i.click();
});
['dragover', 'dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => {
    e.preventDefault(); drop.classList.toggle('over', ev === 'dragover');
    if (ev === 'drop' && e.dataTransfer.files[0]) readCover(e.dataTransfer.files[0]);
}));
function readCover(f) {
    const r = new FileReader();
    r.onload = () => { S.cover = r.result; drop.classList.add('filled'); drop.style.backgroundImage = `url(${r.result})`; drop.textContent = f.name; renderCardPreview(); };
    r.readAsDataURL(f);
}

$('[data-edtags]').addEventListener('input', e => {
    S.tags = e.target.value;
    $('[data-chips]').innerHTML = e.target.value.split(',').map(t => t.trim()).filter(Boolean)
        .slice(0, 8).map(t => `<span>#${t}</span>`).join('');
});

/* --- SEO --- */
const TRANS = { а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'e',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',
                р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'c',ч:'ch',ш:'sh',щ:'sch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya' };
const slugify = s => stripMd(s).toLowerCase().split('').map(c => TRANS[c] ?? c).join('')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70);

const seoTitle = $('[data-edseotitle]'), seoDesc = $('[data-edseodesc]'), slugInp = $('[data-edslug]');
let slugTouched = false;
slugInp.addEventListener('input', () => { slugTouched = true; S.slug = slugify(slugInp.value); syncSeo(); });
seoTitle.addEventListener('input', () => { S.seoTitle = seoTitle.value; syncSeo(); });
seoDesc.addEventListener('input', () => { S.seoDesc = seoDesc.value; syncSeo(); });

function counter(el, len, max) {
    el.textContent = len + '/' + max;
    el.className = 'cnt' + (len > max ? ' bad' : len > max * .85 ? ' warn' : '');
}
function syncSeo() {
    if (!slugTouched) { S.slug = slugify(S.title); slugInp.value = S.slug; }
    const t = S.seoTitle || stripMd(S.title) || 'Заголовок статьи';
    const d = S.seoDesc || firstParagraph() || 'Описание появится здесь.';
    counter($('[data-cnt="title"]'), t.length, 60);
    counter($('[data-cnt="desc"]'), d.length, 160);
    $('[data-snipslug]').textContent = S.slug || '…';
    $('[data-snipt]').textContent = t;
    $('[data-snipd]').textContent = d;
}
const firstParagraph = () => stripMd(
    (S.md.split(/\n\s*\n/).find(p => p.trim() && !/^[#>\-*\d]/.test(p.trim())) || '')
).trim().slice(0, 200);

function renderCardPreview() {
    const box = $('[data-cardprev]');
    const img = S.card === 'text' ? '' :
        `<div class="cardprev__img" style="${S.cover ? `background-image:url(${S.cover})` : ''}"></div>`;
    box.className = 'cardprev' + (S.card === 'small' ? ' cardprev--small' : '');
    box.innerHTML = img + `<div class="cardprev__b">
        <div class="cardprev__t">${esc(stripMd(S.title) || 'Заголовок статьи')}</div>
        <div class="cardprev__d">${esc(firstParagraph() || 'Первый абзац станет анонсом в ленте.')}</div></div>`;
}

/* --- кросспостинг --- */
const PLATS = [
    { id: 'tg',     name: 'Telegram',  limit: 4096 },
    { id: 'vk',     name: 'ВКонтакте', limit: 16000 },
    { id: 'dtf',    name: 'DTF',       limit: 0 },
    { id: 'habr',   name: 'Habr',      limit: 0 },
    { id: 'boosty', name: 'Boosty',    limit: 0 },
];
PLATS.forEach(p => S.plats[p.id] = { on: p.id === 'tg', mode: 'full' });

function renderPlats() {
    $('[data-plats]').innerHTML = PLATS.map(p => {
        const st = S.plats[p.id];
        return `<div class="plat${st.on ? ' on' : ''}" data-plat="${p.id}">
          <div class="plat__top">${svg('share', 'ic--sm')}${p.name}
            <span class="plat__lim">${p.limit ? '≤ ' + p.limit.toLocaleString('ru-RU') : 'без лимита'}</span></div>
          <div class="plat__modes">
            <button data-pmode="full" class="${st.mode === 'full' ? 'on' : ''}">Полный текст</button>
            <button data-pmode="teaser" class="${st.mode === 'teaser' ? 'on' : ''}">Анонс + ссылка</button>
          </div></div>`;
    }).join('');
}
$('[data-plats]').addEventListener('click', e => {
    const m = e.target.closest('[data-pmode]');
    const p = e.target.closest('[data-plat]'); if (!p) return;
    const st = S.plats[p.dataset.plat];
    if (m) st.mode = m.dataset.pmode; else st.on = !st.on;
    S.plat = p.dataset.plat;
    renderPlats(); renderCross(); buzz(8);
});

function adapt(id) {
    const url = 'https://dustore.ru/fid/' + (S.slug || 'post');
    const st = S.plats[id], title = stripMd(S.title) || 'Без заголовка';
    if (st.mode === 'teaser') return `${title}\n\n${firstParagraph()}\n\nЧитать целиком → ${url}`;
    if (id === 'tg')   return `<b>${title}</b>\n\n` + mdToTg(S.md) + `\n\n<a href="${url}">Оригинал на Dustore</a>`;
    if (id === 'vk')   return `${title.toUpperCase()}\n\n` + stripMd(S.md) + `\n\nОригинал: ${url}`;
    if (id === 'habr') return `<h2>${title}</h2>\n` + mdToHtml(S.md, false).replace(/></g, '>\n<');
    return `# ${title}\n\n${S.md}\n\n*Оригинал: ${url}*`;
}
function renderCross() {
    const out = adapt(S.plat), p = PLATS.find(x => x.id === S.plat);
    $('[data-crossout]').value = out;
    const over = p.limit && out.length > p.limit;
    $('[data-crosslimit]').innerHTML = `${p.name}: ${out.length.toLocaleString('ru-RU')}` +
        (p.limit ? ` / ${p.limit.toLocaleString('ru-RU')}` : '') +
        (over ? ' <b style="color:var(--red)">— не влезает, используйте анонс</b>' : '');
}
$('[data-crosscopy]').addEventListener('click', () => {
    navigator.clipboard?.writeText($('[data-crossout]').value);
    toast('Текст для ' + PLATS.find(x => x.id === S.plat).name + ' скопирован');
    buzz(10);
});

/* ======================================================================
   7. СИНХРОНИЗАЦИЯ И АВТОСОХРАНЕНИЕ
   ====================================================================== */
const grow = el => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };

function syncAll() {
    S.title = edTitle.value;
    S.md = pullMd();

    const chars = S.md.length, words = wordCount(S.md);
    stats.textContent = `${chars.toLocaleString('ru-RU')} знаков · ${words} слов · ~${Math.max(1, Math.round(words / 180))} мин чтения`;

    const g = findGame(S.title + ' ' + S.md);
    autogame.classList.toggle('on', !!g);
    autogame.querySelector('span').textContent = g
        ? `Баннер игры: ${g.name}` + (g.rating ? ` · ★ ${String(g.rating).replace('.', ',')}` : '')
        : 'Не найдена — упомяните игру через @ в заголовке';

    syncSeo(); renderCardPreview(); renderCross();
    scheduleSave();
}
[edTitle, mdArea].forEach(el => el.addEventListener('input', syncAll));
edTitle.addEventListener('input', () => grow(edTitle));
mdArea.addEventListener('input', () => grow(mdArea));

let saveT;
const scheduleSave = () => { clearTimeout(saveT); saveT = setTimeout(save, 1200); };
function save() {
    if (!S.title.trim() && !S.md.trim()) return;
    const { id, inCookie } = Drafts.save(S);
    S.id = id;
    saved.textContent = (inCookie ? 'черновик в cookie · ' : 'сохранён (тело в localStorage) · ') +
        new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    saved.classList.add('flash'); setTimeout(() => saved.classList.remove('flash'), 700);
    window.dfid?.updateDraftCount(Drafts.all().length);
}

$('[data-edpublish]').addEventListener('click', () => {
    if (!S.title.trim()) { toast('Добавьте заголовок'); edTitle.focus(); return; }
    const active = PLATS.filter(p => S.plats[p.id].on).map(p => p.name);
    if (S.id) Drafts.remove(S.id);
    closeEditor(); resetDraft();
    window.dfid?.updateDraftCount(Drafts.all().length);
    toast('Статья опубликована на стене: ' + targetById(S.target).name +
          (active.length ? ' · кросспостинг: ' + active.join(', ') : ''));
    buzz(16);
});

/* свайп вниз по «ручке» закрывает панель параметров */
let gy = 0;
$('.ed__grip').addEventListener('touchstart', e => gy = e.touches[0].clientY, { passive: true });
$('.ed__grip').addEventListener('touchend', e => {
    if (e.changedTouches[0].clientY - gy > 40) ed.classList.remove('side-open');
}, { passive: true });

/* стартовая отрисовка */
renderPlats(); ccSync(); syncSeo(); renderCardPreview(); renderCross();
window.dfid?.updateDraftCount(Drafts.all().length);
window.dfidEditor = { open: openEditor, openDraft, state: S };
})();