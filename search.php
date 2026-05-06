<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — Каталог студий и пользователей</title>
    <link rel="stylesheet" href="swad/css/explore.css">
    <link rel="shortcut icon" href="swad/static/img/logo.svg" type="image/x-icon">
    <style>
        /* --------------------------------------------- */
        /*  Переменные (можно синхронизировать с explore) */
        /* --------------------------------------------- */
        :root {
            --primary: #c32178;
            --primary-dark: #9e1a66;
            --primary-light: #e6399e;
            --bg-dark: #14041d;
            --bg-card: rgba(255, 255, 255, 0.05);
            --border-light: rgba(255, 255, 255, 0.08);
            --text-primary: #fff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.5);
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.4);
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --transition: 0.2s ease;
        }

        /* --------------------------------------------- */
        /*  Глобальные стили (переопределение/дополнение) */
        /* --------------------------------------------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(180deg, #0f0a20);
            color: var(--text-primary);
            line-height: 1.5;
        }

        main {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px - 40px); /* хедер + футер */
            overflow: hidden;
        }

        .games-header {
            flex-shrink: 0;
            padding: 40px 0 0;
        }

        .container1 {
            text-align: center;
            font-size: 15px;
            background: transparent;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
        }

        .container1 h1 {
            font-size: 2.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, #ffb6e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .container1 p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Главный контейнер контента */
        .container {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: rgb(255 255 255 / 9%);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            max-width: 100%;
            min-width: 95.5%;
            margin: 0 auto;
            box-shadow: var(--shadow-sm);
        }

        /* --------------------------------------------- */
        /*  Фильтры + поиск                              */
        /* --------------------------------------------- */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 24px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(195, 33, 120, 0.3);
        }

        /* Поисковое поле */
        .search-wrapper {
            flex: 1 1 320px;
            max-width: 500px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 48px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }

        .search-input:focus {
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 3px rgba(195, 33, 120, 0.2);
        }

        .search-input::placeholder {
            color: var(--text-muted);
            font-weight: 300;
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-clear {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            display: none;
            padding: 4px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .search-clear:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .search-wrapper.filled .search-clear {
            display: block;
        }

        /* Счётчик результатов */
        .results-count {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-left: auto;
            white-space: nowrap;
        }

        /* --------------------------------------------- */
        /*  Сетка карточек                                */
        /* --------------------------------------------- */
        .grid-container {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(279px, 1fr));
            gap: 20px;
            padding: 10px 10px 10px 10px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
            background: #00000078;
            border-radius: 20px;
        }

        .grid-container::-webkit-scrollbar {
            width: 4px;
        }
        .grid-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .grid-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }
        .grid-container::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* --------------------------------------------- */
        /*  Карточка (современный glassmorphism)         */
        /* --------------------------------------------- */
        .card {
            backdrop-filter: blur(15px);
            border-radius: var(--radius-md);
            padding: 20px;
            cursor: pointer;
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1), box-shadow 0.3s, border-color 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: 0px 0px 4px 4px rgba(0, 0, 0, 0.2);
            transform: translateY(0);
            background: #a9a9a947;
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(195, 33, 120, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(195, 33, 120, 0.2);
        }

        /* Псевдоэлемент для красивого свечения */
        .card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(195,33,120,0.2));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card:hover::after {
            opacity: 1;
        }

        /* Шапка карточки с аватаром и типом */
        .card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(195, 33, 120, 0.4);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255,255,255,0.1);
            color: var(--text-secondary);
            border: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 6px;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 6px;
            color: #fff;
        }

        .card-desc {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .card-desc-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-desc-item i {
            opacity: 0.6;
            font-size: 14px;
            width: 18px;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .card-footer .website {
            color: var(--primary-light);
            text-decoration: none;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-footer .website:hover {
            text-decoration: underline;
        }

        /* --------------------------------------------- */
        /*  Пустое состояние                              */
        /* --------------------------------------------- */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(4px);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            font-weight: 400;
            margin-bottom: 10px;
            color: var(--text-secondary);
        }

        .empty-state p {
            font-size: 1.1rem;
        }

        /* --------------------------------------------- */
        /*  Адаптивность                                  */
        /* --------------------------------------------- */
        @media (max-width: 700px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .filters {
                justify-content: center;
            }
            .search-wrapper {
                max-width: 100%;
            }
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: 16px;
            }
            .container1 h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .card-header {
                flex-direction: column;
                text-align: center;
            }
        }

        /* --------------------------------------------- */
        /*  Анимация появления карточек                  */
        /* --------------------------------------------- */
        .card {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.4s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Задержки для карточек (задаются через JS) */
    </style>
</head>
<body>
    <!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m, e, t, r, i, k, a) {
            m[i] = m[i] || function() {
                (m[i].a = m[i].a || []).push(arguments)
            };
            m[i].l = 1 * new Date();
            for (var j = 0; j < document.scripts.length; j++) {
                if (document.scripts[j].src === r) {
                    return;
                }
            }
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(101729504, "init", {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true
        });
    </script>
    <noscript>
        <div><img src="https://mc.yandex.ru/watch/101729504" style="position:absolute; left:-9999px;" alt="" /></div>
    </noscript>
    <!-- /Yandex.Metrika counter -->
    <link rel="stylesheet" href="/swad/css/header.css?v=49d783a0">
    <link rel="shortcut icon" href="../img/logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="/swad/css/style.css?v=828198b9">
    <link rel="stylesheet" href="/swad/css/notifications.css?v=89c2baaa">
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Status Bar">
    <meta name="theme-color" content="#14041d">
    <meta name="description" content="Dustore.ru - новая игровая платформа! Скачивайте новинки инди-разработчиков.">
    <meta name="robots" content="index,follow">
    <meta name="generator" content="SWAD Framework">
    <meta name="google" content="notranslate">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <style>
        .update-progress {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-right: 15px;
            font-family: inherit;
        }

        @media (width < 900px) {
            .update-progress {
                display: none;
            }
        }

        .update-percent {
            font-size: 12px;
            color: #fff;
            margin-bottom: 3px;
        }

        .update-bar {
            width: 100px;
            height: 6px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 3px;
        }

        .update-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #7fff9f, #42d37d);
            width: 0%;
            transition: width 0.4s ease;
        }

        .update-next {
            font-size: 11px;
            opacity: 0.75;
            color: #fff;
        }

        .nav-dropdown__item--accent:hover {
            color: #ff5ba8 !important;
            background: rgba(195,33,120,.08);
        }
    </style>
</head>

<body>
    <!-- <div id="preloader" class="preloader">
        <div class="preloader-content">
            <img src="/swad/static/img/logo_new.png" alt="Dustore" class="preloader-logo">
        </div>
    </div> -->
    <!-- <div id="push-banner" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#333; color:#fff; padding:15px; text-align:center; z-index:1000;">
        🔔 Хотите получать уведомления? Так вы не пропустите ничего нового...
        <button id="enable-push" style="margin-left:10px; padding:5px 10px;">Включить</button>
        <button id="dismiss-push" style="margin-left:10px; padding:5px 10px;">Конечно! (отключить)</button>
    </div> -->

    <script>
        async function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(base64), c => c.charCodeAt(0));
        }

        async function subscribeToPush() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('Ваш браузер не поддерживает push-уведомления.');
                return;
            }

            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: await urlBase64ToUint8Array("BC-vFfqR7RP4t-JzKUMPwdI6SFSxpuGTF2hH79bF6mMIafYrEXN_EfTLVhIomEI6id0DDH0cLncM42QkCiOim7U")
                });

                const res = await fetch('/api/push/subscribe.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(sub)
                });

                const data = await res.json();
                if (data.ok) {
                    alert('Вы успешно подписались на уведомления!');
                    localStorage.setItem('push-banner-dismissed', 'true'); // скрываем баннер навсегда
                } else {
                    alert('Ошибка при подписке: ' + (data.msg || 'неизвестная ошибка'));
                }
            } catch (err) {
                console.error(err);
                alert('Подписка не удалась.');
            }
        }

        async function requestPushPermission() {
            if (!('Notification' in window)) {
                alert('Ваш браузер не поддерживает уведомления.');
                return;
            }

            if (Notification.permission === 'granted') {
                // Уже разрешено
                await subscribeToPush();
            } else if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    await subscribeToPush();
                } else {
                    console.log('Пользователь отклонил уведомления или закрыл запрос');
                }
            } else if (Notification.permission === 'denied') {
                alert('Вы запретили уведомления. Включите их в настройках браузера.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Показ баннера, если разрешение ещё не дано и пользователь не закрыл ранее
            setTimeout(() => {
                if (!localStorage.getItem('push-banner-dismissed') && Notification.permission !== 'granted') {
                    document.getElementById('push-banner').style.display = 'block';
                }
            }, 500);

            document.getElementById('enable-push').addEventListener('click', async () => {
                await requestPushPermission();
                document.getElementById('push-banner').style.display = 'none';
            });

            document.getElementById('dismiss-push').addEventListener('click', () => {
                document.getElementById('push-banner').style.display = 'none';
                localStorage.setItem('push-banner-dismissed', 'true'); // скрываем навсегда
            });
        });
    </script>


    <!-- <button onclick='subscribeToPush()'>
        Subscribe
    </button>
    <button id="pushBtn">
        Push
    </button> -->

    <div class="top-banner" id="top-banner">
        <div class="banner-content">
            <div class="banner-text">
                Домену dustore.ru 1 год! Самое время присоединиться и следить за новостями в нашем <a style="color: lightgreen;" target="_blank" href="https://t.me/dustore_official">Telegram канале<svg style="vertical-align: middle;"
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#fff"
                        stroke-width="1"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M15 10l-4 4l6 6l4 -16l-18 7l4 2l2 6l3 -4" />
                    </svg></a> и <a style="color: lightgreen;" target="_blank" href="https://vk.com/crazyprojectslab">VK сообществе<svg style="vertical-align: middle;"
                        xmlns=" http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#fff"
                        stroke-width="1"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M14 19h-4a8 8 0 0 1 -8 -8v-5h4v5a4 4 0 0 0 4 4h0v-9h4v4.5l.03 0a4.531 4.531 0 0 0 3.97 -4.496h4l-.342 1.711a6.858 6.858 0 0 1 -3.658 4.789h0a5.34 5.34 0 0 1 3.566 4.111l.434 2.389h0h-4a4.531 4.531 0 0 0 -3.97 -4.496v4.5z" />
                    </svg></a>

            </div>
            <button class="close-banner" id="close-banner">&times;</button>
        </div>
    </div>
    <div class="header">
        <div class="section left-section">
            <div>
                <button id="burger" class="button" style="padding: 0; z-index: 1000;"><svg height="48" id="svg8" version="1.1" viewBox="0 0 12.7 12.7" width="48" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg">
                        <g id="layer1" transform="translate(0,-284.29998)">
                            <path d="m 2.8222223,287.1222 v 1.41111 h 7.0555558 v -1.41111 z m 0,2.82222 v 1.41112 h 7.0555558 v -1.41112 z m 0,2.82223 v 1.41111 h 7.0555558 v -1.41111 z" id="rect4487" style="opacity:1;vector-effect:none;fill:#000000;fill-opacity:1;stroke:none;stroke-width:0.07055555;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1" />
                        </g>
                    </svg></button>
            </div>
            <div class="buttons-left">
                <button class="button" onclick="location.href='/explore'">Игры</button>
                <button class="button" onclick="location.href='/search'">Поиск</button>
                <!-- <button class="button" onclick="location.href='/about'">О нас</button> -->

                <!-- Dropdown «Для разработчиков» -->
                <div class="nav-dropdown">
                    <button class="button nav-dropdown__trigger" aria-haspopup="true" aria-expanded="false">
                        Для разработчиков
                        <svg class="nav-dropdown__arrow" width="10" height="6" viewBox="0 0 10 6" fill="none">
                            <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <ul class="nav-dropdown__menu" role="menu">
                        <!-- <li><a class="nav-dropdown__item" href="/about"        role="menuitem">О нас</a></li> -->
                        <li><a class="nav-dropdown__item" href="/assetstore"   role="menuitem">Ассеты</a></li>
                        <li><a class="nav-dropdown__item" href="/brokenpixel"  role="menuitem">Битый Пиксель</a></li>
                        <li><a class="nav-dropdown__item" href="/jams/sprints" role="menuitem">Джемы</a></li>

                        <!-- Разделитель -->
                        <li role="separator" style="height:1px;background:rgba(255,255,255,.08);margin:4px 8px;"></li>

                                                    <!-- Залогинен — показываем прямую ссылку в консоль -->
                            <li>
                                <a class="nav-dropdown__item nav-dropdown__item--accent"
                                href="/devs/" role="menuitem"
                                style="color:#ff5ba8;font-weight:600;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        style="vertical-align:middle;margin-right:4px;">
                                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                                        <path d="M8 21h8M12 17v4"/>
                                    </svg>
                                    Консоль разработчика
                                </a>
                            </li>
                                            </ul>
                </div>
            </div>
        </div>
        <div class="section center-section">
            <div class="center-floating-block">
                <!-- Сюда можешь поместить любой контент: текст, картинку, форму -->
                <p style="color: #c4a93a;font-weight: bolder;font-size: larger;font-family: 'PixelizerBold';">1 год домену</p>
            </div>
            <div class="image">
                <!-- <img src="/swad/static/img/logo_.png" alt="" onclick="location.href='/'"> -->
                <img src="/swad/static/img/logo_new_year.png" alt="" onclick="location.href='/'">
                <!-- <img id="dancingCow" style="height: 80px;"
                    src="https://media.tenor.com/yNy3XaDrdjgAAAAj/polish-dancing-cow-dancing.gif"
                    alt=""
                    onclick="location.href='/'">

                <audio id="cowSound" src="/swad/static/img/cow.mp3" preload="auto"></audio> -->

                <script>
                    const gif = document.getElementById('dancingCow');
                    const sound = document.getElementById('cowSound');

                    gif.addEventListener('mouseenter', () => {
                        sound.currentTime = 0; // перемотка на начало
                        sound.play().catch(e => console.log('Автовоспроизведение заблокировано', e));
                    });

                    gif.addEventListener('mouseleave', () => {
                        sound.pause();
                        sound.currentTime = 0;
                    });

                </script>
            </div>
        </div>
        <div class="section right-section">
            <div class="buttons-right" style="padding-left:100px;">

                <!--        <div class="update-progress">
                    <div class="update-percent" id="updatePercent">50%</div>
                    <div class="update-bar">
                        <div class="update-bar-fill" id="updateBarFill" style="width: 50%;"></div>
                    </div>
                    <div class="update-next" id="updateNext">Следующее обновление: v1.4</div>
                </div>
-->
                <button class="button" style="padding: 6px;" onclick="location.href='/notifications'">
                    <!--0 -->
                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="icon icon-tabler icons-tabler-filled icon-tabler-bell"
                        style="vertical-align: middle;">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14.235 19c.865 0 1.322 1.024 .745 1.668a3.992 3.992 0 0 1 -2.98 1.332a3.992 3.992 0 0 1 -2.98 -1.332c-.552 -.616 -.158 -1.579 .634 -1.661l.11 -.006h4.471z" />
                        <path d="M12 2c1.358 0 2.506 .903 2.875 2.141l.046 .171l.008 .043a8.013 8.013 0 0 1 4.024 6.069l.028 .287l.019 .289v2.931l.021 .136a3 3 0 0 0 1.143 1.847l.167 .117l.162 .099c.86 .487 .56 1.766 -.377 1.864l-.116 .006h-16c-1.028 0 -1.387 -1.364 -.493 -1.87a3 3 0 0 0 1.472 -2.063l.021 -.143l.001 -2.97a8 8 0 0 1 3.821 -6.454l.248 -.146l.01 -.043a3.003 3.003 0 0 1 2.562 -2.29l.182 -.017l.176 -.004z" />
                    </svg>
                </button>
                <button class="button" onclick="location.href='/player/AshTheStar'">AshTheStar</button>                </button>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('burger').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelector('.buttons-left').classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.buttons-left') && !e.target.closest('#burger')) {
                document.querySelector('.buttons-left').classList.remove('active');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.buttons-left').classList.remove('active');
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('top-banner');
            const closeBtn = document.getElementById('close-banner');

            if (!banner || !closeBtn) return;

            // 👉 если уже закрывали — сразу скрываем
            if (localStorage.getItem('bannerClosed') === 'true') {
                banner.style.display = 'none';
                return;
            }

            closeBtn.addEventListener('click', function() {
                banner.style.display = 'none';
                localStorage.setItem('bannerClosed', 'true');
            });
});
    </script>

    <script>
        function updateUserActivity() {
            fetch('/swad/controllers/activity.php', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // console.log('Activity updated:', data.last_activity);
                    } else {
                        // console.error('Failed to update activity:', data.message);
                    }
                })
                .catch(error => {
                    // console.error('Error updating activity:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateUserActivity();
        });

        let activityTimeout;

        function resetActivityTimer() {
            clearTimeout(activityTimeout);
            activityTimeout = setTimeout(updateUserActivity, 30000); // 30 секунд
        }

        ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, {
                passive: true
            });
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateUserActivity();
            }
        });

        setInterval(updateUserActivity, 60000);
    </script>
    <script>
        function setUpdateProgress(percent, nextText) {
            document.getElementById("updatePercent").textContent = percent + "%";
            document.getElementById("updateBarFill").style.width = percent + "%";
            document.getElementById("updateNext").textContent = nextText;
        }

        setUpdateProgress(12, "Следующее обновление: v1.15.2");
    </script>
    <!-- subscribe to push 19.01.2025 (c) Alexander Livanov -->
    <script>
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            return Uint8Array.from(atob(base64), c => c.charCodeAt(0));
        }

        async function subscribeToPush() {
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array("BC-vFfqR7RP4t-JzKUMPwdI6SFSxpuGTF2hH79bF6mMIafYrEXN_EfTLVhIomEI6id0DDH0cLncM42QkCiOim7U")
                });

                console.log("Subscription object:", sub);

                const response = await fetch("/api/push/subscribe.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(sub)
                });

                const data = await response.json();
                console.log("Response from PHP:", data);
                alert("Подписка сохранена");
            } catch (err) {
                console.error("Push subscription failed:", err);
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageContainer = document.querySelector('.image');
            const logoImg = document.querySelector('.image img');
            if (!imageContainer || !logoImg) return;

            // Переменные для перетаскивания
            let isDragging = false;
            let startX, startY, originalX, originalY;

            // Эффект наклона (голографический)
            function handleTilt(e) {
                if (isDragging) return; // при перетаскивании наклон не нужен

                const rect = logoImg.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const nx = (x / rect.width) * 2 - 1;
                const ny = (y / rect.height) * 2 - 1;

                const maxAngle = 25;
                const rotateY = maxAngle * nx;
                const rotateX = -maxAngle * ny;

                // Применяем наклон к картинке (без translate, т.к. при перетаскивании он сброшен)
                logoImg.style.transform = `perspective(600px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;

                // Голографический градиент
                const bgX = (nx * 50) + 50;
                const bgY = (ny * 50) + 50;
                imageContainer.style.setProperty('--bg-x', `${bgX}%`);
                imageContainer.style.setProperty('--bg-y', `${bgY}%`);
            }

            // Сброс эффекта
            function resetTilt() {
                if (isDragging) return;
                logoImg.style.transform = 'perspective(600px) rotateX(0deg) rotateY(0deg) translateZ(0)';
                imageContainer.style.setProperty('--bg-x', '0%');
                imageContainer.style.setProperty('--bg-y', '0%');
            }

            // Отмена стандартного перетаскивания картинки браузером
            logoImg.addEventListener('dragstart', (e) => e.preventDefault());

            // Начало перетаскивания
            imageContainer.addEventListener('mousedown', (e) => {
                e.preventDefault(); // чтобы не выделялось
                isDragging = true;

                // Сохраняем начальную позицию мыши
                startX = e.clientX;
                startY = e.clientY;

                // Сохраняем текущее смещение (если уже было)
                const transform = logoImg.style.transform;
                // Простейший способ – сбросить наклон и запомнить translate
                // Но мы просто будем двигать относительно исходного положения,
                // поэтому сбрасываем transform и будем применять только translate
                logoImg.style.transition = 'none'; // отключаем анимацию при перетаскивании
                logoImg.style.transform = ''; // убираем наклон
                originalX = 0;
                originalY = 0;
            });

            // Перемещение
            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;

                const dx = e.clientX - startX;
                const dy = e.clientY - startY;

                // Перемещаем картинку
                logoImg.style.transform = `translate(${dx}px, ${dy}px)`;
            });

            // Завершение перетаскивания
            window.addEventListener('mouseup', (e) => {
                if (!isDragging) return;
                isDragging = false;

                // Возвращаем transition
                logoImg.style.transition = 'transform 0.3s ease-out';

                // Плавно возвращаем на место
                logoImg.style.transform = '';

                // После окончания анимации можно вернуть наклон (но пока убираем)
                setTimeout(() => {
                    logoImg.style.transition = 'transform 0.01s ease-out'; // возвращаем быструю реакцию
                }, 30); // время должно совпадать с transition
            });

            // Обработчики наклона
            imageContainer.addEventListener('mousemove', handleTilt);
            imageContainer.addEventListener('mouseleave', resetTilt);
        });

        (function() {
            const headerButtons = document.querySelectorAll('.header .button');
            if (!headerButtons.length) return;

            function resetTilt(btn) {
                btn.style.transform = '';
            }

            function handleMouseMove(e) {
                const btn = e.currentTarget;
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;


                const nx = (x / rect.width) * 2 - 1;
                const ny = (y / rect.height) * 2 - 1;

                const maxAngle = 15; // мягкий наклон
                const rotateY = maxAngle * nx;
                const rotateX = -maxAngle * ny;


                const translateY = -3; // в пикселях
                const scale = 1.04;


                btn.style.transform = `perspective(400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(${translateY}px) scale(${scale})`;
            }

            function handleMouseLeave(e) {
                resetTilt(e.currentTarget);
            }

            headerButtons.forEach(btn => {
                btn.addEventListener('mousemove', handleMouseMove);
                btn.addEventListener('mouseleave', handleMouseLeave);
            });
        })();


        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('hidden');

                setTimeout(() => preloader.remove(), 500);
            }
        });

        // ── Dropdown «Для разработчиков» ──
(function () {
    const dropdown = document.querySelector('.nav-dropdown');
    const trigger  = dropdown?.querySelector('.nav-dropdown__trigger');
    if (!trigger) return;

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen);
    });

    // Закрыть по клику вне
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    // Закрыть при resize > 900 вместе с burger-меню
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
})();
    </script>

</body>

</html>
    <main>
        <section class="games-header">
            <div class="container1">
                <h1>Каталог студий и пользователей</h1>
                <p>Найдите разработчиков, художников, геймдизайнеров и единомышленников</p>
            </div>
        </section>

        <div class="container">
            <!-- Панель фильтров и поиска -->
            <div class="filter-row">
                <div class="filters" role="tablist" aria-label="Тип контента">
                    <button class="filter-btn active" data-type="all" role="tab" aria-selected="true">Все</button>
                    <button class="filter-btn" data-type="studios" role="tab" aria-selected="false">Студии</button>
                    <button class="filter-btn" data-type="users" role="tab" aria-selected="false">Пользователи</button>
                </div>

                <div class="search-wrapper" id="searchWrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="searchInput" placeholder="Искать по имени, описанию, локации..." aria-label="Поиск">
                    <button class="search-clear" id="searchClear" aria-label="Очистить поиск">✕</button>
                </div>

                <div class="results-count" id="resultsCount"></div>
            </div>

            <!-- Сетка карточек -->
            <div class="grid-container" id="cardsGrid">
                <!-- Студии -->
                                    <article class="card studio-card" data-type="studios" data-name="dust game studio" data-desc="Лучшее от действительности игр." data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="Dust Game Studio" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">🏢 Студия</span>
                                <h2 class="card-title">Dust Game Studio</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <div class="card-desc-item">Лучшее от действительности игр.</div>
                        </div>
                        <div class="card-footer">
                                                            <a href="https://vk.com/dgscorp" class="website" target="_blank" rel="noopener" onclick="event.stopPropagation();">vk.com</a>
                                                        <time datetime="2026-03-18 05:27:07" class="card-date">📅 18.03.2026</time>
                        </div>
                        <!-- Ссылка для клика по всей карточке -->
                        <a href="/d/INTKE" class="card-link" aria-label="Перейти к студии Dust Game Studio" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                    <article class="card studio-card" data-type="studios" data-name="тестовая студия, проверка почты" data-desc="нам пизда нам пизда пизда пизда" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="тестовая студия, проверка почты" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">🏢 Студия</span>
                                <h2 class="card-title">тестовая студия, проверка почты</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <div class="card-desc-item">нам пизда нам пизда пизда пизда</div>
                        </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-12-11 22:55:39" class="card-date">📅 11.12.2025</time>
                        </div>
                        <!-- Ссылка для клика по всей карточке -->
                        <a href="/d/ZN6M" class="card-link" aria-label="Перейти к студии тестовая студия, проверка почты" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                    <article class="card studio-card" data-type="studios" data-name="the boring company" data-desc="hello world! hello world! hello world! hello world! hello world! hello world! hello world! hello world! hello world! hello world! " data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="The Boring Company" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">🏢 Студия</span>
                                <h2 class="card-title">The Boring Company</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <div class="card-desc-item">Hello world! Hello world! Hello world! Hello world! Hello world! Hello world! Hello world! Hello world! Hello world! ...</div>
                        </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-08-25 21:46:28" class="card-date">📅 25.08.2025</time>
                        </div>
                        <!-- Ссылка для клика по всей карточке -->
                        <a href="/d/" class="card-link" aria-label="Перейти к студии The Boring Company" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                    <article class="card studio-card" data-type="studios" data-name="crazyprojectslab" data-desc="&lt;p&gt;Наша студия — это место, где творчество и технологии сливаются воедино. В «crazyprojectslab russia» работают всего два человека, но их талант и профессионализм позволяют создавать по-настоящему впечатляющие продукты.&lt;/p&gt;&lt;p&gt;&lt;br&gt;&lt;/p&gt;&lt;p&gt;Мы специализируемся на разработке программного обеспечения, которое помогает решать сложные задачи и упрощает жизнь пользователей.&lt;/p&gt;&lt;p&gt;&lt;br&gt;&lt;/p&gt;&lt;p&gt;Мы уделяем особое внимание деталям и стремимся к совершенству в каждом проекте. А ещё кредо нашей студии - только революционные проекты двигают прогресс вперёд! Поэтому у нас лаборатория безумных проектов и наш слоган: &quot;just drive &#039;em crazy!&quot;&lt;/p&gt;" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="https://s3.regru.cloud/dustore.public.usercontent/studios/5/avatar/32502f56b8ddbd167bc9f731325d713f.png" alt="CrazyProjectsLab" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">🏢 Студия</span>
                                <h2 class="card-title">CrazyProjectsLab</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                            <div class="card-desc-item">&lt;p&gt;Наша студия — это место, где творчество и технологии сливаются воедино. В «CrazyProjectsLab Russia» работают всего...</div>
                        </div>
                        <div class="card-footer">
                                                            <a href="https://dustore.ru/d/CPL" class="website" target="_blank" rel="noopener" onclick="event.stopPropagation();">dustore.ru</a>
                                                        <time datetime="2025-07-01 22:15:26" class="card-date">📅 01.07.2025</time>
                        </div>
                        <!-- Ссылка для клика по всей карточке -->
                        <a href="/d/CPL" class="card-link" aria-label="Перейти к студии CrazyProjectsLab" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>

                <!-- Пользователи -->
                                                        <article class="card user-card" data-type="users" data-name="Эsh ashthestar" data-desc="" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="https://t.me/i/userpic/320/G3cwvNjadTucJGqBnZKk36RyCFPmYepLJlP3sjkml6A.jpg" alt="Эsh" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Эsh</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@AshTheStar</div>
                                                                                </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2026-02-28 06:50:21" class="card-date">📅 28.02.2026</time>
                        </div>
                        <a href="/player/AshTheStar" class="card-link" aria-label="Перейти к профилю Эsh" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                                        <article class="card user-card" data-type="users" data-name="Неопознанный Игрок superuser" data-desc="" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="Неопознанный Игрок" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Неопознанный Игрок</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@Superuser</div>
                                                                                </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-12-16 16:44:48" class="card-date">📅 16.12.2025</time>
                        </div>
                        <a href="/player/Superuser" class="card-link" aria-label="Перейти к профилю Неопознанный Игрок" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                                        <article class="card user-card" data-type="users" data-name="Неопознанный Игрок loginlogin" data-desc="" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="Неопознанный Игрок" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Неопознанный Игрок</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@LoginLogin</div>
                                                                                </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-12-12 22:48:16" class="card-date">📅 12.12.2025</time>
                        </div>
                        <a href="/player/LoginLogin" class="card-link" aria-label="Перейти к профилю Неопознанный Игрок" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                                        <article class="card user-card" data-type="users" data-name="Неопознанный Игрок testuser" data-desc="" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="/swad/static/img/logo.svg" alt="Неопознанный Игрок" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Неопознанный Игрок</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@TestUser</div>
                                                                                </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-11-24 22:07:36" class="card-date">📅 24.11.2025</time>
                        </div>
                        <a href="/player/TestUser" class="card-link" aria-label="Перейти к профилю Неопознанный Игрок" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                                        <article class="card user-card" data-type="users" data-name="Менеджер Дасти kissmyassbro123" data-desc="" data-location="">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="https://t.me/i/userpic/320/1atZUYqala2EJX1_lTTR8MAEHoZTZp142Ecm2ovkdptL9JiT40RAnkwdZqDShL1E.jpg" alt="Менеджер Дасти" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Менеджер Дасти</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@kissmyassbro123</div>
                                                                                </div>
                        <div class="card-footer">
                                                            <span></span>
                                                        <time datetime="2025-05-20 20:58:32" class="card-date">📅 20.05.2025</time>
                        </div>
                        <a href="/player/kissmyassbro123" class="card-link" aria-label="Перейти к профилю Менеджер Дасти" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                                                        <article class="card user-card" data-type="users" data-name="Александр thecreator" data-desc="Москва, Россия" data-location="Москва, Россия">
                        <div class="card-header">
                            <div class="avatar">
                                <img src="https://s3.regru.cloud/dustore.public.usercontent/avatars/1_1770244787.jpg" alt="Александр" loading="lazy">
                            </div>
                            <div>
                                <span class="type-badge">👤 Пользователь</span>
                                <h2 class="card-title">Александр</h2>
                            </div>
                        </div>
                        <div class="card-desc">
                                                            <div class="card-desc-item">@TheCreator</div>
                                                                                        <div class="card-desc-item">📍 Москва, Россия</div>
                                                    </div>
                        <div class="card-footer">
                                                            <a href="https://dustore.ru/player/TheCreator" class="website" target="_blank" rel="noopener" onclick="event.stopPropagation();">dustore.ru</a>
                                                        <time datetime="2025-02-15 13:00:00" class="card-date">📅 15.02.2025</time>
                        </div>
                        <a href="/player/TheCreator" class="card-link" aria-label="Перейти к профилю Александр" style="position:absolute; inset:0; opacity:0;"></a>
                    </article>
                            </div>
        </div>
    </main>


<head>
    <style>
        #notify-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 320px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notify {
            background: rgba(20, 20, 20, 0.95);
            padding: 14px 16px;
            border-radius: 12px;
            color: #fff;
            font-family: system-ui, sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            opacity: 0;
            transform: translateX(30px);
            animation: slide-in 0.25s forwards, fade-out 0.4s 4s forwards;
        }

        @keyframes slide-in {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fade-out {
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }
    </style>

</head>
<div class="footer">
    &copy; 2025 DUST STUDIO. Все права защищены.
    <br>
    <a href="https://vk.com/dgscorp">VKontakte (DGS)</a> .
    <a href="https://vk.com/crazyprojectslab">VKontakte (CPL)</a> .
    <a href="https://t.me/dgscorp">Telegram (DGS)</a> .
    <a href="https://t.me/dustore_official">Telegram (DUSTORE)</a> .
    <a href="/oferta.txt">Публичная оферта</a> .
    <a href="/developer-agreement">Соглашение с разработчиком</a>
    <p class="footer-p">DUSTORE (Dust Store) является собственностью Dust Studio и Crazy Projects Lab. Все торговые марки являются собственностью соответствующих владельцев. НДС включён во все цены, где он применим</p>
    <!-- <p class="footer-p">ИП Ливанов Александр Алексеевич <br>ИНН 771392840109 <br>ОГРНИП 326774600034839 <br>г. Москва</p> -->
    <!-- SemVer spec: status-global.design.tech#patch -->
    <!-- <p class="footer-p">Версия платформы: beta-1.11.24#22</p> -->
            <p class="footer-p">Версия: <strong>no version</strong>
            | <a href="https://github.com/AlexanderLivanov/dustore/commit/b36af19421e4018994712d96a6bdc697c85593ed" target="_blank">b36af19</a>
            | 06.05.2026 19:32</p>
    </div>
<div id="notify-container"></div>
<style>
    #notify-container {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 320px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .notify {
        background: rgba(20, 20, 20, 0.95);
        padding: 14px 16px;
        border-radius: 12px;
        color: #fff;
        font-family: system-ui, sans-serif;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        opacity: 0;
        transform: translateX(30px);
        animation: slide-in 0.25s forwards, fade-out 0.4s 4s forwards;
    }

    @keyframes slide-in {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fade-out {
        to {
            opacity: 0;
            transform: translateX(30px);
        }
    }
</style>
<!-- <script>
    document.addEventListener("DOMContentLoaded", () => {
        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }
    });
</script>
<script>
    const source = new EventSource("/swad/controllers/notification_stream.php");

    source.onmessage = (event) => {
        const data = JSON.parse(event.data);
        notify(data.title, data.message);
    };
</script> -->
    <script>
        (function() {
            const cards = Array.from(document.querySelectorAll('.grid-container .card'));
            const filterButtons = document.querySelectorAll('.filters .filter-btn');
            const searchInput = document.getElementById('searchInput');
            const searchClear = document.getElementById('searchClear');
            const searchWrapper = document.getElementById('searchWrapper');
            const resultsCount = document.getElementById('resultsCount');
            const gridContainer = document.getElementById('cardsGrid');

            let currentFilter = 'all';
            let searchTerm = '';

            // Debounce для поиска
            let searchTimeout;
            const DEBOUNCE_DELAY = 250;

            // Функция фильтрации
            function filterCards() {
                const term = searchTerm.toLowerCase().trim();
                let visibleCount = 0;

                cards.forEach(card => {
                    const type = card.dataset.type;
                    const name = (card.dataset.name || '').toLowerCase();
                    const desc = (card.dataset.desc || '').toLowerCase();
                    const location = (card.dataset.location || '').toLowerCase();

                    const matchesType = currentFilter === 'all' || type === currentFilter;
                    const matchesSearch = term === '' || name.includes(term) || desc.includes(term) || location.includes(term);

                    const visible = matchesType && matchesSearch;
                    card.style.display = visible ? 'flex' : 'none';
                    if (visible) visibleCount++;
                });

                // Обновление счётчика результатов
                resultsCount.textContent = visibleCount ? `Найдено: ${visibleCount}` : '';

                // Показываем пустое состояние, если нет карточек
                let emptyEl = document.getElementById('empty-state');
                if (visibleCount === 0) {
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.id = 'empty-state';
                        emptyEl.className = 'empty-state';
                        emptyEl.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 6v6l4 2" />
                            </svg>
                            <h3>Ничего не найдено</h3>
                            <p>Попробуйте изменить параметры поиска</p>
                        `;
                        gridContainer.appendChild(emptyEl);
                    }
                } else {
                    if (emptyEl) emptyEl.remove();
                }

                // Обновление aria-selected для кнопок фильтров
                filterButtons.forEach(btn => {
                    const isActive = btn.dataset.type === currentFilter;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', isActive);
                });
            }

            // Обработчики фильтров
            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    currentFilter = btn.dataset.type;
                    filterCards();
                });
            });

            // Обработчик поиска с debounce
            searchInput.addEventListener('input', () => {
                searchTerm = searchInput.value;
                searchWrapper.classList.toggle('filled', searchTerm.length > 0);

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterCards();
                }, DEBOUNCE_DELAY);
            });

            // Очистка поиска
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchTerm = '';
                searchWrapper.classList.remove('filled');
                filterCards();
                searchInput.focus();
            });

            // Закрытие кликом вне (необязательно)
            document.addEventListener('click', (e) => {
                if (!searchWrapper.contains(e.target)) {
                    // можно убрать, если не нужно
                }
            });

            // Предотвращаем всплытие клика по ссылкам внутри карточки
            document.querySelectorAll('.card .website, .card .card-link').forEach(el => {
                el.addEventListener('click', (e) => e.stopPropagation());
            });

            // Начальная фильтрация (устанавливаем active классы и счётчик)
            filterCards();

            // Анимация появления с задержкой (альтернатива CSS-анимации)
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 30}ms`;
            });

            // Доступность: поддержка клавиши Enter на карточках
            cards.forEach(card => {
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const link = card.querySelector('.card-link');
                        if (link) link.click();
                    }
                });
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'article');
            });
        })();
    </script>
</body>
</html>