<?php
// TOKENS FOR USERS AUTH
define('SECRET_KEY', 'S+a2pTxd4NTyzC6HrmYUy6AEMIaq+jYfpqfJ5FqqM20=');
define('TOKEN_EXPIRE', 3600 * 24 * 3); // 3 days

function generateToken($telegram_id)
{

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'telegram_id' => $telegram_id,
        'iat' => time(),
        'exp' => time() + TOKEN_EXPIRE
    ]);

    $base64Header = base64_encode($header);
    $base64Payload = base64_encode($payload);

    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, SECRET_KEY, true);
    $base64Signature = base64_encode($signature);
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function validateToken($token)
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    list($base64Header, $base64Payload, $base64Signature) = $parts;

    // check signature
    $signature = base64_decode($base64Signature);
    $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, SECRET_KEY, true);

    if (!hash_equals($signature, $expectedSignature)) return false;

    $payload = json_decode(base64_decode($base64Payload), true);

    // check expiration date
    if (time() > $payload['exp']) return false;

    return $payload['telegram_id'];
}

function authUser($telegram_id)
{
    $token = generateToken($telegram_id);
    setcookie('auth_token', $token, [
        'expires' => time() + TOKEN_EXPIRE,
        'path' => '/',
        'secure' => ($_SERVER['HTTP_HOST'] != '127.0.0.1'), // На localhost без HTTPS
        'httponly' => true,
        'samesite' => 'Lax' // Для кросс-доменных запросов
    ]);
    return $token;
}