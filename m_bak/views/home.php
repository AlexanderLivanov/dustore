<?php

/**
 * m/views/home.php
 */

$featured = $db->query("
    SELECT g.id, g.name, g.short_description, g.price,
           g.path_to_cover, g.genre,
           s.name AS studio_name,
           COALESCE(AVG(r.rating), 0) AS rating
    FROM games g
    JOIN studios s ON s.id = g.developer
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE g.moderation_status = 'approved'
    GROUP BY g.id, g.name, g.short_description, g.price, g.path_to_cover, g.genre, s.name
    ORDER BY g.created_at DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$popular = $db->query("
    SELECT g.id, g.name, g.price, g.path_to_cover,
           s.name AS studio_name,
           COALESCE(AVG(r.rating), 0) AS rating
    FROM games g
    JOIN studios s ON s.id = g.developer
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE g.moderation_status = 'approved'
    GROUP BY g.id, g.name, g.price, g.path_to_cover, s.name
    ORDER BY rating DESC, g.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$show_install = empty($_COOKIE['pwa_standalone']);
?>

<!-- PWA Install banner -->
<?php if ($show_install): ?>
    <div class="install-banner" id="install-banner" style="display:none;margin:12px 14px 0">
        <i class="ti ti-device-mobile"></i>
        <div class="install-text">
            <div class="install-title">Добавить на экран</div>
            <div class="install-sub">Работает без браузера</div>
        </div>
        <button class="install-btn" onclick="triggerInstall()">Установить</button>
    </div>
<?php endif; ?>

<div class="spacer"></div>

<!-- Search -->
<div style="padding:0 14px">
    <a href="/m/search" class="search-pill" style="cursor:pointer">
        <i class="ti ti-search"></i>
        <span style="color:var(--txt3)">Поиск игр и студий...</span>
    </a>
</div>

<div class="spacer"></div>

<!-- Genre chips -->
<div class="h-scroll chip-group" role="group" aria-label="Жанры">
    <div class="chip active" data-filter="all">Все</div>
    <div class="chip" data-filter="action">Экшен</div>
    <div class="chip" data-filter="rpg">RPG</div>
    <div class="chip" data-filter="indie">Инди</div>
    <div class="chip" data-filter="strategy">Стратегия</div>
    <div class="chip" data-filter="puzzle">Пазл</div>
    <div class="chip" data-filter="horror">Хоррор</div>
    <div class="chip" data-filter="platformer">Платформер</div>
</div>

<div class="spacer"></div>

<!-- Новинки -->
<div class="px">
    <div class="section-header">
        <span class="section-title">Новинки</span>
        <a href="/m/catalog" class="section-link">Все →</a>
    </div>
</div>

<?php if (empty($featured)): ?>
    <div style="padding:20px 14px;color:var(--txt3);font-size:14px">Скоро появятся игры</div>
<?php else: ?>
    <div class="h-scroll">
        <?php foreach ($featured as $g):
            $free = ((float)$g['price'] === 0.0);
        ?>
            <a href="/m/game/<?= (int)$g['id'] ?>" class="feat-card">
                <div class="feat-cover">
                    <?php if (!empty($g['path_to_cover'])): ?>
                        <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                            alt="<?= htmlspecialchars($g['name']) ?>"
                            loading="lazy"
                            onerror="this.style.display='none'">
                    <?php endif; ?>
                    <span class="feat-badge <?= $free ? 'free' : '' ?>">
                        <?= $free ? 'Бесплатно' : 'Новинка' ?>
                    </span>
                </div>
                <div class="feat-info">
                    <div class="feat-title"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="feat-studio"><?= htmlspecialchars($g['studio_name']) ?></div>
                    <div class="feat-footer">
                        <div class="feat-price <?= $free ? 'free' : '' ?>">
                            <?= $free ? 'Бесплатно' : number_format((float)$g['price'], 0, ',', ' ') . ' ₽' ?>
                        </div>
                        <?php if ((float)$g['rating'] > 0): ?>
                            <div class="feat-rating">
                                <i class="ti ti-star-filled"></i>
                                <?= number_format((float)$g['rating'], 1) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="spacer"></div>

<!-- Популярное -->
<div class="px">
    <div class="section-header">
        <span class="section-title">Популярное</span>
        <a href="/m/catalog?sort=rating" class="section-link">Все →</a>
    </div>
    <?php if (!empty($popular)): ?>
        <div class="mini-grid">
            <?php foreach ($popular as $g):
                $free = ((float)$g['price'] === 0.0);
            ?>
                <a href="/m/game/<?= (int)$g['id'] ?>" class="mini-card">
                    <div class="mini-cover">
                        <?php if (!empty($g['path_to_cover'])): ?>
                            <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                                alt="" loading="lazy"
                                onerror="this.style.display='none'">
                        <?php endif; ?>
                    </div>
                    <div class="mini-info">
                        <div class="mini-title"><?= htmlspecialchars($g['name']) ?></div>
                        <div class="mini-studio"><?= htmlspecialchars($g['studio_name']) ?></div>
                        <div class="mini-price <?= $free ? 'free' : '' ?>">
                            <?= $free ? 'Бесплатно' : number_format((float)$g['price'], 0, ',', ' ') . ' ₽' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="spacer"></div>

<div style="text-align:center;padding:0 14px 8px">
    <button onclick="goDesktop()"
        style="background:none;border:none;color:var(--txt3);font-size:12px;cursor:pointer;font-family:inherit">
        Полная версия сайта →
    </button>
</div>