<?php
/**
 * devs/mon_review.php — админка монетизации (только администратор платформы):
 * проверка анкет и изменений реквизитов, включение/пауза приёма платежей, обзор подключённых студий.
 * Одобрение анкеты = активация: профиль active, приём платежей включён, бонус на счёт.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';
require_once __DIR__ . '/../swad/controllers/mon/lib.php';

$mr_admin = (int)($_SESSION['USERDATA']['global_role'] ?? 0) === -1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mr_admin) {
    $db = (new Database())->connect();
    mon_schema($db);
    $back = function (string $msg, string $kind = 'ok', string $q = '') {
        $_SESSION['mr_flash'] = [$kind, $msg];
        header('Location: /devs/mon_review' . $q);
        exit();
    };
    if (!csrf_valid()) $back('Сессия устарела — обновите страницу.', 'err');
    $act = (string)($_POST['act'] ?? '');
    $me  = (int)($_SESSION['USERDATA']['id'] ?? 0);

    if ($act === 'approve' || $act === 'reject') {
        $st = $db->prepare("SELECT * FROM mon_requests WHERE id = ? AND status = 'pending'");
        $st->execute([(int)($_POST['id'] ?? 0)]);
        $req = $st->fetch(PDO::FETCH_ASSOC);
        if (!$req) $back('Заявка уже обработана.', 'err');
        $studio  = (int)$req['studio_id'];
        $payload = json_decode($req['payload'], true) ?: [];

        if ($act === 'reject') {
            $comment = trim(mb_substr((string)($_POST['comment'] ?? ''), 0, 1000));
            if ($comment === '') $back('Напишите причину отказа — её увидит разработчик.', 'err');
            $db->prepare("UPDATE mon_requests SET status = 'rejected', comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
               ->execute([$comment, $me, $req['id']]);
            if ($req['kind'] === 'onboarding') $db->prepare("UPDATE mon_profiles SET status = 'rejected' WHERE studio_id = ?")->execute([$studio]);
            $back('Заявка отклонена.');
        }

        $db->beginTransaction();
        try {
            if ($req['kind'] === 'onboarding') {
                $p = ['legal_type' => $payload['type'], 'tax' => $payload['tax'] ?? []];
                $db->prepare("
                    INSERT INTO mon_profiles (studio_id, legal_type, status, legal, tax, bank, payments_enabled, tax_pct, offer_version, offer_at, activated_at)
                    VALUES (:s, :t, 'active', :l, :x, :b, 1, :tp, :ov, :oa, NOW())
                    ON DUPLICATE KEY UPDATE legal_type = VALUES(legal_type), status = 'active', legal = VALUES(legal), tax = VALUES(tax),
                        bank = VALUES(bank), payments_enabled = 1, tax_pct = VALUES(tax_pct), offer_version = VALUES(offer_version),
                        offer_at = VALUES(offer_at), activated_at = COALESCE(activated_at, NOW())
                ")->execute([
                    's' => $studio, 't' => $payload['type'],
                    'l' => json_encode($payload['legal'] ?? [], JSON_UNESCAPED_UNICODE),
                    'x' => json_encode($payload['tax'] ?? [], JSON_UNESCAPED_UNICODE),
                    'b' => json_encode($payload['bank'] ?? [], JSON_UNESCAPED_UNICODE),
                    'tp' => mon_tax_suggest($p), 'ov' => $req['offer_version'], 'oa' => $req['created_at'],
                ]);
                // ref уникален — повторное одобрение (или двойной клик) не начислит бонус дважды
                mon_ledger_add($db, $studio, 'bonus', MON_BONUS_START, 'bonus', 'bonus:start:' . $studio, null, 'Стартовый бонус при подключении');
            } elseif ($req['kind'] === 'bank') {
                $db->prepare("UPDATE mon_profiles SET bank = ? WHERE studio_id = ?")
                   ->execute([json_encode($payload['bank'] ?? [], JSON_UNESCAPED_UNICODE), $studio]);
            }
            $db->prepare("UPDATE mon_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([$me, $req['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('mon_review approve: ' . $e->getMessage());
            $back('Не удалось одобрить: ошибка базы.', 'err');
        }
        $back($req['kind'] === 'onboarding' ? 'Анкета одобрена, приём платежей включён, бонус начислен.' : 'Новые реквизиты применены.');
    }

    if ($act === 'toggle') {
        $db->prepare("UPDATE mon_profiles SET payments_enabled = 1 - payments_enabled WHERE studio_id = ? AND status = 'active'")
           ->execute([(int)($_POST['studio'] ?? 0)]);
        $back('Приём платежей переключён.', 'ok', '?tab=active');
    }
    $back('Неизвестное действие.', 'err');
}

$page_title = 'Монетизация: проверка';
$active_nav = 'mon_review';
require_once __DIR__ . '/includes/header.php';

if (!$is_admin) {
    echo '<div class="alert alert-err">Раздел доступен только администратору платформы.</div>';
    require __DIR__ . '/includes/footer.php';
    return;
}

$conn = $db->connect();
mon_schema($conn);
$flash = $_SESSION['mr_flash'] ?? null;
unset($_SESSION['mr_flash']);
$tab = in_array($_GET['tab'] ?? '', ['active', 'history'], true) ? $_GET['tab'] : 'queue';

$names = [];
$nameOf = function (int $id) use ($conn, &$names) {
    if (!isset($names[$id])) {
        $s = $conn->prepare("SELECT name FROM studios WHERE id = ?");
        $s->execute([$id]);
        $names[$id] = (string)($s->fetchColumn() ?: 'Студия #' . $id);
    }
    return $names[$id];
};

$pending = $conn->query("SELECT * FROM mon_requests WHERE status = 'pending' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$history = $conn->query("SELECT * FROM mon_requests WHERE status <> 'pending' ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$active  = $conn->query("SELECT * FROM mon_profiles WHERE status = 'active' ORDER BY activated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$labels = [
    'name' => 'Наименование', 'fio' => 'ФИО', 'inn' => 'ИНН', 'kpp' => 'КПП', 'ogrn' => 'ОГРН', 'ogrnip' => 'ОГРНИП',
    'director' => 'Руководитель', 'address' => 'Адрес', 'email' => 'Почта', 'phone' => 'Телефон',
    'bank_name' => 'Банк', 'bik' => 'БИК', 'ks' => 'Корр. счёт', 'account' => 'Счёт', 'recipient' => 'Получатель',
];
?>
<style>
    .mr-tabs { display: flex; gap: 6px; margin-bottom: 16px; }
    .mr-tabs a { padding: 7px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; color: var(--ts); text-decoration: none; border: 1px solid transparent; }
    .mr-tabs a.on { background: rgba(195, 33, 120, .14); border-color: rgba(195, 33, 120, .35); color: var(--pl); }
    .mr-req { margin-bottom: 12px; }
    .mr-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mr-head b { font-size: 14px; }
    .mr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin: 14px 0; }
    .mr-dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 13px; }
    .mr-dl dt { color: var(--tm); }
    .mr-dl dd { margin: 0; word-break: break-word; }
    .mr-bad { color: var(--err); }
    .mr-ok { color: var(--ok); }
    .mr-sub { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--tm); margin-bottom: 8px; }
    .mr-actions { display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid var(--bd); }
    .mr-actions textarea { flex: 1; min-width: 220px; min-height: 38px; background: var(--elev); border: 1px solid var(--bd); border-radius: 8px; padding: 8px 10px; color: var(--tt); font: 13px Inter, sans-serif; }
    .mr-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .mr-table th { text-align: left; color: var(--tm); padding: 6px 8px; border-bottom: 1px solid var(--bd); }
    .mr-table td { padding: 8px; border-bottom: 1px solid var(--bd); color: var(--ts); vertical-align: middle; }
    .mr-links a { color: var(--pl); font-size: 12px; margin-right: 12px; }
</style>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<nav class="mr-tabs">
    <a href="?tab=queue" class="<?= $tab === 'queue' ? 'on' : '' ?>">Заявки<?= $pending ? ' · ' . count($pending) : '' ?></a>
    <a href="?tab=active" class="<?= $tab === 'active' ? 'on' : '' ?>">Подключённые · <?= count($active) ?></a>
    <a href="?tab=history" class="<?= $tab === 'history' ? 'on' : '' ?>">История</a>
</nav>

<?php if ($tab === 'queue'): ?>
    <?php if (!$pending): ?><div class="card" style="text-align:center;color:var(--tm);padding:40px;">Новых заявок нет</div><?php endif; ?>
    <?php foreach ($pending as $r):
        $pl = json_decode($r['payload'], true) ?: [];
        $studio = (int)$r['studio_id'];
        $prof = mon_profile($conn, $studio);
        $type = $r['kind'] === 'onboarding' ? ($pl['type'] ?? '') : ($prof['legal_type'] ?? '');
        // перепроверяем на сервере: контрольные суммы и формат — подсветим, что не сходится
        [, $errs] = $r['kind'] === 'onboarding' ? mon_validate($pl + ['legal' => [], 'bank' => []]) : [null, mn_bank_errs($type, $pl['bank'] ?? [])];
        $inn = $pl['legal']['inn'] ?? ($prof['legal']['inn'] ?? '');
    ?>
    <div class="card mr-req">
        <div class="mr-head">
            <b><?= htmlspecialchars($nameOf($studio)) ?></b>
            <span class="badge badge-rev"><?= $r['kind'] === 'onboarding' ? 'анкета · ' . (MON_TYPES[$type] ?? '?') : 'новые реквизиты' ?></span>
            <span style="font-size:12px;color:var(--tm);margin-left:auto;"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?> · IP <?= htmlspecialchars((string)$r['ip']) ?></span>
        </div>
        <div class="mr-grid">
            <?php if ($r['kind'] === 'onboarding'): ?>
            <div>
                <div class="mr-sub">Данные</div>
                <dl class="mr-dl"><?php foreach ($pl['legal'] ?? [] as $k => $val): $bad = $errs['legal.' . $k] ?? null; ?>
                    <dt><?= $labels[$k] ?? $k ?></dt><dd class="<?= $bad ? 'mr-bad' : '' ?>"><?= htmlspecialchars((string)$val) ?><?= $bad ? ' — ' . htmlspecialchars($bad) : '' ?></dd>
                <?php endforeach; ?>
                <?php if (!empty($pl['tax']['system'])): ?>
                    <dt>Налоги</dt><dd><?= htmlspecialchars(mon_tax_systems($type)[$pl['tax']['system']][0] ?? $pl['tax']['system']) ?>, <?= htmlspecialchars(mon_vat_options($pl['tax']['system'])[$pl['tax']['vat']] ?? '') ?></dd>
                <?php endif; ?></dl>
            </div>
            <?php else: ?>
            <div>
                <div class="mr-sub">Сейчас</div>
                <dl class="mr-dl"><?php foreach ($prof['bank'] as $k => $val): ?><dt><?= $labels[$k] ?? $k ?></dt><dd><?= htmlspecialchars((string)$val) ?></dd><?php endforeach; ?></dl>
            </div>
            <?php endif; ?>
            <div>
                <div class="mr-sub"><?= $r['kind'] === 'onboarding' ? 'Реквизиты' : 'Новые' ?></div>
                <dl class="mr-dl"><?php foreach ($pl['bank'] ?? [] as $k => $val): $bad = $errs['bank.' . $k] ?? null;
                    $changed = $r['kind'] === 'bank' && ($prof['bank'][$k] ?? null) !== $val; ?>
                    <dt><?= $labels[$k] ?? $k ?></dt><dd class="<?= $bad ? 'mr-bad' : ($changed ? 'mr-ok' : '') ?>"><?= htmlspecialchars((string)$val) ?><?= $bad ? ' — ' . htmlspecialchars($bad) : '' ?></dd>
                <?php endforeach; ?></dl>
            </div>
            <div>
                <div class="mr-sub">Проверка</div>
                <dl class="mr-dl">
                    <dt>Контрольные суммы</dt><dd class="<?= $errs ? 'mr-bad' : 'mr-ok' ?>"><?= $errs ? 'есть ошибки (' . count($errs) . ')' : 'всё сходится' ?></dd>
                    <?php if ($r['kind'] === 'onboarding'): ?>
                    <dt>Договор</dt><dd>ред. <?= htmlspecialchars((string)$r['offer_version']) ?>,
                        <?= hash_equals(mon_offer_hash_for($type, (string)$r['offer_version']), (string)$r['offer_hash']) ? '<span class="mr-ok">текст совпадает</span>' : 'старая редакция' ?></dd>
                    <?php endif; ?>
                </dl>
                <div class="mr-links" style="margin-top:10px;">
                    <?php if ($type === 'npd'): ?><a href="https://npd.nalog.ru/check-status/" target="_blank">Статус НПД по ИНН ↗</a>
                    <?php else: ?><a href="https://egrul.nalog.ru/" target="_blank">ЕГРЮЛ/ЕГРИП ↗</a><?php endif; ?>
                    <a href="https://bik-info.ru/bik_<?= htmlspecialchars($pl['bank']['bik'] ?? '') ?>.html" target="_blank">БИК ↗</a>
                </div>
            </div>
        </div>
        <div class="mr-actions">
            <form method="POST"><?= csrf_field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-p"><span class="material-icons">check</span><?= $r['kind'] === 'onboarding' ? 'Одобрить и включить платежи' : 'Применить реквизиты' ?></button></form>
            <form method="POST" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;"><?= csrf_field() ?><input type="hidden" name="act" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <textarea name="comment" placeholder="Причина отказа — её увидит разработчик" required></textarea>
                <button class="btn btn-d"><span class="material-icons">close</span>Отклонить</button></form>
        </div>
    </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'active'): ?>
    <div class="card">
        <table class="mr-table">
            <tr><th>Студия</th><th>Форма</th><th>Агрегатор</th><th>Сбор</th><th>Доход за месяц</th><th>Баланс</th><th>Платежи</th></tr>
            <?php foreach ($active as $a): $sid = (int)$a['studio_id']; $b = mon_balance($conn, $sid); $pa = mon_profile($conn, $sid); ?>
            <tr>
                <td><?= htmlspecialchars($nameOf($sid)) ?></td>
                <td><?= MON_TYPES[$a['legal_type']] ?? '—' ?></td>
                <td><?= $a['legal_type'] === 'npd' ? 'магазин платформы' : (($a['agg'] ? MON_AGGS[$a['agg']]['label'] : '—') . ($a['agg_live_ok'] ? ' ✓' : ($a['agg_test_ok'] ? ' · тест пройден' : ''))) ?></td>
                <td><?= mon_fee_pct($pa, $inc = mon_month_income($conn, $sid)) ?>% · <?= $a['fee_payer'] === 'dev' ? 'разработчик' : 'игрок' ?></td>
                <td><?= mon_money($inc) ?></td>
                <td style="color:<?= $b['total'] <= 0 ? 'var(--err)' : 'inherit' ?>;"><?= mon_money($b['total']) ?></td>
                <td><form method="POST"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="studio" value="<?= $sid ?>">
                    <button class="btn <?= $a['payments_enabled'] ? 'btn-g' : 'btn-p' ?>" style="padding:5px 10px;font-size:12px;"><?= $a['payments_enabled'] ? 'Приостановить' : 'Включить' ?></button></form></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$active): ?><div style="text-align:center;color:var(--tm);padding:24px;">Пока никого</div><?php endif; ?>
    </div>

<?php else: ?>
    <div class="card">
        <table class="mr-table">
            <tr><th>Дата</th><th>Студия</th><th>Что</th><th>Статус</th><th>Комментарий</th></tr>
            <?php foreach ($history as $h): ?>
            <tr><td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td><td><?= htmlspecialchars($nameOf((int)$h['studio_id'])) ?></td>
                <td><?= $h['kind'] === 'onboarding' ? 'анкета' : 'реквизиты' ?></td><td><?= htmlspecialchars($h['status']) ?></td><td><?= htmlspecialchars((string)$h['comment']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<?php
function mn_bank_errs(string $type, array $bank): array
{
    [, $e] = mon_validate_bank($type, $bank, (string)($bank['recipient'] ?? ''));
    $out = [];
    foreach ($e as $k => $v) $out['bank.' . $k] = $v;
    return $out;
}

function mon_offer_hash_for(string $type, string $version): string
{
    require_once __DIR__ . '/../swad/controllers/mon/offer.php';
    return $version === MON_OFFER_VERSION ? mon_offer_hash($type) : '';
}

require __DIR__ . '/includes/footer.php';
