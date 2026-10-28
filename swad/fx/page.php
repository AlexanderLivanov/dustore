<?php
declare(strict_types=1);

/**
 * swad/fx/page.php — подключение Fid Core на странице-хозяине (/fid, игра, студия, игрок).
 *
 *   <head>  … <?= FxPage::head() ?>  </head>
 *   <body>  <?= FxPage::body() ?>  …  <?= FxPage::scripts() ?>
 *
 * head() — стили, body() — svg-спрайт иконок + window.FX (CSRF, id зрителя), scripts() — fx.js.
 * Корневой элемент страницы — <div class="fx …">; всё, что внутри, наследует токены.
 */

require_once __DIR__ . '/render_hub.php';
require_once __DIR__ . '/../controllers/csrf.php';

final class FxPage
{
    /** /swad/css/fx.css → /swad/css/fx.css?v=<mtime>: браузер не держит старый файл после правок. */
    public static function asset(string $path): string
    {
        $f = dirname(__DIR__, 2) . $path;
        return $path . (is_file($f) ? '?v=' . filemtime($f) : '');
    }

    public static function head(): string
    {
        return '<link rel="stylesheet" href="' . self::asset('/swad/css/fx.css') . '">';
    }

    public static function body(): string
    {
        $cfg = ['api' => '/api/fx.php', 'csrf' => csrf_token(), 'viewer' => Fx::uid(), 'm' => Fx::mobile() ? 1 : 0];
        return FxRender::sprite()
            . '<script>window.FX=' . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';</script>';
    }

    /** $editor — подключить редактор статей (кнопка «Статья» в композере). */
    public static function scripts(bool $editor = false): string
    {
        return '<script src="' . self::asset('/swad/js/fx.js') . '" defer></script>'
            . ($editor ? '<script src="' . self::asset('/swad/js/fx-editor.js') . '" defer></script>' : '');
    }

    /**
     * Ссылка в консоль разработчика на конкретную настройку — открывается в новой вкладке (см. FxRenderHub::gear).
     *   to     — раздел консоли: edit | coop | monetization | mystudio | staff | analytics | projects
     *   $q     — ['game' => id] или ['studio' => id]
     *   $anchor — имя поля или id карточки на странице консоли (name, description, screenshots-card…)
     * devs/goto.php проверит права, выберет нужную студию в сессии и перекинет на страницу с подсветкой поля.
     */
    public static function console(string $to, array $q = [], string $anchor = ''): string
    {
        return '/devs/goto?' . http_build_query(['to' => $to] + $q) . ($anchor !== '' ? '&a=' . rawurlencode($anchor) : '');
    }

    /** Данные зрителя для композера: [id, name, img] или null для гостя. */
    public static function viewer(): ?array
    {
        $id = Fx::uid();
        if ($id <= 0) return null;
        $u = FxPeople::user($id);
        return $u ? ['id' => $id, 'name' => $u['name'], 'img' => $u['img']] : null;
    }
}
