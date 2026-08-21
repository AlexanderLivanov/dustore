// ============================================================
// UI.JS — вкладки, сайдбар (из API), поиск, fullscreen v3.0
// ============================================================

const UI = (() => {

  const BGSW = [
    { n:'Классический', c:'#008080' }, { n:'Тёмный', c:'#1a1a2e' },
    { n:'Лесной', c:'#1a3020' }, { n:'Угольный', c:'#1c1c1c' },
    { n:'Ночной', c:'#0a0a1a' }, { n:'Бордовый', c:'#2a0a0a' },
  ];
  const TAB_LABELS = {
    main:'Загрузка главной...', obj:'Загрузка объектов...',
    ent:'Загрузка сущностей...', exp:'Загрузка экспериментов...',
    fan:'Загрузка фан-архива...', mod:'Загрузка модерации...',
  };

  function initClock() {
    function tick() {
      const d = new Date();
      const el = document.getElementById('tb-clock');
      if (el) el.textContent = String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
    }
    tick(); setInterval(tick, 1000);
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(()=>{});
      Toast.show('Полный экран — Esc или F11 для выхода', 'info', 2500);
    } else document.exitFullscreen().catch(()=>{});
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'F11') { e.preventDefault(); toggleFullscreen(); }
  });

  function switchTab(tab) {
    S.activeTab = tab;
    document.querySelectorAll('.wtab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    const targetPanel = document.getElementById('panel-' + tab);
    if (!targetPanel) return;
    const wcontent = document.querySelector('.wcontent');
    if (!wcontent) return;

    Loader.run(wcontent, TAB_LABELS[tab] || 'Загрузка...', () => {
      document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
      targetPanel.classList.add('active');
      if (tab === 'main') Articles.renderAll();
      if (tab === 'obj')  Articles.renderByTag('obj-list', 'ОБЪ');
      if (tab === 'exp')  Articles.renderByTag('exp-list', 'ЭКСП');
      if (tab === 'fan')  Articles.renderByTag('fan-list', 'ФАН');
      if (tab === 'mod')  Moderation.render();
    }, 350);
  }

  // ── Sections из API ──────────────────────────────────────
  async function loadSections() {
    try {
      const { sections } = await API.listSections();
      S.sections    = sections.main.map(s => s.name);
      S.fanSections = sections.fan.map(s => s.name);
      S._sectionMain = sections.main; // с id для удаления
      S._sectionFan  = sections.fan;
      renderSidebar();
    } catch (e) {
      Toast.show('Не удалось загрузить разделы', 'warn');
    }
  }

  function renderSidebar() {
    const sb = document.getElementById('sb-main');
    if (sb) sb.innerHTML = S.sections.map(s =>
      `<div class="si ${S.selSection===s?'sel':''}" onclick="UI.selectSec('${_esc(s)}')"><span class="bul">►</span>${_esc(s)}</div>`
    ).join('');
    const sf = document.getElementById('sb-fan');
    if (sf) sf.innerHTML = S.fanSections.map(s =>
      `<div class="si" onclick="UI.switchTab('fan')"><span class="bul">►</span>${_esc(s)}</div>`
    ).join('');
  }

  function selectSec(n) { S.selSection = n; renderSidebar(); }

  async function addSection(type) {
    const name = prompt('Название нового раздела:');
    if (!name?.trim()) return;
    try {
      await API.createSection(name.trim(), type === 'main' ? 'main' : 'fan');
      Toast.show(`Раздел «${name.trim()}» добавлен`, 'success');
      await loadSections();
    } catch (e) {
      Toast.show('Ошибка: ' + e.message, 'error');
    }
  }

  function updatePills() {
    const ok = S.articles.length;
    const artEl = document.getElementById('pill-art');
    if (artEl) artEl.textContent = 'Статей: ' + ok;
    // pending виден только модератору — счётчик подгружается отдельно
    const pendEl = document.getElementById('pill-pend');
    if (pendEl && S.user && ['moderator','admin'].includes(S.user.role)) {
      API.listArticles('pending').then(r => {
        pendEl.textContent = 'На модерации: ' + r.articles.length;
        pendEl.style.display = '';
      }).catch(()=>{});
    } else if (pendEl) {
      pendEl.style.display = 'none';
    }
  }

  function initSearch() {
    const input = document.getElementById('search-input');
    const clear = document.getElementById('search-clear');
    if (!input) return;
    input.addEventListener('input', () => {
      S.searchQuery = input.value.trim();
      Articles.renderAll();
      const t = S.activeTab;
      if (t==='obj') Articles.renderByTag('obj-list','ОБЪ');
      if (t==='exp') Articles.renderByTag('exp-list','ЭКСП');
      if (t==='fan') Articles.renderByTag('fan-list','ФАН');
      if (clear) clear.style.display = S.searchQuery ? '' : 'none';
    });
    if (clear) {
      clear.style.display = 'none';
      clear.addEventListener('click', () => {
        input.value=''; S.searchQuery='';
        Articles.renderAll(); clear.style.display='none'; input.focus();
      });
    }
  }

  function initDialogs() {
    const overlay = document.getElementById('dlg-overlay');
    if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) Dialogs.closeAll(); });
  }

  function buildBgGrid() {
    const grid = document.getElementById('bg-grid');
    if (!grid) return;
    grid.innerHTML = BGSW.map(sw =>
      `<div class="bgswatch ${sw.c===S.bg?'sel':''}" data-c="${sw.c}" style="background:${sw.c};">${sw.n}</div>`
    ).join('');
    grid.querySelectorAll('.bgswatch').forEach(el => el.addEventListener('click', () => setBg(el.dataset.c)));
  }

  function setBg(c) {
    S.bg = c;
    document.getElementById('app').style.background = c;
    document.querySelectorAll('.bgswatch').forEach(b => b.classList.toggle('sel', b.dataset.c === c));
    const dark = ['#1a1a2e','#1c1c1c','#0a0a1a','#1a3020','#2a0a0a'];
    document.querySelectorAll('.dicon span').forEach(el => {
      el.style.background = dark.includes(c) ? 'rgba(0,0,60,.8)' : 'rgba(0,0,128,.65)';
    });
    Toast.show('Фон изменён', 'info', 1500);
  }

  function toggleSettings() {
    S.settingsOpen = !S.settingsOpen;
    const el = document.getElementById('dlg-settings');
    if (S.settingsOpen) { el.classList.add('open'); buildBgGrid(); }
    else el.classList.remove('open');
  }

  function _esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,"\\'");}

  return {
    initClock, toggleFullscreen, switchTab, loadSections, renderSidebar,
    selectSec, addSection, updatePills, initSearch, initDialogs,
    buildBgGrid, setBg, toggleSettings,
  };
})();

window.UI = UI;

const Dialogs = {
  openLogin()  { document.getElementById('dlg-overlay').style.display=''; document.getElementById('dlg-login').classList.add('open'); },
  openSubmit() { if (!S.user) { Auth.openLogin(); return; } Editor.open(); },
  closeAll()   {
    document.getElementById('dlg-overlay').style.display='none';
    document.querySelectorAll('.dialog').forEach(d => d.classList.remove('open'));
  },
};
window.Dialogs = Dialogs;
