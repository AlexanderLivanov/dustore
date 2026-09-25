<?php
/** m/views/library.php — мои игры и вишлист. */
$title = 'Мои игры — Dustore';
if (!$uid) {
    echo m_empty('lock', 'Войдите в аккаунт', 'Здесь будут ваши игры и вишлист', ['Войти', '/login?backUrl=/m/library']);
    return;
}
$tab = ($_GET['tab'] ?? '') === 'wishlist' ? 'wishlist' : 'games';

/* та же выборка, что у десктопной library.php: строки с game_id = 0 —
   коллекционные предметы, удалённые игры отваливаются на JOIN */
$st = $db->prepare("SELECT g.id, g.name, g.path_to_cover, g.price, g.genre, s.name AS studio_name
                      FROM (SELECT game_id, MAX(date) AS d FROM library
                             WHERE player_id = ? AND game_id > 0 GROUP BY game_id) l
                      JOIN games g ON g.id = l.game_id JOIN studios s ON s.id = g.developer
                     ORDER BY l.d DESC LIMIT 300");
$st->execute([$uid]);
$games = $st->fetchAll(PDO::FETCH_ASSOC);

$wish = [];
try {
    $st = $db->prepare("SELECT g.id, g.name, g.path_to_cover, g.price, g.genre, s.name AS studio_name
                          FROM wishlists w JOIN games g ON g.id = w.game_id JOIN studios s ON s.id = g.developer
                         WHERE w.user_id = ? ORDER BY w.id DESC LIMIT 300");
    $st->execute([$uid]);
    $wish = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { }
?>
<div class="page-h"><h1>Мои игры</h1></div>
<div class="seg wide" role="tablist">
  <a class="<?= $tab === 'games' ? 'on' : '' ?>" href="/m/library" role="tab">Библиотека <small><?= count($games) ?></small></a>
  <a class="<?= $tab === 'wishlist' ? 'on' : '' ?>" href="/m/library?tab=wishlist" role="tab">Вишлист <small><?= count($wish) ?></small></a>
</div>

<div class="list">
<?php if ($tab === 'games'): ?>
  <?php if (!$games): ?><?= m_empty('device-gamepad-2', 'Библиотека пуста', 'Скачанные и купленные игры появятся здесь', ['В каталог', '/m/catalog']) ?><?php endif; ?>
  <?php foreach ($games as $g) echo m_card($g, 'row'); ?>
<?php else: ?>
  <?php if (!$wish): ?><?= m_empty('heart', 'Вишлист пуст', 'Жмите ♥ на странице игры — например, чтобы скачать её потом с компьютера') ?><?php endif; ?>
  <?php foreach ($wish as $g) echo m_card($g, 'row'); ?>
<?php endif; ?>
</div>
