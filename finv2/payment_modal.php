<?php
$game_price_rub = number_format($game['price'], 0, ',', ' ');
$infra_amount   = max(10, round($game['price'] * 0.05)); // 5% or min 10₽
?>

<div id="paymentModal" class="pm-overlay" role="dialog" aria-modal="true" aria-labelledby="pm-title">
    <div class="pm-sheet">

        <!-- Close -->
        <button class="pm-close" onclick="closePaymentModal()" aria-label="Закрыть">✕</button>

        <div class="pm-body">

            <div class="pm-col pm-col--main" id="pm-game-col">
                <div class="pm-col-inner">

                    <div class="pm-section-label">Основной платёж</div>
                    <h2 id="pm-title" class="pm-heading">Купить игру</h2>
                    <br>
                    <p class="pm-sub"><?= htmlspecialchars($game['name']) ?></p>

                    <div class="pm-price-tag">
                        <span class="pm-price-amount"><?= $game_price_rub ?> ₽</span>
                        <span class="pm-price-note">100% стоимости уходит разработчику</span>
                    </div>

                    <ul class="pm-benefits">
                        <li><span class="pm-check">✓</span> Игра навсегда в вашей библиотеке</li>
                        <li><span class="pm-check">✓</span> Комиссия платформы — 0%</li>
                        <li><span class="pm-check">✓</span> Оплата через ЮКасса</li>
                    </ul>

                    <!-- State: idle -->
                    <div id="pm-idle-state">
                        <button class="pm-btn pm-btn--primary" id="pm-pay-game-btn" onclick="startGamePayment()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" />
                                <line x1="1" y1="10" x2="23" y2="10" />
                            </svg>
                            Оплатить <?= $game_price_rub ?> ₽
                        </button>
                        <p class="pm-hint">Откроется страница ЮКасса. Вернитесь после оплаты.</p>
                    </div>

                    <!-- State: waiting -->
                    <div id="pm-waiting-state" style="display:none">
                        <div class="pm-spinner-wrap">
                            <div class="pm-spinner"></div>
                            <span>Ожидаем подтверждения оплаты…</span>
                        </div>
                        <button class="pm-btn pm-btn--ghost" onclick="startGamePayment()" style="margin-top:12px; font-size:.85rem;">
                            Не перешли на страницу оплаты? Открыть снова
                        </button>
                    </div>

                    <!-- State: success -->
                    <div id="pm-success-state" style="display:none">
                        <div class="pm-success-badge">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                        <p class="pm-success-text">Оплата прошла! Игра добавлена в библиотеку.</p>
                        <a href="/explore" class="pm-btn pm-btn--ghost" style="margin-top:8px;">К каталогу</a>
                    </div>

                    <div class="pm-divider"></div>

                    <?php if (!empty($studio_payment_data['donate_link'])): ?>
                        <div class="pm-donate-row">
                            <div>
                                <div class="pm-donate-title">Поддержать разработчика</div>
                                <div class="pm-donate-sub">Необязательно. Каждая копейка важна независимым авторам.</div>
                            </div>
                            <a href="<?= htmlspecialchars($studio_payment_data['donate_link']) ?>" target="_blank" class="pm-btn pm-btn--donate">
                                💝 Задонатить
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pm-col pm-col--side">
                <div class="pm-col-inner">

                    <div class="pm-section-label pm-section-label--dim">Необязательно</div>
                    <h3 class="pm-heading pm-heading--sm">Инфраструктурный<br>налог</h3>

                    <p class="pm-side-desc">
                        Платформа не берёт комиссию с разработчиков. Серверы, домен, поддержка и развитие существуют на добровольные взносы игроков.
                    </p>
                    <p class="pm-side-desc">
                        Оплатив инфраструктурный налог, вы помогаете нам снижать стоимость игр, поддерживать бесплатный хостинг для инди-авторов и держать комиссию на уровне <strong>0%</strong>.
                    </p>

                    <div class="pm-infra-amount"><?= number_format($infra_amount, 0, ',', ' ') ?> ₽</div>

                    <button class="pm-btn pm-btn--secondary" id="pm-pay-infra-btn" onclick="startInfraPayment()">
                        Оплатить поддержку
                    </button>

                    <div id="pm-infra-success" style="display:none" class="pm-infra-ok">
                        ✓ Спасибо! Вы помогаете платформе.
                    </div>

                    <div class="pm-divider pm-divider--subtle"></div>

                    <div class="pm-donate-row pm-donate-row--stacked">
                        <div class="pm-donate-title">Задонатить платформе</div>
                        <div class="pm-donate-sub">Любую сумму — по желанию.</div>
                        <a href="https://pay.cloudtips.ru/p/dustore" target="_blank" class="pm-btn pm-btn--donate pm-btn--sm" style="margin-top:10px;">
                            🙌 Поддержать Dustore
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /.pm-body -->
    </div><!-- /.pm-sheet -->
</div><!-- /.pm-overlay -->


<!-- ═══════════════════════════════════════════════════
     STYLES
═══════════════════════════════════════════════════ -->
<style>
    /* ── Overlay ── */
    .pm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(10, 3, 18, 0.85);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: pmFadeIn .2s ease;
    }

    .pm-overlay.is-open {
        display: flex;
    }

    @keyframes pmFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* ── Sheet ── */
    .pm-sheet {
        position: relative;
        width: 100%;
        max-width: 900px;
        max-height: 92vh;
        overflow-y: auto;
        border-radius: 24px;
        background: linear-gradient(145deg, #1a0626 0%, #200830 50%, #1a0626 100%);
        border: 1px solid rgba(195, 33, 120, 0.25);
        box-shadow:
            0 40px 80px rgba(0, 0, 0, .6),
            0 0 0 1px rgba(255, 255, 255, .04) inset;
        animation: pmSlideUp .3s cubic-bezier(.22, 1, .36, 1);
    }

    @keyframes pmSlideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* ── Close button ── */
    .pm-close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
        color: rgba(255, 255, 255, .6);
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .2s, color .2s;
        z-index: 10;
    }

    .pm-close:hover {
        background: rgba(195, 33, 120, .4);
        color: #fff;
    }

    /* ── Body ── */
    .pm-body {
        display: grid;
        grid-template-columns: 2fr 1fr;
        min-height: 480px;
    }

    /* ── Columns ── */
    .pm-col {
        padding: 40px 36px;
    }

    .pm-col--main {
        border-right: 1px solid rgba(255, 255, 255, .07);
    }

    .pm-col--side {
        background: rgba(0, 0, 0, .15);
        border-radius: 0 24px 24px 0;
    }

    .pm-col-inner {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    /* Success state for left column */
    #pm-game-col.is-paid {
        background: linear-gradient(145deg, rgba(0, 200, 100, .08), rgba(0, 160, 80, .05));
        border-right-color: rgba(0, 200, 100, .2);
        transition: background .6s ease;
    }

    /* ── Section label ── */
    .pm-section-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #c32178;
        margin-bottom: 8px;
    }

    .pm-section-label--dim {
        color: rgba(255, 255, 255, .3);
    }

    /* ── Headings ── */
    .pm-heading {
        font-size: 1.7rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 4px;
        line-height: 1.2;
    }

    .pm-heading--sm {
        font-size: 1.25rem;
        margin-bottom: 16px;
    }

    .pm-sub {
        color: rgba(255, 255, 255, .5);
        font-size: .9rem;
        margin: 0 0 24px;
    }

    /* ── Price tag ── */
    .pm-price-tag {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 20px;
        padding: 16px 20px;
        background: rgba(195, 33, 120, .1);
        border: 1px solid rgba(195, 33, 120, .25);
        border-radius: 14px;
    }

    .pm-price-amount {
        font-size: 2rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -.02em;
    }

    .pm-price-note {
        font-size: .8rem;
        color: rgba(255, 255, 255, .5);
    }

    /* ── Benefits ── */
    .pm-benefits {
        list-style: none;
        padding: 0;
        margin: 0 0 24px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .pm-benefits li {
        font-size: .9rem;
        color: rgba(255, 255, 255, .65);
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .pm-check {
        color: #00c46a;
        font-weight: 700;
    }

    /* ── Buttons ── */
    .pm-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 12px;
        border: none;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: transform .15s, box-shadow .15s, background .2s;
        width: 100%;
    }

    .pm-btn:active {
        transform: scale(.97);
    }

    .pm-btn--primary {
        background: linear-gradient(135deg, #c32178, #74155d);
        color: #fff;
        box-shadow: 0 4px 20px rgba(195, 33, 120, .35);
    }

    .pm-btn--primary:hover {
        box-shadow: 0 6px 28px rgba(195, 33, 120, .55);
        background: linear-gradient(135deg, #e02e8e, #8c1a70);
    }

    .pm-btn--secondary {
        background: rgba(255, 255, 255, .07);
        border: 1px solid rgba(255, 255, 255, .15);
        color: rgba(255, 255, 255, .85);
    }

    .pm-btn--secondary:hover {
        background: rgba(255, 255, 255, .12);
    }

    .pm-btn--ghost {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, .2);
        color: rgba(255, 255, 255, .6);
        font-size: .85rem;
    }

    .pm-btn--ghost:hover {
        border-color: rgba(255, 255, 255, .4);
        color: #fff;
    }

    .pm-btn--donate {
        background: rgba(255, 100, 150, .1);
        border: 1px solid rgba(255, 100, 150, .25);
        color: rgba(255, 180, 210, 1);
        padding: 10px 18px;
        border-radius: 10px;
        font-size: .85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background .2s;
        width: auto;
    }

    .pm-btn--donate:hover {
        background: rgba(255, 100, 150, .2);
    }

    .pm-btn--sm {
        padding: 8px 16px;
        font-size: .8rem;
    }

    /* ── Hint ── */
    .pm-hint {
        margin: 10px 0 0;
        font-size: .78rem;
        color: rgba(255, 255, 255, .3);
        text-align: center;
    }

    /* ── Spinner ── */
    .pm-spinner-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
        padding: 24px;
        color: rgba(255, 255, 255, .6);
        font-size: .9rem;
    }

    .pm-spinner {
        width: 36px;
        height: 36px;
        border: 3px solid rgba(195, 33, 120, .2);
        border-top-color: #c32178;
        border-radius: 50%;
        animation: pmSpin .8s linear infinite;
    }

    @keyframes pmSpin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ── Success ── */
    .pm-success-badge {
        width: 64px;
        height: 64px;
        background: rgba(0, 200, 100, .12);
        border: 2px solid rgba(0, 200, 100, .4);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        color: #00c46a;
        animation: pmPop .4s cubic-bezier(.22, 1, .36, 1);
    }

    @keyframes pmPop {
        from {
            transform: scale(0);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .pm-success-text {
        text-align: center;
        color: #00c46a;
        font-weight: 600;
        font-size: 1rem;
        margin: 0;
    }

    /* ── Divider ── */
    .pm-divider {
        height: 1px;
        background: rgba(255, 255, 255, .08);
        margin: 24px 0;
    }

    .pm-divider--subtle {
        background: rgba(255, 255, 255, .04);
    }

    /* ── Donate row ── */
    .pm-donate-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .pm-donate-row--stacked {
        flex-direction: column;
        align-items: flex-start;
    }

    .pm-donate-title {
        font-size: .9rem;
        font-weight: 600;
        color: rgba(255, 255, 255, .75);
    }

    .pm-donate-sub {
        font-size: .78rem;
        color: rgba(255, 255, 255, .35);
        margin-top: 3px;
    }

    /* ── Side column ── */
    .pm-side-desc {
        font-size: .85rem;
        color: rgba(255, 255, 255, .45);
        line-height: 1.6;
        margin: 0 0 14px;
    }

    .pm-side-desc strong {
        color: rgba(255, 255, 255, .7);
    }

    .pm-infra-amount {
        font-size: 1.6rem;
        font-weight: 800;
        color: rgba(255, 255, 255, .75);
        margin: 0 0 16px;
        letter-spacing: -.02em;
    }

    .pm-infra-ok {
        margin-top: 12px;
        padding: 10px 14px;
        background: rgba(0, 200, 100, .1);
        border: 1px solid rgba(0, 200, 100, .25);
        border-radius: 10px;
        color: #00c46a;
        font-size: .85rem;
        font-weight: 600;
    }

    /* ── Responsive ── */
    @media (max-width: 700px) {
        .pm-body {
            grid-template-columns: 1fr;
        }

        .pm-col {
            padding: 28px 22px;
        }

        .pm-col--main {
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .pm-col--side {
            border-radius: 0 0 24px 24px;
        }

        .pm-heading {
            font-size: 1.4rem;
        }
    }
</style>


<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════ -->
<script>
    /* ── Open / close ── */
    function openPaymentModal() {
        document.getElementById('paymentModal').classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('is-open');
        document.body.style.overflow = '';
        clearInterval(window._pmPollTimer);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) closePaymentModal();
        });
    });

    const GAME_ID = <?= (int)$game_id ?>;
    const GAME_PRICE = <?= (int)$game['price'] ?>;
    const INFRA_AMT = <?= (int)$infra_amount ?>;

    /* ── Start game payment ── */
    async function startGamePayment() {
        const btn = document.getElementById('pm-pay-game-btn');
        btn.disabled = true;
        btn.textContent = 'Создаём платёж…';

        try {
            const res = await fetch('../finv2/create_payment_game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    game_id: GAME_ID
                })
            });
            const data = await res.json();

            if (!data.payment_url || !data.payment_id) {
                throw new Error(data.error || 'Не удалось создать платёж');
            }

            window._pmPaymentId = data.payment_id;
            window.open(data.payment_url, '_blank');

            document.getElementById('pm-idle-state').style.display = 'none';
            document.getElementById('pm-waiting-state').style.display = '';

            startPollGame(data.payment_id);

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Оплатить ${GAME_PRICE.toLocaleString('ru-RU')} ₽`;
            console.error('Ошибка: ' + err.message);
        }
    }

    function startPollGame(paymentId) {
        clearInterval(window._pmPollTimer);
        window._pmPollTimer = setInterval(async () => {
            try {
                const res = await fetch('/finv2/check_payment_game.php?payment_id=' + encodeURIComponent(paymentId));
                const data = await res.json();

                if (data.status === 'succeeded') {
                    clearInterval(window._pmPollTimer);
                    onGamePaymentSuccess();
                } else if (data.status === 'canceled') {
                    clearInterval(window._pmPollTimer);
                    document.getElementById('pm-waiting-state').style.display = 'none';
                    document.getElementById('pm-idle-state').style.display = '';
                    const btn = document.getElementById('pm-pay-game-btn');
                    btn.disabled = false;
                    btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Попробовать снова`;
                    alert('Платёж отменён. Попробуйте ещё раз.');
                }
            } catch (_) {}
        }, 3000);
    }

    function onGamePaymentSuccess() {
        document.getElementById('pm-game-col').classList.add('is-paid');
        document.getElementById('pm-waiting-state').style.display = 'none';
        document.getElementById('pm-success-state').style.display = '';
    }

    /* ── Infra payment ── */
    async function startInfraPayment() {
        const btn = document.getElementById('pm-pay-infra-btn');
        btn.disabled = true;
        btn.textContent = 'Создаём платёж…';

        try {
            const res = await fetch('/finv2/create_infra_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    amount: INFRA_AMT,
                    game_id: GAME_ID
                })
            });
            const data = await res.json();

            if (!data.payment_url) throw new Error(data.error || 'Ошибка');

            window.open(data.payment_url, '_blank');

            let infraPoll = setInterval(async () => {
                try {
                    const r = await fetch('/finv2/check_payment_game.php?payment_id=' + encodeURIComponent(data.payment_id));
                    const d = await r.json();
                    if (d.status === 'succeeded') {
                        clearInterval(infraPoll);
                        btn.style.display = 'none';
                        document.getElementById('pm-infra-success').style.display = '';
                    }
                } catch (_) {}
            }, 3000);

        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Оплатить поддержку';
            alert('Ошибка: ' + err.message);
        }
    }
</script>