<?php
/**
 * assetstore/manage.php
 * ---------------------------------------------------------------------------
 * Админка ассетов. Один экран для двух ролей:
 *   - разработчик видит ассеты своих студий
 *   - staff (role <= 3) видит всё, с явным баннером "режим модератора"
 *
 * Мутаций тут нет вообще — всё через /assetstore/api_admin.php.
 * Поэтому POST-before-header проблема здесь не возникает by design.
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../swad/config.php';
require_once __DIR__ . '/_acl.php';

if (empty($_SESSION['USERDATA']['id'])) {
    header('Location: /login?backUrl=/assetstore/manage.php');
    exit;
}

$db  = new Database();
$pdo = $db->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ctx  = acl_ctx($pdo);
$csrf = acl_csrf_token();

if (!$ctx['is_staff'] && empty($ctx['studios'])) {
    header('Location: /devs/create-studio');
    exit;
}

/* ── Фильтры ─────────────────────────────────────────────────────────────── */
$fStatus = $_GET['status']   ?? 'all';
$fCat    = $_GET['category'] ?? '';
$fQ      = trim((string)($_GET['q'] ?? ''));
$fScope  = $_GET['scope']    ?? ($ctx['is_staff'] ? 'all' : 'mine');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if (!in_array($fStatus, array_merge(['all'], asset_statuses()), true)) $fStatus = 'all';
if (!$ctx['is_staff']) $fScope = 'mine';

/* ── WHERE ───────────────────────────────────────────────────────────────── */
$where  = [];
$params = [];

if ($fScope === 'mine') {
    if (empty($ctx['studio_ids'])) {
        $where[] = '0';
    } else {
        // studio_id ИЛИ легаси studio_name — пока бэкфилл не подтверждён.
        $inId   = implode(',', array_fill(0, count($ctx['studio_ids']), '?'));
        $inName = implode(',', array_fill(0, count($ctx['studio_names']), '?'));
        $where[] = "(a.studio_id IN ($inId) OR a.studio_name IN ($inName))";
        $params  = array_merge($params, $ctx['studio_ids'], $ctx['studio_names']);
    }
}

if ($fStatus === 'all') {
    $where[] = "a.status <> 'deleted'";
} else {
    $where[] = 'a.status = ?';
    $params[] = $fStatus;
}

if ($fCat !== '') { $where[] = 'a.category = ?'; $params[] = $fCat; }
if ($fQ !== '')   { $where[] = '(a.name LIKE ? OR a.tags LIKE ?)'; $params[] = "%$fQ%"; $params[] = "%$fQ%"; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ── Данные ──────────────────────────────────────────────────────────────── */
$cnt = $pdo->prepare("SELECT COUNT(*) FROM assets a $whereSql");
$cnt->execute($params);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT a.*, s.display_name AS studio_display
          FROM assets a
     LEFT JOIN studios s ON s.id = a.studio_id
        $whereSql
      ORDER BY FIELD(a.status,'pending','rejected','draft','hidden','published','deleted'),
               a.created_at DESC
         LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ── Счётчики по статусам (в том же скоупе) ──────────────────────────────── */
$cWhere = $where;
$cParams = $params;
// вырезаем условие по статусу
foreach ($cWhere as $i => $w) {
    if ($w === 'a.status = ?' || $w === "a.status <> 'deleted'") {
        if ($w === 'a.status = ?') {
            $pos = 0;
            foreach (array_slice($cWhere, 0, $i) as $prev) $pos += substr_count($prev, '?');
            array_splice($cParams, $pos, 1);
        }
        unset($cWhere[$i]);
    }
}
$cSql = $cWhere ? ('WHERE ' . implode(' AND ', $cWhere)) : '';
$cst = $pdo->prepare("SELECT status, COUNT(*) c FROM assets a $cSql GROUP BY status");
$cst->execute(array_values($cParams));
$counts = [];
foreach ($cst->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['status']] = (int)$r['c'];
$counts['all'] = array_sum(array_diff_key($counts, ['deleted' => 1]));

$CATS = [
    '3d_model' => '🧊 3D Модель', 'texture' => '🖼️ Текстура', 'music' => '🎵 Музыка',
    'sfx' => '🔊 Звук / SFX', 'sprite' => '🎨 Спрайт / 2D', 'shader' => '✨ Шейдер',
    'font' => '🔤 Шрифт', 'script' => '📜 Скрипт', 'ui_kit' => '🎛️ UI Кит',
    'animation' => '🎬 Анимация', 'vfx' => '💥 VFX / FX', 'video' => '📹 Видео',
];

function qs(array $over = []): string
{
    return '?' . http_build_query(array_merge($_GET, $over));
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmtBytesM($b): string
{
    $b = (float)$b; if ($b <= 0) return '—';
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, $b < 10 ? 1 : 0) . ' ' . $u[$i];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Мои ассеты — Dustore</title>
<link rel="stylesheet" href="../swad/css/pages.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:where(:root){
  --p:#c32178; --p-d:#74155d; --dark:#0d0118;
  --surf:rgba(255,255,255,.04); --surf2:rgba(255,255,255,.075);
  --bdr:rgba(255,255,255,.09); --bdr2:rgba(255,255,255,.18);
  --txt:#f0e6ff; --muted:rgba(240,230,255,.45);
  --ok:#00e887; --warn:#f59e0b; --err:#f44336;
  --pix:6px;
}
:where(*){box-sizing:border-box}
body{background:var(--dark);color:var(--txt);font-family:Inter,sans-serif;margin:0}
.pix{clip-path:polygon(var(--pix) 0,100% 0,100% calc(100% - var(--pix)),calc(100% - var(--pix)) 100%,0 100%,0 var(--pix))}
.wrap{max-width:1240px;margin:0 auto;padding:28px 20px 80px}

.mg-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.mg-head h1{font-family:Syne,sans-serif;font-size:1.9rem;margin:0}
.mg-head p{color:var(--muted);font-size:.85rem;margin:6px 0 0}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border:1px solid var(--bdr2);
  background:var(--surf2);color:var(--txt);font:600 .84rem Inter,sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{border-color:var(--p)}
.btn.primary{background:var(--p);border-color:var(--p)}
.btn.ghost{background:transparent}
.btn.danger{color:#ffb4ae;border-color:rgba(244,67,54,.4)}
.btn:disabled{opacity:.4;cursor:not-allowed}

/* staff banner */
.staff-bar{display:flex;align-items:center;gap:12px;padding:11px 16px;margin-bottom:18px;
  background:repeating-linear-gradient(135deg,rgba(245,158,11,.10) 0 12px,rgba(245,158,11,.04) 12px 24px);
  border:1px solid rgba(245,158,11,.35);font-size:.84rem}
.staff-bar b{color:var(--warn)}

.tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--bdr);margin-bottom:14px}
.tab{padding:9px 14px;color:var(--muted);text-decoration:none;font-size:.84rem;font-weight:600;
  border-bottom:2px solid transparent;margin-bottom:-1px}
.tab:hover{color:var(--txt)}
.tab.on{color:var(--txt);border-bottom-color:var(--p)}
.tab .n{font-family:'JetBrains Mono',monospace;font-size:.72rem;opacity:.6;margin-left:5px}

.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.filters input,.filters select{background:var(--surf);border:1px solid var(--bdr);color:var(--txt);
  padding:8px 11px;font:400 .84rem Inter,sans-serif;outline:none}
.filters input:focus,.filters select:focus{border-color:var(--p)}
.filters select option{background:#1a0a26}

/* bulk */
.bulk{display:none;align-items:center;gap:10px;padding:10px 14px;margin-bottom:12px;
  background:rgba(195,33,120,.10);border:1px solid rgba(195,33,120,.35);font-size:.84rem}
.bulk.on{display:flex}
.bulk .cnt{font-family:'JetBrains Mono',monospace;color:var(--p)}

table.grid{width:100%;border-collapse:collapse;font-size:.86rem}
.grid thead th{text-align:left;padding:9px 10px;color:var(--muted);font-size:.7rem;
  text-transform:uppercase;letter-spacing:.06em;font-weight:600;border-bottom:1px solid var(--bdr)}
.grid tbody tr{border-bottom:1px solid rgba(255,255,255,.05)}
.grid tbody tr:hover{background:rgba(255,255,255,.02)}
.grid td{padding:11px 10px;vertical-align:middle}
.cover{width:44px;height:44px;object-fit:cover;background:#1c0b2a;border:1px solid var(--bdr)}
.a-name{font-weight:600;color:var(--txt);text-decoration:none;display:block}
.a-name:hover{color:var(--p)}
.a-sub{color:var(--muted);font-size:.74rem;margin-top:2px}
.pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;font-size:.72rem;font-weight:600;
  border:1px solid currentColor;border-radius:2px}
.mono{font-family:'JetBrains Mono',monospace;font-size:.8rem}
.foreign{display:inline-block;padding:1px 6px;font-size:.66rem;color:var(--warn);
  border:1px solid rgba(245,158,11,.4);margin-left:6px}
.acts{display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap}
.acts .btn{padding:5px 10px;font-size:.75rem}

.empty{text-align:center;padding:70px 20px;color:var(--muted)}
.pager{display:flex;gap:6px;justify-content:center;margin-top:22px}
.pager a,.pager span{padding:7px 12px;border:1px solid var(--bdr);font-size:.8rem;text-decoration:none;color:var(--muted)}
.pager .on{border-color:var(--p);color:var(--txt)}

/* modal */
.mx{position:fixed;inset:0;background:rgba(3,0,8,.86);backdrop-filter:blur(6px);
  display:none;align-items:center;justify-content:center;z-index:900;padding:20px}
.mx.on{display:flex}
.mx-box{background:#150a20;border:1px solid var(--bdr2);width:100%;max-width:520px;padding:24px;max-height:86vh;overflow:auto}
.mx-box h3{font-family:Syne,sans-serif;margin:0 0 14px}
.fld{margin-bottom:13px}
.fld label{display:block;font-size:.74rem;color:var(--muted);margin-bottom:5px}
.fld input,.fld textarea,.fld select{width:100%;background:var(--surf);border:1px solid var(--bdr);
  color:var(--txt);padding:9px 11px;font:400 .86rem Inter,sans-serif;outline:none}
.fld input:focus,.fld textarea:focus{border-color:var(--p)}
.mx-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:18px}
.logline{border-left:2px solid var(--bdr);padding:6px 0 6px 12px;margin-bottom:8px;font-size:.8rem}
.logline .lt{color:var(--muted);font-size:.7rem;font-family:'JetBrains Mono',monospace}
.toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);background:#1c0b2a;
  border:1px solid var(--bdr2);padding:11px 18px;font-size:.84rem;z-index:1000;opacity:0;
  transition:opacity .2s;pointer-events:none}
.toast.on{opacity:1}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../swad/static/elements/header.php'; ?>

<div class="wrap">

  <div class="mg-head">
    <div>
      <h1>Управление ассетами</h1>
      <p>
        <?php if ($ctx['is_staff']): ?>
          Всего в системе: <?= $total ?>
        <?php else: ?>
          <?= h(implode(', ', array_column($ctx['studios'], 'name'))) ?> · <?= $total ?> шт.
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/assetstore/upload_asset.php" class="btn primary pix">+ Загрузить ассет</a>
      <a href="/assetstore/" class="btn ghost pix">Витрина</a>
    </div>
  </div>

  <?php if ($ctx['is_staff']): ?>
  <div class="staff-bar pix">
    <span style="font-size:1.1rem">⚡</span>
    <div>
      <b>Режим модератора</b> (роль <span class="mono"><?= (int)$ctx['role'] ?></span><?= $ctx['is_root'] ? ', root' : '' ?>).
      Тебе видны и доступны ассеты всех студий. Каждое действие пишется в аудит-лог.
    </div>
    <div style="margin-left:auto;display:flex;gap:6px">
      <a class="btn ghost pix" href="<?= h(qs(['scope' => 'all', 'page' => 1])) ?>"
         style="<?= $fScope === 'all' ? 'border-color:var(--p)' : '' ?>">Все</a>
      <a class="btn ghost pix" href="<?= h(qs(['scope' => 'mine', 'page' => 1])) ?>"
         style="<?= $fScope === 'mine' ? 'border-color:var(--p)' : '' ?>">Только мои</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Табы статусов -->
  <div class="tabs">
    <?php
    $tabs = ['all' => 'Все'];
    foreach (asset_statuses() as $s) $tabs[$s] = asset_status_meta($s)[0];
    foreach ($tabs as $k => $label):
        $n = $counts[$k] ?? 0;
        if ($k === 'deleted' && !$n) continue;
    ?>
      <a class="tab <?= $fStatus === $k ? 'on' : '' ?>" href="<?= h(qs(['status' => $k, 'page' => 1])) ?>">
        <?= h($label) ?><span class="n"><?= $n ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Фильтры -->
  <form class="filters" method="get">
    <input type="hidden" name="status" value="<?= h($fStatus) ?>">
    <input type="hidden" name="scope"  value="<?= h($fScope) ?>">
    <input type="text" name="q" value="<?= h($fQ) ?>" placeholder="Поиск по названию или тегам…" style="min-width:260px" class="pix">
    <select name="category" class="pix">
      <option value="">Все категории</option>
      <?php foreach ($CATS as $k => $v): ?>
        <option value="<?= $k ?>" <?= $fCat === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn pix" type="submit">Применить</button>
    <?php if ($fQ || $fCat): ?>
      <a class="btn ghost pix" href="?status=<?= h($fStatus) ?>&scope=<?= h($fScope) ?>">Сбросить</a>
    <?php endif; ?>
  </form>

  <!-- Bulk-панель -->
  <div class="bulk pix" id="bulkBar">
    <span>Выбрано <span class="cnt" id="bulkCnt">0</span></span>
    <div style="display:flex;gap:6px;margin-left:auto;flex-wrap:wrap">
      <button class="btn pix" data-bulk="submit">На проверку</button>
      <?php if ($ctx['is_staff']): ?>
        <button class="btn pix" data-bulk="publish" style="border-color:rgba(0,232,135,.4);color:#8effc9">Одобрить</button>
        <button class="btn pix" data-bulk="reject">Отклонить</button>
      <?php endif; ?>
      <button class="btn pix" data-bulk="hide">Скрыть</button>
      <button class="btn danger pix" data-bulk="delete">Удалить</button>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <div style="font-size:2.4rem;margin-bottom:10px">📦</div>
      <p>Ничего не найдено.</p>
    </div>
  <?php else: ?>
  <table class="grid">
    <thead>
      <tr>
        <th style="width:28px"><input type="checkbox" id="checkAll"></th>
        <th style="width:54px"></th>
        <th>Ассет</th>
        <th style="width:120px">Статус</th>
        <th style="width:90px">Цена</th>
        <th style="width:80px">Скач.</th>
        <th style="width:90px">Размер</th>
        <th style="width:1%"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $a):
        $meta  = asset_status_meta((string)$a['status']);
        $own   = acl_owns($ctx, $a);
        $trs   = acl_transitions($ctx, $a);
        $cat   = $CATS[$a['category']] ?? $a['category'];
    ?>
      <tr data-id="<?= (int)$a['id'] ?>">
        <td><input type="checkbox" class="rowchk" value="<?= (int)$a['id'] ?>"></td>
        <td>
          <img class="cover pix" src="<?= $a['path_to_cover'] ? h($a['path_to_cover']) : 'https://placehold.co/44x44/160028/c32178?text=%20' ?>" alt="">
        </td>
        <td>
          <a class="a-name" href="/assetstore/asset.php?id=<?= (int)$a['id'] ?>"><?= h($a['name']) ?></a>
          <div class="a-sub">
            <?= h($cat) ?> · v<?= h($a['version'] ?: '1.0') ?>
            <?php if (!$own): ?>
              <span class="foreign pix">чужой · <?= h($a['studio_display'] ?: $a['studio_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($a['featured'])): ?><span class="foreign pix" style="color:var(--ok);border-color:rgba(0,232,135,.4)">в подборке</span><?php endif; ?>
          </div>
        </td>
        <td>
          <span class="pill" style="color:<?= $meta[1] ?>"><?= $meta[2] ?> <?= h($meta[0]) ?></span>
          <?php if ($a['status'] === 'rejected' && $a['moderator_note']): ?>
            <div class="a-sub" title="<?= h($a['moderator_note']) ?>" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($a['moderator_note']) ?>
            </div>
          <?php endif; ?>
        </td>
        <td class="mono"><?= $a['price'] > 0 ? number_format((float)$a['price'], 0, ',', ' ') . ' ₽' : 'Free' ?></td>
        <td class="mono"><?= (int)$a['downloads_count'] ?></td>
        <td class="mono" style="color:var(--muted)"><?= fmtBytesM($a['file_size_bytes']) ?></td>
        <td>
          <div class="acts">
            <?php foreach ($trs as $to):
                if ($to === 'deleted') continue; // удаление — в меню, чтобы не жать случайно
                $needNote = ($to === 'rejected');
            ?>
              <button class="btn pix act-tr"
                      data-id="<?= (int)$a['id'] ?>" data-to="<?= $to ?>" data-note="<?= $needNote ? 1 : 0 ?>"
                      <?= $to === 'published' ? 'style="border-color:rgba(0,232,135,.4);color:#8effc9"' : '' ?>>
                <?= h(asset_transition_label((string)$a['status'], $to)) ?>
              </button>
            <?php endforeach; ?>

            <?php if (acl_can($ctx, $a, 'edit')): ?>
              <button class="btn ghost pix act-edit"
                      data-id="<?= (int)$a['id'] ?>"
                      data-price="<?= h($a['price']) ?>"
                      data-version="<?= h($a['version']) ?>"
                      data-tags="<?= h($a['tags']) ?>"
                      data-share="<?= (int)($a['dev_share'] ?: 70) ?>"
                      data-featured="<?= (int)($a['featured'] ?? 0) ?>">✎</button>
            <?php endif; ?>

            <button class="btn ghost pix act-log" data-id="<?= (int)$a['id'] ?>" title="История">🕘</button>

            <?php if (in_array('deleted', $trs, true)): ?>
              <button class="btn danger pix act-tr" data-id="<?= (int)$a['id'] ?>" data-to="deleted"
                      data-confirm="Удалить «<?= h($a['name']) ?>»? Ассет уйдёт в корзину.">🗑</button>
            <?php endif; ?>

            <?php if ($ctx['is_root'] && $a['status'] === 'deleted'): ?>
              <button class="btn danger pix act-purge" data-id="<?= (int)$a['id'] ?>"
                      title="Необратимое удаление">☠</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span class="on"><?= $i ?></span>
      <?php else: ?><a href="<?= h(qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ── Modals ── -->
<div class="mx" id="mxEdit"><div class="mx-box pix">
  <h3>Быстрая правка</h3>
  <p style="color:var(--muted);font-size:.76rem;margin:-8px 0 14px">
    Здесь только поля, не требующие перемодерации. Название, описание, файлы —
    в <a href="#" id="fullEditLink" style="color:var(--p)">полном редакторе</a>.
  </p>
  <input type="hidden" id="edId">
  <div class="fld"><label>Цена, ₽ (0 = бесплатно)</label><input type="number" id="edPrice" min="0" step="1" class="pix"></div>
  <div class="fld"><label>Версия</label><input type="text" id="edVersion" maxlength="20" class="pix"></div>
  <div class="fld"><label>Теги (через запятую, до 15)</label><input type="text" id="edTags" class="pix"></div>
  <div class="fld"><label>Доля разработчика, % (10–90)</label><input type="number" id="edShare" min="10" max="90" class="pix"></div>
  <?php if ($ctx['is_staff']): ?>
  <div class="fld"><label><input type="checkbox" id="edFeatured" style="width:auto;margin-right:6px">В подборку</label></div>
  <?php endif; ?>
  <div class="mx-btns">
    <button class="btn ghost pix" data-close>Отмена</button>
    <button class="btn primary pix" id="edSave">Сохранить</button>
  </div>
</div></div>

<div class="mx" id="mxNote"><div class="mx-box pix" style="max-width:440px">
  <h3 id="noteTitle">Причина</h3>
  <div class="fld"><textarea id="noteText" rows="4" class="pix" placeholder="Разработчик увидит этот текст."></textarea></div>
  <div class="mx-btns">
    <button class="btn ghost pix" data-close>Отмена</button>
    <button class="btn primary pix" id="noteOk">Подтвердить</button>
  </div>
</div></div>

<div class="mx" id="mxLog"><div class="mx-box pix">
  <h3>История изменений</h3>
  <div id="logBody" style="color:var(--muted)">Загрузка…</div>
  <div class="mx-btns"><button class="btn ghost pix" data-close>Закрыть</button></div>
</div></div>

<div class="toast" id="toast"></div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const API  = '/assetstore/api_admin.php';

const $ = s => document.querySelector(s);
const toast = (m, bad) => {
  const t = $('#toast');
  t.textContent = m;
  t.style.borderColor = bad ? 'rgba(244,67,54,.6)' : 'rgba(0,232,135,.5)';
  t.classList.add('on');
  setTimeout(() => t.classList.remove('on'), 2600);
};

async function api(payload) {
  const r = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({...payload, csrf: CSRF})
  });
  let d;
  try { d = await r.json(); } catch { throw new Error('Сервер вернул не JSON (HTTP ' + r.status + ')'); }
  if (!d.ok) throw new Error(d.error || 'Ошибка');
  return d;
}

/* ── modal plumbing ── */
document.querySelectorAll('.mx').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('on'); });
  m.querySelectorAll('[data-close]').forEach(b => b.onclick = () => m.classList.remove('on'));
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.mx.on').forEach(m => m.classList.remove('on'));
});

/* ── причина (rejected) ── */
let notePending = null;
function askNote(title, cb) {
  $('#noteTitle').textContent = title;
  $('#noteText').value = '';
  notePending = cb;
  $('#mxNote').classList.add('on');
  setTimeout(() => $('#noteText').focus(), 40);
}
$('#noteOk').onclick = () => {
  const v = $('#noteText').value.trim();
  if (!v) { toast('Причина обязательна', true); return; }
  $('#mxNote').classList.remove('on');
  notePending && notePending(v);
};

/* ── переходы ── */
document.querySelectorAll('.act-tr').forEach(b => b.onclick = () => {
  const id = +b.dataset.id, to = b.dataset.to;
  if (b.dataset.confirm && !confirm(b.dataset.confirm)) return;
  const run = note => {
    b.disabled = true;
    api({action: 'transition', id, to, note})
      .then(d => { toast('Статус: ' + d.label); setTimeout(() => location.reload(), 550); })
      .catch(e => { toast(e.message, true); b.disabled = false; });
  };
  if (b.dataset.note === '1') askNote('Причина отклонения', run); else run(null);
});

/* ── purge ── */
document.querySelectorAll('.act-purge').forEach(b => b.onclick = () => {
  if (!confirm('НЕОБРАТИМО удалить ассет из БД? Останется только запись в аудит-логе.')) return;
  api({action: 'purge', id: +b.dataset.id})
    .then(() => { toast('Удалено навсегда'); setTimeout(() => location.reload(), 550); })
    .catch(e => toast(e.message, true));
});

/* ── quick edit ── */
document.querySelectorAll('.act-edit').forEach(b => b.onclick = () => {
  const d = b.dataset;
  $('#edId').value = d.id;
  $('#edPrice').value = parseFloat(d.price) || 0;
  $('#edVersion').value = d.version || '1.0';
  $('#edTags').value = d.tags || '';
  $('#edShare').value = d.share || 70;
  const f = $('#edFeatured'); if (f) f.checked = d.featured === '1';
  $('#fullEditLink').href = '/assetstore/edit_asset.php?id=' + d.id;
  $('#mxEdit').classList.add('on');
});
$('#edSave').onclick = () => {
  const p = {
    action: 'quick_edit',
    id: +$('#edId').value,
    price: +$('#edPrice').value,
    version: $('#edVersion').value,
    tags: $('#edTags').value,
    dev_share: +$('#edShare').value
  };
  const f = $('#edFeatured'); if (f) p.featured = f.checked ? 1 : 0;
  api(p).then(() => { toast('Сохранено'); setTimeout(() => location.reload(), 500); })
        .catch(e => toast(e.message, true));
};

/* ── log ── */
document.querySelectorAll('.act-log').forEach(b => b.onclick = () => {
  $('#logBody').innerHTML = 'Загрузка…';
  $('#mxLog').classList.add('on');
  api({action: 'log', id: +b.dataset.id}).then(d => {
    if (!d.rows.length) { $('#logBody').innerHTML = 'Пока пусто.'; return; }
    $('#logBody').innerHTML = d.rows.map(r => {
      const who = r.first_name || r.username || ('#' + r.actor_id);
      const badge = +r.as_staff ? ' <span style="color:#f59e0b">[модератор]</span>' : '';
      const move = r.status_to ? `${r.status_from || '—'} → <b>${r.status_to}</b>` : r.action;
      const note = r.note ? `<div style="margin-top:3px">${escapeHtml(r.note)}</div>` : '';
      return `<div class="logline"><div class="lt">${r.created_at} · ${escapeHtml(who)}${badge}</div>${move}${note}</div>`;
    }).join('');
  }).catch(e => $('#logBody').textContent = e.message);
});
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── bulk ── */
const chks = [...document.querySelectorAll('.rowchk')];
const bar = $('#bulkBar');
function sync() {
  const n = chks.filter(c => c.checked).length;
  $('#bulkCnt').textContent = n;
  bar.classList.toggle('on', n > 0);
}
chks.forEach(c => c.onchange = sync);
const all = $('#checkAll');
if (all) all.onchange = () => { chks.forEach(c => c.checked = all.checked); sync(); };

document.querySelectorAll('[data-bulk]').forEach(b => b.onclick = () => {
  const ids = chks.filter(c => c.checked).map(c => +c.value);
  if (!ids.length) return;
  const op = b.dataset.bulk;
  const run = note => {
    api({action: 'bulk', ids, op, note}).then(d => {
      const nf = Object.keys(d.failed).length;
      toast(`Готово: ${d.done.length}` + (nf ? `, пропущено: ${nf}` : ''), nf > 0);
      setTimeout(() => location.reload(), 900);
    }).catch(e => toast(e.message, true));
  };
  if (op === 'delete' && !confirm(`Удалить ${ids.length} шт.?`)) return;
  if (op === 'reject') askNote('Причина отклонения (для всех выбранных)', run); else run(null);
});
</script>
</body>
</html>