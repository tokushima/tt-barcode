<?php
spl_autoload_register(function($class){
	$file = __DIR__.'/../../lib/'.str_replace('\\', '/', $class).'.php';
	if(is_file($file)){
		require_once $file;
	}
});

use tt\barcode\QRCode;

$output_dir = __DIR__.'/output';
if(!is_dir($output_dir)){
	mkdir($output_dir, 0777, true);
}

$text = 'https://example.com/test?id=12345';

echo "=== QRCode Test ===\n\n";

echo "1. Standard SVG ... ";
QRCode::create($text)->save_svg($output_dir.'/standard.svg');
echo "OK\n";

echo "2. Rounded SVG ... ";
QRCode::create($text)->design('rounded')->fg_color('#1a1a2e')->save_svg($output_dir.'/rounded.svg');
echo "OK\n";

echo "3. Dots SVG ... ";
QRCode::create($text)->design('dots')->fg_color('#e94560')->bg_color('#0f3460')->save_svg($output_dir.'/dots.svg');
echo "OK\n";

echo "4. YouTube SVG ... ";
QRCode::create($text, QRCode::EC_H)
	->design('youtube')->fg_color('#FF0000')->finder_color('#CC0000')->margin(2)
	->save_svg($output_dir.'/youtube.svg');
echo "OK\n";

echo "5. Gradient SVG ... ";
QRCode::create($text)->design('dots')->gradient('#FF6B6B', '#4ECDC4')->save_svg($output_dir.'/gradient.svg');
echo "OK\n";

echo "6. Standard PNG ... ";
QRCode::png($text, $output_dir.'/standard.png');
echo "OK\n";

echo "7. YouTube PNG ... ";
QRCode::create($text, QRCode::EC_H)->design('youtube')->fg_color('#FF0000')->save_png($output_dir.'/youtube.png');
echo "OK\n";

echo "8. Japanese ... ";
QRCode::create('こんにちは世界')->design('rounded')->save_svg($output_dir.'/japanese.svg');
echo "OK\n";

echo "9. Shortcut SVG ... ";
$svg = QRCode::svg('Hello');
echo "OK (".strlen($svg)." bytes)\n";

echo "\n=== All Passed ===\n";

foreach(glob($output_dir.'/*') as $f){
	printf("  %-20s %s\n", basename($f), number_format(filesize($f)).' bytes');
}
