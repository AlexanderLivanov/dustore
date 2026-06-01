<?php

/**
 * m/views/game.php
 * $param = game ID
 */

$game_id = (int)($param ?? 0);
if (!$game_id) {
    header('Location: /m/catalog', true, 302);
    exit;
}

$stmt = $db->prepare("
    SELECT g.*, s.name AS studio_name, s.id AS studio_id, s.tiker AS studio_tiker,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(DISTINCT r.id) AS review_count
    FROM games g
    JOIN studios s ON s.id = g.developer
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE g.id = ? AND g.moderation_status = 'approved'
    GROUP BY g.id, s.name, s.id, s.tiker
");
$stmt->execute([$game_id]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$g) {
    header('Location: /m/catalog', true, 302);
    exit;
}

$game_title = $g['name']; // для мета-тега в shell.php

/* Куплена? */
$isOwned = false;
if ($user) {
    $c = $db->prepare("SELECT 1 FROM library WHERE player_id=? AND game_id=? AND purchased=1 LIMIT 1");
    $c->execute([$user['id'], $game_id]);
    $isOwned = (bool)$c->fetchColumn();
}

/* Вишлист */
$inWishlist = false;
if ($user) {
    $w = $db->prepare("SELECT 1 FROM wishlist WHERE user_id=? AND game_id=? LIMIT 1");
    $w->execute([$user['id'], $game_id]);
    $inWishlist = (bool)$w->fetchColumn();
}

/* Скриншоты */
$screens = [];
if (!empty($g['screenshots'])) {
    $screens = array_filter(array_map('trim', explode(',', $g['screenshots'])));
}

/* Отзывы */
$revs = $db->prepare("
    SELECT r.rating, r.text, r.created_at, u.first_name, u.last_name
    FROM game_reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.game_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$revs->execute([$game_id]);
$reviews = $revs->fetchAll(PDO::FETCH_ASSOC);

$free   = ((float)$g['price'] === 0.0);
$rating = number_format((float)$g['avg_rating'], 1);

/* Ссылка на студию */
$studio_href = '/m/developer/' . (int)$g['studio_id'];
?>

<!-- Cover -->
<div class="gp-cover-wrap">
    <?php if (!empty($g['path_to_cover'])): ?>
        <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
            alt="<?= htmlspecialchars($g['name']) ?>"
            loading="lazy">
    <?php endif; ?>
    <div class="gp-cover-overlay"></div>
    <a href="javascript:history.back()" class="gp-back" aria-label="Назад">
        <i class="ti ti-arrow-left"></i>
    </a>
</div>

<!-- Body -->
<div class="gp-body">
    <div class="gp-title"><?= htmlspecialchars($g['name']) ?></div>

    <a href="<?= $studio_href ?>" class="gp-studio">
        <?= htmlspecialchars($g['studio_name']) ?>
    </a>

    <!-- Meta -->
    <div class="gp-meta-row">
        <?php if ((float)$g['avg_rating'] > 0): ?>
            <div class="gp-meta-item">
                <i class="ti ti-star-filled" style="color:var(--warn)"></i>
                <b><?= $rating ?></b>
                <span>(<?= (int)$g['review_count'] ?>)</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($g['genre'])): ?>
            <div class="gp-meta-item">
                <i class="ti ti-tag"></i>
                <span><?= htmlspecialchars($g['genre']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($g['age_rating'])): ?>
            <div class="gp-meta-item">
                <i class="ti ti-shield-check"></i>
                <b><?= htmlspecialchars($g['age_rating']) ?></b>
            </div>
        <?php endif; ?>
        <?php if (!empty($g['release_date'])): ?>
            <div class="gp-meta-item">
                <i class="ti ti-calendar"></i>
                <span><?= date('Y', strtotime($g['release_date'])) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Screenshots -->
    <?php if (!empty($screens)): ?>
        <div class="gp-screenshots">
            <?php foreach ($screens as $src): ?>
                <img class="gp-screenshot"
                    src="<?= htmlspecialchars($src) ?>"
                    alt="" loading="lazy"
                    onerror="this.style.display='none'">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Description -->
    <?php if (!empty($g['description'])): ?>
        <div class="gp-desc collapsed" id="gp-desc">
            <?= nl2br(htmlspecialchars($g['description'])) ?>
        </div>
        <button class="gp-desc-toggle" onclick="toggleDesc(this)">Показать полностью</button>
    <?php endif; ?>

    <!-- Tags -->
    <?php if (!empty($g['platforms']) || !empty($g['languages'])): ?>
        <div class="gp-tags">
            <?php if (!empty($g['platforms'])): ?>
                <?php foreach (explode(',', $g['platforms']) as $p): ?>
                    <span class="gp-tag"><?= htmlspecialchars(trim($p)) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($g['languages'])): ?>
                <?php foreach (explode(',', $g['languages']) as $l): ?>
                    <span class="gp-tag"><?= htmlspecialchars(trim($l)) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Reviews -->
    <div class="sec-label" style="padding-left:0">Отзывы</div>
    <?php if (empty($reviews)): ?>
        <div style="font-size:13px;color:var(--txt3);margin-bottom:16px">Отзывов пока нет</div>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div class="review-card">
                <div class="review-head">
                    <span class="review-author"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></span>
                    <?php $rv = max(0, min(5, (int)($rev['rating'] ?? 0))); ?>
                    <span class="review-stars"><?= str_repeat('★', $rv) ?><?= str_repeat('☆', 5 - $rv) ?></span>
                </div>
                <?php if (!empty($rev['text'])): ?>
                    <div class="review-text"><?= htmlspecialchars(mb_strimwidth($rev['text'], 0, 200, '...')) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Spacer for buy bar -->
    <div style="height:80px"></div>
</div>

<!-- Buy bar (fixed, без bottom-nav) -->
<div class="gp-buy-bar">
    <?php if ($user): ?>
        <button class="gp-wishlist-btn <?= $inWishlist ? 'active' : '' ?>"
            onclick="toggleWishlist(this, <?= $game_id ?>)"
            aria-label="Вишлист">
            <i class="ti ti-<?= $inWishlist ? 'heart-filled' : 'heart' ?>"></i>
        </button>
    <?php endif; ?>

    <?php if ($isOwned): ?>
        <span class="gp-buy-price" style="font-size:15px;color:var(--ok)">В библиотеке</span>
        <?php if (!empty($g['game_zip_url'])): ?>
            <a href="/swad/controllers/download_game.php?game_id=<?= $game_id ?>"
                class="gp-buy-btn owned">⬇ Скачать</a>
        <?php else: ?>
            <div class="gp-buy-btn owned">Файл не загружен</div>
        <?php endif; ?>

    <?php elseif ($free): ?>
        <span class="gp-buy-price">Бесплатно</span>
        <?php if ($user): ?>
            <a href="/swad/controllers/add_to_library.php?game_id=<?= $game_id ?>&back=/m/game/<?= $game_id ?>"
                class="gp-buy-btn">Получить</a>
        <?php else: ?>
            <a href="/login?backUrl=/m/game/<?= $game_id ?>" class="gp-buy-btn">Войти и получить</a>
        <?php endif; ?>

    <?php else: ?>
        <span class="gp-buy-price"><?= number_format((float)$g['price'], 0, ',', ' ') ?> ₽</span>
        <?php if ($user): ?>
            <a href="/pay?game_id=<?= $game_id ?>&back=/m/game/<?= $game_id ?>"
                class="gp-buy-btn">Купить</a>
        <?php else: ?>
            <a href="/login?backUrl=/m/game/<?= $game_id ?>" class="gp-buy-btn">Войти и купить</a>
        <?php endif; ?>
    <?php endif; ?>
</div>