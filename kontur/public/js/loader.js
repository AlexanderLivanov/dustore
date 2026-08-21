// ============================================================
// LOADER.JS — Win95 progress bar при переключении вкладок
// ============================================================

const Loader = (() => {

  /**
   * Показывает анимированный прогресс-бар в переданном контейнере.
   * Вызывает callback когда "загрузка" завершена.
   * @param {HTMLElement} container — куда монтировать оверлей
   * @param {string} label — текст под баром
   * @param {Function} onDone — вызвать после анимации
   * @param {number} duration — общее время анимации (ms)
   */
  function run(container, label, onDone, duration = 400) {
    // Создаём оверлей
    const overlay = document.createElement('div');
    overlay.className = 'tab-loading';
    overlay.innerHTML = `
      <div style="font-size:11px;font-weight:bold;letter-spacing:1px;margin-bottom:4px;">
        К.О.Н.Т.У.Р. — Загрузка данных...
      </div>
      <div class="tab-loading-bar">
        <div class="tab-loading-fill" id="_ldr_fill" style="width:0%"></div>
      </div>
      <div class="tab-loading-label">${label}</div>`;

    container.appendChild(overlay);

    const fill = overlay.querySelector('#_ldr_fill');
    const steps = [0, 15, 35, 60, 80, 95, 100];
    let i = 0;

    const tick = () => {
      if (i < steps.length) {
        fill.style.width = steps[i++] + '%';
        setTimeout(tick, duration / steps.length);
      } else {
        // Небольшая задержка после 100% — как у настоящего Win95 :)
        setTimeout(() => {
          overlay.remove();
          if (onDone) onDone();
        }, 80);
      }
    };

    setTimeout(tick, 30);
  }

  return { run };
})();

window.Loader = Loader;
