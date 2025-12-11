<?php
function getProjectPulse($repo = "AlexanderLivanov/dustore")
{
    $headers = ["User-Agent: ProjectPulse"];

    // ---- last commit ----
    $commitUrl = "https://api.github.com/repos/$repo/commits";
    $c = curl_init($commitUrl);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $commitRaw = curl_exec($c);
    curl_close($c);

    if (!$commitRaw) return null;
    $commits = json_decode($commitRaw, true);

    $last = $commits[0] ?? "0";
    $lastCommitSha = $last["sha"] ?? "";
    $lastCommitMsg = $last["commit"]["message"] ?? "";
    $lastCommitDate = $last["commit"]["committer"]["date"] ?? "";
    $lastCommitAuthor = $last["commit"]["committer"]["name"] ?? "";

    // ---- contributors stats ----
    $statsUrl = "https://api.github.com/repos/$repo/stats/commit_activity";
    $s = curl_init($statsUrl);
    curl_setopt_array($s, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $statsRaw = curl_exec($s);
    curl_close($s);

    $commitActivity = json_decode($statsRaw, true);
    $commitsLastMonth = $commitActivity ? array_sum(array_column(array_slice($commitActivity, -4), "total")) : 0;

    // ---- repo info ----
    $infoUrl = "https://api.github.com/repos/$repo";
    $i = curl_init($infoUrl);
    curl_setopt_array($i, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $infoRaw = curl_exec($i);
    curl_close($i);

    $info = json_decode($infoRaw, true);

    // ---- issues ----
    $openIssues = $info["open_issues_count"] ?? 0;
    $stars = $info["stargazers_count"] ?? 0;
    $watchers = $info["subscribers_count"] ?? 0;
    $forks = $info["forks"] ?? 0;

    // activity status
    $daysSinceCommit = (time() - strtotime($lastCommitDate)) / 86400;
    if ($daysSinceCommit < 7) $status = "🔥 Active";
    elseif ($daysSinceCommit < 30) $status = "⚡ Moderate";
    else $status = "💤 Inactive";

    return [
        "status" => $status,
        "last_commit_msg" => $lastCommitMsg,
        "last_commit_date" => date("d.m.Y H:i", strtotime($lastCommitDate)),
        "last_commit_author" => $lastCommitAuthor,
        "last_sha_short" => substr($lastCommitSha, 0, 7),

        "month_commits" => $commitsLastMonth,
        "stars" => $stars,
        "watchers" => $watchers,
        "forks" => $forks,
        "open_issues" => $openIssues,

        "url_commit" => $last["html_url"],
        "url_repo" => $info["html_url"] ?? ""
    ];
}

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
    <!-- SemVer spec: status-global.design.tech#patch -->
    <!-- <p class="footer-p">Версия платформы: beta-1.11.24#22</p> -->
    <!-- <?php if ($info): ?>
        <p class="footer-p">Версия: <strong><?= $info['version'] ?></strong>
            | <a href="<?= $info['url'] ?>" target="_blank"><?= $info['short'] ?></a>
            | <?= $info['date'] ?></p>
    <?php else: ?>
        Не удалось получить версию
    <?php endif; ?> -->



    
    <?php
    $pulse = getProjectPulse("AlexanderLivanov/dustore");
    ?>

    <?php if ($pulse): ?>
        <p class="footer-p">
            <strong>Пульс проекта:</strong> <?= $pulse["status"] ?><br>
            Последний коммит: <a href="<?= $pulse["url_commit"] ?>" target="_blank"><?= $pulse["last_sha_short"] ?></a>
            — <?= $pulse["last_commit_msg"] ?>
            <br>
            Дата: <?= $pulse["last_commit_date"] ?> | Автор: <?= $pulse["last_commit_author"] ?><br>
            Коммитов за месяц: <?= $pulse["month_commits"] ?>
            | ⭐ <?= $pulse["stars"] ?>
            | 👁 <?= $pulse["watchers"] ?>
            | 🍴 <?= $pulse["forks"] ?>
            | 🐞 Issues: <?= $pulse["open_issues"] ?>
        </p>
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