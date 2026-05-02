<?php
$page_title = 'Моя студия';
$active_nav = 'studio';
require_once(__DIR__ . '/includes/header.php');

// Показываем уведомление если студия только что создана
$created = isset($_GET['created']);

$conn = $db->connect();
$stmt = $conn->prepare("SELECT * FROM studios WHERE id=?");
$stmt->execute([$studio_id]);
$si = $stmt->fetch(PDO::FETCH_ASSOC);

$success_msg = $created ? 'Студия создана! Заполните профиль и дождитесь модерации.' : '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_tags = '<p><br><b><i><u><strong><em><a><ul><ol><li><h2><h3><h4><blockquote><s>';
    $data = [
        'name'          => mb_substr(preg_replace("/[^A-Za-zА-Яа-яёЁ0-9\-_! ]/u", '', $_POST['studio-name'] ?? ''), 0, 100),
        'tiker'         => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $_POST['tiker'] ?? ''), 0, 5)),
        'description'   => strip_tags($_POST['description'] ?? '', $allowed_tags),
        'website'       => filter_var($_POST['website'] ?? '', FILTER_SANITIZE_URL),
        'vk_public_id'  => preg_replace('/[^0-9_]/', '', $_POST['vk_public_id'] ?? ''),
        'tg_studio_id'  => preg_replace('/[^0-9_\-]/', '', $_POST['tg_studio_id'] ?? ''),
        'country'       => mb_substr($_POST['country'] ?? '', 0, 64),
        'city'          => mb_substr($_POST['city'] ?? '', 0, 100),
        'contact_email' => filter_var($_POST['contact_email'] ?? '', FILTER_SANITIZE_EMAIL),
        'foundation_date' => $_POST['foundation_date'] ?? '',
        'team_size'     => $_POST['team_size'] ?? '',
        'specialization' => $_POST['specialization'] ?? '',
        'pre_alpha'     => isset($_POST['pre_alpha_program']) ? 1 : 0,
        'donate_link'   => filter_var($_POST['donate_link'] ?? '', FILTER_SANITIZE_URL),
        'avatar_link'   => filter_var($_POST['avatar_link'] ?? '', FILTER_SANITIZE_URL),
        'banner_link'   => filter_var($_POST['banner_link'] ?? '', FILTER_SANITIZE_URL),
    ];
    $token_raw = $_POST['api_token'] ?? '';
    $data['api_token'] = !empty($token_raw) ? hash('sha256', $token_raw) : $si['api_token'];

    if (empty($data['name'])) {
        $error_msg = 'Название студии не может быть пустым.';
    } else {
        try {
            $conn->prepare("
                UPDATE studios SET
                    name=:name,tiker=:tiker,description=:description,
                    website=:website,vk_public_id=:vk,tg_studio_id=:tg,
                    country=:country,city=:city,contact_email=:email,
                    foundation_date=:fdate,team_size=:tsize,specialization=:spec,
                    pre_alpha_program=:pre_alpha,donate_link=:donate,
                    avatar_link=:avatar,banner_link=:banner,api_token=:api_token
                WHERE id=:id
            ")->execute([
                'name' => $data['name'],
                'tiker' => $data['tiker'],
                'description' => $data['description'],
                'website' => $data['website'],
                'vk' => $data['vk_public_id'],
                'tg' => $data['tg_studio_id'],
                'country' => $data['country'],
                'city' => $data['city'],
                'email' => $data['contact_email'],
                'fdate' => $data['foundation_date'],
                'tsize' => $data['team_size'],
                'spec' => $data['specialization'],
                'pre_alpha' => $data['pre_alpha'],
                'donate' => $data['donate_link'],
                'avatar' => $data['avatar_link'],
                'banner' => $data['banner_link'],
                'api_token' => $data['api_token'],
                'id' => $studio_id,
            ]);
            $success_msg = 'Изменения сохранены!';
            $stmt = $conn->prepare("SELECT * FROM studios WHERE id=?");
            $stmt->execute([$studio_id]);
            $si = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_msg = 'Ошибка: ' . $e->getMessage();
        }
    }
}

function sv($a, $k)
{
    return htmlspecialchars($a[$k] ?? '');
}
?>

<!-- Quill editor CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .ql-toolbar {
        background: var(--elev);
        border: 1px solid var(--bd) !important;
        border-radius: 8px 8px 0 0 !important;
    }

    .ql-container {
        background: var(--elev);
        border: 1px solid var(--bd) !important;
        border-radius: 0 0 8px 8px !important;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        min-height: 160px;
    }

    .ql-editor {
        color: #fff;
        min-height: 160px;
        padding: 12px 14px;
    }

    .ql-editor.ql-blank::before {
        color: var(--tm);
        font-style: normal;
    }

    .ql-toolbar .ql-stroke {
        stroke: #b0b0c0;
    }

    .ql-toolbar .ql-fill {
        fill: #b0b0c0;
    }

    .ql-toolbar .ql-picker-label {
        color: #b0b0c0;
    }

    .ql-toolbar button:hover .ql-stroke,
    .ql-toolbar button.ql-active .ql-stroke {
        stroke: #fff;
    }

    .ql-toolbar button:hover .ql-fill,
    .ql-toolbar button.ql-active .ql-fill {
        fill: #fff;
    }
</style>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

<form method="POST" id="studio-form">
    <div class="grid-2" style="gap:16px;align-items:start;">

        <div class="card">
            <div class="card-title"><span class="material-icons">apartment</span>Профиль студии</div>
            <div class="field"><label>Название студии *</label>
                <input type="text" name="studio-name" value="<?= sv($si, 'name') ?>" required minlength="3" maxlength="100">
            </div>
            <div class="field"><label>Тикер (до 5 букв)</label>
                <input type="text" name="tiker" value="<?= sv($si, 'tiker') ?>" maxlength="5" placeholder="CPL">
            </div>
            <div class="field"><label>Специализация</label>
                <select name="specialization">
                    <?php foreach (['mobile' => 'Мобильные игры', 'pc' => 'ПК игры', 'console' => 'Консольные', 'vr' => 'VR игры', 'software' => 'ПО и утилиты', 'all' => 'Всё'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= sv($si, 'specialization') === $v ? ' selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Размер команды</label>
                <select name="team_size">
                    <?php foreach (['1' => '1 человек', '2-5' => '2–5', '6-10' => '6–10', '11-20' => '11–20', '20+' => '20+'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= sv($si, 'team_size') === $v ? ' selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Дата основания</label>
                <input type="date" name="foundation_date" value="<?= sv($si, 'foundation_date') ?>">
            </div>
            <div class="field"><label>Страна</label>
                <input type="text" name="country" value="<?= sv($si, 'country') ?>" maxlength="64">
            </div>
            <div class="field"><label>Город</label>
                <input type="text" name="city" value="<?= sv($si, 'city') ?>" maxlength="100">
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="card">
                <div class="card-title"><span class="material-icons">language</span>Контакты</div>
                <div class="field"><label>Сайт студии</label>
                    <input type="url" name="website" value="<?= sv($si, 'website') ?>" placeholder="https://...">
                </div>
                <div class="field"><label>Email</label>
                    <input type="email" name="contact_email" value="<?= sv($si, 'contact_email') ?>">
                </div>
                <div class="field"><label>VK сообщество (ID)</label>
                    <input type="text" name="vk_public_id" value="<?= sv($si, 'vk_public_id') ?>" placeholder="public123456">
                </div>
                <div class="field"><label>Telegram чат (ID)</label>
                    <input type="text" name="tg_studio_id" value="<?= sv($si, 'tg_studio_id') ?>" placeholder="-1001234567890">
                </div>
                <div class="field"><label>Ссылка на донаты</label>
                    <input type="url" name="donate_link" value="<?= sv($si, 'donate_link') ?>" placeholder="https://...">
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">image</span>Медиа</div>
                <div class="field"><label>Аватар (URL)</label>
                    <input type="url" name="avatar_link" id="ava_inp" value="<?= sv($si, 'avatar_link') ?>"
                        oninput="liveImg('ava_inp','ava_prev','ava_wrap')">
                </div>
                <div id="ava_wrap" style="<?= empty($si['avatar_link']) ? 'display:none;' : '' ?>margin-bottom:10px;">
                    <img id="ava_prev" src="<?= sv($si, 'avatar_link') ?>"
                        style="width:64px;height:64px;border-radius:12px;object-fit:cover;">
                </div>
                <div class="field"><label>Баннер (URL)</label>
                    <input type="url" name="banner_link" id="ban_inp" value="<?= sv($si, 'banner_link') ?>"
                        oninput="liveImg('ban_inp','ban_prev','ban_wrap')">
                </div>
                <div id="ban_wrap" style="<?= empty($si['banner_link']) ? 'display:none;' : '' ?>">
                    <img id="ban_prev" src="<?= sv($si, 'banner_link') ?>"
                        style="width:100%;border-radius:8px;object-fit:cover;max-height:80px;">
                </div>
            </div>
        </div>

        <!-- Описание на всю ширину -->
        <div class="card col-full">
            <div class="card-title"><span class="material-icons">description</span>Описание студии</div>
            <!-- Скрытый textarea для отправки -->
            <textarea id="description_hidden" name="description" style="display:none;"><?= sv($si, 'description') ?></textarea>
            <!-- Quill editor -->
            <div id="quill_editor"></div>
            <div style="font-size:11px;color:var(--tm);margin-top:8px;">
                Поддерживается форматирование: жирный, курсив, списки, заголовки, ссылки.
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">vpn_key</span>API Токен</div>
            <div class="field">
                <label><?= !empty($si['api_token']) ? 'Токен установлен — введите новый для замены' : 'Установить токен' ?></label>
                <input type="password" name="api_token" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div class="alert alert-warn" style="font-size:12px;">⚠️ После сохранения токен скрыт — запишите заранее.</div>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">science</span>Pre-Alpha программа</div>
            <p style="font-size:13px;color:var(--ts);margin-bottom:14px;line-height:1.6;">Тестируйте новые функции платформы раньше других.</p>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="checkbox" name="pre_alpha_program" style="accent-color:var(--p);width:auto;" <?= $si['pre_alpha_program'] ? 'checked' : '' ?>>
                <span style="font-size:13px;font-weight:500;">Участвовать в Pre-Alpha</span>
            </label>
        </div>

        <div class="col-full" style="display:flex;gap:10px;justify-content:flex-end;">
            <a href="/devs/" class="btn btn-g">Отмена</a>
            <button type="submit" class="btn btn-p"><span class="material-icons">save</span>Сохранить</button>
        </div>
    </div>
</form>

<?php
$initial_content = addslashes(htmlspecialchars_decode($si['description'] ?? ''));
$extra_js = <<<JS
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const quill = new Quill('#quill_editor', {
    theme: 'snow',
    placeholder: 'Расскажите о вашей студии...',
    modules: {
        toolbar: [
            ['bold','italic','underline','strike'],
            [{'header':2},{'header':3}],
            [{'list':'ordered'},{'list':'bullet'}],
            ['link','clean']
        ]
    }
});

// Загружаем сохранённый HTML
const saved = document.getElementById('description_hidden').value;
if (saved) quill.clipboard.dangerouslyPasteHTML(saved);

// Перед отправкой пишем HTML из редактора в hidden textarea
document.getElementById('studio-form').addEventListener('submit', () => {
    document.getElementById('description_hidden').value = quill.root.innerHTML;
});

function liveImg(inpId, imgId, wrapId) {
    const url  = document.getElementById(inpId).value.trim();
    const img  = document.getElementById(imgId);
    const wrap = document.getElementById(wrapId);
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
}
</script>
JS;
require_once(__DIR__ . '/includes/footer.php');
?>