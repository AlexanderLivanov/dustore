<?php
/**
 * l4t/views/positions_matches.php — совпадения: пары «моя позиция × чужая».
 * Ожидает: $myMatches, $h.
 */
$groups = [
    'todo'  => ['Ждут вашего ответа', array_filter($myMatches, fn($m) => !$m['deal'] && $m['mine'] === 'new')],
    'wait'  => ['Вы согласились — ждём другую сторону', array_filter($myMatches, fn($m) => !$m['deal'] && $m['mine'] === 'yes')],
    'deals' => ['Сделки', array_filter($myMatches, fn($m) => $m['deal'])],
];
?>
<?php if (!$myMatches): ?>
    <div class="l4x-empty">Совпадений пока нет. Разместите спрос («Мне нужно») или предложение («Я могу») — L4T сам найдёт вторую сторону и пришлёт уведомление.</div>
<?php endif; ?>
<?php
/* Одна карточка пары. Вынесено в замыкание: рисуется и в группах, и в «ещё N». */
$card = function (array $m) use ($h) { $o = $m['other']; ob_start(); ?>
            <article class="mk-match pix <?= $m['deal'] ? 'is-deal' : '' ?>" data-match="<?= (int)$m['id'] ?>">
                <div class="mk-match__score" title="Насколько позиции подходят друг другу"><b><?= (int)$m['score'] ?></b>%</div>
                <div class="mk-match__body">
                    <div class="mk-match__pair">
                        <span class="mk-tag mk-tag--<?= $m['side'] ?>"><?= $m['side'] === 'need' ? 'мой спрос' : 'моё предложение' ?></span>
                        <b><?= $h($m['side'] === 'need' ? $m['need']['title'] : $m['offer']['title']) ?></b>
                        <span class="l4x-muted">↔</span>
                        <b><?= $h($m['side'] === 'need' ? $m['offer']['title'] : $m['need']['title']) ?></b>
                    </div>
                    <a class="mk-match__who" href="/l4t/<?= $h($o['handle']) ?>" target="_blank">
                        <span class="l4x-rec__ava"><?= $o['avatar'] ? '<img src="' . $h($o['avatar']) . '" alt="">' : $h(mb_strtoupper(mb_substr(ltrim($o['name'], '@'), 0, 1))) ?></span>
                        <span><b><?= $h($o['name']) ?></b><span class="l4x-muted"><?= $h($o['role'] ?: '—') ?></span></span>
                    </a>
                    <?php if ($m['reasons']): ?><div class="l4x-why"><?= l4x_icon('check') ?><?= $h($m['reasons']) ?></div><?php endif; ?>
                    <?php if ($m['initiator'] !== 'engine' && !$m['deal'] && $m['mine'] === 'new'): ?><div class="l4x-muted" style="font-size:12px">Предложено напрямую другой стороной</div><?php endif; ?>
                </div>
                <div class="mk-match__act">
                    <?php if ($m['deal']): ?>
                        <span class="l4x-verified"><?= l4x_icon('check') ?>сделка</span>
                        <?php if ($m['tg']): ?><a class="l4x-btn l4x-btn--acc l4x-btn--sm" href="https://t.me/<?= $h($m['tg']) ?>" target="_blank" rel="noopener"><?= l4x_icon('telegram') ?>Написать</a><?php endif; ?>
                    <?php elseif ($m['mine'] === 'new'): ?>
                        <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="match-yes" data-id="<?= (int)$m['id'] ?>"><?= l4x_icon('check') ?>Интересно</button>
                        <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="match-no" data-id="<?= (int)$m['id'] ?>">Не подходит</button>
                    <?php else: ?>
                        <span class="l4x-muted" style="font-size:12.5px"><?= $m['their'] === 'new' ? 'ждём ответ' : '' ?></span>
                    <?php endif; ?>
                </div>
            </article>
<?php return ob_get_clean(); };

foreach ($groups as $gk => [$gTitle, $list]):
    if (!$list) continue;
    /* Ждущие ответа группируем по МОЕЙ позиции и показываем топ-3 —
       иначе одна заявка на популярный навык даёт ленту из 30 карточек. */
    $byPos = [];
    foreach ($list as $m) {
        $key = $m['side'] . ($m['side'] === 'need' ? $m['need']['id'] : $m['offer']['id']);
        $byPos[$key]['title'] = ($m['side'] === 'need' ? $m['need']['title'] : $m['offer']['title'])
                              . ' #' . ($m['side'] === 'need' ? $m['need']['id'] : $m['offer']['id']);
        $byPos[$key]['side'] = $m['side'];
        $byPos[$key]['items'][] = $m;
    }
?>
    <div class="l4x-card__head l4x-card__head--bare" style="margin-top:6px"><h2><?= $h($gTitle) ?></h2><span class="l4x-muted"><?= count($list) ?></span></div>
    <?php if ($gk !== 'todo'): ?>
        <div class="mk-matches"><?php foreach ($list as $m) echo $card($m); ?></div>
    <?php else: foreach ($byPos as $pos): ?>
        <div class="mk-group">
            <div class="mk-group__h"><span class="mk-tag mk-tag--<?= $pos['side'] ?>"><?= $pos['side'] === 'need' ? 'мой спрос' : 'моё предложение' ?></span><b><?= $h($pos['title']) ?></b><span class="l4x-muted"><?= count($pos['items']) ?></span></div>
            <div class="mk-matches" style="margin-bottom:0"><?php foreach (array_slice($pos['items'], 0, 3) as $m) echo $card($m); ?></div>
            <?php if (count($pos['items']) > 3): ?>
                <details class="mk-more"><summary>Ещё <?= count($pos['items']) - 3 ?> — показать</summary>
                    <div class="mk-matches"><?php foreach (array_slice($pos['items'], 3) as $m) echo $card($m); ?></div>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
<?php endforeach; ?>
<p class="l4x-hint">Сделка — когда обе стороны нажали «Интересно». Тогда открываются контакты, проект попадает в титры, и вы сможете оставить друг другу рекомендации.</p>
