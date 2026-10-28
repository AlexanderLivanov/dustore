<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Живое превью трейлера при вводе ссылки. Раньше это была JS-эвристика
   (val.includes('embed') || val.includes('player.vimeo')) — не ловила самый
   частый случай, когда просто вставляют youtube.com/watch?v=... из адресной
   строки, так что превью на этом сценарии молча не появлялось никогда.
   Чинится не второй копией регулярок в JS (это и была причина расхождения),
   а вызовом той же trailer_embed(), что рендерит саму игровую страницу —
   единственный источник правды, всегда актуален по построению. Ветка стоит
   до тяжёлых require ниже, чтобы AJAX не тянул лишнее. */
if (($_GET['ajax'] ?? '') === 'trailer_preview') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['studio_id'])) { echo json_encode(['success' => false]); exit(); }
    require_once(__DIR__ . '/../swad/controllers/trailer_embed.php');
    echo json_encode(['success' => true, 'html' => trailer_embed($_GET['url'] ?? '')]);
    exit();
}

require_once(__DIR__ . '/../swad/config.php');
require_once(__DIR__ . '/../swad/controllers/s3.php');
require_once(__DIR__ . '/../swad/controllers/tg_bot.php');
require_once(__DIR__ . '/../swad/controllers/deplex_web.php');
require_once(__DIR__ . '/../swad/controllers/game_changelog.php');
require_once(__DIR__ . '/../swad/controllers/trailer_embed.php');
require_once(__DIR__ . '/../swad/controllers/csrf.php');

/** Ответ автосохранения (fetch из вкладок редактора). */
function ed_json(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/* ── Платформы: вкладки редактора ──────────────────────────────────────────
   Включённые платформы — это по-прежнему games.platforms (CSV): его читают game.php,
   каталог и раздатчики. Всё остальное по платформе — строка в game_builds. */
const ED_PLAT = [
    'Windows' => ['id' => 'windows', 'icon' => 'window',       'accept' => '.zip'],
    'macOS'   => ['id' => 'macos',   'icon' => 'laptop_mac',   'accept' => '.zip'],
    'Linux'   => ['id' => 'linux',   'icon' => 'terminal',     'accept' => '.zip'],
    'Android' => ['id' => 'android', 'icon' => 'android',      'accept' => '.apk'],
    'iOS'     => ['id' => 'ios',     'icon' => 'phone_iphone', 'accept' => '.ipa'],
    'Web'     => ['id' => 'web',     'icon' => 'language',     'accept' => '.zip'],
];
const ED_MOBILE  = ['Android', 'iOS'];
const ED_DESKTOP = ['Windows', 'macOS', 'Linux'];

/** Поля требований платформы: key => [подпись, пример]. Хранятся как [{key,label,value}] — формат game.php. */
function ed_req_fields(string $pl): array
{
    $other = ['other' => ['Дополнительно', 'Например: нужен интернет, геймпад']];
    return match ($pl) {
        'Android' => ['os' => ['Версия Android', 'Android 8.0 и новее'], 'ram' => ['Оперативная память', '3 ГБ'], 'disk' => ['Место', '500 МБ']] + $other,
        'iOS'     => ['os' => ['Версия iOS', 'iOS 15 и новее'], 'device' => ['Устройства', 'iPhone XS и новее'], 'disk' => ['Место', '500 МБ']] + $other,
        'Web'     => ['browser' => ['Браузер', 'Chrome 110+, Firefox 115+, Safari 16+'], 'input' => ['Управление', 'Клавиатура и мышь']] + $other,
        default   => [
            'os'   => ['ОС', ['Windows' => 'Windows 10 64-bit', 'macOS' => 'macOS 12 Monterey', 'Linux' => 'Ubuntu 22.04'][$pl] ?? ''],
            'cpu'  => ['Процессор', 'Intel Core i5-4460 / Ryzen 3 1200'],
            'ram'  => ['Оперативная память', '8 ГБ'],
            'gpu'  => ['Видеокарта', 'GTX 960 / RX 470, 2 ГБ'],
            'disk' => ['Место на диске', '2 ГБ'],
        ] + $other,
    };
}

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

    if (($game['moderation_status'] ?? '') === 'pending'
        && in_array($action, ['save', 'save_announce', 'save_jam'], true)) {
        if (!empty($_POST['ajax'])) ed_json(['ok' => false, 'error' => 'Игра на модерации — редактирование заблокировано'], 423);
        header('Location: /devs/edit?id=' . $project_id . '&locked=1'); exit();
    }

    if ($action === 'save') {
        $ajax         = !empty($_POST['ajax']);
        $name         = trim($_POST['name']         ?? '');
        $genre        = trim($_POST['genre']        ?? '');
        $short_desc   = trim($_POST['short_description'] ?? '');
        $description  = trim($_POST['description']  ?? '');
        $platforms_on = array_values(array_intersect(array_keys(ED_PLAT), (array)($_POST['platform'] ?? [])));
        $platforms    = implode(',', $platforms_on);
        $release_date = $_POST['release_date']      ?? $game['release_date'];
        $game_website = filter_var($_POST['website']    ?? '', FILTER_SANITIZE_URL);
        $trailer_url  = filter_var($_POST['trailer_url'] ?? '', FILTER_SANITIZE_URL);
        $game_exec    = trim($_POST['game_exec']    ?? '');
        $languages    = trim($_POST['languages']    ?? '');
        $age_rating   = $_POST['age_rating']        ?? '0+';
        $cover_path   = $game['path_to_cover'];
        $icon_path    = $game['icon_url'] ?? '';

        $save_err = !csrf_valid() ? 'Сессия устарела — обновите страницу и повторите.'
                  : ($name === '' ? 'Укажите название игры.' : '');
        if ($save_err !== '') {
            if ($ajax) ed_json(['ok' => false, 'error' => $save_err], 422);
            $error_msg = $save_err;
        } else {

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

        // Требования и разрешения по платформам (вкладки платформ).
        // На страницу игры (games.requirements) зеркалим требования первой включённой
        // платформы — game.php пока показывает один набор, как build_upload зеркалит билд.
        $plat_data = [];
        $store_req = [];
        foreach (array_keys(ED_PLAT) as $pl) {
            $req = [];
            foreach (ed_req_fields($pl) as $k => [$label]) {
                $v = trim(mb_substr((string)($_POST['req'][$pl][$k] ?? ''), 0, 300));
                if ($v !== '') $req[] = ['key' => $k, 'label' => $label, 'value' => $v];
            }
            $perm = in_array($pl, ED_MOBILE, true) ? trim(mb_substr((string)($_POST['perm'][$pl] ?? ''), 0, 4000)) : null;
            $plat_data[$pl] = [$req ? json_encode($req, JSON_UNESCAPED_UNICODE) : null, $perm];
            if (!$store_req && $req && in_array($pl, $platforms_on, true)) $store_req = $req;
        }
        $requirements = json_encode($store_req, JSON_UNESCAPED_UNICODE);

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
                    requirements      = :req,
                    moderation_status = 'draft',
                    status            = IF(status='published','draft',status),
                    updated_at        = NOW()
                WHERE id = :id AND developer = :dev
            ")->execute([
                'name'      => $name,      'genre'    => $genre,
                'short'     => $short_desc,'desc'     => $description,
                'platforms' => $platforms, 'release'  => $release_date,
                'cover'     => $cover_path,'icon'     => $icon_path,
                'website'   => $game_website,'trailer' => $trailer_url,
                'exec'      => $game_exec, 'languages'=> $languages,
                'age'       => $age_rating,'req'      => $requirements,
                'id'        => $project_id,'dev'      => $studio_id,
            ]);

            // game_builds: пишем только то, что изменилось, и не плодим пустые строки
            require_once(__DIR__ . '/../swad/controllers/game_builds_schema.php');
            ensure_game_builds_table($conn);
            $gbSel = $conn->prepare("SELECT requirements, permissions FROM game_builds WHERE game_id = ? AND platform = ?");
            $gbUp  = $conn->prepare("
                INSERT INTO game_builds (game_id, platform, requirements, permissions) VALUES (:g, :p, :r, :pm)
                ON DUPLICATE KEY UPDATE requirements = VALUES(requirements), permissions = VALUES(permissions), updated_at = NOW()
            ");
            foreach ($plat_data as $pl => [$reqJson, $perm]) {
                $gbSel->execute([$project_id, $pl]);
                $row  = $gbSel->fetch(PDO::FETCH_ASSOC);
                $perm = $perm === null ? ($row['permissions'] ?? null) : ($perm !== '' ? $perm : null);
                if (!$row && $reqJson === null && $perm === null) continue;
                if ($row && $row['requirements'] === $reqJson && $row['permissions'] === $perm) continue;
                $gbUp->execute(['g' => $project_id, 'p' => $pl, 'r' => $reqJson, 'pm' => $perm]);
            }

            $logUser = (int)($_SESSION['USERDATA']['id'] ?? 0);
log_game_diff($conn, $project_id, $studio_id, $logUser, [
    'name'              => [$game['name']              ?? '', $name],
    'genre'             => [$game['genre']             ?? '', $genre],
    'short_description' => [$game['short_description'] ?? '', $short_desc],
    'description'       => [$game['description']       ?? '', $description],
    'platforms'         => [$game['platforms']         ?? '', $platforms],
    'release_date'      => [$game['release_date']      ?? '', $release_date],
    'game_website'      => [$game['game_website']      ?? '', $game_website],
    'trailer_url'       => [$game['trailer_url']       ?? '', $trailer_url],
    'game_exec'         => [$game['game_exec']         ?? '', $game_exec],
    'languages'         => [$game['languages']         ?? '', $languages],
    'age_rating'        => [$game['age_rating']        ?? '', $age_rating],
    'requirements'      => [$game['requirements']      ?? '', $requirements],
    'cover'             => [$game['path_to_cover']     ?? '', $cover_path],
    'icon'              => [$game['icon_url']          ?? '', $icon_path],
]);
            $conn->prepare("DELETE FROM moderation_reviews WHERE game_id = ?")->execute([$project_id]);
            if ($ajax) ed_json(['ok' => true, 'saved_at' => date('H:i'), 'cover' => $cover_path, 'icon' => $icon_path]);
            $tab = preg_replace('/[^a-z]/', '', (string)($_POST['tab'] ?? ''));
            header('Location: /devs/edit?id=' . $project_id . '&saved=1' . ($tab ? '&tab=' . $tab : ''));
            exit();
        } catch (PDOException $e) {
            error_log('edit.php save: ' . $e->getMessage());
            if ($ajax) ed_json(['ok' => false, 'error' => 'Ошибка базы данных — изменения не сохранены'], 500);
            $error_msg = 'Ошибка сохранения: ' . $e->getMessage();
        }
        }

    } elseif ($action === 'moderation') {
        $conn->prepare("UPDATE games SET moderation_status='pending', updated_at=NOW() WHERE id=? AND developer=?")
             ->execute([$project_id, $studio_id]);
        $pendingTotal = (int)$conn->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
        send_group_message(-1002916906978,
            "🆕 <b>Новый проект на модерации</b>\n" .
            "ℹ️ Разработчик может отменить отправку до конца голосования.\n\n" .
            "📊 Всего проектов на модерации: {$pendingTotal}",
            true, 'https://dustore.ru/devs/experts');
            log_game_change($conn, $project_id, $studio_id, (int)($_SESSION['USERDATA']['id'] ?? 0), 'moderation', null, null, 'submitted');
        header('Location: /devs/edit?id=' . $project_id . '&moderated=1');
        exit();

    } elseif ($action === 'cancel_moderation') {
        $conn->prepare("DELETE FROM moderation_reviews WHERE game_id = ?")->execute([$project_id]);
        $conn->prepare("UPDATE games SET moderation_status='draft', updated_at=NOW() WHERE id=? AND developer=?")
             ->execute([$project_id, $studio_id]);
        header('Location: /devs/edit?id=' . $project_id . '&cancelled=1');
        exit();

    } elseif ($action === 'resubmit_moderation') {
        if ($is_owner) {
            $conn->prepare("DELETE FROM moderation_reviews WHERE game_id = ?")->execute([$project_id]);
            $conn->prepare("UPDATE games SET moderation_status='pending', moderation_submitted_at=NOW(), updated_at=NOW() WHERE id=? AND developer=?")
                 ->execute([$project_id, $studio_id]);
            $pendingTotal = (int)$conn->query("SELECT COUNT(*) FROM games WHERE moderation_status='pending'")->fetchColumn();
            send_group_message(-1002916906978,
                "🔄 <b>Проект отправлен на повторную модерацию</b>\n" .
                "ℹ️ Разработчик может отменить отправку.\n\n" .
                "📊 Всего проектов на модерации: {$pendingTotal}",
                true, 'https://dustore.ru/devs/experts');
                log_game_change($conn, $project_id, $studio_id, (int)($_SESSION['USERDATA']['id'] ?? 0), 'moderation', null, null, 'resubmitted');
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

    } elseif ($action === 'save_jam') {
        // ── Привязка проекта к джему ──
        // Менять привязку нельзя, если текущий джем ушёл в голосование / завершён.
        $locked = false;
        if (!empty($game['sprint_id'])) {
            $lc = $conn->prepare("SELECT status, voting_start FROM sprints WHERE id = ?");
            $lc->execute([(int)$game['sprint_id']]);
            if ($ls = $lc->fetch(PDO::FETCH_ASSOC)) {
                $locked = ($ls['status'] === 'finished')
                    || (!empty($ls['voting_start']) && strtotime($ls['voting_start']) <= time());
            }
        }
        if ($locked) {
            $error_msg = 'Джем в стадии голосования или завершён — привязку менять нельзя.';
        } elseif (isset($_POST['jam_enabled']) && $_POST['jam_enabled'] && (int)($_POST['sprint_id'] ?? 0) > 0) {
            $sid = (int)$_POST['sprint_id'];
            $jc  = $conn->prepare("SELECT title FROM sprints WHERE id = ? AND status IN ('registration','ongoing') AND (voting_start IS NULL OR voting_start > NOW()) LIMIT 1");
            $jc->execute([$sid]);
            if ($jc->fetchColumn() !== false) {
                $conn->prepare("UPDATE games SET sprint_id = ?, updated_at = NOW() WHERE id = ? AND developer = ?")
                     ->execute([$sid, $project_id, $studio_id]);
                header('Location: /devs/edit?id=' . $project_id . '&jam=1'); exit();
            }
            $error_msg = 'Этот джем сейчас не принимает работы.';
        } else {
            $conn->prepare("UPDATE games SET sprint_id = NULL, updated_at = NOW() WHERE id = ? AND developer = ?")
                 ->execute([$project_id, $studio_id]);
            header('Location: /devs/edit?id=' . $project_id . '&jam=1'); exit();
        }
    } elseif ($action === 'toggle_hidden') {
        $conn->prepare("UPDATE games SET hidden = IF(hidden=1,0,1), updated_at=NOW() WHERE id=? AND developer=?")
             ->execute([$project_id, $studio_id]);
        header('Location: /devs/edit?id=' . $project_id . '&visibility=1'); exit();

    } elseif ($action === 'make_preview_link') {
        $token = bin2hex(random_bytes(16));
        $conn->prepare("UPDATE games SET preview_token=?, preview_expires=DATE_ADD(NOW(), INTERVAL 7 DAY), updated_at=NOW() WHERE id=? AND developer=?")
             ->execute([$token, $project_id, $studio_id]);
        header('Location: /devs/edit?id=' . $project_id . '&pvlink=1'); exit();
    } elseif ($action === 'delete') {
        if ($is_owner) {
            $conn->prepare("DELETE FROM games WHERE id = ? AND developer = ?")->execute([$project_id, $studio_id]);
            header('Location: /devs/projects?deleted=1');
            exit();
        }
        $error_msg = 'Недостаточно прав для удаления проекта.';
    } elseif ($action === 'create_deplex_token') {
        $raw = 'dplx_live_' . bin2hex(random_bytes(12));
        $conn->prepare("INSERT INTO deplex_tokens (token_hash, token_prefix, studio_id, user_id) VALUES (?, ?, ?, ?)")
             ->execute([hash('sha256', $raw), substr($raw, 0, 14), $studio_id, (int)($_SESSION['USERDATA']['id'] ?? 0)]);
        $_SESSION['new_deplex_token'] = $raw;
        header('Location: /devs/edit?id=' . $project_id . '&dpxtoken=1');
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$project_id]);
$game        = $stmt->fetch(PDO::FETCH_ASSOC);
$screenshots = json_decode($game['screenshots'] ?? '[]', true) ?: [];

// ── Джем: текущая привязка + список открытых джемов ──
$current_sprint = null;
if (!empty($game['sprint_id'])) {
    $cs = $conn->prepare("SELECT id, title, status, voting_start FROM sprints WHERE id = ?");
    $cs->execute([(int)$game['sprint_id']]);
    $current_sprint = $cs->fetch(PDO::FETCH_ASSOC) ?: null;
}
$jam_locked = $current_sprint && (($current_sprint['status'] === 'finished')
    || (!empty($current_sprint['voting_start']) && strtotime($current_sprint['voting_start']) <= time()));
$open_jams = $conn->query("
    SELECT id, title FROM sprints
    WHERE status IN ('registration','ongoing') AND (voting_start IS NULL OR voting_start > NOW())
    ORDER BY jam_start DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$success_msg = '';
if (isset($_GET['saved']))     $success_msg = 'Изменения сохранены!';
if (isset($_GET['created']))   $success_msg = 'Проект создан! Теперь загрузите файл игры.';
if (isset($_GET['moderated'])) $success_msg = 'Проект отправлен на модерацию!';
if (isset($_GET['published'])) $success_msg = 'Игра опубликована! Теперь она видна всем игрокам.';
if (isset($_GET['announced'])) $success_msg = 'Настройки анонса сохранены!';
if (isset($_GET['jam']))       $success_msg = 'Настройки участия в джеме сохранены!';
if (isset($_GET['cancelled'])) $success_msg = 'Отправка на модерацию отменена — можно редактировать.';
if (isset($_GET['locked']))    $error_msg   = 'Игра на модерации — редактирование заблокировано. Отмените отправку, чтобы вносить правки.';
if (isset($_GET['visibility'])) $success_msg = 'Видимость игры изменена.';
if (isset($_GET['pvlink']))     $success_msg = 'Ссылка для просмотра создана — действует 7 дней.';

$page_title = 'Редактирование: ' . ($game['name'] ?? '');
$active_nav = 'projects';
require_once(__DIR__ . '/includes/header.php');

$status_labels   = ['published'=>['badge-pub','Опубликован'],'draft'=>['badge-draft','Черновик'],'closed'=>['badge-err','Закрыт']];
[$status_cls, $status_lbl] = $status_labels[$game['status'] ?? 'draft'] ?? ['badge-draft','Черновик'];
$platforms_saved = array_filter(array_map('trim', explode(',', $game['platforms'] ?? '')));
$has_zip         = !empty($game['game_zip_url']);
$is_chunked      = $has_zip && str_ends_with((string)$game['game_zip_url'], 'manifest.json');
$only_android    = $platforms_saved === ['Android'];

// Мультиплатформенные билды (game_builds) — своя строка на каждую выбранную платформу.
// games.game_zip_url/game_zip_size (выше) остаются «текущим активным билдом» для
// download_game.php/download_apk.php/webplayer.php — см. комментарий в build_upload.php.
$builds_by_platform = [];
require_once(__DIR__ . '/../swad/controllers/game_builds_schema.php');
ensure_game_builds_table($conn);
$gb_stmt = $conn->prepare("SELECT * FROM game_builds WHERE game_id = ?");
$gb_stmt->execute([$project_id]);
foreach ($gb_stmt->fetchAll(PDO::FETCH_ASSOC) as $b) { $builds_by_platform[$b['platform']] = $b; }
$platforms_list = array_values($platforms_saved);

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

$lang_options = [
    'English',
    'العربية',
    'Български',
    'Magyar',
    'Tiếng Việt',
    'Ελληνικά',
    'Dansk',
    'עברית',
    'Bahasa Indonesia',
    'Español',
    'Italiano',
    '中文',
    '한국어',
    'Deutsch',
    'Nederlands',
    'Norsk',
    'فارسی',
    'Polski',
    'Português',
    'Română',
    'Русский',
    'ไทย',
    'Türkçe',
    'Українська',
    'Suomi',
    'Français',
    'हिन्दी',
    'Čeština',
    'Svenska',
    '日本語'
];
$langs_saved  = array_map('trim', explode(',', $game['languages'] ?? ''));

function ev(array $a, string $k): string { return htmlspecialchars($a[$k] ?? ''); }

// Автосохранение — только для черновика: сохранение опубликованной/одобренной игры возвращает её на модерацию
$ed_autosave = ($game['status'] ?? '') !== 'published' && ($game['moderation_status'] ?? 'draft') === 'draft';

// ── Вкладки: «Общие» + платформы ──
$ed_tab = (string)($_GET['tab'] ?? '');
if (!in_array($ed_tab, array_merge(['general'], array_column(ED_PLAT, 'id')), true)) {
    $ed_tab = 'general';
    if (isset($_GET['dpxtoken'])) { // токен deplex создаётся из вкладки десктопа — туда и возвращаем
        $ed_tab = 'windows';
        foreach (ED_DESKTOP as $pl) if (in_array($pl, $platforms_saved, true)) { $ed_tab = ED_PLAT[$pl]['id']; break; }
    }
}

// Требования: сохранённые по платформе; для старых проектов — подхватываем games.requirements
// в первую включённую платформу (совпавшие подписи — в поля, остальное — в «Дополнительно»).
$legacy_req = [];
foreach (json_decode($game['requirements'] ?? '[]', true) ?: [] as $r) {
    if (is_array($r) && ($r['value'] ?? '') !== '') $legacy_req[(string)($r['label'] ?? '')] = (string)$r['value'];
}
$req_vals = [];
foreach (array_keys(ED_PLAT) as $pl) {
    $saved = json_decode($builds_by_platform[$pl]['requirements'] ?? '', true) ?: [];
    $req_vals[$pl] = array_column($saved, 'value', 'key');
    if (!$saved && $legacy_req && in_array($pl, $platforms_saved, true)) {
        $rest = $legacy_req;
        foreach (ed_req_fields($pl) as $k => [$label]) {
            if (isset($rest[$label])) { $req_vals[$pl][$k] = $rest[$label]; unset($rest[$label]); }
        }
        if ($rest) $req_vals[$pl]['other'] = implode('; ', array_map(fn($l, $v) => $l ? "$l: $v" : $v, array_keys($rest), $rest));
        $legacy_req = [];
    }
}
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
<?php if (!empty($game['hidden'])): ?><span class="badge badge-draft">Скрыта</span><?php endif; ?>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/g/<?= $project_id ?>" target="_blank" class="btn btn-g" style="padding:6px 14px;font-size:12px;">
            <span class="material-icons" style="font-size:15px;">open_in_new</span>Открыть
        </a>
        <?php if ($game['status'] === 'draft' && $game['moderation_status'] === 'pending'): ?>
        <span class="btn btn-g" style="padding:6px 14px;font-size:12px;opacity:.6;cursor:default;">
            <span class="material-icons" style="font-size:15px;">hourglass_top</span>На модерации
        </span>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Отменить отправку на модерацию? Голоса экспертов сбросятся, зато можно будет редактировать.')">
            <input type="hidden" name="action" value="cancel_moderation">
            <button type="submit" class="btn btn-g" style="padding:6px 14px;font-size:12px;">
                <span class="material-icons" style="font-size:15px;">undo</span>Отменить отправку
            </button>
        </form>
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
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="toggle_hidden">
            <?php if (!empty($game['hidden'])): ?>
            <button type="submit" class="btn btn-p" style="padding:6px 14px;font-size:12px;">
                <span class="material-icons" style="font-size:15px;">visibility</span>Показать в магазине
            </button>
            <?php else: ?>
            <button type="submit" class="btn btn-g" style="padding:6px 14px;font-size:12px;">
                <span class="material-icons" style="font-size:15px;">visibility_off</span>Скрыть из магазина
            </button>
            <?php endif; ?>
        </form>
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

<!-- Форма сохранения пустая: поля разбросаны по вкладкам и привязаны к ней атрибутом form="save-form".
     Так внутри вкладок можно держать свои формы (анонс, джем, токен deplex) без вложенных <form>. -->
<form method="POST" enctype="multipart/form-data" id="save-form">
    <input type="hidden" name="action" value="save">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" id="ed-tab-input" value="<?= $ed_tab ?>">
</form>

<nav class="ed-tabs" role="tablist">
    <button type="button" class="ed-tab<?= $ed_tab === 'general' ? ' on' : '' ?>" data-tab="general">
        <span class="material-icons">tune</span>Общие
    </button>
    <span class="ed-tabs-sep"></span>
    <?php foreach (ED_PLAT as $pl => $m):
        $on = in_array($pl, $platforms_saved, true);
        $hasB = !empty($builds_by_platform[$pl]['build_url']); ?>
    <button type="button" class="ed-tab<?= $ed_tab === $m['id'] ? ' on' : '' ?><?= $on ? '' : ' off' ?>" data-tab="<?= $m['id'] ?>">
        <span class="material-icons"><?= $m['icon'] ?></span><?= $pl ?>
        <span class="ed-dot"<?= $hasB ? '' : ' hidden' ?> title="Билд загружен"></span>
    </button>
    <?php endforeach; ?>
</nav>

<!-- ═══════════════ ОБЩИЕ ═══════════════ -->
<section class="ed-pane" data-tab="general"<?= $ed_tab === 'general' ? '' : ' hidden' ?>>
<div class="ed-cols">
  <div class="ed-col">

    <div class="card" id="c-basic">
        <div class="card-title"><span class="material-icons">info</span>Основная информация</div>
        <div class="grid-2">
            <div class="field col-full">
                <label>Название *</label>
                <input type="text" name="name" form="save-form" value="<?= ev($game,'name') ?>" required maxlength="64">
            </div>
            <div class="field">
                <label>Жанр</label>
                <select name="genre" form="save-form">
                    <?php foreach (['Платформер','RPG','Аркада','Стратегия','Головоломка','Экшн','Симулятор','Хоррор','Приключение','Визуальная новелла','Файтинг','Гонки','Другое'] as $g): ?>
                    <option<?= $game['genre'] === $g ? ' selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Возрастной рейтинг</label>
                <select name="age_rating" form="save-form">
                    <?php foreach (['0+','6+','12+','16+','18+'] as $a): ?>
                    <option<?= $game['age_rating'] === $a ? ' selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field col-full">
                <label>Краткое описание</label>
                <input type="text" name="short_description" form="save-form" value="<?= ev($game,'short_description') ?>" maxlength="200">
            </div>
            <div class="field col-full">
                <label>Описание</label>
                <textarea name="description" form="save-form" style="min-height:140px;"><?= ev($game,'description') ?></textarea>
            </div>
            <div class="field">
                <label>Дата релиза</label>
                <input type="date" name="release_date" form="save-form" value="<?= ev($game,'release_date') ?>">
            </div>
            <div class="field">
                <label>Сайт проекта</label>
                <input type="url" name="website" form="save-form" value="<?= ev($game,'game_website') ?>" placeholder="https://...">
            </div>
            <div class="field col-full">
                <label>Языки интерфейса</label>
                <div style="position:relative;">
                    <button type="button" id="lang-trigger" class="ed-select">
                        <span id="lang-label"><?= !empty(array_filter($langs_saved)) ? htmlspecialchars(implode(', ', array_filter($langs_saved))) : 'Выберите языки...' ?></span>
                        <span id="lang-arrow" style="font-size:10px;transition:transform .2s;flex-shrink:0;margin-left:8px;">▾</span>
                    </button>
                    <div id="lang-menu" class="ed-menu" style="display:none;">
                        <?php foreach ($lang_options as $lang): ?>
                        <label>
                            <input type="checkbox" name="languages_list[]" value="<?= $lang ?>" style="accent-color:var(--p);width:14px;height:14px;flex-shrink:0;"
                                <?= in_array($lang, $langs_saved) ? 'checked' : '' ?>>
                            <?= $lang ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="languages" form="save-form" id="languages-hidden" value="<?= ev($game,'languages') ?>">
            </div>
        </div>
    </div>

    <div class="card" id="c-trailer">
        <div class="card-title"><span class="material-icons">movie</span>Трейлер</div>
        <div class="field">
            <label>Ссылка на трейлер</label>
            <input type="url" name="trailer_url" form="save-form" id="trailer-url-input"
                   value="<?= ev($game,'trailer_url') ?>" placeholder="https://...">
            <div style="font-size:11px;color:var(--tm);margin-top:4px;">
                Видео на YouTube могут не загружаться у пользователей без VPN
            </div>
        </div>
        <?php $trailerPreviewHtml = trailer_embed($game['trailer_url'] ?? ''); ?>
        <style>#trailer-embed-box iframe{position:absolute;inset:0;width:100%;height:100%;border:none;}</style>
        <div id="trailer-preview" style="<?= $trailerPreviewHtml === '' ? 'display:none;' : '' ?>margin-top:10px;position:relative;width:100%;aspect-ratio:16/9;border-radius:10px;overflow:hidden;border:1px solid var(--elev);">
            <div id="trailer-embed-box" style="position:absolute;inset:0;"><?= $trailerPreviewHtml ?></div>
        </div>
    </div>

    <div class="card" id="screenshots-card">
        <div class="card-title" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:8px;">
                <span class="material-icons">photo_library</span>Скриншоты витрины
            </span>
            <span id="scr-count-lbl" style="font-size:11px;color:var(--tm);"><?= count($screenshots) ?> / 10</span>
        </div>
        <div style="font-size:11px;color:var(--tm);margin:-6px 0 12px;">Галерея на странице игры. Скриншоты под конкретную платформу — на её вкладке.</div>
        <div id="scr-grid" class="ed-scr-grid">
            <?php foreach ($screenshots as $scr): ?>
            <div class="scr-item ed-scr" data-url="<?= htmlspecialchars($scr['path'] ?? '') ?>">
                <img src="<?= htmlspecialchars($scr['path'] ?? '') ?>">
                <button type="button" onclick="deleteScreenshot(this)">×</button>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="scr-drop" class="ed-drop">
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

  </div>

  <div class="ed-col">

    <div class="card" id="c-media">
        <div class="card-title"><span class="material-icons">image</span>Медиа</div>
        <div class="ed-sub">Обложка <span>· 460×215</span></div>
        <?php if (!empty($game['path_to_cover'])): ?>
        <img src="<?= ev($game,'path_to_cover') ?>" id="current-cover"
             style="width:100%;border-radius:8px;margin-bottom:8px;object-fit:cover;max-height:160px;">
        <?php endif; ?>
        <label for="cover_art" class="ed-drop ed-drop-sm" style="margin-bottom:16px;">
            <span class="material-icons" style="font-size:22px;color:var(--p);display:block;margin-bottom:4px;">add_photo_alternate</span>
            <span style="font-size:12px;color:var(--ts);display:block;"><?= !empty($game['path_to_cover']) ? 'Заменить обложку' : 'Загрузить обложку' ?></span>
        </label>
        <input type="file" id="cover_art" name="cover_art" accept="image/*" style="display:none;" form="save-form">
        <div id="cover_wrap" style="display:none;margin-bottom:16px;">
            <img id="cover_prev" src="" style="width:100%;border-radius:8px;object-fit:cover;max-height:120px;">
        </div>

        <div class="ed-sub">Иконка <span>· 512×512 · общая для всех платформ</span></div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div id="icon-preview-wrap" style="<?= empty($game['icon_url']) ? 'display:none;' : '' ?>flex-shrink:0;">
                <img id="current-icon" src="<?= ev($game,'icon_url') ?>" class="ed-icon">
            </div>
            <label for="icon_art" class="ed-drop ed-drop-sm" style="flex:1;">
                <span class="material-icons" style="font-size:20px;color:var(--p);display:block;margin-bottom:4px;">add_photo_alternate</span>
                <span style="font-size:11px;color:var(--ts);display:block;" id="icon-label">
                    <?= !empty($game['icon_url']) ? 'Заменить иконку' : 'Загрузить иконку' ?>
                </span>
            </label>
        </div>
        <input type="file" id="icon_art" name="icon_art" accept="image/*" style="display:none;" form="save-form">
    </div>

    <div class="card">
        <div class="card-title"><span class="material-icons">visibility</span>Просмотр страницы</div>
        <p style="font-size:12px;color:var(--tm);margin-bottom:10px;line-height:1.5;">
            Неопубликованная или скрытая игра доступна тебе всегда, другим — по временной ссылке (7 дней).
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="/g/<?= $project_id ?>" target="_blank" class="btn btn-g" style="font-size:12px;">
                <span class="material-icons" style="font-size:15px;">open_in_new</span>Открыть
            </a>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="make_preview_link">
                <button type="submit" class="btn btn-g" style="font-size:12px;">
                    <span class="material-icons" style="font-size:15px;">link</span><?= !empty($game['preview_token']) ? 'Обновить ссылку' : 'Ссылка для просмотра' ?>
                </button>
            </form>
        </div>
        <?php if (!empty($game['preview_token']) && !empty($game['preview_expires']) && strtotime($game['preview_expires']) > time()): ?>
        <div style="margin-top:12px;padding:10px 12px;background:rgba(195,33,120,.06);border:1px solid rgba(195,33,120,.2);border-radius:8px;">
            <div style="font-size:11px;color:var(--tm);margin-bottom:6px;">Временная ссылка (до <?= date('d.m.Y H:i', strtotime($game['preview_expires'])) ?>):</div>
            <input type="text" readonly onclick="this.select()"
                   value="https://dustore.ru/g/<?= $project_id ?>?preview=<?= htmlspecialchars($game['preview_token']) ?>"
                   class="ed-input" style="font-size:12px;">
        </div>
        <?php endif; ?>
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

    <form method="POST" id="jam-form">
        <input type="hidden" name="action" value="save_jam">
        <div class="card" style="border-color:rgba(195,33,120,.25);">
            <div class="card-title">
                <span class="material-icons" style="color:var(--p);">sports_esports</span>Участие в джеме
            </div>
            <?php if ($jam_locked && $current_sprint): ?>
                <div style="font-size:12px;color:var(--ts);margin-bottom:6px;">
                    Проект участвует в джеме <strong><?= htmlspecialchars($current_sprint['title']) ?></strong>.
                </div>
                <div style="font-size:11px;color:var(--tm);">Идёт голосование или джем завершён — привязку менять нельзя.</div>
            <?php else: ?>
                <p style="font-size:11px;color:var(--tm);margin-bottom:12px;line-height:1.5;">
                    Прикрепите проект к джему — после модерации он появится на странице голосования.
                </p>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px;">
                    <input type="checkbox" name="jam_enabled" id="jam-enabled" value="1"
                           onchange="document.getElementById('jam-select-wrap').style.display=this.checked?'':'none';"
                           style="accent-color:var(--p);width:16px;height:16px;"
                           <?= $current_sprint ? 'checked' : '' ?>>
                    <span style="font-size:13px;font-weight:600;">Участвует в джеме</span>
                </label>
                <div class="field" id="jam-select-wrap" style="<?= $current_sprint ? '' : 'display:none;' ?>">
                    <label>Джем</label>
                    <select name="sprint_id" style="width:100%;">
                        <?php $listed = false; foreach ($open_jams as $j):
                            $sel = $current_sprint && (int)$current_sprint['id'] === (int)$j['id'];
                            if ($sel) $listed = true; ?>
                        <option value="<?= (int)$j['id'] ?>"<?= $sel ? ' selected' : '' ?>><?= htmlspecialchars($j['title']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($current_sprint && !$listed): ?>
                        <option value="<?= (int)$current_sprint['id'] ?>" selected><?= htmlspecialchars($current_sprint['title']) ?> (текущий)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;margin-top:12px;font-size:12px;">
                    <span class="material-icons" style="font-size:15px;">save</span>Сохранить участие
                </button>
            <?php endif; ?>
        </div>
    </form>

  </div>
</div>

<?php if ($is_owner): ?>
<form method="POST" id="delete-form"
      onsubmit="return confirm('Удалить проект «<?= htmlspecialchars(addslashes($game['name'])) ?>»?\n\nЭто действие нельзя отменить.')">
    <input type="hidden" name="action" value="delete">
    <div class="card" style="border-color:rgba(255,61,113,.25);margin-top:14px;">
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
</section>

<!-- ═══════════════ ПЛАТФОРМЫ ═══════════════ -->
<?php foreach (ED_PLAT as $pl => $m):
    $b         = $builds_by_platform[$pl] ?? null;
    $on        = in_array($pl, $platforms_saved, true);
    $isMobile  = in_array($pl, ED_MOBILE, true);
    $hasBuild  = !empty($b['build_url']);
    $isActive  = $hasBuild && ($game['game_zip_url'] ?? '') === $b['build_url'];
    $buildSize = !empty($b['build_size']) ? round($b['build_size'] / 1048576, 1) . ' МБ' : '';
    $buildScr  = $b ? (json_decode($b['screenshots'] ?? '[]', true) ?: []) : [];
    $plH       = htmlspecialchars($pl);
?>
<section class="ed-pane" data-tab="<?= $m['id'] ?>" data-plat="<?= $plH ?>"<?= $ed_tab === $m['id'] ? '' : ' hidden' ?>>

    <label class="card ed-switch">
        <input type="checkbox" class="ed-plat-on" name="platform[]" value="<?= $plH ?>" form="save-form"<?= $on ? ' checked' : '' ?>>
        <span class="ed-switch-ui"></span>
        <span class="material-icons ed-switch-ico"><?= $m['icon'] ?></span>
        <span>
            <b>Выпускать на <?= $plH ?></b>
            <small>Игра появится в магазине с этой платформой после «Сохранить» и модерации</small>
        </span>
    </label>

    <div class="ed-off card"<?= $on ? ' hidden' : '' ?>>
        <span class="material-icons"><?= $m['icon'] ?></span>
        <div>Платформа выключена. Включите её, чтобы загрузить билд, скриншоты, иконку и требования.</div>
        <?php if ($hasBuild): ?><div style="margin-top:6px;">Загруженный ранее билд<?= $buildSize ? " ({$buildSize})" : '' ?> сохранится.</div><?php endif; ?>
    </div>

    <div class="ed-plat-body"<?= $on ? '' : ' hidden' ?>>
    <div class="ed-cols">
      <div class="ed-col">

        <div class="card">
            <div class="card-title"><span class="material-icons">folder_zip</span>Билд для <?= $plH ?></div>

            <?php if ($pl === 'iOS'):
                $iosVers = [];
                try {
                    require_once(__DIR__ . '/../swad/controllers/ios_source.php');
                    ios_ensure_tables($conn);
                    $iv = $conn->prepare("SELECT bundle_id, version, build, created_at FROM ios_app_versions WHERE game_id = ?");
                    $iv->execute([$project_id]);
                    $iosVers = $iv->fetchAll(PDO::FETCH_ASSOC);
                    usort($iosVers, fn($x, $y) => ios_is_newer($x['version'], $x['build'], $y['version'], $y['build']) ? -1 : 1);
                } catch (\Throwable $e) { error_log('edit.php ios: ' . $e->getMessage()); }
            ?>
            <div style="font-size:11px;color:var(--tm);margin-bottom:10px;line-height:1.5;">
                Дистрибуция через AltStore: загрузите <b>неподписанный</b> .ipa — bundle id, версия и minOS
                прочитаются из самого файла, а приложение появится в источнике
                <code>https://dustore.ru/source.json</code>. Для обновления загрузите .ipa с бо́льшей версией
                или номером сборки. Приложение видно в источнике, когда игра опубликована.
            </div>
            <?php if ($iosVers): ?>
            <div style="font-size:12px;color:var(--ts);margin-bottom:12px;line-height:1.7;">
                <b><?= htmlspecialchars($iosVers[0]['bundle_id']) ?></b>
                <?php foreach (array_slice($iosVers, 0, 5) as $k => $iv2): ?>
                <div style="<?= $k ? 'color:var(--tm);' : '' ?>">
                    v<?= htmlspecialchars($iv2['version']) ?> (<?= htmlspecialchars($iv2['build']) ?>) · <?= htmlspecialchars(substr($iv2['created_at'], 0, 10)) ?><?= $k ? '' : ' · текущая' ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="alert alert-ok ed-build-state"<?= $hasBuild ? '' : ' hidden' ?> style="margin-bottom:12px;">
                <span class="material-icons" style="font-size:18px;">check_circle</span>
                <div><span class="ed-build-text">Билд загружен<?= $buildSize ? " · {$buildSize}" : '' ?></span>
                    <?php if ($isActive): ?><div style="font-size:11px;opacity:.8;margin-top:2px;">Сейчас раздаётся по кнопке «Скачать»</div><?php endif; ?>
                </div>
            </div>

            <div class="ed-drop ed-drop-build" data-plat="<?= $plH ?>" data-accept="<?= $m['accept'] ?>">
                <span class="material-icons" style="font-size:32px;color:<?= $pl === 'Android' ? '#a4c639' : 'var(--p)' ?>;display:block;margin-bottom:8px;"><?= $m['icon'] ?></span>
                <span style="font-size:13px;color:var(--ts);display:block;">
                    Перетащите <?= $m['accept'] ?> сюда или нажмите, чтобы <?= $hasBuild ? 'заменить билд' : 'выбрать файл' ?>
                </span>
                <span style="font-size:11px;color:var(--tm);display:block;margin-top:4px;">
                    любой размер — собираем в один файл на S3
                </span>
            </div>
            <input type="file" class="plat-input" data-plat="<?= $plH ?>" accept="<?= $m['accept'] ?>" style="display:none;">
            <div class="plat-progress" style="display:none;margin-top:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span class="plat-status" style="font-size:12px;color:var(--ts);">Подготовка...</span>
                    <span class="plat-pct" style="font-size:12px;font-weight:600;color:var(--tm);">0%</span>
                </div>
                <div style="height:8px;background:var(--elev);border-radius:4px;overflow:hidden;">
                    <div class="plat-bar" style="height:100%;background:var(--p);border-radius:4px;width:0%;transition:width .3s;"></div>
                </div>
            </div>
            <?php if (count($platforms_saved) > 1 && $pl !== 'iOS'): ?>
            <div style="font-size:11px;color:var(--tm);margin-top:10px;line-height:1.5;">
                Пока кнопка «Скачать» на странице игры отдаёт один файл — тот, что загружен последним на любой вкладке.
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title" style="justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:8px;"><span class="material-icons">photo_library</span>Скриншоты <?= $plH ?></span>
                <span style="font-size:11px;color:var(--tm);"><?= $isMobile ? 'вертикальные 9:16' : '16:9' ?> · до 10</span>
            </div>
            <div class="plat-scr-grid ed-scr-grid<?= $isMobile ? ' v' : '' ?>" data-plat="<?= $plH ?>">
                <?php foreach ($buildScr as $scr): ?>
                <div class="plat-scr-item ed-scr" data-url="<?= htmlspecialchars($scr['path'] ?? '') ?>">
                    <img src="<?= htmlspecialchars($scr['path'] ?? '') ?>">
                    <button type="button" class="plat-scr-del" data-plat="<?= $plH ?>">×</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="ed-drop ed-drop-sm ed-drop-scr" data-plat="<?= $plH ?>">
                <span class="material-icons" style="font-size:20px;color:var(--p);display:block;margin-bottom:2px;">add_photo_alternate</span>
                <span style="font-size:12px;color:var(--ts);">Перетащите скриншоты или нажмите</span>
            </div>
            <input type="file" class="plat-scr-input" data-plat="<?= $plH ?>" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;">
        </div>

      </div>
      <div class="ed-col">

        <div class="card">
            <div class="card-title"><span class="material-icons">apps</span>Иконка <?= $plH ?></div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="plat-icon-wrap" style="<?= empty($b['icon_url']) ? 'display:none;' : '' ?>flex-shrink:0;">
                    <img class="plat-icon-img ed-icon" src="<?= htmlspecialchars($b['icon_url'] ?? '') ?>">
                </div>
                <label class="ed-drop ed-drop-sm" style="flex:1;">
                    <span class="material-icons" style="font-size:18px;color:var(--p);display:block;margin-bottom:2px;">add_photo_alternate</span>
                    <span style="font-size:11px;color:var(--ts);">Необязательно — иначе общая</span>
                    <input type="file" class="plat-icon-input" data-plat="<?= $plH ?>" accept="image/*" style="display:none;">
                </label>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="material-icons">memory</span>Системные требования</div>
            <?php foreach (ed_req_fields($pl) as $k => [$label, $ph]): ?>
            <div class="field">
                <label><?= $label ?></label>
                <input type="text" name="req[<?= $plH ?>][<?= $k ?>]" form="save-form" maxlength="300"
                       value="<?= htmlspecialchars($req_vals[$pl][$k] ?? '') ?>" placeholder="<?= htmlspecialchars($ph) ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pl === 'Windows'): ?>
        <div class="card">
            <div class="card-title"><span class="material-icons">play_circle</span>Запуск</div>
            <div class="field">
                <label>Исполняемый файл</label>
                <input type="text" name="game_exec" form="save-form" value="<?= ev($game,'game_exec') ?>" placeholder="Game.exe">
                <div style="font-size:11px;color:var(--tm);margin-top:4px;">Путь внутри архива — по нему игру запускает DustoreX</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isMobile): ?>
        <div class="card">
            <div class="card-title"><span class="material-icons">admin_panel_settings</span>Разрешения</div>
            <div class="field">
                <textarea name="perm[<?= $plH ?>]" form="save-form" rows="4"
                          placeholder="По одному на строку: камера, геолокация, микрофон..."><?= htmlspecialchars($b['permissions'] ?? '') ?></textarea>
            </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
    </div>
</section>
<?php endforeach; ?>

<!-- deplex — для десктопных вкладок; один экземпляр, показывается под активной вкладкой -->
<div class="card" id="ed-deplex" hidden style="margin-bottom:16px;">
    <div class="card-title"><span class="material-icons">terminal</span>Загрузка через deplex (CLI)</div>
    <div style="font-size:11px;color:var(--tm);margin:-6px 0 12px;line-height:1.5;">
        Для больших десктопных билдов и частых обновлений: deplex заливает только изменившиеся куски.
    </div>
    <?php include(__DIR__ . '/../swad/controllers/devs_edit_deplex_tab.php'); ?>
</div>

<div class="ed-savebar" id="ed-savebar">
    <span id="ed-dirty">Файлы сохраняются сразу, поля — по кнопке</span>
    <a href="/devs/projects" class="btn btn-g" style="font-size:12px;">← К проектам</a>
    <button type="submit" form="save-form" class="btn btn-p"><span class="material-icons">save</span>Сохранить</button>
</div>

<style>
    /* редактор проекта — всё под .ed-* */
    .ed-tabs {
        position: sticky; top: -20px; z-index: 5;
        display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none;
        margin: 0 -20px 16px; padding: 10px 20px;
        background: rgba(20, 4, 29, .92); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
        border-bottom: 1px solid var(--bd);
    }
    .ed-tab {
        display: inline-flex; align-items: center; gap: 6px; flex: none;
        padding: 7px 12px; border-radius: 8px; border: 1px solid transparent;
        background: none; color: var(--ts); font: 600 12px 'Inter', sans-serif; cursor: pointer; white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .ed-tab .material-icons { font-size: 16px; }
    .ed-tab:hover { background: var(--elev); color: #fff; }
    .ed-tab.on { background: rgba(195, 33, 120, .14); border-color: rgba(195, 33, 120, .35); color: var(--pl); }
    .ed-tab.off { color: var(--tm); }
    .ed-tab.off .material-icons { opacity: .5; }
    .ed-tab.off.on { color: var(--ts); }
    .ed-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ok); }
    .ed-dot[hidden] { display: none; }
    .ed-tabs-sep { width: 1px; margin: 4px; background: var(--bd); flex: none; }

    .ed-cols { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 14px; align-items: start; }
    .ed-col { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
    /* hidden должен побеждать display у .alert/.card внутри вкладок */
    .ed-pane [hidden], #ed-deplex[hidden] { display: none !important; }
    .ed-pane { padding-bottom: 16px; }

    /* панель сохранения прижата к низу окна даже на короткой вкладке */
    .ds-content:has(> .ed-savebar) { display: flex; flex-direction: column; }
    .ds-content:has(> .ed-savebar) > * { flex-shrink: 0; }

    .ed-switch { position: relative; display: flex; align-items: center; gap: 14px; cursor: pointer; margin-bottom: 14px; }
    .ed-switch input { position: absolute; opacity: 0; pointer-events: none; }
    .ed-switch-ui {
        position: relative; flex: none; width: 40px; height: 22px; border-radius: 11px;
        background: var(--elev); border: 1px solid rgba(255, 255, 255, .12); transition: background .18s, border-color .18s;
    }
    .ed-switch-ui::after {
        content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
        background: var(--tm); transition: transform .18s, background .18s;
    }
    .ed-switch input:checked + .ed-switch-ui { background: var(--p); border-color: var(--p); }
    .ed-switch input:checked + .ed-switch-ui::after { transform: translateX(18px); background: #fff; }
    .ed-switch input:focus-visible + .ed-switch-ui { outline: 2px solid var(--pl); outline-offset: 2px; }
    .ed-switch-ico { font-size: 26px; color: var(--tm); }
    .ed-switch input:checked ~ .ed-switch-ico { color: var(--pl); }
    .ed-switch b { display: block; font-size: 14px; }
    .ed-switch small { font-size: 11px; color: var(--tm); }

    .ed-off { text-align: center; padding: 40px 20px; color: var(--tm); font-size: 13px; line-height: 1.5; }
    .ed-off .material-icons { font-size: 40px; opacity: .4; display: block; margin-bottom: 10px; }

    .ed-drop {
        display: block; border: 2px dashed rgba(195, 33, 120, .3); border-radius: 12px; padding: 28px;
        text-align: center; cursor: pointer; transition: border-color .2s, background .2s;
    }
    .ed-drop-sm { padding: 14px; border-radius: 10px; }
    .ed-drop:hover, .ed-drop.over { border-color: var(--p); background: rgba(195, 33, 120, .06); }
    .ed-drop.bad { border-color: var(--err); background: rgba(255, 61, 113, .06); }

    .ed-scr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 12px; }
    .ed-scr-grid.v { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
    .ed-scr-grid:not(:has(.ed-scr)) { display: none; }
    .ed-scr { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 16/9; background: var(--elev); }
    .ed-scr-grid.v .ed-scr { aspect-ratio: 9/16; }
    .ed-scr img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .ed-scr button {
        position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border: 0; border-radius: 6px;
        background: rgba(0, 0, 0, .7); color: #fff; cursor: pointer; font-size: 15px; line-height: 1;
    }
    .ed-icon { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 1px solid var(--bd); display: block; }
    .ed-sub { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--tm); margin-bottom: 8px; }
    .ed-sub span { font-weight: 400; text-transform: none; letter-spacing: 0; }
    .ed-input { width: 100%; background: var(--elev); border: 1px solid var(--bd); border-radius: 8px; padding: 8px 10px; color: var(--ts); }
    .ed-select {
        width: 100%; min-height: 38px; display: flex; align-items: center; justify-content: space-between;
        background: var(--elev); border: 1px solid var(--bd); border-radius: 8px; padding: 8px 12px;
        color: var(--tt); font: 13px 'Inter', sans-serif; text-align: left; cursor: pointer;
    }
    .ed-menu {
        position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0; max-height: 280px; overflow-y: auto;
        background: var(--surf); border: 1px solid rgba(255, 255, 255, .1); border-radius: 10px; padding: 4px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .6);
    }
    .ed-menu label { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 6px; cursor: pointer; font-size: 13px; color: var(--ts); }
    .ed-menu label:hover { background: rgba(255, 255, 255, .06); }

    .ed-savebar {
        position: sticky; bottom: -20px; z-index: 5;
        display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        margin: auto -20px -20px; padding: 12px 20px;
        background: rgba(20, 4, 29, .92); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
        border-top: 1px solid var(--bd);
    }
    .ed-savebar[hidden] { display: none; }
    .ed-savebar span { margin-right: auto; font-size: 12px; color: var(--tm); }
    .ed-savebar.dirty span { color: var(--warn); }

    @keyframes fxFlash { 0% { box-shadow: 0 0 0 3px var(--p, #c32178); } 100% { box-shadow: 0 0 0 3px transparent; } }
    .fx-flash { animation: fxFlash 1.8s ease-out; border-radius: 10px; }

    @media (max-width: 900px) {
        .ed-cols { grid-template-columns: 1fr; }
    }
    @media (max-width: 860px) {
        .ed-tabs { top: -16px; margin: 0 -16px 14px; padding: 10px 16px; }
        .ed-savebar { bottom: -16px; margin: auto -16px -16px; padding: 10px 16px; }
    }
</style>

<script>
(function () {
    var PID = <?= (int)$project_id ?>;
    var IS_LOCKED = <?= (($game['moderation_status'] ?? '') === 'pending') ? 'true' : 'false' ?>;
    var MOBILE = ['Android', 'iOS'], DESKTOP = ['Windows', 'macOS', 'Linux'];
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var paneOf = function (pl) { return $('.ed-pane[data-plat="' + pl + '"]'); };

    // ── Вкладки ────────────────────────────────────────────────────────────
    function edTab(id) {
        $$('.ed-tab').forEach(function (t) { t.classList.toggle('on', t.dataset.tab === id); });
        $$('.ed-pane').forEach(function (p) { p.hidden = p.dataset.tab !== id; });
        $('#ed-tab-input').value = id;
        var u = new URL(location.href);
        u.searchParams.set('tab', id);
        history.replaceState(null, '', u);
        syncDeplex();
    }
    window.edTab = edTab;
    $$('.ed-tab').forEach(function (t) { t.addEventListener('click', function () { edTab(t.dataset.tab); }); });

    // невалидное поле на скрытой вкладке: сначала показываем вкладку, иначе браузер не сможет его подсветить
    document.addEventListener('invalid', function (e) {
        var p = e.target.closest && e.target.closest('.ed-pane');
        if (p && p.hidden) edTab(p.dataset.tab);
    }, true);

    // ── Включение платформы ────────────────────────────────────────────────
    function syncPlat(pane) {
        var on = $('.ed-plat-on', pane).checked;
        $('.ed-plat-body', pane).hidden = !on;
        $('.ed-off', pane).hidden = on;
        $('.ed-tab[data-tab="' + pane.dataset.tab + '"]').classList.toggle('off', !on);
        syncDeplex();
    }
    $$('.ed-plat-on').forEach(function (cb) {
        cb.addEventListener('change', function () { syncPlat(cb.closest('.ed-pane')); });
    });

    function syncDeplex() {
        var pane = $('.ed-pane:not([hidden])');
        var pl = pane && pane.dataset.plat;
        $('#ed-deplex').hidden = !(pl && DESKTOP.indexOf(pl) !== -1 && $('.ed-plat-on', pane).checked);
    }
    syncDeplex();

    // ── Сохранение ─────────────────────────────────────────────────────────
    // Черновик сохраняется сам через 1.5 с после последней правки. Опубликованную или
    // одобренную игру — только вручную: сохранение возвращает её на модерацию.
    // В оболочке консоли состояние показывает индикатор слева от дока (клик или Ctrl+S — сохранить),
    // без оболочки — панель внизу страницы.
    var AUTO = <?= $ed_autosave ? 'true' : 'false' ?>;
    var form = $('#save-form'), dirty = false, seq = 0, inflight = null, timer = null;
    var wm = window.parent !== window && window.parent.dsWM && window.parent.dsWM.saver ? window.parent.dsWM : null;
    var LABEL = { saved: 'Все изменения сохранены', dirty: 'Есть изменения — сохраню через секунду', manual: 'Есть несохранённые изменения',
                  saving: 'Сохраняю…', error: 'Не сохранено' };

    function show(state, msg) {
        if (wm) wm.saverState(window, state, msg);
        $('#ed-savebar').classList.toggle('dirty', state !== 'saved');
        $('#ed-dirty').textContent = msg || LABEL[state];
    }

    function setDirty() {
        dirty = true;
        seq++;
        if (!AUTO) return show('manual', 'Есть изменения — после сохранения игра уйдёт на повторную модерацию');
        show('dirty');
        clearTimeout(timer);
        timer = setTimeout(function () { save(false); }, 1500);
    }
    window.edSetDirty = setDirty;

    function save(manual) {
        clearTimeout(timer);
        if (inflight) return inflight.then(function () { if (dirty) return save(manual); });
        if (!form.checkValidity()) {
            if (manual) form.reportValidity();   // ручное сохранение показывает поле, автосохранение не дёргает вкладки
            return Promise.resolve(show('error', 'Заполните обязательные поля'));
        }
        if (!dirty && !manual) return Promise.resolve();
        var my = seq;
        var fd = new FormData(form);              // берёт и поля из вкладок: они привязаны к форме атрибутом form
        fd.append('ajax', '1');
        show('saving');
        inflight = fetch('/devs/edit.php?id=' + PID, { method: 'POST', body: fd, credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) return show('error', d.error || 'Ошибка сохранения');
                ['cover_art', 'icon_art'].forEach(function (id) { $('#' + id).value = ''; }); // файлы уже на S3
                if (!AUTO) {                       // статус игры сменился — перерисовываем шапку
                    dirty = false;
                    location.href = '/devs/edit?id=' + PID + '&saved=1&tab=' + $('#ed-tab-input').value;
                    return;
                }
                if (seq === my) { dirty = false; show('saved', 'Сохранено в ' + d.saved_at); }
                else setDirty();                   // пока сохраняли, успели ещё что-то поменять
            })
            .catch(function () { show('error', 'Нет связи с сервером — нажми, чтобы повторить'); })
            .finally(function () { inflight = null; });
        return inflight;
    }

    ['input', 'change'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { if (e.target.form === form) setDirty(); });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); save(true); });
    document.addEventListener('visibilitychange', function () { if (document.hidden && dirty && AUTO) save(false); });
    window.addEventListener('beforeunload', function (e) { if (dirty || inflight) { e.preventDefault(); e.returnValue = ''; } });
    if (wm && !IS_LOCKED) {
        wm.saver(window, { save: function () { return save(true); } });
        $('#ed-savebar').hidden = true;
    }

    // ── Drag'n'drop ────────────────────────────────────────────────────────
    // Файл, брошенный мимо зоны, браузер иначе откроет вместо страницы
    ['dragover', 'drop'].forEach(function (ev) { window.addEventListener(ev, function (e) { e.preventDefault(); }); });

    function extOk(file, accept) {
        var ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
        return accept.split(',').indexOf(ext) !== -1;
    }
    document.addEventListener('dragover', function (e) {
        var z = e.target.closest && e.target.closest('.ed-drop-build, .ed-drop-scr');
        if (z) z.classList.add('over');
    });
    document.addEventListener('dragleave', function (e) {
        var z = e.target.closest && e.target.closest('.ed-drop-build, .ed-drop-scr');
        if (z && !z.contains(e.relatedTarget)) z.classList.remove('over');
    });
    document.addEventListener('drop', function (e) {
        var z = e.target.closest && e.target.closest('.ed-drop-build, .ed-drop-scr');
        if (!z) return;
        z.classList.remove('over');
        var files = e.dataTransfer.files;
        if (!files.length) return;
        if (z.classList.contains('ed-drop-scr')) return uploadPlatformScreenshots(files, z.dataset.plat);
        if (files.length > 1 || !extOk(files[0], z.dataset.accept)) {
            z.classList.add('bad');
            setTimeout(function () { z.classList.remove('bad'); }, 1200);
            return platErr(paneOf(z.dataset.plat), 'Нужен один файл ' + z.dataset.accept);
        }
        uploadPlatformBuild(files[0], z.dataset.plat);
    });

    document.addEventListener('click', function (e) {
        var z = e.target.closest('.ed-drop-build, .ed-drop-scr');
        if (z) {
            var sel = z.classList.contains('ed-drop-build') ? '.plat-input' : '.plat-scr-input';
            $(sel, paneOf(z.dataset.plat)).click();
            return;
        }
        var del = e.target.closest('.plat-scr-del');
        if (del) deletePlatformScreenshot(del);
    });

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t.classList.contains('plat-input') && t.files[0]) uploadPlatformBuild(t.files[0], t.dataset.plat);
        if (t.classList.contains('plat-icon-input') && t.files[0]) uploadPlatformIcon(t.files[0], t.dataset.plat);
        if (t.classList.contains('plat-scr-input') && t.files.length) uploadPlatformScreenshots(t.files, t.dataset.plat);
        if (t.type === 'file' && !t.form) t.value = '';
    });

    // ── Загрузка билда (механизм прежний: чанки → build_upload.php → один объект в S3) ──
    var uploading = 0;
    async function uploadPlatformBuild(file, platform) {
        var CHUNK = 5 * 1024 * 1024;
        var total = Math.ceil(file.size / CHUNK) || 1;
        var pane = paneOf(platform);
        if (!pane) return;
        var prog = $('.plat-progress', pane), bar = $('.plat-bar', pane), pct = $('.plat-pct', pane), stat = $('.plat-status', pane);
        prog.style.display = 'block';
        bar.style.width = '0%';
        bar.style.background = platform === 'Android' ? '#a4c639' : 'var(--p)';
        stat.style.color = '';
        uploading++;
        try {
            for (var i = 0; i < total; i++) {
                var fd = new FormData();
                fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK));
                fd.append('chunk_index', i);
                fd.append('total_chunks', total);
                fd.append('file_name', file.name);
                fd.append('file_size', file.size);
                fd.append('project_id', PID);
                fd.append('platform', platform);
                var data;
                try {
                    var res = await fetch('/devs/build_upload.php', { method: 'POST', body: fd, credentials: 'include' });
                    data = JSON.parse(await res.text());
                } catch (err) {
                    return platErr(pane, 'Сервер вернул не-JSON: ' + String(err).substring(0, 160));
                }
                if (!data.success) return platErr(pane, data.message || 'Ошибка сервера');
                if (data.done) {
                    bar.style.width = '100%'; pct.textContent = '100%';
                    bar.style.background = 'var(--ok)'; stat.style.color = 'var(--ok)';
                    stat.textContent = '✓ Билд загружен · ' + data.size_mb + ' МБ' +
                        (data.ios ? ' · ' + data.ios.bundle_id + ' v' + data.ios.version + ' (' + data.ios.build + ') добавлен в source.json' : '');
                    $('.ed-build-state', pane).hidden = false;
                    $('.ed-build-text', pane).textContent = 'Билд загружен · ' + data.size_mb + ' МБ';
                    $('.ed-tab[data-tab="' + pane.dataset.tab + '"] .ed-dot').hidden = false;
                    return;
                }
                var p = Math.round((i + 1) / total * 100);
                bar.style.width = p + '%'; pct.textContent = p + '%';
                stat.textContent = 'Загрузка ' + (i + 1) + ' из ' + total + ' · не закрывайте окно';
            }
        } finally { uploading--; }
    }
    window.addEventListener('beforeunload', function (e) { if (uploading) { e.preventDefault(); e.returnValue = ''; } });

    function platErr(pane, msg) {
        var prog = $('.plat-progress', pane), bar = $('.plat-bar', pane), stat = $('.plat-status', pane);
        prog.style.display = 'block';
        bar.style.background = 'var(--err)';
        stat.textContent = '✗ ' + msg; stat.style.color = 'var(--err)';
    }

    async function uploadPlatformIcon(file, platform) {
        var pane = paneOf(platform);
        var fd = new FormData();
        fd.append('project_id', PID); fd.append('platform', platform); fd.append('type', 'build_icon'); fd.append('file', file);
        try {
            var data = await (await fetch('/devs/upload_media.php', { method: 'POST', body: fd, credentials: 'include' })).json();
            if (data.success) { $('.plat-icon-img', pane).src = data.url; $('.plat-icon-wrap', pane).style.display = ''; }
            else alert(data.message || 'Ошибка загрузки иконки');
        } catch (err) { alert('Сетевая ошибка'); }
    }

    async function uploadPlatformScreenshots(files, platform) {
        for (var i = 0; i < files.length; i++) {
            var fd = new FormData();
            fd.append('project_id', PID); fd.append('platform', platform); fd.append('type', 'build_screenshot'); fd.append('file', files[i]);
            try {
                var data = await (await fetch('/devs/upload_media.php', { method: 'POST', body: fd, credentials: 'include' })).json();
                if (data.success) renderPlatformScreenshots(platform, data.screenshots);
                else { alert(data.message || 'Ошибка загрузки скриншота'); break; }
            } catch (err) { alert('Сетевая ошибка'); break; }
        }
    }

    function scrItem(url, delCls, plat) {
        var d = document.createElement('div');
        d.className = (delCls ? 'plat-scr-item' : 'scr-item') + ' ed-scr';
        d.dataset.url = url;
        d.innerHTML = '<img><button type="button">×</button>';
        d.firstChild.src = url;
        if (delCls) { d.lastChild.className = 'plat-scr-del'; d.lastChild.dataset.plat = plat; }
        else d.lastChild.setAttribute('onclick', 'deleteScreenshot(this)');
        return d;
    }

    function renderPlatformScreenshots(platform, list) {
        var grid = $('.plat-scr-grid', paneOf(platform));
        grid.innerHTML = '';
        list.forEach(function (s) { grid.append(scrItem(s.path, true, platform)); });
    }

    async function deletePlatformScreenshot(btn) {
        var item = btn.closest('.plat-scr-item'), platform = btn.dataset.plat;
        if (!confirm('Удалить этот скриншот?')) return;
        item.style.opacity = '.4';
        var fd = new FormData();
        fd.append('project_id', PID); fd.append('platform', platform); fd.append('type', 'delete_build_screenshot'); fd.append('url', item.dataset.url);
        try {
            var data = await (await fetch('/devs/upload_media.php', { method: 'POST', body: fd, credentials: 'include' })).json();
            if (data.success) renderPlatformScreenshots(platform, data.screenshots);
            else { item.style.opacity = '1'; alert(data.message || 'Ошибка удаления'); }
        } catch (err) { item.style.opacity = '1'; alert('Сетевая ошибка'); }
    }

    // ── Модалка модерации ──────────────────────────────────────────────────
    var modalAction = 'first';
    window.openModerationModal = function (action) {
        modalAction = action;
        $('#modal-confirm-label').textContent = action === 'resubmit' ? 'Отправить повторно' : 'Отправить на модерацию';
        $('#moderation-modal').style.display = 'flex';
    };
    window.closeModerationModal = function () { $('#moderation-modal').style.display = 'none'; };
    $('#modal-confirm-btn').addEventListener('click', function () {
        closeModerationModal();
        $(modalAction === 'resubmit' ? '#form-resubmit' : '#form-moderation').submit();
    });
    $('#moderation-modal').addEventListener('click', function (e) { if (e.target === this) closeModerationModal(); });

    // ── Анонс ──────────────────────────────────────────────────────────────
    $('#ann-enabled').addEventListener('change', function () { $('#ann-fields').style.display = this.checked ? '' : 'none'; });
    $('#ann-tbd').addEventListener('change', function () { $('#ann-date-wrap').style.display = this.checked ? 'none' : ''; });

    // ── Обложка и иконка: превью ───────────────────────────────────────────
    $('#cover_art').addEventListener('change', function () {
        if (!this.files[0]) return;
        var cur = $('#current-cover');
        if (cur) cur.style.display = 'none';
        $('#cover_prev').src = URL.createObjectURL(this.files[0]);
        $('#cover_wrap').style.display = 'block';
    });
    $('#icon_art').addEventListener('change', function () {
        if (!this.files[0]) return;
        $('#current-icon').src = URL.createObjectURL(this.files[0]);
        $('#icon-preview-wrap').style.display = '';
        $('#icon-label').textContent = 'Заменить иконку';
    });

    // ── Трейлер: превью через тот же trailer_embed(), что и на странице игры ──
    (function () {
        var input = $('#trailer-url-input'), wrap = $('#trailer-preview'), box = $('#trailer-embed-box'), timer = null;
        input.addEventListener('input', function () {
            var val = this.value.trim();
            clearTimeout(timer);
            if (!val) { wrap.style.display = 'none'; box.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch('/devs/edit.php?ajax=trailer_preview&url=' + encodeURIComponent(val))
                    .then(function (r) { return r.json(); })
                    .then(function (data) { box.innerHTML = data.html || ''; wrap.style.display = data.html ? '' : 'none'; })
                    .catch(function () {});
            }, 350);
        });
    })();

    // ── Скриншоты витрины ──────────────────────────────────────────────────
    var drop = $('#scr-drop');
    drop.addEventListener('click', function () { $('#scr-input').click(); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('over'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('over'); uploadScreenshots(e.dataTransfer.files); });
    $('#scr-input').addEventListener('change', function () { uploadScreenshots(this.files); });

    async function uploadScreenshots(files) {
        var bar = $('#scr-bar'), status = $('#scr-status'), prog = $('#scr-progress');
        prog.style.display = ''; bar.style.background = 'var(--p)';
        for (var i = 0; i < files.length; i++) {
            var fd = new FormData();
            fd.append('project_id', PID); fd.append('type', 'screenshot'); fd.append('file', files[i]);
            status.textContent = 'Загружаю ' + (i + 1) + '/' + files.length + ': ' + files[i].name;
            bar.style.width = Math.round(i / files.length * 100) + '%';
            try {
                var data = await (await fetch('/devs/upload_media.php', { method: 'POST', body: fd, credentials: 'include' })).json();
                if (data.success) renderScreenshots(data.screenshots);
                else {
                    status.textContent = '✗ ' + (data.message || 'Ошибка'); status.style.color = 'var(--err)';
                    await new Promise(function (r) { setTimeout(r, 2000); });
                    status.style.color = '';
                }
            } catch (err) { status.textContent = '✗ Сетевая ошибка'; }
        }
        bar.style.width = '100%'; bar.style.background = 'var(--ok)';
        status.textContent = '✓ Готово';
        setTimeout(function () { prog.style.display = 'none'; bar.style.width = '0%'; }, 2000);
    }

    function renderScreenshots(list) {
        var grid = $('#scr-grid');
        grid.innerHTML = '';
        list.forEach(function (s) { grid.append(scrItem(s.path, false)); });
        $('#scr-count-lbl').textContent = list.length + ' / 10';
    }

    window.deleteScreenshot = async function (btn) {
        var item = btn.closest('.scr-item');
        if (!confirm('Удалить этот скриншот?')) return;
        item.style.opacity = '.4';
        var fd = new FormData();
        fd.append('project_id', PID); fd.append('type', 'delete_screenshot'); fd.append('url', item.dataset.url);
        try {
            var data = await (await fetch('/devs/upload_media.php', { method: 'POST', body: fd, credentials: 'include' })).json();
            if (data.success) renderScreenshots(data.screenshots);
            else { item.style.opacity = '1'; alert(data.message || 'Ошибка удаления'); }
        } catch (err) { item.style.opacity = '1'; alert('Сетевая ошибка'); }
    };

    // ── Языки ──────────────────────────────────────────────────────────────
    (function () {
        var trigger = $('#lang-trigger'), menu = $('#lang-menu'), arrow = $('#lang-arrow');
        function show(v) { menu.style.display = v ? 'block' : 'none'; arrow.style.transform = v ? 'rotate(180deg)' : ''; }
        trigger.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); show(menu.style.display === 'none'); });
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { show(false); });
        $$('input', menu).forEach(function (cb) { cb.addEventListener('change', function () { syncLang(); setDirty(); }); });
        function syncLang() {
            var checked = $$('input:checked', menu).map(function (c) { return c.value; });
            $('#lang-label').textContent = checked.length ? checked.join(', ') : 'Выберите языки...';
            $('#languages-hidden').value = checked.join(', ');
        }
        syncLang();
    })();

    // ── Игра на модерации: смотреть можно, менять нельзя ──────────────────
    if (IS_LOCKED) {
        $$('input, textarea, select, button').forEach(function (el) {
            if (el.closest('#moderation-modal') || el.classList.contains('ed-tab')) return;
            var f = el.closest('form');
            if (f && f.querySelector('input[name="action"][value="cancel_moderation"]')) return;
            el.disabled = true;
        });
        $$('.ed-drop, .ed-switch').forEach(function (el) { el.style.pointerEvents = 'none'; el.style.opacity = '.5'; });
        $('#ed-savebar').hidden = true;
    }

    // ── Переход с публичной страницы по шестерёнке: открыть вкладку и подсветить поле ──
    (function () {
        var h = decodeURIComponent(location.hash.replace('#', ''));
        if (!h) return;
        var el = document.getElementById(h) || document.querySelector('[name="' + h.replace(/"/g, '') + '"]');
        if (!el) return;
        var pane = el.closest('.ed-pane');
        if (pane && pane.hidden) edTab(pane.dataset.tab);
        var box = el.closest('.field') || el.closest('.card') || el;
        box.scrollIntoView({ block: 'center' });
        box.classList.add('fx-flash');
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) try { el.focus({ preventScroll: true }); } catch (err) {}
        setTimeout(function () { box.classList.remove('fx-flash'); }, 2200);
    })();
})();
</script>
<?php
require_once(__DIR__ . '/includes/footer.php');
?>
