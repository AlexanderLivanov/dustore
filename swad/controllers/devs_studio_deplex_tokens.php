<?php
/**
 * devs_studio_deplex_tokens.php — карточка deploy-токенов deplex для страницы студии.
 * include ПОСЛЕ основной <form> профиля (свои формы, чтобы не вкладывать в профильную).
 *
 * Ожидает в области видимости: $conn (PDO), $studio_id (int).
 * Токен показывается один раз из $_SESSION['new_deplex_token'] (ставится POST-обработчиком).
 */
$dpx_tokens = $conn->prepare(
    "SELECT id, token_prefix, label, last_used_at
     FROM deplex_tokens
     WHERE studio_id = ? AND revoked = 0
     ORDER BY created_at DESC"
);
$dpx_tokens->execute([(int)$studio_id]);
$dpx_tokens = $dpx_tokens->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card col-full" style="margin-top:16px;border-color:rgba(195,33,120,.25);">
    <div class="card-title"><span class="material-icons">terminal</span>Deploy-токены (deplex CLI)</div>
    <p style="font-size:12px;color:var(--tm);margin-bottom:14px;line-height:1.6;">
        Токен для загрузки билдов через <code>deplex</code>. Действует на всю студию.
        Отличается от «API Токена» выше — тот для общего API, этот только для CLI-загрузки.
        Показывается один раз при создании — сохраните сразу.
    </p>

    <?php if (!empty($_SESSION['new_deplex_token'])): ?>
    <div class="alert alert-ok" style="margin-bottom:14px;">
        Новый токен — сохраните, больше не покажем:<br>
        <code style="user-select:all;word-break:break-all;font-size:13px;"><?= htmlspecialchars($_SESSION['new_deplex_token']) ?></code>
    </div>
    <?php unset($_SESSION['new_deplex_token']); endif; ?>

    <?php if ($dpx_tokens): ?>
    <table style="width:100%;font-size:12px;color:var(--ts);border-collapse:collapse;margin-bottom:14px;">
        <thead>
            <tr style="color:var(--tm);text-align:left;">
                <th style="padding:6px 0;">Токен</th>
                <th>Метка</th>
                <th>Последнее использование</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dpx_tokens as $t): ?>
            <tr style="border-top:1px solid var(--elev);">
                <td style="padding:8px 0;"><code><?= htmlspecialchars($t['token_prefix']) ?>…</code></td>
                <td><?= htmlspecialchars($t['label'] ?? '—') ?></td>
                <td style="color:var(--tm);"><?= $t['last_used_at'] ? htmlspecialchars($t['last_used_at']) : 'ни разу' ?></td>
                <td style="text-align:right;">
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Отозвать токен? Он сразу перестанет работать.')">
                        <input type="hidden" name="action" value="revoke_deplex_token">
                        <input type="hidden" name="token_id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn-d" style="padding:4px 12px;font-size:11px;">Отозвать</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:12px;color:var(--tm);margin-bottom:14px;">Токенов пока нет.</p>
    <?php endif; ?>

    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="action" value="create_deplex_token">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>Метка (необязательно)</label>
            <input type="text" name="label" maxlength="120" placeholder="Напр. «Ноутбук Саши»">
        </div>
        <button type="submit" class="btn btn-p"><span class="material-icons">add</span>Создать токен</button>
    </form>
</div>