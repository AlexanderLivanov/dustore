<?php
/**
 * l4t/views/profile_aside.php — верх боковой колонки профиля: то, что
 * требует действия владельца, и «условия работы» для всех.
 * Ожидает: $P, $X, $h, $isOwner, $pendingVer, $toRec, $attended, $respSpeed.
 */
$pr = $P->profile;
?>

<?php if ($isOwner && $pr['avail_expired']): ?>
<div class="l4x-card pix l4x-card--warn">
    <div class="l4x-card__head"><h2><?= l4x_icon('clock') ?>Вы всё ещё открыты?</h2></div>
    <p class="l4x-hint" style="margin-top:0">Статус «<?= $h(L4TProfile::AVAILABILITY[$pr['availability']][0] ?? '') ?>» стоит больше 30 дней. Гостям он уже не показывается — чтобы биржа не зарастала «вечно открытыми» профилями.</p>
    <div class="l4x-row" style="margin-top:12px">
        <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="avail-renew">Да, ещё на 30 дней</button>
        <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="customize">Изменить</button>
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner && $pendingVer): ?>
<div class="l4x-card pix">
    <div class="l4x-card__head"><h2><?= l4x_icon('shield') ?>Подтвердите опыт</h2><span class="l4x-badge"><?= count($pendingVer) ?></span></div>
    <?php foreach ($pendingVer as $v): ?>
        <div class="l4x-verify" data-id="<?= (int)$v['id'] ?>">
            <div><b><?= $h($v['user']['name'] ?? 'Пользователь') ?></b> — <?= $h($v['title']) ?><br>
                <span class="l4x-muted"><?= $h($v['org_name']) ?><?= $v['start_ym'] ? ' · с ' . $h($v['start_ym']) : '' ?></span></div>
            <div class="l4x-row">
                <button class="l4x-btn l4x-btn--acc l4x-btn--sm" data-act="exp-verify" data-id="<?= (int)$v['id'] ?>" data-ok="1"><?= l4x_icon('check') ?></button>
                <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="exp-verify" data-id="<?= (int)$v['id'] ?>" data-ok="0"><?= l4x_icon('close') ?></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isOwner && $toRec): ?>
<div class="l4x-card pix">
    <div class="l4x-card__head"><h2><?= l4x_icon('star') ?>Оцените тиммейтов</h2></div>
    <p class="l4x-hint" style="margin-top:0">Пара предложений от вас — сильный аргумент в их портфолио. Писать могут только те, кто работал вместе.</p>
    <?php foreach ($toRec as $u): ?>
        <button class="l4x-studio" data-act="rec-write" data-id="<?= (int)$u['id'] ?>" data-name="<?= $h($u['name']) ?>" style="width:100%;text-align:left">
            <span class="l4x-studio__ic pix"><?= $u['avatar'] ? '<img src="' . $h($u['avatar']) . '" alt="" style="width:100%;height:100%;object-fit:cover">' : $h(mb_strtoupper(mb_substr(ltrim($u['name'], '@'), 0, 1))) ?></span>
            <span class="l4x-studio__body"><b><?= $h($u['name']) ?></b><span class="l4x-muted"><?= $h($u['context']) ?></span></span>
            <?= l4x_icon('edit') ?>
        </button>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$modes = array_intersect_key(L4TX::WORK_MODES, array_flip($pr['work_modes']));
$tzLabel = $pr['tz'] !== null ? 'UTC' . ($pr['tz'] >= 0 ? '+' : '−') . intdiv(abs($pr['tz']), 60) . (abs($pr['tz']) % 60 ? ':' . str_pad((string)(abs($pr['tz']) % 60), 2, '0') : '') : '';
if ($modes || $pr['rate'] !== '' || $tzLabel || $respSpeed !== null || $pr['manual'] !== '' || $isOwner):
?>
<div class="l4x-card pix">
    <div class="l4x-card__head">
        <h2><?= l4x_icon('briefcase') ?>Как со мной работать</h2>
        <?php if ($isOwner): ?><button class="l4x-link" data-act="work"><?= l4x_icon('edit') ?>Изменить</button><?php endif; ?>
    </div>
    <?php if ($modes): ?><div class="l4x-exp" style="margin-bottom:10px"><?php foreach ($modes as $mLabel): ?><span class="l4x-chip l4x-chip--acc"><?= $h($mLabel) ?></span><?php endforeach; ?></div><?php endif; ?>
    <dl class="l4x-dl">
        <?php if ($pr['rate'] !== ''): ?><div><dt>Ставка</dt><dd><?= $h($pr['rate']) ?></dd></div><?php endif; ?>
        <?php if ($tzLabel): ?><div><dt>Часовой пояс</dt><dd><?= $h($tzLabel) ?></dd></div><?php endif; ?>
        <?php if ($respSpeed !== null): ?><div><dt>Отвечает на отклики</dt><dd>~<?= $respSpeed < 1 ? 'за час' : ($respSpeed < 48 ? round($respSpeed) . ' ч' : round($respSpeed / 24) . ' дн.') ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($pr['manual'] !== ''): ?>
        <div class="l4x-about" style="margin-top:10px;font-size:13.5px"><?= nl2br($h($pr['manual'])) ?></div>
    <?php elseif ($isOwner && !$modes && $pr['rate'] === ''): ?>
        <span class="l4x-muted" style="font-size:13px">Формат, ставка, часовой пояс и короткое «как со мной работать» — экономят обеим сторонам первый созвон.</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($attended): ?>
<div class="l4x-card pix">
    <div class="l4x-card__head"><h2><?= l4x_icon('pin') ?>Мероприятия</h2><span class="l4x-muted"><?= count($attended) ?></span></div>
    <ul class="l4x-events">
        <?php foreach (array_slice($attended, 0, 6) as $e): ?>
            <li><b><?= $h($e['title']) ?></b><span class="l4x-muted"><?= date('d.m.Y', strtotime((string)$e['starts_at'])) ?><?= $e['place'] ? ' · ' . $h($e['place']) : '' ?></span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
