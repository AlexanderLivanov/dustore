<?php
/**
 * media/post.php — страница поста
 * Доступ: /media/p/{short_code}  (rewrite → post.php?c={code})
 * Здесь же: серверный учёт просмотра + OG-теги для превью в Telegram/VK.
 */
require_once __DIR__ . '/_bootstrap.php';

$pdo  = media_pdo();
$uid  = media_user_id();
$code = preg_replace('~[^A-Za-z0-9]~', '', $_GET['c'] ?? '');
if (strlen($code) !== 7) { http_response_code(404); exit('Not found'); }

$stmt = $pdo->prepare("
    SELECT p.*,
           u.username, u.profile_picture,
           s.name AS studio_name, s.avatar_link AS studio_avatar,
           g.name AS game_name,
           " . ($uid ? "EXISTS(SELECT 1 FROM media_likes ml WHERE ml.post_id = p.id AND ml.user_id = {$uid})" : "0") . " AS my_like
    FROM media_posts p
    JOIN users u        ON u.id = p.author_user_id
    LEFT JOIN studios s ON s.id = p.studio_id
    LEFT JOIN games g   ON g.id = p.game_id
    WHERE p.short_code = ? AND p.status = 'published'
    LIMIT 1
");
$stmt->execute([$code]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { http_response_code(404); exit('Пост не найден'); }

/* ── Учёт просмотра: INSERT IGNORE в дедуп-таблицу, инкремент только при новой строке ── */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = (bool)preg_match('~bot|crawl|spider|preview|telegram|vkshare|facebookexternalhit|whatsapp~i', $ua);
if (!$isBot) {
    $viewerKey = $uid > 0
        ? 'u:' . $uid
        : 'a:' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua . '|' . MEDIA_VIEW_PEPPER;
    $hash = md5($viewerKey, true); // BINARY(16)
    $ins = $pdo->prepare("INSERT IGNORE INTO media_views (post_id, viewer_hash, view_date) VALUES (?, ?, CURDATE())");
    $ins->execute([(int)$p['id'], $hash]);
    if ($ins->rowCount() > 0) {
        $pdo->prepare("UPDATE media_posts SET views_count = views_count + 1 WHERE id = ?")->execute([(int)$p['id']]);
        $p['views_count']++;
    }
}

$isStudio   = !empty($p['studio_id']);
$authorName = $isStudio ? $p['studio_name'] : $p['username'];
$authorAv   = $isStudio ? $p['studio_avatar'] : $p['profile_picture'];
$att        = json_decode($p['attachments'] ?? '[]', true) ?: [];
$imgs       = array_values(array_filter($att, fn($a) => ($a['kind'] ?? '') === 'image'));
$videos     = array_values(array_filter($att, fn($a) => ($a['kind'] ?? '') === 'video'));

function med_e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* OG-данные для превью в мессенджерах */
$ogTitle = $p['title'] ?: ($authorName . ' — Dustore.Media');
$ogDesc  = mb_substr(trim(strip_tags($p['body'])), 0, 180);
$ogImage = $imgs[0]['path'] ?? MEDIA_CANON_HOST . '/swad/static/img/logo_new.png';
$ogUrl   = MEDIA_CANON_HOST . '/media/p/' . $p['short_code'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= med_e($ogTitle) ?> — Dustore.Media</title>
    <link rel="canonical" href="<?= med_e($ogUrl) ?>">
    <meta property="og:type"        content="article">
    <meta property="og:title"       content="<?= med_e($ogTitle) ?>">
    <meta property="og:description" content="<?= med_e($ogDesc) ?>">
    <meta property="og:image"       content="<?= med_e($ogImage) ?>">
    <meta property="og:url"         content="<?= med_e($ogUrl) ?>">
    <meta property="og:site_name"   content="Dustore.Media">
    <meta name="twitter:card"       content="summary_large_image">
    <?php
    // Если DustoreSEO подключён глобально — можно заменить блок выше на него:
    // (new DustoreSEO())->render([...]) — CONFIRM API класса
    require_once __DIR__ . '/../swad/static/elements/header.php'; // CONFIRM: шапка выводит свой <head>? Тогда убери дубли.
    ?>
    <style>
        :where(body) {
    background: linear-gradient(180deg, #0f0a20, #240038, #780066);
    min-height: 100vh;
}
    .dm-wrap { max-width: 680px; margin: 0 auto; padding: 30px 16px 120px; }
    .dm-card { background:var(--surf); border:1px solid var(--border); padding:24px;
               clip-path:polygon(0 6px,6px 6px,6px 0,calc(100% - 6px) 0,calc(100% - 6px) 6px,100% 6px,
                                 100% calc(100% - 6px),calc(100% - 6px) calc(100% - 6px),calc(100% - 6px) 100%,
                                 6px 100%,6px calc(100% - 6px),0 calc(100% - 6px)); }
    .dm-post-head { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .dm-post-head img.av { width:44px; height:44px; object-fit:cover; border:1px solid var(--border); }
    .dm-badge { font-size:10px; text-transform:uppercase; letter-spacing:.08em; padding:2px 7px;
                border:1px solid var(--p); color:var(--p); margin-left:6px; }
    h1.dm-title { font-family:'Syne',sans-serif; font-size:26px; margin:6px 0 16px; }
    .dm-body { font-size:15px; line-height:1.7; overflow-wrap:break-word; }
    .dm-body img { max-width:100%; margin:10px 0; }
    .dm-body a { color:var(--p); }
    .dm-gallery { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:8px; margin-top:16px; }
    .dm-gallery img { width:100%; aspect-ratio:16/9; object-fit:cover; cursor:pointer; border:1px solid var(--border); }
    .dm-video iframe { width:100%; aspect-ratio:16/9; border:1px solid var(--border); margin-top:16px; }
    .dm-foot { display:flex; align-items:center; gap:18px; margin-top:20px; padding-top:14px;
               border-top:1px solid var(--border); font-size:14px; color:var(--tm,#888); }
    .dm-like { display:flex; align-items:center; gap:6px; background:none; border:none; color:inherit;
               cursor:pointer; font-size:14px; }
    .dm-like.liked { color:var(--p); }
    .dm-share { margin-left:auto; background:none; border:none; color:var(--tm,#888); cursor:pointer; font-size:13px;
                display:flex; align-items:center; gap:4px; }
    </style>
</head>
<body>
<div class="dm-wrap">
    <a href="/media/" style="color:var(--tm,#888);font-size:13px;text-decoration:none;">← Dustore.Media</a>
    <article class="dm-card" style="margin-top:14px;">
        <div class="dm-post-head">
            <img class="av" src="<?= med_e($authorAv ?: '/swad/static/img/logo_new.png') ?>" alt="">
            <div>
                <b><?= med_e($authorName) ?></b>
                <?php if ($isStudio): ?><span class="dm-badge">студия</span><?php endif; ?>
                <div style="font-size:12px;color:var(--tm,#888);">
                    <?= date('d.m.Y H:i', strtotime($p['published_at'])) ?>
                    <?php if ($p['game_name']): ?> · девлог: <a href="/g/<?= (int)$p['game_id'] ?>" style="color:var(--p);"><?= med_e($p['game_name']) ?></a><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($p['title']): ?><h1 class="dm-title"><?= med_e($p['title']) ?></h1><?php endif; ?>

        <div class="dm-body"><?= $p['body'] ?></div>

        <?php if ($imgs): ?>
        <div class="dm-gallery">
            <?php foreach ($imgs as $im): ?>
            <img src="<?= med_e($im['path']) ?>" loading="lazy" onclick="window.open(this.src,'_blank')">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($videos as $v): ?>
        <div class="dm-video">
            <iframe src="<?= med_e($v['embed']) ?>" allowfullscreen allow="autoplay; encrypted-media; fullscreen"></iframe>
        </div>
        <?php endforeach; ?>

        <div class="dm-foot">
            <button class="dm-like <?= $p['my_like'] ? 'liked' : '' ?>" onclick="dmLike(this, <?= (int)$p['id'] ?>)">
                <span class="material-icons"><?= $p['my_like'] ? 'favorite' : 'favorite_border' ?></span>
                <span class="cnt"><?= (int)$p['likes_count'] ?></span>
            </button>
            <span><span class="material-icons" style="font-size:17px;vertical-align:-3px;">visibility</span>
                  <?= (int)$p['views_count'] ?></span>
            <button class="dm-share" onclick="dmShare()">
                <span class="material-icons" style="font-size:16px;">link</span> dustore.gg/<?= med_e($p['short_code']) ?>
            </button>
        </div>
    </article>
</div>

<script>
async function dmLike(btn, id) {
    <?php if (!$uid): ?> location.href = '/login?backUrl=<?= urlencode('/media/p/' . $p['short_code']) ?>'; return; <?php endif; ?>
    const fd = new FormData();
    fd.append('action', 'toggle_like');
    fd.append('post_id', id);
    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    if (!r.success) return;
    btn.classList.toggle('liked', r.liked);
    btn.querySelector('.material-icons').textContent = r.liked ? 'favorite' : 'favorite_border';
    btn.querySelector('.cnt').textContent = r.likes;
}
function dmShare() {
    navigator.clipboard.writeText('https://dustore.gg/<?= med_e($p['short_code']) ?>')
        .then(() => alert('Короткая ссылка скопирована'));
}
</script>
<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; // CONFIRM ?>
</body>
</html>