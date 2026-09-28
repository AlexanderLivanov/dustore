<?php
session_start();
require_once('../../swad/config.php');
require_once(__DIR__ . '/../../assetstore/_bundles.php');

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

    // Набор — отдельная ветка: metadata.kind='bundle' ставит buy_bundle.php,
    // у обычной покупки ассета такого ключа нет вовсе.
    if (($meta['kind'] ?? '') === 'bundle') {
        $bundle_id = intval($meta['bundle_id'] ?? 0);
        $user_id   = intval($meta['user_id']   ?? 0);
        if ($status === 'succeeded' && $bundle_id && $user_id) {
            try {
                $pdo->prepare("UPDATE asset_bundle_payments SET status = 'succeeded' WHERE payment_id = ?")
                    ->execute([$pay_id]);
                asset_bundle_grant($pdo, $user_id, $bundle_id);
            } catch (Exception $e) {
                error_log('Bundle payment webhook error: ' . $e->getMessage());
            }
        }
        http_response_code(200);
        exit;
    }

    $asset_id = intval($meta['asset_id'] ?? 0);
    $user_id  = intval($meta['user_id']  ?? 0);
    $tier_id  = intval($meta['tier_id']  ?? 0);

    if ($status === 'succeeded' && $asset_id && $user_id) {
        try {
            // Добавляем в библиотеку. Вебхук — источник истины для "succeeded"
            // (может прийти раньше, чем пользователь вернётся на return_url),
            // поэтому tier_id тянем из metadata платежа, а не из сессии.
            $pdo->prepare("INSERT IGNORE INTO asset_library (player_id, asset_id, tier_id, date) VALUES (?, ?, ?, NOW())")
                ->execute([$user_id, $asset_id, $tier_id > 0 ? $tier_id : null]);
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
$asset_id  = intval($_GET['asset_id']  ?? 0);
$bundle_id = intval($_GET['bundle_id'] ?? 0);

if (empty($_SESSION['USERDATA']['id']) || (!$asset_id && !$bundle_id)) {
    header('Location: /assetstore/');
    exit;
}
$user_id = (int)$_SESSION['USERDATA']['id'];

if ($bundle_id) {
    // Иногда вебхук приходит раньше, чем пользователь вернётся на return_url.
    $paid = asset_bundle_is_owned($pdo, $user_id, $bundle_id);
    header("Location: /assetstore/bundle.php?id={$bundle_id}&" . ($paid ? 'paid=1' : 'pending=1'));
    exit;
}

$owned = $pdo->prepare("SELECT id FROM asset_library WHERE player_id = ? AND asset_id = ? LIMIT 1");
$owned->execute([$user_id, $asset_id]);

if ($owned->fetch()) {
    header("Location: /assetstore/asset.php?id={$asset_id}&paid=1");
} else {
    header("Location: /assetstore/asset.php?id={$asset_id}&pending=1");
}
exit;
