<?php
use tt\barcode\MicroQR;

// M1: 数字のみ
eq(file_get_contents(\testman\Resource::path('MicroQR/m1_numeric.svg')), MicroQR::svg('01234'));

// M2: 数字
$m2 = MicroQR::create('1234567890');
eq(file_get_contents(\testman\Resource::path('MicroQR/m2_numeric.svg')), $m2->render_svg());
eq(file_get_contents(\testman\Resource::path('MicroQR/m2_numeric.png')), $m2->render_png());

// M2: 英数字
eq(file_get_contents(\testman\Resource::path('MicroQR/m2_alnum.svg')), MicroQR::create('HELLO')->render_svg());

// M3: バイト
$m3 = MicroQR::create('Hello')->fg_color('#003366')->module_size(15);
eq(file_get_contents(\testman\Resource::path('MicroQR/m3_byte.svg')), $m3->render_svg());
eq(file_get_contents(\testman\Resource::path('MicroQR/m3_byte.png')), $m3->render_png());

// M4: バイトモード
eq(file_get_contents(\testman\Resource::path('MicroQR/m4_byte.svg')), MicroQR::create('Hello, World!!', MicroQR::EC_L)->margin(3)->render_svg());

// M4-Q: 高誤り訂正
eq(file_get_contents(\testman\Resource::path('MicroQR/m4q_numeric.svg')), MicroQR::create('12345', MicroQR::EC_Q)->render_svg());

// M4: 英数字
eq(file_get_contents(\testman\Resource::path('MicroQR/m4_alnum.svg')), MicroQR::create('HTTP://EXAMPLE.COM', MicroQR::EC_L)->render_svg());

// ファイル保存
$tmp = tempnam(sys_get_temp_dir(), 'mqr');
MicroQR::create('01234')->save_svg($tmp);
eq(file_get_contents(\testman\Resource::path('MicroQR/m1_numeric.svg')), file_get_contents($tmp));
unlink($tmp);
