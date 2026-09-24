<?php
/**
 * l4t/views/profile_blocks.php — навыки, опыт, титры, рекомендации.
 * Включается из index.php внутри основной колонки профиля.
 * Ожидает: $X, $h, $isOwner, $me, $userdata, $skillsAll, $uSkills, $workExp, $suggestExp,
 *          $credits, $recs, $again, $canRecommend.
 */
$lvlName = [1 => 'учусь', 2 => 'уверенно', 3 => 'эксперт'];
$ymFmt = function (?string $ym): string {
    if (!$ym) return '';
    $m = ['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'][(int)substr($ym, 5, 2) - 1] ?? '';
    return $m . ' ' . substr($ym, 0, 4);
};
$dur = function (?string $a, ?string $b): string {
    if (!$a) return '';
    $s = (int)substr($a, 0, 4) * 12 + (int)substr($a, 5, 2);
    $e = $b ? (int)substr($b, 0, 4) * 12 + (int)substr($b, 5, 2) : (int)date('Y') * 12 + (int)date('n');
    $n = max(1, $e - $s + 1); $y = intdiv($n, 12); $mo = $n % 12;
    return trim(($y ? $y . ' г. ' : '') . ($mo ? $mo . ' мес.' : ''));
};
?>

<?php if ($X->has('user_skills')): ?>
<div class="l4x-card pix" id="skillsCard">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('star') ?>Навыки</h2>
        <?php if ($isOwner): ?><button class="l4x-link" data-act="skills"><?= l4x_icon('edit') ?>Изменить</button><?php endif; ?>
    </div>
    <div class="l4x-skills" id="skillsList">
        <?php if (!$uSkills): ?>
            <span class="l4x-muted"><?= $isOwner ? 'Отметьте навыки — по ним работают лента «Для тебя», поиск специалистов и автосборка команд.' : 'Не указаны' ?></span>
        <?php endif; ?>
        <?php foreach ($uSkills as $slug => $lvl): if (!isset($skillsAll[$slug])) continue; ?>
            <span class="l4x-skill g-<?= $h($skillsAll[$slug]['grp']) ?>" title="<?= $h($lvlName[$lvl]) ?>">
                <?= $h($skillsAll[$slug]['name']) ?><i class="l4x-lvl l<?= $lvl ?>"><b></b><b></b><b></b></i>
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($X->has('work_experience')): ?>
<div class="l4x-card pix">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('studio') ?>Опыт работы</h2>
        <?php if ($isOwner): ?><button class="l4x-link" data-act="exp-new"><?= l4x_icon('plus') ?>Добавить</button><?php endif; ?>
    </div>

    <?php if ($isOwner && $suggestExp): ?>
        <div class="l4x-suggest">
            <?php foreach ($suggestExp as $st): ?>
                <button class="l4x-chip l4x-chip--acc" data-act="exp-new" data-studio="<?= (int)$st['id'] ?>" data-name="<?= $h($st['name']) ?>">
                    <?= l4x_icon('plus') ?>Вы в команде «<?= $h($st['name']) ?>» — добавить в опыт
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$workExp): ?><span class="l4x-muted"><?= $isOwner ? 'Студии, компании, фриланс. Опыт в студии Dustore подтверждает её владелец.' : 'Не указан' ?></span><?php endif; ?>
    <ol class="l4x-timeline">
        <?php foreach ($workExp as $e): ?>
            <li class="l4x-tl">
                <span class="l4x-tl__dot <?= $e['status'] === 'verified' ? 'is-ok' : '' ?>"></span>
                <div class="l4x-tl__body">
                    <div class="l4x-tl__title">
                        <b><?= $h($e['title']) ?></b>
                        <?php if ($e['status'] === 'verified'): ?>
                            <span class="l4x-verified" title="Подтверждено студией на Dustore"><?= l4x_icon('check') ?>подтверждено</span>
                        <?php elseif ($e['status'] === 'pending' && $isOwner): ?>
                            <span class="l4x-chip l4x-chip--line">ждёт подтверждения</span>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                            <button class="l4x-link" data-act="exp-edit" data-exp="<?= $h(json_encode($e, JSON_UNESCAPED_UNICODE)) ?>"><?= l4x_icon('edit') ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="l4x-tl__org">
                        <?php if (!empty($e['studio_id'])): ?><a href="/d/<?= $h($X->val($pdo, 'SELECT tiker FROM studios WHERE id = ?', [(int)$e['studio_id']]) ?? '') ?>"><?= $h($e['org_name']) ?></a><?php else: ?><?= $h($e['org_name']) ?><?php endif; ?>
                    </div>
                    <div class="l4x-tl__when l4x-muted">
                        <?= $h($ymFmt($e['start_ym'])) ?><?= $e['start_ym'] ? ' — ' . ($e['end_ym'] ? $h($ymFmt($e['end_ym'])) : 'по сей день') : '' ?>
                        <?php if ($e['start_ym']): ?> · <?= $h($dur($e['start_ym'], $e['end_ym'])) ?><?php endif; ?>
                    </div>
                    <?php if (!empty($e['description'])): ?><p class="l4x-tl__desc"><?= nl2br($h($e['description'])) ?></p><?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<?php if ($X->has('credits')): ?>
<div class="l4x-card pix">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('gamepad') ?>Титры</h2>
        <?php if ($isOwner): ?><button class="l4x-link" data-act="credit-new"><?= l4x_icon('plus') ?>Добавить</button><?php endif; ?>
    </div>
    <?php if (!$credits): ?>
        <span class="l4x-muted"><?= $isOwner ? 'Титры собираются сами: команды джемов, игры ваших студий, принятые отклики. Вне Dustore — добавьте вручную.' : 'Пока пусто' ?></span>
    <?php endif; ?>
    <ul class="l4x-credits">
        <?php foreach ($credits as $c): ?>
            <li class="l4x-credit <?= $c['hidden'] ? 'is-hidden' : '' ?>">
                <?php if ($c['game'] && $c['game']['path_to_cover']): ?>
                    <img class="l4x-credit__cover" src="<?= $h($c['game']['path_to_cover']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="l4x-credit__cover l4x-credit__cover--ph"><?= l4x_icon($c['source'] === 'jam' ? 'flame' : 'gamepad') ?></span>
                <?php endif; ?>
                <span class="l4x-credit__body">
                    <?php if ($c['game_id']): ?><a href="/g/<?= (int)$c['game_id'] ?>"><b><?= $h($c['title']) ?></b></a><?php else: ?><b><?= $h($c['title']) ?></b><?php endif; ?>
                    <span class="l4x-muted"><?= $h(trim(($c['role'] ?? '') . ($c['year'] ? ' · ' . (int)$c['year'] : ''), ' ·')) ?></span>
                </span>
                <?php if ((int)$c['verified']): ?><span class="l4x-verified" title="Собрано из данных Dustore"><?= l4x_icon('check') ?></span><?php endif; ?>
                <?php if ($isOwner): ?>
                    <button class="l4x-link" data-act="credit-hide" data-id="<?= (int)$c['id'] ?>" data-hide="<?= $c['hidden'] ? 0 : 1 ?>"
                            title="<?= $c['source'] === 'manual' ? 'Удалить' : ($c['hidden'] ? 'Показать' : 'Скрыть') ?>"><?= l4x_icon($c['hidden'] ? 'eye' : 'close') ?></button>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($X->has('recommendations')): ?>
<div class="l4x-card pix" id="recsCard">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('users') ?>Рекомендации</h2>
        <?php if ($again): ?>
            <span class="l4x-again" title="Доля коллег, которые поработали бы снова. Показывается от трёх ответов."><b><?= (int)$again['pct'] ?>%</b> поработали бы снова · <?= (int)$again['n'] ?></span>
        <?php endif; ?>
    </div>
    <?php if (!$isOwner && $canRecommend): ?>
        <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="rec-write" data-id="<?= (int)$userdata['id'] ?>" data-name="<?= $h($displayName) ?>" style="margin-bottom:12px">
            <?= l4x_icon('edit') ?>Написать рекомендацию
        </button>
    <?php endif; ?>
    <?php if (!$recs): ?>
        <span class="l4x-muted">Рекомендации пишут только те, с кем человек реально работал на Dustore: команда джема, студия, проект через L4T.</span>
    <?php endif; ?>
    <div class="l4x-recs">
        <?php foreach ($recs as $r): $a = $r['author']; ?>
            <figure class="l4x-rec <?= $r['hidden'] ? 'is-hidden' : '' ?>">
                <blockquote><?= nl2br($h($r['text'])) ?></blockquote>
                <figcaption>
                    <a class="l4x-rec__who" href="/l4t/<?= $h($a['handle'] ?? '') ?>">
                        <span class="l4x-rec__ava"><?= !empty($a['avatar']) ? '<img src="' . $h($a['avatar']) . '" alt="">' : $h(mb_strtoupper(mb_substr(ltrim((string)($a['name'] ?? '?'), '@'), 0, 1))) ?></span>
                        <span><b><?= $h($a['name'] ?? 'Пользователь') ?></b><span class="l4x-muted"><?= $h($r['context_label']) ?></span></span>
                    </a>
                    <?php foreach (array_filter(explode(',', (string)$r['skills'])) as $sk): if (!isset($skillsAll[$sk])) continue; ?>
                        <span class="l4x-chip"><?= $h($skillsAll[$sk]['name']) ?></span>
                    <?php endforeach; ?>
                    <?php if ($isOwner): ?>
                        <button class="l4x-link" data-act="rec-hide" data-id="<?= (int)$r['id'] ?>" data-hide="<?= $r['hidden'] ? 0 : 1 ?>"><?= $r['hidden'] ? 'Показать' : 'Скрыть' ?></button>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
