<?php
/** m/layout/shell.php — оболочка приложения. Переменные задаёт роутер и вьюха. */

/* Вкладки как в дизайне: Игры · Медиа · Чаты · Профиль.
   Внутренние страницы подсвечивают «родителя»: игра, каталог, поиск и студия — это «Игры»,
   библиотека живёт внутри «Профиля». Вкладка «Медиа» появится сама, как только
   будет m/views/media.php: без него ссылка вела бы в пустоту. */
$tabOf = [
    'home' => 'games', 'catalog' => 'games', 'game' => 'games', 'search' => 'games', 'developer' => 'games',
    'media' => 'media', 'chat' => 'chat', 'library' => 'profile', 'profile' => 'profile', 'login' => 'profile',
];
$tab = $tabOf[$page] ?? 'games';
$nav = [['games', '/m/', 'Игры']];
if (is_file(__DIR__ . '/../views/media.php')) $nav[] = ['media', '/m/media', 'Медиа'];
$nav[] = ['chat', '/m/chat', 'Чаты'];
$nav[] = ['profile', '/m/profile', 'Профиль'];

$navSvg = [
    'games'   => '<rect x="2" y="7" width="20" height="11" rx="5"/><path d="M7 11v3M5.5 12.5h3M15.5 11.5h.01M18 14h.01"/>',
    'media'   => '<rect x="3" y="4" width="18" height="16" rx="4"/><path d="M7 9h10M7 13h10M7 17h5"/>',
    'chat'    => '<path d="M4 5h16v11H9l-5 4z"/>',
    'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.8-3.6 3.6-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/>',
];
$ava = trim((string)($user['profile_picture'] ?? ''));
$initial = mb_strtoupper(mb_substr(trim((string)($user['username'] ?? $user['first_name'] ?? '')), 0, 1));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<!-- масштабирование НЕ запрещаем (доступность); авто-зум iOS убран 16px-полями в CSS -->
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#14041d">
<meta name="color-scheme" content="dark">
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Dustore">
<link rel="manifest" href="/m/manifest.json">
<link rel="icon" href="/m/icons/icon-192.png?v=2">
<link rel="apple-touch-icon" href="/m/icons/apple-180.png?v=2">
<link rel="preload" href="/m/fonts/onest-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
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
  <?php foreach ($nav as [$k, $href, $label]): $on = $k === $tab; ?>
    <a class="bn<?= $on ? ' on' : '' ?>" href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>>
      <span class="bn-ic">
        <?php if ($k === 'profile' && $ava): ?><img class="bn-ava" src="<?= h($ava) ?>" alt="" draggable="false">
        <?php elseif ($k === 'profile' && $initial !== ''): ?><span class="bn-ava"><?= h($initial) ?></span>
        <?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true"><?= $navSvg[$k] ?></svg><?php endif; ?>
        <?php if ($k === 'chat'): ?><b class="bn-badge" id="chatBadge" hidden></b><?php endif; ?>
      </span>
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

<?php /* Шторка «Как установить». Показывается плашкой выше, кнопкой в профиле и из чата (push на iPhone).
         Нужный блок (ios / android / other) выбирает app.js. */ ?>
<div class="sheet-ov" id="installSheet" hidden role="dialog" aria-modal="true" aria-labelledby="isTitle">
  <div class="ish">
    <div class="sheet-grab"></div>
    <div class="sheet-head">
      <img src="/m/icons/icon-192.png?v=2" alt="" width="52" height="52">
      <div><h2 id="isTitle">Установите Dustore</h2><p class="sheet-sub">Иконка на экране «Домой», полный экран и уведомления</p></div>
    </div>

    <div data-for="ios" hidden>
      <ol class="steps">
        <li><span class="n">1</span><span>Нажмите <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 15V3M8 7l4-4 4 4M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg></span> <b>«Поделиться»</b> в нижней панели Safari (или в меню «⋯»)</span></li>
        <li><span class="n">2</span><span>Прокрутите список и выберите <span class="ico"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8.5v7M8.5 12h7"/></svg></span> <b>«На экран “Домой”»</b></span></li>
        <li><span class="n">3</span><span>Нажмите <b>«Добавить»</b> — иконка Dustore появится среди приложений</span></li>
      </ol>
      <p class="sheet-note" data-inapp hidden>Вы открыли Dustore внутри другого приложения (Telegram, ВК…). Откройте ссылку в <b>Safari</b> — через меню «⋯» → «Открыть в браузере», — и повторите шаги.</p>
      <p class="sheet-note">Уведомления на iPhone работают только из приложения на экране «Домой» (iOS 16.4 и новее).</p>
    </div>

    <div data-for="android" hidden>
      <div data-native hidden>
        <p class="sheet-note" style="margin-top:16px">Браузер может поставить Dustore в один тап.</p>
        <button type="button" class="btn-big" id="installNative"><i class="ti ti-download"></i>Установить</button>
      </div>
      <ol class="steps" data-manual>
        <li><span class="n">1</span><span>Откройте меню браузера <span class="ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="19" r="1.2"/></svg></span> (три точки справа вверху)</span></li>
        <li><span class="n">2</span><span>Выберите <b>«Установить приложение»</b> или <b>«Добавить на главный экран»</b></span></li>
        <li><span class="n">3</span><span>Подтвердите — Dustore появится среди приложений</span></li>
      </ol>
      <p class="sheet-note" data-inapp hidden>Вы открыли Dustore внутри другого приложения (Telegram, ВК…). Откройте ссылку в <b>Chrome</b> — через меню «⋮» → «Открыть в браузере», — и повторите шаги.</p>
    </div>

    <div data-for="other" hidden>
      <ol class="steps">
        <li><span class="n">1</span><span>Откройте Dustore с телефона в <b>Safari</b> (iPhone) или <b>Chrome</b> (Android)</span></li>
        <li><span class="n">2</span><span>Через меню браузера выберите «На экран “Домой”» или «Установить приложение»</span></li>
      </ol>
    </div>

    <div class="sheet-why">
      <span>Открывается сразу, без адресной строки</span>
      <span>Пуши о сообщениях и обновлениях игр</span>
      <span>Библиотека и чаты всегда под рукой</span>
    </div>
    <button type="button" class="btn-big" id="installClose" style="background:var(--card-3);box-shadow:none">Понятно</button>
  </div>
</div>

<div class="m-toast" id="mToast" role="status" aria-live="polite"></div>

<script>window.M = { user: <?= $uid ?>, page: <?= json_encode($page) ?> };</script>
<script src="<?= m_asset('/m/js/app.js') ?>" defer></script>
<?= $footExtra ?>
</body>
</html>
