<?php
declare(strict_types=1);

/**
 * swad/fx/boot.php — единая точка входа Fid Core.
 *   require_once __DIR__ . '/swad/fx/boot.php';
 * Подключает ядро, посты, ленты, хабы, иконки и рендер; схему ставит лениво (Fx::pdo() / Fx::use()).
 */
require_once __DIR__ . '/hub.php';
require_once __DIR__ . '/icons.php';
