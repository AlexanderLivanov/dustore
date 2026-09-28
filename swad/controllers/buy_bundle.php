<?php
declare(strict_types=1);

/* Тот же защитный каркас, что в buy_asset.php: ответ обязан быть JSON при
   любом исходе, иначе клиентский r.json() падает и кнопка "просто не работает". */
ob_start();

function reply(array $d): void {
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[assetstore] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        reply(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../../assetstore/_bundles.php';

if (empty($_SESSION['USERDATA']['id'])) {
    http_response_code(401);
    reply(['success' => false, 'error' => 'Требуется авторизация']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reply(['success' => false, 'error' => 'Method not allowed']);
}
if (!csrf_valid()) {
    http_response_code(403);
    reply(['success' => false, 'error' => 'Сессия устарела, обновите страницу']);
}

$db  = new Database();
$pdo = $db->connect();

$bundle_id = intval($_POST['bundle_id'] ?? 0);
$user_id   = (int)$_SESSION['USERDATA']['id'];

if ($bundle_id <= 0) {
    reply(['success' => false, 'error' => 'Invalid bundle_id']);
}

try {
    $bundle = asset_bundle_by_id($pdo, $bundle_id);
    if (!$bundle || $bundle['status'] !== 'published') {
        reply(['success' => false, 'error' => 'Набор не найден']);
    }
    if (!asset_bundle_items($pdo, $bundle_id)) {
        reply(['success' => false, 'error' => 'В наборе не осталось доступных ассетов']);
    }

    if (asset_bundle_is_owned($pdo, $user_id, $bundle_id)) {
        reply(['success' => true, 'already_owned' => true]);
    }

    $price = (float)$bundle['price'];

    if ($price <= 0) {
        $pdo->prepare("
            INSERT INTO asset_bundle_payments (bundle_id, user_id, amount, status, created_at)
            VALUES (?, ?, 0, 'succeeded', NOW())
            ON DUPLICATE KEY UPDATE status = 'succeeded'
        ")->execute([$bundle_id, $user_id]);
        asset_bundle_grant($pdo, $user_id, $bundle_id);
        reply(['success' => true]);
    }

    $shop_id    = defined('YOOKASSA_SHOP_ID')    ? YOOKASSA_SHOP_ID    : ($_ENV['YOOKASSA_SHOP_ID']    ?? '');
    $secret_key = defined('YOOKASSA_SECRET_KEY') ? YOOKASSA_SECRET_KEY : ($_ENV['YOOKASSA_SECRET_KEY'] ?? '');

    if (!$shop_id || !$secret_key) {
        if (defined('ASSETSTORE_DEV_FREE') && ASSETSTORE_DEV_FREE === true) {
            $pdo->prepare("
                INSERT INTO asset_bundle_payments (bundle_id, user_id, amount, status, created_at)
                VALUES (?, ?, ?, 'succeeded', NOW())
                ON DUPLICATE KEY UPDATE status = 'succeeded'
            ")->execute([$bundle_id, $user_id, $price]);
            asset_bundle_grant($pdo, $user_id, $bundle_id);
            reply(['success' => true, 'dev_mode' => true]);
        }
        error_log('[assetstore/buy_bundle] YooKassa keys are not configured');
        reply(['success' => false, 'error' => 'Оплата временно недоступна']);
    }

    $idempotency_key = uniqid('bundle_', true);
    $return_url      = "https://{$_SERVER['HTTP_HOST']}/assetstore/payment_success.php?bundle_id={$bundle_id}";

    $payload = [
        'amount'       => ['value' => number_format($price, 2, '.', ''), 'currency' => 'RUB'],
        'confirmation' => ['type' => 'redirect', 'return_url' => $return_url],
        'capture'      => true,
        'description'  => "Набор ассетов: {$bundle['name']} (ID {$bundle_id})",
        'metadata'     => ['kind' => 'bundle', 'bundle_id' => $bundle_id, 'user_id' => $user_id],
    ];

    $ch = curl_init('https://api.yookassa.ru/v3/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_USERPWD        => "{$shop_id}:{$secret_key}",
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Idempotence-Key: ' . $idempotency_key,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 && $http_code !== 201) {
        reply(['success' => false, 'error' => 'Ошибка платёжного сервиса']);
    }

    $data = json_decode($response, true);
    $payment_url = $data['confirmation']['confirmation_url'] ?? null;
    if (!$payment_url) {
        reply(['success' => false, 'error' => 'Не удалось получить ссылку оплаты']);
    }

    $pdo->prepare("
        INSERT INTO asset_bundle_payments (bundle_id, user_id, payment_id, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE payment_id = VALUES(payment_id), status = 'pending'
    ")->execute([$bundle_id, $user_id, $data['id'], $price]);

    reply(['success' => true, 'payment_url' => $payment_url]);

} catch (Throwable $e) {
    error_log('[assetstore/buy_bundle.php] ' . $e->getMessage());
    reply(['success' => false, 'error' => 'Внутренняя ошибка']);
}
