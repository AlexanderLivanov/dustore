<?php
/** m/views/game.php — страница игры. */
require_once __DIR__ . '/../../swad/controllers/trailer_embed.php';
require_once __DIR__ . '/../../swad/controllers/og.php';

$gid  = (int)$param;
$game = $gid > 0 ? (new Game())->getGameById($gid) : null;
if (!$game || strtolower((string)$game['status']) !== 'published' || !empty($game['hidden'])) {
    http_response_code(404);
    $title = 'Игра не найдена — Dustore';
    echo m_empty('mood-empty', 'Игра не найдена', 'Возможно, её сняли с публикации', ['В каталог', '/m/catalog']);
    return;
}
$title     = $game['name'] . ' — Dustore';
$hideNav   = true;
$hideHead  = true;
$headExtra = og_game($game);

$owned = false; $wished = false;
if ($uid) {
    $st = $db->prepare("SELECT 1 FROM library WHERE player_id = ? AND game_id = ? LIMIT 1");
    $st->execute([$uid, $gid]); $owned = (bool)$st->fetchColumn();
    try {
        $st = $db->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND game_id = ? LIMIT 1");
        $st->execute([$uid, $gid]); $wished = (bool)$st->fetchColumn();
    } catch (Throwable $e) { }
}
$st = $db->prepare("SELECT COUNT(*) FROM library WHERE game_id = ?");
$st->execute([$gid]); $downloads = (int)$st->fetchColumn();

try { $reviews = (new Game())->getReviewsArray($gid); } catch (Throwable $e) { $reviews = []; }
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : null;

$shots  = m_shots($game['screenshots'] ?? '', 10);
$icon   = trim((string)($game['icon_url'] ?? '')) ?: m_cover($game);
/* Фон шапки: баннер → первый скриншот → обложка. Раньше фоном шёл первый
   скриншот, и он же открывал ленту скриншотов, а без скриншотов за иконкой
   стояла та же картинка — «повторяются сзади». Теперь скриншот, ушедший в
   шапку, из ленты убираем (тап по шапке открывает его в просмотре), а если
   фон совпал с иконкой — это размытая подложка, а не копия. */
$banner    = trim((string)($game['banner_url'] ?? ''));
$heroShot  = $banner === '' && $shots;             // шапка = shots[0]
$hero      = $banner ?: ($shots[0] ?? m_cover($game));
$heroDupe  = $hero === $icon;
$railShots = $heroShot ? array_slice($shots, 1, null, true) : $shots;   // ключи сохраняем: data-i = индекс в просмотре
$genres = m_genres($game['genre'] ?? '');
$plats  = m_platforms($game['platforms'] ?? '');
$cta    = m_cta($game, $owned);
$studioHref = !empty($game['studio_slug']) ? '/m/dev/' . rawurlencode($game['studio_slug']) : '/m/developer/' . (int)$game['developer'];
$desc   = trim((string)($game['description'] ?? '')) ?: trim((string)($game['short_description'] ?? ''));
$short  = trim((string)($game['short_description'] ?? ''));
$platLabel = ['windows' => 'Windows', 'linux' => 'Linux', 'macos' => 'macOS', 'android' => 'Android', 'web' => 'Браузер', 'ios' => 'iOS'];
$platIcon  = ['windows' => 'brand-windows', 'linux' => 'brand-ubuntu', 'macos' => 'brand-apple', 'android' => 'brand-android', 'web' => 'world', 'ios' => 'brand-apple'];
$rel = !empty($game['release_date']) && strtotime($game['release_date']) ? date('d.m.Y', strtotime($game['release_date'])) : null;

$features     = json_decode((string)($game['features'] ?? ''), true) ?: [];
$requirements = json_decode((string)($game['requirements'] ?? ''), true) ?: [];
$achievements = json_decode((string)($game['achievements'] ?? ''), true) ?: [];
$badges       = array_values(array_filter(array_map('trim', explode(',', (string)($game['badges'] ?? '')))));
$trailer      = trailer_embed($game['trailer_url'] ?? '');
$size         = (int)($game['game_zip_size'] ?? 0);
$sizeLabel    = $size > 0 ? ($size >= 1073741824 ? number_format($size / 1073741824, 1, ',', '') . ' ГБ' : max(1, round($size / 1048576)) . ' МБ') : null;
$isApk        = $cta['href'] && str_contains((string)$cta['href'], 'download_apk.php');
?>
<article class="gp">
  <div class="gp-hero<?= $heroDupe ? ' dupe' : '' ?>">
    <img class="gp-hero-img" id="gpHeroImg" src="<?= h($hero) ?>" alt="" fetchpriority="high" draggable="false">
    <?php if ($heroShot): ?><button type="button" class="shot gp-hero-open" data-i="0" aria-label="Открыть скриншот"></button><?php endif; ?>
    <div class="gp-bar">
      <a class="ic-btn glass" href="/m/" data-back aria-label="Назад"><i class="ti ti-arrow-left"></i></a>
      <button type="button" class="ic-btn glass" data-share aria-label="Поделиться"><i class="ti ti-share-3"></i></button>
    </div>
  </div>

  <header class="gp-head">
    <img class="gp-cover" src="<?= h($icon) ?>" alt="" draggable="false" data-vt-target>
    <div class="gp-title">
      <?php if ($badges): ?><div class="gp-badges"><?php foreach ($badges as $b): ?><span><?= h($b) ?></span><?php endforeach; ?></div><?php endif; ?>
      <h1><?= h($game['name']) ?></h1>
      <a class="gp-studio" href="<?= h($studioHref) ?>"><?= h($game['studio_name']) ?><i class="ti ti-chevron-right"></i></a>
    </div>
  </header>

  <div class="gp-stats">
    <div><b><?= $avg !== null ? number_format($avg, 1, '.', '') : '—' ?></b><small><?= $reviews ? count($reviews) . ' ' . m_plural(count($reviews), 'отзыв', 'отзыва', 'отзывов') : 'нет оценок' ?></small></div>
    <div><b><?= $downloads ?></b><small><?= m_plural($downloads, 'игрок', 'игрока', 'игроков') ?></small></div>
    <div><b><?= h($game['age_rating'] ?: '0+') ?></b><small>возраст</small></div>
    <div><b><?= $sizeLabel ?: ((float)$game['price'] <= 0 ? '0 ₽' : h(m_price($game['price']))) ?></b><small><?= $sizeLabel ? 'размер' : 'цена' ?></small></div>
  </div>

  <?php if ($plats): ?>
  <div class="gp-plats">
    <?php foreach ($plats as $p): ?><span><i class="ti ti-<?= $platIcon[$p] ?? 'device-gamepad-2' ?>"></i><?= h($platLabel[$p] ?? ucfirst($p)) ?></span><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($railShots): ?>
  <div class="shots" id="shots">
    <?php foreach ($railShots as $i => $s): ?>
      <button type="button" class="shot" data-i="<?= $i ?>"><img src="<?= h($s) ?>" alt="" loading="lazy" decoding="async" draggable="false"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($short && $short !== $desc): ?><p class="gp-lead"><?= h($short) ?></p><?php endif; ?>

  <?php if ($desc): ?>
  <section class="gp-sec">
    <h2>Об игре</h2>
    <div class="gp-desc clamp" id="gpDesc"><?= nl2br(h(strip_tags($desc))) ?></div>
    <button type="button" class="link-btn" id="gpMore" hidden>Читать полностью</button>
  </section>
  <?php endif; ?>

  <?php if ($trailer !== ''): ?>
  <section class="gp-sec">
    <h2>Трейлер</h2>
    <div class="gp-trailer"><?= $trailer ?></div>
  </section>
  <?php endif; ?>

  <?php if ($features): ?>
  <section class="gp-sec">
    <h2>Особенности</h2>
    <div class="gp-feats">
      <?php foreach ($features as $f): if (!is_array($f)) continue; ?>
        <div class="gp-feat"><span class="gp-feat-ic"><?= h($f['icon'] ?? '✦') ?></span><div><b><?= h($f['title'] ?? '') ?></b><?php if (!empty($f['description'])): ?><small><?= h($f['description']) ?></small><?php endif; ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="gp-sec">
    <h2>Информация</h2>
    <dl class="gp-info">
      <?php if ($genres): ?><dt>Жанр</dt><dd><?php foreach ($genres as $gn): ?><a class="tag" href="/m/catalog?genre=<?= h(urlencode($gn)) ?>"><?= h($gn) ?></a><?php endforeach; ?></dd><?php endif; ?>
      <?php if ($plats): ?><dt>Платформы</dt><dd><?= h(implode(', ', array_map(fn($p) => $platLabel[$p] ?? ucfirst($p), $plats))) ?></dd><?php endif; ?>
      <?php if ($rel): ?><dt>Релиз</dt><dd><?= $rel ?></dd><?php endif; ?>
      <?php if (!empty($game['languages'])): ?><dt>Языки</dt><dd><?= h($game['languages']) ?></dd><?php endif; ?>
      <?php if ($sizeLabel): ?><dt>Размер</dt><dd><?= $sizeLabel ?></dd><?php endif; ?>
      <dt>Цена</dt><dd><?= (float)$game['price'] <= 0 ? 'Бесплатно' : h(m_price($game['price'])) ?><?= !empty($game['in_subscription']) ? ' · в подписке' : '' ?></dd>
      <dt>Возраст</dt><dd><?= h($game['age_rating'] ?: '0+') ?></dd>
      <dt>Студия</dt><dd><a href="<?= h($studioHref) ?>"><?= h($game['studio_name']) ?></a></dd>
    </dl>
  </section>

  <?php if ($requirements): ?>
  <section class="gp-sec">
    <h2>Системные требования</h2>
    <dl class="gp-info">
      <?php foreach ($requirements as $r): if (!is_array($r)) continue; ?><dt><?= h($r['label'] ?? '') ?></dt><dd><?= h($r['value'] ?? '') ?></dd><?php endforeach; ?>
    </dl>
  </section>
  <?php endif; ?>

  <?php if ($achievements): ?>
  <section class="gp-sec">
    <h2>Достижения <small><?= count($achievements) ?></small></h2>
    <div class="gp-achs">
      <?php foreach ($achievements as $a): if (!is_array($a)) continue; ?><div class="gp-ach"><span><?= h($a['icon'] ?? '🏆') ?></span><small><?= h($a['title'] ?? '') ?></small></div><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="gp-sec">
    <h2>Отзывы<?= $reviews ? ' <small>' . count($reviews) . '</small>' : '' ?></h2>
    <?php if (!$reviews): ?>
      <p class="muted">Пока никто не оставил отзыв<?= $owned ? ' — будьте первым на полной версии страницы' : '' ?>.</p>
    <?php endif; ?>
    <?php foreach (array_slice($reviews, 0, 8) as $r): $rt = (int)$r['rating'];
      // Автор — ссылка на его профиль. Раньше имя было просто текстом.
      $author = trim((string)($r['username'] ?? ''));
      $authorHref = $author !== '' ? '/player/' . rawurlencode($author) : null; ?>
      <div class="rev">
        <div class="rev-h">
          <?php if ($authorHref): ?><a class="rev-who" href="<?= h($authorHref) ?>"><?php else: ?><span class="rev-who"><?php endif; ?>
            <span class="rev-av"><?php if (!empty($r['profile_picture'])): ?><img src="<?= h($r['profile_picture']) ?>" alt="" loading="lazy" draggable="false"><?php else: ?><?= h(mb_strtoupper(mb_substr($author ?: '?', 0, 1))) ?><?php endif; ?></span>
            <b><?= h($author ?: 'Игрок') ?></b>
          <?= $authorHref ? '</a>' : '</span>' ?>
          <span class="rev-score s<?= $rt >= 8 ? 'hi' : ($rt >= 5 ? 'mid' : 'lo') ?>"><?= $rt ?>/10</span>
        </div>
        <?php if (trim((string)$r['text']) !== ''): ?><p class="rev-t"><?= nl2br(h($r['text'])) ?></p><?php endif; ?>
        <small class="rev-d"><?= m_ago($r['created_at'] ?? null) ?></small>
        <?php if (!empty($r['developer_reply'])): ?><div class="rev-reply"><b>Ответ студии</b><?= nl2br(h($r['developer_reply'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (count($reviews) > 8): ?><a class="link-btn" href="/g/<?= $gid ?>#reviews" data-desktop>Все отзывы на сайте</a><?php endif; ?>
  </section>
</article>

<div class="buybar">
  <button type="button" class="ic-btn wish<?= $wished ? ' on' : '' ?>" id="wishBtn" data-game="<?= $gid ?>" aria-pressed="<?= $wished ? 'true' : 'false' ?>" aria-label="В вишлист">
    <i class="ti ti-heart<?= $wished ? '-filled' : '' ?>"></i>
  </button>
  <?php if ($cta['href']): ?>
    <a class="buy <?= $cta['kind'] ?>" href="<?= h($cta['href']) ?>"<?= str_starts_with($cta['href'], '/g/') ? ' data-desktop' : '' ?>
       <?= $isApk ? 'id="apkBtn" data-size="' . $size . '" data-name="' . h(m_file_slug($game['name'], 'game-' . $gid)) . '.apk"' : '' ?>><i class="ti ti-<?= $cta['icon'] ?>"></i><span class="buy-l"><?= h($cta['label']) ?><?= $isApk && $sizeLabel ? ' · ' . $sizeLabel : '' ?></span></a>
  <?php else: ?>
    <span class="buy off"><i class="ti ti-<?= $cta['icon'] ?>"></i><?= h($cta['label']) ?></span>
  <?php endif; ?>
  <?php if ($owned): ?><span class="owned"><i class="ti ti-check"></i>В библиотеке</span><?php endif; ?>
  <?php if ($cta['note']): ?><small class="buy-note"><?= h($cta['note']) ?></small><?php endif; ?>
</div>

<div class="viewer" id="viewer" hidden>
  <div class="viewer-track"><?php foreach ($shots as $s): ?><img src="<?= h($s) ?>" alt="" loading="lazy" draggable="false"><?php endforeach; ?></div>
  <button type="button" class="ic-btn glass viewer-x" aria-label="Закрыть"><i class="ti ti-x"></i></button>
</div>
