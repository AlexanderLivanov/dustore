<?php
session_start();
require_once('../../swad/config.php');

$db  = new Database();
$pdo = $db->connect();

// ── Webhook от ЮКасса (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data || $data['type'] !== 'notification') {
        http_response_code(400);
        exit;
    }

    $payment  = $data['object'] ?? [];
    $pay_id   = $payment['id']     ?? '';
    $status   = $payment['status'] ?? '';
    $meta     = $payment['metadata'] ?? [];
    $asset_id = intval($meta['asset_id'] ?? 0);
    $user_id  = intval($meta['user_id']  ?? 0);

    if ($status === 'succeeded' && $asset_id && $user_id) {
        try {
            // Добавляем в библиотеку
            $pdo->prepare("INSERT IGNORE INTO asset_library (player_id, asset_id, date) VALUES (?, ?, NOW())")
                ->execute([$user_id, $asset_id]);
            // Обновляем статус платежа
            $pdo->prepare("UPDATE asset_payments SET status = 'succeeded' WHERE payment_id = ?")
                ->execute([$pay_id]);
            // Счётчик
            $pdo->prepare("UPDATE assets SET downloads_count = downloads_count + 1 WHERE id = ?")
                ->execute([$asset_id]);
        } catch (Exception $e) {
            error_log('Payment webhook error: ' . $e->getMessage());
        }
    }
    http_response_code(200);
    exit;
}

// ── Redirect после оплаты (GET) ───────────────────────────────────────
$asset_id = intval($_GET['asset_id'] ?? 0);

if (empty($_SESSION['USERDATA']['id']) || !$asset_id) {
    header('Location: /assetstore/');
    exit;
}

// Проверяем статус платежа (иногда вебхук приходит раньше)
$user_id = (int)$_SESSION['USERDATA']['id'];
$owned = $pdo->prepare("SELECT id FROM asset_library WHERE player_id = ? AND asset_id = ? LIMIT 1");
$owned->execute([$user_id, $asset_id]);

if ($owned->fetch()) {
    header("Location: /assetstore/asset.php?id={$asset_id}&paid=1");
} else {
    header("Location: /assetstore/asset.php?id={$asset_id}&pending=1");
}
exit;
