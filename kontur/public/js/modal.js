// ============================================================
// MODAL.JS — просмотр статьи, тело грузится с сервера v3.0
// ============================================================

const Modal = (() => {

  let _overlay, _titleEl, _metaEl, _bodyEl, _starsEl, _avgEl;
  let _currentId = null;

  function _init() {
    if (_overlay) return;
    _overlay = document.createElement('div');
    _overlay.id = 'modal-overlay';
    _overlay.innerHTML = `
      <div id="modal-win">
        <div class="modal-tb">
          <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-size:14px;">📄</span>
            <span id="modal-title">—</span>
          </div>
          <div class="wbtn" id="modal-close" style="font-size:11px;">✕</div>
        </div>
        <div class="modal-meta" id="modal-meta"></div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:11px;">Ваша оценка:</span>
            <div id="modal-stars"></div>
            <span id="modal-avg" class="avgr"></span>
          </div>
          <button class="bigbtn" id="modal-close-btn">Закрыть</button>
        </div>
      </div>`;
    document.body.appendChild(_overlay);

    _titleEl = document.getElementById('modal-title');
    _metaEl  = document.getElementById('modal-meta');
    _bodyEl  = document.getElementById('modal-body');
    _starsEl = document.getElementById('modal-stars');
    _avgEl   = document.getElementById('modal-avg');

    _overlay.addEventListener('click', e => { if (e.target === _overlay) close(); });
    document.getElementById('modal-close').addEventListener('click', close);
    document.getElementById('modal-close-btn').addEventListener('click', close);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && _overlay.classList.contains('open')) close();
    });
  }

  async function open(id) {
    _init();
    _currentId = id;
    _overlay.classList.add('open');

    _titleEl.textContent = 'Загрузка...';
    _bodyEl.textContent = '';
    _metaEl.innerHTML = '';
    _starsEl.innerHTML = '';
    _avgEl.textContent = '';

    try {
      const { article } = await API.getArticle(id);
      _titleEl.textContent = article.title;
      _metaEl.innerHTML = `
        <span class="atag ${article.tc}">${article.tag}</span>
        <span style="font-size:11px;flex:1;">${_esc(article.title)}</span>
        <span class="adate">Автор: ${article.author || '—'}</span>
        <span class="adate">${article.date}</span>`;
      _bodyEl.textContent = (article.body || '[ Текст отсутствует ]').replace(/\\n/g, '\n');

      if (article.my_rating) S.myRatings[id] = article.my_rating;
      _renderStars(id, article.avg_rating, article.ratings_count);
    } catch (e) {
      _titleEl.textContent = 'Ошибка';
      _bodyEl.textContent = 'Не удалось загрузить статью: ' + e.message;
    }
  }

  function close() {
    if (_overlay) _overlay.classList.remove('open');
    _currentId = null;
  }

  function _renderStars(id, avg, count) {
    const myR = S.myRatings[id] || 0;
    _starsEl.innerHTML = [1,2,3,4,5].map(i =>
      `<span class="star ${myR>=i?'lit':''}" data-r="${i}">★</span>`
    ).join('');
    _avgEl.textContent = avg > 0 ? avg : '';
    _avgEl.title = `Средняя: ${avg} (${count} оценок)`;

    _starsEl.querySelectorAll('.star').forEach(star => {
      star.addEventListener('click', async () => {
        if (!S.user) { Auth.openLogin(); return; }
        await Articles.rate(id, +star.dataset.r);
        const a = S.articles.find(x => x.id === id);
        _renderStars(id, a?.avg_rating || 0, a?.ratings_count || 0);
      });
      star.addEventListener('mouseenter', () => {
        const r = +star.dataset.r;
        _starsEl.querySelectorAll('.star').forEach((s,i) => s.classList.toggle('lit', i < r));
      });
      star.addEventListener('mouseleave', () => {
        const a = S.articles.find(x => x.id === id);
        _renderStars(id, a?.avg_rating || 0, a?.ratings_count || 0);
      });
    });
  }

  function _esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  return { open, close };
})();

window.Modal = Modal;
