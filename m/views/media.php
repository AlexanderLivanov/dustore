<?php
/**
 * m/views/media.php — «Медиа»: лента платформы в приложении (вкладка нижнего меню).
 *
 * Тот же код, что у /fid на сайте (swad/fx/pages.php → FxPages::fid): композер, вкладки «Друзья» / «Лента» / DustHunt,
 * пост открывается поверх ленты. Ссылки /m/media?post=123 открывают ленту сразу с постом (уведомления, «поделиться»).
 * Вкладка «Медиа» в нижнем меню появляется сама, как только существует этот файл (см. layout/shell.php).
 */
m_fx($db);

$hub = FxPages::fid($uid, [
    'm'    => true,
    'tab'  => (string)($_GET['tab'] ?? ''),
    'open' => (int)($_GET['post'] ?? 0),
]);

$title      = 'Медиа — Dustore';
$bodyClass .= ' p-hub';
$headExtra .= m_fx_head();
$footExtra .= m_fx_scripts(true);

echo m_fx_top();
echo $hub['html'];
