<?php
/**
 * l4t/lib/icons.php — SVG-спрайт L4T. Та же манера, что в Dustore.Fid:
 * 24×24, обводка currentColor, stroke 1.8. Спрайт вставляется один раз,
 * дальше <use href="#l4i-name"> — без картинок и шрифтов-иконок.
 */

function l4x_icon(string $n, string $cls = ''): string
{
    return '<svg class="l4x-ic' . ($cls ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#l4i-' . $n . '"/></svg>';
}

function l4x_sprite(): void
{
    $P = [
        'user'      => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c0-3.4 3-5.6 7-5.6s7 2.2 7 5.6"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c0-3 2.5-4.8 5.5-4.8s5.5 1.8 5.5 4.8"/><path d="M16 5.5a3 3 0 0 1 0 5.6M17.5 14.6c2 .7 3 2.2 3 4.4"/>',
        'grid'      => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'briefcase' => '<rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M9 7.5V5.8A1.3 1.3 0 0 1 10.3 4.5h3.4A1.3 1.3 0 0 1 15 5.8v1.7M3.5 12.5h17"/>',
        'inbox'     => '<path d="M4 13.5 6.5 5h11l2.5 8.5V19a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M4 13.5h4.5l1.2 2.2h4.6l1.2-2.2H20"/>',
        'send'      => '<path d="M20.5 3.5 10 14M20.5 3.5 14 20.5l-4-6.5-6.5-4z"/>',
        'eye'       => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/>',
        'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'image'     => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m4 18 5.5-5 4 3.5 2.5-2 4 3.5"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M5.6 18.4l1.6-1.6M16.8 7.2l1.6-1.6"/>',
        'share'     => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="m8.2 10.8 7.6-4.1M8.2 13.2l7.6 4.1"/>',
        'link'      => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'file'      => '<path d="M6.5 3.5h7l4 4v13h-11z"/><path d="M13.5 3.5v4h4M9 12.5h6M9 16h6"/>',
        'shield'    => '<path d="M12 3.5 19 6v5.5c0 4.4-3 7.7-7 9-4-1.3-7-4.6-7-9V6z"/>',
        'lock'      => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'pin'       => '<path d="M12 21s-6.5-5.8-6.5-11a6.5 6.5 0 0 1 13 0c0 5.2-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'coin'      => '<circle cx="12" cy="12" r="8.5"/><path d="M10 16.5v-9h3a2.5 2.5 0 0 1 0 5h-4.5M8.5 14.5h5"/>',
        'flame'     => '<path d="M12 3s5 4 5 9a5 5 0 0 1-10 0c0-2 1-3.4 1-3.4S9 11 10.5 11C12 11 12 8 12 3z"/>',
        'star'      => '<path d="m12 3.8 2.5 5.1 5.6.8-4 4 1 5.5-5.1-2.7-5 2.7 1-5.5-4.1-4 5.6-.8z"/>',
        'gamepad'   => '<path d="M7.5 8h9a4.5 4.5 0 0 1 4.4 3.6l.7 4a2.6 2.6 0 0 1-4.7 2L15.6 16H8.4l-1.3 1.6a2.6 2.6 0 0 1-4.7-2l.7-4A4.5 4.5 0 0 1 7.5 8z"/><path d="M7 11v2.4M5.8 12.2h2.4M15.8 11.6h.01M18 13.4h.01"/>',
        'studio'    => '<path d="M4 20V7l7-3v16"/><path d="M11 20h9V10l-9-3"/><path d="M14.5 12.5h2M14.5 16h2M7 11h1M7 14.5h1"/>',
        'search'    => '<circle cx="11" cy="11" r="6.4"/><path d="m16 16 4 4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'trophy'    => '<path d="M8 4.5h8v5a4 4 0 0 1-8 0z"/><path d="M8 6.5H5a3 3 0 0 0 3 4M16 6.5h3a3 3 0 0 1-3 4M12 13.5v3.5M8.5 20h7M10 17h4"/>',
        'chart'     => '<path d="M4 20V4M4 20h16"/><path d="m7.5 15 3.5-4 3 2.5 5-6"/>',
        'telegram'  => '<path d="m20.5 4.5-17 6.8 5.2 1.9 2 6.3 3-3.7 4.6 3.4z"/><path d="m8.7 13.2 8.8-6"/>',
        'camera'    => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2.3l1.4-2h5.6l1.4 2h2.3A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5z"/><circle cx="12" cy="12.8" r="3.2"/>',
        'bookmark'  => '<path d="M6.5 4h11a1 1 0 0 1 1 1v15l-6.5-4-6.5 4V5a1 1 0 0 1 1-1z"/>',
        'qr'        => '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2zM18 14h2M14 18v2M18 18h2v2h-2z"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
    foreach ($P as $k => $d) {
        echo '<symbol id="l4i-' . $k . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</symbol>';
    }
    echo '</defs></svg>';
}
