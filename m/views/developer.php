<?php
/**
 * m/views/developer.php — страница студии: /m/dev/<тикер> или /m/developer/<id>.
 *
 * Тело — тот же хаб, что и на сайте (swad/fx/pages.php → FxPages::studio): баннер с логотипом,
 * подпиской и «О студии», награды, «Задонатить», маленький блок с цифрами/командой/проектами
 * (на телефоне — горизонтальная лента карточек сверху) и девблог. Вторая копия разметки не нужна:
 * что добавят в хаб позже — появится и в приложении.
 */
m_fx($db);

$isId = $param !== null && ctype_digit((string)$param);
$hub = ($param !== null && $param !== '')
    ? FxPages::studio($db, $isId ? '' : (string)$param, ['m' => true, 'id' => $isId ? (int)$param : 0])
    : null;

if (!$hub) {
    http_response_code(404);
    $title = 'Студия не найдена — Dustore';
    echo m_empty('building-store', 'Студия не найдена', '', ['На главную', '/m/']);
    return;
}

$title      = $hub['name'] . ' — Dustore';
$bodyClass .= ' p-hub';
$headExtra .= m_fx_head();
$footExtra .= m_fx_scripts(true);

echo m_fx_top();
echo $hub['html'];
