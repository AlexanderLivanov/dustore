<?php
// Reviews page template
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>Рецензии</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/styles.css"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto p-6">
  <h2 class="text-xl font-semibold mb-4">Рецензии</h2>
  <div class="panel">
    <div class="panel-header">Последние рецензии</div>
    <div class="panel-body">
      <ul class="divide-y">
        <li class="py-3">
          <div class="flex justify-between">
            <div>
              <div class="font-medium">Отличная игра!</div>
              <div class="text-xs text-slate-500">Пользователь: gamer123 • 2 часа назад</div>
              <div class="mt-1 text-sm">Очень понравилась механика и саундтрек.</div>
            </div>
            <div><a class="btn" href="#">Ответить</a></div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</div>
<?php include 'index_dock.php' ?? include 'index.php'; ?><script src="assets/js/app.js"></script></body></html>
