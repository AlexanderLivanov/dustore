<?php
declare(strict_types=1);
/**
 * swad/controllers/og.php — превью ссылок (Open Graph + Twitter Card).
 *
 * По ним Telegram, VK, WhatsApp и Discord рисуют карточку: картинку, название
 * и описание. Раньше у страниц игр этих тегов не было, и ссылка на /g/123
 * расшаривалась голым адресом.
 */

if (!function_exists('og_abs')) {
    /** Мессенджеры принимают только абсолютные https-ссылки на картинку. */
    function og_abs(string $u): string {
        $u = trim($u);
        if ($u === '' || str_starts_with($u, 'data:')) return '';
        if (preg_match('~^https?://~i', $u)) return $u;
        return 'https://dustore.ru/' . ltrim($u, '/');
    }

    function og_tags(string $title, string $desc, string $url, string $image, string $type = 'website'): string {
        $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $desc = trim(preg_replace('/\s+/u', ' ', strip_tags($desc)) ?? '');
        if (mb_strlen($desc) > 200) $desc = mb_substr($desc, 0, 199) . '…';
        $img = og_abs($image);
        $out = [
            '<meta name="description" content="' . $e($desc) . '">',
            '<meta property="og:site_name" content="Dustore">',
            '<meta property="og:type" content="' . $e($type) . '">',
            '<meta property="og:title" content="' . $e($title) . '">',
            '<meta property="og:description" content="' . $e($desc) . '">',
            '<meta property="og:url" content="' . $e($url) . '">',
            '<meta property="og:locale" content="ru_RU">',
            '<meta name="twitter:card" content="' . ($img ? 'summary_large_image' : 'summary') . '">',
            '<link rel="canonical" href="' . $e($url) . '">',
        ];
        if ($img) {
            $out[] = '<meta property="og:image" content="' . $e($img) . '">';
            $out[] = '<meta name="twitter:image" content="' . $e($img) . '">';
        }
        return implode("\n    ", $out) . "\n";
    }

    /** Карточка игры: обложка (широкая картинка крупной карточки), иначе баннер, иначе иконка. */
    function og_game(array $g): string {
        $img = ($g['path_to_cover'] ?? '') ?: (($g['banner_url'] ?? '') ?: ($g['icon_url'] ?? ''));
        $desc = ($g['short_description'] ?? '') ?: ($g['description'] ?? '');
        if (!empty($g['studio_name'])) $desc = trim($desc) !== '' ? $desc : 'Игра студии ' . $g['studio_name'] . ' на Dustore';
        return og_tags((string)$g['name'] . ' — Dustore', (string)$desc, 'https://dustore.ru/g/' . (int)$g['id'], (string)$img);
    }
}
