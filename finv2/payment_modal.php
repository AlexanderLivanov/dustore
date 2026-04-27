<?php
/**
 * payment_modal.php
 * Expects: $game_id, $game (price, name, path_to_cover),
 *          $studio_payment_data (donate_link)
 */
$game_price   = (int)$game['price'];
$max_platform = max(500, $game_price);
$default_plat = min(50, (int)round($game_price * 0.08));
?>

<div id="paymentModal" class="pm-overlay" role="dialog" aria-modal="true">
    <div class="pm-sheet">

        <button class="pm-x" onclick="closePaymentModal()" aria-label="Закрыть">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <!-- ─── TOP BAR ─── -->
        <div class="pm-top">
            <div class="pm-game-row">
                <?php if (!empty($game['path_to_cover'])): ?>
                    <img class="pm-cover" src="<?= htmlspecialchars($game['path_to_cover']) ?>" alt="">
                <?php else: ?>
                    <div class="pm-cover pm-cover-ph">🎮</div>
                <?php endif; ?>
                <div>
                    <div class="pm-game-name"><?= htmlspecialchars($game['name']) ?></div>
                    <div class="pm-game-sub">Цифровая копия · навсегда в библиотеке</div>
                </div>
            </div>
            <div class="pm-total-wrap">
                <div class="pm-total-lbl">Итого</div>
                <div class="pm-total-val" id="pm-total">— ₽</div>
            </div>
        </div>

        <!-- ─── SLIDER ZONE ─── -->
        <div class="pm-slider-zone">

            <!-- Amounts row -->
            <div class="pm-amounts">
                <div class="pm-amt pm-amt--dev">
                    <div class="pm-amt-val" id="pm-dev-val">— ₽</div>
                    <div class="pm-amt-lbl">Разработчику</div>
                </div>
                <div class="pm-amt pm-amt--plat">
                    <div class="pm-amt-val" id="pm-plat-val">— ₽</div>
                    <div class="pm-amt-lbl">Платформе</div>
                </div>
            </div>

            <!-- Visual track -->
            <div class="pm-track-wrap">
                <div class="pm-track">
                    <div class="pm-track-dev"  id="pm-track-dev"></div>
                    <div class="pm-track-plat" id="pm-track-plat"></div>
                </div>
                <input
                    type="range"
                    id="pm-slider"
                    class="pm-slider"
                    min="0"
                    max="<?= $max_platform ?>"
                    step="10"
                    value="<?= $default_plat ?>"
                    oninput="pmUpdate(this.value)"
                >
            </div>

            <div class="pm-track-labels">
                <span>◀ Меньше платформе</span>
                <span>Больше платформе ▶</span>
            </div>
        </div>

        <!-- ─── SPLIT BODY ─── -->
        <div class="pm-body">

            <!-- LEFT: Developer -->
            <div class="pm-half pm-half--dev">
                <div class="pm-half-chip pm-half-chip--dev">Разработчику</div>
                <div class="pm-half-heading">Покупка игры</div>
                <p class="pm-half-desc">
                    100% суммы уходит разработчику. Платформа не берёт комиссию —
                    это наш принцип поддержки инди-авторов.
                </p>

                <?php if (!empty($studio_payment_data['donate_link'])): ?>
                    <a class="pm-extra-link" href="<?= htmlspecialchars($studio_payment_data['donate_link']) ?>" target="_blank">
                        💝 Задонатить разработчику сверх
                    </a>
                <?php endif; ?>

                <div class="pm-actions">
                    <!-- STEP 1: idle — pay for game -->
                    <div id="pm-idle">
                        <button class="pm-btn-pay" id="pm-pay-btn" onclick="pmStartPayment()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                            Оплатить
                        </button>
                    </div>

                    <!-- STEP 1: waiting for game payment -->
                    <div id="pm-waiting" style="display:none">
                        <div class="pm-spin-row">
                            <div class="pm-spinner"></div>
                            <span>Ожидаем подтверждения…</span>
                        </div>
                        <button class="pm-btn-ghost" onclick="pmStartPayment()">Открыть страницу снова</button>
                    </div>

                    <!-- STEP 2: game paid, now platform -->
                    <div id="pm-game-ok" style="display:none">
                        <div class="pm-ok" style="margin-bottom:12px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Игра оплачена!
                        </div>
                        <!-- shown only if platform amount > 0 -->
                        <div id="pm-plat-step">
                            <p class="pm-plat-prompt">Теперь вы можете поддержать платформу:</p>
                            <button class="pm-btn-pay pm-btn-pay--plat" id="pm-pay-plat-btn" onclick="pmStartPlatPayment()">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                                Поддержать платформу <span id="pm-plat-btn-amt"></span>
                            </button>
                            <div id="pm-plat-waiting" style="display:none">
                                <div class="pm-spin-row">
                                    <div class="pm-spinner"></div>
                                    <span>Ожидаем оплату платформе…</span>
                                </div>
                                <button class="pm-btn-ghost" onclick="pmStartPlatPayment()">Открыть страницу снова</button>
                            </div>
                            <div id="pm-plat-ok" style="display:none" class="pm-ok pm-ok--plat">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Спасибо за поддержку!
                            </div>
                            <button class="pm-btn-ghost" id="pm-skip-plat" onclick="pmShowDone()" style="margin-top:6px;">
                                Пропустить →
                            </button>
                        </div>
                        <!-- shown if platform = 0 or after platform paid/skipped -->
                        <div id="pm-goto-lib" style="display:none">
                            <a href="/library" class="pm-btn-pay" style="text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px;">
                                Перейти в библиотеку →
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Platform -->
            <div class="pm-half pm-half--plat">
                <div class="pm-half-chip pm-half-chip--plat">Платформе</div>
                <div class="pm-half-heading">Поддержка Dustore</div>
                <p class="pm-half-desc">
                    Необязательно. Помогает содержать серверы, снижать стоимость игр
                    и держать комиссию для авторов на уровне <strong style="color:#c4b5fd;">0%</strong>.
                </p>

                <div class="pm-perks">
                    <div class="pm-perk">🖥️ Серверы и инфраструктура</div>
                    <div class="pm-perk">📉 Снижение цен на игры</div>
                    <div class="pm-perk">💸 0% комиссии для инди-авторов</div>
                </div>

                <div id="pm-zero-note" class="pm-zero-note" style="display:none">
                    Платить платформе необязательно — вы всё равно получите игру.
                </div>

                <a class="pm-extra-link" href="https://pay.cloudtips.ru/p/dustore" target="_blank">
                    🙌 Задонатить платформе отдельно
                </a>
            </div>

        </div><!-- /.pm-body -->
    </div>
</div>

<!-- ═══════════ STYLES ═══════════ -->
<style>
.pm-overlay {
    display: none;
    position: fixed; inset: 0;
    z-index: 9999;
    background: rgba(4, 0, 14, .85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    align-items: center; justify-content: center;
    padding: 16px;
}
.pm-overlay.is-open { display: flex; }

.pm-sheet {
    position: relative;
    width: 100%; max-width: 820px;
    max-height: 92vh; overflow-y: auto;
    border-radius: 22px;
    background: linear-gradient(160deg, #140228 0%, #0e0118 100%);
    border: 1px solid rgba(255,255,255,.07);
    box-shadow: 0 0 0 1px rgba(255,255,255,.02) inset, 0 40px 100px rgba(0,0,0,.65);
    animation: pmIn .35s cubic-bezier(.22,1,.36,1);
}
@keyframes pmIn {
    from { opacity:0; transform:translateY(20px) scale(.97); }
    to   { opacity:1; transform:translateY(0)    scale(1); }
}

/* Close */
.pm-x {
    position: absolute; top:14px; right:14px;
    width:30px; height:30px; border-radius:50%;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    color: rgba(255,255,255,.4); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition:.18s; z-index:5;
}
.pm-x:hover { background:rgba(195,33,120,.25); color:#fff; }

/* ── TOP ── */
.pm-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 28px 18px;
    border-bottom: 1px solid rgba(255,255,255,.05);
    flex-wrap: wrap;
}
.pm-game-row { display:flex; align-items:center; gap:13px; }
.pm-cover {
    width:48px; height:48px; border-radius:9px;
    object-fit:cover; flex-shrink:0;
    border:1px solid rgba(255,255,255,.1);
}
.pm-cover-ph {
    background: rgba(195,33,120,.08);
    display:flex; align-items:center; justify-content:center; font-size:1.3rem;
}
.pm-game-name { font-weight:700; font-size:.95rem; color:#fff; }
.pm-game-sub  { font-size:.72rem; color:rgba(255,255,255,.3); margin-top:2px; }
.pm-total-wrap { text-align:right; }
.pm-total-lbl  { font-size:.68rem; color:rgba(255,255,255,.35); text-transform:uppercase; letter-spacing:.08em; }
.pm-total-val  {
    font-size:1.9rem; font-weight:800; color:#fff;
    letter-spacing:-.03em; line-height:1;
    transition: color .25s;
}

/* ── SLIDER ZONE ── */
.pm-slider-zone {
    padding: 20px 28px 4px;
    border-bottom: 1px solid rgba(255,255,255,.05);
}

.pm-amounts {
    display: flex;
    justify-content: space-between;
    margin-bottom: 14px;
}
.pm-amt { display:flex; flex-direction:column; }
.pm-amt--plat { align-items:flex-end; }
.pm-amt-val {
    font-size:1.4rem; font-weight:800; letter-spacing:-.02em; line-height:1;
    transition: all .2s;
}
.pm-amt--dev  .pm-amt-val { color: #fff; }
.pm-amt--plat .pm-amt-val { color: #a78bfa; }
.pm-amt-lbl { font-size:.7rem; color:rgba(255,255,255,.3); margin-top:4px; }

/* Track */
.pm-track-wrap { position:relative; margin-bottom:6px; }

.pm-track {
    height: 8px; border-radius:8px;
    overflow: hidden; display:flex;
    background: rgba(255,255,255,.06);
    margin-bottom: 10px;
}
.pm-track-dev  { background: linear-gradient(to right, #c32178, #9b1760); transition:width .12s; }
.pm-track-plat { background: linear-gradient(to right, #7c3aed, #a78bfa); transition:width .12s; }

.pm-slider {
    -webkit-appearance: none; appearance: none;
    width:100%; height:4px; border-radius:4px;
    background: rgba(255,255,255,.08);
    outline:none; cursor:pointer; display:block;
}
.pm-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width:24px; height:24px; border-radius:50%;
    background:#fff;
    border:3px solid #c32178;
    box-shadow: 0 2px 12px rgba(195,33,120,.45), 0 0 0 4px rgba(195,33,120,.1);
    cursor: grab;
    transition: transform .15s, box-shadow .15s;
}
.pm-slider::-webkit-slider-thumb:active {
    cursor:grabbing; transform:scale(1.25);
    box-shadow: 0 4px 24px rgba(195,33,120,.65), 0 0 0 8px rgba(195,33,120,.12);
}
.pm-slider::-moz-range-thumb {
    width:24px; height:24px; border-radius:50%;
    background:#fff; border:3px solid #c32178; cursor:grab;
}

.pm-track-labels {
    display:flex; justify-content:space-between;
    font-size:.65rem; color:rgba(255,255,255,.2);
    letter-spacing:.04em; padding-bottom:6px;
}

/* ── BODY ── */
.pm-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.pm-half {
    padding: 24px 28px 28px;
    display: flex; flex-direction: column; gap: 0;
}
.pm-half--plat {
    background: rgba(0,0,0,.12);
    border-left: 1px solid rgba(255,255,255,.05);
    border-radius: 0 0 22px 0;
}
.pm-half--dev { border-radius: 0 0 0 22px; }

.pm-half-chip {
    display: inline-flex; align-items:center;
    padding: 3px 10px; border-radius:20px;
    font-size:.68rem; font-weight:700; letter-spacing:.07em;
    text-transform:uppercase; margin-bottom:10px; width:fit-content;
}
.pm-half-chip--dev {
    background: rgba(195,33,120,.12);
    border: 1px solid rgba(195,33,120,.25);
    color: #e88fc0;
}
.pm-half-chip--plat {
    background: rgba(124,58,237,.12);
    border: 1px solid rgba(124,58,237,.3);
    color: #c4b5fd;
}

.pm-half-heading {
    font-size:1.05rem; font-weight:700; color:#fff;
    margin-bottom:8px;
}
.pm-half-desc {
    font-size:.82rem; color:rgba(255,255,255,.4);
    line-height:1.65; margin-bottom:16px;
}

.pm-perks {
    display:flex; flex-direction:column; gap:8px;
    margin-bottom:18px;
}
.pm-perk {
    font-size:.8rem; color:rgba(167,139,250,.75);
    display:flex; align-items:center; gap:7px;
}

.pm-zero-note {
    background: rgba(245,158,11,.06);
    border: 1px solid rgba(245,158,11,.15);
    border-radius:8px; padding:8px 12px;
    font-size:.77rem; color:rgba(245,158,11,.7);
    margin-bottom:12px; line-height:1.5;
}

.pm-extra-link {
    font-size:.77rem; color:rgba(255,255,255,.28);
    text-decoration:none; margin-top:auto;
    transition:color .18s; display:inline-block;
}
.pm-extra-link:hover { color:rgba(255,255,255,.6); }

/* Actions */
.pm-actions { margin-top:auto; padding-top:16px; }

.pm-btn-pay {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:13px 20px;
    border:none; border-radius:12px;
    background: linear-gradient(135deg,#c32178,#74155d);
    color:#fff; font-weight:700; font-size:.92rem;
    cursor:pointer; font-family:inherit;
    box-shadow: 0 4px 18px rgba(195,33,120,.35);
    transition: box-shadow .18s, transform .12s;
}
.pm-btn-pay:hover { box-shadow: 0 6px 26px rgba(195,33,120,.52); }
.pm-btn-pay:active { transform:scale(.97); }

.pm-btn-ghost {
    display:block; width:100%; margin-top:8px;
    padding:8px; background:transparent;
    border:1px solid rgba(255,255,255,.09);
    border-radius:8px; color:rgba(255,255,255,.35);
    font-size:.78rem; cursor:pointer; font-family:inherit;
    transition:.18s;
}
.pm-btn-ghost:hover { color:#fff; border-color:rgba(255,255,255,.22); }

.pm-spin-row {
    display:flex; align-items:center; gap:10px;
    font-size:.83rem; color:rgba(255,255,255,.45); padding:10px 0;
}
.pm-spinner {
    width:18px; height:18px; flex-shrink:0;
    border:2px solid rgba(195,33,120,.2);
    border-top-color:#c32178; border-radius:50%;
    animation:pmSpin .75s linear infinite;
}
@keyframes pmSpin { to { transform:rotate(360deg); } }

.pm-btn-pay--plat {
    background: linear-gradient(135deg, #7c3aed, #5b21b6);
    box-shadow: 0 4px 18px rgba(124,58,237,.35);
}
.pm-btn-pay--plat:hover { box-shadow: 0 6px 26px rgba(124,58,237,.52); }

.pm-ok--plat {
    background: rgba(167,139,250,.08);
    border-color: rgba(167,139,250,.2);
    color: #c4b5fd;
}

.pm-plat-prompt {
    font-size: .8rem;
    color: rgba(255,255,255,.35);
    margin-bottom: 10px;
    line-height: 1.5;
}

/* ── MOBILE ── */
@media (max-width:640px) {
    .pm-body { grid-template-columns:1fr; }
    .pm-half--plat { border-left:none; border-top:1px solid rgba(255,255,255,.05); border-radius:0 0 22px 22px; }
    .pm-half--dev  { border-radius:0; }
    .pm-top { padding:16px 18px 14px; }
    .pm-slider-zone { padding:16px 18px 4px; }
    .pm-half { padding:20px 18px 22px; }
    .pm-total-val { font-size:1.5rem; }
    .pm-amt-val   { font-size:1.15rem; }
}
</style>

<!-- ═══════════ SCRIPT ═══════════ -->
<script>
(function() {
    const GAME_PRICE   = <?= $game_price ?>;
    const MAX_PLATFORM = <?= $max_platform ?>;
    let   platAmt      = <?= $default_plat ?>;
    let   gamePollTimer = null;
    let   platPollTimer = null;
    let   gamePaymentId = null;
    let   platPaymentId = null;

    window.openPaymentModal = function() {
        document.getElementById('paymentModal').classList.add('is-open');
        document.body.style.overflow = 'hidden';
        pmUpdate(platAmt);
    };
    window.closePaymentModal = function() {
        document.getElementById('paymentModal').classList.remove('is-open');
        document.body.style.overflow = '';
        clearInterval(gamePollTimer);
        clearInterval(platPollTimer);
    };
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) closePaymentModal();
    });

    window.pmUpdate = function(val) {
        val = parseInt(val) || 0;
        platAmt = val;

        const total  = GAME_PRICE + val;
        const devPct = total > 0 ? (GAME_PRICE / total) * 100 : 100;

        document.getElementById('pm-total').textContent     = total.toLocaleString('ru-RU') + ' ₽';
        document.getElementById('pm-dev-val').textContent   = GAME_PRICE.toLocaleString('ru-RU') + ' ₽';
        document.getElementById('pm-plat-val').textContent  = (val > 0 ? '+\u202F' : '') + val.toLocaleString('ru-RU') + ' ₽';

        document.getElementById('pm-track-dev').style.width  = devPct + '%';
        document.getElementById('pm-track-plat').style.width = (100 - devPct) + '%';

        const pct = (val / MAX_PLATFORM) * 100;
        document.getElementById('pm-slider').style.background =
            `linear-gradient(to right,rgba(195,33,120,.5) 0%,rgba(124,58,237,.5) ${pct}%,rgba(255,255,255,.08) ${pct}%)`;

        document.getElementById('pm-total').style.color = val > 0 ? '#c4b5fd' : '#fff';
        document.getElementById('pm-zero-note').style.display = val === 0 ? '' : 'none';

        // update pay button
        const btn = document.getElementById('pm-pay-btn');
        if (btn) btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Оплатить ${GAME_PRICE.toLocaleString('ru-RU')} ₽`;

        // update platform button amount label
        const pba = document.getElementById('pm-plat-btn-amt');
        if (pba) pba.textContent = val > 0 ? '— ' + val.toLocaleString('ru-RU') + ' ₽' : '';
    };

    /* ── STEP 1: pay for game ── */
    window.pmStartPayment = async function() {
        const btn = document.getElementById('pm-pay-btn');
        btn.disabled = true;
        btn.textContent = 'Создаём платёж…';

        try {
            const res  = await fetch('/finv2/create_payment_game.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ game_id: <?= (int)$game_id ?>, platform_tip: 0 })
            });
            const data = await res.json();
            if (!data.payment_url || !data.payment_id) throw new Error(data.error || 'Нет ответа');

            gamePaymentId = data.payment_id;
            window.open(data.payment_url, '_blank');

            document.getElementById('pm-idle').style.display    = 'none';
            document.getElementById('pm-waiting').style.display = '';

            gamePollTimer = setInterval(async () => {
                try {
                    const r = await fetch('/finv2/check_payment_game.php?payment_id=' + encodeURIComponent(gamePaymentId));
                    const d = await r.json();
                    if (d.status === 'succeeded') {
                        clearInterval(gamePollTimer);
                        onGamePaid();
                    } else if (d.status === 'canceled') {
                        clearInterval(gamePollTimer);
                        document.getElementById('pm-waiting').style.display = 'none';
                        document.getElementById('pm-idle').style.display    = '';
                        btn.disabled = false;
                        pmUpdate(platAmt);
                    }
                } catch(_) {}
            }, 3000);

        } catch(err) {
            btn.disabled = false;
            pmUpdate(platAmt);
            alert('Ошибка: ' + err.message);
        }
    };

    /* ── STEP 2: game paid → show platform step ── */
    function onGamePaid() {
        document.getElementById('pm-waiting').style.display  = 'none';
        document.getElementById('pm-game-ok').style.display  = '';

        if (platAmt > 0) {
            // show platform payment step
            document.getElementById('pm-plat-step').style.display = '';
            document.getElementById('pm-goto-lib').style.display  = 'none';
        } else {
            // no platform fee — go straight to done
            pmShowDone();
        }
    }

    /* ── STEP 2b: pay platform ── */
    window.pmStartPlatPayment = async function() {
        const btn = document.getElementById('pm-pay-plat-btn');
        btn.disabled = true;
        btn.textContent = 'Создаём платёж…';

        document.getElementById('pm-skip-plat').style.display = 'none';

        try {
            const res  = await fetch('/finv2/create_infra_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: platAmt, game_id: <?= (int)$game_id ?> })
            });
            const data = await res.json();
            if (!data.payment_url || !data.payment_id) throw new Error(data.error || 'Нет ответа');

            platPaymentId = data.payment_id;
            window.open(data.payment_url, '_blank');

            btn.style.display = 'none';
            document.getElementById('pm-plat-waiting').style.display = '';

            platPollTimer = setInterval(async () => {
                try {
                    const r = await fetch('/finv2/check_payment_game.php?payment_id=' + encodeURIComponent(platPaymentId));
                    const d = await r.json();
                    if (d.status === 'succeeded') {
                        clearInterval(platPollTimer);
                        document.getElementById('pm-plat-waiting').style.display = 'none';
                        document.getElementById('pm-plat-ok').style.display      = '';
                        setTimeout(pmShowDone, 1200);
                    } else if (d.status === 'canceled') {
                        clearInterval(platPollTimer);
                        document.getElementById('pm-plat-waiting').style.display = 'none';
                        btn.style.display  = '';
                        btn.disabled = false;
                        btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg> Попробовать снова`;
                        document.getElementById('pm-skip-plat').style.display = '';
                    }
                } catch(_) {}
            }, 3000);

        } catch(err) {
            btn.disabled = false;
            btn.textContent = 'Поддержать платформу';
            document.getElementById('pm-skip-plat').style.display = '';
            alert('Ошибка: ' + err.message);
        }
    };

    /* ── STEP 3: done ── */
    window.pmShowDone = function() {
        document.getElementById('pm-plat-step').style.display = 'none';
        document.getElementById('pm-goto-lib').style.display  = '';
    };

    document.addEventListener('DOMContentLoaded', () => pmUpdate(platAmt));
})();
</script>