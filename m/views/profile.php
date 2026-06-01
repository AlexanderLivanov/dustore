<?php

/**
 * m/views/profile.php
 * ИСПРАВЛЕНО: таблица wishlists (не wishlist)
 */
// print_r($_SESSION);
if (!$user):
?>
    <div class="empty-state" style="padding-top:60px">
        <div class="empty-icon"><i class="ti ti-user" style="font-size:44px"></i></div>
        <div class="empty-title">Войди в аккаунт</div>
        <div class="empty-sub">Чтобы видеть свой профиль,<br>библиотеку и историю покупок</div>
        <a href="/login?backUrl=/m/profile" class="btn" style="margin-top:20px">Войти</a>
    </div>
<?php return;
endif; ?>

<?php
/* Статистика — каждый запрос в отдельном try/catch чтобы один упавший не ронял страницу */
$games_count   = 0;
$wishlist_count = 0;
$badges_count  = 0;

try {
    $s = $db->prepare("SELECT COUNT(*) FROM library WHERE player_id = ? AND purchased = 1");
    $s->execute([$user['id']]);
    $games_count = (int)$s->fetchColumn();
} catch (Exception $e) {
}

try {
    /* Таблица называется wishlists */
    $s = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $s->execute([$user['id']]);
    $wishlist_count = (int)$s->fetchColumn();
} catch (Exception $e) {
}

try {
    $s = $db->prepare("SELECT COUNT(*) FROM given_user_badges WHERE user_id = ?");
    $s->execute([$user['id']]);
    $badges_count = (int)$s->fetchColumn();
} catch (Exception $e) {
}

/* Студия разработчика */
$studio = null;
try {
    $s = $db->prepare("SELECT id, name FROM studios WHERE owner_id = ? LIMIT 1");
    $s->execute([$user['id']]);
    $studio = $s->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

$initials = mb_strtoupper(
    mb_substr($user['first_name'] ?? 'U', 0, 1) .
        mb_substr($user['last_name']  ?? '',  0, 1)
);
?>

<div class="prof-header">
    <div class="prof-avatar">
        <?php if (!empty($user['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="">
        <?php else: ?>
            <?= htmlspecialchars($initials) ?>
        <?php endif; ?>
    </div>
    <div class="prof-name"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></div>
    <?php if (!empty($user['username'])): ?>
        <div class="prof-username">@<?= htmlspecialchars($user['username']) ?></div>
    <?php endif; ?>

    <div class="prof-stats">
        <div class="prof-stat">
            <div class="prof-stat-val"><?= $games_count ?></div>
            <div class="prof-stat-lbl">Игр</div>
        </div>
        <div class="prof-stat">
            <div class="prof-stat-val"><?= $wishlist_count ?></div>
            <div class="prof-stat-lbl">Вишлист</div>
        </div>
        <div class="prof-stat">
            <div class="prof-stat-val"><?= $badges_count ?></div>
            <div class="prof-stat-lbl">Значков</div>
        </div>
    </div>
</div>

<div class="spacer"></div>

<div class="menu-group">
    <a href="/m/library" class="menu-item">
        <i class="ti ti-library"></i>
        <span>Моя библиотека</span>
        <i class="ti ti-chevron-right"></i>
    </a>
    <a href="/m/library?tab=wishlist" class="menu-item">
        <i class="ti ti-heart"></i>
        <span>Вишлист</span>
        <i class="ti ti-chevron-right"></i>
    </a>
    <a href="/profile" class="menu-item">
        <i class="ti ti-user-circle"></i>
        <span>Полный профиль</span>
        <i class="ti ti-chevron-right"></i>
    </a>
</div>

<?php if ($studio): ?>
    <div class="sec-label">Разработчик</div>
    <div class="menu-group">
        <a href="/m/developer/<?= (int)$studio['id'] ?>" class="menu-item">
            <i class="ti ti-building"></i>
            <span>Моя студия</span>
            <i class="ti ti-chevron-right"></i>
        </a>
        <a href="/devs/" class="menu-item">
            <i class="ti ti-code"></i>
            <span>Консоль разработчика</span>
            <i class="ti ti-chevron-right"></i>
        </a>
    </div>
<?php endif; ?>

<div class="menu-group">
    <a href="/settings" class="menu-item">
        <i class="ti ti-settings"></i>
        <span>Настройки</span>
        <i class="ti ti-chevron-right"></i>
    </a>
    <button onclick="goDesktop()" class="menu-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer">
        <i class="ti ti-device-desktop"></i>
        <span>Полная версия сайта</span>
        <i class="ti ti-chevron-right"></i>
    </button>
</div>

<div class="menu-group">
    <a href="/logout" class="menu-item danger">
        <i class="ti ti-logout"></i>
        <span>Выйти</span>
    </a>
</div>

<div class="spacer"></div>