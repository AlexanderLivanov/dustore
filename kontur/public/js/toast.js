// ============================================================
// TOAST.JS — Win95-стиль уведомления
// Типы: 'info' | 'success' | 'warn' | 'error'
// ============================================================

const Toast = (() => {

  let _container;

  function _init() {
    if (_container) return;
    _container = document.createElement('div');
    _container.id = 'toast-container';
    document.body.appendChild(_container);
  }

  // Иконки и заголовки по типу
  const META = {
    info:    { icon: 'ℹ', title: 'Информация' },
    success: { icon: '✓', title: 'Успешно' },
    warn:    { icon: '⚠', title: 'Внимание' },
    error:   { icon: '✕', title: 'Ошибка' },
  };

  /**
   * show(message, type?, duration?)
   * @param {string} message
   * @param {'info'|'success'|'warn'|'error'} type
   * @param {number} duration  ms (0 = sticky)
   */
  function show(message, type = 'info', duration = 3000) {
    _init();
    const m = META[type] || META.info;

    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `
      <div class="toast-tb">
        <span>${m.icon}</span>
        <span>${m.title}</span>
      </div>
      <div class="toast-body">${message}</div>`;

    _container.appendChild(el);

    if (duration > 0) {
      setTimeout(() => dismiss(el), duration);
    }

    // Click to dismiss early
    el.addEventListener('click', () => dismiss(el));

    return el;
  }

  function dismiss(el) {
    if (!el || !el.parentNode) return;
    el.classList.add('hide');
    setTimeout(() => el.remove(), 220);
  }

  return { show, dismiss };

})();

window.Toast = Toast;
