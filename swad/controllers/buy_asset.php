<?php
session_start();
header('Content-Type: application/json');
require_once('../../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db  = new Database();
$pdo = $db->connect();

$asset_id = intval($_POST['asset_id'] ?? 0);
$user_id  = (int)$_SESSION['USERDATA']['id'];

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset_id']);
    exit;
}

try {
    // Получаем данные ассета
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        echo json_encode(['success' => false, 'error' => 'Ассет не найден']);
        exit;
    }

    // Уже куплен?
    $exists = $pdo->prepare("SELECT id FROM asset_library WHERE player_id = ? AND asset_id = ? LIMIT 1");
    $exists->execute([$user_id, $asset_id]);
    if ($exists->fetch()) {
        echo json_encode(['success' => true, 'already_owned' => true]);
        exit;
    }

    $price = (float)$asset['price'];

    // Бесплатный — просто добавляем
    if ($price <= 0) {
        $pdo->prepare("INSERT INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
            ->execute([$user_id, $asset_id]);
        $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
            ->execute([$asset_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── ЮКасса интеграция ─────────────────────────────────────────────
    // Загрузи .env или config с ключами
    $shop_id    = defined('YOOKASSA_SHOP_ID')    ? YOOKASSA_SHOP_ID    : ($_ENV['YOOKASSA_SHOP_ID']    ?? '');
    $secret_key = defined('YOOKASSA_SECRET_KEY') ? YOOKASSA_SECRET_KEY : ($_ENV['YOOKASSA_SECRET_KEY'] ?? '');

    if (!$shop_id || !$secret_key) {
        // Fallback: без платёжки просто добавляем (для dev)
        $pdo->prepare("INSERT INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
            ->execute([$user_id, $asset_id]);
        $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
            ->execute([$asset_id]);
        echo json_encode(['success' => true, 'dev_mode' => true]);
        exit;
    }

    $idempotency_key = uniqid('asset_', true);
    $return_url      = "https://{$_SERVER['HTTP_HOST']}/assetstore/payment_success.php?asset_id={$asset_id}";

    $payload = [
        'amount'      => ['value' => number_format($price, 2, '.', ''), 'currency' => 'RUB'],
        'confirmation'=> ['type' => 'redirect', 'return_url' => $return_url],
        'capture'     => true,
        'description' => "Ассет: {$asset['name']} (ID {$asset_id})",
        'metadata'    => ['asset_id' => $asset_id, 'user_id' => $user_id],
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
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 && $http_code !== 201) {
        echo json_encode(['success' => false, 'error' => 'Ошибка платёжного сервиса']);
        exit;
    }

    $data = json_decode($response, true);
    $payment_url = $data['confirmation']['confirmation_url'] ?? null;

    if (!$payment_url) {
        echo json_encode(['success' => false, 'error' => 'Не удалось получить ссылку оплаты']);
        exit;
    }

    // Сохраняем pending-платёж
    $pdo->prepare("
        INSERT INTO asset_payments (asset_id, user_id, payment_id, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE payment_id = VALUES(payment_id), status = 'pending'
    ")->execute([$asset_id, $user_id, $data['id'], $price]);

    echo json_encode(['success' => true, 'payment_url' => $payment_url]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
