<?php
// swad/controllers/trailer_embed.php
// Превращает произвольную ссылку на трейлер в готовый HTML для <div class="gp-trailer">:
// YouTube / VK / RuTube / Vimeo -> <iframe embed>, прямой файл (mp4/webm, osnova) -> <video>,
// неизвестное -> ссылка-заглушка. Возвращает готовый (уже экранированный) HTML или ''.
//
// trailer_facade_html() — обёртка для ПУБЛИЧНЫХ страниц (game.php, m/views/game.php):
// постер + кнопка play вместо сразу живого iframe. Сам эмбед лежит внутри <template> —
// его src не грузится браузером, пока JS не вытащит содержимое наружу по клику
// (тот же приём, что в lite-youtube-embed). Экономит трафик/JS всем, кто трейлер
// не откроет, и не требует повторной реализации разбора ссылки — используется
// тот же trailer_embed(), что и раньше.

if (!function_exists('trailer_embed')) {

    // Общий разбор YouTube-ссылки: вынесен отдельно, чтобы им могли пользоваться
    // и trailer_embed() (готовый iframe), и trailer_poster_url() (превью-картинка) —
    // раньше подобный разбор дублировали по месту (см. правку devs/edit.php),
    // и одна из копий тихо разошлась с другой.
    function _tr_youtube_id(string $u): ?array {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $u, $m)) {
            $start = preg_match('~[?&]t=(\d+)~', $u, $tm) ? (int)$tm[1] : 0;
            return [$m[1], $start];
        }
        return null;
    }

    function _tr_iframe(string $src): string {
        $safe = htmlspecialchars($src, ENT_QUOTES);
        return "<iframe src=\"{$safe}\" allowfullscreen allow=\"autoplay; encrypted-media; fullscreen; picture-in-picture\"></iframe>";
    }

    function _tr_note(string $text, string $orig): string {
        $safe = htmlspecialchars($orig, ENT_QUOTES);
        return "<div style=\"position:absolute;bottom:8px;right:8px;font-size:.7rem;background:rgba(0,0,0,.6);padding:3px 8px;border-radius:6px;z-index:2;\">"
             . "<a href=\"{$safe}\" target=\"_blank\" rel=\"noopener\" style=\"color:#fff;text-decoration:none;\">{$text} · открыть ↗</a></div>";
    }

    function trailer_embed(?string $url): string {
        $u = trim((string)$url);
        if ($u === '') return '';
        $u = html_entity_decode($u);

        // YouTube (watch / youtu.be / shorts / embed) + метка про VPN
        if ($yt = _tr_youtube_id($u)) {
            [$id, $start] = $yt;
            $src = "https://www.youtube-nocookie.com/embed/{$id}" . ($start ? "?start={$start}" : '');
            return _tr_iframe($src) . _tr_note('YouTube может не открываться без VPN', $u);
        }

        // VK — готовый embed video_ext.php (нормализуем домен на vk.com)
        if (preg_match('~(?:vk\.com|vkvideo\.ru)/video_ext\.php\?(.+)$~', $u, $m)) {
            return _tr_iframe('https://vk.com/video_ext.php?' . $m[1]);
        }
        // VK — обычная ссылка video-OID_ID / video OID_ID
        if (preg_match('~(?:vk\.com|vkvideo\.ru)/video(-?\d+)_(\d+)~', $u, $m)) {
            return _tr_iframe("https://vk.com/video_ext.php?oid={$m[1]}&id={$m[2]}&hd=2");
        }

        // RuTube
        if (preg_match('~rutube\.ru/(?:video|play/embed)/([0-9A-Za-z]+)~', $u, $m)) {
            return _tr_iframe("https://rutube.ru/play/embed/{$m[1]}");
        }

        // Vimeo
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $u, $m)) {
            return _tr_iframe("https://player.vimeo.com/video/{$m[1]}");
        }

        // Прямой видеофайл или медиа-CDN osnova (leonardo.osnova.io и т.п.)
        if (preg_match('~\.(mp4|webm|mov|m4v)(\?|$)~i', $u) || preg_match('~(?:leonardo\.)?osnova\.io~', $u)) {
            $safe = htmlspecialchars($u, ENT_QUOTES);
            return "<video controls preload=\"metadata\" playsinline "
                 . "style=\"position:absolute;inset:0;width:100%;height:100%;background:#000;\" src=\"{$safe}\"></video>";
        }

        // Неизвестный источник — ссылка-заглушка
        $safe = htmlspecialchars($u, ENT_QUOTES);
        return "<a href=\"{$safe}\" target=\"_blank\" rel=\"noopener\" "
             . "style=\"position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:8px;color:#fff;background:#000;text-decoration:none;font-weight:700;\">▶ Смотреть трейлер</a>";
    }

    // Картинка для постера facade. Честно умеем только YouTube (готовый URL без
    // похода в чужое API); для VK/RuTube/Vimeo/файлов вызывающий код сам передаёт
    // запасную картинку (обычно обложка игры) — это не хуже, а по сути то же самое,
    // что делают крупные магазины игр для не-YouTube трейлеров.
    function trailer_poster_url(?string $url): ?string {
        $u = trim((string)$url);
        if ($u === '') return null;
        $u = html_entity_decode($u);
        if ($yt = _tr_youtube_id($u)) {
            return "https://img.youtube.com/vi/{$yt[0]}/hqdefault.jpg";
        }
        return null;
    }

    // Facade для публичных страниц: постер + кнопка play, реальный эмбед — в
    // <template> (его src не грузится браузером, пока JS не вынет содержимое
    // наружу по клику). Разметка кладётся внутрь уже существующего .gp-trailer —
    // ему не нужно ничего знать про facade, position:relative он даёт как и раньше.
    function trailer_facade_html(?string $url, string $fallbackPoster = ''): string {
        $embed = trailer_embed($url);
        if ($embed === '') return '';

        $poster = trailer_poster_url($url) ?: $fallbackPoster;
        $bg = $poster !== ''
            ? 'background-image:url(\'' . htmlspecialchars($poster, ENT_QUOTES) . '\')'
            : 'background:#000';

        return "<div class=\"gp-trailer-facade\" style=\"{$bg}\" role=\"button\" tabindex=\"0\" aria-label=\"Смотреть трейлер\">"
             . "<span class=\"gp-trailer-play\"><svg viewBox=\"0 0 24 24\" width=\"30\" height=\"30\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></span>"
             . "<template>{$embed}</template>"
             . "</div>";
    }
}