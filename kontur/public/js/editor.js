// ============================================================
// EDITOR.JS — полноценный редактор статей
// Подход: plain-text разметка (Markdown-like), live preview.
// Синтаксис: == Заголовок ==, --- раздел ---, **жирный**,
//            >> цитата, [[ ПРЕДУПРЕЖДЕНИЕ ]]
// ============================================================

const Editor = (() => {

  let _mode = 'edit'; // 'edit' | 'preview'
  let _dlgEl, _areaEl, _previewEl;
  let _onSubmit = null;

  // ── Синтаксис → HTML ──────────────────────────────────────
  function _render(raw) {
    const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    return raw.split('\n').map(line => {
      const l = esc(line);

      // == Заголовок ==
      if (/^==\s*(.+?)\s*==$/.test(line)) {
        const m = line.match(/^==\s*(.+?)\s*==$/);
        return `<div style="font-weight:bold;font-size:13px;border-bottom:1px solid #808080;padding-bottom:2px;margin-bottom:4px;letter-spacing:1px;">${esc(m[1])}</div>`;
      }
      // --- РАЗДЕЛ ---
      if (/^---\s*(.+?)\s*---$/.test(line)) {
        const m = line.match(/^---\s*(.+?)\s*---$/);
        return `<div style="font-weight:bold;color:#000080;margin-top:8px;">${esc(m[1])}</div>`;
      }
      // [[ ПРЕДУПРЕЖДЕНИЕ ]]
      if (/^\[\[(.+?)\]\]$/.test(line)) {
        const m = line.match(/^\[\[(.+?)\]\]$/);
        return `<div style="background:#ffeeee;border:1px solid #800000;padding:4px 8px;margin:4px 0;font-weight:bold;color:#800000;">⚠ ${esc(m[1])}</div>`;
      }
      // >> Цитата
      if (/^>>\s*(.*)$/.test(line)) {
        const m = line.match(/^>>\s*(.*)$/);
        return `<div style="border-left:3px solid #808080;padding-left:8px;color:#404040;font-style:italic;">${esc(m[1])}</div>`;
      }
      // Пустая строка
      if (!line.trim()) return '<br>';

      // Инлайн: **жирный**, //курсив//, __подчёркнутый__, --зачёркнутый--
      let out = l;
      out = out.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
      out = out.replace(/\/\/(.+?)\/\//g, '<i>$1</i>');
      out = out.replace(/__(.+?)__/g, '<u>$1</u>');
      out = out.replace(/~~(.+?)~~/g, '<s>$1</s>');
      out = out.replace(/`(.+?)`/g, '<code style="background:#dfdfdf;padding:1px 3px;">$1</code>');

      return `<div>${out}</div>`;
    }).join('');
  }

  function _init() {
    if (_dlgEl) return;

    _dlgEl = document.createElement('div');
    _dlgEl.className = 'dialog';
    _dlgEl.id = 'dlg-editor';
    _dlgEl.innerHTML = `
      <div class="dlg-tb">
        <span>✎ Редактор материала</span>
        <div class="wbtn" id="ed-close" style="font-size:11px;">✕</div>
      </div>
      <div class="dlg-body" style="gap:7px;">
        <div class="dlg-row">
          <label>Заголовок:</label>
          <input id="ed-title" type="text" placeholder="Объект №103 — ...">
        </div>
        <div style="display:flex;gap:8px;">
          <div class="dlg-row" style="flex:1;">
            <label>Тип:</label>
            <select id="ed-type">
              <option value="ОБЪ">Объект</option>
              <option value="СУЩ">Сущность</option>
              <option value="ЭКСП">Эксперимент</option>
              <option value="ИСТ">История / Лор</option>
              <option value="ФАН">Фан-творчество</option>
            </select>
          </div>
        </div>
        <div class="editor-tabs">
          <div class="editor-tab active" id="et-edit" onclick="Editor.switchMode('edit')">Редактор</div>
          <div class="editor-tab" id="et-prev" onclick="Editor.switchMode('preview')">Превью</div>
          <div style="flex:1;"></div>
          <span style="font-size:9px;color:#808080;align-self:flex-end;padding:0 4px;">== заголовок == | --- раздел --- | **жирный** | [[ ВАЖНО ]]</span>
        </div>
        <div class="editor-toolbar" id="ed-toolbar">
          <button class="ed-btn" onclick="Editor.ins('**','**')" title="Жирный"><b>Ж</b></button>
          <button class="ed-btn" onclick="Editor.ins('//','//') " title="Курсив"><i>К</i></button>
          <button class="ed-btn" onclick="Editor.ins('__','__')" title="Подчёркнутый"><u>П</u></button>
          <button class="ed-btn" onclick="Editor.ins('~~','~~')" title="Зачёркнутый"><s>З</s></button>
          <button class="ed-btn" onclick="Editor.ins('\`','\`')" title="Код">{ }</button>
          <div style="width:1px;background:#808080;margin:0 4px;"></div>
          <button class="ed-btn" onclick="Editor.insLine('== ', ' ==')" title="Заголовок">H1</button>
          <button class="ed-btn" onclick="Editor.insLine('--- ', ' ---')" title="Раздел">—</button>
          <button class="ed-btn" onclick="Editor.insLine('[[ ', ' ]]')" title="Предупреждение">⚠</button>
          <button class="ed-btn" onclick="Editor.insLine('>> ', '')" title="Цитата">»</button>
        </div>
        <textarea class="editor-area" id="ed-area"
          placeholder="Напишите текст статьи...&#10;&#10;== Заголовок ==&#10;--- РАЗДЕЛ ---&#10;Обычный текст. **Жирный текст**.&#10;[[ ВАЖНАЯ ПОМЕТКА ]]"></textarea>
        <div class="editor-preview hidden" id="ed-preview"></div>
        <div style="font-size:10px;color:#808080;" id="ed-charcount">0 символов</div>
        <div class="dlg-btns">
          <button class="bigbtn" onclick="Editor.close()">Отмена</button>
          <button class="bigbtn blue" onclick="Editor.submit()">Отправить →</button>
        </div>
      </div>`;

    document.body.appendChild(_dlgEl);

    _areaEl    = document.getElementById('ed-area');
    _previewEl = document.getElementById('ed-preview');

    document.getElementById('ed-close').addEventListener('click', close);

    // Live char count + preview sync
    _areaEl.addEventListener('input', () => {
      document.getElementById('ed-charcount').textContent = _areaEl.value.length + ' символов';
      if (_mode === 'preview') _previewEl.innerHTML = _render(_areaEl.value);
    });
  }

  // ── Open ─────────────────────────────────────────────────
  function open(onSubmitCb) {
    _init();
    _onSubmit = onSubmitCb || null;

    document.getElementById('ed-title').value = '';
    _areaEl.value = '';
    _previewEl.innerHTML = '';
    document.getElementById('ed-charcount').textContent = '0 символов';
    switchMode('edit');

    document.getElementById('dlg-overlay').style.display = '';
    _dlgEl.classList.add('open');
    document.getElementById('ed-title').focus();
  }

  function close() {
    _dlgEl?.classList.remove('open');
    document.getElementById('dlg-overlay').style.display = 'none';
  }

  // ── Switch edit/preview mode ─────────────────────────────
  function switchMode(mode) {
    _mode = mode;
    document.getElementById('et-edit').classList.toggle('active', mode === 'edit');
    document.getElementById('et-prev').classList.toggle('active', mode === 'preview');
    document.getElementById('ed-toolbar').style.display = mode === 'edit' ? '' : 'none';

    if (mode === 'preview') {
      _areaEl.classList.add('hidden');
      _previewEl.classList.remove('hidden');
      _previewEl.innerHTML = _render(_areaEl.value);
    } else {
      _areaEl.classList.remove('hidden');
      _previewEl.classList.add('hidden');
      _areaEl.focus();
    }
  }

  // ── Insert inline markup ─────────────────────────────────
  function ins(before, after) {
    if (!_areaEl) return;
    const s = _areaEl.selectionStart;
    const e = _areaEl.selectionEnd;
    const sel = _areaEl.value.substring(s, e) || 'текст';
    const replacement = before + sel + after;
    _areaEl.setRangeText(replacement, s, e, 'end');
    _areaEl.focus();
    // Trigger charcount
    _areaEl.dispatchEvent(new Event('input'));
  }

  // ── Insert block/line markup ─────────────────────────────
  function insLine(before, after) {
    if (!_areaEl) return;
    const s = _areaEl.selectionStart;
    const lineStart = _areaEl.value.lastIndexOf('\n', s - 1) + 1;
    const lineEnd   = _areaEl.value.indexOf('\n', s);
    const end = lineEnd === -1 ? _areaEl.value.length : lineEnd;
    const cur = _areaEl.value.substring(lineStart, end) || 'Текст';
    _areaEl.setRangeText(before + cur + after, lineStart, end, 'end');
    _areaEl.focus();
    _areaEl.dispatchEvent(new Event('input'));
  }

  // ── Submit ───────────────────────────────────────────────
  async function submit() {
    const title = document.getElementById('ed-title').value.trim();
    const type  = document.getElementById('ed-type').value;
    const body  = _areaEl.value.trim();

    if (!title) {
      Toast.show('Введите заголовок!', 'warn');
      document.getElementById('ed-title').focus();
      return;
    }
    if (!body) {
      Toast.show('Текст статьи не может быть пустым', 'warn');
      return;
    }

    // Блокируем кнопку на время запроса
    const btn = document.querySelector('#dlg-editor .bigbtn.blue');
    if (btn) btn.disabled = true;

    const ok = await Articles.submit(title, type, body);

    if (btn) btn.disabled = false;
    if (ok) {
      close();
      if (_onSubmit) _onSubmit();
    }
  }

  return { open, close, switchMode, ins, insLine, submit };
})();

window.Editor = Editor;
