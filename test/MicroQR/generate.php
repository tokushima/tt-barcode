<?php
spl_autoload_register(function($class){
	$file = __DIR__.'/../../lib/'.str_replace('\\', '/', $class).'.php';
	if(is_file($file)){
		require_once $file;
	}
});

use tokushima\barcode\MicroQR;

$output_dir = __DIR__.'/output';
if(!is_dir($output_dir)){
	mkdir($output_dir, 0777, true);
}

// M1: 数字のみ (最大5桁)
$svg = MicroQR::svg('01234');
file_put_contents($output_dir.'/m1_numeric.svg', $svg);
echo "Generated: m1_numeric.svg (M1, numeric)\n";

// M2: 数字
MicroQR::create('1234567890')
	->save_svg($output_dir.'/m2_numeric.svg')
	->save_png($output_dir.'/m2_numeric.png');
echo "Generated: m2_numeric.svg/png (M2, numeric)\n";

// M2: 英数字
MicroQR::create('HELLO')
	->save_svg($output_dir.'/m2_alnum.svg');
echo "Generated: m2_alnum.svg (M2, alphanumeric)\n";

// M3: バイト
MicroQR::create('Hello')
	->fg_color('#003366')
	->module_size(15)
	->save_svg($output_dir.'/m3_byte.svg')
	->save_png($output_dir.'/m3_byte.png');
echo "Generated: m3_byte.svg/png (M3, byte)\n";

// M4: より長いデータ (バイトモード最大15文字)
MicroQR::create('Hello, World!!', MicroQR::EC_L)
	->margin(3)
	->save_svg($output_dir.'/m4_byte.svg');
echo "Generated: m4_byte.svg (M4, byte)\n";

// M4-Q: 高誤り訂正
MicroQR::create('12345', MicroQR::EC_Q)
	->save_svg($output_dir.'/m4q_numeric.svg');
echo "Generated: m4q_numeric.svg (M4, EC_Q)\n";

// M4: 英数字で長めのデータ
MicroQR::create('HTTP://EXAMPLE.COM', MicroQR::EC_L)
	->save_svg($output_dir.'/m4_alnum.svg');
echo "Generated: m4_alnum.svg (M4, alphanumeric)\n";

echo "Done.\n";
