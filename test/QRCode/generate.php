<?php
use tt\barcode\QRCode;

$text = 'https://example.com/test?id=12345';
// SVG文字列の一致テスト
eq(file_get_contents(\testman\Resource::path('QRCode/standard.svg')), QRCode::create($text)->render_svg());
eq(file_get_contents(\testman\Resource::path('QRCode/rounded.svg')), QRCode::create($text)->design('rounded')->fg_color('#1a1a2e')->render_svg());
eq(file_get_contents(\testman\Resource::path('QRCode/dots.svg')), QRCode::create($text)->design('dots')->fg_color('#e94560')->bg_color('#0f3460')->render_svg());
eq(file_get_contents(\testman\Resource::path('QRCode/youtube.svg')), QRCode::create($text, QRCode::EC_H)->design('youtube')->fg_color('#FF0000')->finder_color('#CC0000')->margin(2)->render_svg());
eq(file_get_contents(\testman\Resource::path('QRCode/gradient.svg')), QRCode::create($text)->design('dots')->gradient('#FF6B6B', '#4ECDC4')->render_svg());
eq(file_get_contents(\testman\Resource::path('QRCode/japanese.svg')), QRCode::create('こんにちは世界')->design('rounded')->render_svg());

// PNG バイナリの一致テスト
eq(file_get_contents(\testman\Resource::path('QRCode/standard.png')), QRCode::create($text)->render_png());
eq(file_get_contents(\testman\Resource::path('QRCode/youtube.png')), QRCode::create($text, QRCode::EC_H)->design('youtube')->fg_color('#FF0000')->render_png());

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
