<?php
// (c) Alexander Livanov 01.09.2025
// Login with passphrase


require_once('swad/config.php');
require_once('swad/controllers/jwt.php');
require_once('swad/controllers/user.php');
function ppLogin($username, $passphrase)
{

    $user = new User();

    // Проверяем существование пользователя
    if (!$user->checkUsernameExists($username)) {
        return ['success' => false, 'message' => 'Пользователь с таким именем не найден'];
    }

    // Получаем данные пользователя
    $stmt = $user->db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        return ['success' => false, 'message' => 'Ошибка при получении данных пользователя'];
    }

    // Проверяем passphrase
    if (empty($user_data['passphrase'])) {
        return ['success' => false, 'message' => 'Для этого аккаунта не установлена passphrase'];
    }

    if (!password_verify($passphrase, $user_data['passphrase'])) {
        return ['success' => false, 'message' => 'Неверная passphrase'];
    }

    // Создаем токен
    $token = authUser($user_data['telegram_id']);

    // Обновляем токен в базе данных
    $db = new Database();
    $db->Update(
        "UPDATE `users` SET `telegram_token` = :telegram_token WHERE `id` = :id",
        [
            'telegram_token' => $token,
            'id' => $user_data['id']
        ]
    );

    // Создаем сессию
    $_SESSION = [
        'logged-in' => TRUE,
        'telegram_id' => $user_data['telegram_id'],
        'auth_token' => $token,
        'usernm' => $username
    ];

    setcookie('auth_token', $token, time() + 86400 * 30, '/', '', true, true);

    return ['success' => true, 'message' => 'Успешный вход'];
}
