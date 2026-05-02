<?php
$page_title = 'Новые организации';
$active_nav = 'recentorgs';
require_once(__DIR__ . '/includes/header.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();

// Обработка смены статуса
$action_msg = '';
$action_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_studio = (int)($_POST['studio_id'] ?? 0);
    $new_status    = $_POST['new_status'] ?? '';
    $ban_reason    = trim($_POST['ban_reason'] ?? '');

    if ($target_studio && in_array($new_status, ['active', 'banned', 'pending'])) {
        $conn->prepare("UPDATE studios SET status=?, ban_reason=? WHERE id=?")
            ->execute([$new_status, $ban_reason, $target_studio]);
        $action_msg = "Статус студии #{$target_studio} изменён на «{$new_status}».";
    } else {
        $action_err = 'Неверные параметры.';
    }
}

// Студии — последние 50
$studios = $conn->query("
    SELECT s.*, u.username AS owner_name, u.telegram_username,
           COUNT(g.id) AS games_count
    FROM studios s
    LEFT JOIN users u ON u.id = s.owner_id
    LEFT JOIN games g ON g.developer = s.id
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$status_cfg = [
    'active'  => ['badge-pub', 'Активна'],
    'banned'  => ['badge-err', 'Заблокирована'],
    'pending' => ['badge-rev', 'На проверке'],
];
?>

<?php if ($action_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($action_msg) ?></div><?php endif; ?>
<?php if ($action_err): ?><div class="alert alert-err"><?= htmlspecialchars($action_err) ?></div><?php endif; ?>

<div class="sec-head" style="margin-bottom:16px;">
    <div class="sec-title"><?= count($studios) ?> последних студий</div>
    <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" id="search-inp" placeholder="Поиск..." style="background:var(--elev);border:1px solid var(--bd);border-radius:8px;padding:6px 12px;color:#fff;font-size:12px;width:200px;outline:none;" oninput="filterStudios(this.value)">
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:8px;" id="studios-list">
    <?php foreach ($studios as $s):
        [$scls, $slbl] = $status_cfg[$s['status'] ?? 'pending'] ?? ['badge-rev', 'На проверке'];
    ?>
        <div class="card studio-row" style="padding:14px;" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <!-- Avatar -->
                <div style="width:44px;height:44px;border-radius:10px;flex-shrink:0;background:var(--elev);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;">
                    <?php if (!empty($s['avatar_link'])): ?>
                        <img src="<?= htmlspecialchars($s['avatar_link']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?><?= mb_strtoupper(mb_substr($s['name'], 0, 2)) ?><?php endif; ?>
                </div>
                <!-- Info -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:14px;font-weight:600;"><?= htmlspecialchars($s['name']) ?></span>
                        <?php if ($s['tiker']): ?><span style="font-size:10px;color:var(--tm);background:var(--elev);padding:1px 6px;border-radius:4px;"><?= htmlspecialchars($s['tiker']) ?></span><?php endif; ?>
                        <span class="badge <?= $scls ?>"><?= $slbl ?></span>
                    </div>
                    <div style="font-size:11px;color:var(--tm);margin-top:3px;">
                        Владелец: <?= htmlspecialchars($s['owner_name'] ?? '—') ?>
                        <?php if ($s['telegram_username']): ?> · @<?= htmlspecialchars($s['telegram_username']) ?><?php endif; ?>
                            · Проектов: <?= (int)$s['games_count'] ?>
                            · Зарегистрирована: <?= $s['created_at'] ? date('d.m.Y', strtotime($s['created_at'])) : '—' ?>
                    </div>
                    <?php if (!empty($s['ban_reason'])): ?>
                        <div style="font-size:11px;color:var(--err);margin-top:3px;">Причина блокировки: <?= htmlspecialchars($s['ban_reason']) ?></div>
                    <?php endif; ?>
                </div>
                <!-- Actions -->
                <div style="flex-shrink:0;">
                    <button onclick="toggleForm(<?= $s['id'] ?>)" class="btn btn-g" style="padding:5px 12px;font-size:12px;">
                        <span class="material-icons" style="font-size:14px;">manage_accounts</span>Управление
                    </button>
                </div>
            </div>
            <!-- Collapsible action form -->
            <div id="form-<?= $s['id'] ?>" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--bd);">
                <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="studio_id" value="<?= $s['id'] ?>">
                    <div class="field" style="margin:0;flex:1;min-width:140px;">
                        <label>Новый статус</label>
                        <select name="new_status">
                            <option value="active" <?= $s['status'] === 'active' ? ' selected' : '' ?>>active</option>
                            <option value="pending" <?= $s['status'] === 'pending' ? ' selected' : '' ?>>pending</option>
                            <option value="banned" <?= $s['status'] === 'banned' ? ' selected' : '' ?>>banned</option>
                        </select>
                    </div>
                    <div class="field" style="margin:0;flex:2;min-width:200px;">
                        <label>Причина (для блокировки)</label>
                        <input type="text" name="ban_reason" value="<?= htmlspecialchars($s['ban_reason'] ?? '') ?>" placeholder="Нарушение правил...">
                    </div>
                    <button type="submit" class="btn btn-p" style="padding:7px 14px;font-size:12px;margin-bottom:0;">
                        <span class="material-icons" style="font-size:14px;">save</span>Применить
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$extra_js = '<script>
function toggleForm(id){const f=document.getElementById("form-"+id);f.style.display=f.style.display==="none"?"block":"none";}
function filterStudios(q){document.querySelectorAll(".studio-row").forEach(r=>{r.style.display=r.dataset.name.includes(q.toLowerCase())?"":"none";});}
</script>';
require_once(__DIR__ . '/includes/footer.php');
?>