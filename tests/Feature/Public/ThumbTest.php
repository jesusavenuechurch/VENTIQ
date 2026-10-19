<?php

use App\Support\Thumb;
use Illuminate\Support\Facades\Storage;

it('serves a small WebP copy of a big poster, made once', function () {
    Storage::fake('public');
    $img = imagecreatetruecolor(3000, 2000);
    ob_start(); imagejpeg($img, null, 95); Storage::disk('public')->put('event-banners/big.jpg', ob_get_clean());

    $url = Thumb::url('event-banners/big.jpg', 480);

    expect($url)->toContain('thumbs/480/event-banners/big.webp');
    [$w] = getimagesizefromstring(Storage::disk('public')->get('thumbs/480/event-banners/big.webp'));
    expect($w)->toBe(480);
});

it('falls back to the original when a copy cannot be made', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-banners/odd.jpg', 'not an image');

    expect(Thumb::url('event-banners/odd.jpg'))->toContain('event-banners/odd.jpg')
        ->and(Thumb::url(null))->toBeNull();
});

it('shows the per-ticket fee on the pricing page, not the old packages', function () {
    $this->get(route('pricing'))->assertOk()
        ->assertSee('4.9%')->assertSee('M7.50')->assertSee('M12.40')->assertSee('M87.60')
        ->assertDontSee('Starter')->assertDontSee('M600');
});
