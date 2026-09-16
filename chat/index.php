<?php
declare(strict_types=1);
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['USERDATA'])) { header('Location: /login'); exit; }
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);

$studioIds  = get_user_studio_ids($db, $myId);
$hasStudio  = !empty($studioIds);
$openTo     = (int)($_GET['to'] ?? 0);
$openStudio = (int)($_GET['studio'] ?? 0);
$openConv   = (int)($_GET['conversation'] ?? 0);   // из пуш-уведомления

/**
 * Публичный VAPID-ключ. Он ПУБЛИЧНЫЙ по определению — браузер получает его
 * при подписке, — но хардкодить его в разметке всё равно не стоит: при
 * ротации ключей пришлось бы править файл.
 *
 * Ищем по очереди: переменная окружения -> /etc/dustore/push.env (тот же
 * файл, что читает systemd-юнит воркера) -> константа из swad/config.php.
 * Приватный ключ здесь не нужен и не читается.
 */
function vapid_public_key(): string {
    $v = getenv('VAPID_PUBLIC');
    if ($v) return trim($v);

    $path = getenv('PUSH_ENV_FILE') ?: '/etc/dustore/push.env';
    if (is_readable($path)) {
        // parse_ini_file споткнётся о строки без кавычек со спецсимволами,
        // поэтому разбираем сами — формат KEY=value
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') continue;
            [$k, $val] = array_pad(explode('=', $line, 2), 2, '');
            if (in_array(trim($k), ['VAPID_PUBLIC', 'VAPID_PUBLIC_KEY'], true)) {
                return trim($val, " \t\"'");
            }
        }
    }

    if (defined('VAPID_PUBLIC_KEY')) return (string)VAPID_PUBLIC_KEY;
    return '';
}
$VAPID_PUBLIC = vapid_public_key();

require __DIR__ . '/../swad/static/elements/header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Эфир · Dustore</title>
<link rel="stylesheet" href="/swad/css/chat.css">
</head>
<body>

<div class="app" id="app">
  <aside class="panel side">
    <div class="side-head">
      <div class="brand">
        <span class="glyph">//</span>
        <div>Эфир<small>dustore comms</small></div>
      </div>
      <div class="tabs">
        <button type="button" class="tab active" data-tab="personal">Личные</button>
        <?php if ($hasStudio): ?><button type="button" class="tab" data-tab="studio">Студия</button><?php endif; ?>
      </div>
    </div>

    <div class="search-top" id="searchWrap">
      <span class="si"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
      <input id="searchInput" placeholder="Поиск или новый эфир…" autocomplete="off">
      <button type="button" id="searchClear" aria-label="Очистить">&times;</button>
    </div>

    <div class="list" id="list"><div class="empty">Загрузка…</div></div>
    <div class="search-results" id="searchResults" hidden></div>
  </aside>

  <section class="panel room" id="room">
    <div class="room-empty" id="roomEmpty">Выберите диалог слева<br>или найдите собеседника через поиск</div>

    <div class="room-head" id="roomHead" hidden>
      <button class="back" id="back" aria-label="Назад">‹</button>
      <button class="peer" id="peerHead">
        <div class="av" id="rhAv"></div>
        <div style="min-width:0">
          <div class="rh-name" id="rhName"></div>
          <div class="rh-sub" id="rhSub"></div>
        </div>
      </button>
      <div class="head-menu">
        <button class="icon-btn" id="menuBtn" aria-label="Меню">⋯</button>
        <div class="menu" id="menu" hidden>
          <button class="danger" id="delConv">Удалить переписку</button>
        </div>
      </div>
    </div>

    <div class="thread" id="thread" hidden></div>

    <div class="composer" id="composer" hidden>
      <textarea id="input" rows="1" placeholder="Написать сообщение…"></textarea>
      <button class="send" id="send" disabled aria-label="Отправить">↑</button>
    </div>

    <div class="profile" id="profile">
      <div class="profile-head"><button class="icon-btn" id="profBack">‹</button><span>Профиль</span></div>
      <div class="profile-body" id="profileBody"></div>
    </div>
  </section>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="/pwa/push-client.js"></script>
<script>
(function () {
'use strict';

const ME   = <?= (int)$myId ?>;
const AUTO = { to: <?= $openTo ?>, studio: <?= $openStudio ?>, conversation: <?= $openConv ?> };

const $ = s => document.querySelector(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g,
    c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

function api(action, params = {}, method = 'GET') {
  const opt = { method };
  let url = 'api.php?action=' + action;
  if (method === 'GET') url += '&' + new URLSearchParams(params);
  else opt.body = new URLSearchParams({ action, ...params });
  return fetch(url, opt).then(r => r.json()).catch(() => ({ ok: false, error: 'network' }));
}

const initials = n => (n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
const avatarHTML = p => p.avatar
  ? `<img src="${esc(p.avatar)}" alt="" data-fb="${esc(initials(p.name))}" onerror="this.outerHTML=this.dataset.fb">`
  : esc(initials(p.name));
const asDate = ts => new Date(String(ts).replace(' ', 'T'));
const fmtTime = ts => asDate(ts).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' });
const fmtDay  = ts => asDate(ts).toLocaleDateString('ru', { day: 'numeric', month: 'long' });

function lastSeen(ts) {
  if (!ts) return '';
  const s = (Date.now() - asDate(ts)) / 1000;
  if (s < 90)    return 'в сети';
  if (s < 3600)  return 'был(а) ' + Math.floor(s / 60) + ' мин назад';
  if (s < 86400) return 'был(а) ' + Math.floor(s / 3600) + ' ч назад';
  return 'был(а) ' + asDate(ts).toLocaleDateString('ru', { day: 'numeric', month: 'short' });
}

let toastTimer;
function toast(msg, err) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.toggle('err', !!err);
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

/* ── Звук и пуш ───────────────────────────────────────────────────────────── */
let audioCtx = null, prevTotal = null, pushInited = false;
window.VAPID_PUBLIC = <?= json_encode($VAPID_PUBLIC) ?>;
document.addEventListener('click', () => {
  if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {} }
  if (!pushInited && window.initPush) {
    pushInited = true;
    // Без ключа подписка молча падает внутри pushManager.subscribe —
    // лучше сказать это в консоль, чем гадать, почему пуши не приходят.
    if (!window.VAPID_PUBLIC) console.warn('[push] VAPID_PUBLIC пуст: ключ не найден ни в окружении, ни в /etc/dustore/push.env');
    else window.initPush();
  }
}, { once: false });

function ping() {
  if (!audioCtx || document.hidden) return;
  const o = audioCtx.createOscillator(), g = audioCtx.createGain();
  o.connect(g); g.connect(audioCtx.destination);
  o.type = 'sine'; o.frequency.value = 880;
  const t = audioCtx.currentTime;
  g.gain.setValueAtTime(0.0001, t);
  g.gain.exponentialRampToValueAtTime(0.12, t + 0.01);
  g.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
  o.start(t); o.stop(t + 0.23);
}
window.chatPing = ping;

/* ── Поллинг ──────────────────────────────────────────────────────────────── */
/* Раньше таймеры крутились всегда, даже когда вкладка в фоне: каждая забытая
   вкладка дёргала api.php раз в 8 секунд бесконечно. Теперь на скрытой вкладке
   опрос останавливается и возобновляется при возврате. */
const POLL_LIST = { fast: 8000, slow: 25000 };
const POLL_THREAD = { fast: 3000, slow: 15000 };
let wsRate = 'fast', ws = null, wsBackoff = 1000;

const state = { tab: 'personal', convId: 0, lastId: 0, firstId: 0, hasMore: false,
                draft: null, header: null, isSystem: false, listTimer: null, threadTimer: null };

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
    ws = new WebSocket(`wss://${location.host}/ws?ticket=${encodeURIComponent(t.ticket)}`);
    ws.onopen = () => { wsBackoff = 1000; setRate('slow'); };
    ws.onmessage = e => {
      let m; try { m = JSON.parse(e.data); } catch { return; }
      if (m.type === 'new_message') {
        if (m.conversation_id === state.convId) pollThread();
        loadList();
      }
    };
    ws.onclose = ws.onerror = () => {
      setRate('fast');
      setTimeout(connectWS, wsBackoff);
      wsBackoff = Math.min(wsBackoff * 1.6 + Math.random() * 300, 15000);
    };
  } catch (e) {
    setRate('fast');
    setTimeout(connectWS, wsBackoff);
    wsBackoff = Math.min(wsBackoff * 1.6, 15000);
  }
}

/* ── Список бесед ─────────────────────────────────────────────────────────── */
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
    return `<button type="button" class="card${cls}${active}" data-id="${c.id}"
              data-peer='${esc(JSON.stringify(c.peer))}' data-studio="${c.type === 'studio' ? 1 : 0}" data-system="${isSys ? 1 : 0}">
      <div class="av${isSys ? ' sys' : ''}">${isSys ? '!' : avatarHTML(c.peer)}</div>
      <div class="c-main">
        <div class="c-top"><span class="c-name">${esc(c.peer.name)}</span><span class="c-time">${c.ts ? fmtTime(c.ts) : ''}</span></div>
        <div class="c-last">${last}</div>
        ${c.peer.tag ? `<div class="c-tag">→ ${esc(c.peer.tag)}</div>` : ''}
      </div>
      ${badge}
    </button>`;
  }).join('');

  box.querySelectorAll('.card').forEach(el => el.addEventListener('click', () =>
    openConv(+el.dataset.id, JSON.parse(el.dataset.peer), el.dataset.studio === '1', null, el.dataset.system === '1')));
}

/* ── Тред ─────────────────────────────────────────────────────────────────── */
function showRoom(on) {
  $('#roomEmpty').hidden = on;
  $('#roomHead').hidden = !on;
  $('#thread').hidden = !on;
  $('#composer').hidden = !on || state.isSystem;
}

function openConv(id, peer, isStudio, draft, isSystem) {
  state.convId = id; state.lastId = 0; state.firstId = 0; state.hasMore = false;
  state.draft = draft || null; state.isSystem = !!isSystem; state.header = { peer, isStudio };

  $('#room').classList.toggle('is-studio', !!isStudio);
  $('#room').classList.toggle('is-system', !!isSystem);
  $('#app').classList.add('show-room');
  $('#profile').classList.remove('open');
  $('#menu').hidden = true;
  showRoom(true);

  const th = $('#thread');
  th.innerHTML = ''; th.dataset.lastDay = '';
  $('#rhAv').innerHTML = isSystem ? '!' : avatarHTML(peer);
  $('#rhName').textContent = peer.name;
  $('#rhSub').innerHTML = '<span class="live off"></span>загрузка…';
  document.querySelectorAll('.card').forEach(c => c.classList.toggle('active', +c.dataset.id === id));

  clearInterval(state.threadTimer);
  if (id > 0) { loadInitial().then(startThreadTimer); }
  else { th.innerHTML = `<div class="empty">Новый эфир с ${esc(peer.name)}.<br>Напишите первое сообщение ↓</div>`; }
  $('#input').focus();
}

function applyHeader(h) {
  state.header = { ...state.header, peer_id: h.peer_id, kind: h.kind };
  let sub;
  if (h.kind === 'system') sub = 'системные уведомления';
  else if (h.kind === 'studio') sub = 'официальный канал студии';
  else sub = h.tag ? ('обращение · ' + h.tag) : (lastSeen(h.last_seen) || 'личный эфир');
  const online = h.kind === 'user' && h.last_seen && (Date.now() - asDate(h.last_seen) < 90000);
  $('#rhSub').innerHTML = `<span class="live${online ? '' : ' off'}"></span>${esc(sub)}`;
}

/* Первая загрузка: свежий хвост. Раньше сервер отдавал первые 500 сообщений
   беседы, и в длинной переписке чат открывался на прошлогодней истории. */
async function loadInitial() {
  const r = await api('thread', { conversation_id: state.convId });
  if (!r.ok) { toast('Не удалось открыть диалог', true); return; }
  applyHeader(r.header);
  state.hasMore = !!r.has_more;

  const th = $('#thread');
  th.innerHTML = '';
  th.dataset.lastDay = '';
  if (state.hasMore) th.insertAdjacentHTML('beforeend', '<button type="button" class="more-btn" id="moreBtn">Показать раньше</button>');
  appendMessages(r.messages, 'beforeend');
  th.scrollTop = th.scrollHeight;
  bindMore();
  loadList();
}

async function loadOlder() {
  if (!state.hasMore || !state.firstId) return;
  const btn = $('#moreBtn'); if (btn) btn.textContent = 'Загружаю…';
  const r = await api('thread', { conversation_id: state.convId, before_id: state.firstId });
  if (!r.ok) { if (btn) btn.textContent = 'Показать раньше'; return; }

  const th = $('#thread');
  const prevH = th.scrollHeight;
  state.hasMore = !!r.has_more;
  if (btn) btn.remove();
  if (state.hasMore) th.insertAdjacentHTML('afterbegin', '<button type="button" class="more-btn" id="moreBtn">Показать раньше</button>');
  appendMessages(r.messages, 'older');
  th.scrollTop = th.scrollHeight - prevH;   // держим позицию просмотра
  bindMore();
}

function bindMore() {
  const btn = $('#moreBtn');
  if (btn) btn.addEventListener('click', loadOlder);
}

async function pollThread() {
  if (!state.convId || !state.lastId) return;
  const r = await api('thread', { conversation_id: state.convId, after_id: state.lastId });
  if (!r.ok || !r.messages.length) return;
  applyHeader(r.header);
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
  if (where === 'older') {
    // вставляем блоком в начало, день пересчитываем локально
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
      const day = fmtDay(m.at);
      if (!state.isSystem && day !== lastDay) { html += `<div class="day">${day}</div>`; lastDay = day; }
      html += renderMsg(m);
      state.lastId = Math.max(state.lastId, m.id);
      state.firstId = state.firstId ? Math.min(state.firstId, m.id) : m.id;
    });
    th.dataset.lastDay = lastDay;
    th.insertAdjacentHTML('beforeend', html);
  }
  bindDelete();
}

function renderMsg(m) {
  if (state.isSystem) {
    return `<div class="sysmsg">${m.deleted ? '<i>удалено</i>' : esc(m.body)}<span class="b-time">${fmtTime(m.at)}</span></div>`;
  }
  if (m.deleted) {
    return `<div class="msg ${m.mine ? 'mine' : 'them'}" data-id="${m.id}"><div class="bubble gone">сообщение удалено</div></div>`;
  }
  const del = m.mine ? `<button class="del" data-mid="${m.id}" aria-label="Удалить">✕</button>` : '';
  return `<div class="msg ${m.mine ? 'mine' : 'them'}" data-id="${m.id}">
    <div class="bubble">${del}${esc(m.body)}<span class="b-time">${fmtTime(m.at)}</span></div></div>`;
}

function bindDelete() {
  document.querySelectorAll('.del:not([data-bound])').forEach(b => {
    b.dataset.bound = '1';
    b.addEventListener('click', async e => {
      e.stopPropagation();
      if (!confirm('Удалить сообщение?')) return;
      const r = await api('delete_message', { message_id: b.dataset.mid }, 'POST');
      if (!r.ok) { toast('Не удалось удалить', true); return; }
      const bub = b.closest('.bubble');
      bub.classList.add('gone');
      bub.textContent = 'сообщение удалено';
      loadList();
    });
  });
}

/* ── Отправка ─────────────────────────────────────────────────────────────── */
/* Было: текст стирался из поля сразу, и при ошибке ответа функция просто
   выходила — сообщение исчезало бесследно. Теперь при неудаче текст
   возвращается в поле и показывается причина. */
let sending = false;
async function send() {
  const inp = $('#input');
  const body = inp.value.trim();
  if (!body || sending || (!state.convId && !state.draft)) return;

  sending = true;
  $('#send').disabled = true;
  const backup = inp.value;
  inp.value = ''; autosize();

  const params = state.convId > 0
    ? { conversation_id: state.convId, body }
    : (state.draft.to ? { to: state.draft.to, body } : { studio: state.draft.studio, body });

  const r = await api('send', params, 'POST');
  sending = false;

  if (!r.ok) {
    inp.value = backup; autosize(); inp.focus();
    const why = r.error === 'too_fast' ? 'Слишком часто, подождите секунду'
              : r.error === 'network'  ? 'Нет соединения'
              : r.error === 'too_long' ? 'Сообщение длиннее 4000 символов'
              : 'Не удалось отправить';
    toast(why, true);
    return;
  }

  const th = $('#thread');
  if (!state.convId) {
    state.convId = r.conversation_id; state.lastId = 0; state.firstId = 0; state.draft = null;
    await loadInitial(); startThreadTimer();
  } else {
    appendMessages([r.message], 'beforeend');
  }
  th.scrollTop = th.scrollHeight;
  loadList();
}

function autosize() {
  const t = $('#input');
  t.style.height = 'auto';
  t.style.height = Math.min(t.scrollHeight, 130) + 'px';
  $('#send').disabled = !t.value.trim() || sending;
}
$('#input').addEventListener('input', autosize);
$('#input').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
});
$('#send').addEventListener('click', send);
$('#back').addEventListener('click', () => {
  $('#app').classList.remove('show-room');
  state.convId = 0; clearInterval(state.threadTimer); showRoom(false);
});

/* ── Меню беседы ──────────────────────────────────────────────────────────── */
$('#menuBtn').addEventListener('click', e => { e.stopPropagation(); $('#menu').hidden = !$('#menu').hidden; });
document.addEventListener('click', () => { $('#menu').hidden = true; });
$('#menu').addEventListener('click', e => e.stopPropagation());
$('#delConv').addEventListener('click', async () => {
  $('#menu').hidden = true;
  if (!state.convId || !confirm('Удалить переписку у себя? Новое сообщение вернёт её в список.')) return;
  const r = await api('delete_conversation', { conversation_id: state.convId }, 'POST');
  if (!r.ok) { toast('Не удалось удалить', true); return; }
  $('#app').classList.remove('show-room');
  state.convId = 0; clearInterval(state.threadTimer); showRoom(false); loadList();
});

/* ── Профиль ──────────────────────────────────────────────────────────────── */
$('#peerHead').addEventListener('click', async () => {
  const h = state.header;
  if (!h || h.kind === 'studio' || h.kind === 'system' || !h.peer_id) return;
  $('#profileBody').innerHTML = '<div class="empty">Загрузка…</div>';
  $('#profile').classList.add('open');
  const r = await api('user_profile', { user_id: h.peer_id });
  if (!r.ok) {
    // Раньше писало просто «Профиль недоступен» — причина терялась
    console.error('[chat] user_profile:', r.error || 'unknown');
    const why = r.error === 'not_found' ? 'Пользователь удалён'
              : r.error === 'bad_id'    ? 'Не удалось определить собеседника'
              : r.error === 'network'   ? 'Нет соединения'
              : 'Профиль недоступен';
    $('#profileBody').innerHTML = '<div class="empty">' + esc(why) + '</div>';
    return;
  }
  const p = r.profile, handle = p.handle || '', ls = lastSeen(p.last_seen);

  /* Было: `/@${handle}` и `/player.php?id=${p.id}` — обоих маршрутов
     не существует. player.php вытаскивает ник из адреса регуляркой
     /\/player\/([a-zA-Z0-9_]+)/ и на всё остальное отвечает 404
     с текстом «Пользователь не найден». Именно это и вылезало
     при попытке открыть профиль собеседника. */
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

/* ── Поиск ────────────────────────────────────────────────────────────────── */
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
  searchInput.focus();
});
searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') $('#searchClear').click(); });

function renderResults(users) {
  const box = $('#searchResults');
  if (!users.length) { box.innerHTML = '<div class="empty">Никого не нашлось</div>'; return; }
  box.innerHTML = users.map(u => `<button type="button" class="result" data-u='${esc(JSON.stringify(u))}'>
      <div class="av">${avatarHTML({ name: u.username, avatar: u.avatar })}</div>
      <div><div class="r-name">${esc(u.username)}</div><div class="r-sub">личный эфир</div></div>
    </button>`).join('');
  box.querySelectorAll('.result').forEach(el => el.addEventListener('click', () => {
    const u = JSON.parse(el.dataset.u);
    $('#searchClear').click();
    openConv(0, { kind: 'user', id: u.id, name: u.username, avatar: u.avatar }, false, { to: u.id }, false);
  }));
}

/* ── Вкладки ──────────────────────────────────────────────────────────────── */
document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
  document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
  t.classList.add('active');
  state.tab = t.dataset.tab;
  $('#searchClear').click();
  loadList();
}));

/* ── Старт ────────────────────────────────────────────────────────────────── */
(async function init() {
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
</script>
</body>
</html>