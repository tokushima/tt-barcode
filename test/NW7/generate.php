<?php
use tt\barcode\NW7;

// シンプル
eq(file_get_contents(\testman\Resource::path('NW7/simple.svg')), NW7::svg('12345'));
eq(file_get_contents(\testman\Resource::path('NW7/simple.png')), NW7::create('12345')->render_png());

// カスタマイズ
eq(file_get_contents(\testman\Resource::path('NW7/custom.svg')), NW7::create('9876543210', 'A', 'B')->fg_color('#003366')->height(100)->margin(20)->wide_ratio(3.0)->render_svg());

// テキスト非表示
eq(file_get_contents(\testman\Resource::path('NW7/no_text.svg')), NW7::create('12345')->show_text(false)->render_svg());

// スタート/ストップ指定
$ss = NW7::create('9999', 'C', 'D');
eq(file_get_contents(\testman\Resource::path('NW7/start_stop.svg')), $ss->render_svg());
eq(file_get_contents(\testman\Resource::path('NW7/start_stop.png')), $ss->render_png());

// ファイル保存
$tmp = tempnam(sys_get_temp_dir(), 'nw7');
NW7::png('12345', $tmp);
eq(file_get_contents(\testman\Resource::path('NW7/simple.png')), file_get_contents($tmp));
unlink($tmp);
