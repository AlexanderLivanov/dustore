<?php
/** m/views/catalog.php — каталог: жанры, сортировка, бесконечная лента. */
$title = 'Каталог — Dustore';

$sorts = M_SORTS;
$f = m_catalog_filters($_GET);
$res = m_games($f);
try { $genres = (new Game())->collectGenres(false, $f['web']); } catch (Throwable $e) { $genres = []; }

/** Ссылка с изменённым параметром (остальные фильтры сохраняются). */
function cat_url(array $patch): string {
    $q = array_merge(array_intersect_key($_GET, array_flip(['genre', 'sort', 'price', 'web'])), $patch);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== false);
    return '/m/catalog' . ($q ? '?' . http_build_query($q) : '');
}
$apiQs = http_build_query(array_filter([
    'genre' => $f['genre'], 'sort' => $f['sort'], 'price' => $f['price_type'] !== 'all' ? $f['price_type'] : null, 'web' => $f['web'] ? 1 : null,
]));
?>
<div class="page-h">
  <h1>Каталог</h1>
  <span class="count"><?= $res['total'] ?> <?= m_plural($res['total'], 'игра', 'игры', 'игр') ?></span>
</div>

<div class="filters">
  <div class="seg" role="tablist">
    <?php foreach ($sorts as $k => $label): ?>
      <a class="<?= $f['sort'] === $k ? 'on' : '' ?>" href="<?= h(cat_url(['sort' => $k])) ?>" role="tab"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <nav class="chips" aria-label="Фильтры">
    <a class="chip<?= $f['price_type'] === 'free' ? ' on' : '' ?>" href="<?= h(cat_url(['price' => $f['price_type'] === 'free' ? null : 'free'])) ?>"><i class="ti ti-gift"></i>Бесплатные</a>
    <a class="chip<?= $f['web'] ? ' on' : '' ?>" href="<?= h(cat_url(['web' => $f['web'] ? null : 1, 'genre' => null])) ?>"><i class="ti ti-world"></i>В браузере</a>
    <?php if ($f['genre'] && !in_array($f['genre'], $genres, true)) array_unshift($genres, $f['genre']); ?>
    <?php foreach ($genres as $gname): $on = $f['genre'] !== null && mb_strtolower($gname) === mb_strtolower($f['genre']); ?>
      <a class="chip<?= $on ? ' on' : '' ?>" href="<?= h(cat_url(['genre' => $on ? null : $gname])) ?>"><?= h($gname) ?><?= $on ? '<i class="ti ti-x"></i>' : '' ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if ($res['items']): ?>
  <div class="grid" id="feed" data-api="/m/api/games.php?<?= h($apiQs) ?>" data-offset="<?= count($res['items']) ?>" data-total="<?= $res['total'] ?>">
    <?php foreach ($res['items'] as $g) echo m_card($g); ?>
  </div>
  <div class="feed-more" id="feedMore"<?= count($res['items']) >= $res['total'] ? ' hidden' : '' ?>><span class="spin"></span></div>
<?php else: ?>
  <?= m_empty('mood-empty', 'Ничего не нашлось', 'Попробуйте другой жанр или сбросьте фильтры', ['Сбросить', '/m/catalog']) ?>
<?php endif; ?>
