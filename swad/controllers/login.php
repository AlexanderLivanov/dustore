<?php
require_once(ROOT_DIR . '/swad/config.php'); // Подключаем класс для работы с БД

// Проверяем, что запрос POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $db = new Database();
        $conn = $db->connect();

        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $curr_user->auth($user['id'], $_POST['username']);

            header('Location: /');
            exit();
        } else {
            // Неверные учетные данные
            header('Location: login.php?error=1');
            exit();
        }
    } catch (PDOException $e) {
        // Ошибка БД
        header('Location: login.php?error=1');
        exit();
    }
} else {
    // Если запрос не POST, перенаправляем на страницу входа
    header('Location: login.html');
    exit();
}
