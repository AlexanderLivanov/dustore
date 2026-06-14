<?php
$page_title = 'Аналитика';
$active_nav = 'analytics';
require_once(__DIR__ . '/includes/header.php');

$conn = $db->connect();

// Все игры студии
$stmt = $conn->prepare("SELECT id, name, genre, status, GQI, rating_count, created_at FROM games WHERE developer=? ORDER BY id DESC");
$stmt->execute([$studio_id]);
$games_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$game_ids   = array_column($games_list, 'id');

// Игроки суммарно
$players_total = 0;
$players_by_game = [];
if ($game_ids) {
    $in   = implode(',', array_fill(0, count($game_ids), '?'));
    $stmt = $conn->prepare("SELECT game_id, COUNT(*) AS cnt FROM library WHERE game_id IN ($in) GROUP BY game_id");
    $stmt->execute($game_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $players_by_game[(int)$row['game_id']] = (int)$row['cnt'];
        $players_total += (int)$row['cnt'];
    }
}

// Отзывы суммарно
$reviews_total = 0;
$avg_ratings   = [];
$reviews_by_game = [];
if ($game_ids) {
    $stmt = $conn->prepare("SELECT game_id, COUNT(*) AS cnt, ROUND(AVG(rating),1) AS avg_r FROM game_reviews WHERE game_id IN ($in) GROUP BY game_id");
    $stmt->execute($game_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reviews_by_game[(int)$row['game_id']] = (int)$row['cnt'];
        $avg_ratings[(int)$row['game_id']]     = $row['avg_r'];
        $reviews_total += (int)$row['cnt'];
    }
}

$avg_global = $reviews_total > 0
    ? round(array_sum($avg_ratings) / count(array_filter($avg_ratings)), 1)
    : '—';

// Топ игра
$top_game = null;
$top_players = 0;
foreach ($games_list as $g) {
    $cnt = $players_by_game[(int)$g['id']] ?? 0;
    if ($cnt > $top_players) {
        $top_players = $cnt;
        $top_game = $g;
    }
}

// Последние 14 дней — отзывы по дням
$reviews_chart = [];
if ($game_ids) {
    $stmt = $conn->prepare("
        SELECT DATE(created_at) AS day, COUNT(*) AS cnt
        FROM game_reviews
        WHERE game_id IN ($in) AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY day ORDER BY day
    ");
    $stmt->execute($game_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reviews_chart[$row['day']] = (int)$row['cnt'];
    }
}

// Последние 14 дней — библиотека (новые игроки)
$library_chart = [];
if ($game_ids) {
    $stmt = $conn->prepare("
        SELECT DATE(date) AS day, COUNT(*) AS cnt
        FROM library
        WHERE game_id IN ($in) AND date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY day ORDER BY day
    ");
    $stmt->execute($game_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $library_chart[$row['day']] = (int)$row['cnt'];
    }
}

// Строим полный массив дат за 14 дней
$chart_days = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chart_days[] = [
        'date'    => $day,
        'label'   => date('d.m', strtotime($day)),
        'reviews' => $reviews_chart[$day] ?? 0,
        'players' => $library_chart[$day] ?? 0,
    ];
}
$max_r = max(1, max(array_column($chart_days, 'reviews')));
$max_p = max(1, max(array_column($chart_days, 'players')));

$status_map = [
    'published' => ['badge-pub', 'Опубликован'],
    'draft'    => ['badge-draft', 'Черновик'],
    'closed'   => ['badge-err', 'Закрыт'],
];
?>

<!-- KPI -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">videogame_asset</span></div>
        <div class="stat-num"><?= count($games_list) ?></div>
        <div class="stat-label">Проектов</div>
        <div class="stat-sub"><?= count(array_filter($games_list, fn($g) => $g['status'] === 'published')) ?> опубликовано</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">people</span></div>
        <div class="stat-num"><?= number_format($players_total) ?></div>
        <div class="stat-label">Всего игроков</div>
        <?php if ($top_game): ?>
            <div class="stat-sub">Топ: <?= htmlspecialchars(mb_substr($top_game['name'], 0, 20)) ?></div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">rate_review</span></div>
        <div class="stat-num"><?= $reviews_total ?></div>
        <div class="stat-label">Отзывов</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><span class="material-icons">star</span></div>
        <div class="stat-num"><?= $avg_global ?></div>
        <div class="stat-label">Средний рейтинг</div>
        <?php if ($avg_global !== '—'): ?>
            <div class="stat-sub" style="color:#ffaa00;">из 10</div>
        <?php endif; ?>
    </div>
</div>

<!-- Charts -->
<div class="grid-2" style="gap:16px;margin-bottom:24px;">

    <!-- Players chart -->
    <div class="card">
        <div class="card-title"><span class="material-icons">people</span>Новые игроки (14 дней)</div>
        <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:0 4px;">
            <?php foreach ($chart_days as $d):
                $h = $max_p > 0 ? round($d['players'] / $max_p * 100) : 0;
            ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;">
                    <div style="flex:1;display:flex;align-items:flex-end;width:100%;">
                        <div title="<?= $d['label'] ?>: <?= $d['players'] ?>"
                            style="width:100%;height:<?= max(2, $h) ?>%;background:<?= $d['players'] > 0 ? 'var(--p)' : 'var(--elev)' ?>;border-radius:3px 3px 0 0;transition:.2s;cursor:default;min-height:2px;"
                            onmouseover="this.style.background='var(--pl)'" onmouseout="this.style.background='<?= $d['players'] > 0 ? 'var(--p)' : 'var(--elev)' ?>'"></div>
                    </div>
                    <div style="font-size:9px;color:var(--tm);writing-mode:vertical-lr;text-orientation:mixed;transform:rotate(180deg);"><?= $d['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($players_total === 0): ?>
            <div style="text-align:center;color:var(--tm);font-size:12px;margin-top:8px;">Нет данных за период</div>
        <?php endif; ?>
    </div>

    <!-- Reviews chart -->
    <div class="card">
        <div class="card-title"><span class="material-icons">rate_review</span>Отзывы (14 дней)</div>
        <div style="display:flex;align-items:flex-end;gap:4px;height:120px;padding:0 4px;">
            <?php foreach ($chart_days as $d):
                $h = $max_r > 0 ? round($d['reviews'] / $max_r * 100) : 0;
            ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;">
                    <div style="flex:1;display:flex;align-items:flex-end;width:100%;">
                        <div title="<?= $d['label'] ?>: <?= $d['reviews'] ?>"
                            style="width:100%;height:<?= max(2, $h) ?>%;background:<?= $d['reviews'] > 0 ? '#7aa2f7' : 'var(--elev)' ?>;border-radius:3px 3px 0 0;transition:.2s;cursor:default;min-height:2px;"
                            onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'"></div>
                    </div>
                    <div style="font-size:9px;color:var(--tm);writing-mode:vertical-lr;text-orientation:mixed;transform:rotate(180deg);"><?= $d['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($reviews_total === 0): ?>
            <div style="text-align:center;color:var(--tm);font-size:12px;margin-top:8px;">Нет данных за период</div>
        <?php endif; ?>
    </div>
</div>

<!-- Per-game table -->
<div class="card">
    <div class="card-title"><span class="material-icons">table_chart</span>По проектам</div>
    <?php if (empty($games_list)): ?>
        <div style="text-align:center;padding:30px;color:var(--tm);">Нет проектов</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--bd);">
                        <th style="text-align:left;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Игра</th>
                        <th style="text-align:center;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Статус</th>
                        <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Игроки</th>
                        <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Отзывы</th>
                        <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Рейтинг</th>
                        <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">GQI</th>
                        <th style="padding:8px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games_list as $g):
                        $gid = (int)$g['id'];
                        [$scls, $slbl] = $status_map[$g['status'] ?? 'draft'] ?? ['badge-draft', 'Черновик'];
                        $pl_cnt = $players_by_game[$gid] ?? 0;
                        $rv_cnt = $reviews_by_game[$gid] ?? 0;
                        $avg_r  = $avg_ratings[$gid] ?? '—';
                    ?>
                        <tr style="border-bottom:1px solid var(--bd);">
                            <td style="padding:10px 12px;font-weight:500;"><?= htmlspecialchars($g['name']) ?></td>
                            <td style="padding:10px 12px;text-align:center;"><span class="badge <?= $scls ?>"><?= $slbl ?></span></td>
                            <td style="padding:10px 12px;text-align:right;color:var(--ts);"><?= number_format($pl_cnt) ?></td>
                            <td style="padding:10px 12px;text-align:right;color:var(--ts);"><?= $rv_cnt ?></td>
                            <td style="padding:10px 12px;text-align:right;color:<?= $avg_r !== '—' ? '#ffaa00' : 'var(--tm)' ?>;"><?= $avg_r !== '—' ? $avg_r . '/10' : '—' ?></td>
                            <td style="padding:10px 12px;text-align:right;color:var(--p);font-weight:600;"><?= (int)$g['GQI'] ?></td>
                            <td style="padding:10px 12px;text-align:right;">
                                <a href="/devs/edit?id=<?= $gid ?>" class="btn btn-g" style="padding:4px 10px;font-size:11px;">
                                    <span class="material-icons" style="font-size:13px;">edit</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>