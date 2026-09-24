<?php
session_start();
require_once __DIR__ . '/../../swad/config.php';
require_once __DIR__ . '/../../swad/controllers/game_changelog.php';

$db  = new Database();
$pdo = $db->connect();

// Эта панель редактирует «сырые» данные ЛЮБОЙ студии — в отличие от
// expert/admin/moderation.php (там любой approved-эксперт голосует по GQI),
// сюда пускаем только админов. Экспертная оценка — отдельная система,
// её не трогаем.
$isAdmin = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
if (!$isAdmin) { die('Доступ запрещён'); }

const AGE_RATINGS  = ['0+', '6+', '12+', '16+', '18+'];
const STATUSES     = ['draft', 'published'];
const MOD_STATUSES = ['draft', 'pending', 'approved', 'rejected', 'revision'];
const PLATFORM_ORDER = ['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Web'];

$saved = false;
$error_msg = '';

// ---------------------------------------------------------------------
// Сохранение простых полей (POST). Redirect-after-post, как в devs/edit.php.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $editId = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$editId]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$before) {
        header('Location: /expert/admin/games'); exit();
    }

    $name         = trim($_POST['name'] ?? '');
    $genre        = trim($_POST['genre'] ?? '');
    $short_desc   = trim($_POST['short_description'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $platforms    = implode(',', array_values(array_intersect(PLATFORM_ORDER, $_POST['platform'] ?? [])));
    $release_date = $_POST['release_date'] !== '' ? $_POST['release_date'] : null;
    $game_website = filter_var($_POST['game_website'] ?? '', FILTER_SANITIZE_URL);
    $trailer_url  = filter_var($_POST['trailer_url'] ?? '', FILTER_SANITIZE_URL);
    $languages    = trim($_POST['languages'] ?? '');
    $age_rating   = in_array($_POST['age_rating'] ?? '', AGE_RATINGS, true) ? $_POST['age_rating'] : $before['age_rating'];
    $price        = max(0, (float)($_POST['price'] ?? 0));
    $status       = in_array($_POST['status'] ?? '', STATUSES, true) ? $_POST['status'] : $before['status'];
    $mod_status   = in_array($_POST['moderation_status'] ?? '', MOD_STATUSES, true) ? $_POST['moderation_status'] : $before['moderation_status'];
    $hidden       = isset($_POST['hidden']) ? 1 : 0;

    $pdo->prepare("
        UPDATE games SET
            name              = :name,
            genre             = :genre,
            short_description = :short,
            description       = :desc,
            platforms         = :platforms,
            release_date      = :release,
            game_website      = :website,
            trailer_url       = :trailer,
            languages         = :languages,
            age_rating        = :age,
            price             = :price,
            status            = :status,
            moderation_status = :mstatus,
            hidden            = :hidden,
            updated_at        = NOW()
        WHERE id = :id
    ")->execute([
        'name' => $name, 'genre' => $genre, 'short' => $short_desc, 'desc' => $description,
        'platforms' => $platforms, 'release' => $release_date, 'website' => $game_website,
        'trailer' => $trailer_url, 'languages' => $languages, 'age' => $age_rating,
        'price' => $price, 'status' => $status, 'mstatus' => $mod_status, 'hidden' => $hidden,
        'id' => $editId,
    ]);

    // Тот же журнал изменений, что и у студийного редактора — правки
    // админа видно там же, откуда студия видит свои собственные.
    $logUser = (int)($_SESSION['USERDATA']['id'] ?? 0);
    log_game_diff($pdo, $editId, (int)$before['developer'], $logUser, [
        'name'              => [$before['name'] ?? '', $name],
        'genre'             => [$before['genre'] ?? '', $genre],
        'short_description' => [$before['short_description'] ?? '', $short_desc],
        'description'       => [$before['description'] ?? '', $description],
        'platforms'         => [$before['platforms'] ?? '', $platforms],
        'release_date'      => [$before['release_date'] ?? '', $release_date],
        'game_website'      => [$before['game_website'] ?? '', $game_website],
        'trailer_url'       => [$before['trailer_url'] ?? '', $trailer_url],
        'languages'         => [$before['languages'] ?? '', $languages],
        'age_rating'        => [$before['age_rating'] ?? '', $age_rating],
        'price'             => [$before['price'] ?? '', $price],
        'status'            => [$before['status'] ?? '', $status],
        'moderation_status' => [$before['moderation_status'] ?? '', $mod_status],
        'hidden'            => [$before['hidden'] ?? '', $hidden],
    ]);

    header('Location: /expert/admin/games?id=' . $editId . '&saved=1'); exit();
}

// ---------------------------------------------------------------------
// Список слева: поиск + фильтры.
// ---------------------------------------------------------------------
$q       = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fMod    = $_GET['mstatus'] ?? '';
$fPlat   = $_GET['platform'] ?? '';

$sortMap = [
    'updated' => 'g.updated_at DESC',
    'new'     => 'g.created_at DESC',
    'name'    => 'g.name ASC',
];
$sort    = $sortMap[$_GET['sort'] ?? 'updated'] ?? $sortMap['updated'];

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(g.name LIKE :q OR g.studio_name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($fStatus !== '' && in_array($fStatus, STATUSES, true)) {
    $where[] = 'g.status = :status';
    $params['status'] = $fStatus;
}
if ($fMod !== '' && in_array($fMod, MOD_STATUSES, true)) {
    $where[] = 'g.moderation_status = :mstatus';
    $params['mstatus'] = $fMod;
}
if ($fPlat !== '' && in_array($fPlat, PLATFORM_ORDER, true)) {
    $where[] = 'FIND_IN_SET(:platform, g.platforms)';
    $params['platform'] = $fPlat;
}

$sql = "
    SELECT g.id, g.name, g.developer, g.path_to_cover, g.status, g.moderation_status,
           g.hidden, g.price, g.updated_at
      FROM games g
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $sort
     LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalGames = (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

// ---------------------------------------------------------------------
// Выбранная игра справа — полная запись для превью и формы.
// ---------------------------------------------------------------------
$selectedId = (int)($_GET['id'] ?? 0);
$selected   = null;
if ($selectedId) {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$selectedId]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$selPlatforms = $selected ? array_map('trim', explode(',', (string)$selected['platforms'])) : [];

$modLabels = [
    'draft'    => 'Черновик',
    'pending'  => 'На проверке',
    'approved' => 'Одобрено',
    'rejected' => 'Отклонено',
    'revision' => 'На доработке',
];
$modColors = [
    'draft'    => '#6b7a99',
    'pending'  => '#fbbf24',
    'approved' => '#4ade80',
    'rejected' => '#f87171',
    'revision' => '#fb923c',
];

$active_page = 'games';
$uname = $_SESSION['USERDATA']['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все игры — Dustore Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0b0e13;--surface:#131720;--surface2:#1a2030;--border:#232b3a;--accent:#4ade80;--accent2:#22d3ee;--text:#e8edf5;--muted:#6b7a99;--danger:#f87171;--warning:#fbbf24;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
        main{flex:1;overflow:hidden;display:flex;flex-direction:column;height:100vh;}
        .ga-header{padding:22px 28px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
        .eyebrow{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--accent);margin-bottom:6px;}
        .ga-header h1{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;letter-spacing:-.5px;}
        .ga-header p{color:var(--muted);font-size:.85rem;margin-top:4px;}

        .ga-body{flex:1;display:flex;overflow:hidden;}

        /* ---- левая колонка: поиск/фильтры/список ---- */
        .ga-left{width:380px;flex-shrink:0;border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
        .ga-filters{padding:16px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px;flex-shrink:0;}
        .ga-filters input[type=text]{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:9px;padding:10px 12px;color:var(--text);font-size:.88rem;font-family:inherit;}
        .ga-filters input[type=text]:focus{outline:none;border-color:var(--accent);}
        .ga-filter-row{display:flex;gap:8px;}
        .ga-filters select{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:9px;padding:9px 8px;color:var(--text);font-size:.8rem;font-family:inherit;min-width:0;}
        .ga-filters button{background:var(--accent);color:#0b0e13;border:0;border-radius:9px;padding:9px 16px;font-weight:700;font-size:.85rem;cursor:pointer;font-family:inherit;}
        .ga-filters button:hover{opacity:.88;}
        .ga-count{font-size:.75rem;color:var(--muted);padding:0 2px;}

        .ga-list{flex:1;overflow-y:auto;}
        .ga-row{display:flex;align-items:center;gap:12px;padding:12px 16px;text-decoration:none;color:var(--text);border-bottom:1px solid var(--border);transition:.12s;}
        .ga-row:hover{background:var(--surface2);}
        .ga-row.active{background:rgba(74,222,128,.1);border-left:3px solid var(--accent);padding-left:13px;}
        .ga-row-cover{width:52px;height:52px;border-radius:8px;object-fit:cover;background:var(--surface2);flex-shrink:0;}
        .ga-row-info{min-width:0;flex:1;}
        .ga-row-name{font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .ga-row-studio{font-size:.76rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;}
        .ga-row-badges{display:flex;gap:5px;margin-top:5px;flex-wrap:wrap;}
        .ga-badge{font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:20px;white-space:nowrap;}
        .ga-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:.85rem;}

        /* ---- правая колонка: превью + форма ---- */
        .ga-right{flex:1;overflow-y:auto;padding:28px 36px;}
        .ga-placeholder{height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.95rem;text-align:center;}

        .ga-preview{display:flex;gap:20px;margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--border);}
        .ga-preview-cover{width:140px;height:140px;border-radius:14px;object-fit:cover;background:var(--surface2);flex-shrink:0;}
        .ga-preview-info h2{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:6px;}
        .ga-preview-info .ga-studio{color:var(--muted);font-size:.9rem;margin-bottom:10px;}
        .ga-preview-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
        .ga-preview-link{margin-top:12px;display:inline-block;color:var(--accent2);font-size:.85rem;text-decoration:none;}
        .ga-preview-link:hover{text-decoration:underline;}
        .ga-saved{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.35);color:var(--accent);border-radius:10px;padding:10px 16px;font-size:.85rem;margin-bottom:20px;}

        .ga-form-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:14px;color:var(--text);}
        .ga-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
        .ga-field{display:flex;flex-direction:column;gap:6px;}
        .ga-field.full{grid-column:1/-1;}
        .ga-field label{font-size:.75rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);}
        .ga-field input[type=text],.ga-field input[type=url],.ga-field input[type=date],.ga-field input[type=number],.ga-field select,.ga-field textarea{
            background:var(--surface);border:1px solid var(--border);border-radius:9px;padding:10px 12px;color:var(--text);font-size:.88rem;font-family:inherit;width:100%;
        }
        .ga-field textarea{resize:vertical;min-height:70px;}
        .ga-field input:focus,.ga-field select:focus,.ga-field textarea:focus{outline:none;border-color:var(--accent);}
        .ga-platforms{display:flex;flex-wrap:wrap;gap:10px;}
        .ga-plat-chip{display:flex;align-items:center;gap:6px;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:7px 12px 7px 10px;font-size:.82rem;cursor:pointer;}
        .ga-plat-chip input{accent-color:var(--accent);}
        .ga-checkbox-row{display:flex;align-items:center;gap:8px;}
        .ga-checkbox-row input{accent-color:var(--accent);width:16px;height:16px;}
        .ga-save-btn{background:var(--accent);color:#0b0e13;border:0;border-radius:10px;padding:12px 26px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;margin-top:6px;}
        .ga-save-btn:hover{opacity:.9;}
    </style>
</head>
<body>

<?php require __DIR__ . '/_sidebar.php'; ?>

<main>
    <div class="ga-header">
        <div class="eyebrow">Админка</div>
        <h1>Все игры</h1>
        <p>Каталог всех игр платформы (<?= $totalGames ?>) — поиск, фильтры и редактирование параметров.</p>
    </div>

    <div class="ga-body">
        <div class="ga-left">
            <form class="ga-filters" method="get" action="/expert/admin/games">
                <?php if ($selectedId): ?><input type="hidden" name="id" value="<?= $selectedId ?>"><?php endif; ?>
                <input type="text" name="q" placeholder="Поиск по названию или студии…" value="<?= htmlspecialchars($q) ?>">
                <div class="ga-filter-row">
                    <select name="mstatus">
                        <option value="">Модерация: все</option>
                        <?php foreach (MOD_STATUSES as $ms): ?>
                            <option value="<?= $ms ?>"<?= $fMod === $ms ? ' selected' : '' ?>><?= $modLabels[$ms] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Статус: все</option>
                        <option value="draft"<?= $fStatus === 'draft' ? ' selected' : '' ?>>Черновик</option>
                        <option value="published"<?= $fStatus === 'published' ? ' selected' : '' ?>>Опубликовано</option>
                    </select>
                </div>
                <div class="ga-filter-row">
                    <select name="platform">
                        <option value="">Платформа: все</option>
                        <?php foreach (PLATFORM_ORDER as $pl): ?>
                            <option value="<?= $pl ?>"<?= $fPlat === $pl ? ' selected' : '' ?>><?= $pl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="sort">
                        <option value="updated"<?= ($_GET['sort'] ?? 'updated') === 'updated' ? ' selected' : '' ?>>Сначала обновлённые</option>
                        <option value="new"<?= ($_GET['sort'] ?? '') === 'new' ? ' selected' : '' ?>>Сначала новые</option>
                        <option value="name"<?= ($_GET['sort'] ?? '') === 'name' ? ' selected' : '' ?>>По алфавиту</option>
                    </select>
                </div>
                <button type="submit">Применить</button>
                <div class="ga-count"><?= count($games) ?> из <?= $totalGames ?> показано</div>
            </form>

            <div class="ga-list">
                <?php if (!$games): ?>
                    <div class="ga-empty">Ничего не найдено.</div>
                <?php else: foreach ($games as $g): ?>
                    <a class="ga-row <?= $g['id'] == $selectedId ? 'active' : '' ?>"
                       href="?id=<?= $g['id'] ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $fMod !== '' ? '&mstatus=' . $fMod : '' ?><?= $fStatus !== '' ? '&status=' . $fStatus : '' ?><?= $fPlat !== '' ? '&platform=' . urlencode($fPlat) : '' ?>">
                        <img class="ga-row-cover" src="<?= htmlspecialchars($g['path_to_cover'] ?: '') ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
                        <div class="ga-row-info">
                            <div class="ga-row-name"><?= htmlspecialchars($g['name']) ?></div>
                            <div class="ga-row-studio"><?= htmlspecialchars($g['studio_name'] ?? '') ?></div>
                            <div class="ga-row-badges">
                                <span class="ga-badge" style="background:<?= $modColors[$g['moderation_status']] ?? '#6b7a99' ?>22;color:<?= $modColors[$g['moderation_status']] ?? '#6b7a99' ?>;"><?= $modLabels[$g['moderation_status']] ?? $g['moderation_status'] ?></span>
                                <?php if ($g['status'] === 'published'): ?><span class="ga-badge" style="background:#4ade8022;color:#4ade80;">live</span><?php endif; ?>
                                <?php if ($g['hidden']): ?><span class="ga-badge" style="background:#f8717122;color:#f87171;">скрыта</span><?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="ga-right">
            <?php if (!$selected): ?>
                <div class="ga-placeholder">Выберите игру слева, чтобы посмотреть и отредактировать её параметры.</div>
            <?php else: ?>
                <?php if (!empty($_GET['saved'])): ?>
                    <div class="ga-saved">✅ Сохранено.</div>
                <?php endif; ?>

                <div class="ga-preview">
                    <img class="ga-preview-cover" src="<?= htmlspecialchars($selected['path_to_cover'] ?: '') ?>" alt="" onerror="this.style.visibility='hidden'">
                    <div class="ga-preview-info">
                        <h2><?= htmlspecialchars($selected['name']) ?></h2>
                        <div class="ga-studio"><?= htmlspecialchars($selected['studio_name'] ?? '') ?> · ID <?= (int)$selected['id'] ?></div>
                        <div class="ga-preview-meta">
                            <span class="ga-badge" style="background:<?= $modColors[$selected['moderation_status']] ?? '#6b7a99' ?>22;color:<?= $modColors[$selected['moderation_status']] ?? '#6b7a99' ?>;"><?= $modLabels[$selected['moderation_status']] ?? $selected['moderation_status'] ?></span>
                            <span class="ga-badge" style="background:#22d3ee22;color:#22d3ee;"><?= $selected['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?></span>
                            <?php if ($selected['GQI'] !== null): ?><span class="ga-badge" style="background:#8b5cf622;color:#c4b5fd;">GQI <?= (int)$selected['GQI'] ?></span><?php endif; ?>
                        </div>
                        <a class="ga-preview-link" href="/g/<?= (int)$selected['id'] ?>" target="_blank">Открыть страницу игры →</a>
                    </div>
                </div>

                <form method="post" action="/expert/admin/games">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">

                    <div class="ga-form-title">Основное</div>
                    <div class="ga-grid">
                        <div class="ga-field"><label>Название</label><input type="text" name="name" value="<?= htmlspecialchars($selected['name']) ?>"></div>
                        <div class="ga-field"><label>Жанр</label><input type="text" name="genre" value="<?= htmlspecialchars($selected['genre'] ?? '') ?>"></div>
                        <div class="ga-field full"><label>Краткое описание</label><input type="text" name="short_description" value="<?= htmlspecialchars($selected['short_description'] ?? '') ?>"></div>
                        <div class="ga-field full"><label>Описание</label><textarea name="description"><?= htmlspecialchars($selected['description'] ?? '') ?></textarea></div>
                    </div>

                    <div class="ga-form-title">Платформы</div>
                    <div class="ga-platforms" style="margin-bottom:18px;">
                        <?php foreach (PLATFORM_ORDER as $pl): ?>
                            <label class="ga-plat-chip">
                                <input type="checkbox" name="platform[]" value="<?= $pl ?>"<?= in_array($pl, $selPlatforms, true) ? ' checked' : '' ?>>
                                <?= $pl ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="ga-form-title">Данные и ссылки</div>
                    <div class="ga-grid">
                        <div class="ga-field"><label>Дата выхода</label><input type="date" name="release_date" value="<?= htmlspecialchars($selected['release_date'] ?? '') ?>"></div>
                        <div class="ga-field"><label>Языки</label><input type="text" name="languages" value="<?= htmlspecialchars($selected['languages'] ?? '') ?>"></div>
                        <div class="ga-field"><label>Сайт игры</label><input type="url" name="game_website" value="<?= htmlspecialchars($selected['game_website'] ?? '') ?>"></div>
                        <div class="ga-field"><label>Трейлер (URL)</label><input type="url" name="trailer_url" value="<?= htmlspecialchars($selected['trailer_url'] ?? '') ?>"></div>
                        <div class="ga-field">
                            <label>Возрастной рейтинг</label>
                            <select name="age_rating">
                                <?php foreach (AGE_RATINGS as $a): ?>
                                    <option<?= $selected['age_rating'] === $a ? ' selected' : '' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ga-field"><label>Цена (₽)</label><input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string)($selected['price'] ?? 0)) ?>"></div>
                    </div>

                    <div class="ga-form-title">Публикация и модерация</div>
                    <div class="ga-grid">
                        <div class="ga-field">
                            <label>Статус</label>
                            <select name="status">
                                <option value="draft"<?= $selected['status'] === 'draft' ? ' selected' : '' ?>>Черновик</option>
                                <option value="published"<?= $selected['status'] === 'published' ? ' selected' : '' ?>>Опубликовано</option>
                            </select>
                        </div>
                        <div class="ga-field">
                            <label>Статус модерации</label>
                            <select name="moderation_status">
                                <?php foreach (MOD_STATUSES as $ms): ?>
                                    <option value="<?= $ms ?>"<?= $selected['moderation_status'] === $ms ? ' selected' : '' ?>><?= $modLabels[$ms] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ga-field" style="justify-content:center;">
                            <label class="ga-checkbox-row" style="text-transform:none;font-size:.88rem;color:var(--text);font-weight:400;">
                                <input type="checkbox" name="hidden"<?= !empty($selected['hidden']) ? ' checked' : '' ?>>
                                Скрыть из каталога
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="ga-save-btn">Сохранить изменения</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
