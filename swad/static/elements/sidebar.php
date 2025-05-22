<?php
session_start();
require_once('../constants.php');
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');

$curr_user = new User();
$db = new Database();

if (empty($_SESSION['logged-in']) or $curr_user->checkAuth() > 0) {
    echo ("<script>window.location.replace('../login');</script>");
}

$curr_user_org = $curr_user->getUserOrgs($_SESSION['id'], 1);
if (empty($curr_user_org) or empty($_SESSION['studio_id'])) {
    header('Location: select');
    exit();
}

if ($curr_user_org[0]['status'] != 'active') {
    header('Location: select');
    exit();
}

if (empty($_SESSION['studio_id'])) {
    header('Location: select');
    exit();
}
?>

<ul id="slide-out" class="side-nav fixed z-depth-4">
    <li style="line-height: 36px;">
        <div class="userView">
            <div class="background">
                <!-- Баннер в профиле -->
                <img src="assets/img/photo1.png">
            </div>
            <img class="circle" src="assets/img/avatar04.png">
            <span class="white-text">Добро пожаловать,</span>
            <span class="white-text"><?= $curr_user->getUsername($_SESSION['telegram_id']); ?></span>
        </div>
    </li>

    <li><a class="active" href="/devs/"><i class="material-icons pink-item">dashboard</i>Панель управления</a></li>
    <li>
        <div class="divider"></div>
    </li>

    <li><a class="subheader">Моя студия</a></li>
    <li><a href="staff"><i class="material-icons pink-item">groups</i>Сотрудники</a></li>
    <li><a href="feedback"><i class="material-icons pink-item">note_add</i>Отзывы</a></li>

    <li><a class="subheader">Для администраторов</a></li>

    <li class="no-padding">
        <ul class="collapsible collapsible-accordion">
            <li>
                <a class="collapsible-header">Администрирование<i class="material-icons pink-item">shield</i></a>
                <div class="collapsible-body" style="padding: 0 2rem;">
                    <ul>
                        <li><a href="userdetails">Поиск пользователя</a></li>
                        <li><a href="recentusers">Новые пользователи</a></li>
                        <li><a href="recentorgs">Новые организации</a></li>
                        <li><a href="reports">Репорты</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </li>

    <li class="no-padding">
        <ul class="collapsible collapsible-accordion">
            <li>
                <a class="collapsible-header">Объявления<i class="material-icons pink-item">campaign</i></a>
                <div class="collapsible-body" style="padding: 0 2rem;">
                    <ul>
                        <li><a href="addannoun">Создать новое</a></li>
                        <li><a href="allannoun">Все</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </li>
</ul>