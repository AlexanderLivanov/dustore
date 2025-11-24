<?php
require_once('constants.php');
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');

$curr_user = new User();
$db = new Database();

$curr_user->checkAuth();

if (empty($_COOKIE['temp_id'])) {
    setcookie("temp_id", rand(-10 ** 5, -10 ** 2));
}

print_r($_COOKIE);
?>

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
    <link rel="stylesheet" href="/swad/css/header.css">
    <link rel="shortcut icon" href="../img/logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="/swad/css/style.css">
    <link rel="stylesheet" href="/swad/css/notifications.css">
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
</head>

<body>
    <!-- <div class="top-banner" id="top-banner">
        <div class="banner-content">
            <div class="banner-text">
                ⚠️ Важное уведомление! С 1 июля по 3 сентября Платформа работает в тестовом режиме. Возможны перебои в работе сервиса.
            </div>
            <button class="close-banner" id="close-banner">&times;</button>
        </div>
    </div> -->
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
                <button class="button" onclick="location.href='/magazine'">Журнал</button>
                <!-- <button class="button" onclick="location.href='https:\/\/media.dustore.ru'">Медиа</button> -->
                <button class="button" onclick="location.href='/about'">О платформе</button>
                <button class="button disabled-btn tooltip">Финансы<span class="tooltiptext">Финансов пока нет</span></button>
            </div>
        </div>
        <div class="section center-section">
            <div class="image">
                <!-- <img src="/swad/static/img/logo_.png" alt="" onclick="location.href='/'"> -->
                <img src="/swad/static/img/logo_new.png" alt="" onclick="location.href='/'">
            </div>
        </div>
        <div class="section right-section">
            <div class="buttons-right">
                <!-- <button class="button" onclick="location.href='/wallet'"><?= "0 ₽" ?></button> -->
                <?php
                $curr_user->checkAuth();
                if (empty($_SESSION['USERDATA']['telegram_id'])) {
                    echo ("<button class=\"button\" onclick=\"location.href='/login'\">");
                    echo ("Войти в аккаунт");
                    echo ("</button>");
                } else {
                    echo ("<button class=\"button\" onclick=\"location.href='/me'\">");
                    echo ($_SESSION['USERDATA']['username']);
                    echo ("</button>");
                }
                ?>
                </button>
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

            if (localStorage.getItem('bannerClosed') === 'true') {
                banner.style.display = 'none';
                return;
            }

            closeBtn.addEventListener('click', function() {
                banner.style.animation = 'slideUp 0.5s forwards';

                setTimeout(() => {
                    banner.style.display = 'none';
                }, 500);

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
</body>

</html>