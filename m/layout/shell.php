<?php

/**
 * m/layout/shell.php
 */

$page_titles = [
    'home'      => 'Dustore',
    'catalog'   => 'Каталог — Dustore',
    'game'      => ($game_title ?? 'Игра') . ' — Dustore',
    'library'   => 'Библиотека — Dustore',
    'profile'   => 'Профиль — Dustore',
    'search'    => 'Поиск — Dustore',
    'developer' => ($dev_title ?? 'Студия') . ' — Dustore',
    'sprints'   => 'Спринты — Dustore',   // <-- это
];
$meta_title = $page_titles[$page] ?? 'Dustore';

$nav_items = [
    ['page' => 'home',    'href' => '/m/',        'icon' => 'home',     'label' => 'Главная'],
    ['page' => 'catalog', 'href' => '/m/catalog', 'icon' => 'grid-3x3', 'label' => 'Каталог'],
    ['page' => 'library', 'href' => '/m/library', 'icon' => 'library',  'label' => 'Библиотека'],
    ['page' => 'sprints', 'href' => '/m/sprints', 'icon' => 'run',     'label' => 'Спринты'],
    ['page' => 'profile', 'href' => '/m/profile', 'icon' => 'user',     'label' => 'Профиль'],
];

/* Версия для cache-busting — меняй при каждом деплое CSS/JS */
$v = '20260515d';

/* Реальное время изменения файла — автоматический cache-bust */
$css_path = __DIR__ . '/../css/app.css';
$js_path  = __DIR__ . '/../js/app.js';
$css_v = file_exists($css_path) ? filemtime($css_path) : $v;
$js_v  = file_exists($js_path)  ? filemtime($js_path)  : $v;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#14041d">

    <!-- PWA / iOS -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Dustore">
    <link rel="manifest" href="/m/manifest.json">
    <link rel="apple-touch-icon" href="/swad/static/img/pwa/icon-180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/swad/static/img/pwa/icon-152.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/swad/static/img/pwa/icon-167.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/swad/static/img/pwa/icon-180.png">

    <!-- iOS Splash -->
    <link rel="apple-touch-startup-image" href="/swad/static/img/pwa/splash-1290x2796.png"
        media="(device-width:430px) and (device-height:932px) and (-webkit-device-pixel-ratio:3)">
    <link rel="apple-touch-startup-image" href="/swad/static/img/pwa/splash-1170x2532.png"
        media="(device-width:390px) and (device-height:844px) and (-webkit-device-pixel-ratio:3)">
    <link rel="apple-touch-startup-image" href="/swad/static/img/pwa/splash-750x1334.png"
        media="(device-width:375px) and (device-height:667px) and (-webkit-device-pixel-ratio:2)">

    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.8.0/dist/tabler-icons.min.css">

    <!-- App CSS с автоматическим cache-busting по времени изменения файла -->
    <link rel="stylesheet" href="/m/css/app.css?v=<?= $css_v ?>">

    <title><?= htmlspecialchars($meta_title) ?></title>
</head>

<body>
    <div id="app">

        <?php if ($page !== 'game'): ?>
            <header class="m-header">
                <a href="/m/" class="m-header-logo">
                    <img src="/swad/static/img/logo_new.png"
                        alt="Dustore"
                        onerror="this.src='/swad/static/img/logo.png'">
                </a>
                <div class="m-header-right">
                    <a href="/m/search" class="m-header-btn" aria-label="Поиск">
                        <i class="ti ti-search"></i>
                    </a>
                    <?php if ($user): ?>
                        <a href="/m/profile" class="m-header-btn">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>"
                                    style="width:22px;height:22px;border-radius:50%;object-fit:cover" alt="">
                            <?php else: ?>
                                <i class="ti ti-user"></i>
                                <span><?= htmlspecialchars(mb_substr($user['first_name'] ?? '', 0, 8)) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="/login?backUrl=/m/" class="m-header-btn">
                            <i class="ti ti-login"></i>
                            <span>Войти</span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>
        <?php endif; ?>

        <main id="main-content">
            <?php require $view; ?>
        </main>

        <?php if ($page !== 'game'): ?>
            <nav class="bottom-nav" role="navigation" aria-label="Навигация">
                <?php foreach ($nav_items as $item):
                    $active = ($page === $item['page']) ? 'active' : '';
                ?>
                    <a href="<?= $item['href'] ?>"
                        class="nav-item <?= $active ?>"
                        aria-label="<?= $item['label'] ?>">
                        <i class="ti ti-<?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

    </div>

    <!-- JS с cache-busting -->
    <script src="/m/js/app.js?v=<?= $js_v ?>"></script>
    <script>
        (function() {
            var sa = window.navigator.standalone || window.matchMedia('(display-mode:standalone)').matches;
            if (sa) {
                document.cookie = 'pwa_standalone=1;path=/;max-age=86400;SameSite=Lax';
                var b = document.getElementById('install-banner');
                if (b) b.style.display = 'none';
            }
            var iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            if (iOS && !sa) {
                var b2 = document.getElementById('install-banner');
                if (b2) b2.style.display = 'flex';
            }
        })();
    </script>
</body>

</html>