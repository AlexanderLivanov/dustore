<?php
session_start();

// // When the user is logged in, go to the user page
if (isset($_SESSION['logged-in']) && $_SESSION['logged-in'] == TRUE) {
    die(header('Location: /me'));
}


// Import database connection and class
require_once('../config.php');
// Import JWT script
require_once('jwt.php');

$db = new Database;

// TOKENS FOR TG BOTS (GLOBAL and LOCAL)
define('BOT_TOKEN', '7993358429:AAH3EfKtSW7oqyN1fVWBAQsD6ehKZViF1do');
define('LOCAL_BOT_TOKEN', '8111791435:AAHs41kdMZ0PBkm2lt0lNavG9vI9xCiJ_FA');



if (!isset($_GET['hash'])) {
    die('Telegram hash not found');
}

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
    if($_SERVER['HTTP_HOST'] == 'dustore.ru'){
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


// User authentication - function
function userAuthentication($db, $auth_data)
{
    function createNewUser($db, $auth_data, $token)
    {
        // User not found, so create it
        $id = $db->Insert(
            "INSERT INTO `users`
                (`first_name`, `last_name`, `telegram_id`, `telegram_username`, `telegram_token`, `profile_picture`, `auth_date`, `global_role`)
                    values (:first_name, :last_name, :telegram_id, :telegram_username, :telegram_token, :profile_picture, :auth_date, 0)",
            [
                'first_name'        => $auth_data['first_name'],
                'last_name'         => $auth_data['last_name'],
                'telegram_id'       => $auth_data['id'],
                'telegram_username' => $auth_data['username'],
                'profile_picture'   => $auth_data['photo_url'],
                'auth_date'         => $auth_data['auth_date'],
                'telegram_token'    => $token
            ]
        );
    }

    function updateExistedUser($db, $auth_data, $token)
    {
        // User found, so update it
        $db->Update(
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

    // User checker - function
    function checkUserExists($db, $auth_data)
    {
        // Get the user Telegram ID
        $target_id = $auth_data['id'];

        // Check the user is exist in database or not
        $isUser = $db->Select(
            "SELECT `telegram_id`
                FROM `users`
                    WHERE `telegram_id` = :id",
            [
                'id' => $target_id
            ]
        );

        if (!empty($isUser) && $isUser[0]['telegram_id'] === $target_id) {
            return TRUE;
        }
    }


    $_SESSION['id'] = $auth_data['id'];
    $token = authUser($auth_data['id']);
    if(!empty($_COOKIE['auth_token'])){
        $token = $_COOKIE['auth_token'];
    }

    if (checkUserExists($db, $auth_data) == TRUE) {
        updateExistedUser($db, $auth_data, $token);
    } else {
        createNewUser($db, $auth_data, $token);
    }

    // Create logged in user session
    $_SESSION = [
        'logged-in' => TRUE,
        'telegram_id' => $auth_data['id'],
        'auth_token' => $token
    ];
}

try {
    $auth_data = checkTelegramAuthorization($_GET);

    userAuthentication($db, $auth_data);
} catch (Exception $e) {
    die($e->getMessage());
}

die(header('Location: /me'));

