<?php
$page_title = 'Отчёты проверок';
$active_nav = 'scan-reports';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Доступ только root (-1) и модератор (3).
$role = (int)($_SESSION['USERDATA']['global_role'] ?? 0);
if (!in_array($role, [-1, 3], true)) {
    echo '<div class="card" style="text-align:center;padding:48px;color:var(--ts);">
            <span class="material-icons" style="font-size:40px;color:var(--tm);display:block;margin-bottom:10px;">lock</span>
            Раздел доступен только администрации.
          </div>';
    require_once(__DIR__ . '/includes/footer.php');
    exit;
}

/* Единый источник событий скана: deplex-билды + веб-игры (games.vt_*).
   Статусы нормализуем к одним именам: flagged→infected, skipped_oversize→skipped. */
$union = "
    SELECT g.id AS game_id, g.name AS game_name, 'deplex' AS source,
           b.scan_status AS status, b.scan_signature AS signature,
           b.scan_started_at AS started, b.scan_finished_at AS finished
    FROM deplex_builds b
    JOIN games g ON g.id = b.game_id
    WHERE b.scan_status IS NOT NULL
    UNION ALL
    SELECT g.id, g.name, 'web',
           CASE g.vt_status WHEN 'flagged' THEN 'infected'
                            WHEN 'skipped_oversize' THEN 'skipped'
                            ELSE g.vt_status END,
           g.vt_report_url, g.scan_started_at, g.scan_finished_at
    FROM games g
    WHERE g.game_zip_url IS NOT NULL AND g.game_zip_url <> '' AND g.vt_status IS NOT NULL
";

// Сводка (все события).
$counts = ['clean' => 0, 'infected' => 0, 'error' => 0, 'skipped' => 0, 'queued' => 0, 'scanning' => 0];
foreach ($conn->query("SELECT status, COUNT(*) n FROM ($union) t GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$pending = $counts['queued'] + $counts['scanning'];

// График: события за 30 дней по дням и статусам.
$rows = $conn->query("
    SELECT DATE(finished) d, status, COUNT(*) n
    FROM ($union) t
    WHERE finished IS NOT NULL AND finished >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY d, status
")->fetchAll(PDO::FETCH_ASSOC);

$days = [];
for ($i = 29; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['clean' => 0, 'infected' => 0, 'error' => 0, 'skipped' => 0];
}
foreach ($rows as $r) {
    if (isset($days[$r['d']]) && isset($days[$r['d']][$r['status']])) {
        $days[$r['d']][$r['status']] = (int)$r['n'];
    }
}
$chartLabels = array_map(fn($d) => date('d.m', strtotime($d)), array_keys($days));
$chartClean    = array_map(fn($x) => $x['clean'], array_values($days));
$chartInfected = array_map(fn($x) => $x['infected'], array_values($days));
$chartError    = array_map(fn($x) => $x['error'], array_values($days));
$chartSkipped  = array_map(fn($x) => $x['skipped'], array_values($days));

// Последние 80 проверок.
$recent = $conn->query("
    SELECT * FROM ($union) t
    ORDER BY COALESCE(finished, started) DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

function sr_dur($start, $end): string
{
    if (empty($start) || empty($end)) return '—';
    $s = strtotime($end) - strtotime($start);
    if ($s < 0) return '—';
    return $s < 60 ? $s . ' с' : intdiv($s, 60) . ' мин ' . ($s % 60) . ' с';
}
$statusUi = [
    'clean'    => ['#00d68f', 'Чисто'],
    'infected' => ['#f87171', 'Угроза'],
    'error'    => ['#fbbf24', 'Ошибка'],
    'skipped'  => ['#9aa0b0', 'Пропущено'],
    'queued'   => ['#6b7a99', 'В очереди'],
    'scanning' => ['#6b7a99', 'Сканируется'],
];
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">verified_user</span></div>
        <div class="stat-num" style="color:#00d68f;"><?= $counts['clean'] ?></div>
        <div class="stat-label">Чисто</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">gpp_bad</span></div>
        <div class="stat-num" style="color:#f87171;"><?= $counts['infected'] ?></div>
        <div class="stat-label">Угрозы</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">error_outline</span></div>
        <div class="stat-num" style="color:#fbbf24;"><?= $counts['error'] ?></div>
        <div class="stat-label">Ошибки</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">hourglass_empty</span></div>
        <div class="stat-num"><?= $pending ?></div>
        <div class="stat-label">В очереди</div>
        <?php if ($counts['skipped'] > 0): ?>
            <div class="stat-sub"><?= $counts['skipped'] ?> пропущено</div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding:20px;margin-bottom:16px;">
    <div class="sec-head" style="margin-bottom:12px;">
        <div class="sec-title">Проверки за 30 дней</div>
    </div>
    <div style="height:280px;"><canvas id="scanChart"></canvas></div>
</div>

<div class="sec-head">
    <div class="sec-title">Последние проверки</div>
</div>
<div class="card" style="padding:0;overflow:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="color:var(--tm);text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                <th style="padding:12px 16px;">Игра</th>
                <th style="padding:12px 8px;">Путь</th>
                <th style="padding:12px 8px;">Статус</th>
                <th style="padding:12px 8px;">Проверено</th>
                <th style="padding:12px 8px;">Время</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent)): ?>
                <tr><td colspan="5" style="padding:24px 16px;text-align:center;color:var(--tm);">Проверок пока нет</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r):
                [$col, $lbl] = $statusUi[$r['status']] ?? ['#9aa0b0', $r['status']];
            ?>
            <tr style="border-top:1px solid var(--bd);">
                <td style="padding:10px 16px;">
                    <a href="/expert/admin/moderation-game?id=<?= (int)$r['game_id'] ?>" style="color:var(--ts);text-decoration:none;font-weight:500;">
                        <?= htmlspecialchars($r['game_name']) ?>
                    </a>
                    <?php if ($r['status'] === 'infected' && $r['signature']): ?>
                        <div style="font-size:11px;color:#f87171;">🦠 <?= htmlspecialchars($r['signature']) ?></div>
                    <?php elseif ($r['status'] === 'error' && $r['signature']): ?>
                        <div style="font-size:11px;color:var(--tm);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px;"><?= htmlspecialchars($r['signature']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 8px;color:var(--tm);"><?= $r['source'] === 'deplex' ? 'deplex' : 'веб-зип' ?></td>
                <td style="padding:10px 8px;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                        background:<?= $col ?>22;border:1px solid <?= $col ?>55;color:<?= $col ?>;"><?= htmlspecialchars($lbl) ?></span>
                </td>
                <td style="padding:10px 8px;color:var(--tm);white-space:nowrap;"><?= $r['finished'] ? htmlspecialchars($r['finished']) : '—' ?></td>
                <td style="padding:10px 8px;color:var(--tm);white-space:nowrap;"><?= sr_dur($r['started'], $r['finished']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
new Chart(document.getElementById('scanChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            { label: 'Чисто',     data: <?= json_encode($chartClean) ?>,    backgroundColor: '#00d68f' },
            { label: 'Угрозы',    data: <?= json_encode($chartInfected) ?>, backgroundColor: '#f87171' },
            { label: 'Ошибки',    data: <?= json_encode($chartError) ?>,    backgroundColor: '#fbbf24' },
            { label: 'Пропущено', data: <?= json_encode($chartSkipped) ?>,  backgroundColor: '#6b7a99' },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { stacked: true, grid: { display: false }, ticks: { color: '#8a8a99', maxRotation: 0, autoSkip: true } },
            y: { stacked: true, beginAtZero: true, ticks: { color: '#8a8a99', precision: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }
        },
        plugins: { legend: { labels: { color: '#c8c8d4', boxWidth: 12 } } }
    }
});
</script>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>