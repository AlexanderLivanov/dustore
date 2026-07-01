<?php
require_once('../vendor/autoload.php');
require_once('../swad/config.php');

use YooKassa\Client;

$db  = new Database();
$pdo = $db->connect();

session_start();

$infraId   = (int)($_GET['id']      ?? 0);
$paymentId = trim($_GET['payment']  ?? '');

/* ── 1. Find infra payment ── */
$infra = null;
if ($infraId) {
    $stmt = $pdo->prepare("SELECT * FROM infra_payments WHERE id = ?");
    $stmt->execute([$infraId]);
    $infra = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$infra && $paymentId) {
    $stmt = $pdo->prepare("SELECT * FROM infra_payments WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    $infra = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ── 2. Verify with YooKassa ── */
$paymentVerified = false;

if ($paymentId) {
    try {
        $client = new Client();
        $client->setAuth(YOOKASSA_SHOP_ID, YOOKASSA_SHOP_KEY);
        $paymentInfo = $client->getPaymentInfo($paymentId);

        if ($paymentInfo->getStatus() === 'succeeded') {
            $paymentVerified = true;

            // Sync if webhook was slow
            if ($infra && $infra['status'] !== 'succeeded') {
                $pdo->prepare("UPDATE infra_payments SET status = 'succeeded', completed_at = NOW() WHERE id = ?")
                    ->execute([$infra['id']]);
                $infra['status'] = 'succeeded';
            }
        }
    } catch (Exception $e) {
        error_log("YooKassa success_infra.php error: " . $e->getMessage());
    }
}

$isPaid = $infra && $infra['status'] === 'succeeded';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поддержка принята — Dustore</title>
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #c32178;
            --primary-glow: rgba(195,33,120,.4);
            --dark: #0d0118;
            --surface: rgba(255,255,255,.04);
            --border: rgba(255,255,255,.08);
            --accent: #a78bfa;
            --accent-dim: rgba(167,139,250,.12);
            --text: #f0e6ff;
            --muted: rgba(255,255,255,.4);
        }

        body {
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(116,21,93,.35), transparent 70%);
            top: -100px; left: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(167,139,250,.1), transparent 70%);
            bottom: -80px; right: -80px;
        }

        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            background: rgba(20,4,35,.75);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 48px 40px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow:
                0 0 0 1px rgba(255,255,255,.03) inset,
                0 32px 64px rgba(0,0,0,.55);
            animation: slideUp .5s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes slideUp {
            from { opacity:0; transform:translateY(32px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Icon ── */
        .icon-wrap {
            width: 88px; height: 88px;
            margin: 0 auto 28px;
            border-radius: 50%;
            background: var(--accent-dim);
            border: 2px solid rgba(167,139,250,.3);
            display: flex; align-items: center; justify-content: center;
            position: relative;
            animation: popIn .5s .1s cubic-bezier(.22,1,.36,1) both;
            font-size: 2.4rem;
        }
        .icon-wrap::after {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(167,139,250,.1), transparent 70%);
        }

        @keyframes popIn {
            from { transform:scale(0) rotate(-15deg); opacity:0; }
            to   { transform:scale(1) rotate(0deg);  opacity:1; }
        }

        h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            letter-spacing: -.02em;
            margin-bottom: 6px;
            animation: fadeUp .4s .2s both;
        }
        .subtitle {
            text-align: center;
            color: var(--muted);
            font-size: .92rem;
            line-height: 1.55;
            margin-bottom: 28px;
            animation: fadeUp .4s .25s both;
        }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Impact block ── */
        .impact {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
            animation: fadeUp .4s .28s both;
        }
        .impact-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 10px;
            text-align: center;
        }
        .impact-icon { font-size: 1.4rem; margin-bottom: 6px; }
        .impact-label { font-size: .72rem; color: var(--muted); line-height: 1.4; }

        /* ── Details ── */
        .details {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 24px;
            animation: fadeUp .4s .32s both;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 20px;
            font-size: .88rem;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--muted); }
        .detail-value { font-weight: 600; color: #fff; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 700;
        }
        .status-badge.ok {
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(167,139,250,.2);
        }
        .status-badge.pending {
            background: rgba(245,158,11,.1);
            color: #f59e0b;
            border: 1px solid rgba(245,158,11,.2);
        }
        .status-dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .status-dot.pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse {
            0%,100% { opacity:1; }
            50%      { opacity:.3; }
        }

        /* ── Notice ── */
        .notice {
            background: rgba(245,158,11,.07);
            border: 1px solid rgba(245,158,11,.2);
            border-radius: 14px;
            padding: 18px 20px;
            font-size: .88rem;
            color: rgba(255,255,255,.65);
            line-height: 1.6;
            margin-bottom: 24px;
            animation: fadeUp .4s .3s both;
        }
        .notice .pid {
            margin-top: 8px;
            font-family: monospace;
            font-size: .78rem;
            color: var(--muted);
            word-break: break-all;
        }

        /* ── Buttons ── */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            animation: fadeUp .4s .38s both;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: .95rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform .15s, box-shadow .15s, background .2s;
        }
        .btn:active { transform: scale(.97); }
        .btn-primary {
            background: linear-gradient(135deg, #c32178, #74155d);
            color: #fff;
            box-shadow: 0 4px 20px var(--primary-glow);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 28px rgba(195,33,120,.55);
            background: linear-gradient(135deg, #e02e8e, #8c1a70);
        }
        .btn-ghost {
            background: var(--surface);
            border: 1px solid var(--border);
            color: rgba(255,255,255,.6);
        }
        .btn-ghost:hover { background: rgba(255,255,255,.07); color:#fff; }

        .or-row {
            display: flex; align-items: center; gap:12px;
            color: var(--muted); font-size:.8rem;
        }
        .or-row::before, .or-row::after {
            content:''; flex:1; height:1px; background: var(--border);
        }

        .back-link {
            margin-top: 22px;
            text-align: center;
            font-size: .82rem;
            color: var(--muted);
            animation: fadeUp .4s .42s both;
        }
        .back-link a { color: var(--primary); text-decoration:none; }
        .back-link a:hover { text-decoration:underline; }

        @media (max-width: 560px) {
            .card { padding: 32px 20px; }
            h1 { font-size: 1.6rem; }
            .impact { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="card">

    <div class="icon-wrap">🙌</div>

    <h1>Спасибо за поддержку!</h1>
    <p class="subtitle">Ваш взнос помогает платформе существовать и держать комиссию для разработчиков на уровне 0%.</p>

    <div class="impact">
        <div class="impact-item">
            <div class="impact-icon">🖥️</div>
            <div class="impact-label">Серверы и инфраструктура</div>
        </div>
        <div class="impact-item">
            <div class="impact-icon">🎮</div>
            <div class="impact-label">0% комиссии для разработчиков</div>
        </div>
        <div class="impact-item">
            <div class="impact-icon">📉</div>
            <div class="impact-label">Снижение цен на игры</div>
        </div>
    </div>

    <?php if ($infra): ?>
    <div class="details">
        <div class="detail-row">
            <span class="detail-label">Номер платежа</span>
            <span class="detail-value">#<?= htmlspecialchars($infra['id']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Сумма</span>
            <span class="detail-value"><?= number_format($infra['amount'], 0, ',', ' ') ?> ₽</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Дата</span>
            <span class="detail-value"><?= date('d.m.Y, H:i') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Статус</span>
            <span>
                <?php if ($isPaid): ?>
                    <span class="status-badge ok">
                        <span class="status-dot"></span> Получено
                    </span>
                <?php else: ?>
                    <span class="status-badge pending">
                        <span class="status-dot pulse"></span> Обрабатывается
                    </span>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php else: ?>
    <div class="notice">
        Платёж получен и обрабатывается. Спасибо!
        <?php if ($paymentId): ?>
            <div class="pid">ID платежа: <?= htmlspecialchars($paymentId) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="actions">
        <a href="/explore" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Смотреть игры
        </a>
        <div class="or-row">или</div>
        <a href="/library" class="btn btn-ghost">Моя библиотека</a>
    </div>

    <div class="back-link">
        <a href="https://vk.com/im?entrypoint=website&media=&sel=-208261651" target="_blank">Написать в техподдержку</a>
    </div>

</div>

</body>
</html>