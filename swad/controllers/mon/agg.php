<?php
/**
 * swad/controllers/mon/agg.php — драйверы платёжных агрегаторов на чистом HTTP (без SDK):
 * один интерфейс — создать платёж, узнать статус, проверить ключи.
 *
 *   mon_agg_create($agg, $creds, $p)  → ['id', 'url', 'status', 'raw']
 *   mon_agg_status($agg, $creds, $id) → ['status' => pending|succeeded|canceled|refunded, 'raw']
 *   mon_agg_verify($agg, $creds, $mode) → null | текст ошибки
 *
 * Адреса API можно переопределить константами MON_YK_API / MON_TB_API (песочница, тесты).
 */
require_once __DIR__ . '/lib.php';

class MonAggError extends RuntimeException {}

function mon_api(string $agg): string
{
    return match ($agg) {
        'yookassa' => defined('MON_YK_API') ? MON_YK_API : 'https://api.yookassa.ru/v3/',
        'tbank'    => defined('MON_TB_API') ? MON_TB_API : 'https://securepay.tinkoff.ru/v2/',
        default    => throw new MonAggError('Агрегатор не поддерживается'),
    };
}

/** @return array{0:int,1:?array,2:string} код, JSON, сырое тело */
function mon_http(string $method, string $url, array $headers = [], ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new MonAggError('Нет связи с агрегатором: ' . $err);
    return [$code, json_decode((string)$raw, true), (string)$raw];
}

/** Проверка формы ключей до запроса: тестовые и боевые ключи у агрегаторов отличаются по виду. */
function mon_agg_clean_creds(string $agg, string $mode, array $in): array
{
    $e = [];
    if ($agg === 'yookassa') {
        $c = ['shop_id' => mon_digits($in['shop_id'] ?? ''), 'secret' => trim((string)($in['secret'] ?? ''))];
        if ($c['shop_id'] === '') $e['shop_id'] = 'shopId — число из раздела «Интеграция → Ключи API»';
        $want = $mode === 'test' ? 'test_' : 'live_';
        if (!str_starts_with($c['secret'], $want)) $e['secret'] = "Ключ для этого режима начинается с {$want} — тестовый берётся в тестовом магазине";
    } elseif ($agg === 'tbank') {
        $c = ['terminal' => trim((string)($in['terminal'] ?? '')), 'password' => trim((string)($in['password'] ?? ''))];
        $demo = str_ends_with($c['terminal'], 'DEMO');
        if ($c['terminal'] === '') $e['terminal'] = 'TerminalKey из личного кабинета Т-Бизнеса';
        elseif ($mode === 'test' && !$demo) $e['terminal'] = 'Тестовый терминал заканчивается на DEMO';
        elseif ($mode === 'live' && $demo)  $e['terminal'] = 'Это тестовый терминал (…DEMO) — нужен боевой';
        if ($c['password'] === '') $e['password'] = 'Пароль терминала';
    } else {
        return [[], ['agg' => 'Этот агрегатор пока недоступен']];
    }
    return [$c, $e];
}

/* ─────────────────────────────── ЮKassa ───────────────────────────────
   Basic-авторизация shopId:secret, Idempotence-Key защищает от двойного платежа при повторе запроса. */

function mon_yk(array $c, string $method, string $path, ?array $body = null, string $idem = ''): array
{
    $h = ['Authorization: Basic ' . base64_encode($c['shop_id'] . ':' . $c['secret'])];
    if ($idem !== '') $h[] = 'Idempotence-Key: ' . $idem;
    [$code, $json] = mon_http($method, mon_api('yookassa') . $path, $h, $body);
    if ($code >= 400 || !is_array($json)) {
        $msg = $json['description'] ?? ('HTTP ' . $code);
        throw new MonAggError($code === 401 ? 'ЮKassa: неверный shopId или секретный ключ' : 'ЮKassa: ' . $msg);
    }
    return $json;
}

function mon_yk_status(string $s): string
{
    return match ($s) { 'succeeded' => 'succeeded', 'canceled' => 'canceled', default => 'pending' };
}

/* ─────────────────────────────── Т-Касса ───────────────────────────────
   Подпись Token: берём только параметры верхнего уровня (вложенные Receipt/DATA — нет),
   добавляем Password, сортируем по ключу, склеиваем значения, SHA-256.
   Старый swad/controllers/TBankPay.php склеивал и вложенные массивы — с чеком подпись не сойдётся. */

function mon_tb_token(array $params, string $password): string
{
    $flat = ['Password' => $password];
    foreach ($params as $k => $v) {
        if ($k === 'Token' || is_array($v)) continue;
        $flat[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
    }
    ksort($flat);
    return hash('sha256', implode('', $flat));
}

function mon_tb(array $c, string $method, array $params): array
{
    $params = ['TerminalKey' => $c['terminal']] + $params;
    $params['Token'] = mon_tb_token($params, $c['password']);
    [$code, $json] = mon_http('POST', mon_api('tbank') . $method, [], $params);
    if (!is_array($json)) throw new MonAggError('Т-Касса: пустой ответ (HTTP ' . $code . ')');
    if (empty($json['Success'])) {
        $msg = trim(($json['Message'] ?? '') . ' ' . ($json['Details'] ?? ''));
        throw new MonAggError('Т-Касса: ' . ($msg ?: 'ошибка ' . ($json['ErrorCode'] ?? '?')));
    }
    return $json;
}

function mon_tb_status(string $s): string
{
    return match ($s) {
        'CONFIRMED' => 'succeeded',
        'REFUNDED', 'PARTIAL_REFUNDED' => 'refunded',
        'CANCELED', 'REJECTED', 'DEADLINE_EXPIRED', 'AUTH_FAIL', 'REVERSED' => 'canceled',
        default => 'pending',
    };
}

/* ─────────────────────────── Общий интерфейс ─────────────────────────── */

/**
 * $p: amount (₽), description, return_url, notify_url, order (уникальная строка), email?, vat? (none|5|7|22)
 */
function mon_agg_create(string $agg, array $c, array $p): array
{
    $amount = round((float)$p['amount'], 2);
    $desc = mb_substr($p['description'], 0, 120);
    if ($agg === 'yookassa') {
        $body = [
            'amount'       => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'RUB'],
            'capture'      => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $p['return_url']],
            'description'  => $desc,
            'metadata'     => ['mon_order' => $p['order']],
        ];
        if (!empty($p['email'])) {
            // vat_code: 1 — без НДС, 7 — 5%, 8 — 7%, 11 — 22% (справочник ЮKassa для 54-ФЗ, 2026)
            $vat = ['none' => 1, '5' => 7, '7' => 8, '22' => 11][$p['vat'] ?? 'none'] ?? 1;
            $body['receipt'] = ['customer' => ['email' => $p['email']], 'items' => [[
                'description' => $desc, 'quantity' => 1, 'amount' => $body['amount'], 'vat_code' => $vat,
                'payment_subject' => 'intellectual_activity', 'payment_mode' => 'full_payment',
            ]]];
        }
        $r = mon_yk($c, 'POST', 'payments', $body, 'dst-' . $p['order']);
        return ['id' => $r['id'], 'url' => $r['confirmation']['confirmation_url'] ?? '', 'status' => mon_yk_status($r['status'] ?? ''), 'raw' => $r];
    }
    if ($agg === 'tbank') {
        $params = [
            'Amount'          => (int)round($amount * 100),     // в копейках
            'OrderId'         => 'dst-' . $p['order'],
            'Description'     => $desc,
            'PayType'         => 'O',                           // одностадийная оплата
            'SuccessURL'      => $p['return_url'],
            'FailURL'         => $p['return_url'],
            'NotificationURL' => $p['notify_url'],
        ];
        if (!empty($p['email'])) {
            // Tax — значения API Т-Кассы; ставка 22% с 2026 года: сверить актуальное имя (vat22) в документации
            $tax = ['none' => 'none', '5' => 'vat5', '7' => 'vat7', '22' => 'vat22'][$p['vat'] ?? 'none'] ?? 'none';
            $params['Receipt'] = ['Email' => $p['email'], 'Taxation' => $p['taxation'] ?? 'usn_income', 'Items' => [[
                'Name' => $desc, 'Price' => $params['Amount'], 'Quantity' => 1, 'Amount' => $params['Amount'],
                'Tax' => $tax, 'PaymentObject' => 'intellectual_activity', 'PaymentMethod' => 'full_payment',
            ]]];
        }
        $r = mon_tb($c, 'Init', $params);
        return ['id' => (string)$r['PaymentId'], 'url' => $r['PaymentURL'] ?? '', 'status' => mon_tb_status($r['Status'] ?? ''), 'raw' => $r];
    }
    throw new MonAggError('Агрегатор не поддерживается');
}

function mon_agg_status(string $agg, array $c, string $id): array
{
    if ($agg === 'yookassa') {
        $r = mon_yk($c, 'GET', 'payments/' . rawurlencode($id));
        return ['status' => mon_yk_status($r['status'] ?? ''), 'raw' => $r];
    }
    if ($agg === 'tbank') {
        $r = mon_tb($c, 'GetState', ['PaymentId' => $id]);
        return ['status' => mon_tb_status($r['Status'] ?? ''), 'raw' => $r];
    }
    throw new MonAggError('Агрегатор не поддерживается');
}

/**
 * Проверка боевых ключей без денег. ЮKassa: GET /me — магазин отвечает и сообщает, тестовый ли он.
 * Т-Касса: создаём платёж на 1 ₽ и сразу отменяем — успешный Init значит, что ключи верны.
 */
function mon_agg_verify(string $agg, array $c, string $mode): ?string
{
    try {
        if ($agg === 'yookassa') {
            $me = mon_yk($c, 'GET', 'me');
            if ($mode === 'live' && !empty($me['test'])) return 'Это тестовый магазин — нужны ключи боевого';
            return null;
        }
        if ($agg === 'tbank') {
            $r = mon_tb($c, 'Init', ['Amount' => 100, 'OrderId' => 'dst-check-' . bin2hex(random_bytes(6)), 'Description' => 'Проверка ключей Dustore', 'PayType' => 'O']);
            try { mon_tb($c, 'Cancel', ['PaymentId' => (string)$r['PaymentId']]); } catch (MonAggError $e) {}
            return null;
        }
        return 'Агрегатор не поддерживается';
    } catch (MonAggError $e) {
        return $e->getMessage();
    }
}
