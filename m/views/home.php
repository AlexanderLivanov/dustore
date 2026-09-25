<?php
/** m/views/home.php — витрина: герой-карусель, ленты, жанры. */
$title = 'Dustore — инди-игры';

$top   = m_games(['sort' => 'popularity', 'limit' => 13])['items'];   // 5 в герой + 8 в сетку (чётно для 2 колонок)
$fresh = m_games(['sort' => 'date', 'limit' => 12])['items'];
$free  = m_games(['sort' => 'popularity', 'price_type' => 'free', 'limit' => 12])['items'];
try { $genres = array_slice((new Game())->collectGenres(false), 0, 14); } catch (Throwable $e) { $genres = []; }

$hero = array_slice($top, 0, 5);
$topRest = array_slice($top, 5);

/* «Продолжить играть» — последние из библиотеки. Отдельный маленький запрос:
   это данные пользователя, а не витрины. */
$mine = [];
if ($uid) {
    try {
        $st = $db->prepare("SELECT g.id, g.name, g.path_to_cover, g.price, g.genre, s.name AS studio_name
                              FROM library l JOIN games g ON g.id = l.game_id JOIN studios s ON s.id = g.developer
                             WHERE l.player_id = ? AND l.game_id > 0
                             ORDER BY l.date DESC LIMIT 8");
        $st->execute([$uid]);
        $mine = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { }
}

function m_rail(string $title, string $more, array $games): void {
    if (!$games) return; ?>
  <section class="sec">
    <div class="sec-h"><h2><?= h($title) ?></h2><?php if ($more): ?><a href="<?= h($more) ?>">Все<i class="ti ti-chevron-right"></i></a><?php endif; ?></div>
    <div class="rail"><?php foreach ($games as $g) echo m_card($g, 'rail'); ?></div>
  </section>
<?php }
?>

<?php if ($hero): ?>
<section class="hero" aria-label="Популярное сейчас">
  <div class="hero-track" id="heroTrack">
    <?php foreach ($hero as $i => $g):
      $bg = m_shots($g['screenshots'] ?? '', 1)[0] ?? m_cover($g);
      $rate = m_rating($g['avg_rating'] ?? null); ?>
      <a class="hero-slide" href="/m/game/<?= (int)$g['id'] ?>" data-vt>
        <img src="<?= h($bg) ?>" alt="" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?> decoding="async" draggable="false">
        <span class="hero-info">
          <span class="hero-tag"><i class="ti ti-flame"></i>Популярное</span>
          <b><?= h($g['name']) ?></b>
          <small><?= h($g['studio_name'] ?? '') ?><?= $rate ? ' · ★ ' . $rate : '' ?></small>
          <span class="hero-cta"><?= m_price($g['price'] ?? 0) ?><i class="ti ti-arrow-right"></i></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="hero-dots" id="heroDots"><?php foreach ($hero as $i => $_): ?><i<?= $i ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if ($genres): ?>
<nav class="chips" aria-label="Жанры">
  <?php foreach ($genres as $gname): ?><a class="chip" href="/m/catalog?genre=<?= h(urlencode($gname)) ?>"><?= h($gname) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>

<?php m_rail('Продолжить', '/m/library', $mine); ?>
<?php m_rail('Новинки', '/m/catalog?sort=date', $fresh); ?>
<?php m_rail('Бесплатно', '/m/catalog?price=free', $free); ?>

<?php if ($topRest): ?>
<section class="sec">
  <div class="sec-h"><h2>Все любят</h2><a href="/m/catalog">Каталог<i class="ti ti-chevron-right"></i></a></div>
  <div class="grid"><?php foreach ($topRest as $g) echo m_card($g); ?></div>
</section>
<?php endif; ?>

<?php if (!$hero && !$fresh): ?>
  <?= m_empty('mood-empty', 'Витрина пуста', 'Скоро здесь появятся игры') ?>
<?php endif; ?>
