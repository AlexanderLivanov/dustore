<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../controllers/user.php');


$curr_user = new User();
$db = new Database();

$curr_user->checkAuth();

if (empty($_COOKIE['temp_id'])) {
    setcookie("temp_id", rand(-10 ** 5, -10 ** 2));
}

// Определяем сегодняшнюю дату
$today = date("Y-m-d");

$conn = $db->connect();

// Проверяем, нет ли уже записи на сегодня
$exists = $conn->prepare("SELECT id FROM daily_stats WHERE date = ?");
$exists->execute([$today]);
if ($exists->rowCount() <= 0) {
    /* ---- Получение TOTAL ---- */

    $users_total = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $studios_total = $conn->query("SELECT COUNT(*) FROM studios")->fetchColumn();
    $games_total = $conn->query("SELECT COUNT(*) FROM games")->fetchColumn();
    $published_total = $conn->query("SELECT COUNT(*) FROM games WHERE status = 'published'")->fetchColumn();

    /* ---- Получение NEW за сутки ---- */

    $users_new = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(added) = '$today'")->fetchColumn();
    $studios_new = $conn->query("SELECT COUNT(*) FROM studios WHERE DATE(created_at) = '$today'")->fetchColumn();
    $games_new = $conn->query("SELECT COUNT(*) FROM games WHERE DATE(created_at) = '$today'")->fetchColumn();
    $published_new = $conn->query("SELECT COUNT(*) FROM games WHERE status='published' AND DATE(created_at)='$today'")->fetchColumn();

    /* ---- Добавляем ---- */

    $insert = $conn->prepare("
    INSERT INTO daily_stats (
        date,
        users_total, users_new,
        studios_total, studios_new,
        games_total, games_new,
        published_total, published_new
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

    $insert->execute([
        $today,
        $users_total,
        $users_new,
        $studios_total,
        $studios_new,
        $games_total,
        $games_new,
        $published_total,
        $published_new
    ]);
}

$online_count = (int)$conn->query("
    SELECT COUNT(*) FROM users
    WHERE last_activity >= NOW() - INTERVAL 5 MINUTE
")->fetchColumn();

// Округляем до часа
$hour = date('Y-m-d H:00:00');

$stmt = $conn->prepare("
    INSERT INTO users_online_history (ts, online_count)
    VALUES (:ts, :count)
    ON DUPLICATE KEY UPDATE online_count = :count
");

$stmt->execute([
    ':ts' => $hour,
    ':count' => $online_count
]);
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
    </style>
</head>

<body>
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
                    applicationServerKey: await urlBase64ToUint8Array("<?= VAPID_PUBLIC_KEY ?>")
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
    <div class="top-banner hidden" id="top-banner">
        <div class="banner-content">
            <div class="banner-text">
                Следите за новостями в нашем <a style="color: lightgreen;" target="_blank" href="https://t.me/dustore_official">Telegram канале<svg style="vertical-align: middle;"
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
                <!--<button class="button disabled-btn tooltip">Ассеты<span class="tooltiptext">Скоро</span></button>-->
                <button class="button" onclick="location.href='/about'">О нас</button>
                <button class="button" onclick="location.href='/search'">Поиск</button>
                <button class="button disabled-btn tooltip">L4T<span class="tooltiptext">Скоро</span></button>
            </div>
        </div>
        <div class="section center-section">
            <div class="image">
                <!-- <img src="/swad/static/img/logo_.png" alt="" onclick="location.href='/'"> -->
                <img src="/swad/static/img/logo_new.png" alt="" onclick="location.href='/'">
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
                <?php
                if (!empty($_SESSION['USERDATA'])) {
                    $pdo = $db->connect();
                    // print_r($_SESSION['USERDATA']);
                    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND status = 'unread'");
                    $stmt->execute([$_SESSION['USERDATA']['id']]);
                    $unread_notif_count = sizeof($stmt->fetchAll(PDO::FETCH_ASSOC));
                } else {
                    $unread_notif_count = 0;
                }

                if ($unread_notif_count > 0) {
                    $unread_notif_count = "+" . $unread_notif_count;
                }
                ?>

        <!--        <div class="update-progress">
                    <div class="update-percent" id="updatePercent">50%</div>
                    <div class="update-bar">
                        <div class="update-bar-fill" id="updateBarFill" style="width: 50%;"></div>
                    </div>
                    <div class="update-next" id="updateNext">Следующее обновление: v1.4</div>
                </div>
-->
                <button class="button" style="padding: 6px;" onclick="location.href='/notifications'">
                    <!--<?= $unread_notif_count ?> -->
                    <svg xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="icon icon-tabler icons-tabler-filled icon-tabler-bell"
                    style="vertical-align: middle;">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M14.235 19c.865 0 1.322 1.024 .745 1.668a3.992 3.992 0 0 1 -2.98 1.332a3.992 3.992 0 0 1 -2.98 -1.332c-.552 -.616 -.158 -1.579 .634 -1.661l.11 -.006h4.471z" />
                    <path d="M12 2c1.358 0 2.506 .903 2.875 2.141l.046 .171l.008 .043a8.013 8.013 0 0 1 4.024 6.069l.028 .287l.019 .289v2.931l.021 .136a3 3 0 0 0 1.143 1.847l.167 .117l.162 .099c.86 .487 .56 1.766 -.377 1.864l-.116 .006h-16c-1.028 0 -1.387 -1.364 -.493 -1.87a3 3 0 0 0 1.472 -2.063l.021 -.143l.001 -2.97a8 8 0 0 1 3.821 -6.454l.248 -.146l.01 -.043a3.003 3.003 0 0 1 2.562 -2.29l.182 -.017l.176 -.004z" />
                </svg>
            </button>
                <?php
                $curr_user->checkAuth();
                if (empty($_SESSION['USERDATA']['id'])) {
                    echo ("<button class=\"button\" onclick=\"location.href='/login'\">");
                    echo ("Войти в аккаунт");
                    echo ("</button>");
                } else {
                    echo ("<button class=\"button\" onclick=\"location.href='/player/" . $_SESSION['USERDATA']['username'] . "'\">");
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
                    applicationServerKey: urlBase64ToUint8Array("<?= VAPID_PUBLIC_KEY ?>")
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
                console.log("Response from PHP:", data); // ✅ ответ сервера
                alert("Подписка сохранена");
            } catch (err) {
                console.error("Push subscription failed:", err);
            }
        }
    </script>

<script>
  (function() {
)
    const gradientColors = ['#14041d', '#c32178', '#ffaa00']; // ЗАМЕНИ НА СВОИ

    const hero = document.querySelector('.hero');
    const header = document.querySelector('.header');

    if (!hero || !header) return;

    header.style.transition = 'background-color 0.01s linear';

    function interpolateColor(color1, color2, factor) {

      const r1 = parseInt(color1.substring(1,3), 16);
      const g1 = parseInt(color1.substring(3,5), 16);
      const b1 = parseInt(color1.substring(5,7), 16);
      const r2 = parseInt(color2.substring(1,3), 16);
      const g2 = parseInt(color2.substring(3,5), 16);
      const b2 = parseInt(color2.substring(5,7), 16);

      const r = Math.round(r1 + factor * (r2 - r1));
      const g = Math.round(g1 + factor * (g2 - g1));
      const b = Math.round(b1 + factor * (b2 - b1));

      return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }

    function updateHeaderColor() {
      const scrollY = window.scrollY;
      const heroTop = hero.offsetTop;
      const heroHeight = hero.offsetHeight;
      const heroBottom = heroTop + heroHeight;

      let factor;
      if (scrollY < heroTop) {
        factor = 0;                     // выше hero
      } else if (scrollY > heroBottom) {
        factor = 1;                     // ниже hero
      } else {
        factor = (scrollY - heroTop) / heroHeight; // внутри hero
      }

      if (gradientColors.length === 2) {

        const newColor = interpolateColor(gradientColors[0], gradientColors[1], factor);
        header.style.backgroundColor = newColor;
      } else if (gradientColors.length > 2) {

        const totalSegments = gradientColors.length - 1;
        const exactIndex = factor * totalSegments;
        const leftIndex = Math.floor(exactIndex);
        const rightIndex = Math.min(leftIndex + 1, totalSegments);
        const segmentFactor = exactIndex - leftIndex;

        const newColor = interpolateColor(
          gradientColors[leftIndex],
          gradientColors[rightIndex],
          segmentFactor
        );
        header.style.backgroundColor = newColor;
      }
    }

    window.addEventListener('scroll', updateHeaderColor);
    window.addEventListener('resize', updateHeaderColor); // на случай изменения высоты hero

    updateHeaderColor();
  })();
</script>

<script>
  (function() {

    document.addEventListener('DOMContentLoaded', function() {
      console.log('Скрипт глобального изменения цвета запущен');

      const colorTop = '#2e0f32';    // цвет вверху страницы
      const colorBottom = '#65154c'; // цвет внизу страницы

      const header = document.querySelector('.header');
      if (!header) {
        console.warn('Header не найден');
        return;
      }

      header.style.transition = 'background-color 0.2s ease';

      function interpolateColor(color1, color2, factor) {

        const r1 = parseInt(color1.substring(1,3), 16);
        const g1 = parseInt(color1.substring(3,5), 16);
        const b1 = parseInt(color1.substring(5,7), 16);
        const r2 = parseInt(color2.substring(1,3), 16);
        const g2 = parseInt(color2.substring(3,5), 16);
        const b2 = parseInt(color2.substring(5,7), 16);

        const r = Math.round(r1 + factor * (r2 - r1));
        const g = Math.round(g1 + factor * (g2 - g1));
        const b = Math.round(b1 + factor * (b2 - b1));

        return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
      }

      function updateHeaderColor() {
        const scrollY = window.scrollY;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

        let factor;
        if (maxScroll <= 0) {

          factor = 0;
        } else {
          factor = Math.min(1, Math.max(0, scrollY / maxScroll));
        }

        const newColor = interpolateColor(colorTop, colorBottom, factor);
        header.style.backgroundColor = newColor;
      }

      window.addEventListener('scroll', updateHeaderColor);
      window.addEventListener('resize', updateHeaderColor);

      updateHeaderColor();
    });
  })();
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
            }, 50); // время должно совпадать с transition
        });

        // Обработчики наклона
        imageContainer.addEventListener('mousemove', handleTilt);
        imageContainer.addEventListener('mouseleave', resetTilt);
    });
</script>
</body>

</html>