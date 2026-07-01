/* messages.js — мессенджер */

const AUTO_REPLIES = [
  'Интересная мысль, надо обдумать!',
  'Полностью согласна — давай попробуем.',
  'Именно об этом я и думала ✦',
  'Хорошая идея. Когда начнём?',
  'Да, звучит вдохновляюще!',
  'Отлично сказано 👌',
];

function selectChat(el, name, ini, online, handle) {
  document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  const ub = el.querySelector('.ci-badge'); if (ub) ub.remove();

  const dlgName   = document.getElementById('dlg-name');
  const dlgStatus = document.getElementById('dlg-status');
  const dlgOnline = document.querySelector('.dlg-av .online-dot');

  if (dlgName)   dlgName.textContent   = name;
  if (dlgStatus) dlgStatus.textContent = (online ? 'онлайн · ' : '') + handle;
  if (dlgOnline) dlgOnline.style.display = online ? '' : 'none';

  const area = document.getElementById('msgs-area');
  if (!area) return;
  area.innerHTML = `
    <div class="msg-day"><span>Сегодня</span></div>
    <div class="msg-row">
      <div class="msg-av">${ini}</div>
      <div class="bubble">Привет! Рада пообщаться здесь.<span class="btime">${ftime()}</span></div>
    </div>`;
  area.scrollTop = area.scrollHeight;
}

function sendMsg() {
  const ta   = document.getElementById('msg-ta');
  const text = ta ? ta.value.trim() : '';
  if (!text) return;

  const area = document.getElementById('msgs-area');
  const name = document.getElementById('dlg-name');
  const ini  = name ? name.textContent.split(' ').map(w => w[0]).join('').slice(0, 2) : '??';

  const row = document.createElement('div');
  row.className = 'msg-row mine';
  row.innerHTML = `<div class="msg-av">А</div>
    <div class="bubble">${esc(text)}<span class="btime">${ftime()}</span></div>`;
  area.appendChild(row);
  ta.value = ''; ta.style.height = '';
  area.scrollTop = area.scrollHeight;

  // typing indicator + auto-reply
  setTimeout(() => {
    const tr = document.createElement('div');
    tr.className = 'typing-row';
    tr.innerHTML = `<div class="msg-av">${ini}</div>
      <div class="typing-bub"><span></span><span></span><span></span></div>`;
    area.appendChild(tr);
    area.scrollTop = area.scrollHeight;

    setTimeout(() => {
      tr.remove();
      const rr = document.createElement('div');
      rr.className = 'msg-row';
      rr.innerHTML = `<div class="msg-av">${ini}</div>
        <div class="bubble">${AUTO_REPLIES[Math.floor(Math.random() * AUTO_REPLIES.length)]}<span class="btime">${ftime()}</span></div>`;
      area.appendChild(rr);
      area.scrollTop = area.scrollHeight;
    }, 1800);
  }, 600);
}

function ftime() {
  const d = new Date();
  return `${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Enter to send (Shift+Enter = newline)
const msgTa = document.getElementById('msg-ta');
if (msgTa) {
  msgTa.addEventListener('input', () => autoGrow(msgTa));
  msgTa.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
  });
}
