<?php
function asset_url(string $path): string
{
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
        .context-menu {
            position: fixed;
            display: none;
            min-width: 320px;

            background: rgba(20, 4, 29, 0.97);
            backdrop-filter: blur(20px);

            border: 1px solid rgba(195, 33, 120, .25);
            border-radius: 14px;

            padding: 8px;

            box-shadow:
                0 15px 40px rgba(0, 0, 0, .5),
                0 0 25px rgba(195, 33, 120, .15);

            z-index: 999999;
            animation: menuOpen .12s ease;
        }

        .context-menu-item {
            width: 100%;

            display: flex;
            align-items: center;

            gap: 12px;

            background: transparent;
            border: none;

            color: #fff;

            padding: 11px 12px;
            border-radius: 10px;

            cursor: pointer;
            transition: .15s;

            font-size: 14px;
        }

        .context-menu-item:hover {
            background: rgba(195, 33, 120, .18);
        }

        .context-menu-icon {
            width: 20px;
            text-align: center;
            opacity: .85;
        }

        .shortcut {
            margin-left: auto;
            opacity: .55;
            font-size: 12px;
        }

        .context-menu-divider {
            height: 1px;
            margin: 6px 4px;

            background:
                linear-gradient(90deg,
                    transparent,
                    rgba(195, 33, 120, .35),
                    transparent);
        }

        @keyframes menuOpen {
            from {
                opacity: 0;
                transform: translateY(-5px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
</head>

<body>
    <div id="custom-menu" class="context-menu">
        <button class="context-menu-item" data-action="back">
            <span class="context-menu-icon">←</span>
            <span>Назад</span>
            <span class="shortcut">Alt+←</span>
        </button>

        <button class="context-menu-item" data-action="forward">
            <span class="context-menu-icon">→</span>
            <span>Вперёд</span>
            <span class="shortcut">Alt+→</span>
        </button>

        <button class="context-menu-item" data-action="reload">
            <span class="context-menu-icon">↻</span>
            <span>Перезагрузить</span>
            <span class="shortcut">Ctrl+R</span>
        </button>

        <div class="context-menu-divider"></div>

        <button class="context-menu-item" data-action="print">
            <span class="context-menu-icon">🖨</span>
            <span>Печать...</span>
            <span class="shortcut">Ctrl+P</span>
        </button>

        <button class="context-menu-item" data-action="save">
            <span class="context-menu-icon">💾</span>
            <span>Сохранить страницу как...</span>
            <span class="shortcut">Ctrl+S</span>
        </button>

        <button class="context-menu-item" data-action="translate">
            <span class="context-menu-icon">🌐</span>
            <span>Перевести на русский</span>
        </button>

        <button class="context-menu-item" data-action="find">
            <span class="context-menu-icon">🔍</span>
            <span>Поиск по странице</span>
            <span class="shortcut">Ctrl+F</span>
        </button>

        <button class="context-menu-item" data-action="viewsource">
            <span class="context-menu-icon">&lt;/&gt;</span>
            <span>Просмотр кода страницы</span>
            <span class="shortcut">Ctrl+U</span>
        </button>

        <div class="context-menu-divider"></div>

        <button class="context-menu-item" data-action="inspect">
            <span class="context-menu-icon">⚙</span>
            <span>Посмотреть код</span>
            <span class="shortcut">F12</span>
        </button>
    </div>

    <script>
        const menu = document.getElementById('custom-menu');

        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();

            menu.style.display = 'block';

            const rect = menu.getBoundingClientRect();

            let x = e.clientX;
            let y = e.clientY;

            if (x + rect.width > window.innerWidth) {
                x = window.innerWidth - rect.width - 10;
            }

            if (y + rect.height > window.innerHeight) {
                y = window.innerHeight - rect.height - 10;
            }

            menu.style.left = `${x}px`;
            menu.style.top = `${y}px`;
        });

        document.addEventListener('click', () => {
            menu.style.display = 'none';
        });

        menu.addEventListener('click', (e) => {
            const button = e.target.closest('.context-menu-item');
            if (!button) return;

            const action = button.dataset.action;

            switch (action) {
                case 'back':
                    history.back();
                    break;

                case 'forward':
                    history.forward();
                    break;

                case 'reload':
                    location.reload();
                    break;

                case 'print':
                    window.print();
                    break;

                case 'save':
                    alert('Браузеры не позволяют программно открыть "Сохранить как"');
                    break;

                case 'find':
                    alert('Используйте Ctrl+F');
                    break;

                case 'viewsource':
                    window.open('view-source:' + location.href);
                    break;

                case 'inspect':
                    alert('Невозможно открыть DevTools через JS');
                    break;
            }

            menu.style.display = 'none';
        });
    </script>
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
                    <!-- Dropdown «Платформа» -->
                    <div class="nav-dropdown">
                        <button class="button nav-dropdown__trigger" aria-haspopup="true" aria-expanded="false">
                            Платформа
                            <svg class="nav-dropdown__arrow" width="10" height="6" viewBox="0 0 10 6" fill="none">
                                <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="nav-dropdown__menu" role="menu">
                            <li><a class="nav-dropdown__item" href='/jams' role="menuitem">Спринты</a></li>
                            <li><a class="nav-dropdown__item" href="/about" role="menuitem">О нас</a></li>
                            <li><a class="nav-dropdown__item" href="#" role="menuitem">Битый Пиксель</a></li>
                            <li><a class="nav-dropdown__item" href="#" role="menuitem">Медиа</a></li>
                        </ul>
                    </div>
                    

                    <!-- Dropdown «Для разработчиков» -->
                    <div class="nav-dropdown">
                        <button class="button nav-dropdown__trigger" aria-haspopup="true" aria-expanded="false">
                            Devs
                            <svg class="nav-dropdown__arrow" width="10" height="6" viewBox="0 0 10 6" fill="none">
                                <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <ul class="nav-dropdown__menu" role="menu">
                            <li><a class="nav-dropdown__item" href="/assetstore" role="menuitem">Ассеты</a></li>
                            <li><a class="nav-dropdown__item" href="/l4t" role="menuitem">L4T</a></li>
                            <li><a class="nav-dropdown__item" href="#" role="menuitem">GDDB</a></li>
                            <li role="separator" style="height:1px;background:rgba(255,255,255,.08);margin:4px 8px;"></li>

                            <?php if (!empty($_SESSION['USERDATA']['id'])): ?>
                                <li>
                                    <a class="nav-dropdown__item nav-dropdown__item--accent"
                                        href="/devs/" role="menuitem"
                                        style="color:#ff5ba8;font-weight:600;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            style="vertical-align:middle;margin-right:4px;">
                                            <rect x="2" y="3" width="20" height="14" rx="2" />
                                            <path d="M8 21h8M12 17v4" />
                                        </svg>
                                        Консоль разработчика
                                    </a>
                                </li>
                            <?php else: ?>
                                <li>
                                    <a class="nav-dropdown__item nav-dropdown__item--accent"
                                        href="/login?backUrl=/devs/" role="menuitem"
                                        style="color:rgba(255,91,168,.7);font-weight:600;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            style="vertical-align:middle;margin-right:4px;">
                                            <rect x="2" y="3" width="20" height="14" rx="2" />
                                            <path d="M8 21h8M12 17v4" />
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
                    <img src="/swad/static/img/LogoV3-Appolo_mini.png" alt="" onclick="location.href='/'">
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
                    <div class="update-next" id="updateNext">Следующее обновление: v1.4</div>F
                </div>
-->


                    <!-- Кнопка переключения темы (Appollo / Moonlight) -->
                    <button class="button" style="padding: 6px;" id="themeToggleBtn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                    </button>
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
                            <path d="M12 10c-1.32 0 -1.983 .421 -2.931 1.924l-.244 .398l-.395 .688a50.89 50.89 0 0 0 -.141 .254c-.24 .434 -.571 .753 -1.139 1.142l-.55 .365c-.94 .627 -1.432 1.118 -1.707 1.955c-.124 .338 -.196 .853 -.193 1.28c0 1.687 1.198 2.994 2.8 2.994l.242 -.006c.119 -.006 .234 -.017 .354 -.034l.248 -.043l.132 -.028l.291 -.073l.162 -.045l.57 -.17l.763 -.243l.455 -.136c.53 -.15 .94 -.222 1.283 -.222c.344 0 .753 .073 1.283 .222l.455 .136l.764 .242l.569 .171l.312 .084c.097 .024 .187 .045 .273 .062l.248 .043c.12 .017 .235 .028 .354 .034l.242 .006c1.602 0 2.8 -1.307 2.8 -3c0 -.427 -.073 -.939 -.207 -1.306c-.236 -.724 -.677 -1.223 -1.48 -1.83l-.257 -.19l-.528 -.38c-.642 -.47 -1.003 -.826 -1.253 -1.278l-.27 -.485l-.252 -.432c-1.011 -1.696 -1.618 -2.099 -3.053 -2.099z" />
                            <path d="M19.78 7h-.03c-1.219 .02 -2.35 1.066 -2.908 2.504c-.69 1.775 -.348 3.72 1.075 4.333c.256 .109 .527 .163 .801 .163c1.231 0 2.38 -1.053 2.943 -2.504c.686 -1.774 .34 -3.72 -1.076 -4.332a2.05 2.05 0 0 0 -.804 -.164z" />
                            <path d="M9.025 3c-.112 0 -.185 .002 -.27 .015l-.093 .016c-1.532 .206 -2.397 1.989 -2.108 3.855c.272 1.725 1.462 3.114 2.92 3.114l.187 -.005a1.26 1.26 0 0 0 .084 -.01l.092 -.016c1.533 -.206 2.397 -1.989 2.108 -3.855c-.27 -1.727 -1.46 -3.114 -2.92 -3.114z" />
                            <path d="M14.972 3c-1.459 0 -2.647 1.388 -2.916 3.113c-.29 1.867 .574 3.65 2.174 3.867c.103 .013 .2 .02 .296 .02c1.39 0 2.543 -1.265 2.877 -2.883l.041 -.23c.29 -1.867 -.574 -3.65 -2.174 -3.867a2.154 2.154 0 0 0 -.298 -.02z" />
                            <path d="M4.217 7c-.274 0 -.544 .054 -.797 .161c-1.426 .615 -1.767 2.562 -1.078 4.335c.563 1.451 1.71 2.504 2.941 2.504c.274 0 .544 -.054 .797 -.161c1.426 -.615 1.767 -2.562 1.078 -4.335c-.563 -1.451 -1.71 -2.504 -2.941 -2.504z" />
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
         // Улучшенная обработка бургера для тач-устройств и закрытие по клику вне
document.addEventListener('DOMContentLoaded', function() {
    const burger = document.getElementById('burger');
    const menu = document.querySelector('.buttons-left');

    if (!burger || !menu) return;

    function toggleMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        menu.classList.toggle('mobile-open');
    }

    // Клик и касание
    burger.addEventListener('click', toggleMenu);
    burger.addEventListener('touchstart', toggleMenu, { passive: false });

    // Закрытие при клике/тапе вне меню и бургера
    function closeMenu(e) {
        if (menu.classList.contains('mobile-open') &&
            !menu.contains(e.target) &&
            !burger.contains(e.target)) {
            menu.classList.remove('mobile-open');
        }
    }

    document.addEventListener('click', closeMenu);
    document.addEventListener('touchstart', closeMenu, { passive: true });

    // При ресайзе окна > 900px закрываем мобильное меню (если открыто)
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 901) {
            menu.classList.remove('mobile-open');
        }
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
                    imageContainer.style.transform = `perspective(600px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;

                    // Голографический градиент
                    const bgX = (nx * 50) + 50;
                    const bgY = (ny * 50) + 50;
                    imageContainer.style.setProperty('--bg-x', `${bgX}%`);
                    imageContainer.style.setProperty('--bg-y', `${bgY}%`);
                }

                // Сброс эффекта
                function resetTilt() {
                    if (isDragging) return;
                    imageContainer.style.transform = 'perspective(600px) rotateX(0deg) rotateY(0deg) translateZ(0)';
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
                    imageContainer.style.transform = '';
                    originalX = 0;
                    originalY = 0;
                });

                // Перемещение
                window.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;

                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;

                    // Перемещаем картинку
                    imageContainer.style.transform = `translate(${dx}px, ${dy}px)`;
                });

                // Завершение перетаскивания
                window.addEventListener('mouseup', (e) => {
                    if (!isDragging) return;
                    isDragging = false;

                    // Возвращаем transition
                    imageContainer.style.transition = 'transform 0.3s ease-out';

                    // Плавно возвращаем на место
                    imageContainer.style.transform = '';

                    // После окончания анимации можно вернуть наклон (но пока убираем)
                    setTimeout(() => {
                        imageContainer.style.transition = 'transform 0.01s ease-out'; // возвращаем быструю реакцию
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
                    const scale = 1.1;


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

            // ── Универсальные дропдауны (hover + клик, закрытие других) ──
            (function() {
                const dropdowns = document.querySelectorAll('.nav-dropdown');
                if (!dropdowns.length) return;

                let closeTimeout = null;

                function closeAllDropdowns(exceptDropdown = null) {
                    dropdowns.forEach(dd => {
                        if (exceptDropdown && dd === exceptDropdown) return;
                        dd.classList.remove('open');
                        const trig = dd.querySelector('.nav-dropdown__trigger');
                        if (trig) trig.setAttribute('aria-expanded', 'false');
                    });
                }

                function openDropdown(dropdown) {
                    if (closeTimeout) clearTimeout(closeTimeout);
                    closeAllDropdowns(dropdown);
                    dropdown.classList.add('open');
                    const trig = dropdown.querySelector('.nav-dropdown__trigger');
                    if (trig) trig.setAttribute('aria-expanded', 'true');
                }

                function scheduleClose(dropdown) {
                    if (closeTimeout) clearTimeout(closeTimeout);
                    closeTimeout = setTimeout(() => {
                        dropdown.classList.remove('open');
                        const trig = dropdown.querySelector('.nav-dropdown__trigger');
                        if (trig) trig.setAttribute('aria-expanded', 'false');
                    }, 200);
                }

                function cancelClose() {
                    if (closeTimeout) {
                        clearTimeout(closeTimeout);
                        closeTimeout = null;
                    }
                }

                dropdowns.forEach(dropdown => {
                    const trigger = dropdown.querySelector('.nav-dropdown__trigger');
                    if (!trigger) return;

                    // Клик по триггеру (для мобильных и чтобы закрыть ручным кликом)
                    trigger.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (dropdown.classList.contains('open')) {
                            dropdown.classList.remove('open');
                            trigger.setAttribute('aria-expanded', 'false');
                        } else {
                            openDropdown(dropdown);
                        }
                    });

                    // Открытие при наведении на триггер или само меню
                    dropdown.addEventListener('mouseenter', () => {
                        cancelClose();
                        openDropdown(dropdown);
                    });

                    dropdown.addEventListener('mouseleave', () => {
                        scheduleClose(dropdown);
                    });
                });

                // Клик вне любого дропдауна → закрыть все
                document.addEventListener('click', (e) => {
                    let isInside = false;
                    dropdowns.forEach(dd => {
                        if (dd.contains(e.target)) isInside = true;
                    });
                    if (!isInside) closeAllDropdowns();
                });

                // При ресайзе окна > 900px закрываем все (для десктопного вида)
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 900) {
                        closeAllDropdowns();
                    }
                });
            })();
        </script>

        <script>
            // Shadow только при скролле
            (function() {
                const header = document.querySelector('.header');
                const floater = document.querySelector('.center-floating-block');

                function onScroll() {
                    const scrolled = window.scrollY > 4;
                    header?.classList.toggle('scrolled', scrolled);
                    floater?.classList.toggle('scrolled', scrolled);
                }

                window.addEventListener('scroll', onScroll, {
                    passive: true
                });
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
                const actionsPanel = document.querySelector('.dust-layout__actions');

                // Звук печати
                const typeSound = new Audio("/swad/static/sounds/dusty_fx.mp3");
                typeSound.volume = 0.3;
                const SOUND_EVERY_N_CHARS = 3;

                // ---------- Скорости анимации ----------
                const TALK_SPEED = 150; // скорость смены кадров при разговоре (мс)
                const BLINK_SPEED = 100; // скорость переключения кадров моргания (мс)
                const BLINK_PAUSE_MIN = 1000; // минимальная пауза между морганиями (мс)
                const BLINK_PAUSE_MAX = 3000; // максимальная пауза между морганиями (мс)

                // ---------- Кадры анимации по эмоциям ----------
                const catEmotions = {
                    normal: {
                        talk: [
                            "/swad/static/img/dastyframe1.png",
                            "/swad/static/img/dastyframe2.png",
                            "/swad/static/img/dastyframe3.png"
                        ],
                        idle: [
                            "/swad/static/img/dastyframe_idle.png",
                            "/swad/static/img/dastyframe_idle2.png"
                        ]
                    },
                    happy: {
                        talk: [
                            "/swad/static/img/dastyframe_happy1.png",
                            "/swad/static/img/dastyframe_happy2.png",
                            "/swad/static/img/dastyframe_happy3.png"
                        ],
                        idle: [
                            "/swad/static/img/dastyframe_happy_idle.png",
                            "/swad/static/img/dastyframe_happy_idle2.png"
                        ]
                    },
                    think: {
                        talk: [
                            "/swad/static/img/dastyframe_think1.png",
                            "/swad/static/img/dastyframe_think2.png",
                            "/swad/static/img/dastyframe_think3.png"
                        ],
                        idle: [
                            "/swad/static/img/dastyframe_think_idle.png",
                            "/swad/static/img/dastyframe_think_idle2.png",
                            "/swad/static/img/dastyframe_think_idle3.png"
                        ]
                    },
                    sleepy: {
                        talk: [
                            "/swad/static/img/dastyframe_sleepy1.png",
                            "/swad/static/img/dastyframe_sleepy2.png",
                            "/swad/static/img/dastyframe_sleepy3.png"
                        ],
                        idle: [
                            "/swad/static/img/dastyframe_sleepy_idle.png",
                            "/swad/static/img/dastyframe_sleepy_idle2.png"
                        ]
                    }
                };

                // ---------- База знаний Дасти (greetings и topics) ----------
                const pagesTopics = {
                    '/': {
                        greetings: [
                            "Ты на главной Dustore! Что хочешь узнать?",
                            "О, привет! Давно не виделись. Куда направимся?",
                            "Главная — сердце платформы. Выбирай, что интересно!"
                        ],
                        topics: [{
                                label: "Что такое Dustore?",
                                answer: "Dustore — это открытая платформа для инди-разработчиков и игроков. Тут можно публиковать игры, искать команду, участвовать в джемах и многое другое.",
                                emotion: 'sleepy'
                            },
                            {
                                label: "Куда пойти с главной?",
                                answer: "С главной можно сразу перейти в каталог игр (кнопка «Игры») или зарегистрироваться, нажав «Войти в аккаунт» в правом верхнем углу."
                            },
                            {
                                label: "Как стать разработчиком?",
                                answer: "Нажми «Для разработчиков» в шапке, а затем «Войти в консоль». Если у тебя ещё нет аккаунта, система предложит создать его."
                            }
                        ]
                    },
                    '/explore': {
                        greetings: [
                            "Ты в каталоге игр! Чем помочь?",
                            "Ищешь что-то конкретное? Я подскажу.",
                            "Ого, сколько игр! Давай найдём ту самую."
                        ],
                        topics: [{
                                label: "Как найти игру?",
                                answer: "В каталоге можно искать по названию, фильтровать по жанрам или просто листать. Если не нашёл нужное, воспользуйся поиском (кнопка «Поиск»)."
                            },
                            {
                                label: "Как скачать игру?",
                                answer: "Зайди на страницу игры и нажми кнопку «Скачать». Если игра платная, сначала оплати через FinV2 — это наша платёжная система."
                            },
                            {
                                label: "Есть ли возрастные ограничения?",
                                answer: "Да, мы предупреждаем о контенте 18+. На странице игры будет пометка, если это необходимо.",
                                emotion: 'think'
                            }
                        ]
                    },
                    '/search': {
                        greetings: [
                            "Ты в поиске! Что ищешь?",
                            "Поиск — сила! Введи запрос, и я помогу.",
                            "Что будем искать? Игру, разработчика, жанр?"
                        ],
                        topics: [{
                                label: "Как искать по жанрам?",
                                answer: "Введи ключевое слово или выбери жанр из подсказок. Можно искать и по названию игры, и по разработчику."
                            },
                            {
                                label: "Что делать, если ничего не нашлось?",
                                answer: "Попробуй другое написание или более общий запрос. Если совсем ничего нет — возможно, такая игра ещё не добавлена.",
                                emotion: 'think'
                            }
                        ]
                    },
                    '/devs': {
                        greetings: [
                            "Ты в консоли разработчика! Нужна помощь?",
                            "Привет, творец! Что сегодня создаём?",
                            "Консоль — твой штаб. Спрашивай, не стесняйся."
                        ],
                        topics: [{
                                label: "Как загрузить игру?",
                                answer: "В консоли нажми «Добавить проект», заполни описание, загрузи файлы и отправь на модерацию. После проверки игра появится в каталоге."
                            },
                            {
                                label: "Как подключить монетизацию?",
                                answer: "В разделе «Финансы» ты можешь настроить цену игры и подключить FinV2. Комиссия платформы — 0%!",
                                emotion: 'think'
                            },
                            {
                                label: "Где взять команду?",
                                answer: "Загляни в сервис L4T (Looking for a Team) — там можно найти художников, программистов и других специалистов."
                            }
                        ]
                    },
                    // ===== НОВЫЕ СТРАНИЦЫ =====
                    '/l4t': {
                        greetings: [
                            "Ты на бирже L4T! Ищешь команду или работу?",
                            "L4T — место, где инди-творцы находят друг друга. Что интересует?",
                            "Привет! Готов найти команду мечты или предложить свои навыки?"
                        ],
                        topics: [{
                                label: "Как найти команду для проекта?",
                                answer: "Создай объявление о поиске — опиши, кто нужен (художник, программист, композитор). Или просмотри резюме других пользователей.",
                                emotion: 'happy'
                            },
                            {
                                label: "Как предложить свои услуги?",
                                answer: "Заполни свой профиль в L4T: укажи навыки, опыт и желаемую роль. Студии сами смогут тебя найти!",
                                emotion: 'normal'
                            },
                            {
                                label: "Как нанять человека в студию?",
                                answer: "На странице студии есть кнопка «Нанять». Ты можешь создать вакансию и собирать отклики от соискателей.",
                                emotion: 'think'
                            },
                            {
                                label: "Как откликнуться на заказ?",
                                answer: "Нажимай «Откликнуться» на интересном предложении и пиши, почему ты подходишь. Не стесняйся, мы все здесь свои!"
                            }
                        ]
                    },
                    '/assetstore': {
                        greetings: [
                            "Добро пожаловать в DustAsset — магазин ассетов!",
                            "Тут можно купить готовые ассеты или продать свои. Что хочешь сделать?",
                            "Ассеты, музыка, спрайты — всё для твоей игры. Спрашивай!"
                        ],
                        topics: [{
                                label: "Как купить ассет?",
                                answer: "Выбери понравившийся ассет и нажми «Купить». Оплата через FinV2, и файл сразу у тебя. Всё просто!",
                                emotion: 'happy'
                            },
                            {
                                label: "Как продать свой ассет?",
                                answer: "Нажми «Загрузить ассет», заполни описание, укажи цену и отправь на проверку. После одобрения он появится в магазине.",
                                emotion: 'think'
                            },
                            {
                                label: "Какие форматы поддерживаются?",
                                answer: "Картинки, звуки, 3D-модели, даже готовые скрипты. Главное — чтобы лицензия позволяла перепродажу."
                            },
                            {
                                label: "Можно ли вернуть ассет?",
                                answer: "Если ассет не соответствует описанию, напиши в поддержку через «Битый Пиксель». Мы разберёмся."
                            }
                        ]
                    },
                    '/player': {
                        greetings: [
                            "Это твой профиль! Тут вся твоя активность.",
                            "Привет! Смотри свою статистику, достижения и настройки.",
                            "Твой уютный уголок на Dustore. Что хочешь изменить?"
                        ],
                        topics: [{
                                label: "Как изменить аватар?",
                                answer: "Нажми на текущий аватар и загрузи новый. Картинка должна быть квадратной и не слишком тяжёлой.",
                                emotion: 'normal'
                            },
                            {
                                label: "Где посмотреть мои достижения?",
                                answer: "В разделе «Достижения» собраны все твои награды. Чем активнее ты на платформе, тем их больше!",
                                emotion: 'happy'
                            },
                            {
                                label: "Как изменить настройки профиля?",
                                answer: "Нажми на шестерёнку или «Редактировать профиль». Там можно поменять имя, описание и приватность.",
                                emotion: 'think'
                            },
                            {
                                label: "Как посмотреть мои отзывы?",
                                answer: "На вкладке «Отзывы» видны все оценки и комментарии от других игроков и разработчиков."
                            }
                        ]
                    },
                    '/g': {
                        greetings: [
                            "Ты на странице игры! Хочешь узнать подробности?",
                            "Вот она — игра! Смотри скриншоты, читай описание и решай, стоит ли скачивать.",
                            "Привет! Готов оценить этот проект?"
                        ],
                        topics: [{
                                label: "Как скачать эту игру?",
                                answer: "Нажми большую кнопку «Скачать». Если игра платная, сначала оплати через FinV2. Нулевая комиссия для тебя!",
                                emotion: 'happy'
                            },
                            {
                                label: "Как оставить отзыв?",
                                answer: "Прокрути вниз до блока «Отзывы» и поставь оценку. Можешь написать подробный фидбек — разработчики будут рады."
                            },
                            {
                                label: "Что значит «Название студии»?",
                                answer: "Это студия-разработчик. Нажми на название, чтобы посмотреть все её игры и контакты."
                            },
                            {
                                label: "Игра не запускается, что делать?",
                                answer: "Проверь системные требования в описании. Если всё совпадает, нажми «Пожаловаться» или напиши в «Битый Пиксель».",
                                emotion: 'think'
                            }
                        ]
                    },
                    '_default': {
                        greetings: [
                            "Я Дасти! Чем могу помочь?",
                            "Привет-привет! Что будем делать?",
                            "Дасти на связи. Задавай вопрос!"
                        ],
                        topics: [{
                                label: "Что такое Dustore?",
                                answer: "Dustore — это платформа для инди-разработчиков и игроков. Здесь ты можешь находить новые игры или публиковать свои."
                            },
                            {
                                label: "Как зарегистрироваться?",
                                answer: "Нажми «Войти в аккаунт» в правом верхнем углу. Регистрация бесплатная."
                            },
                            {
                                label: "Где я сейчас?",
                                answer: "Я вижу текущую страницу, но у меня пока нет для неё специальных подсказок. Попробуй спросить что-то общее!",
                                emotion: 'think'
                            }
                        ]
                    }
                };

                const timeGreetings = {
                    morning: [
                        "Доброе утро! Как настроение?",
                        "Утречка! Кофе уже выпил?",
                        "Солнышко встало — пора за игры!"
                    ],
                    day: [
                        "Добрый день! Самое время для новых открытий.",
                        "День в разгаре! Чем займёмся?",
                        "Привет! Не пора ли немного поиграть?"
                    ],
                    evening: [
                        "Добрый вечер! Как прошёл день?",
                        "Вечер — лучшее время для инди-игр.",
                        "Привет! Готов к вечерним приключениям?"
                    ],
                    night: [
                        "Полуночничаешь? Я с тобой.",
                        "Ночью тоже работаем! Что нужно?",
                        "Тихая ночь — отличное время для вопросов."
                    ]
                };

                let currentPath = window.location.pathname;
                // Приводим динамические пути к базовым ключам
                if (currentPath.startsWith('/g/')) currentPath = '/g';
                else if (currentPath.startsWith('/player/')) currentPath = '/player';
                else if (currentPath.startsWith('/l4t/')) currentPath = '/l4t';
                else if (currentPath.startsWith('/assetstore/')) currentPath = '/assetstore';
                const pageData = pagesTopics[currentPath] || pagesTopics['_default'];

                // ---------- Переменные анимации ----------
                let frameIndex = 0;
                let animationInterval = null;
                let idleTimeout = null;
                let blinkInterval = null; // интервал для кадров моргания
                let charIndex = 0;
                let soundCounter = 0;
                let typingTimer = null; // хранит setTimeout печати
                let isModalOpen = false; // флаг, что модалка открыта
                let currentEmotion = 'normal';
                let currentFrames = catEmotions.normal.idle;

                // ---------- Управление анимацией ----------
                function stopAnimation() {
                    clearTimeout(typingTimer);
                    typingTimer = null;
                    if (animationInterval) {
                        clearInterval(animationInterval);
                        animationInterval = null;
                    }
                    if (idleTimeout) {
                        clearTimeout(idleTimeout);
                        idleTimeout = null;
                    }
                    if (blinkInterval) { // <-- добавь это
                        clearInterval(blinkInterval);
                        blinkInterval = null;
                    }
                }

                // Анимация говорения (равномерная смена кадров)
                function startTalkAnimation(frames) {
                    stopAnimation();
                    if (!frames || frames.length === 0) return;
                    currentFrames = frames;
                    frameIndex = 0;
                    cat.src = frames[0];
                    animationInterval = setInterval(() => {
                        frameIndex = (frameIndex + 1) % frames.length;
                        cat.src = frames[frameIndex];
                    }, TALK_SPEED);
                }

                // Анимация моргания с паузами
                function startIdleAnimation(frames) {
                    stopAnimation();
                    if (!frames || frames.length < 2) {
                        cat.src = frames?.[0] || catEmotions.normal.idle[0];
                        return;
                    }
                    currentFrames = frames;

                    function blinkCycle() {
                        // Показываем открытые глаза (первый кадр)
                        cat.src = frames[0];
                        const pause = BLINK_PAUSE_MIN + Math.random() * (BLINK_PAUSE_MAX - BLINK_PAUSE_MIN);
                        idleTimeout = setTimeout(() => {
                            let step = 1;
                            cat.src = frames[step];
                            blinkInterval = setInterval(() => {
                                step++;
                                if (step >= frames.length) {
                                    clearInterval(blinkInterval);
                                    blinkInterval = null; // не обязательно, но для чистоты
                                    cat.src = frames[0];
                                    blinkCycle();
                                } else {
                                    cat.src = frames[step];
                                }
                            }, BLINK_SPEED);
                        }, pause);
                    }

                    blinkCycle();
                }

                // ---------- Запуск эмоций ----------
                function startCatTalk(emotion = 'normal') {
                    currentEmotion = emotion;
                    const frames = catEmotions[emotion]?.talk || catEmotions.normal.talk;
                    startTalkAnimation(frames);
                }

                function startCatIdle(emotion = 'normal') {
                    currentEmotion = emotion;
                    const frames = catEmotions[emotion]?.idle || catEmotions.normal.idle;
                    startIdleAnimation(frames);
                }

                // Подмигивание
                function winkAnimation(callback) {
                    stopAnimation();
                    cat.src = "/swad/static/img/dastyframe_half.png";
                    setTimeout(() => {
                        cat.src = "/swad/static/img/dastyframe_full.png";
                        setTimeout(() => {
                            startCatIdle(currentEmotion);
                            if (callback) callback();
                        }, 900);
                    }, 700);
                }

                // ---------- Побуквенная печать (с эмоцией) ----------
                function typeText(text, emotion = 'normal', onComplete) {
                    clearTimeout(typingTimer);
                    textElement.textContent = '';
                    charIndex = 0;
                    soundCounter = 0;
                    startCatTalk(emotion);

                    function type() {
                        if (charIndex < text.length) {
                            textElement.textContent += text.charAt(charIndex);
                            soundCounter++;
                            if (soundCounter % SOUND_EVERY_N_CHARS === 0) {
                                typeSound.currentTime = 0;
                                typeSound.play().catch(() => {});
                            }
                            charIndex++;
                            typingTimer = setTimeout(type, 25);
                        } else {
                            startCatIdle(emotion);
                            if (onComplete) onComplete();
                        }
                    }
                    type();
                }

                // ---------- Панель действий ----------
                function clearActions() {
                    actionsPanel.innerHTML = '';
                }

                function getRandomGreeting() {
                    const greetings = Array.isArray(pageData.greetings) ?
                        pageData.greetings : [pageData.greeting || "Привет!"];
                    return greetings[Math.floor(Math.random() * greetings.length)];
                }

                function showTopicsMenu() {
                    clearActions();
                    pageData.topics.forEach(topic => {
                        const btn = document.createElement('button');
                        btn.className = 'dust-close';
                        btn.textContent = topic.label;
                        btn.addEventListener('click', () => {
                            clearActions();
                            const isNight = (new Date().getHours() >= 0 && new Date().getHours() < 6);
                            const emotion = topic.emotion || (isNight ? 'sleepy' : 'normal');
                            typeText(topic.answer, emotion, () => {
                                if (topic.wink) {
                                    winkAnimation(() => {
                                        showBackButton();
                                    });
                                } else {
                                    showBackButton();
                                }
                            });
                        });
                        actionsPanel.appendChild(btn);
                    });

                    function showBackButton() {
                        const backBtn = document.createElement('button');
                        backBtn.className = 'dust-close';
                        backBtn.textContent = 'Ещё вопрос?';
                        backBtn.addEventListener('click', () => {
                            textElement.textContent = getRandomGreeting();
                            showTopicsMenu();
                        });
                        actionsPanel.appendChild(backBtn);
                    }
                }

                // ---------- Открытие / закрытие ----------
                function openModal() {
                    modal.classList.remove('hidden');
                    isModalOpen = true;
                    textElement.textContent = '';
                    clearActions();

                    const hour = new Date().getHours();
                    let greeting;
                    let greetingEmotion = 'normal'; // эмоция для приветствия

                    // Определяем время суток
                    let timeKey;
                    if (hour >= 6 && hour < 12) timeKey = 'morning';
                    else if (hour >= 12 && hour < 18) timeKey = 'day';
                    else if (hour >= 18 && hour < 24) timeKey = 'evening';
                    else {
                        timeKey = 'night';
                        greetingEmotion = 'sleepy'; // ночью Дасти сонный
                    }

                    if (Math.random() < 0.4) {
                        const arr = timeGreetings[timeKey];
                        greeting = arr[Math.floor(Math.random() * arr.length)];
                    } else {
                        greeting = getRandomGreeting();
                        // если не временное приветствие, но ночь – всё равно сонный
                        if (timeKey === 'night') greetingEmotion = 'sleepy';
                    }

                    // Печатаем приветствие с нужной эмоцией
                    typeText(greeting, greetingEmotion, () => {
                        showTopicsMenu();
                    });
                }

                function closeModal() {
                    modal.classList.add('hidden');
                    isModalOpen = false;
                    stopAnimation();
                    cat.src = catEmotions.normal.idle[0];
                }

                openBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openModal();
                });

                closeBtn.addEventListener('click', closeModal);

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closeModal();
                });

                continueBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            })();
        </script>

        <script>
            // Тоггл темы (иконка солнце/луна + localStorage + логотип)
            (function() {
                const themeBtn = document.getElementById('themeToggleBtn');
                const logoImg = document.querySelector('.image img');
                if (!themeBtn) return;

                // Пути к логотипам
                const logoAppollo = '/swad/static/img/LogoV3-Appolo_mini.png';
                const logoMoonlight = '/swad/static/img/LogoV3-Moonlight_mini.png';

                // Установить иконку и логотип в зависимости от темы
                function setThemeUI(theme) {
                    // Меняем иконку кнопки
                    if (theme === 'moonlight') {
                        themeBtn.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>`;
                        // Меняем логотип на лунный
                        if (logoImg) logoImg.src = logoMoonlight;
                    } else {
                        themeBtn.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>`;
                        // Меняем логотип на светлый
                        if (logoImg) logoImg.src = logoAppollo;
                    }
                }

                // Загружаем сохранённую тему (по умолчанию 'appollo')
                const savedTheme = localStorage.getItem('dustore_theme');
                if (savedTheme === 'moonlight') {
                    setThemeUI('moonlight');
                    document.body.classList.add('moonlight-theme');
                } else {
                    setThemeUI('appollo');
                    document.body.classList.remove('moonlight-theme');
                    if (!savedTheme) localStorage.setItem('dustore_theme', 'appollo');
                }

                // Клик — переключение
                themeBtn.addEventListener('click', function() {
                    const current = localStorage.getItem('dustore_theme');
                    const newTheme = current === 'moonlight' ? 'appollo' : 'moonlight';
                    localStorage.setItem('dustore_theme', newTheme);
                    setThemeUI(newTheme);
                    document.body.classList.toggle('moonlight-theme', newTheme === 'moonlight');
                });
            })();
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const burger = document.getElementById('burger');
                const menu = document.querySelector('.buttons-left');

                if (!burger || !menu) return;

                burger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menu.classList.toggle('mobile-open');
                });

                // Закрыть меню при клике вне его (по желанию)
                document.addEventListener('click', function(e) {
                    if (menu.classList.contains('mobile-open') && !menu.contains(e.target) && !burger.contains(e.target)) {
                        menu.classList.remove('mobile-open');
                    }
                });
            });
        </script>

</body>

</html>