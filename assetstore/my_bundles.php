<?php
/**
 * assetstore/my_bundles.php
 * ---------------------------------------------------------------------------
 * Список наборов текущего разработчика + форма создания/правки. Набор
 * собирается только из СВОИХ уже опубликованных ассетов — своей модерации
 * у наборов нет (см. assetstore/_bundles.php).
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_acl.php';
require_once __DIR__ . '/_bundles.php';

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login?backUrl=/assetstore/my_bundles.php');
    exit;
}

$db  = new Database();
$pdo = $db->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ctx = acl_ctx($pdo);
if (empty($ctx['studios'])) {
    header('Location: /devs/create-studio');
    exit;
}

$studioId = (int)($_GET['studio_id'] ?? $ctx['studio_ids'][0]);
if (!in_array($studioId, $ctx['studio_ids'], true)) $studioId = $ctx['studio_ids'][0];

$error = null;
$success = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bundleId = (int)($_POST['bundle_id'] ?? 0) ?: null;
        // Правим только СВОЙ набор — save() уже фильтрует по studio_id в
        // WHERE, но здесь дополнительно проверяем, чтобы не создать новый
        // с чужим bundle_id по ошибке маршрутизации формы.
        if ($bundleId) {
            $own = asset_bundle_by_id($pdo, $bundleId);
            if (!$own || (int)$own['studio_id'] !== $studioId) throw new Exception('Нет доступа к этому набору');
        }
        $name   = trim((string)($_POST['name'] ?? ''));
        $desc   = trim((string)($_POST['description'] ?? ''));
        $price  = max(0, (float)($_POST['price'] ?? 0));
        $status = (string)($_POST['status'] ?? 'draft');
        $assetIds = array_map('intval', (array)($_POST['asset_ids'] ?? []));

        if ($name === '') throw new Exception('Укажите название набора');
        if (!$assetIds) throw new Exception('Выберите хотя бы один ассет');

        $bundleId = asset_bundle_save($pdo, $studioId, $bundleId, $name, $desc, $price, $status, $assetIds);
        $success = $bundleId;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!empty($_GET['edit'])) {
    $editing = asset_bundle_by_id($pdo, (int)$_GET['edit']);
    if (!$editing || (int)$editing['studio_id'] !== $studioId) $editing = null;
}

$bundles = asset_bundles_for_studio($pdo, $studioId);
$myAssets = $pdo->prepare("SELECT id, name, price, path_to_cover FROM assets WHERE studio_id = ? AND status = 'published' ORDER BY name");
$myAssets->execute([$studioId]);
$myAssets = $myAssets->fetchAll(PDO::FETCH_ASSOC);

$editItems = $editing ? array_column(asset_bundle_items($pdo, (int)$editing['id']), 'id') : [];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Мои наборы — Dustore</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:where(:root){
  --p:#c32178; --p-d:#74155d; --dark:#0d0118;
  --surf:rgba(255,255,255,.04); --surf2:rgba(255,255,255,.075);
  --bdr:rgba(255,255,255,.09); --bdr2:rgba(255,255,255,.18);
  --txt:#f0e6ff; --muted:rgba(240,230,255,.45);
  --ok:#00e887; --warn:#f59e0b; --err:#f44336; --pix:6px;
}
:where(*){box-sizing:border-box}
body{background:var(--dark);color:var(--txt);font-family:Inter,sans-serif;margin:0}
.pix{clip-path:polygon(var(--pix) 0,100% 0,100% calc(100% - var(--pix)),calc(100% - var(--pix)) 100%,0 100%,0 var(--pix))}
.wrap{max-width:960px;margin:0 auto;padding:28px 20px 80px}
h1{font-family:Syne,sans-serif;font-size:1.9rem;margin:0 0 4px}
.sub{color:var(--muted);font-size:.85rem;margin:0 0 20px}
.card{background:var(--surf);border:1px solid var(--bdr);padding:18px;margin-bottom:18px}
.card h2{font-family:Syne,sans-serif;font-size:1.1rem;margin:0 0 14px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border:1px solid var(--bdr2);
  background:var(--surf2);color:var(--txt);font:600 .84rem Inter,sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{border-color:var(--p)}
.btn.primary{background:var(--p);border-color:var(--p)}
.fld{margin-bottom:13px}
.fld label{display:block;font-size:.78rem;color:var(--muted);margin-bottom:5px}
.fld input,.fld textarea,.fld select{width:100%;background:var(--surf);border:1px solid var(--bdr);
  color:var(--txt);padding:9px 11px;font:400 .86rem Inter,sans-serif;outline:none}
.fld input:focus,.fld textarea:focus{border-color:var(--p)}
.asset-pick{border:1px solid var(--bdr);max-height:260px;overflow:auto;padding:6px}
.asset-pick label{display:flex;align-items:center;gap:10px;padding:7px 6px;font-size:.85rem;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05)}
.asset-pick label:last-child{border-bottom:none}
.asset-pick img{width:32px;height:32px;object-fit:cover;background:#1c0b2a;flex-shrink:0}
table.grid{width:100%;border-collapse:collapse;font-size:.86rem}
.grid th{text-align:left;padding:8px 10px;color:var(--muted);font-size:.7rem;text-transform:uppercase;border-bottom:1px solid var(--bdr)}
.grid td{padding:10px;border-bottom:1px solid rgba(255,255,255,.05)}
.pill{display:inline-flex;padding:2px 8px;font-size:.7rem;font-weight:700;border:1px solid currentColor}
.alert{padding:10px 14px;margin-bottom:14px;font-size:.85rem}
.alert-ok{background:rgba(0,232,135,.08);border:1px solid rgba(0,232,135,.3);color:var(--ok)}
.alert-err{background:rgba(244,67,54,.08);border:1px solid rgba(244,67,54,.3);color:var(--err)}
.studio-switch{margin-bottom:16px}
</style>
</head>
<body>
<div class="wrap">
  <h1>📦 Мои наборы</h1>
  <p class="sub">Собирайте несколько своих ассетов в один набор со скидочной ценой.</p>

  <?php if (count($ctx['studios']) > 1): ?>
    <form class="studio-switch" method="get">
      <select name="studio_id" onchange="this.form.submit()" class="pix" style="background:var(--surf);border:1px solid var(--bdr);color:var(--txt);padding:8px 12px">
        <?php foreach ($ctx['studios'] as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $studioId === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['display_name'] ?: $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>

  <?php if ($error): ?><div class="alert alert-err">⚠ <?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-ok">✓ Набор сохранён</div><?php endif; ?>

  <?php if (!$myAssets): ?>
    <div class="card">У этой студии пока нет опубликованных ассетов — наборы собираются только из них. <a href="/assetstore/upload_asset.php" style="color:var(--p)">Загрузить ассет →</a></div>
  <?php else: ?>
  <div class="card">
    <h2><?= $editing ? 'Правка набора' : 'Новый набор' ?></h2>
    <form method="post">
      <?php if ($editing): ?><input type="hidden" name="bundle_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
      <div class="fld"><label>Название</label>
        <input type="text" name="name" value="<?= h($editing['name'] ?? '') ?>" maxlength="128" required></div>
      <div class="fld"><label>Описание</label>
        <textarea name="description" rows="3"><?= h($editing['description'] ?? '') ?></textarea></div>
      <div class="fld"><label>Цена набора, ₽</label>
        <input type="number" name="price" min="0" step="10" value="<?= h($editing['price'] ?? 0) ?>"></div>
      <div class="fld"><label>Статус</label>
        <select name="status">
          <option value="draft" <?= ($editing['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик (не виден покупателям)</option>
          <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликован</option>
        </select>
      </div>
      <div class="fld"><label>Ассеты в наборе</label>
        <div class="asset-pick">
          <?php foreach ($myAssets as $a): ?>
            <label>
              <input type="checkbox" name="asset_ids[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $editItems, true) ? 'checked' : '' ?>>
              <img src="<?= $a['path_to_cover'] ? h($a['path_to_cover']) : 'https://placehold.co/32x32/160028/c32178?text=%20' ?>" alt="">
              <span style="flex:1"><?= h($a['name']) ?></span>
              <span class="mono" style="color:var(--muted)"><?= $a['price'] > 0 ? number_format((float)$a['price'], 0, ',', ' ') . ' ₽' : 'Free' ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn primary pix"><?= $editing ? 'Сохранить' : 'Создать набор' ?></button>
      <?php if ($editing): ?><a href="/assetstore/my_bundles.php?studio_id=<?= $studioId ?>" class="btn ghost pix">Отмена</a><?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($bundles): ?>
  <div class="card">
    <h2>Существующие наборы</h2>
    <table class="grid">
      <thead><tr><th>Название</th><th>Цена</th><th>Статус</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($bundles as $b):
          $itemsCount = count(asset_bundle_items($pdo, (int)$b['id']));
      ?>
        <tr>
          <td><a href="/assetstore/bundle.php?id=<?= (int)$b['id'] ?>" style="color:var(--txt);text-decoration:none"><?= h($b['name']) ?></a>
              <div style="color:var(--muted);font-size:.76rem"><?= $itemsCount ?> ассет(ов)</div></td>
          <td class="mono"><?= number_format((float)$b['price'], 0, ',', ' ') ?> ₽</td>
          <td><span class="pill" style="color:<?= $b['status'] === 'published' ? 'var(--ok)' : 'var(--muted)' ?>"><?= $b['status'] === 'published' ? 'Опубликован' : 'Черновик' ?></span></td>
          <td><a class="btn ghost pix" href="?studio_id=<?= $studioId ?>&edit=<?= (int)$b['id'] ?>">Править</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <a href="/assetstore/my_assets.php" class="btn ghost pix">← К моим ассетам</a>
</div>
</body>
</html>
