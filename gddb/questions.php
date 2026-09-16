<?php
require_once __DIR__ . '/lib/core.php';

$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$sql = 'SELECT q.*, a.slug, a.title AS art, a.status AS art_status
        FROM questions q JOIN articles a ON a.id = q.article_id'
     . ($show === 'open' ? " WHERE q.status = 'open'" : '')
     . ' ORDER BY q.status, a.title, q.created_at';
$rows = db()->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Открытые вопросы — GDDB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/ui.css">
<style>
.wrap{max-width:840px;margin:0 auto;padding:40px 32px 120px}
h1{font-size:32px;font-weight:800;letter-spacing:-1px;margin-bottom:8px}
.lead{font-family:var(--serif);font-size:16px;color:var(--text2);margin-bottom:28px;line-height:1.6}
.qrow{display:flex;gap:14px;align-items:flex-start;padding:15px 0;border-bottom:1px solid var(--border)}
.qrow b{font-family:var(--mono);font-size:12px;color:var(--amber);flex:none;padding-top:3px}
.qrow.done b{color:var(--green)}
.qrow .t{flex:1;font-family:var(--serif);font-size:16px;line-height:1.5}
.qrow.done .t{color:var(--text3)}
.qrow .src{font-family:var(--mono);font-size:11px;color:var(--muted);margin-top:5px;display:block}
.qrow .src:hover{color:var(--amber)}
</style>
</head>
<body>
<nav>
  <a class="logo" href="index.php"><span class="logo-mark"></span>GD<em>DB</em></a>
  <div class="nav-right">
    <a class="btn btn-ghost btn-sm" href="questions.php?show=open">Только открытые</a>
    <a class="btn btn-ghost btn-sm" href="questions.php?show=all">Все</a>
  </div>
</nav>
<div class="wrap">
  <h1>Чего я пока не понимаю</h1>
  <p class="lead">Это не список задач, а очередь на изучение: каждая строка — дыра, которую ты сам нашёл,
     когда пытался объяснить тему своими словами.</p>
  <?php if (!$rows): ?>
    <div class="empty">Вопросов нет. Либо ты всё понял, либо плохо старался объяснить.</div>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="qrow <?= $r['status'] === 'answered' ? 'done' : '' ?>">
      <b><?= $r['status'] === 'answered' ? '✓' : '?' ?></b>
      <div class="t"><?= e($r['text']) ?>
        <a class="src" href="editor.php?slug=<?= e($r['slug']) ?>"><?= e($r['art']) ?> →</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>
