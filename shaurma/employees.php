<?php
// Employees page template
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>Сотрудники</title>
<script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="assets/css/styles.css"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"></head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto p-6">
  <h2 class="text-xl font-semibold mb-4">Сотрудники</h2>
  <div class="panel">
    <div class="panel-header">Список сотрудников</div>
    <div class="panel-body">
      <table class="w-full text-sm">
        <thead class="text-slate-600 text-left"><tr><th>Имя</th><th>Роль</th><th>Статус</th><th></th></tr></thead>
        <tbody>
          <tr class="border-t"><td>Ivan</td><td>Разработчик</td><td>Активен</td><td><a class="btn" href="#">Открыть</a></td></tr>
          <tr class="border-t"><td>Marina</td><td>Артист</td><td>В отпуске</td><td><a class="btn" href="#">Открыть</a></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include 'index_dock.php' ?? include 'index.php'; ?><script src="assets/js/app.js"></script></body></html>
