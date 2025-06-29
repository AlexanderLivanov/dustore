<?php
session_start();
require_once('../constants.php');
require_once(ROOT_DIR . '/swad/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_id'])) {
    // Проверка авторизации
    if (empty($_SESSION['logged-in'])) {
        header('Location: ../login');
        exit();
    }

    $_SESSION['studio_id'] = (int)$_POST['studio_id'];
    header('Location: index');
    exit();
}

header('Location: select');
exit();
