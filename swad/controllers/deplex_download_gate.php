<?php
/**
 * deplex_download_gate.php — модалка подтверждения скачивания со сводкой антивируса.
 * include ОДИН раз на странице игры (нужны $conn|$pdo и $game). Требует deplex_web.php.
 *
 * В кнопках/ссылках скачивания:  onclick="dpxDownload('URL'); return false;"
 * Работает для любого варианта (deplex-установщик, веб-зип, APK) — просто передай URL.
 */
if (!function_exists('deplex_scan_summary')) {
    return;
}
$__db = $conn ?? ($pdo ?? null);
$__s  = $__db ? deplex_scan_summary($__db, (int) ($game['id'] ?? 0)) : null;

$__badge = ['#9aa0b0', 'Антивирус: нет данных', '•'];
if ($__s) {
    $__m = [
        'clean'    => ['#00d68f', 'Проверено антивирусом — угроз не найдено', '🛡'],
        'skipped'  => ['#febc2e', 'Проверка пропущена (слишком большой файл)', '—'],
        'infected' => ['#ff5f57', 'Обнаружена угроза', '⚠'],
        'error'    => ['#ffa04d', 'Ошибка проверки', '❗'],
        'queued'   => ['#febc2e', 'В очереди на проверку', '⏳'],
        'scanning' => ['#febc2e', 'Идёт проверка', '⏳'],
    ];
    $__badge = $__m[$__s['status']] ?? $__badge;
}
?>
<div id="dpx-dl-gate" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.8);
     align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)this.style.display='none';">
    <div style="background:#160a24;border:1px solid #ffffff1f;border-radius:18px;max-width:460px;width:100%;padding:26px;">
        <div style="font-size:17px;font-weight:800;color:#fff;margin-bottom:2px;">Перед скачиванием</div>

        <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:9px 12px;border-radius:10px;
             background:<?= $__badge[0] ?>18;border:1px solid <?= $__badge[0] ?>44;">
            <span style="font-size:16px;"><?= $__badge[2] ?></span>
            <span style="color:<?= $__badge[0] ?>;font-weight:600;font-size:13px;"><?= htmlspecialchars($__badge[1]) ?></span>
        </div>
        <?php if ($__s): ?>
        <div style="font-size:11px;color:#8a8a99;margin-bottom:14px;line-height:1.7;">
            Движок: ClamAV<?php if ($__s['duration_sec'] !== null): ?> · время проверки: <?= deplex_duration((int) $__s['duration_sec']) ?><?php endif; ?>
            <?php if (!empty($__s['finished_at'])): ?><br>Проверено: <?= htmlspecialchars($__s['finished_at']) ?><?php endif; ?>
            <?php if ($__s['status'] === 'infected' && $__s['signature']): ?><br>Сигнатура: <code><?= htmlspecialchars($__s['signature']) ?></code><?php endif; ?>
        </div>
        <?php endif; ?>

        <p style="font-size:13px;color:#cfc9da;line-height:1.65;margin-bottom:18px;">
            Файлы прогнали через антивирус — результат выше. Но давай честно: ни один антивирус
            не ловит 100% угроз, что-то теоретически может проскочить. Так что скачиваешь на свой
            страх и риск — платформа не отвечает за то, как программа поведёт себя на твоём устройстве.
            Доверяешь разработчику — вперёд 👍
        </p>

        <div style="display:flex;gap:10px;">
            <button type="button" onclick="document.getElementById('dpx-dl-gate').style.display='none';"
                style="flex:1;background:transparent;border:1px solid #ffffff33;color:#cfc9da;border-radius:10px;
                       padding:11px;cursor:pointer;font-size:14px;">Отмена</button>
            <a id="dpx-dl-go" href="#" rel="noopener"
                style="flex:2;text-align:center;background:linear-gradient(135deg,#c32178,#74155d);color:#fff;
                       border-radius:10px;padding:11px;text-decoration:none;font-weight:700;font-size:14px;">Скачать</a>
        </div>
    </div>
</div>
<script>
function dpxDownload(url) {
    document.getElementById('dpx-dl-go').setAttribute('href', url);
    document.getElementById('dpx-dl-gate').style.display = 'flex';
}
document.getElementById('dpx-dl-go').addEventListener('click', function () {
    setTimeout(function () { document.getElementById('dpx-dl-gate').style.display = 'none'; }, 150);
});
</script>