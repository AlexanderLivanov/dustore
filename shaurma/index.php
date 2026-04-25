<?php
// Dashboard template - keep your PHP logic intact; replace the view with this file.
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Панель управления — Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-slate-50 min-h-screen font-sans">
  <?php // include 'header.php'; ?>
  <div class="max-w-screen-xl mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Добро пожаловать, <span class="text-indigo-600">crazya11my1if3</span></h1>
      <div class="text-sm text-slate-600">← <a href="/profile.php" class="text-indigo-600 hover:underline">Назад, в профиль</a></div>
    </div>

    <!-- Top metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <div class="card">
        <div class="card-title">Активные проекты</div>
        <div class="card-value">12</div>
      </div>
      <div class="card">
        <div class="card-title">Новые отзывы</div>
        <div class="card-value">8</div>
      </div>
      <div class="card">
        <div class="card-title">Доход (месяц)</div>
        <div class="card-value">₽ 124,500</div>
      </div>
      <div class="card">
        <div class="card-title">Рейтинг студии</div>
        <div class="card-value">4.7 ★</div>
      </div>
    </div>

    <!-- Main content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="col-span-2">
        <div class="panel">
          <div class="panel-header">Аналитика — общая</div>
          <div class="panel-body">
            <p class="text-sm text-slate-600">Здесь будет график/таблица. Мы подготовили структурированные блоки для простой интеграции.</p>
            <div class="mt-4 h-48 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-400">[ApexCharts/Chart.js placeholder]</div>
          </div>
        </div>

        <div class="panel mt-6">
          <div class="panel-header">Свежее — Проекты</div>
          <div class="panel-body">
            <ul class="divide-y">
              <li class="py-3 flex justify-between items-center">
                <div>
                  <div class="font-medium">Project Apollo</div>
                  <div class="text-xs text-slate-500">Автор: Ivan • Статус: В разработке</div>
                </div>
                <div><a class="btn" href="projects.php">Открыть</a></div>
              </li>
              <li class="py-3 flex justify-between items-center">
                <div>
                  <div class="font-medium">Neon Racer</div>
                  <div class="text-xs text-slate-500">Автор: Marina • Статус: Релиз</div>
                </div>
                <div><a class="btn" href="projects.php">Открыть</a></div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <aside>
        <div class="panel">
          <div class="panel-header">Моя студия</div>
          <div class="panel-body">
            <div class="text-sm text-slate-600">Название: Crazy Studio</div>
            <div class="mt-2 flex gap-2">
              <a class="btn" href="studio.php">Перейти</a>
              <a class="btn-outline" href="admin.php">Настройки</a>
            </div>
          </div>
        </div>

        <div class="panel mt-4">
          <div class="panel-header">Быстрые действия</div>
          <div class="panel-body">
            <div class="grid grid-cols-2 gap-2 text-sm">
              <a class="action-card" href="projects.php">add Создать новый</a>
              <a class="action-card" href="employees.php">group Исполнители</a>
              <a class="action-card" href="reviews.php">reviews Отзывы</a>
              <a class="action-card" href="analytics.php">area_chart Аналитика</a>
            </div>
          </div>
        </div>
      </aside>
    </div>

  </div>

  <!-- Bottom dock navigation (unusual solution instead of sidebar) -->
  <div id="bottom-dock" class="bottom-dock">
    <div class="dock-grid">
      <button class="dock-item" data-panel="dashboard">
        <span class="material-icons">dashboard</span>
        <div class="dock-label">dashboard</div>
      </button>
      <button class="dock-item" data-panel="studio">
        <span class="material-icons">apartment</span>
        <div class="dock-label">Моя студия</div>
      </button>
      <button class="dock-item" data-panel="employees">
        <span class="material-icons">groups</span>
        <div class="dock-label">Сотрудники</div>
      </button>
      <button class="dock-item" data-panel="projects">
        <span class="material-icons">work</span>
        <div class="dock-label">Проекты</div>
      </button>
      <button class="dock-item" data-panel="reviews">
        <span class="material-icons">note_add</span>
        <div class="dock-label">Рецензии</div>
      </button>
      <button class="dock-item" data-panel="analytics">
        <span class="material-icons">area_chart</span>
        <div class="dock-label">Аналитика</div>
      </button>
      <button class="dock-item admin-only" data-panel="admin">
        <span class="material-icons">shield</span>
        <div class="dock-label">Админ</div>
      </button>
    </div>

    <div id="context-panel" class="context-panel" aria-hidden="true">
      <!-- dynamic content injected by JS -->
    </div>
  </div>

  <script src="assets/js/app.js"></script>
</body>
</html>
