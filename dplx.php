<?php
// Лендинг deplex — /deplex. Публичная страница, использует общий header/footer сайта.
session_start();
$page_title = 'deplex — заливка билдов в Dustore';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — deplex</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{
            --primary:#c32178;--primary-d:#74155d;--dark:#0d0118;
            --surface:rgba(255,255,255,.05);--border:rgba(255,255,255,.09);
            --text:#f0e6ff;--muted:rgba(255,255,255,.45);--success:#00ff99;
            --radius:14px;
        }
        body{background:var(--dark);color:var(--text);font-family:'Inter',sans-serif;line-height:1.6;
            -webkit-font-smoothing:antialiased;overflow-x:hidden;}
        a{color:inherit;text-decoration:none;}
        .wrap{max-width:1080px;margin:0 auto;padding:0 24px;}
        .mono{font-family:'JetBrains Mono',monospace;}

        /* ── HERO ── */
        .hero{position:relative;padding:80px 0 64px;text-align:center;overflow:hidden;}
        .hero::before{content:'';position:absolute;top:-40%;left:50%;transform:translateX(-50%);
            width:900px;height:900px;border-radius:50%;
            background:radial-gradient(circle,rgba(195,33,120,.22),transparent 60%);z-index:0;}
        .hero>*{position:relative;z-index:1;}
        .hero-banner{font-family:'JetBrains Mono',monospace;color:var(--primary);
            font-size:clamp(8px,2.1vw,18px);line-height:1.25;white-space:pre;display:inline-block;
            text-align:left;margin-bottom:28px;text-shadow:0 0 30px rgba(195,33,120,.5);}
        .hero h1{font-family:'Syne',sans-serif;font-size:clamp(1.8rem,5vw,3rem);font-weight:800;
            letter-spacing:-.02em;margin-bottom:14px;color:#fff;}
        .hero p{font-size:clamp(1rem,2.5vw,1.2rem);color:var(--muted);max-width:620px;margin:0 auto 32px;}
        .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:var(--radius);
            font-weight:700;font-size:.95rem;cursor:pointer;border:none;transition:transform .15s,box-shadow .15s,background .2s;}
        .btn:active{transform:scale(.97);}
        .btn-primary{background:linear-gradient(135deg,#c32178,#74155d);color:#fff;box-shadow:0 4px 24px rgba(195,33,120,.35);}
        .btn-primary:hover{box-shadow:0 6px 32px rgba(195,33,120,.55);}
        .btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text);}
        .btn-ghost:hover{background:var(--surface);}

        /* ── SECTION ── */
        section{padding:56px 0;}
        .sec-title{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;color:#fff;
            text-align:center;margin-bottom:8px;}
        .sec-sub{text-align:center;color:var(--muted);margin-bottom:40px;}

        /* ── FEATURES ── */
        .features{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;}
        .feature{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:24px;}
        .feature .material-icons{font-size:28px;color:var(--primary);margin-bottom:12px;}
        .feature h3{font-size:1.05rem;color:#fff;margin-bottom:6px;}
        .feature p{font-size:.9rem;color:var(--muted);}

        /* ── QUICKSTART ── */
        .terminal{background:#0a0012;border:1px solid var(--border);border-radius:16px;overflow:hidden;
            max-width:720px;margin:0 auto;box-shadow:0 20px 60px rgba(0,0,0,.5);}
        .terminal-bar{display:flex;align-items:center;gap:7px;padding:12px 16px;background:rgba(255,255,255,.03);
            border-bottom:1px solid var(--border);}
        .dot{width:11px;height:11px;border-radius:50%;}
        .dot.r{background:#ff5f57;}.dot.y{background:#febc2e;}.dot.g{background:#28c840;}
        .terminal-title{margin-left:8px;font-size:.78rem;color:var(--muted);font-family:'JetBrains Mono',monospace;}
        .terminal-body{padding:20px 22px;font-family:'JetBrains Mono',monospace;font-size:.86rem;line-height:1.9;}
        .terminal-body .c{color:var(--primary);}
        .terminal-body .p{color:var(--success);}
        .terminal-body .m{color:var(--muted);}

        /* ── DOWNLOAD ── */
        .downloads{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;max-width:720px;margin:0 auto;}
        .dl{display:flex;align-items:center;gap:14px;background:var(--surface);border:1px solid var(--border);
            border-radius:16px;padding:18px 20px;transition:border-color .2s,transform .15s;}
        .dl:hover{border-color:var(--primary);transform:translateY(-2px);}

        .inactive{display:flex;align-items:center;gap:14px;background:var(--surface);border:1px solid var(--border);
            border-radius:16px;padding:18px 20px;transition:border-color .2s,transform .15s;}
        .inactive:hover{border-color:gray;}

        .dl .material-icons{font-size:30px;color:var(--primary);}
        .dl-os{font-weight:700;color:#fff;}
        .dl-hint{font-size:.78rem;color:var(--muted);}

        /* ── PLAYERS ── */
        .players{background:linear-gradient(135deg,rgba(195,33,120,.08),rgba(116,21,93,.05));
            border:1px solid var(--border);border-radius:22px;padding:40px;text-align:center;}
        .players h2{font-family:'Syne',sans-serif;font-size:1.5rem;color:#fff;margin-bottom:10px;}
        .players p{color:var(--muted);max-width:560px;margin:0 auto;}

        .foot{text-align:center;padding:48px 0;color:var(--muted);font-size:.85rem;border-top:1px solid var(--border);}
    </style>
</head>
<body>
<?php 
    require_once($_SERVER['DOCUMENT_ROOT'] . '/swad/static/elements/header.php');  ?>

<!-- ══ HERO ══ -->
<div class="hero">
    <div class="wrap">
<pre class="hero-banner"> __   __   __        ___
|  \ |__  |__) |    |__  \_/
|__/ |___ |    |___ |___ / \  sdk.</pre>
        <h1>Заливай билды проще и быстрее</h1>
        <p>deplex — командная утилита для загрузки игр в Dustore. Заливает только изменения,
           хранит данные один раз, шифрует и даёт игрокам установку одним файлом.</p>
        <div class="cta">
            <a class="btn btn-primary" href="#download"><span class="material-icons">download</span>Скачать</a>
            <a class="btn btn-ghost" href="#quickstart"><span class="material-icons">terminal</span>Быстрый старт</a>
        </div>
    </div>
</div>

<!-- ══ FEATURES ══ -->
<section>
    <div class="wrap">
        <h2 class="sec-title">Почему deplex</h2>
        <p class="sec-sub">Не «залей zip заново», а умная синхронизация билда</p>
        <div class="features">
            <div class="feature">
                <span class="material-icons">difference</span>
                <h3>Дельта-загрузка</h3>
                <p>Поменял один файл в 10-гигабайтном билде — зальётся только он. Как git, но для игр.</p>
            </div>
            <div class="feature">
                <span class="material-icons">content_copy</span>
                <h3>Дедупликация</h3>
                <p>Одинаковые куски хранятся один раз. Повторяющиеся ассеты не занимают место дважды.</p>
            </div>
            <div class="feature">
                <span class="material-icons">lock</span>
                <h3>Шифрование</h3>
                <p>Каждый чанк шифруется на твоей машине до отправки в хранилище. Целостность — по хешу.</p>
            </div>
            <div class="feature">
                <span class="material-icons">install_desktop</span>
                <h3>Установка одним файлом</h3>
                <p>Игроку не нужен deplex — он качает один <span class="mono">Install-Игра.exe</span> и жмёт «Далее».</p>
            </div>
            <div class="feature">
                <span class="material-icons">devices</span>
                <h3>Кроссплатформа</h3>
                <p>Один бинарник под Windows, macOS и Linux. Никаких рантаймов и зависимостей.</p>
            </div>
            <div class="feature">
                <span class="material-icons">hub</span>
                <h3>P2P-ready <span style="font-size:.7rem;color:var(--primary);">скоро</span></h3>
                <p>Куски адресуются по содержимому — раздача между игроками работает даже без серверов.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══ QUICKSTART ══ -->
<section id="quickstart">
    <div class="wrap">
        <h2 class="sec-title">Пять команд до релиза</h2>
        <p class="sec-sub">Токен берётся в консоли разработчика → «Deploy-токены»</p>
        <div class="terminal">
            <div class="terminal-bar">
                <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
                <span class="terminal-title">deplex</span>
            </div>
            <div class="terminal-body">
<span class="m"># авторизация (один раз)</span><br>
<span class="c">deplex</span> auth dplx_live_xxxxx<br>
<span class="m"># выбрать игру и папку с билдом</span><br>
<span class="c">deplex</span> select 42<br>
<span class="c">deplex</span> init .<br>
<span class="m"># что изменилось → залить</span><br>
<span class="c">deplex</span> status<br>
<span class="c">deplex</span> update -m <span class="p">"релиз 1.0"</span><br>
<span class="p">✔ Готово. Билд закоммичен.</span>
            </div>
        </div>
    </div>
</section>

<!-- ══ DOWNLOAD ══ -->
<section id="download">
    <div class="wrap">
        <h2 class="sec-title">Скачать deplex</h2>
        <p class="sec-sub">Распакуй и добавь в PATH · ~8 МБ · без установщика</p>
        <div class="downloads">
            <a class="dl" href="/deplex/deplex.exe" download>
                <span class="material-icons">window</span>
                <div><div class="dl-os">Windows</div><div class="dl-hint">64-bit · .exe</div></div>
            </a>
            <a class="inactive" href="#">
                <span class="material-icons">laptop_mac</span>
                <div><div class="dl-os">macOS <span style="font-size:.7rem;color:var(--primary);">скоро</span></div><div class="dl-hint">Apple Silicon</div></div>
            </a>
            <a class="inactive" href="#">
                <span class="material-icons">terminal</span>
                <div><div class="dl-os">Linux <span style="font-size:.7rem;color:var(--primary);">скоро</span></div><div class="dl-hint">64-bit</div></div>
            </a>
        </div>
        <p style="text-align:center;color:var(--muted);font-size:.85rem;margin-top:20px;">
            Полная документация — <a href="https://api.dustore.ru/docs" style="color:#e88fc0;">api.dustore.ru/docs</a>
        </p>
    </div>
</section>

<div class="foot">
    deplex · часть экосистемы <a href="/" style="color:#e88fc0;">Dustore</a> · <a style="color:#e88fc0;" href="https://github.com/AlexanderLivanov/deplexsdk" target="_blank">Исходный код</a>
</div>

<?php /* require_once($_SERVER['DOCUMENT_ROOT'] . '/swad/static/elements/footer.php'); */ ?>
</body>
</html>