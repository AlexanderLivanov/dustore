<?php
require_once __DIR__ . '/lib/core.php';

/* Создание заметки — обычной формой, без JS. */
if (($_POST['do'] ?? '') === 'new') {
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title !== '') {
        $sec = ($_POST['section_id'] ?? '') !== '' ? (int)$_POST['section_id'] : null;
        $slug = unique_slug($title);
        db()->prepare("INSERT INTO articles (slug, title, section_id, body_md, status) VALUES (?, ?, ?, ?, 'draft')")
            ->execute([$slug, $title, $sec, NOTE_TEMPLATE]);
        header('Location: editor.php?id=' . (int)db()->lastInsertId());
        exit;
    }
}

$q       = trim((string)($_GET['q'] ?? ''));
$secSlug = (string)($_GET['section'] ?? '');
$tagSlug = (string)($_GET['tag'] ?? '');
$status  = (string)($_GET['status'] ?? '');

$where = [];
$args  = [];
if ($q !== '') { $where[] = '(a.title LIKE ? OR a.summary LIKE ? OR a.body_md LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%", "%$q%"]); }
if ($secSlug !== '') { $where[] = '(s.slug = ? OR ps.slug = ?)'; $args[] = $secSlug; $args[] = $secSlug; }
if ($status !== '' && isset(STATUS_LABELS[$status])) { $where[] = 'a.status = ?'; $args[] = $status; }
if ($tagSlug !== '') { $where[] = 'EXISTS (SELECT 1 FROM article_tags at JOIN tags t ON t.id = at.tag_id WHERE at.article_id = a.id AND t.slug = ?)'; $args[] = $tagSlug; }

$sql = 'SELECT a.*, s.title AS sec_title, s.slug AS sec_slug,
          (SELECT COUNT(*) FROM questions q2 WHERE q2.article_id = a.id AND q2.status = "open") AS q_open,
          (SELECT COUNT(*) FROM article_links l WHERE l.from_id = a.id OR l.to_id = a.id) AS deg
        FROM articles a
        LEFT JOIN sections s ON s.id = a.section_id
        LEFT JOIN sections ps ON ps.id = s.parent_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY FIELD(a.status,'draft','published','stub'), a.updated_at DESC";
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$tree = sections_tree();
$allTags = db()->query(
    'SELECT t.*, COUNT(at.article_id) AS cnt FROM tags t
     LEFT JOIN article_tags at ON at.tag_id = t.id
     GROUP BY t.id HAVING cnt > 0 ORDER BY cnt DESC, t.slug'
)->fetchAll();
$stats = db()->query(
    "SELECT
       (SELECT COUNT(*) FROM articles) AS total,
       (SELECT COUNT(*) FROM articles WHERE status='stub') AS stubs,
       (SELECT COUNT(*) FROM articles WHERE confidence >= 3) AS solid,
       (SELECT COUNT(*) FROM questions WHERE status='open') AS qopen,
       (SELECT COUNT(*) FROM article_links) AS links"
)->fetch();
$sectionsFlat = db()->query('SELECT id, title, parent_id FROM sections ORDER BY position, title')->fetchAll();

function qs(array $over): string
{
    $base = array_filter([
        'q' => $_GET['q'] ?? '', 'section' => $_GET['section'] ?? '',
        'tag' => $_GET['tag'] ?? '', 'status' => $_GET['status'] ?? '',
    ], fn($v) => $v !== '' && $v !== null);
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') unset($base[$k]); else $base[$k] = $v;
    }
    return $base ? '?' . http_build_query($base) : 'index.php';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GDDB — библиотека знаний</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/ui.css">
<style>
.wrap{display:grid;grid-template-columns:250px 1fr;gap:36px;max-width:1420px;margin:0 auto;padding:28px 32px 100px;align-items:start}
.side{position:sticky;top:84px;display:flex;flex-direction:column;gap:26px}
.side h4{font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:9px}
.tree a{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text2);padding:4px 8px;border-radius:6px;margin-left:-8px}
.tree a:hover{color:var(--text);background:var(--bg2)}
.tree a.on{color:var(--amber);background:var(--amber-glow)}
.tree a .c{margin-left:auto;font-family:var(--mono);font-size:10.5px;color:var(--text3)}
.tree .kids{margin-left:10px;border-left:1px solid var(--border);padding-left:8px;margin-top:2px}
.tagcloud{display:flex;flex-wrap:wrap;gap:5px}
.tagcloud a{font-family:var(--mono);font-size:11px;color:var(--text2);background:var(--bg2);
  border:1px solid var(--border);border-radius:99px;padding:3px 9px}
.tagcloud a:hover{border-color:var(--border2);color:var(--text)}
.tagcloud a.on{background:var(--amber-glow);border-color:var(--amber-dim);color:var(--amber)}

.stats{display:flex;gap:26px;flex-wrap:wrap;padding-bottom:22px;margin-bottom:22px;border-bottom:1px solid var(--border)}
.stat b{display:block;font-size:26px;font-weight:800;letter-spacing:-1px;line-height:1}
.stat span{font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.stat.warn b{color:var(--amber)}

.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.toolbar form{display:flex;gap:8px;align-items:center}
.chips{display:flex;gap:5px}
.chips a{font-family:var(--mono);font-size:11px;color:var(--text2);border:1px solid var(--border2);
  border-radius:6px;padding:4px 9px}
.chips a.on{background:var(--bg4);color:var(--amber);border-color:var(--amber-dim)}

.list{display:flex;flex-direction:column;gap:1px}
.item{display:grid;grid-template-columns:minmax(0,1fr) auto;text-align:left;gap:4px 16px;padding:14px 16px;background:var(--bg2);
  border:1px solid var(--border);border-radius:var(--r);transition:.14s}
.item:hover{border-color:var(--border2);background:var(--bg3)}
.item h3{font-size:16px;font-weight:700;letter-spacing:-.3px}
.item h3 a:hover{color:var(--amber)}
.item p{font-family:var(--serif);font-size:13.5px;color:var(--text2);line-height:1.5;max-width:78ch}
.item .meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-family:var(--mono);font-size:10.5px;color:var(--text3)}
.item .right{display:flex;align-items:center;gap:8px;grid-column:2;grid-row:1/3;align-self:start}
.conf{display:flex;gap:2px}
.conf i{width:5px;height:12px;border-radius:2px;background:var(--bg4)}
.conf i.f{background:var(--amber)}
.qb{font-family:var(--mono);font-size:10px;color:var(--amber);border:1px solid var(--amber-dim);border-radius:4px;padding:1px 5px}
.newform{display:flex;gap:8px;align-items:center;background:var(--bg2);border:1px solid var(--border);
  border-radius:var(--r);padding:10px;margin-bottom:20px}
@media(max-width:900px){.wrap{grid-template-columns:1fr;padding:20px 18px 80px}.side{position:static}}
</style>
</head>
<body>

<nav>
  <a class="logo" href="index.php"><span class="logo-mark"></span>GD<em>DB</em></a>
  <div class="nav-right">
    <a class="btn btn-ghost btn-sm" href="<?= e(qs(['status' => 'stub'])) ?>">Заготовки <?= (int)$stats['stubs'] ?></a>
    <a class="btn btn-ghost btn-sm" href="questions.php">Вопросы <?= (int)$stats['qopen'] ?></a>
  </div>
</nav>

<div class="wrap">
  <aside class="side">
    <div>
      <h4>Разделы</h4>
      <div class="tree">
        <a href="<?= e(qs(['section' => null])) ?>" class="<?= $secSlug === '' ? 'on' : '' ?>">Все<span class="c"><?= (int)$stats['total'] ?></span></a>
        <?php foreach ($tree as $n): ?>
          <a href="<?= e(qs(['section' => $n['slug']])) ?>" class="<?= $secSlug === $n['slug'] ? 'on' : '' ?>">
            <?= e($n['title']) ?><span class="c"><?= (int)$n['cnt'] ?></span></a>
          <?php if ($n['children']): ?>
            <div class="kids">
              <?php foreach ($n['children'] as $c): ?>
                <a href="<?= e(qs(['section' => $c['slug']])) ?>" class="<?= $secSlug === $c['slug'] ? 'on' : '' ?>">
                  <?= e($c['title']) ?><span class="c"><?= (int)$c['cnt'] ?></span></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h4>Теги</h4>
      <div class="tagcloud">
        <?php foreach ($allTags as $t): ?>
          <a href="<?= e(qs(['tag' => $tagSlug === $t['slug'] ? null : $t['slug']])) ?>"
             class="<?= $tagSlug === $t['slug'] ? 'on' : '' ?>">#<?= e($t['title']) ?> <?= (int)$t['cnt'] ?></a>
        <?php endforeach; ?>
        <?php if (!$allTags): ?><div class="empty">пока нет</div><?php endif; ?>
      </div>
    </div>
  </aside>

  <main>
    <div class="stats">
      <div class="stat"><b><?= (int)$stats['total'] ?></b><span>заметок</span></div>
      <div class="stat warn"><b><?= (int)$stats['stubs'] ?></b><span>заготовок</span></div>
      <div class="stat"><b><?= (int)$stats['solid'] ?></b><span>объясню с нуля</span></div>
      <div class="stat warn"><b><?= (int)$stats['qopen'] ?></b><span>открытых вопросов</span></div>
      <div class="stat"><b><?= (int)$stats['links'] ?></b><span>связей</span></div>
    </div>

    <form class="newform" method="post">
      <input type="hidden" name="do" value="new">
      <input class="inp" name="title" placeholder="Новая тема — например «Транзисторы»" required>
      <select class="sel" name="section_id" style="max-width:220px">
        <option value="">— без раздела —</option>
        <?php foreach ($sectionsFlat as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= $s['parent_id'] ? '· ' : '' ?><?= e($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary">Создать</button>
    </form>

    <div class="toolbar">
      <form method="get">
        <?php foreach (['section' => $secSlug, 'tag' => $tagSlug, 'status' => $status] as $k => $v): ?>
          <?php if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; ?>
        <?php endforeach; ?>
        <input class="inp" name="q" value="<?= e($q) ?>" placeholder="поиск по тексту заметок" style="width:280px">
        <button class="btn btn-sm">Найти</button>
      </form>
      <div class="chips">
        <a href="<?= e(qs(['status' => null])) ?>" class="<?= $status === '' ? 'on' : '' ?>">все</a>
        <?php foreach (STATUS_LABELS as $k => $v): ?>
          <a href="<?= e(qs(['status' => $status === $k ? null : $k])) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($v) ?></a>
        <?php endforeach; ?>
      </div>
      <div style="flex:1"></div>
      <span class="muted mono" style="font-size:11px"><?= count($rows) ?> шт.</span>
    </div>

    <div class="list">
      <?php if (!$rows): ?>
        <div class="empty">Ничего не нашлось. Создай заметку выше.</div>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <article class="item">
          <h3><a href="note.php?slug=<?= e($r['slug']) ?>"><?= e($r['title']) ?></a></h3>
          <div class="right">
            <?php if ((int)$r['q_open']): ?><span class="qb"><?= (int)$r['q_open'] ?> ?</span><?php endif; ?>
            <span class="conf" title="<?= e(CONFIDENCE_LABELS[(int)$r['confidence']]) ?>">
              <?php for ($i = 1; $i <= 3; $i++): ?><i class="<?= (int)$r['confidence'] >= $i ? 'f' : '' ?>"></i><?php endfor; ?>
            </span>
            <span class="st st-<?= e($r['status']) ?>"><?= e(STATUS_LABELS[$r['status']]) ?></span>
            <a class="btn btn-sm" href="editor.php?id=<?= (int)$r['id'] ?>">Править</a>
          </div>
          <?php if ($r['summary'] !== ''): ?><p><?= e($r['summary']) ?></p><?php endif; ?>
          <div class="meta">
            <?php if ($r['sec_title']): ?><span><?= e($r['sec_title']) ?></span>·<?php endif; ?>
            <span><?= (int)$r['deg'] ?> связей</span>·
            <span><?= date('d.m.Y', strtotime($r['updated_at'])) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </main>
</div>
</body>
</html>
