<?php
/* ======================================================================
   Dustore.Fid v2 — медиа-лента платформы (реальные данные, ядро swad/fx)

   Две вкладки:
     «Друзья» — записи друзей и на страницах друзей (в т.ч. пост друга на странице игры),
                а также официальные записи студий и игр, на которые вы подписаны.
     «Лента»  — то, что написано прямо в медиа: посты, статьи, новости платформы,
                плюс рекомендации и случайные находки.
   Записи со «стен» игроков и обсуждения игр сюда НЕ попадают — только через «Друзья».

   Третья вкладка — Dustore.Hunt (идущие охоты игр).
   Адреса /fid/post/123 (уведомления, ссылки на статьи) открывают ленту с оверлеем поста.
   Тело страницы — swad/fx/pages.php → FxPages::fid(): его же зовёт мобильное PWA (/m/media).
   ====================================================================== */
require_once __DIR__ . '/../swad/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/fx/pages.php';

Fx::use((new Database())->connect());
$page = FxPages::fid(Fx::uid(), ['tab' => (string)($_GET['tab'] ?? ''), 'open' => (int)($_GET['post'] ?? 0)]);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0d0911">
    <title>Dustore.Fid — лента</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <?= FxPage::head() ?>
    <style>
        body { background: #0d0911 }
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>
    <?= FxPage::body() ?>
    <?= $page['html'] ?>
    <?= FxPage::scripts(true) ?>
</body>

</html>
