<?php

/**
 * m/views/developer.php
 * $param = studio ID или tiker
 */

if (!$param) {
    echo '<div class="empty-state"><div class="empty-title">Студия не найдена</div></div>';
    return;
}

/* Ищем по ID или тикеру */
if (is_numeric($param)) {
    $stmt = $db->prepare("SELECT * FROM studios WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$param]);
} else {
    $stmt = $db->prepare("SELECT * FROM studios WHERE tiker = ? LIMIT 1");
    $stmt->execute([$param]);
}
$studio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$studio) {
    echo '<div class="empty-state" style="padding-top:60px">
        <div class="empty-icon"><i class="ti ti-building-off" style="font-size:44px"></i></div>
        <div class="empty-title">Студия не найдена</div>
    </div>';
    return;
}

$dev_title = $studio['name']; // для мета-тега

/* Игры студии */
$games_stmt = $db->prepare("
    SELECT g.id, g.name, g.price, g.path_to_cover,
           COALESCE(AVG(r.rating), 0) AS rating
    FROM games g
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE g.developer = ? AND g.moderation_status = 'approved'
    GROUP BY g.id, g.name, g.price, g.path_to_cover
    ORDER BY g.created_at DESC
    LIMIT 20
");
$games_stmt->execute([$studio['id']]);
$games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

/* Статистика */
$stat_stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT g.id) AS game_count,
        COALESCE(SUM(l.cnt), 0) AS total_downloads
    FROM games g
    LEFT JOIN (
        SELECT game_id, COUNT(*) AS cnt
        FROM library WHERE purchased = 1
        GROUP BY game_id
    ) l ON l.game_id = g.id
    WHERE g.developer = ? AND g.moderation_status = 'approved'
");
$stat_stmt->execute([$studio['id']]);
$stat = $stat_stmt->fetch(PDO::FETCH_ASSOC);

/* Владелец */
$owner_stmt = $db->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE id = ? LIMIT 1");
$owner_stmt->execute([$studio['owner_id']]);
$owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Banner -->
<div class="dev-banner">
    <?php if (!empty($studio['banner_link'])): ?>
        <img src="<?= htmlspecialchars($studio['banner_link']) ?>" alt="" loading="lazy">
    <?php endif; ?>
    <!-- Back -->
    <a href="javascript:history.back()"
        style="position:absolute;top:12px;left:12px;width:36px;height:36px;
              background:rgba(20,4,29,.6);border-radius:50%;display:flex;
              align-items:center;justify-content:center;color:#fff;
              font-size:20px;backdrop-filter:blur(8px);z-index:2">
        <i class="ti ti-arrow-left"></i>
    </a>
</div>

<!-- Header -->
<div class="dev-header">
    <?php if (!empty($studio['avatar_link'])): ?>
        <img class="dev-avatar"
            src="<?= htmlspecialchars($studio['avatar_link']) ?>"
            alt="<?= htmlspecialchars($studio['name']) ?>"
            onerror="this.style.display='none'">
    <?php else: ?>
        <div class="dev-avatar" style="display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff">
            <?= mb_strtoupper(mb_substr($studio['name'], 0, 1)) ?>
        </div>
    <?php endif; ?>

    <div class="dev-name"><?= htmlspecialchars($studio['name']) ?></div>

    <?php if (!empty($studio['tiker'])): ?>
        <div class="dev-ticker">$<?= htmlspecialchars($studio['tiker']) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="dev-stats">
        <div class="dev-stat">
            <div class="dev-stat-val"><?= (int)$stat['game_count'] ?></div>
            <div class="dev-stat-lbl">Игр</div>
        </div>
        <div class="dev-stat">
            <div class="dev-stat-val"><?= number_format((int)$stat['total_downloads']) ?></div>
            <div class="dev-stat-lbl">Загрузок</div>
        </div>
        <div class="dev-stat">
            <div class="dev-stat-val"><?= !empty($studio['foundation_date']) ? substr($studio['foundation_date'], 0, 4) : '—' ?></div>
            <div class="dev-stat-lbl">Основана</div>
        </div>
    </div>

    <!-- Description -->
    <?php if (!empty($studio['description'])): ?>
        <div class="dev-desc"><?= nl2br(htmlspecialchars(mb_strimwidth($studio['description'], 0, 280, '...'))) ?></div>
    <?php endif; ?>

    <!-- Social links -->
    <div class="dev-links">
        <?php if (!empty($studio['website'])): ?>
            <a href="<?= htmlspecialchars($studio['website']) ?>" class="dev-link" target="_blank" rel="noopener">
                <i class="ti ti-world" style="font-size:14px"></i> Сайт
            </a>
        <?php endif; ?>
        <?php if (!empty($studio['vk_link'])): ?>
            <a href="<?= htmlspecialchars($studio['vk_link']) ?>" class="dev-link" target="_blank" rel="noopener">
                <i class="ti ti-brand-vk" style="font-size:14px"></i> ВКонтакте
            </a>
        <?php endif; ?>
        <?php if (!empty($studio['tg_link'])): ?>
            <a href="<?= htmlspecialchars($studio['tg_link']) ?>" class="dev-link" target="_blank" rel="noopener">
                <i class="ti ti-brand-telegram" style="font-size:14px"></i> Telegram
            </a>
        <?php endif; ?>
        <?php if (!empty($studio['contact_email'])): ?>
            <a href="mailto:<?= htmlspecialchars($studio['contact_email']) ?>" class="dev-link">
                <i class="ti ti-mail" style="font-size:14px"></i> Email
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Games -->
<?php if (!empty($games)): ?>
    <div class="spacer-sm"></div>
    <div class="px">
        <div class="section-header">
            <span class="section-title">Игры студии</span>
            <span style="font-size:12px;color:var(--txt3)"><?= count($games) ?></span>
        </div>
        <div class="mini-grid">
            <?php foreach ($games as $g):
                $free = ((float)$g['price'] === 0.0);
            ?>
                <a href="/m/game/<?= (int)$g['id'] ?>" class="mini-card">
                    <div class="mini-cover">
                        <?php if (!empty($g['path_to_cover'])): ?>
                            <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                                alt="" loading="lazy" onerror="this.style.display='none'">
                        <?php endif; ?>
                    </div>
                    <div class="mini-info">
                        <div class="mini-title"><?= htmlspecialchars($g['name']) ?></div>
                        <div class="mini-price <?= $free ? 'free' : '' ?>">
                            <?= $free ? 'Бесплатно' : number_format((float)$g['price'], 0, ',', ' ') . ' ₽' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="ti ti-device-gamepad" style="font-size:44px"></i></div>
        <div class="empty-title">Игр пока нет</div>
        <div class="empty-sub">Студия ещё не выпустила игры</div>
    </div>
<?php endif; ?>

<!-- Team info -->
<?php if (!empty($studio['city']) || !empty($studio['country']) || !empty($studio['team_size'])): ?>
    <div class="spacer"></div>
    <div class="sec-label">О студии</div>
    <div style="margin:0 14px;background:var(--surf);border:1px solid var(--bdr);border-radius:10px;padding:14px">
        <?php if (!empty($studio['city']) || !empty($studio['country'])): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;color:var(--txt2)">
                <i class="ti ti-map-pin" style="color:var(--txt3)"></i>
                <?= htmlspecialchars(trim(($studio['city'] ?? '') . ', ' . ($studio['country'] ?? ''), ', ')) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($studio['team_size'])): ?>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--txt2)">
                <i class="ti ti-users" style="color:var(--txt3)"></i>
                Команда: <?= htmlspecialchars($studio['team_size']) ?> чел.
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="spacer"></div>