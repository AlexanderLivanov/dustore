/**
 * l4t/js/l4t.js — модальные редакторы профиля L4T.
 *
 * Главная правка — экранирование. Раньше значения подставлялись в разметку
 * как есть:
 *     `<input class="exp-role" value="${e.role}">`
 * Роль/ссылка/текст приходят из БД, туда их пишет сам пользователь. Достаточно
 * было сохранить роль вида  " onfocus=alert(1) x="  — и при открытии редактора
 * код выполнялся. Клиентская «санитайзация» .replace(/[<>"']/g,'') при
 * сохранении от этого не защищает: её видит только браузер того, кто сохраняет,
 * а в базу можно писать напрямую через endpoint.
 * Правильный порядок: экранируем при ВЫВОДЕ (здесь и в PHP), а не при вводе.
 */
const L4T = {

    /* Элементы ищем при первом обращении, а не при создании объекта.
       Раньше getElementById вызывался на этапе разбора скрипта: если файл
       подключён в <head>, все три ссылки оказывались null. */
    get modal()   { return document.getElementById('l4tModal'); },
    get content() { return document.getElementById('modalContent'); },
    get title()   { return document.getElementById('modalTitle'); },

    esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    /** URL для вставки в CSS url(...) и href. Пропускаем только http(s). */
    safeUrl(v) {
        const s = String(v == null ? '' : v).trim();
        return /^https?:\/\//i.test(s) ? s : '';
    },

    open(title, html, onSave) {
        if (!this.modal) return;
        this.title.textContent = title;
        this.content.innerHTML = html;
        this.modal.classList.remove('hidden');

        const save = document.getElementById('modalSave');
        if (save) {
            save.disabled = false;
            save.onclick = async () => {
                save.disabled = true;
                try { await onSave(); } finally { save.disabled = false; }
            };
        }

        /* Удаление строк — один делегат на всю модалку вместо обработчика
           на каждый крестик. Раньше крестики .del вообще ни к чему не были
           привязаны: нарисованы, но не работали. */
        this.content.onclick = e => {
            const del = e.target.closest('.del');
            if (!del) return;
            const row = del.closest('.exp-row, .file-row, .proj-row');
            if (row) row.remove();
        };
    },

    close() {
        if (this.modal) this.modal.classList.add('hidden');
    },

    async post(payload) {
        try {
            const r = await fetch('/swad/controllers/l4t/l4t_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await r.json();
            if (!data || data.success === false) {
                alert(data && data.message ? data.message : 'Не удалось сохранить');
                return null;
            }
            return data;
        } catch (e) {
            alert('Нет соединения с сервером');
            return null;
        }
    },

    /** Сохранили — закрываем и перезагружаем. Раньше reload шёл и при ошибке. */
    async saveAnd(payload) {
        const r = await this.post(payload);
        if (r) { this.close(); location.reload(); }
    },

    /* ===== ОПЫТ ===================================================== */
    editExp(current = []) {
        const row = (role = '', years = '') => `
          <div class="exp-row">
            <input class="exp-role l4t-input" maxlength="30" value="${this.esc(role)}" placeholder="Роль">
            <input class="exp-years l4t-input" type="number" min="0" max="60" value="${this.esc(years)}" placeholder="Лет">
            <span class="del" title="Удалить">×</span>
          </div>`;

        this.open('Опыт', `
          <div id="expWrap">${current.map(e => row(e.role, e.years)).join('')}</div>
          <button type="button" id="addExp" class="l4t-input" style="cursor:pointer">+ Добавить</button>
        `, () => {
            const data = [...document.querySelectorAll('.exp-row')]
                .map(r => ({
                    role:  r.querySelector('.exp-role').value.trim().slice(0, 30),
                    years: Math.max(0, Math.min(60, parseInt(r.querySelector('.exp-years').value, 10) || 0))
                }))
                .filter(e => e.role !== '');
            return this.saveAnd({ type: 'exp', data });
        });

        document.getElementById('addExp').onclick = () =>
            document.getElementById('expWrap').insertAdjacentHTML('beforeend', row());
    },

    /* ===== ФАЙЛЫ / ССЫЛКИ =========================================== */
    editFiles(current = []) {
        const row = (type = 'link', value = '', name = '') => `
          <div class="file-row">
            <select class="ftype l4t-select">
              <option value="link"${type === 'link' ? ' selected' : ''}>ссылка</option>
              <option value="file"${type === 'file' ? ' selected' : ''}>файл</option>
            </select>
            <input class="fname l4t-input" maxlength="40" value="${this.esc(name)}" placeholder="Название">
            <input class="fval l4t-input" maxlength="200" value="${this.esc(value)}" placeholder="https://…">
            <span class="del" title="Удалить">×</span>
          </div>`;

        this.open('Доп. данные', `
          <div id="fileWrap">${current.map(f => row(f.type, f.value, f.name)).join('')}</div>
          <button type="button" id="addFile" class="l4t-input" style="cursor:pointer">+ Добавить</button>
        `, () => {
            const data = [...document.querySelectorAll('.file-row')]
                .map(r => ({
                    type:  r.querySelector('.ftype').value === 'file' ? 'file' : 'link',
                    name:  r.querySelector('.fname').value.trim().slice(0, 40),
                    value: r.querySelector('.fval').value.trim().slice(0, 200)
                }))
                .filter(f => f.value !== '');
            return this.saveAnd({ type: 'files', data });
        });

        document.getElementById('addFile').onclick = () =>
            document.getElementById('fileWrap').insertAdjacentHTML('beforeend', row());
    },

    /* ===== ПРОЕКТЫ ================================================== */
    editProjects(current = []) {
        /* Обложка кладётся в data-cover сразу, а не только после ответа
           game_preview. Раньше у уже сохранённых проектов она была только
           в inline-style, dataset пустой — и при следующем сохранении
           обложка терялась. */
        const row = (url = '', cover = '', title = '') => {
            const c = this.safeUrl(cover);
            return `
          <div class="proj-row">
            <input class="plink l4t-input" maxlength="300" value="${this.esc(url)}" placeholder="https://dustore.ru/g/123">
            <input class="ptitle l4t-input" maxlength="60" value="${this.esc(title)}" placeholder="Название">
            <div class="preview" data-cover="${this.esc(c)}"
                 style="${c ? `background-image:url('${this.esc(c)}');background-size:cover;background-position:center` : ''}"></div>
            <span class="del" title="Удалить">×</span>
          </div>`;
        };

        this.open('Проекты', `
          <div id="projWrap">${current.map(p => row(p.url, p.cover, p.title)).join('')}</div>
          <button type="button" id="addProj" class="l4t-input" style="cursor:pointer">+ Добавить</button>
        `, () => {
            const data = [...document.querySelectorAll('.proj-row')]
                .map(r => ({
                    url:   r.querySelector('.plink').value.trim().slice(0, 300),
                    title: r.querySelector('.ptitle').value.trim().slice(0, 60),
                    cover: r.querySelector('.preview').dataset.cover || ''
                }))
                .filter(p => p.url !== '');
            return this.saveAnd({ type: 'projects', data });
        });

        document.getElementById('addProj').onclick = () =>
            document.getElementById('projWrap').insertAdjacentHTML('beforeend', row());

        /* Подтягивание обложки по ссылке на игру.
           Раньше addEventListener('change') вешался на .content при КАЖДОМ
           открытии модалки и никогда не снимался: на пятый заход один ввод
           ссылки давал пять запросов к game_preview. Теперь onchange —
           присваивание, старый обработчик заменяется. */
        this.content.onchange = e => {
            if (!e.target.classList.contains('plink')) return;
            const m = e.target.value.match(/dustore\.ru\/g\/(\d+)/);
            if (!m) return;

            fetch('/api/game_preview.php?id=' + encodeURIComponent(m[1]))
                .then(r => r.json())
                .then(g => {
                    const cover = this.safeUrl(g && g.cover_url);
                    if (!cover) return;
                    const pr = e.target.closest('.proj-row').querySelector('.preview');
                    pr.dataset.cover = cover;
                    pr.style.backgroundImage = `url('${cover.replace(/'/g, '%27')}')`;
                    pr.style.backgroundSize = 'cover';
                    pr.style.backgroundPosition = 'center';
                    const t = e.target.closest('.proj-row').querySelector('.ptitle');
                    if (t && !t.value && g.name) t.value = String(g.name).slice(0, 60);
                })
                .catch(() => {});
        };
    },

    /* ===== О СЕБЕ =================================================== */
    editAbout(text = '') {
        /* `<textarea>${text}</textarea>` без экранирования — самый простой
           способ выйти из тега: достаточно написать в «о себе» закрывающий
           </textarea>. */
        this.open('О себе', `
          <textarea id="aboutText" class="l4t-textarea" maxlength="1000"
                    placeholder="Пара слов о себе">${this.esc(text)}</textarea>
        `, () => {
            const v = document.getElementById('aboutText').value.trim().slice(0, 1000);
            return this.saveAnd({ type: 'about', data: v });
        });
    }
};