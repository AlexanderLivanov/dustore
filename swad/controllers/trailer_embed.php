<?php
// swad/controllers/trailer_embed.php
// Превращает произвольную ссылку на трейлер в готовый HTML для <div class="gp-trailer">:
// YouTube / VK / RuTube / Vimeo -> <iframe embed>, прямой файл (mp4/webm, osnova) -> <video>,
// неизвестное -> ссылка-заглушка. Возвращает готовый (уже экранированный) HTML или ''.

if (!function_exists('trailer_embed')) {

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
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $u, $m)) {
            $start = preg_match('~[?&]t=(\d+)~', $u, $tm) ? (int)$tm[1] : 0;
            $src = "https://www.youtube-nocookie.com/embed/{$m[1]}" . ($start ? "?start={$start}" : '');
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
}