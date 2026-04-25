<?php
session_start();
require_once('swad/config.php');
require_once('swad/controllers/organization.php');

$db = new Database();
$studio = $db->Select("SELECT * FROM studios WHERE tiker  = ?", [$_GET['name'] ?? ''])[0];

if (!$studio) {
    header('Location: /explore');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dustore - <?= $studio['name'] ?></title>
    <script type="text/javascript" src="https://vk.com/js/api/openapi.js?168"></script>
    <link rel="stylesheet" href="/swad/css/devpage.css">
    <link rel="shortcut icon" href="/swad/static/img/logo.svg" type="image/x-icon">
</head>

<body>
    <?php require_once('swad/static/elements/header.php'); ?>
    <section class="studio-hero">
        <div class="studio-banner" style="background-image: url('<?= $studio['banner_link'] ?>');"></div>
        <div class="container">
            <div class="studio-content">
                <div class="studio-main">
                    <div class="studio-header">
                        <img class="studio-logo" src="<?= $studio['avatar_link'] ?>" alt="">
                        <div class="studio-info-header">
                            <h1><?= $studio['name'] ?></h1>
                            <?php if(!empty($studio['donate_link'])): ?>
                                <h3>💰<a href="<?= $studio['donate_link'] ?>" style="color: #ffffff;" target="_blank">Задонатить разработчику</a></h3>
                            <?php endif; ?>
                            <div class="studio-badges">
                                <?php
                                $date = new DateTime($studio['foundation_date']);
                                $months = [
                                    1 => 'января',
                                    2 => 'февраля',
                                    3 => 'марта',
                                    4 => 'апреля',
                                    5 => 'мая',
                                    6 => 'июня',
                                    7 => 'июля',
                                    8 => 'августа',
                                    9 => 'сентября',
                                    10 => 'октября',
                                    11 => 'ноября',
                                    12 => 'декабря'
                                ];

                                $day = $date->format('d');
                                $month = $months[(int)$date->format('m')];
                                $year = $date->format('Y');

                                $all_projects = $db->Select("SELECT * FROM games WHERE developer = ?", [$studio['id']]);
                                $all_badges = $db->Select("SELECT 
                                                                            b.icon_url,
                                                                            b.name AS badge_name,
                                                                            b.description,
                                                                            b.multiplier AS coefficient,
                                                                            sb.awarded_at AS award_date
                                                                        FROM given_badges sb
                                                                        JOIN badges b ON sb.badge_id = b.id
                                                                        WHERE sb.studio_id = ?
                                                                        ORDER BY sb.awarded_at DESC", [$studio['id']])
                                ?>
                                <div class="studio-badge">Дата основания: <?= $day . " " . $month . " " . $year ?></div>
                                <div class="studio-badge">Сотрудников: <?= $studio['team_size'] ?></div>
                                <div class="studio-badge">Проектов: <?= sizeof($all_projects) ?></div>
                                <div class="studio-badge">Тикер: [<?= $studio['tiker'] ?>] <span style="color: green;">+100%</span></div>
                            </div>
                            <div class="studio-stats">
                                <div class="stat-item">
                                    <div class="stat-value">0.0</div>
                                    <div class="stat-label">Рейтинг</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= sizeof($all_projects) ?></div>
                                    <div class="stat-label">Проекты</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">0</div>
                                    <div class="stat-label">Загрузок</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">100%</div>
                                    <div class="stat-label">Рекомендаций</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="studio-description">
                        <h2>О студии</h2>
                        <p><?= $studio['description'] ?></p>
                    </div>

                    <!-- <div class="studio-history">
                    </div> -->

                    <!-- <div class="team-section">
                        <h2>Наша команда</h2>
                        <div class="team-members">
                            <div class="team-member">
                                <div class="member-avatar">👨‍💻</div>
                                <h3>Иван Иванов</h3>
                                <p>Основатель & CEO</p>
                            </div>
                            <div class="team-member">
                                <div class="member-avatar">👩‍🎨</div>
                                <h3>Иван Иванов</h3>
                                <p>Главный дизайнер</p>
                            </div>
                        </div>
                    </div> -->

                    <div class="projects-section">
                        <h2>Наши проекты</h2>
                        <?php if (sizeof($all_projects) > 0): ?>
                            <div class="projects-grid">
                                <?php foreach ($all_projects as $project): ?>
                                    <div class="project-card" onclick="location.href='/g/<?= $project['id'] ?>'">
                                        <div class="project-image" style="background: url('<?= $project['path_to_cover'] ?>') no-repeat center center / cover;"></div>
                                        <div class="project-info">
                                            <h3 class="project-title"><?= $project['name'] ?></h3>
                                            <div class="project-rating">GQI: <?= $project['GQI'] ?></div>
                                            <p><?= $project['short_description'] ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (sizeof($all_projects) < 1): ?>
                            <h2>У студии пока нет открытых проектов...</hw>
                            <?php endif; ?>
                    </div>
                </div>

                <div class="studio-sidebar">
                    <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                        <h3>Обратная связь</h3>
                        <div style="margin-top: 20px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                <div style="font-size: 1.5rem;">🌐</div>
                                <div><?= $studio['website'] ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                <div style="font-size: 1.5rem;">✉️</div>
                                <div><?= $studio['contact_email'] ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                <div style="font-size: 1.5rem;">📱</div>
                                <div><a href="<?= $studio['tg_link'] ?>">Telegram</a> и <a href="<?= $studio['vk_link'] ?>">VK</a></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="font-size: 1.5rem;">🏢</div>
                                <div><?= strtoupper($studio['country']) . ', ' . $studio['city'] ?></div>
                            </div>
                            <div id="vk_groups" style="padding: 1rem;"></div>
                            <script type="text/javascript">
                                VK.Widgets.Group("vk_groups", {
                                    mode: 3,
                                    height: 400,
                                    color1: "FFFFFF",
                                    color2: "000000",
                                    color3: "c32178"
                                }, <?= $studio['vk_public_id'] ?>);
                            </script>
                        </div>
                    </div>

                    <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                        <h3>Награды и достижения</h3>
                        <?php if (sizeof($all_badges) > 0): ?>
                            <?php foreach ($all_badges as $badge): ?>
                                <div style="margin-top: 20px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <div style="font-size: 2rem;"><img src="<?= $badge['icon_url'] ?>" alt="" style="width: 100%; border-radius: 50%"></div>
                                        <div><?= $badge['badge_name'] ?> <span style="font-size: 0.7rem; font-style: italic;"><?= $badge['description'] ?></span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (sizeof($all_badges) <= 0): ?>
                            <h3>Эта студия ещё не получила трофеи :(</h3>
                        <?php endif; ?>
                    </div>

                    <div style="background: rgba(255,255,255,0.05); border-radius: 15px; padding: 25px;">
                        <h3>Статьи на Dustore.Media</h3>
                        <div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 10px; margin-top: 20px;">
                            <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                                <div style="font-size: 1rem;">Этот раздел пока не доступен...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require_once('swad/static/elements/footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация элементов при загрузке
            const animateElements = [
                ...document.querySelectorAll('.studio-logo, .stat-item'),
                ...document.querySelectorAll('.team-member'),
                ...document.querySelectorAll('.project-card')
            ];

            animateElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `all 0.1s ease ${index * 0.1}s`;

                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100);
            });
        });
    </script>
</body>

</html>