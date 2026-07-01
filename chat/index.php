<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../swad/config.php';   // CONFIRM: путь к файлу с class Database
require_once __DIR__ . '/_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['USERDATA'])) { header('Location: /login'); exit; }
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);

$studioIds = get_user_studio_ids($db, $myId);

$hasStudio = !empty($studioIds);
// автооткрытие беседы по ссылке: /chat/?to=42  или  /chat/?studio=7
$openTo     = (int)($_GET['to'] ?? 0);
$openStudio = (int)($_GET['studio'] ?? 0);

// Сюда поставь свой require header.php, если нужна общая шапка сайта:
require __DIR__ . '/../swad/static/elements/header.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Эфир · Dustore</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{
  --p:#c32178;            /* брендовый магента */
  --p2:#74155d;
  --bg0:#14041d;
  --s:#22d3ee;            /* акцент «студия = официальный канал» */
  --glass:rgba(255,255,255,.045);
  --glass-br:rgba(255,255,255,.08);
  --txt:#f3e8f7;
  --mut:#b58fc0;
  --mut2:#7c5a87;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{
  font-family:Inter,system-ui,sans-serif;color:var(--txt);
  background:
    radial-gradient(1200px 700px at 80% -10%, rgba(195,33,120,.25), transparent 60%),
    radial-gradient(900px 600px at -10% 110%, rgba(34,211,238,.10), transparent 55%),
    linear-gradient(160deg,#1b0726,var(--bg0) 70%);
  background-attachment:fixed;
}
.app{
  display:grid;grid-template-columns:340px 1fr;gap:14px;
  height:85dvh;padding:14px;max-width:1280px;margin:0 auto;
}
.panel{
  background:var(--glass);border:1px solid var(--glass-br);
  border-radius:20px;backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  display:flex;flex-direction:column;min-height:0;overflow:hidden;
}

/* ---------- ЛЕВАЯ КОЛОНКА ---------- */
.side-head{padding:18px 18px 10px}
.brand{display:flex;align-items:center;gap:10px;font-family:Syne;font-weight:800;letter-spacing:.5px}
.brand .glyph{
  width:30px;height:30px;border-radius:9px;display:grid;place-items:center;
  background:linear-gradient(135deg,var(--p),var(--p2));font-family:"JetBrains Mono";font-size:15px;
  box-shadow:0 0 24px rgba(195,33,120,.55);
}
.brand small{font-family:"JetBrains Mono";font-weight:500;color:var(--mut);font-size:11px;letter-spacing:1px;text-transform:uppercase}
.tabs{display:flex;gap:6px;margin-top:14px;padding:4px;background:rgba(0,0,0,.25);border-radius:13px}
.tab{
  flex:1;border:0;cursor:pointer;padding:9px 8px;border-radius:10px;background:transparent;
  color:var(--mut);font:600 13px Inter;letter-spacing:.3px;transition:.18s;display:flex;align-items:center;justify-content:center;gap:7px
}
.tab .dot{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.0;transition:.18s}
.tab.active{color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));box-shadow:0 6px 18px -6px var(--p)}
.tab.active .dot{opacity:1}
.tab[data-tab=studio].active{background:linear-gradient(135deg,var(--s),#0e7490);box-shadow:0 6px 18px -6px var(--s)}

.list{flex:1;min-height:0;overflow-y:auto;padding:8px 10px 14px}
.list::-webkit-scrollbar{width:8px}.list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:8px}

.card{
  display:grid;grid-template-columns:46px 1fr auto;gap:12px;align-items:center;
  padding:11px 12px;border-radius:14px;cursor:pointer;position:relative;
  border:1px solid transparent;transition:.16s;
}
.card:hover{background:rgba(255,255,255,.04)}
.card.active{background:rgba(255,255,255,.06);border-color:var(--glass-br)}
.av{
  width:46px;height:46px;border-radius:13px;display:grid;place-items:center;position:relative;
  font:700 16px Syne;color:#fff;overflow:hidden;
  background:linear-gradient(135deg,#3a1140,#220a2c);
}
.av img{width:100%;height:100%;object-fit:cover}
.card.studio .av{border-radius:13px;box-shadow:0 0 0 1.5px rgba(34,211,238,.5)}
.c-main{min-width:0}
.c-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.c-name{font-weight:600;font-size:14.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.c-time{font:500 11px "JetBrains Mono";color:var(--mut2);flex:none}
.c-last{font-size:13px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.c-last .me{color:var(--mut2)}
.c-tag{font:500 10px "JetBrains Mono";color:var(--s);letter-spacing:.5px;margin-top:3px;text-transform:uppercase}
/* «сигнал»-индикатор непрочитанного */
.badge{
  min-width:20px;height:20px;padding:0 6px;border-radius:10px;display:grid;place-items:center;
  font:700 11px "JetBrains Mono";color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));
  box-shadow:0 0 14px rgba(195,33,120,.6);animation:pulse 2.2s infinite
}
.card.studio .badge{background:linear-gradient(135deg,var(--s),#0e7490);box-shadow:0 0 14px rgba(34,211,238,.6)}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.12);opacity:.85}}
.empty{padding:40px 20px;text-align:center;color:var(--mut2);font-size:13px}

/* ---------- ПРАВАЯ КОЛОНКА: ЭФИР ---------- */
.room{position:relative}
.room.is-studio{--p:var(--s);--p2:#0e7490}   /* перекраска эфира студии в циан */
.room-head{
  display:flex;align-items:center;gap:13px;padding:16px 20px;border-bottom:1px solid var(--glass-br);
}
.room-head .av{width:42px;height:42px;border-radius:12px}
.rh-name{font-family:Syne;font-weight:700;font-size:17px}
.rh-sub{font:500 11px "JetBrains Mono";color:var(--mut);letter-spacing:.6px;display:flex;align-items:center;gap:6px;margin-top:2px}
.live{width:7px;height:7px;border-radius:50%;background:var(--p);box-shadow:0 0 10px var(--p);animation:pulse 2s infinite}

/* лента-таймлайн с центральным «позвоночником» */
.thread{flex:1;min-height:0;overflow-y:auto;padding:26px 28px;position:relative}
.thread::-webkit-scrollbar{width:8px}.thread::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:8px}
.thread::before{                       /* сам позвоночник */
  content:"";position:absolute;top:0;bottom:0;left:50%;width:2px;transform:translateX(-50%);
  background:linear-gradient(var(--p2),rgba(255,255,255,.06) 18%,rgba(255,255,255,.06) 82%,var(--p2));
  opacity:.5;
}
.msg{display:grid;grid-template-columns:1fr 30px 1fr;align-items:start;margin:14px 0;position:relative}
.lane{grid-column:2;display:flex;justify-content:center;padding-top:14px}
.node{width:11px;height:11px;border-radius:50%;background:var(--bg0);
  border:2px solid var(--p);box-shadow:0 0 10px var(--p);z-index:1}
.bubble{
  max-width:100%;padding:10px 14px;border-radius:16px;font-size:14.5px;line-height:1.45;
  word-wrap:break-word;position:relative;
}
.msg.them .bubble{grid-column:1;justify-self:end;background:rgba(255,255,255,.06);
  border:1px solid var(--glass-br);border-bottom-right-radius:5px}
.msg.mine .bubble{grid-column:3;justify-self:start;color:#fff;
  background:linear-gradient(135deg,var(--p),var(--p2));border-bottom-left-radius:5px;
  box-shadow:0 8px 22px -10px var(--p)}
/* тонкий «провод» от бабла к узлу */
.msg.them .bubble::after,.msg.mine .bubble::after{
  content:"";position:absolute;top:14px;width:18px;height:2px;background:rgba(255,255,255,.18)}
.msg.them .bubble::after{right:-18px}
.msg.mine .bubble::after{left:-18px;background:linear-gradient(90deg,transparent,var(--p))}
.b-time{display:block;font:500 10px "JetBrains Mono";color:var(--mut2);margin-top:4px}
.msg.mine .b-time{color:rgba(255,255,255,.6);text-align:right}
.day{grid-column:1/-1;text-align:center;margin:8px 0}
.day span{font:500 10px "JetBrains Mono";color:var(--mut2);background:rgba(0,0,0,.3);padding:4px 12px;border-radius:20px;letter-spacing:1px}

/* композер */
.composer{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--glass-br)}
.composer textarea{
  flex:1;resize:none;max-height:120px;min-height:46px;padding:13px 16px;border-radius:14px;
  background:rgba(0,0,0,.3);border:1px solid var(--glass-br);color:var(--txt);
  font:400 14.5px Inter;outline:none;transition:.16s
}
.composer textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(195,33,120,.18)}
.send{
  flex:none;width:46px;height:46px;border:0;border-radius:14px;cursor:pointer;align-self:flex-end;
  background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;font-size:20px;
  display:grid;place-items:center;transition:.16s;box-shadow:0 8px 20px -8px var(--p)
}
.send:hover{transform:translateY(-1px)}.send:active{transform:translateY(0)}
.send:disabled{opacity:.4;cursor:default;transform:none}

.placeholder{flex:1;display:grid;place-items:center;text-align:center;color:var(--mut2)}
.placeholder .big{font-family:Syne;font-weight:800;font-size:30px;
  background:linear-gradient(135deg,var(--p),var(--s));-webkit-background-clip:text;background-clip:text;color:transparent}
.placeholder p{margin:8px 0 0;font-size:13px}

.back{display:none;background:0;border:0;color:var(--txt);font-size:22px;cursor:pointer;margin-right:4px}

/* ---------- адаптив ---------- */
@media(max-width:760px){
  .app{grid-template-columns:1fr;padding:0;gap:0;height:100dvh}
  .panel{border-radius:0;border:0;border-bottom:1px solid var(--glass-br)}
  .room{display:none}
  .app.show-room .side{display:none}
  .app.show-room .room{display:flex}
  .back{display:block}
  /* на узком экране — одна дорожка слева, различаем цветом */
  .thread::before{left:18px}
  .msg{grid-template-columns:30px 1fr;padding-left:0}
  .lane{grid-column:1}
  .msg.them .bubble,.msg.mine .bubble{grid-column:2;justify-self:start;max-width:88%}
  .msg.them .bubble::after,.msg.mine .bubble::after{display:none}
}
</style>
</head>
<body>
<div class="app" id="app">

  <!-- ЛЕВО -->
  <aside class="panel side">
    <div class="side-head">
      <div class="brand">
        <span class="glyph">//</span>
        <div>Эфир <small>dustore comms</small></div>
      </div>
      <div class="tabs">
        <button class="tab active" data-tab="personal"><span class="dot"></span>Личные</button>
        <?php if ($hasStudio): ?>
        <button class="tab" data-tab="studio"><span class="dot"></span>Студия</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="list" id="list"><div class="empty">Загрузка…</div></div>
  </aside>

  <!-- ПРАВО -->
  <section class="panel room" id="room">
    <div class="room-head" id="roomHead">
      <button class="back" id="back">‹</button>
      <div class="av" id="rhAv"></div>
      <div>
        <div class="rh-name" id="rhName"></div>
        <div class="rh-sub" id="rhSub"></div>
      </div>
    </div>
    <div class="thread" id="thread"></div>
    <div class="composer">
      <textarea id="input" rows="1" placeholder="Передать в эфир…"></textarea>
      <button class="send" id="send" disabled>↑</button>
    </div>
  </section>
</div>

<script>
const ME = <?= (int)$myId ?>;
const AUTO = { to: <?= $openTo ?>, studio: <?= $openStudio ?> };
const $ = s => document.querySelector(s);
const api = (action, params={}, method='GET') => {
  const opt = { method };
  let url = 'api.php?action=' + action;
  if (method === 'GET') url += '&' + new URLSearchParams(params);
  else { opt.body = new URLSearchParams({action, ...params}); }
  return fetch(url, opt).then(r => r.json());
};
const esc = s => (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const initials = n => (n||'?').trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase();
const avatarHTML = (p) => p.avatar
  ? `<img src="${esc(p.avatar)}" alt="" data-fb="${esc(initials(p.name))}" onerror="this.outerHTML=this.dataset.fb">`
  : esc(initials(p.name));
const fmtTime = ts => { const d=new Date(ts.replace(' ','T')); return d.toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'}); };
const fmtDay  = ts => { const d=new Date(ts.replace(' ','T')); return d.toLocaleDateString('ru',{day:'numeric',month:'long'}); };

let state = { tab:'personal', convId:0, lastId:0, header:null, threadTimer:null, listTimer:null };

/* ---------- список ---------- */
async function loadList(){
  const r = await api('list', { tab: state.tab });
  const box = $('#list');
  if(!r.ok){ box.innerHTML = '<div class="empty">Ошибка авторизации</div>'; return; }
  if(!r.conversations.length){
    box.innerHTML = '<div class="empty">'+(state.tab==='studio'
      ? 'Пока никто не писал в студию' : 'Здесь появятся ваши диалоги')+'</div>';
    return;
  }
  box.innerHTML = r.conversations.map(c => {
    const lastTxt = c.last ? (c.last.mine?'<span class="me">Вы: </span>':'') + esc(c.last.body) : '<i>нет сообщений</i>';
    const studioCls = c.peer.kind==='studio' || (state.tab==='studio') ? ' studio' : '';
    return `<div class="card${studioCls}${c.id===state.convId?' active':''}" data-id="${c.id}"
                 data-peer='${esc(JSON.stringify(c.peer))}' data-studio="${c.type==='studio'?1:0}">
      <div class="av">${avatarHTML(c.peer)}</div>
      <div class="c-main">
        <div class="c-top">
          <span class="c-name">${esc(c.peer.name)}</span>
          <span class="c-time">${c.ts?fmtTime(c.ts):''}</span>
        </div>
        <div class="c-last">${lastTxt}</div>
        ${c.peer.tag?`<div class="c-tag">→ ${esc(c.peer.tag)}</div>`:''}
      </div>
      <div>${c.unread?`<span class="badge">${c.unread>99?'99+':c.unread}</span>`:''}</div>
    </div>`;
  }).join('');
  box.querySelectorAll('.card').forEach(el=>{
    el.onclick = ()=> openConv(+el.dataset.id, JSON.parse(el.dataset.peer), +el.dataset.studio===1);
  });
}

/* ---------- тред ---------- */
function openConv(id, peer, isStudio){
  state.convId=id; state.lastId=0; state.header={peer,isStudio};
  $('#room').classList.toggle('is-studio', !!isStudio);
  $('#app').classList.add('show-room');
  $('#thread').innerHTML=''; $('#thread').dataset.lastDay='';
  $('#rhAv').innerHTML = avatarHTML(peer);
  $('#rhName').textContent = peer.name;
  $('#rhSub').innerHTML = `<span class="live"></span>${isStudio
      ? (peer.kind==='studio'?'официальный канал студии':'обращение в студию'+(peer.tag?' · '+esc(peer.tag):''))
      : 'личный эфир'}`;
  document.querySelectorAll('.card').forEach(c=>c.classList.toggle('active',+c.dataset.id===id));
  clearInterval(state.threadTimer);
  loadThread().then(()=> state.threadTimer=setInterval(loadThread, 3000));
  $('#input').focus();
}

async function loadThread(){
  if(!state.convId) return;
  const r = await api('thread', { conversation_id: state.convId, after_id: state.lastId });
  if(!r.ok) return;
  const th = $('#thread');
  const atBottom = th.scrollHeight - th.scrollTop - th.clientHeight < 60;
  let html='';
  let lastDay = th.dataset.lastDay || '';
  r.messages.forEach(m=>{
    const day = fmtDay(m.at);
    if(day!==lastDay){ html+=`<div class="day"><span>${day}</span></div>`; lastDay=day; }
    html += renderMsg(m);
    state.lastId = Math.max(state.lastId, m.id);
  });
  th.dataset.lastDay = lastDay;
  if(html){ th.insertAdjacentHTML('beforeend', html); if(atBottom) th.scrollTop=th.scrollHeight; }
}
function renderMsg(m){
  return `<div class="msg ${m.mine?'mine':'them'}">
    <div class="lane"><span class="node"></span></div>
    <div class="bubble">${esc(m.body)}<span class="b-time">${fmtTime(m.at)}</span></div>
  </div>`;
}

/* ---------- отправка ---------- */
async function send(){
  const inp=$('#input'); const body=inp.value.trim();
  if(!body || !state.convId) return;
  inp.value=''; autosize(); $('#send').disabled=true;
  const r = await api('send', { conversation_id: state.convId, body }, 'POST');
  if(r.ok){
    const th=$('#thread'); th.insertAdjacentHTML('beforeend', renderMsg(r.message));
    state.lastId=Math.max(state.lastId, r.message.id);
    th.scrollTop=th.scrollHeight;
    loadList();
  }
}

/* ---------- ui ---------- */
function autosize(){ const t=$('#input'); t.style.height='auto'; t.style.height=Math.min(t.scrollHeight,120)+'px'; $('#send').disabled=!t.value.trim(); }
$('#input').addEventListener('input', autosize);
$('#input').addEventListener('keydown', e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); send(); }});
$('#send').onclick = send;
$('#back').onclick = ()=>{ $('#app').classList.remove('show-room'); state.convId=0; clearInterval(state.threadTimer); };

document.querySelectorAll('.tab').forEach(t=>{
  t.onclick=()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    t.classList.add('active'); state.tab=t.dataset.tab; loadList();
  };
});

/* ---------- старт ---------- */
(async function init(){
  await loadList();
  state.listTimer=setInterval(loadList, 8000);
  if(AUTO.to || AUTO.studio){
    const r=await api('start', AUTO.to?{to:AUTO.to}:{studio:AUTO.studio});
    if(r.ok){ await loadList(); const card=document.querySelector(`.card[data-id="${r.conversation_id}"]`);
      if(card) card.click();
      else openConv(r.conversation_id, {kind:AUTO.studio?'studio':'user',name:'…'}, !!AUTO.studio);
    }
  }
})();
</script>
<?php // require __DIR__ . '/../swad/footer.php'; ?>
</body>
</html>