<?php
/**
 * assetstore/bundles.php — витрина наборов.
 */

declare(strict_types=1);
session_start();
require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/_bundles.php');

$db  = new Database();
$pdo = $db->connect();

$bundles = asset_bundles_published($pdo, 48);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Наборы ассетов — Dustore</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:where(:root){
  --p:#c32178; --dark:#0d0118; --surf:rgba(255,255,255,.04); --surf2:rgba(255,255,255,.075);
  --bdr:rgba(255,255,255,.09); --bdr2:rgba(255,255,255,.18);
  --txt:#f0e6ff; --muted:rgba(240,230,255,.45); --ok:#00e887; --pix:6px;
}
:where(*){box-sizing:border-box}
body{background:var(--dark);color:var(--txt);font-family:Inter,sans-serif;margin:0}
.pix{clip-path:polygon(var(--pix) 0,100% 0,100% calc(100% - var(--pix)),calc(100% - var(--pix)) 100%,0 100%,0 var(--pix))}
.wrap{max-width:1200px;margin:0 auto;padding:28px 20px 80px}
h1{font-family:Syne,sans-serif;font-size:1.9rem;margin:0 0 4px}
.sub{color:var(--muted);font-size:.85rem;margin:0 0 24px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.card{background:var(--surf);border:1px solid var(--bdr);text-decoration:none;color:var(--txt);display:block;overflow:hidden;transition:border-color .15s}
.card:hover{border-color:var(--p)}
.thumb{height:120px;background:linear-gradient(135deg,#1c0b2a,#2a0f3d);display:flex;align-items:center;justify-content:center;font-size:2.4rem}
.body{padding:14px}
.name{font-weight:700;margin-bottom:4px}
.by{color:var(--muted);font-size:.78rem;margin-bottom:10px}
.foot{display:flex;justify-content:space-between;align-items:center;font-size:.84rem}
.price{font-family:'JetBrains Mono',monospace;font-weight:700}
.count{color:var(--muted);font-size:.76rem}
.empty{text-align:center;padding:70px 20px;color:var(--muted)}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../swad/static/elements/header.php'; ?>
<div class="wrap">
  <h1>📦 Наборы ассетов</h1>
  <p class="sub">Несколько ассетов одной студии по общей цене.</p>

  <?php if (!$bundles): ?>
    <div class="empty">Пока нет опубликованных наборов.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($bundles as $b): ?>
        <a class="card pix" href="/assetstore/bundle.php?id=<?= (int)$b['id'] ?>">
          <div class="thumb">📦</div>
          <div class="body">
            <div class="name"><?= h($b['name']) ?></div>
            <div class="by">от <?= h($b['studio_display'] ?: $b['studio_name']) ?> · <?= (int)$b['items_count'] ?> ассет(ов)</div>
            <div class="foot">
              <span class="price"><?= $b['price'] > 0 ? number_format((float)$b['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>
</body>
</html>
