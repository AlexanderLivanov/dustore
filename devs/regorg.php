<?php

/**
 * devs/regorg.php — регистрация новой студии.
 * Standalone-страница без includes/header.php (как select.php).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/user.php');

$curr_user = new User();
$db        = new Database();

if ($curr_user->checkAuth() > 0) {
    header('Location: /login?backUrl=/devs/regorg');
    exit();
}

$user_data = $_SESSION['USERDATA'];
$user_id   = (int)($user_data['id'] ?? 0);
$conn      = $db->connect();

$error_msg = '';

// ── Fire-and-forget: вызываем notify_worker.php без ожидания ответа ───────
function dispatchNotifications(array $params): void
{
    $body = http_build_query($params);
    $len  = strlen($body);
    $req  = "POST /devs/notify_worker.php HTTP/1.1\r\n"
        . "Host: localhost\r\n"
        . "Content-Type: application/x-www-form-urlencoded\r\n"
        . "Content-Length: {$len}\r\n"
        . "Connection: close\r\n\r\n"
        . $body;

    $sock = @fsockopen('127.0.0.1', 80, $errno, $errstr, 0.2);
    if (!$sock) return;
    stream_set_blocking($sock, false); // не ждём ответа вообще
    fwrite($sock, $req);
    fclose($sock);
}

// ── POST: создание студии ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
    $tiker          = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['tiker'] ?? ''), 0, 5));
    $description    = trim($_POST['description'] ?? '');
    $specialization = $_POST['specialization'] ?? 'pc';
    $team_size      = $_POST['team_size'] ?? '1';
    $country        = mb_substr(trim($_POST['country'] ?? ''), 0, 64);
    $city           = mb_substr(trim($_POST['city'] ?? ''), 0, 100);
    $website        = filter_var($_POST['website'] ?? '', FILTER_SANITIZE_URL);
    $contact_email  = filter_var($_POST['contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $avatar_link    = filter_var($_POST['avatar_link'] ?? '', FILTER_SANITIZE_URL);

    if (mb_strlen($name) < 3) {
        $error_msg = 'Название студии должно содержать минимум 3 символа.';
    } elseif (mb_strlen($description) < 10) {
        $error_msg = 'Описание слишком короткое — минимум 10 символов.';
    } else {
        $check = $conn->prepare("SELECT COUNT(*) FROM studios WHERE name=?");
        $check->execute([$name]);
        if ((int)$check->fetchColumn() > 0) {
            $error_msg = 'Студия с таким названием уже существует.';
        } else {
            try {
                $conn->prepare("
                    INSERT INTO studios
                        (status, ban_reason, name, tiker, owner_id, description,
                         vk_link, tg_link, website, country, city, contact_email,
                         avatar_link, specialization, team_size, created_at)
                    VALUES
                        ('pending', '', :name, :tiker, :owner_id, :description,
                         '', '', :website, :country, :city, :email,
                         :avatar, :spec, :tsize, NOW())
                ")->execute([
                    'name'        => $name,
                    'tiker'       => $tiker ?: strtoupper(mb_substr($name, 0, 3)),
                    'owner_id'    => $user_id,
                    'description' => $description,
                    'website'     => $website,
                    'country'     => $country,
                    'city'        => $city,
                    'email'       => $contact_email,
                    'avatar'      => $avatar_link,
                    'spec'        => $specialization,
                    'tsize'       => $team_size,
                ]);
                $new_studio_id = (int)$conn->lastInsertId();

                // Добавляем владельца в staff
                $conn->prepare("
                    INSERT INTO staff (telegram_id, org_id, created, role)
                    VALUES (?, ?, NOW(), 'Владелец')
                ")->execute([$user_data['telegram_id'] ?? '', $new_studio_id]);

                // Активируем студию в сессии
                $_SESSION['studio_id'] = $new_studio_id;
                unset($_SESSION['STUDIODATA']);

                // ── Асинхронно запускаем уведомления — не ждём ───────────
                dispatchNotifications([
                    'studio_id'      => $new_studio_id,
                    'studio_name'    => $name,
                    'tiker'          => $tiker ?: mb_substr($name, 0, 3),
                    'owner_id'       => $user_id,
                    'owner_name'     => $user_data['username'] ?? '',
                    'owner_email'    => $user_data['email']    ?? '',
                    'specialization' => $specialization,
                ]);

                // Редиректим мгновенно — письма уходят в фоне
                header('Location: /devs/mystudio?created=1');
                exit();
            } catch (PDOException $e) {
                $error_msg = 'Ошибка при создании студии: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать студию — Dustore.Devs</title>
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
            padding: 40px 24px;
        }

        .wrap {
            width: 100%;
            max-width: 580px;
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

        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
        }

        .step-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step.active .step-num {
            background: var(--p);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(195, 33, 120, .25);
        }

        .step.idle .step-num {
            background: var(--elev);
            color: var(--tm);
        }

        .step.idle span {
            color: var(--tm);
        }

        .step-line {
            width: 32px;
            height: 2px;
            background: var(--elev);
            margin: 0 4px;
            flex-shrink: 0;
        }

        .card {
            background: var(--surf);
            border: 1px solid var(--bd);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title .material-icons {
            font-size: 18px;
            color: var(--p);
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 11px;
            color: var(--ts);
            margin-bottom: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            background: var(--elev);
            border: 1px solid var(--bd);
            border-radius: 9px;
            padding: 10px 14px;
            color: #fff;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color .15s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--p);
        }

        .field input::placeholder {
            color: var(--tm);
        }

        .field select option {
            background: var(--elev);
        }

        .field textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.5;
        }

        .field-hint {
            font-size: 11px;
            color: var(--tm);
            margin-top: 5px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .ava-preview-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 10px;
        }

        .ava-preview {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: var(--elev);
            border: 1px solid var(--bd);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ava-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .alert-err {
            background: rgba(255, 61, 113, .1);
            border: 1px solid rgba(255, 61, 113, .2);
            color: var(--err);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notice {
            background: rgba(255, 170, 0, .07);
            border: 1px solid rgba(255, 170, 0, .18);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--warn);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notice-info {
            background: rgba(195, 33, 120, .07);
            border: 1px solid rgba(195, 33, 120, .18);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--pl);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all .15s;
            text-decoration: none;
            width: 100%;
        }

        .btn-p {
            background: var(--p);
            color: #fff;
        }

        .btn-p:hover {
            background: var(--pd);
        }

        .btn-g {
            background: var(--elev);
            color: var(--ts);
            border: 1px solid var(--bd);
        }

        .btn-g:hover {
            color: #fff;
        }

        .btn .material-icons {
            font-size: 18px;
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

        @media(max-width:560px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
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
            <h1 class="page-title">Создать студию</h1>
            <p class="page-sub">Заполните основную информацию — остальное можно добавить позже</p>
        </div>

        <div class="steps">
            <div class="step active">
                <div class="step-num">1</div><span>Информация</span>
            </div>
            <div class="step-line"></div>
            <div class="step idle">
                <div class="step-num">2</div><span>Настройки</span>
            </div>
            <div class="step-line"></div>
            <div class="step idle">
                <div class="step-num">3</div><span>Модерация</span>
            </div>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert-err">
                <span class="material-icons" style="font-size:16px;">error_outline</span>
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <div class="notice">
            ⏳ После создания студия попадёт на <strong>модерацию</strong>. Вы получите письмо-подтверждение, а администратор — уведомление.
        </div>

        <?php if (empty($user_data['email'])): ?>
            <div class="notice-info">
                📧 У вас не привязан email — письмо-подтверждение не будет отправлено.
                <a href="/me" style="color:var(--pl);">Привязать в профиле →</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="card">
                <div class="card-title"><span class="material-icons">apartment</span>Основная информация</div>
                <div class="grid-2">
                    <div class="field" style="grid-column:1/-1;">
                        <label>Название студии *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            required minlength="3" maxlength="100" placeholder="Название вашей студии"
                            oninput="updateTiker(this.value)">
                        <div class="field-hint">Минимум 3 символа · будет отображаться в каталоге</div>
                    </div>
                    <div class="field">
                        <label>Тикер (до 5 букв)</label>
                        <input type="text" name="tiker" id="tiker_inp"
                            value="<?= htmlspecialchars($_POST['tiker'] ?? '') ?>"
                            maxlength="5" placeholder="CPL" style="text-transform:uppercase;">
                        <div class="field-hint">Только латиница</div>
                    </div>
                    <div class="field">
                        <label>Специализация</label>
                        <select name="specialization">
                            <?php foreach (['pc' => 'ПК игры', 'mobile' => 'Мобильные игры', 'console' => 'Консольные игры', 'vr' => 'VR игры', 'software' => 'ПО и утилиты', 'all' => 'Всё сразу'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($_POST['specialization'] ?? '') === $v ? ' selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Размер команды</label>
                        <select name="team_size">
                            <?php foreach (['1' => 'Только я', '2-5' => '2–5', '6-10' => '6–10', '11-20' => '11–20', '20+' => '20+'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($_POST['team_size'] ?? '') === $v ? ' selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <label>Описание студии *</label>
                        <textarea name="description" required minlength="10"
                            placeholder="Расскажите о вашей студии..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">language</span>Контакты
                    <span style="font-size:11px;color:var(--tm);font-weight:400;margin-left:4px;">необязательно</span>
                </div>
                <div class="grid-2">
                    <div class="field"><label>Страна</label><input type="text" name="country" value="<?= htmlspecialchars($_POST['country'] ?? '') ?>" placeholder="Россия"></div>
                    <div class="field"><label>Город</label><input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="Москва"></div>
                    <div class="field"><label>Сайт студии</label><input type="url" name="website" value="<?= htmlspecialchars($_POST['website'] ?? '') ?>" placeholder="https://..."></div>
                    <div class="field"><label>Email для связи</label><input type="email" name="contact_email" value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>" placeholder="studio@example.com"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">image</span>Аватар
                    <span style="font-size:11px;color:var(--tm);font-weight:400;margin-left:4px;">необязательно</span>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Ссылка на изображение</label>
                    <input type="url" name="avatar_link" id="avatar_url_inp"
                        value="<?= htmlspecialchars($_POST['avatar_link'] ?? '') ?>"
                        placeholder="https://..." oninput="previewAvatar(this.value)">
                    <div class="ava-preview-wrap">
                        <div class="ava-preview" id="ava_box">
                            <img id="ava_img" src="" alt="">
                            <span id="ava_ph" class="material-icons" style="color:var(--tm);font-size:24px;">apartment</span>
                        </div>
                        <span style="font-size:12px;color:var(--tm);">Предпросмотр</span>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;">
                <button type="submit" class="btn btn-p"><span class="material-icons">send</span>Создать студию</button>
                <a href="/devs/select" class="btn btn-g">Отмена</a>
            </div>
        </form>

        <div class="footer-links">
            <a href="/me">← Профиль</a>
            <a href="/">Главная</a>
        </div>
    </div>
    <script>
        function updateTiker(val) {
            const inp = document.getElementById('tiker_inp');
            if (inp.dataset.manual) return;
            const map = {
                А: 'A',
                Б: 'B',
                В: 'V',
                Г: 'G',
                Д: 'D',
                Е: 'E',
                Ё: 'E',
                Ж: 'J',
                З: 'Z',
                И: 'I',
                Й: 'Y',
                К: 'K',
                Л: 'L',
                М: 'M',
                Н: 'N',
                О: 'O',
                П: 'P',
                Р: 'R',
                С: 'S',
                Т: 'T',
                У: 'U',
                Ф: 'F',
                Х: 'H',
                Ц: 'C',
                Ч: 'C',
                Ш: 'S',
                Щ: 'S',
                Ъ: '',
                Ы: 'Y',
                Ь: '',
                Э: 'E',
                Ю: 'U',
                Я: 'A'
            };
            inp.value = val.toUpperCase().replace(/[А-ЯЁ]/g, c => map[c] || c).replace(/[^A-Z]/g, '').substring(0, 5);
        }
        document.getElementById('tiker_inp').addEventListener('input', function() {
            this.dataset.manual = '1';
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 5);
        });

        function previewAvatar(url) {
            const img = document.getElementById('ava_img'),
                ph = document.getElementById('ava_ph');
            if (url) {
                img.src = url;
                img.style.display = 'block';
                ph.style.display = 'none';
                img.onerror = () => {
                    img.style.display = 'none';
                    ph.style.display = '';
                };
            } else {
                img.style.display = 'none';
                ph.style.display = '';
            }
        }
    </script>
</body>

</html>