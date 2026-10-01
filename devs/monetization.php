<?php
$page_title = 'Монетизация';
$active_nav = 'monetization';
require_once(__DIR__ . '/includes/header.php');

require_once(__DIR__ . '/../swad/controllers/csrf.php');
require_once(__DIR__ . '/../swad/fx/boot.php');

$conn = $db->connect();
Fx::use($conn);

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

    /* Донаты, закрытая комната и ссылки обратной связи — то, что видно на публичной странице студии */
    elseif (str_starts_with((string)$section, 'fx_')) {
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


<?php
/* ── Публичная страница студии: донаты, закрытая комната, ссылки ── */
$fxCan   = FxAuth::isStudioOwner($studio_id, (int)$user_id);
$fxMon   = FxMonet::get($studio_id);
$fxLinks = FxMonet::links($studio_id) ?: FxStats::feedback($pay);   // ещё ничего не сохраняли — то, что уже видят посетители из карточки студии
$fxLinks = array_map(static fn($l) => ['label' => $l['label'], 'url' => preg_replace('#^mailto:#', '', (string)$l['url'])], $fxLinks);
$fxLinks = array_slice(array_pad($fxLinks, max(count($fxLinks) + 1, 3), ['label' => '', 'url' => '']), 0, 8);
?>
<div style="display:flex;align-items:center;gap:10px;margin:28px 0 12px;">
    <span class="material-icons" style="color:var(--p);">storefront</span>
    <div>
        <div style="font-size:15px;font-weight:700;">Страница студии</div>
        <div style="font-size:12px;color:var(--tm);">Эти настройки видят посетители на <a href="/d/<?= htmlspecialchars((string)($pay['tiker'] ?? '')) ?>" target="_blank" style="color:var(--p);text-decoration:none;">публичной странице студии ↗</a></div>
    </div>
</div>

<?php if (!$fxCan): ?>
    <div class="alert" style="margin-bottom:14px;background:rgba(255,255,255,.04);border:1px solid var(--bd);color:var(--ts);">
        <span class="material-icons" style="vertical-align:-6px;margin-right:6px;">lock</span>Смотреть можно всем сотрудникам, менять — только владелец или администратор студии.
    </div>
<?php endif; ?>

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

<style>
    @keyframes fxFlash { 0% { box-shadow: 0 0 0 3px var(--p, #c32178); } 100% { box-shadow: 0 0 0 3px transparent; } }
    .fx-flash { animation: fxFlash 1.8s ease-out; border-radius: 12px; }
</style>
<script>
(function () {
    // «ещё ссылка»: не больше 8 строк
    var rows = document.getElementById('fx-link-rows'), add = document.getElementById('fx-link-add');
    if (rows && add) add.addEventListener('click', function () {
        if (rows.children.length >= 8) { add.disabled = true; return; }
        var d = rows.lastElementChild.cloneNode(true);
        d.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        rows.appendChild(d);
        if (rows.children.length >= 8) add.disabled = true;
    });
    // переход по шестерёнке со страницы студии: #donate | #room | #links
    var h = decodeURIComponent(location.hash.replace('#', ''));
    var el = h && document.getElementById(h);
    if (el) {
        el.scrollIntoView({ block: 'center' });
        el.classList.add('fx-flash');
        setTimeout(function () { el.classList.remove('fx-flash'); }, 2200);
    }
})();
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>
