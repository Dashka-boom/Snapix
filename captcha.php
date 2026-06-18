<?php
session_start();

function snapix_generate_captcha_code(int $length = 5): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxIndex = strlen($alphabet) - 1;
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $maxIndex)];
    }

    return $code;
}

$code = snapix_generate_captcha_code();
$_SESSION['captcha_code'] = $code;

$width = 190;
$height = 66;
$image = imagecreatetruecolor($width, $height);

$background = imagecolorallocate($image, 4, 6, 7);
$panel = imagecolorallocate($image, 11, 18, 21);
$accent = imagecolorallocate($image, 104, 197, 219);
$text = imagecolorallocate($image, 255, 255, 255);
$noise = imagecolorallocatealpha($image, 104, 197, 219, 70);
$line = imagecolorallocatealpha($image, 255, 255, 255, 95);

imagefill($image, 0, 0, $background);
imagefilledrectangle($image, 4, 4, $width - 5, $height - 5, $panel);
imagerectangle($image, 4, 4, $width - 5, $height - 5, $accent);

for ($i = 0; $i < 26; $i++) {
    imagesetpixel($image, random_int(8, $width - 9), random_int(8, $height - 9), $noise);
}

for ($i = 0; $i < 5; $i++) {
    imageline(
        $image,
        random_int(8, $width - 20),
        random_int(8, $height - 8),
        random_int(20, $width - 8),
        random_int(8, $height - 8),
        $line
    );
}

$fontSize = 5;
$charWidth = imagefontwidth($fontSize);
$charHeight = imagefontheight($fontSize);
$spacing = 11;
$totalWidth = strlen($code) * $charWidth + (strlen($code) - 1) * $spacing;
$x = (int) (($width - $totalWidth) / 2);
$baseY = (int) (($height - $charHeight) / 2);

for ($i = 0, $length = strlen($code); $i < $length; $i++) {
    imagestring($image, $fontSize, $x, $baseY + random_int(-4, 4), $code[$i], $text);
    $x += $charWidth + $spacing;
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
imagepng($image);
imagedestroy($image);
