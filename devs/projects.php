<?php
$page_title = 'Мои проекты';
$active_nav = 'projects';
require_once(__DIR__ . '/includes/header.php');
require_once(__DIR__ . '/../swad/controllers/pm.php');

$pm = new ProjectManagment();
$all_projects = $pm->getAllStudioGames($studio_id);

$status_map = [
    'published' => ['badge-pub',   'Опубликован'],
    'pending'   => ['badge-rev',   'На модерации'],
    'draft'     => ['badge-draft', 'Черновик'],
    'rejected'  => ['badge-err',   'Отклонён'],
];
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <div style="font-size:22px;font-weight:700;"><?= count($all_projects) ?> <?= count($all_projects) === 1 ? 'проект' : 'проекта' ?></div>
        <div style="font-size:13px;color:var(--ts);margin-top:2px;">Все ваши проекты на платформе</div>
    </div>
    <a href="/devs/new" class="btn btn-p"><span class="material-icons">add</span>Новый проект</a>
</div>
<?php if (empty($all_projects)): ?>
    <div class="card" style="text-align:center;padding:60px;">
        <span class="material-icons" style="font-size:48px;color:var(--p);display:block;margin-bottom:12px;">videogame_asset</span>
        <div style="font-size:16px;font-weight:600;margin-bottom:8px;">Нет проектов</div>
        <p style="color:var(--ts);margin-bottom:20px;font-size:14px;">Загрузите свою первую игру на платформу Dustore</p>
        <a href="/devs/new" class="btn btn-p"><span class="material-icons">add</span>Создать проект</a>
    </div>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($all_projects as $p):
            [$cls, $lbl] = $status_map[$p['status'] ?? 'draft'] ?? ['badge-draft', 'Черновик'];
            $cover = htmlspecialchars($p['path_to_cover'] ?? '');
            $zip_mb = !empty($p['game_zip_size']) ? round($p['game_zip_size'] / 1048576, 1) . ' МБ' : '—';
        ?>
            <div class="card" style="display:flex;align-items:center;gap:16px;padding:16px;">
                <div style="width:60px;height:60px;border-radius:12px;flex-shrink:0;background:var(--elev)<?= $cover ? ';background-image:url(\'' . $cover . '\');background-size:cover;background-position:center' : '' ?>;">
                    <?php if (!$cover): ?><span class="material-icons" style="font-size:28px;color:var(--tm);margin:16px auto;display:block;text-align:center;">image</span><?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                        <div style="font-size:15px;font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
                        <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--ts);">
                        <?= htmlspecialchars($p['genre'] ?? '') ?>
                        <?php if (!empty($p['platforms'])): ?> · <?= htmlspecialchars($p['platforms']) ?><?php endif; ?>
                            <?php if (!empty($p['release_date'])): ?> · <?= htmlspecialchars($p['release_date']) ?><?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:2px;">
                        ZIP: <?= $zip_mb ?>
                        <?php if (!empty($p['GQI'])): ?> · GQI: <?= $p['GQI'] ?><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <a href="/devs/edit?id=<?= (int)$p['id'] ?>" class="btn btn-g" style="padding:6px 12px;font-size:12px;">
                        <span class="material-icons" style="font-size:15px;">edit</span>Изменить
                    </a>
                    <a href="/g/<?= (int)$p['id'] ?>" target="_blank" class="icon-btn" title="Открыть на Dustore">
                        <span class="material-icons">open_in_new</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once(__DIR__ . '/includes/footer.php'); ?>