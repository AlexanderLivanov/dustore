<?php
// Studio page template
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Моя студия</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/styles.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen">
  <?php // include 'header.php'; ?>
  <div class="max-w-4xl mx-auto p-6">
    <h2 class="text-xl font-semibold mb-4">Моя студия</h2>
    <div class="panel">
      <div class="panel-header">Информация о студии</div>
      <div class="panel-body">
        <p class="text-sm text-slate-600">Название: <strong>Crazy Studio</strong></p>
        <p class="text-sm text-slate-600">Описание: Инди-студия, делающая пиксельные игры.</p>
        <div class="mt-3">
          <a class="btn" href="#">Редактировать</a>
        </div>
      </div>
    </div>

    <div class="panel mt-6">
      <div class="panel-header">Игры студии</div>
      <div class="panel-body">
        <ul class="divide-y">
          <li class="py-3 flex justify-between items-center">
            <div>
              <div class="font-medium">Neon Racer</div>
              <div class="text-xs text-slate-500">Рейтинг: 4.8 • Продажи: 12k</div>
            </div>
            <div><a class="btn-outline" href="#">Управлять</a></div>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <!-- dock -->
  <?php include 'index_dock.php' ?? include 'index.php'; ?>
  <script src="assets/js/app.js"></script>
</body>
</html>
