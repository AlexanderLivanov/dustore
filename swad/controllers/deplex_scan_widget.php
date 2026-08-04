<?php
/**
 * deplex_scan_widget.php — статус антивирус-скана + модалка с деталями + (опц.) перепроверка.
 * include на game.php (под кнопкой скачивания) и в панели экспертов.
 *
 * Ожидает: $conn (PDO), $game['id']. Требует подключённого deplex_web.php.
 * Опционально перед include: $deplex_can_rescan = true;  — показать кнопку «Перепроверить».
 *
 * Цвета захардкожены (не CSS-переменные), чтобы виджет одинаково выглядел на любой странице.
 */
if (!function_exists('deplex_scan_summary')) {
    return;
}
// game.php держит соединение в $pdo, dev/expert-страницы — в $conn. Работаем с любым.
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo;
}
$__gid  = (int) ($game['id'] ?? 0);
$__scan = deplex_scan_summary($conn, $__gid);
if ($__scan === null) {
    return; // у игры нет deplex-билда — виджет не показываем
}
$__details   = deplex_scan_details($conn, (int) $__scan['build_id']);
$__canRescan = !empty($deplex_can_rescan);
$__terminal  = in_array($__scan['status'], ['clean', 'infected', 'skipped', 'error'], true);

$__map = [
    'clean'    => ['#00d68f', 'Проверено антивирусом', '🛡'],
    'infected' => ['#ff5f57', 'Обнаружена угроза',      '⚠'],
    'queued'   => ['#febc2e', 'В очереди на проверку',  '⏳'],
    'scanning' => ['#febc2e', 'Идёт проверка…',          '⏳'],
    'error'    => ['#ffa04d', 'Ошибка проверки',         '❗'],
    'skipped'  => ['#9aa0b0', 'Проверка пропущена',      '—'],
];
[$__c, $__label, $__icon] = $__map[$__scan['status']] ?? ['#9aa0b0', $__scan['status'], '•'];
?>
<div style="margin-top:10px;font-size:12px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;
            background:<?= $__c ?>22;border:1px solid <?= $__c ?>55;color:<?= $__c ?>;font-weight:600;">
            <?= $__icon ?> <?= htmlspecialchars($__label) ?>
        </span>
        <?php if ($__scan['duration_sec'] !== null): ?>
            <span style="color:#8a8a99;">за <?= deplex_duration((int) $__scan['duration_sec']) ?></span>
        <?php endif; ?>
        <?php if ($__terminal): ?>
            <a href="#" style="color:#e88fc0;text-decoration:none;"
               onclick="document.getElementById('dpx-scan-modal').style.display='flex';return false;">Отчёт</a>
        <?php endif; ?>
        <?php if ($__canRescan): ?>
            <button type="button" onclick="dpxRescan(<?= $__gid ?>, this)"
                style="margin-left:auto;background:transparent;border:1px solid #ffffff33;color:#cfc9da;
                       border-radius:8px;padding:3px 12px;font-size:11px;cursor:pointer;">Перепроверить</button>
        <?php endif; ?>
    </div>
    <?php if ($__scan['status'] === 'infected' && $__scan['signature']): ?>
        <div style="margin-top:6px;color:#ff8a80;">Сигнатура: <code><?= htmlspecialchars($__scan['signature']) ?></code></div>
    <?php elseif ($__scan['status'] === 'queued' || $__scan['status'] === 'scanning'): ?>
        <div style="margin-top:6px;color:#8a8a99;">Билд станет доступен для скачивания после проверки.</div>
    <?php endif; ?>
</div>

<!-- Модалка с пофайловым отчётом -->
<div id="dpx-scan-modal" style="display:none;position:fixed;inset:0;z-index:10000;
     background:rgba(0,0,0,.78);align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)this.style.display='none';">
    <div style="background:#160a24;border:1px solid #ffffff1f;border-radius:16px;max-width:640px;width:100%;
         max-height:82vh;overflow:auto;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">Отчёт антивируса</div>
            <button onclick="document.getElementById('dpx-scan-modal').style.display='none';"
                style="background:none;border:none;color:#999;font-size:20px;cursor:pointer;">✕</button>
        </div>
        <div style="font-size:12px;color:#9aa0b0;margin-bottom:14px;line-height:1.7;">
            Билд <code><?= htmlspecialchars($__scan['ulid']) ?></code> · статус: <?= htmlspecialchars($__label) ?>
            <?php if ($__scan['duration_sec'] !== null): ?> · время скана: <?= deplex_duration((int) $__scan['duration_sec']) ?><?php endif; ?>
            <?php if ($__scan['started_at']): ?><br>Начат: <?= htmlspecialchars($__scan['started_at']) ?>
                <?php if ($__scan['finished_at']): ?> · завершён: <?= htmlspecialchars($__scan['finished_at']) ?><?php endif; ?>
            <?php endif; ?>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;color:#ddd;">
            <thead>
                <tr style="color:#8a8a99;text-align:left;">
                    <th style="padding:6px 4px;">Файл</th><th>Движок</th><th>Результат</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($__details as $d):
                $ds = (string) $d['status'];
                $dc = $ds === 'clean' ? '#00d68f'
                    : ($ds === 'flagged' ? '#ff5f57'
                    : ($ds === 'error' ? '#ffa04d' : '#9aa0b0'));
            ?>
                <tr style="border-top:1px solid #ffffff14;">
                    <td style="padding:6px 4px;word-break:break-all;"><?= htmlspecialchars($d['file_path']) ?></td>
                    <td style="text-transform:uppercase;color:#8a8a99;"><?= htmlspecialchars($d['engine']) ?></td>
                    <td style="color:<?= $dc ?>;">
                        <?= htmlspecialchars($ds) ?><?= $d['signature'] ? ' (' . htmlspecialchars($d['signature']) . ')' : '' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$__details): ?>
                <tr><td colspan="3" style="padding:10px 4px;color:#8a8a99;">
                    <?= $__scan['source'] === 'web'
                        ? 'Архив просканирован целиком (clamd развернул содержимое). Пофайловой разбивки для веб-загрузки нет.'
                        : 'Пофайловых записей пока нет.' ?>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($__canRescan): ?>
<script>
function dpxRescan(gameId, btn) {
    btn.disabled = true;
    btn.textContent = 'Отправляю…';
    fetch('/devs/deplex_rescan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: 'game_id=' + encodeURIComponent(gameId)
    }).then(r => r.json()).then(d => {
        if (d.ok) { btn.textContent = 'Отправлено на перепроверку'; setTimeout(() => location.reload(), 1200); }
        else { btn.disabled = false; btn.textContent = 'Перепроверить'; alert(d.error || 'Ошибка'); }
    }).catch(() => { btn.disabled = false; btn.textContent = 'Перепроверить'; alert('Сетевая ошибка'); });
}
</script>
<?php endif; ?>