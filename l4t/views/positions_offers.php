<?php
/**
 * l4t/views/positions_offers.php — форма «Я могу» (предложение на рынке).
 * Живёт скрытой в #wsForms и открывается в боковой панели.
 * Отправка — l4x-market.js → op=offer_save. Ожидает: $skillsAll, $uSkills, $h.
 */
?>
<form class="l4x-form ws-form" id="offerForm">
    <h2 id="offerFormTitle" hidden>Новое предложение</h2>
    <button type="button" class="l4x-link" id="offerCancel" hidden></button>
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
    <div class="l4x-field">
        <span class="l4x-field__label">Сколько висит на рынке — потом снимется само</span>
        <div class="l4x-seg l4x-seg--form">
            <?php foreach (L4TMarket::LIFETIMES as $d => $dl): ?><label><input type="radio" name="lifetime_days" value="<?= $d ?>" <?= $d === 30 ? 'checked' : '' ?>><span><?= $h($dl) ?></span></label><?php endforeach; ?>
        </div>
    </div>
    <div class="l4x-form__foot">
        <span class="l4x-muted">Продлить можно одной кнопкой</span>
        <button class="l4x-btn l4x-btn--acc" type="submit"><?= l4x_icon('check') ?><span id="offerSubmitText">Выставить</span></button>
    </div>
</form>
