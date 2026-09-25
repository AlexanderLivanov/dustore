<?php
/**
 * devs/notifications.php — уведомления платформы (только администраторы).
 *
 *   • аналитика: у скольких включены пуши, какие устройства, доставка, прочитанность;
 *   • рассылка: каналы галочками (сайт / пуш / почта), адресаты — все, с пушами,
 *     разработчики, админы или список ников/ID; своя иконка и ссылка;
 *   • история рассылок.
 *
 * Сайт и пуш — это вставки в БД, они мгновенные. Почта медленная (SMTP на
 * каждое письмо), поэтому уходит в фоне через devs/broadcast_mail.php, а
 * страница показывает прогресс.
 *
 * POST обрабатывается ДО вывода и заканчивается редиректом (Post/Redirect/Get):
 * иначе F5 после отправки повторял рассылку всем адресатам.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/../swad/controllers/user.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';
require_once __DIR__ . '/../swad/controllers/loopback.php';
require_once __DIR__ . '/../chat/push_helpers.php';
require_once __DIR__ . '/../chat/_bridge.php';

const BC_AUDIENCES = [
    'push'   => 'С включёнными пушами',
    'all'    => 'Все пользователи',
    'devs'   => 'Разработчики (владельцы студий)',
    'admins' => 'Администраторы',
    'list'   => 'Список ников или ID',
];

/** Таблица истории и колонка иконки у очереди пушей — создаём сами, один раз. */
function bc_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS broadcasts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        created_by  INT NOT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        title       VARCHAR(120) NOT NULL,
        body        TEXT NOT NULL,
        url         VARCHAR(255) NULL,
        icon        VARCHAR(255) NULL,
        channels    VARCHAR(32) NOT NULL,
        audience    VARCHAR(16) NOT NULL,
        recipients  MEDIUMTEXT NOT NULL,
        n_users     INT NOT NULL DEFAULT 0,
        n_site      INT NOT NULL DEFAULT 0,
        n_push      INT NOT NULL DEFAULT 0,
        n_email     INT NOT NULL DEFAULT 0,
        email_sent  INT NOT NULL DEFAULT 0,
        email_fail  INT NOT NULL DEFAULT 0,
        email_done  TINYINT NOT NULL DEFAULT 0
    ) DEFAULT CHARSET=utf8mb4");
    if (!push_has_icon_column($db)) {
        try { $db->exec("ALTER TABLE push_outbox ADD COLUMN icon VARCHAR(255) NULL"); } catch (Throwable $e) { }
    }
}

/** ID получателей по типу аудитории. */
function bc_audience(PDO $db, string $aud, string $list): array {
    switch ($aud) {
        case 'all':    $ids = $db->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN); break;
        case 'push':   $ids = $db->query("SELECT DISTINCT user_id FROM push_subscriptions")->fetchAll(PDO::FETCH_COLUMN); break;
        case 'devs':   $ids = $db->query("SELECT DISTINCT owner_id FROM studios WHERE owner_id > 0")->fetchAll(PDO::FETCH_COLUMN); break;
        case 'admins': $ids = $db->query("SELECT id FROM users WHERE global_role = -1")->fetchAll(PDO::FETCH_COLUMN); break;
        case 'list':
            $ids = [];
            $names = [];
            foreach (preg_split('/[\s,;]+/u', $list, -1, PREG_SPLIT_NO_EMPTY) as $t) {
                $t = ltrim($t, '@');
                if (ctype_digit($t)) $ids[] = (int)$t; else $names[] = $t;
            }
            if ($names) {
                $in = implode(',', array_fill(0, count($names), '?'));
                $q = $db->prepare("SELECT id FROM users WHERE username IN ($in)");
                $q->execute($names);
                $ids = array_merge($ids, $q->fetchAll(PDO::FETCH_COLUMN));
            }
            if ($ids) {        // только существующие
                $in = implode(',', array_fill(0, count($ids), '?'));
                $q = $db->prepare("SELECT id FROM users WHERE id IN ($in)");
                $q->execute(array_map('intval', $ids));
                $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            break;
        default: $ids = [];
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

/* ── Отправка (до любого вывода) ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // та же проверка, что в includes/header.php, плюс роль — из свежих данных checkAuth()
    if ((new User())->checkAuth() > 0 || (int)($_SESSION['USERDATA']['global_role'] ?? 0) !== -1) {
        http_response_code(403); exit('Доступно только администраторам платформы');
    }
    $meId = (int)$_SESSION['USERDATA']['id'];
    $db0  = (new Database())->connect();
    $db0->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    bc_schema($db0);

    $title = trim((string)($_POST['title'] ?? ''));
    $body  = trim((string)($_POST['body'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $icon  = trim((string)($_POST['icon'] ?? ''));
    $aud   = array_key_exists($_POST['audience'] ?? '', BC_AUDIENCES) ? $_POST['audience'] : 'push';
    $list  = (string)($_POST['list'] ?? '');
    $ch    = array_values(array_intersect(['site', 'push', 'email'], (array)($_POST['ch'] ?? [])));
    $test  = isset($_POST['test']);

    // ссылки только свои (/...) или https — никакого javascript: в пуше
    $safeUrl = fn(string $u) => $u === '' || $u[0] === '/' || preg_match('~^https://~i', $u);
    $msg = ''; $err = '';

    if (!csrf_valid())                             $err = 'Сессия устарела — обновите страницу.';
    elseif ($title === '' || $body === '')         $err = 'Нужны заголовок и текст.';
    elseif (mb_strlen($title) > 120)               $err = 'Заголовок длиннее 120 символов.';
    elseif (!$ch)                                  $err = 'Выберите хотя бы один канал.';
    elseif (!$safeUrl($url) || !$safeUrl($icon))   $err = 'Ссылка и иконка — только путь на сайте (/...) или https://';
    else {
        $ids = $test ? [$meId] : bc_audience($db0, $aud, $list);
        if (!$ids) {
            $err = 'Получателей не нашлось.';
        } else {
            $n = ['site' => 0, 'push' => 0, 'email' => 0];
            if (in_array('site', $ch, true)) {
                $ins = $db0->prepare("INSERT INTO notifications (user_id, title, message, action, status, date) VALUES (?, ?, ?, ?, 'unread', NOW())");
                foreach ($ids as $id) { $ins->execute([$id, $title, $body, $url ?: null]); $n['site']++; }
            }
            if (in_array('push', $ch, true)) {
                // клик по пушу: своя ссылка, иначе лента «Уведомления» (если туда тоже писали) или главная
                $pushUrl = $url ?: (in_array('site', $ch, true) ? '/chat/?system=1' : '/');
                foreach ($ids as $id) if (push_enqueue_user($db0, $id, $title, $body, $pushUrl, $icon ?: null)) $n['push']++;
            }
            if (in_array('email', $ch, true)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $q = $db0->prepare("SELECT COUNT(*) FROM users WHERE id IN ($in) AND email LIKE '%@%'");
                $q->execute($ids);
                $n['email'] = (int)$q->fetchColumn();
            }
            $db0->prepare("INSERT INTO broadcasts (created_by, title, body, url, icon, channels, audience, recipients, n_users, n_site, n_push, n_email, email_done)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$meId, $title, $body, $url ?: null, $icon ?: null, implode(',', $ch), $test ? 'test' : $aud,
                           json_encode($ids), count($ids), $n['site'], $n['push'], $n['email'], $n['email'] ? 0 : 1]);
            $bid = (int)$db0->lastInsertId();
            if ($n['email']) {
                loopback_fire('/devs/broadcast_mail.php', json_encode(['secret' => bridge_secret(), 'id' => $bid]), 'application/json');
            }
            $parts = [];
            if (in_array('site', $ch, true))  $parts[] = "на сайт — {$n['site']}";
            if (in_array('push', $ch, true))  $parts[] = "пушей в очереди — {$n['push']}";
            if (in_array('email', $ch, true)) $parts[] = "писем — {$n['email']} (уходят в фоне)";
            $msg = ($test ? 'Тест себе: ' : 'Рассылка #' . $bid . ': ') . count($ids) . ' получ.; ' . implode(', ', $parts) . '.';
        }
    }
    // при ошибке вернём введённое в форму, при успехе — чистая форма
    $_SESSION['bc_flash'] = ['msg' => $msg, 'err' => $err, 'form' => $err ? $_POST : null];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

$page_title = 'Уведомления';
$active_nav = 'notifications';
require_once(__DIR__ . '/includes/header.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$flash = $_SESSION['bc_flash'] ?? ['msg' => '', 'err' => '', 'form' => null];
unset($_SESSION['bc_flash']);
$msg  = $flash['msg'];
$err  = $flash['err'];
$form = $flash['form'] ?? [];

$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

bc_schema($conn);

/* ── Аналитика ────────────────────────────────────────────────────────── */
$one = fn(string $sql) => (int)$conn->query($sql)->fetchColumn();
$usersTotal  = $one("SELECT COUNT(*) FROM users");
$usersPush   = $one("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions");
$usersEmail  = $one("SELECT COUNT(*) FROM users WHERE email LIKE '%@%'");
$devices     = $one("SELECT COUNT(DISTINCT endpoint) FROM push_subscriptions");
$pct = fn(int $a, int $b) => $b ? round($a * 100 / $b) : 0;

$byService = [];
foreach ($conn->query("SELECT endpoint, COUNT(DISTINCT user_id) u FROM push_subscriptions GROUP BY endpoint")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $h = (string)parse_url($r['endpoint'], PHP_URL_HOST);
    $k = str_contains($h, 'apple') ? 'iPhone / Safari' : (str_contains($h, 'mozilla') ? 'Firefox' : (str_contains($h, 'windows') || str_contains($h, 'notify.windows') ? 'Edge / Windows' : (str_contains($h, 'google') ? 'Chrome / Android' : 'Другие')));
    $byService[$k] = ($byService[$k] ?? 0) + 1;
}
arsort($byService);

$q7 = $conn->query("SELECT status, COUNT(*) n FROM push_outbox WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$sent7 = (int)($q7['sent'] ?? 0); $fail7 = (int)($q7['failed'] ?? 0); $pend = $one("SELECT COUNT(*) FROM push_outbox WHERE status='pending'");
$n7    = $conn->query("SELECT COUNT(*) total, SUM(status='read') rd FROM notifications WHERE date >= NOW() - INTERVAL 7 DAY")->fetch(PDO::FETCH_ASSOC);

$history = $conn->query("SELECT b.*, u.username FROM broadcasts b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
$audCounts = [];
foreach (array_keys(BC_AUDIENCES) as $a) if ($a !== 'list') $audCounts[$a] = count(bc_audience($conn, $a, ''));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<?php if ($msg): ?><div class="alert alert-ok"><?= $h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= $h($err) ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">notifications_active</span></div>
        <div class="stat-num"><?= $usersPush ?> <small style="font-size:13px;color:var(--tm)">/ <?= $usersTotal ?></small></div>
        <div class="stat-label">Включили пуши · <?= $pct($usersPush, $usersTotal) ?>%</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">devices</span></div>
        <div class="stat-num"><?= $devices ?></div>
        <div class="stat-label">Устройств с подпиской</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">send</span></div>
        <div class="stat-num"><?= $sent7 ?></div>
        <div class="stat-label">Пушей за 7 дней · доставлено <?= $pct($sent7, $sent7 + $fail7) ?>%<?= $pend ? " · в очереди {$pend}" : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">mark_email_read</span></div>
        <div class="stat-num"><?= (int)$n7['total'] ?></div>
        <div class="stat-label">На сайте за 7 дней · прочитано <?= $pct((int)$n7['rd'], (int)$n7['total']) ?>%</div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns:1.4fr 1fr;align-items:start;margin-bottom:20px;">
    <form class="card" method="post" id="bcForm">
        <div class="card-title"><span class="material-icons">campaign</span>Новая рассылка</div>
        <?= csrf_field() ?>
        <div class="field"><label>Заголовок</label><input name="title" maxlength="120" required placeholder="Например: Хэллоуин на Dustore" value="<?= $h($form['title'] ?? '') ?>"></div>
        <div class="field"><label>Текст</label><textarea name="body" required placeholder="Коротко: в пуш влезает ~140 символов"><?= $h($form['body'] ?? '') ?></textarea></div>
        <div class="grid-2">
            <div class="field"><label>Ссылка по клику (необязательно)</label><input name="url" placeholder="/explore или https://…" value="<?= $h($form['url'] ?? '') ?>"></div>
            <div class="field"><label>Иконка пуша (необязательно)</label><input name="icon" placeholder="/m/icons/icon-192.png" value="<?= $h($form['icon'] ?? '') ?>"></div>
        </div>

        <div class="field"><label>Каналы</label>
            <div class="bc-ch">
                <label><input type="checkbox" name="ch[]" value="site" checked> <span class="material-icons">web</span>На сайт</label>
                <label><input type="checkbox" name="ch[]" value="push" checked> <span class="material-icons">notifications</span>Пуш</label>
                <label><input type="checkbox" name="ch[]" value="email"> <span class="material-icons">mail</span>Почта</label>
            </div>
        </div>

        <div class="field"><label>Кому</label>
            <select name="audience" id="bcAud">
                <?php foreach (BC_AUDIENCES as $k => $label): ?>
                    <option value="<?= $k ?>"<?= ($form['audience'] ?? 'push') === $k ? ' selected' : '' ?>><?= $h($label) ?><?= isset($audCounts[$k]) ? ' — ' . $audCounts[$k] : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" id="bcList" hidden><label>Ники или ID через запятую или пробел</label><textarea name="list" placeholder="@vasya, petya, 42"><?= $h($form['list'] ?? '') ?></textarea></div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn btn-p" type="submit" name="send" value="1"><span class="material-icons" style="font-size:16px">send</span>Отправить</button>
            <button class="btn btn-g" type="submit" name="test" value="1"><span class="material-icons" style="font-size:16px">person</span>Сначала себе</button>
        </div>
    </form>

    <div class="card">
        <div class="card-title"><span class="material-icons">pie_chart</span>Где включены пуши</div>
        <?php if (!$byService): ?><p style="color:var(--tm);font-size:13px;">Подписок пока нет.</p><?php endif; ?>
        <?php foreach ($byService as $k => $v): ?>
            <div class="bc-bar"><span><?= $h($k) ?></span><b><?= $v ?></b><i style="--w:<?= $pct($v, $devices) ?>%"></i></div>
        <?php endforeach; ?>
        <div class="card-title" style="margin-top:18px;"><span class="material-icons">info</span>Охват каналов</div>
        <div class="bc-bar"><span>Пуш</span><b><?= $usersPush ?></b><i style="--w:<?= $pct($usersPush, $usersTotal) ?>%"></i></div>
        <div class="bc-bar"><span>Почта</span><b><?= $usersEmail ?></b><i style="--w:<?= $pct($usersEmail, $usersTotal) ?>%"></i></div>
        <div class="bc-bar"><span>Сайт</span><b><?= $usersTotal ?></b><i style="--w:100%"></i></div>
        <p style="color:var(--tm);font-size:12px;margin-top:10px;line-height:1.5;">Звук пуша задаёт система устройства — сайт его не выбирает. В открытом чате играет звук из настроек чата.</p>
    </div>
</div>

<div class="card">
    <div class="card-title"><span class="material-icons">history</span>История рассылок</div>
    <?php if (!$history): ?><p style="color:var(--tm);font-size:13px;">Рассылок ещё не было.</p><?php else: ?>
    <div style="overflow-x:auto;">
    <table class="bc-table">
        <tr><th>#</th><th>Когда</th><th>Заголовок</th><th>Кому</th><th>Сайт</th><th>Пуш</th><th>Почта</th><th>Автор</th></tr>
        <?php foreach ($history as $b): ?>
        <tr>
            <td><?= (int)$b['id'] ?></td>
            <td><?= $h(date('d.m H:i', strtotime($b['created_at']))) ?></td>
            <td title="<?= $h($b['body']) ?>"><?= $h(mb_strimwidth($b['title'], 0, 48, '…')) ?></td>
            <td><?= $h(BC_AUDIENCES[$b['audience']] ?? ($b['audience'] === 'test' ? 'Тест себе' : $b['audience'])) ?> · <?= (int)$b['n_users'] ?></td>
            <td><?= str_contains($b['channels'], 'site') ? (int)$b['n_site'] : '—' ?></td>
            <td><?= str_contains($b['channels'], 'push') ? (int)$b['n_push'] : '—' ?></td>
            <td><?php if (!str_contains($b['channels'], 'email')): ?>—<?php else: ?><?= (int)$b['email_sent'] ?>/<?= (int)$b['n_email'] ?><?= (int)$b['email_fail'] ? ' <span class="badge badge-err">' . (int)$b['email_fail'] . ' ошиб.</span>' : '' ?><?= !(int)$b['email_done'] ? ' <span class="badge badge-rev">идёт</span>' : '' ?><?php endif; ?></td>
            <td><?= $h($b['username'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<style>
.bc-ch { display: flex; gap: 8px; flex-wrap: wrap; }
.bc-ch label { display: inline-flex !important; align-items: center; gap: 6px; margin: 0 !important; padding: 8px 12px; border-radius: 10px;
    background: var(--elev); border: 1px solid var(--bd); color: var(--tt) !important; font-size: 13px !important; cursor: pointer; }
.bc-ch label:has(input:checked) { border-color: rgba(var(--brand-rgb, 195, 33, 120), .6); background: rgba(var(--brand-rgb, 195, 33, 120), .12); }
.bc-ch input { width: auto !important; accent-color: rgb(var(--brand-rgb, 195, 33, 120)); }
.bc-ch .material-icons { font-size: 16px; }
.bc-bar { position: relative; display: flex; justify-content: space-between; padding: 7px 10px; margin-bottom: 6px; border-radius: 8px; background: var(--elev); font-size: 13px; overflow: hidden; }
.bc-bar i { position: absolute; left: 0; top: 0; bottom: 0; width: var(--w); background: rgba(var(--brand-rgb, 195, 33, 120), .18); }
.bc-bar span, .bc-bar b { position: relative; }
.bc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.bc-table th { text-align: left; color: var(--tm); font-weight: 500; font-size: 11px; padding: 6px 8px; border-bottom: 1px solid var(--bd); }
.bc-table td { padding: 8px; border-bottom: 1px solid var(--bd); white-space: nowrap; }
@media (max-width: 900px) { .stats-grid { grid-template-columns: 1fr 1fr !important; } .grid-2 { grid-template-columns: 1fr !important; } }
</style>
<script>
(() => {
  const aud = document.getElementById('bcAud'), list = document.getElementById('bcList');
  const sync = () => { list.hidden = aud.value !== 'list'; };
  aud.addEventListener('change', sync); sync();
  document.getElementById('bcForm').addEventListener('submit', e => {
    if (e.submitter && e.submitter.name === 'test') return;
    const opt = aud.options[aud.selectedIndex].text;
    if (!confirm('Отправить рассылку: ' + opt + '?')) e.preventDefault();
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
