<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore.Devs - Консоль разработчиков</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #c32178;
            --primary-dark: #9a1a5e;
            --primary-light: #ff5ba8;
            --secondary: #74155d;
            --dark: #14041d;
            --dark-surface: #1a0a24;
            --dark-elevated: #241030;
            --text-primary: #ffffff;
            --text-secondary: #b8b8b8;
            --text-muted: #7a7a7a;
            --success: #00d68f;
            --warning: #ffaa00;
            --danger: #ff3d71;
            --border: rgba(255, 255, 255, 0.08);
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, var(--secondary) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-surface);
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .studio-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .studio-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }

        .studio-details h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .studio-role {
            font-size: 12px;
            color: var(--text-muted);
            background: rgba(195, 33, 120, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Navigation */
        .nav-section {
            padding: 16px;
        }

        .nav-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 8px 12px;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 12px;
            margin: 2px 0;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary);
            transform: scaleY(0);
            transition: transform 0.2s ease;
        }

        .nav-item:hover {
            background: var(--dark-elevated);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: rgba(195, 33, 120, 0.15);
            color: var(--primary-light);
        }

        .nav-item.active::before {
            transform: scaleY(1);
        }

        .nav-item .material-icons {
            font-size: 20px;
        }

        .nav-item span {
            font-size: 14px;
            font-weight: 500;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            background: transparent;
        }

        /* Header */
        .header {
            background: var(--dark-surface);
            border-bottom: 1px solid var(--border);
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-title {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text-primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--dark-elevated);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
        }

        .icon-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--dark-elevated);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-menu:hover {
            background: var(--primary);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }

        /* Content Area */
        .content {
            padding: 32px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--dark-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(195, 33, 120, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-light);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text-primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 6px;
            margin-top: 8px;
        }

        .stat-change.positive {
            background: rgba(0, 214, 143, 0.1);
            color: var(--success);
        }

        .stat-change.negative {
            background: rgba(255, 61, 113, 0.1);
            color: var(--danger);
        }

        /* Action Cards */
        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .action-card {
            background: var(--dark-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .action-card .material-icons {
            font-size: 48px;
            color: var(--primary-light);
            margin-bottom: 16px;
        }

        .action-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .action-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Table Section */
        .table-section {
            background: var(--dark-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(195, 33, 120, 0.4);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: var(--dark-elevated);
        }

        .data-table th {
            padding: 16px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .data-table tr:hover {
            background: var(--dark-elevated);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.active {
            background: rgba(0, 214, 143, 0.15);
            color: var(--success);
        }

        .badge.pending {
            background: rgba(255, 170, 0, 0.15);
            color: var(--warning);
        }

        .badge.draft {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid,
            .action-cards {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 16px 20px;
            }

            .content {
                padding: 20px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
            opacity: 0;
        }

        .delay-2 {
            animation-delay: 0.2s;
            opacity: 0;
        }

        .delay-3 {
            animation-delay: 0.3s;
            opacity: 0;
        }

        .delay-4 {
            animation-delay: 0.4s;
            opacity: 0;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="studio-info">
                    <div class="studio-avatar">CP</div>
                    <div class="studio-details">
                        <h3>CrazyProjectsLab</h3>
                        <span class="studio-role">Создатель</span>
                    </div>
                </div>
            </div>

            <nav class="nav-section">
                <div class="nav-title">Главное</div>
                <a href="#" class="nav-item active">
                    <i class="material-icons">dashboard</i>
                    <span>Панель управления</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">apartment</i>
                    <span>Моя студия</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">groups</i>
                    <span>Сотрудники</span>
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">Проекты</div>
                <a href="#" class="nav-item">
                    <i class="material-icons">work</i>
                    <span>Все проекты</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">add_circle</i>
                    <span>Создать новый</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">stars</i>
                    <span>Рейтинг игр</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">currency_ruble</i>
                    <span>Монетизация</span>
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">Поддержка</div>
                <a href="#" class="nav-item">
                    <i class="material-icons">bug_report</i>
                    <span>Сообщить об ошибке</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="material-icons">menu_book</i>
                    <span>Документация</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1 class="header-title">Панель управления</h1>
                <div class="header-actions">
                    <div class="icon-btn">
                        <i class="material-icons">notifications</i>
                    </div>
                    <div class="icon-btn">
                        <i class="material-icons">settings</i>
                    </div>
                    <div class="user-menu">
                        <div class="user-avatar"></div>
                        <span>crazya11my1if3</span>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Сотрудники</div>
                                <div class="stat-change positive">
                                    <i class="material-icons" style="font-size: 14px;">arrow_upward</i>
                                    <span>+0% за месяц</span>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="material-icons">groups</i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Проекты</div>
                                <div class="stat-change positive">
                                    <i class="material-icons" style="font-size: 14px;">arrow_upward</i>
                                    <span>+0% за месяц</span>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="material-icons">work</i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Активные игроки</div>
                                <div class="stat-change positive">
                                    <i class="material-icons" style="font-size: 14px;">arrow_upward</i>
                                    <span>+0% за месяц</span>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="material-icons">sports_esports</i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card animate-in delay-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">0 ₽</div>
                                <div class="stat-label">Доход за месяц</div>
                                <div class="stat-change positive">
                                    <i class="material-icons" style="font-size: 14px;">arrow_upward</i>
                                    <span>+0% за месяц</span>
                                </div>
                            </div>
                            <div class="stat-icon">
                                <i class="material-icons">payments</i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="action-cards animate-in delay-4">
                    <div class="action-card">
                        <i class="material-icons">add_circle</i>
                        <h3>Создать проект</h3>
                        <p>Начните разработку нового проекта</p>
                    </div>
                    <div class="action-card">
                        <i class="material-icons">menu_book</i>
                        <h3>Документация</h3>
                        <p>Изучите руководства для разработчиков</p>
                    </div>
                    <div class="action-card">
                        <i class="material-icons">support_agent</i>
                        <h3>Поддержка</h3>
                        <p>Получите помощь от нашей команды</p>
                    </div>
                </div>

                <!-- Team Table -->
                <div class="table-section animate-in delay-4">
                    <div class="table-header">
                        <h2>Команда</h2>
                        <button class="btn btn-primary">
                            <i class="material-icons">add</i>
                            Пригласить
                        </button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Имя пользователя</th>
                                <th>Должность</th>
                                <th>Последний вход</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>@crazya11my1if3</td>
                                <td>Создатель</td>
                                <td>1 час назад</td>
                                <td><span class="badge active">Активен</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>