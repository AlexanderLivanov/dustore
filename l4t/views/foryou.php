<?php
/**
 * l4t/views/foryou.php — вкладка «Для тебя».
 * Ожидает: $forYou, $uSkills, $h, $authors, $tab, $X.
 */
?>
<section class="l4x-view <?= $tab === 'foryou' ? 'is-on' : '' ?>" data-view="foryou">
    <?php if (!$uSkills): ?>
        <div class="l4x-card pix l4x-card--warn" style="margin-bottom:16px">
            <div class="l4x-card__head"><h2><?= l4x_icon('star') ?>Лента угадывает по роли</h2>
                <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="skills">Отметить навыки</button></div>
            <p class="l4x-hint" style="margin:0">Отметьте 3–5 навыков — подбор станет точным, а вас начнут находить через поиск специалистов.</p>
        </div>
    <?php endif; ?>

    <?php if (!$forYou): ?>
        <div class="l4x-empty">Пока ничего подходящего. Загляните на биржу или отметьте больше навыков.</div>
    <?php endif; ?>

    <div class="l4x-feed">
        <?php foreach ($forYou as $it):
            $why = '<div class="l4x-why">' . l4x_icon('check') . 'Совпало: ' . $h(implode(', ', array_slice($it['why'], 0, 3))) . '</div>';
            if ($it['type'] === 'bid'):
                $bid = $it['bid']; ?>
                <div class="l4x-fy">
                    <?= $why ?>
                    <?php require __DIR__ . '/../_bid_card.php'; ?>
                </div>
            <?php elseif ($it['type'] === 'team'): $t = $it['team']; $j = $it['jam']; ?>
                <div class="l4x-fy">
                    <?= $why ?>
                    <article class="l4x-bid l4x-bid--jam">
                        <div class="l4x-bid__top"><span class="l4x-chip l4x-chip--acc">команда на джем</span><time><?= (int)$t['members'] ?>/<?= (int)$t['team_limit'] ?></time></div>
                        <h3 class="l4x-bid__role"><?= $h($t['team_name']) ?></h3>
                        <div class="l4x-muted" style="font-size:13px">Джем «<?= $h($j['title']) ?>»</div>
                        <?php if (!empty($t['team_desc'])): ?><p class="l4x-bid__desc"><?= $h(mb_substr((string)$t['team_desc'], 0, 180)) ?></p><?php endif; ?>
                        <div class="l4x-bid__foot">
                            <?php if ($it['registered']): ?>
                                <a class="l4x-btn l4x-btn--acc l4x-btn--sm" href="/jams?sprint=<?= (int)$j['id'] ?>#teams">Попроситься в команду</a>
                            <?php else: ?>
                                <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/jams?sprint=<?= (int)$j['id'] ?>">Сначала регистрация на джем</a>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php elseif ($it['type'] === 'queue'): $j = $it['jam']; ?>
                <div class="l4x-fy">
                    <div class="l4x-why"><?= l4x_icon('flame') ?>Автосборка команд</div>
                    <article class="l4x-bid l4x-bid--queue">
                        <div class="l4x-bid__top"><span class="l4x-chip l4x-chip--acc">очередь</span><time><?= (int)$it['in_queue'] ?> в очереди</time></div>
                        <h3 class="l4x-bid__role">Нет команды на «<?= $h($j['title']) ?>»?</h3>
                        <p class="l4x-bid__desc">Встаньте в очередь со своей ролью — организатор соберёт сбалансированные команды: код, арт, дизайн и звук вместе, с близкими часовыми поясами.</p>
                        <div class="l4x-bid__foot">
                            <?php if (!$it['registered']): ?>
                                <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/jams?sprint=<?= (int)$j['id'] ?>">Зарегистрироваться на джем</a>
                            <?php elseif ($it['queued']): ?>
                                <span class="l4x-verified"><?= l4x_icon('check') ?>вы в очереди</span>
                                <button class="l4x-link" data-act="queue-leave" data-sprint="<?= (int)$j['id'] ?>">Выйти</button>
                            <?php else: ?>
                                <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="queue-join" data-sprint="<?= (int)$j['id'] ?>" data-title="<?= $h($j['title']) ?>">Встать в очередь</button>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
