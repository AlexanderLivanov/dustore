<?php
/**
 * l4t/_bid_card.php — одна карточка заявки в ленте.
 *
 * Вынесено, чтобы разметку рисовало ОДНО место. Первую страницу собирает
 * index.php, следующие и результаты фильтрации — l4t/api/feed.php. Если бы
 * карточку рисовал ещё и JS, пришлось бы дублировать десяток data-атрибутов
 * на .respond-btn — их читает submitRespond(), и потеря любого молча ломает
 * модалку отклика.
 *
 * Ожидает в области видимости: $bid (строка из bids).
 */

$isStudio = ($bid['owner_type'] ?? 'user') === 'studio';
$created  = !empty($bid['created_at']) ? strtotime($bid['created_at']) : time();
?>
<div class="bid-card-item"
    data-id="<?= (int)$bid['id'] ?>"
    data-role="<?= htmlspecialchars($bid['search_role']  ?? '', ENT_QUOTES) ?>"
    data-spec="<?= htmlspecialchars($bid['search_spec']  ?? '', ENT_QUOTES) ?>"
    data-exp="<?= htmlspecialchars($bid['experience']    ?? '', ENT_QUOTES) ?>"
    data-cond="<?= htmlspecialchars($bid['conditions']   ?? '', ENT_QUOTES) ?>"
    data-goal="<?= htmlspecialchars($bid['goal']         ?? '', ENT_QUOTES) ?>"
    data-details="<?= htmlspecialchars(mb_substr($bid['details'] ?? '', 0, 300), ENT_QUOTES) ?>">

    <div class="bid-badge">
        <div class="bid-icon <?= $isStudio ? 'studio' : 'user' ?>"><?= $isStudio ? '🏢' : '👤' ?></div>
        <div class="bid-type"><?= $isStudio ? 'студия' : 'пользователь' ?></div>
    </div>

    <div class="bid-main">
        <div class="bid-role"><?= htmlspecialchars($bid['search_role'] ?? '') ?></div>
        <div class="bid-meta">
            <?php if (!empty($bid['jam_id'])): ?><span class="bid-tag">🎮 Джем</span><?php endif; ?>
            <?php if (!empty($bid['search_spec'])): ?><span class="bid-tag"><?= htmlspecialchars($bid['search_spec']) ?></span><?php endif; ?>
            <?php if (!empty($bid['experience'])): ?><span class="bid-tag"><?= htmlspecialchars($bid['experience']) ?></span><?php endif; ?>
            <?php if (!empty($bid['conditions'])): ?><span class="bid-tag"><?= htmlspecialchars($bid['conditions']) ?></span><?php endif; ?>
            <?php if (!empty($bid['goal'])): ?><span class="bid-tag"><?= htmlspecialchars(mb_substr($bid['goal'], 0, 20)) ?></span><?php endif; ?>
        </div>
        <div class="bid-desc"><?= htmlspecialchars(mb_substr($bid['details'] ?? '', 0, 120)) ?></div>
    </div>

    <div class="bid-right">
        <div class="bid-date"><?= date('d.m.Y', $created) ?></div>
        <div class="bid-stats">
            <span>👁 <?= (int)($bid['views'] ?? 0) ?></span>
            <span>💬 <?= (int)($bid['responses'] ?? 0) ?></span>
        </div>
        <button class="respond-btn"
            data-bid="<?= (int)$bid['id'] ?>"
            data-role="<?= htmlspecialchars($bid['search_role'] ?? '') ?>"
            data-spec="<?= htmlspecialchars($bid['search_spec'] ?? '') ?>"
            data-exp="<?= htmlspecialchars($bid['experience']   ?? '') ?>"
            data-cond="<?= htmlspecialchars($bid['conditions']  ?? '') ?>"
            data-goal="<?= htmlspecialchars($bid['goal']        ?? '') ?>"
            data-details="<?= htmlspecialchars($bid['details']  ?? '') ?>"
            data-type="<?= htmlspecialchars($bid['owner_type']  ?? 'user') ?>"
            data-date="<?= date('d.m.Y', $created) ?>"
            data-views="<?= (int)($bid['views'] ?? 0) ?>"
            data-responses="<?= (int)($bid['responses'] ?? 0) ?>">
            Откликнуться
        </button>
    </div>
</div>
