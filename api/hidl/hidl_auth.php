<?php
session_start();

require_once('../../swad/config.php');

$db = new Database();

if (empty($_SESSION['USERDATA'])) {
    header("Location: /login?backUrl=/api/hidl/hidl_auth");
    exit;
}

$user = $_SESSION['USERDATA'];

$token = bin2hex(random_bytes(32));

$db->connect()->prepare("
    INSERT INTO desktop_tokens (user_id, token, created_at)
    VALUES (?, ?, NOW())
")->execute([$user['id'], $token]);

echo $token;
header("Location: http://127.0.0.1:8000/auth_success?token=" . $token);
exit;