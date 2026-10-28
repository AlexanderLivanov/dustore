<?php /* devs/includes/mon_assets.php — стили и скрипт страницы монетизации (всё под .mn-*) */ ?>
<style>
    .alert .material-icons { font-size: 18px; flex: none; }
    .btn[disabled] { opacity: .45; cursor: not-allowed; }

    .mn-hero {
        display: flex; align-items: center; gap: 20px; margin-bottom: 16px; padding: 22px 24px; border-radius: 14px;
        background: linear-gradient(120deg, rgba(195, 33, 120, .22), rgba(110, 40, 200, .14) 60%, rgba(26, 10, 36, .6));
        border: 1px solid rgba(195, 33, 120, .3);
    }
    .mn-hero-wait { background: linear-gradient(120deg, rgba(255, 170, 0, .14), rgba(26, 10, 36, .6)); border-color: rgba(255, 170, 0, .25); }
    .mn-hero-title { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
    .mn-hero-sub { font-size: 13px; color: var(--ts); line-height: 1.55; max-width: 640px; }
    .mn-hero-ico { margin-left: auto; font-size: 56px; color: rgba(255, 255, 255, .18); }

    .mn-wizard { padding: 22px; }
    .mn-steps { display: flex; gap: 6px; list-style: none; counter-reset: s; margin-bottom: 22px; flex-wrap: wrap; }
    .mn-steps li {
        counter-increment: s; display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px;
        border-radius: 20px; font-size: 12px; font-weight: 600; color: var(--tm); background: var(--elev);
    }
    .mn-steps li::before {
        content: counter(s); width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center;
        background: rgba(255, 255, 255, .06); font-size: 11px;
    }
    .mn-steps li.on { color: #fff; background: rgba(195, 33, 120, .2); }
    .mn-steps li.on::before { background: var(--p); }
    .mn-steps li.done { color: var(--ts); }
    .mn-steps li.done::before { content: '✓'; background: rgba(0, 214, 143, .2); color: var(--ok); }
    .mn-step[hidden], .mn-pane[hidden], [data-only][hidden] { display: none !important; }
    .mn-h { font-size: 16px; font-weight: 700; margin-bottom: 14px; }

    .mn-types { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .mn-type {
        position: relative; display: flex; flex-direction: column; gap: 6px; padding: 18px; border-radius: 12px; cursor: pointer;
        background: var(--elev); border: 1px solid var(--bd); transition: border-color .15s, background .15s;
    }
    .mn-type input { position: absolute; opacity: 0; }
    .mn-type .material-icons { font-size: 28px; color: var(--tm); }
    .mn-type b { font-size: 15px; }
    .mn-type small { font-size: 12px; color: var(--tm); line-height: 1.5; }
    .mn-type:hover { border-color: rgba(195, 33, 120, .4); }
    .mn-type:has(input:checked) { border-color: var(--p); background: rgba(195, 33, 120, .1); }
    .mn-type:has(input:checked) .material-icons { color: var(--pl); }

    .mn-tax { display: flex; flex-direction: column; gap: 8px; }
    .mn-opt {
        display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 10px; cursor: pointer;
        background: var(--elev); border: 1px solid var(--bd);
    }
    .mn-opt input { accent-color: var(--p); margin-top: 3px; }
    .mn-opt b { display: block; font-size: 13px; }
    .mn-opt small { font-size: 12px; color: var(--tm); }
    .mn-opt:has(input:checked) { border-color: var(--p); background: rgba(195, 33, 120, .08); }

    .mn-err { font-size: 11px; color: var(--err); margin-top: 4px; }
    .field input.mn-bad, .mn-accept.mn-bad { border-color: rgba(255, 61, 113, .6) !important; }
    .field input.mn-good { border-color: rgba(0, 214, 143, .45); }
    .mn-note { font-size: 12px; color: var(--tm); line-height: 1.55; margin-top: 8px; }
    .mn-muted { color: var(--tm); font-size: 12px; }

    .mn-summary { margin-bottom: 14px; }
    .mn-offer {
        max-height: 300px; overflow-y: auto; padding: 16px 18px; border-radius: 10px; background: var(--dark); border: 1px solid var(--bd);
        font-size: 12px; line-height: 1.6; color: var(--ts);
    }
    .mn-offer h3 { font-size: 15px; color: #fff; margin-bottom: 2px; }
    .mn-offer h4 { font-size: 13px; color: var(--tt); margin: 14px 0 4px; }
    .mn-offer p { margin: 4px 0; }
    .mn-offer-meta { color: var(--tm); }
    .mn-accept {
        display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; padding: 12px 14px; border-radius: 10px;
        border: 1px solid var(--bd); background: var(--elev); font-size: 13px; cursor: pointer;
    }
    .mn-accept input { accent-color: var(--p); width: 16px; height: 16px; margin-top: 1px; flex: none; }

    .mn-nav { display: flex; gap: 10px; justify-content: space-between; margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--bd); }
    .mn-nav #mn-next, .mn-nav #mn-submit { margin-left: auto; }
    .mn-nav [hidden] { display: none; }

    .mn-tabs {
        position: sticky; top: -20px; z-index: 5; display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none;
        margin: 0 -20px 16px; padding: 10px 20px; background: rgba(20, 4, 29, .92);
        -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); border-bottom: 1px solid var(--bd);
    }
    .mn-tab {
        display: inline-flex; align-items: center; gap: 6px; flex: none; padding: 7px 12px; border-radius: 8px;
        border: 1px solid transparent; color: var(--ts); font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap;
    }
    .mn-tab .material-icons { font-size: 16px; }
    .mn-tab:hover { background: var(--elev); color: #fff; }
    .mn-tab.on { background: rgba(195, 33, 120, .14); border-color: rgba(195, 33, 120, .35); color: var(--pl); }

    .ed-cols-mn { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: start; }
    .ed-cols-mn .col-span { grid-column: 1 / -1; }
    @media (max-width: 900px) { .ed-cols-mn { grid-template-columns: 1fr; } }
    @media (max-width: 860px) { .mn-tabs { top: -16px; margin: 0 -16px 14px; padding: 10px 16px; } }

    .mn-dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 13px; margin: 0 0 4px; }
    .mn-dl dt { color: var(--tm); }
    .mn-dl dd { margin: 0; word-break: break-word; }
    .mn-check { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
    .mn-check li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--tm); }
    .mn-check li .material-icons { font-size: 18px; }
    .mn-check li.ok { color: var(--tt); }
    .mn-check li.ok .material-icons { color: var(--ok); }

    .mn-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .mn-table th { text-align: left; color: var(--tm); font-weight: 600; padding: 6px 8px; border-bottom: 1px solid var(--bd); }
    .mn-table td { padding: 8px; border-bottom: 1px solid var(--bd); color: var(--ts); }

    .mn-aggs { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
    .mn-agg {
        display: flex; flex-direction: column; gap: 4px; padding: 16px; border-radius: 12px; cursor: pointer; text-align: left;
        background: var(--surf); border: 1px solid var(--bd); color: var(--tt); font-family: inherit;
    }
    .mn-agg b { font-size: 15px; }
    .mn-agg small { font-size: 11px; color: var(--tm); }
    .mn-agg:hover:not([disabled]) { border-color: rgba(195, 33, 120, .45); }
    .mn-agg.on { border-color: var(--p); background: rgba(195, 33, 120, .1); }
    .mn-agg.on small { color: var(--pl); }
    .mn-agg[disabled]:not(.on) { opacity: .45; cursor: default; }
    .mn-agg-step.locked { opacity: .55; }
    .mn-agg-step.done { border-color: rgba(0, 214, 143, .3); }
    .mn-num {
        width: 22px; height: 22px; border-radius: 50%; display: inline-grid; place-items: center; font-size: 11px;
        background: var(--p); color: #fff; flex: none;
    }
    .mn-agg-step.done .mn-num { background: var(--ok); }
    .mn-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .mn-test-state { display: flex; align-items: center; gap: 8px; margin-top: 12px; font-size: 12px; color: var(--ts); }
    .mn-test-state .material-icons { font-size: 18px; }
    .mn-copy, .mn-input {
        width: 100%; background: var(--elev); border: 1px solid var(--bd); border-radius: 8px; padding: 8px 12px;
        color: var(--tt); font: 12px 'JetBrains Mono', monospace;
    }
    .mn-input { font: 13px 'Inter', sans-serif; }

    .mn-switch { position: relative; display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 12px 0 0; font-size: 13px; font-weight: 600; }
    .mn-switch input { position: absolute; opacity: 0; }
    .mn-switch-ui { position: relative; flex: none; width: 36px; height: 20px; border-radius: 10px; background: var(--elev); border: 1px solid rgba(255, 255, 255, .12); transition: background .18s; }
    .mn-switch-ui::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--tm); transition: transform .18s, background .18s; }
    .mn-switch input:checked + .mn-switch-ui { background: var(--p); border-color: var(--p); }
    .mn-switch input:checked + .mn-switch-ui::after { transform: translateX(16px); background: #fff; }
    .mn-switch input:focus-visible + .mn-switch-ui { outline: 2px solid var(--pl); outline-offset: 2px; }
    .mn-switch.off { opacity: .5; cursor: not-allowed; }

    .mn-sub { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--ts); margin: 16px 0 8px; }
    .mn-sub b { color: var(--pl); font-size: 14px; }
    .mn-seg { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 4px; border-radius: 10px; background: var(--elev); }
    .mn-seg label { cursor: pointer; }
    .mn-seg input { position: absolute; opacity: 0; }
    .mn-seg span { display: block; padding: 8px 10px; border-radius: 7px; font-size: 12px; text-align: center; color: var(--ts); }
    .mn-seg input:checked + span { background: var(--p); color: #fff; }
    .mn-range { width: 100%; accent-color: var(--p); }
    .mn-range:disabled { opacity: .35; }
    .mn-range-lbl { display: flex; justify-content: space-between; font-size: 11px; color: var(--tm); }
    .mn-link { background: none; border: 0; color: var(--pl); font: inherit; font-weight: 500; cursor: pointer; margin-left: auto; padding: 0; }

    .mn-calc { display: grid; grid-template-columns: 160px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 700px) { .mn-calc { grid-template-columns: 1fr; } }
    .mn-calc-out { display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
    .mn-calc-out .r { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px dashed var(--bd); color: var(--ts); }
    .mn-calc-out .r b { color: var(--tt); font-variant-numeric: tabular-nums; }
    .mn-calc-out .r.big { border: 0; font-size: 15px; color: #fff; }
    .mn-calc-out .r.big b { color: var(--pl); font-size: 20px; }
    .mn-calc-out .hint { font-size: 11px; color: var(--warn); }
    .mn-big { font-size: 32px; font-weight: 700; margin: 4px 0 12px; font-variant-numeric: tabular-nums; }

    @keyframes fxFlash { 0% { box-shadow: 0 0 0 3px var(--p, #c32178); } 100% { box-shadow: 0 0 0 3px transparent; } }
    .fx-flash { animation: fxFlash 1.8s ease-out; border-radius: 12px; }
</style>

<script>
window.MN = (function () {
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var rub = function (v) { return Math.round(v).toLocaleString('ru-RU') + ' ₽'; };
    var digits = function (s) { return String(s || '').replace(/\D/g, ''); };

    // ── те же контрольные суммы, что в mon/lib.php: ошибку видно ещё до отправки ──
    function innOk(s) {
        var d = s.split('').map(Number);
        var chk = function (w) { return w.reduce(function (a, k, i) { return a + k * d[i]; }, 0) % 11 % 10; };
        if (s.length === 10) return chk([2, 4, 10, 3, 5, 9, 4, 6, 8]) === d[9];
        if (s.length === 12) return chk([7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === d[10] && chk([3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === d[11];
        return false;
    }
    function modOk(s, len, m) { return s.length === len && Number(BigInt(s.slice(0, len - 1)) % BigInt(m) % 10n) === +s[len - 1]; }
    function keyOk(pre, acc) {
        var s = pre + acc, w = [7, 1, 3], sum = 0;
        if (s.length !== 23) return false;
        for (var i = 0; i < 23; i++) sum += (+s[i] * w[i % 3]) % 10;
        return sum % 10 === 0;
    }
    function bikOf(el) { var f = el.closest('form'); return digits(($('[name="bank[bik]"]', f) || {}).value); }
    var CHECKS = {
        inn:     function (v) { return innOk(v) || 'ИНН не сходится: проверьте цифры'; },
        ogrn:    function (v) { return modOk(v, 13, 11) || 'ОГРН — 13 цифр, контрольная сумма не сходится'; },
        ogrnip:  function (v) { return modOk(v, 15, 13) || 'ОГРНИП — 15 цифр, контрольная сумма не сходится'; },
        bik:     function (v) { return /^04\d{7}$/.test(v) || 'БИК — 9 цифр, начинается с 04'; },
        ks:      function (v, el) { var b = bikOf(el); return !b || (v.length === 20 && keyOk('0' + b.slice(4, 6), v)) || 'Корр. счёт не сходится с БИК'; },
        account: function (v, el) { var b = bikOf(el); return !b || (v.length === 20 && keyOk(b.slice(-3), v)) || 'Счёт не сходится с БИК — проверьте цифры'; },
    };
    function check(el) {
        var v = digits(el.value), box = el.closest('.field');
        var old = box.querySelector('.mn-err.js');
        if (old) old.remove();
        el.classList.remove('mn-bad', 'mn-good');
        if (!v) return true;
        var r = CHECKS[el.dataset.check](v, el);
        if (r === true) { el.classList.add('mn-good'); return true; }
        el.classList.add('mn-bad');
        var e = document.createElement('div');
        e.className = 'mn-err js';
        e.textContent = r;
        box.appendChild(e);
        return false;
    }

    // ── анкета ──
    function wizard() {
        var f = $('#mn-wizard');
        if (!f) return;
        var step = 1;
        var type = function () { var r = $('input[name=type]:checked', f); return r ? r.value : ''; };
        var steps = function () { return type() === 'npd' ? [1, 2, 4, 5] : [1, 2, 3, 4, 5]; };

        function applyType() {
            var t = type();
            $$('[data-only]', f).forEach(function (el) {
                var on = !t || el.dataset.only.split(' ').indexOf(t) !== -1;
                if (el.classList.contains('mn-step')) return;          // шаг показывает go()
                el.hidden = !on;
                $$('input, select, textarea', el).forEach(function (i) { i.disabled = !on; }); // скрытое не уходит на сервер
            });
            $$('[data-osno-ooo]', f).forEach(function (s) {
                if (!s.dataset.ip) s.dataset.ip = s.textContent;
                if (s.dataset.osnoOoo) s.textContent = t === 'ooo' ? s.dataset.osnoOoo : s.dataset.ip;
            });
            if (t === 'ooo' && $('input[name="tax[system]"][value=psn]', f).checked) $('input[name="tax[system]"][value=psn]', f).checked = false;
            vat();
        }

        function vat() {
            var sel = $('#mn-vat'), sys = $('input[name="tax[system]"]:checked', f);
            var opts = sys ? (CFG.vat[sys.value] || []) : [];   // [[значение, подпись], …] — порядок как на сервере
            var cur = sel.value || sel.dataset.value;
            sel.innerHTML = '';
            opts.forEach(function (kv) {
                var o = document.createElement('option');
                o.value = kv[0]; o.textContent = kv[1]; o.selected = String(kv[0]) === cur;
                sel.appendChild(o);
            });
            $('#mn-vat-wrap').hidden = !sys;
        }

        function stepOk(n) {
            var sec = $('.mn-step[data-step="' + n + '"]', f), ok = true;
            if (n === 1 && !type()) { alert('Выберите: ИП, ООО или самозанятость'); return false; }
            if (n === 3 && !$('input[name="tax[system]"]:checked', f)) { alert('Выберите систему налогообложения'); return false; }
            $$('input:not([type=radio]):not([type=checkbox]):not([disabled])', sec).forEach(function (i) {
                if (i.dataset.check && !check(i)) ok = false;
                if (!i.value.trim()) { i.classList.add('mn-bad'); ok = false; }
            });
            if (!ok) { var b = $('.mn-bad', sec); if (b) b.focus(); }
            return ok;
        }

        function summary() {
            var g = function (n) { var el = $('[name="' + n + '"]:not([disabled])', f); return el ? el.value : ''; };
            var t = type(), sys = $('input[name="tax[system]"]:checked', f);
            var rows = [
                ['Форма', { ip: 'ИП', ooo: 'ООО', npd: 'Самозанятый' }[t]],
                [t === 'ooo' ? 'Наименование' : 'ФИО', t === 'ooo' ? g('legal[name]') : g('legal[fio]')],
                ['ИНН', g('legal[inn]')],
                t === 'npd' ? null : ['Налоги', sys ? sys.closest('label').querySelector('b').textContent + ', ' + ($('#mn-vat').selectedOptions[0] || {}).textContent : ''],
                ['Счёт', g('bank[account]') + ' · ' + g('bank[bank_name]')],
            ].filter(Boolean);
            $('#mn-summary').innerHTML = '<dl class="mn-dl"></dl>';
            rows.forEach(function (r) {
                var dt = document.createElement('dt'), dd = document.createElement('dd');
                dt.textContent = r[0]; dd.textContent = r[1] || '—';
                $('#mn-summary dl').append(dt, dd);
            });
            $('#mn-recipient').textContent = (t === 'ooo' ? g('legal[name]') : g('legal[fio]')) || 'владелец анкеты';
        }

        function go(n) {
            var list = steps();
            step = n;
            $$('.mn-step', f).forEach(function (s) { s.hidden = +s.dataset.step !== n; });
            $$('.mn-steps li', f).forEach(function (li) {
                var k = +li.dataset.step;
                li.hidden = list.indexOf(k) === -1;
                li.classList.toggle('on', k === n);
                li.classList.toggle('done', list.indexOf(k) !== -1 && list.indexOf(k) < list.indexOf(n));
            });
            $$('.mn-offer', f).forEach(function (o) { o.hidden = o.dataset.only !== type(); });
            if (n === 5) summary();
            $('#mn-prev').style.visibility = n === 1 ? 'hidden' : '';
            $('#mn-next').hidden = n === 5;
            $('#mn-submit').hidden = n !== 5;
            f.scrollIntoView({ block: 'start' });
        }

        $('#mn-next').addEventListener('click', function () {
            if (!stepOk(step)) return;
            var l = steps(); go(l[l.indexOf(step) + 1]);
        });
        $('#mn-prev').addEventListener('click', function () { var l = steps(); go(l[Math.max(0, l.indexOf(step) - 1)]); });
        $$('.mn-steps li', f).forEach(function (li) {
            li.style.cursor = 'pointer';
            li.addEventListener('click', function () { if (li.classList.contains('done')) go(+li.dataset.step); });
        });
        f.addEventListener('change', function (e) {
            if (e.target.name === 'type') applyType();
            if (e.target.name === 'tax[system]') vat();
        });
        f.addEventListener('input', function (e) { e.target.classList.remove('mn-bad'); });

        applyType();
        // после ошибки сервера — сразу на первый шаг с ошибкой
        var bad = $('.mn-bad, .mn-err', f);
        go(bad ? +bad.closest('.mn-step').dataset.step : 1);
    }

    // ── цена и сбор: тот же расчёт, что mon_price() ──
    function price(p, base) {
        base = Math.max(0, Math.round(base));
        var pct = (p.fee_off && CFG.income < CFG.free_upto) ? 0 : p.fee_pct;
        var fee = base > 0 && pct > 0 ? Math.max(1, Math.round(base * pct / 100)) : 0;
        var sub = base + (p.fee_payer !== 'dev' ? fee : 0), total = sub;
        if (p.tax_on && sub > 0) total = Math.ceil(sub / (1 - p.tax_pct / 100));
        return { base: base, pct: pct, fee: fee, tax: total - sub, total: total, net: total - fee };
    }
    function readPricing() {
        var f = $('#mn-pricing');
        return {
            fee_payer: $('input[name=fee_payer]:checked', f).value,
            fee_pct: +$('#mn-fee').value, fee_off: $('#mn-fee-off').checked ? 1 : 0,
            tax_on: $('#mn-tax-on').checked ? 1 : 0, tax_pct: +$('#mn-tax').value,
        };
    }
    function row(label, val, cls) { return '<div class="r ' + (cls || '') + '"><span>' + label + '</span><b>' + val + '</b></div>'; }
    function pricing() {
        var f = $('#mn-pricing');
        if (!f) return;
        function upd() {
            var p = readPricing(), r = price(p, +$('#mn-ex').value || 0);
            $('#mn-fee-val').textContent = p.fee_off && r.pct === 0 ? 'выкл' : p.fee_pct + '%';
            $('#mn-tax-val').textContent = p.tax_on ? p.tax_pct + '%' : 'выкл';
            $('#mn-fee').disabled = !!(p.fee_off && r.pct === 0);
            $('#mn-tax').disabled = !p.tax_on;
            $('#mn-calc-out').innerHTML =
                row('Игрок платит', rub(r.total), 'big') +
                row('в том числе цена игры', rub(r.base)) +
                (p.fee_payer !== 'dev' && r.fee ? row('сбор платформы', '+' + rub(r.fee)) : '') +
                (r.tax ? row('надбавка на ваш налог', '+' + rub(r.tax)) : '') +
                row('Поступит на ваш счёт', rub(r.total)) +
                row('Спишется с баланса (сбор ' + r.pct + '%)', '−' + rub(r.fee)) +
                row('Остаётся вам до налогов', rub(r.net));
        }
        f.addEventListener('input', upd);
        f.addEventListener('change', upd);
        $('#mn-ex').addEventListener('input', upd);
        $('#mn-tax-suggest').addEventListener('click', function () {
            $('#mn-tax').value = this.dataset.v; $('#mn-tax-on').checked = true; upd();
        });
        upd();
    }

    // ── калькулятор баланса на месяц ──
    function balanceCalc() {
        var out = $('#mn-bc-out');
        if (!out) return;
        function upd() {
            var p = CFG.profile, qty = Math.max(0, +$('#mn-bc-qty').value || 0);
            var r = price(p, +$('#mn-bc-price').value || 0), revenue = r.total * qty;
            var feeOffNow = p.fee_off && CFG.income < CFG.free_upto;
            var monthFee = r.fee * qty;
            if (feeOffNow) {   // сбор выключен, но включится, когда доход за месяц дойдёт до порога
                var full = price(Object.assign({}, p, { fee_off: 0 }), r.base);
                var freeQty = Math.min(qty, Math.ceil(Math.max(0, CFG.free_upto - CFG.income) / Math.max(1, full.total)));
                monthFee = full.fee * (qty - freeQty);
            }
            var taxPct = p.tax_pct, need = Math.max(0, monthFee - CFG.balance.total);
            out.innerHTML =
                row('Выручка за месяц', rub(revenue)) +
                row('Сбор платформы за месяц', rub(monthFee)) +
                row('Ваш налог (≈' + taxPct + '% с выручки)', rub(revenue * taxPct / 100)) +
                row('На балансе сейчас', rub(CFG.balance.total)) +
                row(need > 0 ? 'Пополнить, чтобы хватило на месяц' : 'Баланса хватит на месяц', need > 0 ? rub(need) : '✓', 'big') +
                (feeOffNow ? '<div class="hint">Сбор сейчас выключен: он включится, когда доход за месяц дойдёт до ' + rub(CFG.free_upto) + ' — это уже учтено.</div>' : '');
        }
        ['#mn-bc-price', '#mn-bc-qty'].forEach(function (s) { $(s).addEventListener('input', upd); });
        upd();
    }

    // ── тестовая оплата: окно открываем сразу по клику, иначе браузер заблокирует его после await ──
    function testPay() {
        var btn = $('#mn-testpay');
        if (!btn) return;
        var state = $('#mn-test-state');
        function api(body) {
            return fetch('/devs/mon_api.php', {
                method: 'POST', credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
                body: JSON.stringify(body),
            }).then(function (r) { return r.json(); });
        }
        btn.addEventListener('click', function () {
            var win = window.open('', '_blank');
            btn.disabled = true;
            state.innerHTML = '<span class="material-icons">sync</span>Создаём тестовый платёж…';
            api({ action: 'test_create' }).then(function (d) {
                if (!d.ok) throw new Error(d.error || 'Ошибка');
                if (win) {
                    win.location = d.url;
                    state.innerHTML = '<span class="material-icons">hourglass_top</span>Ждём оплату в открывшейся вкладке…';
                } else {   // окно заблокировали — даём ссылку и всё равно ждём
                    state.innerHTML = '<span class="material-icons">open_in_new</span><a href="' + d.url + '" target="_blank" style="color:var(--pl)">Открыть страницу оплаты</a> — ждём оплату…';
                }
                var started = Date.now();
                var t = setInterval(function () {
                    api({ action: 'test_status', id: d.id }).then(function (s) {
                        if (s.status === 'succeeded') {
                            clearInterval(t);
                            state.innerHTML = '<span class="material-icons" style="color:var(--ok)">check_circle</span>Тестовая оплата прошла!';
                            setTimeout(function () { location.href = '/devs/monetization?tab=agg'; }, 1200);
                        } else if (s.status === 'canceled' || Date.now() - started > 15 * 60 * 1000) {
                            clearInterval(t);
                            btn.disabled = false;
                            state.innerHTML = '<span class="material-icons" style="color:var(--err)">cancel</span>Оплата не прошла — попробуйте ещё раз';
                        }
                    }).catch(function () {});
                }, 3000);
            }).catch(function (e) {
                if (win) win.close();
                btn.disabled = false;
                state.innerHTML = '<span class="material-icons" style="color:var(--err)">error</span>' + e.message;
            });
        });
    }

    // ── вкладки без перезагрузки ──
    function tabs() {
        $$('.mn-tab').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                $$('.mn-tab').forEach(function (t) { t.classList.toggle('on', t === a); });
                $$('.mn-pane').forEach(function (p) { p.hidden = p.dataset.tab !== a.dataset.tab; });
                var u = new URL(location.href); u.searchParams.set('tab', a.dataset.tab); history.replaceState(null, '', u);
            });
        });
    }

    // ── страница студии (как было) ──
    function fxPage() {
        var rows = $('#fx-link-rows'), add = $('#fx-link-add');
        if (rows && add) add.addEventListener('click', function () {
            if (rows.children.length >= 8) { add.disabled = true; return; }
            var d = rows.lastElementChild.cloneNode(true);
            d.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            rows.appendChild(d);
            if (rows.children.length >= 8) add.disabled = true;
        });
        var h = decodeURIComponent(location.hash.replace('#', ''));
        var el = h && document.getElementById(h);
        if (el) {
            var pane = el.closest('.mn-pane');
            if (pane && pane.hidden) { var t = $('.mn-tab[data-tab="' + pane.dataset.tab + '"]'); if (t) t.click(); }
            el.scrollIntoView({ block: 'center' });
            el.classList.add('fx-flash');
            setTimeout(function () { el.classList.remove('fx-flash'); }, 2200);
        }
    }

    var CFG;
    return {
        init: function (cfg) {
            CFG = cfg;
            document.addEventListener('focusout', function (e) { if (e.target.dataset && e.target.dataset.check) check(e.target); });
            // ошибку снимаем сразу при вводе, а не при уходе с поля: иначе на blur вёрстка прыгает
            // вверх ровно в момент клика по «Далее», и клик проходит мимо кнопки
            document.addEventListener('input', function (e) {
                var box = e.target.closest && e.target.closest('.field');
                if (!box) return;
                e.target.classList.remove('mn-bad', 'mn-good');
                $$('.mn-err', box).forEach(function (x) { x.remove(); });
            });
            tabs(); wizard(); pricing(); balanceCalc(); testPay(); fxPage();
        },
    };
})();
</script>
