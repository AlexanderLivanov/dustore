// ============================================================
// CONTEXT-MENU.JS — универсальное контекстное меню v2.1
// Работает везде: на статьях, иконках, вкладках, рабочем столе
// ============================================================

const CtxMenu = (() => {

  let _el;

  function _init() {
    if (_el) return;
    _el = document.createElement('div');
    _el.id = 'ctx-menu';
    document.body.appendChild(_el);
    document.addEventListener('mousedown', e => {
      if (!_el.contains(e.target)) hide();
    });
    document.addEventListener('scroll', hide, true);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') hide();
    });
  }

  function show(x, y, items) {
    _init();
    _el.innerHTML = items.filter(Boolean).map(item => {
      if (item.sep) return '<div class="ctx-sep"></div>';
      const dis = item.disabled ? 'disabled' : '';
      const icon = item.icon
        ? `<span style="width:16px;text-align:center;flex-shrink:0;">${item.icon}</span>`
        : `<span style="width:16px;display:inline-block;flex-shrink:0;"></span>`;
      const shortcut = item.shortcut
        ? `<span class="ctx-shortcut">${item.shortcut}</span>`
        : '';
      return `<div class="ctx-item ${dis}" data-key="${item.key||''}">${icon}<span style="flex:1;">${item.label}</span>${shortcut}</div>`;
    }).join('');

    _el.querySelectorAll('.ctx-item:not(.disabled)').forEach(el => {
      const item = items.find(i => i && i.key === el.dataset.key);
      if (item?.action) el.addEventListener('click', () => { hide(); item.action(); });
    });

    _el.style.display = 'block';
    _el.style.left = '0'; _el.style.top = '0';
    const mw = _el.offsetWidth, mh = _el.offsetHeight;
    _el.style.left = (x + mw > window.innerWidth  ? window.innerWidth  - mw - 4 : x) + 'px';
    _el.style.top  = (y + mh > window.innerHeight ? window.innerHeight - mh - 4 : y) + 'px';
  }

  function hide() { if (_el) _el.style.display = 'none'; }

  // ── Article row context menu ─────────────────────────────
  function bindArticleRow(el, articleId) {
    el.addEventListener('contextmenu', e => {
      e.preventDefault(); e.stopPropagation();
      const article = S.articles.find(a => a.id === articleId);
      if (!article) return;
      const canRate = !!S.user;
      const canMod  = S.user && (S.user.role === 'moderator' || S.user.role === 'admin');
      const canAdmin = S.user && S.user.role === 'admin';

      show(e.clientX, e.clientY, [
        { key:'open',   icon:'📄', label:'Открыть статью',     action: () => Modal.open(articleId) },
        { sep:true },
        { key:'rate5',  icon:'★',  label:'Оценить ★★★★★',    disabled:!canRate, action:() => Articles.rate(articleId,5) },
        { key:'rate3',  icon:'☆',  label:'Оценить ★★★',       disabled:!canRate, action:() => Articles.rate(articleId,3) },
        { key:'rate1',  icon:'☆',  label:'Оценить ★',          disabled:!canRate, action:() => Articles.rate(articleId,1) },
        { sep:true },
        { key:'copy',   icon:'📋', label:'Скопировать заголовок', action:() => {
            navigator.clipboard?.writeText(article.title).catch(()=>{});
            Toast.show('Скопировано!', 'success', 1500);
          }
        },
        { sep:true },
        canMod ? { key:'approve', icon:'✓', label:'Одобрить',  disabled:article.status==='approved', action:()=>Moderation.approve(articleId) } : null,
        canMod ? { key:'reject',  icon:'✕', label:'Отклонить', disabled:article.status==='rejected', action:()=>Moderation.reject(articleId) } : null,
        canAdmin ? { key:'delete', icon:'🗑', label:'Удалить',  action:()=>Moderation.del(articleId) } : null,
      ]);
    });
  }

  // ── Desktop background context menu ──────────────────────
  function bindDesktop(el) {
    el.addEventListener('contextmenu', e => {
      if (e.target !== el && !e.target.classList.contains('desk-icons')) {
        // Если клик по иконке — не перехватываем
        if (e.target.closest('.dicon')) return;
      }
      e.preventDefault();
      show(e.clientX, e.clientY, [
        { key:'fs',       icon:'⛶',  label:'Полный экран',       shortcut:'F11',  action:() => UI.toggleFullscreen() },
        { key:'settings', icon:'⚙',  label:'Настройки рабочего стола',            action:() => UI.toggleSettings() },
        { sep:true },
        { key:'winmain',  icon:'📂',  label:'Открыть архив К.О.Н.Т.У.Р.',         action:() => WM.toggle('main') },
        { sep:true },
        { key:'refresh',  icon:'🔄',  label:'Обновить',                            action:() => location.reload() },
      ]);
    });
  }

  // ── Taskbar icon context menu ─────────────────────────────
  function bindDicon(el, actions) {
    el.addEventListener('contextmenu', e => {
      e.preventDefault(); e.stopPropagation();
      show(e.clientX, e.clientY, actions);
    });
  }

  // ── Tab context menu ──────────────────────────────────────
  function bindTab(el, tabId) {
    el.addEventListener('contextmenu', e => {
      e.preventDefault(); e.stopPropagation();
      show(e.clientX, e.clientY, [
        { key:'open', icon:'📂', label:`Открыть вкладку`,    action:() => UI.switchTab(tabId) },
        { key:'sep',  sep:true },
        { key:'main', icon:'🏠', label:'Вернуться на главную', action:() => UI.switchTab('main') },
      ]);
    });
  }

  return { show, hide, bindArticleRow, bindDesktop, bindDicon, bindTab };
})();

window.CtxMenu = CtxMenu;
