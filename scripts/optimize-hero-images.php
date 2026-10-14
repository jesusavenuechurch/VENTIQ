<?php
// Makes web-sized copies of the welcome page photos in public/images/hero/web:
// WebP at 640, 1280 and 1920 px wide, plus a 1280 px JPEG for old browsers.
// The originals were 8-14 MB each (up to 8495 px wide); these are ~100-300 KB.
// Run with: php -d memory_limit=1G scripts/optimize-hero-images.php
$dir = __DIR__ . '/../public/images/hero';
foreach (['2', '3', '4', 'sing'] as $name) {
    $src = imagecreatefromjpeg("$dir/$name.jpg");
    [$w, $h] = [imagesx($src), imagesy($src)];
    foreach ([640, 1280, 1920] as $width) {
        $height = (int) round($h * $width / $w);
        $img = imagescale($src, $width, $height, IMG_BICUBIC);
        imagewebp($img, "$dir/web/$name-$width.webp", 72);
        if ($width === 1280) {
            imageinterlace($img, true);
            imagejpeg($img, "$dir/web/$name-1280.jpg", 75);
        }
        imagedestroy($img);
    }
    imagedestroy($src);
    echo "$name done\n";
}
