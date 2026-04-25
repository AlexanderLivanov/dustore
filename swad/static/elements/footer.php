<?php
function getLastCommitInfo()
{
    $repo = "AlexanderLivanov/dustore"; // <<< поменяй
    $branch = "main";        // или master

    $url = "https://api.github.com/repos/$repo/commits/$branch";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'version-checker'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;

    $commitData = json_decode($response, true);

    $sha = $commitData['sha'];
    $message = $commitData['commit']['message'];
    $date = $commitData['commit']['committer']['date'];

    // Парсим версию из сообщения
    preg_match('/v(\d+\.\d+\.\d+)/', $message, $match);
    $version = $match[0] ?? "no version";

    return [
        'version' => $version,
        'sha' => $sha,
        'short' => substr($sha, 0, 7),
        'date' => date("d.m.Y H:i", strtotime($date)),
        'url' => "https://github.com/$repo/commit/$sha"
    ];
}

$info = getLastCommitInfo();
?>

<head>
    <style>
        #notify-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 320px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notify {
            background: rgba(20, 20, 20, 0.95);
            padding: 14px 16px;
            border-radius: 12px;
            color: #fff;
            font-family: system-ui, sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            opacity: 0;
            transform: translateX(30px);
            animation: slide-in 0.25s forwards, fade-out 0.4s 4s forwards;
        }

        @keyframes slide-in {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fade-out {
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }
    </style>

</head>
<div class="footer">
    &copy; 2025 DUST STUDIO. Все права защищены.
    <br>
    <a href="https://vk.com/dgscorp">VKontakte (DGS)</a> .
    <a href="https://vk.com/crazyprojectslab">VKontakte (CPL)</a> .
    <a href="https://t.me/dgscorp">Telegram (DGS)</a> .
    <a href="https://t.me/dustore_official">Telegram (DUSTORE)</a> .
    <a href="/oferta.txt">Публичная оферта</a> .
    <a href="/developer-agreement">Соглашение с разработчиком</a>
    <p class="footer-p">DUSTORE (Dust Store) является собственностью Dust Studio и Crazy Projects Lab. Все торговые марки являются собственностью соответствующих владельцев. НДС включён во все цены, где он применим</p>
    <!-- <p class="footer-p">ИП Ливанов Александр Алексеевич <br>ИНН 771392840109 <br>ОГРНИП 326774600034839 <br>г. Москва</p> -->
    <!-- SemVer spec: status-global.design.tech#patch -->
    <!-- <p class="footer-p">Версия платформы: beta-1.11.24#22</p> -->
    <?php if ($info): ?>
        <p class="footer-p">Версия: <strong><?= $info['version'] ?></strong>
            | <a href="<?= $info['url'] ?>" target="_blank"><?= $info['short'] ?></a>
            | <?= $info['date'] ?></p>
    <?php else: ?>
        Не удалось получить версию
    <?php endif; ?>
</div>
<div id="notify-container"></div>
<style>
    #notify-container {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 320px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .notify {
        background: rgba(20, 20, 20, 0.95);
        padding: 14px 16px;
        border-radius: 12px;
        color: #fff;
        font-family: system-ui, sans-serif;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        opacity: 0;
        transform: translateX(30px);
        animation: slide-in 0.25s forwards, fade-out 0.4s 4s forwards;
    }

    @keyframes slide-in {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fade-out {
        to {
            opacity: 0;
            transform: translateX(30px);
        }
    }
</style>
<!-- <script>
    document.addEventListener("DOMContentLoaded", () => {
        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }
    });
</script>
<script>
    const source = new EventSource("/swad/controllers/notification_stream.php");

    source.onmessage = (event) => {
        const data = JSON.parse(event.data);
        notify(data.title, data.message);
    };
</script> -->