<?php
use tt\barcode\DataMatrix;

// 基本: Hello (12x12)
$dm = DataMatrix::create('Hello');
eq(file_get_contents(\testman\Resource::path('DataMatrix/simple.svg')), $dm->render_svg());
eq(file_get_contents(\testman\Resource::path('DataMatrix/simple.png')), $dm->render_png());

// カスタムカラー
eq(file_get_contents(\testman\Resource::path('DataMatrix/custom.svg')), DataMatrix::create('Hello')->fg_color('#003366')->module_size(15)->margin(3)->render_svg());

// 数字
eq(file_get_contents(\testman\Resource::path('DataMatrix/numeric.svg')), DataMatrix::create('1234567890')->render_svg());

// 長文 (マルチリージョン)
eq(file_get_contents(\testman\Resource::path('DataMatrix/large.svg')), DataMatrix::create('Hello, World! This is a Data Matrix test.')->render_svg());

// マルチブロック (52x52, 2ブロック)
$mb = DataMatrix::create(str_repeat('A', 180));
eq(file_get_contents(\testman\Resource::path('DataMatrix/multiblock.svg')), $mb->render_svg());
eq(file_get_contents(\testman\Resource::path('DataMatrix/multiblock.png')), $mb->render_png());

// ショートカット
eq(file_get_contents(\testman\Resource::path('DataMatrix/simple.svg')), DataMatrix::svg('Hello'));

// ファイル保存
$tmp = tempnam(sys_get_temp_dir(), 'dm_svg');
DataMatrix::create('Hello')->save_svg($tmp);
eq(file_get_contents(\testman\Resource::path('DataMatrix/simple.svg')), file_get_contents($tmp));
unlink($tmp);

$tmp = tempnam(sys_get_temp_dir(), 'dm_png');
DataMatrix::png('Hello', $tmp);
eq(file_get_contents(\testman\Resource::path('DataMatrix/simple.png')), file_get_contents($tmp));
unlink($tmp);

// マトリクスサイズ確認
eq(12, count(DataMatrix::create('Hello')->matrix()));
eq(52, count(DataMatrix::create(str_repeat('A', 180))->matrix()));

// 形状指定: 正方形
$sq = DataMatrix::create('Hello')->shape('square');
$sq_matrix = $sq->matrix();
eq(count($sq_matrix), count($sq_matrix[0])); // rows == cols

// 形状指定: 長方形
$rect = DataMatrix::create('Hello')->shape('rectangle');
$rect_matrix = $rect->matrix();
neq(count($rect_matrix), count($rect_matrix[0])); // rows != cols
eq(8, count($rect_matrix)); // 8x18
eq(18, count($rect_matrix[0]));

// 形状指定: 長方形 SVG/PNG
eq(file_get_contents(\testman\Resource::path('DataMatrix/rectangle.svg')), $rect->render_svg());
eq(file_get_contents(\testman\Resource::path('DataMatrix/rectangle.png')), $rect->render_png());
