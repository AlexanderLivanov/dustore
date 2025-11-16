// Dock behavior: show contextual panels when dock items are clicked
document.addEventListener('DOMContentLoaded', function(){
  const dock = document.querySelectorAll('.dock-item');
  const panel = document.getElementById('context-panel');
  function contentFor(key){
    switch(key){
      case 'dashboard': return '<div class="text-sm">Быстрый доступ к панелям, метрики и уведомления.</div>';
      case 'studio': return '<div class="text-sm"><strong>Моя студия</strong><div class="mt-2 text-xs text-gray-500">Название • Статистика • Управление играми</div><div class="mt-3"><a class="btn" href="studio.php">Открыть студию</a></div></div>';
      case 'employees': return '<div class="text-sm">Список сотрудников и приглашения. <div class="mt-3"><a class="btn" href="employees.php">Управлять</a></div></div>';
      case 'projects': return '<div class="text-sm">Список проектов, быстрые действия. <div class="mt-3"><a class="btn" href="projects.php">Проекты</a></div></div>';
      case 'reviews': return '<div class="text-sm">Последние рецензии и модерация. <div class="mt-3"><a class="btn" href="reviews.php">Рецензии</a></div></div>';
      case 'analytics': return '<div class="text-sm">Быстрый график продаж. <div class="mt-3"><a class="btn" href="analytics.php">Аналитика</a></div></div>';
      case 'admin': return '<div class="text-sm">Администрирование и объявления. <div class="mt-3"><a class="btn" href="admin.php">Админ</a></div></div>';
      default: return '<div class="text-sm">Раздел</div>';
    }
  }

  dock.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const key = btn.dataset.panel;
      panel.innerHTML = contentFor(key);
      panel.classList.add('open');
      panel.setAttribute('aria-hidden','false');
      // small animation
      panel.animate([{opacity:0, transform:'translateY(6px)'},{opacity:1, transform:'translateY(0)'}], {duration:180, easing:'ease-out'});
      // auto-link navigation when clicking label in panel
    });
  });

  // Close context panel when clicking outside
  document.addEventListener('click', (e)=>{
    if(!e.target.closest('.bottom-dock')){
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden','true');
    }
  });
});
