<?php
/**
 * l4t/_bid_card.php — одна карточка заявки.
 *
 * Рисует ОДНО место: первую страницу ленты собирает index.php, следующие
 * страницы и результаты поиска — l4t/api/feed.php, тем же партиалом.
 *
 * Было: десяток data-атрибутов на кнопке, потеря любого молча ломала модалку.
 * Стало: один data-bid с JSON — модалка берёт всё оттуда.
 *
 * Ожидает: $bid (строка bids), $authors (id => [name, avatar, handle]) — опционально.
 */

$isStudio = ($bid['owner_type'] ?? 'user') === 'studio';
$created  = !empty($bid['created_at']) ? strtotime((string)$bid['created_at']) : time();
$author   = $authors[(int)($bid['bidder_id'] ?? 0)] ?? null;

$payload = [
    'id'        => (int)$bid['id'],
    'role'      => (string)($bid['search_role'] ?? ''),
    'spec'      => (string)($bid['search_spec'] ?? ''),
    'exp'       => (string)($bid['experience']  ?? ''),
    'cond'      => (string)($bid['conditions']  ?? ''),
    'goal'      => (string)($bid['goal']        ?? ''),
    'details'   => (string)($bid['details']     ?? ''),
    'type'      => $isStudio ? 'studio' : 'user',
    'jam'       => !empty($bid['jam_id']),
    'date'      => date('d.m.Y', $created),
    'views'     => (int)($bid['views'] ?? 0),
    'responses' => (int)($bid['responses'] ?? 0),
    'author'    => $author,
    'mine'      => !empty($_SESSION['USERDATA']['id']) && (int)$_SESSION['USERDATA']['id'] === (int)($bid['bidder_id'] ?? 0),
];
?>
<article class="l4x-bid" tabindex="0" data-bid="<?= htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
    <div class="l4x-bid__top">
        <span class="l4x-bid__who">
            <?php if ($author && $author['avatar']): ?>
                <img src="<?= htmlspecialchars($author['avatar'], ENT_QUOTES) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="l4x-bid__ph"><?= l4x_icon($isStudio ? 'studio' : 'user') ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($author['name'] ?? ($isStudio ? 'Студия' : 'Пользователь')) ?>
            <?php if ($isStudio): ?><span class="l4x-chip l4x-chip--line">студия</span><?php endif; ?>
        </span>
        <time><?= date('d.m', $created) ?></time>
    </div>

    <h3 class="l4x-bid__role"><?= htmlspecialchars($payload['role'] ?: 'Без названия') ?></h3>

    <div class="l4x-bid__tags">
        <?php if ($payload['jam']): ?><span class="l4x-chip l4x-chip--acc">джем</span><?php endif; ?>
        <?php foreach (['spec', 'exp', 'cond'] as $k): if ($payload[$k] !== ''): ?>
            <span class="l4x-chip"><?= htmlspecialchars(mb_substr($payload[$k], 0, 32)) ?></span>
        <?php endif; endforeach; ?>
    </div>

    <?php if ($payload['details'] !== ''): ?>
        <p class="l4x-bid__desc"><?= htmlspecialchars(mb_substr($payload['details'], 0, 180)) ?><?= mb_strlen($payload['details']) > 180 ? '…' : '' ?></p>
    <?php endif; ?>

    <div class="l4x-bid__foot">
        <span><?= l4x_icon('eye') ?><?= $payload['views'] ?></span>
        <span><?= l4x_icon('inbox') ?><?= $payload['responses'] ?></span>
        <?php if ($payload['goal'] !== ''): ?><span class="l4x-bid__goal"><?= htmlspecialchars(mb_substr($payload['goal'], 0, 40)) ?></span><?php endif; ?>
        <span class="l4x-bid__go"><?= l4x_icon('arrow') ?></span>
    </div>
</article>
