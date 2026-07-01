<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Битый Пиксель — концепт</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --void:#0c0a12; --surf:#161121; --surf2:#1f1830; --surf3:#281f3c;
  --p:#c32178; --p-soft:#e0589f; --glitch:#2de2c4; --vuln:#ff5a3c;
  --ink:#ece8f5; --ink2:#9a90b8; --ink3:#665d82; --border:#2a2140;
  --sev-critical:#ff3b6b; --sev-high:#ff5a3c; --sev-medium:#f3a93c; --sev-low:#6c7bd1; --sev-info:#665d82;
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

/* pixel notched corners (his trick: two points per corner) */
.pixel{--s:5px;
  clip-path:polygon(0 var(--s),var(--s) var(--s),var(--s) 0,
    calc(100% - var(--s)) 0,calc(100% - var(--s)) var(--s),100% var(--s),
    100% calc(100% - var(--s)),calc(100% - var(--s)) calc(100% - var(--s)),calc(100% - var(--s)) 100%,
    var(--s) 100%,var(--s) calc(100% - var(--s)),0 calc(100% - var(--s)));}
.pix-border{filter:drop-shadow(1px 0 0 var(--border))drop-shadow(-1px 0 0 var(--border))drop-shadow(0 1px 0 var(--border))drop-shadow(0 -1px 0 var(--border));}
.pix-glow{filter:drop-shadow(1px 0 0 var(--p))drop-shadow(-1px 0 0 var(--p))drop-shadow(0 1px 0 var(--p))drop-shadow(0 -1px 0 var(--p));}

/* ---------- top bar ---------- */
.topbar{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:24px;
  padding:14px 26px;background:rgba(12,10,18,.86);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);}
.brand{display:flex;align-items:baseline;gap:10px;flex-shrink:0}
.brand .glyph{font-family:var(--display);font-size:13px;color:var(--p);letter-spacing:1px;
  text-shadow:-1.5px 0 var(--glitch),1.5px 0 var(--p);}
.brand .sub{font-family:var(--mono);font-size:10px;color:var(--ink3);letter-spacing:2px;text-transform:uppercase}
.nav{display:flex;gap:4px;flex:1}
.nav button{padding:8px 14px;font-size:13px;font-weight:500;color:var(--ink2);border-radius:0;letter-spacing:.2px;
  display:flex;align-items:center;gap:7px;transition:color .15s,background .15s}
.nav button .ic{font-size:11px;color:var(--ink3)}
.nav button:hover{color:var(--ink)}
.nav button.on{color:var(--ink);background:var(--surf2)}
.nav button.on .ic{color:var(--p)}
.catch{flex-shrink:0;background:var(--p);color:#fff;font-weight:700;font-size:13px;letter-spacing:.3px;
  padding:11px 18px;display:flex;align-items:center;gap:9px}
.catch:hover{background:var(--p-soft)}
.catch .dot{width:9px;height:9px;background:#fff;box-shadow:2px 0 var(--glitch),-2px 0 #fff}

/* ---------- layout ---------- */
.wrap{max-width:1180px;margin:0 auto;padding:34px 26px 80px}
.view{display:none}
.view.on{display:block;animation:fade .35s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

.eyebrow{font-family:var(--mono);font-size:11px;letter-spacing:3px;text-transform:uppercase;color:var(--p);margin-bottom:14px;display:flex;align-items:center;gap:10px}
.eyebrow::before{content:"";width:18px;height:2px;background:var(--p)}
h1.hero{font-family:var(--display);font-size:30px;line-height:1.45;letter-spacing:1px;margin-bottom:18px;color:var(--ink)}
h1.hero .hl{color:var(--p);text-shadow:-2px 0 var(--glitch)}
.lede{font-size:17px;color:var(--ink2);max-width:560px;margin-bottom:30px}
.lede b{color:var(--ink);font-weight:600}
.motto{font-family:var(--mono);font-size:12px;color:var(--ink3);margin-top:18px;letter-spacing:.5px}

/* ---------- canvas / Полотно ---------- */
.canvas-shell{background:var(--surf);padding:22px}
.canvas-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:14px}
.canvas-title{font-weight:600;font-size:16px;display:flex;align-items:center;gap:10px}
.canvas-title .tag{font-family:var(--mono);font-size:10px;color:var(--ink3);border:1px solid var(--border);padding:3px 8px;letter-spacing:1px}
.chips{display:flex;gap:7px;flex-wrap:wrap}
.chip{font-family:var(--mono);font-size:11px;padding:5px 10px;background:var(--surf2);color:var(--ink2);letter-spacing:.5px;transition:.15s}
.chip:hover{color:var(--ink)}
.chip.on{background:var(--p);color:#fff}
.canvas{display:grid;grid-template-columns:repeat(40,1fr);gap:3px;margin-bottom:20px}
.px{aspect-ratio:1;background:var(--surf2);transition:transform .12s}
.px.dim{background:#1a1428}
.px.healed{background:var(--glitch);opacity:.78}
.px.broken{background:var(--p);position:relative;cursor:pointer;z-index:1}
.px.broken.crit{background:var(--sev-critical)}
@media (prefers-reduced-motion:no-preference){
  .px.broken{animation:glitch 2.4s steps(2) infinite}
  .px.broken:nth-child(3n){animation-delay:-.8s}
  .px.broken:nth-child(5n){animation-delay:-1.5s}
}
@keyframes glitch{
  0%,92%,100%{box-shadow:1.5px 0 var(--glitch),-1.5px 0 var(--vuln);transform:none}
  94%{box-shadow:3px 0 var(--glitch),-3px 0 var(--vuln);transform:translateX(1px)}
  96%{box-shadow:-2px 0 var(--glitch),2px 0 var(--vuln);transform:translateX(-1px)}
}
.px.broken:hover{transform:scale(1.6);z-index:5}
.legend{display:flex;gap:20px;flex-wrap:wrap;font-size:12px;color:var(--ink2);font-family:var(--mono)}
.legend span{display:flex;align-items:center;gap:8px}
.legend i{width:11px;height:11px;display:inline-block}
.li-broken{background:var(--p);box-shadow:1.5px 0 var(--glitch),-1.5px 0 var(--vuln)}
.li-crit{background:var(--sev-critical)}
.li-healed{background:var(--glitch)}
.li-dim{background:#1a1428}

.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:28px 0}
.stat{background:var(--surf);padding:20px}
.stat .n{font-family:var(--display);font-size:22px;color:var(--ink);margin-bottom:10px}
.stat .n.p{color:var(--p);text-shadow:-1.5px 0 var(--glitch)}
.stat .n.g{color:var(--glitch)}
.stat .n.v{color:var(--vuln)}
.stat .l{font-family:var(--mono);font-size:11px;color:var(--ink2);letter-spacing:1px;text-transform:uppercase}

/* ---------- bug board ---------- */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:22px;flex-wrap:wrap}
.toolbar .sp{flex:1}
.seg{display:flex;background:var(--surf);padding:3px}
.seg button{font-family:var(--mono);font-size:11px;padding:6px 11px;color:var(--ink2);letter-spacing:.5px}
.seg button.on{background:var(--surf3);color:var(--ink)}
.count{font-family:var(--mono);font-size:12px;color:var(--ink3)}

.cards{display:flex;flex-direction:column;gap:11px}
.card{background:var(--surf);padding:17px 18px;display:flex;align-items:center;gap:16px;cursor:pointer;
  border-left:3px solid var(--border);transition:background .15s,border-color .15s}
.card:hover{background:var(--surf2);border-left-color:var(--p)}
.card .sev{width:5px;height:38px;flex-shrink:0}
.card .main{flex:1;min-width:0}
.card .code{font-family:var(--mono);font-size:11px;color:var(--ink3);letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:9px}
.card .title{font-weight:500;font-size:15px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card .meta{display:flex;align-items:center;gap:9px;flex-shrink:0}
.badge{font-family:var(--mono);font-size:10px;letter-spacing:.5px;padding:4px 8px;text-transform:uppercase;font-weight:500;white-space:nowrap}
.b-sev{color:#0c0a12}
.b-comp{background:var(--surf3);color:var(--ink2)}
.b-status{border:1px solid var(--border);color:var(--ink2)}
.votes{display:flex;align-items:center;gap:6px;font-family:var(--mono);font-size:13px;color:var(--ink2);
  background:var(--surf2);padding:7px 11px;flex-shrink:0;transition:.15s}
.votes:hover{color:var(--glitch)}
.votes.voted{color:var(--glitch)}
.votes .tri{width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:7px solid currentColor}

/* ---------- detail ---------- */
.back{font-family:var(--mono);font-size:12px;color:var(--ink2);margin-bottom:20px;display:inline-flex;align-items:center;gap:7px;letter-spacing:.5px}
.back:hover{color:var(--ink)}
.detail-grid{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start}
.panel{background:var(--surf);padding:22px}
.d-code{font-family:var(--mono);font-size:12px;color:var(--p);letter-spacing:1px;margin-bottom:8px}
.d-title{font-size:22px;font-weight:600;line-height:1.35;margin-bottom:16px}
.d-body{color:var(--ink2);font-size:15px;margin-bottom:6px}
.d-body code{font-family:var(--mono);font-size:13px;background:var(--surf2);padding:2px 6px;color:var(--p-soft)}

/* state machine */
.machine{display:flex;align-items:center;gap:0;margin:24px 0;flex-wrap:wrap}
.stage{font-family:var(--mono);font-size:10px;letter-spacing:.5px;padding:7px 11px;background:var(--surf2);color:var(--ink3);white-space:nowrap}
.stage.done{background:var(--surf3);color:var(--ink2)}
.stage.cur{background:var(--p);color:#fff}
.stage.cur.pub{background:var(--glitch);color:#0c0a12}
.arrow{color:var(--ink3);font-size:11px;padding:0 5px}
.advance{margin-top:6px;background:var(--surf3);color:var(--ink);font-family:var(--mono);font-size:12px;
  padding:10px 16px;letter-spacing:.5px;display:inline-flex;align-items:center;gap:8px}
.advance:hover{background:var(--p);color:#fff}
.advance:disabled{opacity:.4;cursor:default;background:var(--surf2)}

/* captured env */
.env-head{font-family:var(--mono);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--glitch);
  margin-bottom:14px;display:flex;align-items:center;gap:9px}
.env-head .auto{margin-left:auto;font-size:9px;color:var(--ink3);background:var(--surf2);padding:3px 7px;letter-spacing:1px}
.env-row{display:flex;justify-content:space-between;gap:14px;font-family:var(--mono);font-size:12px;padding:8px 0;border-bottom:1px solid var(--border)}
.env-row .k{color:var(--ink3)}
.env-row .v{color:var(--ink2);text-align:right;word-break:break-all}
.console{background:#0a0810;padding:13px;margin-top:14px;font-family:var(--mono);font-size:11px;line-height:1.7;color:var(--ink3)}
.console .err{color:var(--vuln)}
.console .warn{color:var(--sev-medium)}

.side-block{margin-bottom:18px}
.side-block .lbl{font-family:var(--mono);font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);margin-bottom:9px}
.assignee{display:flex;align-items:center;gap:10px}
.ava{width:30px;height:30px;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:var(--display);font-size:9px;color:var(--p)}
.repro{display:flex;align-items:center;gap:10px;background:var(--surf2);padding:11px 13px;font-family:var(--mono);font-size:12px;color:var(--ink2)}

/* vulns */
.vuln-banner{background:linear-gradient(0deg,var(--surf),var(--surf));border-left:3px solid var(--vuln);
  padding:16px 18px;margin-bottom:24px;display:flex;align-items:center;gap:14px}
.vuln-banner .lock{font-family:var(--mono);color:var(--vuln);font-size:20px}
.vuln-banner .txt{font-size:14px;color:var(--ink2)}
.vuln-banner .txt b{color:var(--ink);font-weight:600}
.vcard{background:var(--surf);padding:18px;display:flex;align-items:center;gap:16px;border-left:3px solid var(--vuln);margin-bottom:11px}
.cvss{flex-shrink:0;width:54px;height:54px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--surf2)}
.cvss .sc{font-family:var(--display);font-size:14px;color:var(--vuln)}
.cvss .lb{font-family:var(--mono);font-size:8px;color:var(--ink3);letter-spacing:1px;margin-top:3px}
.vmain{flex:1;min-width:0}
.vmeta{display:flex;align-items:center;gap:9px;margin-top:7px;flex-wrap:wrap}
.embargo{font-family:var(--mono);font-size:11px;color:var(--sev-medium);display:flex;align-items:center;gap:6px}

/* leaderboard */
.lb-row{background:var(--surf);padding:15px 18px;display:flex;align-items:center;gap:16px;margin-bottom:9px}
.lb-rank{font-family:var(--display);font-size:14px;color:var(--ink3);width:34px;text-align:center}
.lb-rank.top{color:var(--p);text-shadow:-1.5px 0 var(--glitch)}
.lb-ava{width:38px;height:38px;background:var(--surf3);display:flex;align-items:center;justify-content:center;font-family:var(--display);font-size:11px;color:var(--p)}
.lb-main{flex:1;min-width:0}
.lb-nick{font-family:var(--mono);font-weight:500;font-size:15px;color:var(--ink)}
.lb-tier{font-size:12px;color:var(--ink2);margin-top:2px}
.lb-badges{display:flex;gap:5px;margin-top:5px}
.lb-badge{font-family:var(--mono);font-size:9px;letter-spacing:.5px;padding:3px 7px;background:var(--surf2);color:var(--glitch);text-transform:uppercase}
.lb-pts{text-align:right;flex-shrink:0}
.lb-pts .v{font-family:var(--display);font-size:15px;color:var(--ink)}
.lb-pts .l{font-family:var(--mono);font-size:10px;color:var(--ink3);letter-spacing:1px;margin-top:4px}

/* ---------- modal ---------- */
.overlay{display:none;position:fixed;inset:0;z-index:60;background:rgba(8,6,14,.78);backdrop-filter:blur(3px);
  align-items:flex-start;justify-content:center;padding:60px 20px;overflow-y:auto}
.overlay.on{display:flex}
.modal{background:var(--surf);width:100%;max-width:560px;padding:26px}
.modal-head{display:flex;align-items:center;gap:12px;margin-bottom:6px}
.modal-head .glyph{width:22px;height:22px;background:var(--p);box-shadow:3px 0 var(--glitch),-3px 0 var(--vuln);flex-shrink:0}
.modal-head h3{font-family:var(--display);font-size:13px;color:var(--ink);letter-spacing:.5px}
.modal-sub{font-size:13px;color:var(--ink2);margin-bottom:20px}
.field{margin-bottom:15px}
.field label{font-family:var(--mono);font-size:11px;letter-spacing:1px;text-transform:uppercase;color:var(--ink3);display:block;margin-bottom:7px}
.field input,.field textarea,.field select{width:100%;background:var(--surf2);border:1px solid var(--border);color:var(--ink);
  font-family:var(--body);font-size:14px;padding:10px 12px}
.field textarea{resize:vertical;min-height:64px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.modal-actions{display:flex;gap:10px;margin-top:22px}
.btn-primary{flex:1;background:var(--p);color:#fff;font-weight:700;font-size:14px;padding:13px}
.btn-primary:hover{background:var(--p-soft)}
.btn-ghost{background:var(--surf2);color:var(--ink2);font-size:14px;padding:13px 20px}
.btn-ghost:hover{color:var(--ink)}

/* toast */
.toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);z-index:80;
  background:var(--surf3);color:var(--ink);font-size:14px;padding:13px 20px;display:flex;align-items:center;gap:11px;
  opacity:0;transition:.3s;pointer-events:none}
.toast .dot{width:10px;height:10px;background:var(--glitch);box-shadow:2px 0 var(--p),-2px 0 var(--glitch)}
.toast.on{opacity:1;transform:translateX(-50%) translateY(0)}

@media(max-width:820px){
  .nav{display:none}
  .detail-grid{grid-template-columns:1fr}
  .stat-row{grid-template-columns:repeat(2,1fr)}
  h1.hero{font-size:20px}
  .canvas{grid-template-columns:repeat(28,1fr)}
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
    <button data-view="canvas" class="on"><span class="ic">▦</span>Полотно</button>
    <button data-view="bugs"><span class="ic">◧</span>Баги</button>
    <button data-view="vulns"><span class="ic">▲</span>Уязвимости</button>
    <button data-view="board"><span class="ic">★</span>Лидерборд</button>
  </nav>
  <button class="catch pixel" onclick="openCatch()"><span class="dot"></span>Поймать пиксель</button>
</header>

<main class="wrap">

  <!-- ============ ПОЛОТНО ============ -->
  <section class="view on" id="v-canvas">
    <div class="eyebrow">здоровье платформы в реальном времени</div>
    <h1 class="hero">Каждый баг — <span class="hl">битый пиксель</span>.<br>Почини картинку.</h1>
    <p class="lede">Полотно — это живая карта Dustore. Открытый репорт глитчит на своём месте, исправленный — <b>срастается в чистый пиксель</b>. Видно всё и сразу: где больно, что чинится, кто чинит.</p>

    <div class="stat-row">
      <div class="stat pixel pix-border"><div class="n p" id="st-open">—</div><div class="l">открытых багов</div></div>
      <div class="stat pixel pix-border"><div class="n v">3</div><div class="l">уязвимости в эмбарго</div></div>
      <div class="stat pixel pix-border"><div class="n g" id="st-healed">—</div><div class="l">залечено за месяц</div></div>
      <div class="stat pixel pix-border"><div class="n">4.2ч</div><div class="l">медиана до триажа</div></div>
    </div>

    <div class="canvas-shell pixel pix-border">
      <div class="canvas-head">
        <div class="canvas-title">Полотно <span class="tag">40 × 14</span></div>
        <div class="chips" id="comp-chips"></div>
      </div>
      <div class="canvas" id="canvas"></div>
      <div class="legend">
        <span><i class="li-broken"></i> открытый баг</span>
        <span><i class="li-crit"></i> критический</span>
        <span><i class="li-healed"></i> залечен</span>
        <span><i class="li-dim"></i> чисто</span>
      </div>
    </div>
    <p class="motto">// мы не делаем лучше, мы делаем иначе</p>
  </section>

  <!-- ============ БАГИ ============ -->
  <section class="view" id="v-bugs">
    <div class="eyebrow">очередь триажа</div>
    <h1 class="hero" style="font-size:22px">Баги</h1>
    <div class="toolbar">
      <div class="seg" id="sev-seg">
        <button data-sev="all" class="on">все</button>
        <button data-sev="critical">crit</button>
        <button data-sev="high">high</button>
        <button data-sev="medium">med</button>
        <button data-sev="low">low</button>
      </div>
      <div class="sp"></div>
      <div class="count" id="bug-count"></div>
    </div>
    <div class="cards" id="bug-cards"></div>
  </section>

  <!-- ============ ДЕТАЛЬ ============ -->
  <section class="view" id="v-detail">
    <a class="back" onclick="go('bugs')">◄ назад к багам</a>
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
    <h1 class="hero" style="font-size:22px">Уязвимости</h1>
    <div class="vuln-banner pixel pix-border">
      <span class="lock">▣</span>
      <span class="txt"><b>Приватный режим.</b> Эти репорты видит только security-стафф. Публичное advisory выходит автоматически после патча и истечения эмбарго — не раньше.</span>
    </div>
    <div id="vuln-cards"></div>
  </section>

  <!-- ============ ЛИДЕРБОРД ============ -->
  <section class="view" id="v-board">
    <div class="eyebrow">охотники за пикселями</div>
    <h1 class="hero" style="font-size:22px">Лидерборд</h1>
    <p class="lede" style="font-size:15px">Репутация капает за валидные находки: чем выше severity и чем точнее репро — тем больше очков. Награды не деньгами, а ранним доступом, store-credit и фичерингом.</p>
    <div id="lb-rows"></div>
  </section>

</main>

<!-- modal -->
<div class="overlay" id="overlay">
  <div class="modal pixel pix-glow">
    <div class="modal-head"><span class="glyph"></span><h3>Поймать пиксель</h3></div>
    <p class="modal-sub">Контекст уже захвачен автоматически. Допиши, что сломалось — и всё.</p>

    <div class="field">
      <label>что пошло не так</label>
      <textarea id="catch-body" placeholder="Например: при клике на «Опубликовать» страница перезагружается, черновик пропадает"></textarea>
    </div>
    <div class="field-row">
      <div class="field">
        <label>компонент</label>
        <select id="catch-comp"></select>
      </div>
      <div class="field">
        <label>severity</label>
        <select id="catch-sev">
          <option value="low">low</option>
          <option value="medium" selected>medium</option>
          <option value="high">high</option>
          <option value="critical">critical</option>
        </select>
      </div>
    </div>

    <div class="env-head">захвачено автоматически <span class="auto">read-only</span></div>
    <div id="catch-env"></div>
    <div class="console" id="catch-console"></div>

    <div class="modal-actions">
      <button class="btn-ghost pixel" onclick="closeCatch()">Отмена</button>
      <button class="btn-primary pixel" onclick="submitCatch()">Отправить репорт</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"><span class="dot"></span><span id="toast-txt"></span></div>

<script>
/* ---------------- data ---------------- */
const COMPONENTS = ['Публикация','Консоль','Джемы','Мобилка','S3','Уведомления'];
const SEV = {critical:'var(--sev-critical)',high:'var(--sev-high)',medium:'var(--sev-medium)',low:'var(--sev-low)',info:'var(--sev-info)'};
const STATUS_RU = {new:'Новый',triaged:'Триаж',confirmed:'Подтверждён',in_progress:'В работе',resolved:'Зафикшен',verified:'Проверен',published:'Опубликован'};
const FLOW = ['new','triaged','confirmed','in_progress','resolved','verified','published'];

let bugs = [
  {id:'BP-2026-0042',title:'Мобильная PWA сбрасывает сессию после логина',comp:'Мобилка',sev:'high',status:'in_progress',votes:14,voted:false,
   body:'После успешного логина на мобильном клиенте <code>$_SESSION[USERDATA]</code> не пишется — следующий запрос уходит как аноним. Десктоп не воспроизводит. Подозрение на расхождение <code>HTTP_HOST</code> в Database-классе.',
   assignee:'segfault_sam', env:autoEnv('m.dustore.ru/jams','iOS 17 · Safari','414×896')},
  {id:'BP-2026-0039',title:'Поиск участников L4T пуст для telegram_username',comp:'Джемы',sev:'medium',status:'verified',votes:9,voted:false,
   body:'Автокомплит участников искал только по <code>username</code>, а у телеграм-регов заполнен только <code>telegram_username</code> — выдача пустая. Фикс расширяет запрос на оба поля.',
   assignee:'glitch_hunter', env:autoEnv('dustore.ru/jams','Win11 · Chrome 126','1920×1080')},
  {id:'BP-2026-0037',title:'Чанковая выгрузка APK обрывается на файлах ≥500 МБ',comp:'S3',sev:'high',status:'confirmed',votes:6,voted:false,
   body:'Сборка манифеста падает на последнем чанке — <code>chunk_NNNN.bin</code> загружен, но <code>manifest.json</code> не собирается. Прямая сборка ниже порога работает.',
   assignee:'null_pointer', env:autoEnv('console.dustore.ru/upload','Win11 · Chrome 126','2560×1440')},
  {id:'BP-2026-0033',title:'Telegram-уведомления теряются при оффлайн notify_worker',comp:'Уведомления',sev:'medium',status:'triaged',votes:4,voted:false,
   body:'Когда <code>notify_worker.php</code> недоступен, fire-and-forget молча проглатывает сообщение. Нужна очередь с ретраями вместо потери.',
   assignee:'race_condition', env:autoEnv('cron','—','—')},
  {id:'BP-2026-0028',title:'Карточка игры ломает грид при длинном названии города',comp:'Публикация',sev:'low',status:'new',votes:2,voted:false,
   body:'Поле города — свободный текст без валидации. Длинная строка распирает карточку и ломает сетку каталога. Нужна нормализация или автокомплит.',
   assignee:null, env:autoEnv('dustore.ru/games','Android · Chrome','360×800')},
  {id:'BP-2026-0021',title:'Вложенные <form> в edit.php удаляют черновик при сохранении',comp:'Консоль',sev:'high',status:'published',votes:11,voted:false,
   body:'Вложенные <code>&lt;form&gt;</code> отдавали PHP несколько значений <code>action</code> — побеждал последний (delete). Сохранение черновика триггерило удаление. Формы расцеплены.',
   assignee:'glitch_hunter', env:autoEnv('console.dustore.ru/edit','Win11 · Firefox','1920×1080')},
];

let vulns = [
  {id:'BP-2026-0011',title:'SSRF через webhook в интеграции reg.ru',cvss:'8.2',sev:'high',status:'triaged',embargo:'15.09.2026',reporter:'null_pointer'},
  {id:'BP-2026-0009',title:'Stored XSS через Quill-описание игры',cvss:'6.4',sev:'medium',status:'in_progress',embargo:'30.08.2026',reporter:'race_condition'},
  {id:'BP-2026-0007',title:'IDOR: чужие тимовые инвайты через invite_member.php',cvss:'7.1',sev:'high',status:'resolved',embargo:'20.07.2026',reporter:'glitch_hunter'},
];

let hunters = [
  {nick:'glitch_hunter',tier:'Охотник за пикселями',pts:2840,found:47,badges:['first-find','crit-slayer']},
  {nick:'race_condition',tier:'Охотник за пикселями',pts:2310,found:38,badges:['xss-master']},
  {nick:'segfault_sam',tier:'Дебаггер',pts:1920,found:31,badges:[]},
  {nick:'null_pointer',tier:'Дебаггер',pts:1655,found:28,badges:['ssrf-find']},
  {nick:'kernel_panic',tier:'Новобранец',pts:540,found:9,badges:[]},
];

function autoEnv(url,client,vp){
  return {url, client, vp};
}

/* ---------------- canvas ---------------- */
let activeComp = null;
let pxMap = [];   // index -> bugId | null
const TOTAL_PX = 40*14;

function buildCanvas(){
  const el = document.getElementById('canvas');
  el.innerHTML='';
  pxMap = new Array(TOTAL_PX).fill(null);
  // map open bugs to scattered pixels
  const openBugs = bugs.filter(b=>b.status!=='published');
  const healed = bugs.filter(b=>b.status==='published');
  let used = new Set();
  function slot(seed){let i=(seed*later())%TOTAL_PX;while(used.has(i)){i=(i+37)%TOTAL_PX;}used.add(i);return i;}
  let counter=7;
  function later(){counter=(counter*131+17)%9973;return counter;}
  const brokenSlots={}, healedSlots=new Set(), critSlots=new Set();
  openBugs.forEach(b=>{const i=slot(b.id.charCodeAt(8)+1);brokenSlots[i]=b.id;pxMap[i]=b.id;if(b.sev==='critical'||b.sev==='high'&&b.votes>10)critSlots.add(i);});
  // ambient broken noise (unlinked) for density
  for(let k=0;k<14;k++){const i=slot(900+k);if(!brokenSlots[i]&&!pxMap[i]){brokenSlots[i]='_noise';}}
  healed.forEach(b=>{const i=slot(b.id.charCodeAt(9)+5);healedSlots.add(i);});
  for(let k=0;k<22;k++){const i=slot(2000+k);if(!brokenSlots[i]&&!healedSlots.has(i))healedSlots.add(i);}

  for(let i=0;i<TOTAL_PX;i++){
    const d=document.createElement('div');
    d.className='px';
    if(brokenSlots[i]!==undefined){
      d.className='px broken'+(critSlots.has(i)?' crit':'');
      if(pxMap[i]){d.title=bugs.find(b=>b.id===pxMap[i]).title; d.onclick=()=>openDetail(pxMap[i]);}
      else d.title='открытый репорт';
    } else if(healedSlots.has(i)){
      d.className='px healed';
    } else {
      d.className='px '+( (i*7%5===0)?'dim':'');
    }
    el.appendChild(d);
  }
  document.getElementById('st-open').textContent=openBugs.length;
  document.getElementById('st-healed').textContent=healed.length+11;
}

function buildChips(){
  const el=document.getElementById('comp-chips');
  el.innerHTML='';
  COMPONENTS.forEach(c=>{
    const b=document.createElement('button');
    b.className='chip'+(activeComp===c?' on':'');
    b.textContent=c;
    b.onclick=()=>{activeComp=activeComp===c?null:c; buildChips(); go('bugs'); setSevFilter('all'); filterByComp(c);};
    el.appendChild(b);
  });
}
function filterByComp(c){ renderBugs(b=> (activeComp? b.comp===activeComp:true)); }

/* ---------------- bug board ---------------- */
let sevFilter='all';
function setSevFilter(s){sevFilter=s;document.querySelectorAll('#sev-seg button').forEach(b=>b.classList.toggle('on',b.dataset.sev===s));}

function renderBugs(extra){
  const el=document.getElementById('bug-cards');
  el.innerHTML='';
  let list=bugs.filter(b=>sevFilter==='all'||b.sev===sevFilter);
  if(activeComp) list=list.filter(b=>b.comp===activeComp);
  list.sort((a,b)=>FLOW.indexOf(a.status)-FLOW.indexOf(b.status));
  list.forEach(b=>{
    const c=document.createElement('div');
    c.className='card pixel';
    c.onclick=()=>openDetail(b.id);
    c.innerHTML=`
      <div class="sev" style="background:${SEV[b.sev]}"></div>
      <div class="main">
        <div class="code">${b.id}<span class="badge b-comp">${b.comp}</span></div>
        <div class="title">${b.title.replace('<','&lt;')}</div>
      </div>
      <div class="meta">
        <span class="badge b-sev" style="background:${SEV[b.sev]}">${b.sev}</span>
        <span class="badge b-status">${STATUS_RU[b.status]}</span>
      </div>
      <button class="votes pixel ${b.voted?'voted':''}" onclick="event.stopPropagation();vote('${b.id}')">
        <span class="tri"></span>${b.votes}
      </button>`;
    el.appendChild(c);
  });
  document.getElementById('bug-count').textContent=list.length+' / '+bugs.length+' репортов';
}

function vote(id){
  const b=bugs.find(x=>x.id===id);
  if(b.voted){b.votes--;b.voted=false;} else {b.votes++;b.voted=true; toast('«Я тоже поймал» — приоритет '+id+' поднят');}
  renderBugs();
}

/* ---------------- detail ---------------- */
let curBug=null;
function openDetail(id){
  curBug=bugs.find(b=>b.id===id);
  go('detail');
  renderDetail();
}
function renderDetail(){
  const b=curBug;
  const idx=FLOW.indexOf(b.status);
  let machine='';
  FLOW.forEach((s,i)=>{
    const cls=i<idx?'done':i===idx?('cur'+(s==='published'?' pub':'')):'';
    machine+=`<span class="stage ${cls}">${STATUS_RU[s]}</span>`;
    if(i<FLOW.length-1)machine+='<span class="arrow">►</span>';
  });
  const isPub=b.status==='published';
  document.getElementById('detail-main').innerHTML=`
    <div class="d-code">${b.id} · ${b.comp}</div>
    <div class="d-title">${b.title.replace('<','&lt;')}</div>
    <p class="d-body">${b.body}</p>
    <div class="env-head" style="margin-top:22px;color:var(--ink3)">жизненный цикл</div>
    <div class="machine">${machine}</div>
    <button class="advance pixel" id="adv-btn" ${isPub?'disabled':''} onclick="advance()">
      ${isPub?'◇ цикл завершён':'продвинуть статус ►'}
    </button>`;
  document.getElementById('detail-env').innerHTML=`
    <div class="env-head">окружение <span class="auto">авто</span></div>
    <div class="env-row"><span class="k">url</span><span class="v">${b.env.url}</span></div>
    <div class="env-row"><span class="k">клиент</span><span class="v">${b.env.client}</span></div>
    <div class="env-row"><span class="k">viewport</span><span class="v">${b.env.vp}</span></div>
    <div class="env-row"><span class="k">сборка</span><span class="v">dustore v0.9.4</span></div>
    <div class="side-block" style="margin-top:18px">
      <div class="lbl">исполнитель</div>
      ${b.assignee?`<div class="assignee"><span class="ava">${b.assignee.slice(0,2).toUpperCase()}</span><span style="font-family:var(--mono);font-size:13px;color:var(--ink2)">${b.assignee}</span></div>`:'<span style="font-family:var(--mono);font-size:13px;color:var(--ink3)">не назначен</span>'}
    </div>
    <div class="side-block">
      <div class="lbl">воспроизводимость</div>
      <div class="repro">▣ подтвердили ${b.votes} раз</div>
    </div>`;
}
function advance(){
  const idx=FLOW.indexOf(curBug.status);
  if(idx>=FLOW.length-1)return;
  curBug.status=FLOW[idx+1];
  if(curBug.status==='published'){
    toast('Пиксель залечен — '+curBug.id+' опубликован, +120 репутации');
    buildCanvas();
  } else {
    toast('Статус → '+STATUS_RU[curBug.status]);
  }
  renderDetail();
}

/* ---------------- vulns ---------------- */
function renderVulns(){
  const el=document.getElementById('vuln-cards');
  el.innerHTML='';
  vulns.forEach(v=>{
    const d=document.createElement('div');
    d.className='vcard pixel';
    d.innerHTML=`
      <div class="cvss pixel"><span class="sc">${v.cvss}</span><span class="lb">CVSS</span></div>
      <div class="vmain">
        <div class="d-code" style="color:var(--vuln)">${v.id}</div>
        <div class="title" style="font-weight:500;color:var(--ink);font-size:15px">${v.title.replace('<','&lt;')}</div>
        <div class="vmeta">
          <span class="badge b-status">${STATUS_RU[v.status]}</span>
          <span class="embargo">▣ эмбарго до ${v.embargo}</span>
          <span style="font-family:var(--mono);font-size:11px;color:var(--ink3)">репортер: ${v.reporter}</span>
        </div>
      </div>`;
    el.appendChild(d);
  });
}

/* ---------------- leaderboard ---------------- */
function renderBoard(){
  const el=document.getElementById('lb-rows');
  el.innerHTML='';
  hunters.sort((a,b)=>b.pts-a.pts).forEach((h,i)=>{
    const r=document.createElement('div');
    r.className='lb-row pixel pix-border';
    r.innerHTML=`
      <div class="lb-rank ${i<3?'top':''}">${i+1}</div>
      <div class="lb-ava">${h.nick.slice(0,2).toUpperCase()}</div>
      <div class="lb-main">
        <div class="lb-nick">${h.nick}</div>
        <div class="lb-tier">${h.tier} · ${h.found} находок</div>
        ${h.badges.length?`<div class="lb-badges">${h.badges.map(b=>`<span class="lb-badge">${b}</span>`).join('')}</div>`:''}
      </div>
      <div class="lb-pts"><div class="v">${h.pts}</div><div class="l">очков</div></div>`;
    el.appendChild(r);
  });
}

/* ---------------- catch modal ---------------- */
function openCatch(){
  const sel=document.getElementById('catch-comp');
  sel.innerHTML=COMPONENTS.map(c=>`<option>${c}</option>`).join('');
  // real auto-capture
  const ua=navigator.userAgent;
  let browser='Браузер'; if(/Chrome/.test(ua)&&!/Edg/.test(ua))browser='Chrome';else if(/Firefox/.test(ua))browser='Firefox';else if(/Safari/.test(ua))browser='Safari';else if(/Edg/.test(ua))browser='Edge';
  let os='OS'; if(/Windows/.test(ua))os='Windows';else if(/Mac/.test(ua))os='macOS';else if(/Android/.test(ua))os='Android';else if(/iPhone|iPad/.test(ua))os='iOS';else if(/Linux/.test(ua))os='Linux';
  document.getElementById('catch-env').innerHTML=`
    <div class="env-row"><span class="k">url</span><span class="v">${location.href.slice(0,46)}</span></div>
    <div class="env-row"><span class="k">клиент</span><span class="v">${os} · ${browser}</span></div>
    <div class="env-row"><span class="k">viewport</span><span class="v">${window.innerWidth}×${window.innerHeight}</span></div>
    <div class="env-row"><span class="k">сборка</span><span class="v">dustore v0.9.4 (a3f1c2)</span></div>
    <div class="env-row"><span class="k">сессия</span><span class="v">usr_8842 · staff:false</span></div>`;
  document.getElementById('catch-console').innerHTML=`<span style="color:var(--ink2)">// перехвачено из console:</span><br><span class="warn">⚠ [chunk] manifest assembly retry 2/3</span><br><span class="err">✕ POST /upload/finalize → 504 (gateway timeout)</span>`;
  document.getElementById('overlay').classList.add('on');
}
function closeCatch(){document.getElementById('overlay').classList.remove('on');}
function submitCatch(){
  const body=document.getElementById('catch-body').value.trim()||'Без описания — см. захваченный контекст';
  const comp=document.getElementById('catch-comp').value;
  const sev=document.getElementById('catch-sev').value;
  const n=String(43+ (bugs.length-6)).padStart(4,'0');
  const id='BP-2026-0'+n;
  const ua=navigator.userAgent;
  let browser=/Firefox/.test(ua)?'Firefox':/Edg/.test(ua)?'Edge':/Chrome/.test(ua)?'Chrome':'Safari';
  let os=/Windows/.test(ua)?'Windows':/Mac/.test(ua)?'macOS':/Android/.test(ua)?'Android':'iOS';
  bugs.unshift({id,title:body.slice(0,64),comp,sev,status:'new',votes:1,voted:true,
    body:body+' <br><br><span style="color:var(--ink3);font-family:var(--mono);font-size:12px">// контекст захвачен автоматически виджетом «Поймать пиксель»</span>',
    assignee:null, env:autoEnv(location.href.slice(0,40),os+' · '+browser,window.innerWidth+'×'+window.innerHeight)});
  document.getElementById('catch-body').value='';
  closeCatch();
  go('bugs'); setSevFilter('all'); activeComp=null; buildChips(); renderBugs();
  buildCanvas();
  toast('Пиксель пойман — '+id+' создан');
}

/* ---------------- nav / toast ---------------- */
function go(view){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('on'));
  document.getElementById('v-'+view).classList.add('on');
  document.querySelectorAll('#nav button').forEach(b=>b.classList.toggle('on',b.dataset.view===view||(view==='detail'&&b.dataset.view==='bugs')));
  window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('#nav button').forEach(b=>b.onclick=()=>{if(b.dataset.view==='bugs'){activeComp=null;buildChips();renderBugs();}go(b.dataset.view);});
document.querySelectorAll('#sev-seg button').forEach(b=>b.onclick=()=>{setSevFilter(b.dataset.sev);renderBugs();});
document.getElementById('overlay').onclick=e=>{if(e.target.id==='overlay')closeCatch();};

let toastT;
function toast(msg){
  clearTimeout(toastT);
  document.getElementById('toast-txt').textContent=msg;
  document.getElementById('toast').classList.add('on');
  toastT=setTimeout(()=>document.getElementById('toast').classList.remove('on'),2600);
}

/* ---------------- init ---------------- */
buildCanvas(); buildChips(); renderBugs(); renderVulns(); renderBoard();
</script>
</body>
</html>