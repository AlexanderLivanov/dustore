<?php
// Admin / moderation page template
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>Администрирование</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/styles.css"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto p-6">
  <h2 class="text-xl font-semibold mb-4">Администрирование</h2>
  <div class="panel">
    <div class="panel-header">Объявления</div>
    <div class="panel-body">
      <p class="text-sm text-slate-600">Создавайте объявления и управляйте глобальными настройками.</p>
      <div class="mt-3"><a class="btn" href="#">Создать объявление</a></div>
    </div>
  </div>
</div>
<?php include 'index_dock.php' ?? include 'index.php'; ?><script src="assets/js/app.js"></script></body></html>
