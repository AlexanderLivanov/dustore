<?php
require_once __DIR__ . '/lib/core.php';

$art = null;
if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $st->execute([(int)$_GET['id']]);
    $art = $st->fetch() ?: null;
} elseif (isset($_GET['slug'])) {
    $art = article_by_slug((string)$_GET['slug']);
}
if (!$art) { header('Location: index.php'); exit; }

$id        = (int)$art['id'];
$sections  = db()->query('SELECT s.id, s.title, s.parent_id FROM sections s ORDER BY s.position, s.title')->fetchAll();
$secTitles = [];
foreach ($sections as $s) $secTitles[$s['id']] = $s['title'];
$tags      = array_column(article_tags($id), 'title');
$questions = article_questions($id);
$boot = [
    'id'         => $id,
    'slug'       => $art['slug'],
    'body'       => $art['body_md'],
    'linksOut'   => article_links_out($id),
    'linksIn'    => article_links_in($id),
    'questions'  => $questions,
    'linkLabels' => LINK_LABELS,
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($art['title']) ?> — редактор GDDB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/ui.css">
<style>
body{overflow:hidden}
.ed{display:grid;grid-template-columns:1fr var(--panel,348px);grid-template-rows:48px 1fr;height:100vh}
.ed.panel-off{--panel:0px}

/* --- верхняя полоса --- */
.ed-top{grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:0 14px;
  background:var(--bg2);border-bottom:1px solid var(--border)}
.ed-top .sp{flex:1}
.save-ind{font-family:var(--mono);font-size:11px;color:var(--muted);min-width:118px;text-align:right}
.save-ind.dirty{color:var(--amber)}
.save-ind.err{color:var(--red)}
.kbd{font-family:var(--mono);font-size:10px;color:var(--text3);border:1px solid var(--border2);
  border-radius:4px;padding:1px 4px}

/* --- рабочая область --- */
.ed-main{overflow:hidden;display:grid;grid-template-columns:1fr;min-width:0}
.ed-main.split{grid-template-columns:1fr 1fr}
.ed-main.split .ed-prev{display:block;border-left:1px solid var(--border)}
.ed-main.only-prev{grid-template-columns:1fr}
.ed-main.only-prev .ed-write{display:none}
.ed-main.only-prev .ed-prev{display:block}
.ed-prev{display:none;overflow-y:auto;padding:40px 48px 120px}
.ed-prev .prose{margin:0 auto}

.ed-write{display:flex;flex-direction:column;overflow:hidden;min-width:0}
.ed-inner{flex:1;display:flex;flex-direction:column;overflow-y:auto;padding:36px 0 160px}
.ed-col{width:100%;max-width:760px;margin:0 auto;padding:0 30px;display:flex;flex-direction:column;flex:1}
.ed-title{font-family:var(--sans);font-size:30px;font-weight:800;letter-spacing:-.7px;line-height:1.2;
  color:var(--text);background:transparent;border:0;outline:none;width:100%;margin-bottom:6px}
.ed-title::placeholder{color:var(--text3)}
.ed-slug{font-family:var(--mono);font-size:11px;color:var(--text3);margin-bottom:22px}
.ed-body{flex:1;min-height:60vh;font-family:var(--mono);font-size:14px;line-height:1.75;color:#DCD8D1;
  background:transparent;border:0;outline:none;resize:none;width:100%;tab-size:2}
.ed-body::placeholder{color:var(--text3)}

/* --- правая панель --- */
.ed-panel{background:var(--bg2);border-left:1px solid var(--border);overflow-y:auto;overflow-x:hidden}
.ed.panel-off .ed-panel{display:none}
.pn{border-bottom:1px solid var(--border)}
.pn>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:11px 14px;
  font-family:var(--mono);font-size:10.5px;text-transform:uppercase;letter-spacing:.11em;color:var(--text2);user-select:none}
.pn>summary::-webkit-details-marker{display:none}
.pn>summary:hover{color:var(--text);background:var(--bg3)}
.pn>summary .cnt{margin-left:auto;font-size:10px;color:var(--text3)}
.pn>summary::before{content:'▸';font-size:9px;color:var(--text3);transition:.15s}
.pn[open]>summary::before{transform:rotate(90deg)}
.pn-body{padding:2px 14px 16px;display:flex;flex-direction:column;gap:12px}

#conf{flex-wrap:wrap}
#conf button{flex:0 0 calc(50% - 2px)}
.lk{display:flex;align-items:center;gap:6px;font-size:12.5px;padding:4px 0}
.lk a{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)}
.lk a:hover{color:var(--amber)}
.lk .auto{font-family:var(--mono);font-size:9px;color:var(--text3);border:1px solid var(--border2);border-radius:3px;padding:0 3px}
.lk .x{cursor:pointer;color:var(--text3);font-size:13px}
.lk .x:hover{color:var(--red)}
.lk-group{font-family:var(--mono);font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;
  color:var(--amber-dim);margin-top:6px}
.results{display:flex;flex-direction:column;gap:2px;max-height:190px;overflow-y:auto}
.results button{text-align:left;font-family:var(--sans);font-size:12.5px;color:var(--text);background:var(--bg3);
  border:1px solid var(--border);border-radius:6px;padding:6px 9px;cursor:pointer}
.results button:hover{border-color:var(--amber-dim)}
.results button small{color:var(--text3);font-family:var(--mono);font-size:10px}

.tagbox{display:flex;flex-wrap:wrap;gap:5px;align-items:center;background:var(--bg2);border:1px solid var(--border2);
  border-radius:var(--r);padding:6px}
.tagbox input{flex:1;min-width:80px;background:transparent;border:0;outline:none;color:var(--text);font-size:12px;font-family:var(--mono)}

.q{display:flex;gap:7px;align-items:flex-start;font-size:12.5px;line-height:1.45;padding:5px 0}
.q .box{flex:none;width:14px;height:14px;margin-top:2px;border:1px solid var(--border2);border-radius:3px;
  cursor:pointer;display:grid;place-items:center;font-size:10px;color:var(--green)}
.q.done .txt{color:var(--text3);text-decoration:line-through}
.q .txt{flex:1}
.q .x{cursor:pointer;color:var(--text3)}
.q .x:hover{color:var(--red)}

.outline a{display:block;font-size:12px;color:var(--text2);padding:3px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.outline a:hover{color:var(--amber)}
.outline a[data-l="2"]{padding-left:12px;font-size:11.5px}
.outline a[data-l="3"]{padding-left:24px;font-size:11px;color:var(--text3)}

.rev{display:flex;align-items:center;gap:8px;font-size:12px;padding:5px 0;cursor:pointer;border-bottom:1px solid var(--border)}
.rev:hover{color:var(--amber)}
.rev .t{font-family:var(--mono);font-size:11px;color:var(--text2)}
.rev .l{margin-left:auto;font-family:var(--mono);font-size:10px;color:var(--text3)}

/* --- модалка ревизии --- */
.ov{position:fixed;inset:0;background:rgba(8,8,10,.8);backdrop-filter:blur(3px);z-index:200;display:none;
  align-items:center;justify-content:center;padding:40px}
.ov.on{display:flex}
.ov-box{background:var(--bg2);border:1px solid var(--border2);border-radius:12px;width:min(860px,100%);
  max-height:100%;display:flex;flex-direction:column;overflow:hidden}
.ov-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border)}
.ov-head b{font-size:14px}
.ov-body{overflow-y:auto;padding:18px;font-family:var(--mono);font-size:12.5px;line-height:1.7;
  white-space:pre-wrap;color:#CFCBC4}

.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--bg3);
  border:1px solid var(--border2);border-radius:var(--r);padding:9px 16px;font-size:12.5px;z-index:300;
  opacity:0;transition:.2s;pointer-events:none}
.toast.on{opacity:1}
.toast b{color:var(--amber)}

@media(max-width:1100px){.ed{grid-template-columns:1fr var(--panel,300px)}.ed-main.split{grid-template-columns:1fr}.ed-main.split .ed-prev{display:none}}
@media(max-width:720px){.ed{grid-template-columns:1fr}.ed-panel{display:none}.ed-col{padding:0 18px}}
</style>
</head>
<body>
<div class="ed" id="ed">

  <div class="ed-top">
    <a class="btn btn-ghost btn-sm" href="index.php">← Библиотека</a>
    <a class="btn btn-ghost btn-sm" href="note.php?slug=<?= e($art['slug']) ?>" target="_blank">Открыть</a>
    <div class="sp"></div>
    <div class="seg" id="modes" style="width:230px">
      <button data-mode="write"   aria-pressed="true">Текст</button>
      <button data-mode="split"   aria-pressed="false">Пополам</button>
      <button data-mode="preview" aria-pressed="false">Превью</button>
    </div>
    <span class="save-ind" id="ind">сохранено</span>
    <button class="btn btn-sm" id="saveBtn">Сохранить <span class="kbd">⌘S</span></button>
    <button class="btn btn-ghost icon-btn" id="togglePanel" title="Панель (⌘\)">◨</button>
  </div>

  <div class="ed-main" id="main">
    <div class="ed-write">
      <div class="ed-inner">
        <div class="ed-col">
          <input class="ed-title" id="title" value="<?= e($art['title']) ?>" placeholder="Название темы" spellcheck="false">
          <div class="ed-slug" id="slugLine">/<?= e($art['slug']) ?></div>
          <textarea class="ed-body" id="body" spellcheck="true" placeholder="Объясняй так, будто человек напротив не знает ничего. Ссылайся на другие темы через [[двойные скобки]] — если заметки нет, она создастся как заготовка."><?= e($art['body_md']) ?></textarea>
        </div>
      </div>
    </div>
    <div class="ed-prev" id="prevPane"><div class="prose" id="prev"></div></div>
  </div>

  <aside class="ed-panel">

    <details class="pn" open data-k="meta">
      <summary>Мета</summary>
      <div class="pn-body">
        <div>
          <label class="lbl" for="section">Раздел</label>
          <select class="sel" id="section">
            <option value="">— без раздела —</option>
            <?php foreach ($sections as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (int)$art['section_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                <?= $s['parent_id'] ? '　· ' : '' ?><?= e($s['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="lbl">Статус</label>
          <div class="seg" id="status">
            <?php foreach (STATUS_LABELS as $k => $v): ?>
              <button data-v="<?= $k ?>" aria-pressed="<?= $art['status'] === $k ? 'true' : 'false' ?>"><?= e($v) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="lbl">Могу объяснить</label>
          <div class="seg" id="conf">
            <?php foreach (CONFIDENCE_LABELS as $k => $v): ?>
              <button data-v="<?= $k ?>" aria-pressed="<?= (int)$art['confidence'] === $k ? 'true' : 'false' ?>"><?= e($v) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="hint">Ставь честно. Это карта того, где у тебя тонко.</div>
        </div>
        <div>
          <label class="lbl" for="summary">Одной строкой</label>
          <textarea class="ta" id="summary" style="min-height:56px" placeholder="Что читатель поймёт из этой заметки"><?= e($art['summary']) ?></textarea>
        </div>
      </div>
    </details>

    <details class="pn" open data-k="tags">
      <summary>Теги <span class="cnt" id="tagCnt"></span></summary>
      <div class="pn-body">
        <div class="tagbox" id="tagbox">
          <input id="tagInput" placeholder="тег + Enter" spellcheck="false">
        </div>
      </div>
    </details>

    <details class="pn" open data-k="links">
      <summary>Связи <span class="cnt" id="linkCnt"></span></summary>
      <div class="pn-body">
        <div id="linksOut"></div>
        <div>
          <label class="lbl">Добавить связь</label>
          <select class="sel" id="linkType" style="margin-bottom:6px">
            <?php foreach (LINK_LABELS as $k => $v): ?>
              <option value="<?= $k ?>"><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="inp" id="linkSearch" placeholder="найти заметку..." spellcheck="false">
          <div class="results" id="linkResults" style="margin-top:6px"></div>
        </div>
        <div>
          <label class="lbl">Ссылаются сюда</label>
          <div id="linksIn"></div>
        </div>
      </div>
    </details>

    <details class="pn" open data-k="questions">
      <summary>Вопросы <span class="cnt" id="qCnt"></span></summary>
      <div class="pn-body">
        <div id="qList"></div>
        <input class="inp" id="qInput" placeholder="чего я пока не понимаю? + Enter">
        <div class="hint">Открытые вопросы — твоя очередь на изучение.</div>
      </div>
    </details>

    <details class="pn" data-k="outline">
      <summary>Оглавление</summary>
      <div class="pn-body"><div class="outline" id="outline"><div class="empty">появится после превью</div></div></div>
    </details>

    <details class="pn" data-k="revs">
      <summary>Ревизии <span class="cnt" id="revCnt"></span></summary>
      <div class="pn-body">
        <div class="row">
          <input class="inp" id="revNote" placeholder="что изменилось">
          <button class="btn btn-sm" id="revSave">Версия</button>
        </div>
        <div id="revList"><div class="empty">загрузка…</div></div>
        <div class="hint">Через полгода сравнишь, как объяснял это сегодня.</div>
      </div>
    </details>

    <details class="pn" data-k="danger">
      <summary>Опасное</summary>
      <div class="pn-body">
        <button class="btn btn-sm btn-danger" id="delBtn">Удалить заметку</button>
      </div>
    </details>

  </aside>
</div>

<div class="ov" id="ov"><div class="ov-box">
  <div class="ov-head">
    <b id="ovTitle">Ревизия</b>
    <div class="sp" style="flex:1"></div>
    <button class="btn btn-sm" id="ovRestore">Вернуть в редактор</button>
    <button class="btn btn-ghost btn-sm" id="ovClose">Закрыть</button>
  </div>
  <div class="ov-body" id="ovBody"></div>
</div></div>

<div class="toast" id="toast"></div>

<script>
const BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;
const TAGS = new Set(<?= json_encode($tags, JSON_UNESCAPED_UNICODE) ?>);
const $ = s => document.querySelector(s);
const api = (action, data) => fetch('api.php?action=' + action, {
  method: 'POST', headers: {'Content-Type': 'application/json'},
  body: JSON.stringify(data || {})
}).then(r => r.json());

let dirty = false, timer = null, prevTimer = null, mode = 'write', linkTarget = null;

/* --- сохранение ------------------------------------------------------ */
function ind(text, cls) { const el = $('#ind'); el.textContent = text; el.className = 'save-ind ' + (cls || ''); }
function touch() { dirty = true; ind('не сохранено', 'dirty'); clearTimeout(timer); timer = setTimeout(save, 1500); }

function payload(extra) {
  return Object.assign({
    id: BOOT.id,
    title: $('#title').value,
    summary: $('#summary').value,
    body_md: $('#body').value,
    status: segVal('#status'),
    confidence: +segVal('#conf'),
    section_id: $('#section').value,
    tags: [...TAGS],
  }, extra || {});
}

async function save(extra) {
  clearTimeout(timer);
  ind('сохранение…');
  try {
    const r = await api('save', payload(extra));
    if (r.error) { ind(r.error, 'err'); return r; }
    dirty = false;
    ind('сохранено ' + r.saved_at);
    BOOT.slug = r.slug;
    $('#slugLine').textContent = '/' + r.slug;
    setSeg('#status', r.status);
    renderLinks(r.links);
    if (r.created_stubs && r.created_stubs.length) {
      toast('Создано заготовок: <b>' + r.created_stubs.map(s => s.title).join(', ') + '</b>');
    }
    if (r.snapshot) loadRevs();
    return r;
  } catch (err) { ind('ошибка сети', 'err'); }
}

/* --- сегменты -------------------------------------------------------- */
function segVal(sel) { return $(sel + ' button[aria-pressed="true"]').dataset.v; }
function setSeg(sel, v) {
  document.querySelectorAll(sel + ' button').forEach(b =>
    b.setAttribute('aria-pressed', String(b.dataset.v === String(v))));
}
document.querySelectorAll('#status,#conf').forEach(seg => seg.addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  setSeg('#' + seg.id, b.dataset.v); touch();
}));
$('#section').addEventListener('change', touch);
['#title', '#summary', '#body'].forEach(s => $(s).addEventListener('input', () => {
  touch(); if (s === '#body' && mode !== 'write') schedulePreview();
}));

/* --- режимы ---------------------------------------------------------- */
function setMode(m) {
  mode = m;
  document.querySelectorAll('#modes button').forEach(b =>
    b.setAttribute('aria-pressed', String(b.dataset.mode === m)));
  const main = $('#main');
  main.classList.toggle('split', m === 'split');
  main.classList.toggle('only-prev', m === 'preview');
  if (m !== 'write') schedulePreview(0);
}
$('#modes').addEventListener('click', e => {
  const b = e.target.closest('button'); if (b) setMode(b.dataset.mode);
});

function schedulePreview(ms) {
  clearTimeout(prevTimer);
  prevTimer = setTimeout(async () => {
    const r = await api('preview', {body_md: $('#body').value});
    $('#prev').innerHTML = r.html || '<div class="empty">пусто</div>';
    renderOutline(r.outline || []);
  }, ms === undefined ? 450 : ms);
}

function renderOutline(items) {
  $('#outline').innerHTML = items.length
    ? items.map(i => `<a data-l="${i.level}" href="#${i.anchor}">${esc(i.text)}</a>`).join('')
    : '<div class="empty">заголовков нет</div>';
}

/* --- панель ---------------------------------------------------------- */
$('#togglePanel').addEventListener('click', () => {
  const off = $('#ed').classList.toggle('panel-off');
  try { localStorage.setItem('gddb.panel', off ? '0' : '1'); } catch (e) {}
});
try { if (localStorage.getItem('gddb.panel') === '0') $('#ed').classList.add('panel-off'); } catch (e) {}
document.querySelectorAll('.pn').forEach(d => {
  const k = 'gddb.pn.' + d.dataset.k;
  try { const v = localStorage.getItem(k); if (v !== null) d.open = v === '1'; } catch (e) {}
  d.addEventListener('toggle', () => { try { localStorage.setItem(k, d.open ? '1' : '0'); } catch (e) {} });
});

/* --- теги ------------------------------------------------------------ */
function renderTags() {
  const box = $('#tagbox'), inp = $('#tagInput');
  [...box.querySelectorAll('.pill')].forEach(p => p.remove());
  [...TAGS].forEach(t => {
    const s = document.createElement('span');
    s.className = 'pill';
    s.innerHTML = '<b>#' + esc(t) + '</b><span class="x">✕</span>';
    s.querySelector('.x').onclick = () => { TAGS.delete(t); renderTags(); touch(); };
    box.insertBefore(s, inp);
  });
  $('#tagCnt').textContent = TAGS.size || '';
}
$('#tagInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const v = e.target.value.trim().replace(/^#/, '');
    if (v) { TAGS.add(v); e.target.value = ''; renderTags(); touch(); }
  } else if (e.key === 'Backspace' && !e.target.value && TAGS.size) {
    const last = [...TAGS].pop(); TAGS.delete(last); renderTags(); touch();
  }
});

/* --- связи ----------------------------------------------------------- */
function renderLinks(links) {
  BOOT.linksOut = links.out; BOOT.linksIn = links.in;
  const out = $('#linksOut');
  if (!links.out.length) out.innerHTML = '<div class="empty">связей пока нет</div>';
  else {
    let html = '', cur = '';
    links.out.forEach(l => {
      if (l.type !== cur) { cur = l.type; html += `<div class="lk-group">${esc(BOOT.linkLabels[cur])}</div>`; }
      html += `<div class="lk">
        <a href="editor.php?id=${l.id}">${esc(l.title)}</a>
        ${l.status === 'stub' ? '<span class="st st-stub">заготовка</span>' : ''}
        ${+l.auto ? '<span class="auto" title="из [[скобок]] в тексте">auto</span>' : ''}
        <span class="x" data-to="${l.id}" data-type="${l.type}">✕</span></div>`;
    });
    out.innerHTML = html;
    out.querySelectorAll('.x').forEach(x => x.onclick = async () => {
      renderLinks((await api('link_del', {from: BOOT.id, to: x.dataset.to, type: x.dataset.type})).links);
    });
  }
  $('#linksIn').innerHTML = links.in.length
    ? links.in.map(l => `<div class="lk"><a href="editor.php?id=${l.id}">${esc(l.title)}</a>
        <span class="muted mono" style="font-size:9.5px">${esc(BOOT.linkLabels[l.type])}</span></div>`).join('')
    : '<div class="empty">никто не ссылается</div>';
  $('#linkCnt').textContent = links.out.length + ' / ' + links.in.length;
}

let searchTimer = null;
$('#linkSearch').addEventListener('input', e => {
  clearTimeout(searchTimer);
  const q = e.target.value.trim();
  if (!q) { $('#linkResults').innerHTML = ''; return; }
  searchTimer = setTimeout(async () => {
    const r = await api('search', {q, not: BOOT.id});
    $('#linkResults').innerHTML = (r.items || []).map(i =>
      `<button data-id="${i.id}">${esc(i.title)} <small>${i.status === 'stub' ? 'заготовка' : ''}</small></button>`
    ).join('') || '<div class="empty">ничего не найдено</div>';
    $('#linkResults').querySelectorAll('button').forEach(b => b.onclick = async () => {
      const r2 = await api('link_add', {from: BOOT.id, to: b.dataset.id, type: $('#linkType').value});
      if (r2.error) return toast(r2.error);
      renderLinks(r2.links);
      $('#linkSearch').value = ''; $('#linkResults').innerHTML = '';
    });
  }, 220);
});

/* --- вопросы --------------------------------------------------------- */
function renderQ(list) {
  BOOT.questions = list;
  const open = list.filter(q => q.status === 'open').length;
  $('#qCnt').textContent = list.length ? open + ' открыт.' : '';
  $('#qList').innerHTML = list.length ? list.map(q => `
    <div class="q ${q.status === 'answered' ? 'done' : ''}">
      <span class="box" data-id="${q.id}">${q.status === 'answered' ? '✓' : ''}</span>
      <span class="txt">${esc(q.text)}</span>
      <span class="x" data-del="${q.id}">✕</span>
    </div>`).join('') : '<div class="empty">вопросов нет — подозрительно</div>';
  $('#qList').querySelectorAll('.box').forEach(b => b.onclick = async () =>
    renderQ((await api('question_toggle', {id: b.dataset.id})).questions));
  $('#qList').querySelectorAll('[data-del]').forEach(b => b.onclick = async () =>
    renderQ((await api('question_del', {id: b.dataset.del})).questions));
}
$('#qInput').addEventListener('keydown', async e => {
  if (e.key !== 'Enter' || !e.target.value.trim()) return;
  e.preventDefault();
  const r = await api('question_add', {article_id: BOOT.id, text: e.target.value.trim()});
  e.target.value = ''; renderQ(r.questions);
});

/* --- ревизии --------------------------------------------------------- */
async function loadRevs() {
  const r = await api('revisions', {id: BOOT.id});
  const items = r.items || [];
  $('#revCnt').textContent = items.length || '';
  $('#revList').innerHTML = items.length ? items.map(i => `
    <div class="rev" data-id="${i.id}">
      <span class="t">${i.created_at.slice(0, 16).replace('T', ' ')}</span>
      ${i.note ? '<span>' + esc(i.note) + '</span>' : ''}
      <span class="l">${i.len} зн.</span>
    </div>`).join('') : '<div class="empty">история пуста</div>';
  $('#revList').querySelectorAll('.rev').forEach(el => el.onclick = async () => {
    const g = await api('revision_get', {id: el.dataset.id});
    if (!g.item) return;
    $('#ovTitle').textContent = 'Ревизия от ' + g.item.created_at;
    $('#ovBody').textContent = g.item.body_md;
    $('#ovRestore').onclick = () => {
      $('#body').value = g.item.body_md;
      $('#ov').classList.remove('on'); touch();
      toast('Текст ревизии вставлен. Текущая версия уже в истории.');
    };
    $('#ov').classList.add('on');
  });
}
$('#ovClose').onclick = () => $('#ov').classList.remove('on');
$('#ov').onclick = e => { if (e.target === $('#ov')) $('#ov').classList.remove('on'); };
$('#revSave').onclick = async () => {
  await save({snapshot_note: $('#revNote').value.trim() || 'вручную'});
  $('#revNote').value = ''; loadRevs();
  toast('Версия записана');
};

$('#delBtn').onclick = async () => {
  if (!confirm('Удалить заметку вместе со связями, вопросами и историей?')) return;
  await api('delete', {id: BOOT.id});
  location.href = 'index.php';
};
$('#saveBtn').onclick = () => save();

/* --- клавиатура ------------------------------------------------------ */
function wrap(before, after) {
  const t = $('#body'), s = t.selectionStart, e2 = t.selectionEnd, sel = t.value.slice(s, e2);
  t.setRangeText(before + sel + after, s, e2, 'select');
  if (s === e2) t.setSelectionRange(s + before.length, s + before.length);
  touch();
}
document.addEventListener('keydown', e => {
  const meta = e.metaKey || e.ctrlKey;
  if (!meta) { if (e.key === 'Escape') $('#ov').classList.remove('on'); return; }
  const k = e.key.toLowerCase();
  if (k === 's') { e.preventDefault(); save(); }
  else if (k === '\\') { e.preventDefault(); $('#togglePanel').click(); }
  else if (k === '1') { e.preventDefault(); setMode('write'); }
  else if (k === '2') { e.preventDefault(); setMode('split'); }
  else if (k === '3') { e.preventDefault(); setMode('preview'); }
  else if (document.activeElement === $('#body')) {
    if (k === 'b') { e.preventDefault(); wrap('**', '**'); }
    else if (k === 'i') { e.preventDefault(); wrap('*', '*'); }
    else if (k === 'k') { e.preventDefault(); wrap('[[', ']]'); }
  }
});
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

/* --- прочее ---------------------------------------------------------- */
function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
let toastTimer = null;
function toast(html) {
  const t = $('#toast'); t.innerHTML = html; t.classList.add('on');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('on'), 3200);
}

renderTags();
renderLinks({out: BOOT.linksOut, in: BOOT.linksIn});
renderQ(BOOT.questions);
loadRevs();
$('#body').focus();
</script>
</body>
</html>
