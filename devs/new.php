<?php

/**
 * devs/new.php — создание нового проекта.
 * POST обрабатывается ДО include header.php (иначе headers already sent).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3.php');

// ── POST: обрабатываем до любого вывода ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db       = new Database();
    $conn     = $db->connect();
    $user_data  = $_SESSION['USERDATA'] ?? [];
    $studio_id  = (int)($_SESSION['studio_id'] ?? 0);
    $is_admin   = ((int)($user_data['global_role'] ?? 0)) === -1;

    // Проверяем роль из staff
    $tg_id = (string)($user_data['telegram_id'] ?? '');
    $_sr   = $conn->prepare("SELECT role FROM staff WHERE telegram_id=? AND org_id=? LIMIT 1");
    $_sr->execute([$tg_id, $studio_id]);
    $_row  = $_sr->fetch(PDO::FETCH_ASSOC);
    $is_owner = $is_admin || (($_row['role'] ?? '') === 'Владелец');

    if (!$is_owner && !$is_admin) {
        header('Location: /devs/?err=no_permission');
        exit();
    }

    $name         = trim($_POST['project-name']  ?? '');
    $genre        = trim($_POST['genre']         ?? '');
    $short_desc   = trim($_POST['short_description'] ?? '');
    $description  = trim($_POST['description']   ?? '');
    $platforms    = implode(',', $_POST['platform'] ?? []);
    $release_date = $_POST['release-date']  ?? date('Y-m-d');
    $game_website = filter_var($_POST['website'] ?? '', FILTER_SANITIZE_URL);
    $game_exec    = trim($_POST['game-exec'] ?? '');
    $trailer_url  = filter_var($_POST['trailer'] ?? '', FILTER_SANITIZE_URL);
    $languages    = trim($_POST['languages'] ?? '');
    $age_rating   = $_POST['age_rating'] ?? '0+';
    $cover_path   = '';

    $error_msg = '';
    if (empty($name))  $error_msg = 'Укажите название проекта.';
    elseif (empty($genre)) $error_msg = 'Укажите жанр.';

    if (!$error_msg) {
        // Cover upload → S3
        if (!empty($_FILES['cover-art']['name']) && $_FILES['cover-art']['error'] === UPLOAD_ERR_OK) {
            try {
                $s3  = new S3Uploader();
                $ext = pathinfo($_FILES['cover-art']['name'], PATHINFO_EXTENSION);
                $key = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio')
                    . '/' . preg_replace('/[^a-z0-9]/i', '-', $name) . '/cover-' . uniqid() . '.' . $ext;
                $up  = $s3->uploadFile($_FILES['cover-art']['tmp_name'], $key);
                if ($up) $cover_path = $up;
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        try {
            $conn->prepare("
                INSERT INTO games
                    (developer, publisher, name, genre, short_description, description,
                     platforms, release_date, path_to_cover, game_website,
                     banner_url, trailer_url, features, screenshots, requirements,
                     languages, age_rating, game_exec, status, GQI)
                VALUES
                    (:dev,:pub,:name,:genre,:short,:desc,
                     :platforms,:release,:cover,:website,
                     '',  :trailer,'[]','[]','[]',
                     :languages,:age,:exec,'draft',0)
            ")->execute([
                'dev'       => $studio_id,
                'pub'      => $studio_id,
                'name'      => $name,
                'genre'    => $genre,
                'short'     => $short_desc ?: mb_substr($description, 0, 200),
                'desc'      => $description,
                'platforms' => $platforms,
                'release'   => $release_date,
                'cover'  => $cover_path,
                'website'   => $game_website,
                'trailer' => $trailer_url,
                'languages' => $languages,
                'age'      => $age_rating,
                'exec'      => $game_exec,
            ]);
            $new_id = (int)$conn->lastInsertId();
            header('Location: /devs/edit?id=' . $new_id . '&created=1');
            exit();
        } catch (PDOException $e) {
            $error_msg = 'Ошибка БД: ' . $e->getMessage();
        }
    }
    // Если ошибка — запоминаем и идём дальше на вывод формы
    $_SESSION['_new_error'] = $error_msg;
    $_SESSION['_new_post']  = $_POST;
    header('Location: /devs/new?err=1');
    exit();
}

// ── GET: показываем форму ─────────────────────────────────────────────────
$error_msg = '';
if (isset($_GET['err'])) {
    $error_msg = $_SESSION['_new_error'] ?? '';
    $old_post  = $_SESSION['_new_post']  ?? [];
    unset($_SESSION['_new_error'], $_SESSION['_new_post']);
} else {
    $old_post = [];
}

$page_title = 'Новый проект';
$active_nav = 'new';
require_once(__DIR__ . '/includes/header.php');

// Проверка прав (после header.php, т.к. уже рендерим страницу)
if (!$is_owner && !$is_admin) {
    echo '<div class="alert alert-err"><span class="material-icons" style="font-size:16px;vertical-align:middle;">lock</span> У вас нет прав на создание проектов.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

function old(string $k, array $p): string
{
    return htmlspecialchars($p[$k] ?? '');
}
?>

<?php if ($error_msg): ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">

        <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="card">
                <div class="card-title"><span class="material-icons">info</span>Основная информация</div>
                <div class="grid-2">
                    <div class="field col-full">
                        <label>Название игры *</label>
                        <input type="text" name="project-name" value="<?= old('project-name', $old_post) ?>"
                            required minlength="2" maxlength="64" placeholder="Название вашей игры">
                    </div>
                    <div class="field">
                        <label>Жанр *</label>
                        <select name="genre" required>
                            <option value="">— выберите —</option>
                            <?php foreach (['Платформер', 'RPG', 'Аркада', 'Стратегия', 'Головоломка', 'Экшн', 'Симулятор', 'Хоррор', 'Приключение', 'Визуальная новелла', 'Файтинг', 'Гонки', 'Другое'] as $g): ?>
                                <option<?= old('genre', $old_post) === $g ? ' selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Возрастной рейтинг</label>
                        <select name="age_rating">
                            <?php foreach (['0+', '6+', '12+', '16+', '18+'] as $a): ?>
                                <option<?= old('age_rating', $old_post) === $a ? ' selected' : '' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field col-full">
                        <label>Краткое описание</label>
                        <input type="text" name="short_description" maxlength="200"
                            value="<?= old('short_description', $old_post) ?>"
                            placeholder="Отображается в каталоге · до 200 символов">
                    </div>
                    <div class="field col-full">
                        <label>Полное описание *</label>
                        <textarea name="description" required minlength="20"
                            style="min-height:120px;"><?= old('description', $old_post) ?></textarea>
                    </div>
                    <div class="field"><label>Дата релиза</label>
                        <input type="date" name="release-date" value="<?= old('release-date', $old_post) ?: date('Y-m-d') ?>">
                    </div>
                    <div class="field"><label>Сайт проекта</label>
                        <input type="url" name="website" value="<?= old('website', $old_post) ?>" placeholder="https://...">
                    </div>
                    <div class="field"><label>Трейлер (YouTube)</label>
                        <input type="url" name="trailer" value="<?= old('trailer', $old_post) ?>" placeholder="https://youtube.com/...">
                    </div>
                    <div class="field"><label>Исполняемый файл</label>
                        <input type="text" name="game-exec" value="<?= old('game-exec', $old_post) ?>" placeholder="game.exe">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">devices</span>Платформы и языки</div>
                <div class="field">
                    <label>Платформы</label>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach (['Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Web'] as $pl): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--ts);cursor:pointer;">
                                <input type="checkbox" name="platform[]" value="<?= $pl ?>" style="accent-color:var(--p);"> <?= $pl ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field"><label>Языки интерфейса</label>
                    <input type="text" name="languages" value="<?= old('languages', $old_post) ?>" placeholder="Русский, English...">
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="card">
                <div class="card-title"><span class="material-icons">image</span>Обложка</div>
                <label for="cover_inp" style="display:block;border:2px dashed rgba(195,33,120,.3);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:all .2s;">
                    <span class="material-icons" style="font-size:32px;color:var(--p);display:block;margin-bottom:8px;">add_photo_alternate</span>
                    <span style="font-size:13px;color:var(--ts);display:block;">Выбрать обложку</span>
                    <span style="font-size:11px;color:var(--tm);display:block;margin-top:4px;">JPG, PNG, WEBP · до 5 МБ</span>
                </label>
                <input type="file" id="cover_inp" name="cover-art" accept="image/*" style="display:none;"
                    onchange="previewImg(this,'cover_prev','cover_wrap')">
                <div id="cover_wrap" style="display:none;margin-top:10px;">
                    <img id="cover_prev" src="" style="width:100%;border-radius:8px;object-fit:cover;max-height:160px;">
                </div>
            </div>

            <div class="card" style="background:rgba(255,170,0,.05);border-color:rgba(255,170,0,.2);">
                <div style="font-size:12px;color:var(--ts);line-height:1.7;">
                    💡 ZIP-архив с игрой загружается <strong style="color:#fff;">после создания</strong> на странице редактирования.
                </div>
            </div>

            <div class="card" style="background:rgba(195,33,120,.05);border-color:rgba(195,33,120,.2);">
                <div style="font-size:12px;color:var(--ts);line-height:1.6;margin-bottom:14px;">
                    После создания проект сохраняется как <strong style="color:#fff;">черновик</strong>.
                </div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                    <span class="material-icons">save</span>Создать черновик
                </button>
                <a href="/devs/projects" class="btn btn-g" style="width:100%;justify-content:center;margin-top:8px;">Отмена</a>
            </div>
        </div>
    </div>
</form>

<?php
$extra_js = '<script>
function previewImg(inp,imgId,wrapId){
    if(inp.files&&inp.files[0]){
        document.getElementById(imgId).src=URL.createObjectURL(inp.files[0]);
        document.getElementById(wrapId).style.display="block";
    }
}
</script>';
require_once(__DIR__ . '/includes/footer.php');
?>