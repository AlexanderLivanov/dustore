<?php
/**
 * l4t/views/market_book.php — «стакан» рынка L4T.
 * Каркас. Котировки, стакан и ленту сделок рисует l4x-market.js из L4X.market
 * и обновляет опросом /l4t/api/action.php?op=quotes|book раз в 20 секунд.
 * Ожидает: $mkSum, $me, $h.
 */
?>
<div class="mk" id="mk">
    <div class="mk-head">
        <div class="mk-stats">
            <span class="mk-live" title="Обновляется каждые 20 секунд"><i></i>рынок</span>
            <span>Спрос <b id="mkNeeds"><?= (int)$mkSum['needs'] ?></b></span>
            <span>Предложение <b id="mkOffers"><?= (int)$mkSum['offers'] ?></b></span>
            <span>Сделок за 7 дней <b id="mkDeals"><?= (int)$mkSum['deals7'] ?></b></span>
        </div>
        <div class="l4x-row">
            <div class="l4x-seg" id="mkPane">
                <button class="is-on" data-pane="book">Стакан</button>
                <button data-pane="feed">Все заявки</button>
            </div>
            <?php if ($me): ?>
                <a class="l4x-btn l4x-btn--sm mk-btn-need" href="/l4t/?tab=bids&pane=need"><?= l4x_icon('plus') ?>Мне нужно</a>
                <a class="l4x-btn l4x-btn--sm mk-btn-offer" href="/l4t/?tab=bids&pane=offer"><?= l4x_icon('plus') ?>Я могу</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mk-grid" id="mkBookPane">
        <aside class="mk-side">
            <div class="l4x-card pix mk-quotes">
                <label class="l4x-search mk-qsearch pix"><?= l4x_icon('search') ?><input type="search" id="mkQ" placeholder="Навык: Unity, 3D, звук…" autocomplete="off"></label>
                <div class="mk-qhead"><span>Инструмент</span><span>Спрос</span><span>Предл.</span><span>Спред</span></div>
                <div id="mkQuotes" class="mk-qlist"></div>
            </div>
            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('flame') ?>Лента сделок</h2></div>
                <div id="mkTape" class="mk-tape"></div>
            </div>
        </aside>

        <div class="l4x-card pix mk-book" id="mkBook">
            <div class="l4x-empty">Выберите навык слева — откроется стакан: кто сколько готов заплатить и за сколько готовы сделать.</div>
        </div>
    </div>
</div>
