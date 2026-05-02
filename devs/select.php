<?php

/**
 * devs/select.php — выбор активной студии + обработчик смены студии.
 * НЕ подключаем includes/header.php — избегаем бесконечного редиректа.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/user.php');

$curr_user = new User();
$db        = new Database();

if ($curr_user->checkAuth() > 0) {
    header('Location: /login?backUrl=/devs/select');
    exit();
}

$user_data = $_SESSION['USERDATA'];
$user_id   = (int)($user_data['id'] ?? 0);
$conn      = $db->connect();

// ── Обработчик выбора студии (вместо set_studio.php) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_id'])) {
    $selected_id = (int)$_POST['studio_id'];
    $back_url    = $_POST['backUrl'] ?? '/devs/';

    // Проверяем что студия реально принадлежит юзеру
    $check = $conn->prepare("SELECT id FROM studios WHERE id=? AND owner_id=?");
    $check->execute([$selected_id, $user_id]);

    if ($check->fetchColumn()) {
        $_SESSION['studio_id'] = $selected_id;
        // Сбрасываем закешированные данные студии
        unset($_SESSION['STUDIODATA']);
        // Редиректим только на /devs/ во избежание open redirect
        header('Location: /devs/');
    } else {
        header('Location: /devs/select?err=forbidden');
    }
    exit();
}

// ── Загрузка студий пользователя ─────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT * FROM studios
    WHERE owner_id = :id
    ORDER BY name ASC
");
$stmt->execute(['id' => $user_id]);
$studios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_studio_id = (int)($_SESSION['studio_id'] ?? 0);
$error = $_GET['err'] ?? '';

$status_labels = [
    'active'  => ['label' => 'Активна',       'color' => 'var(--ok)'],
    'banned'  => ['label' => 'Заблокирована',  'color' => 'var(--err)'],
    'pending' => ['label' => 'На проверке',    'color' => 'var(--warn)'],
];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор студии — Dustore.Devs</title>
    <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --p: #c32178;
            --pd: #9a1a5e;
            --pl: #ff5ba8;
            --dark: #14041d;
            --surf: #1a0a24;
            --elev: #241030;
            --tp: #fff;
            --ts: #b0b0c0;
            --tm: #5a5a6e;
            --ok: #00d68f;
            --warn: #ffaa00;
            --err: #ff3d71;
            --bd: rgba(255, 255, 255, 0.07);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, #2a0a3a 100%);
            color: var(--tp);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wrap {
            width: 100%;
            max-width: 520px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--p), var(--pl));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
        }

        .logo-name {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .page-sub {
            font-size: 14px;
            color: var(--ts);
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 20px;
            padding: 6px 14px 6px 8px;
            margin-bottom: 24px;
        }

        .user-ava {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--elev);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .user-ava img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 13px;
            font-weight: 500;
        }

        .studio-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .studio-card {
            width: 100%;
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all .15s;
            text-align: left;
            font-family: 'Inter', sans-serif;
            color: var(--tp);
        }

        .studio-card:hover {
            border-color: rgba(195, 33, 120, .4);
            background: rgba(195, 33, 120, .05);
            transform: translateY(-1px);
        }

        .studio-card.current {
            border-color: var(--p);
            background: rgba(195, 33, 120, .08);
        }

        .studio-card.banned {
            opacity: .6;
            cursor: not-allowed;
        }

        .studio-card.banned:hover {
            transform: none;
            border-color: rgba(255, 61, 113, .3);
            background: rgba(255, 61, 113, .04);
        }

        .stu-ava {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), #7a155d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            overflow: hidden;
        }

        .stu-ava img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .stu-info {
            flex: 1;
            min-width: 0;
        }

        .stu-name {
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stu-meta {
            font-size: 12px;
            color: var(--ts);
            margin-top: 2px;
        }

        .stu-role {
            font-size: 11px;
            color: var(--p);
            background: rgba(195, 33, 120, .12);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }

        .current-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(0, 214, 143, .12);
            color: var(--ok);
            flex-shrink: 0;
        }

        .status-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .empty {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 40px;
            text-align: center;
        }

        .empty .material-icons {
            font-size: 48px;
            color: var(--p);
            display: block;
            margin-bottom: 12px;
        }

        .empty p {
            color: var(--ts);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all .15s;
            text-decoration: none;
        }

        .btn-p {
            background: var(--p);
            color: #fff;
            width: 100%;
            justify-content: center;
        }

        .btn-p:hover {
            background: var(--pd);
        }

        .btn .material-icons {
            font-size: 18px;
        }

        .alert-err {
            background: rgba(255, 61, 113, .1);
            border: 1px solid rgba(255, 61, 113, .2);
            color: var(--err);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .footer-links {
            text-align: center;
            margin-top: 16px;
            display: flex;
            justify-content: center;
            gap: 16px;
        }

        .footer-links a {
            font-size: 12px;
            color: var(--tm);
            text-decoration: none;
            transition: .15s;
        }

        .footer-links a:hover {
            color: var(--p);
        }
    </style>
</head>

<body>
    <div class="wrap">

        <div class="page-header">
            <a href="/" class="logo">
                <div class="logo-icon">D</div>
                <div class="logo-name">Dustore.Devs</div>
            </a>
            <h1 class="page-title">Выберите студию</h1>
            <p class="page-sub">Выберите студию для работы в консоли разработчика</p>
        </div>

        <div style="text-align:center;margin-bottom:20px;">
            <div class="user-pill">
                <div class="user-ava">
                    <?php if (!empty($user_data['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($user_data['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <?= mb_strtoupper(mb_substr($user_data['username'] ?? 'U', 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <span class="user-name"><?= htmlspecialchars($user_data['username'] ?? '') ?></span>
            </div>
        </div>

        <?php if ($error === 'forbidden'): ?>
            <div class="alert-err">Эта студия вам не принадлежит.</div>
        <?php endif; ?>

        <?php if (empty($studios)): ?>
            <div class="empty">
                <span class="material-icons">apartment</span>
                <p>У вас пока нет студий.<br>Создайте свою первую студию на платформе.</p>
                <a href="/devs/regorg" class="btn btn-p">
                    <span class="material-icons">add_business</span>Создать студию
                </a>
            </div>

        <?php else: ?>
            <div class="studio-list">
                <?php foreach ($studios as $s):
                    $sid      = (int)$s['id'];
                    $initials = mb_strtoupper(mb_substr($s['name'], 0, 2));
                    $is_cur   = ($sid === $current_studio_id);
                    $status   = $s['status'] ?? 'active';
                    $is_banned = ($status === 'banned');
                    $scfg     = $status_labels[$status] ?? $status_labels['active'];
                ?>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="studio_id" value="<?= $sid ?>">
                        <button type="submit"
                            class="studio-card <?= $is_cur ? 'current' : '' ?> <?= $is_banned ? 'banned' : '' ?>"
                            <?= $is_banned ? 'disabled title="Студия заблокирована"' : '' ?>>

                            <div class="stu-ava">
                                <?php if (!empty($s['avatar_link'])): ?>
                                    <img src="<?= htmlspecialchars($s['avatar_link']) ?>" alt="">
                                    <?php else: ?><?= $initials ?><?php endif; ?>
                            </div>

                            <div class="stu-info">
                                <div class="stu-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="stu-meta">
                                    <?php if (!empty($s['tiker'])): ?>[<?= htmlspecialchars($s['tiker']) ?>]<?php endif; ?>
                                    <?php if (!empty($s['city'])): ?> · <?= htmlspecialchars($s['city']) ?><?php endif; ?>
                                        <?php if (!empty($s['team_size'])): ?> · <?= htmlspecialchars($s['team_size']) ?> чел.<?php endif; ?>
                                </div>
                                <?php if ($is_banned && !empty($s['ban_reason'])): ?>
                                    <span style="font-size:11px;color:var(--err);margin-top:4px;display:block;">
                                        Причина: <?= htmlspecialchars($s['ban_reason']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="stu-role">Владелец</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($is_cur && !$is_banned): ?>
                                <span class="current-badge">Активна</span>
                            <?php elseif (!$is_banned): ?>
                                <span class="material-icons" style="color:var(--tm);flex-shrink:0;">chevron_right</span>
                            <?php else: ?>
                                <span class="status-badge" style="background:rgba(255,61,113,.12);color:var(--err);">Заблокирована</span>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>

            <a href="/devs/regorg" class="btn btn-p">
                <span class="material-icons">add_business</span>Создать новую студию
            </a>
        <?php endif; ?>

        <div class="footer-links">
            <a href="/me">← Профиль</a>
            <a href="/">Главная</a>
        </div>
    </div>
</body>

</html>