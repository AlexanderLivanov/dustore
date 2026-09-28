<?php
/**
 * devs/mobile_analytics.php — «Аналитика: мобильная версия» (платформенная,
 * не по-студийная — рядом с devs/recentorgs.php и devs/experts.php).
 *
 * До этой фичи мобильная оболочка (m/layout/shell.php) не звала хартбит
 * (swad/controllers/activity.php), поэтому user_daily_activity — источник
 * DAU/сессий/ретеншна для GPI — видела только десктоп. Хартбит и колонка
 * platform добавлены в activity.php; здесь — только чтение.
 *
 * Страница переживает отсутствие колонки platform (свежая БД, где хартбит
 * ещё ни разу не дёргался): секция DAU-по-платформам тогда молча скрывается,
 * а разбивка по analytics_events (она есть независимо от этой миграции)
 * показывается всегда.
 */
$page_title = 'Аналитика: мобильная версия';
$active_nav = 'mobile_analytics';
require_once(__DIR__ . '/includes/header.php');

if (!$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> Доступно только администраторам платформы.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$conn = $db->connect();

$hasPlatformCol = (bool)$conn->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'user_daily_activity' AND column_name = 'platform'
")->fetchColumn();

$today = ['mobile' => 0, 'desktop' => 0, 'other' => 0, 'total' => 0];
$chart_days = [];
$platformSince = null;

if ($hasPlatformCol) {
    // Сегодняшний срез — карточки KPI
    $rows = $conn->query("
        SELECT COALESCE(platform, 'desktop') AS platform, COUNT(*) AS cnt
        FROM user_daily_activity WHERE day = CURDATE()
        GROUP BY COALESCE(platform, 'desktop')
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cnt = (int)$r['cnt'];
        $today['total'] += $cnt;
        if ($r['platform'] === 'mobile') $today['mobile'] += $cnt;
        elseif ($r['platform'] === 'desktop') $today['desktop'] += $cnt;
        else $today['other'] += $cnt; // tablet, mixed
    }

    // Разбивка по дням за последние 14 — доля мобильных в активности
    $stmt = $conn->query("
        SELECT day, COALESCE(platform, 'desktop') AS platform, COUNT(*) AS cnt
        FROM user_daily_activity
        WHERE day >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY day, COALESCE(platform, 'desktop')
    ");
    $byDay = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = $r['day'];
        $byDay[$d]['mobile']  = ($byDay[$d]['mobile']  ?? 0) + ($r['platform'] === 'mobile' ? (int)$r['cnt'] : 0);
        $byDay[$d]['desktop'] = ($byDay[$d]['desktop'] ?? 0) + ($r['platform'] === 'desktop' ? (int)$r['cnt'] : 0);
        $byDay[$d]['other']   = ($byDay[$d]['other']   ?? 0) + (!in_array($r['platform'], ['mobile', 'desktop'], true) ? (int)$r['cnt'] : 0);
    }
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $m = $byDay[$day]['mobile']  ?? 0;
        $d = $byDay[$day]['desktop'] ?? 0;
        $o = $byDay[$day]['other']   ?? 0;
        $t = $m + $d + $o;
        $chart_days[] = [
            'label' => date('d.m', strtotime($day)), 'total' => $t,
            'mobile_pct'  => $t ? round($m / $t * 100) : 0,
            'desktop_pct' => $t ? round($d / $t * 100) : 0,
            'other_pct'   => $t ? round($o / $t * 100) : 0,
        ];
    }

    // С какого дня колонка вообще размечена (у более ранних дней platform=NULL
    // читается как "десктоп" — это исторически верно, мобильный хартбит тогда
    // ещё не существовал, но явно не "измерено", а "предполагается")
    $platformSince = $conn->query("SELECT MIN(day) FROM user_daily_activity WHERE platform IS NOT NULL")->fetchColumn() ?: null;
}

// Разбивка по устройствам из уже существующей аналитики игр (impression/view/
// click/download/launch) — эти данные копятся давно, никакой миграции не нужно
$funnel = []; // platform => event_type => cnt
$hasEventsTable = (bool)$conn->query("SHOW TABLES LIKE 'analytics_events'")->fetchColumn();
if ($hasEventsTable) {
    $rows = $conn->query("
        SELECT platform, event_type, COUNT(*) AS cnt
        FROM analytics_events
        WHERE subject_type = 'game' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY platform, event_type
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $funnel[$r['platform']][$r['event_type']] = (int)$r['cnt'];
    }
}
$funnelEvents = ['impression' => 'Показы', 'view' => 'Просмотры', 'click' => 'Клики', 'download' => 'Скачивания', 'launch' => 'Запуски'];
$funnelPlatforms = ['mobile' => 'Мобильные', 'desktop' => 'Десктоп', 'tablet' => 'Планшеты'];
?>

<?php if (!$hasPlatformCol): ?>
    <div class="alert alert-warn">
        <span class="material-icons" style="font-size:16px;vertical-align:middle;">info</span>
        Колонка <code>platform</code> в <code>user_daily_activity</code> ещё не создана — она появится сама на первом
        хартбите (<code>swad/controllers/activity.php</code>). Ниже пока только разбивка по игровой воронке —
        она не зависит от этой миграции.
    </div>
<?php elseif ($platformSince === date('Y-m-d')): ?>
    <div class="alert alert-warn">
        <span class="material-icons" style="font-size:16px;vertical-align:middle;">schedule</span>
        Учёт платформы по DAU запущен только сегодня (<?= date('d.m.Y') ?>) — мобильная оболочка раньше не слала
        хартбит вообще, так что за прошлые дни делить активность по платформам физически нечем. Через 1–2 недели
        график ниже станет показательным.
    </div>
<?php endif; ?>

<?php if ($hasPlatformCol): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">smartphone</span></div>
            <div class="stat-num"><?= number_format($today['mobile']) ?></div>
            <div class="stat-label">Мобильные сегодня</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">computer</span></div>
            <div class="stat-num"><?= number_format($today['desktop']) ?></div>
            <div class="stat-label">Десктоп сегодня</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">pie_chart</span></div>
            <div class="stat-num"><?= $today['total'] ? round($today['mobile'] / $today['total'] * 100) . '%' : '—' ?></div>
            <div class="stat-label">Доля мобильных</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-icons">groups</span></div>
            <div class="stat-num"><?= number_format($today['total']) ?></div>
            <div class="stat-label">Всего активных сегодня</div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-title"><span class="material-icons">stacked_bar_chart</span>Доля мобильных в активности (14 дней)</div>
        <div style="display:flex;align-items:flex-end;gap:4px;height:140px;padding:0 4px;">
            <?php foreach ($chart_days as $d): ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;" title="<?= $d['label'] ?>: <?= $d['total'] ?> активных, <?= $d['mobile_pct'] ?>% мобильных">
                    <?php if ($d['total'] > 0): ?>
                        <div style="flex:1;display:flex;flex-direction:column-reverse;width:100%;border-radius:3px 3px 0 0;overflow:hidden;">
                            <div style="height:<?= $d['desktop_pct'] ?>%;background:var(--p);"></div>
                            <div style="height:<?= $d['other_pct'] ?>%;background:var(--tm);"></div>
                            <div style="height:<?= $d['mobile_pct'] ?>%;background:#22d3ee;"></div>
                        </div>
                    <?php else: ?>
                        <div style="flex:1;width:100%;background:var(--elev);border-radius:3px 3px 0 0;"></div>
                    <?php endif; ?>
                    <div style="font-size:9px;color:var(--tm);writing-mode:vertical-lr;text-orientation:mixed;transform:rotate(180deg);"><?= $d['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;margin-top:10px;font-size:11px;color:var(--tm);">
            <span><i style="display:inline-block;width:10px;height:10px;background:#22d3ee;border-radius:2px;vertical-align:middle;margin-right:4px;"></i>Мобильные</span>
            <span><i style="display:inline-block;width:10px;height:10px;background:var(--p);border-radius:2px;vertical-align:middle;margin-right:4px;"></i>Десктоп</span>
            <span><i style="display:inline-block;width:10px;height:10px;background:var(--tm);border-radius:2px;vertical-align:middle;margin-right:4px;"></i>Планшет / оба за день</span>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px;">
    <div class="card-title"><span class="material-icons">table_chart</span>Игровая воронка по устройствам (30 дней)</div>
    <?php if (!$hasEventsTable || empty($funnel)): ?>
        <div style="text-align:center;padding:30px;color:var(--tm);">Нет данных за период</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--bd);">
                        <th style="text-align:left;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;">Платформа</th>
                        <?php foreach ($funnelEvents as $label): ?>
                            <th style="text-align:right;padding:8px 12px;color:var(--tm);font-weight:500;font-size:11px;text-transform:uppercase;"><?= $label ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funnelPlatforms as $pkey => $plabel): ?>
                        <tr style="border-bottom:1px solid var(--bd);">
                            <td style="padding:10px 12px;font-weight:500;"><?= $plabel ?></td>
                            <?php foreach ($funnelEvents as $ekey => $elabel): ?>
                                <td style="padding:10px 12px;text-align:right;color:var(--ts);"><?= number_format($funnel[$pkey][$ekey] ?? 0) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>
