<?php
/**
 * l4t/views/foryou.php — «Для тебя»: одна карточка за раз.
 *
 * Лента из двадцати карточек заставляла сравнивать всё со всем. Колода
 * задаёт один вопрос: «интересно?» — влево пропустить, вправо откликнуться.
 * Джемы — отдельной узкой полосой сверху: у них своё действие (очередь/команда).
 * Колоду рендерит l4x-pos.js из op=deck. Ожидает: $forYou, $uSkills, $h, $tab.
 */
$jamItems = array_values(array_filter($forYou, fn($it) => $it['type'] !== 'bid'));
?>
<section class="l4x-view <?= $tab === 'foryou' ? 'is-on' : '' ?>" data-view="foryou">
    <?php if (!$uSkills): ?>
        <div class="sw-note pix">
            <?= l4x_icon('star') ?><span>Отметьте 3–5 навыков — подбор станет точным, а вас начнут находить заказчики.</span>
            <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="skills">Отметить навыки</button>
        </div>
    <?php endif; ?>

    <?php if ($jamItems): ?>
        <div class="sw-jams">
            <?php foreach (array_slice($jamItems, 0, 4) as $it): $j = $it['jam']; ?>
                <div class="sw-jam pix">
                    <?= l4x_icon('flame') ?>
                    <?php if ($it['type'] === 'team'): $t = $it['team']; ?>
                        <span><b><?= $h($t['team_name']) ?></b> ищет людей · <?= (int)$t['members'] ?>/<?= (int)$t['team_limit'] ?> · джем «<?= $h($j['title']) ?>»</span>
                        <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/jams?sprint=<?= (int)$j['id'] ?><?= $it['registered'] ? '#teams' : '' ?>"><?= $it['registered'] ? 'Попроситься' : 'Регистрация' ?></a>
                    <?php else: ?>
                        <span>Нет команды на <b>«<?= $h($j['title']) ?>»</b>? Автосборка · <?= (int)$it['in_queue'] ?> в очереди</span>
                        <?php if (!$it['registered']): ?>
                            <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/jams?sprint=<?= (int)$j['id'] ?>">Регистрация</a>
                        <?php elseif ($it['queued']): ?>
                            <span class="l4x-verified"><?= l4x_icon('check') ?>в очереди</span>
                            <button class="l4x-link" data-act="queue-leave" data-sprint="<?= (int)$j['id'] ?>">Выйти</button>
                        <?php else: ?>
                            <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="queue-join" data-sprint="<?= (int)$j['id'] ?>" data-title="<?= $h($j['title']) ?>">В очередь</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="sw" id="sw">
        <div class="sw-stack" id="swStack"><div class="sw-empty l4x-muted">Подбираем заявки…</div></div>
        <div class="sw-ctl">
            <button class="sw-btn sw-btn--skip" data-act="sw-skip" title="Пропустить (←)"><?= l4x_icon('close') ?><span>Пропустить</span></button>
            <button class="sw-btn sw-btn--info" data-act="sw-info" title="Подробнее (↑)"><?= l4x_icon('eye') ?><span>Подробнее</span></button>
            <button class="sw-btn sw-btn--go" data-act="sw-apply" title="Откликнуться (→)"><?= l4x_icon('check') ?><span>Откликнуться</span></button>
        </div>
        <div class="sw-foot">
            <span class="l4x-muted" id="swLeft"></span>
            <button class="l4x-link" data-act="sw-undo" id="swUndo" hidden>↶ Вернуть пропущенную</button>
        </div>
        <p class="l4x-hint sw-hint">Смахните карточку: влево — не интересно, вправо — откликнуться. На компьютере — стрелки ← →. Если у вас есть предложение «Я могу», отклик уйдёт от него, и при встречном «да» сразу откроются контакты.</p>
    </div>
</section>
