<?php
session_start();
require_once __DIR__ . '/../swad/config.php';

$db   = new Database();
$conn = $db->connect();

$user = $_SESSION['USERDATA'] ?? null;

// Статистика для живости страницы
$totalExperts  = (int)$conn->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
$totalReviewed = (int)$conn->query("SELECT COUNT(DISTINCT game_id) FROM moderation_reviews")->fetchColumn();
$totalApproved = (int)$conn->query("SELECT COUNT(*) FROM games WHERE moderation_status='approved'")->fetchColumn();

// Проверяем, есть ли у пользователя уже заявка/статус эксперта
$userExpertStatus = null;
if ($user) {
    $st = $conn->prepare("SELECT status FROM experts WHERE user_id = ?");
    $st->execute([$user['id']]);
    $userExpertStatus = $st->fetchColumn();
}

// Текущий период выборов
$election = $conn->query("
    SELECT * FROM expert_elections
    WHERE NOW() BETWEEN start_date AND end_date
    ORDER BY id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Экспертная оценка — Dustore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0e13;
            --surf: #131720;
            --elev: #1a2030;
            --border: #232b3a;
            --text: #e8edf5;
            --muted: #6b7a99;
            --accent: #4ade80;
            --accent2: #22d3ee;
            --p: #c32178;
            --warn: #fbbf24;
            --danger: #f87171;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            line-height: 1.6;
        }

        /* ── NAV ── */
        nav {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            height: 60px;
            background: rgba(11, 14, 19, .85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .nav-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            text-decoration: none;
            color: var(--text);
        }

        .nav-logo span {
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: .88rem;
            transition: all .2s;
        }

        .nav-links a:hover {
            color: var(--text);
            background: var(--elev);
        }

        .nav-links .btn-nav {
            background: var(--p);
            color: #fff;
            font-weight: 600;
        }

        .nav-links .btn-nav:hover {
            background: #a01860;
            color: #fff;
        }

        /* ── HERO ── */
        .hero {
            position: relative;
            overflow: hidden;
            padding: 100px 32px 80px;
            text-align: center;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 50% 0%, rgba(195, 33, 120, .18) 0%, transparent 70%),
                radial-gradient(ellipse 40% 30% at 80% 80%, rgba(34, 211, 238, .08) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(195, 33, 120, .12);
            border: 1px solid rgba(195, 33, 120, .25);
            border-radius: 20px;
            padding: 5px 16px;
            font-size: .78rem;
            font-weight: 700;
            color: var(--p);
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: var(--p);
        }

        .hero p {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 580px;
            margin: 0 auto 40px;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 24px;
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .95rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all .2s;
        }

        .btn-primary {
            background: var(--p);
            color: #fff;
        }

        .btn-primary:hover {
            background: #a01860;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(195, 33, 120, .3);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            border-color: var(--muted);
            background: var(--elev);
        }

        /* ── STATS BAR ── */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .stat-item {
            flex: 1;
            max-width: 220px;
            padding: 28px 20px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-num {
            font-family: 'Syne', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-num.accent {
            color: var(--accent);
        }

        .stat-num.cyan {
            color: var(--accent2);
        }

        .stat-num.pink {
            color: var(--p);
        }

        .stat-label {
            font-size: .82rem;
            color: var(--muted);
        }

        /* ── SECTION ── */
        section {
            padding: 80px 32px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .section-eyebrow {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--p);
            margin-bottom: 10px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            margin-bottom: 12px;
        }

        .section-sub {
            font-size: .95rem;
            color: var(--muted);
            max-width: 560px;
            margin-bottom: 48px;
        }

        /* ── HOW IT WORKS ── */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .step-card {
            background: var(--surf);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            position: relative;
            transition: border-color .2s, transform .2s;
        }

        .step-card:hover {
            border-color: rgba(195, 33, 120, .3);
            transform: translateY(-2px);
        }

        .step-num {
            font-family: 'Syne', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: rgba(195, 33, 120, .2);
            line-height: 1;
            margin-bottom: 12px;
        }

        .step-icon {
            font-size: 1.6rem;
            margin-bottom: 12px;
        }

        .step-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .step-card p {
            font-size: .84rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ── CRITERIA ── */
        .criteria-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 12px;
        }

        .criterion {
            background: var(--surf);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .criterion-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .criterion h4 {
            font-family: 'Syne', sans-serif;
            font-size: .88rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .criterion p {
            font-size: .78rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .criterion .weight {
            margin-left: auto;
            font-size: .72rem;
            font-weight: 700;
            color: var(--muted);
            white-space: nowrap;
            padding-top: 2px;
        }

        /* ── GQI EXPLAIN ── */
        .gqi-box {
            background: var(--surf);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 32px;
            align-items: center;
        }

        .gqi-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--p) 0% 78%, var(--border) 78% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }

        .gqi-circle::before {
            content: '';
            position: absolute;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--surf);
        }

        .gqi-circle span {
            position: relative;
            z-index: 1;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--p);
        }

        .gqi-weights {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .gqi-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .gqi-label {
            font-size: .82rem;
            color: var(--muted);
            width: 140px;
            flex-shrink: 0;
        }

        .gqi-track {
            flex: 1;
            height: 6px;
            background: var(--elev);
            border-radius: 3px;
            overflow: hidden;
        }

        .gqi-fill {
            height: 100%;
            border-radius: 3px;
        }

        .gqi-pct {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text);
            width: 32px;
            text-align: right;
        }

        /* ── ELECTION BANNER ── */
        .election-banner {
            background: linear-gradient(135deg, rgba(74, 222, 128, .08), rgba(34, 211, 238, .06));
            border: 1px solid rgba(74, 222, 128, .2);
            border-radius: 16px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 0;
        }

        .election-icon {
            font-size: 3rem;
            flex-shrink: 0;
        }

        .election-banner h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .election-banner p {
            font-size: .86rem;
            color: var(--muted);
        }

        /* ── REQUIREMENTS ── */
        .req-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .req-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: var(--surf);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .req-check {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: rgba(74, 222, 128, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .req-check .material-icons {
            font-size: 14px;
            color: var(--accent);
        }

        .req-item span {
            font-size: .88rem;
            line-height: 1.5;
        }

        /* ── FAQ ── */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .faq-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .faq-item+.faq-item {
            margin-top: 8px;
        }

        .faq-q {
            padding: 16px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            font-size: .92rem;
            background: var(--surf);
            transition: background .15s;
            user-select: none;
        }

        .faq-q:hover {
            background: var(--elev);
        }

        .faq-q .material-icons {
            transition: transform .25s;
            color: var(--muted);
            font-size: 20px;
        }

        .faq-item.open .faq-q .material-icons {
            transform: rotate(180deg);
        }

        .faq-a {
            display: none;
            padding: 0 20px 16px;
            font-size: .88rem;
            color: var(--muted);
            line-height: 1.7;
            background: var(--surf);
            border-top: 1px solid var(--border);
        }

        .faq-item.open .faq-a {
            display: block;
            padding-top: 14px;
        }

        /* ── CTA ── */
        .cta-block {
            background: var(--surf);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 56px 40px;
            text-align: center;
        }

        .cta-block h2 {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .cta-block p {
            font-size: .95rem;
            color: var(--muted);
            margin-bottom: 32px;
        }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--border);
            padding: 32px;
            text-align: center;
            font-size: .82rem;
            color: var(--muted);
        }

        footer a {
            color: var(--muted);
            text-decoration: none;
        }

        footer a:hover {
            color: var(--text);
        }

        @media (max-width: 700px) {
            nav {
                padding: 0 16px;
            }

            .hero {
                padding: 60px 16px 50px;
            }

            section {
                padding: 50px 16px;
            }

            .gqi-box {
                grid-template-columns: 1fr;
            }

            .election-banner {
                flex-direction: column;
                text-align: center;
            }

            .stats-bar {
                flex-wrap: wrap;
            }

            .stat-item {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
        }
    </style>
</head>

<body>

    <nav>
        <a href="/" class="nav-logo">Du<span>store</span></a>
        <div class="nav-links">
            <a href="/games">Каталог</a>
            <?php if ($user): ?>
                <a href="/me">@<?= htmlspecialchars($user['username'] ?? 'Профиль') ?></a>
                <?php if ($userExpertStatus === 'approved'): ?>
                    <a href="/expert/admin/" class="btn-nav btn">Панель эксперта</a>
                <?php elseif (!$userExpertStatus && $election): ?>
                    <a href="/expert/expert-apply" class="btn-nav btn">Подать заявку</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="/login">Войти</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ── HERO ── -->
    <div class="hero">
        <div class="hero-eyebrow">
            <span class="material-icons" style="font-size:14px;">verified</span>
            Программа экспертной оценки
        </div>
        <h1>Кто решает, какие игры<br>попадают на <span>Dustore</span>?</h1>
        <p>Не алгоритмы. Не один модератор. Сообщество экспертов — реальных людей, которые разбираются в геймдеве.</p>
        <div class="hero-actions">
            <?php if ($election && !$userExpertStatus): ?>
                <a href="/expert/expert-apply" class="btn btn-primary">
                    <span class="material-icons" style="font-size:18px;">how_to_vote</span>
                    Стать экспертом
                </a>
            <?php elseif ($userExpertStatus === 'approved'): ?>
                <a href="/expert/admin/" class="btn btn-primary">
                    <span class="material-icons" style="font-size:18px;">dashboard</span>
                    Открыть панель
                </a>
            <?php else: ?>
                <a href="#how-it-works" class="btn btn-primary">
                    <span class="material-icons" style="font-size:18px;">play_arrow</span>
                    Как это работает
                </a>
            <?php endif; ?>
            <a href="/games" class="btn btn-ghost">Смотреть одобренные игры</a>
        </div>
    </div>

    <!-- ── STATS ── -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-num accent"><?= $totalExperts ?></div>
            <div class="stat-label">активных экспертов</div>
        </div>
        <div class="stat-item">
            <div class="stat-num cyan"><?= $totalReviewed ?></div>
            <div class="stat-label">игр прошло проверку</div>
        </div>
        <div class="stat-item">
            <div class="stat-num pink"><?= $totalApproved ?></div>
            <div class="stat-label">опубликовано</div>
        </div>
        <div class="stat-item">
            <div class="stat-num" style="color:var(--warn);">51%</div>
            <div class="stat-label">порог положительных оценок</div>
        </div>
    </div>

    <!-- ── HOW IT WORKS ── -->
    <section id="how-it-works">
        <div class="section-eyebrow">Процесс</div>
        <h2 class="section-title">Как проходит оценка</h2>
        <p class="section-sub">От отправки разработчиком до публикации в каталоге — прозрачный процесс без чёрных ящиков.</p>

        <div class="steps">
            <div class="step-card">
                <div class="step-num">01</div>
                <div class="step-icon">📤</div>
                <h3>Разработчик отправляет игру</h3>
                <p>Игра проходит чеклист обязательных требований и попадает в очередь на экспертизу.</p>
            </div>
            <div class="step-card">
                <div class="step-num">02</div>
                <div class="step-icon">🔔</div>
                <h3>Эксперты получают уведомление</h3>
                <p>Все активные эксперты видят новую игру в своей панели и могут приступить к оценке.</p>
            </div>
            <div class="step-card">
                <div class="step-num">03</div>
                <div class="step-icon">🎮</div>
                <h3>Оценка по 7 критериям</h3>
                <p>Эксперт скачивает билд, проходит игру и выставляет оценку по каждому из критериев плюс общий вердикт.</p>
            </div>
            <div class="step-card">
                <div class="step-num">04</div>
                <div class="step-icon">📊</div>
                <h3>Подсчёт голосов</h3>
                <p>Нужно набрать более 51% положительных голосов. Результат считается автоматически в реальном времени.</p>
            </div>
            <div class="step-card">
                <div class="step-num">05</div>
                <div class="step-icon">✅</div>
                <h3>Публикация или доработка</h3>
                <p>Одобренные игры разработчик публикует сам. Не одобренные — получают рецензии и могут быть доработаны.</p>
            </div>
        </div>
    </section>

    <!-- ── CRITERIA ── -->
    <section style="background: var(--surf); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 80px 0;">
        <div style="max-width:1100px; margin:0 auto; padding: 0 32px;">
            <div class="section-eyebrow">Критерии</div>
            <h2 class="section-title">По чему оценивается игра</h2>
            <p class="section-sub">Каждый критерий влияет на итоговый GQI — Game Quality Index.</p>

            <div class="criteria-grid">
                <?php
                $criteria = [
                    ['🎮', 'Геймплей',      'Механики, баланс, реиграбельность, отзывчивость управления.',  '20%', '#22d3ee', 'rgba(34,211,238,.12)'],
                    ['🎨', 'Визуал',         'Арт-стиль, UI, анимации — визуальная целостность.',             '12%', '#a78bfa', 'rgba(167,139,250,.12)'],
                    ['🔧', 'Стабильность',   'Отсутствие критических багов, вылетов, просадок FPS.',          '12%', '#4ade80', 'rgba(74,222,128,.12)'],
                    ['💡', 'Оригинальность', 'Насколько идея и реализация выделяется на рынке.',               '6%',  '#fbbf24', 'rgba(251,191,36,.12)'],
                    ['🎵', 'Звук и музыка',  'Качество OST, звуковых эффектов, звуковая атмосфера.',           '5%',  '#fb923c', 'rgba(251,146,60,.12)'],
                    ['📖', 'Глубина',        'Объём контента, продолжительность, реиграбельность.',            '5%',  '#f472b6', 'rgba(244,114,182,.12)'],
                    ['⭐', 'Общая оценка',   'Субъективное общее впечатление эксперта от игры.',               '40%', '#c32178', 'rgba(195,33,120,.12)'],
                ];
                foreach ($criteria as [$icon, $name, $desc, $weight, $col, $bg]): ?>
                    <div class="criterion">
                        <div class="criterion-icon" style="background:<?= $bg ?>; color:<?= $col ?>;">
                            <?= $icon ?>
                        </div>
                        <div style="flex:1;">
                            <h4><?= $name ?></h4>
                            <p><?= $desc ?></p>
                        </div>
                        <div class="weight"><?= $weight ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ── GQI ── -->
    <section>
        <div class="section-eyebrow">Метрика качества</div>
        <h2 class="section-title">Что такое GQI</h2>
        <p class="section-sub">Game Quality Index — взвешенная оценка игры на основе голосов всех экспертов. Обновляется после каждого голоса.</p>

        <div class="gqi-box">
            <div>
                <div class="gqi-circle"><span>78</span></div>
                <div style="text-align:center; margin-top:10px; font-size:.78rem; color:var(--muted);">пример GQI</div>
            </div>
            <div>
                <div style="font-size:.82rem; color:var(--muted); margin-bottom:16px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">
                    Веса критериев в расчёте GQI
                </div>
                <div class="gqi-weights">
                    <?php
                    $weights = [
                        ['Общая оценка', 40, '#c32178'],
                        ['Геймплей',     20, '#22d3ee'],
                        ['Визуал',       12, '#a78bfa'],
                        ['Стабильность', 12, '#4ade80'],
                        ['Оригинальность', 6, '#fbbf24'],
                        ['Звук',          5, '#fb923c'],
                        ['Глубина',       5, '#f472b6'],
                    ];
                    foreach ($weights as [$label, $pct, $col]): ?>
                        <div class="gqi-row">
                            <div class="gqi-label"><?= $label ?></div>
                            <div class="gqi-track">
                                <div class="gqi-fill" style="width:<?= $pct * 2 ?>%; background:<?= $col ?>;"></div>
                            </div>
                            <div class="gqi-pct" style="color:<?= $col ?>;"><?= $pct ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ── ELECTION BANNER ── -->
    <?php if ($election): ?>
        <section style="padding-top:0;">
            <div class="election-banner">
                <div class="election-icon">🗳️</div>
                <div style="flex:1;">
                    <h3>Идут выборы новых экспертов!</h3>
                    <p>
                        Приём заявок открыт до
                        <strong style="color:var(--accent);">
                            <?= date('d.m.Y', strtotime($election['end_date'])) ?>
                        </strong>.
                        Подайте заявку — сообщество проголосует за лучших кандидатов.
                    </p>
                </div>
                <?php if (!$userExpertStatus): ?>
                    <a href="/expert/expert-apply" class="btn btn-primary" style="flex-shrink:0;">
                        Подать заявку →
                    </a>
                <?php else: ?>
                    <a href="/expert/expert-apply" class="btn btn-primary" style="flex-shrink:0;">
                        Просмотр заявки
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── REQUIREMENTS ── -->
    <section style="background:var(--surf); border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:80px 0;">
        <div style="max-width:1100px; margin:0 auto; padding:0 32px;">
            <div class="section-eyebrow">Требования</div>
            <h2 class="section-title">Кто может стать экспертом</h2>
            <p class="section-sub">Эксперт — не профессия и не звание. Это ответственность.</p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <div style="font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--accent); margin-bottom:12px;">✅ Нужно</div>
                    <div class="req-list">
                        <?php
                        $reqs = [
                            'Опыт в геймдеве, игровой критике или тестировании',
                            'Умение давать аргументированную обратную связь',
                            'Возможность тестировать игры на ПК (Windows/Linux/macOS)',
                            'Готовность оценивать честно, даже если игра нравится автору',
                            'Регулярное участие — минимум 1 оценка за период выборов',
                        ];
                        foreach ($reqs as $r): ?>
                            <div class="req-item">
                                <div class="req-check"><span class="material-icons">check</span></div>
                                <span><?= $r ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--danger); margin-bottom:12px;">❌ Не подходит</div>
                    <div class="req-list">
                        <?php
                        $nreqs = [
                            'Оценивать игры своих знакомых или коллег предвзято',
                            'Писать рецензии в два слова без аргументов',
                            'Систематически игнорировать игры в очереди',
                            'Использовать статус для продвижения своих проектов',
                        ];
                        foreach ($nreqs as $r): ?>
                            <div class="req-item" style="border-color: rgba(248,113,113,.2);">
                                <div class="req-check" style="background:rgba(248,113,113,.1);">
                                    <span class="material-icons" style="color:var(--danger);">close</span>
                                </div>
                                <span style="color:var(--muted);"><?= $r ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── FAQ ── -->
    <section>
        <div class="section-eyebrow">Вопросы и ответы</div>
        <h2 class="section-title">FAQ</h2>
        <p class="section-sub" style="margin-bottom:32px;">Самые частые вопросы об экспертной программе.</p>

        <div class="faq-list">
            <?php
            $faqs = [
                [
                    'Сколько экспертов должны проголосовать за игру?',
                    'Нужно набрать более 51% положительных голосов от числа всех активных экспертов. Например, при 10 экспертах нужно минимум 6 голосов «Рекомендую».'
                ],
                [
                    'Анонимны ли рецензии?',
                    'Да — рецензии экспертов анонимны для разработчика до завершения голосования. После принятия решения разработчик видит агрегированные оценки.'
                ],
                [
                    'Что происходит, если игра не прошла?',
                    'Разработчик получает обратную связь с рецензиями, дорабатывает игру и может отправить её на повторную модерацию. Предыдущие голоса при этом сбрасываются.'
                ],
                [
                    'Как долго идёт голосование?',
                    'Голосование идёт до тех пор, пока игра не наберёт достаточно голосов в ту или иную сторону. Нет жёсткого дедлайна — всё зависит от активности экспертов.'
                ],
                [
                    'Что такое «Вернуть на доработку»?',
                    'Это отличается от отклонения. Эксперт считает, что игра перспективна, но сырая — и рекомендует разработчику доработать конкретные аспекты, не завершая голосование.'
                ],
                [
                    'Могу ли я стать экспертом без опыта в геймдеве?',
                    'Строгих формальных требований нет, но ценится опыт в игровой индустрии, критике или тестировании. Сообщество голосует за кандидатов — решение за ним.'
                ],
            ];
            foreach ($faqs as $i => [$q, $a]): ?>
                <div class="faq-item" id="faq-<?= $i ?>">
                    <div class="faq-q" onclick="toggleFaq(<?= $i ?>)">
                        <?= htmlspecialchars($q) ?>
                        <span class="material-icons">expand_more</span>
                    </div>
                    <div class="faq-a"><?= htmlspecialchars($a) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── CTA ── -->
    <section style="padding-top:0; padding-bottom:80px;">
        <div class="cta-block">
            <h2>Формируй каталог вместе с нами</h2>
            <p>Эксперты Dustore — это люди, которым небезразлично качество российского инди-геймдева.</p>
            <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
                <?php if ($election && !$userExpertStatus): ?>
                    <a href="/expert/expert-apply" class="btn btn-primary">
                        <span class="material-icons" style="font-size:18px;">how_to_vote</span>
                        Подать заявку на эксперта
                    </a>
                <?php elseif ($userExpertStatus === 'approved'): ?>
                    <a href="/expert/admin/" class="btn btn-primary">
                        <span class="material-icons" style="font-size:18px;">dashboard</span>
                        Перейти в панель эксперта
                    </a>
                <?php else: ?>
                    <a href="/games" class="btn btn-primary">
                        <span class="material-icons" style="font-size:18px;">sports_esports</span>
                        Смотреть одобренные игры
                    </a>
                <?php endif; ?>
                <a href="/devs/" class="btn btn-ghost">Для разработчиков →</a>
            </div>
        </div>
    </section>

    <footer>
        <p>© <?= date('Y') ?> Dustore · <a href="/about">О платформе</a> · <a href="/devs/">Разработчикам</a></p>
    </footer>

    <script>
        function toggleFaq(i) {
            const el = document.getElementById('faq-' + i);
            el.classList.toggle('open');
        }
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                const target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>

</html>