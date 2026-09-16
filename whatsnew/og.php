<?php

$dataFile = __DIR__ . '/data/versions.json';

$versions = [];

if (file_exists($dataFile)) {
    $versions = json_decode(file_get_contents($dataFile), true) ?: [];
}

$version = $_GET['v'] ?? '';

$item = null;

foreach ($versions as $release) {
    if (($release['version'] ?? '') === $version) {
        $item = $release;
        break;
    }
}

if (!$item) {
    $item = $versions[0] ?? [
        'version' => 'WHAT’S NEW',
        'title' => 'Обновления DUSTORE',
        'date' => date('Y-m-d')
    ];
}

$width = 1200;
$height = 630;

$image = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($image, 13, 11, 15);
$white = imagecolorallocate($image, 242, 237, 244);
$muted = imagecolorallocate($image, 146, 139, 150);
$accent = imagecolorallocate($image, 195, 33, 120);
$line = imagecolorallocate($image, 41, 37, 44);

imagefill($image, 0, 0, $bg);

for ($x = 0; $x < $width; $x += 80) {
    imageline($image, $x, 0, $x, $height, $line);
}

for ($y = 0; $y < $height; $y += 80) {
    imageline($image, 0, $y, $width, $y, $line);
}

imagefilledrectangle($image, 70, 70, 79, 560, $accent);

$font = __DIR__ . '/font.ttf';

if (!file_exists($font)) {
    $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
}

$boldFont = $font;

imagettftext(
    $image,
    24,
    0,
    110,
    120,
    $accent,
    $boldFont,
    'DUSTORE / WHAT’S NEW'
);

imagettftext(
    $image,
    72,
    0,
    110,
    225,
    $white,
    $boldFont,
    $item['version']
);

$title = $item['title'];

if (mb_strlen($title) > 42) {
    $title = mb_substr($title, 0, 39) . '...';
}

imagettftext(
    $image,
    38,
    0,
    110,
    295,
    $white,
    $font,
    $title
);

imagettftext(
    $image,
    22,
    0,
    110,
    535,
    $muted,
    $font,
    $item['date']
);

imagettftext(
    $image,
    24,
    0,
    940,
    535,
    $muted,
    $boldFont,
    'dustore.ru'
);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

imagepng($image);
imagedestroy($image);