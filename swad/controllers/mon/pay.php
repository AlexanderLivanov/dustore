<?php
/**
 * swad/controllers/mon/pay.php — оплаты через агрегатор студии.
 *
 *   mon_game_checkout($db, $gameId, $userId, $email) — покупка игры (зовёт finv2/create_payment_game.php)
 *   mon_test_checkout($db, $studioId)                — тестовая оплата при подключении агрегатора
 *   mon_sync($db, $row)                               — спросить статус у агрегатора и применить
 *
 * Каждая оплата — строка mon_payments; статус всегда берём из API агрегатора, а не из того,
 * что прислали в вебхуке или вернули в адресе: так поддельный запрос ничего не «оплатит».
 */
require_once __DIR__ . '/agg.php';

class MonPayError extends RuntimeException {}

function mon_site(): string
{
    return defined('MON_SITE_URL') ? MON_SITE_URL : 'https://dustore.ru';
}

/** Ключи для оплаты: самозанятый продаёт через магазин платформы, ИП/ООО — через свой агрегатор. */
function mon_creds(array $p, string $mode): array
{
    if ($p['legal_type'] === 'npd') {
        if (!defined('YOOKASSA_SHOP_ID') || !defined('YOOKASSA_SHOP_KEY')) throw new MonPayError('Магазин платформы не настроен');
        return ['yookassa', ['shop_id' => (string)YOOKASSA_SHOP_ID, 'secret' => (string)YOOKASSA_SHOP_KEY], 'platform'];
    }
    $c = mon_decrypt($mode === 'test' ? ($p['agg_test'] ?? null) : ($p['agg_live'] ?? null));
    if (!$c || empty($p['agg'])) throw new MonPayError('Ключи агрегатора не заданы');
    return [$p['agg'], $c, 'studio'];
}

function mon_notify_url(int $studioId, string $agg): string
{
    return mon_site() . '/finv2/mon_hook.php?agg=' . $agg . '&s=' . $studioId . '&k=' . mon_hook_sig($studioId);
}

function mon_taxation(array $p): string
{
    return ['osno' => 'osn', 'usn6' => 'usn_income', 'usn15' => 'usn_income_outcome', 'psn' => 'patent'][$p['tax']['system'] ?? ''] ?? 'usn_income';
}

function mon_payment_row(PDO $db, int $id): ?array
{
    $st = $db->prepare("SELECT * FROM mon_payments WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Покупка игры. Возвращает ['payment_id' (id у агрегатора), 'payment_url', 'order_id'].
 * Бросает MonPayError с понятным игроку текстом.
 */
function mon_game_checkout(PDO $db, int $gameId, int $userId, string $email): array
{
    mon_schema($db);
    $g = $db->prepare("SELECT id, developer, name, price FROM games WHERE id = ? AND status = 'published'");
    $g->execute([$gameId]);
    $game = $g->fetch(PDO::FETCH_ASSOC);
    if (!$game) throw new MonPayError('Игра не найдена', 404);
    if ((float)$game['price'] <= 0) throw new MonPayError('Игра бесплатная', 400);

    $studio = (int)$game['developer'];
    $p = mon_profile($db, $studio);
    [$ok, $why] = mon_sell_state($p);
    if (!$ok) throw new MonPayError('Покупка временно недоступна: ' . mb_strtolower($why), 409);

    $price = mon_price($p, (float)$game['price'], mon_month_income($db, $studio));
    if ($price['fee'] > 0 && mon_balance($db, $studio)['total'] < $price['fee']) {
        throw new MonPayError('Покупка временно недоступна: у разработчика закончился баланс для оплаты инфраструктуры', 409);
    }

    [$agg, $creds] = mon_creds($p, 'live');

    $db->prepare("INSERT INTO game_orders (user_id, game_id, amount, status, created_at) VALUES (?, ?, ?, 'pending', NOW())")
       ->execute([$userId, $gameId, $price['total']]);
    $orderId = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO mon_payments (studio_id, game_id, user_id, order_id, purpose, agg, mode, amount, base_price, fee, fee_payer, tax_add)
                  VALUES (?, ?, ?, ?, 'game', ?, 'live', ?, ?, ?, ?, ?)")
       ->execute([$studio, $gameId, $userId, $orderId, $agg, $price['total'], $price['base'], $price['fee'],
                  $price['fee_by_player'] ? 'player' : 'dev', $price['tax_add']]);
    $mid = (int)$db->lastInsertId();

    try {
        $r = mon_agg_create($agg, $creds, [
            'amount'      => $price['total'],
            'description' => 'Игра «' . $game['name'] . '»',
            'return_url'  => mon_site() . '/finv2/success?order=' . $orderId,
            'notify_url'  => mon_notify_url($studio, $agg),
            'order'       => 'g' . $mid,
            'email'       => !empty($p['send_receipt']) ? $email : '',
            'vat'         => $p['legal_type'] === 'npd' ? 'none' : ($p['tax']['vat'] ?? 'none'),
            'taxation'    => mon_taxation($p),
        ]);
    } catch (MonAggError $e) {
        error_log('mon_game_checkout #' . $mid . ': ' . $e->getMessage());
        $db->prepare("UPDATE mon_payments SET status = 'canceled', raw = ? WHERE id = ?")->execute([$e->getMessage(), $mid]);
        $db->prepare("UPDATE game_orders SET status = 'canceled' WHERE id = ?")->execute([$orderId]);
        throw new MonPayError('Платёжная система не ответила. Попробуйте позже.', 502);
    }

    $db->prepare("UPDATE mon_payments SET external_id = ?, status = ?, confirm_url = ?, raw = ? WHERE id = ?")
       ->execute([$r['id'], $r['status'], $r['url'], json_encode($r['raw'], JSON_UNESCAPED_UNICODE), $mid]);
    $db->prepare("UPDATE game_orders SET payment_id = ? WHERE id = ?")->execute([$r['id'], $orderId]);

    return ['payment_id' => $r['id'], 'payment_url' => $r['url'], 'order_id' => $orderId];
}

/** Тестовая оплата тестовыми ключами: разработчик проходит её сам, тестовой картой агрегатора. */
function mon_test_checkout(PDO $db, int $studioId): array
{
    $p = mon_profile($db, $studioId);
    [$agg, $creds] = mon_creds($p, 'test');
    $db->prepare("INSERT INTO mon_payments (studio_id, purpose, agg, mode, amount, base_price) VALUES (?, 'agg_test', ?, 'test', ?, ?)")
       ->execute([$studioId, $agg, MON_TEST_AMOUNT, MON_TEST_AMOUNT]);
    $mid = (int)$db->lastInsertId();
    try {
        $r = mon_agg_create($agg, $creds, [
            'amount'      => MON_TEST_AMOUNT,
            'description' => 'Тестовая оплата Dustore',
            'return_url'  => mon_site() . '/devs/monetization?tab=agg&test=' . $mid,
            'notify_url'  => mon_notify_url($studioId, $agg),
            'order'       => 't' . $mid,
        ]);
    } catch (MonAggError $e) {
        $db->prepare("UPDATE mon_payments SET status = 'canceled', raw = ? WHERE id = ?")->execute([$e->getMessage(), $mid]);
        throw new MonPayError($e->getMessage());
    }
    $db->prepare("UPDATE mon_payments SET external_id = ?, status = ?, confirm_url = ?, raw = ? WHERE id = ?")
       ->execute([$r['id'], $r['status'], $r['url'], json_encode($r['raw'], JSON_UNESCAPED_UNICODE), $mid]);
    return ['id' => $mid, 'url' => $r['url']];
}

/** Спросить статус у агрегатора и применить. Возвращает актуальный статус. */
function mon_sync(PDO $db, array $row): string
{
    if (in_array($row['status'], ['succeeded', 'canceled', 'refunded'], true) || empty($row['external_id'])) return $row['status'];
    $p = mon_profile($db, (int)$row['studio_id']);
    try {
        [$agg, $creds] = mon_creds($p, $row['mode']);
        if ($agg !== $row['agg']) return $row['status'];          // агрегатор сменили — старые ключи уже не подходят
        $r = mon_agg_status($agg, $creds, $row['external_id']);
    } catch (Throwable $e) {
        error_log('mon_sync #' . $row['id'] . ': ' . $e->getMessage());
        return $row['status'];
    }
    mon_apply($db, $row, $r['status'], $r['raw']);
    return $r['status'];
}

/**
 * Применить статус. UPDATE … WHERE status <> новый — гонка двух проверок (вебхук + опрос со страницы)
 * решается базой: побочные эффекты выполнит только тот запрос, который реально сменил статус.
 */
function mon_apply(PDO $db, array $row, string $status, $raw = null): void
{
    $st = $db->prepare("UPDATE mon_payments SET status = ?, raw = ?, paid_at = IF(? = 'succeeded', NOW(), paid_at)
                        WHERE id = ? AND status <> ?");
    $st->execute([$status, json_encode($raw, JSON_UNESCAPED_UNICODE), $status, $row['id'], $status]);
    if ($st->rowCount() !== 1) return;

    if ($status === 'succeeded') {
        if ($row['purpose'] === 'game') mon_on_game_paid($db, $row);
        if ($row['purpose'] === 'agg_test') {
            $db->prepare("UPDATE mon_profiles SET agg_test_ok = NOW() WHERE studio_id = ? AND agg = ?")
               ->execute([$row['studio_id'], $row['agg']]);
        }
    } elseif ($status === 'canceled' && $row['purpose'] === 'game' && $row['order_id']) {
        $db->prepare("UPDATE game_orders SET status = 'canceled' WHERE id = ? AND status = 'pending'")->execute([$row['order_id']]);
    }
}

/** Игра оплачена: в библиотеку, заказ закрыт, сбор списан со счёта (сначала бонус, потом пополнения). */
function mon_on_game_paid(PDO $db, array $row): void
{
    $db->prepare("INSERT IGNORE INTO library (player_id, game_id, purchased, date) VALUES (?, ?, 1, NOW())")
       ->execute([$row['user_id'], $row['game_id']]);
    if ($row['order_id']) {
        $db->prepare("UPDATE game_orders SET status = 'succeeded', completed_at = NOW() WHERE id = ?")->execute([$row['order_id']]);
    }
    $fee = (float)$row['fee'];
    if ($fee <= 0 || $row['mode'] !== 'live') return;

    $studio = (int)$row['studio_id'];
    $db->beginTransaction();
    try {
        // блокируем профиль: две оплаты одновременно не поделят один и тот же бонус
        $db->prepare("SELECT studio_id FROM mon_profiles WHERE studio_id = ? FOR UPDATE")->execute([$studio]);
        $bonus = max(0, mon_balance($db, $studio)['bonus']);
        $fromBonus = min($bonus, $fee);
        $note = 'Сбор за игру #' . $row['game_id'] . ', оплата #' . $row['id'];
        if ($fromBonus > 0) mon_ledger_add($db, $studio, 'bonus', -$fromBonus, 'fee', 'fee:' . $row['id'] . ':bonus', (int)$row['id'], $note);
        if ($fee - $fromBonus > 0) mon_ledger_add($db, $studio, 'cash', -($fee - $fromBonus), 'fee', 'fee:' . $row['id'] . ':cash', (int)$row['id'], $note);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('mon_on_game_paid fee #' . $row['id'] . ': ' . $e->getMessage());
    }
}

/** Синхронизация по заказу — для finv2/success.php после возврата игрока. */
function mon_sync_order(PDO $db, int $orderId): void
{
    mon_schema($db);
    $st = $db->prepare("SELECT * FROM mon_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$orderId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) mon_sync($db, $row);
}
