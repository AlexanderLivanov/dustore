<?php
/**
 * l4t/views/game_credits.php — «Над игрой работали» на странице игры.
 * Каждое имя ведёт в профиль L4T: игра становится входом в портфолио
 * людей, а портфолио — в игру. Подключается из game.php, ожидает $game.
 * Любой сбой — блок просто не рисуется, страница игры не страдает.
 */
$__cr = [];
try {
    require_once __DIR__ . '/../lib/extras.php';
    $__db = new Database();
    $__x  = new L4TX($__db->connect(), $__db->connect('desl4t') ?: null);
    $__cr = $__x->gameCredits((int)$game['id']);
} catch (Throwable $e) {
    error_log('[game_credits] ' . $e->getMessage());
}
if ($__cr): ?>
<div class="gp-info-card">
    <h3>Над игрой работали</h3>
    <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach (array_slice($__cr, 0, 12) as $__p): ?>
            <a href="/l4t/<?= htmlspecialchars(rawurlencode($__p['handle'])) ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit">
                <?php if ($__p['avatar']): ?>
                    <img src="<?= htmlspecialchars($__p['avatar']) ?>" alt="" style="width:30px;height:30px;object-fit:cover;flex:none">
                <?php else: ?>
                    <span style="width:30px;height:30px;display:grid;place-items:center;background:rgba(195,33,120,.2);font-weight:700;flex:none"><?= htmlspecialchars(mb_strtoupper(mb_substr(ltrim($__p['name'], '@'), 0, 1))) ?></span>
                <?php endif; ?>
                <span style="display:flex;flex-direction:column;line-height:1.3;min-width:0">
                    <span style="font-weight:600"><?= htmlspecialchars($__p['name']) ?><?= $__p['verified'] ? ' <span title="Подтверждено данными Dustore" style="color:#2ee6a8">✓</span>' : '' ?></span>
                    <span style="font-size:12px;opacity:.6"><?= htmlspecialchars((string)($__p['role'] ?? '')) ?: 'команда' ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif;
