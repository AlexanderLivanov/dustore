/* ============================================================
 * MINI · modal.js
 * Модальное окно: открытие, закрытие, рендер списка файлов.
 * ============================================================ */

const Modal = (() => {
  let backdrop, body, title;

  function init() {
    backdrop = document.getElementById('modalBackdrop');
    body = document.getElementById('modalBody');
    title = document.getElementById('modalTitle');

    document.getElementById('modalClose').addEventListener('click', close);
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !backdrop.classList.contains('hidden')) close();
    });
  }

  function open(titleText) {
    title.textContent = titleText || 'MINI';
    backdrop.classList.remove('hidden');
  }

  function close() {
    backdrop.classList.add('hidden');
    body.innerHTML = '';
  }

  function showLoading() {
    body.innerHTML =
      '<div class="modal-loading"><div class="typing-bubble"><span></span><span></span><span></span></div></div>';
  }

  function showEmpty(text) {
    body.innerHTML = '';
    const el = document.createElement('div');
    el.className = 'modal-empty';
    const mark = document.createElement('div');
    mark.className = 'brand-mark';
    mark.textContent = 'MINI';
    const msg = document.createElement('div');
    msg.textContent = text;
    el.append(mark, msg);
    body.appendChild(el);
  }

  function formatSize(bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return bytes + ' Б';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
    if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' МБ';
    return (bytes / 1024 / 1024 / 1024).toFixed(1) + ' ГБ';
  }

  /** Рендерит произвольный DOM-узел (формы, списки и т.д.). */
  function showNode(node) {
    body.innerHTML = '';
    body.appendChild(node);
  }

  /** Рендерит список файлов Яндекс.Диска. */
  function showFiles(items) {
    body.innerHTML = '';
    if (!items.length) {
      showEmpty('На Диске пока пусто — самое тихое место в интернете.');
      return;
    }
    const frag = document.createDocumentFragment();
    for (const f of items) {
      const row = document.createElement('div');
      row.className = 'file-row';

      const icon = document.createElement('div');
      icon.className = 'file-icon';
      icon.innerHTML =
        '<svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M6 3h8l4 4v14H6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';

      const meta = document.createElement('div');
      meta.className = 'file-meta';
      const name = document.createElement('div');
      name.className = 'file-name';
      name.textContent = f.name || '(без имени)';
      const path = document.createElement('div');
      path.className = 'file-path';
      path.textContent = f.path || '';
      meta.append(name, path);

      const size = document.createElement('div');
      size.className = 'file-size';
      size.textContent = formatSize(f.size);

      row.append(icon, meta, size);
      frag.appendChild(row);
    }
    body.appendChild(frag);
  }

  return { init, open, close, showLoading, showEmpty, showFiles, showNode };
})();
