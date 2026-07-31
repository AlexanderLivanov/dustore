<?php
/**
 * devs_edit_deplex_tab.php — вкладка «Через deplex» для /devs/edit.
 * include внутри карточки «Файл игры».
 *
 * Ожидает в области видимости: $conn (PDO), $project_id (int), $studio_id (int).
 * Требует подключённого swad/controllers/deplex_web.php.
 */
$dpx_info  = deplex_build_info($conn, (int)$project_id);
$dpx_quota = deplex_studio_quota($conn, (int)$studio_id);
$dpx_pct   = $dpx_quota['quota'] > 0 ? min(100, round($dpx_quota['used'] * 100 / $dpx_quota['quota'])) : 0;
?>

<?php if (!empty($_SESSION['new_deplex_token'])): ?>
<div class="alert alert-ok" style="margin-bottom:12px;">
    Токен создан — сохрани, больше не покажем:<br>
    <code style="user-select:all;word-break:break-all;"><?= htmlspecialchars($_SESSION['new_deplex_token']) ?></code>
</div>
<?php unset($_SESSION['new_deplex_token']); endif; ?>

<!-- Квота (общая для обоих путей загрузки) -->
<div style="margin-bottom:14px;font-size:12px;color:var(--tm);">
    Занято <?= deplex_human($dpx_quota['used']) ?> из <?= deplex_human($dpx_quota['quota']) ?>
    <span style="opacity:.7;">(deplex <?= deplex_human($dpx_quota['deplex']) ?> + архивы <?= deplex_human($dpx_quota['web']) ?>)</span>
    <div style="height:6px;background:var(--elev);border-radius:3px;overflow:hidden;margin-top:6px;">
        <div style="height:100%;width:<?= $dpx_pct ?>%;background:var(--p);border-radius:3px;"></div>
    </div>
</div>

<?php if ($dpx_info === null): ?>
    <!-- Файлов ещё нет: краткая инструкция -->
    <ol style="font-size:13px;color:var(--ts);line-height:1.9;padding-left:18px;margin:0 0 12px;">
        <li>Скачай <a href="/download/deplex" style="color:var(--pl);">deplex</a> и добавь в PATH.</li>
        <li>Создай deploy-токен:
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="create_deplex_token">
                <button type="submit" class="btn btn-g" style="padding:4px 12px;font-size:12px;">Создать токен</button>
            </form>
        </li>
        <li><code>deplex auth &lt;твой токен&gt;</code></li>
        <li><code>deplex select <?= (int)$project_id ?></code></li>
        <li>В папке с билдом: <code>deplex init .</code></li>
        <li><code>deplex update -m "первый билд"</code></li>
    </ol>
    <div style="font-size:11px;color:var(--tm);">
        После первого <code>deplex update</code> здесь появятся файлы билда,
        а на странице игры — кнопка «Скачать (установщик)».
    </div>

<?php else: ?>
    <!-- Файлы есть: инфо о билде + список файлов -->
    <div class="alert alert-ok" style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
        <span class="material-icons" style="font-size:18px;">check_circle</span>
        <div>
            Загружено через deplex · <?= deplex_human($dpx_info['total_size']) ?> · <?= (int)$dpx_info['chunk_count'] ?> чанков
            <?php if (!empty($dpx_info['committed_at'])): ?>
                <div style="font-size:11px;color:var(--tm);">Обновлён: <?= htmlspecialchars($dpx_info['committed_at']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .dpx-tree details > summary { list-style: none; }
        .dpx-tree summary::-webkit-details-marker { display: none; }
        .dpx-tree summary:hover { color: var(--pl); }
    </style>
    <div class="dpx-tree" style="max-height:340px;overflow:auto;padding-right:4px;">
        <?php deplex_render_tree(deplex_files_tree($dpx_info['files'])); ?>
    </div>

    <div style="font-size:11px;color:var(--tm);margin-top:10px;">
        Папки свёрнуты — кликни, чтобы раскрыть. Обновить билд — ещё раз <code>deplex update</code> из папки с игрой.
    </div>
<?php endif; ?>