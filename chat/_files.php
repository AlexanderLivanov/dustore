<?php
declare(strict_types=1);
/**
 * chat/_files.php — вложения чата в S3.
 *
 * Модель как у Telegram, только поверх S3:
 *   1. upload_init   — сервер заводит запись chat_files(pending) и выдаёт
 *                      подписанную PUT-ссылку; браузер кладёт файл прямо в бакет,
 *                      PHP байты не трогает (быстро, без post_max_size).
 *   2. превью        — картинку уменьшает сам клиент (canvas) и кладёт рядом,
 *                      как TG шлёт thumb вместе с фото. Сервер GD не нужен.
 *   3. upload_commit — сервер делает HEAD и сверяет размер: только после этого
 *                      файл «ready» и его можно прикрепить к сообщению.
 *   4. скачивание    — /chat/file.php проверяет, что ты участник беседы, и
 *                      редиректит на подписанную GET-ссылку на 10 минут.
 *
 * Объекты кладутся БЕЗ ACL public-read (в отличие от аватарок), а ключ содержит
 * 128 бит случайности — ни перебрать, ни угадать ссылку нельзя.
 * Если PUT в S3 из браузера не прошёл (CORS на локалке), есть запасной путь
 * upload_proxy: файл идёт через PHP и кладётся тем же ключом.
 */

require_once __DIR__ . '/../vendor/autoload.php';

const CHAT_FILE_MAX   = 50 * 1024 * 1024;   // 50 МБ
const CHAT_SOUND_MAX  = 200 * 1024;         // 200 КБ
const CHAT_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const CHAT_SOUND_MIME = ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/aac', 'audio/mp4'];

function chat_bucket(): string {
    return defined('CHAT_S3_BUCKET') ? CHAT_S3_BUCKET : AWS_S3_BUCKET_USERCONTENT;
}

function chat_s3(): Aws\S3\S3Client {
    static $s3 = null;
    if ($s3) return $s3;
    $s3 = new Aws\S3\S3Client([
        'version'                 => 'latest',
        'region'                  => AWS_S3_REGION,
        'endpoint'                => AWS_S3_ENDPOINT,
        'use_path_style_endpoint' => true,
        'credentials'             => ['key' => AWS_S3_KEY, 'secret' => AWS_S3_SECRET],
    ]);
    return $s3;
}

/** Имя файла для ключа: латиница/цифры/._- , остальное — дефис. Оригинал хранится в БД. */
function chat_safe_name(string $name): string {
    $name = preg_replace('~[^A-Za-z0-9._-]+~', '-', $name) ?? 'file';
    $name = trim($name, '-.');
    return $name === '' ? 'file' : mb_substr($name, -80);
}

function chat_new_key(string $name): string {
    return 'chat/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '/' . chat_safe_name($name);
}

/** Тип вложения по MIME. null — тип не принимаем для этой цели. */
function chat_kind_for(string $mime, string $purpose): ?string {
    if ($purpose === 'sound') return in_array($mime, CHAT_SOUND_MIME, true) ? 'sound' : null;
    if (in_array($mime, CHAT_IMAGE_MIME, true)) return 'image';
    // исполняемое и html не принимаем: с подписанной ссылки браузер мог бы его отрисовать
    if (preg_match('~^(text/html|application/xhtml|image/svg)~', $mime)) return null;
    return 'file';
}

function chat_presign_put(string $key, string $mime, string $ttl = '+20 minutes'): string {
    $cmd = chat_s3()->getCommand('PutObject', ['Bucket' => chat_bucket(), 'Key' => $key, 'ContentType' => $mime]);
    return (string)chat_s3()->createPresignedRequest($cmd, $ttl)->getUri();
}

/** Размер объекта в бакете или null, если его нет. */
function chat_head_size(string $key): ?int {
    try {
        $h = chat_s3()->headObject(['Bucket' => chat_bucket(), 'Key' => $key]);
        return (int)$h['ContentLength'];
    } catch (Throwable $e) {
        return null;
    }
}

function chat_put_local(string $key, string $path, string $mime): bool {
    try {
        chat_s3()->putObject(['Bucket' => chat_bucket(), 'Key' => $key, 'SourceFile' => $path, 'ContentType' => $mime]);
        return true;
    } catch (Throwable $e) {
        error_log('[chat_files] put: ' . $e->getMessage());
        return false;
    }
}

function chat_delete_key(?string $key): void {
    if (!$key) return;
    try { chat_s3()->deleteObject(['Bucket' => chat_bucket(), 'Key' => $key]); } catch (Throwable $e) { }
}

/**
 * Подписанная GET-ссылка. Картинки и звуки — inline с жёстко заданным типом,
 * всё остальное — attachment с оригинальным именем (RFC 5987 для кириллицы).
 */
function chat_presign_get(array $f, bool $thumb = false, string $ttl = '+10 minutes'): string {
    $inline = in_array($f['kind'], ['image', 'sound'], true);
    $name   = str_replace(['"', "\r", "\n"], '', (string)$f['name']);
    $params = [
        'Bucket' => chat_bucket(),
        'Key'    => $thumb && !empty($f['thumb_key']) ? $f['thumb_key'] : $f['s3_key'],
        'ResponseContentDisposition' => ($inline ? 'inline' : 'attachment')
            . '; filename="' . chat_safe_name($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name),
    ];
    if ($inline) $params['ResponseContentType'] = $thumb && !empty($f['thumb_key']) ? 'image/webp' : $f['mime'];
    $cmd = chat_s3()->getCommand('GetObject', $params);
    return (string)chat_s3()->createPresignedRequest($cmd, $ttl)->getUri();
}

/** Публичный DTO вложения для фронта: ссылки ведут на наш шлюз, а не в бакет. */
function chat_file_dto(array $f): array {
    $id = (int)$f['id'];
    return [
        'id'    => $id,
        'kind'  => $f['kind'],
        'name'  => $f['name'],
        'mime'  => $f['mime'],
        'size'  => (int)$f['size'],
        'w'     => $f['width']  !== null ? (int)$f['width']  : null,
        'h'     => $f['height'] !== null ? (int)$f['height'] : null,
        'url'   => '/chat/file.php?id=' . $id,
        'thumb' => $f['kind'] === 'image' ? '/chat/file.php?id=' . $id . (empty($f['thumb_key']) ? '' : '&thumb=1') : null,
    ];
}

/** Пакетная загрузка вложений: [id => row]. */
function chat_files_by_ids(PDO $db, array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT * FROM chat_files WHERE id IN ($in) AND status='ready'");
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int)$r['id']] = $r;
    return $out;
}
