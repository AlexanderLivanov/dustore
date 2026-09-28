<?php
/**
 * assetstore/bundle.php — витринная страница набора + покупка целиком.
 */

declare(strict_types=1);
session_start();
require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/csrf.php');
require_once(__DIR__ . '/_bundles.php');

$db  = new Database();
$pdo = $db->connect();

$bundleId = (int)($_GET['id'] ?? 0);
$bundle = $bundleId ? asset_bundle_by_id($pdo, $bundleId) : null;
if (!$bundle || $bundle['status'] !== 'published') {
    header('Location: /assetstore/bundles.php');
    exit;
}

$items = asset_bundle_items($pdo, $bundleId);
$itemsSum = asset_bundle_items_sum($pdo, $bundleId);
$price = (float)$bundle['price'];
$savings = max(0, $itemsSum - $price);

$userId = (int)($_SESSION['USERDATA']['id'] ?? 0);
$isOwned = $userId ? asset_bundle_is_owned($pdo, $userId, $bundleId) : false;

// Что из набора уже есть в библиотеке (частично куплено по отдельности) —
// не блокирует покупку набора целиком, но честно показать стоит.
$ownedIds = [];
if ($userId && $items) {
    $ids = array_map(fn($a) => (int)$a['id'], $items);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT asset_id FROM asset_library WHERE player_id = ? AND asset_id IN ($in)");
    $st->execute([$userId, ...$ids]);
    $ownedIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$CSRF = csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($bundle['name']) ?> — набор ассетов — Dustore</title>
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
.wrap{max-width:900px;margin:0 auto;padding:28px 20px 80px;display:grid;grid-template-columns:1fr 300px;gap:24px}
@media(max-width:760px){.wrap{grid-template-columns:1fr}}
h1{font-family:Syne,sans-serif;font-size:1.9rem;margin:0 0 6px}
.by{color:var(--muted);font-size:.85rem;margin-bottom:20px}
.desc{color:var(--txt);opacity:.85;line-height:1.6;margin-bottom:24px;white-space:pre-wrap}
.items{display:flex;flex-direction:column;gap:10px}
.item{display:flex;align-items:center;gap:12px;background:var(--surf);border:1px solid var(--bdr);padding:10px}
.item img{width:52px;height:52px;object-fit:cover;background:#1c0b2a;flex-shrink:0}
.item .name{font-weight:600}
.item .price{margin-left:auto;font-family:'JetBrains Mono',monospace;color:var(--muted)}
.owned-tag{font-size:.7rem;color:var(--ok);border:1px solid rgba(0,232,135,.4);padding:1px 6px}
.buy-card{background:var(--surf);border:1px solid var(--bdr2);padding:20px;align-self:start}
.buy-price{font-family:Syne,sans-serif;font-size:2rem;font-weight:800}
.buy-old{color:var(--muted);text-decoration:line-through;font-size:1rem}
.savings{color:var(--ok);font-size:.82rem;margin:6px 0 16px}
.btn{display:inline-flex;width:100%;justify-content:center;align-items:center;gap:6px;padding:12px 16px;border:1px solid var(--bdr2);
  background:var(--p);border-color:var(--p);color:#fff;font:700 .92rem Inter,sans-serif;cursor:pointer;text-decoration:none;margin-bottom:8px}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn.ghost{background:transparent;color:var(--txt)}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../swad/static/elements/header.php'; ?>
<div class="wrap">
  <div>
    <h1>📦 <?= h($bundle['name']) ?></h1>
    <div class="by">Набор от <?= h($bundle['studio_display'] ?: $bundle['studio_name']) ?> · <?= count($items) ?> ассет(ов)</div>
    <?php if ($bundle['description']): ?><div class="desc"><?= h($bundle['description']) ?></div><?php endif; ?>
    <div class="items">
      <?php foreach ($items as $a): ?>
        <a href="/assetstore/asset.php?id=<?= (int)$a['id'] ?>" class="item" style="text-decoration:none;color:inherit">
          <img src="<?= $a['path_to_cover'] ? h($a['path_to_cover']) : 'https://placehold.co/52x52/160028/c32178?text=%20' ?>" alt="">
          <span class="name"><?= h($a['name']) ?></span>
          <?php if (in_array((int)$a['id'], $ownedIds, true)): ?><span class="owned-tag">уже есть</span><?php endif; ?>
          <span class="price"><?= $a['price'] > 0 ? number_format((float)$a['price'], 0, ',', ' ') . ' ₽' : 'Free' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <aside class="buy-card pix">
    <?php if ($savings > 0): ?><span class="buy-old"><?= number_format($itemsSum, 0, ',', ' ') ?> ₽</span><?php endif; ?>
    <div class="buy-price"><?= $price > 0 ? number_format($price, 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></div>
    <?php if ($savings > 0): ?><div class="savings">Экономия <?= number_format($savings, 0, ',', ' ') ?> ₽ по сравнению с покупкой по отдельности</div><?php endif; ?>

    <?php if (!$userId): ?>
      <a href="/login" class="btn pix">🔑 Войти и купить</a>
    <?php elseif ($isOwned): ?>
      <button class="btn pix" disabled>✅ Набор уже куплен</button>
    <?php else: ?>
      <button class="btn pix" id="buyBtn" onclick="buyBundle()">🛒 Купить набор</button>
    <?php endif; ?>
    <a href="/assetstore/bundles.php" class="btn ghost pix">← Все наборы</a>
  </aside>
</div>

<?php require_once __DIR__ . '/../swad/static/elements/footer.php'; ?>
<script>
const CSRF = <?= json_encode($CSRF) ?>;
function buyBundle() {
    const btn = document.getElementById('buyBtn');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.textContent = 'Обработка…';
    fetch('/swad/controllers/buy_bundle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `bundle_id=<?= (int)$bundleId ?>&csrf=${encodeURIComponent(CSRF)}`
    }).then(r => r.json()).then(d => {
        if (d.success) {
            if (d.payment_url) window.location.href = d.payment_url;
            else location.reload();
        } else {
            alert('Ошибка: ' + (d.error || 'Неизвестная ошибка'));
            btn.disabled = false;
            btn.textContent = '🛒 Купить набор';
        }
    }).catch(() => { btn.disabled = false; btn.textContent = 'Повторить'; });
}
</script>
</body>
</html>
