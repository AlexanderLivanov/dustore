<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка принята — Dustore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0e13;
            --surface: #131720;
            --border: #232b3a;
            --accent: #4ade80;
            --text: #e8edf5;
            --muted: #6b7a99;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 60% 60% at 50% 40%, rgba(74, 222, 128, .07) 0%, transparent 70%);
            pointer-events: none;
        }

        .card {
            position: relative;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 60px 48px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            animation: pop .6s cubic-bezier(.34, 1.56, .64, 1) both;
        }

        .icon-ring {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(74, 222, 128, .1);
            border: 2px solid rgba(74, 222, 128, .3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 28px;
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 12px rgba(74, 222, 128, .2);
            }

            to {
                box-shadow: 0 0 32px rgba(74, 222, 128, .45);
            }
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 14px;
        }

        p {
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 10px;
        }

        .steps {
            margin: 28px 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: rgba(255, 255, 255, .03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: rgba(74, 222, 128, .15);
            color: var(--accent);
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: .8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .step-text {
            font-size: .88rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .step-text strong {
            color: var(--text);
            display: block;
            margin-bottom: 2px;
        }

        .btn {
            display: inline-block;
            padding: 13px 28px;
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            text-decoration: none;
            transition: all .2s;
            margin-top: 8px;
        }

        .btn:hover {
            background: var(--border);
        }

        @keyframes pop {
            from {
                opacity: 0;
                transform: scale(.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon-ring">✓</div>
        <h1>Заявка отправлена!</h1>
        <p>Спасибо! Ваша заявка на участие в экспертной программе Dustore принята и будет рассмотрена в ближайшее время.</p>

        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">
                    <strong>Заявка на проверке</strong>
                    Администраторы изучают ваш опыт и мотивацию.
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">
                    <strong>Уведомление на email</strong>
                    Мы отправим решение на ваш адрес электронной почты.
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">
                    <strong>Доступ к панели</strong>
                    После одобрения вы получите доступ к инструментам оценки игр.
                </div>
            </div>
        </div>

        <a href="/expert" class="btn">← Вернуться</a>
    </div>
</body>

</html>