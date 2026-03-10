<?php
use tt\barcode\QRCode;

$text = 'https://example.com/test?id=12345';

// SVG: standard (デフォルト)
eq(file_get_contents(\testman\Resource::path('QRCode/standard.svg')), QRCode::create($text)->render_svg());

// SVG: dots
eq(file_get_contents(\testman\Resource::path('QRCode/dots.svg')), QRCode::create($text)->module_shape('dots')->fg_color('#e94560')->bg_color('#0f3460')->render_svg());

// SVG: dots + round finder
eq(file_get_contents(\testman\Resource::path('QRCode/dots_round.svg')), QRCode::create($text)->module_shape('dots')->finder_shape('round')->fg_color('#FF0000')->finder_color('#CC0000')->margin(2)->render_svg());

// SVG: dots + modern finder
eq(file_get_contents(\testman\Resource::path('QRCode/dots_modern.svg')), QRCode::create($text)->module_shape('dots')->finder_shape('modern')->fg_color('#FF0000')->finder_color('#CC0000')->margin(2)->render_svg());

// SVG: gradient
eq(file_get_contents(\testman\Resource::path('QRCode/gradient.svg')), QRCode::create($text)->module_shape('dots')->gradient('#FF6B6B', '#4ECDC4')->render_svg());

// SVG: japanese
eq(file_get_contents(\testman\Resource::path('QRCode/japanese.svg')), QRCode::create('こんにちは世界')->render_svg());

// PNG: standard
eq(file_get_contents(\testman\Resource::path('QRCode/standard.png')), QRCode::create($text)->render_png());

// PNG: dots + modern finder
eq(file_get_contents(\testman\Resource::path('QRCode/dots_modern.png')), QRCode::create($text)->module_shape('dots')->finder_shape('modern')->fg_color('#FF0000')->render_png());

// ショートカット
eq(file_get_contents(\testman\Resource::path('QRCode/standard.svg')), QRCode::svg($text));

// ファイル保存
$tmp = tempnam(sys_get_temp_dir(), 'qr_svg');
QRCode::create($text)->save_svg($tmp);
eq(file_get_contents(\testman\Resource::path('QRCode/standard.svg')), file_get_contents($tmp));
unlink($tmp);

$tmp = tempnam(sys_get_temp_dir(), 'qr_png');
QRCode::png($text, $tmp);
eq(file_get_contents(\testman\Resource::path('QRCode/standard.png')), file_get_contents($tmp));
unlink($tmp);

// 背景画像: standard + bg
eq(file_get_contents(\testman\Resource::path('QRCode/bg_standard.png')), QRCode::create('https://example.com')->bg_image(\testman\Resource::path('QRCode/bg_test.png'), 40)->module_size(10)->render_png());

// 背景画像: dots + round finder + bg
eq(file_get_contents(\testman\Resource::path('QRCode/bg_dots_round.png')), QRCode::create('https://example.com')->module_shape('dots')->finder_shape('round')->bg_image(\testman\Resource::path('QRCode/bg_test.png'), 60)->module_size(10)->margin(2)->render_png());

// 背景画像: dots + modern finder + bg
eq(file_get_contents(\testman\Resource::path('QRCode/bg_dots_modern.png')), QRCode::create('https://example.com')->module_shape('dots')->finder_shape('modern')->fg_color('#FF0000')->finder_color('#CC0000')->bg_image(\testman\Resource::path('QRCode/bg_landscape.png'), 50)->module_size(10)->margin(2)->render_png());
