<?php
/**
 * l4t/views/profile_offers.php — «Готов взять»: активные предложения человека
 * прямо в профиле. Гость может предложить задачу из своей заявки.
 * Ожидает: $profileOffers, $isOwner, $me, $h.
 */
?>
<div class="l4x-card pix">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('briefcase') ?>Готов взять</h2>
        <?php if ($isOwner): ?><a class="l4x-link" href="/l4t/?tab=bids&pane=offer"><?= l4x_icon('plus') ?>Выставить</a><?php endif; ?>
    </div>
    <?php if (!$profileOffers): ?>
        <span class="l4x-muted">Выставьте предложение на рынок: «что делаю, за сколько, когда свободен». Его увидят в стакане, а L4T будет приводить подходящие задачи.</span>
    <?php endif; ?>
    <div class="mk-ocards">
        <?php foreach ($profileOffers as $o): ?>
            <div class="mk-ocard pix">
                <div class="mk-ocard__top">
                    <b><?= $h($o['title']) ?></b>
                    <span class="mk-price-tag"><?= $h(L4TMarket::priceLabel($o)) ?></span>
                </div>
                <div class="l4x-muted" style="font-size:12.5px">
                    <?= $o['kind'] === 'any' ? 'любая работа' : $h(mb_strtolower(L4TMarket::KINDS[$o['kind']] ?? '')) ?> ·
                    <?= $o['from'] ? 'свободен с ' . date('d.m', strtotime((string)$o['from'])) : 'свободен сейчас' ?><?= $o['hours'] ? ' · ' . (int)$o['hours'] . ' ч/нед' : '' ?>
                </div>
                <?php if ($o['details'] !== ''): ?><p class="mk-ocard__d"><?= $h(mb_substr($o['details'], 0, 220)) ?></p><?php endif; ?>
                <?php if (!$isOwner && $me): ?>
                    <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="propose-to-offer" data-id="<?= (int)$o['id'] ?>" data-title="<?= $h($o['title']) ?>" data-owner="<?= $h($displayName) ?>"><?= l4x_icon('send') ?>Предложить задачу</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
