<?php
session_start();
require_once('../constants.php');
require_once(ROOT_DIR . '/swad/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_id'])) {
    // Проверка авторизации
    if (empty($_SESSION['USERDATA'])) {
        header('Location: ../login');
        exit();
    }

    $_SESSION['studio_id'] = (int)$_POST['studio_id'];
    header('Location: ' . $_POST['backUrl']);
    exit();
}

header('Location: select');
exit();
