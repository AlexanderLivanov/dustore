<?php
require_once __DIR__ . '/lib/core.php';

$art = article_by_slug((string)($_GET['slug'] ?? ''));
if (!$art) { http_response_code(404); $art = null; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $art ? e($art['title']) : 'Не найдено' ?> — GDDB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/ui.css">
<style>
.wrap{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:48px;max-width:1180px;margin:0 auto;padding:40px 32px 120px;align-items:start}
.head{margin-bottom:32px}
.head .crumb{font-family:var(--mono);font-size:11px;color:var(--muted);margin-bottom:10px}
.head h1{font-size:38px;font-weight:800;letter-spacing:-1.2px;line-height:1.12;margin-bottom:12px}
.head .sum{font-family:var(--serif);font-size:17px;color:var(--text2);line-height:1.6;max-width:700px;margin-bottom:16px}
.head .meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.aside{position:sticky;top:80px;display:flex;flex-direction:column;gap:24px}
.aside h4{font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:8px}
.aside .l{display:block;font-size:13px;color:var(--text2);padding:3px 0}
.aside .l:hover{color:var(--amber)}
.aside .l.stub{color:var(--text3)}
.ql{display:flex;gap:7px;font-size:13px;line-height:1.5;padding:5px 0;font-family:var(--serif)}
.ql.done{color:var(--text3)}
.ql b{color:var(--amber);font-family:var(--mono);font-size:11px;flex:none;padding-top:2px}
@media(max-width:900px){.wrap{grid-template-columns:1fr;padding:24px 18px 80px}.aside{position:static}}
</style>
</head>
<body>
<nav>
  <a class="logo" href="index.php"><span class="logo-mark"></span>GD<em>DB</em></a>
  <div class="nav-right">
    <?php if ($art): ?><a class="btn btn-sm" href="editor.php?id=<?= (int)$art['id'] ?>">Править</a><?php endif; ?>
  </div>
</nav>

<?php if (!$art): ?>
  <div class="wrap"><div><h1 class="head">Такой заметки нет</h1>
    <p class="muted">Возможно, ссылка ведёт на тему, которую ты ещё не создавал.
    <a class="wikilink" href="index.php">Вернуться в библиотеку</a>.</p></div></div>
<?php else:
  $id = (int)$art['id'];
  $tags = article_tags($id);
  $out = article_links_out($id);
  $in = article_links_in($id);
  $qs = article_questions($id);
  $outline = md_outline($art['body_md']);
  $grouped = [];
  foreach ($out as $l) $grouped[$l['type']][] = $l;
?>
<div class="wrap">
  <main>
    <div class="head">
      <div class="crumb">
        <?php
        if ($art['section_id']) {
            $st = db()->prepare('SELECT s.title, ps.title AS parent FROM sections s LEFT JOIN sections ps ON ps.id = s.parent_id WHERE s.id = ?');
            $st->execute([(int)$art['section_id']]);
            $s = $st->fetch();
            echo e(trim(($s['parent'] ? $s['parent'] . ' / ' : '') . $s['title']));
        } else { echo 'без раздела'; }
        ?>
      </div>
      <h1><?= e($art['title']) ?></h1>
      <?php if ($art['summary'] !== ''): ?><div class="sum"><?= e($art['summary']) ?></div><?php endif; ?>
      <div class="meta">
        <span class="st st-<?= e($art['status']) ?>"><?= e(STATUS_LABELS[$art['status']]) ?></span>
        <span class="pill"><?= e(CONFIDENCE_LABELS[(int)$art['confidence']]) ?></span>
        <?php foreach ($tags as $t): ?>
          <a class="pill" href="index.php?tag=<?= e($t['slug']) ?>"><b>#<?= e($t['title']) ?></b></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="prose"><?= md_render($art['body_md'], wiki_linkmap($art['body_md'])) ?></div>
  </main>

  <aside class="aside">
    <?php foreach (LINK_LABELS as $type => $label): ?>
      <?php if (!empty($grouped[$type])): ?>
        <div>
          <h4><?= e($label) ?></h4>
          <?php foreach ($grouped[$type] as $l): ?>
            <a class="l <?= $l['status'] === 'stub' ? 'stub' : '' ?>" href="note.php?slug=<?= e($l['slug']) ?>">→ <?= e($l['title']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($in): ?>
      <div>
        <h4>Ссылаются сюда</h4>
        <?php foreach ($in as $l): ?>
          <a class="l" href="note.php?slug=<?= e($l['slug']) ?>">← <?= e($l['title']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($qs): ?>
      <div>
        <h4>Вопросы</h4>
        <?php foreach ($qs as $q): ?>
          <div class="ql <?= $q['status'] === 'answered' ? 'done' : '' ?>">
            <b><?= $q['status'] === 'answered' ? '✓' : '?' ?></b><span><?= e($q['text']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($outline): ?>
      <div>
        <h4>На странице</h4>
        <?php foreach ($outline as $o): ?>
          <a class="l" style="padding-left:<?= ($o['level'] - 1) * 10 ?>px" href="#<?= e($o['anchor']) ?>"><?= e($o['text']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>
<?php endif; ?>
</body>
</html>
