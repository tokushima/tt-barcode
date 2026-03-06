<?php
/**
 * 郵便カスタマーバーコード
 */
$get_type = function($str){
	$bits = [
		'0'=>[1,4,4],'1'=>[1,1,4],'2'=>[1,3,2],'3'=>[3,1,2],'4'=>[1,2,3],
		'5'=>[1,4,1],'6'=>[3,2,1],'7'=>[2,1,3],'8'=>[2,3,1],'9'=>[4,1,1],
		'!'=>[3,2,4],'#'=>[3,4,2],'%'=>[2,3,4],'@'=>[4,3,2],'('=>[2,4,3],
		')'=>[4,2,3],'['=>[4,4,1],']'=>[1,1,1],'-'=>[4,1,4],
		'S'=>[1], 'E'=>[1],
	];

	$type = [];
	for($i=0;$i<strlen($str);$i++){
		foreach($bits[$str[$i]] as $t){
			$type[] = $t;
		}
	}
	return $type;
};

// https://www.post.japanpost.jp/zipcode/zipmanual/p25.html
$bar = \tt\barcode\CustomerBarcode::create('263-0023','千葉市稲毛区緑町3丁目30－8 郵便ビル403号');
eq('S26300233-30-8-403@@@5E',$bar->getChardata());
eq($get_type('S26300233-30-8-403@@@5E'),$bar->getBars());

$bar = \tt\barcode\CustomerBarcode::create('014-0113','秋田県仙北郡仙北町堀見内 南田茂木 添60－1');
eq('S014011360-1@@@@@@@@@]E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('1100016','東京都台東区台東5－6－3 ABCビル10F');
eq('S11000165-6-3-10@@@@@9E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('0600906','北海道札幌市東区北六条東4丁目 郵便センター6号館');
eq('S06009064-6@@@@@@@@@@9E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('0650006','北海道札幌市東区北六条東8丁目 郵便センター10号館');
eq('S06500068-10@@@@@@@@@9E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('4070033','山梨県韮崎市龍岡町下條南割 韮崎400');
eq('S4070033400@@@@@@@@@@-E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('2730102','千葉県鎌ケ谷市右京塚 東3丁目20－5 郵便・A&bコーポB604号');
eq('S27301023-20-5!1604@@0E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('1980036','東京都青梅市河辺町十一丁目六番地一号 郵便タワー601');
eq('S198003611-6-1-601@@@]E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('0270203','岩手県宮古市大字津軽石第二十一地割大淵川480');
eq('S027020321-480@@@@@@@(E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('5900016','大阪府堺市中田出井町四丁目六番十九号');
eq('S59000164-6-19@@@@@@@#E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('0800831','北海道帯広市稲田町南七線 西28');
eq('S08008317-28@@@@@@@@@[E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('3170055','茨城県日立市宮田町6丁目7－14 ABCビル2F');
eq('S31700556-7-14-2@@@@@!E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('6500046','神戸市中央区港島中町9丁目7－6 郵便シティA棟1F1号');
eq('S65000469-7-6!01-1@@@5E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('6230011','京都府綾部市青野町綾部6－7 LプラザB106');
eq('S62300116-7#1!1106@@@4E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('2280024','神奈川県座間市入谷6丁目3454－5 郵便ハイツ6－1108');
eq('S22800246-3454-5-6-112E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('9100067','福井県福井市新田塚3丁目80－25 J1ビル2－B');
eq('S91000673-80-25!91-2!9E',$bar->getChardata());

$bar = \tt\barcode\CustomerBarcode::create('0640804','札幌市中央区南四条西29丁目1524－23 第2郵便ハウス501');
eq('S064080429-1524-23-2-3E',$bar->getChardata());

// バー数テスト (スタート1 + データ20*3 + CD1*3 + ストップ1 = 65)
eq(65, count($bar->getBars()));

// SVG 一致テスト
$bar1 = \tt\barcode\CustomerBarcode::create('263-0023','千葉市稲毛区緑町3丁目30－8 郵便ビル403号');
eq(file_get_contents(\testman\Resource::path('CustomerBarcode/basic.svg')), $bar1->render_svg());

// PNG 一致テスト
eq(file_get_contents(\testman\Resource::path('CustomerBarcode/basic.png')), $bar1->render_png());

// オプション付きSVG 一致テスト
$bar2 = \tt\barcode\CustomerBarcode::create('0640804','札幌市中央区南四条西29丁目1524－23 第2郵便ハウス501');
eq(file_get_contents(\testman\Resource::path('CustomerBarcode/custom.svg')), $bar2->render_svg(['color' => '#003366', 'bgcolor' => '#FFFFFF']));

// ファイル保存テスト
$tmp = tempnam(sys_get_temp_dir(), 'cb_svg');
$bar1->save_svg($tmp);
eq(file_get_contents(\testman\Resource::path('CustomerBarcode/basic.svg')), file_get_contents($tmp));
unlink($tmp);
