<?php
/**
 * media/crosspost_worker.php — разгребает outbox media_crossposts.
 *
 * Триггерится fire-and-forget fsockopen'ом из api.php сразу после публикации,
 * плюс подстраховочный cron раз в 5 минут добивает failed/pending
 * (крон-строка — в README, тут её писать нельзя: звёздочка-слэш ломает docblock).
 */
declare(strict_types=1);

// ВАЖНО: Database::connect() роутит креды по HTTP_HOST.
// Локальный curl/fsockopen даёт localhost → ЛОКАЛЬНЫЕ креды. Форсим прод:
$_SERVER['HTTP_HOST'] = 'dustore.ru';

require_once __DIR__ . '/_bootstrap.php';

if (($_GET['secret'] ?? '') !== MEDIA_WORKER_SECRET) { http_response_code(403); exit; }

ignore_user_abort(true);
set_time_limit(120);
// Отпускаем клиента сразу — fsockopen не ждёт нас
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { header('Content-Length: 2'); echo 'ok'; @ob_flush(); @flush(); }

/* ── Конфиг каналов ─────────────────────────────────────────────────── */
const XP_TG_CHANNEL   = '@dustore_official';               // CONFIRM: канал/чат для кросс-постов
const XP_TG_TOKEN_ENV = 'TG_BOT_TOKEN';                    // CONFIRM: где лежит токен (env / config)
const XP_TG_PROXY     = 'socks5h://user:pass@ee-proxy:1080'; // CONFIRM: тот же SOCKS5, что в tg_bot.php
const XP_VK_GROUP_ID  = 0;                                 // CONFIRM: id группы VK (положительное число)
const XP_VK_TOKEN_ENV = 'VK_GROUP_TOKEN';                  // CONFIRM

const XP_MAX_ATTEMPTS = 5;

$pdo = media_pdo();

/* Захват пачки: атомарно помечаем attempts, чтобы параллельные воркеры не дрались */
$rows = $pdo->query("
    SELECT x.id, x.target, x.attempts, p.id AS post_id, p.type, p.title, p.body,
           p.attachments, p.short_code, p.studio_id, p.game_id,
           u.username, s.name AS studio_name, g.name AS game_name
    FROM media_crossposts x
    JOIN media_posts p  ON p.id = x.post_id AND p.status = 'published'
    JOIN users u        ON u.id = p.author_user_id
    LEFT JOIN studios s ON s.id = p.studio_id
    LEFT JOIN games g   ON g.id = p.game_id
    WHERE x.status IN ('pending','failed') AND x.attempts < " . XP_MAX_ATTEMPTS . "
    ORDER BY x.id ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $pdo->prepare("UPDATE media_crossposts SET attempts = attempts + 1 WHERE id = ?")
        ->execute([(int)$row['id']]);

    try {
        $extId = match ($row['target']) {
            'telegram' => xp_send_telegram($row),
            'vk'       => xp_send_vk($row),
        };
        $pdo->prepare("UPDATE media_crossposts SET status='sent', external_id=?, sent_at=NOW(), last_error=NULL WHERE id=?")
            ->execute([$extId, (int)$row['id']]);
    } catch (Throwable $e) {
        error_log("crosspost #{$row['id']} ({$row['target']}): " . $e->getMessage());
        $pdo->prepare("UPDATE media_crossposts SET status='failed', last_error=? WHERE id=?")
            ->execute([mb_substr($e->getMessage(), 0, 2000), (int)$row['id']]);
    }
}
exit;

/* ═══════════════════════════ РЕНДЕР ТЕКСТА ═══════════════════════════ */
function xp_render_text(array $row, int $limit): string {
    $author = $row['studio_id'] ? $row['studio_name'] : $row['username'];
    $head   = $row['title'] ?: '';
    $body   = trim(preg_replace('~\s+~u', ' ', strip_tags($row['body'])));
    if (mb_strlen($body) > $limit) $body = mb_substr($body, 0, $limit - 1) . '…';

    $parts = [];
    if ($head) $parts[] = $head;
    $parts[] = $body;
    if ($row['game_name']) $parts[] = '🎮 ' . $row['game_name'];
    $parts[] = '✍️ ' . $author . ' · Dustore.Media';
    $parts[] = 'https://dustore.gg/' . $row['short_code'];  // короткая ссылка = трекинг источника
    return implode("\n\n", array_filter($parts));
}

function xp_first_image(array $row): ?string {
    $att = json_decode($row['attachments'] ?? '[]', true) ?: [];
    foreach ($att as $a) if (($a['kind'] ?? '') === 'image') return $a['path'];
    return null;
}

/* ═══════════════════════════ TELEGRAM ════════════════════════════════ */
function xp_send_telegram(array $row): string {
    $token = getenv(XP_TG_TOKEN_ENV) ?: (defined('TG_BOT_TOKEN') ? TG_BOT_TOKEN : '');
    if (!$token) throw new RuntimeException('TG token не сконфигурирован');

    $img  = xp_first_image($row);
    $text = xp_render_text($row, $img ? 900 : 3500); // caption у фото ограничен 1024

    if ($img) {
        $method = 'sendPhoto';
        $params = ['chat_id' => XP_TG_CHANNEL, 'photo' => $img, 'caption' => $text];
    } else {
        $method = 'sendMessage';
        $params = ['chat_id' => XP_TG_CHANNEL, 'text' => $text, 'disable_web_page_preview' => false];
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_PROXY          => XP_TG_PROXY,   // SOCKS5 через Эстонию — как в tg_bot.php
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new RuntimeException('TG curl: ' . curl_error($ch));
    curl_close($ch);

    $j = json_decode($res, true);
    if (empty($j['ok'])) throw new RuntimeException('TG API: ' . ($j['description'] ?? $res));
    return (string)($j['result']['message_id'] ?? '');
}

/* ═══════════════════════════ VK ══════════════════════════════════════ */
function xp_send_vk(array $row): string {
    $token = getenv(XP_VK_TOKEN_ENV) ?: (defined('VK_GROUP_TOKEN') ? VK_GROUP_TOKEN : '');
    if (!$token || !XP_VK_GROUP_ID) throw new RuntimeException('VK не сконфигурирован');

    // v1: текст + короткая ссылка (VK сам подтянет OG-превью со страницы поста).
    // Загрузка фото в VK — трёхшаговая (getWallUploadServer → upload → saveWallPhoto),
    // добавим отдельным шагом когда базовый пайплайн заведётся.
    $params = [
        'owner_id'     => -XP_VK_GROUP_ID,
        'from_group'   => 1,
        'message'      => xp_render_text($row, 2000),
        'access_token' => $token,
        'v'            => '5.199',
    ];

    $ch = curl_init('https://api.vk.com/method/wall.post');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new RuntimeException('VK curl: ' . curl_error($ch));
    curl_close($ch);

    $j = json_decode($res, true);
    if (isset($j['error'])) throw new RuntimeException('VK API: ' . ($j['error']['error_msg'] ?? $res));
    return (string)($j['response']['post_id'] ?? '');
}