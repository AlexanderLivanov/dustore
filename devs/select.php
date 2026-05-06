<?php
/**
 * devs/select.php — выбор активной студии.
 * FIX: показываем студии где юзер числится через staff (не только owner_id)
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
$user_id   = (int)($user_data['id']          ?? 0);
$tg_id     = (string)($user_data['telegram_id'] ?? '');
$conn      = $db->connect();

// ── Обработчик выбора студии ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_id'])) {
    $selected_id = (int)$_POST['studio_id'];

    // Проверяем: юзер — владелец ИЛИ числится в staff
    $check = $conn->prepare("
        SELECT s.id FROM studios s
        WHERE s.id = ?
          AND (
            s.owner_id = ?
            OR EXISTS (
                SELECT 1 FROM staff st
                WHERE st.org_id = s.id
                  AND CAST(st.telegram_id AS CHAR) = CAST(? AS CHAR)
            )
          )
        LIMIT 1
    ");
    $check->execute([$selected_id, $user_id, $tg_id]);

    if ($check->fetchColumn()) {
        $_SESSION['studio_id'] = $selected_id;
        unset($_SESSION['STUDIODATA']);
        header('Location: /devs/');
    } else {
        header('Location: /devs/select?err=forbidden');
    }
    exit();
}

// ── Загрузка студий: owner + staff ────────────────────────────────────────
// Объединяем: студии где owner_id = user_id + студии из staff по telegram_id
$stmt = $conn->prepare("
    SELECT DISTINCT s.*,
        CASE WHEN s.owner_id = :uid THEN 'Владелец'
             ELSE COALESCE(st.role, 'Участник')
        END AS my_role
    FROM studios s
    LEFT JOIN staff st ON st.org_id = s.id
        AND CAST(st.telegram_id AS CHAR) = CAST(:tg AS CHAR)
    WHERE s.owner_id = :uid2
       OR (st.id IS NOT NULL AND CAST(st.telegram_id AS CHAR) = CAST(:tg2 AS CHAR))
    ORDER BY s.name ASC
");
$stmt->execute([
    'uid'  => $user_id,
    'uid2' => $user_id,
    'tg'   => $tg_id,
    'tg2'  => $tg_id,
]);
$studios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_studio_id = (int)($_SESSION['studio_id'] ?? 0);
$error = $_GET['err'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор студии — Dustore.Devs</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:   #0d0118;
            --surf: #160824;
            --elev: #1e0f2e;
            --bd:   rgba(255,255,255,.08);
            --p:    #c32178;
            --pl:   #ff5ba8;
            --pd:   #9a1a5e;
            --tt:   #f0e6ff;
            --ts:   rgba(240,230,255,.6);
            --tm:   rgba(240,230,255,.35);
            --ok:   #00d68f;
            --err:  #ff3d71;
            --r:    10px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--tt);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .wrap {
            width: 100%;
            max-width: 440px;
        }

        .page-header { text-align: center; margin-bottom: 28px; }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--p), var(--pl));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #fff;
        }

        .logo-name { font-size: 18px; font-weight: 700; color: #fff; }

        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub   { font-size: 14px; color: var(--ts); }

        .user-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--surf); border: 1px solid var(--bd);
            border-radius: 20px; padding: 6px 14px 6px 8px;
            margin-bottom: 24px;
        }

        .user-ava {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--elev); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
        }

        .user-ava img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-size: 13px; font-weight: 500; }

        .alert-err {
            background: rgba(255,61,113,.1); border: 1px solid rgba(255,61,113,.2);
            color: var(--err); padding: 12px 16px; border-radius: var(--r);
            font-size: 13px; margin-bottom: 16px;
        }

        .studio-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }

        .studio-card {
            background: var(--surf); border: 1px solid var(--bd);
            border-radius: 14px; padding: 0;
            transition: border-color .2s, background .2s;
            cursor: pointer; overflow: hidden;
        }

        .studio-card:hover { border-color: rgba(195,33,120,.4); background: var(--elev); }
        .studio-card.current { border-color: var(--p); }

        .studio-card form { display: contents; }

        .studio-btn {
            width: 100%; background: none; border: none; cursor: pointer;
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; color: var(--tt); text-align: left;
            font-family: inherit;
        }

        .studio-avatar {
            width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), var(--pd));
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; overflow: hidden;
        }

        .studio-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .studio-info { flex: 1; min-width: 0; }

        .studio-name {
            font-size: 14px; font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .studio-meta { font-size: 11px; color: var(--ts); margin-top: 3px; }

        .studio-role {
            font-size: 10px; font-weight: 700; letter-spacing: .04em;
            padding: 2px 8px; border-radius: 6px;
            background: rgba(195,33,120,.15); color: var(--pl);
            white-space: nowrap;
        }

        .studio-role.owner { background: rgba(255,170,0,.12); color: #ffaa00; }

        .current-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--ok); flex-shrink: 0;
        }

        .empty {
            text-align: center; padding: 40px 20px;
            background: var(--surf); border: 1px solid var(--bd);
            border-radius: 14px;
        }

        .empty .material-icons { font-size: 48px; color: var(--tm); display: block; margin-bottom: 12px; }
        .empty p { font-size: 13px; color: var(--ts); line-height: 1.6; margin-bottom: 20px; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: var(--r);
            font-size: 13px; font-weight: 600; border: none;
            cursor: pointer; text-decoration: none; font-family: inherit;
        }

        .btn-p { background: var(--p); color: #fff; }
        .btn-p:hover { background: var(--pd); }
        .btn-g {
            background: var(--elev); color: var(--ts);
            border: 1px solid var(--bd);
        }
        .btn-g:hover { color: #fff; }

        .footer-links {
            text-align: center; margin-top: 20px;
            display: flex; justify-content: center; gap: 20px;
        }

        .footer-links a {
            font-size: 12px; color: var(--tm); text-decoration: none;
        }

        .footer-links a:hover { color: var(--p); }
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
            <div class="alert-err">
                <span class="material-icons" style="font-size:15px;vertical-align:middle;">lock</span>
                У вас нет доступа к этой студии.
            </div>
        <?php endif; ?>

        <?php if (empty($studios)): ?>
            <div class="empty">
                <span class="material-icons">apartment</span>
                <p>У вас пока нет студий.<br>Создайте свою первую или попросите владельца добавить вас в команду.</p>
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
                    $my_role  = $s['my_role'] ?? 'Участник';
                    $is_owner_role = ($my_role === 'Владелец');
                ?>
                    <div class="studio-card <?= $is_cur ? 'current' : '' ?>">
                        <form method="POST">
                            <input type="hidden" name="studio_id" value="<?= $sid ?>">
                            <button type="submit" class="studio-btn">
                                <div class="studio-avatar">
                                    <?php if (!empty($s['avatar_link'])): ?>
                                        <img src="<?= htmlspecialchars($s['avatar_link']) ?>" alt="">
                                    <?php else: ?>
                                        <?= $initials ?>
                                    <?php endif; ?>
                                </div>
                                <div class="studio-info">
                                    <div class="studio-name"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="studio-meta">
                                        <?= htmlspecialchars($s['specialization'] ?? '') ?>
                                        <?php if (!empty($s['city'])): ?>
                                            · <?= htmlspecialchars($s['city']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="studio-role <?= $is_owner_role ? 'owner' : '' ?>">
                                    <?= htmlspecialchars($my_role) ?>
                                </span>
                                <?php if ($is_cur): ?>
                                    <div class="current-dot" title="Текущая студия"></div>
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align:center;">
                <a href="/devs/regorg" class="btn btn-g">
                    <span class="material-icons">add_business</span>Создать новую студию
                </a>
            </div>
        <?php endif; ?>

        <div class="footer-links">
            <a href="/">← На главную</a>
            <a href="/me">Профиль</a>
        </div>
    </div>
</body>
</html>