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

<?php require_once('../swad/controllers/ymcounter.php'); ?>

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
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">groups</i>Сотрудники</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">work</i>Проекты</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">note_add</i>Отзывы</a></li>
    <li>
        <div class="divider"></div>
    </li>
    <li><a class="subheader">Проекты</a></li>
    <li><a href="new"><i class="material-icons pink-item">add</i>Создать новый</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">group</i>Исполнители</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">list_alt</i>Задачи</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">reviews</i>Отзывы</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">stars</i>Рейтинг игры</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">currency_ruble</i>Монетизация</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">bug_report</i>Написать отчёт</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">campaign</i>Создать событие</a></li>
    <li>
        <div class="divider"></div>
    </li>
    <?php if ($curr_user->getUserRole($_SESSION['id'], "global") == -1): ?>
        <li><a class="subheader">Для администраторов</a></li>
        <li class="no-padding">
            <ul class="collapsible collapsible-accordion">
                <li>
                    <a class="collapsible-header">Администрирование<i class="material-icons pink-item">shield</i></a>
                    <div class="collapsible-body" style="padding: 0;">
                        <ul>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">person_search</i>Поиск пользователя</a></li>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">how_to_reg</i>Новые пользователи</a></li>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">domain_add</i>Новые организации</a></li>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">report</i>Репорты</a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </li>
    <?php endif; ?>
    <li>
        <div class="divider"></div>
    </li>
    <li class="no-padding">
        <ul class="collapsible collapsible-accordion">
            <li>
                <a class="collapsible-header">Объявления<i class="material-icons pink-item">campaign</i></a>
                <div class="collapsible-body" style="padding: 0;">
                    <ul>
                        <?php if ($curr_user->getUserRole($_SESSION['id'], "global") == -1): ?>
                            <li><a href="addannoun" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">add_alert</i>Создать новое</a></li>
                        <?php endif; ?>
                        <li><a href="allannoun" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">notifications</i>Все</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </li>
</ul>