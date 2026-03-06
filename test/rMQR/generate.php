<?php
use tt\barcode\rMQR;

// 基本: Hello (R7x43)
$rmqr = rMQR::create('Hello');
eq(file_get_contents(\testman\Resource::path('rMQR/simple.svg')), $rmqr->render_svg());
eq(file_get_contents(\testman\Resource::path('rMQR/simple.png')), $rmqr->render_png());

// カスタムカラー
eq(file_get_contents(\testman\Resource::path('rMQR/custom.svg')), rMQR::create('Hello')->fg_color('#003366')->module_size(15)->margin(3)->render_svg());

// 数字モード
eq(file_get_contents(\testman\Resource::path('rMQR/numeric.svg')), rMQR::create('1234567890')->render_svg());

// 英数字モード
eq(file_get_contents(\testman\Resource::path('rMQR/alnum.svg')), rMQR::create('HELLO WORLD')->render_svg());

// EC_H
eq(file_get_contents(\testman\Resource::path('rMQR/ec_h.svg')), rMQR::create('Hello', rMQR::EC_H)->render_svg());

// バージョン指定
eq(file_get_contents(\testman\Resource::path('rMQR/version.svg')), rMQR::create('Hello', rMQR::EC_M, 'R9x43')->render_svg());

// ショートカット
eq(file_get_contents(\testman\Resource::path('rMQR/simple.svg')), rMQR::svg('Hello'));

// ファイル保存
$tmp = tempnam(sys_get_temp_dir(), 'rmqr');
rMQR::create('Hello')->save_svg($tmp);
eq(file_get_contents(\testman\Resource::path('rMQR/simple.svg')), file_get_contents($tmp));
unlink($tmp);

$tmp = tempnam(sys_get_temp_dir(), 'rmqr_png');
rMQR::png('Hello', $tmp);
eq(file_get_contents(\testman\Resource::path('rMQR/simple.png')), file_get_contents($tmp));
unlink($tmp);

// マトリクスサイズ確認
eq(7, count(rMQR::create('Hello')->matrix()));
eq(43, count(rMQR::create('Hello')->matrix()[0]));
