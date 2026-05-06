<?php
$page_title = 'Сотрудники';
$active_nav = 'staff';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Владелец студии
$stmt = $conn->prepare("SELECT owner_id FROM studios WHERE id=?");
$stmt->execute([$studio_id]);
$owner_id = (int)($stmt->fetchColumn() ?? 0);

// Сотрудники: staff → JOIN users по telegram_id
$load = function () use ($conn, $studio_id) {
    $stmt = $conn->prepare("
        SELECT st.id AS staff_id, st.telegram_id, st.role AS staff_role, st.created AS joined_at,
               u.id AS user_id, u.username, u.telegram_username, u.profile_picture
        FROM staff st
        LEFT JOIN users u ON CAST(u.telegram_id AS CHAR) = CAST(st.telegram_id AS CHAR)
        WHERE st.org_id = :sid
        ORDER BY st.created ASC
    ");
    $stmt->execute(['sid' => $studio_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$members = $load();

$action_msg = '';
$action_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($is_owner || $is_admin)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        $staff_id   = (int)($_POST['staff_id']       ?? 0);
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        if ($target_uid && $target_uid === $owner_id) {
            $action_err = 'Нельзя удалить владельца студии.';
        } elseif ($staff_id) {
            $conn->prepare("DELETE FROM staff WHERE id=? AND org_id=?")
                ->execute([$staff_id, $studio_id]);
            $action_msg = 'Сотрудник удалён.';
        }
    } elseif ($action === 'change_role') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $new_role = trim($_POST['new_role'] ?? '');
        if ($staff_id && $new_role) {
            $conn->prepare("UPDATE staff SET role=? WHERE id=? AND org_id=?")
                ->execute([$new_role, $staff_id, $studio_id]);
            $action_msg = 'Роль обновлена.';
        }
    } elseif ($action === 'invite') {
        $username = trim(ltrim($_POST['invite_username'] ?? '', '@'));
        $new_role = trim($_POST['invite_role'] ?? 'Участник');

        if (!$username) {
            $action_err = 'Укажите имя пользователя.';
        } else {
            $stmt2 = $conn->prepare("
                SELECT id, telegram_id FROM users
                WHERE username=? OR telegram_username=? LIMIT 1
            ");
            $stmt2->execute([$username, $username]);
            $found = $stmt2->fetch(PDO::FETCH_ASSOC);

            if (!$found) {
                $action_err = "Пользователь «{$username}» не найден на платформе.";
            } elseif (empty($found['telegram_id'])) {
                $action_err = 'У пользователя нет Telegram ID — добавление невозможно.';
            } else {
                $check = $conn->prepare("SELECT COUNT(*) FROM staff WHERE telegram_id=? AND org_id=?");
                $check->execute([$found['telegram_id'], $studio_id]);
                if ((int)$check->fetchColumn() > 0) {
                    $action_err = 'Пользователь уже в составе студии.';
                } else {
                    $conn->prepare("
                        INSERT INTO staff (telegram_id, org_id, created, role)
                        VALUES (?, ?, NOW(), ?)
                    ")->execute([$found['telegram_id'], $studio_id, $new_role]);
                    $action_msg = "Пользователь @{$username} добавлен как «{$new_role}».";
                }
            }
        }
    }

    $members = $load();
}

$available_roles = ['Владелец', 'Администратор', 'Разработчик', 'Дизайнер', 'Маркетолог', 'Модератор', 'Участник'];
$role_colors = [
    'Владелец'     => '#ff5ba8',
    'Администратор' => 'var(--warn)',
    'Разработчик'  => 'var(--ok)',
    'Дизайнер'     => '#7aa2f7',
    'Маркетолог'   => '#c8a0ff',
    'Модератор'    => '#7aa2f7',
    'Участник'     => 'var(--tm)',
];
?>

<?php if ($action_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($action_msg) ?></div><?php endif; ?>
<?php if ($action_err): ?><div class="alert alert-err"><?= htmlspecialchars($action_err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">

    <div>
        <div class="sec-head" style="margin-bottom:14px;">
            <div class="sec-title"><?= count($members) ?> <?= count($members) === 1 ? 'участник' : (count($members) < 5 ? 'участника' : 'участников') ?></div>
        </div>

        <?php if (empty($members)): ?>
            <div class="card" style="text-align:center;padding:40px;">
                <span class="material-icons" style="font-size:40px;color:var(--p);display:block;margin-bottom:10px;">group_add</span>
                <p style="color:var(--ts);">В студии пока нет сотрудников</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($members as $m):
                    $color    = $role_colors[$m['staff_role']] ?? 'var(--tm)';
                    $initials = mb_strtoupper(mb_substr($m['username'] ?? $m['telegram_id'], 0, 2));
                    $is_this_owner = ((int)($m['user_id'] ?? 0) === $owner_id);
                ?>
                    <div class="card" style="display:flex;align-items:center;gap:14px;padding:14px;">
                        <div style="width:40px;height:40px;border-radius:10px;flex-shrink:0;background:var(--elev);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;">
                            <?php if (!empty($m['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($m['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?><?= $initials ?><?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span style="font-size:14px;font-weight:600;">
                                    <?= htmlspecialchars($m['username'] ?? 'TG: ' . $m['telegram_id']) ?>
                                </span>
                                <?php if (!empty($m['telegram_username'])): ?>
                                    <span style="font-size:11px;color:var(--tm);">@<?= htmlspecialchars($m['telegram_username']) ?></span>
                                <?php endif; ?>
                                <span style="font-size:11px;font-weight:600;padding:1px 8px;border-radius:4px;background:rgba(255,255,255,.06);color:<?= $color ?>;">
                                    <?= htmlspecialchars($m['staff_role']) ?>
                                </span>
                            </div>
                            <div style="font-size:11px;color:var(--tm);margin-top:2px;">
                                С <?= $m['joined_at'] ? date('d.m.Y', strtotime($m['joined_at'])) : '—' ?>
                                · TG: <?= htmlspecialchars($m['telegram_id']) ?>
                            </div>
                        </div>

                        <?php if (($is_owner || $is_admin) && !$is_this_owner): ?>
                            <div style="display:flex;gap:6px;flex-shrink:0;">
                                <form method="POST" style="display:flex;gap:6px;">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="staff_id" value="<?= (int)$m['staff_id'] ?>">
                                    <select name="new_role" style="background:var(--elev);border:1px solid var(--bd);border-radius:7px;color:#fff;padding:5px 8px;font-size:12px;font-family:Inter,sans-serif;outline:none;">
                                        <?php foreach ($available_roles as $r): ?>
                                            <option<?= $r === $m['staff_role'] ? ' selected' : '' ?>><?= $r ?></option>
                                            <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-g" style="padding:5px 10px;">
                                        <span class="material-icons" style="font-size:15px;">check</span>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Удалить из студии?')">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="staff_id" value="<?= (int)$m['staff_id'] ?>">
                                    <input type="hidden" name="target_user_id" value="<?= (int)($m['user_id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-d" style="padding:5px 10px;">
                                        <span class="material-icons" style="font-size:15px;">person_remove</span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($is_owner || $is_admin): ?>
        <div class="card" style="position:sticky;top:16px;">
            <div class="card-title"><span class="material-icons">person_add</span>Добавить сотрудника</div>
            <form method="POST">
                <input type="hidden" name="action" value="invite">
                <div class="field">
                    <label>Username или @telegram</label>
                    <input type="text" name="invite_username" placeholder="username" required>
                </div>
                <div class="field">
                    <label>Роль в студии</label>
                    <select name="invite_role">
                        <?php foreach ($available_roles as $r): ?>
                            <option><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                    <span class="material-icons">send</span>Добавить
                </button>
            </form>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--bd);">
                <div style="font-size:11px;color:var(--tm);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Роли</div>
                <?php foreach ($role_colors as $name => $color): ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:4px 0;">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;"></span>
                        <span style="font-size:12px;color:var(--ts);"><?= $name ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="text-align:center;padding:24px;color:var(--tm);">
            <span class="material-icons" style="display:block;font-size:32px;margin-bottom:8px;">lock</span>
            Управление доступно только владельцу
        </div>
    <?php endif; ?>
</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>