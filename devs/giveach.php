<?php
declare(strict_types=1);

/**
 * devs/giveach.php — «Выдать достижение»: платформенный админ выдаёт
 * глобальный бейдж (таблица badges) одному или нескольким игрокам.
 *
 * ВАЖНО (баг предыдущей версии): форма писала в given_badges(player_id,
 * ach_id, date) — а это таблица наград СТУДИЯМ (studio_id, badge_id,
 * awarded_at; см. swad/fx/pages.php:258 и шапку /d/<тикер>). Правильная
 * таблица для игрока — given_user_badges(user_id, badge_id, awarded_at),
 * её же читает профиль игрока (swad/fx/pages.php, l4t/lib/profile.php).
 * Поскольку PDO по умолчанию эмулирует prepare(), несуществующие колонки
 * не роняли prepare() — ошибка вылезала только на execute(), а он был
 * обёрнут в try/catch с пустым телом. Итог: форма молча писала «Выдано
 * 0 пользователям» и НИКОГДА ничего не сохраняла. Ниже — исправлено.
 *
 * AJAX-поиск получателей (?ajax=search_users) отвечает ДО require header.php:
 * header.php сразу печатает всю обвязку консоли (<html>…<nav>…), так что
 * чистый JSON после него отдать уже нельзя — тот же приём, что у chat/api.php
 * vs chat/index.php, только в одном файле, т.к. у каждой вкладки консоли
 * свой отдельный .php без общего роутера.
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
    if (mb_strlen($q) < 2) {
        echo json_encode(['ok' => true, 'users' => []]);
        exit();
    }

    require_once(__DIR__ . '/../swad/config.php');
    $pdo = (new Database())->connect();

    // % и _ — метасимволы LIKE, экранируем как везде в проекте (см. chat/api.php search_users)
    $esc    = addcslashes($q, '%_\\');
    $like   = '%' . $esc . '%';
    $starts = $esc . '%';
    $st = $pdo->prepare(
        "SELECT id, username, telegram_username, profile_picture FROM users
         WHERE username LIKE ? OR telegram_username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
         ORDER BY (username LIKE ?) DESC, username ASC LIMIT 12"
    );
    $st->execute([$like, $like, $like, $like, $starts]);
    echo json_encode(['ok' => true, 'users' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit();
}

$page_title = 'Выдать достижение';
$active_nav = 'giveach';
require_once(__DIR__ . '/includes/header.php');
require_once(__DIR__ . '/../swad/controllers/csrf.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();

$badges    = $conn->query("SELECT * FROM badges ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$userCount = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error_msg = 'Сессия устарела — обновите страницу и повторите.';
    } else {
        $badge_id  = (int)($_POST['badge'] ?? 0);
        $send_all  = isset($_POST['sendtoall']);
        $sel_users = array_values(array_unique(array_filter(array_map('intval', $_POST['users'] ?? []))));
        $badgeIds  = array_map('intval', array_column($badges, 'id'));

        if (!$badge_id || !in_array($badge_id, $badgeIds, true)) {
            $error_msg = 'Выберите достижение из списка.';
        } else {
            $target_ids = $send_all
                ? array_map('intval', array_column($conn->query("SELECT id FROM users")->fetchAll(PDO::FETCH_ASSOC), 'id'))
                : $sel_users;

            if (empty($target_ids)) {
                $error_msg = 'Выберите хотя бы одного пользователя или включите «Всем пользователям».';
            } else {
                // INSERT ... WHERE NOT EXISTS вместо INSERT IGNORE: работает
                // предсказуемо независимо от того, есть ли в таблице уникальный
                // ключ (user_id, badge_id) — и rowCount() честно говорит,
                // вставилась строка или нет, так что «уже было» отличимо от
                // «выдано впервые» без отдельного SELECT на каждого игрока.
                $stmt = $conn->prepare(
                    "INSERT INTO given_user_badges (user_id, badge_id, awarded_at)
                     SELECT ?, ?, NOW() FROM DUAL
                     WHERE NOT EXISTS (SELECT 1 FROM given_user_badges WHERE user_id = ? AND badge_id = ?)"
                );
                $newly = 0; $already = 0; $failed = 0;
                foreach ($target_ids as $uid) {
                    if ($uid <= 0) continue;
                    try {
                        $stmt->execute([$uid, $badge_id, $uid, $badge_id]);
                        if ($stmt->rowCount() > 0) $newly++; else $already++;
                    } catch (PDOException $e) {
                        $failed++;
                        error_log('[devs/giveach] insert failed for user ' . $uid . ': ' . $e->getMessage());
                    }
                }
                $success_msg = "Выдано новых: {$newly}"
                    . ($already ? ", уже было: {$already}" : '')
                    . ($failed  ? ", не удалось: {$failed}" : '') . '.';
            }
        }
    }
}
?>

<style>
    .ga-badges { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 4px; }
    .ga-badge-card {
        position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px;
        padding: 14px 10px; border: 1px solid var(--bd); border-radius: var(--r); background: var(--elev);
        cursor: pointer; transition: border-color .15s, background .15s;
    }
    .ga-badge-card:hover { border-color: rgba(var(--brand-rgb, 195, 33, 120), .35); }
    .ga-badge-card:has(input:checked) { border-color: var(--p); background: rgba(var(--brand-rgb, 195, 33, 120), .12); }
    .ga-badge-card input { position: absolute; opacity: 0; pointer-events: none; }
    .ga-badge-ico {
        width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        background: linear-gradient(135deg, #f3c969, #b8860b); color: #3a2a00; overflow: hidden;
    }
    .ga-badge-ico img { width: 100%; height: 100%; object-fit: cover; }
    .ga-badge-name { font-size: 12px; font-weight: 600; line-height: 1.3; }
    .ga-badge-desc { font-size: 10px; color: var(--tm); line-height: 1.3; }
    .ga-empty { text-align: center; color: var(--tm); padding: 20px; font-size: 13px; }

    .ga-allrow { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 12px; }
    .ga-allrow input { accent-color: var(--p); width: auto; }
    .ga-allrow span { font-size: 13px; font-weight: 500; }

    .ga-picker { position: relative; transition: opacity .15s; }
    .ga-picker.ga-disabled { opacity: .4; pointer-events: none; }
    .ga-results {
        position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 5; max-height: 260px; overflow-y: auto;
        background: var(--surf); border: 1px solid var(--bd); border-radius: 8px; box-shadow: 0 12px 30px rgba(0, 0, 0, .4);
    }
    .ga-result {
        display: flex; align-items: baseline; gap: 6px; width: 100%; padding: 8px 12px; border: 0; background: none;
        color: var(--tt); font: inherit; font-size: 13px; text-align: left; cursor: pointer;
    }
    .ga-result:hover { background: var(--elev); }
    .ga-result-tg { color: var(--tm); font-size: 11px; }
    .ga-result-empty { padding: 10px 12px; color: var(--tm); font-size: 12px; }

    .ga-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .ga-chip {
        display: inline-flex; align-items: center; gap: 5px; padding: 4px 6px 4px 10px; border-radius: 999px;
        background: rgba(var(--brand-rgb, 195, 33, 120), .14); color: var(--pl); font-size: 12px; font-weight: 600;
    }
    .ga-chip-x {
        width: 16px; height: 16px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .12); color: inherit;
        font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .ga-chip-x:hover { background: rgba(255, 255, 255, .22); }
</style>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

<form method="POST" id="gaForm">
    <?= csrf_field() ?>
    <div class="grid-2" style="gap:16px;align-items:start;">
        <div class="card">
            <div class="card-title"><span class="material-icons">military_tech</span>Выбор достижения</div>
            <?php if (empty($badges)): ?>
                <div class="ga-empty">Нет достижений в БД</div>
            <?php else: ?>
                <div class="ga-badges">
                    <?php foreach ($badges as $b): $bid = (int)$b['id']; ?>
                        <label class="ga-badge-card">
                            <input type="radio" name="badge" value="<?= $bid ?>" required
                                <?= ((int)($_POST['badge'] ?? 0) === $bid) ? 'checked' : '' ?>>
                            <span class="ga-badge-ico">
                                <?php if (!empty($b['icon_url'])): ?>
                                    <img src="<?= htmlspecialchars($b['icon_url']) ?>" alt="">
                                <?php else: ?>
                                    <span class="material-icons">emoji_events</span>
                                <?php endif; ?>
                            </span>
                            <span class="ga-badge-name"><?= htmlspecialchars($b['name']) ?></span>
                            <?php if (!empty($b['description'])): ?>
                                <span class="ga-badge-desc"><?= htmlspecialchars($b['description']) ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="border-top:1px solid var(--bd);margin:16px 0 14px;"></div>
            <div class="card-title"><span class="material-icons">group</span>Кому выдать</div>

            <label class="ga-allrow">
                <input type="checkbox" name="sendtoall" id="sendtoall">
                <span>Всем пользователям (<?= $userCount ?>)</span>
            </label>

            <div class="field" style="margin-bottom:0;">
                <div class="ga-picker" id="gaPicker">
                    <input type="text" id="gaSearch" placeholder="Начните вводить имя пользователя…" autocomplete="off">
                    <div class="ga-results" id="gaResults" hidden></div>
                    <div class="ga-chips" id="gaChips"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;margin-top:16px;">
                <span class="material-icons">send</span>Выдать достижение
            </button>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">list</span>Список достижений</div>
            <?php if (empty($badges)): ?>
                <div class="ga-empty">Нет достижений в БД</div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($badges as $b): ?>
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:var(--elev);border-radius:8px;">
                            <span class="material-icons" style="font-size:20px;color:var(--p);flex-shrink:0;margin-top:2px;">emoji_events</span>
                            <div>
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($b['name']) ?></div>
                                <div style="font-size:11px;color:var(--ts);margin-top:2px;"><?= htmlspecialchars($b['description'] ?? '') ?></div>
                                <?php if (!empty($b['type'])): ?>
                                    <div style="font-size:10px;color:var(--tm);margin-top:4px;"><?= htmlspecialchars($b['type']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
(function () {
    const search  = document.getElementById('gaSearch');
    const results = document.getElementById('gaResults');
    const chips   = document.getElementById('gaChips');
    const sendAll = document.getElementById('sendtoall');
    const picker  = document.getElementById('gaPicker');
    const form    = document.getElementById('gaForm');
    const selected = new Map(); // id (string) -> username

    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

    function renderChips() {
        chips.innerHTML = [...selected.entries()].map(([id, name]) =>
            `<span class="ga-chip">${esc(name)}<button type="button" class="ga-chip-x" data-id="${id}" aria-label="Убрать">&times;</button>` +
            `<input type="hidden" name="users[]" value="${id}"></span>`
        ).join('');
    }

    let t = null;
    search?.addEventListener('input', () => {
        clearTimeout(t);
        const q = search.value.trim();
        if (q.length < 2) { results.hidden = true; results.innerHTML = ''; return; }
        t = setTimeout(async () => {
            let data;
            try {
                const r = await fetch('?ajax=search_users&q=' + encodeURIComponent(q));
                data = await r.json();
            } catch (e) { results.hidden = true; return; }
            const users = (data.users || []).filter(u => !selected.has(String(u.id)));
            results.innerHTML = users.length
                ? users.map(u => `<button type="button" class="ga-result" data-id="${u.id}" data-name="${esc(u.username || '')}">` +
                    `<span>${esc(u.username || '')}</span>${u.telegram_username ? `<span class="ga-result-tg">@${esc(u.telegram_username)}</span>` : ''}</button>`).join('')
                : '<div class="ga-result-empty">Никого не найдено</div>';
            results.hidden = false;
        }, 250);
    });

    results?.addEventListener('click', e => {
        const b = e.target.closest('.ga-result'); if (!b) return;
        selected.set(b.dataset.id, b.dataset.name);
        renderChips();
        search.value = ''; results.hidden = true; results.innerHTML = ''; search.focus();
    });

    chips?.addEventListener('click', e => {
        const x = e.target.closest('.ga-chip-x'); if (!x) return;
        selected.delete(x.dataset.id);
        renderChips();
    });

    document.addEventListener('click', e => {
        if (picker && !picker.contains(e.target)) results.hidden = true;
    });

    sendAll?.addEventListener('change', () => {
        picker.classList.toggle('ga-disabled', sendAll.checked);
    });

    form?.addEventListener('submit', e => {
        if (sendAll.checked) {
            if (!confirm('Выдать это достижение ВСЕМ пользователям (<?= $userCount ?>)? Действие затронет большое число аккаунтов.')) {
                e.preventDefault();
            }
            return;
        }
        if (selected.size === 0) {
            e.preventDefault();
            alert('Выберите хотя бы одного пользователя или включите «Всем пользователям».');
        }
    });
})();
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>
