<?php
/**
 * swad/static/elements/footer.php
 *
 * Что было не так с блоком версии:
 *
 * 1. Он ходил в api.github.com СИНХРОННО НА КАЖДОЙ ЗАГРУЗКЕ ЛЮБОЙ СТРАНИЦЫ,
 *    без таймаута. Пока GitHub отвечает — весь сайт ждёт. Из России это
 *    десятки, а то и сотни миллисекунд к каждому запросу; если API недоступен,
 *    страница висит до дефолтного таймаута curl (несколько минут).
 *    Теперь ответ кэшируется на 6 часов в файл, таймаут 2 секунды,
 *    и при недоступности берётся прошлое значение из кэша.
 *
 * 2. Разбор ответа был сломан целиком:
 *        $commitData = json_decode($response, true);   // присвоили
 *        $sha = $gitInfo['sha'] ?? '';                 // читаем ДРУГУЮ переменную
 *        preg_match('/v(\d+...)/', $message, $match);  // $message не существует
 *        'date' => date("d.m.Y H:i", strtotime($date)) // $date не существует
 *    $gitInfo, $message и $date не определены нигде. Отсюда и
 *    «Версия: no version |  | 01.01.1970 03:00»: пустой sha, пустая версия,
 *    а strtotime(null) вернул false, который date() показал как эпоху.
 *
 * 3. Посреди body стоял <head> с блоком стилей, полностью дублирующим тот,
 *    что идёт ниже. Убрано.
 */

function getLastCommitInfo(): ?array
{
    $repo   = "AlexanderLivanov/dustore";
    $branch = "main";

    $cacheFile = sys_get_temp_dir() . '/dustore_version.json';
    $cacheTtl  = 6 * 3600;

    // свежий кэш — отдаём сразу, в сеть не идём
    if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }

    $ch = curl_init("https://api.github.com/repos/$repo/commits/$branch");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'dustore-version-checker',
        CURLOPT_TIMEOUT        => 2,   // без него страница ждала бы вечно
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $response = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $code !== 200) {
        // сеть недоступна — показываем прошлое значение, даже протухшее
        if (is_readable($cacheFile)) {
            $stale = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($stale)) return $stale;
        }
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['sha'])) return null;

    $sha     = (string)$data['sha'];
    $message = (string)($data['commit']['message'] ?? '');
    $date    = (string)($data['commit']['author']['date'] ?? $data['commit']['committer']['date'] ?? '');

    preg_match('/v(\d+\.\d+\.\d+)/', $message, $m);

    $info = [
        'version' => $m[0] ?? 'dev',
        'sha'     => $sha,
        'short'   => substr($sha, 0, 7),
        'date'    => $date !== '' ? date('d.m.Y H:i', strtotime($date)) : '',
        'url'     => "https://github.com/$repo/commit/$sha",
    ];

    @file_put_contents($cacheFile, json_encode($info), LOCK_EX);
    return $info;
}

$info = getLastCommitInfo();
?>
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

    <?php if ($info && $info['short'] !== ''): ?>
        <p class="footer-p">
            Версия: <strong><?= htmlspecialchars($info['version'], ENT_QUOTES) ?></strong>
            | <a href="<?= htmlspecialchars($info['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($info['short'], ENT_QUOTES) ?></a>
            <?php if ($info['date'] !== ''): ?>| <?= htmlspecialchars($info['date'], ENT_QUOTES) ?><?php endif; ?>
        </p>
    <?php endif; ?>
    <?php /* Раньше при неудаче печаталось «Не удалось получить версию».
             Версия сборки — служебная информация; если её нет, посетителю
             про это знать незачем, блок просто не рисуется. */ ?>
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
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes fade-out {
        to { opacity: 0; transform: translateX(30px); }
    }
</style>