<?php
/**
 * m/api/search_results.php — HTML результатов поиска по $q.
 * Подключается и страницей /m/search (первый рендер по ?q=), и api/search.php.
 */
if (!isset($q, $db)) { http_response_code(404); exit; }   // напрямую по URL не открывается
$res = m_games(['q' => $q, 'limit' => 20]);
$studios = [];
try {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st = $db->prepare("SELECT s.id, s.name, s.tiker, s.avatar_link,
                               (SELECT COUNT(*) FROM games g WHERE g.developer = s.id AND g.status = 'published') AS n
                          FROM studios s WHERE s.name LIKE ? ORDER BY n DESC LIMIT 5");
    $st->execute([$like]);
    $studios = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { }

if (!$res['items'] && !$studios) {
    echo m_empty('mood-empty', 'Ничего не найдено', 'По запросу «' . h($q) . '» пусто — попробуйте иначе');
    return;
}
if ($studios): ?>
  <div class="sec-h"><h2>Студии</h2></div>
  <div class="list">
  <?php foreach ($studios as $s): $n = (int)$s['n']; ?>
    <a class="row" href="<?= h($s['tiker'] ? '/m/dev/' . rawurlencode($s['tiker']) : '/m/developer/' . (int)$s['id']) ?>">
      <span class="row-cover round"><?php if (!empty($s['avatar_link'])): ?><img src="<?= h($s['avatar_link']) ?>" alt="" loading="lazy" draggable="false"><?php else: ?><i class="ti ti-building-store"></i><?php endif; ?></span>
      <span class="row-main"><b><?= h($s['name']) ?></b><small><?= $n ?> <?= m_plural($n, 'игра', 'игры', 'игр') ?></small></span>
      <i class="ti ti-chevron-right row-side"></i>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif;
if ($res['items']): ?>
  <div class="sec-h"><h2>Игры</h2><span class="count"><?= $res['total'] ?></span></div>
  <div class="list"><?php foreach ($res['items'] as $g) echo m_card($g, 'row'); ?></div>
<?php endif;
