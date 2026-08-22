// ============================================================
// MODERATION.JS — очередь модерации через API v3.0
// ============================================================

const Moderation = (() => {

  // Загружаем pending (и rejected для админа) с сервера
  async function render() {
    if (!S.user || !['moderator','admin'].includes(S.user.role)) return;

    const pendEl = document.getElementById('mod-pending');
    if (pendEl) pendEl.innerHTML = '<div style="padding:8px;color:#808080;font-size:11px;">Загрузка...</div>';

    try {
      const { articles: pending } = await API.listArticles('pending');
      _renderList(pendEl, pending, 'pending');

      const isAdmin = S.user.role === 'admin';
      const archSec = document.getElementById('mod-archive-section');
      if (archSec) archSec.style.display = isAdmin ? '' : 'none';

      if (isAdmin) {
        const { articles: rejected } = await API.listArticles('rejected');
        _renderList(document.getElementById('mod-rejected'), rejected, 'rejected');
      }
    } catch (e) {
      if (pendEl) pendEl.innerHTML = `<div style="padding:8px;color:#800000;font-size:11px;">Ошибка: ${e.message}</div>`;
    }
  }

  function _renderList(el, list, kind) {
    if (!el) return;
    if (!list.length) {
      el.innerHTML = `<div style="padding:8px;color:#808080;font-size:11px;">${kind==='pending'?'Очередь пуста':'Архив пуст'}</div>`;
      return;
    }
    const isRejected = kind === 'rejected';
    el.innerHTML = list.map(a => `
      <div class="mod-row">
        <span class="atag ${a.tc}" ${isRejected?'style="opacity:.5;"':''}>${a.tag}</span>
        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${isRejected?'color:#808080;':''}">${_esc(a.title)}</span>
        <span style="font-size:9px;color:#606060;flex-shrink:0;">${a.author || '—'}</span>
        ${isRejected ? `
          <button class="mod-btn ap" data-act="restore" data-id="${a.id}">↑ Разместить</button>
          <button class="mod-btn ar" data-act="edit"    data-id="${a.id}">✎ Ред.</button>
          <button class="mod-btn rj" data-act="delete"  data-id="${a.id}">🗑</button>
        ` : `
          <button class="mod-btn ap" data-act="approve" data-id="${a.id}">✓ Одобрить</button>
          <button class="mod-btn ar" data-act="edit"    data-id="${a.id}">✎ Ред.</button>
          <button class="mod-btn rj" data-act="reject"  data-id="${a.id}">✕ Откл.</button>
        `}
      </div>`).join('');

    el.querySelectorAll('.mod-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = +btn.dataset.id;
        const act = btn.dataset.act;
        if (act === 'approve') approve(id);
        else if (act === 'reject') reject(id);
        else if (act === 'restore') approve(id);
        else if (act === 'delete') del(id);
        else if (act === 'edit') editTitle(id);
      });
    });
  }

  async function approve(id) {
    try {
      await API.moderate(id, 'approve');
      Toast.show('Материал одобрен', 'success');
      await Articles.load();
      render();
    } catch (e) { Toast.show('Ошибка: ' + e.message, 'error'); }
  }

  async function reject(id) {
    try {
      await API.moderate(id, 'reject');
      Toast.show('Материал отклонён', 'warn');
      await Articles.load();
      render();
    } catch (e) { Toast.show('Ошибка: ' + e.message, 'error'); }
  }

  async function del(id) {
    if (!confirm('Удалить материал навсегда?')) return;
    try {
      await API.deleteArticle(id);
      Toast.show('Запись удалена', 'error');
      render();
    } catch (e) { Toast.show('Ошибка: ' + e.message, 'error'); }
  }

  async function editTitle(id) {
    // Подтягиваем текущий заголовок
    let current = '';
    try { const { article } = await API.getArticle(id); current = article.title; }
    catch { /* ignore */ }
    const t = prompt('Редактировать заголовок:', current);
    if (t && t.trim()) {
      try {
        await API.editArticle(id, { title: t.trim() });
        Toast.show('Заголовок обновлён', 'info');
        await Articles.load();
        render();
      } catch (e) { Toast.show('Ошибка: ' + e.message, 'error'); }
    }
  }

  function _esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  return { render, approve, reject, del, editTitle };
})();

window.Moderation = Moderation;
