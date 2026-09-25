/* =============================================================================
   chat/chat.js — клиент чатов Dustore (v2).
   Вынесен из index.php, чтобы тот же код подключался и в мобильной PWA (/m/chat):
   конфиг приходит через window.CHAT_CFG, DOM — одинаковые id в обоих шеллах.

   Модули ниже: утилиты · звук · поллинг/WS · список · тред · ответы и свайп ·
   пины · контекстное меню · вложения · отправка · настройки · профиль/поиск.
   ============================================================================= */
(function () {
  'use strict';

  const CFG = window.CHAT_CFG || {};
  const ME = CFG.me | 0;
  const AUTO = CFG.auto || {};
  const API = CFG.api || '/chat/api.php';

  /* ── Утилиты ─────────────────────────────────────────────────────────────── */
  const $ = s => document.querySelector(s);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const isTouch = matchMedia('(pointer: coarse)').matches;

  function api(action, params = {}, method = 'GET') {
    const opt = { method, credentials: 'same-origin' };
    let url = API + '?action=' + action;
    if (method === 'GET') url += '&' + new URLSearchParams(params);
    else opt.body = new URLSearchParams({ action, ...params });
    return fetch(url, opt).then(r => r.json()).catch(() => ({ ok: false, error: 'network' }));
  }

  const initials = n => (n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  const avatarHTML = p => p.avatar
    ? `<img src="${esc(p.avatar)}" alt="" draggable="false" data-fb="${esc(initials(p.name))}" onerror="this.outerHTML=this.dataset.fb">`
    : esc(initials(p.name));
  const BELL = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>';
  const ICON = {
    reply: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
    pin: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.8V4h6v6.8l3 3.2H6z"/></svg>',
    copy: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>',
    dl: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>',
    del: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>',
    read: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l5 5L18 6"/><path d="M12 16l1 1L23 6"/></svg>',
    gear: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
    file: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
    retry: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>',
    wall: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>',
    more: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
  };
  const avShape = p => p && p.kind === 'system' ? 'sys' : (p && p.kind === 'studio' ? 'sq' : 'round');

  const asDate = ts => new Date(String(ts).replace(' ', 'T'));
  const fmtTime = ts => asDate(ts).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
  const fmtDay = ts => asDate(ts).toLocaleDateString('ru', { day: 'numeric', month: 'long' });
  const fmtSize = b => b < 1024 ? b + ' Б' : b < 1048576 ? (b / 1024).toFixed(0) + ' КБ' : (b / 1048576).toFixed(1).replace('.', ',') + ' МБ';

  function lastSeen(ts) {
    if (!ts) return '';
    const s = (Date.now() - asDate(ts)) / 1000;
    if (s < 90) return 'в сети';
    if (s < 3600) return 'был(а) ' + Math.floor(s / 60) + ' мин назад';
    if (s < 86400) return 'был(а) ' + Math.floor(s / 3600) + ' ч назад';
    return 'был(а) ' + asDate(ts).toLocaleDateString('ru', { day: 'numeric', month: 'short' });
  }

  /* Ссылки в тексте: экранированный текст → кликабельные http(s)-ссылки. */
  const linkify = html => html.replace(/https?:\/\/[^\s<]+[^\s<.,;:!?)\]»"']/g,
    u => `<a href="${u}" target="_blank" rel="noopener noreferrer">${u}</a>`);
  /* Ссылка из уведомления: только http(s) и относительные пути — никаких javascript: */
  const safeLink = u => { u = String(u || '').trim(); return (/^https?:\/\//i.test(u) || (u.startsWith('/') && !u.startsWith('//'))) ? u : ''; };

  let toastTimer;
  function toast(msg, err) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.toggle('err', !!err);
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  /* ── Состояние ───────────────────────────────────────────────────────────── */
  const state = {
    tab: 'personal', convId: 0, lastId: 0, firstId: 0, hasMore: false,
    draft: null, header: null, isSystem: false, listTimer: null, threadTimer: null,
    peerLastRead: 0, peerDelivered: 0,
    wallpaper: null,          // что пришло с сервера: { active, scope, mine, shared }
    msgs: new Map(),          // id → сообщение (для меню, копирования, ответа)
    pins: [], pinIdx: 0,
    replyTo: null,            // { id, name, text }
    atts: [],                 // вложения в композере
    settings: { sound: 'dust', volume: 70, custom: null }, v2: true,
    convs: [], filter: 'all',  // последний список с сервера и активный фильтр (только мобильная вёрстка)
  };
  const MOBILE = !!CFG.mobile;

  /* ════════════════════════ ЗВУК ═══════════════════════════════════════════
     Четыре фирменных звука синтезируются WebAudio (ни файлов, ни лицензий),
     плюс свой файл пользователя. AudioContext живёт после первого жеста. */
  let audioCtx = null, customAudio = null;
  function ctx() {
    if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { } }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
    return audioCtx;
  }
  document.addEventListener('pointerdown', ctx, { once: true, capture: true });
  document.addEventListener('keydown', ctx, { once: true, capture: true });

  function tone(ac, { f0, f1 = f0, type = 'sine', at = 0, dur = .2, peak = .2, out }) {
    const o = ac.createOscillator(), g = ac.createGain(), t = ac.currentTime + at;
    o.type = type;
    o.frequency.setValueAtTime(f0, t);
    if (f1 !== f0) o.frequency.exponentialRampToValueAtTime(f1, t + dur * .8);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(peak, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(out);
    o.start(t); o.stop(t + dur + .02);
  }
  const SOUNDS = {
    dust: { label: 'Dustore', play: (ac, o) => { tone(ac, { f0: 1318.5, dur: .22, peak: .22, out: o }); tone(ac, { f0: 1975.5, at: .09, dur: .32, peak: .16, out: o }); } },
    drop: { label: 'Капля', play: (ac, o) => { tone(ac, { f0: 1400, f1: 380, dur: .18, peak: .3, out: o }); } },
    pop: { label: 'Поп', play: (ac, o) => { tone(ac, { f0: 520, f1: 900, type: 'triangle', dur: .09, peak: .35, out: o }); tone(ac, { f0: 900, type: 'triangle', at: .06, dur: .08, peak: .15, out: o }); } },
    pixel: { label: 'Пиксель', play: (ac, o) => { [1046.5, 1318.5, 1568].forEach((f, i) => tone(ac, { f0: f, type: 'square', at: i * .06, dur: .07, peak: .07, out: o })); } },
  };

  function playSound(name = state.settings.sound, volume = state.settings.volume, force = false) {
    if (!force && document.hidden) return;              // фоновую вкладку озвучит системный пуш
    const vol = Math.max(0, Math.min(100, volume)) / 100;
    if (name === 'none' || vol === 0) return;
    if (name === 'custom' && state.settings.custom) {
      if (!customAudio || customAudio.dataset.id != state.settings.custom.id) {
        customAudio = new Audio(state.settings.custom.url);
        customAudio.dataset.id = state.settings.custom.id;
      }
      customAudio.volume = vol;
      customAudio.currentTime = 0;
      customAudio.play().catch(() => { });
      return;
    }
    const ac = ctx(); if (!ac) return;
    const master = ac.createGain(); master.gain.value = vol; master.connect(ac.destination);
    (SOUNDS[name] || SOUNDS.dust).play(ac, master);
  }
  function ping() { playSound(); }
  window.chatPing = ping;          // push-client.js зовёт при пуше в открытую вкладку

  /* ── Поллинг и WebSocket ─────────────────────────────────────────────────── */
  const POLL_LIST = { fast: 8000, slow: 25000 };
  const POLL_THREAD = { fast: 3000, slow: 15000 };
  let wsRate = 'fast', wsBackoff = 1000, prevTotal = null;

  function startListTimer() {
    clearInterval(state.listTimer);
    if (document.hidden) return;
    state.listTimer = setInterval(loadList, POLL_LIST[wsRate]);
  }
  function startThreadTimer() {
    clearInterval(state.threadTimer);
    if (document.hidden || !state.convId) return;
    state.threadTimer = setInterval(pollThread, POLL_THREAD[wsRate]);
  }
  function setRate(mode) { if (wsRate === mode) return; wsRate = mode; startListTimer(); startThreadTimer(); }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(state.listTimer); clearInterval(state.threadTimer); }
    else { loadList(); if (state.convId) pollThread(); startListTimer(); startThreadTimer(); }
  });
  /* «Прочитано» ставим, только когда человек реально смотрит: вкладка видна И окно
     в фокусе. Иначе сервер отметит лишь «доставлено». Вернулся в окно — сразу
     дёргаем тред, чтобы собеседник увидел яркие галочки без задержки поллинга.
     На телефоне фокус окна — ненадёжный признак: в PWA и WebView document.hasFocus()
     может вернуть false у видимой страницы, и тогда сообщения не читались вовсе.
     Там достаточно того, что вкладка видна. */
  const touch = matchMedia('(pointer: coarse)').matches;

  /* Высота видимой области → CSS (--vvh, --vvt). На iOS клавиатура не сжимает
     страницу, а сдвигает видимое окно: без этого беседа уезжала вверх вместе с
     шапкой, а после закрытия клавиатуры композер оставался не у нижнего края. */
  if (touch && window.visualViewport) {
    const vv = visualViewport, root = document.documentElement;
    const syncVV = () => {
      root.style.setProperty('--vvh', vv.height + 'px');
      root.style.setProperty('--vvt', vv.offsetTop + 'px');
      root.classList.toggle('kb', innerHeight - vv.height > 120);
    };
    vv.addEventListener('resize', syncVV);
    vv.addEventListener('scroll', syncVV);
    syncVV();
  }
  let unseenFetch = false;               // был запрос с seen=0 — отметим, как только человек «появится»
  const isSeen = () => {
    const s = document.visibilityState === 'visible' && (touch || document.hasFocus()) ? 1 : 0;
    if (!s) unseenFetch = true;
    return s;
  };
  const catchUpSeen = () => { if (unseenFetch && isSeen() && state.convId && state.lastId) { unseenFetch = false; pollThread(); } };
  window.addEventListener('focus', () => { if (state.convId && state.lastId) pollThread(); });
  document.addEventListener('pointerdown', catchUpSeen, { passive: true });
  document.addEventListener('keydown', catchUpSeen);

  async function connectWS() {
    try {
      const t = await fetch('/chat/ws_ticket.php').then(r => r.json());
      if (!t.ok) throw new Error('ticket');
      const ws = new WebSocket(`wss://${location.host}/ws?ticket=${encodeURIComponent(t.ticket)}`);
      ws.onopen = () => { wsBackoff = 1000; setRate('slow'); };
      ws.onmessage = e => {
        let m; try { m = JSON.parse(e.data); } catch { return; }
        if (m.type === 'new_message') { if (m.conversation_id === state.convId) pollThread(); loadList(); }
      };
      ws.onclose = ws.onerror = () => {
        setRate('fast');
        setTimeout(connectWS, wsBackoff);
        wsBackoff = Math.min(wsBackoff * 1.6 + Math.random() * 300, 15000);
      };
    } catch (e) {
      setRate('fast');
      if (wsBackoff < 15000) setTimeout(connectWS, wsBackoff);   // сокета нет вовсе — не долбим бесконечно
      wsBackoff = Math.min(wsBackoff * 1.6, 15000);
    }
  }

  /* ════════════════════════ СПИСОК БЕСЕД ═══════════════════════════════════ */
  const PIN_SVG = '<svg class="c-pin" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15 3l6 6-3 1-4 4 1 5-2 2-4-4-5 5-1-1 5-5-4-4 2-2 5 1 4-4z"/></svg>';
  const FILTERS = [['all', 'Все'], ['dm', 'Личные'], ['studio', 'Студии'], ['unread', 'Непрочитанные']];
  const isOnline = p => !!(p && p.seen && lastSeen(p.seen) === 'в сети');
  /* «Уведомления» — не беседа: в фильтрах «Личные» и «Студии» её нет */
  function inFilter(c, f) {
    if (f === 'unread') return (c.unread || 0) > 0;
    if (c.peer.kind === 'system') return f === 'all';
    if (f === 'dm') return c.peer.kind === 'user' && c.type !== 'studio';
    if (f === 'studio') return c.type === 'studio' || c.peer.kind === 'studio';
    return true;
  }

  async function loadList() {
    const r = await api('list', { tab: state.tab });
    const box = $('#list');
    if (!r.ok) { box.innerHTML = '<div class="empty">Не удалось загрузить список</div>'; return; }

    const total = r.conversations.reduce((a, c) => a + (c.unread || 0), 0);
    if (prevTotal !== null && total > prevTotal) ping();
    prevTotal = total;
    state.convs = r.conversations;
    paintList();
  }

  function paintList() {
    const box = $('#list');
    const all = state.convs;
    if (MOBILE) { paintChips(); paintRecent(); }
    if (!all.length) { box.innerHTML = '<div class="empty">Здесь появятся ваши диалоги</div>'; return; }
    const shown = MOBILE ? all.filter(c => inFilter(c, state.filter)) : all;
    if (!shown.length) { box.innerHTML = '<div class="empty">В этом разделе пока пусто</div>'; return; }

    box.innerHTML = shown.map(c => {
      const badge = c.unread ? `<span class="badge">${c.unread > 99 ? '99+' : c.unread}</span>` : '<span></span>';
      const active = c.id === state.convId ? ' active' : '';
      const isSys = c.peer.kind === 'system';
      const cls = isSys ? ' system' : (c.type === 'studio' ? ' studio' : '');
      const last = isSys
        ? (c.last ? esc(c.last.body) : 'нет уведомлений')
        : (c.last ? (c.last.mine ? '<span class="me">Вы: </span>' : '') + esc(c.last.body) : '<i>нет сообщений</i>');
      /* только телефон: точка «в сети», плашка «студия», значок закрепа у «Уведомлений» */
      const av = `<div class="av ${avShape(c.peer)}">${isSys ? BELL : avatarHTML(c.peer)}</div>`;
      const avCell = MOBILE ? `<div class="av-w">${av}${isOnline(c.peer) ? '<i class="on" title="в сети"></i>' : ''}</div>` : av;
      const pill = MOBILE && c.peer.kind === 'studio' ? '<span class="c-pill">студия</span>' : '';
      const time = `<span class="c-time">${c.last?.state ? `<span class="ticks ${c.last.state === 'delivered' ? 'dlv' : c.last.state}">${TICK_SVG}</span>` : ''}${c.ts ? fmtTime(c.ts) : ''}</span>`;
      /* на телефоне правая колонка как в макете: время сверху, закреп и счётчик снизу */
      const end = MOBILE ? `<span class="c-end">${time}<span class="c-bot">${isSys ? PIN_SVG : ''}${badge}</span></span>` : badge;
      return `<button type="button" class="card${cls}${active}" data-id="${c.id}" data-unread="${c.unread || 0}"
              data-peer='${esc(JSON.stringify(c.peer))}' data-studio="${c.type === 'studio' ? 1 : 0}" data-system="${isSys ? 1 : 0}">
      ${avCell}
      <div class="c-main">
        <div class="c-top"><span class="c-name">${esc(c.peer.name)}</span>${pill}${MOBILE ? '' : time}</div>
        <div class="c-last">${last}</div>
        ${c.peer.tag ? `<div class="c-tag">→ ${esc(c.peer.tag)}</div>` : ''}
      </div>
      ${end}
    </button>`;
    }).join('');
  }

  /* Фильтры-«чипы» над списком (телефон): считают непрочитанное по разделу */
  function paintChips() {
    const box = $('#chips'); if (!box) return;
    box.innerHTML = FILTERS.map(([k, label]) => {
      const n = k === 'unread' ? 0 : state.convs.filter(c => inFilter(c, k)).reduce((a, c) => a + (c.unread || 0), 0);
      return `<button type="button" class="chip${state.filter === k ? ' on' : ''}" data-f="${k}">${label}${n ? `<b>${n > 99 ? '99+' : n}</b>` : ''}</button>`;
    }).join('');
  }
  $('#chips')?.addEventListener('click', e => {
    const b = e.target.closest('.chip'); if (!b) return;
    state.filter = b.dataset.f;
    paintList();
  });

  /* Лента «Недавние»: те, кто написал (кольцо) и кто сейчас в сети (точка). Быстрый вход
     в беседу одним тапом — вместо «историй» из макета, у нас их нет. */
  function paintRecent() {
    const box = $('#recent'); if (!box) return;
    const cs = state.convs.filter(c => c.peer.kind !== 'system' && c.id > 0);
    const score = c => (c.unread ? 2 : 0) + (isOnline(c.peer) ? 1 : 0);
    const top = cs.map((c, i) => ({ c, i })).sort((a, b) => score(b.c) - score(a.c) || a.i - b.i).slice(0, 12).map(x => x.c);
    box.hidden = top.length < 2 || state.filter !== 'all';
    box.innerHTML = box.hidden ? '' : top.map(c =>
      `<button type="button" class="rc${c.unread ? ' hot' : ''}" data-id="${c.id}">
        <span class="ring"><span class="av ${avShape(c.peer)}">${avatarHTML(c.peer)}</span>${isOnline(c.peer) ? '<i class="on"></i>' : ''}</span>
        <span class="rc-n">${esc(c.peer.name)}</span></button>`).join('');
  }
  $('#recent')?.addEventListener('click', e => {
    const b = e.target.closest('.rc'); if (!b) return;
    const c = state.convs.find(x => x.id === +b.dataset.id); if (!c) return;
    openConv(c.id, c.peer, c.type === 'studio', null, false);
  });
  $('#list').addEventListener('click', e => {
    const el = e.target.closest('.card'); if (!el) return;
    openConv(+el.dataset.id, JSON.parse(el.dataset.peer), el.dataset.studio === '1', null, el.dataset.system === '1');
  });

  /* ════════════════════════ ТРЕД ═══════════════════════════════════════════ */
  function showRoom(on) {
    $('#roomEmpty').hidden = on;
    $('#roomHead').hidden = !on;
    $('#thread').hidden = !on;
    $('#composer').hidden = !on || state.isSystem;
    if (!on) $('#pinbar').hidden = true;
  }

  function openConv(id, peer, isStudio, draft, isSystem) {
    state.convId = id; state.lastId = 0; state.firstId = 0; state.hasMore = false;
    state.draft = draft || null; state.isSystem = !!isSystem; state.header = { peer, isStudio };
    state.msgs.clear(); state.pins = []; state.pinIdx = 0; state._pinSig = ''; state.peerLastRead = 0; state.peerDelivered = 0;
    applyWallpaper(null);
    cancelReply(); clearAtts();

    $('#room').classList.toggle('is-studio', !!isStudio);
    $('#room').classList.toggle('is-system', !!isSystem);
    $('#app').classList.add('show-room');
    $('#profile').classList.remove('open');
    $('#menu').hidden = true;
    $('#delConv').hidden = !!isSystem;
    $('#wpOpen').hidden = !!isSystem || id <= 0;
    showRoom(true);
    renderPins();
    syncQuick();

    const th = $('#thread');
    th.innerHTML = ''; th.dataset.lastDay = '';
    $('#rhAv').className = 'av ' + avShape(isSystem ? { kind: 'system' } : peer);
    $('#rhAv').innerHTML = isSystem ? BELL : avatarHTML(peer);
    $('#rhName').textContent = peer.name;
    $('#rhSub').innerHTML = '<span class="live off"></span>загрузка…';
    document.querySelectorAll('.card').forEach(c => c.classList.toggle('active', +c.dataset.id === id));

    clearInterval(state.threadTimer);
    if (id > 0) { loadInitial().then(startThreadTimer); }
    else { th.innerHTML = `<div class="empty">Новый чат с ${esc(peer.name)}.<br>Напишите первое сообщение ↓</div>`; }
    if (!isTouch) $('#input').focus();       // на телефоне фокус = клавиатура поверх треда
  }

  function applyHeader(h) {
    if (!h) return;
    state.header = { ...state.header, peer_id: h.peer_id, kind: h.kind };
    if (state.header.peer && state.header.peer.name === '…' && h.name) {
      state.header.peer = { kind: h.kind, id: h.peer_id, name: h.name, avatar: h.avatar };
      $('#rhName').textContent = h.name;
      $('#rhAv').className = 'av ' + avShape(state.header.peer); $('#rhAv').innerHTML = avatarHTML(state.header.peer);
      $('#room').classList.toggle('is-studio', !!h.studio);
    }
    state.peerLastRead = h.peer_last_read_id || 0;
    state.peerDelivered = Math.max(h.peer_last_delivered_id || 0, state.peerLastRead);
    let sub;
    if (h.kind === 'system') sub = 'системные уведомления';
    else if (h.kind === 'studio') sub = 'официальный канал студии';
    else sub = h.tag ? ('обращение · ' + h.tag) : (lastSeen(h.last_seen) || 'личный чат');
    const online = h.kind === 'user' && h.last_seen && (Date.now() - asDate(h.last_seen) < 90000);
    $('#rhSub').innerHTML = `<span class="live${online ? '' : ' off'}"></span>${esc(sub)}`;
    updateReadTicks();
  }

  async function loadInitial() {
    const cid = state.convId;
    const r = await api('thread', { conversation_id: cid, seen: isSeen() });
    if (cid !== state.convId) return;          // пока грузили, открыли другой диалог
    if (!r.ok) { toast('Не удалось открыть диалог', true); return; }
    applyHeader(r.header);
    setPins(r.pins);
    applyWallpaper(r.wallpaper);
    state.hasMore = !!r.has_more;

    const th = $('#thread');
    th.innerHTML = '';
    th.dataset.lastDay = '';
    if (state.hasMore) th.insertAdjacentHTML('beforeend', '<button type="button" class="more-btn" id="moreBtn">Показать раньше</button>');
    appendMessages(r.messages, 'beforeend');
    if (!r.messages.length) {
      th.innerHTML = `<div class="empty">${state.isSystem ? 'Уведомлений пока нет' : 'Сообщений пока нет — напишите первым'}</div>`;
    }
    // неотправленное в этот диалог переживает переключение между чатами
    [inflight, ...outbox, ...failed.values()].filter(p => p && p.convId === cid).forEach(paintPending);
    th.scrollTop = th.scrollHeight;
    loadList();
  }

  async function loadOlder() {
    if (!state.hasMore || !state.firstId) return false;
    const btn = $('#moreBtn'); if (btn) btn.textContent = 'Загружаю…';
    const r = await api('thread', { conversation_id: state.convId, before_id: state.firstId });
    if (!r.ok) { if (btn) btn.textContent = 'Показать раньше'; return false; }
    const th = $('#thread');
    const prevH = th.scrollHeight;
    state.hasMore = !!r.has_more;
    if (btn) btn.remove();
    if (state.hasMore) th.insertAdjacentHTML('afterbegin', '<button type="button" class="more-btn" id="moreBtn">Показать раньше</button>');
    appendMessages(r.messages, 'older');
    th.scrollTop = th.scrollHeight - prevH;   // держим позицию просмотра
    return true;
  }

  /* Поллинг. Раньше шапка обновлялась только при новых сообщениях — из-за этого
     галочки «прочитано» не синели, пока собеседник не напишет сам. */
  async function pollThread() {
    if (!state.convId || !state.lastId) return;
    const cid = state.convId;
    const r = await api('thread', { conversation_id: cid, after_id: state.lastId, seen: isSeen() });
    if (!r.ok || cid !== state.convId) return;
    applyHeader(r.header);
    if (r.pins) setPins(r.pins);
    if (r.wallpaper !== undefined) applyWallpaper(r.wallpaper, true);
    if (!r.messages.length) return;
    const th = $('#thread');
    const atBottom = th.scrollHeight - th.scrollTop - th.clientHeight < 80;
    const gotIncoming = r.messages.some(m => !m.mine);
    appendMessages(r.messages, 'beforeend');
    if (atBottom) th.scrollTop = th.scrollHeight;
    if (gotIncoming) ping();
    loadList();
  }

  function appendMessages(msgs, where) {
    if (!msgs.length) return;
    const th = $('#thread');
    th.querySelector(':scope > .empty')?.remove();
    msgs.forEach(m => state.msgs.set(m.id, m));
    if (where === 'older') {
      let html = '', lastDay = '';
      msgs.forEach(m => {
        const day = fmtDay(m.at);
        if (!state.isSystem && day !== lastDay) { html += `<div class="day">${day}</div>`; lastDay = day; }
        html += renderMsg(m);
        state.firstId = state.firstId ? Math.min(state.firstId, m.id) : m.id;
      });
      const anchor = $('#moreBtn');
      if (anchor) anchor.insertAdjacentHTML('afterend', html);
      else th.insertAdjacentHTML('afterbegin', html);
    } else {
      let html = '', lastDay = th.dataset.lastDay || '';
      msgs.forEach(m => {
        if (th.querySelector(`.msg[data-id="${m.id}"]`)) {        // уже отрисовано поллингом раньше ответа send
          if (m.tmp) th.querySelector(`.msg[data-tmp="${m.tmp}"]`)?.remove();
          return;
        }
        if (m.mine && m.tmp) { const p = th.querySelector(`.msg[data-tmp="${m.tmp}"]`); if (p) { p.outerHTML = renderMsg(m); state.lastId = Math.max(state.lastId, m.id); return; } }
        const day = fmtDay(m.at);
        if (!state.isSystem && day !== lastDay) { html += `<div class="day">${day}</div>`; lastDay = day; }
        html += renderMsg(m);
        state.lastId = Math.max(state.lastId, m.id);
        state.firstId = state.firstId ? Math.min(state.firstId, m.id) : m.id;
      });
      th.dataset.lastDay = lastDay;
      th.insertAdjacentHTML('beforeend', html);
    }
  }

  const TICK_SVG = '<svg viewBox="0 0 16 11" width="15" height="11" fill="none" aria-hidden="true">'
    + '<path class="tick-a" d="M1 5.3L4.2 8.5L9.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
    + '<path class="tick-b" d="M5.5 5.3L8.7 8.5L15 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
    + '</svg>';
  /* Статусы своего сообщения, как в TG:
       часики  — ещё летит на сервер      ! — не ушло, тап = повторить
       ✓       — сервер принял           ✓✓ тусклые — дошло до устройства собеседника
       ✓✓ яркие — собеседник открыл диалог и увидел */
  const CLOCK_SVG = '<svg viewBox="0 0 16 16" width="13" height="13" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.4"/><path class="clock-h" d="M8 4.6V8l2.2 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
  const tickState = id => state.peerLastRead >= id ? 'read' : state.peerDelivered >= id ? 'dlv' : 'sent';
  const TICK_LABEL = { sent: 'Отправлено', dlv: 'Доставлено', read: 'Прочитано' };
  const renderTicks = id => { const t = tickState(id); return `<span class="ticks ${t}" data-mid="${id}" title="${TICK_LABEL[t]}">${TICK_SVG}</span>`; };
  function updateReadTicks() {
    document.querySelectorAll('#thread .ticks[data-mid]').forEach(el => {
      const t = tickState(+el.dataset.mid);
      if (!el.classList.contains(t)) { el.className = 'ticks ' + t; el.title = TICK_LABEL[t]; }
    });
  }

  function renderFile(f) {
    if (f.kind === 'image') {
      const ratio = f.w && f.h ? `aspect-ratio:${f.w}/${f.h};` : '';
      return `<button type="button" class="m-img" style="${ratio}" data-full="${esc(f.url)}" data-name="${esc(f.name)}">
      <img src="${esc(f.thumb || f.url)}" alt="" loading="lazy" draggable="false"></button>`;
    }
    return `<a class="m-file" href="${esc(f.url)}" target="_blank" rel="noopener" download="${esc(f.name)}">
    <span class="mf-ic">${ICON.file}</span>
    <span class="mf-main"><span class="mf-name">${esc(f.name)}</span><span class="mf-size">${fmtSize(f.size)}</span></span>
    <span class="mf-dl">${ICON.dl}</span></a>`;
  }

  function renderNotif(m) {
    const link = safeLink(m.link);
    return `<div class="notif${m.unread ? ' unread' : ''}" data-id="${m.id}">
    <div class="n-ico">${BELL}</div>
    <div class="n-main">
      ${m.title ? `<div class="n-title">${esc(m.title)}</div>` : ''}
      <div class="n-body">${esc(m.body)}</div>
      <div class="n-foot"><span class="n-time">${fmtDay(m.at)}, ${fmtTime(m.at)}</span>${link ? `<a class="n-link" href="${esc(link)}">Открыть →</a>` : ''}</div>
    </div>
  </div>`;
  }

  function renderMsg(m) {
    if (state.isSystem) return renderNotif(m);
    const side = m.mine ? 'mine' : 'them';
    if (m.deleted) return `<div class="msg ${side}" data-id="${m.id}"><div class="bubble gone">сообщение удалено</div></div>`;
    const quote = m.reply
      ? `<button type="button" class="quote${m.reply.deleted ? ' gone' : ''}" data-jump="${m.reply.id}">
         <b>${esc(m.reply.mine ? 'Вы' : m.reply.name)}</b><span>${esc(m.reply.text)}</span></button>`
      : '';
    const media = m.file ? renderFile(m.file) : '';
    const text = m.body ? `<div class="b-text">${linkify(esc(m.body))}</div>` : '';
    const onlyImg = m.file && m.file.kind === 'image' && !m.body && !m.reply;
    const ticks = m.mine ? renderTicks(m.id) : '';
    return `<div class="msg ${side}" data-id="${m.id}">
    <span class="swipe-ic" aria-hidden="true">${ICON.reply}</span>
    <div class="bubble${onlyImg ? ' media' : ''}">${quote}${media}${text}<span class="b-time">${fmtTime(m.at)}${ticks}</span></div>
    <button type="button" class="m-more" aria-label="Действия">${ICON.more}</button>
  </div>`;
  }

  /* Переход к сообщению (цитата, пин): догружаем историю, пока не найдём. */
  async function jumpTo(id) {
    let el = document.querySelector(`#thread .msg[data-id="${id}"]`);
    for (let i = 0; !el && state.hasMore && i < 15; i++) {
      if (!(await loadOlder())) break;
      el = document.querySelector(`#thread .msg[data-id="${id}"]`);
    }
    if (!el) { toast('Сообщение не найдено'); return; }
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el.classList.remove('flash'); void el.offsetWidth; el.classList.add('flash');
  }

  $('#thread').addEventListener('click', e => {
    if (e.target.closest('#moreBtn')) { loadOlder(); return; }
    const rt = e.target.closest('.m-retry'); if (rt) { retryPending(rt.closest('.msg').dataset.tmp); return; }
    const dr = e.target.closest('.m-drop'); if (dr) { dropPending(dr.closest('.msg').dataset.tmp); return; }
    const q = e.target.closest('.quote'); if (q) { jumpTo(+q.dataset.jump); return; }
    const img = e.target.closest('.m-img'); if (img) { openLightbox(img.dataset.full, img.dataset.name); return; }
    const more = e.target.closest('.m-more');
    if (more) { const r = more.getBoundingClientRect(); openMsgMenu(+more.closest('.msg[data-id]')?.dataset.id, r.left, r.bottom); }
  });

  /* ── Лайтбокс ── */
  function openLightbox(url, name) {
    const lb = $('#lightbox');
    lb.querySelector('img').src = url;
    $('#lbDl').href = url; $('#lbDl').download = name || '';
    lb.hidden = false;
  }
  $('#lightbox').addEventListener('click', e => { if (!e.target.closest('#lbDl')) { $('#lightbox').hidden = true; $('#lightbox img').removeAttribute('src'); } });

  /* ════════════════════════ ОТВЕТЫ И СВАЙП ═════════════════════════════════ */
  function startReply(id) {
    const m = state.msgs.get(id); if (!m || m.deleted || state.isSystem) return;
    const text = m.body || (m.file ? (m.file.kind === 'image' ? '🖼 Фото' : '📎 ' + m.file.name) : '');
    state.replyTo = { id, name: m.mine ? 'Вы' : m.sender.name, text };
    $('#rbName').textContent = 'Ответ ' + (m.mine ? 'себе' : m.sender.name);
    $('#rbText').textContent = text.length > 120 ? text.slice(0, 119) + '…' : text;
    $('#replyBar').hidden = false;
    $('#input').focus();
    syncQuick();
  }
  function cancelReply() { state.replyTo = null; $('#replyBar').hidden = true; syncQuick(); }
  $('#rbX').addEventListener('click', cancelReply);

  /* Свайп вправо на тач-экране (как в Telegram) и горизонтальный жест тачпада.
     .msg имеет touch-action: pan-y — вертикальный скролл остаётся браузеру,
     горизонталь приходит к нам в pointermove. */
  const SWIPE_MAX = 76, SWIPE_TRIGGER = 56;
  let sw = null, lpTimer = null;

  function swipeSet(msg, dx) {
    msg.style.transform = dx ? `translateX(${dx}px)` : '';
    msg.classList.toggle('swipe-ready', Math.abs(dx) >= SWIPE_TRIGGER);
    msg.style.setProperty('--swipe', Math.min(1, Math.abs(dx) / SWIPE_TRIGGER));
  }
  function swipeRelease(msg, dx) {
    msg.classList.add('swipe-back');
    swipeSet(msg, 0);
    setTimeout(() => msg.classList.remove('swipe-back'), 220);
    if (Math.abs(dx) >= SWIPE_TRIGGER) { navigator.vibrate?.(8); startReply(+msg.dataset.id); }
  }

  const thread = $('#thread');
  thread.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'touch' || state.isSystem) return;
    const msg = e.target.closest('.msg[data-id]'); if (!msg || msg.querySelector('.gone')) return;
    sw = { msg, x: e.clientX, y: e.clientY, dx: 0, axis: null, id: e.pointerId };
    clearTimeout(lpTimer);
    lpTimer = setTimeout(() => {               // долгий тап = наше меню, не системное
      if (sw && !sw.axis) { navigator.vibrate?.(12); openMsgMenu(+msg.dataset.id, sw.x, sw.y); sw = null; }
    }, 450);
  });
  thread.addEventListener('pointermove', e => {
    if (!sw || e.pointerId !== sw.id) return;
    const dx = e.clientX - sw.x, dy = e.clientY - sw.y;
    if (!sw.axis && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
      sw.axis = Math.abs(dx) > Math.abs(dy) && dx > 0 ? 'x' : 'y';
      clearTimeout(lpTimer);
    }
    if (sw.axis === 'x') { sw.dx = Math.max(0, Math.min(SWIPE_MAX, dx)); swipeSet(sw.msg, sw.dx); }
  });
  const endSwipe = e => {
    clearTimeout(lpTimer);
    if (!sw || (e.pointerId !== undefined && e.pointerId !== sw.id)) return;
    if (sw.axis === 'x') swipeRelease(sw.msg, e.type === 'pointercancel' ? 0 : sw.dx);
    sw = null;
  };
  thread.addEventListener('pointerup', endSwipe);
  thread.addEventListener('pointercancel', endSwipe);

  // тачпад: горизонтальная прокрутка над сообщением
  let wheel = null;
  thread.addEventListener('wheel', e => {
    if (state.isSystem || Math.abs(e.deltaX) <= Math.abs(e.deltaY) * 1.2) return;
    const msg = e.target.closest('.msg[data-id]'); if (!msg || msg.querySelector('.gone')) return;
    e.preventDefault();
    if (!wheel || wheel.msg !== msg) { if (wheel) swipeRelease(wheel.msg, 0); wheel = { msg, acc: 0, done: false }; }
    if (wheel.done) return;
    wheel.acc = Math.max(-SWIPE_MAX, Math.min(SWIPE_MAX, wheel.acc - e.deltaX));
    swipeSet(msg, wheel.acc);
    clearTimeout(wheel.t);
    if (Math.abs(wheel.acc) >= SWIPE_TRIGGER) { wheel.done = true; const w = wheel; setTimeout(() => { swipeRelease(w.msg, w.acc); wheel = null; }, 60); return; }
    wheel.t = setTimeout(() => { swipeRelease(msg, 0); wheel = null; }, 160);
  }, { passive: false });

  /* ════════════════════════ ПИНЫ ═══════════════════════════════════════════ */
  function setPins(pins) {
    const sig = JSON.stringify(pins || []);
    if (sig === state._pinSig) return;
    state._pinSig = sig;
    state.pins = pins || [];
    if (state.pinIdx >= state.pins.length) state.pinIdx = 0;
    renderPins();
  }
  function renderPins() {
    const bar = $('#pinbar');
    if (!state.pins.length || state.isSystem || !state.convId) { bar.hidden = true; return; }
    const p = state.pins[state.pinIdx];
    bar.hidden = false;
    $('#pbTitle').textContent = state.pins.length > 1 ? `Закреплённое · ${state.pinIdx + 1} из ${state.pins.length}` : 'Закреплённое';
    $('#pbText').textContent = (p.name ? p.name + ': ' : '') + p.text;
    $('#pbLine').style.setProperty('--n', state.pins.length);
  }
  $('#pinbar').addEventListener('click', e => {
    const p = state.pins[state.pinIdx]; if (!p) return;
    if (e.target.closest('#pbX')) { togglePin(p.id, false); return; }
    jumpTo(p.id);
    if (state.pins.length > 1) { state.pinIdx = (state.pinIdx + 1) % state.pins.length; renderPins(); }  // как в TG: клик листает
  });
  async function togglePin(id, pin) {
    const r = await api(pin ? 'pin' : 'unpin', { message_id: id }, 'POST');
    if (!r.ok) { toast(r.error === 'migration' ? 'Нужна миграция чата v2' : 'Не удалось', true); return; }
    state.pinIdx = 0; setPins(r.pins);
    toast(pin ? 'Сообщение закреплено' : 'Откреплено');
  }

  /* ════════════════════════ КОНТЕКСТНОЕ МЕНЮ ═══════════════════════════════
     ПКМ на ПК и долгий тап на телефоне открывают одно и то же меню. */
  function openCtx(items, x, y) {
    const c = $('#ctx');
    c.innerHTML = items.map((it, i) => `<button type="button" class="${it.danger ? 'danger' : ''}" data-i="${i}">${it.icon || ''}<span>${esc(it.label)}</span></button>`).join('');
    c.hidden = false;
    const w = c.offsetWidth, h = c.offsetHeight;
    c.style.left = Math.max(8, Math.min(x, innerWidth - w - 8)) + 'px';
    c.style.top = Math.max(8, Math.min(y, innerHeight - h - 8)) + 'px';
    c.onclick = e => { const b = e.target.closest('button'); if (!b) return; closeCtx(); items[+b.dataset.i].run(); };
  }
  function closeCtx() { $('#ctx').hidden = true; }
  document.addEventListener('pointerdown', e => { if (!e.target.closest('#ctx')) closeCtx(); }, true);
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeCtx(); $('#lightbox').hidden = true; $('#settings').hidden = true; closeWallpaper();
    if (state.replyTo) cancelReply();
  });
  thread.addEventListener('scroll', closeCtx, { passive: true });

  function openMsgMenu(id, x, y) {
    const m = state.msgs.get(id); if (!m || m.deleted || state.isSystem) return;
    const pinned = state.pins.some(p => p.id === id);
    const items = [{ label: 'Ответить', icon: ICON.reply, run: () => startReply(id) }];
    if (state.v2) items.push({ label: pinned ? 'Открепить' : 'Закрепить', icon: ICON.pin, run: () => togglePin(id, !pinned) });
    if (m.body) items.push({ label: 'Копировать текст', icon: ICON.copy, run: () => navigator.clipboard?.writeText(m.body).then(() => toast('Скопировано')) });
    if (m.file) items.push({
      label: m.file.kind === 'image' ? 'Открыть картинку' : 'Скачать файл', icon: ICON.dl,
      run: () => m.file.kind === 'image' ? openLightbox(m.file.url, m.file.name) : window.open(m.file.url, '_blank')
    });
    if (m.mine) items.push({ label: 'Удалить', icon: ICON.del, danger: true, run: () => deleteMessage(id) });
    openCtx(items, x, y);
  }
  thread.addEventListener('contextmenu', e => {
    const msg = e.target.closest('.msg[data-id]');
    if (!msg || e.target.closest('a')) return;          // по ссылке — обычное меню браузера на ПК
    e.preventDefault();
    if (!isTouch) openMsgMenu(+msg.dataset.id, e.clientX, e.clientY);
  });

  async function deleteMessage(id) {
    if (!confirm('Удалить сообщение?')) return;
    const r = await api('delete_message', { message_id: id }, 'POST');
    if (!r.ok) { toast('Не удалось удалить', true); return; }
    const m = state.msgs.get(id); if (m) m.deleted = true;
    const el = document.querySelector(`#thread .msg[data-id="${id}"]`);
    if (el) el.outerHTML = renderMsg({ ...m, deleted: true });
    if (state.pins.some(p => p.id === id)) setPins(state.pins.filter(p => p.id !== id));
    loadList();
  }

  /* меню беседы в списке */
  function openCardMenu(card, x, y) {
    const id = +card.dataset.id, isSys = card.dataset.system === '1';
    const items = [];
    if (+card.dataset.unread) items.push({ label: 'Отметить прочитанным', icon: ICON.read, run: () => markRead(id) });
    if (!isSys) items.push({ label: 'Удалить переписку', icon: ICON.del, danger: true, run: () => deleteConv(id) });
    if (items.length) openCtx(items, x, y);
  }
  $('#list').addEventListener('contextmenu', e => {
    const card = e.target.closest('.card'); if (!card) return;
    e.preventDefault();
    if (!isTouch) openCardMenu(card, e.clientX, e.clientY);
  });
  let cardLp = null;
  $('#list').addEventListener('pointerdown', e => {
    if (e.pointerType !== 'touch') return;
    const card = e.target.closest('.card'); if (!card) return;
    const x = e.clientX, y = e.clientY;
    cardLp = setTimeout(() => { navigator.vibrate?.(12); card.dataset.lp = '1'; openCardMenu(card, x, y); }, 450);
  });
  ['pointerup', 'pointercancel', 'pointermove'].forEach(t => $('#list').addEventListener(t, e => {
    if (t === 'pointermove' && e.pointerType === 'touch' && Math.abs(e.movementX) + Math.abs(e.movementY) < 3) return;
    clearTimeout(cardLp);
  }));
  // после долгого тапа не открываем беседу «кликом»
  $('#list').addEventListener('click', e => { const c = e.target.closest('.card'); if (c?.dataset.lp) { e.stopImmediatePropagation(); delete c.dataset.lp; } }, true);

  async function markRead(id) {
    const r = await api('mark_read', { conversation_id: id }, 'POST');
    if (r.ok) loadList(); else toast('Не удалось', true);
  }
  async function markAllRead() {
    const r = await api('mark_all_read', { tab: state.tab }, 'POST');
    if (r.ok) { toast('Всё прочитано'); loadList(); } else toast('Не удалось', true);
  }
  async function deleteConv(id) {
    if (!confirm('Удалить переписку у себя? Новое сообщение вернёт её в список.')) return;
    const r = await api('delete_conversation', { conversation_id: id }, 'POST');
    if (!r.ok) { toast('Не удалось удалить', true); return; }
    if (id === state.convId) { $('#app').classList.remove('show-room'); state.convId = 0; clearInterval(state.threadTimer); showRoom(false); }
    loadList();
  }

  $('#sideMore').addEventListener('click', e => {
    const r = e.currentTarget.getBoundingClientRect();
    openCtx([
      { label: 'Отметить всё как прочитанное', icon: ICON.read, run: markAllRead },
      { label: 'Звук и уведомления', icon: ICON.gear, run: openSettings },
    ], r.right - 240, r.bottom + 6);
  });

  /* ════════════════════════ ВЛОЖЕНИЯ ═══════════════════════════════════════
     Как в Telegram: превью делает клиент, файл летит прямо в хранилище по
     подписанной ссылке, сервер только сверяет результат. Если S3 не принял PUT
     (CORS на локалке), тот же файл уходит запасным путём через PHP. */
  function xhr(method, url, body, headers, onProgress) {
    return new Promise((res, rej) => {
      const x = new XMLHttpRequest();
      x.open(method, url);
      Object.entries(headers || {}).forEach(([k, v]) => x.setRequestHeader(k, v));
      if (onProgress) x.upload.onprogress = e => e.lengthComputable && onProgress(e.loaded / e.total);
      x.onload = () => (x.status >= 200 && x.status < 300) ? res(x.responseText) : rej(new Error('HTTP ' + x.status));
      x.onerror = () => rej(new Error('network'));
      x.send(body);
    });
  }

  async function makeThumb(file) {
    if (file.type === 'image/gif' || !window.createImageBitmap) return { blob: null, w: 0, h: 0 };   // gif: не замораживаем анимацию
    const bmp = await createImageBitmap(file);
    const w = bmp.width, h = bmp.height, k = Math.min(1, 480 / Math.max(w, h));
    const c = document.createElement('canvas');
    c.width = Math.round(w * k); c.height = Math.round(h * k);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise(r => c.toBlob(r, 'image/webp', 0.82));
    return { blob: blob && blob.type === 'image/webp' ? blob : null, w, h };  // Safari не умеет webp → без превью
  }

  async function uploadFile(file, purpose, onProgress) {
    const mime = file.type || 'application/octet-stream';
    const init = await api('upload_init', { name: file.name, size: file.size, mime, purpose }, 'POST');
    if (!init.ok) throw init;
    let thumb = null, w = 0, h = 0;
    if (init.kind === 'image') { try { ({ blob: thumb, w, h } = await makeThumb(file)); } catch (e) { } }

    try {
      await xhr('PUT', init.put_url, file, { 'Content-Type': mime }, onProgress);
      if (thumb && init.thumb_url) { try { await xhr('PUT', init.thumb_url, thumb, { 'Content-Type': 'image/webp' }); } catch (e) { thumb = null; } }
      const c = await api('upload_commit', { file_id: init.file_id, w, h, thumb: thumb ? 1 : 0 }, 'POST');
      if (c.ok) return c.file;
    } catch (e) { /* ниже — запасной путь */ }

    const fd = new FormData();
    fd.append('action', 'upload_proxy'); fd.append('file_id', init.file_id); fd.append('w', w); fd.append('h', h);
    fd.append('file', file, file.name);
    if (thumb) fd.append('thumb', thumb, 'thumb.webp');
    const r = JSON.parse(await xhr('POST', API, fd, null, onProgress));
    if (!r.ok) throw r;
    return r.file;
  }

  const uploadError = e => ({
    too_big: 'Файл больше 50 МБ', bad_type: 'Такой тип файла нельзя отправить',
    too_many: 'Слишком много загрузок, подождите', migration: 'Нужна миграция чата v2', network: 'Нет соединения'
  }[e && e.error] || 'Не удалось загрузить');

  function addFiles(list) {
    if (!state.convId && !state.draft) return;
    if (state.isSystem) return;
    [...list].slice(0, 10).forEach(file => {
      const a = {
        key: Math.random().toString(36).slice(2), file, status: 'uploading', progress: 0, dto: null,
        preview: /^image\//.test(file.type) ? URL.createObjectURL(file) : null
      };
      state.atts.push(a);
      uploadFile(file, 'chat', p => { a.progress = p; renderAtts(); })
        .then(dto => { a.status = 'ready'; a.dto = dto; })
        .catch(e => { a.status = 'error'; toast(uploadError(e), true); })
        .finally(() => { renderAtts(); autosize(); });
    });
    renderAtts(); autosize();
  }
  function renderAtts() {
    const tray = $('#attachTray');
    tray.hidden = !state.atts.length;
    syncQuick();
    tray.innerHTML = state.atts.map(a => `<div class="att ${a.status}" data-key="${a.key}">
      ${a.preview ? `<img src="${a.preview}" alt="" draggable="false">` : `<span class="att-ic">${ICON.file}</span>`}
      <span class="att-main"><span class="att-name">${esc(a.file.name)}</span>
        <span class="att-meta">${a.status === 'uploading' ? Math.round(a.progress * 100) + '%' : a.status === 'error' ? 'ошибка' : fmtSize(a.file.size)}</span></span>
      ${a.status === 'uploading' ? `<span class="att-bar" style="--p:${a.progress}"></span>` : ''}
      <button type="button" class="att-x" aria-label="Убрать">&times;</button>
    </div>`).join('');
  }
  $('#attachTray').addEventListener('click', e => {
    const x = e.target.closest('.att-x'); if (!x) return;
    const key = x.closest('.att').dataset.key;
    const a = state.atts.find(a => a.key === key);
    if (a?.preview) URL.revokeObjectURL(a.preview);
    state.atts = state.atts.filter(a => a.key !== key);
    renderAtts(); autosize();
  });
  function clearAtts() { state.atts.forEach(a => a.preview && URL.revokeObjectURL(a.preview)); state.atts = []; renderAtts(); }

  $('#attachBtn').addEventListener('click', () => $('#fileInput').click());
  $('#fileInput').addEventListener('change', e => { addFiles(e.target.files); e.target.value = ''; });
  $('#input').addEventListener('paste', e => {
    const files = [...(e.clipboardData?.files || [])];
    if (files.length) { e.preventDefault(); addFiles(files); }
  });
  // drag & drop файлов в комнату
  let dragDepth = 0;
  const room = $('#room');
  room.addEventListener('dragenter', e => { if (!e.dataTransfer?.types?.includes('Files') || !state.convId && !state.draft || state.isSystem) return; dragDepth++; $('#dropzone').hidden = false; });
  room.addEventListener('dragleave', () => { if (--dragDepth <= 0) { dragDepth = 0; $('#dropzone').hidden = true; } });
  room.addEventListener('dragover', e => { if (!$('#dropzone').hidden) e.preventDefault(); });
  room.addEventListener('drop', e => {
    if ($('#dropzone').hidden) return;
    e.preventDefault(); dragDepth = 0; $('#dropzone').hidden = true;
    addFiles(e.dataTransfer.files);
  });

  /* ════════════════════════ ОТПРАВКА ═══════════════════════════════════════
     Оптимистичная очередь, как в TG: пузырь с часиками появляется мгновенно,
     поле ввода сразу свободно — можно писать следующее, пока летит предыдущее.
     Очередь строго последовательная (порядок сообщений = порядок набора).
     Не ушло — пузырь краснеет, ↻ повторяет, ✕ убирает.
     Несколько вложений = несколько сообщений (подпись и ответ — у первого).
     Антифлуд сервера — 5 сообщений за 5 секунд; упёрлись — ждём и повторяем. */
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const outbox = [];                 // очередь на отправку
  const failed = new Map();          // tmp → неотправленное
  let pumping = false, tmpSeq = 0, inflight = null;

  function renderPending(p) {
    const quote = p.reply ? `<div class="quote"><b>${esc(p.reply.name)}</b><span>${esc(p.reply.text)}</span></div>` : '';
    let media = '';
    if (p.att) media = p.att.kind === 'image' && p.att.preview
      ? `<div class="m-img"><img src="${esc(p.att.preview)}" alt="" draggable="false"></div>`
      : `<div class="m-file"><span class="mf-ic">${ICON.file}</span><span class="mf-main"><span class="mf-name">${esc(p.att.name)}</span><span class="mf-size">${fmtSize(p.att.size)}</span></span></div>`;
    const onlyImg = p.att && p.att.kind === 'image' && !p.body && !p.reply;
    const text = p.body ? `<div class="b-text">${linkify(esc(p.body))}</div>` : '';
    const bad = p.status === 'failed';
    const mark = bad ? '<span class="ticks fail" title="Не отправлено">!</span>' : `<span class="ticks clock" title="Отправляется">${CLOCK_SVG}</span>`;
    return `<div class="msg mine pending${bad ? ' failed' : ''}" data-tmp="${p.tmp}">
    <div class="bubble${onlyImg ? ' media' : ''}">${quote}${media}${text}<span class="b-time">${fmtTime(new Date())}${mark}</span></div>
    ${bad ? `<span class="m-fail"><button type="button" class="m-retry" aria-label="Повторить">${ICON.retry}</button><button type="button" class="m-drop" aria-label="Убрать">&times;</button></span>` : ''}
  </div>`;
  }
  function paintPending(p) {
    const el = document.querySelector(`#thread .msg[data-tmp="${p.tmp}"]`);
    if (el) el.outerHTML = renderPending(p);
    else if (p.convId && p.convId === state.convId) {
      const th = $('#thread');
      th.querySelector(':scope > .empty')?.remove();
      th.insertAdjacentHTML('beforeend', renderPending(p));
      th.scrollTop = th.scrollHeight;
    }
  }

  function send() {
    const inp = $('#input');
    const body = inp.value.trim();
    const ready = state.atts.filter(a => a.status === 'ready');
    if ((!body && !ready.length) || (!state.convId && !state.draft)) return;
    if (state.atts.some(a => a.status === 'uploading')) { toast('Дождитесь загрузки файлов'); return; }

    const target = state.convId > 0 ? { conversation_id: state.convId }
      : (state.draft.to ? { to: state.draft.to } : { studio: state.draft.studio });
    const reply = state.replyTo;
    const parts = ready.length ? ready.map((a, i) => ({ att: a, body: i === 0 ? body : '' })) : [{ att: null, body }];
    parts.forEach((x, i) => {
      const p = {
        tmp: 't' + (++tmpSeq), convId: state.convId, target, body: x.body, status: 'pending',
        file_id: x.att ? x.att.dto.id : 0,
        att: x.att ? { kind: x.att.dto.kind, name: x.att.dto.name, size: x.att.dto.size, preview: x.att.preview } : null,
        reply: i === 0 && reply ? reply : null, backup: i === 0 ? inp.value : '',
      };
      outbox.push(p);
      paintPending(p);
    });
    // композер свободен сразу; blob-превью живёт до ответа сервера
    state.atts = state.atts.filter(a => !ready.includes(a)); renderAtts();
    inp.value = ''; cancelReply(); autosize();
    pump();
  }

  async function pump() {
    if (pumping) return;
    pumping = true;
    while (outbox.length) {
      const p = inflight = outbox.shift();
      const params = { ...p.target, body: p.body };
      if (p.file_id) params.file_id = p.file_id;
      if (p.reply) params.reply_to = p.reply.id;
      let r = await api('send', params, 'POST');
      for (let t = 0; !r.ok && (r.error === 'too_fast' || r.error === 'network') && t < 3; t++) {
        await sleep(r.error === 'network' ? 2000 : 1200);
        r = await api('send', params, 'POST');
      }
      inflight = null;
      if (!r.ok) { failPending(p, r.error); continue; }
      if (p.att && p.att.preview) URL.revokeObjectURL(p.att.preview);
      if (!p.convId) {
        // первое сообщение нового диалога: беседа появилась — открываем её по-настоящему
        outbox.forEach(q => { if (!q.convId && JSON.stringify(q.target) === JSON.stringify(p.target)) { q.convId = r.conversation_id; q.target = { conversation_id: r.conversation_id }; } });
        if (!state.convId && state.draft) {
          state.convId = r.conversation_id; state.lastId = 0; state.firstId = 0; state.draft = null;
          await loadInitial(); startThreadTimer();
          outbox.forEach(paintPending);
        }
      } else if (p.convId === state.convId) {
        appendMessages([{ ...r.message, tmp: p.tmp }], 'beforeend');
        const th = $('#thread'); th.scrollTop = th.scrollHeight;
      }
    }
    pumping = false;
    loadList();
  }

  function failPending(p, err) {
    const why = err === 'too_fast' ? 'Слишком часто, подождите секунду'
      : err === 'network' ? 'Нет соединения — сообщение не ушло'
        : err === 'too_long' ? 'Сообщение длиннее 4000 символов'
          : 'Не удалось отправить';
    toast(why, true);
    if (!p.convId) {                       // черновик диалога: пузыря нет — вернём текст в поле
      if (p.backup && !$('#input').value) { $('#input').value = p.backup; autosize(); }
      return;
    }
    p.status = 'failed'; failed.set(p.tmp, p); paintPending(p);
  }
  function retryPending(tmp) {
    const p = failed.get(tmp); if (!p) return;
    failed.delete(tmp); p.status = 'pending'; paintPending(p);
    outbox.push(p); pump();
  }
  function dropPending(tmp) {
    const p = failed.get(tmp); failed.delete(tmp);
    if (p?.att?.preview) URL.revokeObjectURL(p.att.preview);
    document.querySelector(`#thread .msg[data-tmp="${tmp}"]`)?.remove();
  }

  function autosize() {
    const t = $('#input');
    t.style.height = 'auto';
    t.style.height = Math.min(t.scrollHeight, 140) + 'px';
    const hasReady = state.atts.some(a => a.status === 'ready');
    $('#send').disabled = (!t.value.trim() && !hasReady) || state.atts.some(a => a.status === 'uploading');
    syncQuick();
  }

  /* Быстрые ответы над композером (телефон): видны, пока поле пустое, нет цитаты и вложений */
  function syncQuick() {
    const q = $('#quick'); if (!q) return;
    q.hidden = !(state.convId || state.draft) || state.isSystem || !!$('#input').value || !!state.replyTo || state.atts.length > 0;
  }
  $('#quick')?.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    $('#input').value = b.dataset.q; autosize(); send();
  });
  $('#input').addEventListener('input', autosize);
  $('#input').addEventListener('keydown', e => {
    // на телефоне Enter = перенос строки, отправка — кнопкой (как в TG)
    if (e.key === 'Enter' && !e.shiftKey && !isTouch) { e.preventDefault(); send(); }
    if (e.key === 'ArrowUp' && !e.target.value) {          // ↑ в пустом поле — ответить на последнее входящее
      const last = [...state.msgs.values()].reverse().find(m => !m.mine && !m.deleted);
      if (last) { e.preventDefault(); startReply(last.id); }
    }
  });
  $('#send').addEventListener('click', send);
  $('#back').addEventListener('click', () => {
    $('#app').classList.remove('show-room');
    state.convId = 0; clearInterval(state.threadTimer); showRoom(false);
  });

  /* ── Меню комнаты ── */
  $('#menuBtn').addEventListener('click', e => { e.stopPropagation(); $('#menu').hidden = !$('#menu').hidden; });
  document.addEventListener('click', () => { $('#menu').hidden = true; });
  $('#menu').addEventListener('click', e => e.stopPropagation());
  $('#markRead').addEventListener('click', () => { $('#menu').hidden = true; if (state.convId) markRead(state.convId); });
  $('#wpOpen').addEventListener('click', () => { $('#menu').hidden = true; if (state.convId) openWallpaper(); });
  $('#delConv').addEventListener('click', () => { $('#menu').hidden = true; if (state.convId) deleteConv(state.convId); });

  /* ════════════════════════ ОБОИ ═══════════════════════════════════════════
     Пресеты — чистый CSS (слои градиентов): ноль запросов, ноль байт, чёткие
     на любом DPI. Свои фото — обычное вложение chat_files через file.php.
     Общие обои видят оба, личные перекрывают общие только у тебя. Затемнение —
     отдельный слой поверх картинки, чтобы пузыри читались на любом фото. */
  const WP = {
    aurora: { img: 'radial-gradient(60% 50% at 18% 12%, rgba(230,55,154,.42), transparent 70%), radial-gradient(55% 45% at 88% 82%, rgba(34,211,238,.28), transparent 70%), linear-gradient(160deg, #1d0628, #0b0512)', size: 'cover' },
    dunes: { img: 'radial-gradient(120% 55% at 25% 108%, #b4532f 0 44%, transparent 44.5%), radial-gradient(110% 50% at 85% 112%, #7c2d3f 0 46%, transparent 46.5%), radial-gradient(30% 22% at 70% 28%, rgba(255,200,120,.55), transparent 70%), linear-gradient(180deg, #2a0d33 0%, #7d2f47 55%, #d9794b 100%)', size: 'cover' },
    synth: { img: 'linear-gradient(rgba(230,55,154,.22) 1px, transparent 1px), linear-gradient(90deg, rgba(230,55,154,.22) 1px, transparent 1px), radial-gradient(90% 60% at 50% 0%, #4a0f5c, #0c0414 70%)', size: '32px 32px, 32px 32px, cover' },
    stars: { img: 'radial-gradient(1.2px 1.2px at 22px 34px, #fff, transparent), radial-gradient(1px 1px at 92px 128px, rgba(255,255,255,.75), transparent), radial-gradient(1.6px 1.6px at 158px 62px, #fff, transparent), radial-gradient(1px 1px at 118px 184px, rgba(255,255,255,.6), transparent), radial-gradient(1px 1px at 190px 150px, rgba(255,210,240,.8), transparent), linear-gradient(180deg, #0a0618, #1d0b31)', size: '130px 130px, 170px 170px, 150px 150px, 110px 110px, 190px 190px, cover' },
    mesh: { img: 'radial-gradient(40% 40% at 15% 25%, rgba(245,185,66,.35), transparent 70%), radial-gradient(45% 45% at 80% 20%, rgba(195,33,120,.45), transparent 70%), radial-gradient(50% 50% at 60% 90%, rgba(62,122,217,.4), transparent 70%), linear-gradient(135deg, #140a22, #0d0a18)', size: 'cover' },
    noir: { img: 'radial-gradient(120% 80% at 50% 0%, #2a2630, #0b0a0e 70%)', size: 'cover' },
    sunset: { img: 'radial-gradient(28% 20% at 50% 72%, rgba(255,214,140,.7), transparent 70%), linear-gradient(180deg, #1f0833 0%, #6d1a5a 42%, #d9566e 78%, #f5b942 100%)', size: 'cover' },
    ocean: { img: 'radial-gradient(70% 50% at 28% 18%, rgba(34,211,238,.3), transparent 70%), radial-gradient(60% 40% at 80% 90%, rgba(62,122,217,.35), transparent 70%), linear-gradient(170deg, #04202b, #062f44 50%, #0a1426)', size: 'cover' },
  };
  const WP_NAMES = { aurora: 'Аврора', dunes: 'Дюны', synth: 'Синтвейв', stars: 'Звёзды', mesh: 'Туманность', noir: 'Нуар', sunset: 'Закат', ocean: 'Океан' };

  /** { preset | url, dim } → стили фона. null — без обоев (родной паттерн треда). */
  function wpStyle(w) {
    if (!w || w.preset === 'none' || (!w.preset && !w.url)) return null;
    const d = (w.dim || 0) / 100;
    const shade = `linear-gradient(rgba(6,2,10,${d}), rgba(6,2,10,${d}))`;
    if (w.url) return { backgroundImage: `${shade}, url("${w.url}")`, backgroundSize: 'auto, cover', backgroundPosition: 'center' };
    const p = WP[w.preset]; if (!p) return null;
    return { backgroundImage: `${shade}, ${p.img}`, backgroundSize: 'auto, ' + p.size, backgroundPosition: 'center' };
  }
  function paintBg(el, w) {
    const st = wpStyle(w);
    el.style.backgroundImage = st ? st.backgroundImage : '';
    el.style.backgroundSize = st ? st.backgroundSize : '';
    el.style.backgroundPosition = st ? st.backgroundPosition : '';
    return !!st;
  }
  const wpSig = w => w ? JSON.stringify([w.scope, w.active && [w.active.preset, w.active.url, w.active.dim, w.active.ts]]) : '';

  function applyWallpaper(w, fromPoll) {
    const prev = state.wallpaper;
    if (fromPoll && wpSig(prev) === wpSig(w)) return;
    state.wallpaper = w || null;
    const on = paintBg($('#thread'), w && w.active);
    $('#room').classList.toggle('has-wp', on);
    // собеседник поменял ОБЩИЕ обои, пока диалог открыт — скажем, откуда красота
    if (fromPoll && prev && w?.shared && !w.shared.by_me && !w.mine
      && JSON.stringify(prev.shared) !== JSON.stringify(w.shared)) toast('Собеседник сменил обои чата');
  }

  const wpSel = { scope: 'shared', preset: null, url: null, file_id: 0, dim: 0 };
  function openWallpaper() {
    const w = state.wallpaper || {};
    const cur = w.mine || w.shared || null;
    wpSel.scope = w.mine ? 'mine' : 'shared';
    wpSel.preset = cur ? cur.preset : 'none';
    wpSel.url = cur ? cur.url : null; wpSel.file_id = cur ? (cur.file_id || 0) : 0;
    wpSel.dim = cur ? cur.dim : 0;
    $('#wpDim').value = wpSel.dim;
    renderWallpaper();
    $('#wpModal').hidden = false;
  }
  function closeWallpaper() { $('#wpModal').hidden = true; }
  function renderWallpaper() {
    const w = state.wallpaper || {};
    document.querySelectorAll('.wp-scope button').forEach(b => b.classList.toggle('on', b.dataset.scope === wpSel.scope));
    const tiles = Object.keys(WP).map(k => `<button type="button" class="wp-tile${!wpSel.url && wpSel.preset === k ? ' on' : ''}" data-preset="${k}"><i></i><span>${WP_NAMES[k]}</span></button>`);
    const own = wpSel.url
      ? `<button type="button" class="wp-tile on" data-own="1"><i></i><span>Своё фото</span></button>`
      : '';
    $('#wpGrid').innerHTML = own + tiles.join('')
      + `<button type="button" class="wp-tile add" data-upload="1"><i>${ICON.wall}</i><span>Загрузить фото</span></button>`
      + `<button type="button" class="wp-tile none${!wpSel.url && wpSel.preset === 'none' ? ' on' : ''}" data-preset="none"><i></i><span>Без обоев</span></button>`;
    $('#wpGrid').querySelectorAll('.wp-tile[data-preset]').forEach(t => { if (t.dataset.preset !== 'none') paintBg(t.querySelector('i'), { preset: t.dataset.preset }); });
    const ownTile = $('#wpGrid').querySelector('[data-own] i'); if (ownTile) paintBg(ownTile, { url: wpSel.url });
    paintBg($('#wpPreview'), { preset: wpSel.url ? null : wpSel.preset, url: wpSel.url, dim: wpSel.dim });
    const peerName = state.header?.peer?.name || 'собеседник';
    $('#wpNote').textContent = wpSel.scope === 'shared'
      ? `${peerName} тоже увидит эти обои${w.mine ? ' (ваши личные сейчас их перекрывают — они будут заменены)' : ''}.`
      : 'Видите только вы. Общие обои собеседника не меняются.';
    $('#wpResetMine').hidden = !w.mine;
  }
  $('#wpModal').addEventListener('click', e => { if (e.target.id === 'wpModal') closeWallpaper(); });
  $('#wpClose').addEventListener('click', closeWallpaper);
  document.querySelector('.wp-scope').addEventListener('click', e => {
    const b = e.target.closest('button[data-scope]'); if (!b) return;
    wpSel.scope = b.dataset.scope; renderWallpaper();
  });
  $('#wpGrid').addEventListener('click', e => {
    const t = e.target.closest('.wp-tile'); if (!t) return;
    if (t.dataset.upload) { $('#wpInput').click(); return; }
    if (t.dataset.own) return;
    wpSel.preset = t.dataset.preset; wpSel.url = null; wpSel.file_id = 0;
    renderWallpaper();
  });
  $('#wpDim').addEventListener('input', e => { wpSel.dim = +e.target.value; paintBg($('#wpPreview'), { preset: wpSel.url ? null : wpSel.preset, url: wpSel.url, dim: wpSel.dim }); });
  $('#wpInput').addEventListener('change', async e => {
    const f = e.target.files[0]; e.target.value = '';
    if (!f) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(f.type)) { toast('Нужна картинка JPG, PNG, WebP или GIF', true); return; }
    if (f.size > 8 * 1024 * 1024) { toast('Фото до 8 МБ', true); return; }
    const add = $('#wpGrid').querySelector('[data-upload] span'); if (add) add.textContent = 'Загружаю…';
    try {
      const dto = await uploadFile(f, 'wallpaper', pct => { if (add) add.textContent = Math.round(pct * 100) + '%'; });
      wpSel.url = dto.url; wpSel.file_id = dto.id; wpSel.preset = null;
      if (!wpSel.dim) { wpSel.dim = 30; $('#wpDim').value = 30; }   // на фото без затемнения пузыри тонут
      renderWallpaper();
    } catch (err) {
      toast(err?.error === 'too_big' ? 'Фото до 8 МБ' : 'Не удалось загрузить фото', true);
      renderWallpaper();
    }
  });
  $('#wpApply').addEventListener('click', async () => {
    const p = { conversation_id: state.convId, scope: wpSel.scope, dim: wpSel.dim };
    if (wpSel.file_id) p.file_id = wpSel.file_id; else p.preset = wpSel.preset || 'none';
    const r = await api('wallpaper_set', p, 'POST');
    if (!r.ok) { toast(r.error === 'migration' ? 'Обои появятся после обновления чата' : 'Не удалось сохранить обои', true); return; }
    // личные «без обоев» не должны перекрывать выбранные общие: если выбрали для обоих — личные сбрасываем
    if (wpSel.scope === 'shared' && state.wallpaper?.mine) {
      const rr = await api('wallpaper_reset', { conversation_id: state.convId, scope: 'mine' }, 'POST');
      if (rr.ok) r.wallpaper = rr.wallpaper;
    }
    applyWallpaper(r.wallpaper);
    closeWallpaper();
    toast(wpSel.scope === 'shared' ? 'Обои установлены для обоих' : 'Обои установлены для вас');
  });
  $('#wpResetMine').addEventListener('click', async () => {
    const r = await api('wallpaper_reset', { conversation_id: state.convId, scope: 'mine' }, 'POST');
    if (r.ok) { applyWallpaper(r.wallpaper); openWallpaper(); }
  });

  /* ════════════════════════ НАСТРОЙКИ ══════════════════════════════════════ */
  async function loadSettings() {
    const r = await api('settings');
    if (r.ok) { state.settings = r.settings; state.v2 = r.v2 !== false; }
  }
  function renderSettings() {
    const s = state.settings;
    const rows = Object.entries(SOUNDS).map(([k, v]) => [k, v.label]);
    if (s.custom) rows.push(['custom', 'Свой: ' + s.custom.name]);
    rows.push(['none', 'Без звука']);
    $('#stSounds').innerHTML = rows.map(([k, label]) => `<label class="st-row${s.sound === k ? ' on' : ''}">
      <input type="radio" name="snd" value="${k}"${s.sound === k ? ' checked' : ''}>
      <span class="st-name">${esc(label)}</span>
      ${k !== 'none' ? `<button type="button" class="st-play" data-play="${k}" aria-label="Прослушать">▶</button>` : ''}
    </label>`).join('') +
      `<button type="button" class="st-upload" id="stUpload"${state.v2 ? '' : ' disabled'}>${ICON.dl}<span>${s.custom ? 'Заменить свой звук' : 'Загрузить свой звук'}</span><small>mp3, ogg, wav · до 200 КБ</small></button>`;
    $('#stVolume').value = s.volume;
  }
  let saveTimer;
  async function saveSettings(extra = {}) {
    const r = await api('save_settings', { sound: state.settings.sound, volume: state.settings.volume, ...extra }, 'POST');
    if (r.ok) state.settings = r.settings;
    else toast(r.error === 'migration' ? 'Нужна миграция чата v2' : 'Не удалось сохранить', true);
    renderSettings();
  }
  function openSettings() { renderSettings(); renderPushState(); $('#settings').hidden = false; }
  $('#stClose').addEventListener('click', () => { $('#settings').hidden = true; });
  $('#settings').addEventListener('click', e => { if (e.target.id === 'settings') $('#settings').hidden = true; });
  $('#stSounds').addEventListener('change', e => {
    if (e.target.name !== 'snd') return;
    state.settings.sound = e.target.value;
    playSound(e.target.value, state.settings.volume, true);
    saveSettings();
  });
  $('#stSounds').addEventListener('click', e => {
    const p = e.target.closest('[data-play]');
    if (p) { e.preventDefault(); playSound(p.dataset.play, state.settings.volume, true); return; }
    if (e.target.closest('#stUpload')) $('#soundInput').click();
  });
  $('#stVolume').addEventListener('input', e => {
    state.settings.volume = +e.target.value;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => { playSound(state.settings.sound, state.settings.volume, true); saveSettings(); }, 350);
  });
  $('#soundInput').addEventListener('change', async e => {
    const f = e.target.files[0]; e.target.value = '';
    if (!f) return;
    if (f.size > 200 * 1024) { toast('Звук должен быть не больше 200 КБ', true); return; }
    toast('Загружаю звук…');
    try {
      const dto = await uploadFile(f, 'sound');
      state.settings.sound = 'custom';
      await saveSettings({ sound: 'custom', sound_file_id: dto.id });
      customAudio = null;
      playSound('custom', state.settings.volume, true);
      toast('Свой звук установлен');
    } catch (err) { toast(uploadError(err), true); }
  });

  /* Уведомления: разрешение спрашиваем только по кнопке — браузеры (и iOS
     в особенности) показывают запрос лишь в ответ на явный жест. */
  async function renderPushState() {
    const st = $('#pushState'), btn = $('#pushBtn');
    const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    btn.hidden = true;
    // Раньше «Включены ✓» рисовалось по одному разрешению. Разрешение есть, а
    // подписки нет (не сохранилась, сменили ключ, почистили данные) — и человек
    // видел галочку при мёртвых пушах. Теперь смотрим на саму подписку.
    const state = window.pushState ? await window.pushState() : 'unsupported';
    if (state === 'unsupported') {
      st.textContent = iOS && !standalone ? 'На iPhone уведомления работают, если добавить Dustore на экран «Домой»' : 'Браузер не поддерживает уведомления';
      return;
    }
    if (state === 'on') { st.textContent = 'Включены ✓'; return; }
    if (state === 'denied') { st.textContent = 'Запрещены в настройках браузера'; return; }
    st.textContent = 'Выключены'; btn.hidden = false;
  }
  $('#pushBtn').addEventListener('click', async () => {
    if (!window.initPush) return;
    const r = await window.initPush();
    if (!r.ok) toast(r.reason, true);
    renderPushState();
  });

  /* ── Профиль ── */
  $('#peerHead').addEventListener('click', async () => {
    const h = state.header;
    if (!h || h.kind === 'studio' || h.kind === 'system' || !h.peer_id) return;
    $('#profileBody').innerHTML = '<div class="empty">Загрузка…</div>';
    $('#profile').classList.add('open');
    const r = await api('user_profile', { user_id: h.peer_id });
    if (!r.ok) {
      const why = r.error === 'not_found' ? 'Пользователь удалён' : r.error === 'network' ? 'Нет соединения' : 'Профиль недоступен';
      $('#profileBody').innerHTML = '<div class="empty">' + esc(why) + '</div>';
      return;
    }
    const p = r.profile, handle = p.handle || '', ls = lastSeen(p.last_seen);
    const link = handle ? `/player/${encodeURIComponent(handle)}` : null;
    $('#profileBody').innerHTML = `
    <div class="big-av">${avatarHTML({ name: p.name, avatar: p.avatar })}</div>
    <div class="p-name">${esc(p.name)}</div>
    ${handle ? `<div class="p-handle">@${esc(handle)}</div>` : ''}
    ${ls ? `<div class="p-meta">${esc(ls)}</div>` : ''}
    ${p.location ? `<div class="p-meta">${esc(p.location)}</div>` : ''}
    <div class="p-stats">
      <div class="p-stat"><b>${p.votes_up}</b><span>лайки</span></div>
      <div class="p-stat"><b>${p.views}</b><span>просмотры</span></div>
    </div>
    ${link ? `<div class="p-links"><a class="primary" href="${esc(link)}">Профиль Dustore</a></div>` : ''}`;
  });
  $('#profBack').addEventListener('click', () => $('#profile').classList.remove('open'));

  /* ── Поиск ── */
  let searchTimer;
  const searchInput = $('#searchInput'), searchWrap = $('#searchWrap');
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim().replace(/^@/, '');
    searchWrap.classList.toggle('has-value', searchInput.value !== '');
    if (q.length < 2) { $('#searchResults').hidden = true; $('#list').hidden = false; return; }
    $('#list').hidden = true;
    $('#searchResults').hidden = false;
    $('#searchResults').innerHTML = '<div class="empty">Ищу…</div>';
    searchTimer = setTimeout(async () => {
      const r = await api('search_users', { q });
      renderResults(r.ok ? r.users : []);
    }, 250);
  });
  $('#searchClear').addEventListener('click', () => {
    searchInput.value = '';
    searchWrap.classList.remove('has-value');
    $('#searchResults').hidden = true;
    $('#list').hidden = false;
    if (!isTouch) searchInput.focus();
  });
  searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') $('#searchClear').click(); });
  function renderResults(users) {
    const box = $('#searchResults');
    if (!users.length) { box.innerHTML = '<div class="empty">Никого не нашлось</div>'; return; }
    box.innerHTML = users.map(u => `<button type="button" class="result" data-u='${esc(JSON.stringify(u))}'>
      <div class="av round">${avatarHTML({ name: u.username, avatar: u.avatar })}</div>
      <div><div class="r-name">${esc(u.username)}</div><div class="r-sub">личный чат</div></div>
    </button>`).join('');
  }
  $('#searchResults').addEventListener('click', e => {
    const el = e.target.closest('.result'); if (!el) return;
    const u = JSON.parse(el.dataset.u);
    $('#searchClear').click();
    openConv(0, { kind: 'user', id: u.id, name: u.username, avatar: u.avatar }, false, { to: u.id }, false);
  });
  $('#emptyNew').addEventListener('click', () => { $('#app').classList.remove('show-room'); searchInput.focus(); });
  $('#newChat')?.addEventListener('click', () => { searchInput.focus(); searchInput.scrollIntoView({ block: 'nearest' }); });   // телефон: карандаш в шапке = «новый чат» через поиск

  /* ── Вкладки ── */
  document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    state.tab = t.dataset.tab;
    $('#searchClear').click();
    loadList();
  }));

  /* ── Старт ── */
  (async function init() {
    loadSettings();
    // разрешение уже дано раньше — тихо обновляем подписку, без запроса
    if (window.initPush && window.VAPID_PUBLIC && 'Notification' in window && Notification.permission === 'granted') window.initPush().then(r => r.ok || console.warn('[push]', r.reason));
    await loadList();
    startListTimer();
    connectWS();

    if (AUTO.system) {
      const card = document.querySelector('.card[data-system="1"]');
      if (card) { card.click(); return; }
    }
    if (AUTO.conversation) {
      const card = document.querySelector(`.card[data-id="${AUTO.conversation}"]`);
      if (card) { card.click(); return; }
      // беседы нет в личных (например, обращение в студию из пуша сотруднику) —
      // открываем напрямую: имя и аватар подтянет шапка треда
      openConv(AUTO.conversation, { kind: 'user', name: '…' }, false);
      return;
    }
    if (AUTO.to || AUTO.studio) {
      const r = await api('start', AUTO.to ? { to: AUTO.to } : { studio: AUTO.studio });
      if (r.ok) {
        await loadList();
        const card = document.querySelector(`.card[data-id="${r.conversation_id}"]`);
        if (card) card.click();
        else openConv(r.conversation_id, { kind: AUTO.studio ? 'studio' : 'user', name: '…' }, !!AUTO.studio);
      }
    }
  })();
})();