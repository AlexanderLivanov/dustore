/* feed.js — compose, публикация, редактор статьи, фид */

const UPGRADE_CHARS = 180; // порог для предложения редактора

// ── Compose ────────────────────────────────────────────────
const composeTa   = document.getElementById('compose-ta');
const composeCnt  = document.getElementById('compose-cnt');
const upgradeHint = document.getElementById('upgrade-hint');
let hintShown     = false;

if (composeTa) {
  composeTa.addEventListener('input', () => {
    autoGrow(composeTa);
    const len  = composeTa.value.length;
    const left = 400 - len;
    if (composeCnt) {
      composeCnt.textContent = left;
      composeCnt.classList.toggle('warn', left < 40);
    }
    if (upgradeHint) {
      if (len >= UPGRADE_CHARS && !hintShown) {
        hintShown = true;
        upgradeHint.classList.add('show');
      } else if (len < UPGRADE_CHARS && hintShown) {
        hintShown = false;
        upgradeHint.classList.remove('show');
      }
    }
  });
}

function publishPost() {
  const text = composeTa ? composeTa.value.trim() : '';
  if (!text) { toast('Напишите что-нибудь вдохновляющее ✦'); return; }
  addPostToFeed(text, 'note');
  resetCompose();
  toast('Опубликовано ✦');
}

function resetCompose() {
  if (!composeTa) return;
  composeTa.value = '';
  composeTa.style.height = '';
  if (composeCnt) { composeCnt.textContent = '400'; composeCnt.classList.remove('warn'); }
  if (upgradeHint) { upgradeHint.classList.remove('show'); hintShown = false; }
}

function addPostToFeed(text, type, title) {
  const feed = document.getElementById('feed');
  if (!feed) return;
  const art = document.createElement('article');
  art.className = `post ${type}`;
  let bodyHTML;
  if (type === 'article-card' && title) {
    bodyHTML = `
      <div class="article-tag">✦ Статья</div>
      <div class="article-preview">
        <div class="ap-title">${esc(title)}</div>
        <div class="ap-excerpt">${esc(text.slice(0, 200))}…</div>
      </div>`;
  } else {
    bodyHTML = `<p class="post-body">${esc(text)}</p>`;
  }
  art.innerHTML = `
    <div class="post-header">
      <div class="post-meta">
        <b>Александр</b><span class="dot">·</span>
        <span>@alexander</span><span class="dot">·</span><span>только что</span>
      </div>
      <button class="post-menu-btn" aria-label="Меню поста">···</button>
      <div class="post-dropdown">
        <button class="pd-item" onclick="copyLink()"><span class="pd-icon">🔗</span>Поделиться ссылкой</button>
        <button class="pd-item" onclick="toast('Открыть форму ответа')"><span class="pd-icon">↩</span>Написать ответ</button>
        <button class="pd-item danger" onclick="toast('Жалоба отправлена')"><span class="pd-icon">⚑</span>Пожаловаться</button>
      </div>
    </div>
    ${bodyHTML}
    <div class="react">
      <button class="react-btn resonate" data-base="0">
        <span class="glyph">✶</span><span class="react-cnt"> 0</span>&nbsp;отзывается
      </button>
      <button class="react-btn" onclick="toast('Открыть тред')">↳&nbsp;0</button>
      <button class="react-btn" onclick="toast('Сохранено')">⤴&nbsp;Сохранить</button>
    </div>`;
  art.querySelector('.post-menu-btn').addEventListener('click', e => {
    e.stopPropagation();
    const dd  = art.querySelector('.post-dropdown');
    const was = dd.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!was) dd.classList.add('open');
  });
  art.querySelector('.resonate').addEventListener('click', function () { resonateClick(this); });
  feed.insertBefore(art, feed.firstChild);
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Feed tabs ──────────────────────────────────────────────
function feedTab(btn) {
  document.querySelectorAll('.feed-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  toast(btn.textContent.trim());
}

// ── Article editor ─────────────────────────────────────────
const edOverlay = document.getElementById('editor-overlay');
const edTitle   = document.getElementById('ed-title');
const edContent = document.getElementById('ed-content');
const edStatus  = document.getElementById('ed-status');
let edOpen      = false;
let autoSaveT   = null;

function openEditor(prefill) {
  if (!edOverlay) return;
  edOpen = true;
  edOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
  if (prefill && edContent) {
    edContent.value = prefill;
    autoGrow(edContent);
  }
  setTimeout(() => edTitle && edTitle.focus(), 430);
  updateEditorStats();
}

function closeEditor() {
  if (!edOverlay) return;
  edOverlay.classList.remove('open');
  document.body.style.overflow = '';
  edOpen = false;
}

function openEditorFromCompose() {
  openEditor(composeTa ? composeTa.value.trim() : '');
}

function saveDraft() {
  const time = new Date().toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
  if (edStatus) edStatus.textContent = 'Черновик сохранён · ' + time;
  toast('Черновик сохранён');
}

function publishArticle() {
  const title = edTitle ? edTitle.value.trim() : '';
  const text  = edContent ? edContent.value.trim() : '';
  if (!title) { toast('Добавьте заголовок'); edTitle && edTitle.focus(); return; }
  if (text.length < 50) { toast('Статья слишком короткая'); edContent && edContent.focus(); return; }
  closeEditor();
  addPostToFeed(text, 'article-card', title);
  if (edTitle) edTitle.value = '';
  if (edContent) edContent.value = '';
  resetCompose();
  toast('Статья опубликована ✦');
}

if (edTitle)   edTitle.addEventListener('input',   () => { autoGrow(edTitle);   scheduleAutoSave(); updateEditorStats(); });
if (edContent) edContent.addEventListener('input', () => { autoGrow(edContent); scheduleAutoSave(); updateEditorStats(); });

function scheduleAutoSave() {
  clearTimeout(autoSaveT);
  if (edStatus) edStatus.textContent = 'Черновик…';
  autoSaveT = setTimeout(() => {
    const time = new Date().toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
    if (edStatus) edStatus.textContent = 'Сохранён · ' + time;
  }, 2000);
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && edOpen) closeEditor();
});

// ── Editor stats ───────────────────────────────────────────
function updateEditorStats() {
  const title   = edTitle   ? edTitle.value   : '';
  const content = edContent ? edContent.value : '';
  const words   = (title + ' ' + content).trim().split(/\s+/).filter(Boolean).length;
  const chars   = content.length;
  const paras   = content.split(/\n\n+/).filter(p => p.trim()).length || (content.trim() ? 1 : 0);
  const readMin = Math.max(1, Math.round(words / 200));

  setText('s-chars', chars.toLocaleString('ru'));
  setText('s-words', words.toLocaleString('ru'));
  setText('s-paras', paras || '—');
  setText('s-read',  words > 0 ? readMin + ' мин' : '—');

  // readability
  const sentences  = content.split(/[.!?]+/).filter(s => s.trim().length > 3);
  const avgSentLen = sentences.length ? words / sentences.length : 0;
  const longSents  = sentences.filter(s => s.trim().split(/\s+/).length > 20).length;
  setText('s-long', longSents > 0 ? longSents : '—');

  const allW  = content.trim().split(/\s+/).filter(Boolean);
  const avgWL = allW.length
    ? (allW.reduce((s, w) => s + w.replace(/[^а-яёa-z]/gi, '').length, 0) / allW.length).toFixed(1)
    : '—';
  setText('s-avgword', avgWL !== '—' ? avgWL + ' букв' : '—');

  // readability score
  let rs = words > 0 ? 100 : 0;
  if (avgSentLen > 20) rs -= 20;
  if (avgSentLen > 30) rs -= 15;
  if (avgWL > 6)       rs -= 10;
  if (longSents > 3)   rs -= 15;
  rs = Math.max(0, Math.min(100, rs));

  const bar = document.getElementById('readab-bar');
  const lbl = document.getElementById('readab-label');
  if (bar) bar.style.width = rs + '%';
  if (lbl) lbl.textContent = words === 0 ? 'Начните писать'
    : rs >= 75 ? 'Читается легко'
    : rs >= 50 ? 'Средняя сложность'
    : 'Сложно — упростите предложения';

  // SEO score
  let seo = 0; const tips = [];
  if (title.length >= 10) seo += 25; else tips.push('Заголовок слишком короткий.');
  if (title.length <= 60) seo += 10; else tips.push('Заголовок длиннее 60 символов.');
  if (chars >= 300)  seo += 25; else tips.push('Добавьте больше текста (мин. 300 симв.).');
  if (chars >= 1000) seo += 15;
  if (paras >= 3)    seo += 15; else tips.push('Разбейте текст на абзацы.');
  if (document.querySelectorAll('.tag-chip').length >= 2) seo += 10; else tips.push('Добавьте хотя бы 2 тега.');
  seo = Math.min(100, seo);

  const arc = document.getElementById('seo-arc');
  if (arc) {
    const circ = 213.6;
    arc.style.strokeDashoffset = circ - (circ * seo / 100);
    arc.style.stroke = seo < 40 ? '#c0392b' : seo < 70 ? '#e67e22' : '#2e7d32';
  }
  setText('seo-num', seo);
  setText('seo-lbl', seo < 40 ? 'слабый' : seo < 70 ? 'средний' : 'хороший');
  const tipEl = document.getElementById('seo-tip');
  if (tipEl) tipEl.innerHTML = tips.length
    ? `<strong>Совет:</strong> ${tips[0]}`
    : `<strong>Отлично!</strong> Статья хорошо оптимизирована.`;
}

function setText(id, val) {
  const el = document.getElementById(id); if (el) el.textContent = val;
}

// ── Tags ────────────────────────────────────────────────────
function addTag(e) {
  if (e.key !== 'Enter' && e.key !== ',') return;
  e.preventDefault();
  const inp  = document.getElementById('tag-input');
  const val  = inp.value.trim().replace(/^#/, '');
  if (!val) return;
  const wrap = document.getElementById('tags-wrap');
  const chip = document.createElement('span');
  chip.className = 'tag-chip';
  chip.textContent = '#' + val;
  chip.title = 'Удалить';
  chip.onclick = () => { chip.remove(); updateEditorStats(); };
  wrap.appendChild(chip);
  inp.value = '';
  updateEditorStats();
}
