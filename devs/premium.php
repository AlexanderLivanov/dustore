<?php
declare(strict_types=1);

/**
 * devs/premium.php — «Dustore Premium»: платформенный админ выдаёт/снимает
 * синюю галочку и рамку аватара. Статус — users.premium_until, журнал —
 * premium_grants (миграция migrations/0002_dustore_premium.sql).
 *
 * AJAX-поиск (?ajax=search_users) отвечает ДО подключения header.php — тот же
 * приём, что в devs/giveach.php: header печатает обвязку консоли, JSON после
 * неё отдать нельзя.
 */

if (($_GET['ajax'] ?? '') === 'search_users') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json; charset=utf-8');

    $u = $_SESSION['USERDATA'] ?? null;
    if (!$u || (int)($u['global_role'] ?? 0) !== -1) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit();
    }
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'users' => []]); exit(); }

    require_once(__DIR__ . '/../swad/config.php');
    $pdo  = (new Database())->connect();
    $esc  = addcslashes($q, '%_\\');
    $st   = $pdo->prepare(
        "SELECT id, username, telegram_username FROM users
         WHERE username LIKE ? OR telegram_username LIKE ?
         ORDER BY (username LIKE ?) DESC, username ASC LIMIT 12"
    );
    $st->execute(['%' . $esc . '%', '%' . $esc . '%', $esc . '%']);
    echo json_encode(['ok' => true, 'users' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit();
}

$page_title = 'Dustore Premium';
$active_nav = 'premium';
require_once(__DIR__ . '/includes/header.php');
require_once(__DIR__ . '/../swad/controllers/csrf.php');
require_once(__DIR__ . '/../swad/controllers/premium.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();
$success_msg = '';
$error_msg   = '';

/* срок в днях; 'forever' — 2099-12-31 */
$periods = ['7' => '7 дней', '30' => '30 дней', '90' => '90 дней', '365' => 'Год', 'forever' => 'Навсегда'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $act    = ($_POST['action'] ?? '') === 'revoke' ? 'revoke' : 'grant';
    $period = (string)($_POST['period'] ?? '30');
    $note   = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);

    if (!csrf_valid()) {
        $error_msg = 'Сессия устарела — обновите страницу и повторите.';
    } elseif ($uid <= 0) {
        $error_msg = 'Выберите пользователя из списка поиска.';
    } elseif ($act === 'grant' && !isset($periods[$period])) {
        $error_msg = 'Неизвестный срок.';
    } else {
        try {
            $conn->beginTransaction();
            $st = $conn->prepare("SELECT username, premium_until FROM users WHERE id = ? FOR UPDATE");
            $st->execute([$uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Пользователь не найден.');

            $now = new DateTimeImmutable('now', dp_tz());
            if ($act === 'revoke') {
                $until = null;
            } elseif ($period === 'forever') {
                $until = DP_FOREVER;
            } else {
                /* продлеваем от текущего срока, если он ещё идёт (и это не «навсегда») */
                $cur  = dp_is_premium($row['premium_until']) ? new DateTimeImmutable((string)$row['premium_until'], dp_tz()) : null;
                $base = ($cur && (int)$cur->format('Y') < 2099) ? $cur : $now;
                $until = $base->modify('+' . (int)$period . ' days')->format('Y-m-d H:i:s');
            }

            $conn->prepare("UPDATE users SET premium_until = ? WHERE id = ?")->execute([$until, $uid]);
            $conn->prepare(
                "INSERT INTO premium_grants (user_id, granted_by, action, until, note, created_at) VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$uid, (int)($curr_user_data['id'] ?? 0), $act, $until, $note !== '' ? $note : null, $now->format('Y-m-d H:i:s')]);
            $conn->commit();

            $success_msg = $act === 'revoke'
                ? 'Premium снят у @' . $row['username'] . '.'
                : 'Premium у @' . $row['username'] . ': ' . dp_until_label($until) . '.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('[devs/premium] ' . $e->getMessage());
            $error_msg = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Не удалось сохранить. Применена ли миграция 0002_dustore_premium.sql?';
        }
    }
}

/* списки: активные и последние операции */
$active = []; $log = []; $schema_ok = true;
try {
    $nowStr = (new DateTimeImmutable('now', dp_tz()))->format('Y-m-d H:i:s');
    $st = $conn->prepare("SELECT id, username, premium_until FROM users WHERE premium_until > ? ORDER BY premium_until DESC LIMIT 100");
    $st->execute([$nowStr]);
    $active = $st->fetchAll(PDO::FETCH_ASSOC);
    $log = $conn->query(
        "SELECT g.action, g.until, g.note, g.created_at, u.username AS target, a.username AS admin
         FROM premium_grants g
         LEFT JOIN users u ON u.id = g.user_id
         LEFT JOIN users a ON a.id = g.granted_by
         ORDER BY g.id DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $schema_ok = false;
}
$h = fn($s) => htmlspecialchars((string)$s);
?>

<link rel="stylesheet" href="/swad/css/premium.css?v=<?= (int)@filemtime(__DIR__ . '/../swad/css/premium.css') ?>">
<style>
    .pm-pick { position: relative; }
    .pm-results { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 5; max-height: 260px; overflow-y: auto;
        background: var(--surf); border: 1px solid var(--bd); border-radius: 8px; box-shadow: 0 12px 30px rgba(0, 0, 0, .4); }
    .pm-result { display: flex; gap: 6px; align-items: baseline; width: 100%; padding: 8px 12px; border: 0; background: none;
        color: var(--tt); font: inherit; font-size: 13px; text-align: left; cursor: pointer; }
    .pm-result:hover { background: var(--elev); }
    .pm-result small { color: var(--tm); }
    .pm-chosen { margin-top: 10px; font-size: 13px; font-weight: 600; min-height: 20px; }
    .pm-periods { display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 14px; }
    .pm-periods label { padding: 7px 12px; border: 1px solid var(--bd); border-radius: 999px; background: var(--elev); font-size: 12px; cursor: pointer; }
    .pm-periods label:has(input:checked) { border-color: #2aabee; background: rgba(42, 171, 238, .14); }
    .pm-periods input { position: absolute; opacity: 0; pointer-events: none; }
    .pm-row { display: flex; justify-content: space-between; gap: 10px; padding: 8px 10px; background: var(--elev); border-radius: 8px; font-size: 13px; }
    .pm-row small { color: var(--tm); }
    .pm-list { display: flex; flex-direction: column; gap: 6px; }
</style>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= $h($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= $h($error_msg) ?></div><?php endif; ?>
<?php if (!$schema_ok): ?><div class="alert alert-err">Таблицы Premium не найдены — примените <code>migrations/0002_dustore_premium.sql</code> (<code>php tools/migrate.php</code>).</div><?php endif; ?>

<div class="grid-2" style="gap:16px;align-items:start;">
    <form method="POST" class="card" id="pmForm">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" id="pmUid" value="">
        <div class="card-title"><span class="material-icons">verified</span>Выдать Premium</div>

        <div class="field">
            <div class="pm-pick" id="pmPick">
                <input type="text" id="pmSearch" placeholder="Ник пользователя…" autocomplete="off">
                <div class="pm-results" id="pmResults" hidden></div>
            </div>
            <div class="pm-chosen" id="pmChosen">Пользователь не выбран</div>
        </div>

        <div class="pm-periods">
            <?php foreach ($periods as $k => $label): ?>
                <label><input type="radio" name="period" value="<?= $h($k) ?>" <?= $k === '30' ? 'checked' : '' ?>><?= $h($label) ?></label>
            <?php endforeach; ?>
        </div>

        <div class="field">
            <input type="text" name="note" maxlength="255" placeholder="Заметка (за что выдаём) — по желанию">
        </div>

        <div style="display:flex;gap:8px;margin-top:6px;">
            <button type="submit" name="action" value="grant" class="btn btn-p" style="flex:1;justify-content:center;">
                <span class="material-icons">check_circle</span>Выдать / продлить
            </button>
            <button type="submit" name="action" value="revoke" class="btn" style="justify-content:center;"
                onclick="return confirm('Снять Premium у выбранного пользователя?');">Снять</button>
        </div>
    </form>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-title"><span class="material-icons">workspace_premium</span>Сейчас с Premium (<?= count($active) ?>)</div>
            <div class="pm-list">
                <?php foreach ($active as $r): ?>
                    <div class="pm-row"><span>@<?= $h($r['username']) ?> <?= dp_badge($r['premium_until']) ?></span><small><?= $h(dp_until_label($r['premium_until'])) ?></small></div>
                <?php endforeach; ?>
                <?php if (!$active): ?><div class="pm-row"><small>Пока никого</small></div><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-title"><span class="material-icons">history</span>Журнал</div>
            <div class="pm-list">
                <?php foreach ($log as $r): ?>
                    <div class="pm-row">
                        <span><?= $r['action'] === 'revoke' ? 'Снят' : 'Выдан' ?> · @<?= $h($r['target'] ?? '?') ?><?= $r['note'] ? ' — ' . $h($r['note']) : '' ?></span>
                        <small><?= $h(date('d.m.Y H:i', strtotime((string)$r['created_at']))) ?> · @<?= $h($r['admin'] ?? '?') ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$log): ?><div class="pm-row"><small>Операций ещё не было</small></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const search = document.getElementById('pmSearch'), results = document.getElementById('pmResults'),
          chosen = document.getElementById('pmChosen'), uid = document.getElementById('pmUid');
    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    let t = null;

    search.addEventListener('input', () => {
        clearTimeout(t);
        uid.value = ''; chosen.textContent = 'Пользователь не выбран';
        const q = search.value.trim();
        if (q.length < 2) { results.hidden = true; return; }
        t = setTimeout(async () => {
            let data;
            try { data = await (await fetch('?ajax=search_users&q=' + encodeURIComponent(q))).json(); }
            catch (e) { results.hidden = true; return; }
            const users = data.users || [];
            results.innerHTML = users.length
                ? users.map(u => `<button type="button" class="pm-result" data-id="${u.id}" data-name="${esc(u.username || '')}">${esc(u.username || '')}${u.telegram_username ? `<small>@${esc(u.telegram_username)}</small>` : ''}</button>`).join('')
                : '<div class="pm-result"><small>Никого не найдено</small></div>';
            results.hidden = false;
        }, 250);
    });

    results.addEventListener('click', e => {
        const b = e.target.closest('.pm-result[data-id]'); if (!b) return;
        uid.value = b.dataset.id; chosen.textContent = '@' + b.dataset.name;
        search.value = b.dataset.name; results.hidden = true;
    });
    document.addEventListener('click', e => { if (!document.getElementById('pmPick').contains(e.target)) results.hidden = true; });
    document.getElementById('pmForm').addEventListener('submit', e => {
        if (!uid.value) { e.preventDefault(); alert('Выберите пользователя из списка поиска.'); }
    });
})();
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>
