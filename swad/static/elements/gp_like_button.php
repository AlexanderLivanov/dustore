<?php
/**
 * swad/static/elements/gp_like_button.php — компактное сердечко «сохранить в
 * коллекцию без скачивания/покупки» на карточке покупки game.php.
 *
 * Тот же механизм, что у большой карточки анонса (gp-wishlist-card) — одна
 * таблица wishlists, один toggle-эндпоинт (/api/wishlist/toggle.php), только
 * своя, компактная разметка и свой JS-хэндлер (toggleLike, не путать с
 * toggleWishlist — тот перекрашивает текст «В вишлисте», это лишнее здесь).
 *
 * Ожидает в области видимости: $game_id (int), $isInWishlist (bool).
 * Инклюдится из game.php, ID не дублируется на странице (вызывается не
 * больше одного раза за рендер — либо в ветке «купить», либо в ветке
 * «бесплатно/куплено», никогда в обеих сразу).
 */
?>
<?php if (!empty($_SESSION['USERDATA']['id'])): ?>
    <button class="gp-like-btn<?= $isInWishlist ? ' gp-like-btn-active' : '' ?>"
            id="gpLikeBtn"
            type="button"
            title="<?= $isInWishlist ? 'Убрать из коллекции' : 'Сохранить в коллекцию без скачивания' ?>"
            onclick="toggleLike(<?= (int)$game_id ?>, this)">
        <?= $isInWishlist ? '♥' : '♡' ?>
    </button>
<?php else: ?>
    <a class="gp-like-btn" href="/login" title="Войдите, чтобы сохранить в коллекцию">♡</a>
<?php endif; ?>
