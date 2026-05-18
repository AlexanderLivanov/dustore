<?php
function asset_url(string $path): string {
    $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
    $v   = file_exists($abs) ? substr(md5_file($abs), 0, 8) : time();
    return $path . '?v=' . $v;
}
?>
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
    <link rel="stylesheet" href="<?= asset_url('/swad/css/header.css') ?>">
    <link rel="shortcut icon" href="../img/logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="<?= asset_url('/swad/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/swad/css/notifications.css') ?>">
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
    <div class="center-floating-block">
        <p style="color: #c4a93a; font-weight: 100; font-size: large; font-family: 'PixelizerBold'; margin-top: -4px;"></p>
    </div>
    <div class="header-wrapper">
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

                        <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
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
                        <?php else: ?>
                            <!-- Не залогинен — ведём на логин с редиректом -->
                            <li>
                                <a class="nav-dropdown__item nav-dropdown__item--accent"
                                href="/login?backUrl=/devs/" role="menuitem"
                                style="color:rgba(255,91,168,.7);font-weight:600;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        style="vertical-align:middle;margin-right:4px;">
                                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                                        <path d="M8 21h8M12 17v4"/>
                                    </svg>
                                    Войти в консоль
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="section center-section">
            <div class="image">
                <!-- <img src="/swad/static/img/logo_.png" alt="" onclick="location.href='/'"> -->
                <img src="/swad/static/img/LogoV3 - Appolo_mini.png" alt="" onclick="location.href='/'">
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
                <button class="button" style="padding: 6px;" id="modeBtn">
                <svg xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="icon icon-tabler icons-tabler-filled icon-tabler-paw">
                    <path stroke="none"
                    d="M0 0h24v24H0z"
                    fill="none" />
                    <path d="M12 10c-1.32 0 -1.983 .421 -2.931 1.924l-.244 .398l-.395 .688a50.89 50.89 0 0 0 -.141 .254c-.24 .434 -.571 .753 -1.139 1.142l-.55 .365c-.94 .627 -1.432 1.118 -1.707 1.955c-.124 .338 -.196 .853 -.193 1.28c0 1.687 1.198 2.994 2.8 2.994l.242 -.006c.119 -.006 .234 -.017 .354 -.034l.248 -.043l.132 -.028l.291 -.073l.162 -.045l.57 -.17l.763 -.243l.455 -.136c.53 -.15 .94 -.222 1.283 -.222c.344 0 .753 .073 1.283 .222l.455 .136l.764 .242l.569 .171l.312 .084c.097 .024 .187 .045 .273 .062l.248 .043c.12 .017 .235 .028 .354 .034l.242 .006c1.602 0 2.8 -1.307 2.8 -3c0 -.427 -.073 -.939 -.207 -1.306c-.236 -.724 -.677 -1.223 -1.48 -1.83l-.257 -.19l-.528 -.38c-.642 -.47 -1.003 -.826 -1.253 -1.278l-.27 -.485l-.252 -.432c-1.011 -1.696 -1.618 -2.099 -3.053 -2.099z" /><path d="M19.78 7h-.03c-1.219 .02 -2.35 1.066 -2.908 2.504c-.69 1.775 -.348 3.72 1.075 4.333c.256 .109 .527 .163 .801 .163c1.231 0 2.38 -1.053 2.943 -2.504c.686 -1.774 .34 -3.72 -1.076 -4.332a2.05 2.05 0 0 0 -.804 -.164z" /><path d="M9.025 3c-.112 0 -.185 .002 -.27 .015l-.093 .016c-1.532 .206 -2.397 1.989 -2.108 3.855c.272 1.725 1.462 3.114 2.92 3.114l.187 -.005a1.26 1.26 0 0 0 .084 -.01l.092 -.016c1.533 -.206 2.397 -1.989 2.108 -3.855c-.27 -1.727 -1.46 -3.114 -2.92 -3.114z" /><path d="M14.972 3c-1.459 0 -2.647 1.388 -2.916 3.113c-.29 1.867 .574 3.65 2.174 3.867c.103 .013 .2 .02 .296 .02c1.39 0 2.543 -1.265 2.877 -2.883l.041 -.23c.29 -1.867 -.574 -3.65 -2.174 -3.867a2.154 2.154 0 0 0 -.298 -.02z" /><path d="M4.217 7c-.274 0 -.544 .054 -.797 .161c-1.426 .615 -1.767 2.562 -1.078 4.335c.563 1.451 1.71 2.504 2.941 2.504c.274 0 .544 -.054 .797 -.161c1.426 -.615 1.767 -2.562 1.078 -4.335c-.563 -1.451 -1.71 -2.504 -2.941 -2.504z" />
                </svg>
                </button>
                <button class="button" style="padding: 6px;" onclick="location.href='/notifications'">
                    <!--<?= $unread_notif_count ?> -->
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
                console.log("Response from PHP:", data);
                alert("Подписка сохранена");
            } catch (err) {
                console.error("Push subscription failed:", err);
            }
        }
    </script>

    <script>
        const header = document.querySelector('.header');
        const floatingBlock = document.querySelector('.center-floating-block');

        header.addEventListener('mouseenter', () => {
          floatingBlock.classList.add('header-hovered');
        });
        header.addEventListener('mouseleave', () => {
          floatingBlock.classList.remove('header-hovered');
        });
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

    <script>
    // Shadow только при скролле
    (function() {
        const header  = document.querySelector('.header');
        const floater = document.querySelector('.center-floating-block');

        function onScroll() {
            const scrolled = window.scrollY > 4;
            header?.classList.toggle('scrolled', scrolled);
            floater?.classList.toggle('scrolled', scrolled);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); // сразу при загрузке
    })();
    </script>


<!-- Модалка помощника Дасти -->
<div id="dusty-helper-modal" class="dust-modal hidden">
  <div class="dust-layout">
    <button class="dust-helper-close">&times;</button>

    <!-- Левая часть: котик -->
    <div class="dust-layout__cat">
      <img id="dusty-cat" src="/swad/static/img/dastyframe1.png" alt="Дасти">
    </div>

    <!-- Правая часть: диалог + нижняя панель -->
    <div class="dust-layout__right">
      <div class="dust-layout__dialogue">
        <div id="dusty-text"></div>
      </div>
      <div class="dust-layout__actions">
        <button id="dusty-continue-btn" class="dust-close hidden">Продолжить</button>
        <!-- Здесь в будущем появятся кнопки вопросов -->
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    const modal = document.getElementById('dusty-helper-modal');
    const closeBtn = modal.querySelector('.dust-helper-close');
    const continueBtn = document.getElementById('dusty-continue-btn');
    const openBtn = document.getElementById('modeBtn');
    const cat = document.getElementById('dusty-cat');
    const textElement = document.getElementById('dusty-text');

    // Звук печати (тот же, что в приветствии)
    const typeSound = new Audio("/swad/static/sounds/dusty_fx.mp3");
    typeSound.volume = 0.3;
    const SOUND_EVERY_N_CHARS = 5; // понижаем частоту

    // Кадры анимации
    const catFrames = [
        "/swad/static/img/dastyframe1.png",
        "/swad/static/img/dastyframe2.png",
        "/swad/static/img/dastyframe3.png"
    ];

    // Диалоги (пока демо, потом заменишь на нужные)
    const dialogues = [
        "Привет! Я Дасти, твой помощник на Dustore.",
        "Хочешь найти игру? Или узнать, как стать разработчиком?",
        "Просто спроси — я расскажу!"
    ];

    // После каких реплик кот подмигнёт (индексы)
    const winkAfter = [0, 2];

    let frameIndex = 0;
    let animationInterval = null;
    let dialogueIndex = 0;
    let charIndex = 0;
    let soundCounter = 0;

    // Анимация «говорения» кота
    function startCatTalk() {
        if (animationInterval) return;
        frameIndex = 0;
        cat.src = catFrames[0];
        animationInterval = setInterval(() => {
            frameIndex = (frameIndex + 1) % catFrames.length;
            cat.src = catFrames[frameIndex];
        }, 150);
    }

    function stopCatTalk() {
        if (animationInterval) {
            clearInterval(animationInterval);
            animationInterval = null;
        }
        cat.src = "/swad/static/img/dastyframe1.png"; // обычный кадр
    }

    // Подмигивание
    function winkAnimation(callback) {
        cat.src = "/swad/static/img/dastyframe_half.png";
        setTimeout(() => {
            cat.src = "/swad/static/img/dastyframe_full.png";
            setTimeout(() => {
                cat.src = "/swad/static/img/dastyframe1.png";
                if (callback) callback();
            }, 900);
        }, 700);
    }

    // Побуквенная печать
    function typeDialogue() {
        const currentText = dialogues[dialogueIndex];
        if (charIndex < currentText.length) {
            textElement.textContent += currentText.charAt(charIndex);
            soundCounter++;
            if (soundCounter % SOUND_EVERY_N_CHARS === 0) {
                typeSound.currentTime = 0;
                typeSound.play().catch(() => {});
            }
            charIndex++;
            setTimeout(typeDialogue, 10);
        } else {
            stopCatTalk();
            // Если нужно подмигивание — запускаем, затем показываем кнопку
            if (winkAfter.includes(dialogueIndex)) {
                winkAnimation(() => {
                    continueBtn.classList.remove('hidden');
                });
            } else {
                continueBtn.classList.remove('hidden');
            }
        }
    }

    // Открыть модалку и начать диалог
    function openModal() {
        modal.classList.remove('hidden');
        dialogueIndex = 0;
        charIndex = 0;
        soundCounter = 0;
        textElement.textContent = '';
        continueBtn.classList.add('hidden');
        startCatTalk();
        typeDialogue();
    }

    // Закрыть модалку и всё остановить
    function closeModal() {
        modal.classList.add('hidden');
        stopCatTalk();
    }

    // Клик по лапке
    openBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        openModal();
    });

    // Крестик
    closeBtn.addEventListener('click', closeModal);

    // Клик по фону (опционально)
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Кнопка «Продолжить»
    continueBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dialogueIndex++;
        if (dialogueIndex >= dialogues.length) {
            // Все фразы показаны — закрываем
            closeModal();
            return;
        }
        // Следующая фраза
        textElement.textContent = '';
        charIndex = 0;
        soundCounter = 0;
        continueBtn.classList.add('hidden');
        startCatTalk();
        typeDialogue();
    });

})();
</script>

</body>

</html>

