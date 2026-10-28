<?php
/**
 * devs/monetization.php — монетизация студии.
 *
 * Пока не подключено: анкета (форма → данные → налоги → реквизиты → договор) → заявка
 * на проверку администратору (devs/mon_review.php). После активации — вкладки:
 * «Обзор», «Реквизиты» (изменения тоже через проверку), «Агрегатор» (только ИП/ООО),
 * «Цена и сбор», «Баланс», «Страница студии» (донаты, закрытая комната, ссылки — как раньше).
 *
 * Действия монетизации (поле mon) обрабатываются до header.php и уходят редиректом (PRG),
 * разделы страницы студии (поле section) — как раньше, после шапки.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';
require_once __DIR__ . '/../swad/controllers/mon/pay.php';
require_once __DIR__ . '/../swad/controllers/mon/offer.php';

function mn_back(string $tab = '', array $flash = []): never
{
    if ($flash) $_SESSION['mon_flash'] = $flash;
    header('Location: /devs/monetization' . ($tab ? '?tab=' . $tab : ''));
    exit();
}

function mn_prefix(array $errors, string $prefix): array
{
    $out = [];
    foreach ($errors as $k => $v) $out[$prefix . $k] = $v;
    return $out;
}

$mn_studio = (int)($_SESSION['studio_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mon']) && $mn_studio && !empty($_SESSION['USERDATA'])) {
    $pdo = (new Database())->connect();
    mon_schema($pdo);
    $act = (string)$_POST['mon'];
    $tab = preg_replace('/[^a-z]/', '', (string)($_POST['tab'] ?? ''));
    $uid = (int)($_SESSION['USERDATA']['id'] ?? 0);

    $r = $pdo->prepare("SELECT role FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
    $r->execute([(string)($_SESSION['USERDATA']['telegram_id'] ?? ''), $mn_studio]);
    $owner = $r->fetchColumn() === 'Владелец' || (int)($_SESSION['USERDATA']['global_role'] ?? 0) === -1;

    if (!csrf_valid()) mn_back($tab, ['err', 'Сессия устарела — обновите страницу и повторите.']);
    if (!$owner)       mn_back($tab, ['err', 'Монетизацию меняет только владелец студии.']);
    $p = mon_profile($pdo, $mn_studio);

    // ── Анкета ──
    if ($act === 'apply') {
        if (in_array($p['status'], ['pending', 'active'], true)) mn_back('', ['err', 'Анкета уже отправлена.']);
        [$clean, $errs] = mon_validate($_POST);
        if (empty($_POST['offer_accept'])) $errs['offer'] = 'Чтобы отправить анкету, примите договор';
        if ($errs) {
            $_SESSION['mon_form'] = ['in' => $_POST, 'errors' => $errs];
            mn_back('', ['err', 'Проверьте поля, отмеченные красным.']);
        }
        $pdo->prepare("INSERT INTO mon_requests (studio_id, kind, payload, user_id, offer_version, offer_hash, ip) VALUES (?, 'onboarding', ?, ?, ?, ?, ?)")
            ->execute([$mn_studio, json_encode($clean, JSON_UNESCAPED_UNICODE), $uid, MON_OFFER_VERSION, mon_offer_hash($clean['type']), $_SERVER['REMOTE_ADDR'] ?? '']);
        $pdo->prepare("INSERT INTO mon_profiles (studio_id, legal_type, status) VALUES (?, ?, 'pending')
                       ON DUPLICATE KEY UPDATE legal_type = VALUES(legal_type), status = 'pending'")
            ->execute([$mn_studio, $clean['type']]);
        mn_back('', ['ok', 'Анкета отправлена на проверку. Когда включим приём платежей, здесь откроются настройки.']);
    }
    if ($act === 'withdraw' && $p['status'] === 'pending') {
        $pdo->prepare("UPDATE mon_requests SET status = 'withdrawn' WHERE studio_id = ? AND kind = 'onboarding' AND status = 'pending'")->execute([$mn_studio]);
        $pdo->prepare("UPDATE mon_profiles SET status = 'draft' WHERE studio_id = ?")->execute([$mn_studio]);
        mn_back('', ['ok', 'Анкета отозвана — исправьте и отправьте заново.']);
    }
    if ($p['status'] !== 'active') mn_back('', ['err', 'Сначала подключите монетизацию.']);

    // ── Реквизиты: каждое изменение — новая заявка на проверку ──
    if ($act === 'bank') {
        $recipient = $p['legal_type'] === 'ooo' ? ($p['legal']['name'] ?? '') : ($p['legal']['fio'] ?? '');
        [$bank, $e] = mon_validate_bank($p['legal_type'], (array)($_POST['bank'] ?? []), $recipient);
        if ($e) {
            $_SESSION['mon_form'] = ['in' => $_POST, 'errors' => mn_prefix($e, 'bank.')];
            mn_back('bank', ['err', 'Реквизиты не прошли проверку.']);
        }
        if ($bank === array_intersect_key($p['bank'], $bank)) mn_back('bank', ['err', 'Реквизиты не изменились.']);
        $pdo->prepare("UPDATE mon_requests SET status = 'withdrawn' WHERE studio_id = ? AND kind = 'bank' AND status = 'pending'")->execute([$mn_studio]);
        $pdo->prepare("INSERT INTO mon_requests (studio_id, kind, payload, user_id, ip) VALUES (?, 'bank', ?, ?, ?)")
            ->execute([$mn_studio, json_encode(['bank' => $bank], JSON_UNESCAPED_UNICODE), $uid, $_SERVER['REMOTE_ADDR'] ?? '']);
        mn_back('bank', ['ok', 'Новые реквизиты отправлены на проверку. До одобрения действуют прежние.']);
    }

    // ── Агрегатор (ИП/ООО) ──
    if ($p['legal_type'] !== 'npd') {
        if ($act === 'agg_select') {
            $agg = (string)($_POST['agg'] ?? '');
            if (!(MON_AGGS[$agg]['active'] ?? false)) mn_back('agg', ['err', 'Этот агрегатор пока недоступен.']);
            if ($agg !== $p['agg']) {
                $pdo->prepare("UPDATE mon_profiles SET agg = ?, agg_test = NULL, agg_test_ok = NULL, agg_live = NULL, agg_live_ok = NULL WHERE studio_id = ?")
                    ->execute([$agg, $mn_studio]);
            }
            mn_back('agg', ['ok', MON_AGGS[$agg]['label'] . ' выбрана. Дальше — тестовые ключи.']);
        }
        if ($act === 'agg_test' || $act === 'agg_live') {
            $mode = $act === 'agg_test' ? 'test' : 'live';
            if (!$p['agg']) mn_back('agg', ['err', 'Сначала выберите агрегатора.']);
            if ($mode === 'live' && !$p['agg_test_ok']) mn_back('agg', ['err', 'Сначала пройдите тестовую оплату.']);
            [$c, $e] = mon_agg_clean_creds($p['agg'], $mode, (array)($_POST['creds'] ?? []));
            if ($e) {
                $_SESSION['mon_form'] = ['in' => ['creds' => [$mode => array_diff_key((array)($_POST['creds'] ?? []), ['secret' => 1, 'password' => 1])]], 'errors' => mn_prefix($e, "creds.$mode.")];
                mn_back('agg', ['err', reset($e)]);
            }
            if ($mode === 'test') {
                $pdo->prepare("UPDATE mon_profiles SET agg_test = ?, agg_test_ok = NULL WHERE studio_id = ?")->execute([mon_encrypt($c), $mn_studio]);
                mn_back('agg', ['ok', 'Тестовые ключи сохранены. Теперь проведите тестовую оплату.']);
            }
            if ($err = mon_agg_verify($p['agg'], $c, 'live')) mn_back('agg', ['err', $err]);
            $pdo->prepare("UPDATE mon_profiles SET agg_live = ?, agg_live_ok = NOW() WHERE studio_id = ?")->execute([mon_encrypt($c), $mn_studio]);
            mn_back('agg', ['ok', 'Боевые ключи проверены — оплаты за игры пойдут через ' . MON_AGGS[$p['agg']]['label'] . '.']);
        }
        if ($act === 'receipt') {
            $pdo->prepare("UPDATE mon_profiles SET send_receipt = ? WHERE studio_id = ?")->execute([empty($_POST['send_receipt']) ? 0 : 1, $mn_studio]);
            mn_back('agg', ['ok', 'Настройка чеков сохранена.']);
        }
    }

    // ── Цена и сбор ──
    if ($act === 'pricing') {
        $feeOff = empty($_POST['fee_off']) ? 0 : 1;
        if ($feeOff && mon_month_income($pdo, $mn_studio) >= MON_FEE_FREE_UPTO) {
            mn_back('pricing', ['err', 'Отключить сбор можно, пока доход за месяц меньше ' . mon_money(MON_FEE_FREE_UPTO) . '.']);
        }
        $pdo->prepare("UPDATE mon_profiles SET fee_payer = ?, fee_pct = ?, fee_off = ?, tax_on = ?, tax_pct = ? WHERE studio_id = ?")->execute([
            ($_POST['fee_payer'] ?? '') === 'dev' ? 'dev' : 'player',
            max(MON_FEE_MIN, min(MON_FEE_MAX, round((float)($_POST['fee_pct'] ?? MON_FEE_DEFAULT)))),
            $feeOff,
            empty($_POST['tax_on']) ? 0 : 1,
            max(MON_TAX_MIN, min(MON_TAX_MAX, round((float)($_POST['tax_pct'] ?? 6)))),
            $mn_studio,
        ]);
        mn_back('pricing', ['ok', 'Сохранено — цены на витрине уже пересчитаны.']);
    }
    mn_back($tab);
}

$page_title = 'Монетизация';
$active_nav = 'monetization';
require_once(__DIR__ . '/includes/header.php');
require_once(__DIR__ . '/../swad/fx/boot.php');

$conn = $db->connect();
Fx::use($conn);
mon_schema($conn);

$stmt = $conn->prepare("SELECT * FROM studios WHERE id = ?");
$stmt->execute([$studio_id]);
$pay = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$success_msg = '';
$error_msg   = '';

/* Донаты, закрытая комната и ссылки обратной связи — то, что видно на публичной странице студии */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string)($_POST['section'] ?? ''), 'fx_')) {
    $section = (string)$_POST['section'];
    if (!csrf_valid()) {
        $error_msg = 'Сессия устарела — обновите страницу и повторите.';
    } elseif (!FxAuth::isStudioOwner($studio_id, (int)$user_id)) {
        $error_msg = 'Менять это может владелец или администратор студии.';
    } else {
        $cur = FxMonet::get($studio_id);
        if ($section === 'fx_donate') {
            $cur['donate_on']   = !empty($_POST['donate_on']) ? 1 : 0;
            $cur['donate_url']  = trim((string)($_POST['donate_url'] ?? ''));
            $cur['donate_note'] = (string)($_POST['donate_note'] ?? '');
            FxMonet::save($studio_id, $cur);
            $success_msg = 'Настройки донатов сохранены.';
        } elseif ($section === 'fx_room') {
            $cur['room_on']    = !empty($_POST['room_on']) ? 1 : 0;
            $cur['room_title'] = (string)($_POST['room_title'] ?? '');
            $cur['room_desc']  = (string)($_POST['room_desc'] ?? '');
            $cur['room_price'] = (string)($_POST['room_price'] ?? '');
            FxMonet::save($studio_id, $cur);
            $success_msg = 'Закрытая комната сохранена.';
        } elseif ($section === 'fx_links') {
            $rows = [];
            foreach ((array)($_POST['link_label'] ?? []) as $i => $label) {
                $rows[] = ['label' => (string)$label, 'url' => (string)($_POST['link_url'][$i] ?? '')];
            }
            FxMonet::saveLinks($studio_id, $rows);
            $success_msg = 'Ссылки обратной связи сохранены.';
        }
    }
}

if ($f = $_SESSION['mon_flash'] ?? null) {
    $f[0] === 'ok' ? $success_msg = $f[1] : $error_msg = $f[1];
    unset($_SESSION['mon_flash']);
}
$form = $_SESSION['mon_form'] ?? null;
unset($_SESSION['mon_form']);
$errs = $form['errors'] ?? [];

$p       = mon_profile($conn, $studio_id);
$can     = $is_owner;
$type    = $p['legal_type'] ?? '';
$income  = mon_month_income($conn, $studio_id);
$balance = mon_balance($conn, $studio_id);

$reqs = $conn->prepare("SELECT * FROM mon_requests WHERE studio_id = ? ORDER BY id DESC LIMIT 30");
$reqs->execute([$studio_id]);
$reqs = $reqs->fetchAll(PDO::FETCH_ASSOC);
$last_onb  = current(array_filter($reqs, fn($r) => $r['kind'] === 'onboarding')) ?: null;
$bank_reqs = array_values(array_filter($reqs, fn($r) => $r['kind'] === 'bank'));
$bank_pend = current(array_filter($bank_reqs, fn($r) => $r['status'] === 'pending')) ?: null;

// Данные для анкеты: после ошибки — введённое, иначе последняя заявка, иначе старые поля студии
$wz = $form['in'] ?? (json_decode((string)($last_onb['payload'] ?? ''), true) ?: [
    'type'  => '',
    'legal' => ['inn' => $pay['INN'] ?? ''],
    'bank'  => ['bank_name' => $pay['bank_name'] ?? '', 'bik' => $pay['BIC'] ?? '', 'account' => $pay['acc_num'] ?? ''],
]);
$v  = function (string $path, $src = null) use (&$wz) {
    $cur = $src ?? $wz;
    foreach (explode('.', $path) as $k) $cur = is_array($cur) ? ($cur[$k] ?? '') : '';
    return htmlspecialchars(is_scalar($cur) ? (string)$cur : '');
};
$er = fn(string $k) => isset($errs[$k]) ? '<div class="mn-err">' . htmlspecialchars($errs[$k]) . '</div>' : '';
$ec = fn(string $k) => isset($errs[$k]) ? ' mn-bad' : '';

$tabs = ['overview' => ['dashboard', 'Обзор'], 'bank' => ['account_balance', 'Реквизиты']];
if ($type && $type !== 'npd') $tabs['agg'] = ['credit_card', 'Агрегатор'];
$tabs += ['pricing' => ['sell', 'Цена и сбор'], 'balance' => ['account_balance_wallet', 'Баланс'], 'page' => ['storefront', 'Страница студии']];
$is_active = $p['status'] === 'active';
$tab = (string)($_GET['tab'] ?? '');
if (!isset($tabs[$tab])) $tab = $is_active ? 'overview' : 'page';
if (!$is_active && $tab !== 'page') $tab = 'page';
[$sell_ok, $sell_why] = mon_sell_state($p);

function mn_status_badge(string $s): string
{
    [$cls, $t] = ['pending' => ['badge-rev', 'на проверке'], 'approved' => ['badge-pub', 'одобрено'], 'rejected' => ['badge-err', 'отклонено'],
                  'withdrawn' => ['badge-draft', 'отозвано']][$s] ?? ['badge-draft', $s];
    return "<span class=\"badge {$cls}\">{$t}</span>";
}

function mn_mask(string $acc): string { return strlen($acc) > 8 ? substr($acc, 0, 5) . ' •••• ' . substr($acc, -4) : $acc; }
?>

<?php if ($success_msg): ?><div class="alert alert-ok"><span class="material-icons">check_circle</span><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><span class="material-icons">error</span><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
<?php if (!$can): ?>
<div class="alert" style="background:rgba(255,255,255,.04);border:1px solid var(--bd);color:var(--ts);">
    <span class="material-icons">lock</span>Смотреть можно всем сотрудникам, менять — только владелец студии.
</div>
<?php endif; ?>

<?php if (in_array($p['status'], ['draft', 'rejected'], true)): /* ═══════════ АНКЕТА ═══════════ */ ?>

<?php if ($p['status'] === 'rejected' && $last_onb): ?>
<div class="alert alert-err"><span class="material-icons">report</span>
    <div>Анкета отклонена<?= $last_onb['comment'] ? ': ' . htmlspecialchars($last_onb['comment']) : '' ?>. Исправьте и отправьте снова.</div>
</div>
<?php endif; ?>

<div class="mn-hero">
    <div>
        <div class="mn-hero-title">Подключите продажу игр</div>
        <div class="mn-hero-sub">Пять шагов: форма, данные, налоги, реквизиты и договор. Мы проверим анкету и включим приём платежей. На счёт для оплаты инфраструктуры сразу начислим <?= MON_BONUS_START ?> ₽.</div>
    </div>
    <span class="material-icons mn-hero-ico">storefront</span>
</div>

<form method="POST" id="mn-wizard" class="card mn-wizard" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="mon" value="apply">

    <ol class="mn-steps">
        <li data-step="1">Форма</li><li data-step="2">Данные</li><li data-step="3" data-only="ip ooo">Налоги</li><li data-step="4">Реквизиты</li><li data-step="5">Договор</li>
    </ol>

    <!-- 1. Форма -->
    <section class="mn-step" data-step="1">
        <div class="mn-h">Кто будет продавать игры?</div>
        <div class="mn-types">
            <?php foreach ([
                'ip'  => ['person', 'ИП', 'Свой агрегатор (ЮKassa, Т-Касса). Оплаты идут сразу на ваш расчётный счёт.'],
                'ooo' => ['apartment', 'ООО', 'Свой агрегатор, оплаты на расчётный счёт компании. Подходит студиям с командой.'],
                'npd' => ['badge', 'Самозанятый', 'Без агрегатора: оплаты принимает платформа и раз в месяц переводит вам. Налог 4–6%.'],
            ] as $k => [$ico, $t, $d]): ?>
            <label class="mn-type">
                <input type="radio" name="type" value="<?= $k ?>"<?= ($wz['type'] ?? '') === $k ? ' checked' : '' ?><?= $can ? '' : ' disabled' ?>>
                <span class="material-icons"><?= $ico ?></span><b><?= $t ?></b><small><?= $d ?></small>
            </label>
            <?php endforeach; ?>
        </div>
        <?= $er('type') ?>
    </section>

    <!-- 2. Данные -->
    <section class="mn-step" data-step="2" hidden>
        <div class="mn-h">Данные <span data-only="ip">индивидуального предпринимателя</span><span data-only="ooo">организации</span><span data-only="npd">самозанятого</span></div>
        <div class="grid-2">
            <div class="field col-full" data-only="ooo"><label>Наименование</label>
                <input name="legal[name]" value="<?= $v('legal.name') ?>" placeholder="ООО «Пыль»" class="<?= $ec('legal.name') ?>"><?= $er('legal.name') ?></div>
            <div class="field col-full" data-only="ip npd"><label>ФИО полностью</label>
                <input name="legal[fio]" value="<?= $v('legal.fio') ?>" placeholder="Иванов Иван Иванович" autocomplete="name" class="<?= $ec('legal.fio') ?>"><?= $er('legal.fio') ?></div>
            <div class="field"><label>ИНН</label>
                <input name="legal[inn]" value="<?= $v('legal.inn') ?>" inputmode="numeric" data-check="inn" class="<?= $ec('legal.inn') ?>"><?= $er('legal.inn') ?></div>
            <div class="field" data-only="ooo"><label>КПП</label>
                <input name="legal[kpp]" value="<?= $v('legal.kpp') ?>" maxlength="9" class="<?= $ec('legal.kpp') ?>"><?= $er('legal.kpp') ?></div>
            <div class="field" data-only="ooo"><label>ОГРН</label>
                <input name="legal[ogrn]" value="<?= $v('legal.ogrn') ?>" inputmode="numeric" data-check="ogrn" class="<?= $ec('legal.ogrn') ?>"><?= $er('legal.ogrn') ?></div>
            <div class="field" data-only="ip"><label>ОГРНИП</label>
                <input name="legal[ogrnip]" value="<?= $v('legal.ogrnip') ?>" inputmode="numeric" data-check="ogrnip" class="<?= $ec('legal.ogrnip') ?>"><?= $er('legal.ogrnip') ?></div>
            <div class="field" data-only="ooo"><label>Руководитель (ФИО)</label>
                <input name="legal[director]" value="<?= $v('legal.director') ?>" class="<?= $ec('legal.director') ?>"><?= $er('legal.director') ?></div>
            <div class="field col-full" data-only="ip ooo"><label><span data-only="ip">Адрес регистрации</span><span data-only="ooo">Юридический адрес</span></label>
                <input name="legal[address]" value="<?= $v('legal.address') ?>" class="<?= $ec('legal.address') ?>"><?= $er('legal.address') ?></div>
            <div class="field"><label>Почта для документов</label>
                <input name="legal[email]" type="email" value="<?= $v('legal.email') ?>" autocomplete="email" class="<?= $ec('legal.email') ?>"><?= $er('legal.email') ?></div>
            <div class="field"><label>Телефон</label>
                <input name="legal[phone]" value="<?= $v('legal.phone') ?>" inputmode="tel" placeholder="+7 900 000-00-00" autocomplete="tel" class="<?= $ec('legal.phone') ?>"><?= $er('legal.phone') ?></div>
        </div>
        <div class="mn-note" data-only="npd">Статус самозанятого проверим по ИНН в сервисе ФНС. Зарегистрироваться можно в приложении «Мой налог».</div>
    </section>

    <!-- 3. Налоги (ИП/ООО) -->
    <section class="mn-step" data-step="3" data-only="ip ooo" hidden>
        <div class="mn-h">Система налогообложения</div>
        <div class="mn-tax">
            <?php foreach (mon_tax_systems('ip') as $k => [$t, $d]): ?>
            <label class="mn-opt" data-only="<?= $k === 'psn' ? 'ip' : 'ip ooo' ?>">
                <input type="radio" name="tax[system]" value="<?= $k ?>"<?= ($wz['tax']['system'] ?? '') === $k ? ' checked' : '' ?>>
                <span><b><?= $t ?></b><small data-osno-ooo="<?= $k === 'osno' ? htmlspecialchars(mon_tax_systems('ooo')['osno'][1]) : '' ?>"><?= $d ?></small></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?= $er('tax.system') ?>
        <div class="field" id="mn-vat-wrap" style="margin-top:14px;">
            <label>НДС</label>
            <select name="tax[vat]" id="mn-vat" data-value="<?= $v('tax.vat') ?>"></select>
            <div class="mn-note" id="mn-vat-note">С 2026 года основная ставка НДС — 22%. На УСН НДС не нужен, пока доход за год меньше 20 млн ₽.</div>
        </div>
    </section>

    <!-- 4. Реквизиты -->
    <section class="mn-step" data-step="4" hidden>
        <div class="mn-h">Счёт для <span data-only="npd">выплат</span><span data-only="ip ooo">оплат и возвратов</span></div>
        <div class="grid-2">
            <div class="field"><label>БИК</label>
                <input name="bank[bik]" value="<?= $v('bank.bik') ?>" inputmode="numeric" maxlength="9" data-check="bik" class="<?= $ec('bank.bik') ?>"><?= $er('bank.bik') ?></div>
            <div class="field"><label>Банк</label>
                <input name="bank[bank_name]" value="<?= $v('bank.bank_name') ?>" placeholder="ПАО Сбербанк" class="<?= $ec('bank.bank_name') ?>"><?= $er('bank.bank_name') ?></div>
            <div class="field"><label>Корреспондентский счёт</label>
                <input name="bank[ks]" value="<?= $v('bank.ks') ?>" inputmode="numeric" maxlength="20" data-check="ks" class="<?= $ec('bank.ks') ?>"><?= $er('bank.ks') ?></div>
            <div class="field"><label><span data-only="ip ooo">Расчётный счёт</span><span data-only="npd">Счёт физлица</span></label>
                <input name="bank[account]" value="<?= $v('bank.account') ?>" inputmode="numeric" maxlength="20" data-check="account" class="<?= $ec('bank.account') ?>"><?= $er('bank.account') ?></div>
        </div>
        <div class="mn-note">Номер счёта проверяется по контрольному ключу вместе с БИК — опечатку увидите сразу. Получатель — <span id="mn-recipient">владелец анкеты</span>.</div>
    </section>

    <!-- 5. Договор -->
    <section class="mn-step" data-step="5" hidden>
        <div class="mn-h">Проверьте данные и примите договор</div>
        <div class="mn-summary" id="mn-summary"></div>
        <?php foreach (MON_TYPES as $k => $_): ?>
        <div class="mn-offer" data-only="<?= $k ?>"><?= mon_offer_html($k) ?></div>
        <?php endforeach; ?>
        <label class="mn-accept<?= $ec('offer') ?>">
            <input type="checkbox" name="offer_accept" value="1">
            <span>Я принимаю условия договора-оферты (редакция <?= MON_OFFER_VERSION ?>) и согласен на обработку персональных данных</span>
        </label>
        <?= $er('offer') ?>
    </section>

    <div class="mn-nav">
        <button type="button" class="btn btn-g" id="mn-prev"><span class="material-icons">arrow_back</span>Назад</button>
        <button type="button" class="btn btn-p" id="mn-next">Далее<span class="material-icons">arrow_forward</span></button>
        <button type="submit" class="btn btn-p" id="mn-submit" hidden<?= $can ? '' : ' disabled' ?>><span class="material-icons">send</span>Принимаю условия и отправляю анкету</button>
    </div>
</form>

<?php elseif ($p['status'] === 'pending'): /* ═══════════ НА ПРОВЕРКЕ ═══════════ */
    $pl = json_decode((string)($last_onb['payload'] ?? ''), true) ?: []; ?>
<div class="mn-hero mn-hero-wait">
    <div>
        <div class="mn-hero-title">Анкета на проверке</div>
        <div class="mn-hero-sub">Отправлена <?= $last_onb ? date('d.m.Y в H:i', strtotime($last_onb['created_at'])) : '' ?>. Обычно проверяем за 1–2 рабочих дня. Когда включим приём платежей, здесь откроются настройки.</div>
    </div>
    <span class="material-icons mn-hero-ico">hourglass_top</span>
</div>
<div class="card">
    <div class="card-title"><span class="material-icons">description</span>Что отправлено</div>
    <dl class="mn-dl">
        <dt>Форма</dt><dd><?= MON_TYPES[$pl['type'] ?? ''] ?? '—' ?></dd>
        <dt><?= ($pl['type'] ?? '') === 'ooo' ? 'Наименование' : 'ФИО' ?></dt><dd><?= htmlspecialchars($pl['legal']['name'] ?? $pl['legal']['fio'] ?? '') ?></dd>
        <dt>ИНН</dt><dd><?= htmlspecialchars($pl['legal']['inn'] ?? '') ?></dd>
        <?php if (!empty($pl['tax']['system'])): ?><dt>Налоги</dt><dd><?= htmlspecialchars(mon_tax_systems($pl['type'])[$pl['tax']['system']][0] ?? '') ?>, <?= htmlspecialchars(mon_vat_options($pl['tax']['system'])[$pl['tax']['vat']] ?? '') ?></dd><?php endif; ?>
        <dt>Счёт</dt><dd><?= htmlspecialchars(mn_mask($pl['bank']['account'] ?? '')) ?> · <?= htmlspecialchars($pl['bank']['bank_name'] ?? '') ?></dd>
        <dt>Договор</dt><dd>редакция <?= htmlspecialchars($last_onb['offer_version'] ?? '') ?>, принят</dd>
    </dl>
    <form method="POST" onsubmit="return confirm('Отозвать анкету? Её можно будет исправить и отправить снова.')">
        <?= csrf_field() ?><input type="hidden" name="mon" value="withdraw">
        <button class="btn btn-g"<?= $can ? '' : ' disabled' ?>><span class="material-icons">undo</span>Отозвать и исправить</button>
    </form>
</div>

<?php endif; ?>

<?php if ($is_active): /* ═══════════ ВКЛАДКИ ═══════════ */ ?>
<nav class="mn-tabs">
    <?php foreach ($tabs as $k => [$ico, $t]): ?>
    <a href="?tab=<?= $k ?>" class="mn-tab<?= $tab === $k ? ' on' : '' ?>" data-tab="<?= $k ?>"><span class="material-icons"><?= $ico ?></span><?= $t ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($is_active):
    $legal_name = $type === 'ooo' ? ($p['legal']['name'] ?? '') : ($p['legal']['fio'] ?? '');
    $sys = $p['tax']['system'] ?? '';
?>

<!-- ─────────── Обзор ─────────── -->
<section class="mn-pane" data-tab="overview"<?= $tab === 'overview' ? '' : ' hidden' ?>>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons"><?= $sell_ok ? 'check_circle' : 'pause_circle' ?></span></div>
            <div class="stat-num" style="font-size:16px;"><?= $sell_ok ? 'Продажи включены' : 'Продажи на паузе' ?></div>
            <div class="stat-label"><?= $sell_ok ? 'игроки могут покупать ваши игры' : htmlspecialchars($sell_why) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">account_balance_wallet</span></div>
            <div class="stat-num"><?= mon_money($balance['total']) ?></div>
            <div class="stat-label">баланс для сбора · бонус <?= mon_money($balance['bonus']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">trending_up</span></div>
            <div class="stat-num"><?= mon_money($income) ?></div>
            <div class="stat-label">доход в этом месяце</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">percent</span></div>
            <div class="stat-num"><?= mon_fee_pct($p, $income) ?>%</div>
            <div class="stat-label">сбор · платит <?= $p['fee_payer'] === 'dev' ? 'разработчик' : 'игрок' ?></div>
        </div>
    </div>

    <div class="ed-cols-mn">
        <div class="card">
            <div class="card-title"><span class="material-icons">badge</span><?= MON_TYPES[$type] ?></div>
            <dl class="mn-dl">
                <dt><?= $type === 'ooo' ? 'Наименование' : 'ФИО' ?></dt><dd><?= htmlspecialchars($legal_name) ?></dd>
                <dt>ИНН</dt><dd><?= htmlspecialchars($p['legal']['inn'] ?? '') ?></dd>
                <?php if ($type === 'ip'): ?><dt>ОГРНИП</dt><dd><?= htmlspecialchars($p['legal']['ogrnip'] ?? '') ?></dd><?php endif; ?>
                <?php if ($type === 'ooo'): ?><dt>ОГРН / КПП</dt><dd><?= htmlspecialchars(($p['legal']['ogrn'] ?? '') . ' / ' . ($p['legal']['kpp'] ?? '')) ?></dd><?php endif; ?>
                <dt>Налоги</dt><dd><?= $type === 'npd' ? 'НПД: 4% с физлиц, 6% с компаний' : htmlspecialchars((mon_tax_systems($type)[$sys][0] ?? '') . ' · ' . (mon_vat_options($sys)[$p['tax']['vat'] ?? ''] ?? '')) ?></dd>
                <dt>Договор</dt><dd>редакция <?= htmlspecialchars((string)$p['offer_version']) ?></dd>
            </dl>
        </div>
        <div class="card">
            <div class="card-title"><span class="material-icons">checklist</span>Что осталось</div>
            <?php $steps = $type === 'npd'
                ? [['Анкета одобрена', true], ['Приём платежей включён', !empty($p['payments_enabled'])], ['На балансе есть средства для сбора', $balance['total'] > 0]]
                : [['Анкета одобрена', true], ['Выбран агрегатор', !empty($p['agg'])], ['Тестовая оплата прошла', !empty($p['agg_test_ok'])],
                   ['Боевые ключи проверены', !empty($p['agg_live_ok'])], ['На балансе есть средства для сбора', $balance['total'] > 0]]; ?>
            <ul class="mn-check">
                <?php foreach ($steps as [$t, $ok]): ?><li class="<?= $ok ? 'ok' : '' ?>"><span class="material-icons"><?= $ok ? 'check_circle' : 'radio_button_unchecked' ?></span><?= $t ?></li><?php endforeach; ?>
            </ul>
            <?php if ($type !== 'npd' && empty($p['agg_live_ok'])): ?><a href="?tab=agg" class="btn btn-p" style="margin-top:6px;"><span class="material-icons">credit_card</span>Подключить агрегатора</a><?php endif; ?>
        </div>
    </div>
</section>

<!-- ─────────── Реквизиты ─────────── -->
<section class="mn-pane" data-tab="bank"<?= $tab === 'bank' ? '' : ' hidden' ?>>
    <div class="ed-cols-mn">
        <div class="card">
            <div class="card-title"><span class="material-icons">account_balance</span>Действующие реквизиты</div>
            <dl class="mn-dl">
                <dt>Получатель</dt><dd><?= htmlspecialchars($p['bank']['recipient'] ?? $legal_name) ?></dd>
                <dt>Банк</dt><dd><?= htmlspecialchars($p['bank']['bank_name'] ?? '') ?></dd>
                <dt>БИК</dt><dd><?= htmlspecialchars($p['bank']['bik'] ?? '') ?></dd>
                <dt>Корр. счёт</dt><dd><?= htmlspecialchars($p['bank']['ks'] ?? '') ?></dd>
                <dt>Счёт</dt><dd><?= htmlspecialchars($p['bank']['account'] ?? '') ?></dd>
            </dl>
            <?php if ($bank_pend): $bp = json_decode($bank_pend['payload'], true)['bank'] ?? []; ?>
            <div class="alert alert-warn" style="margin:14px 0 0;"><span class="material-icons">hourglass_top</span>
                <div>На проверке новый счёт <?= htmlspecialchars(mn_mask($bp['account'] ?? '')) ?> в <?= htmlspecialchars($bp['bank_name'] ?? '') ?>. До одобрения действуют эти реквизиты.</div></div>
            <?php endif; ?>
        </div>
        <form method="POST" class="card">
            <?= csrf_field() ?><input type="hidden" name="mon" value="bank"><input type="hidden" name="tab" value="bank">
            <div class="card-title"><span class="material-icons">edit</span>Изменить реквизиты</div>
            <div class="mn-note" style="margin-top:-6px;margin-bottom:12px;">Каждое изменение проверяем вручную — так никто не подменит счёт для выплат.</div>
            <?php $bv = fn($k) => $v('bank.' . $k, $form['in'] ?? ['bank' => $p['bank']]); ?>
            <div class="field"><label>БИК</label><input name="bank[bik]" value="<?= $bv('bik') ?>" maxlength="9" inputmode="numeric" data-check="bik" class="<?= $ec('bank.bik') ?>"><?= $er('bank.bik') ?></div>
            <div class="field"><label>Банк</label><input name="bank[bank_name]" value="<?= $bv('bank_name') ?>" class="<?= $ec('bank.bank_name') ?>"><?= $er('bank.bank_name') ?></div>
            <div class="field"><label>Корр. счёт</label><input name="bank[ks]" value="<?= $bv('ks') ?>" maxlength="20" inputmode="numeric" data-check="ks" class="<?= $ec('bank.ks') ?>"><?= $er('bank.ks') ?></div>
            <div class="field"><label>Счёт</label><input name="bank[account]" value="<?= $bv('account') ?>" maxlength="20" inputmode="numeric" data-check="account" class="<?= $ec('bank.account') ?>"><?= $er('bank.account') ?></div>
            <button class="btn btn-p"<?= $can ? '' : ' disabled' ?>><span class="material-icons">send</span>Отправить на проверку</button>
        </form>
    </div>
    <?php if ($bank_reqs): ?>
    <div class="card" style="margin-top:14px;">
        <div class="card-title"><span class="material-icons">history</span>История изменений</div>
        <table class="mn-table">
            <tr><th>Дата</th><th>Счёт</th><th>Банк</th><th>Статус</th><th>Комментарий</th></tr>
            <?php foreach ($bank_reqs as $r): $b = json_decode($r['payload'], true)['bank'] ?? []; ?>
            <tr><td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td><td><?= htmlspecialchars(mn_mask($b['account'] ?? '')) ?></td>
                <td><?= htmlspecialchars($b['bank_name'] ?? '') ?></td><td><?= mn_status_badge($r['status']) ?></td><td><?= htmlspecialchars((string)$r['comment']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($type !== 'npd'): /* ─────────── Агрегатор ─────────── */
    $agg = $p['agg'] ?? '';
    $hook = $agg ? mon_notify_url($studio_id, $agg) : '';
    $test_ok = !empty($p['agg_test_ok']);
    $live_ok = !empty($p['agg_live_ok']);
    // несекретные поля (shopId, TerminalKey) показываем из сохранённых ключей; секреты — никогда
    $saved_creds = ['test' => mon_decrypt($p['agg_test'] ?? null) ?: [], 'live' => mon_decrypt($p['agg_live'] ?? null) ?: []];
    $cin = $form['in']['creds'] ?? [];
    foreach (['test', 'live'] as $m_) $cin[$m_] = ($cin[$m_] ?? []) + array_diff_key($saved_creds[$m_], ['secret' => 1, 'password' => 1]);
?>
<section class="mn-pane" data-tab="agg"<?= $tab === 'agg' ? '' : ' hidden' ?>>
    <form method="POST" class="mn-aggs">
        <?= csrf_field() ?><input type="hidden" name="mon" value="agg_select"><input type="hidden" name="tab" value="agg">
        <?php foreach (MON_AGGS as $k => $a): ?>
        <button name="agg" value="<?= $k ?>" class="mn-agg<?= $agg === $k ? ' on' : '' ?>"<?= ($a['active'] && $can) ? '' : ' disabled' ?>
                <?= ($agg && $agg !== $k && $live_ok) ? 'onclick="return confirm(\'Сменить агрегатора? Ключи текущего удалятся, продажи остановятся до подключения нового.\')"' : '' ?>>
            <b><?= $a['label'] ?></b>
            <small><?= $agg === $k ? ($live_ok ? 'подключена' : 'выбрана') : ($a['active'] ? 'выбрать' : 'скоро') ?></small>
        </button>
        <?php endforeach; ?>
    </form>
    <div class="mn-note" style="margin:-4px 0 14px;">Подключается только один агрегатор. Оплаты за игры идут на ваш счёт напрямую — платформа не держит ваши деньги.</div>

    <?php if ($agg): $fields = MON_AGGS[$agg]['fields']; ?>
    <div class="ed-cols-mn">
        <form method="POST" class="card mn-agg-step<?= $test_ok ? ' done' : '' ?>">
            <?= csrf_field() ?><input type="hidden" name="mon" value="agg_test"><input type="hidden" name="tab" value="agg">
            <div class="card-title"><span class="mn-num">1</span>Тестовые ключи и тестовая оплата</div>
            <div class="mn-note" style="margin:-6px 0 12px;">
                <?= $agg === 'yookassa'
                    ? 'В ЮKassa создайте тестовый магазин: «Магазины → Тестовый магазин». Ключ API берётся в «Интеграция → Ключи API» и начинается с test_.'
                    : 'В кабинете Т-Бизнеса откройте «Интернет-эквайринг → Магазины → Терминалы» и возьмите тестовый терминал (…DEMO) и его пароль.' ?>
            </div>
            <?php foreach ($fields as $f => $label): $secret = in_array($f, ['secret', 'password'], true); ?>
            <div class="field"><label><?= $label ?></label>
                <input name="creds[<?= $f ?>]" <?= $secret ? 'type="password" autocomplete="new-password" placeholder="' . (!empty($p['agg_test']) ? 'сохранён — введите заново, чтобы сменить' : '') . '"' : 'value="' . htmlspecialchars((string)($cin['test'][$f] ?? '')) . '"' ?>
                       class="<?= $ec("creds.test.$f") ?>"><?= $er("creds.test.$f") ?></div>
            <?php endforeach; ?>
            <div class="mn-row">
                <button class="btn btn-g"<?= $can ? '' : ' disabled' ?>><span class="material-icons">key</span>Сохранить тестовые ключи</button>
                <?php if (!empty($p['agg_test'])): ?>
                <button type="button" class="btn btn-p" id="mn-testpay"<?= $can ? '' : ' disabled' ?>><span class="material-icons">play_arrow</span><?= $test_ok ? 'Повторить' : 'Провести' ?> тестовую оплату · <?= MON_TEST_AMOUNT ?> ₽</button>
                <?php endif; ?>
            </div>
            <div class="mn-test-state" id="mn-test-state">
                <?php if ($test_ok): ?><span class="material-icons" style="color:var(--ok);">check_circle</span>Тестовая оплата прошла <?= date('d.m.Y H:i', strtotime($p['agg_test_ok'])) ?>
                <?php elseif (!empty($p['agg_test'])): ?><span class="material-icons">info</span>Откроется страница оплаты. Оплатите тестовой картой <?= $agg === 'yookassa' ? '5555 5555 5555 4477, срок любой в будущем, CVC любой' : 'из документации Т-Кассы' ?>.<?php endif; ?>
            </div>
        </form>

        <form method="POST" class="card mn-agg-step<?= $live_ok ? ' done' : '' ?><?= $test_ok ? '' : ' locked' ?>">
            <?= csrf_field() ?><input type="hidden" name="mon" value="agg_live"><input type="hidden" name="tab" value="agg">
            <div class="card-title"><span class="mn-num">2</span>Боевые ключи</div>
            <div class="mn-note" style="margin:-6px 0 12px;"><?= $test_ok ? 'Проверим ключи без списания денег и включим приём оплат за игры.' : 'Откроется после тестовой оплаты.' ?></div>
            <?php foreach ($fields as $f => $label): $secret = in_array($f, ['secret', 'password'], true); ?>
            <div class="field"><label><?= $label ?></label>
                <input name="creds[<?= $f ?>]" <?= $secret ? 'type="password" autocomplete="new-password" placeholder="' . ($live_ok ? 'сохранён — введите заново, чтобы сменить' : '') . '"' : 'value="' . htmlspecialchars((string)($cin['live'][$f] ?? '')) . '"' ?>
                       class="<?= $ec("creds.live.$f") ?>"<?= $test_ok && $can ? '' : ' disabled' ?>><?= $er("creds.live.$f") ?></div>
            <?php endforeach; ?>
            <button class="btn btn-p"<?= $test_ok && $can ? '' : ' disabled' ?>><span class="material-icons">verified</span>Проверить и включить</button>
            <?php if ($live_ok): ?><div class="mn-test-state"><span class="material-icons" style="color:var(--ok);">check_circle</span>Подключено <?= date('d.m.Y H:i', strtotime($p['agg_live_ok'])) ?></div><?php endif; ?>
        </form>
    </div>

    <div class="ed-cols-mn" style="margin-top:14px;">
        <div class="card">
            <div class="card-title"><span class="material-icons">webhook</span>Уведомления об оплатах</div>
            <?php if ($agg === 'yookassa'): ?>
            <div class="mn-note" style="margin:-6px 0 10px;">Вставьте адрес в ЮKassa: «Интеграция → HTTP-уведомления», события payment.succeeded и payment.canceled. Без него статус проверяется, когда игрок возвращается на сайт.</div>
            <input class="mn-copy" readonly value="<?= htmlspecialchars($hook) ?>" onclick="this.select()">
            <?php else: ?>
            <div class="mn-note" style="margin:-6px 0 0;">Т-Касса получает адрес уведомлений в каждом платеже сама — настраивать ничего не нужно.</div>
            <?php endif; ?>
        </div>
        <form method="POST" class="card">
            <?= csrf_field() ?><input type="hidden" name="mon" value="receipt"><input type="hidden" name="tab" value="agg">
            <div class="card-title"><span class="material-icons">receipt_long</span>Чеки 54-ФЗ</div>
            <label class="mn-switch"><input type="checkbox" name="send_receipt" value="1"<?= !empty($p['send_receipt']) ? ' checked' : '' ?> onchange="this.form.requestSubmit()"<?= $can ? '' : ' disabled' ?>>
                <span class="mn-switch-ui"></span><span>Передавать чек агрегатору</span></label>
            <div class="mn-note">Включите, если чеки пробивает касса агрегатора («Чеки от ЮKassa», касса Т-Бизнеса). Если у вас своя касса — выключите.</div>
        </form>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ─────────── Цена и сбор ─────────── -->
<?php $suggest = mon_tax_suggest($p); $free_ok = $income < MON_FEE_FREE_UPTO; ?>
<section class="mn-pane" data-tab="pricing"<?= $tab === 'pricing' ? '' : ' hidden' ?>>
    <form method="POST" class="ed-cols-mn" id="mn-pricing">
        <?= csrf_field() ?><input type="hidden" name="mon" value="pricing"><input type="hidden" name="tab" value="pricing">
        <div class="card">
            <div class="card-title"><span class="material-icons">percent</span>Инфраструктурный сбор</div>
            <div class="mn-note" style="margin:-6px 0 12px;">Доля платформы с каждой покупки: витрина, хранение и доставка билдов, обработка оплат. Списывается с вашего баланса.</div>

            <div class="mn-sub">Кто платит сбор</div>
            <div class="mn-seg">
                <label><input type="radio" name="fee_payer" value="player"<?= $p['fee_payer'] !== 'dev' ? ' checked' : '' ?>><span>Игрок — прибавляем к цене</span></label>
                <label><input type="radio" name="fee_payer" value="dev"<?= $p['fee_payer'] === 'dev' ? ' checked' : '' ?>><span>Разработчик — цена не меняется</span></label>
            </div>

            <div class="mn-sub">Ставка <b id="mn-fee-val"><?= (int)$p['fee_pct'] ?>%</b></div>
            <input type="range" name="fee_pct" min="<?= MON_FEE_MIN ?>" max="<?= MON_FEE_MAX ?>" step="1" value="<?= (int)$p['fee_pct'] ?>" class="mn-range" id="mn-fee">
            <div class="mn-range-lbl"><span><?= MON_FEE_MIN ?>%</span><span><?= MON_FEE_MAX ?>%</span></div>

            <label class="mn-switch<?= $free_ok ? '' : ' off' ?>">
                <input type="checkbox" name="fee_off" value="1" id="mn-fee-off"<?= !empty($p['fee_off']) ? ' checked' : '' ?><?= $free_ok ? '' : ' disabled' ?>>
                <span class="mn-switch-ui"></span><span>Отключить сбор</span>
            </label>
            <div class="mn-note">Можно, пока доход за месяц меньше <?= mon_money(MON_FEE_FREE_UPTO) ?>. Сейчас: <?= mon_money($income) ?><?= $free_ok ? '' : ' — порог пройден, сбор включён до конца месяца' ?>.</div>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">account_balance</span>Налог продавца в цене</div>
            <div class="mn-note" style="margin:-6px 0 12px;">Прибавляем к цене ваш налог, чтобы после его уплаты у вас осталась полная цена игры. Считаем «изнутри»: итог = подытог ÷ (1 − ставка).</div>
            <label class="mn-switch">
                <input type="checkbox" name="tax_on" value="1" id="mn-tax-on"<?= !empty($p['tax_on']) ? ' checked' : '' ?>>
                <span class="mn-switch-ui"></span><span>Прибавлять налог к цене</span>
            </label>
            <div class="mn-sub">Ставка <b id="mn-tax-val"><?= (int)$p['tax_pct'] ?>%</b>
                <button type="button" class="mn-link" id="mn-tax-suggest" data-v="<?= (int)$suggest ?>">для вашего режима ≈ <?= (int)$suggest ?>%</button></div>
            <input type="range" name="tax_pct" min="<?= MON_TAX_MIN ?>" max="<?= MON_TAX_MAX ?>" step="1" value="<?= (int)$p['tax_pct'] ?>" class="mn-range" id="mn-tax">
            <div class="mn-range-lbl"><span><?= MON_TAX_MIN ?>%</span><span><?= MON_TAX_MAX ?>%</span></div>
        </div>

        <div class="card col-span">
            <div class="card-title"><span class="material-icons">calculate</span>Как это увидит игрок</div>
            <div class="mn-calc">
                <div class="field" style="margin:0;"><label>Цена игры</label><input type="number" id="mn-ex" value="100" min="0" step="10"></div>
                <div class="mn-calc-out" id="mn-calc-out"></div>
            </div>
            <div class="mn-row" style="margin-top:14px;">
                <button class="btn btn-p"<?= $can ? '' : ' disabled' ?>><span class="material-icons">save</span>Сохранить</button>
                <span class="mn-note">Новые цены сразу появятся на витрине.</span>
            </div>
        </div>
    </form>
</section>

<!-- ─────────── Баланс ─────────── -->
<?php
$ledger = $conn->prepare("SELECT * FROM mon_ledger WHERE studio_id = ? ORDER BY id DESC LIMIT 20");
$ledger->execute([$studio_id]);
$ledger = $ledger->fetchAll(PDO::FETCH_ASSOC);
$gp = $conn->prepare("SELECT ROUND(AVG(price)) FROM games WHERE developer = ? AND price > 0");
$gp->execute([$studio_id]);
$avg_price = (int)($gp->fetchColumn() ?: 100);
?>
<section class="mn-pane" data-tab="balance"<?= $tab === 'balance' ? '' : ' hidden' ?>>
    <div class="ed-cols-mn">
        <div class="card">
            <div class="card-title"><span class="material-icons">account_balance_wallet</span>Баланс для сбора</div>
            <div class="mn-big"><?= mon_money($balance['total']) ?></div>
            <dl class="mn-dl">
                <dt>Бонус</dt><dd><?= mon_money($balance['bonus']) ?> <span class="mn-muted">— расходуется первым, не выводится</span></dd>
                <dt>Пополнения</dt><dd><?= mon_money($balance['cash']) ?></dd>
            </dl>
            <div class="mn-sub" style="margin-top:14px;">Пополнить</div>
            <div class="mn-row">
                <input type="number" class="mn-input" id="mn-topup" min="100" step="100" value="500" style="max-width:140px;">
                <button type="button" class="btn btn-p" disabled title="Скоро"><span class="material-icons">add_card</span>Пополнить</button>
            </div>
            <div class="mn-note">Оплата пополнения на счёт платформы появится в следующем обновлении.</div>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">calculate</span>Сколько нужно на месяц</div>
            <div class="grid-2">
                <div class="field"><label>Цена игры, ₽</label><input type="number" id="mn-bc-price" value="<?= $avg_price ?>" min="0" step="10"></div>
                <div class="field"><label>Продаж в месяц</label><input type="number" id="mn-bc-qty" value="50" min="0" step="10"></div>
            </div>
            <div class="mn-calc-out" id="mn-bc-out"></div>
        </div>
    </div>

    <div class="card" style="margin-top:14px;">
        <div class="card-title"><span class="material-icons">receipt</span>Движение по счёту</div>
        <?php if (!$ledger): ?><div class="mn-note">Пока пусто.</div><?php else: ?>
        <table class="mn-table">
            <tr><th>Дата</th><th>Операция</th><th>Счёт</th><th style="text-align:right;">Сумма</th></tr>
            <?php foreach ($ledger as $l): ?>
            <tr><td><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td><td><?= htmlspecialchars($l['note'] ?: $l['kind']) ?></td>
                <td><?= $l['bucket'] === 'bonus' ? 'бонус' : 'пополнения' ?></td>
                <td style="text-align:right;color:<?= $l['amount'] < 0 ? 'var(--err)' : 'var(--ok)' ?>;"><?= ($l['amount'] > 0 ? '+' : '') . mon_money((float)$l['amount']) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</section>
<?php endif; /* active */ ?>

<?php
/* ─────────── Страница студии: донаты, закрытая комната, ссылки ─────────── */
$fxCan   = FxAuth::isStudioOwner($studio_id, (int)$user_id);
$fxMon   = FxMonet::get($studio_id);
$fxLinks = FxMonet::links($studio_id) ?: FxStats::feedback($pay);   // ещё ничего не сохраняли — то, что уже видят посетители из карточки студии
$fxLinks = array_map(static fn($l) => ['label' => $l['label'], 'url' => preg_replace('#^mailto:#', '', (string)$l['url'])], $fxLinks);
$fxLinks = array_slice(array_pad($fxLinks, max(count($fxLinks) + 1, 3), ['label' => '', 'url' => '']), 0, 8);
?>
<section class="mn-pane" data-tab="page"<?= $tab === 'page' ? '' : ' hidden' ?>>
<div style="display:flex;align-items:center;gap:10px;margin:<?= $is_active ? '0' : '28px' ?> 0 12px;">
    <span class="material-icons" style="color:var(--p);">storefront</span>
    <div>
        <div style="font-size:15px;font-weight:700;">Страница студии</div>
        <div style="font-size:12px;color:var(--tm);">Эти настройки видят посетители на <a href="/d/<?= htmlspecialchars((string)($pay['tiker'] ?? '')) ?>" target="_blank" style="color:var(--p);text-decoration:none;">публичной странице студии ↗</a></div>
    </div>
</div>

<div class="grid-2" style="gap:16px;align-items:start;">
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Донаты -->
        <div class="card" id="donate">
            <div class="card-title"><span class="material-icons">volunteer_activism</span>Донаты</div>
            <div style="font-size:12px;color:var(--ts);line-height:1.6;margin-bottom:14px;">
                В баннере студии появится кнопка «Задонатить». Она открывает вашу ссылку в новой вкладке — Boosty, Patreon, DonationAlerts, СБП-ссылку и т. п.
            </div>
            <form method="POST">
                <?= csrf_field() ?><input type="hidden" name="section" value="fx_donate">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:14px;">
                    <input type="checkbox" name="donate_on" value="1" style="accent-color:var(--p);width:16px;height:16px;" <?= !empty($fxMon['donate_on']) ? 'checked' : '' ?>>
                    <span style="font-size:13px;font-weight:600;">Показывать кнопку «Задонатить»</span>
                </label>
                <div class="field"><label>Ссылка для донатов</label>
                    <input type="text" inputmode="url" autocapitalize="off" name="donate_url" value="<?= htmlspecialchars((string)($fxMon['donate_url'] ?? '')) ?>" placeholder="https://boosty.to/…" maxlength="255"></div>
                <div class="field"><label>Подпись под кнопкой <small style="color:var(--tm);">(необязательно)</small></label>
                    <input type="text" name="donate_note" value="<?= htmlspecialchars((string)($fxMon['donate_note'] ?? '')) ?>" placeholder="Все донаты идут на новые уровни" maxlength="160"></div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;" <?= $fxCan ? '' : 'disabled' ?>>
                    <span class="material-icons">save</span>Сохранить донаты
                </button>
            </form>
        </div>

        <!-- Закрытая комната -->
        <div class="card" id="room">
            <div class="card-title"><span class="material-icons">lock</span>Закрытая комната
                <span style="margin-left:auto;font-size:10px;background:var(--elev);color:var(--tm);padding:1px 8px;border-radius:4px;border:1px solid var(--bd);">скоро</span></div>
            <div style="font-size:12px;color:var(--ts);line-height:1.6;margin-bottom:14px;">
                Платный контент для тех, кто поддерживает студию: ранние сборки, закулисье, наброски. Вход пока не открыт — кнопка на странице студии
                будет неактивной, а вторая вкладка рядом с девблогом появится позже. Настройки сохранятся и подхватятся автоматически.
            </div>
            <form method="POST">
                <?= csrf_field() ?><input type="hidden" name="section" value="fx_room">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:14px;">
                    <input type="checkbox" name="room_on" value="1" style="accent-color:var(--p);width:16px;height:16px;" <?= !empty($fxMon['room_on']) ? 'checked' : '' ?>>
                    <span style="font-size:13px;font-weight:600;">Показывать «Закрытую комнату»</span>
                </label>
                <div class="field"><label>Название</label>
                    <input type="text" name="room_title" value="<?= htmlspecialchars((string)($fxMon['room_title'] ?? '')) ?>" placeholder="Закулисье" maxlength="80"></div>
                <div class="field"><label>Что внутри</label>
                    <textarea name="room_desc" maxlength="400" style="min-height:80px;" placeholder="Ранние сборки, наброски, голосования"><?= htmlspecialchars((string)($fxMon['room_desc'] ?? '')) ?></textarea></div>
                <div class="field"><label>Цена доступа, монет <small style="color:var(--tm);">(пусто — без цены)</small></label>
                    <input type="number" name="room_price" min="0" value="<?= htmlspecialchars((string)($fxMon['room_price'] ?? '')) ?>" placeholder="150"></div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;" <?= $fxCan ? '' : 'disabled' ?>>
                    <span class="material-icons">save</span>Сохранить комнату
                </button>
            </form>
        </div>
    </div>

    <!-- Обратная связь -->
    <div class="card" id="links">
        <div class="card-title"><span class="material-icons">forum</span>Обратная связь</div>
        <div style="font-size:12px;color:var(--ts);line-height:1.6;margin-bottom:14px;">
            Куда посетителям писать студии: Telegram, Discord, почта, сайт. До 8 ссылок — они покажутся в блоке «Обратная связь» на странице студии.
            Почту можно указать просто адресом, схему <code>https://</code> добавим сами.
        </div>
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="section" value="fx_links">
            <div id="fx-link-rows">
                <?php foreach ($fxLinks as $l): ?>
                    <div style="display:grid;grid-template-columns:130px 1fr;gap:8px;margin-bottom:8px;">
                        <div class="field" style="margin:0;"><input type="text" name="link_label[]" value="<?= htmlspecialchars((string)$l['label']) ?>" placeholder="Название" maxlength="48"></div>
                        <div class="field" style="margin:0;"><input type="text" name="link_url[]" value="<?= htmlspecialchars((string)$l['url']) ?>" placeholder="t.me/… или почта" maxlength="255"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-g" id="fx-link-add" style="margin-bottom:12px;"><span class="material-icons">add</span>Ещё ссылка</button>
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;" <?= $fxCan ? '' : 'disabled' ?>>
                <span class="material-icons">save</span>Сохранить ссылки
            </button>
        </form>
    </div>
</div>
</section>

<?php include __DIR__ . '/includes/mon_assets.php'; ?>
<script>
(function () {
    var CFG = <?= json_encode([
        'csrf' => csrf_token(),
        'profile' => ['fee_payer' => $p['fee_payer'], 'fee_pct' => (float)$p['fee_pct'], 'fee_off' => (int)$p['fee_off'],
                      'tax_on' => (int)$p['tax_on'], 'tax_pct' => (float)$p['tax_pct']],
        'income' => $income, 'free_upto' => MON_FEE_FREE_UPTO, 'balance' => $balance,
        // списком пар, а не объектом: в JS ключи-числа ('5', '22') встают перед 'none', и порядок ломается
        'vat' => array_map(fn($s) => array_map(null, array_keys(mon_vat_options($s)), mon_vat_options($s)),
                           array_combine(array_keys(mon_tax_systems('ip')), array_keys(mon_tax_systems('ip')))),
    ], JSON_UNESCAPED_UNICODE) ?>;
    window.MN && MN.init(CFG);
})();
</script>
<?php require_once(__DIR__ . '/includes/footer.php'); ?>
