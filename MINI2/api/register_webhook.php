<?php
$token = "f9LHodD0cOJZzTOYrmOSPOmFafrLTZKMMe82Dd7Hq9Fg5rsyA9bzFu64NoN3mCPB9GvP0THipUc0uHJVYg-8";

function api($method, $endpoint, $data, $token)
{
    $ch = curl_init("https://platform-api.max.ru/" . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ["Authorization: $token", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => $data ? json_encode($data) : null,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true)];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$result = null;

// Удалить конкретный вебхук
if ($action === 'delete' && !empty($_POST['url'])) {
    $result = api('DELETE', 'subscriptions?url=' . urlencode($_POST['url']), null, $token);
}

// Удалить все вебхуки
if ($action === 'delete_all') {
    $subs = api('GET', 'subscriptions', null, $token);
    foreach (($subs['body']['subscriptions'] ?? []) as $sub) {
        api('DELETE', 'subscriptions?url=' . urlencode($sub['url']), null, $token);
    }
    $result = ['status' => 200, 'body' => ['success' => true, 'message' => 'Все удалены']];
}

// Зарегистрировать новый
if ($action === 'register' && !empty($_POST['url'])) {
    $result = api('POST', 'subscriptions', [
        'url'          => $_POST['url'],
        'update_types' => ['message_created', 'bot_started'],
        'version'      => '1',
    ], $token);
}

$current = api('GET', 'subscriptions', null, $token);
$webhook_url = $_POST['url'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register Webhook</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            max-width: 700px
        }

        h1 {
            color: #ce9178;
            margin-bottom: 20px
        }

        h2 {
            color: #569cd6;
            margin: 16px 0 8px
        }

        .block {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 14px
        }

        pre {
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
            background: #1a1a1a;
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px
        }

        .ok {
            color: #4ec9b0
        }

        .err {
            color: #f44747
        }

        input {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #555;
            background: #2d2d2d;
            color: #fff;
            font-size: 14px;
            margin: 8px 0;
            box-sizing: border-box
        }

        .btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px
        }

        button {
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600
        }

        .btn-green {
            background: #0e8a5f;
            color: #fff
        }

        .btn-red {
            background: #a33;
            color: #fff
        }

        .btn-gray {
            background: #444;
            color: #fff
        }

        .sub-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-bottom: 1px solid #333;
            font-size: 12px
        }

        .sub-url {
            flex: 1;
            word-break: break-all;
            color: #9cdcfe
        }
    </style>
</head>

<body>
    <h1>🔗 Вебхуки MAX</h1>

    <?php if ($result): ?>
        <div class="block">
            <h2>Результат</h2>
            <span class="<?= $result['status'] == 200 ? 'ok' : 'err' ?>">HTTP <?= $result['status'] ?></span>
            <pre><?= htmlspecialchars(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif ?>

    <div class="block">
        <h2>Текущие подписки (<?= count($current['body']['subscriptions'] ?? []) ?>)</h2>
        <?php foreach (($current['body']['subscriptions'] ?? []) as $sub): ?>
            <div class="sub-item">
                <span class="sub-url"><?= htmlspecialchars($sub['url']) ?></span>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="url" value="<?= htmlspecialchars($sub['url']) ?>">
                    <button class="btn-red" type="submit">✕</button>
                </form>
            </div>
        <?php endforeach ?>
        <?php if (!empty($current['body']['subscriptions'])): ?>
            <form method="POST" style="margin-top:10px">
                <input type="hidden" name="action" value="delete_all">
                <button class="btn-red" type="submit">🗑 Удалить все</button>
            </form>
        <?php endif ?>
    </div>

    <div class="block">
        <h2>Добавить вебхук</h2>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <input type="url" name="url" value="<?= htmlspecialchars($webhook_url) ?>" placeholder="https://ВАШ_ТОННЕЛЬ/api/webhook.php">
            <div class="btns">
                <button class="btn-green" type="submit">➕ Зарегистрировать</button>
            </div>
        </form>
    </div>

</body>

</html>