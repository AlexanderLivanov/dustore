<?php
/**
 * devs/wishlists.php — аналитика вишлистов по всем играм студии
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../swad/config.php');

$db        = new Database();
$conn      = $db->connect();
$studio_id = (int)($_SESSION['studio_id'] ?? 0);
if (!$studio_id) { header('Location: /devs'); exit(); }

// ── Параметры: выбранная игра и период ────────────────────────────
$game_id = (int)($_GET['game_id'] ?? 0);
$period  = in_array($_GET['period'] ?? '', ['7','30','90','365']) ? (int)$_GET['period'] : 30;

// ── Все игры студии с анонсом ──────────────────────────────────────
$games_stmt = $conn->prepare("
    SELECT id, name, announce_enabled, announce_date, announce_tbd,
           (SELECT COUNT(*) FROM wishlists w WHERE w.game_id = g.id) AS wl_count
    FROM games g
    WHERE developer = ?
    ORDER BY wl_count DESC, name ASC
");
$games_stmt->execute([$studio_id]);
$all_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

// Если конкретная игра не выбрана — берём первую
if (!$game_id && $all_games) {
    $game_id = (int)$all_games[0]['id'];
}

// ── Данные выбранной игры ──────────────────────────────────────────
$selected_game = null;
foreach ($all_games as $g) {
    if ((int)$g['id'] === $game_id) { $selected_game = $g; break; }
}

// ── График: добавления в вишлист по дням за период ─────────────────
$chart_data = [];
if ($game_id) {
    $rows = $conn->prepare("
        SELECT DATE(created_at) AS day, COUNT(*) AS cnt
        FROM wishlists
        WHERE game_id = ?
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    $rows->execute([$game_id, $period]);
    $raw = $rows->fetchAll(PDO::FETCH_ASSOC);

    // Заполняем пропущенные дни нулями
    $map = [];
    foreach ($raw as $r) $map[$r['day']] = (int)$r['cnt'];
    for ($i = $period - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $chart_data[] = ['day' => $day, 'cnt' => $map[$day] ?? 0];
    }
}

// ── Итоговые цифры ─────────────────────────────────────────────────
$total_wl = 0;
$period_wl = 0;
if ($game_id) {
    $total_wl = (int)$conn->prepare("SELECT COUNT(*) FROM wishlists WHERE game_id=?")
                           ->execute([$game_id]) ? $conn->query("SELECT COUNT(*) FROM wishlists WHERE game_id=$game_id")->fetchColumn() : 0;

    // Используем сумму из chart_data (уже за период)
    $period_wl = array_sum(array_column($chart_data, 'cnt'));
}

// ── Последние добавившие (для таблицы) ────────────────────────────
$recent = [];
if ($game_id) {
    $r = $conn->prepare("
        SELECT u.username, u.profile_picture, w.created_at
        FROM wishlists w
        JOIN users u ON w.user_id = u.id
        WHERE w.game_id = ?
        ORDER BY w.created_at DESC
        LIMIT 20
    ");
    $r->execute([$game_id]);
    $recent = $r->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Вишлисты';
$active_nav = 'wishlists';
require_once(__DIR__ . '/includes/header.php');

$chart_json = json_encode($chart_data);
$labels_js  = json_encode(array_column($chart_data, 'day'));
$values_js  = json_encode(array_column($chart_data, 'cnt'));
?>

<!-- Шапка страницы -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
    <span class="material-icons" style="color:#ffaa00;font-size:28px;">favorite</span>
    <div>
        <div style="font-size:20px;font-weight:700;">Вишлисты</div>
        <div style="font-size:12px;color:var(--tm);">Игроки, ожидающие ваши игры</div>
    </div>
</div>

<!-- Выбор игры + период -->
<div class="card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="flex:1;min-width:200px;margin-bottom:0;">
            <label>Игра</label>
            <select name="game_id" onchange="this.form.submit()">
                <?php foreach ($all_games as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $g['id'] == $game_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                    (<?= $g['wl_count'] ?>
                    <?= $g['announce_enabled'] ? ' · 📢 Анонс' : '' ?>)
                </option>
                <?php endforeach; ?>
                <?php if (!$all_games): ?>
                <option disabled>Нет игр в студии</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Период</label>
            <select name="period" onchange="this.form.submit()">
                <?php foreach (['7'=>'7 дней','30'=>'30 дней','90'=>'3 месяца','365'=>'Год'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $period==$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selected_game): ?>

<!-- Мини-статы -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;padding:18px 12px;">
        <div style="font-size:28px;font-weight:800;color:#ffaa00;"><?= number_format($selected_game['wl_count']) ?></div>
        <div style="font-size:11px;color:var(--tm);margin-top:4px;">Всего в вишлистах</div>
    </div>
    <div class="card" style="text-align:center;padding:18px 12px;">
        <div style="font-size:28px;font-weight:800;color:var(--p);"><?= $period_wl ?></div>
        <div style="font-size:11px;color:var(--tm);margin-top:4px;">За <?= $period ?> дней</div>
    </div>
    <div class="card" style="text-align:center;padding:18px 12px;">
        <?php
        $ann = $selected_game['announce_enabled'];
        $tbd = $selected_game['announce_tbd'];
        $dt  = $selected_game['announce_date'];
        if (!$ann): ?>
            <div style="font-size:14px;font-weight:600;color:var(--tm);">Анонс выключен</div>
            <div style="font-size:11px;color:var(--tm);margin-top:4px;">
                <a href="/devs/edit?id=<?= $game_id ?>" style="color:var(--p);">Включить →</a>
            </div>
        <?php elseif ($tbd): ?>
            <div style="font-size:22px;font-weight:800;color:var(--ts);">TBD</div>
            <div style="font-size:11px;color:var(--tm);margin-top:4px;">Дата выхода неизвестна</div>
        <?php else: ?>
            <div style="font-size:20px;font-weight:800;color:var(--ok);">
                <?= $dt ? date('d.m.Y', strtotime($dt)) : '—' ?>
            </div>
            <div style="font-size:11px;color:var(--tm);margin-top:4px;">Планируемый выход</div>
        <?php endif; ?>
    </div>
</div>

<!-- График -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-title">
        <span class="material-icons">show_chart</span>
        Динамика добавлений в вишлист
    </div>
    <div style="position:relative;height:220px;">
        <canvas id="wl-chart"></canvas>
    </div>
</div>

<!-- Таблица последних добавивших -->
<?php if ($recent): ?>
<div class="card">
    <div class="card-title">
        <span class="material-icons">people</span>
        Последние добавившие
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--brd);">
                    <th style="text-align:left;padding:8px 12px;color:var(--tm);font-weight:600;">Игрок</th>
                    <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:600;">Дата</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $u): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
                    <td style="padding:8px 12px;">
                        <a href="/player/<?= htmlspecialchars($u['username']) ?>"
                           style="display:flex;align-items:center;gap:10px;color:var(--tt);text-decoration:none;">
                            <?php if ($u['profile_picture']): ?>
                            <img src="<?= htmlspecialchars($u['profile_picture']) ?>"
                                 style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--p);
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:12px;font-weight:700;">
                                <?= mb_strtoupper(mb_substr($u['username'],0,1)) ?>
                            </div>
                            <?php endif; ?>
                            <?= htmlspecialchars($u['username']) ?>
                        </a>
                    </td>
                    <td style="padding:8px 12px;text-align:right;color:var(--tm);">
                        <?= date('d.m.Y H:i', strtotime($u['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px;color:var(--tm);">
    <span class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;opacity:.4;">favorite_border</span>
    Пока никто не добавил эту игру в вишлист.<br>
    <a href="/devs/edit?id=<?= $game_id ?>" style="color:var(--p);font-size:13px;margin-top:8px;display:inline-block;">
        Включить анонс →
    </a>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px;color:var(--tm);">
    <span class="material-icons" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3;">favorite_border</span>
    <div style="font-size:15px;">У вашей студии пока нет игр.</div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels = <?= $labels_js ?>;
    const values = <?= $values_js ?>;
    if (!labels.length) return;

    // Форматируем метки: dd.mm
    const fmtLabels = labels.map(d => {
        const [y,m,day] = d.split('-');
        return day + '.' + m;
    });

    const ctx = document.getElementById('wl-chart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: fmtLabels,
            datasets: [{
                label: 'Добавлений в вишлист',
                data: values,
                borderColor: '#ffaa00',
                backgroundColor: 'rgba(255,170,0,0.08)',
                borderWidth: 2,
                pointBackgroundColor: '#ffaa00',
                pointRadius: 3,
                pointHoverRadius: 6,
                tension: 0.35,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => labels[items[0].dataIndex],
                        label: (item) => ' +' + item.raw + ' в вишлист',
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: {
                        color: 'rgba(255,255,255,0.4)',
                        maxTicksLimit: 10,
                        font: { size: 11 },
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: {
                        color: 'rgba(255,255,255,0.4)',
                        precision: 0,
                        font: { size: 11 },
                    }
                }
            }
        }
    });
})();
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>