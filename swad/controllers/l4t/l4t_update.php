<?php
declare(strict_types=1);
/**
 * swad/controllers/l4t/l4t_update.php — сохранение блоков профиля L4T.
 *
 * ЧТО БЫЛО СЛОМАНО: в switch не было ветки 'exp'. l4t.js в editExp() шлёт
 * ровно {type:"exp", data:[...]}, запрос падал в default и возвращал
 *     {"success":false,"msg":"unknown type"}
 * а старый клиент делал .then(() => location.reload()) не глядя на ответ —
 * страница перезагружалась, и выглядело так, будто опыт сохранился.
 * Отсюда и exp_log.txt с [input] => {"exp":[]}: кто-то это отлаживал.
 *
 * Ветки 'exp' и 'role' добавлены и пишут те же колонки, что update_exp.php
 * и update_role.php, — старые эндпоинты продолжают работать, index.php
 * трогать не нужно.
 */

session_start();
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/_csrf.php');

header('Content-Type: application/json; charset=utf-8');

function reply(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['USERDATA']['id'])) reply(['success' => false, 'msg' => 'not authenticated']);
$userId = (int)$_SESSION['USERDATA']['id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['type'])) reply(['success' => false, 'msg' => 'no type']);
csrf_guard_json($data);

$db  = new Database();
$pdo = $db->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Пишем колонку профиля и синхронизируем сессию, чтобы страница после
 *  перезагрузки показывала новое значение, а не старое из $_SESSION. */
function save(PDO $pdo, int $userId, string $column, string $value): void {
    // имя колонки не из пользовательского ввода — только из литералов ниже
    $pdo->prepare("UPDATE users SET $column = ? WHERE id = ?")->execute([$value, $userId]);
    $_SESSION['USERDATA'][$column] = $value;
}

$enc = fn(array $a): string => json_encode($a, JSON_UNESCAPED_UNICODE);

try {
    switch ($data['type']) {

        /* ── ОПЫТ (ветки не было вовсе) ──────────────────────────────── */
        case 'exp': {
            $clean = [];
            foreach (array_slice((array)($data['data'] ?? []), 0, 20) as $e) {
                $role = mb_substr(strip_tags(trim((string)($e['role'] ?? ''))), 0, 30);
                if ($role === '') continue;
                $clean[] = ['role' => $role, 'years' => min(60, max(0, (int)($e['years'] ?? 0)))];
            }
            save($pdo, $userId, 'l4t_exp', $enc($clean));
            reply(['success' => true, 'data' => $clean]);
        }

        /* ── РОЛЬ ────────────────────────────────────────────────────── */
        case 'role': {
            $role = mb_substr(strip_tags(trim((string)($data['data'] ?? ''))), 0, 40);
            save($pdo, $userId, 'l4t_role', $role);
            reply(['success' => true, 'data' => $role]);
        }

        /* ── ФАЙЛЫ / ССЫЛКИ ──────────────────────────────────────────── */
        case 'files': {
            $clean = [];
            foreach (array_slice((array)($data['data'] ?? []), 0, 20) as $f) {
                $type  = in_array($f['type'] ?? '', ['link', 'file'], true) ? $f['type'] : 'link';
                $name  = mb_substr(strip_tags((string)($f['name'] ?? '')), 0, 60);
                $value = mb_substr(trim((string)($f['value'] ?? '')), 0, 500);
                if ($value === '') continue;
                // javascript:/data: в ссылке профиля — готовый XSS по клику
                if (!preg_match('~^https?://~i', $value)) continue;
                $clean[] = ['type' => $type, 'name' => $name !== '' ? $name : $value, 'value' => $value];
            }
            save($pdo, $userId, 'l4t_files', $enc($clean));
            reply(['success' => true, 'data' => $clean]);
        }

        /* ── ПРОЕКТЫ ─────────────────────────────────────────────────── */
        case 'projects': {
            $clean = [];
            foreach (array_slice((array)($data['data'] ?? []), 0, 12) as $p) {
                $url = mb_substr(trim((string)($p['url'] ?? '')), 0, 500);
                if ($url === '' || !preg_match('~^https?://~i', $url)) continue;

                $cover = mb_substr(trim((string)($p['cover'] ?? '')), 0, 500);
                if ($cover !== '' && !preg_match('~^https?://~i', $cover)) $cover = '';

                $title = mb_substr(strip_tags((string)($p['title'] ?? '')), 0, 80);
                $clean[] = [
                    'title'       => $title !== '' ? $title : $url,
                    'role'        => mb_substr(strip_tags((string)($p['role'] ?? '')), 0, 60),
                    'year'        => (int)($p['year'] ?? 0),
                    'url'         => $url,
                    'cover'       => $cover,
                    'description' => mb_substr(strip_tags((string)($p['description'] ?? '')), 0, 500),
                ];
            }
            save($pdo, $userId, 'l4t_projects', $enc($clean));
            reply(['success' => true, 'data' => $clean]);
        }

        /* ── О СЕБЕ ──────────────────────────────────────────────────── */
        case 'about': {
            $about = mb_substr(strip_tags((string)($data['data'] ?? '')), 0, 10000);
            save($pdo, $userId, 'l4t_about', $about);
            reply(['success' => true]);
        }

        /* ── ЗАГРУЗКА ФАЙЛА НА S3 ────────────────────────────────────── */
        case 'upload': {
            require_once(__DIR__ . '/../s3.php');

            $b64 = (string)($data['file'] ?? '');
            if ($b64 === '') reply(['success' => false, 'msg' => 'no file']);

            // Раньше размер не ограничивался: base64 на 100 МБ клал PHP по памяти.
            if (strlen($b64) > 11 * 1024 * 1024) reply(['success' => false, 'msg' => 'too_large']);   // ~8 МБ файла в base64

            $decoded = base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $b64), true);
            if ($decoded === false || $decoded === '') reply(['success' => false, 'msg' => 'bad base64']);

            $tmp = tempnam(sys_get_temp_dir(), 'l4t_');
            file_put_contents($tmp, $decoded);

            /* Расширение раньше бралось из запроса и лишь чистилось от неалфавитных
               символов — то есть 'php' проходил. Определяем тип по содержимому. */
            $mime = function_exists('finfo_open')
                ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp)
                : 'application/octet-stream';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($allowed[$mime])) {
                unlink($tmp);
                reply(['success' => false, 'msg' => 'bad_type']);
            }

            $key = "l4t/u{$userId}_" . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
            $url = (new S3Uploader())->uploadFile($tmp, $key);
            unlink($tmp);

            reply(['success' => (bool)$url, 'url' => $url ?: '']);
        }

        /* ── ВИТРИНА ПРОФИЛЯ (desl4t.profiles) ───────────────────────── */
        case 'profile': {
            $in  = (array)($data['data'] ?? []);
            $str = fn(string $k, int $n) => mb_substr(trim(strip_tags((string)($in[$k] ?? ''))), 0, $n);

            $avail = (string)($in['availability'] ?? '');
            if (!in_array($avail, ['open', 'hiring', 'busy', 'closed'], true)) $avail = '';

            $accent = strtolower((string)($in['accent'] ?? ''));
            if (!preg_match('/^#[0-9a-f]{6}$/', $accent)) $accent = '';

            // Обложка — только https: через неё в style="" уезжает url(), и
            // javascript:/data: там не место.
            $banner = mb_substr(trim((string)($in['banner_url'] ?? '')), 0, 500);
            if ($banner !== '' && !preg_match('~^https://[^\s"\'()<>]+$~i', $banner)) $banner = '';

            $codes  = fn($v, int $max) => implode(',', array_slice(array_values(array_unique(array_filter(
                array_map('strval', (array)$v), fn($c) => (bool)preg_match('/^[a-z0-9_]{1,24}$/', $c)))), 0, $max));

            $hidden = array_values(array_intersect((array)($in['hidden_blocks'] ?? []), ['stats', 'activity', 'platform']));

            $row = [
                'headline'      => $str('headline', 120),
                'status_emoji'  => mb_substr(trim((string)($in['status_emoji'] ?? '')), 0, 4),
                'status_text'   => $str('status_text', 80),
                'availability'  => $avail,
                'location'      => $str('location', 80),
                'banner_url'    => $banner,
                'accent'        => $accent,
                'pinned_badges' => $codes($in['pinned_badges'] ?? [], 3),
                'hidden_blocks' => implode(',', $hidden),
            ];
            $row = array_map(fn($v) => $v === '' ? null : $v, $row);

            $l4t = $db->connect('desl4t');
            $l4t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $cols = array_keys($row);
            $l4t->prepare("INSERT INTO profiles (user_id, " . implode(', ', $cols) . ")
                           VALUES (?, " . implode(', ', array_fill(0, count($cols), '?')) . ")
                           ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols)))
                ->execute(array_merge([$userId], array_values($row)));

            /* Статус «ищу» живёт 30 дней с последнего сохранения — иначе биржа
               зарастает профилями, которые «открыты» с прошлого года. */
            require_once __DIR__ . '/../../../l4t/lib/extras.php';
            $x = new L4TX($pdo, $l4t);
            if ($x->has('profiles', 'avail_until')) {
                $l4t->prepare("UPDATE profiles SET avail_until = IF(availability IS NULL, NULL, CURDATE() + INTERVAL 30 DAY) WHERE user_id = ?")
                    ->execute([$userId]);
            }
            $x->matchSavedSearches($userId);

            reply(['success' => true, 'data' => $row]);
        }

        default:
            reply(['success' => false, 'msg' => 'unknown type']);
    }
} catch (Throwable $e) {
    error_log('[l4t/l4t_update] ' . $e->getMessage());
    reply(['success' => false, 'msg' => 'server_error']);
}