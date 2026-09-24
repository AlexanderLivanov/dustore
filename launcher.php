<?php
/**
 * launcher.php — лендинг DustoreX, лаунчера каталога Dustore для Windows
 * (разработка студии eXepc). Чисто информационная страница + кнопка
 * скачивания; сам .exe лежит статикой в /downloads/DustoreX-Setup.exe
 * (Apache отдаёт файлы из htdocs напрямую, отдельный контроллер не нужен).
 *
 * Все факты о самом лаунчере ниже взяты либо из пересказа Лео, либо из
 * статических метаданных .exe (без запуска — PE-заголовки/версии через
 * pefile): раздел «Системные требования» и размер файла. Придумывать
 * характеристики, которых не давали, не стали.
 */
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DustoreX — лаунчер Dustore для Windows</title>
    <meta name="description" content="DustoreX — лаунчер каталога Dustore для Windows от студии eXepc: библиотека игр, время в игре и отзывы прямо в приложении.">
    <style>
        :root {
            --primary: #c32178;
            --primary-d: #74155d;
            --dark: #0d0118;
            --surface: rgba(255, 255, 255, .05);
            --border: rgba(255, 255, 255, .09);
            --text: #f0e6ff;
            --muted: rgba(255, 255, 255, .5);
            --success: #00ff99;
            --radius: 14px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }

        .dxl-wrap { max-width: 980px; margin: 0 auto; padding: 0 20px 80px; }

        /* ── Hero ── */
        .dxl-hero {
            position: relative;
            border-radius: 24px;
            margin-top: 28px;
            padding: 64px 40px;
            text-align: center;
            overflow: hidden;
            background: linear-gradient(160deg, #14041d 0%, #400c4a 45%, #74155d 78%, #c32178 100%);
            box-shadow: 0 30px 80px -30px rgba(0, 0, 0, .8), 0 0 0 1px rgba(255, 255, 255, .06) inset;
        }

        .dxl-eyebrow {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .65);
            margin-bottom: 14px;
        }

        .dxl-hero h1 {
            font-size: clamp(2.4rem, 6vw, 3.6rem);
            font-weight: 800;
            letter-spacing: -.02em;
            margin-bottom: 16px;
        }

        .dxl-hero p {
            max-width: 560px;
            margin: 0 auto 30px;
            font-size: 1.05rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, .82);
        }

        .dxl-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 999px;
            background: #fff;
            color: #1a0a2a;
            font-size: 1.05rem;
            font-weight: 800;
            text-decoration: none;
            transition: transform .15s, box-shadow .2s;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .35);
        }

        .dxl-cta:hover { transform: translateY(-2px); box-shadow: 0 16px 38px rgba(0, 0, 0, .45); }
        .dxl-cta:active { transform: scale(.98); }

        .dxl-meta {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            font-size: .8rem;
            color: rgba(255, 255, 255, .6);
        }

        .dxl-meta span {
            background: rgba(0, 0, 0, .25);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 999px;
            padding: 5px 13px;
        }

        .dxl-studio {
            margin-top: 22px;
            font-size: .82rem;
            color: rgba(255, 255, 255, .55);
        }

        .dxl-studio b { color: rgba(255, 255, 255, .85); }

        /* ── Секции ── */
        .dxl-section { margin-top: 56px; }

        .dxl-section h2 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -.01em;
            margin-bottom: 14px;
        }

        .dxl-section > p {
            max-width: 680px;
            color: var(--muted);
            line-height: 1.7;
            font-size: .96rem;
        }

        .dxl-grid {
            margin-top: 26px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        @media (max-width: 640px) { .dxl-grid { grid-template-columns: 1fr; } }

        .dxl-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
        }

        .dxl-card-icon { font-size: 1.6rem; margin-bottom: 12px; }
        .dxl-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 8px; }
        .dxl-card p { font-size: .86rem; color: var(--muted); line-height: 1.6; }

        /* ── Системные требования ── */
        .dxl-req {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 28px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 640px) { .dxl-req { grid-template-columns: 1fr; } }

        .dxl-req-item .l { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 6px; }
        .dxl-req-item .v { font-size: 1rem; font-weight: 700; }

        /* ── Финальный CTA ── */
        .dxl-final {
            margin-top: 64px;
            text-align: center;
            padding: 40px 20px;
            border-top: 1px solid var(--border);
        }

        .dxl-final p { color: var(--muted); margin-top: 16px; font-size: .85rem; }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main>
        <div class="dxl-wrap">

            <div class="dxl-hero">
                <div class="dxl-eyebrow">Лаунчер Dustore</div>
                <h1>DustoreX</h1>
                <p>Каталог, загрузка и библиотека игр Dustore — в отдельном приложении для Windows,
                    устроенном по принципу Steam: страницы сайта открываются прямо внутри
                    лаунчера, а скачанные игры остаются под рукой в одном месте.</p>
                <a class="dxl-cta" href="/downloads/DustoreX-Setup.exe" download>
                    ⬇ Скачать DustoreX
                </a>
                <div class="dxl-meta">
                    <span>Windows 64-bit</span>
                    <span>~64 МБ</span>
                    <span>Версия 1.0.0</span>
                </div>
                <div class="dxl-studio">Разработка: <b>eXepc</b></div>
            </div>

            <div class="dxl-section">
                <h2>Как это работает</h2>
                <p>DustoreX не дублирует сайт отдельным интерфейсом — он загружает те же
                    страницы Dustore внутрь своего окна, поэтому каталог, страницы игр и
                    процесс покупки выглядят и работают так же, как в браузере. Разница в
                    том, что скачивание, установка и запуск игр происходят сразу здесь же,
                    без переключения между сайтом и файлами на диске.</p>
            </div>

            <div class="dxl-section">
                <h2>Что внутри</h2>
                <div class="dxl-grid">
                    <div class="dxl-card">
                        <div class="dxl-card-icon">⬇</div>
                        <h3>Загрузка в один клик</h3>
                        <p>Скачивайте игры прямо со страницы Dustore — DustoreX ставит их
                            и добавляет в свою библиотеку автоматически.</p>
                    </div>
                    <div class="dxl-card">
                        <div class="dxl-card-icon">📚</div>
                        <h3>Библиотека</h3>
                        <p>Все установленные игры — в одном месте, без поиска по папкам
                            и ярлыкам на рабочем столе.</p>
                    </div>
                    <div class="dxl-card">
                        <div class="dxl-card-icon">⏱</div>
                        <h3>Время в игре</h3>
                        <p>DustoreX считает, сколько вы провели в каждой игре, и показывает
                            это прямо в библиотеке.</p>
                    </div>
                    <div class="dxl-card">
                        <div class="dxl-card-icon">💬</div>
                        <h3>Отзывы в лаунчере</h3>
                        <p>Отдельная система отзывов внутри DustoreX — независимая от
                            отзывов на сайте Dustore.</p>
                    </div>
                </div>
            </div>

            <div class="dxl-section">
                <h2>Системные требования</h2>
                <div class="dxl-req">
                    <div class="dxl-req-item">
                        <div class="l">Операционная система</div>
                        <div class="v">Windows, 64-bit</div>
                    </div>
                    <div class="dxl-req-item">
                        <div class="l">Размер установщика</div>
                        <div class="v">~64 МБ</div>
                    </div>
                    <div class="dxl-req-item">
                        <div class="l">Версия</div>
                        <div class="v">1.0.0</div>
                    </div>
                </div>
            </div>

            <div class="dxl-final">
                <a class="dxl-cta" href="/downloads/DustoreX-Setup.exe" download>
                    ⬇ Скачать DustoreX
                </a>
                <p>DustoreX разработан студией eXepc и распространяется через Dustore.</p>
            </div>

        </div>
    </main>

    <?php require_once('swad/static/elements/footer.php'); ?>
</body>

</html>
