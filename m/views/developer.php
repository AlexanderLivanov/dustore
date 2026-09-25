<?php
/** m/views/developer.php — страница студии: /m/dev/<tiker> или /m/developer/<id>. */
$studio = null;
if ($param !== null && $param !== '') {
    $st = ctype_digit((string)$param)
        ? $db->prepare("SELECT * FROM studios WHERE id = ? LIMIT 1")
        : $db->prepare("SELECT * FROM studios WHERE tiker = ? LIMIT 1");
    $st->execute([ctype_digit((string)$param) ? (int)$param : (string)$param]);
    $studio = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$studio) {
    http_response_code(404);
    $title = 'Студия не найдена — Dustore';
    echo m_empty('building-store', 'Студия не найдена', '', ['На главную', '/m/']);
    return;
}
$title = $studio['name'] . ' — Dustore';
$sid   = (int)$studio['id'];

/* игры студии — тем же фильтром публикации, что и витрина */
$st = $db->prepare("SELECT g.id, g.name, g.path_to_cover, g.price, g.genre, rv.avg_rating
                      FROM games g
                      LEFT JOIN (SELECT game_id, AVG(rating) AS avg_rating FROM game_reviews GROUP BY game_id) rv ON rv.game_id = g.id
                     WHERE g.developer = ? AND g.status = 'published' AND (g.hidden IS NULL OR g.hidden = 0)
                     ORDER BY g.release_date DESC, g.id DESC LIMIT 60");
$st->execute([$sid]);
$games = $st->fetchAll(PDO::FETCH_ASSOC);
$st = $db->prepare("SELECT COUNT(*) FROM library l JOIN games g ON g.id = l.game_id WHERE g.developer = ?");
$st->execute([$sid]);
$players = (int)$st->fetchColumn();

$banner = trim((string)($studio['banner_link'] ?? ''));
$avatar = trim((string)($studio['avatar_link'] ?? ''));
$about  = trim(strip_tags((string)($studio['description'] ?? $studio['about'] ?? '')));
$links = array_filter([
    'world'          => $studio['website'] ?? '',
    'brand-telegram' => $studio['tg_link'] ?? '',
    'brand-vk'       => $studio['vk_link'] ?? '',
    'mail'           => !empty($studio['contact_email']) ? 'mailto:' . $studio['contact_email'] : '',
], fn($v) => trim((string)$v) !== '');
?>
<section class="studio">
  <div class="studio-banner"<?= $banner ? ' style="background-image:url(\'' . h($banner) . '\')"' : '' ?>></div>
  <div class="studio-av"><?php if ($avatar): ?><img src="<?= h($avatar) ?>" alt="" draggable="false"><?php else: ?><?= h(mb_strtoupper(mb_substr((string)$studio['name'], 0, 2))) ?><?php endif; ?></div>
  <h1><?= h($studio['name']) ?></h1>
  <p class="studio-meta"><?= count($games) ?> <?= m_plural(count($games), 'игра', 'игры', 'игр') ?> · <?= $players ?> <?= m_plural($players, 'игрок', 'игрока', 'игроков') ?></p>
  <div class="studio-act">
    <?php if ($uid): ?><a class="pill-btn" href="/m/chat?studio=<?= $sid ?>"><i class="ti ti-message-circle"></i>Написать студии</a><?php endif; ?>
    <?php foreach ($links as $icon => $href): $href = preg_match('~^(https?:|mailto:)~i', $href) ? $href : 'https://' . $href; ?>
      <a class="ic-btn" href="<?= h($href) ?>" target="_blank" rel="noopener" aria-label="<?= h($icon) ?>"><i class="ti ti-<?= h($icon) ?>"></i></a>
    <?php endforeach; ?>
  </div>
  <?php if ($about): ?><p class="studio-about clamp" id="gpDesc"><?= nl2br(h($about)) ?></p><button type="button" class="link-btn" id="gpMore" hidden>Читать полностью</button><?php endif; ?>
</section>

<?php if ($games): ?>
  <div class="sec-h"><h2>Игры студии</h2></div>
  <div class="grid"><?php foreach ($games as $g) echo m_card($g + ['studio_name' => $studio['name']]); ?></div>
<?php else: ?>
  <?= m_empty('device-gamepad-2', 'Пока без релизов', 'Студия ещё не опубликовала игры') ?>
<?php endif; ?>
