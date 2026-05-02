<?php
$page_title = 'Выдать достижение';
$active_nav = 'giveach';
require_once(__DIR__ . '/includes/header.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();

$badges = $conn->query("SELECT * FROM badges ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$users  = $conn->query("SELECT id, username, telegram_username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $badge_id   = (int)($_POST['badge']    ?? 0);
    $send_all   = isset($_POST['sendtoall']);
    $sel_users  = array_map('intval', $_POST['users'] ?? []);

    if (!$badge_id) {
        $error_msg = 'Выберите достижение.';
    } else {
        $target_ids = $send_all
            ? array_column($users, 'id')
            : $sel_users;

        if (empty($target_ids)) {
            $error_msg = 'Выберите хотя бы одного пользователя.';
        } else {
            $given = 0;
            $stmt  = $conn->prepare("INSERT IGNORE INTO given_badges (player_id, ach_id, date) VALUES (?, ?, NOW())");
            foreach ($target_ids as $uid) {
                try {
                    $stmt->execute([$uid, $badge_id]);
                    $given++;
                } catch (PDOException) {
                }
            }
            $success_msg = "Достижение выдано {$given} пользователям.";
        }
    }
}
?>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

<form method="POST">
    <div class="grid-2" style="gap:16px;align-items:start;">
        <div class="card">
            <div class="card-title"><span class="material-icons">military_tech</span>Выбор достижения</div>
            <div class="field"><label>Достижение *</label>
                <select name="badge" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($badges as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="border-top:1px solid var(--bd);margin:14px 0;"></div>
            <div class="card-title"><span class="material-icons">group</span>Кому выдать</div>
            <div class="field">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:12px;">
                    <input type="checkbox" name="sendtoall" id="sendtoall" style="accent-color:var(--p);width:auto;" onchange="toggleUsers(this)">
                    <span style="font-size:13px;font-weight:500;">Всем пользователям (<?= count($users) ?>)</span>
                </label>
                <div id="users_list" style="max-height:320px;overflow-y:auto;border:1px solid var(--bd);border-radius:8px;padding:8px;">
                    <?php foreach ($users as $u): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:5px 6px;border-radius:6px;cursor:pointer;transition:.1s;">
                            <input type="checkbox" name="users[]" value="<?= $u['id'] ?>" style="accent-color:var(--p);width:auto;flex-shrink:0;">
                            <span style="font-size:13px;color:var(--ts);"><?= htmlspecialchars($u['username'] ?? '') ?>
                                <?php if ($u['telegram_username']): ?>
                                    <span style="color:var(--tm);">@<?= htmlspecialchars($u['telegram_username']) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;margin-top:4px;">
                <span class="material-icons">send</span>Выдать достижение
            </button>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">list</span>Список достижений</div>
            <?php if (empty($badges)): ?>
                <div style="text-align:center;color:var(--tm);padding:20px;">Нет достижений в БД</div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($badges as $b): ?>
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:var(--elev);border-radius:8px;">
                            <span class="material-icons" style="font-size:20px;color:var(--p);flex-shrink:0;margin-top:2px;">emoji_events</span>
                            <div>
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($b['name']) ?></div>
                                <div style="font-size:11px;color:var(--ts);margin-top:2px;"><?= htmlspecialchars($b['description']) ?></div>
                                <?php if (isset($b['type'])): ?>
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

<?php
$extra_js = '<script>function toggleUsers(cb){document.getElementById("users_list").style.opacity=cb.checked?"0.4":"1";document.querySelectorAll("#users_list input").forEach(i=>i.disabled=cb.checked);}</script>';
require_once(__DIR__ . '/includes/footer.php');
?>