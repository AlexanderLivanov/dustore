<?php
/**
 * l4t/views/network.php — вкладка «Связи»: знакомства, ссылки на резюме,
 * сохранённые поиски, мероприятия.
 * Ожидает: $contacts, $shareLinks, $savedSearches, $hostEvents, $skillsAll, $h, $tab, $host.
 */
?>
<section class="l4x-view <?= $tab === 'network' ? 'is-on' : '' ?>" data-view="network">
    <div class="l4x-grid">
        <div class="l4x-col">
            <div class="l4x-card pix">
                <div class="l4x-card__head">
                    <h2><?= l4x_icon('users') ?>Знакомства</h2>
                    <button class="l4x-btn l4x-btn--ghost l4x-btn--sm" data-act="qr" data-mode="card"><?= l4x_icon('qr') ?>Мой QR</button>
                </div>
                <?php if (!$contacts): ?>
                    <p class="l4x-hint" style="margin-top:0">Покажите свой QR на мероприятии: человек сканирует его телефоном, жмёт «Добавить в знакомства» — и вы оба попадаете сюда с пометкой, где познакомились.</p>
                <?php endif; ?>
                <div class="l4x-contacts">
                    <?php foreach ($contacts as $c): $u = $c['user']; ?>
                        <div class="l4x-contact">
                            <a class="l4x-studio" href="/l4t/<?= $h($u['handle']) ?>">
                                <span class="l4x-studio__ic pix"><?= $u['avatar'] ? '<img src="' . $h($u['avatar']) . '" alt="" style="width:100%;height:100%;object-fit:cover">' : $h(mb_strtoupper(mb_substr(ltrim($u['name'], '@'), 0, 1))) ?></span>
                                <span class="l4x-studio__body"><b><?= $h($u['name']) ?></b>
                                    <span class="l4x-muted"><?= $h($u['role'] ?: '—') ?> · <?= $c['event_title'] ? 'на «' . $h($c['event_title']) . '»' : 'по QR' ?>, <?= date('d.m.Y', strtotime((string)$c['created_at'])) ?></span></span>
                            </a>
                            <input class="l4x-input l4x-input--note" data-note="<?= (int)$c['contact_id'] ?>" maxlength="200" placeholder="Заметка: о чём говорили" value="<?= $h($c['note'] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('file') ?>Ссылки на резюме</h2></div>
                <p class="l4x-hint" style="margin-top:0">Отдельная ссылка для каждой студии: видно, открывали ли резюме и когда. Ссылку можно отозвать.</p>
                <form class="l4x-row" id="linkForm" style="margin:12px 0">
                    <input class="l4x-input" name="label" maxlength="80" placeholder="Кому: Studio X, HR Ивана, конференция…" required>
                    <button class="l4x-btn l4x-btn--acc l4x-btn--sm" type="submit"><?= l4x_icon('plus') ?>Создать</button>
                </form>
                <div class="l4x-mylist">
                    <?php foreach ($shareLinks as $l): $url = $host . '/l4t/cv/' . $l['token']; ?>
                        <div class="l4x-my pix <?= $l['revoked_at'] ? 'is-off' : '' ?>">
                            <div class="l4x-my__main">
                                <b><?= $h($l['label']) ?></b>
                                <span class="l4x-muted"><?= $l['revoked_at'] ? 'отозвана' : ((int)$l['views'] ? 'открывали ' . (int)$l['views'] . ' раз, последний — ' . date('d.m H:i', strtotime((string)$l['last_view_at'])) : 'ещё не открывали') ?></span>
                            </div>
                            <?php if (!$l['revoked_at']): ?>
                                <button class="l4x-link" data-act="copy" data-url="<?= $h($url) ?>" title="Скопировать"><?= l4x_icon('link') ?></button>
                                <button class="l4x-link" data-act="qr" data-mode="url" data-url="<?= $h($url) ?>" data-label="<?= $h($l['label']) ?>" title="QR"><?= l4x_icon('qr') ?></button>
                                <button class="l4x-link l4x-danger" data-act="link-revoke" data-id="<?= (int)$l['id'] ?>" title="Отозвать"><?= l4x_icon('close') ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <aside class="l4x-col l4x-col--side">
            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('search') ?>Сохранённые поиски</h2></div>
                <p class="l4x-hint" style="margin-top:0">Когда появится специалист под поиск — придёт уведомление. Сохранить можно на бирже в режиме «Специалисты».</p>
                <?php foreach ($savedSearches as $ss): ?>
                    <div class="l4x-my pix" style="margin-top:8px">
                        <div class="l4x-my__main"><b><?= $h($ss['skill'] ? ($skillsAll[$ss['skill']]['name'] ?? $ss['skill']) : '') ?><?= $ss['skill'] && $ss['q'] ? ' + ' : '' ?><?= $h($ss['q'] ?? '') ?></b></div>
                        <button class="l4x-link l4x-danger" data-act="search-delete" data-id="<?= (int)$ss['id'] ?>"><?= l4x_icon('close') ?></button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="l4x-card pix">
                <div class="l4x-card__head"><h2><?= l4x_icon('pin') ?>Организатору</h2></div>
                <p class="l4x-hint" style="margin-top:0">Проведите встречу или митап: гости показывают пропуск из L4T, вы сканируете его телефоном. Пропуск обновляется каждые 30 секунд — скриншот не пройдёт.</p>
                <?php if ($hostEvents): ?>
                    <ul class="l4x-events" style="margin-top:10px">
                        <?php foreach (array_slice($hostEvents, 0, 5) as $e): ?>
                            <li><b><?= $h($e['title']) ?></b><span class="l4x-muted"><?= date('d.m.Y', strtotime((string)$e['starts_at'])) ?> · отмечено <?= (int)$e['checkins'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="l4x-btn l4x-btn--ghost l4x-btn--sm" href="/l4t/organizer.php" style="margin-top:12px"><?= l4x_icon('settings') ?>Мероприятия и джемы</a>
            </div>
        </aside>
    </div>
</section>
