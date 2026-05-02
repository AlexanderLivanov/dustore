<?php
$page_title = 'Монетизация';
$active_nav = 'monetization';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Добавляем колонки YooKassa если ещё нет (idempotent)
try {
    $conn->exec("ALTER TABLE studios ADD COLUMN IF NOT EXISTS yookassa_shop_id VARCHAR(32) DEFAULT NULL");
    $conn->exec("ALTER TABLE studios ADD COLUMN IF NOT EXISTS yookassa_token VARCHAR(128) DEFAULT NULL");
} catch (PDOException $e) { /* уже существуют */
}

$stmt = $conn->prepare("SELECT * FROM studios WHERE id=?");
$stmt->execute([$studio_id]);
$pay = $stmt->fetch(PDO::FETCH_ASSOC);

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'yookassa') {
        $shop_id = preg_replace('/[^0-9]/', '', $_POST['yookassa_shop_id'] ?? '');
        $token   = trim($_POST['yookassa_token'] ?? '');
        // Обновляем только если заполнены (токен скрываем — не перезаписываем пустым)
        if (!empty($shop_id)) {
            $conn->prepare("UPDATE studios SET yookassa_shop_id=? WHERE id=?")->execute([$shop_id, $studio_id]);
        }
        if (!empty($token)) {
            $conn->prepare("UPDATE studios SET yookassa_token=? WHERE id=?")->execute([$token, $studio_id]);
        }
        $success_msg = 'Настройки ЮКасса сохранены.';
    } elseif ($section === 'bank') {
        $bank_name = substr($_POST['bank_name'] ?? '', 0, 64);
        $BIC       = preg_replace('/[^0-9]/', '', $_POST['BIC'] ?? '');
        $acc_num   = preg_replace('/[^0-9]/', '', $_POST['acc_num'] ?? '');
        $INN       = preg_replace('/[^0-9]/', '', $_POST['INN'] ?? '');
        $conn->prepare("UPDATE studios SET bank_name=?,BIC=?,acc_num=?,INN=? WHERE id=?")
            ->execute([$bank_name, $BIC, $acc_num, $INN, $studio_id]);
        $success_msg = 'Банковские реквизиты сохранены.';
    }

    // Перечитываем
    $stmt->execute([$studio_id]);
    $pay = $stmt->fetch(PDO::FETCH_ASSOC);
}

$yk_ok   = !empty($pay['yookassa_shop_id']) && !empty($pay['yookassa_token']);
$bank_ok = !empty($pay['bank_name']) && !empty($pay['BIC']);

function pv($a, $k)
{
    return htmlspecialchars($a[$k] ?? '');
}
?>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

<!-- Status row -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <div class="card" style="flex:1;min-width:200px;display:flex;align-items:center;gap:12px;padding:14px;">
        <span class="material-icons" style="font-size:24px;color:<?= $yk_ok ? 'var(--ok)' : 'var(--tm)' ?>;">
            <?= $yk_ok ? 'check_circle' : 'radio_button_unchecked' ?>
        </span>
        <div>
            <div style="font-size:13px;font-weight:600;">ЮКасса</div>
            <div style="font-size:11px;color:var(--tm);">
                <?= $yk_ok ? 'Подключена · магазин #' . pv($pay, 'yookassa_shop_id') : 'Не настроена' ?>
            </div>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:200px;display:flex;align-items:center;gap:12px;padding:14px;">
        <span class="material-icons" style="font-size:24px;color:<?= $bank_ok ? 'var(--ok)' : 'var(--tm)' ?>;">
            <?= $bank_ok ? 'check_circle' : 'radio_button_unchecked' ?>
        </span>
        <div>
            <div style="font-size:13px;font-weight:600;">Банковские реквизиты</div>
            <div style="font-size:11px;color:var(--tm);"><?= $bank_ok ? pv($pay, 'bank_name') : 'Не заполнены' ?></div>
        </div>
    </div>
</div>

<div class="grid-2" style="gap:16px;align-items:start;">

    <!-- ЮКасса -->
    <div class="card">
        <div class="card-title">
            <span class="material-icons">payment</span>ЮКасса
            <a href="https://yookassa.ru" target="_blank"
                style="margin-left:auto;font-size:11px;color:var(--p);text-decoration:none;">yookassa.ru ↗</a>
        </div>

        <div class="alert" style="background:rgba(0,214,143,.06);border:1px solid rgba(0,214,143,.15);color:var(--ts);font-size:12px;margin-bottom:16px;line-height:1.6;">
            <strong style="color:var(--ok);">Рекомендуется.</strong>
            ЮКасса поддерживает оплату картой, СБП, ЮMoney и другими методами. Комиссия от 2.8%.
        </div>

        <form method="POST">
            <input type="hidden" name="section" value="yookassa">
            <div class="field">
                <label>Идентификатор магазина (shopId)</label>
                <input type="text" name="yookassa_shop_id"
                    value="<?= pv($pay, 'yookassa_shop_id') ?>"
                    placeholder="123456" maxlength="32">
            </div>
            <div class="field">
                <label><?= !empty($pay['yookassa_token']) ? 'Секретный ключ (установлен — оставьте пустым чтобы не менять)' : 'Секретный ключ' ?></label>
                <input type="password" name="yookassa_token"
                    placeholder="<?= !empty($pay['yookassa_token']) ? '••••••••' : 'live_XXXXXX...' ?>"
                    maxlength="128" autocomplete="new-password">
            </div>
            <?php if (!empty($pay['yookassa_token'])): ?>
                <div class="alert alert-warn" style="font-size:12px;margin-bottom:12px;">
                    ⚠️ Ключ установлен. Введите новый только если хотите его заменить.
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                <span class="material-icons">save</span>Сохранить ЮКассу
            </button>
        </form>

        <!-- Robokassa — отключена -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--bd);">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                <span class="material-icons" style="font-size:16px;color:var(--tm);">block</span>
                <span style="font-size:13px;font-weight:600;color:var(--tm);">Robokassa</span>
                <span style="font-size:10px;background:var(--elev);color:var(--tm);padding:1px 8px;border-radius:4px;border:1px solid var(--bd);">недоступна</span>
            </div>
            <div style="font-size:12px;color:var(--tm);line-height:1.6;">
                Robokassa временно отключена. Используйте ЮКассу — она поддерживает аналогичные методы оплаты.
            </div>
        </div>
    </div>

    <!-- Банк -->
    <div class="card">
        <div class="card-title"><span class="material-icons">account_balance</span>Банковские реквизиты</div>
        <div style="font-size:12px;color:var(--ts);line-height:1.6;margin-bottom:14px;">
            Используются для выплат и формирования платёжных документов.
        </div>
        <form method="POST">
            <input type="hidden" name="section" value="bank">
            <div class="field"><label>Название банка</label>
                <input type="text" name="bank_name" value="<?= pv($pay, 'bank_name') ?>" maxlength="64" placeholder='АО «Сбербанк»'>
            </div>
            <div class="field"><label>БИК</label>
                <input type="text" name="BIC" value="<?= pv($pay, 'BIC') ?>" maxlength="9" placeholder="044525225">
            </div>
            <div class="field"><label>Расчётный счёт</label>
                <input type="text" name="acc_num" value="<?= pv($pay, 'acc_num') ?>" maxlength="20" placeholder="40702810...">
            </div>
            <div class="field"><label>ИНН</label>
                <input type="text" name="INN" value="<?= pv($pay, 'INN') ?>" maxlength="12" placeholder="7700000000">
            </div>
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                <span class="material-icons">save</span>Сохранить реквизиты
            </button>
        </form>
    </div>

</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>