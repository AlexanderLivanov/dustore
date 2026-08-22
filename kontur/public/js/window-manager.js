// ============================================================
// WINDOW-MANAGER.JS — многооконность, drag&drop, z-index
// ============================================================
// Архитектура: каждое окно — DOM-элемент с .win классом.
// window-manager хранит реестр и управляет фокусом / перетаскиванием.
// ============================================================

const WM = (() => {

  // ── Внутренний реестр окон ──────────────────────────────
  // { id: { el, minimized, tbBtn, title } }
  const _wins = {};
  let _topZ   = 100;
  const DESKTOP = () => document.getElementById('desktop');

  // ── Создать новое окно ──────────────────────────────────
  function create({ id, title, icon='К', width=700, height=520,
                    x=null, y=null, content='', onClose=null,
                    tabs=null, menuItems=null }) {

    if (_wins[id]) {
      focus(id);
      return _wins[id].el;
    }

    const desk  = DESKTOP();
    const dRect = desk.getBoundingClientRect();

    // Cascade positioning
    const offset = Object.keys(_wins).length * 22;
    const px = x ?? Math.min(40 + offset, dRect.width  - width  - 20);
    const py = y ?? Math.min(30 + offset, dRect.height - height - 10);

    const el = document.createElement('div');
    el.className = 'win';
    el.id        = 'win-' + id;
    el.style.cssText = `width:${width}px;height:${height}px;left:${Math.max(0,px)}px;top:${Math.max(0,py)}px;z-index:${++_topZ};`;

    // ── Titlebar
    const tbBtns = `
      <div class="win-tb-btns">
        <div class="wbtn" data-action="minimize" title="Свернуть">_</div>
        <div class="wbtn" data-action="maximize" title="Развернуть">□</div>
        <div class="wbtn" data-action="close"    title="Закрыть" style="font-size:11px;">✕</div>
      </div>`;

    const menuBar = menuItems
      ? `<div class="win-menubar">${menuItems.map(m=>`<span class="mi" data-menu="${m.key}">${m.label}</span>`).join('')}</div>`
      : '';

    const tabBar = tabs
      ? `<div class="win-tabbar" id="${id}-tabbar">
           ${tabs.map((t,i)=>`<div class="wtab ${i===0?'active':''}" data-tab="${t.id}">${t.label}</div>`).join('')}
         </div>`
      : '';

    el.innerHTML = `
      <div class="win-titlebar" data-win="${id}">
        <div class="win-tb-left">
          <div class="win-emblem">${icon}</div>
          <span class="win-title-text">${title}</span>
        </div>
        ${tbBtns}
      </div>
      ${menuBar}
      ${tabBar}
      <div class="win-inner" id="${id}-inner">
        ${content}
      </div>
      <div class="win-resize" title="Изменить размер">◢</div>`;

    desk.appendChild(el);
    _makeDraggable(el, id);
    _makeResizable(el, id);
    _bindTitlebarBtns(el, id, onClose);

    // Taskbar button
    const tbBtn = _addTaskbarBtn(id, title, icon);

    _wins[id] = { el, minimized: false, tbBtn, title, onClose };

    // Click to focus
    el.addEventListener('mousedown', () => focus(id), true);
    focus(id);
    return el;
  }

  // ── Focus window ────────────────────────────────────────
  function focus(id) {
    const w = _wins[id];
    if (!w) return;

    // Deactivate all taskbar btns
    document.querySelectorAll('.tbwin').forEach(b => b.classList.remove('active'));

    // Restore if minimized
    if (w.minimized) {
      w.el.classList.remove('minimized');
      w.minimized = false;
    }

    // Raise z-index
    w.el.style.zIndex = ++_topZ;

    // Update titlebar gradient (focused vs unfocused)
    Object.values(_wins).forEach(win => {
      const tb = win.el.querySelector('.win-titlebar');
      if (tb) tb.style.background = win === w
        ? 'linear-gradient(to right,#000080,#1084d0)'
        : '#808080';
    });

    // Mark taskbar
    if (w.tbBtn) w.tbBtn.classList.add('active');
  }

  // ── Minimize ─────────────────────────────────────────────
  function minimize(id) {
    const w = _wins[id];
    if (!w) return;
    w.el.classList.add('minimized');
    w.minimized = true;
    if (w.tbBtn) w.tbBtn.classList.remove('active');
    // Grey out titlebar
    const tb = w.el.querySelector('.win-titlebar');
    if (tb) tb.style.background = '#808080';
  }

  // ── Close / destroy ──────────────────────────────────────
  function close(id) {
    const w = _wins[id];
    if (!w) return;
    if (w.onClose && w.onClose() === false) return; // veto
    w.el.remove();
    if (w.tbBtn) w.tbBtn.remove();
    delete _wins[id];
  }

  // ── Toggle minimize ──────────────────────────────────────
  function toggle(id) {
    const w = _wins[id];
    if (!w) return;
    w.minimized ? focus(id) : minimize(id);
  }

  // ── Get inner element ────────────────────────────────────
  function inner(id) {
    return document.getElementById(id + '-inner');
  }

  // ── Make draggable ───────────────────────────────────────
  function _makeDraggable(el, id) {
    const tb = el.querySelector('.win-titlebar');
    if (!tb) return;

    let ox, oy, sx, sy, dragging = false;

    tb.addEventListener('mousedown', e => {
      if (e.target.closest('.win-tb-btns')) return;
      dragging = true;
      ox = el.offsetLeft; oy = el.offsetTop;
      sx = e.clientX;     sy = e.clientY;
      tb.classList.add('dragging');
      e.preventDefault();
    });

    document.addEventListener('mousemove', e => {
      if (!dragging) return;
      const desk  = DESKTOP();
      const dRect = desk.getBoundingClientRect();
      const nx = ox + e.clientX - sx;
      const ny = oy + e.clientY - sy;
      // Clamp to desktop
      el.style.left = Math.max(0, Math.min(nx, dRect.width  - el.offsetWidth))  + 'px';
      el.style.top  = Math.max(0, Math.min(ny, dRect.height - 30))              + 'px';
    });

    document.addEventListener('mouseup', () => {
      if (dragging) { dragging = false; tb.classList.remove('dragging'); }
    });
  }

  // ── Make resizable ───────────────────────────────────────
  function _makeResizable(el, id) {
    const handle = el.querySelector('.win-resize');
    if (!handle) return;

    let dragging = false, ox, oy, sw, sh;

    handle.addEventListener('mousedown', e => {
      dragging = true;
      ox = e.clientX; oy = e.clientY;
      sw = el.offsetWidth; sh = el.offsetHeight;
      e.preventDefault(); e.stopPropagation();
    });

    document.addEventListener('mousemove', e => {
      if (!dragging) return;
      const nw = Math.max(280, sw + e.clientX - ox);
      const nh = Math.max(200, sh + e.clientY - oy);
      el.style.width  = nw + 'px';
      el.style.height = nh + 'px';
    });

    document.addEventListener('mouseup', () => { dragging = false; });
  }

  // ── Titlebar button bindings ─────────────────────────────
  function _bindTitlebarBtns(el, id, onClose) {
    el.querySelector('[data-action="minimize"]').addEventListener('click', e => {
      e.stopPropagation(); minimize(id);
    });
    el.querySelector('[data-action="maximize"]').addEventListener('click', e => {
      e.stopPropagation(); _toggleMax(el);
    });
    el.querySelector('[data-action="close"]').addEventListener('click', e => {
      e.stopPropagation(); close(id);
    });
  }

  // ── Maximize / restore ───────────────────────────────────
  function _toggleMax(el) {
    if (el._maxed) {
      const s = el._prevStyle;
      el.style.left = s.left; el.style.top = s.top;
      el.style.width = s.width; el.style.height = s.height;
      el._maxed = false;
    } else {
      el._prevStyle = { left:el.style.left, top:el.style.top, width:el.style.width, height:el.style.height };
      const desk = DESKTOP();
      el.style.left = '0'; el.style.top = '0';
      el.style.width = desk.offsetWidth + 'px';
      el.style.height = desk.offsetHeight + 'px';
      el._maxed = true;
    }
  }

  // ── Taskbar button ───────────────────────────────────────
  function _addTaskbarBtn(id, title, icon) {
    const tbwins = document.getElementById('tbwins') || document.querySelector('.tbwins');
    if (!tbwins) return null;
    const btn = document.createElement('div');
    btn.className   = 'tbwin';
    btn.id          = 'tbwin-' + id;
    btn.textContent = (icon.length === 1 ? '' : icon + ' ') + title.substring(0, 18);
    btn.title       = title;
    btn.addEventListener('click', () => toggle(id));
    tbwins.appendChild(btn);
    return btn;
  }

  // ── Public API ───────────────────────────────────────────
  return { create, focus, minimize, close, toggle, inner, wins: _wins };

})();

window.WM = WM;
