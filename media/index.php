<?php
/**
 * media/index.php — лента Dustore.Media + композер (v3)
 * v3: SunEditor (paste из буфера, ресайз картинок), тосты вместо alert,
 *     короткие ссылки dustore.ru/p/{code}, счётчик комментариев.
 */
require_once __DIR__ . '/_bootstrap.php';

$pdo = media_pdo();
$uid = media_user_id();

$myStudios = $uid ? media_user_studios($pdo, $uid) : [];

/* Игры моих студий — некритичный запрос: при ошибке лента живёт дальше */
$myGames = [];
if ($myStudios) {
    try {
        $ids = implode(',', array_map(fn($s) => (int)$s['id'], $myStudios));
        $myGames = $pdo->query("SELECT id, name, developer AS studio_id FROM games WHERE developer IN ($ids) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('media myGames: ' . $e->getMessage());
    }
}

/* Лента: keyset-пагинация по id */
$beforeId = (int)($_GET['before'] ?? 0);
$filter   = in_array($_GET['tab'] ?? 'all', ['all','devlogs','articles'], true) ? ($_GET['tab'] ?? 'all') : 'all';

$where = ["p.status = 'published'"];
$args  = [];
if ($filter === 'devlogs')  $where[] = "p.game_id IS NOT NULL";
if ($filter === 'articles') $where[] = "p.type = 'article'";
if ($beforeId > 0) { $where[] = "p.id < ?"; $args[] = $beforeId; }

$sql = "
    SELECT p.*,
           u.username, u.profile_picture,
           s.name AS studio_name, s.avatar_link AS studio_avatar,
           g.name AS game_name,
           " . ($uid ? "EXISTS(SELECT 1 FROM media_likes ml WHERE ml.post_id = p.id AND ml.user_id = {$uid})" : "0") . " AS my_like
    FROM media_posts p
    JOIN users u        ON u.id = p.author_user_id
    LEFT JOIN studios s ON s.id = p.studio_id
    LEFT JOIN games g   ON g.id = p.game_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.id DESC
    LIMIT 20
";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function med_e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function med_ago(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60)     return 'только что';
    if ($d < 3600)   return floor($d/60) . ' мин';
    if ($d < 86400)  return floor($d/3600) . ' ч';
    if ($d < 604800) return floor($d/86400) . ' дн';
    return date('d.m.Y', strtotime($dt));
}
function med_num(int $n): string {
    if ($n >= 1000000) return round($n/1000000, 1) . 'M';
    if ($n >= 1000)    return round($n/1000, 1) . 'K';
    return (string)$n;
}

$isAjax = isset($_GET['ajax']);
if (!$isAjax):
require_once __DIR__ . '/../swad/static/elements/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/dist/css/suneditor.min.css">
<link rel="stylesheet" href="<?= function_exists('asset_url') ? asset_url('/media/media.css') : '/media/media.css?v=3' ?>">
<link rel="stylesheet" href="<?= function_exists('asset_url') ? asset_url('/media/editor-theme.css') : '/media/editor-theme.css?v=1' ?>">

<div class="dm-scope">
    <div class="dm-head">
        <h1>Dustore<em>.Media</em></h1>
        <div class="dm-sub">девлоги · статьи · жизнь инди-разработки</div>
    </div>

    <div class="dm-tabs">
        <a class="dm-tab <?= $filter==='all'?'active':'' ?>" href="/media/">Всё</a>
        <a class="dm-tab <?= $filter==='devlogs'?'active':'' ?>" href="/media/?tab=devlogs">Девлоги</a>
        <a class="dm-tab <?= $filter==='articles'?'active':'' ?>" href="/media/?tab=articles">Статьи</a>
    </div>

    <?php if ($uid): ?>
    <div class="dm-card dm-composer" id="dm-composer">
        <div class="dm-row" style="margin-bottom:10px;">
            <select id="c-type" style="margin-bottom:0;">
                <option value="post">Короткий пост</option>
                <option value="article">Статья</option>
            </select>
            <?php if ($myStudios): ?>
            <select id="c-studio" style="margin-bottom:0;">
                <option value="">От своего имени</option>
                <?php foreach ($myStudios as $s): ?>
                <option value="<?= (int)$s['id'] ?>">Студия: <?= med_e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="c-game" style="margin-bottom:0;">
                <option value="">Без привязки к игре</option>
                <?php foreach ($myGames as $g): ?>
                <option value="<?= (int)$g['id'] ?>" data-studio="<?= (int)$g['studio_id'] ?>"><?= med_e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>

        <input type="text" id="c-title" placeholder="Заголовок статьи" style="display:none;">

        <!-- SunEditor: вставка картинок из буфера в любое место + ресайз за уголки -->
        <textarea id="c-editor" style="display:none;"></textarea>

        <div class="dm-imgstrip" id="c-imgs"></div>

        <div class="dm-row">
            <label class="dm-btn dm-btn-ghost" style="flex:0 0 auto; text-align:center;">
                <span class="material-icons" style="font-size:16px;vertical-align:-3px;">image</span> Обложки
                <input type="file" id="c-file" accept="image/*" multiple hidden>
            </label>
            <input type="url" id="c-video" placeholder="Ссылка на видео (YouTube / RuTube / VK)" style="margin-bottom:0;">
        </div>

        <div class="dm-row" style="margin-top:12px;">
            <label class="dm-check"><input type="checkbox" id="c-tg"> Кросс-пост в Telegram</label>
            <label class="dm-check"><input type="checkbox" id="c-vk"> Кросс-пост в VK</label>
            <span style="flex:1;"></span>
            <button class="dm-btn" id="c-publish" style="flex:0 0 auto;">Опубликовать</button>
        </div>
    </div>
    <?php else: ?>
    <div class="dm-card dm-login-cta">
        <div class="txt">
            <b>Хотите рассказать о своей разработке?</b>
            <span>Войдите, чтобы публиковать девлоги и статьи</span>
        </div>
        <a class="dm-btn" href="/login?backUrl=/media/">Войти</a>
    </div>
    <?php endif; ?>

    <div id="dm-feed">
<?php endif; /* !$isAjax */ ?>

<?php if (!$posts && !$isAjax): ?>
    <div class="dm-empty">
        <span class="material-icons">auto_awesome</span>
        <b><?= $filter === 'all' ? 'Здесь пока пусто' : 'В этом разделе пока пусто' ?></b>
        <span><?= $uid ? 'Станьте первым — расскажите, над чем работаете' : 'Скоро здесь появятся девлоги и статьи разработчиков' ?></span>
    </div>
<?php endif; ?>

<?php foreach ($posts as $p):
    $isStudio = !empty($p['studio_id']);
    $authorName = $isStudio ? $p['studio_name'] : $p['username'];
    $authorAv   = $isStudio ? $p['studio_avatar'] : $p['profile_picture'];
    $att = json_decode($p['attachments'] ?? '[]', true) ?: [];
    $imgs   = array_filter($att, fn($a) => ($a['kind'] ?? '') === 'image');
    $videos = array_filter($att, fn($a) => ($a['kind'] ?? '') === 'video');
    $purl = '/p/' . $p['short_code'];
?>
    <article class="dm-card <?= $isStudio ? 'is-studio' : '' ?>" data-id="<?= (int)$p['id'] ?>">
        <div class="dm-post-head">
            <img class="av" src="<?= med_e($authorAv ?: '/swad/static/img/logo_new.png') ?>" alt="">
            <div class="who">
                <b><?= med_e($authorName) ?></b><?php
                if ($isStudio): ?><span class="dm-badge">студия</span><?php endif;
                if ($p['type']==='article'): ?><span class="dm-badge">статья</span><?php endif; ?>
                <div class="sub">
                    <?= med_ago($p['published_at']) ?>
                    <?php if ($p['game_name']): ?> · девлог: <a href="/g/<?= (int)$p['game_id'] ?>"><?= med_e($p['game_name']) ?></a><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($p['title']): ?>
        <a class="dm-title-link" href="<?= med_e($purl) ?>">
            <div class="dm-title"><?= med_e($p['title']) ?></div>
        </a>
        <?php endif; ?>

        <div class="dm-body"><?= $p['body'] /* санитизирован при записи */ ?></div>

        <?php if ($imgs): ?>
        <div class="dm-gallery">
            <?php foreach ($imgs as $im): ?>
            <img src="<?= med_e($im['path']) ?>" loading="lazy" onclick="window.open(this.src,'_blank')">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($videos as $v): ?>
        <div class="dm-video">
            <iframe src="<?= med_e($v['embed']) ?>" allowfullscreen
                    allow="autoplay; encrypted-media; fullscreen" loading="lazy"></iframe>
        </div>
        <?php endforeach; ?>

        <div class="dm-foot">
            <button class="dm-like <?= $p['my_like'] ? 'liked' : '' ?>" onclick="dmLike(this, <?= (int)$p['id'] ?>)">
                <span class="material-icons"><?= $p['my_like'] ? 'favorite' : 'favorite_border' ?></span>
                <span class="cnt"><?= med_num((int)$p['likes_count']) ?></span>
            </button>
            <a class="dm-views" href="<?= med_e($purl) ?>#comments" style="color:inherit;text-decoration:none;">
                <span class="material-icons">chat_bubble_outline</span> <?= med_num((int)($p['comments_count'] ?? 0)) ?>
            </a>
            <span class="dm-views">
                <span class="material-icons">visibility</span> <?= med_num((int)$p['views_count']) ?>
            </span>
            <button class="dm-share" onclick="dmShare('<?= med_e($p['short_code']) ?>')">
                <span class="material-icons">link</span> Ссылка
            </button>
        </div>
    </article>
<?php endforeach; ?>

<?php if ($isAjax) exit; ?>
    </div><!-- /dm-feed -->

    <?php if (count($posts) === 20): ?>
    <button class="dm-btn dm-btn-ghost" id="dm-more" style="width:100%;"
            data-before="<?= (int)end($posts)['id'] ?>">Показать ещё</button>
    <?php endif; ?>
</div><!-- /dm-scope -->

<div id="dm-toast" class="dm-toast"></div>

<?php if ($uid): ?>
<script src="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/dist/suneditor.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/src/lang/ru.js"></script>
<?php endif; ?>
<script>
function dmToast(msg) {
    const t = document.getElementById('dm-toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.remove('show'), 2200);
}

function dmShare(code) {
    const url = 'https://dustore.ru/p/' + code;
    navigator.clipboard.writeText(url)
        .then(() => dmToast('Ссылка скопирована: dustore.ru/p/' + code))
        .catch(() => dmToast(url)); // не-secure context: хотя бы покажем
}

<?php if ($uid): ?>
const dmImages = [];

const editor = SUNEDITOR.create('c-editor', {
    lang: SUNEDITOR_LANG['ru'],
    width: '100%',
    minHeight: '130px',
    placeholder: 'Что нового в разработке? Картинку можно вставить прямо из буфера (Ctrl+V)',
    buttonList: [
        ['bold', 'italic', 'strike'],
        ['formatBlock'],
        ['blockquote', 'list'],
        ['link', 'image'],
        ['codeView']
    ],
    formats: ['p', 'h2', 'h3', 'pre'],
    // paste из буфера и drag-drop идут сюда автоматически; ресайз за уголки включён по умолчанию
    imageUploadUrl: '/media/api.php?action=sun_upload',
    imageUploadSizeLimit: 8 * 1024 * 1024,
    imageAccept: '.jpg,.jpeg,.png,.webp,.gif',
    defaultTag: 'p',
    attributesWhitelist: { img: 'style|width|height|alt' }
});

document.getElementById('c-type').addEventListener('change', e => {
    document.getElementById('c-title').style.display = e.target.value === 'article' ? '' : 'none';
});

document.getElementById('c-game')?.addEventListener('change', e => {
    const sid = e.target.selectedOptions[0]?.dataset?.studio;
    if (sid) document.getElementById('c-studio').value = sid;
});

document.getElementById('c-file').addEventListener('change', async e => {
    for (const file of e.target.files) {
        if (dmImages.length >= 10) break;
        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('image', file);
        const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
        if (r.success) { dmImages.push(r.path); dmRenderImgs(); }
        else dmToast(r.error || 'Ошибка загрузки');
    }
    e.target.value = '';
});

function dmRenderImgs() {
    document.getElementById('c-imgs').innerHTML = dmImages.map((p, i) =>
        `<div class="thumb"><img src="${p}"><button onclick="dmImages.splice(${i},1);dmRenderImgs()">✕</button></div>`
    ).join('');
}

document.getElementById('c-publish').addEventListener('click', async function () {
    this.disabled = true;
    const fd = new FormData();
    fd.append('action', 'create_post');
    fd.append('type',   document.getElementById('c-type').value);
    fd.append('title',  document.getElementById('c-title').value);
    fd.append('body',   editor.getContents(true));
    fd.append('images', JSON.stringify(dmImages));
    fd.append('video_url', document.getElementById('c-video').value);
    fd.append('studio_id', document.getElementById('c-studio')?.value || '');
    fd.append('game_id',   document.getElementById('c-game')?.value || '');
    if (document.getElementById('c-tg').checked) fd.append('crosspost_tg', '1');
    if (document.getElementById('c-vk').checked) fd.append('crosspost_vk', '1');

    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    this.disabled = false;
    if (r.success) location.href = r.url;
    else dmToast(r.error || 'Ошибка');
});
<?php endif; ?>

async function dmLike(btn, id) {
    <?php if (!$uid): ?> location.href = '/login?backUrl=/media/'; return; <?php endif; ?>
    const fd = new FormData();
    fd.append('action', 'toggle_like');
    fd.append('post_id', id);
    const r = await fetch('/media/api.php', { method:'POST', body: fd }).then(r => r.json());
    if (!r.success) return;
    btn.classList.toggle('liked', r.liked);
    btn.querySelector('.material-icons').textContent = r.liked ? 'favorite' : 'favorite_border';
    btn.querySelector('.cnt').textContent = r.likes;
}

document.getElementById('dm-more')?.addEventListener('click', async function () {
    const before = this.dataset.before;
    const html = await fetch(`/media/?ajax=1&before=${before}&tab=<?= med_e($filter) ?>`).then(r => r.text());
    if (html.trim() === '') { this.remove(); return; }
    document.getElementById('dm-feed').insertAdjacentHTML('beforeend', html);
    const cards = document.querySelectorAll('#dm-feed .dm-card[data-id]');
    this.dataset.before = cards[cards.length - 1]?.dataset.id || before;
});
</script>

<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>