<?php
/**
 * l4t/views/positions_offers.php — «Я могу»: мои предложения на рынке.
 * Форма отправляется через l4x-market.js в op=offer_save.
 * Ожидает: $myOffers, $skillsAll, $uSkills, $h.
 */
?>
<div class="l4x-grid l4x-grid--form">
    <form class="l4x-card pix l4x-form" id="offerForm">
        <div class="l4x-card__head">
            <h2 id="offerFormTitle"><?= l4x_icon('plus') ?>Новое предложение</h2>
            <button type="button" class="l4x-link" id="offerCancel" hidden><?= l4x_icon('close') ?>Отменить правку</button>
        </div>
        <p class="l4x-hint" style="margin-top:0">Выставьте свою способность выполнить работу — как заявку на продажу в стакане. L4T сам сведёт вас с подходящим спросом.</p>
        <input type="hidden" name="id" value="">
        <label class="l4x-field"><span class="l4x-field__label">Что делаю *</span>
            <input class="l4x-input" name="title" maxlength="120" required placeholder="Unity / C# — мультиплеер и UI"></label>
        <div class="l4x-form__grid">
            <label class="l4x-field"><span class="l4x-field__label">Какую работу беру</span>
                <select class="l4x-input" name="kind">
                    <option value="any">Любую</option>
                    <?php foreach (L4TMarket::KINDS as $k => $kl): ?><option value="<?= $k ?>"><?= $h($kl) ?></option><?php endforeach; ?>
                </select></label>
            <label class="l4x-field"><span class="l4x-field__label">Свободен с</span>
                <input class="l4x-input" name="available_from" type="date" min="<?= date('Y-m-d') ?>" title="Пусто — свободен сейчас"></label>
        </div>
        <div class="l4x-field">
            <span class="l4x-field__label">Цена</span>
            <div class="l4x-seg l4x-seg--form">
                <?php foreach (L4TMarket::PAY as $k => $pl): ?><label><input type="radio" name="pay_type" value="<?= $k ?>" <?= $k === 'money' ? 'checked' : '' ?>><span><?= $h($pl) ?></span></label><?php endforeach; ?>
            </div>
            <div class="l4x-row mk-price" data-price>
                <input class="l4x-input" name="price_min" inputmode="numeric" placeholder="от, ₽">
                <span class="l4x-muted">—</span>
                <input class="l4x-input" name="price_max" inputmode="numeric" placeholder="до, ₽ (необязательно)">
            </div>
        </div>
        <label class="l4x-field"><span class="l4x-field__label">Часов в неделю</span>
            <input class="l4x-input" name="hours_week" type="number" min="1" max="80" placeholder="20" style="max-width:140px"></label>
        <div class="l4x-field">
            <span class="l4x-field__label">Навыки * — по ним вы встанете в стаканы</span>
            <div class="l4x-pick">
                <?php foreach (L4TX::GROUPS as $g => $gLabel): ?>
                    <div class="l4x-pick__grp"><span><?= $h($gLabel) ?></span>
                        <?php foreach ($skillsAll as $sk): if ($sk['grp'] !== $g) continue; ?>
                            <label><input type="checkbox" name="skills" value="<?= $h($sk['slug']) ?>" <?= isset($uSkills[$sk['slug']]) ? 'data-mine="1"' : '' ?>><span><?= $h($sk['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <label class="l4x-field"><span class="l4x-field__label">Подробнее</span>
            <textarea class="l4x-input" name="details" rows="4" maxlength="2000" placeholder="Что уже делал, что интересно, как работаю"></textarea></label>
        <div class="l4x-form__foot">
            <span class="l4x-muted">Предложение живёт 30 дней, потом продлевается одной кнопкой</span>
            <button class="l4x-btn l4x-btn--acc" type="submit"><?= l4x_icon('check') ?><span id="offerSubmitText">Выставить</span></button>
        </div>
    </form>

    <div class="l4x-col">
        <div class="l4x-card__head l4x-card__head--bare"><h2>Мои предложения</h2><span class="l4x-muted"><?= count($myOffers) ?></span></div>
        <?php if (!$myOffers): ?><div class="l4x-empty">Пока ни одного. Хватит одного честного предложения — остальное сделает сведение.</div><?php endif; ?>
        <div class="l4x-mylist">
            <?php foreach ($myOffers as $o): $expired = $o['expires'] && $o['expires'] < date('Y-m-d'); ?>
                <div class="l4x-my pix <?= $o['stage'] !== 'active' || $expired ? 'is-off' : '' ?>">
                    <div class="l4x-my__main">
                        <b><?= $h($o['title']) ?></b>
                        <span class="l4x-muted"><?= $h(L4TMarket::priceLabel($o)) ?> · <?= $o['kind'] === 'any' ? 'любая работа' : $h(mb_strtolower(L4TMarket::KINDS[$o['kind']] ?? '')) ?> ·
                            <?= $o['stage'] === 'paused' ? 'на паузе' : ($expired ? 'истекло' : 'до ' . date('d.m', strtotime((string)$o['expires']))) ?></span>
                    </div>
                    <span class="l4x-my__stat"><?= l4x_icon('eye') ?><?= (int)$o['views'] ?></span>
                    <button class="l4x-link" data-act="offer-edit" data-offer="<?= $h(json_encode($o, JSON_UNESCAPED_UNICODE)) ?>" title="Изменить"><?= l4x_icon('edit') ?></button>
                    <?php if ($o['stage'] === 'active' && !$expired): ?>
                        <button class="l4x-link" data-act="offer-stage" data-id="<?= (int)$o['id'] ?>" data-stage="paused" title="Пауза"><?= l4x_icon('clock') ?></button>
                    <?php else: ?>
                        <button class="l4x-link" data-act="offer-stage" data-id="<?= (int)$o['id'] ?>" data-stage="active" title="Вернуть на рынок на 30 дней"><?= l4x_icon('arrow') ?></button>
                    <?php endif; ?>
                    <button class="l4x-link l4x-danger" data-act="offer-stage" data-id="<?= (int)$o['id'] ?>" data-stage="closed" title="Снять совсем"><?= l4x_icon('close') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
