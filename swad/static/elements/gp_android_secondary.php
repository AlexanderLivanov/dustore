<?php
/**
 * swad/static/elements/gp_android_secondary.php — «также доступно на Android»,
 * компактная вторичная карточка на карточке покупки game.php.
 *
 * Раньше эта разметка существовала только внутри одной ветки (десктоп +
 * android). Вынесена в инклюд, потому что теперь у игры может стать видна
 * из трёх разных мест: десктоп-zip + android, deplex-установщик + android,
 * или веб-плеер + android — код ветвления отличается, а виджет — нет.
 *
 * Ожидает в области видимости: $game (array), $game_id (int).
 * Требует функцию formatFileSize() (объявлена в game.php выше по файлу).
 */
?>
<div class="gp-android-card" style="margin-top:8px">
    <div class="gp-android-header">
        <span class="gp-android-icon">🤖</span>
        <div>
            <div class="gp-android-title">Также доступно на Android</div>
            <div class="gp-android-sub">APK · прямая загрузка</div>
        </div>
    </div>
    <div class="gp-apk-btn-wrap">
        <a class="gp-btn gp-btn-android" href="/swad/controllers/download_apk.php?game_id=<?= $game_id ?>" onclick="handleApkClick(event)">
            <span>📲</span><span>Скачать APK<?= !empty($game['game_zip_size']) ? ' (' . formatFileSize((int)$game['game_zip_size']) . ')' : '' ?></span>
        </a>
        <div class="gp-qr-popup">
            <div class="gp-qr-title">Сканируй — и скачаешь прямо на телефон</div>
            <div id="apkQrCode"></div>
            <div class="gp-qr-hint">Открой камеру и наведи на код</div>
        </div>
    </div>
    <div class="gp-apk-progress" id="apkProgress">
        <div class="gp-apk-track"><div class="gp-apk-fill" id="apkFill"></div></div>
        <div class="gp-apk-label" id="apkLabel">Подготовка…</div>
    </div>
</div>
