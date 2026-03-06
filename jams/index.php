<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GameForge — Sprints</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f0c29, #1a1040, #0d0d2b);
            font-family: 'Inter', system-ui, sans-serif;
            color: #f1f5f9
        }

        ::-webkit-scrollbar {
            width: 6px
        }

        ::-webkit-scrollbar-track {
            background: #0f0c29
        }

        ::-webkit-scrollbar-thumb {
            background: #6366f1;
            border-radius: 3px
        }

        .header {
            background: rgba(15, 12, 41, .97);
            border-bottom: 1px solid rgba(99, 102, 241, .2);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 19px;
            font-weight: 800;
            color: #f1f5f9
        }

        .logo span {
            color: #6366f1
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: .2s
        }

        .btn-primary:hover {
            opacity: .88;
            transform: translateY(-1px)
        }

        .container {
            max-width: 920px;
            margin: 0 auto;
            padding: 28px 16px
        }

        .hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, .15), rgba(139, 92, 246, .1));
            border: 1px solid rgba(99, 102, 241, .25);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden
        }

        .hero::after {
            content: '🎮';
            position: absolute;
            right: -10px;
            top: -10px;
            font-size: 120px;
            opacity: .07;
            pointer-events: none
        }

        .hero h1 {
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(135deg, #a5b4fc, #e879f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px
        }

        .hero p {
            color: #94a3b8;
            font-size: 15px;
            margin-bottom: 20px
        }

        .hero-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap
        }

        .hero-stat .val {
            font-size: 22px;
            font-weight: 800;
            color: #a5b4fc
        }

        .hero-stat .lbl {
            color: #6b7280;
            font-size: 12px;
            margin-top: 2px
        }

        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center
        }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 180px
        }

        .search-wrap .ico {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none
        }

        .search-input {
            width: 100%;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(99, 102, 241, .2);
            border-radius: 10px;
            padding: 10px 14px 10px 36px;
            color: #f1f5f9;
            font-size: 14px;
            outline: none;
            transition: .2s
        }

        .search-input:focus {
            border-color: #6366f1
        }

        .filters {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .filter-btn {
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            background: rgba(255, 255, 255, .06);
            color: #94a3b8;
            transition: .2s
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff
        }

        .filter-btn:hover:not(.active) {
            background: rgba(255, 255, 255, .1)
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280
        }

        .empty .ico {
            font-size: 48px;
            margin-bottom: 12px
        }

        .card {
            background: linear-gradient(145deg, rgba(30, 27, 75, .95), rgba(17, 24, 39, .98));
            border: 1px solid rgba(99, 102, 241, .2);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: .18s
        }

        .card:hover {
            transform: translateY(-3px);
            border-color: rgba(99, 102, 241, .5);
            box-shadow: 0 8px 32px rgba(99, 102, 241, .15)
        }

        .card-top {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 12px
        }

        .card-banner {
            font-size: 34px;
            line-height: 1;
            flex-shrink: 0
        }

        .card-meta {
            flex: 1;
            min-width: 0
        }

        .card-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px
        }

        .card-title {
            color: #f1f5f9;
            font-size: 16px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .card-host {
            color: #6b7280;
            font-size: 12px
        }

        .card-desc {
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden
        }

        .tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 12px
        }

        .tag {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc;
            border-radius: 6px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 600
        }

        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px
        }

        .stat-box {
            background: rgba(255, 255, 255, .04);
            border-radius: 10px;
            padding: 9px 12px
        }

        .stat-box .s-lbl {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 2px
        }

        .stat-box .s-val {
            color: #e2e8f0;
            font-weight: 700;
            font-size: 14px
        }

        .prog-wrap {
            margin-top: 4px
        }

        .prog-lbl {
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 4px
        }

        .prog-lbl span {
            color: #a5b4fc;
            font-weight: 600
        }

        .prog-bar {
            background: rgba(255, 255, 255, .06);
            border-radius: 99px;
            height: 6px;
            overflow: hidden
        }

        .prog-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #a78bfa);
            border-radius: 99px;
            transition: width .4s
        }

        .badge {
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 12px;
            font-weight: 700
        }

        .badge-active {
            background: rgba(34, 197, 94, .12);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .3)
        }

        .badge-upcoming {
            background: rgba(245, 158, 11, .12);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .3)
        }

        .badge-ongoing {
            background: rgba(99, 102, 241, .12);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, .3)
        }

        .badge-finished {
            background: rgba(107, 114, 128, .12);
            color: #9ca3af;
            border: 1px solid rgba(107, 114, 128, .3)
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .78);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: .2s
        }

        .overlay.open {
            opacity: 1;
            pointer-events: all
        }

        .modal {
            background: linear-gradient(145deg, #1e1b4b, #111827);
            border: 1px solid rgba(99, 102, 241, .35);
            border-radius: 20px;
            max-width: 580px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 26px;
            transform: translateY(20px);
            transition: .2s
        }

        .overlay.open .modal {
            transform: translateY(0)
        }

        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px
        }

        .modal-title-row {
            display: flex;
            gap: 14px;
            align-items: center
        }

        .modal-banner {
            font-size: 46px;
            line-height: 1
        }

        .modal-h2 {
            color: #f1f5f9;
            font-size: 21px;
            font-weight: 800;
            margin: 6px 0 2px
        }

        .modal-host {
            color: #6b7280;
            font-size: 13px
        }

        .btn-close {
            background: rgba(255, 255, 255, .07);
            border: none;
            color: #94a3b8;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 18px
        }

        .modal-desc {
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 18px;
            font-size: 14px
        }

        .theme-box {
            background: rgba(99, 102, 241, .1);
            border: 1px solid rgba(99, 102, 241, .3);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 14px
        }

        .theme-box strong {
            color: #a5b4fc
        }

        .modal-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px
        }

        .m-stat {
            background: rgba(255, 255, 255, .05);
            border-radius: 12px;
            padding: 12px;
            text-align: center
        }

        .m-stat .ico {
            font-size: 18px;
            margin-bottom: 4px
        }

        .m-stat .val {
            color: #e2e8f0;
            font-weight: 700;
            font-size: 15px
        }

        .m-stat .lbl {
            color: #6b7280;
            font-size: 11px;
            margin-top: 2px
        }

        .section-title {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 15px;
            margin: 0 0 10px;
            display: block
        }

        .prize-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, .04);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 8px
        }

        .prize-item .pi-place {
            color: #6b7280;
            font-size: 11px
        }

        .prize-item .pi-reward {
            color: #e2e8f0;
            font-weight: 600;
            font-size: 14px
        }

        .expert-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, .04);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 8px
        }

        .expert-item .av {
            font-size: 26px
        }

        .expert-item .ex-name {
            color: #e2e8f0;
            font-weight: 600;
            font-size: 14px
        }

        .expert-item .ex-role {
            color: #6b7280;
            font-size: 12px
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px
        }

        .btn-join {
            flex: 1;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 13px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: .2s
        }

        .btn-join:hover {
            opacity: .88
        }

        .btn-share {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 12px;
            padding: 13px 16px;
            cursor: pointer;
            font-size: 18px
        }

        .steps {
            display: flex;
            gap: 8px;
            margin-bottom: 22px
        }

        .step-tab {
            flex: 1;
            padding: 9px 0;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, .05);
            color: #6b7280;
            border: none;
            transition: .2s
        }

        .step-tab.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff
        }

        .step-panel {
            display: none
        }

        .step-panel.active {
            display: block
        }

        .form-label {
            display: block;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px
        }

        .form-label .req {
            color: #f87171;
            margin-left: 3px
        }

        .form-input,
        .form-textarea {
            width: 100%;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(99, 102, 241, .2);
            border-radius: 10px;
            padding: 10px 14px;
            color: #f1f5f9;
            font-size: 14px;
            outline: none;
            transition: .2s;
            font-family: inherit
        }

        .form-input:focus,
        .form-textarea:focus {
            border-color: #6366f1
        }

        .form-textarea {
            resize: vertical
        }

        .form-group {
            margin-bottom: 14px
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px
        }

        .emoji-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px
        }

        .emoji-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            cursor: pointer;
            font-size: 18px;
            transition: .15s
        }

        .emoji-btn.selected {
            border: 2px solid #6366f1;
            background: rgba(99, 102, 241, .2)
        }

        .dur-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap
        }

        .dur-btn {
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            color: #94a3b8;
            cursor: pointer;
            font-weight: 600;
            transition: .2s
        }

        .dur-btn.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-color: transparent;
            color: #fff
        }

        .dynamic-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px
        }

        .dyn-input {
            flex: 1;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(99, 102, 241, .2);
            border-radius: 10px;
            padding: 9px 13px;
            color: #f1f5f9;
            font-size: 14px;
            outline: none;
            font-family: inherit
        }

        .dyn-input:focus {
            border-color: #6366f1
        }

        .btn-remove {
            background: rgba(239, 68, 68, .1);
            border: none;
            color: #f87171;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer
        }

        .btn-add {
            background: rgba(99, 102, 241, .15);
            border: 1px solid rgba(99, 102, 241, .3);
            color: #a5b4fc;
            border-radius: 8px;
            padding: 6px 14px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600
        }

        .expert-block {
            background: rgba(255, 255, 255, .04);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px
        }

        .form-nav {
            display: flex;
            gap: 10px;
            margin-top: 22px
        }

        .btn-next {
            flex: 2;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 13px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px
        }

        .btn-back {
            flex: 1;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #94a3b8;
            border-radius: 12px;
            padding: 13px;
            cursor: pointer;
            font-weight: 600
        }

        .btn-submit {
            flex: 2;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 13px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px
        }

        .btn-submit:disabled {
            background: rgba(255, 255, 255, .08);
            cursor: not-allowed
        }

        .sec-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px
        }
    </style>
</head>

<body>

    <header class="header">
        <div class="logo">&#x1F579; <span>GameForge</span> / Sprints</div>
        <button class="btn-primary" onclick="openCreate()">+ Создать спринт</button>
    </header>

    <div class="container">
        <div class="hero">
            <h1>Game Sprints</h1>
            <p>Создавай игры в сжатые сроки, соревнуйся с командами, получай признание</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="val" id="stat-total">0</div>
                    <div class="lbl">Спринтов всего</div>
                </div>
                <div class="hero-stat">
                    <div class="val" id="stat-members">0</div>
                    <div class="lbl">Участников</div>
                </div>
                <div class="hero-stat">
                    <div class="val" id="stat-active">0</div>
                    <div class="lbl">Открытых</div>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-wrap">
                <span class="ico">&#x1F50D;</span>
                <input class="search-input" id="search" placeholder="Поиск спринтов..." oninput="renderGrid()">
            </div>
            <div class="filters">
                <button class="filter-btn active" onclick="setFilter('all',this)">Все</button>
                <button class="filter-btn" onclick="setFilter('active',this)">Регистрация</button>
                <button class="filter-btn" onclick="setFilter('upcoming',this)">Скоро</button>
                <button class="filter-btn" onclick="setFilter('ongoing',this)">Идут</button>
                <button class="filter-btn" onclick="setFilter('finished',this)">Завершены</button>
            </div>
        </div>

        <div class="grid" id="grid"></div>
        <div class="empty" id="empty" style="display:none">
            <div class="ico">&#x1F50D;</div>
            <div>Спринты не найдены</div>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="overlay" id="view-overlay" onclick="closeView(event)">
        <div class="modal" id="view-modal"></div>
    </div>

    <!-- CREATE MODAL -->
    <div class="overlay" id="create-overlay" onclick="closeCreateOverlay(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2 style="color:#f1f5f9;font-size:20px;font-weight:800">&#x1F3AE; Создать спринт</h2>
                <button class="btn-close" onclick="closeCreate()">&#x2715;</button>
            </div>
            <div class="steps">
                <button class="step-tab active" id="tab1" onclick="goStep(1)">1. Основное</button>
                <button class="step-tab" id="tab2" onclick="goStep(2)">2. Время</button>
                <button class="step-tab" id="tab3" onclick="goStep(3)">3. Призы</button>
            </div>

            <div class="step-panel active" id="step1">
                <div class="form-group">
                    <label class="form-label">Иконка</label>
                    <div class="emoji-picker" id="banner-picker"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Название <span class="req">*</span></label>
                    <input class="form-input" id="f-title" placeholder="Pixel Chaos Sprint #4">
                </div>
                <div class="form-group">
                    <label class="form-label">Описание <span class="req">*</span></label>
                    <textarea class="form-textarea" id="f-desc" rows="3" placeholder="Расскажи о спринте, правилах и атмосфере..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Тема</label>
                    <input class="form-input" id="f-theme" placeholder="Скрыть до старта / Изоляция / ...">
                </div>
                <div class="form-group">
                    <label class="form-label">Организатор</label>
                    <input class="form-input" id="f-host" placeholder="Название команды или никнейм">
                </div>
                <div class="form-group">
                    <label class="form-label">Теги (через запятую)</label>
                    <input class="form-input" id="f-tags" placeholder="Unity, 48h, Пиксель-арт">
                </div>
                <div class="form-nav">
                    <button class="btn-next" onclick="goStep(2)">Далее &#x2192;</button>
                </div>
            </div>

            <div class="step-panel" id="step2">
                <div class="form-row form-group">
                    <div>
                        <label class="form-label">Дата начала <span class="req">*</span></label>
                        <input class="form-input" type="date" id="f-date">
                    </div>
                    <div>
                        <label class="form-label">Время</label>
                        <input class="form-input" type="time" id="f-time" value="12:00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Длительность</label>
                    <div class="dur-btns" id="dur-btns">
                        <button class="dur-btn active" onclick="setDur('24',this)">24ч</button>
                        <button class="dur-btn" onclick="setDur('48',this)">48ч</button>
                        <button class="dur-btn" onclick="setDur('72',this)">72ч</button>
                        <button class="dur-btn" onclick="setDur('96',this)">96ч</button>
                        <button class="dur-btn" onclick="setDur('168',this)">168ч</button>
                    </div>
                    <input class="form-input" type="number" id="f-dur" value="24" style="margin-top:8px;width:110px" placeholder="Часов">
                </div>
                <div class="form-group">
                    <label class="form-label">Макс. участников</label>
                    <input class="form-input" type="number" id="f-maxp" value="100">
                </div>
                <div class="form-nav">
                    <button class="btn-back" onclick="goStep(1)">&#x2190; Назад</button>
                    <button class="btn-next" onclick="goStep(3)">Далее &#x2192;</button>
                </div>
            </div>

            <div class="step-panel" id="step3">
                <div class="sec-row">
                    <span class="section-title">&#x1F3C6; Призовые места</span>
                    <button class="btn-add" onclick="addPrize()">+ Добавить</button>
                </div>
                <div id="prizes-list"></div>
                <div class="sec-row" style="margin-top:18px">
                    <span class="section-title">&#x2B50; Эксперты</span>
                    <button class="btn-add" onclick="addExpert()">+ Добавить</button>
                </div>
                <div id="experts-list"></div>
                <div class="form-nav">
                    <button class="btn-back" onclick="goStep(2)">&#x2190; Назад</button>
                    <button class="btn-submit" id="btn-submit" onclick="submitJam()">&#x1F680; Опубликовать</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var BANNERS = ['🎮', '🕹', '👾', '🚀', '🔥', '⚔', '🧩', '🌌', '🎲', '🏆', '💡', '🐉'];
        var AVATARS = ['👤', '🦅', '🌸', '🎭', '🦊', '🐺', '🤖', '🧠', '🎯', '⚡', '🌊', '🔮'];
        var MEDALS = ['🥇', '🥈', '🥉'];

        var jams = [{
                id: 1,
                title: 'Pixel Chaos Sprint #3',
                desc: 'Создай игру за 72 часа в пиксельном стиле. Тема раскроется в момент старта. Разрешены любые движки.',
                theme: 'Неизвестна до старта',
                banner: '🎮',
                status: 'active',
                start: Date.now() + 2 * 864e5,
                dur: 72,
                maxP: 200,
                curP: 134,
                prizes: [{
                    place: '1',
                    reward: '50 000 ₽ + Steam'
                }, {
                    place: '2',
                    reward: '20 000 ₽ + Менторство'
                }, {
                    place: '3',
                    reward: '10 000 ₽ + Мерч'
                }],
                experts: [{
                    name: 'Алексей Ворон',
                    role: 'Геймдизайнер, ex-Wargaming',
                    av: '🦅'
                }, {
                    name: 'Мария Седова',
                    role: 'Indie Dev, 3 игры в Steam',
                    av: '🌸'
                }],
                tags: ['Пиксель-арт', '72h', 'Любой движок'],
                host: 'GameDevClub'
            },
            {
                id: 2,
                title: 'Narrative Forge',
                desc: 'Спринт для нарративных игр и визуальных новелл. Исследуй тему одиночества через механики.',
                theme: 'Одиночество',
                banner: '📖',
                status: 'upcoming',
                start: Date.now() + 7 * 864e5,
                dur: 48,
                maxP: 100,
                curP: 47,
                prizes: [{
                    place: '1',
                    reward: '30 000 ₽ + Фичер'
                }, {
                    place: '2',
                    reward: '15 000 ₽'
                }],
                experts: [{
                    name: 'Дмитрий Кай',
                    role: 'Нарративный дизайнер',
                    av: '🎭'
                }],
                tags: ['Нарратив', '48h', 'Renpy/Unity'],
                host: 'StoryDev'
            }
        ];

        var curFilter = 'all';
        var selBanner = '🎮';
        var selDur = '24';
        var prizes = [{
            place: '1',
            reward: ''
        }];
        var experts = [{
            name: '',
            role: '',
            av: '👤'
        }];

        function countdown(ts) {
            var d = ts - Date.now();
            if (d <= 0) return 'Уже началось';
            var days = Math.floor(d / 864e5);
            var h = Math.floor((d % 864e5) / 36e5);
            var m = Math.floor((d % 36e5) / 6e4);
            if (days > 0) return days + 'д ' + h + 'ч';
            if (h > 0) return h + 'ч ' + m + 'м';
            return m + 'м';
        }

        function badgeHtml(s) {
            var map = {
                active: ['badge-active', 'Регистрация'],
                upcoming: ['badge-upcoming', 'Скоро'],
                ongoing: ['badge-ongoing', 'Идёт'],
                finished: ['badge-finished', 'Завершён']
            };
            var c = map[s] || map['upcoming'];
            return '<span class="badge ' + c[0] + '">' + c[1] + '</span>';
        }

        function medal(i) {
            return MEDALS[i] || '🎖';
        }

        function updateStats() {
            document.getElementById('stat-total').textContent = jams.length;
            document.getElementById('stat-members').textContent = jams.reduce(function(s, j) {
                return s + j.curP;
            }, 0);
            document.getElementById('stat-active').textContent = jams.filter(function(j) {
                return j.status !== 'finished';
            }).length;
        }

        function setFilter(f, el) {
            curFilter = f;
            var btns = document.querySelectorAll('.filter-btn');
            for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
            el.classList.add('active');
            renderGrid();
        }

        function renderGrid() {
            var q = document.getElementById('search').value.toLowerCase();
            var data = jams.filter(function(j) {
                var fm = (curFilter === 'all') || (j.status === curFilter);
                var sm = !q || j.title.toLowerCase().indexOf(q) !== -1 || j.desc.toLowerCase().indexOf(q) !== -1;
                return fm && sm;
            });

            var grid = document.getElementById('grid');
            var empty = document.getElementById('empty');

            if (!data.length) {
                grid.innerHTML = '';
                empty.style.display = 'block';
                updateStats();
                return;
            }
            empty.style.display = 'none';

            var html = '';
            for (var i = 0; i < data.length; i++) {
                var j = data[i];
                var pct = Math.min(100, Math.round(j.curP / j.maxP * 100));
                var tagsHtml = '';
                for (var t = 0; t < j.tags.length; t++) tagsHtml += '<span class="tag">' + j.tags[t] + '</span>';
                html += '<div class="card" onclick="openView(' + j.id + ')">';
                html += '<div class="card-top">';
                html += '<div class="card-banner">' + j.banner + '</div>';
                html += '<div class="card-meta">';
                html += '<div class="card-meta-row">' + badgeHtml(j.status) + '<span class="card-host">от ' + j.host + '</span></div>';
                html += '<div class="card-title">' + j.title + '</div>';
                html += '</div></div>';
                html += '<div class="card-desc">' + j.desc + '</div>';
                html += '<div class="tags">' + tagsHtml + '</div>';
                html += '<div class="card-stats">';
                html += '<div class="stat-box"><div class="s-lbl">&#x23F3; До старта</div><div class="s-val">' + countdown(j.start) + '</div></div>';
                html += '<div class="stat-box"><div class="s-lbl">&#x231B; Длительность</div><div class="s-val">' + j.dur + 'ч</div></div>';
                html += '</div>';
                html += '<div class="prog-wrap">';
                html += '<div class="prog-lbl"><span style="color:#94a3b8">&#x1F465; Участники</span><span>' + j.curP + ' / ' + j.maxP + '</span></div>';
                html += '<div class="prog-bar"><div class="prog-fill" style="width:' + pct + '%"></div></div>';
                html += '</div></div>';
            }
            grid.innerHTML = html;
            updateStats();
        }

        function openView(id) {
            var j = null;
            for (var i = 0; i < jams.length; i++)
                if (jams[i].id === id) {
                    j = jams[i];
                    break;
                }
            if (!j) return;

            var tagsHtml = '';
            for (var t = 0; t < j.tags.length; t++) tagsHtml += '<span class="tag">' + j.tags[t] + '</span>';

            var prizesHtml = '';
            for (var p = 0; p < j.prizes.length; p++) {
                prizesHtml += '<div class="prize-item"><span style="font-size:22px">' + medal(p) + '</span>';
                prizesHtml += '<div><div class="pi-place">' + j.prizes[p].place + ' место</div>';
                prizesHtml += '<div class="pi-reward">' + j.prizes[p].reward + '</div></div></div>';
            }

            var expertsHtml = '';
            for (var e = 0; e < j.experts.length; e++) {
                expertsHtml += '<div class="expert-item"><span class="av">' + j.experts[e].av + '</span>';
                expertsHtml += '<div><div class="ex-name">' + j.experts[e].name + '</div>';
                expertsHtml += '<div class="ex-role">' + j.experts[e].role + '</div></div></div>';
            }

            var themeHtml = j.theme ? '<div class="theme-box"><strong>&#x1F3AF; Тема: </strong>' + j.theme + '</div>' : '';

            var html = '';
            html += '<div class="modal-head">';
            html += '<div class="modal-title-row">';
            html += '<span class="modal-banner">' + j.banner + '</span>';
            html += '<div>' + badgeHtml(j.status) + '<div class="modal-h2">' + j.title + '</div><div class="modal-host">Организатор: ' + j.host + '</div></div>';
            html += '</div>';
            html += '<button class="btn-close" onclick="closeView()">&#x2715;</button>';
            html += '</div>';
            html += '<p class="modal-desc">' + j.desc + '</p>';
            html += themeHtml;
            html += '<div class="tags" style="margin-bottom:16px">' + tagsHtml + '</div>';
            html += '<div class="modal-stats">';
            html += '<div class="m-stat"><div class="ico">&#x23F3;</div><div class="val">' + countdown(j.start) + '</div><div class="lbl">До старта</div></div>';
            html += '<div class="m-stat"><div class="ico">&#x231B;</div><div class="val">' + j.dur + 'ч</div><div class="lbl">Длительность</div></div>';
            html += '<div class="m-stat"><div class="ico">&#x1F465;</div><div class="val">' + j.curP + '/' + j.maxP + '</div><div class="lbl">Участники</div></div>';
            html += '</div>';
            html += '<span class="section-title">&#x1F3C6; Призы</span>' + prizesHtml;
            html += '<span class="section-title" style="margin-top:16px">&#x2B50; Эксперты</span>' + expertsHtml;
            html += '<div class="modal-actions">';
            html += '<button class="btn-join">&#x1F3AE; Участвовать</button>';
            html += '<button class="btn-share">&#x1F517;</button>';
            html += '</div>';

            document.getElementById('view-modal').innerHTML = html;
            document.getElementById('view-overlay').classList.add('open');
        }

        function closeView(e) {
            if (!e || e.target === document.getElementById('view-overlay'))
                document.getElementById('view-overlay').classList.remove('open');
        }

        /* CREATE */
        function openCreate() {
            selBanner = '🎮';
            selDur = '24';
            prizes = [{
                place: '1',
                reward: ''
            }];
            experts = [{
                name: '',
                role: '',
                av: '👤'
            }];
            var ids = ['f-title', 'f-desc', 'f-theme', 'f-host', 'f-tags', 'f-date'];
            for (var i = 0; i < ids.length; i++) document.getElementById(ids[i]).value = '';
            document.getElementById('f-time').value = '12:00';
            document.getElementById('f-dur').value = '24';
            document.getElementById('f-maxp').value = '100';
            buildBannerPicker();
            buildPrizes();
            buildExperts();
            goStep(1);
            document.getElementById('create-overlay').classList.add('open');
        }

        function closeCreate() {
            document.getElementById('create-overlay').classList.remove('open');
        }

        function closeCreateOverlay(e) {
            if (e.target === document.getElementById('create-overlay')) closeCreate();
        }

        function buildBannerPicker() {
            var html = '';
            for (var i = 0; i < BANNERS.length; i++) {
                var b = BANNERS[i];
                var sel = b === selBanner ? ' selected' : '';
                html += '<button class="emoji-btn' + sel + '" onclick="selectBanner(\'' + b + '\',this)">' + b + '</button>';
            }
            document.getElementById('banner-picker').innerHTML = html;
        }

        function selectBanner(b, el) {
            selBanner = b;
            var btns = document.querySelectorAll('#banner-picker .emoji-btn');
            for (var i = 0; i < btns.length; i++) btns[i].classList.remove('selected');
            el.classList.add('selected');
        }

        function setDur(h, el) {
            selDur = h;
            document.getElementById('f-dur').value = h;
            var btns = document.querySelectorAll('.dur-btn');
            for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
            el.classList.add('active');
        }

        function goStep(n) {
            for (var i = 1; i <= 3; i++) {
                document.getElementById('step' + i).classList.toggle('active', i === n);
                document.getElementById('tab' + i).classList.toggle('active', i === n);
            }
        }

        function buildPrizes() {
            var html = '';
            for (var i = 0; i < prizes.length; i++) {
                html += '<div class="dynamic-row">';
                html += '<span style="font-size:20px;flex-shrink:0">' + medal(i) + '</span>';
                html += '<input class="dyn-input" data-pi="' + i + '" value="' + prizes[i].reward + '" placeholder="Приз за ' + prizes[i].place + ' место..." oninput="prizes[' + i + '].reward=this.value">';
                if (i > 0) html += '<button class="btn-remove" onclick="removePrize(' + i + ')">&#x2715;</button>';
                html += '</div>';
            }
            document.getElementById('prizes-list').innerHTML = html;
        }

        function addPrize() {
            prizes.push({
                place: String(prizes.length + 1),
                reward: ''
            });
            buildPrizes();
        }

        function removePrize(i) {
            prizes.splice(i, 1);
            for (var k = 0; k < prizes.length; k++) prizes[k].place = String(k + 1);
            buildPrizes();
        }

        function buildExperts() {
            var html = '';
            for (var i = 0; i < experts.length; i++) {
                var e = experts[i];
                html += '<div class="expert-block">';
                html += '<div class="emoji-picker" style="margin-bottom:8px">';
                for (var a = 0; a < AVATARS.length; a++) {
                    var av = AVATARS[a];
                    var sel = av === e.av ? ' selected' : '';
                    html += '<button class="emoji-btn' + sel + '" onclick="selectAv(' + i + ',\'' + av + '\',this)">' + av + '</button>';
                }
                html += '</div>';
                html += '<div class="dynamic-row">';
                html += '<input class="dyn-input" value="' + e.name + '" placeholder="Имя эксперта" oninput="experts[' + i + '].name=this.value">';
                html += '<input class="dyn-input" value="' + e.role + '" placeholder="Роль / опыт" oninput="experts[' + i + '].role=this.value">';
                if (i > 0) html += '<button class="btn-remove" onclick="removeExpert(' + i + ')">&#x2715;</button>';
                html += '</div></div>';
            }
            document.getElementById('experts-list').innerHTML = html;
        }

        function addExpert() {
            experts.push({
                name: '',
                role: '',
                av: '👤'
            });
            buildExperts();
        }

        function removeExpert(i) {
            experts.splice(i, 1);
            buildExperts();
        }

        function selectAv(i, a, el) {
            experts[i].av = a;
            var block = el.closest('.expert-block');
            var btns = block.querySelectorAll('.emoji-btn');
            for (var k = 0; k < btns.length; k++) btns[k].classList.remove('selected');
            el.classList.add('selected');
        }

        function submitJam() {
            var title = document.getElementById('f-title').value.trim();
            var desc = document.getElementById('f-desc').value.trim();
            var date = document.getElementById('f-date').value;
            if (!title || !desc || !date) {
                alert('Заполни обязательные поля (шаги 1–2)');
                return;
            }

            var dt = new Date(date + 'T' + document.getElementById('f-time').value);
            var newJam = {
                id: Date.now(),
                title: title,
                desc: desc,
                theme: document.getElementById('f-theme').value.trim() || 'Не указана',
                banner: selBanner,
                status: 'upcoming',
                start: dt.getTime(),
                dur: parseInt(document.getElementById('f-dur').value) || 48,
                maxP: parseInt(document.getElementById('f-maxp').value) || 100,
                curP: 0,
                prizes: prizes.filter(function(p) {
                    return p.reward;
                }),
                experts: experts.filter(function(e) {
                    return e.name;
                }),
                tags: document.getElementById('f-tags').value.split(',').map(function(t) {
                    return t.trim();
                }).filter(Boolean),
                host: document.getElementById('f-host').value.trim() || 'Аноним'
            };
            jams.unshift(newJam);
            closeCreate();
            renderGrid();
        }

        renderGrid();
        setInterval(renderGrid, 30000);
    </script>
</body>

</html>