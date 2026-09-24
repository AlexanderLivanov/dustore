<?php
/**
 * l4t/views/positions.php — «Мои позиции»: рабочее место.
 *
 * Слева — всё моё: заявки («Мне нужно»), предложения («Я могу»), приглашения мне.
 * Справа — выбранная позиция: таймер, правка, и главное — кто под неё подходит
 * и почему, с понятными действиями. Рендерит l4x-pos.js из L4X.positions
 * (первый показ без запроса) и op=pos_view.
 */
?>
<div class="ws" id="ws" data-pos="<?= $h((string)($_GET['pos'] ?? '')) ?>">
    <aside class="ws-side">
        <div class="ws-new">
            <button class="l4x-btn l4x-btn--acc" data-act="pos-new" data-side="need"><?= l4x_icon('search') ?>Мне нужно</button>
            <button class="l4x-btn l4x-btn--ghost" data-act="pos-new" data-side="offer"><?= l4x_icon('briefcase') ?>Я могу</button>
        </div>
        <div id="wsList"></div>
    </aside>
    <main class="ws-main pix" id="wsMain">
        <div class="ws-hello">
            <h2>Как это работает</h2>
            <ol>
                <li><b>Опишите, кто нужен</b> — или что умеете сами. Выберите, сколько позиция провисит на рынке.</li>
                <li><b>L4T сразу подберёт людей</b> — с рынка, из откликов и среди специалистов с нужными навыками. У каждого написано, чем он подходит и чего не хватает.</li>
                <li><b>Пригласите</b> понравившихся. Когда вторая сторона согласится — откроются контакты.</li>
            </ol>
        </div>
    </main>
</div>
