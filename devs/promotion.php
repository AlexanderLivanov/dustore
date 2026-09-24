<?php

/**
 * devs/promotion.php — вкладка «Продвижение» в панели разработчика.
 * Выбор игры + календарь слотов (12:00 → 12:00 след. суток) + оплата 99₽/сутки
 * через ЮKassa + аналитика по уже купленным слотам.
 *
 * POST обрабатывается ДО require header.php — тот же паттерн, что и в devs/edit.php.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/analytics.php');

if (empty($_SESSION['USERDATA']) || empty($_SESSION['studio_id'])) {
    header('Location: /login?backUrl=/devs/promotion');
    exit();
}

$db        = new Database();
$conn      = $db->connect();
$studio_id = (int)$_SESSION['studio_id'];

// Протухшие pending-брони (начали оформлять, не оплатили) — освобождаем дату.
$conn->prepare("
    DELETE FROM game_promotions
    WHERE status = 'pending_payment' AND created_at < (NOW() - INTERVAL " . Analytics::PENDING_TTL_MINUTES . " MINUTE)
")->execute();

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $game_id   = (int)($_POST['game_id'] ?? 0);
    $slot_date = trim($_POST['slot_date'] ?? '');

    $g = $conn->prepare("SELECT id, name FROM games WHERE id = ? AND developer = ? AND status = 'published' LIMIT 1");
    $g->execute([$game_id, $studio_id]);
    $game = $g->fetch(PDO::FETCH_ASSOC);

    $tz    = new DateTimeZone('Europe/Moscow');
    $now   = new DateTime('now', $tz);
    // Если сегодняшний слот уже идёт (после 12:00) — продавать его задним числом нельзя,
    // ближайшая доступная дата — завтра.
    $minDate = ((int)$now->format('H') >= 12) ? (clone $now)->modify('+1 day') : clone $now;
    $minDate->setTime(0, 0, 0);

    $slotDt = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $slot_date)) {
        try {
            $slotDt = new DateTime($slot_date, $tz);
        } catch (Exception $e) {
            $slotDt = false;
        }
    }

    if (!$game) {
        $error_msg = 'Игра не найдена, не принадлежит вашей студии или не опубликована.';
    } elseif (!$slotDt || $slotDt < $minDate) {
        $error_msg = 'Эта дата недоступна для бронирования.';
    } else {
        $slotDateStr = $slotDt->format('Y-m-d');
        try {
            $ins = $conn->prepare("
                INSERT INTO game_promotions (game_id, studio_id, slot_date, status, price)
                VALUES (?, ?, ?, 'pending_payment', 99.00)
            ");
            $ins->execute([$game_id, $studio_id, $slotDateStr]);
            $promoId = (int)$conn->lastInsertId();
            header('Location: /finv2/create_payment_promotion.php?promo_id=' . $promoId);
            exit();
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $error_msg = 'Этот слот уже заняли буквально только что — выберите другую дату.';
            } else {
                error_log('devs/promotion.php booking error: ' . $e->getMessage());
                $error_msg = 'Не удалось создать бронь. Попробуйте ещё раз.';
            }
        }
    }
}

// ── Игры студии, доступные для продвижения ──
$gamesStmt = $conn->prepare("SELECT id, name FROM games WHERE developer = ? AND status = 'published' ORDER BY name");
$gamesStmt->execute([$studio_id]);
$myGames = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Календарь на месяц (?month=YYYY-MM, по умолчанию текущий) ──
$monthParam = $_GET['month'] ?? date('Y-m');
try {
    $monthStart = new DateTime($monthParam . '-01', new DateTimeZone('Europe/Moscow'));
} catch (Exception $e) {
    $monthStart = new DateTime('first day of this month', new DateTimeZone('Europe/Moscow'));
}
$monthStart->setTime(0, 0, 0);
$monthEnd  = (clone $monthStart)->modify('first day of next month');
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

$slotsStmt = $conn->prepare("
    SELECT gp.slot_date, gp.status, gp.studio_id, g.name AS game_name
    FROM game_promotions gp
    JOIN games g ON g.id = gp.game_id
    WHERE gp.slot_date >= ? AND gp.slot_date < ? AND gp.status IN ('pending_payment','active')
");
$slotsStmt->execute([$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
$slotsByDate = [];
foreach ($slotsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $slotsByDate[$r['slot_date']] = $r;
}

$todayStr = (new DateTime('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$nowMsk   = new DateTime('now', new DateTimeZone('Europe/Moscow'));
$minBookable = ((int)$nowMsk->format('H') >= 12) ? (clone $nowMsk)->modify('+1 day')->format('Y-m-d') : $todayStr;

// ── Мои слоты (активные/завершённые) + аналитика ──
$mineStmt = $conn->prepare("
    SELECT gp.*, g.name AS game_name
    FROM game_promotions gp
    JOIN games g ON g.id = gp.game_id
    WHERE gp.studio_id = ? AND gp.status IN ('active','completed')
    ORDER BY gp.slot_date DESC
    LIMIT 20
");
$mineStmt->execute([$studio_id]);
$myPromotions = $mineStmt->fetchAll(PDO::FETCH_ASSOC);

$analytics  = new Analytics($conn);
$promoIds   = array_column($myPromotions, 'id');
$promoStats = $analytics->summarizeMany('promotion', $promoIds);

$gameEventStats = [];
foreach ($myPromotions as $p) {
    $from = $p['slot_date'] . ' 12:00:00';
    $to   = date('Y-m-d H:i:s', strtotime($p['slot_date'] . ' +1 day 12:00:00'));
    $gameEventStats[$p['id']] = $analytics->summarize('game', (int)$p['game_id'], $from, $to);
}

$page_title = 'Продвижение';
require_once(__DIR__ . '/includes/header.php');
?>
<style>
    /* Скоуп вкладки — своя namespace-обёртка, по конвенции проекта. */
    .ds-promo {
        max-width: 980px;
    }

    .ds-promo .block {
        background: var(--elev, #161022);
        border: 1px solid rgba(255, 255, 255, .07);
        border-radius: 16px;
        padding: 22px 24px;
        margin-bottom: 22px;
    }

    .ds-promo h3 {
        margin: 0 0 14px;
        font-size: 16px;
        font-weight: 800;
    }

    .ds-promo .error {
        background: rgba(248, 113, 113, .1);
        border: 1px solid rgba(248, 113, 113, .3);
        color: #f87171;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 13.5px;
        margin-bottom: 16px;
    }

    .ds-promo select {
        width: 100%;
        max-width: 360px;
        padding: 10px 12px;
        border-radius: 10px;
        background: var(--surf, #1c1630);
        border: 1px solid rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 14px;
        margin-bottom: 18px;
    }

    .ds-promo .cal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .ds-promo .cal-head a {
        color: var(--tm, #9494ad);
        text-decoration: none;
        font-size: 13px;
        padding: 6px 10px;
        border-radius: 8px;
    }

    .ds-promo .cal-head a:hover {
        background: rgba(255, 255, 255, .06);
        color: #fff;
    }

    .ds-promo .cal-title {
        font-weight: 800;
        font-size: 14px;
        text-transform: capitalize;
    }

    .ds-promo .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 6px;
    }

    .ds-promo .cal-dow {
        text-align: center;
        font-size: 11px;
        color: var(--tm, #9494ad);
        padding-bottom: 4px;
    }

    .ds-promo .cal-day {
        aspect-ratio: 1/1;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        gap: 2px;
        border: 1px solid transparent;
    }

    .ds-promo .cal-day.empty {
        visibility: hidden;
    }

    .ds-promo .cal-day.past {
        color: #5a5a6e;
        background: rgba(255, 255, 255, .02);
    }

    .ds-promo .cal-day.taken {
        color: #8f8fa8;
        background: rgba(255, 255, 255, .04);
        border-color: rgba(255, 255, 255, .06);
        cursor: default;
    }

    .ds-promo .cal-day.taken.mine {
        color: #d9d2ff;
        background: rgba(139, 92, 255, .16);
        border-color: rgba(139, 92, 255, .4);
    }

    .ds-promo .cal-day.free {
        background: rgba(74, 222, 128, .08);
        border-color: rgba(74, 222, 128, .28);
        color: #e8fff0;
        cursor: pointer;
        font-weight: 700;
    }

    .ds-promo .cal-day.free:hover {
        background: rgba(74, 222, 128, .18);
        border-color: rgba(74, 222, 128, .5);
    }

    .ds-promo .cal-day .tag {
        font-size: 8.5px;
        opacity: .75;
    }

    .ds-promo .cal-legend {
        display: flex;
        gap: 16px;
        margin-top: 14px;
        font-size: 11.5px;
        color: var(--tm, #9494ad);
        flex-wrap: wrap;
    }

    .ds-promo .cal-legend span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .ds-promo .dot {
        width: 8px;
        height: 8px;
        border-radius: 3px;
        display: inline-block;
    }

    .ds-promo .dot.free {
        background: rgba(74, 222, 128, .6);
    }

    .ds-promo .dot.taken {
        background: rgba(255, 255, 255, .2);
    }

    .ds-promo .dot.mine {
        background: rgba(139, 92, 255, .6);
    }

    .ds-promo .promo-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
    }

    .ds-promo .promo-row:last-child {
        border-bottom: 0;
    }

    .ds-promo .promo-row .pname {
        font-weight: 700;
        font-size: 13.5px;
        min-width: 160px;
    }

    .ds-promo .promo-row .pdate {
        font-size: 12px;
        color: var(--tm, #9494ad);
        min-width: 100px;
    }

    .ds-promo .pstats {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #c3c3d8;
    }

    .ds-promo .pstats b {
        color: #fff;
        font-size: 13.5px;
    }

    .ds-promo .pstat-lbl {
        display: block;
        color: var(--tm, #9494ad);
        font-size: 10.5px;
    }
</style>

<div class="ds-promo">

    <?php if ($error_msg): ?><div class="error"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="block">
        <h3>Забронировать слот</h3>

        <?php if (!$myGames): ?>
            <p style="color:var(--tm,#9494ad);font-size:13.5px;">
                Чтобы продвигать игру, у вас должна быть хотя бы одна опубликованная игра.
            </p>
        <?php else: ?>
            <form method="post" id="promoBookForm">
                <input type="hidden" name="action" value="book">
                <select name="game_id" required>
                    <?php foreach ($myGames as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="cal-head">
                    <a href="?month=<?= $prevMonth ?>">← пред.</a>
                    <span class="cal-title"><?= htmlspecialchars($monthStart->format('F Y')) ?></span>
                    <a href="?month=<?= $nextMonth ?>">след. →</a>
                </div>

                <div class="cal-grid">
                    <?php foreach (['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $dow): ?>
                        <div class="cal-dow"><?= $dow ?></div>
                    <?php endforeach; ?>

                    <?php
                    $leadingEmpty = ((int)$monthStart->format('N')) - 1; // Пн=1 → 0 пустых ячеек
                    for ($i = 0; $i < $leadingEmpty; $i++) echo '<div class="cal-day empty"></div>';

                    $cursor = clone $monthStart;
                    while ($cursor < $monthEnd) {
                        $dStr = $cursor->format('Y-m-d');
                        $slot = $slotsByDate[$dStr] ?? null;

                        if ($dStr < $minBookable) {
                            echo '<div class="cal-day past">' . (int)$cursor->format('j') . '</div>';
                        } elseif ($slot) {
                            $mine = ((int)$slot['studio_id'] === $studio_id) ? ' mine' : '';
                            echo '<div class="cal-day taken' . $mine . '" title="' . htmlspecialchars($slot['game_name']) . '">'
                                . (int)$cursor->format('j')
                                . '<span class="tag">' . ($mine ? 'ваш' : 'занят') . '</span></div>';
                        } else {
                            echo '<button type="submit" name="slot_date" value="' . $dStr . '" class="cal-day free">'
                                . (int)$cursor->format('j')
                                . '<span class="tag">99₽</span></button>';
                        }
                        $cursor->modify('+1 day');
                    }
                    ?>
                </div>

                <div class="cal-legend">
                    <span><i class="dot free"></i> свободно — жмите, чтобы забронировать</span>
                    <span><i class="dot taken"></i> занято другой студией</span>
                    <span><i class="dot mine"></i> ваш слот</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="block">
        <h3>Мои слоты и аналитика</h3>
        <?php if (!$myPromotions): ?>
            <p style="color:var(--tm,#9494ad);font-size:13.5px;">Пока не куплено ни одного слота.</p>
        <?php else: ?>
            <?php foreach ($myPromotions as $p):
                $pid   = (int)$p['id'];
                $ps    = $promoStats[$pid] ?? [];
                $gs    = $gameEventStats[$pid] ?? [];
                $isFuture = $p['slot_date'] >= $todayStr;
            ?>
                <div class="promo-row">
                    <div class="pname"><?= htmlspecialchars($p['game_name']) ?></div>
                    <div class="pdate"><?= date('d.m.Y', strtotime($p['slot_date'])) ?><?= $isFuture ? ' · активен' : '' ?></div>
                    <div class="pstats">
                        <div><b><?= $ps['impression']['count'] ?? 0 ?></b><span class="pstat-lbl">показов</span></div>
                        <div><b><?= $ps['view']['count'] ?? 0 ?></b><span class="pstat-lbl">досмотров</span></div>
                        <div><b><?= $ps['click']['count'] ?? 0 ?></b><span class="pstat-lbl">кликов</span></div>
                        <div><b><?= ($gs['download']['count'] ?? 0) + ($gs['launch']['count'] ?? 0) ?></b><span class="pstat-lbl">скач./запусков</span></div>
                        <div><b><?= gmdate('H:i:s', $gs['playtime']['value'] ?? 0) ?></b><span class="pstat-lbl">времени в игре</span></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>