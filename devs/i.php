<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrazyProjectsLab - Панель управления</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <style>
        :root {
            --primary: #7e57c2;
            --primary-dark: #5e35b1;
            --secondary: #26a69a;
            --dark-bg: #121212;
            --dark-surface: #1e1e1e;
            --dark-surface-hover: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --success: #66bb6a;
            --warning: #ffca28;
            --danger: #ef5350;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Сайдбар */
        .sidebar {
            width: 260px;
            background-color: var(--dark-surface);
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            transition: var(--transition);
            z-index: 100;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        }

        .logo-container {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .logo i {
            color: var(--primary);
            font-size: 28px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
            margin: 4px 0;
        }

        .nav-item:hover,
        .nav-item.active {
            background-color: var(--dark-surface-hover);
            color: var(--text-primary);
            border-left-color: var(--primary);
        }

        .nav-item i {
            margin-right: 15px;
            font-size: 24px;
        }

        .nav-label {
            font-size: 16px;
            font-weight: 500;
        }

        .admin-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-title {
            padding: 0 20px 15px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Основной контент */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .welcome {
            font-size: 28px;
            font-weight: 500;
        }

        .welcome span {
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
        }

        .user-name {
            font-weight: 500;
            font-size: 16px;
        }

        .user-role {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Статистика */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--dark-surface);
            border-radius: var(--border-radius);
            padding: 25px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-title {
            color: var(--text-secondary);
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-icon {
            position: absolute;
            right: 25px;
            top: 25px;
            opacity: 0.2;
            font-size: 50px;
        }

        .stat-action {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .stat-action:hover {
            color: var(--secondary);
        }

        /* Быстрые ссылки */
        .quick-links {
            background-color: var(--dark-surface);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 22px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary);
        }

        /* Таблица сотрудников */
        .table-container {
            background-color: var(--dark-surface);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: rgba(255, 255, 255, 0.05);
            text-align: left;
            padding: 18px 25px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
        }

        td {
            padding: 15px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
        }

        .status.active {
            background-color: rgba(102, 187, 106, 0.15);
            color: var(--success);
        }

        .status i {
            font-size: 18px;
        }

        .footer {
            text-align: center;
            padding: 30px 0 20px;
            color: var(--text-secondary);
            font-size: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 30px;
        }

        /* Адаптивность */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .nav-label,
            .admin-title,
            .logo-text {
                display: none;
            }

            .logo-container {
                padding: 20px 15px;
                text-align: center;
            }

            .logo i {
                margin: 0;
            }

            .nav-item {
                justify-content: center;
                padding: 18px 0;
            }

            .nav-item i {
                margin: 0;
                font-size: 28px;
            }

            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .welcome {
                font-size: 24px;
            }

            .user-info {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
            }

            .nav-item {
                padding: 16px 0;
            }

            .nav-item i {
                font-size: 24px;
            }

            .stat-card {
                padding: 20px;
            }

            th,
            td {
                padding: 12px 15px;
            }

            .stat-icon {
                display: none;
            }
        }

        /* Анимации */
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

        .stat-card,
        .quick-links,
        .table-container {
            animation: fadeIn 0.6s ease forwards;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.3s;
        }

        .quick-links {
            animation-delay: 0.4s;
        }

        .table-container {
            animation-delay: 0.5s;
        }
    </style>
</head>

<body>
    <!-- Сайдбар -->
    <aside class="sidebar">
        <div class="logo-container">
            <div class="logo">
                <i class="material-icons">developer_board</i>
                <span class="logo-text">CrazyProjectsLab</span>
            </div>
        </div>

        <a href="#" class="nav-item active">
            <i class="material-icons">dashboard</i>
            <span class="nav-label">Панель управления</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">groups</i>
            <span class="nav-label">Сотрудники</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">work</i>
            <span class="nav-label">Проекты</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">note_add</i>
            <span class="nav-label">Отзывы</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">stars</i>
            <span class="nav-label">Рейтинг игры</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">currency_ruble</i>
            <span class="nav-label">Монетизация</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">bug_report</i>
            <span class="nav-label">Написать отчёт</span>
        </a>
        <a href="#" class="nav-item">
            <i class="material-icons">campaign</i>
            <span class="nav-label">Создать событие</span>
        </a>

        <div class="admin-section">
            <div class="admin-title">Для администраторов</div>
            <a href="#" class="nav-item">
                <i class="material-icons">shield</i>
                <span class="nav-label">Администрирование</span>
            </a>
            <a href="#" class="nav-item">
                <i class="material-icons">campaign</i>
                <span class="nav-label">Объявления</span>
            </a>
        </div>
    </aside>

    <!-- Основной контент -->
    <main class="main-content">
        <div class="header">
            <h1 class="welcome">Добро пожаловать, <span>crazya11my1if3</span></h1>
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name">@crazya11my1if3</div>
                    <div class="user-role">Создатель студии</div>
                </div>
                <div class="user-avatar">C</div>
            </div>
        </div>

        <!-- Статистика -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="material-icons stat-icon">groups</i>
                <div class="stat-title">
                    <i class="material-icons">groups</i>
                    Сотрудники
                </div>
                <div class="stat-value">0</div>
                <a href="#" class="stat-action">
                    Управление
                    <i class="material-icons">arrow_forward</i>
                </a>
            </div>

            <div class="stat-card">
                <i class="material-icons stat-icon">work</i>
                <div class="stat-title">
                    <i class="material-icons">work</i>
                    Созданные проекты
                </div>
                <div class="stat-value">0</div>
                <a href="#" class="stat-action">
                    Управление
                    <i class="material-icons">arrow_forward</i>
                </a>
            </div>

            <div class="stat-card">
                <i class="material-icons stat-icon">sports_esports</i>
                <div class="stat-title">
                    <i class="material-icons">sports_esports</i>
                    Играют в ваши игры
                </div>
                <div class="stat-value">0</div>
                <a href="#" class="stat-action">
                    Управление
                    <i class="material-icons">arrow_forward</i>
                </a>
            </div>

            <div class="stat-card">
                <i class="material-icons stat-icon">payments</i>
                <div class="stat-title">
                    <i class="material-icons">payments</i>
                    Доход за последний месяц
                </div>
                <div class="stat-value">0 ₽</div>
                <a href="#" class="stat-action">
                    Управление
                    <i class="material-icons">arrow_forward</i>
                </a>
            </div>
        </div>

        <!-- Быстрые ссылки -->
        <div class="quick-links">
            <h2 class="section-title">
                <i class="material-icons">link</i>
                Быстрые ссылки
            </h2>

            <div class="section-subtitle">Ваши сотрудники</div>
        </div>

        <!-- Таблица сотрудников -->
        <div class="table-container">
            <table>
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
                        <td>
                            <span class="status active">
                                <i class="material-icons">check_circle</i>
                                Активен
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Футер -->
        <div class="footer">
            © 2024-2025 Dust Studio
            <span style="margin: 0 10px">|</span>
            <a href="#" style="color: var(--text-secondary); text-decoration: none;">
                <i class="material-icons" style="vertical-align: middle; font-size: 18px;">mode_edit</i>
                Редактировать
            </a>
        </div>
    </main>

    <script>
        // Добавление интерактивности при наведении на карточки
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');

            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>

</html>