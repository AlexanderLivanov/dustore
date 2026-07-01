<?php
session_start();
require_once('../swad/config.php');

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login');
    exit;
}

$db  = new Database();
$pdo = $db->connect();
$user_id = (int)$_SESSION['USERDATA']['id'];

$page = $_GET['tab'] ?? 'library'; // library | wishlist

/* ── Library ── */
$library = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, al.date AS obtained_date
        FROM asset_library al
        JOIN assets a ON a.id = al.asset_id
        WHERE al.player_id = ?
        ORDER BY al.date DESC
    ");
    $stmt->execute([$user_id]);
    $library = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

/* ── Wishlist ── */
$wishlist = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, aw.added_at,
               EXISTS(SELECT 1 FROM asset_library al WHERE al.player_id=? AND al.asset_id=a.id) AS is_owned
        FROM asset_wishlist aw
        JOIN assets a ON a.id = aw.asset_id
        WHERE aw.player_id = ?
        ORDER BY aw.added_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

$CATS = [
    '3d_model' => ['label' => '3D Модели', 'emoji' => '🧊'],
    'texture' => ['label' => 'Текстуры', 'emoji' => '🖼️'],
    'music'   => ['label' => 'Музыка',   'emoji' => '🎵'],
    'sfx'    => ['label' => 'SFX',     'emoji' => '🔊'],
    'sprite'  => ['label' => 'Спрайты',  'emoji' => '🎨'],
    'shader' => ['label' => 'Шейдеры', 'emoji' => '✨'],
    'font'    => ['label' => 'Шрифты',   'emoji' => '🔤'],
    'script' => ['label' => 'Скрипты', 'emoji' => '📜'],
    'ui_kit'  => ['label' => 'UI Киты',  'emoji' => '🎛️'],
    'animation' => ['label' => 'Анимации', 'emoji' => '🎬'],
    'vfx'     => ['label' => 'VFX',      'emoji' => '💥'],
    'video'  => ['label' => 'Видео',   'emoji' => '📹'],
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page === 'library' ? 'Моя библиотека' : 'Список желаний' ?> — Dustore</title>
    <link rel="stylesheet" href="../swad/css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pr: #c32178;
            --dark: #0d0118;
            --surf: rgba(255, 255, 255, .04);
            --surf2: rgba(255, 255, 255, .075);
            --bdr: rgba(255, 255, 255, .09);
            --bdr2: rgba(255, 255, 255, .15);
            --txt: #f0e6ff;
            --muted: rgba(240, 230, 255, .45);
            --success: #00e887;
            --warn: #f59e0b;
            --r: 12px;
            --ease: cubic-bezier(.4, 0, .2, 1)
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            background: var(--dark);
            color: var(--txt);
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px
        }

        .page-hero {
            background: linear-gradient(160deg, #1e0240, #0d0118);
            border-bottom: 1px solid var(--bdr);
            padding: 40px 0 0;
            position: relative
        }

        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 10% 60%, rgba(195, 33, 120, .12) 0%, transparent 55%)
        }

        .page-hero .container {
            position: relative
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(195, 33, 120, .12);
            border: 1px solid rgba(195, 33, 120, .25);
            border-radius: 100px;
            padding: 4px 14px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #e88fc0;
            margin-bottom: 14px
        }

        .page-hero h1 {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -.03em;
            margin-bottom: 6px
        }

        .page-hero p {
            color: var(--muted);
            font-size: .88rem;
            margin-bottom: 24px
        }

        /* Tab nav */
        .tab-nav {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--bdr)
        }

        .tab-nav a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            font-size: .88rem;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all .18s;
            position: relative;
            bottom: -1px
        }

        .tab-nav a:hover {
            color: var(--txt)
        }

        .tab-nav a.active {
            color: var(--txt);
            border-bottom-color: var(--pr)
        }

        .tab-nav .cnt {
            background: var(--surf2);
            border: 1px solid var(--bdr);
            border-radius: 100px;
            padding: 1px 8px;
            font-size: .7rem
        }

        .tab-nav a.active .cnt {
            background: rgba(195, 33, 120, .2);
            border-color: rgba(195, 33, 120, .35);
            color: #e88fc0
        }

        /* Layout */
        .page-body {
            padding: 32px 0 80px
        }

        /* Asset grid */
        .ag {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px
        }

        /* Card */
        .ac {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: var(--r);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: border-color .2s, box-shadow .2s
        }

        .ac:hover {
            border-color: rgba(195, 33, 120, .35);
            box-shadow: 0 8px 32px rgba(0, 0, 0, .4)
        }

        .ac-thumb {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #160028;
            flex-shrink: 0
        }

        .ac-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s;
            display: block
        }

        .ac:hover .ac-thumb img {
            transform: scale(1.05)
        }

        .ac-cat {
            position: absolute;
            top: 8px;
            left: 8px;
            border-radius: 6px;
            padding: 3px 9px;
            font-size: .68rem;
            font-weight: 700;
            backdrop-filter: blur(8px)
        }

        .ac-price {
            position: absolute;
            top: 8px;
            right: 8px;
            border-radius: 6px;
            padding: 3px 9px;
            font-size: .7rem;
            font-weight: 700;
            backdrop-filter: blur(8px)
        }

        .ac-price.free {
            background: rgba(0, 232, 135, .18);
            color: var(--success);
            border: 1px solid rgba(0, 232, 135, .3)
        }

        .ac-price.paid {
            background: rgba(13, 1, 24, .72);
            color: var(--txt);
            border: 1px solid rgba(255, 255, 255, .14)
        }

        .ac-body {
            padding: 12px 13px 8px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px
        }

        .ac-name {
            font-size: .87rem;
            font-weight: 700;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden
        }

        .ac-author {
            font-size: .74rem;
            color: var(--muted)
        }

        .ac-date {
            font-size: .72rem;
            color: var(--muted);
            margin-top: auto;
            padding-top: 6px
        }

        .ac-foot {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px 13px
        }

        .btn-dl {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px;
            border-radius: 8px;
            background: var(--pr);
            color: #fff;
            border: none;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s
        }

        .btn-dl:hover {
            background: #d42485
        }

        .btn-dl.outline {
            background: none;
            border: 1px solid var(--bdr2);
            color: var(--muted)
        }

        .btn-dl.outline:hover {
            border-color: var(--pr);
            color: #e88fc0
        }

        .btn-rm {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--bdr);
            background: none;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            transition: all .18s;
            flex-shrink: 0
        }

        .btn-rm:hover {
            border-color: #ef4444;
            color: #ef4444
        }

        /* Category badge colors */
        .cb-3d_model {
            background: rgba(99, 102, 241, .7);
            color: #c7d2fe
        }

        .cb-texture {
            background: rgba(234, 88, 12, .7);
            color: #fed7aa
        }

        .cb-music {
            background: rgba(16, 185, 129, .7);
            color: #a7f3d0
        }

        .cb-sfx {
            background: rgba(20, 184, 166, .7);
            color: #99f6e4
        }

        .cb-sprite {
            background: rgba(245, 158, 11, .7);
            color: #fde68a
        }

        .cb-shader {
            background: rgba(139, 92, 246, .7);
            color: #ddd6fe
        }

        .cb-font {
            background: rgba(236, 72, 153, .7);
            color: #fbcfe8
        }

        .cb-script {
            background: rgba(59, 130, 246, .7);
            color: #bfdbfe
        }

        .cb-ui_kit {
            background: rgba(16, 185, 129, .7);
            color: #a7f3d0
        }

        .cb-animation {
            background: rgba(239, 68, 68, .7);
            color: #fecaca
        }

        .cb-vfx {
            background: rgba(195, 33, 120, .7);
            color: #f5b8da
        }

        .cb-video {
            background: rgba(107, 114, 128, .7);
            color: #e5e7eb
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surf2);
            border: 1px solid var(--bdr2);
            border-radius: var(--r);
            padding: 0 14px;
            flex: 1;
            max-width: 360px;
            transition: border-color .18s
        }

        .search-bar:focus-within {
            border-color: var(--pr)
        }

        .search-bar input {
            background: none;
            border: none;
            outline: none;
            padding: 10px 0;
            font-size: .86rem;
            color: var(--txt);
            font-family: inherit;
            flex: 1
        }

        .search-bar input::placeholder {
            color: var(--muted)
        }

        .count-badge {
            font-size: .85rem;
            color: var(--muted)
        }

        .count-badge strong {
            color: var(--txt)
        }

        /* Owned badge */
        .owned-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 232, 135, .2);
            border: 1px solid rgba(0, 232, 135, .35);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: .65rem;
            font-weight: 700;
            color: var(--success);
            backdrop-filter: blur(6px)
        }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 80px 20px;
            color: var(--muted)
        }

        .empty-ico {
            font-size: 3rem;
            opacity: .3;
            margin-bottom: 16px
        }

        .empty h3 {
            color: var(--txt);
            margin-bottom: 8px;
            font-family: 'Syne', sans-serif
        }

        .empty a {
            color: #e88fc0;
            text-decoration: none
        }

        .empty a:hover {
            text-decoration: underline
        }

        @media(max-width:640px) {
            .ag {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px
            }
        }

        @media(max-width:380px) {
            .ag {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <?php require_once('../swad/static/elements/header.php'); ?>

    <main>
        <div class="page-hero">
            <div class="container">
                <div class="hero-eyebrow"><?= $page === 'library' ? '📦 Библиотека' : '♡ Желания' ?></div>
                <h1><?= $page === 'library' ? 'Моя библиотека' : 'Список желаний' ?></h1>
                <p><?= $page === 'library' ? 'Все ассеты которые вы получили или купили' : 'Ассеты которые вы хотите купить позже' ?></p>

                <nav class="tab-nav">
                    <a href="?tab=library" class="<?= $page === 'library' ? 'active' : '' ?>">
                        📦 Библиотека <span class="cnt"><?= count($library) ?></span>
                    </a>
                    <a href="?tab=wishlist" class="<?= $page === 'wishlist' ? 'active' : '' ?>">
                        ♡ Список желаний <span class="cnt"><?= count($wishlist) ?></span>
                    </a>
                </nav>
            </div>
        </div>

        <div class="page-body">
            <div class="container">

                <?php $items = $page === 'library' ? $library : $wishlist; ?>

                <div class="toolbar">
                    <div class="count-badge">
                        Итого: <strong><?= count($items) ?></strong> <?= $page === 'library' ? 'ассетов' : 'в списке' ?>
                    </div>
                    <div class="search-bar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                        <input type="text" id="filterInput" placeholder="Поиск...">
                    </div>
                </div>

                <?php if (empty($items)): ?>
                    <div class="empty">
                        <div class="empty-ico"><?= $page === 'library' ? '📦' : '♡' ?></div>
                        <h3><?= $page === 'library' ? 'Библиотека пуста' : 'Список желаний пуст' ?></h3>
                        <p><?= $page === 'library'
                                ? 'Получите бесплатные ассеты или купите платные — они появятся здесь.'
                                : 'Добавляйте ассеты в вишлист кнопкой ♡ на странице ассета.'
                            ?><br><br><a href="/assetstore/">← В магазин ассетов</a></p>
                    </div>
                <?php else: ?>
                    <div class="ag" id="grid">
                        <?php foreach ($items as $a):
                            $cat    = $CATS[$a['category']] ?? ['label' => ucfirst($a['category']), 'emoji' => '📦'];
                            $isFree = ($a['price'] == 0);
                            $priceL = $isFree ? 'Бесплатно' : number_format($a['price'], 0, ',', ' ') . ' ₽';
                            $cover  = !empty($a['path_to_cover']) ? htmlspecialchars($a['path_to_cover'])
                                : 'https://placehold.co/480x270/160028/c32178?text=' . urlencode($cat['label']);
                            $isOwned = ($page === 'library') || !empty($a['is_owned']);
                            $dateLabel = $page === 'library'
                                ? 'Получен: ' . date('d.m.Y', strtotime($a['obtained_date'] ?? 'now'))
                                : 'Добавлен: ' . date('d.m.Y', strtotime($a['added_at'] ?? 'now'));
                        ?>
                            <div class="ac" data-name="<?= htmlspecialchars(strtolower($a['name'])) ?>">
                                <a href="/assetstore/asset.php?id=<?= $a['id'] ?>" style="text-decoration:none;color:inherit">
                                    <div class="ac-thumb">
                                        <img src="<?= $cover ?>" alt="<?= htmlspecialchars($a['name']) ?>" loading="lazy">
                                        <div class="ac-cat cb-<?= $a['category'] ?>"><?= $cat['emoji'] ?> <?= $cat['label'] ?></div>
                                        <div class="ac-price <?= $isFree ? 'free' : 'paid' ?>"><?= $priceL ?></div>
                                        <?php if ($isOwned && $page === 'wishlist'): ?><div class="owned-badge">✓ Получен</div><?php endif; ?>
                                    </div>
                                    <div class="ac-body">
                                        <div class="ac-name"><?= htmlspecialchars($a['name']) ?></div>
                                        <div class="ac-author">by <?= htmlspecialchars($a['studio_name']) ?></div>
                                        <div class="ac-date"><?= $dateLabel ?></div>
                                    </div>
                                </a>
                                <div class="ac-foot">
                                    <?php if ($page === 'library'): ?>
                                        <a href="/swad/controllers/download_asset.php?id=<?= $a['id'] ?>" class="btn-dl">
                                            ↓ Скачать
                                        </a>
                                    <?php else: ?>
                                        <?php if ($isOwned): ?>
                                            <a href="/swad/controllers/download_asset.php?id=<?= $a['id'] ?>" class="btn-dl">↓ Скачать</a>
                                        <?php elseif ($isFree): ?>
                                            <button class="btn-dl" onclick="getAssetFree(<?= $a['id'] ?>)">🎁 Получить</button>
                                        <?php else: ?>
                                            <a href="/assetstore/asset.php?id=<?= $a['id'] ?>" class="btn-dl">🛒 <?= $priceL ?></a>
                                        <?php endif; ?>
                                        <button class="btn-rm" onclick="removeWishlist(<?= $a['id'] ?>, this)" title="Удалить из вишлиста">✕</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php require_once('../swad/static/elements/footer.php'); ?>
    <script>
        /* ── Filter ── */
        document.getElementById('filterInput')?.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#grid .ac').forEach(c => {
                c.style.display = (!q || (c.dataset.name || '').includes(q)) ? '' : 'none';
            });
        });

        /* ── Wishlist remove ── */
        function removeWishlist(id, btn) {
            fetch('../swad/controllers/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `asset_id=${id}`
            }).then(r => r.json()).then(d => {
                if (d.success) btn.closest('.ac').remove();
            });
        }

        /* ── Get free ── */
        function getAssetFree(id) {
            fetch('../swad/controllers/get_asset_free.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `asset_id=${id}`
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else alert('Ошибка: ' + (d.error || ''));
            });
        }
    </script>
</body>

</html>