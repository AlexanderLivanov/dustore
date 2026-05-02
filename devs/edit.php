<?php
/**
 * devs/edit.php — редактирование проекта.
 * Весь POST обрабатывается ДО вывода HTML.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3.php');

$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) { header('Location: /devs/projects'); exit(); }

$db        = new Database();
$conn      = $db->connect();
$studio_id = (int)($_SESSION['studio_id'] ?? 0);

// Загружаем проект (и проверяем принадлежность)
$stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND developer = ?");
$stmt->execute([$project_id, $studio_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) { header('Location: /devs/projects'); exit(); }

$error_msg = '';

// ── Обрабатываем POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Сохранение
    if ($action === 'save') {
        $name         = trim($_POST['name']               ?? '');
        $genre        = trim($_POST['genre']              ?? '');
        $short_desc   = trim($_POST['short_description']  ?? '');
        $description  = trim($_POST['description']        ?? '');
        $platforms    = implode(',', $_POST['platform']   ?? []);
        $release_date = $_POST['release_date']            ?? $game['release_date'];
        $game_website = filter_var($_POST['website']      ?? '', FILTER_SANITIZE_URL);
        $trailer_url  = filter_var($_POST['trailer_url']  ?? '', FILTER_SANITIZE_URL);
        $game_exec    = trim($_POST['game_exec']          ?? '');
        $languages    = trim($_POST['languages']          ?? '');
        $age_rating   = $_POST['age_rating']              ?? '0+';
        $cover_path   = $game['path_to_cover'];

        // Загружаем обложку на S3 если выбрана
        if (!empty($_FILES['cover_art']['name']) && $_FILES['cover_art']['error'] === UPLOAD_ERR_OK) {
            try {
                $s3  = new S3Uploader();
                $ext = pathinfo($_FILES['cover_art']['name'], PATHINFO_EXTENSION);
                $org = preg_replace('/[^a-z0-9]/i', '-', $_SESSION['STUDIODATA']['name'] ?? 'studio');
                $key = $org . '/' . preg_replace('/[^a-z0-9]/i', '-', $name)
                     . '/cover-' . uniqid() . '.' . $ext;
                $uploaded = $s3->uploadFile($_FILES['cover_art']['tmp_name'], $key);
                if ($uploaded) $cover_path = $uploaded;
            } catch (Exception $e) {
                error_log('Cover upload error: ' . $e->getMessage());
            }
        }

        try {
            $conn->prepare("
                UPDATE games SET
                    name             = :name,
                    genre            = :genre,
                    short_description= :short,
                    description      = :desc,
                    platforms        = :platforms,
                    release_date     = :release,
                    path_to_cover    = :cover,
                    game_website     = :website,
                    trailer_url      = :trailer,
                    game_exec        = :exec,
                    languages        = :languages,
                    age_rating       = :age,
                    updated_at       = NOW()
                WHERE id = :id AND developer = :dev
            ")->execute([
                'name'      => $name,
                'genre'     => $genre,
                'short'     => $short_desc,
                'desc'      => $description,
                'platforms' => $platforms,
                'release'   => $release_date,
                'cover'     => $cover_path,
                'website'   => $game_website,
                'trailer'   => $trailer_url,
                'exec'      => $game_exec,
                'languages' => $languages,
                'age'       => $age_rating,
                'id'        => $project_id,
                'dev'       => $studio_id,
            ]);
            header('Location: /devs/edit?id=' . $project_id . '&saved=1');
            exit();
        } catch (PDOException $e) {
            $error_msg = 'Ошибка сохранения: ' . $e->getMessage();
        }

    // На модерацию
    } elseif ($action === 'moderation') {
        $conn->prepare("UPDATE games SET moderation_status = 'pending', updated_at = NOW() WHERE id = ? AND developer = ?")
             ->execute([$project_id, $studio_id]);
        header('Location: /devs/edit?id=' . $project_id . '&moderated=1');
        exit();

    // Удаление (только владелец)
    } elseif ($action === 'delete') {
        // Проверяем права ещё раз на сервере
        $tg_id   = (string)($_SESSION['USERDATA']['telegram_id'] ?? '');
        $is_adm  = ((int)($_SESSION['USERDATA']['global_role'] ?? 0)) === -1;
        $chk     = $conn->prepare("SELECT role FROM staff WHERE telegram_id = ? AND org_id = ? LIMIT 1");
        $chk->execute([$tg_id, $studio_id]);
        $role    = $chk->fetchColumn();
        if ($is_adm || $role === 'Владелец') {
            $conn->prepare("DELETE FROM games WHERE id = ? AND developer = ?")
                 ->execute([$project_id, $studio_id]);
            header('Location: /devs/projects?deleted=1');
            exit();
        }
        $error_msg = 'Недостаточно прав для удаления проекта.';
    }
}

// Перечитываем актуальные данные после возможного обновления
$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$project_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// Флаги из URL
$success_msg = '';
if (isset($_GET['saved']))    $success_msg = 'Изменения сохранены!';
if (isset($_GET['created']))  $success_msg = 'Проект создан! Теперь загрузите файл игры.';
if (isset($_GET['moderated'])) $success_msg = 'Проект отправлен на модерацию!';

$page_title = 'Редактирование: ' . ($game['name'] ?? '');
$active_nav = 'projects';
require_once(__DIR__ . '/includes/header.php');

// Вспомогательные данные
$status_labels = [
    'published' => ['badge-pub',   'Опубликован'],
    'draft'     => ['badge-draft', 'Черновик'],
    'closed'    => ['badge-err',   'Закрыт'],
];
[$status_cls, $status_lbl] = $status_labels[$game['status'] ?? 'draft'] ?? ['badge-draft', 'Черновик'];
$platforms_saved = array_filter(array_map('trim', explode(',', $game['platforms'] ?? '')));
$has_zip     = !empty($game['game_zip_url']);
$is_chunked  = $has_zip && str_ends_with((string)$game['game_zip_url'], 'manifest.json');

function ev(array $a, string $k): string {
    return htmlspecialchars($a[$k] ?? '');
}
?>

<?php if ($success_msg): ?>
<div class="alert alert-ok"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<!-- Шапка страницы -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
    <div style="font-size:20px;font-weight:700;"><?= ev($game, 'name') ?></div>
    <span class="badge <?= $status_cls ?>"><?= $status_lbl ?></span>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/g/<?= $project_id ?>" target="_blank"
           class="btn btn-g" style="padding:6px 14px;font-size:12px;">
            <span class="material-icons" style="font-size:15px;">open_in_new</span>Открыть
        </a>
        <?php if ($game['status'] === 'draft' && $is_owner): ?>
        <form method="POST">
            <input type="hidden" name="action" value="moderation">
            <button type="submit" class="btn btn-p" style="padding:6px 14px;font-size:12px;">
                <span class="material-icons" style="font-size:15px;">send</span>На модерацию
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ФОРМА СОХРАНЕНИЯ (содержит ТОЛЬКО поля для сохранения)
     ═══════════════════════════════════════════════════════════ -->
<form method="POST" enctype="multipart/form-data" id="save-form">
    <input type="hidden" name="action" value="save">

    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">

        <!-- Левая колонка -->
        <div style="display:flex;flex-direction:column;gap:14px;">

            <!-- Основная информация -->
            <div class="card">
                <div class="card-title"><span class="material-icons">info</span>Основная информация</div>
                <div class="grid-2">
                    <div class="field col-full">
                        <label>Название *</label>
                        <input type="text" name="name" value="<?= ev($game,'name') ?>"
                               required maxlength="64">
                    </div>
                    <div class="field">
                        <label>Жанр</label>
                        <select name="genre">
                            <?php foreach ([
                                'Платформер','RPG','Аркада','Стратегия','Головоломка',
                                'Экшн','Симулятор','Хоррор','Приключение',
                                'Визуальная новелла','Файтинг','Гонки','Другое',
                            ] as $g): ?>
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
                        <input type="text" name="short_description"
                               value="<?= ev($game,'short_description') ?>" maxlength="200">
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
                        <label>Трейлер (YouTube)</label>
                        <input type="url" name="trailer_url" value="<?= ev($game,'trailer_url') ?>">
                    </div>
                    <div class="field">
                        <label>Исполняемый файл</label>
                        <input type="text" name="game_exec" value="<?= ev($game,'game_exec') ?>">
                    </div>
                </div>
            </div>

            <!-- Платформы -->
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
                    <input type="text" name="languages" value="<?= ev($game,'languages') ?>"
                           placeholder="Русский, English...">
                </div>
            </div>

            <!-- ZIP файл игры -->
            <div class="card">
                <div class="card-title"><span class="material-icons">folder_zip</span>Файл игры</div>

                <?php if ($has_zip): ?>
                <div class="alert alert-ok" style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                    <span class="material-icons" style="font-size:18px;">check_circle</span>
                    <div>
                        <?php if ($is_chunked): ?>
                            Загружен чанками
                            <?= !empty($game['game_zip_size']) ? '· ' . round($game['game_zip_size']/1048576,1) . ' МБ' : '' ?>
                            <span style="font-size:10px;background:rgba(0,214,143,.15);padding:1px 8px;border-radius:4px;margin-left:6px;">manifest.json</span>
                        <?php else: ?>
                            ZIP загружен
                            <?= !empty($game['game_zip_size']) ? '· ' . round($game['game_zip_size']/1048576,1) . ' МБ' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div id="upload-mode-hint" style="display:none;margin-bottom:10px;padding:8px 12px;border-radius:8px;font-size:12px;"></div>

                <div id="zip-drop"
                     style="border:2px dashed rgba(195,33,120,.3);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:border-color .2s;"
                     onmouseover="this.style.borderColor='var(--p)'"
                     onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                    <span class="material-icons" style="font-size:32px;color:var(--p);display:block;margin-bottom:8px;">upload_file</span>
                    <span style="font-size:13px;color:var(--ts);display:block;">
                        <?= $has_zip ? 'Заменить файл игры' : 'Загрузить ZIP-архив' ?>
                    </span>
                    <span style="font-size:11px;color:var(--tm);display:block;margin-top:4px;">
                        До 500 МБ — прямая загрузка &nbsp;·&nbsp; Больше 500 МБ — чанки + manifest.json
                    </span>
                </div>
                <input type="file" id="zip-input" accept=".zip" style="display:none;">

                <div id="zip-progress" style="display:none;margin-top:14px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span id="zip-status" style="font-size:12px;color:var(--ts);">Подготовка...</span>
                        <span id="zip-pct"    style="font-size:12px;font-weight:600;color:var(--tm);">0%</span>
                    </div>
                    <div style="height:8px;background:var(--elev);border-radius:4px;overflow:hidden;">
                        <div id="zip-bar" style="height:100%;background:var(--p);border-radius:4px;width:0%;transition:width .3s;"></div>
                    </div>
                    <div id="zip-detail" style="font-size:11px;color:var(--tm);margin-top:6px;"></div>
                </div>
            </div>

        </div><!-- /левая колонка -->

        <!-- Правая колонка -->
        <div style="display:flex;flex-direction:column;gap:14px;">

            <!-- Обложка -->
            <div class="card">
                <div class="card-title"><span class="material-icons">image</span>Обложка</div>
                <?php if (!empty($game['path_to_cover'])): ?>
                <img src="<?= ev($game,'path_to_cover') ?>"
                     id="current-cover"
                     style="width:100%;border-radius:8px;margin-bottom:10px;object-fit:cover;max-height:160px;">
                <?php endif; ?>
                <label for="cover_art"
                       style="display:block;border:2px dashed rgba(195,33,120,.3);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s;"
                       onmouseover="this.style.borderColor='var(--p)'"
                       onmouseout="this.style.borderColor='rgba(195,33,120,.3)'">
                    <span class="material-icons" style="font-size:24px;color:var(--p);display:block;margin-bottom:6px;">add_photo_alternate</span>
                    <span style="font-size:12px;color:var(--ts);display:block;">
                        <?= !empty($game['path_to_cover']) ? 'Заменить обложку' : 'Загрузить обложку' ?>
                    </span>
                </label>
                <input type="file" id="cover_art" name="cover_art" accept="image/*" style="display:none;">
                <div id="cover_wrap" style="display:none;margin-top:8px;">
                    <img id="cover_prev" src="" style="width:100%;border-radius:8px;object-fit:cover;max-height:120px;">
                </div>
            </div>

            <!-- Кнопка сохранения -->
            <div class="card">
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;">
                    <span class="material-icons">save</span>Сохранить изменения
                </button>
                <a href="/devs/projects" class="btn btn-g" style="width:100%;justify-content:center;margin-top:8px;">
                    ← Назад к проектам
                </a>
            </div>

        </div><!-- /правая колонка -->

    </div>
</form>
<!-- ═══════ КОНЕЦ ФОРМЫ СОХРАНЕНИЯ ═══════ -->


<!-- ═══════════════════════════════════════════════════════════
     ФОРМА УДАЛЕНИЯ — полностью отдельная, вне save-form
     ═══════════════════════════════════════════════════════════ -->
<?php if ($is_owner): ?>
<form method="POST" id="delete-form"
      onsubmit="return confirm('Удалить проект «<?= htmlspecialchars(addslashes($game['name'])) ?>»?\n\nЭто действие нельзя отменить — игра и все данные будут удалены.')">
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
const LARGE  = 500 * 1024 * 1024;
const SMALL_CHUNK = 5  * 1024 * 1024;
const LARGE_CHUNK = 50 * 1024 * 1024;
const PID = {$project_id};

// ── ZIP upload ─────────────────────────────────────────────────────────────
document.getElementById('zip-drop').addEventListener('click', () =>
    document.getElementById('zip-input').click()
);

document.getElementById('zip-input').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const big  = file.size >= LARGE;
    const hint = document.getElementById('upload-mode-hint');
    hint.style.display    = 'block';
    hint.style.background = big ? 'rgba(195,33,120,.08)' : 'rgba(0,214,143,.06)';
    hint.style.border     = big ? '1px solid rgba(195,33,120,.2)' : '1px solid rgba(0,214,143,.15)';
    hint.style.color      = big ? 'var(--pl)' : 'var(--ok)';
    hint.textContent      = big
        ? '📦 ' + (file.size/1048576).toFixed(1) + ' МБ — чанкованная загрузка (50 МБ/чанк)'
        : '⚡ ' + (file.size/1048576).toFixed(1) + ' МБ — прямая загрузка';
    uploadFile(file);
});

async function uploadFile(file) {
    const big    = file.size >= LARGE;
    const chunk  = big ? LARGE_CHUNK : SMALL_CHUNK;
    const total  = Math.ceil(file.size / chunk);
    const prog   = document.getElementById('zip-progress');
    const bar    = document.getElementById('zip-bar');
    const status = document.getElementById('zip-status');
    const pct    = document.getElementById('zip-pct');
    const detail = document.getElementById('zip-detail');

    prog.style.display   = 'block';
    bar.style.background = 'var(--p)';

    for (let i = 0; i < total; i++) {
        const last = i === total - 1;

        if (last && total > 1) {
            status.textContent = big
                ? '⏳ Последний чанк + создаём manifest.json...'
                : '⏳ Финализируем загрузку...';
        } else {
            bar.style.width  = Math.round(i / total * 100) + '%';
            pct.textContent  = Math.round(i / total * 100) + '%';
            status.textContent = 'Чанк ' + (i+1) + ' из ' + total;
        }

        const fd = new FormData();
        fd.append('chunk',        file.slice(i * chunk, (i+1) * chunk));
        fd.append('chunk_index',  i);
        fd.append('total_chunks', total);
        fd.append('file_name',    file.name);
        fd.append('file_size',    file.size);
        fd.append('project_id',   PID);

        let data;
        try {
            const res  = await fetch('/devs/upload_chunk.php', {method:'POST', body:fd, credentials:'include'});
            const text = await res.text();
            data = JSON.parse(text);
        } catch (e) {
            setErr('Сервер вернул не-JSON: ' + String(e).substring(0, 200));
            return;
        }

        if (!data.success) { setErr(data.message || 'Ошибка сервера'); return; }

        if (data.done) {
            bar.style.width      = '100%';
            pct.textContent      = '100%';
            bar.style.background = 'var(--ok)';
            status.style.color   = 'var(--ok)';
            status.textContent   = data.mode === 'chunked'
                ? '✓ ' + data.chunk_count + ' чанков загружено · ' + data.size_mb + ' МБ'
                : '✓ ZIP загружен · ' + data.size_mb + ' МБ';
            detail.textContent = data.mode === 'chunked'
                ? 'manifest.json создан на S3'
                : '';
            setTimeout(() => location.reload(), 1500);
            return;
        }

        bar.style.width = Math.round((i+1) / total * 100) + '%';
        pct.textContent = Math.round((i+1) / total * 100) + '%';
        if (data.sha256) detail.textContent = 'SHA256: ' + data.sha256.slice(0,16) + '...';
    }
}

function setErr(msg) {
    document.getElementById('zip-bar').style.background = 'var(--err)';
    const s = document.getElementById('zip-status');
    s.textContent = '✗ ' + msg;
    s.style.color = 'var(--err)';
}

// ── Cover preview ──────────────────────────────────────────────────────────
document.getElementById('cover_art').addEventListener('change', function () {
    if (!this.files[0]) return;
    const cur = document.getElementById('current-cover');
    if (cur) cur.style.display = 'none';
    const wrap = document.getElementById('cover_wrap');
    const img  = document.getElementById('cover_prev');
    img.src  = URL.createObjectURL(this.files[0]);
    wrap.style.display = 'block';
});
</script>
JS;

require_once(__DIR__ . '/includes/footer.php');
?>