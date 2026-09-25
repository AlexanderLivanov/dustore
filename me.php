<?php
/**
 * /me — раньше отдельная страница «Мой аккаунт» (ник, аватарка, почта, пароль).
 * Теперь всё это живёт в профиле /player/<ник>: окно «Изменить профиль»
 * (карандаш под аватаркой, swad/static/elements/profile_edit.php) и вкладка
 * «Безопасность» (swad/controllers/account_security.php).
 *
 * Адрес оставлен: на него ведут ссылки из уже отправленных уведомлений
 * («Заявка принята» → /me) и закладки.
 *   гость          → /login
 *   есть ник       → /player/<ник>
 *   ника ещё нет   → окно выбора ника прямо здесь. Без ника профиля нет —
 *                    его адрес и есть /player/<ник>, а ник необязателен при
 *                    регистрации по почте и у Telegram-аккаунтов без username.
 */
session_start();
require_once('swad/config.php');
require_once('swad/controllers/user.php');

// Решаем до любого вывода, чтобы редирект был настоящим 302, а не скриптом
$me = new User();
if ($me->checkAuth() > 0 || empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login?backUrl=/me');
    exit;
}

$username = (string)($_SESSION['USERDATA']['username'] ?? '');
if ($username !== '') {
    header('Location: /player/' . rawurlencode($username));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore — выбор ника</title>
    <?php require_once('swad/controllers/ymcounter.php'); ?>
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>

    <main style="min-height: 60vh"></main>

    <?php
    $pe_user       = $_SESSION['USERDATA'];
    $pe_onboarding = true;
    require_once('swad/static/elements/profile_edit.php');
    ?>

    <?php require_once('swad/static/elements/footer.php'); ?>
</body>

</html>
