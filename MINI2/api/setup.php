<?php

/**
 * setup.php — DustStore Messenger
 * Читает messages_store.json и webhook_log.txt — никаких API-запросов для поиска ID.
 */

const MAX_TOKEN   = 'f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8';
const MAX_API     = 'https://platform-api.max.ru';
const STORE_FILE  = __DIR__ . '/messages_store.json';
const WEBHOOK_LOG = __DIR__ . '/webhook_log.txt';

function botInfo()
{
    $ch = curl_init(MAX_API . '/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: ' . MAX_TOKEN],
        CURLOPT_TIMEOUT => 6
    ]);
    $body = curl_exec($ch);
    $ok   = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    return $ok ? json_decode($body, true) : null;
}

// ── 1. Читаем messages_store.json ──
$store = [];
if (file_exists(STORE_FILE)) {
    $store = json_decode(file_get_contents(STORE_FILE), true) ?: [];
}

$users = [];
foreach ($store as $chat_id => $messages) {
    if (empty($messages)) continue;
    $user_id = null;
    $userName = null;
    $lastMsg = null;
    foreach (array_reverse($messages) as $m) {
        if (!$lastMsg) $lastMsg = $m;
        if (!$user_id && !empty($m['user_id'])) {
            $user_id  = $m['user_id'];
            $userName = $m['sender'] ?? ('User ' . $user_id);
        }
        if ($user_id) break;
    }
    $users[(string)$chat_id] = [
        'chat_id'  => (string)$chat_id,
        'user_id'  => (string)($user_id ?? ''),
        'name'     => $userName ?? "Чат $chat_id",
        'last_msg' => $lastMsg['text']      ?? '',
        'last_ts'  => $lastMsg['timestamp'] ?? '',
        'count'    => count($messages),
    ];
}

// ── 2. Дополняем из webhook_log.txt ──
if (file_exists(WEBHOOK_LOG)) {
    $lines = file(WEBHOOK_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lines) as $line) {
        $p = strpos($line, '{');
        if ($p === false) continue;
        $data = json_decode(substr($line, $p), true);
        if (!$data) continue;
        $msg     = $data['message']  ?? $data;
        $sender  = $msg['sender']    ?? [];
        $recip   = $msg['recipient'] ?? [];
        $chat_id = (string)($recip['chat_id'] ?? $sender['user_id'] ?? '');
        $user_id = (string)($sender['user_id'] ?? '');
        $name    = $sender['name'] ?? $sender['username'] ?? "User $user_id";
        if (!$chat_id || isset($users[$chat_id])) continue;
        $users[$chat_id] = [
            'chat_id'  => $chat_id,
            'user_id'  => $user_id,
            'name'     => $name,
            'last_msg' => $msg['body']['text'] ?? '',
            'last_ts'  => '',
            'count'    => 1,
        ];
    }
}

uasort($users, fn($a, $b) => strcmp($b['last_ts'], $a['last_ts']));

$bot = botInfo();
$serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['PHP_SELF']), '/');

function chip(string $label, string $val): void
{
    $safe = htmlspecialchars($val);
    $js   = addslashes($val);
    $uid  = 'chip-' . preg_replace('/[^a-z0-9]/i', '-', $val) . '-' . substr(md5($label . $val), 0, 4);
    echo <<<HTML
    <div class="chip" id="$uid" onclick="copyChip('$js','$uid')">
      <span class="chip-label">$label</span>
      <span class="chip-val">$safe</span>
      <button class="chip-btn">⎘</button>
    </div>\n
    HTML;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DustStore — Setup</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f0520;
            color: #e8e0f0;
            min-height: 100vh;
            padding: 20px 16px 48px
        }

        h1 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
            background: linear-gradient(90deg, #e8521a, #f0a500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent
        }

        .sub {
            color: #8870aa;
            font-size: 13px;
            margin-bottom: 24px
        }

        .card {
            background: #1a0a30;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
            border: 1px solid #2e1050
        }

        .card-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6a50a0;
            margin-bottom: 14px;
            font-weight: 600
        }

        .row {
            display: flex;
            align-items: center;
            gap: 12px
        }

        .avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800
        }

        .av-bot {
            background: linear-gradient(135deg, #e8521a, #7b2d8b)
        }

        .av-user {
            background: linear-gradient(135deg, #7b2d8b, #1a7be8)
        }

        .row-info {
            flex: 1;
            min-width: 0
        }

        .row-name {
            font-size: 16px;
            font-weight: 600
        }

        .row-sub {
            font-size: 12px;
            color: #8870aa;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .msg-count {
            font-size: 11px;
            color: #6a50a0;
            margin-top: 2px
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #120428;
            border: 1.5px solid #3a1a5e;
            border-radius: 20px;
            padding: 5px 10px 5px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            user-select: none
        }

        .chip:hover {
            border-color: #e8521a;
            background: #1e0838
        }

        .chip-label {
            color: #8870aa
        }

        .chip-val {
            color: #f0c060;
            font-weight: 700;
            font-family: monospace;
            font-size: 13px
        }

        .chip-btn {
            color: #6a50a0;
            font-size: 14px;
            line-height: 1;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: color .15s
        }

        .chip:hover .chip-btn {
            color: #e8521a
        }

        .chip.copied {
            border-color: #4ec97e !important;
            background: #0a2010 !important
        }

        .chip.copied .chip-btn {
            color: #4ec97e
        }

        .user-list {
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .user-item {
            background: #130428;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid #2a0f4a
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .step {
            display: flex;
            gap: 12px;
            align-items: flex-start
        }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, #e8521a, #b33e10);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700
        }

        .step-text {
            font-size: 14px;
            line-height: 1.5;
            color: #c8b8e0;
            padding-top: 3px
        }

        .step-text b {
            color: #f0c060
        }

        .step-text code {
            background: #120428;
            border: 1px solid #3a1a5e;
            border-radius: 4px;
            padding: 1px 6px;
            font-family: monospace;
            font-size: 12px;
            color: #e8521a
        }

        .url-box {
            background: #080118;
            border: 1px solid #2e1050;
            border-radius: 8px;
            padding: 12px 14px;
            font-family: monospace;
            font-size: 13px;
            color: #80ff80;
            word-break: break-all;
            margin-top: 8px
        }

        .copy-big {
            width: 100%;
            margin-top: 10px;
            padding: 13px;
            background: linear-gradient(135deg, #e8521a, #b33e10);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .15s
        }

        .copy-big:hover {
            opacity: .9
        }

        .empty {
            text-align: center;
            padding: 28px 0
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 12px
        }

        .empty-text {
            color: #8870aa;
            font-size: 14px;
            line-height: 1.7
        }

        .empty-text b {
            color: #f0c060
        }

        .reload {
            display: block;
            width: 100%;
            margin-top: 14px;
            padding: 12px;
            background: #1a0a30;
            border: 1.5px solid #3a1a5e;
            border-radius: 10px;
            color: #c8b8e0;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer
        }

        .reload:hover {
            border-color: #8870aa
        }

        .err {
            color: #f44747;
            font-size: 13px
        }
    </style>
</head>

<body>

    <h1>🎮 DustStore Setup</h1>
    <p class="sub">Нажми на любой чип — он скопируется в буфер.</p>

    <!-- Бот -->
    <div class="card">
        <div class="card-title">Бот</div>
        <?php if ($bot): ?>
            <div class="row">
                <div class="avatar av-bot"><?= mb_strtoupper(mb_substr($bot['name'] ?? 'B', 0, 1)) ?></div>
                <div class="row-info">
                    <div class="row-name"><?= htmlspecialchars($bot['name'] ?? '—') ?></div>
                    <div class="row-sub">@<?= htmlspecialchars($bot['username'] ?? '—') ?></div>
                </div>
            </div>
            <div class="chips"><?php chip('Bot user_id', (string)$bot['user_id']) ?></div>
        <?php else: ?>
            <div class="err">❌ Не удалось получить данные бота. Проверь токен.</div>
        <?php endif ?>
    </div>

    <!-- Пользователи -->
    <div class="card">
        <div class="card-title">Пользователи</div>
        <?php if (empty($users)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <div class="empty-text">
                    Нет данных.<br><br>
                    Попроси каждого пользователя <b>написать боту любое сообщение</b> в MAX.<br>
                    После этого обнови страницу — они появятся здесь.<br><br>
                    <small>Данные берутся из <code>messages_store.json</code> и <code>webhook_log.txt</code>.<br>
                        Убедись что вебхук зарегистрирован.</small>
                </div>
            </div>
            <button class="reload" onclick="location.reload()">↻ Обновить страницу</button>
        <?php else: ?>
            <div class="user-list">
                <?php foreach ($users as $u): ?>
                    <div class="user-item">
                        <div class="avatar av-user"><?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?></div>
                        <div class="row-info">
                            <div class="row-name"><?= htmlspecialchars($u['name']) ?></div>
                            <?php if ($u['last_msg']): ?>
                                <div class="row-sub"><?= htmlspecialchars(mb_strimwidth($u['last_msg'], 0, 55, '…')) ?></div>
                            <?php endif ?>
                            <div class="msg-count"><?= $u['count'] ?> сообщений<?php if ($u['last_ts']) echo ' · ' . date('d.m H:i', strtotime($u['last_ts'])); ?></div>
                            <div class="chips">
                                <?php if ($u['user_id']) chip('user_id', $u['user_id']); ?>
                                <?php chip('chat_id', $u['chat_id']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <button class="reload" onclick="location.reload()" style="margin-top:14px">↻ Обновить</button>
        <?php endif ?>
    </div>

    <!-- URL сервера -->
    <div class="card">
        <div class="card-title">URL сервера</div>
        <div class="url-box"><?= htmlspecialchars($serverUrl) ?></div>
        <button class="copy-big" onclick="copyText('<?= addslashes($serverUrl) ?>', this)">⎘ Скопировать URL сервера</button>
    </div>

    <!-- Инструкция -->
    <div class="card">
        <div class="card-title">Как настроить приложение</div>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">Открой приложение → нажми <b>⚙</b></div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">Поле <b>Сервер</b> — вставь URL выше</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">Поле <b>Мой ID</b> — вставь свой <code>user_id</code> из таблицы</div>
            </div>
            <div class="step">
                <div class="step-num">4</div>
                <div class="step-text">Поле <b>Chat ID</b> — вставь свой <code>chat_id</code></div>
            </div>
            <div class="step">
                <div class="step-num">5</div>
                <div class="step-text">Нажми <b>＋</b>, добавь собеседника по его <code>user_id</code></div>
            </div>
        </div>
    </div>

    <script>
        function copyChip(val, id) {
            navigator.clipboard.writeText(val).then(() => {
                const el = document.getElementById(id);
                el.classList.add('copied');
                el.querySelector('.chip-btn').textContent = '✓';
                setTimeout(() => {
                    el.classList.remove('copied');
                    el.querySelector('.chip-btn').textContent = '⎘';
                }, 1600);
            }).catch(() => prompt('Скопируй вручную:', val));
        }

        function copyText(val, btn) {
            navigator.clipboard.writeText(val).then(() => {
                const o = btn.textContent;
                btn.textContent = '✓ Скопировано!';
                setTimeout(() => btn.textContent = o, 1600);
            }).catch(() => prompt('Скопируй вручную:', val));
        }
    </script>
</body>

</html>