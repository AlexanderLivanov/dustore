<?php
declare(strict_types=1);
require_once __DIR__ . '/../swad/config.php';   // CONFIRM
require_once __DIR__ . '/_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = (new Database())->connect('dustore');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['USERDATA'])) { header('Location: /login'); exit; }
$me   = $_SESSION['USERDATA'];
$myId = (int)($me['id'] ?? 0);

$studioIds = get_user_studio_ids($db, $myId);
$hasStudio = !empty($studioIds);
$openTo     = (int)($_GET['to'] ?? 0);
$openStudio = (int)($_GET['studio'] ?? 0);
require __DIR__ . '/../swad/static/elements/header.php';   // сайтовый хедер (путь из твоего mini.php)
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Эфир · Dustore</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
:root{--p:#c32178;--p2:#74155d;--bg0:#14041d;--s:#22d3ee;--glass:rgba(255,255,255,.045);
  --glass-br:rgba(255,255,255,.08);--txt:#f3e8f7;--mut:#b58fc0;--mut2:#7c5a87;--gold:#f5b942}
*{box-sizing:border-box}html,body{height:100%;margin:0}
body{font-family:Inter,system-ui,sans-serif;color:var(--txt);
  background:radial-gradient(1200px 700px at 80% -10%,rgba(195,33,120,.25),transparent 60%),
    radial-gradient(900px 600px at -10% 110%,rgba(34,211,238,.10),transparent 55%),
    linear-gradient(160deg,#1b0726,var(--bg0) 70%);background-attachment:fixed}
.app{display:grid;grid-template-columns:340px 1fr;gap:14px;height:90dvh;padding:14px;max-width:1280px;margin:0 auto}
.panel{background:var(--glass);border:1px solid var(--glass-br);border-radius:20px;
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);display:flex;flex-direction:column;min-height:0;overflow:hidden}
.side-head{padding:18px 18px 8px}
.brand{display:flex;align-items:center;gap:10px;font-family:Syne;font-weight:800;letter-spacing:.5px}
.brand .glyph{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;
  background:linear-gradient(135deg,var(--p),var(--p2));font-family:"JetBrains Mono";font-size:15px;box-shadow:0 0 24px rgba(195,33,120,.55)}
.brand small{font-family:"JetBrains Mono";font-weight:500;color:var(--mut);font-size:11px;letter-spacing:1px;text-transform:uppercase}
.tabs{display:flex;gap:6px;margin-top:14px;padding:4px;background:rgba(0,0,0,.25);border-radius:13px}
.tab{flex:1;border:0;cursor:pointer;padding:9px 8px;border-radius:10px;background:transparent;color:var(--mut);
  font:600 13px Inter;letter-spacing:.3px;transition:.18s;display:flex;align-items:center;justify-content:center;gap:7px}
.tab .dot{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:0;transition:.18s}
.tab.active{color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));box-shadow:0 6px 18px -6px var(--p)}
.tab.active .dot{opacity:1}
.tab[data-tab=studio].active{background:linear-gradient(135deg,var(--s),#0e7490);box-shadow:0 6px 18px -6px var(--s)}
/* поиск наверху (объединён с «новым эфиром») */
.search-top{display:flex;align-items:center;gap:8px;margin:12px 14px 6px;padding:0 12px;
  background:rgba(0,0,0,.3);border:1px solid var(--glass-br);border-radius:13px;transition:.16s}
.search-top:focus-within{border-color:var(--p);box-shadow:0 0 0 3px rgba(195,33,120,.15)}
.search-top .si{color:var(--mut2);font-size:16px}
.search-top input{flex:1;background:0;border:0;outline:0;color:var(--txt);font:400 14px Inter;padding:12px 0}
.list,.search-results{flex:1;min-height:0;overflow-y:auto;padding:6px 10px 14px}
.list::-webkit-scrollbar,.thread::-webkit-scrollbar,.search-results::-webkit-scrollbar{width:8px}
.list::-webkit-scrollbar-thumb,.thread::-webkit-scrollbar-thumb,.search-results::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:8px}
.card{display:grid;grid-template-columns:46px 1fr auto;gap:12px;align-items:center;padding:11px 12px;
  border-radius:14px;cursor:pointer;position:relative;border:1px solid transparent;transition:.16s}
.card:hover{background:rgba(255,255,255,.04)}
.card.active{background:rgba(255,255,255,.06);border-color:var(--glass-br)}
/* отдельный блок «Уведомления» */
.card.system{background:rgba(245,185,66,.06);border:1px solid rgba(245,185,66,.18);margin-bottom:8px}
.card.system:hover{background:rgba(245,185,66,.1)}
.card.system .av.sys{background:linear-gradient(135deg,var(--gold),#a9781f);font-size:20px;box-shadow:0 0 18px rgba(245,185,66,.4)}
.card.system .c-name{color:var(--gold)}
.card.system .badge{background:linear-gradient(135deg,var(--gold),#a9781f);box-shadow:0 0 14px rgba(245,185,66,.5)}
.av{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;position:relative;
  font:700 16px Syne;color:#fff;overflow:hidden;background:linear-gradient(135deg,#3a1140,#220a2c)}
.av img{width:100%;height:100%;object-fit:cover}
.card.studio .av{box-shadow:0 0 0 1.5px rgba(34,211,238,.5)}
.c-main{min-width:0}.c-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.c-name{font-weight:600;font-size:14.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.c-time{font:500 11px "JetBrains Mono";color:var(--mut2);flex:none}
.c-last{font-size:13px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.c-last .me{color:var(--mut2)}
.c-tag{font:500 10px "JetBrains Mono";color:var(--s);letter-spacing:.5px;margin-top:3px;text-transform:uppercase}
.badge{min-width:20px;height:20px;padding:0 6px;border-radius:10px;display:grid;place-items:center;
  font:700 11px "JetBrains Mono";color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));
  box-shadow:0 0 14px rgba(195,33,120,.6);animation:pulse 2.2s infinite}
.card.studio .badge{background:linear-gradient(135deg,var(--s),#0e7490);box-shadow:0 0 14px rgba(34,211,238,.6)}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.12);opacity:.85}}
.empty{padding:40px 20px;text-align:center;color:var(--mut2);font-size:13px}
.result{display:grid;grid-template-columns:42px 1fr;gap:11px;align-items:center;padding:9px 10px;border-radius:12px;cursor:pointer;transition:.14s}
.result:hover{background:rgba(255,255,255,.05)}.result .av{width:42px;height:42px}
.r-name{font-weight:600;font-size:14px}.r-sub{font:500 11px "JetBrains Mono";color:var(--mut2)}
.room{position:relative}.room.is-studio{--p:var(--s);--p2:#0e7490}.room.is-system{--p:var(--gold);--p2:#a9781f}
.room-head{display:flex;align-items:center;gap:13px;padding:16px 20px;border-bottom:1px solid var(--glass-br)}
.room-head .peer{display:flex;align-items:center;gap:13px;cursor:pointer;flex:1;min-width:0}
.room.is-system .room-head .peer{cursor:default}
.room-head .av{width:42px;height:42px;border-radius:12px}
.rh-name{font-family:Syne;font-weight:700;font-size:17px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rh-sub{font:500 11px "JetBrains Mono";color:var(--mut);letter-spacing:.6px;display:flex;align-items:center;gap:6px;margin-top:2px}
.live{width:7px;height:7px;border-radius:50%;background:var(--p);box-shadow:0 0 10px var(--p);animation:pulse 2s infinite}
.live.off{background:var(--mut2);box-shadow:none;animation:none}
.head-menu{position:relative}
.icon-btn{width:38px;height:38px;border:0;border-radius:11px;background:rgba(255,255,255,.05);color:var(--mut);
  font-size:18px;cursor:pointer;display:grid;place-items:center;transition:.14s}
.icon-btn:hover{background:rgba(255,255,255,.1);color:var(--txt)}
.menu{position:absolute;right:0;top:44px;background:#22092e;border:1px solid var(--glass-br);border-radius:12px;
  padding:6px;min-width:180px;z-index:20;box-shadow:0 12px 30px -10px #000}
.menu button{width:100%;text-align:left;background:0;border:0;color:var(--txt);padding:9px 12px;border-radius:8px;font:500 13px Inter;cursor:pointer}
.menu button:hover{background:rgba(255,255,255,.06)}.menu button.danger{color:#ff6b8a}
.thread{flex:1;min-height:0;overflow-y:auto;padding:26px 28px;position:relative}
.thread::before{content:"";position:absolute;top:0;bottom:0;left:50%;width:2px;transform:translateX(-50%);
  background:linear-gradient(var(--p2),rgba(255,255,255,.06) 18%,rgba(255,255,255,.06) 82%,var(--p2));opacity:.5}
.room.is-system .thread::before{display:none}
.msg{display:grid;grid-template-columns:1fr 30px 1fr;align-items:start;margin:14px 0;position:relative}
.lane{grid-column:2;display:flex;justify-content:center;padding-top:14px}
.node{width:11px;height:11px;border-radius:50%;background:var(--bg0);border:2px solid var(--p);box-shadow:0 0 10px var(--p);z-index:1}
.bubble{max-width:100%;padding:10px 14px;border-radius:16px;font-size:14.5px;line-height:1.45;word-wrap:break-word;position:relative}
.msg.them .bubble{grid-column:1;justify-self:end;background:rgba(255,255,255,.06);border:1px solid var(--glass-br);border-bottom-right-radius:5px}
.msg.mine .bubble{grid-column:3;justify-self:start;color:#fff;background:linear-gradient(135deg,var(--p),var(--p2));
  border-bottom-left-radius:5px;box-shadow:0 8px 22px -10px var(--p)}
.msg.them .bubble::after,.msg.mine .bubble::after{content:"";position:absolute;top:14px;width:18px;height:2px;background:rgba(255,255,255,.18)}
.msg.them .bubble::after{right:-18px}.msg.mine .bubble::after{left:-18px;background:linear-gradient(90deg,transparent,var(--p))}
.bubble.gone{background:rgba(255,255,255,.03)!important;color:var(--mut2);font-style:italic;box-shadow:none!important;border:1px dashed var(--glass-br)}
.b-time{display:block;font:500 10px "JetBrains Mono";color:var(--mut2);margin-top:4px}
.msg.mine .b-time{color:rgba(255,255,255,.6);text-align:right}
.del{position:absolute;top:6px;right:8px;width:22px;height:22px;border:0;border-radius:7px;background:rgba(0,0,0,.35);
  color:#fff;font-size:12px;cursor:pointer;opacity:0;transition:.14s;display:grid;place-items:center}
.msg.mine .bubble:hover .del{opacity:.85}
/* системные сообщения */
.sysmsg{max-width:520px;margin:12px auto;padding:12px 16px;border-radius:14px;font-size:13.5px;line-height:1.5;
  background:rgba(245,185,66,.08);border:1px solid rgba(245,185,66,.2);color:var(--txt);text-align:center}
.sysmsg-time{display:block;font:500 10px "JetBrains Mono";color:var(--mut2);margin-top:5px}
.day{grid-column:1/-1;text-align:center;margin:8px 0}
.day span{font:500 10px "JetBrains Mono";color:var(--mut2);background:rgba(0,0,0,.3);padding:4px 12px;border-radius:20px;letter-spacing:1px}
.composer{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--glass-br)}
.composer textarea{flex:1;resize:none;max-height:120px;min-height:46px;padding:13px 16px;border-radius:14px;
  background:rgba(0,0,0,.3);border:1px solid var(--glass-br);color:var(--txt);font:400 14.5px Inter;outline:none;transition:.16s}
.composer textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(195,33,120,.18)}
.send{flex:none;width:46px;height:46px;border:0;border-radius:14px;cursor:pointer;align-self:flex-end;
  background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;font-size:20px;display:grid;place-items:center;transition:.16s;box-shadow:0 8px 20px -8px var(--p)}
.send:hover{transform:translateY(-1px)}.send:disabled{opacity:.4;cursor:default;transform:none}
.thread-hint{text-align:center;color:var(--mut2);font-size:13px;padding:44px 20px}
.back{display:none;background:0;border:0;color:var(--txt);font-size:22px;cursor:pointer;margin-right:4px}
.profile{position:absolute;inset:0;background:linear-gradient(160deg,#1d0827,var(--bg0));z-index:30;
  display:flex;flex-direction:column;transform:translateX(100%);transition:.24s;pointer-events:none}
.profile.open{transform:none;pointer-events:auto}
.profile-head{display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--glass-br)}
.profile-body{flex:1;overflow-y:auto;padding:28px 24px;text-align:center}
.profile .big-av{width:110px;height:110px;border-radius:28px;margin:0 auto 16px;display:grid;place-items:center;
  font:800 40px Syne;color:#fff;overflow:hidden;background:linear-gradient(135deg,#3a1140,#220a2c);box-shadow:0 0 0 2px var(--glass-br)}
.profile .big-av img{width:100%;height:100%;object-fit:cover}
.p-name{font-family:Syne;font-weight:800;font-size:24px}
.p-handle{font:500 13px "JetBrains Mono";color:var(--s);margin-top:2px}
.p-meta{color:var(--mut);font-size:13px;margin-top:8px}
.p-stats{display:flex;justify-content:center;gap:22px;margin:20px 0}
.p-stat b{font-family:Syne;font-size:20px;display:block}
.p-stat span{font:500 10px "JetBrains Mono";color:var(--mut2);text-transform:uppercase;letter-spacing:.5px}
.p-links{display:flex;flex-direction:column;gap:10px;max-width:280px;margin:8px auto 0}
.p-links a{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:13px;text-decoration:none;
  font:600 14px Inter;color:#fff;background:rgba(255,255,255,.06);border:1px solid var(--glass-br);transition:.14s}
.p-links a:hover{background:rgba(255,255,255,.1)}
.p-links a.primary{background:linear-gradient(135deg,var(--p),var(--p2));border-color:transparent}
@media(max-width:760px){
  .app{grid-template-columns:1fr;padding:0;gap:0}
  .panel{border-radius:0;border:0;border-bottom:1px solid var(--glass-br)}
  .room{display:none}.app.show-room .side{display:none}.app.show-room .room{display:flex}.back{display:block}
  .thread::before{left:18px}.msg{grid-template-columns:30px 1fr}.lane{grid-column:1}
  .msg.them .bubble,.msg.mine .bubble{grid-column:2;justify-self:start;max-width:88%}
  .msg.them .bubble::after,.msg.mine .bubble::after{display:none}
}
@keyframes shakeSearch{0%,100%{transform:translateX(0)}20%{transform:translateX(-3px) rotate(-1deg)}
  40%{transform:translateX(3px) rotate(1deg)}60%{transform:translateX(-2px) rotate(-.5deg)}80%{transform:translateX(2px) rotate(.5deg)}}
.shake-it{animation:shakeSearch .3s ease-in-out}
</style>
</head>
<body>
<div class="app" id="app">
  <aside class="panel side">
    <div class="side-head">
      <div class="brand"><span class="glyph">//</span><div>Эфир <small>dustore comms</small></div></div>
      <div class="tabs">
        <button class="tab active" data-tab="personal"><span class="dot"></span>Личные</button>
        <?php if ($hasStudio): ?><button class="tab" data-tab="studio"><span class="dot"></span>Студия</button><?php endif; ?>
      </div>
    </div>
    <div class="search-top"><span class="si">⌕</span>
      <input id="searchInput" placeholder="Поиск или новый эфир…" autocomplete="off"></div>
    <div class="list" id="list"><div class="empty">Загрузка…</div></div>
    <div class="search-results" id="searchResults" hidden></div>
  </aside>

  <section class="panel room" id="room">
    <div class="room-head">
      <button class="back" id="back">‹</button>
      <div class="peer" id="peerHead">
        <div class="av" id="rhAv"></div>
        <div style="min-width:0"><div class="rh-name" id="rhName"></div><div class="rh-sub" id="rhSub"></div></div>
      </div>
      <div class="head-menu">
        <button class="icon-btn" id="menuBtn">⋯</button>
        <div class="menu" id="menu" hidden><button class="danger" id="delConv">Удалить переписку</button></div>
      </div>
    </div>
    <div class="thread" id="thread"></div>
    <div class="composer" id="composer">
      <textarea id="input" rows="1" placeholder="Передать в эфир…"></textarea>
      <button class="send" id="send" disabled>↑</button>
    </div>
    <div class="profile" id="profile">
      <div class="profile-head"><button class="icon-btn" id="profBack">‹</button><b>Профиль</b></div>
      <div class="profile-body" id="profileBody"></div>
    </div>
  </section>
</div>

<script src="/pwa/push-client.js"></script>
<script>
const ME=<?= (int)$myId ?>;
const AUTO={to:<?= $openTo ?>,studio:<?= $openStudio ?>};
const $=s=>document.querySelector(s);
const api=(action,params={},method='GET')=>{const opt={method};let url='api.php?action='+action;
  if(method==='GET')url+='&'+new URLSearchParams(params);else opt.body=new URLSearchParams({action,...params});
  return fetch(url,opt).then(r=>r.json());};
const esc=s=>(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const initials=n=>(n||'?').trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase();
const avatarHTML=p=>p.avatar?`<img src="${esc(p.avatar)}" alt="" data-fb="${esc(initials(p.name))}" onerror="this.outerHTML=this.dataset.fb">`:esc(initials(p.name));
const fmtTime=ts=>new Date(ts.replace(' ','T')).toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'});
const fmtDay=ts=>new Date(ts.replace(' ','T')).toLocaleDateString('ru',{day:'numeric',month:'long'});
function lastSeen(ts){ if(!ts) return '';
  const d=new Date(ts.replace(' ','T')); const s=(Date.now()-d)/1000;
  if(s<90) return 'в сети';
  if(s<3600) return 'был(а) '+Math.floor(s/60)+' мин назад';
  if(s<86400) return 'был(а) '+Math.floor(s/3600)+' ч назад';
  return 'был(а) '+d.toLocaleDateString('ru',{day:'numeric',month:'short'}); }

/* звук */
let audioCtx=null,audioReady=false,prevTotal=null;
window.VAPID_PUBLIC='CONFIRM'; // подставь публичный VAPID ключ
let pushInited=false;
document.addEventListener('click',()=>{
  if(!audioCtx){ try{audioCtx=new (window.AudioContext||window.webkitAudioContext)();audioReady=true;}catch(e){} }
  if(!pushInited && window.initPush){ pushInited=true; window.initPush(); } // подписка на пуш по первому жесту
});
function ping(){ if(!audioReady||!audioCtx) return;
  const o=audioCtx.createOscillator(),g=audioCtx.createGain();o.connect(g);g.connect(audioCtx.destination);
  o.type='sine';o.frequency.value=880;const t=audioCtx.currentTime;
  g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.14,t+0.01);g.gain.exponentialRampToValueAtTime(0.0001,t+0.25);
  o.start(t);o.stop(t+0.26); }
window.chatPing=ping; // SW дёргает его, когда вкладка в фокусе

/* ---------- WS-слой: только будит, данные всегда тянем через api.php ---------- */
let ws=null, wsBackoff=1000, wsRateMode='fast'; // fast=WS не подключён (поллинг несёт всю нагрузку)
const POLL_LIST={fast:8000,slow:25000}, POLL_THREAD={fast:3000,slow:15000};
function restartListTimer(){ clearInterval(state.listTimer); state.listTimer=setInterval(loadList,POLL_LIST[wsRateMode]); }
function restartThreadTimer(){ if(!state.threadTimer) return; clearInterval(state.threadTimer); state.threadTimer=setInterval(loadThread,POLL_THREAD[wsRateMode]); }
function setWsRate(mode){ if(wsRateMode===mode) return; wsRateMode=mode; restartListTimer(); restartThreadTimer(); }

async function connectWS(){
  try{
    const t=await fetch('/chat/ws_ticket.php').then(r=>r.json());
    if(!t.ok) throw new Error('ticket');
    ws=new WebSocket(`wss://${location.host}/ws?ticket=${encodeURIComponent(t.ticket)}`);
    ws.onopen=()=>{ wsBackoff=1000; setWsRate('slow'); }; // соединение живо — поллинг уходит в safety-net режим
    ws.onmessage=e=>{
      let m; try{ m=JSON.parse(e.data); }catch{ return; }
      if(m.type==='new_message'){
        if(m.conversation_id===state.convId) loadThread();
        loadList(); // бейджи/последнее сообщение в сайдбаре обновляем всегда
      }
    };
    ws.onclose=ws.onerror=()=>{
      setWsRate('fast'); // WS недоступен — поллинг возвращается к боевой частоте
      setTimeout(connectWS, wsBackoff);
      wsBackoff=Math.min(wsBackoff*1.6+Math.random()*300,15000);
    };
  }catch(e){ setWsRate('fast'); setTimeout(connectWS, wsBackoff); wsBackoff=Math.min(wsBackoff*1.6,15000); }
}

let state={tab:'personal',convId:0,lastId:0,draft:null,header:null,isSystem:false,threadTimer:null,listTimer:null};

/* ---------- список ---------- */
async function loadList(){
  const r=await api('list',{tab:state.tab}); const box=$('#list');
  if(!r.ok){ box.innerHTML='<div class="empty">Ошибка авторизации</div>'; return; }
  // звук на прирост суммарного непрочитанного (фоновые чаты)
  const total=r.conversations.reduce((a,c)=>a+(c.unread||0),0);
  if(prevTotal!==null && total>prevTotal) ping();
  prevTotal=total;
  if(!r.conversations.length){ box.innerHTML='<div class="empty">Здесь появятся ваши диалоги</div>'; return; }
  box.innerHTML=r.conversations.map(c=>{
    const badge=c.unread?`<span class="badge">${c.unread>99?'99+':c.unread}</span>`:'';
    if(c.peer.kind==='system'){
      const lastTxt=c.last?esc(c.last.body):'нет уведомлений';
      return `<div class="card system${c.id===state.convId?' active':''}" data-id="${c.id}" data-peer='${esc(JSON.stringify(c.peer))}' data-studio="0" data-system="1">
        <div class="av sys">🔔</div>
        <div class="c-main"><div class="c-top"><span class="c-name">Уведомления</span><span class="c-time">${c.ts?fmtTime(c.ts):''}</span></div>
        <div class="c-last">${lastTxt}</div></div><div>${badge}</div></div>`;
    }
    const lastTxt=c.last?(c.last.mine?'<span class="me">Вы: </span>':'')+esc(c.last.body):'<i>нет сообщений</i>';
    const studioCls=(c.peer.kind==='studio'||state.tab==='studio')?' studio':'';
    return `<div class="card${studioCls}${c.id===state.convId?' active':''}" data-id="${c.id}" data-peer='${esc(JSON.stringify(c.peer))}' data-studio="${c.type==='studio'?1:0}">
      <div class="av">${avatarHTML(c.peer)}</div>
      <div class="c-main"><div class="c-top"><span class="c-name">${esc(c.peer.name)}</span><span class="c-time">${c.ts?fmtTime(c.ts):''}</span></div>
      <div class="c-last">${lastTxt}</div>${c.peer.tag?`<div class="c-tag">→ ${esc(c.peer.tag)}</div>`:''}</div>
      <div>${badge}</div></div>`;
  }).join('');
  box.querySelectorAll('.card').forEach(el=>el.onclick=()=>openConv(+el.dataset.id,JSON.parse(el.dataset.peer),+el.dataset.studio===1,null,el.dataset.system==='1'));
}

/* ---------- тред ---------- */
function openConv(id,peer,isStudio,draft,isSystem){
  state.convId=id; state.lastId=0; state.draft=draft||null; state.isSystem=!!isSystem; state.header={peer,isStudio};
  $('#room').classList.toggle('is-studio',!!isStudio);
  $('#room').classList.toggle('is-system',!!isSystem);
  $('#composer').style.display=isSystem?'none':'';
  $('#app').classList.add('show-room');
  $('#profile').classList.remove('open'); $('#menu').hidden=true;
  $('#thread').innerHTML=''; $('#thread').dataset.lastDay='';
  $('#rhAv').innerHTML=isSystem?'🔔':avatarHTML(peer);
  $('#rhName').textContent=peer.name;
  $('#rhSub').innerHTML=`<span class="live"></span>${isSystem?'системные уведомления':(isStudio?'загрузка…':'загрузка…')}`;
  document.querySelectorAll('.card').forEach(c=>c.classList.toggle('active',+c.dataset.id===id));
  clearInterval(state.threadTimer);
  if(id>0){ loadThread().then(()=>state.threadTimer=setInterval(loadThread,POLL_THREAD[wsRateMode])); }
  else { $('#thread').innerHTML=`<div class="thread-hint">Новый эфир с ${esc(peer.name)}.<br>Напишите первое сообщение ↓</div>`; }
  $('#input').focus();
}
async function loadThread(){
  if(!state.convId) return;
  const hadMessages=state.lastId>0;
  const r=await api('thread',{conversation_id:state.convId,after_id:state.lastId});
  if(!r.ok) return;
  const h=r.header; state.header={...state.header,peer_id:h.peer_id,kind:h.kind};
  // подпись с last-seen
  let sub;
  if(h.kind==='system') sub='системные уведомления';
  else if(h.kind==='studio') sub='официальный канал студии';
  else { const ls=lastSeen(h.last_seen); sub=h.tag?('обращение · '+esc(h.tag)):(ls||'личный эфир'); }
  const online=h.kind==='user'&&h.last_seen&&(Date.now()-new Date(h.last_seen.replace(' ','T'))<90000);
  $('#rhSub').innerHTML=`<span class="live${online?'':' off'}"></span>${sub}`;
  const th=$('#thread'); const atBottom=th.scrollHeight-th.scrollTop-th.clientHeight<60;
  let html=''; let lastDay=th.dataset.lastDay||''; let gotIncoming=false;
  r.messages.forEach(m=>{
    if(!state.isSystem){ const day=fmtDay(m.at); if(day!==lastDay){ html+=`<div class="day"><span>${day}</span></div>`; lastDay=day; } }
    html+=renderMsg(m); state.lastId=Math.max(state.lastId,m.id);
    if(!m.mine) gotIncoming=true;
  });
  th.dataset.lastDay=lastDay;
  if(html){ th.insertAdjacentHTML('beforeend',html); if(atBottom) th.scrollTop=th.scrollHeight; bindDelete();
    if(hadMessages&&gotIncoming) ping();
    loadList(); }
}
function renderMsg(m){
  if(state.isSystem) return `<div class="sysmsg">${m.deleted?'<i>удалено</i>':esc(m.body)}<span class="sysmsg-time">${fmtTime(m.at)}</span></div>`;
  if(m.deleted) return `<div class="msg ${m.mine?'mine':'them'}"><div class="lane"><span class="node"></span></div><div class="bubble gone">сообщение удалено</div></div>`;
  const del=m.mine?`<button class="del" data-mid="${m.id}" title="Удалить">✕</button>`:'';
  return `<div class="msg ${m.mine?'mine':'them'}"><div class="lane"><span class="node"></span></div>
    <div class="bubble">${del}${esc(m.body)}<span class="b-time">${fmtTime(m.at)}</span></div></div>`;
}
function bindDelete(){
  document.querySelectorAll('.del').forEach(b=>b.onclick=async e=>{ e.stopPropagation();
    if(!confirm('Удалить сообщение?')) return;
    const r=await api('delete_message',{message_id:b.dataset.mid},'POST');
    if(r.ok){ const bub=b.closest('.bubble'); bub.classList.add('gone'); bub.innerHTML='сообщение удалено'; loadList(); } });
}

/* ---------- отправка ---------- */
async function send(){
  const inp=$('#input'); const body=inp.value.trim();
  if(!body||(!state.convId&&!state.draft)) return;
  inp.value=''; autosize();
  let r;
  if(state.convId>0) r=await api('send',{conversation_id:state.convId,body},'POST');
  else { const d=state.draft; r=await api('send',{...(d.to?{to:d.to}:{studio:d.studio}),body},'POST'); }
  if(!r.ok) return;
  const th=$('#thread');
  if(!state.convId){ state.convId=r.conversation_id; state.lastId=0; state.draft=null;
    th.innerHTML=''; th.dataset.lastDay=''; await loadThread(); state.threadTimer=setInterval(loadThread,POLL_THREAD[wsRateMode]); }
  else { th.insertAdjacentHTML('beforeend',renderMsg(r.message)); state.lastId=Math.max(state.lastId,r.message.id); bindDelete(); }
  th.scrollTop=th.scrollHeight; loadList();
}
function autosize(){ const t=$('#input'); t.style.height='auto'; t.style.height=Math.min(t.scrollHeight,120)+'px'; $('#send').disabled=!t.value.trim(); }
$('#input').addEventListener('input',autosize);
$('#input').addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); send(); }});
$('#send').onclick=send;
$('#back').onclick=()=>{ $('#app').classList.remove('show-room'); state.convId=0; clearInterval(state.threadTimer); };

/* ---------- меню беседы ---------- */
$('#menuBtn').onclick=e=>{ e.stopPropagation(); $('#menu').hidden=!$('#menu').hidden; };
document.addEventListener('click',()=>$('#menu').hidden=true);
$('#menu').onclick=e=>e.stopPropagation();
$('#delConv').onclick=async()=>{ $('#menu').hidden=true;
  if(!state.convId||!confirm('Удалить переписку у себя? Новое сообщение вернёт её в список.')) return;
  const r=await api('delete_conversation',{conversation_id:state.convId},'POST');
  if(r.ok){ $('#app').classList.remove('show-room'); state.convId=0; clearInterval(state.threadTimer); loadList(); } };

/* ---------- профиль по клику на шапку ---------- */
$('#peerHead').onclick=async()=>{
  const h=state.header; if(!h||h.kind==='studio'||h.kind==='system'||!h.peer_id) return;
  $('#profileBody').innerHTML='<div class="empty">Загрузка…</div>'; $('#profile').classList.add('open');
  const r=await api('user_profile',{user_id:h.peer_id});
  if(!r.ok){ $('#profileBody').innerHTML='<div class="empty">Профиль недоступен</div>'; return; }
  const p=r.profile; const handle=p.handle||''; const ls=lastSeen(p.last_seen);
  const linkDustore=handle?`/@${encodeURIComponent(handle)}`:`/player.php?id=${p.id}`;
  const linkL4T=handle?`/l4t/@${encodeURIComponent(handle)}`:'#';   // CONFIRM пути
  const linkMedia=handle?`/media/@${encodeURIComponent(handle)}`:'#';
  $('#profileBody').innerHTML=`
    <div class="big-av">${avatarHTML({name:p.name,avatar:p.avatar})}</div>
    <div class="p-name">${esc(p.name)}</div>
    ${handle?`<div class="p-handle">@${esc(handle)}</div>`:''}
    ${ls?`<div class="p-meta">${ls}</div>`:''}
    ${p.location?`<div class="p-meta">${esc(p.location)}</div>`:''}
    <div class="p-stats"><div class="p-stat"><b>${p.votes_up}</b><span>лайки</span></div><div class="p-stat"><b>${p.views}</b><span>просмотры</span></div></div>
    <div class="p-links"><a class="primary" href="${linkDustore}">Профиль Dustore</a><a href="${linkL4T}">L4T</a><a href="${linkMedia}">Медиа</a></div>`;
};
$('#profBack').onclick=()=>$('#profile').classList.remove('open');

/* ---------- поиск наверху ---------- */
let searchTimer=null;
function shakeSearch(){ const el=$('#searchInput'); el.classList.remove('shake-it'); void el.offsetWidth; el.classList.add('shake-it'); }
$('#searchInput').addEventListener('input',e=>{
  clearTimeout(searchTimer); const q=e.target.value.trim().replace(/^@/,'');
  if(q.length<2){ $('#searchResults').hidden=true; $('#list').style.display=''; return; }
  $('#list').style.display='none'; $('#searchResults').hidden=false;
  $('#searchResults').innerHTML='<div class="empty">Ищу…</div>';
  searchTimer=setTimeout(async()=>{ const r=await api('search_users',{q}); if(r.ok) renderResults(r.users); },250);
});
function renderResults(users){
  const box=$('#searchResults');
  if(!users.length){ box.innerHTML='<div class="empty">Никого не нашлось</div>'; shakeSearch(); return; }
  box.innerHTML=users.map(u=>`<div class="result" data-u='${esc(JSON.stringify(u))}'>
    <div class="av">${avatarHTML({name:u.username,avatar:u.avatar})}</div>
    <div><div class="r-name">${esc(u.username)}</div><div class="r-sub">личный эфир</div></div></div>`).join('');
  box.querySelectorAll('.result').forEach(el=>el.onclick=()=>{ const u=JSON.parse(el.dataset.u);
    $('#searchInput').value=''; $('#searchResults').hidden=true; $('#list').style.display='';
    openConv(0,{kind:'user',id:u.id,name:u.username,avatar:u.avatar},false,{to:u.id},false); });
}

/* ---------- вкладки ---------- */
document.querySelectorAll('.tab').forEach(t=>t.onclick=()=>{
  document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active')); t.classList.add('active');
  state.tab=t.dataset.tab; $('#searchInput').value=''; $('#searchResults').hidden=true; $('#list').style.display=''; loadList();
});

/* ---------- старт ---------- */
(async function init(){
  await loadList(); state.listTimer=setInterval(loadList,POLL_LIST[wsRateMode]);
  connectWS();
  if(AUTO.to||AUTO.studio){
    const r=await api('start',AUTO.to?{to:AUTO.to}:{studio:AUTO.studio});
    if(r.ok){ await loadList(); const card=document.querySelector(`.card[data-id="${r.conversation_id}"]`);
      if(card) card.click(); else openConv(r.conversation_id,{kind:AUTO.studio?'studio':'user',name:'…'},!!AUTO.studio); }
  }
})();
</script>
<?php // require __DIR__ . '/../swad/static/elements/footer.php'; ?>
</body>
</html>