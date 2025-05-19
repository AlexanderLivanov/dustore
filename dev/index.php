<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Core Dashboard</title>

    <!-- Улучшенный кибер-дизайн с детализацией -->
    <style>
        :root {
            --matrix-green: #00ff88;
            --neon-purple: #bc13fe;
            --interface-black: #0a0a12;
            --hud-blue: #00f3ff;
        }

        body {
            background: var(--interface-black);
            color: white;
            font-family: 'Source Code Pro', monospace;
            margin: 0;
            overflow-x: hidden;
        }

        .core-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
        }

        /* Динамическая сетка данных */
        .data-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }

        /* Расширенные карточки проектов */
        .project-frame {
            background: linear-gradient(15deg,
                    rgba(0, 255, 136, 0.1) 0%,
                    rgba(11, 12, 16, 0.95) 40%);
            border: 1px solid var(--matrix-green);
            padding: 1.5rem;
            position: relative;
        }

        .project-header {
            display: grid;
            grid-template-columns: auto 120px;
            border-bottom: 2px solid var(--neon-purple);
            padding-bottom: 1rem;
        }

        .runtime-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .stat-node {
            background: rgba(0, 243, 255, 0.05);
            padding: 1rem;
            border-left: 3px solid var(--hud-blue);
        }

        /* Детализированная секция команды */
        .unit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 4rem 0;
        }

        .unit-card {
            background: rgba(188, 19, 254, 0.05);
            border: 1px solid var(--neon-purple);
            padding: 1.5rem;
        }

        .task-queue {
            margin-top: 1rem;
            border-top: 1px solid rgba(188, 19, 254, 0.3);
            padding-top: 1rem;
        }

        /* Расширенная аналитика */
        .analytics-hub {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 4rem 0;
        }

        .main-graph {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid var(--matrix-green);
            padding: 2rem;
        }

        .side-panel {
            display: grid;
            gap: 1.5rem;
        }

        /* Анимации */
        @keyframes pulse {
            0% {
                opacity: 0.2;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.2;
            }
        }
    </style>
</head>

<body>
    <div class="core-container">
        <!-- Секция проектов -->
        <h2 style="color: var(--matrix-green);">ACTIVE OPERATIONS</h2>
        <div class="data-matrix">
            <div class="project-frame">
                <div class="project-header">
                    <div>
                        <h3>PROJECT: CYBER NOIR</h3>
                        <div class="status-tag" style="background: var(--neon-purple);">BETA v0.8.3</div>
                    </div>
                    <div class="runtime-stats">
                        <div class="stat-node">
                            <small>UPTIME</small>
                            <div>98.7%</div>
                        </div>
                    </div>
                </div>

                <div class="runtime-stats">
                    <div class="stat-node">
                        <small>DAILY ACTIVE</small>
                        <div>24.8K</div>
                        <progress value="75" max="100"></progress>
                    </div>
                    <div class="stat-node">
                        <small>REVENUE 24H</small>
                        <div>$5,429</div>
                        <div style="color: var(--matrix-green);">↑12.4%</div>
                    </div>
                    <div class="stat-node">
                        <small>BUGS</small>
                        <div>142</div>
                        <div style="color: var(--hud-blue);">12 new</div>
                    </div>
                </div>

                <div class="tech-specs">
                    <h4>SYSTEM LOAD</h4>
                    <div class="spec-grid">
                        <div>CPU: 68% <progress value="68" max="100"></progress></div>
                        <div>RAM: 4.2/8GB <progress value="52.5" max="100"></progress></div>
                        <div>NET: 324Mb/s <progress value="65" max="100"></progress></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Детализированная команда -->
        <h2 style="color: var(--neon-purple);">UNIT ROSTER</h2>
        <div class="unit-grid">
            <div class="unit-card">
                <div class="unit-header">
                    <h4>ENGINEER-01</h4>
                    <div class="specs">
                        <span>LEVEL: 47</span>
                        <span>XP: 84%</span>
                    </div>
                </div>
                <button class="hud-button">++ ADD DIRECTIVE</button>
                <div class="task-queue">
                    <div class="directive">
                        <div>▹ OPTIMIZE RENDER PIPELINE</div>
                        <small>PHASE: IMPLEMENTATION</small>
                        <progress value="65" max="100"></progress>
                    </div>
                    <div class="directive">
                        <div>▹ FIX NETWORK DESYNC</div>
                        <small>PHASE: TESTING</small>
                        <progress value="90" max="100"></progress>
                    </div>
                </div>
            </div>
        </div>

        <!-- Комплексная аналитика -->
        <div class="analytics-hub">
            <div class="main-graph">
                <canvas id="mainChart"></canvas>
            </div>
            <div class="side-panel">
                <div class="stat-node">
                    <h4>LIVE FEED</h4>
                    <div>▶ 342 NEW PLAYERS (24H)</div>
                    <div>▶ $12,430 REVENUE</div>
                    <div>▶ 45 CODE COMMITS</div>
                </div>
                <div class="stat-node">
                    <h4>CRITICAL SYSTEMS</h4>
                    <div>■ DATABASE: STABLE</div>
                    <div>■ CDN: 98.4% UPTIME</div>
                    <div>■ API: RESPONSE 142ms</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                        label: 'Active Players',
                        data: [12000, 19000, 30000, 28000, 42000, 39000, 45000],
                        backgroundColor: 'rgba(0, 255, 136, 0.2)',
                        borderColor: '#00ff88'
                    },
                    {
                        label: 'Revenue ($)',
                        data: [2400, 3900, 6000, 5400, 8400, 7500, 9200],
                        backgroundColor: 'rgba(188, 19, 254, 0.2)',
                        borderColor: '#bc13fe'
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        },
                        ticks: {
                            color: '#fff'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        },
                        ticks: {
                            color: '#fff'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>