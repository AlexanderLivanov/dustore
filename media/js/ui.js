/* ui.js — общие утилиты: toasts, dropdowns, profile menu */

// ── Toasts ──────────────────────────────────────────────
function toast(msg) {
  let container = document.getElementById('toasts');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toasts';
    document.body.appendChild(container);
  }
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = msg;
  container.appendChild(t);
  setTimeout(() => t.remove(), 2800);
}

// ── Auto-grow textarea ───────────────────────────────────
function autoGrow(ta) {
  ta.style.height = 'auto';
  ta.style.height = ta.scrollHeight + 'px';
}
document.querySelectorAll('textarea[data-autogrow]').forEach(ta => {
  ta.addEventListener('input', () => autoGrow(ta));
});

// ── Profile menu ─────────────────────────────────────────
const profBtn  = document.getElementById('prof-btn');
const profMenu = document.getElementById('prof-menu');
if (profBtn && profMenu) {
  profBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = profMenu.classList.toggle('open');
    profBtn.setAttribute('aria-expanded', open);
  });
  document.addEventListener('click', () => profMenu.classList.remove('open'));
}

// ── Post 3-dot dropdowns ─────────────────────────────────
// Открывает/закрывает дропдаун поста; клик вне — закрывает все
document.addEventListener('click', e => {
  // Если кликнули не на кнопку меню — закрываем все открытые дропдауны
  if (!e.target.closest('.post-menu-btn')) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

document.querySelectorAll('.post-menu-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const dropdown = btn.nextElementSibling;
    const wasOpen  = dropdown.classList.contains('open');
    // закрыть все
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) dropdown.classList.add('open');
  });
});

// ── Copy link (Share) ────────────────────────────────────
function copyLink() {
  const url = window.location.href;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => toast('Ссылка скопирована'));
  } else {
    toast('Скопируйте ссылку вручную: ' + url);
  }
}

// ── Follow toggle ─────────────────────────────────────────
function toggleFollow(btn) {
  const on = btn.classList.toggle('on');
  const nameEl = btn.closest('.who')?.querySelector('.who-name');
  const name   = nameEl ? nameEl.textContent : '';
  btn.textContent = on ? '✓ Читаю' : 'Читать';
  toast(on ? `Вы читаете ${name}` : `Отписались от ${name}`);
}

// ── Resonate (like) ───────────────────────────────────────
function resonateClick(btn) {
  const cnt  = btn.querySelector('.react-cnt');
  const base = parseInt(btn.dataset.base || '0', 10);
  const on   = btn.classList.toggle('on');
  if (cnt) cnt.textContent = ' ' + (on ? base + 1 : base);
  const glyph = btn.querySelector('.glyph');
  if (glyph) glyph.textContent = on ? '✦' : '✶';
}
document.querySelectorAll('.resonate').forEach(btn => {
  btn.addEventListener('click', () => resonateClick(btn));
});

// ── Active nav link highlight ─────────────────────────────
(function () {
  const page = location.pathname.split('/').pop() || 'feed.html';
  document.querySelectorAll('.nav-btn[data-page]').forEach(btn => {
    if (btn.dataset.page === page) btn.classList.add('active');
  });
})();
