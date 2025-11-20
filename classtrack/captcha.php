<?php
session_start();

// Always regenerate if refresh parameter is present
if (isset($_GET['refresh'])) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $_SESSION['captcha_code'] = substr(str_shuffle($characters), 0, 6);
    $_SESSION['captcha_time'] = time();
}
// Otherwise regenerate if expired or not set
elseif (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time']) || 
        (time() - $_SESSION['captcha_time']) > 300) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $_SESSION['captcha_code'] = substr(str_shuffle($characters), 0, 6);
    $_SESSION['captcha_time'] = time();
}

$code = $_SESSION['captcha_code'];

// Make CAPTCHA image larger
$width = 300;
$height = 80;
$image = imagecreate($width, $height);

// colors
$bg = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg);

// random lines
for ($i = 0; $i < 8; $i++) {
    $line_color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
    imageline($image, 0, rand() % $height, $width, rand() % $height, $line_color);
}


$font_size = 5; // Built-in font size 1-5 only
$char_width = imagefontwidth($font_size);
$char_height = imagefontheight($font_size);

// Calculate total text width and starting position for center alignment
$total_text_width = $char_width * strlen($code) * 3; // 3 is spacing multiplier
$total_text_height = $char_height;

// Starting position to center the text
$x = ($width - $total_text_width) / 2;
$y = ($height - $total_text_height) / 2;

// Draw text with scattering
for ($i = 0; $i < strlen($code); $i++) {
    $char = $code[$i];
    
    // Position each character with good spacing and vertical scattering
    $char_x = $x + ($i * $char_width * 4.5); // 3 times the character width for spacing
    $char_y = $y + rand(-8, 8); // Vertical scattering
    
    $char_color = imagecolorallocate($image, rand(0, 100), rand(0, 100), rand(0, 100));
    imagestring($image, $font_size, $char_x, $char_y, $char, $char_color);
}

// random dots
for ($i = 0; $i < 200; $i++) {
    $dot_color = imagecolorallocate($image, rand(150,255), rand(150,255), rand(150,255));
    imagesetpixel($image, rand(0, $width), rand(0, $height), $dot_color);
}

// Add border
$border_color = imagecolorallocate($image, 200, 200, 200);
imagerectangle($image, 0, 0, $width-1, $height-1, $border_color);

header("Content-type: image/png");
imagepng($image);
imagedestroy($image);