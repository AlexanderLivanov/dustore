<?php
session_start();
require_once('../constants.php');
require_once(ROOT_DIR . '/swad/config.php');
require_once(ROOT_DIR . '/swad/controllers/user.php');

$curr_user = new User();
$db = new Database();

if ($curr_user->checkAuth() > 0) {
    echo ("<script>window.location.replace('../login?backUrl=". $_SERVER['REQUEST_URI'] . "');</script>");
} else {
    $curr_user_data = $_SESSION['USERDATA'];
}

$curr_user_org = $curr_user->getOrgInfo($_SESSION['studio_id']);
// print_r($curr_user_org['status']);
if (empty($_SESSION['studio_id'])) {
    header('Location: select');
    exit();
}

if ($curr_user_org['status'] != 'active') {
    header('Location: select');
    exit();
}
?>

<?php require_once('../swad/controllers/ymcounter.php'); ?>

<ul id="slide-out" class="side-nav fixed z-depth-4">
    <li style="line-height: 36px;">
        <div class="userView" style="padding: 32px 32px 10px 10px">
            <div class="background">
                <!-- Баннер в профиле -->
                <img src="/swad/static/img/DD.svg" style="padding: 2rem; background-color: black; filter: brightness(20%)">
            </div>
            <img class="circle" src="<?= $_SESSION['USERDATA']['profile_picture'] ?>">
            <span class="white-text">Добро пожаловать,</span>
            <span class="white-text"><?= $curr_user->getUsername($_SESSION['USERDATA']['telegram_id']); ?></span>
            <span class="white-text"><a href="/me" style="color: white; border: 1px solid white; border-radius: 15px; margin-bottom: 5px;">← Назад, в профиль</a></span>

        </div>
    </li>

    <li><a class="active" href="/devs/"><i class="material-icons pink-item">dashboard</i>Панель управления</a></li>
    <li>
        <div class="divider"></div>
    </li>

    <li><a class="subheader">Управление</a></li>
    <li><a href="mystudio"><i class="material-icons pink-item">apartment</i>Моя студия</a></li>
    <li><a href="staff" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">groups</i>Сотрудники</a></li>
    <li><a href="projects"><i class="material-icons pink-item">work</i>Проекты</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">note_add</i>Рецензии</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">area_chart</i>Аналитика</a></li>
    <li>
        <div class="divider"></div>
    </li>
    <li><a class="subheader">Проекты</a></li>
    <li><a href="new"><i class="material-icons pink-item">add</i>Создать новый</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">group</i>Исполнители</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">list_alt</i>Задачи</a></li>
    <li><a href="replies"><i class="material-icons pink-item">reviews</i>Отзывы</a></li>
    <li><a href=""><i class="material-icons pink-item">stars</i>Рейтинг игры</a></li>
    <li><a href="monetization" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">currency_ruble</i>Монетизация</a></li>
    <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">bug_report</i>Написать отчёт</a></li>
    <li>
        <div class="divider"></div>
    </li>
    <?php if ($curr_user->getUserRole($curr_user_data['id'], "global") == -1): ?>
        <li><a class="subheader">Для администраторов</a></li>
        <li class="no-padding">
            <ul class="collapsible collapsible-accordion">
                <li>
                    <a class="collapsible-header">Администрирование<i class="material-icons pink-item">shield</i></a>
                    <div class="collapsible-body" style="padding: 0;">
                        <ul>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">person_search</i>Поиск пользователя</a></li>
                            <li><a href="" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">how_to_reg</i>Новые пользователи</a></li>
                            <li><a href="giveach"><i class="material-icons pink-item">star_award</i>Выдать достижение</a></li>
                            <li><a href="createach"><i class="material-icons pink-item">add_circle</i>Добавить достижение</a></li>
                            <li><a href="recentorgs"><i class="material-icons pink-item">domain_add</i>Новые организации</a></li>
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
                <a class="collapsible-header">Уведомления<i class="material-icons pink-item">campaign</i></a>
                <div class="collapsible-body" style="padding: 0;">
                    <ul>
                        <?php if ($curr_user->getUserRole($_SESSION['USERDATA']['id'], "global") == -1): ?>
                            <li><a href="addannoun"><i class="material-icons pink-item">add_alert</i>Создать новое</a></li>
                        <?php endif; ?>
                        <li><a href="allannoun" class="disabled-link" aria-disabled="true"><i class="material-icons pink-item">notifications</i>Все</a></li>
                    </ul>
                </div>
            </li>
        </ul>
    </li>
</ul>