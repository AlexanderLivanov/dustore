<?php
/**
 * launcher.php — лендинг DustoreX, лаунчера каталога Dustore для Windows
 * и Android (разработка студии eXepc).
 *
 * Визуально это НЕ витрина Dustore, а продукт eXepc: тёмно-синий фон,
 * мятно-бирюзовый акцент и стеклянные поверхности — тот же язык, что в
 * самом приложении 3.6.0. Все стили под пространством .dxl, глобальные
 * селекторы сайта не трогаем (кроме фона body — страница целиком тёмная).
 *
 * Картинки: /swad/static/img/launcher/*.webp — реальные скриншоты
 * приложения и фрагменты его собственной графики (баннеры eXepc).
 * Бинарники: /downloads/DustoreX-Setup.exe (3.6.0) и
 * /downloads/DustoreX-Android.apk (5.11), Apache отдаёт их напрямую.
 *
 * Тексты — пересказ Лео; размеры — фактические байты сборок.
 */
// header.php ставит cookie после того, как страница уже начала вывод —
// буфер позволяет заголовкам уйти вовремя.
ob_start();
session_start();
$img = '/swad/static/img/launcher/';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DustoreX — лаунчер Dustore для Windows и Android</title>
    <meta name="description" content="DustoreX — лаунчер dustore.ru для Windows и Android от студии eXepc: магазин, загрузки и библиотека игр в одном приложении, eXen и eXepad.">
    <meta property="og:title" content="DustoreX — игры без границ">
    <meta property="og:description" content="Лаунчер dustore.ru для Windows и Android. Магазин, загрузки и библиотека игр в одном приложении.">
    <meta property="og:image" content="https://dustore.ru<?= $img ?>win-library.webp">
    <style>
        body { background: #0a1120; }

        .dxl {
            --bg: #0a1120;
            --surface: rgba(255, 255, 255, .045);
            --surface-2: rgba(255, 255, 255, .075);
            --line: rgba(255, 255, 255, .09);
            --line-2: rgba(255, 255, 255, .16);
            --text: #e9f1fb;
            --muted: rgba(233, 241, 251, .64);
            --dim: rgba(233, 241, 251, .42);
            --teal: #6fe3d3;
            --teal-hi: #93f0e4;
            --teal-ink: #04241f;
            --teal-soft: rgba(111, 227, 211, .13);
            --violet: #8a6cff;
            --r: 20px;
            --r-sm: 12px;

            position: relative;
            overflow: hidden;
            color: var(--text);
            font-family: 'Segoe UI', 'Inter', system-ui, -apple-system, sans-serif;
            padding-bottom: 90px;
            isolation: isolate;
        }
        .dxl *, .dxl *::before, .dxl *::after { box-sizing: border-box; }
        .dxl img { display: block; max-width: 100%; height: auto; }

        /* фон: две размытые «ауры» из графики eXepc */
        .dxl-bg { position: absolute; inset: 0; z-index: -1; pointer-events: none; }
        .dxl-bg::before, .dxl-bg::after {
            content: ""; position: absolute; border-radius: 50%; filter: blur(110px); opacity: .55;
        }
        .dxl-bg::before { width: 620px; height: 620px; top: -180px; right: -120px; background: radial-gradient(circle, rgba(111,227,211,.35), transparent 65%); }
        .dxl-bg::after  { width: 720px; height: 720px; top: 380px; left: -300px; background: radial-gradient(circle, rgba(138,108,255,.30), transparent 65%); }

        .dxl-wrap { max-width: 1140px; margin: 0 auto; padding: 0 24px; }

        /* ── Hero ── */
        .dxl-hero {
            display: grid; grid-template-columns: 1fr 1.08fr; gap: 48px; align-items: center;
            padding-top: 64px; padding-bottom: 40px;
        }
        .dxl-kicker {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 6px 14px 6px 6px; border-radius: 999px;
            background: var(--surface); box-shadow: inset 0 0 0 1px var(--line);
            font-size: .82rem; color: var(--muted);
        }
        .dxl-mark {
            padding: 3px 10px; border-radius: 999px; font-weight: 700; letter-spacing: .02em;
            background: var(--teal-soft); color: var(--teal); box-shadow: inset 0 0 0 1px rgba(111,227,211,.35);
        }
        .dxl-hero h1 {
            margin: 22px 0 16px; font-size: clamp(2.6rem, 5.4vw, 4.2rem); line-height: 1.02;
            font-weight: 800; letter-spacing: -.035em;
        }
        .dxl-hero h1 span {
            background: linear-gradient(100deg, var(--teal-hi), var(--teal) 40%, #a996ff);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .dxl-lead { max-width: 480px; font-size: 1.1rem; line-height: 1.6; color: var(--muted); margin: 0; }

        .dxl-cta-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .dxl-btn {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 13px 22px 13px 18px; border-radius: 16px;
            font-weight: 700; font-size: 1rem; text-decoration: none; line-height: 1.15;
            transition: transform .18s ease, box-shadow .2s ease, background .2s ease;
        }
        .dxl-btn svg { flex: none; }
        .dxl-btn small { display: block; font-size: .72rem; font-weight: 500; opacity: .72; margin-top: 2px; }
        .dxl-btn.primary { background: var(--teal); color: var(--teal-ink); box-shadow: 0 10px 34px -10px rgba(111,227,211,.7); }
        .dxl-btn.primary:hover { background: var(--teal-hi); transform: translateY(-2px); box-shadow: 0 14px 40px -10px rgba(111,227,211,.85); }
        .dxl-btn.glass { background: var(--surface-2); color: var(--text); box-shadow: inset 0 0 0 1px var(--line-2); backdrop-filter: blur(12px); }
        .dxl-btn.glass:hover { background: rgba(255,255,255,.11); transform: translateY(-2px); }
        .dxl-btn:active { transform: scale(.98); }
        .dxl-btn:focus-visible { outline: 2px solid var(--teal-hi); outline-offset: 3px; }

        .dxl-note { margin-top: 18px; font-size: .84rem; color: var(--dim); }
        .dxl-note b { color: var(--muted); font-weight: 600; }

        /* композиция устройств из реальных скриншотов */
        .dxl-hero-art { position: relative; padding: 0 0 12% 0; }
        .dxl-laptop { margin: 0; position: relative; z-index: 1; animation: dxl-float 7s ease-in-out infinite; }
        .dxl-laptop .screen {
            border-radius: 14px 14px 4px 4px; padding: 9px; background: #070b14;
            box-shadow: 0 0 0 1px rgba(255,255,255,.12), 0 40px 80px -30px rgba(0,0,0,.9), 0 0 60px -20px rgba(111,227,211,.35);
        }
        .dxl-laptop .screen img { border-radius: 6px; width: 100%; }
        .dxl-laptop .base {
            height: 14px; margin: 0 -5%; border-radius: 0 0 14px 14px;
            background: linear-gradient(#2a3446, #121928); box-shadow: 0 1px 0 rgba(255,255,255,.1) inset;
        }
        .dxl-phone {
            margin: 0; position: absolute; z-index: 2; right: -4%; bottom: 0; width: 27%;
            border-radius: 26px; padding: 6px; background: #070b14;
            box-shadow: 0 0 0 1px rgba(255,255,255,.14), 0 30px 60px -20px rgba(0,0,0,.95), 0 0 50px -16px rgba(138,108,255,.5);
            animation: dxl-float 7s ease-in-out -3.5s infinite;
        }
        .dxl-phone img { border-radius: 20px; width: 100%; }
        @keyframes dxl-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

        /* ── Общие секции ── */
        .dxl-section { margin-top: 96px; }
        .dxl-eyebrow { font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--teal); margin-bottom: 12px; }
        .dxl-section h2 { font-size: clamp(1.7rem, 3vw, 2.3rem); font-weight: 800; letter-spacing: -.02em; margin: 0 0 14px; line-height: 1.12; }
        .dxl-section .sub { color: var(--muted); font-size: 1rem; line-height: 1.65; max-width: 560px; margin: 0; }

        .dxl-glass {
            background: var(--surface); border-radius: var(--r);
            box-shadow: inset 0 0 0 1px var(--line), inset 0 1px 0 rgba(255,255,255,.06);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
        }

        /* баннер как в самом приложении: акцентная кромка, текст, арт справа */
        .dxl-banner {
            display: grid; grid-template-columns: 1fr minmax(0, 538px); align-items: stretch; overflow: hidden;
            margin-top: 40px; position: relative;
            /* сплошной фон и обычная рамка: inset-тень уходила под картинку и давала шов */
            background: #111a2c; box-shadow: none; border: 1px solid var(--line);
        }
        .dxl-banner::before { content: ""; position: absolute; left: 0; top: 18px; bottom: 18px; width: 3px; border-radius: 3px; background: var(--teal); }
        .dxl-banner-text { padding: 30px 34px; align-self: center; }
        .dxl-banner-text h3 { margin: 0 0 8px; font-size: 1.55rem; font-weight: 800; letter-spacing: -.01em; }
        .dxl-banner-text p { margin: 0; color: var(--muted); line-height: 1.55; }
        .dxl-banner-art { position: relative; min-height: 184px; }
        .dxl-banner-art img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: right center; }
        .dxl-banner-art::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, #111a2c 0%, rgba(17,26,44,0) 45%); }

        /* сплит: скриншот + список */
        .dxl-split { display: grid; grid-template-columns: 1.15fr 1fr; gap: 56px; align-items: center; }
        .dxl-split.reverse { grid-template-columns: 1fr 1.05fr; }
        .dxl-split.reverse .dxl-split-media { order: 2; }

        .dxl-window { padding: 8px; border-radius: 16px; background: #070b14; box-shadow: 0 0 0 1px var(--line-2), 0 30px 70px -30px rgba(0,0,0,.9); }
        .dxl-window-bar { display: flex; gap: 6px; padding: 4px 6px 10px; }
        .dxl-window-bar i { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,.14); }
        .dxl-window img { border-radius: 8px; width: 100%; }

        .dxl-list { list-style: none; margin: 26px 0 0; padding: 0; display: grid; gap: 4px; }
        .dxl-list li { display: grid; grid-template-columns: 30px 1fr; gap: 12px; align-items: start; padding: 10px 12px; border-radius: 12px; transition: background .2s ease; }
        .dxl-list li:hover { background: var(--surface); }
        .dxl-list .ic { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal); }
        .dxl-list b { display: block; font-weight: 650; font-size: .98rem; }
        .dxl-list span { display: block; color: var(--muted); font-size: .9rem; line-height: 1.5; margin-top: 2px; }

        .dxl-phones { position: relative; display: flex; justify-content: center; gap: 22px; padding: 10px 0 30px; }
        .dxl-phones figure { margin: 0; width: min(42%, 230px); border-radius: 26px; padding: 6px; background: #070b14; box-shadow: 0 0 0 1px rgba(255,255,255,.14), 0 30px 60px -24px rgba(0,0,0,.95); }
        .dxl-phones figure:last-child { transform: translateY(34px); }
        .dxl-phones img { border-radius: 20px; width: 100%; }

        [data-zoom] { cursor: zoom-in; }

        /* eXen / eXepad */
        .dxl-duo { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .dxl-feature { overflow: hidden; display: flex; flex-direction: column; }
        .dxl-feature-media { aspect-ratio: 491 / 198; overflow: hidden; background: #070b14; border-bottom: 1px solid var(--line); }
        .dxl-feature-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
        .dxl-feature:hover .dxl-feature-media img { transform: scale(1.03); }
        .dxl-feature-body { padding: 24px 26px 26px; }
        .dxl-feature-body h3 { margin: 0 0 4px; font-size: 1.35rem; font-weight: 800; }
        .dxl-feature-body .tag { display: inline-block; font-size: .78rem; color: var(--teal); font-weight: 600; margin-bottom: 12px; }
        .dxl-feature-body p { margin: 0; color: var(--muted); line-height: 1.62; font-size: .95rem; }

        /* что нового */
        .dxl-release { padding: 30px 32px; margin-top: 30px; display: grid; grid-template-columns: 220px 1fr; gap: 32px; }
        .dxl-release-meta .v { font-size: 2.6rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
        .dxl-release-meta .v small { font-size: 1rem; color: var(--dim); font-weight: 600; margin-left: 4px; }
        .dxl-release-meta p { color: var(--muted); font-size: .88rem; margin: 10px 0 16px; line-height: 1.5; }
        .dxl-release-meta a { color: var(--teal); font-weight: 600; font-size: .9rem; text-decoration: none; }
        .dxl-release-meta a:hover { text-decoration: underline; }
        .dxl-changes { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .dxl-changes li { padding: 16px 18px; border-radius: 14px; background: rgba(0,0,0,.18); box-shadow: inset 0 0 0 1px var(--line); }
        .dxl-changes b { display: block; font-size: .95rem; margin-bottom: 4px; }
        .dxl-changes span { color: var(--muted); font-size: .88rem; line-height: 1.5; }

        /* требования */
        .dxl-req { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .dxl-req-card { padding: 26px 28px; }
        .dxl-req-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .dxl-req-head .ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal); }
        .dxl-req-head b { font-size: 1.1rem; }
        .dxl-req-head small { display: block; color: var(--dim); font-size: .8rem; }
        .dxl-req dl { margin: 0; display: grid; gap: 0; }
        .dxl-req dl div { display: grid; grid-template-columns: 150px 1fr; gap: 12px; padding: 12px 0; border-top: 1px solid var(--line); }
        .dxl-req dt { color: var(--dim); font-size: .85rem; }
        .dxl-req dd { margin: 0; font-size: .92rem; font-weight: 600; line-height: 1.45; }

        /* финальный CTA */
        .dxl-final {
            margin-top: 96px; padding: 54px 40px; text-align: center; position: relative; overflow: hidden;
            background: linear-gradient(135deg, rgba(111,227,211,.16), rgba(138,108,255,.16));
            box-shadow: inset 0 0 0 1px rgba(111,227,211,.25);
        }
        .dxl-final h2 { margin: 0 0 10px; font-size: clamp(1.8rem, 3.2vw, 2.5rem); font-weight: 800; letter-spacing: -.02em; }
        .dxl-final p { margin: 0 auto; color: var(--muted); max-width: 520px; line-height: 1.6; }
        .dxl-final .dxl-cta-row { justify-content: center; }
        .dxl-final .credit { margin-top: 24px; font-size: .82rem; color: var(--dim); }

        /* лайтбокс скриншотов */
        .dxl-lightbox {
            position: fixed; inset: 0; z-index: 10000; display: grid; place-items: center; padding: 4vh 4vw;
            background: rgba(4, 8, 16, .88); backdrop-filter: blur(8px); cursor: zoom-out;
        }
        .dxl-lightbox[hidden] { display: none; }
        .dxl-lightbox img { max-width: 100%; max-height: 92vh; border-radius: 14px; box-shadow: 0 30px 80px rgba(0,0,0,.7); }

        /* появление при прокрутке — только если JS жив */
        .dxl.js .reveal { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s ease; }
        .dxl.js .reveal.in { opacity: 1; transform: none; }

        @media (max-width: 960px) {
            .dxl-hero, .dxl-split, .dxl-split.reverse { grid-template-columns: 1fr; gap: 40px; }
            .dxl-split.reverse .dxl-split-media { order: 0; }
            .dxl-hero { padding-top: 40px; }
            .dxl-release { grid-template-columns: 1fr; gap: 20px; }
        }
        @media (max-width: 720px) {
            .dxl-banner { grid-template-columns: 1fr; }
            .dxl-banner-art { min-height: 150px; }
            .dxl-banner-art::after { background: linear-gradient(180deg, #111a2c 0%, rgba(17,26,44,0) 40%); }
            .dxl-duo, .dxl-req, .dxl-changes { grid-template-columns: 1fr; }
            .dxl-req dl div { grid-template-columns: 1fr; gap: 2px; }
            .dxl-section { margin-top: 72px; }
            .dxl-final { padding: 40px 22px; }
            .dxl-btn { width: 100%; }
        }
        @media (max-width: 480px) { .dxl-wrap { padding: 0 16px; } }
        @media (prefers-reduced-motion: reduce) {
            .dxl-laptop, .dxl-phone { animation: none; }
            .dxl.js .reveal { opacity: 1; transform: none; transition: none; }
        }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main class="dxl" id="dxl">
        <div class="dxl-bg" aria-hidden="true"></div>
        <script>document.getElementById('dxl').classList.add('js');</script>

        <!-- ── Hero ── -->
        <section class="dxl-wrap dxl-hero">
            <div>
                <div class="dxl-kicker"><span class="dxl-mark">eXepc</span> DustoreX · Windows 3.6.0 · Android 5.11</div>
                <h1>Игры <span>без границ</span></h1>
                <p class="dxl-lead">Лаунчер dustore.ru для Windows и Android. Магазин, загрузки и библиотека игр — в одном приложении.</p>
                <div class="dxl-cta-row">
                    <a class="dxl-btn primary" href="/downloads/DustoreX-Setup.exe" download>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        <span>Скачать для Windows<small>x64 · ~75 МБ · без установки</small></span>
                    </a>
                    <a class="dxl-btn glass" href="/downloads/DustoreX-Android.apk" download>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
                        <span>Скачать для Android<small>7.0+ · ~7,7 МБ · APK</small></span>
                    </a>
                </div>
                <div class="dxl-note">Разработка — <b>студия eXepc</b>. Распространяется через Dustore.</div>
            </div>
            <div class="dxl-hero-art">
                <figure class="dxl-laptop">
                    <div class="screen"><img src="<?= $img ?>win-library.webp" width="1280" height="689" alt="Библиотека DustoreX на Windows" data-zoom></div>
                    <div class="base"></div>
                </figure>
                <figure class="dxl-phone"><img src="<?= $img ?>phone-library.webp" width="590" height="1280" alt="Библиотека DustoreX на Android" data-zoom></figure>
            </div>
        </section>

        <!-- ── Баннер как в приложении ── -->
        <section class="dxl-wrap">
            <div class="dxl-glass dxl-banner reveal">
                <div class="dxl-banner-text">
                    <h3>Одна библиотека на ПК и телефоне</h3>
                    <p>Совместимые игры — на другом экране с eXen.</p>
                </div>
                <div class="dxl-banner-art"><img src="<?= $img ?>banner-devices.webp" width="538" height="184" alt="" loading="lazy"></div>
            </div>
        </section>

        <!-- ── На компьютере ── -->
        <section class="dxl-wrap dxl-section dxl-split reveal">
            <div class="dxl-split-media">
                <div class="dxl-window">
                    <div class="dxl-window-bar"><i></i><i></i><i></i></div>
                    <img src="<?= $img ?>win-library.webp" width="1280" height="689" alt="DustoreX на Windows — вкладка «Библиотека»" loading="lazy" data-zoom>
                </div>
            </div>
            <div>
                <div class="dxl-eyebrow">Windows</div>
                <h2>На компьютере</h2>
                <p class="sub">Один переносимый EXE — сайт открывается прямо внутри приложения.</p>
                <ul class="dxl-list">
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Без установки</b><span>Один EXE-файл, ставить лаунчер не нужно.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Библиотека сама собирается</b><span>Скачанные игры автоматически попадают в библиотеку.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Архивы и файл запуска</b><span>Архив можно распаковать, а файл запуска найти из карточки игры.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Сейвы и диагностика</b><span>Отдельные вкладки для сохранений и диагностики запуска.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Android-игры на ПК</b><span>Открываются через локальную среду Android.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Вкладка «Шлем»</b><span>Подключение VR-устройства и установка приложения-спутника.</span></div></li>
                </ul>
            </div>
        </section>

        <!-- ── На телефоне ── -->
        <section class="dxl-wrap dxl-section dxl-split reverse reveal">
            <div class="dxl-split-media">
                <div class="dxl-phones">
                    <figure><img src="<?= $img ?>phone-library.webp" width="590" height="1280" alt="DustoreX на Android — библиотека" loading="lazy" data-zoom></figure>
                    <figure><img src="<?= $img ?>phone-computer.webp" width="590" height="1280" alt="DustoreX на Android — вкладка «Компьютер»" loading="lazy" data-zoom></figure>
                </div>
            </div>
            <div>
                <div class="dxl-eyebrow">Android</div>
                <h2>На телефоне</h2>
                <p class="sub">Свой магазин и библиотека, плюс связь с компьютером.</p>
                <ul class="dxl-list">
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Магазин и загрузки</b><span>Магазин, загрузки и своя библиотека игр.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Установка APK</b><span>Скачанные APK ставятся прямо из библиотеки.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Веб-игры внутри</b><span>Если у игры есть веб-версия — она запускается в приложении.</span></div></li>
                    <li><span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Связь с ПК</b><span>Перенос игр и работа eXepad.</span></div></li>
                </ul>
            </div>
        </section>

        <!-- ── eXen и eXepad ── -->
        <section class="dxl-wrap dxl-section reveal">
            <div class="dxl-eyebrow">Технологии eXepc</div>
            <h2>eXen и eXepad</h2>
            <p class="sub">Windows-игра на телефоне — и телефон вместо геймпада для компьютера.</p>
            <div class="dxl-duo">
                <article class="dxl-glass dxl-feature">
                    <div class="dxl-feature-media"><img src="<?= $img ?>banner-exen.webp" width="491" height="198" alt="eXen: ПК → телефон" loading="lazy"></div>
                    <div class="dxl-feature-body">
                        <h3>eXen</h3>
                        <span class="tag">ПК → телефон</span>
                        <p>Готовит на телефоне среду для запуска совместимых Windows-игр, скачанных через DustoreX. После подготовки компьютер для игры не нужен. Скорость и совместимость зависят от игры и телефона.</p>
                    </div>
                </article>
                <article class="dxl-glass dxl-feature">
                    <div class="dxl-feature-media"><img src="<?= $img ?>exepad.webp" width="1280" height="590" alt="eXepad — раскладка «Шутер и хоррор»" loading="lazy" data-zoom></div>
                    <div class="dxl-feature-body">
                        <h3>eXepad</h3>
                        <span class="tag">Телефон → пульт</span>
                        <p>Превращает телефон в пульт для игры на ПК. На экране появляются стик и кнопки выбранной раскладки, а нажатия передаются в игру.</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- ── В этой версии ── -->
        <section class="dxl-wrap dxl-section reveal">
            <div class="dxl-eyebrow">Обновление</div>
            <h2>В этой версии</h2>
            <div class="dxl-glass dxl-release">
                <div class="dxl-release-meta">
                    <div class="v">3.6.0<small>/ 5.11</small></div>
                    <p>Windows и Android выходят вместе — с новым дизайном.</p>
                    <a href="/whatsnew">Вся история изменений →</a>
                </div>
                <ul class="dxl-changes">
                    <li><b>Новый дизайн</b><span>Иллюстрации, стеклянные поверхности и короткие анимации на Windows и Android.</span></li>
                    <li><b>Открывается развёрнутым</b><span>Windows-лаунчер стартует на весь экран.</span></li>
                    <li><b>eXepad точнее</b><span>Исправлены расположение стика и кнопок, масштаб превью и отклик на касания.</span></li>
                    <li><b>Знаки eXepc</b><span>Добавлены в интерфейс и графику приложения.</span></li>
                </ul>
            </div>
        </section>

        <!-- ── Требования ── -->
        <section class="dxl-wrap dxl-section reveal">
            <div class="dxl-eyebrow">Перед установкой</div>
            <h2>Системные требования</h2>
            <div class="dxl-req">
                <div class="dxl-glass dxl-req-card">
                    <div class="dxl-req-head">
                        <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                        <div><b>Windows</b><small>версия 3.6.0</small></div>
                    </div>
                    <dl>
                        <div><dt>Система</dt><dd>Windows x64</dd></div>
                        <div><dt>Встроенный браузер</dt><dd>Microsoft Edge WebView2 Runtime</dd></div>
                        <div><dt>Размер</dt><dd>~75 МБ, один EXE</dd></div>
                    </dl>
                </div>
                <div class="dxl-glass dxl-req-card">
                    <div class="dxl-req-head">
                        <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg></span>
                        <div><b>Android</b><small>версия 5.11</small></div>
                    </div>
                    <dl>
                        <div><dt>Система</dt><dd>Android 7.0 и новее</dd></div>
                        <div><dt>Для eXen</dt><dd>ARM64-телефон с подходящей графической системой</dd></div>
                        <div><dt>Размер</dt><dd>~7,7 МБ, APK</dd></div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- ── Финальный CTA ── -->
        <section class="dxl-wrap">
            <div class="dxl-glass dxl-final reveal">
                <h2>Скачайте DustoreX</h2>
                <p>Одна библиотека на компьютере и телефоне.</p>
                <div class="dxl-cta-row">
                    <a class="dxl-btn primary" href="/downloads/DustoreX-Setup.exe" download>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        <span>Windows<small>3.6.0 · ~75 МБ</small></span>
                    </a>
                    <a class="dxl-btn glass" href="/downloads/DustoreX-Android.apk" download>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
                        <span>Android<small>5.11 · ~7,7 МБ</small></span>
                    </a>
                </div>
                <div class="credit">DustoreX разработан студией eXepc и распространяется через Dustore.</div>
            </div>
        </section>
    </main>

    <div class="dxl-lightbox" id="dxlLightbox" hidden><img alt=""></div>

    <script>
        (function () {
            // появление секций при прокрутке
            var els = document.querySelectorAll('.dxl .reveal');
            if ('IntersectionObserver' in window) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
                    });
                }, { rootMargin: '0px 0px -8% 0px' });
                els.forEach(function (el) { io.observe(el); });
            } else {
                els.forEach(function (el) { el.classList.add('in'); });
            }

            // скриншоты в полный размер
            var box = document.getElementById('dxlLightbox'), big = box.querySelector('img');
            document.querySelectorAll('.dxl [data-zoom]').forEach(function (img) {
                img.addEventListener('click', function () {
                    big.src = img.currentSrc || img.src; big.alt = img.alt; box.hidden = false;
                });
            });
            function close() { box.hidden = true; big.removeAttribute('src'); }
            box.addEventListener('click', close);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) close(); });
        })();
    </script>

    <?php require_once('swad/static/elements/footer.php'); ?>
</body>

</html>
