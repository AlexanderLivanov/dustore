<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GameForge — Админка спринта</title>
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
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(195, 33, 120, .1) 0%, transparent 60%),
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

        .badge-live {
            background: rgba(34, 197, 94, .12);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .25);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-live::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .4
            }
        }

        /* ── LAYOUT ── */
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 54px)
        }

        .sidebar {
            width: 220px;
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
            border-color: rgba(195, 33, 120, .25);
        }

        .sidebar-item .ico {
            font-size: 16px;
            width: 20px;
            text-align: center
        }

        .sidebar-badge {
            margin-left: auto;
            background: rgba(195, 33, 120, .25);
            color: #e8ddf0;
            border-radius: 10px;
            padding: 1px 7px;
            font-size: 10px;
            font-weight: 700;
        }

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

        /* ── VIEWS ── */
        .view {
            display: none
        }

        .view.active {
            display: block
        }

        /* ── PAGE TITLE ── */
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

        /* ── STATS ROW ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px
        }

        .stat-card {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 16px;
            transition: .15s;
        }

        .stat-card:hover {
            border-color: rgba(195, 33, 120, .25)
        }

        .stat-card .sc-ico {
            font-size: 22px;
            margin-bottom: 8px
        }

        .stat-card .sc-val {
            font-size: 22px;
            font-weight: 800;
            color: #e8ddf0;
            margin-bottom: 2px
        }

        .stat-card .sc-lbl {
            font-size: 11px;
            color: rgba(255, 255, 255, .35)
        }

        .stat-card .sc-delta {
            font-size: 11px;
            margin-top: 4px
        }

        .sc-delta.up {
            color: #22c55e
        }

        .sc-delta.down {
            color: #f87171
        }

        /* ── CHART PLACEHOLDER ── */
        .chart-wrap {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .chart-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .chart-title {
            font-size: 14px;
            font-weight: 700;
            color: #e8ddf0
        }

        .chart-tabs {
            display: flex;
            gap: 4px
        }

        .chart-tab {
            padding: 5px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .4);
            transition: .15s;
        }

        .chart-tab.active {
            background: rgba(195, 33, 120, .18);
            color: #e8ddf0;
            border: 1px solid rgba(195, 33, 120, .3)
        }

        .chart-area {
            height: 160px;
            position: relative;
            overflow: hidden
        }

        .chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 100%;
            padding-top: 10px
        }

        .chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px
        }

        .chart-bar {
            width: 100%;
            border-radius: 4px 4px 0 0;
            background: linear-gradient(180deg, rgba(195, 33, 120, .7), rgba(195, 33, 120, .25));
            transition: height .6s cubic-bezier(.4, 0, .2, 1);
            min-height: 4px;
            position: relative;
        }

        .chart-bar:hover {
            background: linear-gradient(180deg, rgba(195, 33, 120, 1), rgba(195, 33, 120, .4))
        }

        .chart-bar::after {
            content: attr(data-val);
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            color: rgba(255, 255, 255, .4);
            white-space: nowrap;
        }

        .chart-lbl {
            font-size: 9px;
            color: rgba(255, 255, 255, .25);
            text-align: center
        }

        .chart-grid {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-bottom: 20px;
        }

        .chart-gridline {
            border-top: 1px solid rgba(255, 255, 255, .05);
            width: 100%
        }

        /* ── TWO COL ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px
        }

        .three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px
        }

        /* ── LIST CARD ── */
        .list-card {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 16px
        }

        .list-card-title {
            font-size: 13px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 12px
        }

        .list-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .list-item:last-child {
            border-bottom: none;
            padding-bottom: 0
        }

        .li-rank {
            font-size: 14px;
            width: 20px;
            text-align: center;
            flex-shrink: 0
        }

        .li-info {
            flex: 1;
            min-width: 0
        }

        .li-name {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .li-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, .3)
        }

        .li-val {
            font-size: 13px;
            font-weight: 700;
            color: #c32178;
            flex-shrink: 0
        }

        /* ── PARTICIPANTS TABLE ── */
        .table-wrap {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            overflow: hidden
        }

        .table-toolbar {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            display: flex;
            gap: 10px;
            align-items: center
        }

        .tbl-search {
            flex: 1;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 7px;
            padding: 7px 12px;
            color: #e8ddf0;
            font-size: 12px;
            font-family: inherit;
            outline: none;
        }

        .tbl-search:focus {
            border-color: #c32178
        }

        .tbl-search::placeholder {
            color: rgba(255, 255, 255, .3)
        }

        .tbl-btn {
            padding: 7px 14px;
            border-radius: 7px;
            border: 1px solid rgba(195, 33, 120, .3);
            background: rgba(195, 33, 120, .1);
            color: #e8ddf0;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            transition: .15s;
        }

        .tbl-btn:hover {
            background: rgba(195, 33, 120, .2)
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            color: rgba(255, 255, 255, .3);
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            transition: .15s;
            cursor: pointer
        }

        tbody tr:last-child {
            border-bottom: none
        }

        tbody tr:hover {
            background: rgba(195, 33, 120, .05)
        }

        tbody td {
            padding: 11px 14px;
            font-size: 12px;
            color: rgba(255, 255, 255, .7)
        }

        td .td-name {
            color: #e8ddf0;
            font-weight: 600;
            font-size: 13px
        }

        td .td-sub {
            color: rgba(255, 255, 255, .3);
            font-size: 11px
        }

        .status-pill {
            border-radius: 20px;
            padding: 2px 9px;
            font-size: 10px;
            font-weight: 700
        }

        .pill-green {
            background: rgba(34, 197, 94, .1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .2)
        }

        .pill-yellow {
            background: rgba(245, 158, 11, .1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .2)
        }

        .pill-gray {
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .3);
            border: 1px solid rgba(255, 255, 255, .1)
        }

        .pill-pink {
            background: rgba(195, 33, 120, .1);
            color: #d946a8;
            border: 1px solid rgba(195, 33, 120, .25)
        }

        /* ── SETTINGS ── */
        .settings-section {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 14px;
        }

        .settings-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row-s {
            margin-bottom: 14px
        }

        .form-label-s {
            display: block;
            color: rgba(255, 255, 255, .4);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px
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

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05)
        }

        .toggle-row:last-child {
            border-bottom: none;
            padding-bottom: 0
        }

        .toggle-info .ti-title {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0
        }

        .toggle-info .ti-desc {
            font-size: 11px;
            color: rgba(255, 255, 255, .3);
            margin-top: 2px
        }

        .toggle {
            position: relative;
            width: 40px;
            height: 22px;
            flex-shrink: 0
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, .1);
            border-radius: 22px;
            cursor: pointer;
            transition: .2s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            left: 3px;
            top: 3px;
            transition: .2s;
        }

        .toggle input:checked+.toggle-slider {
            background: #c32178
        }

        .toggle input:checked+.toggle-slider::before {
            transform: translateX(18px)
        }

        .btn-save {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: .15s;
        }

        .btn-save:hover {
            background: #9e1a66
        }

        .btn-danger {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .25);
            color: #f87171;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: .15s;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, .2)
        }

        /* ── ACTIVITY FEED ── */
        .feed-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .feed-item:last-child {
            border-bottom: none
        }

        .feed-ico {
            font-size: 18px;
            flex-shrink: 0;
            width: 24px;
            text-align: center;
            margin-top: 1px
        }

        .feed-text {
            font-size: 12px;
            color: rgba(255, 255, 255, .6);
            line-height: 1.5
        }

        .feed-text strong {
            color: #e8ddf0
        }

        .feed-time {
            font-size: 10px;
            color: rgba(255, 255, 255, .25);
            margin-top: 2px
        }

        /* ── DONUT STUB ── */
        .donut-stub {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 10px auto;
            background: conic-gradient(#c32178 0% 45%,
                    rgba(195, 33, 120, .3) 45% 70%,
                    rgba(255, 255, 255, .08) 70% 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .donut-stub::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d0414;
        }

        .donut-center {
            position: relative;
            z-index: 1;
            text-align: center
        }

        .donut-center .dv {
            font-size: 16px;
            font-weight: 800;
            color: #e8ddf0
        }

        .donut-center .dl {
            font-size: 9px;
            color: rgba(255, 255, 255, .3)
        }

        .donut-legend {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 14px
        }

        .dl-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            color: rgba(255, 255, 255, .5)
        }

        .dl-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0
        }

        /* ── ANNOUNCEMENTS ── */
        .ann-item {
            background: rgba(255, 255, 255, .03);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 12px 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: .15s;
        }

        .ann-item:hover {
            border-color: rgba(195, 33, 120, .3);
            background: rgba(195, 33, 120, .04)
        }

        .ann-title {
            font-size: 13px;
            font-weight: 600;
            color: #e8ddf0;
            margin-bottom: 3px
        }

        .ann-meta {
            font-size: 11px;
            color: rgba(255, 255, 255, .3)
        }

        .ann-textarea {
            width: 100%;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 8px;
            padding: 10px 13px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            resize: vertical;
            min-height: 90px;
            transition: .15s;
        }

        .ann-textarea:focus {
            border-color: #c32178
        }

        .ann-textarea::placeholder {
            color: rgba(255, 255, 255, .25)
        }
    </style>
</head>

<body>

    <header class="header">
        <div style="display:flex;align-items:center;gap:16px">
            <div class="logo">🎮 <span class="brand">GameForge</span><span class="sep">/</span>Админка</div>
            <span class="badge-live">Pixel Chaos Sprint #3</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <a class="nav-btn" href="sprints">← К спринтам</a>
            <a class="nav-btn" href="participant">Панель участника</a>
        </div>
    </header>

    <div class="admin-layout">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sidebar-section">Обзор</div>
            <div class="sidebar-item active" onclick="showView('dashboard',this)">
                <span class="ico">📊</span> Дашборд
            </div>
            <div class="sidebar-item" onclick="showView('analytics',this)">
                <span class="ico">📈</span> Аналитика
            </div>
            <div class="sidebar-item" onclick="showView('activity',this)">
                <span class="ico">⚡</span> Активность
            </div>

            <div class="sidebar-section">Управление</div>
            <div class="sidebar-item" onclick="showView('participants',this)">
                <span class="ico">👥</span> Участники
                <span class="sidebar-badge">134</span>
            </div>
            <div class="sidebar-item" onclick="showView('submissions',this)">
                <span class="ico">🎮</span> Работы
                <span class="sidebar-badge">12</span>
            </div>
            <div class="sidebar-item" onclick="showView('announcements',this)">
                <span class="ico">📢</span> Объявления
            </div>

            <div class="sidebar-section">Настройки</div>
            <div class="sidebar-item" onclick="showView('settings',this)">
                <span class="ico">⚙️</span> Параметры
            </div>
            <div class="sidebar-item" onclick="showView('judges',this)">
                <span class="ico">⭐</span> Жюри
            </div>
            <div class="sidebar-item" onclick="showView('prizes',this)">
                <span class="ico">🏆</span> Призы
            </div>
        </div>

        <!-- MAIN -->
        <div class="main-content">

            <!-- DASHBOARD -->
            <div class="view active" id="view-dashboard">
                <div class="page-title">Дашборд</div>
                <div class="page-sub">Pixel Chaos Sprint #3 · Статус: Идёт регистрация · Старт через 2д 4ч</div>
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="sc-ico">👥</div>
                        <div class="sc-val">134</div>
                        <div class="sc-lbl">Участников</div>
                        <div class="sc-delta up">↑ +12 сегодня</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🎮</div>
                        <div class="sc-val">12</div>
                        <div class="sc-lbl">Работ сдано</div>
                        <div class="sc-delta up">↑ +3 за час</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">👁</div>
                        <div class="sc-val">2.4K</div>
                        <div class="sc-lbl">Просмотров</div>
                        <div class="sc-delta up">↑ +18%</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">💬</div>
                        <div class="sc-val">89</div>
                        <div class="sc-lbl">Комментариев</div>
                        <div class="sc-delta down">↓ -5 вчера</div>
                    </div>
                </div>
                <div class="chart-wrap">
                    <div class="chart-head">
                        <span class="chart-title">Регистрации участников</span>
                        <div class="chart-tabs">
                            <button class="chart-tab active">7 дней</button>
                            <button class="chart-tab">30 дней</button>
                        </div>
                    </div>
                    <div class="chart-area">
                        <div class="chart-grid">
                            <div class="chart-gridline"></div>
                            <div class="chart-gridline"></div>
                            <div class="chart-gridline"></div>
                            <div class="chart-gridline"></div>
                        </div>
                        <div class="chart-bars" id="regChart"></div>
                    </div>
                </div>
                <div class="two-col">
                    <div class="list-card">
                        <div class="list-card-title">🏅 Топ участников по активности</div>
                        <div id="top-members"></div>
                    </div>
                    <div class="list-card">
                        <div class="list-card-title">🔖 Топ тегов</div>
                        <div id="top-tags"></div>
                    </div>
                </div>
                <div class="two-col">
                    <div class="chart-wrap" style="margin-bottom:0">
                        <div class="chart-title" style="margin-bottom:14px">Распределение по движкам</div>
                        <div class="donut-stub">
                            <div class="donut-center">
                                <div class="dv">134</div>
                                <div class="dl">всего</div>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div class="dl-item">
                                <div class="dl-dot" style="background:#c32178"></div>Unity — 45%
                            </div>
                            <div class="dl-item">
                                <div class="dl-dot" style="background:rgba(195,33,120,.45)"></div>Godot — 25%
                            </div>
                            <div class="dl-item">
                                <div class="dl-dot" style="background:rgba(255,255,255,.15)"></div>Другие — 30%
                            </div>
                        </div>
                    </div>
                    <div class="list-card" style="margin-bottom:0">
                        <div class="list-card-title">⏰ Таймлайн спринта</div>
                        <div id="timeline-list"></div>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS -->
            <div class="view" id="view-analytics">
                <div class="page-title">Аналитика</div>
                <div class="page-sub">Детальная статистика спринта</div>
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="sc-ico">📉</div>
                        <div class="sc-val">67%</div>
                        <div class="sc-lbl">Конверсия просмотр→регистр.</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">⌛</div>
                        <div class="sc-val">4.2ч</div>
                        <div class="sc-lbl">Ср. время на странице</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🔁</div>
                        <div class="sc-val">38%</div>
                        <div class="sc-lbl">Повторных участников</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🌍</div>
                        <div class="sc-val">14</div>
                        <div class="sc-lbl">Стран участников</div>
                    </div>
                </div>
                <div class="chart-wrap">
                    <div class="chart-head"><span class="chart-title">Активность по часам суток</span></div>
                    <div class="chart-area">
                        <div class="chart-bars" id="hourlyChart"></div>
                    </div>
                </div>
                <div class="three-col">
                    <div class="list-card">
                        <div class="list-card-title">🌍 Источники трафика</div>
                        <div class="list-item">
                            <div class="li-rank">🔗</div>
                            <div class="li-info">
                                <div class="li-name">Прямые</div>
                            </div>
                            <div class="li-val">42%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-rank">📱</div>
                            <div class="li-info">
                                <div class="li-name">Telegram</div>
                            </div>
                            <div class="li-val">31%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-rank">🐦</div>
                            <div class="li-info">
                                <div class="li-name">Twitter/X</div>
                            </div>
                            <div class="li-val">17%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-rank">🔍</div>
                            <div class="li-info">
                                <div class="li-name">Поиск</div>
                            </div>
                            <div class="li-val">10%</div>
                        </div>
                    </div>
                    <div class="list-card">
                        <div class="list-card-title">💻 Устройства</div>
                        <div class="list-item">
                            <div class="li-rank">🖥</div>
                            <div class="li-info">
                                <div class="li-name">Desktop</div>
                            </div>
                            <div class="li-val">61%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-rank">📱</div>
                            <div class="li-info">
                                <div class="li-name">Mobile</div>
                            </div>
                            <div class="li-val">33%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-rank">📟</div>
                            <div class="li-info">
                                <div class="li-name">Tablet</div>
                            </div>
                            <div class="li-val">6%</div>
                        </div>
                    </div>
                    <div class="list-card">
                        <div class="list-card-title">⚙️ Опыт участников</div>
                        <div class="list-item">
                            <div class="li-info">
                                <div class="li-name">Новички (&lt;1г.)</div>
                            </div>
                            <div class="li-val">28%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-info">
                                <div class="li-name">1–3 года</div>
                            </div>
                            <div class="li-val">38%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-info">
                                <div class="li-name">3–5 лет</div>
                            </div>
                            <div class="li-val">22%</div>
                        </div>
                        <div class="list-item">
                            <div class="li-info">
                                <div class="li-name">5+ лет</div>
                            </div>
                            <div class="li-val">12%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIVITY -->
            <div class="view" id="view-activity">
                <div class="page-title">Активность</div>
                <div class="page-sub">Лента событий в реальном времени</div>
                <div class="list-card">
                    <div class="list-card-title">🔴 Последние события</div>
                    <div id="feed-list"></div>
                </div>
            </div>

            <!-- PARTICIPANTS -->
            <div class="view" id="view-participants">
                <div class="page-title">Участники <span style="font-size:14px;color:rgba(255,255,255,.35);font-weight:400">134</span></div>
                <div class="page-sub">Управление участниками спринта</div>
                <div class="table-wrap">
                    <div class="table-toolbar">
                        <input class="tbl-search" placeholder="Поиск по имени, тегу, движку..." oninput="filterTable(this.value)">
                        <button class="tbl-btn">⬇ Экспорт CSV</button>
                        <button class="tbl-btn" onclick="alert('Уведомление отправлено всем участникам!')">📢 Рассылка</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Участник</th>
                                <th>Движок</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Регистрация</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="participants-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- SUBMISSIONS -->
            <div class="view" id="view-submissions">
                <div class="page-title">Работы <span style="font-size:14px;color:rgba(255,255,255,.35);font-weight:400">12</span></div>
                <div class="page-sub">Сданные игры участников</div>
                <div class="stats-row" style="grid-template-columns:repeat(3,1fr)">
                    <div class="stat-card">
                        <div class="sc-ico">✅</div>
                        <div class="sc-val">12</div>
                        <div class="sc-lbl">Работ сдано</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">⏳</div>
                        <div class="sc-val">8</div>
                        <div class="sc-lbl">На проверке</div>
                    </div>
                    <div class="stat-card">
                        <div class="sc-ico">🏆</div>
                        <div class="sc-val">3</div>
                        <div class="sc-lbl">Финалистов</div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Название игры</th>
                                <th>Команда</th>
                                <th>Движок</th>
                                <th>Оценка</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody id="submissions-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ANNOUNCEMENTS -->
            <div class="view" id="view-announcements">
                <div class="page-title">Объявления</div>
                <div class="page-sub">Рассылки и уведомления участникам</div>
                <div class="settings-section">
                    <div class="settings-section-title">📝 Новое объявление</div>
                    <div class="form-row-s">
                        <label class="form-label-s">Заголовок</label>
                        <input class="form-input-s" id="ann-title" placeholder="Например: Важное обновление правил...">
                    </div>
                    <div class="form-row-s">
                        <label class="form-label-s">Текст</label>
                        <textarea class="ann-textarea" id="ann-body" placeholder="Текст объявления..."></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:4px">
                        <button class="btn-save" onclick="sendAnn()">📢 Отправить всем</button>
                        <button class="tbl-btn" onclick="sendAnn()">Telegram-канал</button>
                        <button class="tbl-btn" onclick="sendAnn()">Email-рассылка</button>
                    </div>
                </div>
                <div class="list-card">
                    <div class="list-card-title">📋 История объявлений</div>
                    <div id="ann-list"></div>
                </div>
            </div>

            <!-- SETTINGS -->
            <div class="view" id="view-settings">
                <div class="page-title">Параметры спринта</div>
                <div class="page-sub">Основные настройки и конфигурация</div>
                <div class="settings-section">
                    <div class="settings-section-title">🎮 Основная информация</div>
                    <div class="form-row-s"><label class="form-label-s">Название спринта</label><input class="form-input-s" value="Pixel Chaos Sprint #3"></div>
                    <div class="form-row-s"><label class="form-label-s">Тема (скрыта до старта)</label><input class="form-input-s" value="Изоляция" placeholder="Тема..."></div>
                    <div class="form-row-s" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div><label class="form-label-s">Дата начала</label><input class="form-input-s" type="date"></div>
                        <div><label class="form-label-s">Длительность (ч)</label><input class="form-input-s" type="number" value="72"></div>
                    </div>
                    <div class="form-row-s"><label class="form-label-s">Макс. участников</label><input class="form-input-s" type="number" value="200" style="width:160px"></div>
                </div>
                <div class="settings-section">
                    <div class="settings-section-title">🔧 Функции</div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="ti-title">Командный режим</div>
                            <div class="ti-desc">Разрешить создание команд</div>
                        </div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="ti-title">Тема скрыта до старта</div>
                            <div class="ti-desc">Участники узнают тему только в момент старта</div>
                        </div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="ti-title">Публичные работы</div>
                            <div class="ti-desc">Все могут просматривать сданные игры</div>
                        </div>
                        <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="ti-title">Голосование сообщества</div>
                            <div class="ti-desc">Зрители могут голосовать за работы</div>
                        </div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="ti-title">Биржа команд (L4T)</div>
                            <div class="ti-desc">Показывать кнопку поиска команды через L4T</div>
                        </div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                </div>
                <div style="display:flex;gap:10px">
                    <button class="btn-save" onclick="alert('Настройки сохранены!')">Сохранить изменения</button>
                    <button class="btn-danger" onclick="confirm('Удалить спринт? Это действие необратимо.')">Удалить спринт</button>
                </div>
            </div>

            <!-- JUDGES -->
            <div class="view" id="view-judges">
                <div class="page-title">Жюри</div>
                <div class="page-sub">Управление составом жюри и экспертов</div>
                <div class="settings-section">
                    <div class="settings-section-title">➕ Добавить судью</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end">
                        <div><label class="form-label-s">Имя</label><input class="form-input-s" placeholder="Имя эксперта"></div>
                        <div><label class="form-label-s">Роль</label><input class="form-input-s" placeholder="Геймдизайнер, студия..."></div>
                        <button class="btn-save" onclick="alert('Судья добавлен!')">+ Добавить</button>
                    </div>
                </div>
                <div class="list-card">
                    <div class="list-card-title">⭐ Текущее жюри</div>
                    <div id="judges-list"></div>
                </div>
            </div>

            <!-- PRIZES -->
            <div class="view" id="view-prizes">
                <div class="page-title">Призы</div>
                <div class="page-sub">Управление призовым фондом</div>
                <div class="settings-section">
                    <div class="settings-section-title">🏆 Призовые места</div>
                    <div id="prizes-settings"></div>
                    <button class="tbl-btn" style="margin-top:10px" onclick="alert('Место добавлено!')">+ Добавить место</button>
                </div>
                <div class="settings-section">
                    <div class="settings-section-title">🎁 Специальные номинации</div>
                    <div class="ann-item">
                        <div class="ann-title">Лучший арт-дирекшн</div>
                        <div class="ann-meta">Награда: Мерч-набор + упоминание в соцсетях</div>
                    </div>
                    <div class="ann-item">
                        <div class="ann-title">Лучшее соло-прохождение</div>
                        <div class="ann-meta">Награда: 5 000 ₽</div>
                    </div>
                    <button class="tbl-btn" style="margin-top:6px" onclick="alert('Номинация добавлена!')">+ Добавить номинацию</button>
                </div>
            </div>

        </div>
    </div>

    <script>
        // ── Nav ──
        function showView(name, el) {
            document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
            document.getElementById('view-' + name).classList.add('active');
            document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
            if (el) el.classList.add('active');
        }

        // ── Chart data ──
        var regData = [5, 12, 18, 9, 24, 31, 27];
        var regLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        var hourlyData = [2, 1, 1, 0, 1, 3, 5, 8, 12, 14, 10, 9, 11, 13, 8, 7, 10, 12, 15, 14, 10, 8, 5, 3];

        function buildBar(container, data, labels) {
            var max = Math.max(...data) || 1;
            container.innerHTML = '';
            data.forEach(function(v, i) {
                var pct = Math.round(v / max * 100);
                var w = document.createElement('div');
                w.className = 'chart-bar-wrap';
                var b = document.createElement('div');
                b.className = 'chart-bar';
                b.style.height = '0%';
                b.dataset.val = v;
                setTimeout(function() {
                    b.style.height = pct + '%'
                }, 50 + i * 30);
                w.appendChild(b);
                if (labels && labels[i]) {
                    var l = document.createElement('div');
                    l.className = 'chart-lbl';
                    l.textContent = labels[i];
                    w.appendChild(l);
                }
                container.appendChild(w);
            });
        }

        // ── Top members ──
        var topMembers = [{
                name: '@pixel_hero',
                sub: 'Unity · 5 работ',
                val: '⭐ 480'
            },
            {
                name: '@gamemaker_ru',
                sub: 'Godot · 3 работы',
                val: '⭐ 390'
            },
            {
                name: '@indie_ann',
                sub: 'Unity · 2 работы',
                val: '⭐ 280'
            },
            {
                name: '@code_wolf',
                sub: 'PICO-8 · 4 работы',
                val: '⭐ 245'
            },
        ];
        var topTags = [{
                name: 'Пиксель-арт',
                val: '67'
            },
            {
                name: '2D Платформер',
                val: '43'
            },
            {
                name: 'Хоррор',
                val: '28'
            },
            {
                name: 'Roguelike',
                val: '19'
            },
            {
                name: 'Puzzle',
                val: '15'
            },
        ];
        var timeline = [{
                name: 'Регистрация открыта',
                sub: '2 дня назад',
                ico: '🟢'
            },
            {
                name: 'Старт спринта',
                sub: 'Через 2д 4ч',
                ico: '🚀'
            },
            {
                name: 'Конец разработки',
                sub: 'Через 5д 4ч',
                ico: '⌛'
            },
            {
                name: 'Голосование',
                sub: 'Через 5д 6ч',
                ico: '🗳'
            },
            {
                name: 'Итоги',
                sub: 'Через 7д',
                ico: '🏆'
            },
        ];
        var feedItems = [{
                ico: '👤',
                text: '<strong>@pixel_hero</strong> зарегистрировался на спринт',
                time: '2 минуты назад'
            },
            {
                ico: '🎮',
                text: '<strong>TeamGodot</strong> сдала работу «Shadow Maze»',
                time: '15 минут назад'
            },
            {
                ico: '💬',
                text: '<strong>@code_wolf</strong> оставил комментарий к работе «Pixel Jump»',
                time: '1 час назад'
            },
            {
                ico: '👥',
                text: '<strong>@indie_ann</strong> создала команду «Art Robots»',
                time: '2 часа назад'
            },
            {
                ico: '🔗',
                text: 'Упоминание спринта в Telegram-канале GameDev RU',
                time: '3 часа назад'
            },
            {
                ico: '👤',
                text: '<strong>@gamemaker_ru</strong> зарегистрировался на спринт',
                time: '5 часов назад'
            },
        ];
        var participants = [{
                n: '@pixel_hero',
                sub: 'Москва',
                engine: 'Unity',
                role: 'Геймдизайнер',
                status: 'pill-green',
                statusT: 'Активен',
                date: '14.04.2026'
            },
            {
                n: '@indie_ann',
                sub: 'Питер',
                engine: 'Godot',
                role: 'Программист',
                status: 'pill-yellow',
                statusT: 'Ищет команду',
                date: '14.04.2026'
            },
            {
                n: '@code_wolf',
                sub: 'Онлайн',
                engine: 'PICO-8',
                role: 'Соло',
                status: 'pill-green',
                statusT: 'Активен',
                date: '15.04.2026'
            },
            {
                n: '@gamemaker_ru',
                sub: 'Екб',
                engine: 'Unity',
                role: 'Художник',
                status: 'pill-pink',
                statusT: 'В команде',
                date: '15.04.2026'
            },
            {
                n: '@neon_dev',
                sub: 'Минск',
                engine: 'Godot',
                role: 'Программист',
                status: 'pill-gray',
                statusT: 'Неактивен',
                date: '13.04.2026'
            },
        ];
        var submissions = [{
                name: 'Shadow Maze',
                team: 'TeamGodot',
                engine: 'Godot',
                score: '8.4',
                status: 'pill-green',
                statusT: 'Финалист'
            },
            {
                name: 'Pixel Jump',
                team: '@pixel_hero',
                engine: 'Unity',
                score: '7.9',
                status: 'pill-yellow',
                statusT: 'На проверке'
            },
            {
                name: 'Neon Escape',
                team: 'Art Robots',
                engine: 'Unity',
                score: '—',
                status: 'pill-gray',
                statusT: 'Ожидает'
            },
        ];
        var anns = [{
                title: 'Добро пожаловать на Pixel Chaos Sprint #3!',
                meta: '16.04.2026 · 134 получателя'
            },
            {
                title: 'Напоминание: до старта 2 дня',
                meta: '15.04.2026 · 134 получателя'
            },
        ];
        var judges = [{
                name: 'Алексей Ворон',
                role: 'Геймдизайнер, ex-Wargaming',
                ico: '🦅'
            },
            {
                name: 'Мария Седова',
                role: 'Indie Dev, 3 игры в Steam',
                ico: '🌸'
            },
        ];
        var prizesData = [{
                place: '1',
                ico: '🥇',
                reward: '50 000 ₽ + Steam-ключи'
            },
            {
                place: '2',
                ico: '🥈',
                reward: '20 000 ₽ + Менторство'
            },
            {
                place: '3',
                ico: '🥉',
                reward: '10 000 ₽ + Мерч'
            },
        ];

        function renderDashboard() {
            buildBar(document.getElementById('regChart'), regData, regLabels);
            document.getElementById('top-members').innerHTML = topMembers.map(function(m, i) {
                return '<div class="list-item"><div class="li-rank">' + (i + 1) + '</div><div class="li-info"><div class="li-name">' + m.name + '</div><div class="li-sub">' + m.sub + '</div></div><div class="li-val">' + m.val + '</div></div>';
            }).join('');
            document.getElementById('top-tags').innerHTML = topTags.map(function(t) {
                return '<div class="list-item"><div class="li-info"><div class="li-name">' + t.name + '</div></div><div class="li-val">' + t.val + '</div></div>';
            }).join('');
            document.getElementById('timeline-list').innerHTML = timeline.map(function(t) {
                return '<div class="list-item"><div class="li-rank">' + t.ico + '</div><div class="li-info"><div class="li-name">' + t.name + '</div><div class="li-sub">' + t.sub + '</div></div></div>';
            }).join('');
        }

        function renderAnalytics() {
            buildBar(document.getElementById('hourlyChart'), hourlyData, hourlyData.map(function(_, i) {
                return i % 6 === 0 ? i + 'ч' : ''
            }));
        }

        function renderActivity() {
            document.getElementById('feed-list').innerHTML = feedItems.map(function(f) {
                return '<div class="feed-item"><div class="feed-ico">' + f.ico + '</div><div><div class="feed-text">' + f.text + '</div><div class="feed-time">' + f.time + '</div></div></div>';
            }).join('');
        }

        function renderParticipants(filter) {
            var data = participants.filter(function(p) {
                return !filter || (p.n.includes(filter) || p.engine.toLowerCase().includes(filter) || p.role.toLowerCase().includes(filter))
            });
            document.getElementById('participants-tbody').innerHTML = data.map(function(p, i) {
                return '<tr><td>' + (i + 1) + '</td><td><div class="td-name">' + p.n + '</div><div class="td-sub">' + p.sub + '</div></td><td>' + p.engine + '</td><td>' + p.role + '</td><td><span class="status-pill ' + p.status + '">' + p.statusT + '</span></td><td style="color:rgba(255,255,255,.35);font-size:11px">' + p.date + '</td><td><button class="tbl-btn" onclick="alert(\'Профиль ' + p.n + '\')">Профиль</button></td></tr>';
            }).join('');
        }

        function filterTable(v) {
            renderParticipants(v.toLowerCase())
        }

        function renderSubmissions() {
            document.getElementById('submissions-tbody').innerHTML = submissions.map(function(s, i) {
                return '<tr><td>' + (i + 1) + '</td><td><div class="td-name">' + s.name + '</div></td><td>' + s.team + '</td><td>' + s.engine + '</td><td style="font-weight:700;color:#c32178">' + s.score + '</td><td><span class="status-pill ' + s.status + '">' + s.statusT + '</span></td></tr>';
            }).join('');
        }

        function renderAnnList() {
            document.getElementById('ann-list').innerHTML = anns.map(function(a) {
                return '<div class="ann-item"><div class="ann-title">' + a.title + '</div><div class="ann-meta">' + a.meta + '</div></div>';
            }).join('');
        }

        function sendAnn() {
            var t = document.getElementById('ann-title').value.trim();
            var b = document.getElementById('ann-body').value.trim();
            if (!t) {
                alert('Введите заголовок');
                return
            }
            anns.unshift({
                title: t,
                meta: new Date().toLocaleDateString('ru') + ' · 134 получателя'
            });
            renderAnnList();
            document.getElementById('ann-title').value = '';
            document.getElementById('ann-body').value = '';
            alert('Объявление отправлено!');
        }

        function renderJudges() {
            document.getElementById('judges-list').innerHTML = judges.map(function(j) {
                return '<div class="list-item"><div class="li-rank">' + j.ico + '</div><div class="li-info"><div class="li-name">' + j.name + '</div><div class="li-sub">' + j.role + '</div></div><button class="tbl-btn" onclick="alert(\'Убрать ' + j.name + '?\')">Убрать</button></div>';
            }).join('');
        }

        function renderPrizes() {
            document.getElementById('prizes-settings').innerHTML = prizesData.map(function(p) {
                return '<div style="display:grid;grid-template-columns:40px 1fr auto;gap:10px;align-items:center;margin-bottom:8px">' +
                    '<span style="font-size:20px;text-align:center">' + p.ico + '</span>' +
                    '<input class="form-input-s" value="' + p.reward + '">' +
                    '<button class="btn-save" onclick="alert(\'Сохранено!\')">✓</button>' +
                    '</div>';
            }).join('');
        }

        // Init all
        renderDashboard();
        renderAnalytics();
        renderActivity();
        renderParticipants();
        renderSubmissions();
        renderAnnList();
        renderJudges();
        renderPrizes();
    </script>
</body>

</html>