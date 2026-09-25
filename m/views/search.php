<?php
/** m/views/search.php — живой поиск по играм и студиям. */
$title = 'Поиск — Dustore';
$q = trim((string)($_GET['q'] ?? ''));
try { $genres = array_slice((new Game())->collectGenres(false), 0, 16); } catch (Throwable $e) { $genres = []; }
?>
<form class="search" action="/m/search" role="search" id="searchForm">
  <i class="ti ti-search"></i>
  <input type="search" name="q" id="searchQ" value="<?= h($q) ?>" placeholder="Игры, жанры, студии" autocomplete="off" enterkeyhint="search" autofocus>
  <button type="button" class="x" id="searchX" aria-label="Очистить"<?= $q === '' ? ' hidden' : '' ?>><i class="ti ti-x"></i></button>
</form>
<div id="searchOut" aria-live="polite"><?php if ($q !== '') { require __DIR__ . '/../api/search_results.php'; } ?></div>
<div id="searchIdle"<?= $q !== '' ? ' hidden' : '' ?>>
  <?php if ($genres): ?>
    <div class="sec-h"><h2>Жанры</h2></div>
    <nav class="chips wrap"><?php foreach ($genres as $gn): ?><a class="chip" href="/m/catalog?genre=<?= h(urlencode($gn)) ?>"><?= h($gn) ?></a><?php endforeach; ?></nav>
  <?php endif; ?>
</div>
