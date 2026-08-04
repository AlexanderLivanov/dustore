<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Битый Пиксель — трекер багов</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --void:#0c0a12; --surf:#161121; --surf2:#1f1830; --surf3:#281f3c;
  --p:#c32178; --p-soft:#e0589f; --glitch:#2de2c4; --vuln:#ff5a3c;
  --ink:#ece8f5; --ink2:#9a90b8; --ink3:#665d82; --border:#2a2140;
  --sev-critical:#ff3b6b; --sev-high:#ff5a3c; --sev-medium:#f3a93c; --sev-low:#6c7bd1; --sev-none:#665d82;
  --ok:#2de2c4;
  --s:5px;
  --mono:'JetBrains Mono',ui-monospace,monospace;
  --display:'Press Start 2P',ui-monospace,monospace;
  --body:'Space Grotesk',system-ui,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--void);color:var(--ink);font-family:var(--body);font-size:15px;line-height:1.6;
  -webkit-font-smoothing:antialiased;min-height:100vh;
  background-image:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(255,255,255,.012) 2px,rgba(255,255,255,.012) 3px);}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
:focus-visible{outline:2px solid var(--glitch);outline-offset:2px}

.pixel{--s:5px;
  clip-path:polygon(0 var(--s),var(--s) var(--s),var(--s) 0,
    calc(100% - var(--s)) 0,calc(100% - var(--s)) var(--s),100% var(--s),
    100% calc(100% - var(--s)),calc(100% - var(--s)) calc(100% - var(--s)),calc(100% - var(--s)) 100%,
    var(--s) 100%,var(--s) calc(100% - var(--s)),0 calc(100% - var(--s)));}
.pix-border{filter:drop-shadow(1px 0 0 var(--border))drop-shadow(-1px 0 0 var(--border))drop-shadow(0 1px 0 var(--border))drop-shadow(0 -1px 0 var(--border));}
.pix-glow{filter:drop-shadow(1px 0 0 var(--p))drop-shadow(-1px 0 0 var(--p))drop-shadow(0 1px 0 var(--p))drop-shadow(0 -1px 0 var(--p));}

/* topbar */
.topbar{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:24px;
  padding:14px 26px;background:rgba(12,10,18,.86);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);}
.brand{display:flex;align-items:baseline;gap:10px;flex-shrink:0}
.brand .glyph{font-family:var(--display);font-size:13px;color:var(--p);letter-spacing:1px;
  text-shadow:-1.5px 0 var(--glitch),1.5px 0 var(--p);}
.brand .sub{font-family:var(--mono);font-size:10px;color:var(--ink3);letter-spacing:2px;text-transform:uppercase}
.nav{display:flex;gap:4px;flex:1}
.nav button{padding:8px 14px;font-size:13px;font-weight:500;color:var(--ink2);letter-spacing:.2px;
  display:flex;align-items:center;gap:7px;transition:color .15s,background .15s}
.nav button .ic{font-size:11px;color:var(--ink3)}
.nav button:hover{color:var(--ink)}
.nav button.on{color:var(--ink);background:var(--surf2)}
.nav button.on .ic{color:var(--p)}
.session-pill{flex-shrink:0;display:flex;align-items:center;gap:9px;font-family:var(--mono);font-size:11px;color:var(--ink2);
  background:var(--surf2);padding:7px 11px;letter-spacing:.3px}
.session-pill .ava{width:20px;height:20px;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:var(--display);font-size:7px;color:var(--p)}
.session-pill .role{color:var(--glitch)}
.catch{flex-shrink:0;background:var(--p);color:#fff;font-weight:700;font-size:13px;letter-spacing:.3px;
  padding:11px 18px;display:flex;align-items:center;gap:9px}
.catch:hover{background:var(--p-soft)}
.catch .dot{width:9px;height:9px;background:#fff;box-shadow:2px 0 var(--glitch),-2px 0 #fff}

.wrap{max-width:1180px;margin:0 auto;padding:34px 26px 80px}
.view{display:none}
.view.on{display:block;animation:fade .35s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

.eyebrow{font-family:var(--mono);font-size:11px;letter-spacing:3px;text-transform:uppercase;color:var(--p);margin-bottom:14px;display:flex;align-items:center;gap:10px}
.eyebrow::before{content:"";width:18px;height:2px;background:var(--p)}
h1.hero{font-family:var(--display);font-size:22px;line-height:1.5;letter-spacing:1px;margin-bottom:18px;color:var(--ink)}
h1.hero .hl{color:var(--p);text-shadow:-2px 0 var(--glitch)}
.lede{font-size:16px;color:var(--ink2);max-width:600px;margin-bottom:26px}
.lede b{color:var(--ink);font-weight:600}
.motto{font-family:var(--mono);font-size:12px;color:var(--ink3);margin-top:24px;letter-spacing:.5px}

/* stat cards */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:0 0 26px}
.stat{background:var(--surf);padding:20px}
.stat .n{font-family:var(--display);font-size:22px;color:var(--ink);margin-bottom:10px}
.stat .n.p{color:var(--p);text-shadow:-1.5px 0 var(--glitch)}
.stat .n.g{color:var(--glitch)}
.stat .n.v{color:var(--vuln)}
.stat .n.a{color:var(--sev-medium)}
.stat .l{font-family:var(--mono);font-size:11px;color:var(--ink2);letter-spacing:1px;text-transform:uppercase}
.stat .d{font-family:var(--mono);font-size:11px;color:var(--ink3);margin-top:6px}

/* chart */
.chart-shell{background:var(--surf);padding:22px}
.chart-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:14px}
.chart-title{font-weight:600;font-size:16px;display:flex;align-items:center;gap:10px}
.chart-title .tag{font-family:var(--mono);font-size:10px;color:var(--ink3);border:1px solid var(--border);padding:3px 8px;letter-spacing:1px}
.seg{display:flex;background:var(--surf2);padding:3px}
.seg button{font-family:var(--mono);font-size:11px;padding:6px 13px;color:var(--ink2);letter-spacing:.5px}
.seg button.on{background:var(--surf3);color:var(--ink)}
#chart-svg{width:100%;height:auto;display:block}
.chart-legend{display:flex;gap:20px;margin-top:16px;font-size:12px;color:var(--ink2);font-family:var(--mono)}
.chart-legend span{display:flex;align-items:center;gap:8px}
.chart-legend i{width:11px;height:11px;display:inline-block}
.li-new{background:var(--p)}
.li-closed{background:var(--glitch)}
.bar{transition:opacity .12s}
.bar:hover{opacity:.7;cursor:pointer}

/* toolbar / lists */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:22px;flex-wrap:wrap}
.toolbar .sp{flex:1}
.count{font-family:var(--mono);font-size:12px;color:var(--ink3)}

.cards{display:flex;flex-direction:column;gap:11px}
.card{background:var(--surf);padding:16px 18px;display:flex;align-items:center;gap:16px;
  border-left:3px solid var(--border);transition:background .15s,border-color .15s}
.card.clickable{cursor:pointer}
.card.clickable:hover{background:var(--surf2);border-left-color:var(--p)}
.card.locked{opacity:.92}
.card .sev{width:5px;height:38px;flex-shrink:0}
.card .main{flex:1;min-width:0}
.card .code{font-family:var(--mono);font-size:11px;color:var(--ink3);letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.card .title{font-weight:500;font-size:15px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card .title.redacted{font-family:var(--mono);color:var(--ink3);letter-spacing:2px}
.card .meta{display:flex;align-items:center;gap:9px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end}
.badge{font-family:var(--mono);font-size:10px;letter-spacing:.5px;padding:4px 8px;text-transform:uppercase;font-weight:500;white-space:nowrap}
.b-sev{color:#0c0a12}
.b-comp{background:var(--surf3);color:var(--ink2)}
.b-status{border:1px solid var(--border);color:var(--ink2)}
.b-status.closed{border-color:var(--glitch);color:var(--glitch)}
.lockhint{font-family:var(--mono);font-size:10px;color:var(--ink3);display:flex;align-items:center;gap:6px}
.votes{display:flex;align-items:center;gap:6px;font-family:var(--mono);font-size:13px;color:var(--ink2);
  background:var(--surf2);padding:7px 11px;flex-shrink:0;transition:.15s}
.votes:hover{color:var(--glitch)}
.votes.voted{color:var(--glitch)}
.votes .tri{width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:7px solid currentColor}
.empty{font-family:var(--mono);font-size:13px;color:var(--ink3);padding:30px;text-align:center;background:var(--surf)}

/* detail */
.back{font-family:var(--mono);font-size:12px;color:var(--ink2);margin-bottom:20px;display:inline-flex;align-items:center;gap:7px;letter-spacing:.5px;cursor:pointer}
.back:hover{color:var(--ink)}
.detail-grid{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start}
.panel{background:var(--surf);padding:22px}
.d-code{font-family:var(--mono);font-size:12px;color:var(--p);letter-spacing:1px;margin-bottom:8px}
.d-title{font-size:22px;font-weight:600;line-height:1.35;margin-bottom:16px}
.rich{color:var(--ink2);font-size:15px}
.rich p{margin-bottom:10px}
.rich pre{font-family:var(--mono);font-size:13px;background:#0a0810;padding:13px;margin:12px 0;color:var(--p-soft);overflow-x:auto;white-space:pre-wrap}
.rich code{font-family:var(--mono);font-size:13px;background:var(--surf2);padding:2px 6px;color:var(--p-soft)}
.rich img{max-width:100%;margin:12px 0;display:block;border:1px solid var(--border)}
.resolution{margin-top:22px;background:var(--surf2);border-left:3px solid var(--glitch);padding:16px 18px}
.resolution .rh{font-family:var(--mono);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--glitch);margin-bottom:10px;display:flex;align-items:center;gap:8px}
.resolution .rmeta{font-family:var(--mono);font-size:11px;color:var(--ink3);margin-top:10px}

.env-head{font-family:var(--mono);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--glitch);
  margin-bottom:14px;display:flex;align-items:center;gap:9px}
.env-head .auto{margin-left:auto;font-size:9px;color:var(--ink3);background:var(--surf2);padding:3px 7px;letter-spacing:1px}
.env-row{display:flex;justify-content:space-between;gap:14px;font-family:var(--mono);font-size:12px;padding:8px 0;border-bottom:1px solid var(--border)}
.env-row .k{color:var(--ink3);flex-shrink:0}
.env-row .v{color:var(--ink2);text-align:right;word-break:break-all}
.side-block{margin-bottom:18px}
.side-block .lbl{font-family:var(--mono);font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);margin-bottom:9px}
.assignee{display:flex;align-items:center;gap:10px}
.ava{width:30px;height:30px;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:var(--display);font-size:9px;color:var(--p)}
.machine{display:flex;align-items:center;gap:0;margin:6px 0 0;flex-wrap:wrap}
.stage{font-family:var(--mono);font-size:10px;letter-spacing:.5px;padding:7px 10px;background:var(--surf2);color:var(--ink3);white-space:nowrap}
.stage.done{background:var(--surf3);color:var(--ink2)}
.stage.cur{background:var(--p);color:#fff}
.stage.cur.closed{background:var(--glitch);color:#0c0a12}
.arrow{color:var(--ink3);font-size:11px;padding:0 4px}
.admin-box{margin-top:20px;background:var(--surf2);border-left:3px solid var(--sev-medium);padding:16px 18px}
.admin-box .ah{font-family:var(--mono);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--sev-medium);margin-bottom:10px;display:flex;align-items:center;gap:8px}
.admin-box textarea{width:100%;background:var(--surf);border:1px solid var(--border);color:var(--ink);font-family:var(--body);font-size:14px;padding:10px 12px;resize:vertical;min-height:70px;margin-bottom:12px}
.advance{background:var(--surf3);color:var(--ink);font-family:var(--mono);font-size:12px;padding:10px 16px;letter-spacing:.5px;display:inline-flex;align-items:center;gap:8px}
.advance:hover{background:var(--p);color:#fff}
.advance.close{background:var(--glitch);color:#0c0a12}
.advance.close:hover{background:#4bf0d6}

/* vuln banner */
.vuln-banner{background:var(--surf);border-left:3px solid var(--vuln);padding:16px 18px;margin-bottom:24px;display:flex;align-items:center;gap:14px}
.vuln-banner .lock{font-family:var(--mono);color:var(--vuln);font-size:20px}
.vuln-banner .txt{font-size:14px;color:var(--ink2)}
.vuln-banner .txt b{color:var(--ink);font-weight:600}
.cvss-chip{flex-shrink:0;width:52px;height:52px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--surf2)}
.cvss-chip .sc{font-family:var(--display);font-size:13px}
.cvss-chip .lb{font-family:var(--mono);font-size:8px;color:var(--ink3);letter-spacing:1px;margin-top:3px}

/* modal */
.overlay{display:none;position:fixed;inset:0;z-index:60;background:rgba(8,6,14,.78);backdrop-filter:blur(3px);
  align-items:flex-start;justify-content:center;padding:48px 20px;overflow-y:auto}
.overlay.on{display:flex}
.modal{background:var(--surf);width:100%;max-width:620px;padding:26px}
.modal-head{display:flex;align-items:center;gap:12px;margin-bottom:6px}
.modal-head .glyph{width:22px;height:22px;background:var(--p);box-shadow:3px 0 var(--glitch),-3px 0 var(--vuln);flex-shrink:0}
.modal-head h3{font-family:var(--display);font-size:13px;color:var(--ink);letter-spacing:.5px}
.modal-sub{font-size:13px;color:var(--ink2);margin-bottom:20px}

/* type toggle */
.type-toggle{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:20px}
.type-toggle button{padding:12px 8px;background:var(--surf2);color:var(--ink2);font-weight:600;font-size:13px;
  display:flex;flex-direction:column;align-items:center;gap:6px;transition:.15s;border-bottom:2px solid transparent}
.type-toggle button .ti{font-family:var(--mono);font-size:16px}
.type-toggle button:hover{color:var(--ink)}
.type-toggle button.on[data-type="bug"]{background:var(--surf3);color:var(--ink);border-bottom-color:var(--p)}
.type-toggle button.on[data-type="vuln"]{background:var(--surf3);color:var(--ink);border-bottom-color:var(--vuln)}
.type-toggle button.on[data-type="idea"]{background:var(--surf3);color:var(--ink);border-bottom-color:var(--glitch)}

.field{margin-bottom:15px}
.field label{font-family:var(--mono);font-size:11px;letter-spacing:1px;text-transform:uppercase;color:var(--ink3);display:block;margin-bottom:7px}
.field input,.field textarea,.field select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--ink);
  font-family:var(--body);font-size:14px;padding:10px 12px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* project search */
.proj-search{position:relative}
.proj-results{position:absolute;top:100%;left:0;right:0;z-index:5;background:var(--surf3);border:1px solid var(--border);max-height:180px;overflow-y:auto;display:none}
.proj-results.on{display:block}
.proj-item{padding:10px 12px;font-size:14px;display:flex;justify-content:space-between;gap:10px;cursor:pointer;border-bottom:1px solid var(--border)}
.proj-item:hover{background:var(--p);color:#fff}
.proj-item .pid{font-family:var(--mono);font-size:11px;color:var(--ink3)}
.proj-item:hover .pid{color:#fff}
.proj-hint{font-family:var(--mono);font-size:10px;color:var(--ink3);margin-top:6px}
.proj-selected{display:flex;align-items:center;gap:10px;background:var(--surf3);padding:10px 12px;font-size:14px;margin-top:4px}
.proj-selected .pid{font-family:var(--mono);font-size:11px;color:var(--glitch)}
.proj-selected .x{margin-left:auto;font-family:var(--mono);color:var(--ink3);cursor:pointer;font-size:16px}
.proj-selected .x:hover{color:var(--vuln)}

/* rich editor */
.editor-toolbar{display:flex;gap:2px;flex-wrap:wrap;background:var(--surf3);padding:5px}
.editor-toolbar button{font-family:var(--mono);font-size:12px;padding:7px 10px;color:var(--ink2);min-width:32px}
.editor-toolbar button:hover{background:var(--p);color:#fff}
.editor-toolbar .divider{width:1px;background:var(--border);margin:3px 4px}
.editor{min-height:120px;max-height:280px;overflow-y:auto;background:var(--surf2);border:1px solid var(--border);border-top:none;padding:12px;font-size:14px;color:var(--ink)}
.editor:empty::before{content:attr(data-ph);color:var(--ink3)}
.editor pre{font-family:var(--mono);font-size:13px;background:#0a0810;padding:10px;margin:8px 0;color:var(--p-soft);white-space:pre-wrap}
.editor img{max-width:220px;margin:8px 0;display:block;border:1px solid var(--border)}
.editor code{font-family:var(--mono);background:var(--surf3);padding:1px 5px;color:var(--p-soft)}

/* cvss calculator */
.cvss-calc{background:var(--surf2);padding:16px;margin-bottom:15px;display:none}
.cvss-calc.on{display:block}
.cvss-calc .ch{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.cvss-calc .ct{font-family:var(--mono);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--vuln)}
.cvss-score{display:flex;align-items:baseline;gap:8px}
.cvss-score .v{font-family:var(--display);font-size:18px}
.cvss-score .sev{font-family:var(--mono);font-size:11px;letter-spacing:1px;text-transform:uppercase}
.metric{margin-bottom:11px}
.metric .ml{font-family:var(--mono);font-size:10px;color:var(--ink3);letter-spacing:.5px;margin-bottom:5px}
.metric .opts{display:flex;gap:4px;flex-wrap:wrap}
.metric .opts button{font-family:var(--mono);font-size:11px;padding:5px 9px;background:var(--surf);color:var(--ink2);flex:1;min-width:44px}
.metric .opts button.on{background:var(--vuln);color:#0c0a12;font-weight:700}
.cvss-vector{font-family:var(--mono);font-size:10px;color:var(--ink3);margin-top:10px;word-break:break-all;background:var(--surf);padding:8px}

/* session capture */
.env-block{background:var(--surf2);padding:14px 16px;margin-bottom:15px}
.console{background:#0a0810;padding:12px;margin-top:12px;font-family:var(--mono);font-size:11px;line-height:1.7;color:var(--ink3)}
.console .err{color:var(--vuln)}
.console .warn{color:var(--sev-medium)}

.modal-actions{display:flex;gap:10px;margin-top:22px}
.btn-primary{flex:1;background:var(--p);color:#fff;font-weight:700;font-size:14px;padding:13px}
.btn-primary:hover{background:var(--p-soft)}
.btn-primary.vuln{background:var(--vuln)}
.btn-primary.idea{background:var(--glitch);color:#0c0a12}
.btn-ghost{background:var(--surf2);color:var(--ink2);font-size:14px;padding:13px 20px}
.btn-ghost:hover{color:var(--ink)}
.notify-note{font-family:var(--mono);font-size:10px;color:var(--ink3);margin-top:12px;display:flex;align-items:center;gap:7px}

.toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);z-index:80;
  background:var(--surf3);color:var(--ink);font-size:14px;padding:13px 20px;display:flex;align-items:center;gap:11px;
  opacity:0;transition:.3s;pointer-events:none;max-width:90vw}
.toast .dot{width:10px;height:10px;background:var(--glitch);box-shadow:2px 0 var(--p),-2px 0 var(--glitch);flex-shrink:0}
.toast.on{opacity:1;transform:translateX(-50%) translateY(0)}

@media(max-width:820px){
  .nav,.session-pill{display:none}
  .detail-grid{grid-template-columns:1fr}
  .stat-row{grid-template-columns:repeat(2,1fr)}
  h1.hero{font-size:16px}
}
</style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <span class="glyph">БИТЫЙ&nbsp;ПИКСЕЛЬ</span>
    <span class="sub">dustore.gg</span>
  </div>
  <nav class="nav" id="nav">
    <button data-view="stats" class="on"><span class="ic">▦</span>Статистика</button>
    <button data-view="bugs"><span class="ic">◧</span>Баги</button>
    <button data-view="vulns"><span class="ic">▲</span>Уязвимости</button>
    <button data-view="ideas"><span class="ic">✦</span>Предложения</button>
  </nav>
  <div class="session-pill" id="session-pill"></div>
  <button class="catch pixel" onclick="openReport()"><span class="dot"></span>Создать отчёт</button>
</header>

<main class="wrap">

  <!-- ============ СТАТИСТИКА ============ -->
  <section class="view on" id="v-stats">
    <div class="eyebrow">здоровье платформы в реальном времени</div>
    <h1 class="hero">Каждый баг — <span class="hl">битый пиксель</span>. Почини картинку.</h1>

    <div class="stat-row">
      <div class="stat pixel pix-border"><div class="n p" id="st-new">—</div><div class="l">новых</div><div class="d">ждут триажа</div></div>
      <div class="stat pixel pix-border"><div class="n a" id="st-prog">—</div><div class="l">в работе</div><div class="d">взяты в фикс</div></div>
      <div class="stat pixel pix-border"><div class="n g" id="st-closed">—</div><div class="l">закрыто</div><div class="d">за всё время</div></div>
      <div class="stat pixel pix-border"><div class="n v" id="st-vuln">—</div><div class="l">уязвимости</div><div class="d">под эмбарго</div></div>
    </div>

    <div class="chart-shell pixel pix-border">
      <div class="chart-head">
        <div class="chart-title">Новые vs закрытые <span class="tag" id="chart-range">— </span></div>
        <div class="seg" id="scale-seg">
          <button data-scale="day" class="on">дни</button>
          <button data-scale="week">недели</button>
          <button data-scale="month">месяцы</button>
        </div>
      </div>
      <svg id="chart-svg" viewBox="0 0 1000 340" preserveAspectRatio="xMidYMid meet"></svg>
      <div class="chart-legend">
        <span><i class="li-new"></i> новые баги</span>
        <span><i class="li-closed"></i> закрытые баги</span>
      </div>
    </div>
    <p class="motto">// мы не делаем лучше, мы делаем иначе</p>
  </section>

  <!-- ============ БАГИ ============ -->
  <section class="view" id="v-bugs">
    <div class="eyebrow">публичная очередь</div>
    <h1 class="hero">Баги</h1>
    <div class="vuln-banner pixel pix-border" style="border-left-color:var(--p)">
      <span class="lock" style="color:var(--p)">◧</span>
      <span class="txt">Открытые баги видны <b>только по названию</b> — описание и контекст раскрываются, когда админ проекта закрывает баг и прикладывает отчёт о фиксе.</span>
    </div>
    <div class="toolbar">
      <div class="seg" id="bug-seg">
        <button data-f="all" class="on">все</button>
        <button data-f="open">открытые</button>
        <button data-f="closed">закрытые</button>
      </div>
      <div class="sp"></div>
      <div class="count" id="bug-count"></div>
    </div>
    <div class="cards" id="bug-cards"></div>
  </section>

  <!-- ============ ДЕТАЛЬ ============ -->
  <section class="view" id="v-detail">
    <span class="back" onclick="go(lastList)">◄ назад</span>
    <div class="detail-grid">
      <div class="panel pixel pix-border" id="detail-main"></div>
      <aside>
        <div class="panel pixel pix-border" id="detail-env"></div>
      </aside>
    </div>
  </section>

  <!-- ============ УЯЗВИМОСТИ ============ -->
  <section class="view" id="v-vulns">
    <div class="eyebrow">приватный трек · coordinated disclosure</div>
    <h1 class="hero">Уязвимости</h1>
    <div class="vuln-banner pixel pix-border">
      <span class="lock">▣</span>
      <span class="txt"><b>Полное эмбарго до закрытия.</b> Пока уязвимость не исправлена, скрыто всё — включая название. Публичное advisory с CVSS и деталями выходит автоматически после фикса.</span>
    </div>
    <div class="toolbar">
      <div class="count" id="vuln-count"></div>
    </div>
    <div class="cards" id="vuln-cards"></div>
  </section>

  <!-- ============ ПРЕДЛОЖЕНИЯ ============ -->
  <section class="view" id="v-ideas">
    <div class="eyebrow">чего не хватает платформе</div>
    <h1 class="hero">Предложения</h1>
    <p class="lede">Не баг, а идея? Кидай сюда. Голоса сообщества двигают предложения в бэклог — что наберёт больше, то и обсуждаем первым.</p>
    <div class="toolbar">
      <div class="seg" id="idea-seg">
        <button data-f="all" class="on">все</button>
        <button data-f="new">новые</button>
        <button data-f="planned">в бэклоге</button>
        <button data-f="done">сделано</button>
      </div>
      <div class="sp"></div>
      <div class="count" id="idea-count"></div>
    </div>
    <div class="cards" id="idea-cards"></div>
  </section>

</main>

<!-- ============ MODAL: СОЗДАТЬ ОТЧЁТ ============ -->
<div class="overlay" id="overlay">
  <div class="modal pixel pix-glow">
    <div class="modal-head"><span class="glyph"></span><h3>Создать отчёт</h3></div>
    <p class="modal-sub">Выбери тип и проект. Контекст сессии захватится автоматически.</p>

    <div class="type-toggle" id="type-toggle">
      <button data-type="bug" class="on"><span class="ti">◧</span>Баг</button>
      <button data-type="vuln"><span class="ti">▲</span>Уязвимость</button>
      <button data-type="idea"><span class="ti">✦</span>Предложение</button>
    </div>

    <!-- project -->
    <div class="field">
      <label>проект</label>
      <div class="proj-search">
        <input id="proj-input" placeholder="ID проекта или название (поиск от 3 символов)" autocomplete="off">
        <div class="proj-results" id="proj-results"></div>
      </div>
      <div class="proj-hint">напр. <code>P-104</code> или «Neon» · поиск начинается с 3 символов</div>
      <div id="proj-selected"></div>
    </div>

    <div class="field">
      <label>заголовок</label>
      <input id="rep-title" placeholder="Коротко: что и где сломалось">
    </div>

    <!-- rich editor -->
    <div class="field">
      <label id="editor-label">описание · скриншоты · код</label>
      <div class="editor-toolbar" id="editor-toolbar">
        <button type="button" data-cmd="bold" title="жирный"><b>B</b></button>
        <button type="button" data-cmd="italic" title="курсив"><i>I</i></button>
        <button type="button" data-cmd="strikeThrough" title="зачёркнутый"><s>S</s></button>
        <div class="divider"></div>
        <button type="button" data-cmd="insertUnorderedList" title="список">☰</button>
        <button type="button" data-cmd="code" title="блок кода">&lt;/&gt;</button>
        <div class="divider"></div>
        <button type="button" data-cmd="image" title="вставить скриншот">🖼 скрин</button>
        <span style="flex:1"></span>
        <span class="proj-hint" style="margin:0;align-self:center;padding-right:6px">⌘V — вставить скрин из буфера</span>
      </div>
      <div class="editor" id="editor" contenteditable="true" data-ph="Опиши шаги воспроизведения, вставь скриншот (Ctrl/⌘+V) или блок кода…"></div>
      <input type="file" id="img-input" accept="image/*" hidden>
    </div>

    <!-- CVSS calc (vuln only) -->
    <div class="cvss-calc" id="cvss-calc">
      <div class="ch">
        <span class="ct">CVSS 3.1 · базовый вектор</span>
        <div class="cvss-score"><span class="v" id="cvss-num">0.0</span><span class="sev" id="cvss-sev">None</span></div>
      </div>
      <div id="cvss-metrics"></div>
      <div class="cvss-vector" id="cvss-vector">CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N</div>
    </div>

    <!-- severity (bug) -->
    <div class="field" id="sev-field">
      <label>severity</label>
      <select id="rep-sev">
        <option value="low">low</option>
        <option value="medium" selected>medium</option>
        <option value="high">high</option>
        <option value="critical">critical</option>
      </select>
    </div>

    <!-- session capture -->
    <div class="field" id="ctx-field">
      <label>контекст сессии · захвачено автоматически (read-only)</label>
      <div class="env-block" id="ctx-env"></div>
      <div class="console" id="ctx-console"></div>
    </div>

    <div class="modal-actions">
      <button class="btn-ghost pixel" onclick="closeReport()">Отмена</button>
      <button class="btn-primary pixel" id="submit-btn" onclick="submitReport()">Отправить отчёт</button>
    </div>
    <div class="notify-note" id="notify-note">✉ владельцу проекта уйдёт письмо о новом отчёте</div>
  </div>
</div>

<div class="toast" id="toast"><span class="dot"></span><span id="toast-txt"></span></div>

<script>
/* =====================================================================
   БИТЫЙ ПИКСЕЛЬ — трекер багов / уязвимостей / предложений
   Фронтенд-прототип. Точки интеграции с PHP-бэком помечены [BACKEND].
   ===================================================================== */

/* ---- текущий пользователь (в бою придёт из $_SESSION) ---- */
const ME = { id:'usr_8842', nick:'segfault_sam', role:'reporter', adminOf:['P-104'] };
// adminOf — список проектов, где юзер админ и может закрывать баги.

/* ---- справочники ---- */
const COMPONENTS = ['Публикация','Консоль','Джемы','Мобилка','S3','Уведомления'];
const SEV = {critical:'var(--sev-critical)',high:'var(--sev-high)',medium:'var(--sev-medium)',low:'var(--sev-low)',none:'var(--sev-none)'};
const STATUS_RU = {new:'Новый',triaged:'Триаж',in_progress:'В работе',closed:'Закрыт'};
const FLOW = ['new','triaged','in_progress','closed'];

/* ---- проекты (в бою — таблица projects, поиск на сервере) ---- */
const PROJECTS = [
  {id:'P-104', name:'Neon Drift', owner:'anna_owner', ownerEmail:'anna@dustore.gg'},
  {id:'P-108', name:'Pixel Siege', owner:'max_dev', ownerEmail:'max@dustore.gg'},
  {id:'P-115', name:'Void Runner', owner:'kate_lead', ownerEmail:'kate@dustore.gg'},
  {id:'P-120', name:'Neon Abyss Clone', owner:'anna_owner', ownerEmail:'anna@dustore.gg'},
  {id:'P-131', name:'Retro Kart', owner:'igor_pm', ownerEmail:'igor@dustore.gg'},
  {id:'P-140', name:'Dustore Core', owner:'core_team', ownerEmail:'core@dustore.gg'},
];

/* ---- данные (в бою — из БД) ---- */
let bugs = [
  {id:'BP-2026-0042',proj:'P-104',title:'Мобильная PWA сбрасывает сессию после логина',comp:'Мобилка',sev:'high',status:'in_progress',votes:14,voted:false,author:'kernel_panic',
   body:'<p>После успешного логина на мобильном клиенте <code>$_SESSION[USERDATA]</code> не пишется — следующий запрос уходит как аноним.</p><pre>// вероятно расхождение HTTP_HOST в Database.php\n$host = $_SERVER[HTTP_HOST]; // m.dustore.gg != dustore.gg</pre>',
   env:autoEnv('m.dustore.gg/jams','iOS 17 · Safari','414×896'), resolution:null},
  {id:'BP-2026-0037',proj:'P-140',title:'Чанковая выгрузка APK обрывается на файлах ≥500 МБ',comp:'S3',sev:'high',status:'triaged',votes:6,voted:false,author:'null_pointer',
   body:'<p>Сборка манифеста падает на последнем чанке — <code>chunk_NNNN.bin</code> загружен, но <code>manifest.json</code> не собирается.</p>',
   env:autoEnv('console.dustore.gg/upload','Win11 · Chrome 126','2560×1440'), resolution:null},
  {id:'BP-2026-0033',proj:'P-108',title:'Telegram-уведомления теряются при оффлайн notify_worker',comp:'Уведомления',sev:'medium',status:'new',votes:4,voted:false,author:'race_condition',
   body:'<p>Когда <code>notify_worker.php</code> недоступен, fire-and-forget молча проглатывает сообщение. Нужна очередь с ретраями.</p>',
   env:autoEnv('cron','—','—'), resolution:null},
  {id:'BP-2026-0028',proj:'P-104',title:'Карточка игры ломает грид при длинном названии города',comp:'Публикация',sev:'low',status:'new',votes:2,voted:false,author:'kernel_panic',
   body:'<p>Поле города — свободный текст без валидации. Длинная строка распирает карточку и ломает сетку каталога.</p>',
   env:autoEnv('dustore.gg/games','Android · Chrome','360×800'), resolution:null},
  {id:'BP-2026-0021',proj:'P-104',title:'Вложенные <form> в edit.php удаляют черновик при сохранении',comp:'Консоль',sev:'high',status:'closed',votes:11,voted:false,author:'glitch_hunter',
   body:'<p>Вложенные <code>&lt;form&gt;</code> отдавали PHP несколько значений <code>action</code> — побеждал последний (delete). Сохранение черновика триггерило удаление.</p>',
   env:autoEnv('console.dustore.gg/edit','Win11 · Firefox','1920×1080'),
   resolution:{by:'anna_owner',at:'28.07.2026',text:'Формы расцеплены: черновик и удаление теперь разные <form> с явным action. Добавлен CSRF-токен на каждый экшен. Регресс-тест покрывает сохранение поверх существующего черновика.'}},
  {id:'BP-2026-0018',proj:'P-108',title:'Поиск участников L4T пуст для telegram_username',comp:'Джемы',sev:'medium',status:'closed',votes:9,voted:false,author:'glitch_hunter',
   body:'<p>Автокомплит искал только по <code>username</code>, а у телеграм-регов заполнен только <code>telegram_username</code>.</p>',
   env:autoEnv('dustore.gg/jams','Win11 · Chrome 126','1920×1080'),
   resolution:{by:'max_dev',at:'22.07.2026',text:'Запрос расширен на оба поля через OR + индекс на telegram_username. Проверено на аккаунтах без обычного username.'}},
];

let vulns = [
  {id:'BP-2026-0011',proj:'P-140',title:'SSRF через webhook в интеграции reg.ru',cvss:'8.2',sevLabel:'High',status:'in_progress',author:'null_pointer',
   body:'<p>Webhook-URL интеграции не валидируется — можно указать внутренний адрес и заставить сервер ходить в метадату облака.</p>',
   env:autoEnv('console.dustore.gg/integrations','Win11 · Chrome 126','1920×1080'), resolution:null},
  {id:'BP-2026-0009',proj:'P-104',title:'Stored XSS через Quill-описание игры',cvss:'6.4',sevLabel:'Medium',status:'triaged',author:'race_condition',
   body:'<p>Quill отдаёт HTML без серверной санитизации — <code>&lt;img onerror&gt;</code> сохраняется и стреляет у любого зрителя карточки.</p>',
   env:autoEnv('console.dustore.gg/edit','Win11 · Chrome 126','1920×1080'), resolution:null},
  {id:'BP-2026-0007',proj:'P-108',title:'IDOR: чужие тимовые инвайты через invite_member.php',cvss:'7.1',sevLabel:'High',status:'closed',author:'glitch_hunter',
   body:'<p>Эндпоинт принимал <code>team_id</code> из запроса без проверки владения — можно было приглашать себя в любую команду.</p>',
   env:autoEnv('console.dustore.gg/team','Win11 · Firefox','1920×1080'),
   resolution:{by:'max_dev',at:'20.07.2026',text:'Добавлена проверка, что текущий юзер — владелец/админ team_id перед созданием инвайта. Все исторические инвайты проревьюжены. Advisory: DUST-SA-2026-003.'}},
];

let ideas = [
  {id:'IDEA-052',proj:'P-140',title:'Тёмная тема для публичных карточек игр',status:'planned',votes:31,voted:false,author:'kernel_panic',
   body:'<p>Каталог светлый, а лендинги игр тёмные — глаза режет при переходе. Дайте системную тему.</p>'},
  {id:'IDEA-048',proj:'P-104',title:'Экспорт списка багов проекта в CSV',status:'new',votes:12,voted:false,author:'segfault_sam',
   body:'<p>Для отчётности удобно выгружать очередь багов таблицей.</p>'},
  {id:'IDEA-041',proj:'P-108',title:'Вебхук в Discord при закрытии бага',status:'done',votes:24,voted:false,author:'race_condition',
   body:'<p>Пусть канал команды пингуется, когда пиксель залечен.</p>'},
];

function autoEnv(url,client,vp){ return {url,client,vp}; }

/* =====================================================================
   ДАШБОРД + ГРАФИКИ
   ===================================================================== */
/* синтетическая история 120 дней (в бою — агрегат по created_at/closed_at) */
const HISTORY = (function(){
  const days=120, out=[]; let seed=1337;
  const rnd=()=>{seed=(seed*1103515245+12345)&0x7fffffff;return seed/0x7fffffff;};
  const today=new Date('2026-08-02');
  for(let i=days-1;i>=0;i--){
    const d=new Date(today); d.setDate(d.getDate()-i);
    const wd=d.getDay(); const workfactor=(wd===0||wd===6)?0.4:1;
    const nw=Math.round((1+rnd()*6)*workfactor);
    const cl=Math.round((1+rnd()*5)*workfactor*(i<days-3?1:0.3));
    out.push({date:d,new:nw,closed:cl});
  }
  return out;
})();

let scale='day';
function aggregate(sc){
  if(sc==='day') return HISTORY.slice(-14).map(d=>({label:fmtDay(d.date),new:d.new,closed:d.closed}));
  if(sc==='week'){
    const wk=[]; for(let i=HISTORY.length-1;i>=0;i-=7){
      const chunk=HISTORY.slice(Math.max(0,i-6),i+1);
      wk.unshift({label:fmtDay(chunk[0].date),new:sum(chunk,'new'),closed:sum(chunk,'closed')});
    }
    return wk.slice(-12);
  }
  // month
  const map={};
  HISTORY.forEach(d=>{const k=d.date.getFullYear()+'-'+d.date.getMonth();
    if(!map[k])map[k]={label:fmtMonth(d.date),new:0,closed:0};
    map[k].new+=d.new; map[k].closed+=d.closed;});
  return Object.values(map).slice(-6);
}
const sum=(a,k)=>a.reduce((s,x)=>s+x[k],0);
const MO=['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
function fmtDay(d){return d.getDate()+'.'+String(d.getMonth()+1).padStart(2,'0');}
function fmtMonth(d){return MO[d.getMonth()];}

function drawChart(){
  const data=aggregate(scale);
  const svg=document.getElementById('chart-svg');
  const W=1000,H=340, padL=40,padR=10,padT=16,padB=42;
  const plotW=W-padL-padR, plotH=H-padT-padB;
  const maxV=Math.max(4,...data.map(d=>Math.max(d.new,d.closed)));
  const n=data.length, groupW=plotW/n, barW=Math.min(22, groupW*0.32);
  let g='';
  // gridlines + y labels
  const steps=4;
  for(let s=0;s<=steps;s++){
    const val=Math.round(maxV*s/steps), y=padT+plotH-(plotH*s/steps);
    g+=`<line x1="${padL}" y1="${y}" x2="${W-padR}" y2="${y}" stroke="#2a2140" stroke-width="1"/>`;
    g+=`<text x="${padL-8}" y="${y+4}" fill="#665d82" font-size="12" font-family="monospace" text-anchor="end">${val}</text>`;
  }
  data.forEach((d,i)=>{
    const cx=padL+groupW*i+groupW/2;
    const hN=plotH*d.new/maxV, hC=plotH*d.closed/maxV;
    const xN=cx-barW-1, xC=cx+1;
    g+=`<rect class="bar" x="${xN}" y="${padT+plotH-hN}" width="${barW}" height="${hN}" fill="#c32178"><title>${d.label} · новых: ${d.new}</title></rect>`;
    g+=`<rect class="bar" x="${xC}" y="${padT+plotH-hC}" width="${barW}" height="${hC}" fill="#2de2c4"><title>${d.label} · закрыто: ${d.closed}</title></rect>`;
    g+=`<text x="${cx}" y="${H-padB+18}" fill="#9a90b8" font-size="11" font-family="monospace" text-anchor="middle">${d.label}</text>`;
  });
  svg.innerHTML=g;
  document.getElementById('chart-range').textContent=
    scale==='day'?'14 дней':scale==='week'?'12 недель':'6 месяцев';
}
document.querySelectorAll('#scale-seg button').forEach(b=>b.onclick=()=>{
  scale=b.dataset.scale;
  document.querySelectorAll('#scale-seg button').forEach(x=>x.classList.toggle('on',x===b));
  drawChart();
});

function renderStats(){
  document.getElementById('st-new').textContent=bugs.filter(b=>b.status==='new').length;
  document.getElementById('st-prog').textContent=bugs.filter(b=>b.status==='triaged'||b.status==='in_progress').length;
  document.getElementById('st-closed').textContent=bugs.filter(b=>b.status==='closed').length + 11;
  document.getElementById('st-vuln').textContent=vulns.filter(v=>v.status!=='closed').length;
}

/* =====================================================================
   BUGS — публичный список: заголовок всегда, тело только у закрытых
   ===================================================================== */
let bugFilter='all', lastList='bugs';
function renderBugs(){
  const el=document.getElementById('bug-cards'); el.innerHTML='';
  let list=bugs.slice().sort((a,b)=>FLOW.indexOf(a.status)-FLOW.indexOf(b.status));
  if(bugFilter==='open') list=list.filter(b=>b.status!=='closed');
  if(bugFilter==='closed') list=list.filter(b=>b.status==='closed');
  if(!list.length){el.innerHTML='<div class="empty">пусто</div>';document.getElementById('bug-count').textContent='';return;}
  list.forEach(b=>{
    const closed=b.status==='closed';
    const c=document.createElement('div');
    c.className='card pixel'+(closed?' clickable':' locked');
    if(closed) c.onclick=()=>openDetail('bug',b.id);
    c.innerHTML=`
      <div class="sev" style="background:${SEV[b.sev]}"></div>
      <div class="main">
        <div class="code">${b.id}<span class="badge b-comp">${b.comp}</span><span>${projName(b.proj)}</span></div>
        <div class="title">${esc(b.title)}</div>
      </div>
      <div class="meta">
        <span class="badge b-sev" style="background:${SEV[b.sev]}">${b.sev}</span>
        <span class="badge b-status ${closed?'closed':''}">${STATUS_RU[b.status]}</span>
        ${closed?`<span class="lockhint" style="color:var(--glitch)">▣ отчёт открыт</span>`
                :`<span class="lockhint">🔒 детали после закрытия</span>`}
        <button class="votes pixel ${b.voted?'voted':''}" onclick="event.stopPropagation();voteBug('${b.id}')"><span class="tri"></span>${b.votes}</button>
      </div>`;
    el.appendChild(c);
  });
  document.getElementById('bug-count').textContent=list.length+' / '+bugs.length+' репортов';
}
function voteBug(id){const b=bugs.find(x=>x.id===id);b.voted=!b.voted;b.votes+=b.voted?1:-1;
  if(b.voted)toast('«Я тоже поймал» — приоритет '+id+' поднят');renderBugs();}
document.querySelectorAll('#bug-seg button').forEach(b=>b.onclick=()=>{
  bugFilter=b.dataset.f;document.querySelectorAll('#bug-seg button').forEach(x=>x.classList.toggle('on',x===b));renderBugs();});

/* =====================================================================
   VULNS — полное эмбарго: до закрытия скрыто даже название
   ===================================================================== */
function renderVulns(){
  const el=document.getElementById('vuln-cards'); el.innerHTML='';
  const disclosed=vulns.filter(v=>v.status==='closed');
  const embargoed=vulns.filter(v=>v.status!=='closed');
  disclosed.forEach(v=>{
    const c=document.createElement('div');
    c.className='card pixel clickable'; c.style.borderLeftColor='var(--vuln)';
    c.onclick=()=>openDetail('vuln',v.id);
    c.innerHTML=`
      <div class="cvss-chip pixel"><span class="sc" style="color:${cvssColor(+v.cvss)}">${v.cvss}</span><span class="lb">CVSS</span></div>
      <div class="main">
        <div class="code">${v.id}<span>${projName(v.proj)}</span></div>
        <div class="title">${esc(v.title)}</div>
      </div>
      <div class="meta">
        <span class="badge b-sev" style="background:${cvssColor(+v.cvss)}">${v.sevLabel}</span>
        <span class="badge b-status closed">Advisory</span>
      </div>`;
    el.appendChild(c);
  });
  // редактированные плейсхолдеры для эмбарго
  embargoed.forEach(v=>{
    const c=document.createElement('div');
    c.className='card pixel locked'; c.style.borderLeftColor='var(--border)';
    c.innerHTML=`
      <div class="sev" style="background:var(--sev-none)"></div>
      <div class="main">
        <div class="code">▓▓▓-▓▓▓▓-▓▓▓▓<span>${myVuln(v)?projName(v.proj):'скрыто'}</span></div>
        <div class="title redacted">▓▓▓▓▓▓▓▓ ▓▓▓▓▓▓ ▓▓▓▓▓▓▓▓▓▓▓</div>
      </div>
      <div class="meta">
        <span class="lockhint">🔒 под эмбарго до фикса</span>
        ${myVuln(v)?`<span class="badge b-status">твой репорт · ${STATUS_RU[v.status]}</span>`:''}
      </div>`;
    el.appendChild(c);
  });
  document.getElementById('vuln-count').textContent=
    disclosed.length+' advisory · '+embargoed.length+' под эмбарго';
}
// автор/стафф может видеть, что его собственная уязвимость существует (но не публично)
function myVuln(v){return v.author===ME.nick;}

/* =====================================================================
   IDEAS — предложения
   ===================================================================== */
const IDEA_RU={new:'Новое',planned:'В бэклоге',done:'Сделано'};
let ideaFilter='all';
function renderIdeas(){
  const el=document.getElementById('idea-cards'); el.innerHTML='';
  let list=ideas.slice().sort((a,b)=>b.votes-a.votes);
  if(ideaFilter!=='all') list=list.filter(i=>i.status===ideaFilter);
  if(!list.length){el.innerHTML='<div class="empty">пусто</div>';document.getElementById('idea-count').textContent='';return;}
  list.forEach(it=>{
    const c=document.createElement('div');
    c.className='card pixel clickable'; c.style.borderLeftColor='var(--glitch)';
    c.onclick=()=>openDetail('idea',it.id);
    c.innerHTML=`
      <div class="sev" style="background:var(--glitch)"></div>
      <div class="main">
        <div class="code">${it.id}<span>${projName(it.proj)}</span></div>
        <div class="title">${esc(it.title)}</div>
      </div>
      <div class="meta">
        <span class="badge b-status ${it.status==='done'?'closed':''}">${IDEA_RU[it.status]}</span>
        <button class="votes pixel ${it.voted?'voted':''}" onclick="event.stopPropagation();voteIdea('${it.id}')"><span class="tri"></span>${it.votes}</button>
      </div>`;
    el.appendChild(c);
  });
  document.getElementById('idea-count').textContent=list.length+' предложений';
}
function voteIdea(id){const it=ideas.find(x=>x.id===id);it.voted=!it.voted;it.votes+=it.voted?1:-1;renderIdeas();}
document.querySelectorAll('#idea-seg button').forEach(b=>b.onclick=()=>{
  ideaFilter=b.dataset.f;document.querySelectorAll('#idea-seg button').forEach(x=>x.classList.toggle('on',x===b));renderIdeas();});

/* =====================================================================
   DETAIL
   ===================================================================== */
let cur=null, curKind=null;
function openDetail(kind,id){
  curKind=kind;
  cur=(kind==='bug'?bugs:kind==='vuln'?vulns:ideas).find(x=>x.id===id);
  lastList=kind==='bug'?'bugs':kind==='vuln'?'vulns':'ideas';
  go('detail'); renderDetail();
}
function renderDetail(){
  const b=cur, main=document.getElementById('detail-main'), aside=document.getElementById('detail-env');

  if(curKind==='idea'){
    main.innerHTML=`
      <div class="d-code" style="color:var(--glitch)">${b.id} · ${projName(b.proj)}</div>
      <div class="d-title">${esc(b.title)}</div>
      <div class="rich">${b.body}</div>`;
    aside.innerHTML=`
      <div class="env-head">предложение <span class="auto">idea</span></div>
      <div class="env-row"><span class="k">статус</span><span class="v">${IDEA_RU[b.status]}</span></div>
      <div class="env-row"><span class="k">автор</span><span class="v">${b.author}</span></div>
      <div class="env-row"><span class="k">голоса</span><span class="v">${b.votes}</span></div>`;
    return;
  }

  const isBug=curKind==='bug';
  const idx=FLOW.indexOf(b.status);
  let machine='';
  FLOW.forEach((s,i)=>{
    const cls=i<idx?'done':i===idx?('cur'+(s==='closed'?' closed':'')):'';
    machine+=`<span class="stage ${cls}">${STATUS_RU[s]}</span>`;
    if(i<FLOW.length-1)machine+='<span class="arrow">►</span>';
  });

  const canAdmin=ME.adminOf.includes(b.proj);
  let adminBox='';
  if(canAdmin && b.status!=='closed'){
    adminBox=`
      <div class="admin-box">
        <div class="ah">▣ действия админа проекта — ${projName(b.proj)}</div>
        ${idx<FLOW.length-2?`<button class="advance pixel" onclick="advance()">продвинуть → ${STATUS_RU[FLOW[idx+1]]} ►</button><div style="height:12px"></div>`:''}
        <div style="font-family:var(--mono);font-size:11px;color:var(--ink3);margin-bottom:8px">закрыть = приложить отчёт о фиксе (станет виден автору и публично):</div>
        <textarea id="resolve-text" placeholder="Что и как исправлено, ссылка на коммит/advisory…"></textarea>
        <button class="advance close pixel" onclick="closeItem()">▣ закрыть и опубликовать отчёт</button>
      </div>`;
  } else if(!canAdmin && b.status!=='closed'){
    adminBox=`<div style="margin-top:18px;font-family:var(--mono);font-size:11px;color:var(--ink3)">🔒 закрыть может только админ проекта ${projName(b.proj)}</div>`;
  }

  const cvssBlock = !isBug ? `<div class="cvss-chip pixel" style="width:auto;padding:8px 14px;display:inline-flex;margin-bottom:14px"><span class="sc" style="color:${cvssColor(+b.cvss)};font-size:15px">CVSS ${b.cvss} · ${b.sevLabel}</span></div><br>` : '';

  main.innerHTML=`
    <div class="d-code" style="${isBug?'':'color:var(--vuln)'}">${b.id} · ${projName(b.proj)}${isBug?' · '+b.comp:''}</div>
    ${cvssBlock}
    <div class="d-title">${esc(b.title)}</div>
    <div class="rich">${b.body}</div>
    ${b.resolution?`
      <div class="resolution">
        <div class="rh">▣ отчёт о фиксе</div>
        <div class="rich">${esc(b.resolution.text)}</div>
        <div class="rmeta">закрыл ${b.resolution.by} · ${b.resolution.at}</div>
      </div>`:''}
    <div class="env-head" style="margin-top:22px;color:var(--ink3)">жизненный цикл</div>
    <div class="machine">${machine}</div>
    ${adminBox}`;

  aside.innerHTML=`
    <div class="env-head">окружение <span class="auto">авто</span></div>
    <div class="env-row"><span class="k">url</span><span class="v">${b.env.url}</span></div>
    <div class="env-row"><span class="k">клиент</span><span class="v">${b.env.client}</span></div>
    <div class="env-row"><span class="k">viewport</span><span class="v">${b.env.vp}</span></div>
    <div class="env-row"><span class="k">сборка</span><span class="v">dustore v0.9.4</span></div>
    <div class="side-block" style="margin-top:18px">
      <div class="lbl">репортёр</div>
      <div class="assignee"><span class="ava">${b.author.slice(0,2).toUpperCase()}</span><span style="font-family:var(--mono);font-size:13px;color:var(--ink2)">${b.author}</span></div>
    </div>
    <div class="side-block"><div class="lbl">воспроизводимость</div>
      <div class="assignee" style="font-family:var(--mono);font-size:13px;color:var(--ink2)">▣ подтвердили ${b.votes||'—'} раз</div></div>`;
}

function advance(){
  const idx=FLOW.indexOf(cur.status);
  if(idx>=FLOW.length-2)return;
  cur.status=FLOW[idx+1];
  // [BACKEND] при переходе в работу — письмо автору
  if(cur.status==='in_progress') toast('Статус → В работе · ✉ письмо автору '+cur.author);
  else toast('Статус → '+STATUS_RU[cur.status]);
  renderDetail(); renderBugs(); renderVulns(); renderStats();
}
function closeItem(){
  const t=document.getElementById('resolve-text').value.trim();
  if(!t){toast('Заполни отчёт о фиксе перед закрытием');return;}
  cur.status='closed';
  cur.resolution={by:ME.nick,at:'02.08.2026',text:t};
  // [BACKEND] письмо автору о закрытии + публикация advisory для уязвимостей
  toast('Пиксель залечен · '+cur.id+' опубликован · ✉ письмо автору '+cur.author);
  renderDetail(); renderBugs(); renderVulns(); renderStats();
}

/* =====================================================================
   CVSS 3.1 — базовый калькулятор
   ===================================================================== */
const CVSS_METRICS=[
  {k:'AV',label:'Attack Vector',opts:[['N','Network',.85],['A','Adjacent',.62],['L','Local',.55],['P','Physical',.2]]},
  {k:'AC',label:'Attack Complexity',opts:[['L','Low',.77],['H','High',.44]]},
  {k:'PR',label:'Privileges Required',opts:[['N','None',.85],['L','Low',.62],['H','High',.27]]},
  {k:'UI',label:'User Interaction',opts:[['N','None',.85],['R','Required',.62]]},
  {k:'S',label:'Scope',opts:[['U','Unchanged',0],['C','Changed',0]]},
  {k:'C',label:'Confidentiality',opts:[['N','None',0],['L','Low',.22],['H','High',.56]]},
  {k:'I',label:'Integrity',opts:[['N','None',0],['L','Low',.22],['H','High',.56]]},
  {k:'A',label:'Availability',opts:[['N','None',0],['L','Low',.22],['H','High',.56]]},
];
let cvssState={AV:'N',AC:'L',PR:'N',UI:'N',S:'U',C:'N',I:'N',A:'N'};

function buildCvss(){
  const el=document.getElementById('cvss-metrics'); el.innerHTML='';
  CVSS_METRICS.forEach(m=>{
    const wrap=document.createElement('div'); wrap.className='metric';
    wrap.innerHTML=`<div class="ml">${m.k} · ${m.label}</div>`;
    const opts=document.createElement('div'); opts.className='opts';
    m.opts.forEach(([code,name])=>{
      const btn=document.createElement('button');
      btn.textContent=code+' · '+name;
      btn.className=cvssState[m.k]===code?'on':'';
      btn.onclick=()=>{cvssState[m.k]=code;buildCvss();};
      opts.appendChild(btn);
    });
    wrap.appendChild(opts); el.appendChild(wrap);
  });
  calcCvss();
}
function calcCvss(){
  const v=cvssState;
  const AV={N:.85,A:.62,L:.55,P:.2}[v.AV];
  const AC={L:.77,H:.44}[v.AC];
  const scopeChanged=v.S==='C';
  const PR=(scopeChanged?{N:.85,L:.68,H:.5}:{N:.85,L:.62,H:.27})[v.PR];
  const UI={N:.85,R:.62}[v.UI];
  const imp={N:0,L:.22,H:.56};
  const iscBase=1-((1-imp[v.C])*(1-imp[v.I])*(1-imp[v.A]));
  let impact = scopeChanged
    ? 7.52*(iscBase-0.029)-3.25*Math.pow(iscBase-0.02,15)
    : 6.42*iscBase;
  const expl=8.22*AV*AC*PR*UI;
  let score;
  if(impact<=0) score=0;
  else score=roundup(Math.min((scopeChanged?1.08:1)*(impact+expl),10));
  const sev=cvssSev(score);
  document.getElementById('cvss-num').textContent=score.toFixed(1);
  const sevEl=document.getElementById('cvss-sev');
  sevEl.textContent=sev; sevEl.style.color=cvssColor(score);
  document.getElementById('cvss-num').style.color=cvssColor(score);
  document.getElementById('cvss-vector').textContent=
    `CVSS:3.1/AV:${v.AV}/AC:${v.AC}/PR:${v.PR}/UI:${v.UI}/S:${v.S}/C:${v.C}/I:${v.I}/A:${v.A}`;
  return {score,sev};
}
function roundup(x){return Math.ceil(x*10)/10;}
function cvssSev(s){return s===0?'None':s<4?'Low':s<7?'Medium':s<9?'High':'Critical';}
function cvssColor(s){return s===0?'var(--sev-none)':s<4?'var(--sev-low)':s<7?'var(--sev-medium)':s<9?'var(--sev-high)':'var(--sev-critical)';}

/* =====================================================================
   ОТЧЁТ — модалка (тип, проект, редактор, cvss, контекст)
   ===================================================================== */
let repType='bug', selProj=null;

function openReport(){
  setType('bug'); selProj=null;
  document.getElementById('proj-selected').innerHTML='';
  document.getElementById('proj-input').value='';
  document.getElementById('rep-title').value='';
  document.getElementById('editor').innerHTML='';
  buildCvss();
  captureSession();
  document.getElementById('overlay').classList.add('on');
}
function closeReport(){document.getElementById('overlay').classList.remove('on');}

function setType(t){
  repType=t;
  document.querySelectorAll('#type-toggle button').forEach(b=>b.classList.toggle('on',b.dataset.type===t));
  document.getElementById('cvss-calc').classList.toggle('on',t==='vuln');
  document.getElementById('sev-field').style.display=t==='bug'?'':'none';
  document.getElementById('ctx-field').style.display=t==='idea'?'none':'';
  const sb=document.getElementById('submit-btn');
  sb.className='btn-primary pixel'+(t==='vuln'?' vuln':t==='idea'?' idea':'');
  sb.textContent=t==='idea'?'Отправить предложение':'Отправить отчёт';
  const note=document.getElementById('notify-note');
  note.textContent=t==='idea'?'✦ предложение попадёт в общий список на голосование'
    :'✉ владельцу проекта уйдёт письмо о новом отчёте';
  document.getElementById('editor-label').textContent=
    t==='idea'?'суть предложения':'описание · скриншоты · код';
}
document.querySelectorAll('#type-toggle button').forEach(b=>b.onclick=()=>setType(b.dataset.type));

/* ---- поиск проекта: ID или название от 3 символов ---- */
const projInput=document.getElementById('proj-input');
projInput.addEventListener('input',()=>{
  const q=projInput.value.trim().toLowerCase();
  const box=document.getElementById('proj-results');
  if(q.length<3){box.classList.remove('on');box.innerHTML='';return;}
  // [BACKEND] в бою — запрос к /api/projects/search?q=
  const res=PROJECTS.filter(p=>p.id.toLowerCase().includes(q)||p.name.toLowerCase().includes(q));
  box.innerHTML=res.length?res.map(p=>
    `<div class="proj-item" onclick="pickProj('${p.id}')"><span>${p.name}</span><span class="pid">${p.id}</span></div>`).join('')
    :`<div class="proj-item" style="cursor:default;color:var(--ink3)">ничего не найдено</div>`;
  box.classList.add('on');
});
function pickProj(id){
  selProj=PROJECTS.find(p=>p.id===id);
  document.getElementById('proj-results').classList.remove('on');
  projInput.value='';
  document.getElementById('proj-selected').innerHTML=
    `<div class="proj-selected"><span>${selProj.name}</span><span class="pid">${selProj.id}</span>
     <span class="pid">владелец: ${selProj.owner}</span><span class="x" onclick="clearProj()">✕</span></div>`;
}
function clearProj(){selProj=null;document.getElementById('proj-selected').innerHTML='';}

/* ---- rich editor ---- */
const editor=document.getElementById('editor');
document.querySelectorAll('#editor-toolbar button').forEach(b=>b.onclick=()=>{
  editor.focus();
  const cmd=b.dataset.cmd;
  if(cmd==='code'){document.execCommand('insertHTML',false,'<pre>// вставь код…</pre><p><br></p>');}
  else if(cmd==='image'){document.getElementById('img-input').click();}
  else document.execCommand(cmd,false,null);
});
document.getElementById('img-input').addEventListener('change',e=>{
  const f=e.target.files[0]; if(!f)return; insertImage(f); e.target.value='';
});
// вставка скриншота из буфера обмена
editor.addEventListener('paste',e=>{
  const items=e.clipboardData&&e.clipboardData.items;
  if(!items)return;
  for(const it of items){
    if(it.type.indexOf('image')===0){e.preventDefault();insertImage(it.getAsFile());return;}
  }
});
function insertImage(file){
  const r=new FileReader();
  r.onload=ev=>{document.execCommand('insertHTML',false,`<img src="${ev.target.result}" alt="screenshot"><p><br></p>`);};
  r.readAsDataURL(file);
}

/* ---- захват контекста сессии ---- */
function captureSession(){
  const ua=navigator.userAgent;
  let browser=/Firefox/.test(ua)?'Firefox':/Edg/.test(ua)?'Edge':/Chrome/.test(ua)?'Chrome':/Safari/.test(ua)?'Safari':'Browser';
  let os=/Windows/.test(ua)?'Windows':/Mac/.test(ua)?'macOS':/Android/.test(ua)?'Android':/iPhone|iPad/.test(ua)?'iOS':/Linux/.test(ua)?'Linux':'OS';
  // [BACKEND] $_SESSION, $_SERVER['REMOTE_ADDR'], заголовки — подставляет PHP при рендере
  document.getElementById('ctx-env').innerHTML=`
    <div class="env-row"><span class="k">$_SESSION.user_id</span><span class="v">${ME.id}</span></div>
    <div class="env-row"><span class="k">$_SESSION.role</span><span class="v">${ME.role}</span></div>
    <div class="env-row"><span class="k">$_SESSION.csrf</span><span class="v">a3f1…c2e9</span></div>
    <div class="env-row"><span class="k">REMOTE_ADDR</span><span class="v">85.140.___.__ (masked)</span></div>
    <div class="env-row"><span class="k">User-Agent</span><span class="v">${os} · ${browser}</span></div>
    <div class="env-row"><span class="k">viewport</span><span class="v">${innerWidth}×${innerHeight}</span></div>
    <div class="env-row"><span class="k">referer</span><span class="v">${(document.referrer||location.href).slice(0,42)}</span></div>
    <div class="env-row"><span class="k">lang</span><span class="v">${navigator.language}</span></div>
    <div class="env-row"><span class="k">build</span><span class="v">dustore v0.9.4 (a3f1c2)</span></div>`;
  document.getElementById('ctx-console').innerHTML=
    `<span style="color:var(--ink2)">// последние записи из console (перехвачено):</span><br>`+
    `<span class="warn">⚠ [chunk] manifest assembly retry 2/3</span><br>`+
    `<span class="err">✕ POST /upload/finalize → 504 (gateway timeout)</span>`;
}

/* ---- submit ---- */
function submitReport(){
  if(!selProj){toast('Сначала выбери проект');return;}
  const title=document.getElementById('rep-title').value.trim();
  if(!title){toast('Добавь заголовок');return;}
  const body=editor.innerHTML.trim()||'<p>Без описания — см. захваченный контекст.</p>';
  const ua=navigator.userAgent;
  let browser=/Firefox/.test(ua)?'Firefox':/Edg/.test(ua)?'Edge':/Chrome/.test(ua)?'Chrome':'Safari';
  let os=/Windows/.test(ua)?'Windows':/Mac/.test(ua)?'macOS':/Android/.test(ua)?'Android':'iOS';
  const env=autoEnv(location.host||'dustore.gg',os+' · '+browser,innerWidth+'×'+innerHeight);

  if(repType==='idea'){
    const id='IDEA-'+String(53+ideas.length).padStart(3,'0');
    ideas.unshift({id,proj:selProj.id,title,status:'new',votes:1,voted:true,author:ME.nick,body});
    closeReport(); go('ideas'); renderIdeas();
    toast('Предложение отправлено · '+id);
    return;
  }
  const n=String(43+bugs.length+vulns.length).padStart(4,'0');
  const id='BP-2026-0'+n;
  if(repType==='vuln'){
    const {score,sev}=calcCvss();
    vulns.unshift({id,proj:selProj.id,title,cvss:score.toFixed(1),sevLabel:sev,status:'new',author:ME.nick,body,env,resolution:null});
    closeReport(); go('vulns'); renderVulns(); renderStats();
    // [BACKEND] mail(selProj.ownerEmail, 'Новая уязвимость', …) — приватно
    toast('Уязвимость отправлена приватно · ✉ владельцу '+selProj.owner+' (эмбарго активно)');
  } else {
    const sev=document.getElementById('rep-sev').value;
    bugs.unshift({id,proj:selProj.id,title,comp:'Публикация',sev,status:'new',votes:1,voted:true,author:ME.nick,body,env,resolution:null});
    closeReport(); go('bugs'); bugFilter='all';
    document.querySelectorAll('#bug-seg button').forEach(x=>x.classList.toggle('on',x.dataset.f==='all'));
    renderBugs(); renderStats();
    // [BACKEND] mail(selProj.ownerEmail, 'Новый баг', …)
    toast('Баг создан · '+id+' · ✉ письмо владельцу '+selProj.owner);
  }
}

/* =====================================================================
   утилиты / навигация
   ===================================================================== */
function projName(id){const p=PROJECTS.find(x=>x.id===id);return p?p.name:id;}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function go(view){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('on'));
  document.getElementById('v-'+view).classList.add('on');
  document.querySelectorAll('#nav button').forEach(b=>b.classList.toggle('on',
    b.dataset.view===view || (view==='detail'&&b.dataset.view===lastList)));
  scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('#nav button').forEach(b=>b.onclick=()=>go(b.dataset.view));
document.getElementById('overlay').onclick=e=>{if(e.target.id==='overlay')closeReport();};

let toastT;
function toast(msg){clearTimeout(toastT);
  document.getElementById('toast-txt').textContent=msg;
  document.getElementById('toast').classList.add('on');
  toastT=setTimeout(()=>document.getElementById('toast').classList.remove('on'),3200);}

/* init */
document.getElementById('session-pill').innerHTML=
  `<span class="ava">${ME.nick.slice(0,2).toUpperCase()}</span>${ME.nick} · <span class="role">${ME.role}</span>`;
renderStats(); drawChart(); renderBugs(); renderVulns(); renderIdeas();
</script>
</body>
</html>