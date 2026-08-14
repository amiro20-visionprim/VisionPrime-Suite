<?php

declare(strict_types=1);

/**
 * Generates the Vision Prime Connector plugin icons (icon-128x128.png and
 * icon-256x256.png) that WordPress shows in the Plugins list and in the
 * plugin details modal. Requires PHP-GD.
 *
 * Usage: php tools/make-icons.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$fontCandidates = [
    'C:/Windows/Fonts/arialbd.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
];
$font = '';
foreach ($fontCandidates as $f) {
    if (is_file($f)) { $font = $f; break; }
}
if ($font === '') {
    fwrite(STDERR, "No TTF font found — install DejaVu or Liberation, or point \$fontCandidates at one.\n");
    exit(1);
}

function hex2rgb(string $h): array
{
    $h = ltrim($h, '#');
    return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
}

/** Punch the four corners of a filled rectangle to radius $r (destructive on alpha). */
function punch_corners($img, int $x1, int $y1, int $x2, int $y2, int $r): void
{
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagealphablending($img, false);
    imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $transparent);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $transparent);
    imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $transparent);
    imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $transparent);
    imagealphablending($img, true);
}

function make_icon(int $size, string $font): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imagealphablending($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Vertical gradient body: indigo -> violet.
    [$r1, $g1, $b1] = hex2rgb('#4338CA');
    [$r2, $g2, $b2] = hex2rgb('#8B5CF6');
    $pad = (int) round($size * 0.02);
    $x1 = $pad; $y1 = $pad; $x2 = $size - $pad; $y2 = $size - $pad;
    $radius = (int) round($size * 0.22);

    for ($y = $y1; $y <= $y2; $y++) {
        $t = ($y - $y1) / max(1, $y2 - $y1);
        $c = imagecolorallocate($img,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t));
        imageline($img, $x1, $y, $x2, $y, $c);
    }
    punch_corners($img, $x1, $y1, $x2, $y2, $radius);

    // Subtle top highlight to give it depth.
    $hl = imagecolorallocatealpha($img, 255, 255, 255, 100);
    imagefilledellipse($img, (int) ($size * 0.5), (int) ($size * 0.18), (int) ($size * 1.1), (int) ($size * 0.62), $hl);

    // Brand mark "VP".
    $white = imagecolorallocate($img, 255, 255, 255);
    $fontSize = (int) round($size * 0.34);
    $bbox = imagettfbbox($fontSize, 0, $font, 'VP');
    $tw = $bbox[2] - $bbox[0];
    $th = $bbox[1] - $bbox[7];
    $tx = (int) round(($size - $tw) / 2 - $bbox[0]);
    $ty = (int) round(($size + $th) / 2 - $bbox[7]);
    imagettftext($img, $fontSize, 0, $tx, $ty, $white, $font, 'VP');

    return $img;
}

$img256 = make_icon(256, $font);
imagepng($img256, $root . '/icon-256x256.png');

$img128 = imagecreatetruecolor(128, 128);
imagesavealpha($img128, true);
imagealphablending($img128, true);
imagecopyresampled($img128, $img256, 0, 0, 0, 0, 128, 128, 256, 256);
imagepng($img128, $root . '/icon-128x128.png');

imagedestroy($img256);
imagedestroy($img128);

echo "OK  icon-256x256.png (" . number_format(filesize($root . '/icon-256x256.png')) . " bytes)\n";
echo "OK  icon-128x128.png (" . number_format(filesize($root . '/icon-128x128.png')) . " bytes)\n";
