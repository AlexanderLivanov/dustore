<?php
try {

    require_once('../vendor/autoload.php');
    require_once('../swad/config.php');
    require_once('../swad/controllers/send_email.php');

    header('Content-Type: application/json');

    $data = json_decode($raw, true);

    if (!$data) {
        throw new Exception('Invalid JSON');
    }

    /* ── Only handle payment.succeeded ── */
    if (($data['event'] ?? '') !== 'payment.succeeded') {
        http_response_code(200);
        echo json_encode(['ok' => true, 'skipped' => true]);
        exit;
    }

    $payment   = $data['object'];
    $paymentId = $payment['id'];

    $meta    = $payment['metadata'] ?? [];
    $type    = $meta['type'] ?? '';
    $orderId = (int)($meta['order_id'] ?? 0);
    $gameId  = (int)($meta['game_id'] ?? 0);
    $userId  = (int)($meta['user_id'] ?? 0);

    file_put_contents(__DIR__ . '/webhook_debug.log',
        date('Y-m-d H:i:s') . " STEP: parsed | paymentId={$paymentId} orderId={$orderId}\n",
        FILE_APPEND
    );

    $db  = new Database();
    $pdo = $db->connect();

    /* ── MAIN FIX: работаем по payment_id ── */
    $stmt = $pdo->prepare("SELECT id, status FROM game_orders WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        file_put_contents(__DIR__ . '/webhook_debug.log',
            date('Y-m-d H:i:s') . " ERROR: order not found by payment_id={$paymentId}\n",
            FILE_APPEND
        );

        // не падаем — просто выходим
        http_response_code(200);
        echo json_encode(['ok' => false, 'reason' => 'order_not_found']);
        exit;
    }

    if ($order['status'] === 'succeeded') {
        file_put_contents(__DIR__ . '/webhook_debug.log',
            date('Y-m-d H:i:s') . " INFO: already succeeded id={$order['id']}\n",
            FILE_APPEND
        );

        http_response_code(200);
        echo json_encode(['ok' => true, 'idempotent' => true]);
        exit;
    }

    file_put_contents(__DIR__ . '/webhook_debug.log',
        date('Y-m-d H:i:s') . " STEP: updating order id={$order['id']}\n",
        FILE_APPEND
    );

    /* ── Add game ── */
    $pdo->prepare("
        INSERT IGNORE INTO library (player_id, game_id, purchased, date)
        VALUES (?, ?, 1, NOW())
    ")->execute([$userId, $gameId]);

    /* ── Update order ── */
    $pdo->prepare("
        UPDATE game_orders
        SET status = 'succeeded', completed_at = NOW()
        WHERE payment_id = ?
    ")->execute([$paymentId]);

    file_put_contents(__DIR__ . '/webhook_debug.log',
        date('Y-m-d H:i:s') . " SUCCESS: order updated payment_id={$paymentId}\n",
        FILE_APPEND
    );

    /* ── Email (не критично) ── */
    try {
        $gameRow = $pdo->prepare("SELECT name FROM games WHERE id = ?");
        $gameRow->execute([$gameId]);
        $gameName = $gameRow->fetchColumn() ?: 'Игра';

        $userRow = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $userRow->execute([$userId]);
        $user = $userRow->fetch(PDO::FETCH_ASSOC);

        if (!empty($user['email'])) {
            $amount = $payment['amount']['value'] ?? '?';

            sendMail(
                $user['email'],
                'Покупка игры «' . $gameName . '» прошла успешно',
                buildGamePurchaseEmail($user['username'] ?? 'Игрок', $gameName, $amount),
                ''
            );
        }
    } catch (Throwable $e) {
        file_put_contents(__DIR__ . '/webhook_debug.log',
            date('Y-m-d H:i:s') . " EMAIL ERROR: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {

    file_put_contents(__DIR__ . '/webhook_debug.log',
        date('Y-m-d H:i:s') . " FATAL: " . $e->getMessage() . "\n",
        FILE_APPEND
    );

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}