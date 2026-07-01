<?php

/**
 * m/views/catalog.php
 * Исправлено: GROUP BY полный, LIMIT увеличен, фильтры работают
 */

$q     = trim($_GET['q']     ?? '');
$genre = trim($_GET['genre'] ?? '');
$sort  = $_GET['sort']        ?? 'new';
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;

$where  = ["g.moderation_status = 'approved'"];
$params = [];

if ($q !== '') {
    $where[]      = "(g.name LIKE :q OR s.name LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if ($genre !== '' && $genre !== 'all') {
    $where[]          = "g.genre = :genre";
    $params[':genre'] = $genre;
}

$order = match ($sort) {
    'rating' => 'avg_rating DESC',
    'price'  => 'g.price ASC',
    'free'   => 'g.price ASC',
    'name'   => 'g.name ASC',
    default  => 'g.created_at DESC',
};

$where_sql = implode(' AND ', $where);

/* Считаем общее количество */
$count_stmt = $db->prepare("
    SELECT COUNT(DISTINCT g.id)
    FROM games g
    JOIN studios s ON s.id = g.developer
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total / $per_page);

/* Основной запрос */
$stmt = $db->prepare("
    SELECT g.id, g.name, g.price, g.genre,
           g.path_to_cover, s.name AS studio_name,
           COALESCE(AVG(r.rating), 0) AS avg_rating
    FROM games g
    JOIN studios s ON s.id = g.developer
    LEFT JOIN game_reviews r ON r.game_id = g.id
    WHERE {$where_sql}
    GROUP BY g.id, g.name, g.price, g.genre, g.path_to_cover, g.created_at, s.name
    ORDER BY {$order}
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$genres = [
    ''           => 'Все',
    'action'     => 'Экшен',
    'rpg'        => 'RPG',
    'indie'      => 'Инди',
    'strategy'   => 'Стратегия',
    'puzzle'     => 'Пазл',
    'horror'     => 'Хоррор',
    'platformer' => 'Платформер',
    'adventure'  => 'Приключения',
    'simulation' => 'Симулятор',
    'sport'      => 'Спорт',
];

function cat_url(array $params_override): string
{
    global $q, $genre, $sort, $page_num;
    $p = array_merge([
        'q'     => $q,
        'genre' => $genre,
        'sort'  => $sort,
    ], $params_override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== 'all');
    return '/m/catalog' . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="cat-header">
    <div class="cat-title">Каталог</div>

    <!-- Search -->
    <form action="/m/catalog" method="get" style="margin-bottom:10px">
        <?php if ($genre): ?><input type="hidden" name="genre" value="<?= htmlspecialchars($genre) ?>"><?php endif; ?>
        <?php if ($sort !== 'new'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
        <div class="search-pill">
            <i class="ti ti-search"></i>
            <input type="search" name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Игры, студии..."
                autocomplete="off">
        </div>
    </form>
</div>

<!-- Genre chips -->
<div class="h-scroll" style="margin-bottom:10px">
    <?php foreach ($genres as $val => $label): ?>
        <a href="<?= cat_url(['genre' => $val, 'p' => 1]) ?>"
            class="chip <?= $genre === $val ? 'active' : '' ?>"
            style="text-decoration:none">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Sort -->
<div class="sort-bar" style="margin-bottom:12px">
    <?php
    $sorts = ['new' => 'Новинки', 'rating' => 'По рейтингу', 'price' => 'По цене', 'free' => 'Бесплатные', 'name' => 'А-Я'];
    foreach ($sorts as $val => $label):
    ?>
        <a href="<?= cat_url(['sort' => $val, 'p' => 1]) ?>"
            class="sort-btn <?= $sort === $val ? 'active' : '' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Count -->
<?php if ($total > 0): ?>
    <div style="padding:0 14px;margin-bottom:10px;font-size:12px;color:var(--txt3)">
        Найдено: <?= $total ?> <?= ($q || $genre) ? 'игр' : 'игр' ?>
        <?php if ($total_pages > 1): ?>
            · Стр. <?= $page_num ?> из <?= $total_pages ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Results -->
<?php if (empty($games)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="ti ti-mood-empty" style="font-size:44px"></i></div>
        <div class="empty-title">Ничего не найдено</div>
        <div class="empty-sub">Попробуй другой жанр или запрос</div>
    </div>
<?php else: ?>
    <div style="padding:0 14px">
        <div class="mini-grid">
            <?php foreach ($games as $g):
                $free = ((float)$g['price'] === 0.0);
            ?>
                <a href="/m/game/<?= (int)$g['id'] ?>" class="mini-card">
                    <div class="mini-cover">
                        <?php if (!empty($g['path_to_cover'])): ?>
                            <img src="<?= htmlspecialchars($g['path_to_cover']) ?>"
                                alt="<?= htmlspecialchars($g['name']) ?>"
                                loading="lazy"
                                onerror="this.style.display='none'">
                        <?php endif; ?>
                    </div>
                    <div class="mini-info">
                        <div class="mini-title"><?= htmlspecialchars($g['name']) ?></div>
                        <div class="mini-studio"><?= htmlspecialchars($g['studio_name']) ?></div>
                        <div class="mini-price <?= $free ? 'free' : '' ?>">
                            <?= $free ? 'Бесплатно' : number_format((float)$g['price'], 0, ',', ' ') . ' ₽' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:center;gap:8px;padding:16px 14px">
        <?php if ($page_num > 1): ?>
            <a href="<?= cat_url(['p' => $page_num - 1]) ?>"
                style="padding:8px 16px;background:var(--surf);border:1px solid var(--bdr);border-radius:9px;font-size:13px;color:var(--txt2);text-decoration:none">
                ← Назад
            </a>
        <?php endif; ?>
        <?php if ($page_num < $total_pages): ?>
            <a href="<?= cat_url(['p' => $page_num + 1]) ?>"
                style="padding:8px 16px;background:var(--primary);border-radius:9px;font-size:13px;color:#fff;text-decoration:none;font-weight:600">
                Далее →
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>