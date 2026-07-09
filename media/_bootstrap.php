<?php
/**
 * media/_bootstrap.php — общий бутстрап и хелперы Dustore.Media (v2)
 * v2: короткие ссылки вида dustore.ru/p/{code}; санитайзер пропускает
 *     width/height у <img> (ресайз из SunEditor).
 */
declare(strict_types=1);

require_once __DIR__ . '/../swad/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

const MEDIA_CANON_HOST    = 'https://dustore.ru';
const MEDIA_SHORT_PREFIX  = MEDIA_CANON_HOST . '/p/';       // канонический короткий URL поста
const MEDIA_VIEW_PEPPER   = 'dm_v1_CHANGE_ME';              // CONFIRM: вынеси в конфиг
const MEDIA_WORKER_SECRET = 'dm_worker_CHANGE_ME';          // CONFIRM

function media_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function media_user_id(): int {
    return (int)($_SESSION['USERDATA']['id'] ?? 0);
}

function media_post_url(string $code): string {
    return MEDIA_SHORT_PREFIX . $code;
}

/* ── Короткий код: base58 без похожих символов (0/O, 1/l/I) ───────── */
function media_short_code(PDO $pdo): string {
    $ab = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($try = 0; $try < 8; $try++) {
        $code = '';
        for ($i = 0; $i < 7; $i++) $code .= $ab[random_int(0, strlen($ab) - 1)];
        $q = $pdo->prepare("SELECT 1 FROM media_posts WHERE short_code = ? LIMIT 1");
        $q->execute([$code]);
        if (!$q->fetch()) return $code;
    }
    throw new RuntimeException('short_code: не смогли сгенерировать уникальный код');
}

/* ── Студии, от лица которых юзер может постить (некритично: catch → []) ── */
function media_user_studios(PDO $pdo, int $uid): array {
    if ($uid <= 0) return [];
    try {
        $out = [];
        $st = $pdo->prepare("SELECT id, name, avatar_link FROM studios WHERE owner_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int)$r['id']] = $r;

        $st = $pdo->prepare("
            SELECT s.id, s.name, s.avatar_link
            FROM users u
            JOIN staff  stf ON stf.telegram_id = CAST(u.telegram_id AS UNSIGNED)
            JOIN studios s  ON s.id = stf.org_id
            WHERE u.id = ? AND u.telegram_id IS NOT NULL AND u.telegram_id != ''
        ");
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int)$r['id']] = $r;

        return array_values($out);
    } catch (Throwable $e) {
        error_log('media_user_studios: ' . $e->getMessage());
        return [];
    }
}

/* ── Санитайзер HTML (SunEditor/Quill): allowlist тегов и атрибутов ──
      На <img> разрешаем width/height/style, где style отфильтрован
      до width/height со значениями px|%|auto — ресайз выживает, XSS нет. */
function media_sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';

    $allowedTags = ['p','br','b','strong','i','em','u','s','a','ul','ol','li',
                    'blockquote','h2','h3','pre','code','span','img','figure'];
    $allowedAttrs = [
        'a'   => ['href'],
        'img' => ['src','alt','width','height','style'],
    ];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>',
                   LIBXML_NOENT | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementById('__root');
    if (!$root) return '';

    $xpath = new DOMXPath($doc);
    $nodes = iterator_to_array($xpath->query('//*[@id="__root"]//*'));
    foreach (array_reverse($nodes) as $node) {
        /** @var DOMElement $node */
        $tag = strtolower($node->nodeName);

        if (!in_array($tag, $allowedTags, true)) {
            if (in_array($tag, ['script','style','iframe','object','embed','form'], true)) {
                $node->parentNode->removeChild($node);
            } else {
                while ($node->firstChild) $node->parentNode->insertBefore($node->firstChild, $node);
                $node->parentNode->removeChild($node);
            }
            continue;
        }

        $keep = $allowedAttrs[$tag] ?? [];
        foreach (iterator_to_array($node->attributes) as $attr) {
            $an = strtolower($attr->nodeName);
            if (!in_array($an, $keep, true)) { $node->removeAttribute($attr->nodeName); continue; }
            $val = trim($attr->nodeValue);

            if ($an === 'href' && !preg_match('~^https?://~i', $val)) {
                $node->removeAttribute($attr->nodeName);
            }
            if ($an === 'src' && !preg_match('~^https://s3\.regru\.cloud/~i', $val)) {
                $node->removeAttribute('src');
            }
            if (in_array($an, ['width','height'], true) && !preg_match('~^\d+(%)?$~', $val)) {
                $node->removeAttribute($an);
            }
            if ($an === 'style') {
                $decl = [];
                foreach (explode(';', $val) as $rule) {
                    [$prop, $v] = array_pad(array_map('trim', explode(':', $rule, 2)), 2, '');
                    if (in_array(strtolower($prop), ['width','height'], true)
                        && preg_match('~^(\d+(\.\d+)?(px|%)|auto)$~', $v)) {
                        $decl[] = strtolower($prop) . ':' . $v;
                    }
                }
                if ($decl) $node->setAttribute('style', implode(';', $decl));
                else $node->removeAttribute('style');
            }
        }
        if ($tag === 'a' && $node->hasAttribute('href')) {
            $node->setAttribute('rel', 'nofollow noopener');
            $node->setAttribute('target', '_blank');
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
    return trim($out);
}

/* ── Whitelist-парсер видео-URL → embed ───────────────────────────── */
function media_video_embed(string $url): ?array {
    $url = trim($url);
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
        return ['kind'=>'video','provider'=>'youtube','url'=>$url,
                'embed'=>'https://www.youtube.com/embed/' . $m[1]];
    }
    if (preg_match('~rutube\.ru/video/([a-f0-9]{32})~i', $url, $m)) {
        return ['kind'=>'video','provider'=>'rutube','url'=>$url,
                'embed'=>'https://rutube.ru/play/embed/' . $m[1]];
    }
    if (preg_match('~(?:vk\.com|vkvideo\.ru)/video(-?\d+)_(\d+)~', $url, $m)) {
        return ['kind'=>'video','provider'=>'vk','url'=>$url,
                'embed'=>"https://vk.com/video_ext.php?oid={$m[1]}&id={$m[2]}&hd=2"];
    }
    return null;
}

/* ── Fire-and-forget: будим воркер кросс-постинга ─────────────────── */
function media_kick_crosspost_worker(): void {
    $fp = @fsockopen('127.0.0.1', 80, $errno, $errstr, 1);
    if (!$fp) return;
    $path = '/media/crosspost_worker.php?secret=' . urlencode(MEDIA_WORKER_SECRET);
    fwrite($fp, "GET {$path} HTTP/1.1\r\nHost: dustore.ru\r\nConnection: Close\r\n\r\n");
    fclose($fp);
}

function media_json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}