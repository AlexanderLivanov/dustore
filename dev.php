<?php
/* ======================================================================
   Страница студии /d/<тикер> — хаб студии (движок swad/fx).

   Баннер:      логотип, название, [ТИКЕР], галочка проверки, подписчики, «Подписаться», «О студии».
                Справа — награды (стопка кружков) и кнопки «Задонатить» / «Закрытая комната»
                (настраиваются в консоли, раздел «Монетизация»).
   Основной:    только девблог — записи пишет команда студии (с подписью роли), сюда же попадают
                собственные посты студии из медиа. Вкладка «Закрытая комната» появится позже.
   Правый блок: статистика (участники, аудитория, просмотры), команда, обратная связь,
                кружки проектов (больше игроков — крупнее), награды проектов.
   Шестерёнки видит только владелец/администратор студии — они ведут в консоль на нужную настройку.

   Тело страницы (данные + разметка хаба) — swad/fx/pages.php → FxPages::studio(): его же зовёт мобильное PWA (/m/dev/<тикер>).
   ====================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('swad/config.php');
require_once __DIR__ . '/swad/fx/pages.php';

$db  = new Database();
$pdo = $db->connect();
Fx::use($pdo);

$page = FxPages::studio($pdo, (string)($_GET['name'] ?? ''));
if (!$page) {
    header('Location: /explore');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0d0911">
    <title>Dustore — <?= e($page['name']) ?></title>
    <meta name="description" content="<?= e($page['desc']) ?>">
    <meta property="og:title" content="<?= e($page['name']) ?> · Dustore">
    <meta property="og:description" content="<?= e($page['desc']) ?>">
    <?php if ($page['image'] !== ''): ?><meta property="og:image" content="<?= e($page['image']) ?>"><?php endif; ?>
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

    <?php require_once('swad/static/elements/footer.php'); ?>
    <?= FxPage::scripts(true) ?>
</body>

</html>
