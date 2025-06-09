<?php
// 02.06.2025 (c) Alexander Livanov
session_start();

require_once('../config.php');

$db = new Database();

if (!isset($_GET['hash'])) {
    die('Telegram hash not found');
}

try {
    $auth_data = checkTelegramAuthorization($_GET);
    userAuthentication($db, $auth_data);
} catch (Exception $e) {
    die($e->getMessage());
}

die(header('Location: /me'));

function checkTelegramAuthorization($auth_data)
{
    $check_hash = $auth_data['hash'];
    unset($auth_data['hash']);
    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);
    if ($_SERVER['HTTP_HOST'] == 'dustore.ru') {
        $secret_key = hash('sha256', BOT_TOKEN, true);
    }
    if ($_SERVER['HTTP_HOST'] == '127.0.0.1') {
        $secret_key = hash('sha256', LOCAL_BOT_TOKEN, true);
    }
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);
    if (strcmp($hash, $check_hash) !== 0) {
        throw new Exception('Data is NOT from Telegram');
    }
    if ((time() - $auth_data['auth_date']) > 86400) {
        throw new Exception('Data is outdated');
    }

    return $auth_data;
}

function userAuthentication($db, $auth_data)
{
    $_SESSION['id'] = $auth_data['id'];
    $tg_token = createToken($_SESSION['id']);

    if (userExists($db, $auth_data)) {
        updateUser($db, $auth_data, $tg_token);
    } else {
        createNewUser($db, $auth_data, $tg_token);
    }

    $_SESSION = [
        'telegram_id' => $auth_data['id'],
        'auth_token' => $tg_token
    ];

    function createNewUser($db, $auth_data, $tg_token)
    {
        $db->EXEC(
            "INSERT INTO `users` (`first_name`,
                                        `last_name`,
                                        `telegram_id`,
                                        `telegram_username`,
                                        `telegram_token`,
                                        `profile_picture`,
                                        `auth_date`,
                                        `global_role`)
                                VALUES `(:first_name,
                                        :last_name,
                                        :telegram_id,
                                        :telegram_username,
                                        :telegram_token,
                                        :profile_picture,
                                        :auth_date,
                                        0",
            [
                'first_name'        => $auth_data['first_name'],
                'last_name'         => $auth_data['last_name'],
                'telegram_id'       => $auth_data['id'],
                'telegram_username' => $auth_data['username'],
                'profile_picture'   => $auth_data['photo_url'],
                'auth_date'         => $auth_data['auth_date'],
                'telegram_token'    => $tg_token
            ]
        );
    }

    function updateUser($db, $auth_data, $token){
        $db->EXEC(
            "UPDATE `users`
                SET `first_name`        = :first_name,
                    `last_name`         = :last_name,
                    `telegram_username` = :telegram_username,
                    `telegram_token`    = :telegram_token,
                    `profile_picture`   = :profile_picture,
                    `auth_date`         = :auth_date
                        WHERE `telegram_id` = :telegram_id",
            [
                'first_name'        => $auth_data['first_name'],
                'last_name'         => $auth_data['last_name'],
                'telegram_username' => $auth_data['username'],
                'profile_picture'   => $auth_data['photo_url'],
                'auth_date'         => $auth_data['auth_date'],
                'telegram_id'       => $auth_data['id'],
                'telegram_token'    => $token
            ]
        );
    }

    function userExists($db, $auth_data){
        $target_id = $auth_data['id'];
        $isUser = $db->EXEC(
            "SELECT `telegram_id`
                FROM `users`
                    WHERE `telegram_id` = :id",
            ['id' => $target_id]
        );

        if (!empty($isUser) && $isUser[0]['telegram_id'] === $target_id) {
            return TRUE;
        }
    }
}
