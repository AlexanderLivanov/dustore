<?php
// (c) 11.12.2025 Alexander Livanov
/* ======================================================================
   Профиль игрока /player/<username> — хаб игрока (движок swad/fx).

   Баннер:      аватар, имя, @ник, статус в сети, «В друзья» / «Написать»; у владельца — «Изменить профиль» и шестерёнка
                настроек (окна из swad/static/elements/*). Справа — награды (достижения).
   Основной:    вкладки «Стена» (личная стена — пишет любой игрок; сюда же попадают посты самого игрока из медиа),
                «Коллекция», «Друзья», «Отзывы» и — только владельцу — «Аккаунт» (безопасность).
   Правый блок: цифры, последние игры, друзья, заявки в друзья (владельцу), ссылки.

   Тело страницы (данные + разметка хаба) — swad/fx/pages.php → FxPages::player(): его же зовёт мобильное PWA (/m/player/<ник>).
   ====================================================================== */
require_once('swad/config.php');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/swad/fx/pages.php';

$request_uri = $_SERVER['REQUEST_URI'];
$pattern = '/\/player\/([a-zA-Z0-9_]+)/';

if (preg_match($pattern, $request_uri, $matches)) {
    $username = $matches[1];
} else {
    $path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    if (count($path_parts) >= 2 && $path_parts[0] == 'player') {
        $username = $path_parts[1];
    } else {
        header("HTTP/1.0 404 Not Found");
        die("Пользователь не найден");
    }
}

if (empty($username)) {
    header("HTTP/1.0 404 Not Found");
    die("Пользователь не найден");
}

$database = new Database();
$pdo = $database->connect();
Fx::use($pdo);

$page = FxPages::player($pdo, $username);
if (!$page) {
    header("HTTP/1.0 404 Not Found");
    die("Пользователь не найден");
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0d0911">
    <title>Профиль <?= e($page['username']) ?> | Dustore</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <?= FxPage::head() ?>
    <style>
        body { background: #0d0911; margin: 0 }
    </style>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>
    <main>
        <?= FxPage::body() ?>
        <?= $page['html'] ?>
    </main>

    <?php /* Окна владельца («Настройки», «Изменить профиль») лежат вне .fx — у них свои стили (user-dialog.css) */ ?>
    <?= $page['modals']() ?>

    <?= FxPage::scripts() ?>
    <script src="<?= FxPage::asset('/swad/js/fx-player.js') ?>" defer></script>
    <script>
    (function(){var l=document.createElement('link');l.rel='stylesheet';l.href='/swad/css/scrollbar.css';document.head.appendChild(l);})();
    </script>
</body>

</html>
