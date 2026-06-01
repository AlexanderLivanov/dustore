<?php

/**
 * m/views/library.php
 * ИСПРАВЛЕНО: таблица wishlists (не wishlist)
 */

if (!$user):
?>
    <div class="empty-state" style="padding-top:60px">
        <div class="empty-icon"><i class="ti ti-lock" style="font-size:44px"></i></div>
        <div class="empty-title">Войди в аккаунт</div>
        <div class="empty-sub">Чтобы видеть свою библиотеку</div>
        <a href="/login?backUrl=/m/library" class="btn" style="margin-top:20px">Войти</a>
    </div>
<?php return;
endif; ?>

<?php
$lib_games = [];
try {
    $s = $db->prepare("
        SELECT g.id, g.name, g.price, g.path_to_cover,
               s.name AS studio_name,
               l.date AS purchased_at,
               COALESCE(AVG(r.rating), 0) AS rating
        FROM library l
        JOIN games g ON g.id = l.game_id
        JOIN studios s ON s.id = g.developer
        LEFT JOIN game_reviews r ON r.game_id = g.id
        WHERE l.player_id = ? AND l.purchased = 1
        GROUP BY g.id, g.name, g.price, g.path_to_cover, s.name, l.date
        ORDER BY l.date DESC
        LIMIT 60
    ");
    $s->execute([$user['id']]);
    $lib_games = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

$wishlist = [];
try {
    /* ИСПРАВЛЕНО: таблица wishlists */
    $s = $db->prepare("
        SELECT g.id, g.name, g.price, g.path_to_cover, s.name AS studio_name
        FROM wishlists w
        JOIN games g ON g.id = w.game_id
        JOIN studios s ON s.id = g.developer
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
        LIMIT 50
    ");
    $s->execute([$user['id']]);
    $wishlist = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

/* Определяем активную вкладку из GET */
$active_tab = ($_GET['tab'] ?? 'games') === 'wishlist' ? 'wishlist' : 'games';
?>

<div class="lib-header">
    <div class="lib-title">Библиотека</div>
    <div class="lib-tabs">
        <div class="lib-tab <?= $active_tab === 'games' ? 'active' : '' ?>" data-tab="games">
            Игры (<?= count($lib_games) ?>)
        </div>
        <div class="lib-tab <?= $active_tab === 'wishlist' ? 'active' : '' ?>" data-tab="wishlist">
            Вишлист (<?= count($wishlist) ?>)
        </div>
    </div>
</div>

<div class="spacer-sm"></div>

<!-- Games pane -->
<div class="library-pane" data-pane="games" <?= $active_tab !== 'games' ? 'style="display:none"' : '' ?>>
    <?php if (empty($lib_games)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="ti ti-devices" style="font-size:44px"></i></div>
            <div class="empty-title">Библиотека пуста</div>
            <div class="empty-sub">Найди свою первую игру<br>в каталоге</div>
            <a href="/m/catalog" class="btn" style="margin-top:20px">Перейти в каталог</a>
        </div>
    <?php else: ?>
        <?php foreach ($lib_games as $g):
            $date = !empty($g['purchased_at']) ? date('d.m.Y', strtotime($g['purchased_at'])) : '';
        ?>
            <a href="/m/game/<?= (int)$g['id'] ?>" class="list-row">
                <div class="list-cover">
                    <?php if (!empty($g['path_to_cover'])): ?>
                        <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                            alt="" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div class="list-body">
                    <div class="list-title"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="list-meta">
                        <?= htmlspecialchars($g['studio_name']) ?>
                        <?php if ($date): ?> · <?= $date ?><?php endif; ?>
                    </div>
                </div>
                <div class="list-right">
                    <?php if ((float)$g['rating'] > 0): ?>
                        <div class="list-rating">
                            <i class="ti ti-star-filled" style="color:var(--warn)"></i>
                            <?= number_format((float)$g['rating'], 1) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Wishlist pane -->
<div class="library-pane" data-pane="wishlist" <?= $active_tab !== 'wishlist' ? 'style="display:none"' : '' ?>>
    <?php if (empty($wishlist)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="ti ti-heart" style="font-size:44px"></i></div>
            <div class="empty-title">Вишлист пуст</div>
            <div class="empty-sub">Добавляй игры, которые<br>хочешь купить позже</div>
        </div>
    <?php else: ?>
        <?php foreach ($wishlist as $g):
            $free = ((float)$g['price'] === 0.0);
        ?>
            <a href="/m/game/<?= (int)$g['id'] ?>" class="list-row">
                <div class="list-cover">
                    <?php if (!empty($g['path_to_cover'])): ?>
                        <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                            alt="" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div class="list-body">
                    <div class="list-title"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="list-meta"><?= htmlspecialchars($g['studio_name']) ?></div>
                </div>
                <div class="list-right">
                    <div class="list-price <?= $free ? 'free' : '' ?>">
                        <?= $free ? 'Бесплатно' : number_format((float)$g['price'], 0, ',', ' ') . ' ₽' ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>