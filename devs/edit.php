<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3.php');
require_once(__DIR__ . '/../swad/controllers/tg_bot.php');

$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) { header('Location: /devs/projects'); exit(); }

$db        = new Database();
$conn      = $db->connect();
$studio_id = (int)($_SESSION['studio_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND developer = ?");
$stmt->execute([$project_id, $studio_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
$screenshots = json_decode($game['screenshots'] ?? '[]', true) ?: [];

if (!$game) { header('Location: /devs/projects'); exit(); }

$tg_id    = (string)($_SESSION['USERDATA']['telegram_id'] ?? '');
$is_adm   = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
$chk      = $conn->prepare("SELECT role FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
$chk->execute([$tg_id, $studio_id]);
$is_owner = $chk->fetchColumn() === 'Владелец' || $is_adm;

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name         = trim($_POST['name']         ?? '');
        $genre        = trim($_POST['genre']        ?? '');
        $short_desc   = trim($_POST['short_description'] ?? '');
        $description  = trim($_POST['description']  ?? '');
        $platforms    = implode(',', $_POST['platform'] ?? []);
        $release_date = $_POST['release_date']      ?? $game['release_date'];
        $game_website = filter_var($_POST['website']    ?? '', FILTER_SANITIZE_URL);
        $trailer_url  = filter_var($_POST['trailer_url'] ?? '', FILTER_SANITIZE_URL);
        $game_exec    = trim($_POST['game_exec']    ?? '');
        $languages    = trim($_POST['languages']    ?? '');
        $age_rating   = $_POST['age_rating']        ?? '0+';
        $cover_path   = $game['path_to_cover'];
        $icon_path    = $game['icon_url'] ?? '';

        // Обложка
        if (!empty($_FILES['cover_art']['name']) && $_FILES['cover_art']['error'] === UPLOAD_ERR_OK) {
            try {
                $s3  = new S3Uploader();
                $ext = pathinfo($_FILES['cover_art']['name'], PATHINFO_EXTENSION);
                $org = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
                $key = $org . '/' . preg_replace('/[^a-z0-9]/i', '-', $name) . '/cover-' . uniqid() . '.' . $ext;
                $up  = $s3->uploadFile($_FILES['cover_art']['tmp_name'], $key);
                if ($up) $cover_path = $up;
            } catch (Exception $e) { error_log('Cover upload: ' . $e->getMessage()); }
        }

        // Иконка
        if (!empty($_FILES['icon_art']['name']) && $_FILES['icon_art']['error'] === UPLOAD_ERR_OK) {
            try {
                $s3  = new S3Uploader();
                $ext = pathinfo($_FILES['icon_art']['name'], PATHINFO_EXTENSION);
                $org = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
                $key = $org . '/' . preg_replace('/[^a-z0-9]/i', '-', $name) . '/icon-' . uniqid() . '.' . $ext;
                $up  = $s3->uploadFile($_FILES['icon_art']['tmp_name'], $key);
                if ($up) $icon_path = $up;
            } catch (Exception $e) { error_log('Icon upload: ' . $e->getMessage()); }
        }

        try {
            $conn->prepare("
                UPDATE games SET
                    name              = :name,
                    genre             = :genre,
                    short_description = :short,
                    description       = :desc,
                    platforms         = :platforms,
                    release_date      = :release,
                    path_to_cover     = :cover,
                    icon_url          = :icon,
                    game_website      = :website,
                    trailer_url       = :trailer,
                    game_exec         = :exec,
                    languages         = :languages,
                    age_rating        = :age,
                    updated_at        = NOW()
                WHERE id = :id AND developer = :dev
            ")->execute([
                'name'      => $name,      'genre'    => $genre,
                'short'     => $short_desc,'desc'     => $description,
                'platforms' => $platforms, 'release'  => $release_date,
                'cover'     => $cover_path,'icon'     => $icon_path,
                'website'   => $game_website,'trailer' => $trailer_url,
                'exec'      => $game_exec, 'languages'=> $languages,
                'age'       => $age_rating,'id'       => $project_id,
                'dev'       => $studio_id,
            ]);
            header('Location: /devs/edit?id=' . $project_id . '&saved=1');
            exit();
        } catch (PDOException $e) {
            $error_msg = 'Ошибка сохранения: ' . $e->getMessage();
        }

    } elseif ($action === 'moderation') {
        $conn->prepare("UPDATE games SET moderation_status = 'pending', updated_at = NOW() WHERE id = ? AND developer = ?")
             ->execute([$project_id, $studio_id]);
        send_group_message(-1002916906978, '🆕 <b>Для экспертов: Новый проект требует прохождения модерации</b>', true, 'https://dustore.ru/devs/experts');
        header('Location: /devs/edit?id=' . $project_id . '&moderated=1');
        exit();

    } elseif ($action === 'resubmit_moderation') {
        if ($is_owner) {
            $conn->prepare("DELETE FROM moderation_reviews WHERE game_id = ?")->execute([$project_id]);
            $conn->prepare("UPDATE games SET moderation_status = 'pending', updated_at = NOW() WHERE id = ? AND developer = ?")
                 ->execute([$project_id, $studio_id]);
            send_group_message(-1002916906978, '🔄 <b>Проект отправлен на повторную модерацию</b>', true, 'https://dustore.ru/devs/experts');
            header('Location: /devs/edit?id=' . $project_id . '&moderated=1');
            exit();
        }
        $error_msg = 'Недостаточно прав.';

    } elseif ($action === 'publish') {
        $chk2 = $conn->prepare("SELECT moderation_status FROM games WHERE id = ? AND developer = ?");
        $chk2->execute([$project_id, $studio_id]);
        if ($chk2->fetchColumn() === 'approved') {
            $conn->prepare("UPDATE games SET status = 'published', updated_at = NOW() WHERE id = ? AND developer = ?")
                 ->execute([$project_id, $studio_id]);
            header('Location: /devs/edit?id=' . $project_id . '&published=1');
            exit();
        }
        $error_msg = 'Игра ещё не прошла модерацию.';

    } elseif ($action === 'save_announce') {
        $ann_date    = $_POST['announce_date'] ?? null;
        $ann_tbd     = isset($_POST['announce_tbd'])     ? 1 : 0;
        $ann_enabled = isset($_POST['announce_enabled']) ? 1 : 0;
        if ($ann_tbd)      $ann_date = null;
        if (!$ann_enabled) { $ann_date = null; $ann_tbd = 0; }
        $conn->prepare("
            UPDATE games SET announce_enabled=:ae, announce_date=:ad, announce_tbd=:at, updated_at=NOW()
            WHERE id=:id AND developer=:dev
        ")->execute(['ae'=>$ann_enabled,'ad'=>$ann_date?:null,'at'=>$ann_tbd,'id'=>$project_id,'dev'=>$studio_id]);
        header('Location: /devs/edit?id=' . $project_id . '&announced=1');
        exit();

    } elseif ($action === 'delete') {
        if ($is_owner) {
            $conn->prepare("DELETE FROM games WHERE id = ? AND developer = ?")->execute([$project_id, $studio_id]);
            header('Location: /devs/projects?deleted=1');
            exit();
        }
        $error_msg = 'Недостаточно прав для удаления проекта.';
    }
}

$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$project_id]);
$game        = $stmt->fetch(PDO::FETCH_ASSOC);
$screenshots = json_decode($game['screenshots'] ?? '[]', true) ?: [];

$success_msg = '';
if (isset($_GET['saved']))     $success_msg = 'Изменения сохранены!';
if (isset($_GET['created']))   $success_msg = 'Проект создан! Теперь загрузите файл игры.';
if (isset($_GET['moderated'])) $success_msg = 'Проект отправлен на модерацию!';
if (isset($_GET['published'])) $success_msg = 'Игра опубликована! Теперь она видна всем игрокам.';
if (isset($_GET['announced'])) $success_msg = 'Настройки анонса сохранены!';

$page_title = 'Редактирование: ' . ($game['name'] ?? '');
$active_nav = 'projects';
require_once(__DIR__ . '/includes/header.php');

$status_labels   = ['published'=>['badge-pub','Опубликован'],'draft'=>['badge-draft','Черновик'],'closed'=>['badge-err','Закрыт']];
[$status_cls, $status_lbl] = $status_labels[$game['status'] ?? 'draft'] ?? ['badge-draft','Черновик'];
$platforms_saved = array_filter(array_map('trim', explode(',', $game['platforms'] ?? '')));
$has_zip         = !empty($game['game_zip_url']);
$is_chunked      = $has_zip && str_ends_with((string)$game['game_zip_url'], 'manifest.json');
$only_android    = $platforms_saved === ['Android'];

$wl_count = $conn->prepare("SELECT COUNT(*) FROM wishlists WHERE game_id = ?");
$wl_count->execute([$project_id]);
$wl_total = (int)$wl_count->fetchColumn();

$mod_data = ['total'=>0,'positive'=>0,'negative'=>0,'need'=>0,'experts_total'=>0];
if (in_array($game['moderation_status'] ?? '', ['pending','rejected'])) {
    $experts_total = (int)$conn->query("SELECT COUNT(*) FROM experts WHERE status='approved'")->fetchColumn();
    $stmt_mod = $conn->prepare("SELECT COUNT(*) AS total, SUM(score>51) AS positive, SUM(score<=51) AS negative FROM moderation_reviews WHERE game_id=?");
    $stmt_mod->execute([$project_id]);
    $mv = $stmt_mod->fetch(PDO::FETCH_ASSOC);
    $mod_data = [
        'total'        => (int)($mv['total']    ?? 0),
        'positive'     => (int)($mv['positive'] ?? 0),
        'negative'     => (int)($mv['negative'] ?? 0),
        'need'         => max(1, (int)ceil($experts_total * 0.51)),
        'experts_total'=> $experts_total,
    ];
}

$lang_options = ['Русский','English','Deutsch','Français','Español','中文','日本語','Português','한국어','Italiano'];
$langs_saved  = array_map('trim', explode(',', $game['languages'] ?? ''));

function ev(array $a, string $k): string { return htmlspecialchars($a[$k] ?? ''); }
?>

<?php if ($success_msg): ?><div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg):   ?><div class="alert alert-err"><?= htmlspecialchars($error_msg)   ?></div><?php endif; ?>

<!-- ═══ МОДАЛКА ПАМЯТКА ═══ -->
<div id="moderation-modal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#131720;border:1px solid #2a3347;border-radius:16px;padding:32px;
                max-width:520px;width:90%;max-height:90vh;overflow-y:auto;position:relative;">
        <div style="font-size:18px;font-weight:700;margin-bottom:6px;">📋 Чеклист перед модерацией</div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:20px;">
            Убедитесь что всё заполнено — эксперты оценивают по этим критериям
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px;">
            <?php
            $checks = [
                ['icon'=>'title',          'label'=>'Название игры',        'hint'=>'Чёткое, без спецсимволов'],
                ['icon'=>'description',    'label'=>'Описание',             'hint'=>'Минимум 100 символов, без воды'],
                ['icon'=>'image',          'label'=>'Обложка',              'hint'=>'Качественное изображение, 460×215 рекомендуется'],
                ['icon'=>'face',           'label'=>'Иконка',               'hint'=>'Квадратная, минимум 256×256'],
                ['icon'=>'photo_library',  'label'=>'Скриншоты',            'hint'=>'Минимум 3 скриншота из реального геймплея'],
                ['icon'=>'folder_zip',     'label'=>'Файл игры',            'hint'=>'ZIP или APK загружен и корректно запускается'],
                ['icon'=>'movie',          'label'=>'Трейлер',              'hint'=>'Желательно, но не обязательно'],
                ['icon'=>'devices',        'label'=>'Платформы и языки',    'hint'=>'Указаны все поддерживаемые платформы'],
                ['icon'=>'calendar_today', 'label'=>'Дата релиза',          'hint'=>'Указана реальная или планируемая дата'],
                ['icon'=>'child_care',     'label'=>'Возрастной рейтинг',   'hint'=>'Соответствует содержанию игры'],
            ];
            foreach ($checks as $c):
                $required = !in_array($c['label'], ['Трейлер']);
            ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;
                        background:rgba(255,255,255,.03);border-radius:8px;border:1px solid #1e2a3a;">
                <span class="material-icons" style="font-size:18px;color:<?= $required ? 'var(--p)' : 'var(--tm)' ?>;flex-shrink:0;margin-top:1px;"><?= $c['icon'] ?></span>
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--ts);">
                        <?= $c['label'] ?>
                        <?php if ($required): ?>
                        <span style="font-size:10px;color:var(--err);margin-left:4px;">обязательно</span>
                        <?php else: ?>
                        <span style="font-size:10px;color:var(--tm);margin-left:4px;">рекомендуется</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:var(--tm);margin-top:2px;"><?= $c['hint'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.2);
                    border-radius:8px;padding:12px 14px;font-size:12px;color:#fbbf24;margin-bottom:20px;line-height:1.6;">
            ⚠️ После отправки на модерацию редактирование будет недоступно до завершения голосования экспертов.
            При повторной отправке все предыдущие голоса сбрасываются.
        </div>

        <div style="display:flex;gap:10px;">
            <button type="button" onclick="closeModerationModal()"
                    class="btn btn-g" style="flex:1;justify-content:center;">
                Отмена
            </button>
            <button type="button" id="modal-confirm-btn"
                    class="btn btn-p" style="flex:1;justify-content:center;">
                <span class="material-icons" style="font-size:15px;">send</span>
                <span id="modal-confirm-label">Отправить</span>
            </button>
        </div>
    </div>
</div>

<?php
$mod_status = $game['moderation_status'] ?? 'draft';
if (in_array($mod_status, ['pending','rejected'])):
    $pct     = $mod_data['need'] > 0 ? min(100, round($mod_data['positive'] / $mod_data['need'] * 100)) : 0;
    $is_dead = $mod_status === 'rejected';
?>
<div class="card" style="margin-bottom:16px;border-color:<?= $is_dead ? 'rgba(248,113,113,.3)' : 'rgba(251,191,36,.25)' ?>;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
        <div>
            <div style="font-size:13px;font-weight:700;color:<?= $is_dead ? 'var(--err)' : '#fbbf24' ?>;">
                <?= $is_dead ? '❌ Модерация: отклонено' : '⏳ Модерация: идёт голосование' ?>
            </div>
            <div style="font-size:11px;color:var(--tm);margin-top:2px;">
                <?= $mod_data['positive'] ?> за · <?= $mod_data['negative'] ?> против ·
                нужно <?= $mod_data['need'] ?> голосов «за» из <?= $mod_data['experts_total'] ?> экспертов
            </div>
        </div>
        <?php if ($is_dead && $is_owner): ?>
        <button type="button" class="btn btn-p" style="padding:8px 18px;font-size:12px;"
                onclick="openModerationModal('resubmit')">
            <span class="material-icons" style="font-size:15px;">refresh</span>Отправить повторно
        </button>
        <?php endif; ?>
    </div>
    <div style="height:8px;background:var(--elev);border-radius:4px;overflow:hidden;display:flex;">
        <?php if ($mod_data['positive'] > 0): ?>
        <div style="width:<?= round($mod_data['positive'] / max($mod_data['experts_total'],1) * 100) ?>%;
                    background:var(--ok);border-radius:4px 0 0 4px;transition:width .6s;"></div>
        <?php endif; ?>
        <?php if ($mod_data['negative'] > 0): ?>
        <div style="width:<?= round($mod_data['negative'] / max($mod_data['experts_total'],1) * 100) ?>%;
                    background:var(--err);border-radius:0 4px 4px 0;transition:width .6s;"></div>
        <?php endif; ?>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:10px;color:var(--tm);">
        <span>👍 <?= $mod_data['positive'] ?> за</span>
        <span><?= $pct ?>% от порога</span>
        <span>👎 <?= $mod_data['negative'] ?> против</span>
    </div>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
    <div style="font-size:20px;font-weight:700;"><?= ev($game,'name') ?></div>
    <span class="badge <?= $status_cls ?>"><?= $status_lbl ?></span>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/g/<?= $project_id ?>" target="_blank" class="btn btn-g" style="padding:6px 14px;font-size:12px;">
            <span class="material-icons" style="font-size:15px;">open_in_new</span>Открыть
        </a>
        <?php if ($game['status'] === 'draft' && $game['moderation_status'] === 'pending'): ?>
        <span class="btn btn-g" style="padding:6px 14px;font-size:12px;opacity:.6;cursor:default;">
            <span class="material-icons" style="font-size:15px;">hourglass_top</span>На модерации
        </span>
        <?php elseif ($game['status'] === 'draft' && $game['moderation_status'] === 'approved'): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="publish">
            <button type="submit" class="btn btn-p" style="padding:6px 14px;font-size:12px;background:linear-gradient(135deg,#00c471,#00a05a);">
                <span class="material-icons" style="font-size:15px;">rocket_launch</span>Опубликовать
            </button>
        </form>
        <?php elseif ($game['status'] === 'draft' && ($game['moderation_status'] ?? 'draft') === 'draft' && $is_owner): ?>
        <button type="button" class="btn btn-p" style="padding:6px 14px;font-size:12px;"
                onclick="openModerationModal('first')">
            <span class="material-icons" style="font-size:15px;">send</span>На модерацию
        </button>
        <?php elseif ($game['status'] === 'published'): ?>
        <span class="btn btn-g" style="padding:6px 14px;font-size:12px;opacity:.6;cursor:default;">
            <span class="material-icons" style="font-size:15px;">check_circle</span>Опубликована
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Скрытые формы для модерации -->
<form method="POST" id="form-moderation" style="display:none;">
    <input type="hidden" name="action" value="moderation">
</form>
<form method="POST" id="form-resubmit" style="display:none;">
    <input type="hidden" name="action" value="resubmit_moderation">
</form>

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">

    <form method="POST" enctype="multipart/form-data" id="save-form">
        <input type="hidden" name="action" value="save">
        <div style="display:flex;flex-direction:column;gap:14px;">

            <div class="card">
                <div class="card-title"><span class="material-icons">info</span>Основная информация</div>
                <div class="grid-2">
                    <div class="field col-full">
                        <label>Название *</label>
                        <input type="text" name="name" value="<?= ev($game,'name') ?>" required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Жанр</label>
                        <select name="genre">
                            <?php foreach (['Платформер','RPG','Аркада','Стратегия','Головоломка','Экшн','Симулятор','Хоррор','Приключение','Визуальная новелла','Файтинг','Гонки','Другое'] as $g): ?>
                            <option<?= $game['genre'] === $g ? ' selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Возрастной рейтинг</label>
                        <select name="age_rating">
                            <?php foreach (['0+','6+','12+','16+','18+'] as $a): ?>
                            <option<?= $game['age_rating'] === $a ? ' selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field col-full">
                        <label>Краткое описание</label>
                        <input type="text" name="short_description" value="<?= ev($game,'short_description') ?>" maxlength="200">
                    </div>
                    <div class="field col-full">
                        <label>Описание</label>
                        <textarea name="description" style="min-height:140px;"><?= ev($game,'description') ?></textarea>
                    </div>
                    <div class="field">
                        <label>Дата релиза</label>
                        <input type="date" name="release_date" value="<?= ev($game,'release_date') ?>">
                    </div>
                    <div class="field">
                        <label>Сайт проекта</label>
                        <input type="url" name="website" value="<?= ev($game,'game_website') ?>" placeholder="https://...">
                    </div>
                    <div class="field">
                        <label>Исполняемый файл</label>
                        <input type="text" name="game_exec" value="<?= ev($game,'game_exec') ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">devices</span>Платформы и языки</div>
                <div class="field">
                    <label>Платформы</label>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach (['Windows','macOS','Linux','Android','iOS','Web'] as $pl): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--ts);cursor:pointer;">
                            <input type="checkbox" name="platform[]" value="<?= $pl ?>"
                                   style="accent-color:var(--p);"
                                <?= in_array($pl, $platforms_saved) ? 'checked' : '' ?>> <?= $pl ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field">
                    <label>Языки интерфейса</label>
                    <div style="position:relative;max-width:50%;">
                        <button type="button" id="lang-trigger"
                                style="width:100%;background:var(--bg);border:1px solid #2a3347;border-radius:8px;
                                       padding:8px 14px;cursor:pointer;font-size:13px;color:var(--ts);
                                       display:flex;align-items:center;justify-content:space-between;
                                       text-align:left;min-height:38px;">
                            <span id="lang-label"><?= !empty(array_filter($langs_saved)) ? htmlspecialchars(implode(', ', array_filter($langs_saved))) : 'Выберите языки...' ?></span>
                            <span id="lang-arrow" style="font-size:10px;transition:transform .2s;flex-shrink:0;margin-left:8px;">▾</span>
                        </button>
                        <div id="lang-menu"
                             style="display:none;position:absolute;z-index:999;top:calc(100% + 4px);left:0;right:0;
                                    background:#1a2030;border:1px solid #2a3347;border-radius:10px;
                                    padding:4px;box-shadow:0 8px 24px rgba(0,0,0,.6);">
                            <?php foreach ($lang_options as $lang): ?>
                            <label style="display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:6px;
                                          cursor:pointer;font-size:13px;color:var(--ts);transition:background .15s;">
                                <input type="checkbox" name="languages_list[]" value="<?= $lang ?>"
                                       style="accent-color:var(--p);width:14px;height:14px;flex-shrink:0;"
                                    <?= in_array($lang, $langs_saved) ? 'checked' : '' ?>>
                                <?= $lang ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" name="languages" id="languages-hidden" value="<?= ev($game,'languages') ?>">
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">folder_zip</span>Файл игры</div>
                <?php if ($has_zip): ?>
                <div class="alert alert-ok" style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                    <span class="material-icons" style="font-size:18px;">check_circle</span>
                    <div>
                        <?php if ($is_chunked): ?>
                            Загружен чанками <?= !empty($game['game_zip_size']) ? '· ' . round($game['game_zip_size']/1048576,1) . ' МБ' : '' ?>
                            <span style="font-size:10px;background:rgba(0,214,143,.15);padding:1px 8px;border-radius:4px;margin-left:6px;">manifest.json</span>
                        <?php else: ?>
                            ZIP загружен <?= !empty($game['game_zip_size']) ? '· ' . round($game['game_zip_size']/1048576,1) . ' МБ' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div id="upload-mode-hint" style="display:none;margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:12px;"></div>
                <div id="zip-drop"
                     style="border:2px dashed rgba(195,33,120,.3);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:border-color .2s;"
                     onmouseover="this.style.borderColor='var(--p)'" onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                    <span id="drop-icon" class="material-icons" style="font-size:32px;color:var(--p);display:block;margin-bottom:8px;"><?= $only_android ? 'android' : 'upload_file' ?></span>
                    <span id="drop-label" style="font-size:13px;color:var(--ts);display:block;">
                        <?= $has_zip ? ($only_android ? 'Заменить APK' : 'Заменить файл игры') : ($only_android ? 'Загрузить APK' : 'Загрузить ZIP') ?>
                    </span>
                    <span id="drop-hint" style="font-size:11px;color:var(--tm);display:block;margin-top:4px;">
                        <?= $only_android ? 'Android APK · до 4 ГБ' : 'До 500 МБ — прямая загрузка · Больше — чанки' ?>
                    </span>
                </div>
                <input type="file" id="zip-input" accept="<?= $only_android ? '.apk' : '.zip' ?>" style="display:none;" data-android="<?= $only_android ? '1' : '0' ?>">
                <div id="zip-progress" style="display:none;margin-top:14px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span id="zip-status" style="font-size:12px;color:var(--ts);">Подготовка...</span>
                        <span id="zip-pct" style="font-size:12px;font-weight:600;color:var(--tm);">0%</span>
                    </div>
                    <div style="height:8px;background:var(--elev);border-radius:4px;overflow:hidden;">
                        <div id="zip-bar" style="height:100%;background:var(--p);border-radius:4px;width:0%;transition:width .3s;"></div>
                    </div>
                    <div id="zip-detail" style="font-size:11px;color:var(--tm);margin-top:6px;"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-title"><span class="material-icons">movie</span>Трейлер</div>
                <div class="field">
                    <label>Ссылка на трейлер</label>
                    <input type="url" name="trailer_url" id="trailer-url-input"
                           value="<?= ev($game,'trailer_url') ?>" placeholder="https://...">
                    <div style="font-size:11px;color:var(--tm);margin-top:4px;">
                        Видео на YouTube могут не загружаться у пользователей без VPN
                    </div>
                </div>
                <div id="trailer-preview" style="<?= empty($game['trailer_url']) ? 'display:none;' : '' ?>margin-top:10px;">
                    <iframe id="trailer-iframe" src="<?= ev($game,'trailer_url') ?>"
                            style="width:100%;aspect-ratio:16/9;border-radius:10px;border:1px solid var(--elev);"
                            allowfullscreen allow="autoplay; encrypted-media; fullscreen"></iframe>
                </div>
            </div>

            <div class="card" id="screenshots-card">
                <div class="card-title" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <span class="material-icons">photo_library</span>Скриншоты
                    </span>
                    <span id="scr-count-lbl" style="font-size:11px;color:var(--tm);"><?= count($screenshots) ?> / 10</span>
                </div>
                <div id="scr-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:12px;">
                    <?php foreach ($screenshots as $scr): ?>
                    <div class="scr-item" data-url="<?= htmlspecialchars($scr['path'] ?? '') ?>"
                         style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:16/9;background:var(--elev);">
                        <img src="<?= htmlspecialchars($scr['path'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
                        <button type="button" onclick="deleteScreenshot(this)"
                                style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.7);
                                       border:none;border-radius:6px;color:#fff;cursor:pointer;
                                       width:26px;height:26px;font-size:15px;line-height:1;
                                       display:flex;align-items:center;justify-content:center;">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="scr-drop"
                     style="border:2px dashed rgba(195,33,120,.3);border-radius:12px;padding:24px;
                            text-align:center;cursor:pointer;transition:border-color .2s;"
                     onmouseover="this.style.borderColor='var(--p)'" onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                    <span class="material-icons" style="font-size:28px;color:var(--p);display:block;margin-bottom:6px;">add_photo_alternate</span>
                    <span style="font-size:13px;color:var(--ts);">Перетащите сюда скриншоты</span>
                    <span style="font-size:11px;color:var(--tm);display:block;margin-top:4px;">jpg, png, webp, gif · до 15 МБ · максимум 10 штук</span>
                </div>
                <input type="file" id="scr-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;">
                <div id="scr-progress" style="display:none;margin-top:10px;">
                    <div style="height:6px;background:var(--elev);border-radius:3px;overflow:hidden;">
                        <div id="scr-bar" style="height:100%;background:var(--p);width:0%;transition:width .3s;border-radius:3px;"></div>
                    </div>
                    <div id="scr-status" style="font-size:11px;color:var(--tm);margin-top:4px;"></div>
                </div>
            </div>

            <div class="card">
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                    <span class="material-icons">save</span>Сохранить изменения
                </button>
                <a href="/devs/projects" class="btn btn-g" style="width:100%;justify-content:center;margin-top:8px;">
                    ← Назад к проектам
                </a>
            </div>

        </div>
    </form>

    <div style="display:flex;flex-direction:column;gap:14px;">

        <!-- Обложка + Иконка -->
        <div class="card">
            <div class="card-title"><span class="material-icons">image</span>Медиа</div>

            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--tm);margin-bottom:8px;">
                Обложка <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--tm);">· 460×215</span>
            </div>
            <?php if (!empty($game['path_to_cover'])): ?>
            <img src="<?= ev($game,'path_to_cover') ?>" id="current-cover"
                 style="width:100%;border-radius:8px;margin-bottom:8px;object-fit:cover;max-height:160px;">
            <?php endif; ?>
            <label for="cover_art"
                   style="display:block;border:2px dashed rgba(195,33,120,.3);border-radius:10px;padding:16px;
                          text-align:center;cursor:pointer;transition:border-color .2s;margin-bottom:16px;"
                   onmouseover="this.style.borderColor='var(--p)'" onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                <span class="material-icons" style="font-size:22px;color:var(--p);display:block;margin-bottom:4px;">add_photo_alternate</span>
                <span style="font-size:12px;color:var(--ts);display:block;"><?= !empty($game['path_to_cover']) ? 'Заменить обложку' : 'Загрузить обложку' ?></span>
            </label>
            <input type="file" id="cover_art" name="cover_art" accept="image/*" style="display:none;" form="save-form">
            <div id="cover_wrap" style="display:none;margin-bottom:16px;">
                <img id="cover_prev" src="" style="width:100%;border-radius:8px;object-fit:cover;max-height:120px;">
            </div>

            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--tm);margin-bottom:8px;">
                Иконка <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--tm);">· 512×512</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div id="icon-preview-wrap" style="<?= empty($game['icon_url']) ? 'display:none;' : '' ?>flex-shrink:0;">
                    <img id="current-icon" src="<?= ev($game,'icon_url') ?>"
                         style="width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid #2a3347;">
                </div>
                <label for="icon_art"
                       style="flex:1;display:block;border:2px dashed rgba(195,33,120,.3);border-radius:10px;
                              padding:14px;text-align:center;cursor:pointer;transition:border-color .2s;"
                       onmouseover="this.style.borderColor='var(--p)'" onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                    <span class="material-icons" style="font-size:20px;color:var(--p);display:block;margin-bottom:4px;">add_photo_alternate</span>
                    <span style="font-size:11px;color:var(--ts);display:block;" id="icon-label">
                        <?= !empty($game['icon_url']) ? 'Заменить иконку' : 'Загрузить иконку' ?>
                    </span>
                </label>
            </div>
            <input type="file" id="icon_art" name="icon_art" accept="image/*" style="display:none;" form="save-form">
        </div>

        <form method="POST" id="announce-form">
            <input type="hidden" name="action" value="save_announce">
            <div class="card" style="border-color:rgba(255,170,0,.2);">
                <div class="card-title">
                    <span class="material-icons" style="color:#ffaa00;">campaign</span>Анонс проекта
                </div>
                <p style="font-size:11px;color:var(--tm);margin-bottom:12px;line-height:1.5;">
                    Игроки смогут добавить игру в вишлист до её выхода и получат уведомление при публикации.
                </p>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:14px;">
                    <input type="checkbox" name="announce_enabled" id="ann-enabled"
                           style="accent-color:var(--p);width:16px;height:16px;"
                           <?= !empty($game['announce_enabled']) ? 'checked' : '' ?>>
                    <span style="font-size:13px;font-weight:600;">Включить анонс</span>
                </label>
                <div id="ann-fields" style="<?= empty($game['announce_enabled']) ? 'display:none;' : '' ?>">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px;">
                        <input type="checkbox" name="announce_tbd" id="ann-tbd"
                               style="accent-color:var(--p);width:16px;height:16px;"
                               <?= !empty($game['announce_tbd']) ? 'checked' : '' ?>>
                        <span style="font-size:12px;color:var(--ts);">Дата выхода неизвестна (TBD)</span>
                    </label>
                    <div class="field" id="ann-date-wrap" style="<?= !empty($game['announce_tbd']) ? 'display:none;' : '' ?>">
                        <label>Планируемая дата выхода</label>
                        <input type="date" name="announce_date" value="<?= htmlspecialchars($game['announce_date'] ?? '') ?>">
                    </div>
                    <?php if ($wl_total > 0): ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:10px;padding:8px 12px;
                                background:rgba(255,170,0,.07);border-radius:8px;border:1px solid rgba(255,170,0,.15);">
                        <span class="material-icons" style="font-size:16px;color:#ffaa00;">favorite</span>
                        <span style="font-size:12px;color:var(--ts);">
                            <strong style="color:#ffaa00;"><?= $wl_total ?></strong>
                            <?= $wl_total === 1 ? 'игрок' : ($wl_total < 5 ? 'игрока' : 'игроков') ?> добавили в вишлист
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-g" style="width:100%;justify-content:center;margin-top:12px;font-size:12px;">
                    <span class="material-icons" style="font-size:15px;">save</span>Сохранить анонс
                </button>
            </div>
        </form>

    </div>
</div>

<?php if ($is_owner): ?>
<form method="POST" id="delete-form"
      onsubmit="return confirm('Удалить проект «<?= htmlspecialchars(addslashes($game['name'])) ?>»?\n\nЭто действие нельзя отменить.')">
    <input type="hidden" name="action" value="delete">
    <div class="card" style="border-color:rgba(255,61,113,.25);margin-top:6px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--err);">Зона опасности</div>
                <div style="font-size:12px;color:var(--tm);margin-top:2px;">Удаление проекта необратимо</div>
            </div>
            <button type="submit" class="btn btn-d" style="padding:8px 20px;">
                <span class="material-icons">delete_forever</span>Удалить проект
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<?php
$extra_js = <<<JS
<script>
const LARGE       = 500 * 1024 * 1024;
const SMALL_CHUNK = 5   * 1024 * 1024;
const LARGE_CHUNK = 50  * 1024 * 1024;
const PID = {$project_id};

// ── Модалка модерации ─────────────────────────────────────────────────────
var _modalAction = 'first';

function openModerationModal(action) {
    _modalAction = action;
    var lbl = action === 'resubmit' ? 'Отправить повторно' : 'Отправить на модерацию';
    document.getElementById('modal-confirm-label').textContent = lbl;
    var modal = document.getElementById('moderation-modal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModerationModal() {
    document.getElementById('moderation-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('modal-confirm-btn').addEventListener('click', function () {
    closeModerationModal();
    if (_modalAction === 'resubmit') {
        document.getElementById('form-resubmit').submit();
    } else {
        document.getElementById('form-moderation').submit();
    }
});

document.getElementById('moderation-modal').addEventListener('click', function (e) {
    if (e.target === this) closeModerationModal();
});

// ── Анонс ─────────────────────────────────────────────────────────────────
document.getElementById('ann-enabled').addEventListener('change', function () {
    document.getElementById('ann-fields').style.display = this.checked ? '' : 'none';
});
document.getElementById('ann-tbd').addEventListener('change', function () {
    document.getElementById('ann-date-wrap').style.display = this.checked ? 'none' : '';
});

// ── Платформы → зона загрузки ─────────────────────────────────────────────
function syncUploadZone() {
    var checkboxes  = document.querySelectorAll('input[name="platform[]"]');
    var selected    = Array.from(checkboxes).filter(function(c){return c.checked;}).map(function(c){return c.value;});
    var onlyAndroid = selected.length === 1 && selected[0] === 'Android';
    var hasAndroid  = selected.includes('Android');
    var input = document.getElementById('zip-input');
    var icon  = document.getElementById('drop-icon');
    var label = document.getElementById('drop-label');
    var hint  = document.getElementById('drop-hint');
    if (onlyAndroid) {
        input.accept = '.apk'; input.dataset.android = '1';
        icon.textContent = 'android'; icon.style.color = '#a4c639';
        label.textContent = 'Загрузить APK-файл';
        hint.textContent  = 'Android APK · до 4 ГБ';
    } else if (hasAndroid) {
        input.accept = '.zip,.apk'; input.dataset.android = '0';
        icon.textContent = 'upload_file'; icon.style.color = 'var(--p)';
        label.textContent = 'Загрузить ZIP или APK';
        hint.textContent  = 'ZIP или APK · до 500 МБ прямая загрузка';
    } else {
        input.accept = '.zip'; input.dataset.android = '0';
        icon.textContent = 'upload_file'; icon.style.color = 'var(--p)';
        label.textContent = 'Загрузить ZIP-архив';
        hint.textContent  = 'До 500 МБ — прямая загрузка · Больше — чанки';
    }
}
document.querySelectorAll('input[name="platform[]"]').forEach(function(cb){
    cb.addEventListener('change', syncUploadZone);
});

// ── ZIP / APK upload ──────────────────────────────────────────────────────
document.getElementById('zip-drop').addEventListener('click', function(){
    document.getElementById('zip-input').click();
});

document.getElementById('zip-input').addEventListener('change', function () {
    var file  = this.files[0];
    if (!file) return;
    var isApk = file.name.toLowerCase().endsWith('.apk');
    var big   = !isApk && file.size >= LARGE;
    var hint  = document.getElementById('upload-mode-hint');
    hint.style.display = 'block';
    if (isApk) {
        hint.style.cssText = 'display:block;margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:12px;background:rgba(164,198,57,.08);border:1px solid rgba(164,198,57,.2);color:#a4c639;';
        hint.textContent = '🤖 APK · ' + (file.size/1048576).toFixed(1) + ' МБ';
    } else if (big) {
        hint.style.cssText = 'display:block;margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:12px;background:rgba(195,33,120,.08);border:1px solid rgba(195,33,120,.2);color:var(--pl);';
        hint.textContent = '📦 ' + (file.size/1048576).toFixed(1) + ' МБ — чанки';
    } else {
        hint.style.cssText = 'display:block;margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:12px;background:rgba(0,214,143,.06);border:1px solid rgba(0,214,143,.15);color:var(--ok);';
        hint.textContent = '⚡ ZIP · ' + (file.size/1048576).toFixed(1) + ' МБ';
    }
    uploadFile(file, isApk);
});

async function uploadFile(file, isApk) {
    isApk = isApk || false;
    var big    = !isApk && file.size >= LARGE;
    var chunk  = big ? LARGE_CHUNK : SMALL_CHUNK;
    var total  = Math.ceil(file.size / chunk);
    var prog   = document.getElementById('zip-progress');
    var bar    = document.getElementById('zip-bar');
    var status = document.getElementById('zip-status');
    var pct    = document.getElementById('zip-pct');
    var detail = document.getElementById('zip-detail');
    prog.style.display   = 'block';
    bar.style.background = isApk ? '#a4c639' : 'var(--p)';
    for (var i = 0; i < total; i++) {
        var last = i === total - 1;
        if (last && total > 1) {
            status.textContent = big ? '⏳ Последний чанк + manifest...' : (isApk ? '⏳ Завершаем APK...' : '⏳ Финализируем...');
        } else {
            bar.style.width    = Math.round(i / total * 100) + '%';
            pct.textContent    = Math.round(i / total * 100) + '%';
            status.textContent = (isApk ? '🤖 APK · часть ' : 'Чанк ') + (i+1) + ' из ' + total;
        }
        var fd = new FormData();
        fd.append('chunk',        file.slice(i * chunk, (i+1) * chunk));
        fd.append('chunk_index',  i);
        fd.append('total_chunks', total);
        fd.append('file_name',    file.name);
        fd.append('file_size',    file.size);
        fd.append('project_id',   PID);
        if (isApk) fd.append('file_type', 'apk');
        var data;
        try {
            var res  = await fetch('/devs/upload_chunk.php', {method:'POST', body:fd, credentials:'include'});
            var text = await res.text();
            data = JSON.parse(text);
        } catch (e) {
            setErr('Сервер вернул не-JSON: ' + String(e).substring(0, 200));
            return;
        }
        if (!data.success) { setErr(data.message || 'Ошибка сервера'); return; }
        if (data.done) {
            bar.style.width = '100%'; pct.textContent = '100%';
            bar.style.background = 'var(--ok)'; status.style.color = 'var(--ok)';
            status.textContent = isApk ? '✓ APK загружен · ' + data.size_mb + ' МБ'
                : (data.mode === 'chunked' ? '✓ ' + data.chunk_count + ' чанков · ' + data.size_mb + ' МБ' : '✓ ZIP загружен · ' + data.size_mb + ' МБ');
            if (data.mode === 'chunked') detail.textContent = 'manifest.json создан на S3';
            setTimeout(function(){ location.reload(); }, 1500);
            return;
        }
        bar.style.width = Math.round((i+1) / total * 100) + '%';
        pct.textContent = Math.round((i+1) / total * 100) + '%';
        if (data.sha256) detail.textContent = 'SHA256: ' + data.sha256.slice(0,16) + '...';
    }
}

function setErr(msg) {
    document.getElementById('zip-bar').style.background = 'var(--err)';
    var s = document.getElementById('zip-status');
    s.textContent = '✗ ' + msg; s.style.color = 'var(--err)';
}

// ── Cover preview ─────────────────────────────────────────────────────────
document.getElementById('cover_art').addEventListener('change', function () {
    if (!this.files[0]) return;
    var cur = document.getElementById('current-cover');
    if (cur) cur.style.display = 'none';
    document.getElementById('cover_prev').src = URL.createObjectURL(this.files[0]);
    document.getElementById('cover_wrap').style.display = 'block';
});

// ── Icon preview ──────────────────────────────────────────────────────────
document.getElementById('icon_art').addEventListener('change', function () {
    if (!this.files[0]) return;
    var url  = URL.createObjectURL(this.files[0]);
    var wrap = document.getElementById('icon-preview-wrap');
    var img  = document.getElementById('current-icon');
    img.src = url;
    wrap.style.display = '';
    document.getElementById('icon-label').textContent = 'Заменить иконку';
});

// ── Трейлер preview ───────────────────────────────────────────────────────
document.getElementById('trailer-url-input').addEventListener('input', function () {
    var val = this.value.trim();
    var wrap = document.getElementById('trailer-preview');
    var iframe = document.getElementById('trailer-iframe');
    if (val.includes('embed') || val.includes('player.vimeo')) {
        iframe.src = val; wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
});

// ── Скриншоты ─────────────────────────────────────────────────────────────
var SCR_PID = {$project_id};

document.getElementById('scr-drop').addEventListener('click', function(){ document.getElementById('scr-input').click(); });
document.getElementById('scr-drop').addEventListener('dragover', function(e){ e.preventDefault(); e.currentTarget.style.borderColor = 'var(--p)'; });
document.getElementById('scr-drop').addEventListener('dragleave', function(e){ e.currentTarget.style.borderColor = 'rgba(195,33,120,.3)'; });
document.getElementById('scr-drop').addEventListener('drop', function(e){ e.preventDefault(); e.currentTarget.style.borderColor = 'rgba(195,33,120,.3)'; uploadScreenshots(e.dataTransfer.files); });
document.getElementById('scr-input').addEventListener('change', function(){ uploadScreenshots(this.files); this.value = ''; });

async function uploadScreenshots(files) {
    var bar    = document.getElementById('scr-bar');
    var status = document.getElementById('scr-status');
    var prog   = document.getElementById('scr-progress');
    prog.style.display = ''; bar.style.background = 'var(--p)';
    for (var i = 0; i < files.length; i++) {
        var f  = files[i];
        var fd = new FormData();
        fd.append('project_id', SCR_PID);
        fd.append('type', 'screenshot');
        fd.append('file', f);
        status.textContent = 'Загружаю ' + (i+1) + '/' + files.length + ': ' + f.name;
        bar.style.width = Math.round((i / files.length) * 100) + '%';
        try {
            var res  = await fetch('/devs/upload_media.php', {method:'POST', body:fd, credentials:'include'});
            var data = await res.json();
            if (data.success) { renderScreenshots(data.screenshots); }
            else {
                status.textContent = '✗ ' + (data.message || 'Ошибка');
                status.style.color = 'var(--err)';
                await new Promise(function(r){ setTimeout(r, 2000); });
                status.style.color = '';
            }
        } catch (e) { status.textContent = '✗ Сетевая ошибка'; }
    }
    bar.style.width = '100%'; bar.style.background = 'var(--ok)';
    status.textContent = '✓ Готово';
    setTimeout(function(){ prog.style.display = 'none'; bar.style.width = '0%'; }, 2000);
}

function renderScreenshots(list) {
    var grid = document.getElementById('scr-grid');
    grid.innerHTML = list.map(function(s){
        return '<div class="scr-item" data-url="' + s.path + '" style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:16/9;background:var(--elev);"><img src="' + s.path + '" style="width:100%;height:100%;object-fit:cover;"><button type="button" onclick="deleteScreenshot(this)" style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.7);border:none;border-radius:6px;color:#fff;cursor:pointer;width:26px;height:26px;font-size:15px;line-height:1;display:flex;align-items:center;justify-content:center;">×</button></div>';
    }).join('');
    document.getElementById('scr-count-lbl').textContent = list.length + ' / 10';
}

async function deleteScreenshot(btn) {
    var item = btn.closest('.scr-item');
    var url  = item.dataset.url;
    if (!confirm('Удалить этот скриншот?')) return;
    item.style.opacity = '.4';
    var fd = new FormData();
    fd.append('project_id', SCR_PID);
    fd.append('type', 'delete_screenshot');
    fd.append('url', url);
    try {
        var res  = await fetch('/devs/upload_media.php', {method:'POST', body:fd, credentials:'include'});
        var data = await res.json();
        if (data.success) renderScreenshots(data.screenshots);
        else { item.style.opacity = '1'; alert(data.message || 'Ошибка удаления'); }
    } catch (e) { item.style.opacity = '1'; alert('Сетевая ошибка'); }
}

// ── Дропдаун языков ───────────────────────────────────────────────────────
(function () {
    var trigger = document.getElementById('lang-trigger');
    var menu    = document.getElementById('lang-menu');
    var arrow   = document.getElementById('lang-arrow');
    var open    = false;

    function openMenu()  { menu.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; open = true; }
    function closeMenu() { menu.style.display = 'none';  arrow.style.transform = '';               open = false; }

    trigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        open ? closeMenu() : openMenu();
    });
    menu.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { if (open) closeMenu(); });

    menu.querySelectorAll('label').forEach(function (lbl) {
        lbl.addEventListener('mouseenter', function () { lbl.style.background = 'rgba(255,255,255,.06)'; });
        lbl.addEventListener('mouseleave', function () { lbl.style.background = ''; });
        lbl.querySelector('input').addEventListener('change', syncLangLabel);
    });

    syncLangLabel();
})();

function syncLangLabel() {
    var checked = Array.from(document.querySelectorAll('input[name="languages_list[]"]:checked')).map(function(c){ return c.value; });
    document.getElementById('lang-label').textContent = checked.length ? checked.join(', ') : 'Выберите языки...';
    document.getElementById('languages-hidden').value = checked.join(', ');
}
</script>
JS;

require_once(__DIR__ . '/includes/footer.php');
?>