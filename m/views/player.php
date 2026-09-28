<?php
/**
 * m/views/player.php — страница игрока: /m/player/<ник>.
 *
 * Тот же хаб, что на сайте (swad/fx/pages.php → FxPages::player): баннер, личная стена, коллекция,
 * друзья, отзывы, у владельца — «Аккаунт». Без ника открываем свою страницу.
 * Настройки приложения (уведомления, установка, passkey) остаются на /m/profile — оттуда сюда ведёт карточка профиля.
 */
$name = trim((string)$param);
if ($name === '') {
    if (!$uid) { header('Location: /m/login?back=' . rawurlencode('/m/player'), true, 302); exit; }
    $st = $db->prepare("SELECT username FROM users WHERE id = ?");
    $st->execute([$uid]);
    $own = (string)$st->fetchColumn();
    header('Location: ' . ($own !== '' ? '/m/player/' . rawurlencode($own) : '/m/profile'), true, 302);
    exit;
}

m_fx($db);
$hub = FxPages::player($db, $name, ['m' => true]);

if (!$hub) {
    http_response_code(404);
    $title = 'Игрок не найден — Dustore';
    echo m_empty('user-off', 'Игрок не найден', '', ['На главную', '/m/']);
    return;
}

$title      = $hub['name'] . ' — Dustore';
$bodyClass .= ' p-hub';
$navTab     = $hub['owner'] ? 'profile' : '';       // чужая страница — ни одна вкладка меню не «своя»
$headExtra .= m_fx_head();
$footExtra .= m_fx_scripts() . '<script src="' . FxPage::asset('/swad/js/fx-player.js') . '" defer></script>';

echo m_fx_top();
echo $hub['html'];
echo $hub['modals']();         // окно «Изменить профиль» — вне .fx, у него свои стили
