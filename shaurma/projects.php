<?php
// Projects page template
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>Проекты</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/styles.css"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto p-6">
  <div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-semibold">Проекты</h2>
    <a class="btn" href="#">add Создать новый</a>
  </div>

  <div class="panel">
    <div class="panel-header">Все проекты</div>
    <div class="panel-body">
      <div class="grid md:grid-cols-2 gap-4">
        <div class="project-card">
          <div class="project-title">Project Apollo</div>
          <div class="project-meta">team: 4 • Статус: В разработке</div>
          <div class="mt-3"><a class="btn" href="#">Открыть</a></div>
        </div>
        <div class="project-card">
          <div class="project-title">Neon Racer</div>
          <div class="project-meta">team: 6 • Статус: Релиз</div>
          <div class="mt-3"><a class="btn" href="#">Открыть</a></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'index_dock.php' ?? include 'index.php'; ?><script src="assets/js/app.js"></script></body></html>
