<?php
/**
 * swad/controllers/mon/lib.php — монетизация студий: схема, проверка реквизитов,
 * шифрование ключей агрегаторов, расчёт цены, виртуальный счёт.
 *
 * Модель денег:
 *  - игрок платит напрямую в агрегатор разработчика (ИП/ООО) или в магазин платформы (самозанятый);
 *  - инфраструктурный сбор платформы списывается с виртуального счёта студии (mon_ledger):
 *    сначала из стартового бонуса (не выводится), потом из пополнений;
 *  - сбор платит игрок (прибавляется к цене) или разработчик (цена не меняется).
 *
 * Подключение: require_once __DIR__ . '/../swad/controllers/mon/lib.php'; затем mon_schema($pdo).
 */

const MON_OFFER_VERSION = '2026-10-28';
const MON_BONUS_START   = 100;      // ₽ на счёт при активации, вывести нельзя
const MON_FEE_MIN       = 5;
const MON_FEE_MAX       = 50;
const MON_FEE_DEFAULT   = 7;
const MON_FEE_FREE_UPTO = 10000;    // ₽ дохода за календарный месяц, до которых сбор можно отключить
const MON_TAX_MIN       = 4;
const MON_TAX_MAX       = 22;
const MON_TEST_AMOUNT   = 10;       // ₽ — тестовая оплата при подключении агрегатора

const MON_TYPES = ['ip' => 'ИП', 'ooo' => 'ООО', 'npd' => 'Самозанятый'];

/** Агрегаторы: active=false — карточка видна, но выбрать нельзя. Поля — как в их кабинетах. */
const MON_AGGS = [
    'yookassa'  => ['label' => 'ЮKassa',    'active' => true,  'fields' => ['shop_id' => 'shopId', 'secret' => 'Секретный ключ']],
    'tbank'     => ['label' => 'Т-Касса',   'active' => true,  'fields' => ['terminal' => 'TerminalKey', 'password' => 'Пароль терминала']],
    'robokassa' => ['label' => 'Robokassa', 'active' => false, 'fields' => []],
    'mtspay'    => ['label' => 'МТС Pay',   'active' => false, 'fields' => []],
];

/** Налоговые режимы по типу: system => [название, описание налога] */
function mon_tax_systems(string $type): array
{
    $all = [
        'osno'  => ['ОСНО', $type === 'ooo' ? 'Налог на прибыль 25% + НДС 22%' : 'НДФЛ 13–15% с прибыли + НДС 22%'],
        'usn6'  => ['УСН «Доходы»', '6% с доходов (регион может снизить ставку)'],
        'usn15' => ['УСН «Доходы минус расходы»', '15% с разницы доходов и расходов'],
        'ausn'  => ['АУСН', '8% с доходов, НДС не платится'],
        'psn'   => ['Патент (ПСН)', 'Фиксированная стоимость патента, НДС не платится'],
    ];
    if ($type === 'ooo') unset($all['psn']);
    return $type === 'npd' ? [] : $all;
}

/** Варианты НДС для режима. Для ОСНО — только 22%, для АУСН/ПСН — без НДС. */
function mon_vat_options(string $system): array
{
    return match ($system) {
        'osno'          => ['22' => 'НДС 22%'],
        'usn6', 'usn15' => ['none' => 'Без НДС — доход за год до 20 млн ₽ (порог 2026 года)', '5' => 'НДС 5% без вычетов', '7' => 'НДС 7% без вычетов', '22' => 'НДС 22% с вычетами'],
        default         => ['none' => 'Без НДС'],
    };
}

/** Подсказка для ползунка «прибавлять налог продавца»: примерная доля налогов с выручки. */
function mon_tax_suggest(array $p): float
{
    $tax = $p['tax'] ?? [];
    $vat = is_numeric($tax['vat'] ?? null) ? (float)$tax['vat'] : 0;
    $pct = match ($p['legal_type'] === 'npd' ? 'npd' : ($tax['system'] ?? '')) {
        'npd'   => 4,
        'osno'  => 22,
        'usn6'  => 6 + $vat,
        'usn15' => $vat ?: 6,
        'ausn'  => 8,
        default => 4,
    };
    return max(MON_TAX_MIN, min(MON_TAX_MAX, $pct));
}

/* ═══════════════════════════ Схема ═══════════════════════════ */

function mon_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if ($db->query("SHOW TABLES LIKE 'mon_ledger'")->fetchColumn()) return;
    $sql = file_get_contents(__DIR__ . '/../../../migrations/0004_monetization.sql');
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', (string)$sql))) as $stmt) {
        if (stripos($stmt, 'CREATE TABLE') !== false) $db->exec($stmt);
    }
}

/* ═══════════════════════════ Проверка реквизитов ═══════════════════════════
   Контрольные суммы — те же, что проверяют банки и ФНС: опечатку ловим до отправки. */

function mon_digits(?string $s): string { return preg_replace('/\D/', '', (string)$s); }

function mon_inn_ok(string $inn): bool
{
    $d = array_map('intval', str_split($inn));
    $chk = fn(array $w) => array_sum(array_map(fn($k, $v) => $k * $v, $w, array_slice($d, 0, count($w)))) % 11 % 10;
    if (strlen($inn) === 10) return $chk([2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[9];
    if (strlen($inn) === 12) return $chk([7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[10]
                                 && $chk([3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[11];
    return false;
}

function mon_ogrn_ok(string $n): bool   // ООО: 13 цифр
{
    return strlen($n) === 13 && (int)substr($n, 0, 12) % 11 % 10 === (int)$n[12];
}

function mon_ogrnip_ok(string $n): bool // ИП: 15 цифр
{
    return strlen($n) === 15 && (int)substr($n, 0, 14) % 13 % 10 === (int)$n[14];
}

function mon_kpp_ok(string $k): bool { return (bool)preg_match('/^\d{4}[\dA-Z]{2}\d{3}$/', $k); }

function mon_bik_ok(string $b): bool { return (bool)preg_match('/^04\d{7}$/', $b); }

/** Ключ счёта: 23 цифры (3 из БИК + счёт) × веса 7,1,3…; сумма младших разрядов кратна 10. */
function mon_acc_key_ok(string $prefix, string $acc): bool
{
    $s = $prefix . $acc;
    if (strlen($s) !== 23) return false;
    $w = [7, 1, 3];
    $sum = 0;
    foreach (str_split($s) as $i => $c) $sum += ((int)$c * $w[$i % 3]) % 10;
    return $sum % 10 === 0;
}

function mon_account_ok(string $acc, string $bik): bool { return strlen($acc) === 20 && mon_acc_key_ok(substr($bik, -3), $acc); }
function mon_ks_ok(string $ks, string $bik): bool { return strlen($ks) === 20 && str_starts_with($ks, '301') && mon_acc_key_ok('0' . substr($bik, 4, 2), $ks); }

/**
 * Проверка анкеты. Возвращает [чистые данные, ошибки по полям].
 * $in = ['type', 'legal' => [...], 'tax' => [...], 'bank' => [...]]
 */
function mon_validate(array $in, bool $withBank = true): array
{
    $type = (string)($in['type'] ?? '');
    $e = [];
    if (!isset(MON_TYPES[$type])) return [[], ['type' => 'Выберите форму: ИП, ООО или самозанятость']];

    $L  = (array)($in['legal'] ?? []);
    $t  = fn($k, $max = 255) => trim(mb_substr((string)($L[$k] ?? ''), 0, $max));
    $legal = ['email' => $t('email', 120), 'phone' => mon_digits($L['phone'] ?? '')];

    if ($type === 'ooo') {
        $legal += ['name' => $t('name', 200), 'inn' => mon_digits($L['inn'] ?? ''), 'kpp' => strtoupper($t('kpp', 9)),
                   'ogrn' => mon_digits($L['ogrn'] ?? ''), 'director' => $t('director', 150), 'address' => $t('address', 300)];
        if (mb_strlen($legal['name']) < 3)        $e['legal.name'] = 'Укажите наименование, например ООО «Пыль»';
        if (!mon_inn_ok($legal['inn']) || strlen($legal['inn']) !== 10) $e['legal.inn'] = 'ИНН организации — 10 цифр, контрольная сумма не сходится';
        if (!mon_kpp_ok($legal['kpp']))           $e['legal.kpp'] = 'КПП — 9 символов';
        if (!mon_ogrn_ok($legal['ogrn']))         $e['legal.ogrn'] = 'ОГРН — 13 цифр, контрольная сумма не сходится';
        if (mb_strlen($legal['director']) < 5)    $e['legal.director'] = 'ФИО руководителя полностью';
        if (mb_strlen($legal['address']) < 10)    $e['legal.address'] = 'Юридический адрес';
    } else {
        $legal += ['fio' => $t('fio', 150), 'inn' => mon_digits($L['inn'] ?? '')];
        if (count(preg_split('/\s+/u', $legal['fio'])) < 2) $e['legal.fio'] = 'ФИО полностью, как в паспорте';
        if (!mon_inn_ok($legal['inn']) || strlen($legal['inn']) !== 12) $e['legal.inn'] = 'ИНН физлица — 12 цифр, контрольная сумма не сходится';
        if ($type === 'ip') {
            $legal += ['ogrnip' => mon_digits($L['ogrnip'] ?? ''), 'address' => $t('address', 300)];
            if (!mon_ogrnip_ok($legal['ogrnip'])) $e['legal.ogrnip'] = 'ОГРНИП — 15 цифр, контрольная сумма не сходится';
            if (mb_strlen($legal['address']) < 10) $e['legal.address'] = 'Адрес регистрации';
        }
    }
    if (!filter_var($legal['email'], FILTER_VALIDATE_EMAIL)) $e['legal.email'] = 'Почта для документов';
    if (strlen($legal['phone']) === 11 && $legal['phone'][0] === '8') $legal['phone'][0] = '7';
    if (strlen($legal['phone']) !== 11 || $legal['phone'][0] !== '7') $e['legal.phone'] = 'Телефон в формате +7…';

    $tax = [];
    if ($type !== 'npd') {
        $sys = (string)($in['tax']['system'] ?? '');
        $vat = (string)($in['tax']['vat'] ?? '');
        if (!isset(mon_tax_systems($type)[$sys])) $e['tax.system'] = 'Выберите систему налогообложения';
        elseif (!isset(mon_vat_options($sys)[$vat])) $vat = array_key_first(mon_vat_options($sys));
        $tax = ['system' => $sys, 'vat' => $vat];
    }

    $bank = [];
    if ($withBank) {
        [$bank, $be] = mon_validate_bank($type, (array)($in['bank'] ?? []), $type === 'ooo' ? ($legal['name'] ?? '') : ($legal['fio'] ?? ''));
        foreach ($be as $k => $v) $e['bank.' . $k] = $v;
    }
    return [['type' => $type, 'legal' => $legal, 'tax' => $tax, 'bank' => $bank], $e];
}

function mon_validate_bank(string $type, array $B, string $recipient): array
{
    $e = [];
    $bank = [
        'bank_name' => trim(mb_substr((string)($B['bank_name'] ?? ''), 0, 150)),
        'bik'       => mon_digits($B['bik'] ?? ''),
        'ks'        => mon_digits($B['ks'] ?? ''),
        'account'   => mon_digits($B['account'] ?? ''),
        'recipient' => $recipient,
    ];
    if (mb_strlen($bank['bank_name']) < 3) $e['bank_name'] = 'Название банка';
    if (!mon_bik_ok($bank['bik']))         $e['bik'] = 'БИК — 9 цифр, начинается с 04';
    elseif (!mon_ks_ok($bank['ks'], $bank['bik']))           $e['ks'] = 'Корр. счёт не сходится с БИК';
    elseif (!mon_account_ok($bank['account'], $bank['bik'])) $e['account'] = 'Номер счёта не сходится с БИК — проверьте цифры';
    $need = ['ip' => '40802', 'ooo' => '40702', 'npd' => '40817'][$type] ?? '';
    if (!isset($e['account']) && $need && !str_starts_with($bank['account'], $need) && !($type === 'npd' && str_starts_with($bank['account'], '40820'))) {
        $e['account'] = "Для этой формы счёт обычно начинается с {$need}" . ($type === 'npd' ? ' (счёт физлица)' : ' (расчётный счёт)');
    }
    return [$bank, $e];
}

/* ═══════════════════════════ Шифрование ключей ═══════════════════════════
   Тот же конверт enc1, что у чатов, но свой ключ: выводим его из MESSAGE_ENC_KEY через HKDF
   с другой «меткой». Утечка одного назначения не раскрывает другое, новый секрет в pass.php не нужен. */

function mon_key(): string
{
    if (!defined('MESSAGE_ENC_KEY')) throw new RuntimeException('MESSAGE_ENC_KEY не задан — см. swad/pass.php');
    $master = base64_decode(MESSAGE_ENC_KEY, true);
    if ($master === false || strlen($master) !== 32) throw new RuntimeException('MESSAGE_ENC_KEY должен быть 32 байта в base64');
    return hash_hkdf('sha256', $master, 32, 'dustore/mon/agg-credentials/v1');
}

function mon_encrypt(array $data): string
{
    $nonce = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt(json_encode($data), 'aes-256-gcm', mon_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    return 'enc1:' . base64_encode($nonce) . ':' . base64_encode($tag) . ':' . base64_encode($ct);
}

function mon_decrypt(?string $s): ?array
{
    if (!$s || !str_starts_with($s, 'enc1:')) return null;
    [, $n, $t, $c] = explode(':', $s) + [null, '', '', ''];
    $pt = openssl_decrypt(base64_decode($c), 'aes-256-gcm', mon_key(), OPENSSL_RAW_DATA, base64_decode($n), base64_decode($t));
    return $pt === false ? null : (json_decode($pt, true) ?: null);
}

/** Подпись для адреса вебхука студии: без неё чужой POST на наш URL отбрасывается сразу. */
function mon_hook_sig(int $studioId): string
{
    return substr(hash_hmac('sha256', 'hook:' . $studioId, mon_key()), 0, 24);
}

/* ═══════════════════════════ Профиль, цена, счёт ═══════════════════════════ */

function mon_profile(PDO $db, int $studioId): array
{
    mon_schema($db);
    $st = $db->prepare("SELECT * FROM mon_profiles WHERE studio_id = ?");
    $st->execute([$studioId]);
    $p = $st->fetch(PDO::FETCH_ASSOC) ?: ['studio_id' => $studioId, 'status' => 'draft', 'legal_type' => null, 'payments_enabled' => 0,
        'agg' => null, 'agg_test_ok' => null, 'agg_live_ok' => null, 'send_receipt' => 1, 'fee_payer' => 'player',
        'fee_pct' => MON_FEE_DEFAULT, 'fee_off' => 0, 'tax_on' => 0, 'tax_pct' => 6, 'legal' => null, 'tax' => null, 'bank' => null];
    foreach (['legal', 'tax', 'bank'] as $k) $p[$k] = json_decode((string)($p[$k] ?? ''), true) ?: [];
    return $p;
}

function mon_month_income(PDO $db, int $studioId): float
{
    $st = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM mon_payments
                        WHERE studio_id = ? AND purpose = 'game' AND mode = 'live' AND status = 'succeeded'
                          AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $st->execute([$studioId]);
    return (float)$st->fetchColumn();
}

/** Действующая ставка сбора: отключение работает, только пока доход за месяц меньше порога. */
function mon_fee_pct(array $p, float $monthIncome): float
{
    if (!empty($p['fee_off']) && $monthIncome < MON_FEE_FREE_UPTO) return 0.0;
    return max(MON_FEE_MIN, min(MON_FEE_MAX, (float)$p['fee_pct']));
}

/**
 * Разбор цены для игрока. Налог продавца прибавляется «изнутри»: итог = подытог / (1 − ставка),
 * иначе после уплаты налога с полной суммы у разработчика останется меньше подытога.
 */
function mon_price(array $p, float $base, float $monthIncome): array
{
    $base = max(0, round($base));
    $pct  = mon_fee_pct($p, $monthIncome);
    $fee  = ($base > 0 && $pct > 0) ? max(1, round($base * $pct / 100)) : 0;
    $byPlayer = ($p['fee_payer'] ?? 'player') !== 'dev';
    $sub  = $base + ($byPlayer ? $fee : 0);
    $total = $sub;
    if (!empty($p['tax_on']) && $sub > 0) {
        $t = max(MON_TAX_MIN, min(MON_TAX_MAX, (float)$p['tax_pct'])) / 100;
        $total = ceil($sub / (1 - $t));
    }
    return ['base' => $base, 'fee_pct' => $pct, 'fee' => $fee, 'fee_by_player' => $byPlayer, 'tax_add' => $total - $sub,
            'total' => $total, 'dev_gets' => $total, 'dev_net' => $total - $fee];
}

function mon_balance(PDO $db, int $studioId): array
{
    mon_schema($db);
    $st = $db->prepare("SELECT bucket, COALESCE(SUM(amount), 0) s FROM mon_ledger WHERE studio_id = ? GROUP BY bucket");
    $st->execute([$studioId]);
    $b = ['bonus' => 0.0, 'cash' => 0.0];
    foreach ($st as $r) $b[$r['bucket']] = (float)$r['s'];
    return $b + ['total' => $b['bonus'] + $b['cash']];
}

/** Проводка по счёту. ref уникален: повторный вызов с тем же ref ничего не делает. */
function mon_ledger_add(PDO $db, int $studioId, string $bucket, float $amount, string $kind, string $ref, ?int $paymentId = null, string $note = ''): bool
{
    $st = $db->prepare("INSERT IGNORE INTO mon_ledger (studio_id, bucket, amount, kind, ref, payment_id, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$studioId, $bucket, $amount, $kind, $ref, $paymentId, mb_substr($note, 0, 255)]);
    return $st->rowCount() === 1;
}

/** Можно ли продавать игры этой студии, и почему нет. */
function mon_sell_state(array $p): array
{
    if (($p['status'] ?? '') !== 'active') return [false, 'Монетизация студии не подключена'];
    if (empty($p['payments_enabled']))     return [false, 'Приём платежей приостановлен'];
    if ($p['legal_type'] === 'npd')        return [true, ''];
    if (empty($p['agg']) || empty($p['agg_live_ok'])) return [false, 'Платёжный агрегатор не подключён'];
    return [true, ''];
}

function mon_money(float $v): string { return number_format($v, 0, ',', ' ') . ' ₽'; }

/** Цена для витрины: то, что реально заплатит игрок. Без подключённой монетизации — как раньше. */
function mon_public_price(PDO $db, array $game): float
{
    $base = (float)($game['price'] ?? 0);
    if ($base <= 0 || empty($game['developer'])) return $base;
    try {
        $p = mon_profile($db, (int)$game['developer']);
        if (($p['status'] ?? '') !== 'active') return $base;
        return mon_price($p, $base, mon_month_income($db, (int)$game['developer']))['total'];
    } catch (Throwable $e) {
        error_log('mon_public_price: ' . $e->getMessage());
        return $base;
    }
}
