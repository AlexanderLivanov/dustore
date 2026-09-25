/* =============================================================================
   chat/chat.js — клиент чатов Dustore (v2).
   Вынесен из index.php, чтобы тот же код подключался и в мобильной PWA (/m/chat):
   конфиг приходит через window.CHAT_CFG, DOM — одинаковые id в обоих шеллах.

   Модули ниже: утилиты · звук · поллинг/WS · список · тред · ответы и свайп ·
   пины · контекстное меню · вложения · отправка · настройки · профиль/поиск.
   ============================================================================= */
(function () {
'use strict';

const CFG  = window.CHAT_CFG || {};
const ME   = CFG.me | 0;
const AUTO = CFG.auto || {};
const API  = CFG.api || '/chat/api.php';

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
  pin:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5"/><path d="M9 10.8V4h6v6.8l3 3.2H6z"/></svg>',
  copy:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>',
  dl:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>',
  del:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>',
  read:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l5 5L18 6"/><path d="M12 16l1 1L23 6"/></svg>',
  gear:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
  file:  '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
  more:  '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
};
const avShape = p => p && p.kind === 'system' ? 'sys' : (p && p.kind === 'studio' ? 'sq' : 'round');

const asDate  = ts => new Date(String(ts).replace(' ', 'T'));
const fmtTime = ts => asDate(ts).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
const fmtDay  = ts => asDate(ts).toLocaleDateString('ru', { day: 'numeric', month: 'long' });
const fmtSize = b => b < 1024 ? b + ' Б' : b < 1048576 ? (b / 1024).toFixed(0) + ' КБ' : (b / 1048576).toFixed(1).replace('.', ',') + ' МБ';

function lastSeen(ts) {
  if (!ts) return '';
  const s = (Date.now() - asDate(ts)) / 1000;
  if (s < 90)    return 'в сети';
  if (s < 3600)  return 'был(а) ' + Math.floor(s / 60) + ' мин назад';
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
  peerLastRead: 0,
  msgs: new Map(),          // id → сообщение (для меню, копирования, ответа)
  pins: [], pinIdx: 0,
  replyTo: null,            // { id, name, text }
  atts: [],                 // вложения в композере
  settings: { sound: 'dust', volume: 70, custom: null }, v2: true,
};

/* ════════════════════════ ЗВУК ═══════════════════════════════════════════
   Четыре фирменных звука синтезируются WebAudio (ни файлов, ни лицензий),
   плюс свой файл пользователя. AudioContext живёт после первого жеста. */
let audioCtx = null, customAudio = null;
function ctx() {
  if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {} }
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
  dust:  { label: 'Dustore',  play: (ac, o) => { tone(ac, { f0: 1318.5, dur: .22, peak: .22, out: o }); tone(ac, { f0: 1975.5, at: .09, dur: .32, peak: .16, out: o }); } },
  drop:  { label: 'Капля',    play: (ac, o) => { tone(ac, { f0: 1400, f1: 380, dur: .18, peak: .3, out: o }); } },
  pop:   { label: 'Поп',      play: (ac, o) => { tone(ac, { f0: 520, f1: 900, type: 'triangle', dur: .09, peak: .35, out: o }); tone(ac, { f0: 900, type: 'triangle', at: .06, dur: .08, peak: .15, out: o }); } },
  pixel: { label: 'Пиксель',  play: (ac, o) => { [1046.5, 1318.5, 1568].forEach((f, i) => tone(ac, { f0: f, type: 'square', at: i * .06, dur: .07, peak: .07, out: o })); } },
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
    customAudio.play().catch(() => {});
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
async function loadList() {
  const r = await api('list', { tab: state.tab });
  const box = $('#list');
  if (!r.ok) { box.innerHTML = '<div class="empty">Не удалось загрузить список</div>'; return; }

  const total = r.conversations.reduce((a, c) => a + (c.unread || 0), 0);
  if (prevTotal !== null && total > prevTotal) ping();
  prevTotal = total;

  if (!r.conversations.length) { box.innerHTML = '<div class="empty">Здесь появятся ваши диалоги</div>'; return; }

  box.innerHTML = r.conversations.map(c => {
    const badge = c.unread ? `<span class="badge">${c.unread > 99 ? '99+' : c.unread}</span>` : '<span></span>';
    const active = c.id === state.convId ? ' active' : '';
    const isSys = c.peer.kind === 'system';
    const cls = isSys ? ' system' : (c.type === 'studio' ? ' studio' : '');
    const last = isSys
      ? (c.last ? esc(c.last.body) : 'нет уведомлений')
      : (c.last ? (c.last.mine ? '<span class="me">Вы: </span>' : '') + esc(c.last.body) : '<i>нет сообщений</i>');
    return `<button type="button" class="card${cls}${active}" data-id="${c.id}" data-unread="${c.unread || 0}"
              data-peer='${esc(JSON.stringify(c.peer))}' data-studio="${c.type === 'studio' ? 1 : 0}" data-system="${isSys ? 1 : 0}">
      <div class="av ${avShape(c.peer)}">${isSys ? BELL : avatarHTML(c.peer)}</div>
      <div class="c-main">
        <div class="c-top"><span class="c-name">${esc(c.peer.name)}</span><span class="c-time">${c.ts ? fmtTime(c.ts) : ''}</span></div>
        <div class="c-last">${last}</div>
        ${c.peer.tag ? `<div class="c-tag">→ ${esc(c.peer.tag)}</div>` : ''}
      </div>
      ${badge}
    </button>`;
  }).join('');
}
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
  state.msgs.clear(); state.pins = []; state.pinIdx = 0; state._pinSig = ''; state.peerLastRead = 0;
  cancelReply(); clearAtts();

  $('#room').classList.toggle('is-studio', !!isStudio);
  $('#room').classList.toggle('is-system', !!isSystem);
  $('#app').classList.add('show-room');
  $('#profile').classList.remove('open');
  $('#menu').hidden = true;
  $('#delConv').hidden = !!isSystem;
  showRoom(true);
  renderPins();

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
  state.peerLastRead = h.peer_last_read_id || 0;
  let sub;
  if (h.kind === 'system') sub = 'системные уведомления';
  else if (h.kind === 'studio') sub = 'официальный канал студии';
  else sub = h.tag ? ('обращение · ' + h.tag) : (lastSeen(h.last_seen) || 'личный чат');
  const online = h.kind === 'user' && h.last_seen && (Date.now() - asDate(h.last_seen) < 90000);
  $('#rhSub').innerHTML = `<span class="live${online ? '' : ' off'}"></span>${esc(sub)}`;
  updateReadTicks();
}

async function loadInitial() {
  const r = await api('thread', { conversation_id: state.convId });
  if (!r.ok) { toast('Не удалось открыть диалог', true); return; }
  applyHeader(r.header);
  setPins(r.pins);
  state.hasMore = !!r.has_more;

  const th = $('#thread');
  th.innerHTML = '';
  th.dataset.lastDay = '';
  if (state.hasMore) th.insertAdjacentHTML('beforeend', '<button type="button" class="more-btn" id="moreBtn">Показать раньше</button>');
  appendMessages(r.messages, 'beforeend');
  if (!r.messages.length) {
    th.innerHTML = `<div class="empty">${state.isSystem ? 'Уведомлений пока нет' : 'Сообщений пока нет — напишите первым'}</div>`;
  }
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
  const r = await api('thread', { conversation_id: state.convId, after_id: state.lastId });
  if (!r.ok) return;
  applyHeader(r.header);
  if (r.pins) setPins(r.pins);
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
      if (th.querySelector(`.msg[data-id="${m.id}"]`)) return;   // уже отрисовано (своё отправленное)
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
const renderTicks = id => `<span class="ticks${state.peerLastRead >= id ? ' read' : ''}" data-mid="${id}">${TICK_SVG}</span>`;
function updateReadTicks() {
  document.querySelectorAll('#thread .ticks').forEach(el => el.classList.toggle('read', state.peerLastRead >= +el.dataset.mid));
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
  const q = e.target.closest('.quote'); if (q) { jumpTo(+q.dataset.jump); return; }
  const img = e.target.closest('.m-img'); if (img) { openLightbox(img.dataset.full, img.dataset.name); return; }
  const more = e.target.closest('.m-more');
  if (more) { const r = more.getBoundingClientRect(); openMsgMenu(+more.closest('.msg').dataset.id, r.left, r.bottom); }
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
}
function cancelReply() { state.replyTo = null; $('#replyBar').hidden = true; }
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
  const msg = e.target.closest('.msg'); if (!msg || msg.querySelector('.gone')) return;
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
  const msg = e.target.closest('.msg'); if (!msg || msg.querySelector('.gone')) return;
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
  c.style.top  = Math.max(8, Math.min(y, innerHeight - h - 8)) + 'px';
  c.onclick = e => { const b = e.target.closest('button'); if (!b) return; closeCtx(); items[+b.dataset.i].run(); };
}
function closeCtx() { $('#ctx').hidden = true; }
document.addEventListener('pointerdown', e => { if (!e.target.closest('#ctx')) closeCtx(); }, true);
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  closeCtx(); $('#lightbox').hidden = true; $('#settings').hidden = true;
  if (state.replyTo) cancelReply();
});
thread.addEventListener('scroll', closeCtx, { passive: true });

function openMsgMenu(id, x, y) {
  const m = state.msgs.get(id); if (!m || m.deleted || state.isSystem) return;
  const pinned = state.pins.some(p => p.id === id);
  const items = [{ label: 'Ответить', icon: ICON.reply, run: () => startReply(id) }];
  if (state.v2) items.push({ label: pinned ? 'Открепить' : 'Закрепить', icon: ICON.pin, run: () => togglePin(id, !pinned) });
  if (m.body) items.push({ label: 'Копировать текст', icon: ICON.copy, run: () => navigator.clipboard?.writeText(m.body).then(() => toast('Скопировано')) });
  if (m.file) items.push({ label: m.file.kind === 'image' ? 'Открыть картинку' : 'Скачать файл', icon: ICON.dl,
    run: () => m.file.kind === 'image' ? openLightbox(m.file.url, m.file.name) : window.open(m.file.url, '_blank') });
  if (m.mine) items.push({ label: 'Удалить', icon: ICON.del, danger: true, run: () => deleteMessage(id) });
  openCtx(items, x, y);
}
thread.addEventListener('contextmenu', e => {
  const msg = e.target.closest('.msg');
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
  if (init.kind === 'image') { try { ({ blob: thumb, w, h } = await makeThumb(file)); } catch (e) {} }

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

const uploadError = e => ({ too_big: 'Файл больше 50 МБ', bad_type: 'Такой тип файла нельзя отправить',
  too_many: 'Слишком много загрузок, подождите', migration: 'Нужна миграция чата v2', network: 'Нет соединения' }[e && e.error] || 'Не удалось загрузить');

function addFiles(list) {
  if (!state.convId && !state.draft) return;
  if (state.isSystem) return;
  [...list].slice(0, 10).forEach(file => {
    const a = { key: Math.random().toString(36).slice(2), file, status: 'uploading', progress: 0, dto: null,
                preview: /^image\//.test(file.type) ? URL.createObjectURL(file) : null };
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
   Несколько вложений = несколько сообщений (подпись и ответ — у первого).
   Антифлуд сервера — 5 сообщений за 5 секунд; упёрлись — ждём и повторяем. */
let sending = false;
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function send() {
  const inp = $('#input');
  const body = inp.value.trim();
  const ready = state.atts.filter(a => a.status === 'ready');
  if (sending || (!body && !ready.length) || (!state.convId && !state.draft)) return;
  if (state.atts.some(a => a.status === 'uploading')) { toast('Дождитесь загрузки файлов'); return; }

  sending = true; autosize();
  const backup = inp.value, reply = state.replyTo;
  inp.value = ''; autosize();
  const items = ready.length ? ready.map((a, i) => ({ file_id: a.dto.id, body: i === 0 ? body : '' })) : [{ body }];

  for (let i = 0; i < items.length; i++) {
    const target = state.convId > 0 ? { conversation_id: state.convId }
      : (state.draft.to ? { to: state.draft.to } : { studio: state.draft.studio });
    const params = { ...target, body: items[i].body };
    if (items[i].file_id) params.file_id = items[i].file_id;
    if (i === 0 && reply) params.reply_to = reply.id;
    let r = await api('send', params, 'POST');
    for (let t = 0; !r.ok && r.error === 'too_fast' && t < 3; t++) { await sleep(1200); r = await api('send', params, 'POST'); }
    if (!r.ok) {
      if (i === 0) { inp.value = backup; }
      const why = r.error === 'too_fast' ? 'Слишком часто, подождите секунду'
                : r.error === 'network'  ? 'Нет соединения'
                : r.error === 'too_long' ? 'Сообщение длиннее 4000 символов'
                : 'Не удалось отправить';
      toast(why, true);
      break;
    }
    if (i === 0) cancelReply();
    const sent = state.atts.find(a => a.dto && a.dto.id === items[i].file_id);
    if (sent) { if (sent.preview) URL.revokeObjectURL(sent.preview); state.atts = state.atts.filter(a => a !== sent); renderAtts(); }
    if (!state.convId) {
      state.convId = r.conversation_id; state.lastId = 0; state.firstId = 0; state.draft = null;
      await loadInitial(); startThreadTimer();
    } else {
      appendMessages([r.message], 'beforeend');
    }
    $('#thread').scrollTop = $('#thread').scrollHeight;
  }
  sending = false; autosize();
  loadList();
}

function autosize() {
  const t = $('#input');
  t.style.height = 'auto';
  t.style.height = Math.min(t.scrollHeight, 140) + 'px';
  const hasReady = state.atts.some(a => a.status === 'ready');
  $('#send').disabled = sending || (!t.value.trim() && !hasReady) || state.atts.some(a => a.status === 'uploading');
}
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
$('#delConv').addEventListener('click', () => { $('#menu').hidden = true; if (state.convId) deleteConv(state.convId); });

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
function renderPushState() {
  const st = $('#pushState'), btn = $('#pushBtn');
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  btn.hidden = true;
  if (!('Notification' in window) || !('serviceWorker' in navigator)) {
    st.textContent = iOS && !standalone ? 'На iPhone уведомления работают, если добавить Dustore на экран «Домой»' : 'Браузер не поддерживает уведомления';
    return;
  }
  if (Notification.permission === 'granted') { st.textContent = 'Включены ✓'; return; }
  if (Notification.permission === 'denied')  { st.textContent = 'Запрещены в настройках браузера'; return; }
  st.textContent = 'Выключены'; btn.hidden = false;
}
$('#pushBtn').addEventListener('click', async () => {
  if (!window.VAPID_PUBLIC) { toast('Пуши не настроены на сервере', true); return; }
  if (window.initPush) await window.initPush();
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
  if (window.initPush && window.VAPID_PUBLIC && 'Notification' in window && Notification.permission === 'granted') window.initPush();
  await loadList();
  startListTimer();
  connectWS();

  if (AUTO.conversation) {
    const card = document.querySelector(`.card[data-id="${AUTO.conversation}"]`);
    if (card) { card.click(); return; }
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
