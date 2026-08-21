// ============================================================
// ARTICLES.JS — рендер + связь с API v3.0
// ============================================================
// Всё, что меняет данные, теперь async и ходит на сервер.
// После успешного ответа — перезагружаем и перерисовываем.
// ============================================================

const Articles = (() => {

  // ── Загрузка approved-статей с сервера ───────────────────
  async function load() {
    try {
      const { articles } = await API.listArticles('approved');
      S.articles = articles;
      renderAll();
      UI.updatePills();
    } catch (e) {
      Toast.show('Не удалось загрузить статьи: ' + e.message, 'error');
    }
  }

  // ── Рендер строки ────────────────────────────────────────
  function rowHTML(a, highlight = '') {
    const myR = S.myRatings[a.id] || 0;
    const av  = a.avg_rating || 0;

    const stars = [1,2,3,4,5].map(i =>
      `<span class="star ${myR >= i ? 'lit' : ''}" data-id="${a.id}" data-r="${i}" title="${i}">★</span>`
    ).join('');

    let title = _escHtml(a.title);
    if (highlight) {
      const re = new RegExp(`(${_escRegex(highlight)})`, 'gi');
      title = title.replace(re, '<mark class="search-hl">$1</mark>');
    }

    return `
      <div class="arow" data-id="${a.id}" title="ЛКМ — открыть | ПКМ — меню">
        <span class="atag ${a.tc}">${a.tag}</span>
        <span class="arow-title">${title}</span>
        <div class="stars">${stars}</div>
        ${av > 0 ? `<span class="avgr" title="${a.ratings_count} оценок">${av}</span>` : '<span class="avgr"></span>'}
        <span class="adate">${a.date}</span>
      </div>`;
  }

  function renderTo(elId, list, highlight = '') {
    const el = document.getElementById(elId);
    if (!el) return;
    if (!list.length) {
      el.innerHTML = '<div style="padding:8px;color:#808080;font-size:11px;">Записей нет</div>';
      return;
    }
    el.innerHTML = list.map(a => rowHTML(a, highlight)).join('');

    el.querySelectorAll('.arow').forEach(row => {
      const id = +row.dataset.id;
      row.addEventListener('click', e => {
        if (e.target.classList.contains('star')) return;
        Modal.open(id);
      });
      row.querySelectorAll('.star').forEach(star => {
        star.addEventListener('click', e => {
          e.stopPropagation();
          if (!S.user) { Auth.openLogin(); return; }
          rate(+star.dataset.id, +star.dataset.r);
        });
      });
      CtxMenu.bindArticleRow(row, id);
    });
  }

  function renderAll(highlight) {
    const q = highlight !== undefined ? highlight : S.searchQuery;
    const filtered = q
      ? S.articles.filter(a => a.title.toLowerCase().includes(q.toLowerCase()))
      : S.articles;
    renderTo('articles-list', filtered, q);
  }

  function renderByTag(elId, tag, highlight) {
    const q = highlight !== undefined ? highlight : S.searchQuery;
    let list = S.articles.filter(a => a.tag === tag);
    if (q) list = list.filter(a => a.title.toLowerCase().includes(q.toLowerCase()));
    renderTo(elId, list, q);
  }

  // ── Оценка (async → сервер) ──────────────────────────────
  async function rate(id, r) {
    if (!S.user) { Auth.openLogin(); return; }
    try {
      const res = await API.rate(id, r);
      S.myRatings[id] = res.my_rating;
      // Обновляем локальный кэш свежими агрегатами
      const a = S.articles.find(x => x.id === id);
      if (a) { a.avg_rating = res.avg_rating; a.ratings_count = res.ratings_count; }
      Toast.show(`Оценка ${r}★ сохранена`, 'success', 1800);
      _rerenderCurrent();
    } catch (e) {
      Toast.show('Ошибка оценки: ' + e.message, 'error');
    }
  }

  // ── Отправка нового материала (async → сервер) ───────────
  async function submit(title, type, body) {
    if (!S.user) return false;
    if (!title.trim()) return false;
    try {
      await API.createArticle(title.trim(), type, body.trim() || '[ Текст не предоставлен ]');
      // Обновляем профиль (счётчик подач вырос на сервере)
      await Auth.refresh();
      Toast.show('Материал отправлен на модерацию!', 'success');
      UI.updatePills();
      return true;
    } catch (e) {
      Toast.show('Ошибка отправки: ' + e.message, 'error');
      return false;
    }
  }

  function _rerenderCurrent() {
    renderAll();
    const t = S.activeTab;
    if (t === 'obj') renderByTag('obj-list', 'ОБЪ');
    if (t === 'exp') renderByTag('exp-list', 'ЭКСП');
    if (t === 'fan') renderByTag('fan-list', 'ФАН');
  }

  function _escHtml(s)  { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function _escRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }

  return { load, renderAll, renderByTag, renderTo, rate, submit };
})();

window.Articles = Articles;
