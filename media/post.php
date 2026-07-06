<?php
/**
 * media/post.php — страница поста (v2)
 * Доступ: /p/{short_code}  (rewrite → post.php?c={code}) — это канонический URL.
 * v2: комментарии (плоские, tombstone), тост вместо alert.
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

/* ── Просмотр: дедуп через INSERT IGNORE ── */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = (bool)preg_match('~bot|crawl|spider|preview|telegram|vkshare|facebookexternalhit|whatsapp~i', $ua);
if (!$isBot) {
    $viewerKey = $uid > 0
        ? 'u:' . $uid
        : 'a:' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua . '|' . MEDIA_VIEW_PEPPER;
    $ins = $pdo->prepare("INSERT IGNORE INTO media_views (post_id, viewer_hash, view_date) VALUES (?, ?, CURDATE())");
    $ins->execute([(int)$p['id'], md5($viewerKey, true)]);
    if ($ins->rowCount() > 0) {
        $pdo->prepare("UPDATE media_posts SET views_count = views_count + 1 WHERE id = ?")->execute([(int)$p['id']]);
        $p['views_count']++;
    }
}

/* ── Комментарии ── */
$cStmt = $pdo->prepare("
    SELECT c.id, c.user_id, c.body, c.created_at, u.username, u.profile_picture
    FROM media_comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.post_id = ? AND c.status = 'published'
    ORDER BY c.id ASC
    LIMIT 300
");
$cStmt->execute([(int)$p['id']]);
$comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

/* право удалять чужие комменты: автор поста или член студии-автора */
$isPostOwner = $uid > 0 && ((int)$p['author_user_id'] === $uid
    || ($p['studio_id'] && in_array((int)$p['studio_id'],
         array_map('intval', array_column(media_user_studios($pdo, $uid), 'id')), true)));

$isStudio   = !empty($p['studio_id']);
$authorName = $isStudio ? $p['studio_name'] : $p['username'];
$authorAv   = $isStudio ? $p['studio_avatar'] : $p['profile_picture'];
$att        = json_decode($p['attachments'] ?? '[]', true) ?: [];
$imgs       = array_values(array_filter($att, fn($a) => ($a['kind'] ?? '') === 'image'));
$videos     = array_values(array_filter($att, fn($a) => ($a['kind'] ?? '') === 'video'));

function med_e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ogTitle = $p['title'] ?: ($authorName . ' — Dustore.Media');
$ogDesc  = mb_substr(trim(strip_tags($p['body'])), 0, 180);
$ogImage = $imgs[0]['path'] ?? MEDIA_CANON_HOST . '/swad/static/img/logo_new.png';
$ogUrl   = media_post_url($p['short_code']);
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
    <?php require_once __DIR__ . '/../swad/static/elements/header.php'; ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="<?= function_exists('asset_url') ? asset_url('/media/media.css') : '/media/media.css?v=3' ?>">
</head>
<body>
<div class="dm-scope">
    <a href="/media/" style="color:var(--dm-muted,#888);font-size:13px;text-decoration:none;">← Dustore.Media</a>

    <article class="dm-card <?= $isStudio ? 'is-studio' : '' ?>" style="margin-top:14px;">
        <div class="dm-post-head">
            <img class="av" src="<?= med_e($authorAv ?: '/swad/static/img/logo_new.png') ?>" alt="">
            <div class="who">
                <b><?= med_e($authorName) ?></b>
                <?php if ($isStudio): ?><span class="dm-badge">студия</span><?php endif; ?>
                <div class="sub">
                    <?= date('d.m.Y H:i', strtotime($p['published_at'])) ?>
                    <?php if ($p['game_name']): ?> · девлог: <a href="/g/<?= (int)$p['game_id'] ?>"><?= med_e($p['game_name']) ?></a><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($p['title']): ?><h1 class="dm-title" style="font-size:26px;"><?= med_e($p['title']) ?></h1><?php endif; ?>

        <div class="dm-body" style="font-size:15px;"><?= $p['body'] ?></div>

        <?php if ($imgs): ?>
        <div class="dm-gallery" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
            <?php
            print_r(med_e($im['path']));
            ?>
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
            <span class="dm-views">
                <span class="material-icons">visibility</span> <?= (int)$p['views_count'] ?>
            </span>
            <button class="dm-share" onclick="dmShare()">
                <span class="material-icons">link</span> dustore.ru/p/<?= med_e($p['short_code']) ?>
            </button>
        </div>
    </article>

    <!-- ═════════ КОММЕНТАРИИ ═════════ -->
    <div class="dm-card" id="comments" style="margin-top:16px;">
        <div style="font-family:var(--dm-f-head,'Syne');font-size:16px;margin-bottom:14px;">
            Комментарии <span id="cm-count" style="color:var(--dm-muted,#888);">(<?= count($comments) ?>)</span>
        </div>

        <div id="cm-list">
            <?php foreach ($comments as $c): ?>
            <div class="dm-comment" data-cid="<?= (int)$c['id'] ?>">
                <img class="cm-av" src="<?= med_e($c['profile_picture'] ?: '/swad/static/img/logo_new.png') ?>" alt="">
                <div class="cm-main">
                    <div class="cm-head">
                        <b><?= med_e($c['username']) ?></b>
                        <span class="cm-date"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
                        <?php if ($uid && ((int)$c['user_id'] === $uid || $isPostOwner)): ?>
                        <button class="cm-del" title="Удалить" onclick="dmDelComment(<?= (int)$c['id'] ?>)">
                            <span class="material-icons" style="font-size:15px;">close</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="cm-body"><?= nl2br(med_e($c['body'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$comments): ?>
            <div id="cm-empty" style="color:var(--dm-muted,#888);font-size:13px;padding:8px 0;">
                Комментариев пока нет — будьте первым.
            </div>
            <?php endif; ?>
        </div>

        <?php if ($uid): ?>
        <div class="cm-form">
            <textarea id="cm-input" maxlength="2000" rows="2" placeholder="Написать комментарий…"></textarea>
            <button class="dm-btn" id="cm-send">Отправить</button>
        </div>
        <?php else: ?>
        <div style="margin-top:12px;font-size:13px;color:var(--dm-muted,#888);">
            <a href="/login?backUrl=<?= urlencode('/p/' . $p['short_code']) ?>" style="color:var(--dm-p,#c32178);">Войдите</a>, чтобы комментировать.
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="dm-toast" class="dm-toast"></div>

<script>
const POST_ID = <?= (int)$p['id'] ?>;

function dmToast(msg) {
    const t = document.getElementById('dm-toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.remove('show'), 2200);
}

function dmShare() {
    const url = 'https://dustore.ru/p/<?= med_e($p['short_code']) ?>';
    navigator.clipboard.writeText(url)
        .then(() => dmToast('Ссылка скопирована'))
        .catch(() => dmToast(url));
}

async function dmLike(btn, id) {
    <?php if (!$uid): ?> location.href = '/login?backUrl=<?= urlencode('/p/' . $p['short_code']) ?>'; return; <?php endif; ?>
    const fd = new FormData();
    fd.append('action', 'toggle_like');
    fd.append('post_id', id);
    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    if (!r.success) return;
    btn.classList.toggle('liked', r.liked);
    btn.querySelector('.material-icons').textContent = r.liked ? 'favorite' : 'favorite_border';
    btn.querySelector('.cnt').textContent = r.likes;
}

function cmEsc(s) {
    return String(s).replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
}

<?php if ($uid): ?>
document.getElementById('cm-send').addEventListener('click', async function () {
    const input = document.getElementById('cm-input');
    const body = input.value.trim();
    if (!body) return;
    this.disabled = true;
    const fd = new FormData();
    fd.append('action', 'add_comment');
    fd.append('post_id', POST_ID);
    fd.append('body', body);
    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    this.disabled = false;
    if (!r.success) { dmToast(r.error || 'Ошибка'); return; }

    document.getElementById('cm-empty')?.remove();
    const c = r.comment;
    document.getElementById('cm-list').insertAdjacentHTML('beforeend', `
        <div class="dm-comment" data-cid="${c.id}">
            <img class="cm-av" src="${cmEsc(c.avatar || '/swad/static/img/logo_new.png')}" alt="">
            <div class="cm-main">
                <div class="cm-head">
                    <b>${cmEsc(c.username)}</b>
                    <span class="cm-date">только что</span>
                    <button class="cm-del" title="Удалить" onclick="dmDelComment(${c.id})">
                        <span class="material-icons" style="font-size:15px;">close</span>
                    </button>
                </div>
                <div class="cm-body">${cmEsc(c.body).replace(/\n/g, '<br>')}</div>
            </div>
        </div>`);
    input.value = '';
    const cnt = document.getElementById('cm-count');
    cnt.textContent = '(' + (document.querySelectorAll('.dm-comment').length) + ')';
});

// Ctrl+Enter — отправить
document.getElementById('cm-input').addEventListener('keydown', e => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) document.getElementById('cm-send').click();
});
<?php endif; ?>

async function dmDelComment(cid) {
    if (!confirm('Удалить комментарий?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_comment');
    fd.append('comment_id', cid);
    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    if (!r.success) { dmToast(r.error || 'Ошибка'); return; }
    document.querySelector(`.dm-comment[data-cid="${cid}"]`)?.remove();
    const cnt = document.getElementById('cm-count');
    cnt.textContent = '(' + document.querySelectorAll('.dm-comment').length + ')';
}
</script>
<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>
</body>
</html>