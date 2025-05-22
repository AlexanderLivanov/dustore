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

    // Записываем ID студии в сессию
    $_SESSION['studio_id'] = (int)$_POST['studio_id'];

    // Перенаправляем на главную
    header('Location: index');
    exit();
}

// Если запрос не POST или нет studio_id
header('Location: select');
exit();
