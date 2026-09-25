<?php
/** m/layout/shell.php — оболочка приложения. Переменные задаёт роутер и вьюха. */
$nav = [
    ['home',    '/m/',        'home',           'Главная'],
    ['catalog', '/m/catalog', 'layout-grid',    'Каталог'],
    ['chat',    '/m/chat',    'message-circle', 'Чаты'],
    ['library', '/m/library', 'bookmark',       'Игры'],
    ['profile', '/m/profile', 'user',           'Профиль'],
];
$ava = trim((string)($user['profile_picture'] ?? ''));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<!-- масштабирование НЕ запрещаем (доступность); авто-зум iOS убран 16px-полями в CSS -->
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#0d0118">
<meta name="color-scheme" content="dark">
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Dustore">
<link rel="manifest" href="/m/manifest.json">
<link rel="icon" href="/m/icons/icon-192.png?v=2">
<link rel="apple-touch-icon" href="/m/icons/apple-180.png?v=2">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.8.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="<?= m_asset('/m/css/app.css') ?>">
<?= $headExtra ?>
<title><?= h($title) ?></title>
<?php /* Chrome: предзагрузка страниц /m/* по началу касания — переход ощущается мгновенным.
         Остальные браузеры правило молча игнорируют. */ ?>
<script type="speculationrules">
{"prefetch":[{"source":"document","where":{"and":[{"href_matches":"/m/*"},{"not":{"href_matches":"/m/chat*"}}]},"eagerness":"moderate"}]}
</script>
</head>
<body class="<?= h($bodyClass) ?><?= $hideNav ? ' no-nav' : '' ?>">
<div id="m-root">

<?php if (!$hideHead): ?>
<header class="m-head">
  <a class="m-logo" href="/m/" aria-label="Dustore — главная"><img src="/m/icons/logo-appolo.png" alt="Dustore" width="161" height="120" draggable="false"></a>
  <div class="m-head-act">
    <a class="ic-btn" href="/m/search" aria-label="Поиск"><i class="ti ti-search"></i></a>
    <?php if ($user): ?>
      <a class="ic-btn ava" href="/m/profile" aria-label="Профиль">
        <?php if ($ava): ?><img src="<?= h($ava) ?>" alt="" draggable="false"><?php else: ?><i class="ti ti-user"></i><?php endif; ?>
      </a>
    <?php else: ?>
      <a class="pill-btn" href="/m/login?back=<?= h(rawurlencode($_SERVER['REQUEST_URI'] ?? '/m/')) ?>">Войти</a>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>

<main class="m-main" id="main"><?= $content ?></main>

<?php if (!$hideNav): ?>
<nav class="bnav" aria-label="Разделы">
  <?php foreach ($nav as [$p, $href, $icon, $label]): $on = $p === $page; ?>
    <a class="bn<?= $on ? ' on' : '' ?>" href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>>
      <span class="bn-ic"><i class="ti ti-<?= $icon ?><?= $on ? '-filled' : '' ?>"></i><?php if ($p === 'chat'): ?><b class="bn-badge" id="chatBadge" hidden></b><?php endif; ?></span>
      <span class="bn-l"><?= $label ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

</div>

<div class="install" id="install" hidden>
  <img src="/m/icons/icon-192.png?v=2" alt="" width="40" height="40">
  <div><b>Dustore на экран «Домой»</b><small id="installHint">Как приложение: быстрее и с уведомлениями</small></div>
  <button type="button" class="pill-btn" id="installBtn">Установить</button>
  <button type="button" class="x" id="installX" aria-label="Скрыть">&times;</button>
</div>
<div class="m-toast" id="mToast" role="status" aria-live="polite"></div>

<script>window.M = { user: <?= $uid ?>, page: <?= json_encode($page) ?> };</script>
<script src="<?= m_asset('/m/js/app.js') ?>" defer></script>
<?= $footExtra ?>
</body>
</html>
