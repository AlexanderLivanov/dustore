<?php
declare(strict_types=1);

/**
 * api/fx.php — API Fid Core: посты, ленты, реакции, комментарии, опросы, подписки, DustHunt, загрузка фото.
 *
 * Соглашения те же, что в api/friends.php:
 *   • сессионная авторизация ($_SESSION['USERDATA']['id']);
 *   • изменяющие действия — только POST + CSRF (поле csrf, заголовок X-CSRF-Token или JSON-тело);
 *   • наружу уходит КОД ошибки, а не текст исключения; подробности — в error_log.
 *
 * Ответы с карточками содержат готовый HTML (swad/fx/render.php) — клиент разметку не строит.
 */

ob_start();

function fx_fail(string $code, int $status = 400, string $log = ''): void
{
    if ($log !== '') error_log('[api/fx] ' . $log);
    if (ob_get_length()) ob_clean();
    http_response_code($status);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

function fx_out(array $data): void
{
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Код ошибки ядра → HTTP-статус. */
function fx_status(string $code): int
{
    return match ($code) {
        'auth' => 401,
        'forbidden' => 403,
        'not_found', 'no_user' => 404,
        'rate' => 429,
        default => 422,
    };
}

/** Результат ядра ['ok'=>false,'error'=>..] → ответ; ok — возвращаем массив дальше. */
function fx_res(array $r): array
{
    if (empty($r['ok'])) fx_fail((string)($r['error'] ?? 'error'), fx_status((string)($r['error'] ?? '')));
    return $r;
}

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    error_log("[api/fx] PHP [$no] $str in $file:$line");
    if (in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_WARNING, E_USER_WARNING], true)) return true;
    fx_fail('server_error', 500);
    return true;
});
set_exception_handler(static function (Throwable $e): void {
    fx_fail('server_error', 500, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
});

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../swad/fx/render_hub.php';
require_once __DIR__ . '/../swad/controllers/csrf.php';

$viewer = Fx::uid();
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$json = null;
if ($isPost && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === 0) {
    $json = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($json)) fx_fail('bad_json');
}
$in = $json ?? ($isPost ? $_POST : $_GET);
$action = (string)($in['action'] ?? $_GET['action'] ?? '');
if (!empty($in['m']) || !empty($_GET['m'])) Fx::mobile(true);   // запрос из PWA: ссылки в карточках ведут в /m

const FX_MUTATING = [
    'create', 'edit', 'delete', 'pin', 'lock', 'react', 'comment', 'comment_delete', 'comment_like',
    'vote', 'follow', 'report', 'hunt_join', 'hunt_leave', 'upload', 'views',
];
if (in_array($action, FX_MUTATING, true)) {
    if (!$isPost) fx_fail('method', 405);
    if (!csrf_valid($json)) fx_fail('csrf', 403);
    if ($viewer <= 0 && $action !== 'views') fx_fail('auth', 401);
}

/* Контекст рендера, который клиент присылает вместе с запросом ленты/создания. */
function fx_ctx(array $in, int $viewer): array
{
    $wt = (string)($in['wall_type'] ?? '');
    $wid = (int)($in['wall_id'] ?? 0);
    $ch = (string)($in['channel'] ?? 'wall');
    $ctx = ['viewer' => $viewer, 'show_reason' => ($in['scope'] ?? '') !== 'wall'];
    if (in_array($wt, ['game', 'studio', 'user'], true)) {
        $ctx['wall'] = ['type' => $wt, 'id' => $wid, 'channel' => $ch];
        if ($wt === 'game') $ctx['wall_game'] = $wid;
        if ($wt === 'game' && $ch === 'forum') $ctx['threads'] = true;
    }
    return $ctx;
}

switch ($action) {

    /* ============================================================ ЧТЕНИЕ */

    case 'feed': {
        $scope = (string)($in['scope'] ?? 'general');
        $cursor = isset($in['cursor']) && $in['cursor'] !== '' ? (string)$in['cursor'] : null;

        if ($scope === 'friends') {
            $r = FxFeed::friends($viewer, $cursor);
        } elseif ($scope === 'general') {
            $r = FxFeed::general($viewer, (int)$cursor);
        } elseif ($scope === 'wall') {
            $wt = (string)($in['wall_type'] ?? '');
            $wid = (int)($in['wall_id'] ?? 0);
            $ch = (string)($in['channel'] ?? 'wall');
            $exists = match ($wt) { 'game' => FxPeople::game($wid), 'studio' => FxPeople::studio($wid), 'user' => FxPeople::user($wid), default => null };
            if (!$exists) fx_fail('not_found', 404);
            $r = FxFeed::wall($wt, $wid, $ch, $viewer, $cursor);
        } else {
            fx_fail('bad_scope');
        }
        FxPosts::view(array_column($r['posts'], 'id'));   // показ = просмотр; раз за сессию на пост
        fx_out(['html' => FxRender::posts($r['posts'], fx_ctx($in, $viewer)), 'next' => $r['next'], 'n' => count($r['posts']), 'empty' => $r['empty'] ?? null]);
    }

    case 'post': {          // оверлей: пост / статья + комментарии
        $p = FxFeed::one((int)($in['id'] ?? 0), $viewer);
        if (!$p) fx_fail('not_found', 404);
        $row = FxPosts::find($p['id']);
        $sort = ($in['sort'] ?? 'top') === 'new' ? 'new' : 'top';
        $tree = FxComments::tree($row, $viewer, $sort);
        FxPosts::view([$p['id']]);
        fx_out([
            'html' => FxRender::overlay($p, (string)($row['article'] ?? ''), $tree, $viewer, $sort),
            'title' => $p['title'] !== '' ? preg_replace('/@\[\w+:\d+\|([^\]]+)\]/u', '$1', $p['title']) : $p['author']['name'],
            'sub' => $p['author']['name'] . ' · ' . $p['time'],
            'comments' => $p['stats']['comments'], 'views' => $p['stats']['views'], 'can_comment' => $viewer > 0 && !$p['locked'],
            'reply' => FxRender::replyBox($viewer, $p['id']),
        ]);
    }

    case 'comments': {
        $row = FxPosts::find((int)($in['id'] ?? 0));
        if (!$row || (int)$row['status'] !== 1) fx_fail('not_found', 404);
        $p = FxPosts::hydrate([$row], $viewer)[0];
        $sort = ($in['sort'] ?? 'top') === 'new' ? 'new' : 'top';
        fx_out(['html' => FxRender::comments(FxComments::tree($row, $viewer, $sort), $p, $viewer, true, $sort), 'count' => $p['stats']['comments']]);
    }

    case 'thread': {        // раскрытие ветки форума
        $row = FxPosts::find((int)($in['id'] ?? 0));
        if (!$row || (int)$row['status'] !== 1 || $row['channel'] !== 'forum') fx_fail('not_found', 404);
        $p = FxPosts::hydrate([$row], $viewer)[0];
        FxPosts::view([$p['id']]);
        fx_out(['html' => FxRender::threadBody($p, FxComments::tree($row, $viewer, 'new'), $viewer)]);
    }

    case 'mentions': {      // автокомплит @: люди, игры, студии
        $q = trim((string)($in['q'] ?? ''));
        if (mb_strlen($q) < 1) fx_out(['items' => []]);
        $pdo = Fx::pdo();
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $out = [];
        $st = $pdo->prepare("SELECT id, username, profile_picture FROM users WHERE username LIKE ? ORDER BY (username = ?) DESC, username ASC LIMIT 5");
        $st->execute([$like, $q]);
        foreach ($st->fetchAll() as $r) if ($r['username'] !== null && $r['username'] !== '') $out[] = ['type' => 'user', 'id' => (int)$r['id'], 'name' => $r['username'], 'img' => $r['profile_picture'] ?: '', 'note' => 'игрок'];
        $st = $pdo->prepare("SELECT id, name, icon_url, path_to_cover, genre FROM games WHERE name LIKE ? AND status = 'published' AND (hidden = 0 OR hidden IS NULL) ORDER BY name ASC LIMIT 5");
        $st->execute([$like]);
        foreach ($st->fetchAll() as $r) $out[] = ['type' => 'game', 'id' => (int)$r['id'], 'name' => $r['name'], 'img' => $r['icon_url'] ?: ($r['path_to_cover'] ?: ''), 'note' => $r['genre'] ?: 'игра'];
        $st = $pdo->prepare("SELECT id, name, avatar_link FROM studios WHERE name LIKE ? OR tiker LIKE ? ORDER BY name ASC LIMIT 4");
        $st->execute([$like, $like]);
        foreach ($st->fetchAll() as $r) $out[] = ['type' => 'studio', 'id' => (int)$r['id'], 'name' => $r['name'], 'img' => $r['avatar_link'] ?: '', 'note' => 'студия'];
        fx_out(['items' => $out]);
    }

    case 'hunt': {          // модалка DustHunt
        $st = Fx::pdo()->prepare("SELECT * FROM fx_hunts WHERE id = ?");
        $st->execute([(int)($in['id'] ?? 0)]);
        if (!$st->fetch()) fx_fail('not_found', 404);
        fx_out(['html' => FxRenderHub::huntModal((int)$in['id'], $viewer)]);
    }

    /* ============================================================ ЗАПИСЬ */

    case 'create': {
        $data = [
            'wall_type' => $in['wall_type'] ?? 'media', 'wall_id' => $in['wall_id'] ?? 0, 'channel' => $in['channel'] ?? 'wall',
            'kind' => $in['kind'] ?? 'post', 'title' => $in['title'] ?? '', 'body' => $in['body'] ?? '', 'article' => $in['article'] ?? '',
            'tags' => $in['tags'] ?? '', 'as_studio_id' => $in['as_studio_id'] ?? 0, 'crosspost' => $in['crosspost'] ?? 0,
            'media' => $in['media'] ?? [], 'poll' => $in['poll'] ?? null,
        ];
        foreach (['media', 'poll'] as $k) {
            if (is_string($data[$k]) && $data[$k] !== '') { $d = json_decode($data[$k], true); $data[$k] = is_array($d) ? $d : ($k === 'media' ? [] : null); }
        }
        $r = fx_res(FxPosts::create($viewer, $data));
        $p = FxFeed::one($r['id'], $viewer);
        fx_out(['id' => $r['id'], 'html' => $p ? FxRender::posts([$p], fx_ctx($in, $viewer)) : '', 'in_media' => $p !== null]);
    }

    case 'edit':
        fx_res(FxPosts::edit($viewer, (int)($in['id'] ?? 0), $in));
        $p = FxFeed::one((int)$in['id'], $viewer);
        fx_out(['html' => $p ? FxRender::posts([$p], fx_ctx($in, $viewer)) : '']);

    case 'delete':
        fx_res(FxPosts::delete($viewer, (int)($in['id'] ?? 0)));
        fx_out([]);

    case 'pin':
        fx_out(fx_res(FxPosts::pin($viewer, (int)($in['id'] ?? 0), !empty($in['on']))));

    case 'lock':
        fx_out(fx_res(FxPosts::lock($viewer, (int)($in['id'] ?? 0), !empty($in['on']))));

    case 'react':
        fx_out(fx_res(FxReact::set($viewer, (int)($in['id'] ?? 0), (string)($in['kind'] ?? ''))));

    case 'comment': {
        $r = fx_res(FxComments::add($viewer, (int)($in['post_id'] ?? 0), (string)($in['body'] ?? ''), !empty($in['parent']) ? (int)$in['parent'] : null));
        $row = FxPosts::find((int)$in['post_id']);
        $p = FxPosts::hydrate([$row], $viewer)[0];
        $tree = FxComments::tree($row, $viewer, ($in['sort'] ?? 'new') === 'top' ? 'top' : 'new');
        fx_out(['id' => $r['id'], 'html' => FxRender::comments($tree, $p, $viewer, !empty($in['head'])), 'count' => $p['stats']['comments']]);
    }

    case 'comment_delete':
        fx_res(FxComments::delete($viewer, (int)($in['id'] ?? 0)));
        fx_out([]);

    case 'comment_like':
        fx_out(fx_res(FxComments::like($viewer, (int)($in['id'] ?? 0))));

    case 'vote': {
        $r = fx_res(FxPoll::vote($viewer, (int)($in['id'] ?? 0), (int)($in['opt'] ?? -1)));
        $row = FxPosts::find((int)$in['id']);
        $p = FxPosts::hydrate([$row], $viewer)[0];
        fx_out(['html' => FxRender::poll($p)]);
    }

    case 'follow':
        fx_out(fx_res(FxFollow::toggle($viewer, (string)($in['type'] ?? ''), (int)($in['id'] ?? 0))));

    case 'report': {
        $type = ($in['type'] ?? 'post') === 'comment' ? 'comment' : 'post';
        $id = (int)($in['id'] ?? 0);
        $reason = preg_replace('/[^a-z_]/', '', (string)($in['reason'] ?? 'other')) ?: 'other';
        Fx::pdo()->prepare("INSERT IGNORE INTO fx_reports (target_type, target_id, reporter_id, reason, note, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$type, $id, $viewer, mb_substr($reason, 0, 24), Fx::text($in['note'] ?? '', 255) ?: null, Fx::now()]);
        fx_out([]);
    }

    case 'views':
        FxPosts::view(array_map('intval', (array)($in['ids'] ?? [])));
        fx_out([]);

    case 'hunt_join':
    case 'hunt_leave': {
        $r = fx_res(FxHunt::join($viewer, (int)($in['id'] ?? 0), $action === 'hunt_leave'));
        fx_out(['hunt' => $r['hunt'], 'html' => FxRenderHub::huntModal((int)$in['id'], $viewer), 'widget' => FxRenderHub::huntWidget($r['hunt'])]);
    }

    /* ============================================================ ЗАГРУЗКА ФОТО */

    case 'upload': {
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) fx_fail('no_file');
        if ((int)$f['size'] > 6 * 1024 * 1024) fx_fail('too_big');

        // не больше 30 загрузок в час на сессию
        $now = time();
        $log = array_values(array_filter((array)($_SESSION['fx_up'] ?? []), static fn($t) => $t > $now - 3600));
        if (count($log) >= 30) fx_fail('rate', 429);
        $log[] = $now;
        $_SESSION['fx_up'] = $log;

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        $info = $ext ? @getimagesize($f['tmp_name']) : false;
        if (!$ext || !$info || ($info['mime'] ?? '') !== $mime) fx_fail('bad_type');

        $root = dirname(__DIR__) . '/media/fx/' . date('Y') . '/' . date('m');
        if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) fx_fail('storage', 500, 'mkdir failed: ' . $root);
        $guard = dirname(__DIR__) . '/media/fx/.htaccess';
        if (!is_file($guard)) @file_put_contents($guard, "Options -ExecCGI -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n");

        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $root . '/' . $name;
        $w = (int)$info[0];
        $h = (int)$info[1];

        // Перекодируем через GD: убирает EXIF и полиглоты, ограничивает размер. GIF оставляем как есть (анимация).
        $saved = false;
        if ($ext !== 'gif' && function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
            if ($img) {
                $max = 2200;
                if ($w > $max || $h > $max) {
                    $k = $max / max($w, $h);
                    $res = imagescale($img, (int)round($w * $k), (int)round($h * $k));
                    if ($res) { imagedestroy($img); $img = $res; $w = imagesx($img); $h = imagesy($img); }
                }
                if ($ext === 'png') { imagesavealpha($img, true); $saved = imagepng($img, $dest, 6); }
                elseif ($ext === 'webp') { $saved = imagewebp($img, $dest, 86); }
                else { $saved = imagejpeg($img, $dest, 86); }
                imagedestroy($img);
            }
        }
        if (!$saved && !move_uploaded_file($f['tmp_name'], $dest)) fx_fail('storage', 500, 'save failed: ' . $dest);
        @chmod($dest, 0644);
        fx_out(['u' => '/media/fx/' . date('Y') . '/' . date('m') . '/' . $name, 'w' => $w, 'h' => $h]);
    }

    default:
        fx_fail('bad_action');
}
