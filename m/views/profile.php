<?php
/** m/views/profile.php — профиль, уведомления, настройки приложения. */
$title = 'Профиль — Dustore';
if (!$uid) {
    echo m_empty('user', 'Вы не вошли', 'Войдите, чтобы видеть библиотеку, чаты и уведомления', ['Войти', '/m/login?back=/m/profile']);
    return;
}
require_once __DIR__ . '/../../chat/_vapid.php';

$st = $db->prepare("SELECT username, first_name, last_name, profile_picture FROM users WHERE id = ?");
$st->execute([$uid]);
$u = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$count = function (string $sql) use ($db, $uid): int {
    try { $q = $db->prepare($sql); $q->execute([$uid]); return (int)$q->fetchColumn(); } catch (Throwable $e) { return 0; }
};
$nGames   = $count("SELECT COUNT(DISTINCT game_id) FROM library WHERE player_id = ? AND game_id > 0");
$nWish    = $count("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
$nReviews = $count("SELECT COUNT(*) FROM game_reviews WHERE user_id = ?");

$studio = null;
try {
    $q = $db->prepare("SELECT id, name, tiker FROM studios WHERE owner_id = ? LIMIT 1");
    $q->execute([$uid]); $studio = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { }

$name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? 'Игрок');
$ava  = trim((string)($u['profile_picture'] ?? ''));
$footExtra = '<script>window.VAPID_PUBLIC = ' . json_encode(vapid_public_key()) . ';</script>'
           . '<script src="' . m_asset('/pwa/push-client.js') . '"></script>'
           . '<script src="' . m_asset('/m/js/auth.js') . '" defer></script>';
?>
<section class="prof">
  <div class="prof-av"><?php if ($ava): ?><img src="<?= h($ava) ?>" alt="" draggable="false"><?php else: ?><?= h(mb_strtoupper(mb_substr($name, 0, 1))) ?><?php endif; ?></div>
  <h1><?= h($name) ?></h1>
  <?php if (!empty($u['username'])): ?><a class="prof-handle" href="/player/<?= h(rawurlencode($u['username'])) ?>">@<?= h($u['username']) ?></a><?php endif; ?>
  <div class="prof-stats">
    <a href="/m/library"><b><?= $nGames ?></b><small><?= m_plural($nGames, 'игра', 'игры', 'игр') ?></small></a>
    <a href="/m/library?tab=wishlist"><b><?= $nWish ?></b><small>в вишлисте</small></a>
    <span><b><?= $nReviews ?></b><small><?= m_plural($nReviews, 'отзыв', 'отзыва', 'отзывов') ?></small></span>
  </div>
</section>

<div class="menu">
  <div class="menu-i push" id="pushRow">
    <i class="ti ti-bell-ringing"></i>
    <span><b>Уведомления</b><small id="pushSub">Сообщения и события аккаунта</small></span>
    <button type="button" class="switch" id="pushSwitch" role="switch" aria-checked="false" aria-label="Уведомления на этом устройстве"><i></i></button>
  </div>
  <a class="menu-i" href="/m/chat"><i class="ti ti-message-circle"></i><span>Чаты</span><i class="ti ti-chevron-right"></i></a>
  <a class="menu-i" href="/m/chat?system=1"><i class="ti ti-bell"></i><span>Лента уведомлений</span><i class="ti ti-chevron-right"></i></a>
</div>

<div class="menu-t">Вход без пароля</div>
<div class="menu">
  <div id="pkList"></div>
  <button type="button" class="menu-i" id="pkAdd"><i class="ti ti-fingerprint"></i><span><b>Добавить Face ID / отпечаток</b><small id="pkHint">Passkey: вход в одно касание, без пароля</small></span><i class="ti ti-plus"></i></button>
</div>

<?php if ($studio): ?>
<div class="menu-t">Разработчик</div>
<div class="menu">
  <a class="menu-i" href="<?= h($studio['tiker'] ? '/m/dev/' . rawurlencode($studio['tiker']) : '/m/developer/' . (int)$studio['id']) ?>"><i class="ti ti-building-store"></i><span><?= h($studio['name']) ?></span><i class="ti ti-chevron-right"></i></a>
  <a class="menu-i" href="/devs/" data-desktop><i class="ti ti-code"></i><span>Консоль разработчика</span><i class="ti ti-external-link"></i></a>
</div>
<?php endif; ?>

<div class="menu">
  <a class="menu-i" href="/settings" data-desktop><i class="ti ti-settings"></i><span>Настройки аккаунта</span><i class="ti ti-external-link"></i></a>
  <button type="button" class="menu-i" data-go-desktop><i class="ti ti-device-desktop"></i><span>Полная версия сайта</span><i class="ti ti-chevron-right"></i></button>
  <a class="menu-i danger" href="/logout"><i class="ti ti-logout"></i><span>Выйти</span></a>
</div>
<p class="ver">Dustore для телефона · PWA</p>
