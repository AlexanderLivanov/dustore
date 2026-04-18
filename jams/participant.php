<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GameForge — Панель участника</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            min-height: 100vh;
            background: #0d0414;
            font-family: 'Manrope', system-ui, sans-serif;
            color: #e8ddf0;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(195, 33, 120, .11) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 110%, rgba(120, 20, 80, .08) 0%, transparent 55%);
        }

        ::-webkit-scrollbar {
            width: 4px
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .3);
            border-radius: 4px
        }

        .header {
            background: rgba(13, 4, 20, .96);
            border-bottom: 1px solid rgba(195, 33, 120, .18);
            padding: 13px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(12px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 800;
            color: #e8ddf0
        }

        .logo .brand {
            color: #c32178
        }

        .logo .sep {
            color: rgba(255, 255, 255, .2);
            margin: 0 2px
        }

        .nav-btn {
            padding: 7px 15px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .5);
            transition: .15s;
            text-decoration: none;
            display: inline-block;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, .1);
            color: #e8ddf0
        }

        .nav-btn.active {
            background: rgba(195, 33, 120, .15);
            color: #e8ddf0;
            border: 1px solid rgba(195, 33, 120, .3)
        }

        .timer-badge {
            background: rgba(195, 33, 120, .12);
            border: 1px solid rgba(195, 33, 120, .3);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            color: #e8ddf0;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        /* ── LAYOUT ── */
        .participant-layout {
            display: flex;
            min-height: calc(100vh - 54px)
        }

        .sidebar {
            width: 230px;
            flex-shrink: 0;
            background: rgba(0, 0, 0, .25);
            border-right: 1px solid rgba(255, 255, 255, .07);
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-section {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255, 255, 255, .25);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 10px 10px 5px;
            margin-top: 6px;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, .45);
            transition: .15s;
            border: 1px solid transparent;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, .05);
            color: #e8ddf0
        }

        .sidebar-item.active {
            background: rgba(195, 33, 120, .12);
            color: #e8ddf0;
            border-color: rgba(195, 33, 120, .25)
        }

        .sidebar-item .ico {
            font-size: 16px;
            width: 20px;
            text-align: center
        }

        .s-new {
            font-size: 9px;
            font-weight: 700;
            background: #c32178;
            color: #fff;
            border-radius: 4px;
            padding: 1px 5px;
            margin-left: 4px;
        }

        /* ── SPRINT CARD (sidebar) ── */
        .sprint-info-card {
            background: rgba(195, 33, 120, .07);
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .si-title {
            font-size: 13px;
            font-weight: 800;
            color: #e8ddf0;
            margin-bottom: 3px
        }

        .si-host {
            font-size: 11px;
            color: rgba(255, 255, 255, .35);
            margin-bottom: 10px
        }

        .si-stat {
            font-size: 11px;
            color: rgba(255, 255, 255, .4);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between
        }

        .si-stat span {
            color: #e8ddf0;
            font-weight: 600
        }

        .countdown-mini {
            background: rgba(0, 0, 0, .3);
            border-radius: 7px;
            padding: 8px;
            text-align: center;
            margin-top: 10px;
        }

        .countdown-mini .cm-val {
            font-size: 18px;
            font-weight: 800;
            color: #c32178;
            font-variant-numeric: tabular-nums
        }

        .countdown-mini .cm-lbl {
            font-size: 10px;
            color: rgba(255, 255, 255, .3);
            margin-top: 2px
        }

        /* ── MAIN ── */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 26px 28px;
            max-height: calc(100vh - 54px)
        }

        .main-content::-webkit-scrollbar {
            width: 4px
        }

        .main-content::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .2);
            border-radius: 4px
        }

        .view {
            display: none
        }

        .view.active {
            display: block
        }

        .page-title {
            font-size: 20px;
            font-weight: 800;
            color: #e8ddf0;
            margin-bottom: 4px;
            letter-spacing: -.3px
        }

        .page-sub {
            color: rgba(255, 255, 255, .35);
            font-size: 13px;
            margin-bottom: 24px
        }

        /* ── WELCOME HERO ── */
        .welcome-hero {
            background: linear-gradient(135deg, rgba(195, 33, 120, .12), rgba(120, 20, 80, .06));
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }

        .welcome-hero::after {
            content: '🎮';
            position: absolute;
            right: -10px;
            top: -10px;
            font-size: 100px;
            opacity: .06;
            pointer-events: none;
        }

        .wh-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px
        }

        .wh-avatar {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: rgba(195, 33, 120, .2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0
        }

        .wh-name {
            font-size: 18px;
            font-weight: 800;
            color: #e8ddf0
        }

        .wh-sub {
            color: rgba(255, 255, 255, .4);
            font-size: 12px;
            margin-top: 2px
        }

        .wh-status {
            margin-left: auto;
            background: rgba(34, 197, 94, .1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .25);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .wh-progress-row {
            display: flex;
            align-items: center;
            gap: 14px
        }

        .wh-prog-label {
            font-size: 12px;
            color: rgba(255, 255, 255, .4);
            flex-shrink: 0
        }

        .wh-prog-bar {
            flex: 1;
            height: 6px;
            background: rgba(255, 255, 255, .06);
            border-radius: 99px;
            overflow: hidden
        }

        .wh-prog-fill {
            height: 100%;
            background: #c32178;
            border-radius: 99px;
            transition: width 1s ease
        }

        .wh-prog-val {
            font-size: 12px;
            font-weight: 700;
            color: #c32178;
            flex-shrink: 0
        }

        /* ── GRID CARDS ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px
        }

        .stat-card {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 15px;
            transition: .15s
        }

        .stat-card:hover {
            border-color: rgba(195, 33, 120, .25)
        }

        .stat-card .sc-ico {
            font-size: 20px;
            margin-bottom: 7px
        }

        .stat-card .sc-val {
            font-size: 20px;
            font-weight: 800;
            color: #e8ddf0;
            margin-bottom: 2px
        }

        .stat-card .sc-lbl {
            font-size: 11px;
            color: rgba(255, 255, 255, .35)
        }

        /* ── CARD ── */
        .card {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 14px
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card:hover {
            border-color: rgba(195, 33, 120, .2)
        }

        /* ── TEAM ── */
        .team-member {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .team-member:last-child {
            border-bottom: none;
            padding-bottom: 0
        }

        .tm-av {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: rgba(195, 33, 120, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0
        }

        .tm-name {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0
        }

        .tm-role {
            font-size: 11px;
            color: rgba(255, 255, 255, .35)
        }

        .tm-status {
            margin-left: auto;
            font-size: 11px
        }

        .tm-status.online {
            color: #22c55e
        }

        .tm-status.away {
            color: rgba(255, 255, 255, .3)
        }

        .btn-sm {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: .15s;
            background: rgba(195, 33, 120, .1);
            border: 1px solid rgba(195, 33, 120, .25);
            color: #e8ddf0;
        }

        .btn-sm:hover {
            background: rgba(195, 33, 120, .2)
        }

        .btn-sm-ghost {
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: .15s;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .5);
        }

        .btn-sm-ghost:hover {
            background: rgba(255, 255, 255, .1);
            color: #e8ddf0
        }

        /* ── SUBMISSION ── */
        .submit-area {
            background: rgba(195, 33, 120, .05);
            border: 1px dashed rgba(195, 33, 120, .3);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: .15s;
            margin-bottom: 14px;
        }

        .submit-area:hover {
            background: rgba(195, 33, 120, .1);
            border-color: rgba(195, 33, 120, .5)
        }

        .submit-area .sa-ico {
            font-size: 36px;
            margin-bottom: 10px
        }

        .submit-area .sa-title {
            font-size: 15px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 4px
        }

        .submit-area .sa-sub {
            font-size: 12px;
            color: rgba(255, 255, 255, .35)
        }

        .form-input-s {
            width: 100%;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 8px;
            padding: 9px 13px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: .15s;
        }

        .form-input-s:focus {
            border-color: #c32178
        }

        .form-input-s::placeholder {
            color: rgba(255, 255, 255, .25)
        }

        .form-label-s {
            display: block;
            color: rgba(255, 255, 255, .4);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px
        }

        .form-row-s {
            margin-bottom: 13px
        }

        .form-textarea-s {
            width: 100%;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 8px;
            padding: 9px 13px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: .15s;
            resize: vertical;
            min-height: 80px;
        }

        .form-textarea-s:focus {
            border-color: #c32178
        }

        .form-textarea-s::placeholder {
            color: rgba(255, 255, 255, .25)
        }

        .btn-primary {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 8px;
            padding: 11px 22px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            transition: .15s;
        }

        .btn-primary:hover {
            background: #9e1a66
        }

        /* ── CRITERIA ── */
        .criteria-item {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }

        .ci-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px
        }

        .ci-name {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0
        }

        .ci-weight {
            font-size: 11px;
            color: #c32178;
            font-weight: 700
        }

        .ci-desc {
            font-size: 12px;
            color: rgba(255, 255, 255, .4);
            line-height: 1.5
        }

        .ci-bar {
            height: 3px;
            background: rgba(255, 255, 255, .06);
            border-radius: 99px;
            margin-top: 8px;
            overflow: hidden
        }

        .ci-bar-fill {
            height: 100%;
            background: #c32178;
            border-radius: 99px
        }

        /* ── SCOREBOARD ── */
        .score-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .score-item:last-child {
            border-bottom: none;
            padding-bottom: 0
        }

        .score-rank {
            font-size: 16px;
            width: 26px;
            text-align: center;
            flex-shrink: 0
        }

        .score-info {
            flex: 1;
            min-width: 0
        }

        .score-name {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0
        }

        .score-team {
            font-size: 11px;
            color: rgba(255, 255, 255, .3)
        }

        .score-val {
            font-size: 15px;
            font-weight: 800;
            color: #c32178;
            flex-shrink: 0
        }

        .score-item.me {
            background: rgba(195, 33, 120, .06);
            border-radius: 8px;
            padding: 10px 10px;
            margin: 0 -10px;
            border-bottom: none;
            border: 1px solid rgba(195, 33, 120, .2);
        }

        .me-badge {
            font-size: 10px;
            background: rgba(195, 33, 120, .2);
            color: #e8ddf0;
            border-radius: 4px;
            padding: 1px 6px;
            margin-left: 6px
        }

        /* ── ANNOUNCEMENTS ── */
        .ann-item {
            background: rgba(255, 255, 255, .03);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }

        .ann-item.new-ann {
            border-color: rgba(195, 33, 120, .3);
            background: rgba(195, 33, 120, .05)
        }

        .ann-title {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 7px
        }

        .ann-body {
            font-size: 12px;
            color: rgba(255, 255, 255, .5);
            line-height: 1.6;
            margin-bottom: 5px
        }

        .ann-meta {
            font-size: 10px;
            color: rgba(255, 255, 255, .25)
        }

        /* ── L4T BLOCK ── */
        .l4t-promo {
            background: linear-gradient(135deg, rgba(195, 33, 120, .1), rgba(120, 20, 80, .06));
            border: 1px solid rgba(195, 33, 120, .25);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .l4t-ico {
            font-size: 32px;
            flex-shrink: 0
        }

        .l4t-text .lt-title {
            font-size: 14px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 3px
        }

        .l4t-text .lt-desc {
            font-size: 12px;
            color: rgba(255, 255, 255, .4);
            line-height: 1.5
        }

        .l4t-action {
            margin-left: auto;
            flex-shrink: 0
        }

        /* ── TAGS ── */
        .tag {
            background: rgba(195, 33, 120, .1);
            border: 1px solid rgba(195, 33, 120, .2);
            color: rgba(195, 33, 120, .9);
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600
        }

        .tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 8px
        }
    </style>
</head>

<body>

    <header class="header">
        <div style="display:flex;align-items:center;gap:16px">
            <div class="logo">🎮 <span class="brand">GameForge</span><span class="sep">/</span>Участник</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <div class="timer-badge">⏳ <span id="countdown-header">2д 4ч до старта</span></div>
            <a class="nav-btn" href="sprints">← К спринтам</a>
            <a class="nav-btn" href="admin">Админка</a>
        </div>
    </header>

    <div class="participant-layout">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sprint-info-card">
                <div class="si-title">Pixel Chaos Sprint #3</div>
                <div class="si-host">от GameDevClub</div>
                <div class="si-stat">Участников <span>134 / 200</span></div>
                <div class="si-stat">Длительность <span>72ч</span></div>
                <div class="si-stat">Движок <span>Любой</span></div>
                <div class="countdown-mini">
                    <div class="cm-val" id="countdown-sidebar">2д 4ч 12м</div>
                    <div class="cm-lbl">до старта</div>
                </div>
            </div>

            <div class="sidebar-section">Моё участие</div>
            <div class="sidebar-item active" onclick="showView('overview',this)">
                <span class="ico">🏠</span> Обзор
            </div>
            <div class="sidebar-item" onclick="showView('team',this)">
                <span class="ico">👥</span> Команда
            </div>
            <div class="sidebar-item" onclick="showView('submit',this)">
                <span class="ico">🚀</span> Сдать работу
            </div>

            <div class="sidebar-section">Спринт</div>
            <div class="sidebar-item" onclick="showView('scoreboard',this)">
                <span class="ico">🏆</span> Рейтинг
            </div>
            <div class="sidebar-item" onclick="showView('criteria',this)">
                <span class="ico">📋</span> Критерии
            </div>
            <div class="sidebar-item" onclick="showView('announcements',this)">
                <span class="ico">📢</span> Объявления <span class="s-new">1</span>
            </div>
            <div class="sidebar-item" onclick="showView('resources',this)">
                <span class="ico">📚</span> Ресурсы
            </div>
        </div>

        <!-- MAIN -->
        <div class="main-content">

            <!-- OVERVIEW -->
            <div class="view active" id="view-overview">
                <div class="welcome-hero">
                    <div class="wh-top">
                        <div class="wh-avatar">👤</div>
                        <div>
                            <div class="wh-name">@pixel_hero</div>
                            <div class="wh-sub">Геймдизайнер · Unity</div>
                        </div>
                        <div class="wh-status">✓ Зарегистрирован</div>
                    </div>
                    <div class="wh-progress-row">
                        <span class="wh-prog-label">Готовность</span>
                        <div class="wh-prog-bar">
                            <div class="wh-prog-fill" style="width:60%"></div>
                        </div>
                        <span class="wh-prog-val">60%</span>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-card">
                        <div class="sc-ico">👥</div>
                        <div class="sc-val">3/4</div>
                        <div class="sc-lbl">Членов команды</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🎮</div>
                        <div class="sc-val">0</div>
                        <div class="sc-lbl">Работ сдано</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🏆</div>
                        <div class="sc-val">—</div>
                        <div class="sc-lbl">Текущее место</div>
                    </div>
                </div>

                <div class="l4t-promo">
                    <div class="l4t-ico">🤝</div>
                    <div class="l4t-text">
                        <div class="lt-title">Нужен участник в команду?</div>
                        <div class="lt-desc">Найдите программиста, художника или саунд-дизайнера через биржу L4T — сообщество геймдевов.</div>
                    </div>
                    <div class="l4t-action">
                        <a href="#" class="btn-sm">Открыть L4T →</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">📋 Чеклист участника <span style="font-size:11px;color:rgba(255,255,255,.3)">3/5 выполнено</span></div>
                    <div id="checklist"></div>
                </div>

                <div class="card">
                    <div class="card-title">🎯 Тема спринта</div>
                    <div style="text-align:center;padding:20px 0">
                        <div style="font-size:36px;margin-bottom:10px">🔒</div>
                        <div style="font-size:15px;font-weight:700;color:#e8ddf0;margin-bottom:4px">Тема скрыта до старта</div>
                        <div style="font-size:12px;color:rgba(255,255,255,.35)">Тема откроется через 2д 4ч в момент начала спринта</div>
                    </div>
                </div>
            </div>

            <!-- TEAM -->
            <div class="view" id="view-team">
                <div class="page-title">Команда</div>
                <div class="page-sub">Art Robots · 3 из 4 мест заняты</div>

                <div class="card">
                    <div class="card-title">👥 Состав команды
                        <button class="btn-sm" onclick="alert('Ссылка-приглашение скопирована!')">🔗 Пригласить</button>
                    </div>
                    <div id="team-list"></div>
                    <div style="margin-top:14px;padding:12px;background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.1);border-radius:9px;text-align:center;cursor:pointer" onclick="alert('Поиск через L4T...')">
                        <div style="font-size:13px;color:rgba(255,255,255,.35)">🔍 Найти участника через <strong style="color:#c32178">L4T</strong></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">⚙️ Настройки команды</div>
                    <div class="form-row-s"><label class="form-label-s">Название команды</label><input class="form-input-s" value="Art Robots"></div>
                    <div class="form-row-s"><label class="form-label-s">Описание</label><textarea class="form-textarea-s" placeholder="Расскажите о вашей команде...">Команда из 3 человек, специализируемся на пиксель-арте и Unity разработке.</textarea></div>
                    <div style="display:flex;gap:8px;margin-top:4px">
                        <button class="btn-primary" onclick="alert('Сохранено!')">Сохранить</button>
                        <button class="btn-sm-ghost" onclick="confirm('Выйти из команды?')">Выйти из команды</button>
                    </div>
                </div>
            </div>

            <!-- SUBMIT -->
            <div class="view" id="view-submit">
                <div class="page-title">Сдать работу</div>
                <div class="page-sub">Загрузите вашу игру и заполните описание</div>

                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:12px;color:rgba(245,158,11,.9)">
                    ⚠️ Спринт ещё не начался. Сдача работ откроется с момента старта и до окончания 72 часов.
                </div>

                <div class="submit-area" onclick="alert('Сдача будет открыта после старта спринта!')">
                    <div class="sa-ico">🎮</div>
                    <div class="sa-title">Загрузить игру</div>
                    <div class="sa-sub">Перетащите файл или нажмите для выбора<br>Форматы: .zip, .rar, .7z / ссылка на itch.io</div>
                </div>

                <div class="card">
                    <div class="card-title">📝 Карточка работы</div>
                    <div class="form-row-s"><label class="form-label-s">Название игры <span style="color:#f87171">*</span></label><input class="form-input-s" placeholder="Введите название..." disabled></div>
                    <div class="form-row-s"><label class="form-label-s">Краткое описание <span style="color:#f87171">*</span></label><textarea class="form-textarea-s" placeholder="О чём ваша игра..." disabled></textarea></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:13px">
                        <div><label class="form-label-s">Движок</label><input class="form-input-s" placeholder="Unity / Godot / ..." disabled></div>
                        <div><label class="form-label-s">Ссылка (itch.io)</label><input class="form-input-s" placeholder="https://..." disabled></div>
                    </div>
                    <div class="form-row-s"><label class="form-label-s">Скриншоты</label>
                        <div style="display:flex;gap:8px">
                            <div style="width:80px;height:60px;border:1px dashed rgba(255,255,255,.15);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:20px;color:rgba(255,255,255,.2);cursor:pointer">+</div>
                        </div>
                    </div>
                    <button class="btn-primary" disabled style="opacity:.4;cursor:not-allowed">🚀 Сдать работу</button>
                </div>
            </div>

            <!-- SCOREBOARD -->
            <div class="view" id="view-scoreboard">
                <div class="page-title">Рейтинг</div>
                <div class="page-sub">Текущие позиции · Обновляется после окончания спринта</div>
                <div class="card">
                    <div class="card-title">🏆 Таблица лидеров</div>
                    <div id="scoreboard-list"></div>
                </div>
            </div>

            <!-- CRITERIA -->
            <div class="view" id="view-criteria">
                <div class="page-title">Критерии оценки</div>
                <div class="page-sub">Как будет оцениваться ваша игра</div>
                <div class="card">
                    <div class="card-title">📊 Критерии жюри</div>
                    <div id="criteria-list"></div>
                </div>
                <div class="card">
                    <div class="card-title">💡 Советы от экспертов</div>
                    <div style="font-size:13px;color:rgba(255,255,255,.5);line-height:1.8">
                        <p style="margin-bottom:10px">🎯 <strong style="color:#e8ddf0">Фокус на теме</strong> — убедитесь, что ваша игра явно связана с темой спринта. Жюри это ценит больше всего.</p>
                        <p style="margin-bottom:10px">🎮 <strong style="color:#e8ddf0">Играбельность важнее графики</strong> — лучше простая но работающая механика, чем красивый но сломанный прототип.</p>
                        <p>📱 <strong style="color:#e8ddf0">WebGL-версия</strong> — загрузите игру на itch.io в WebGL, это значительно увеличивает количество игроков и голосов.</p>
                    </div>
                </div>
            </div>

            <!-- ANNOUNCEMENTS -->
            <div class="view" id="view-announcements">
                <div class="page-title">Объявления</div>
                <div class="page-sub">Новости и обновления от организаторов</div>
                <div id="ann-list"></div>
            </div>

            <!-- RESOURCES -->
            <div class="view" id="view-resources">
                <div class="page-title">Ресурсы</div>
                <div class="page-sub">Полезные материалы для участников</div>
                <div class="card">
                    <div class="card-title">📚 Документация</div>
                    <div id="resources-list"></div>
                </div>
                <div class="card">
                    <div class="card-title">🛠 Бесплатные инструменты</div>
                    <div id="tools-list"></div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function showView(name, el) {
            document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
            document.getElementById('view-' + name).classList.add('active');
            document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
            if (el) el.classList.add('active');
        }

        // Countdown
        var startTime = Date.now() + 2 * 864e5 + 4 * 36e5;

        function updateCountdown() {
            var d = startTime - Date.now();
            if (d <= 0) {
                document.getElementById('countdown-header').textContent = 'Спринт идёт!';
                document.getElementById('countdown-sidebar').textContent = 'Идёт!';
                return;
            }
            var days = Math.floor(d / 864e5);
            var h = Math.floor((d % 864e5) / 36e5);
            var m = Math.floor((d % 36e5) / 6e4);
            var s = Math.floor((d % 6e4) / 1e3);
            var str = days + 'д ' + h + 'ч ' + m + 'м';
            document.getElementById('countdown-header').textContent = str + ' до старта';
            document.getElementById('countdown-sidebar').textContent = str + ' ' + s + 'с';
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Checklist
        var checks = [{
                done: true,
                text: 'Зарегистрироваться на спринт'
            },
            {
                done: true,
                text: 'Создать команду или выбрать соло'
            },
            {
                done: true,
                text: 'Ознакомиться с правилами'
            },
            {
                done: false,
                text: 'Подготовить инструменты и движок'
            },
            {
                done: false,
                text: 'Сдать финальную работу'
            },
        ];
        document.getElementById('checklist').innerHTML = checks.map(function(c) {
            return '<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.05)">' +
                '<div style="width:18px;height:18px;border-radius:5px;flex-shrink:0;display:flex;align-items:center;justify-content:center;' + (c.done ? 'background:#c32178;' : 'background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);') + '">' + (c.done ? '<span style="font-size:10px;color:#fff">✓</span>' : '') + '</div>' +
                '<span style="font-size:13px;' + (c.done ? 'color:rgba(255,255,255,.4);text-decoration:line-through' : 'color:#e8ddf0') + '">' + c.text + '</span>' +
                '</div>';
        }).join('');

        // Team
        var team = [{
                ico: '👤',
                name: '@pixel_hero',
                role: 'Геймдизайнер',
                status: '🟢 Онлайн',
                isMe: true
            },
            {
                ico: '🌸',
                name: '@indie_ann',
                role: 'Художник',
                status: '🟢 Онлайн',
                isMe: false
            },
            {
                ico: '🤖',
                name: '@code_wolf',
                role: 'Программист',
                status: '⚫ Не в сети',
                isMe: false
            },
            {
                ico: '➕',
                name: 'Свободное место',
                role: 'Ищем участника',
                status: '',
                isMe: false,
                isEmpty: true
            },
        ];
        document.getElementById('team-list').innerHTML = team.map(function(m) {
            if (m.isEmpty) return '<div class="team-member" style="opacity:.4"><div class="tm-av" style="background:rgba(255,255,255,.05);font-size:14px">➕</div><div><div class="tm-name">' + m.name + '</div><div class="tm-role">' + m.role + '</div></div></div>';
            return '<div class="team-member' + (m.isMe ? ' me' : '') + '">' +
                '<div class="tm-av">' + m.ico + '</div>' +
                '<div><div class="tm-name">' + m.name + (m.isMe ? ' <span class="me-badge">Я</span>' : '') + '</div><div class="tm-role">' + m.role + '</div></div>' +
                '<div class="tm-status ' + (m.status.includes('Онлайн') ? 'online' : 'away') + '">' + m.status + '</div>' +
                (!m.isMe ? '<button class="btn-sm-ghost" onclick="alert(\'Профиль ' + m.name + '\')" style="margin-left:8px">Профиль</button>' : '') +
                '</div>';
        }).join('');

        // Scoreboard
        var scoreboard = [{
                rank: '🥇',
                name: 'Shadow Maze',
                team: 'TeamGodot',
                val: '8.4'
            },
            {
                rank: '🥈',
                name: 'Pixel Jump',
                team: '@pixel_hero (Я)',
                val: '7.9',
                isMe: true
            },
            {
                rank: '🥉',
                name: 'Neon Escape',
                team: 'Art Robots',
                val: '7.2'
            },
            {
                rank: '4',
                name: 'Dungeon Bits',
                team: '@neon_dev',
                val: '6.8'
            },
            {
                rank: '5',
                name: 'Void Runner',
                team: '@gamemaker_ru',
                val: '6.5'
            },
        ];
        document.getElementById('scoreboard-list').innerHTML = '<div style="margin-bottom:12px;padding:10px 14px;background:rgba(195,33,120,.06);border:1px solid rgba(195,33,120,.15);border-radius:8px;font-size:11px;color:rgba(255,255,255,.4)">⏳ Рейтинг будет обновлён после завершения спринта и оценки жюри. Текущие данные — заглушка.</div>' +
            scoreboard.map(function(s) {
                return '<div class="score-item' + (s.isMe ? ' me' : '') + '">' +
                    '<div class="score-rank">' + s.rank + '</div>' +
                    '<div class="score-info"><div class="score-name">' + s.name + (s.isMe ? ' <span class="me-badge">Я</span>' : '') + '</div><div class="score-team">' + s.team + '</div></div>' +
                    '<div class="score-val">' + s.val + '</div>' +
                    '</div>';
            }).join('');

        // Criteria
        var criteria = [{
                name: 'Соответствие теме',
                weight: 30,
                desc: 'Насколько игра раскрывает заданную тему спринта'
            },
            {
                name: 'Геймплей',
                weight: 25,
                desc: 'Качество и оригинальность игровых механик'
            },
            {
                name: 'Дизайн и арт',
                weight: 20,
                desc: 'Визуальное оформление, стиль, согласованность арта'
            },
            {
                name: 'Звук',
                weight: 15,
                desc: 'Звуковые эффекты, музыка, атмосфера'
            },
            {
                name: 'Техническое исполнение',
                weight: 10,
                desc: 'Отсутствие критических багов, стабильная работа'
            },
        ];
        document.getElementById('criteria-list').innerHTML = criteria.map(function(c) {
            return '<div class="criteria-item">' +
                '<div class="ci-head"><span class="ci-name">' + c.name + '</span><span class="ci-weight">' + c.weight + '%</span></div>' +
                '<div class="ci-desc">' + c.desc + '</div>' +
                '<div class="ci-bar"><div class="ci-bar-fill" style="width:' + c.weight + '%"></div></div>' +
                '</div>';
        }).join('');

        // Announcements
        var anns = [{
                title: '🎉 Добро пожаловать на Pixel Chaos Sprint #3!',
                body: 'Регистрация открыта. Следите за обновлениями — тема будет объявлена строго в момент старта. Удачи!',
                date: '16.04.2026 12:00',
                isNew: true
            },
            {
                title: '📋 Правила обновлены',
                body: 'Добавлены уточнения по использованию ассетов. Разрешены бесплатные ассеты из открытых источников.',
                date: '15.04.2026 18:30',
                isNew: false
            },
        ];
        document.getElementById('ann-list').innerHTML = anns.map(function(a) {
            return '<div class="ann-item' + (a.isNew ? ' new-ann' : '') + '">' +
                '<div class="ann-title">' + a.title + (a.isNew ? '<span style="font-size:9px;background:#c32178;color:#fff;border-radius:4px;padding:1px 6px">NEW</span>' : '') + '</div>' +
                '<div class="ann-body">' + a.body + '</div>' +
                '<div class="ann-meta">' + a.date + '</div>' +
                '</div>';
        }).join('');

        // Resources
        var resources = [{
                ico: '📄',
                name: 'Правила спринта',
                sub: 'PDF · 2 страницы'
            },
            {
                ico: '🎨',
                name: 'Брендбук и шаблоны',
                sub: 'Zip · 4.2 MB'
            },
            {
                ico: '📐',
                name: 'Гайд по itch.io загрузке',
                sub: 'Статья'
            },
            {
                ico: '🎵',
                name: 'Бесплатные звуки (лицензия CC0)',
                sub: 'freesound.org'
            },
        ];
        document.getElementById('resources-list').innerHTML = resources.map(function(r) {
            return '<div class="list-item" style="border-bottom:1px solid rgba(255,255,255,.05);padding:10px 0;display:flex;gap:12px;align-items:center">' +
                '<span style="font-size:20px">' + r.ico + '</span>' +
                '<div><div style="font-size:13px;font-weight:600;color:#e8ddf0">' + r.name + '</div><div style="font-size:11px;color:rgba(255,255,255,.35)">' + r.sub + '</div></div>' +
                '<button class="btn-sm" style="margin-left:auto" onclick="alert(\'Скачивание...\')">Скачать</button>' +
                '</div>';
        }).join('');

        var tools = [{
                ico: '🎮',
                name: 'Unity (бесплатно)',
                url: 'unity.com'
            },
            {
                ico: '🤖',
                name: 'Godot Engine',
                url: 'godotengine.org'
            },
            {
                ico: '🎨',
                name: 'Aseprite — Пиксель-арт',
                url: 'aseprite.org'
            },
            {
                ico: '🎵',
                name: 'LMMS — Музыка (бесплатно)',
                url: 'lmms.io'
            },
            {
                ico: '🔊',
                name: 'sfxr — Генератор звуков',
                url: 'sfxr.me'
            },
        ];
        document.getElementById('tools-list').innerHTML = tools.map(function(t) {
            return '<div style="border-bottom:1px solid rgba(255,255,255,.05);padding:10px 0;display:flex;gap:12px;align-items:center">' +
                '<span style="font-size:20px">' + t.ico + '</span>' +
                '<div><div style="font-size:13px;font-weight:600;color:#e8ddf0">' + t.name + '</div><div style="font-size:11px;color:rgba(255,255,255,.35)">' + t.url + '</div></div>' +
                '<a href="#" class="btn-sm" style="margin-left:auto">Открыть →</a>' +
                '</div>';
        }).join('');
    </script>
</body>

</html>