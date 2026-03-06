<?php
spl_autoload_register(function($class){
	$file = __DIR__.'/../../lib/'.str_replace('\\', '/', $class).'.php';
	if(is_file($file)){
		require_once $file;
	}
});

use tt\barcode\NW7;

$output_dir = __DIR__.'/output';
if(!is_dir($output_dir)){
	mkdir($output_dir, 0777, true);
}

// シンプルなSVG
$svg = NW7::svg('12345');
file_put_contents($output_dir.'/simple.svg', $svg);
echo "Generated: simple.svg\n";

// PNG保存
NW7::png('12345', $output_dir.'/simple.png');
echo "Generated: simple.png\n";

// カスタマイズ
NW7::create('A9876543210B')
	->fg_color('#003366')
	->height(100)
	->margin(20)
	->wide_ratio(3.0)
	->save_svg($output_dir.'/custom.svg');
echo "Generated: custom.svg\n";

// テキスト非表示
NW7::create('12345')
	->show_text(false)
	->save_svg($output_dir.'/no_text.svg');
echo "Generated: no_text.svg\n";

// スタート/ストップ指定
NW7::create('9999', 'C', 'D')
	->save_svg($output_dir.'/start_stop.svg')
	->save_png($output_dir.'/start_stop.png');
echo "Generated: start_stop.svg, start_stop.png\n";

echo "Done.\n";
