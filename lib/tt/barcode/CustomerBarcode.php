<?php
namespace tt\barcode;

/**
 * 郵便カスタマーバーコード
 * @see https://www.post.japanpost.jp/zipcode/zipmanual/index.html
 */
class CustomerBarcode{
	// バーの種類
	const BAR_LONG = 1;      // ロングバー（フルハイト）
	const BAR_SEMI_UP = 2;   // セミロングバー（上）アセンダ
	const BAR_SEMI_DOWN = 3; // セミロングバー（下）ディセンダ
	const BAR_TIMING = 4;    // タイミングバー（短）トラッカー

	// キャラクタ → バーパターン（各3バー）
	private static array $CHAR_PATTERNS = [
		'0'=>[1,4,4], '1'=>[1,1,4], '2'=>[1,3,2], '3'=>[3,1,2], '4'=>[1,2,3],
		'5'=>[1,4,1], '6'=>[3,2,1], '7'=>[2,1,3], '8'=>[2,3,1], '9'=>[4,1,1],
		'!'=>[3,2,4], '#'=>[3,4,2], '%'=>[2,3,4], '@'=>[4,3,2], '('=>[2,4,3],
		')'=>[4,2,3], '['=>[4,4,1], ']'=>[1,1,1], '-'=>[4,1,4],
	];

	// アルファベット → 制御コード変換
	// CC1=!, CC2=#, CC3=%
	private static array $ALPHA_MAP = [
		'A'=>'!0','B'=>'!1','C'=>'!2','D'=>'!3','E'=>'!4','F'=>'!5','G'=>'!6','H'=>'!7','I'=>'!8','J'=>'!9',
		'K'=>'#0','L'=>'#1','M'=>'#2','N'=>'#3','O'=>'#4','P'=>'#5','Q'=>'#6','R'=>'#7','S'=>'#8','T'=>'#9',
		'U'=>'%0','V'=>'%1','W'=>'%2','X'=>'%3','Y'=>'%4','Z'=>'%5',
	];

	// チェックディジット用の値テーブル
	private static array $CD_VALUES = [
		'0'=>0,'1'=>1,'2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,
		'-'=>10,'!'=>11,'#'=>12,'%'=>13,'@'=>14,'('=>15,')'=>16,'['=>17,']'=>18,
	];

	private array $bars = [];
	private string $chardata = '';

	/**
	 * @param string $zip 郵便番号（7桁、ハイフン有無どちらも可）
	 * @param string $address 町域以降の住所（例: ４丁目２−８）
	 */
	public static function create(string $zip, string $address = ''): self{
		$obj = new self();
		$obj->encode($zip, $address);
		return $obj;
	}

	/**
	 * バー配列を取得
	 * @return int[] バー種類の配列（BAR_LONG, BAR_SEMI_UP, BAR_SEMI_DOWN, BAR_TIMING）
	 */
	public function getBars(): array{
		return $this->bars;
	}

	/**
	 * エンコード済みキャラクタデータを取得（スタート・ストップ・CD含む）
	 */
	public function getChardata(): string{
		return $this->chardata;
	}

	private function encode(string $zip, string $address): void{
		$zip = mb_convert_kana($zip, 'a');
		$zip = str_replace('-', '', $zip);

		if(!ctype_digit($zip) || strlen($zip) !== 7){
			throw new \InvalidArgumentException('郵便番号は7桁の数字で指定してください');
		}

		$address = self::normalizeAddress($address);

		// キャラクタデータ構築
		$chardata = '';
		$str = $zip.$address;
		for($i = 0; $i < strlen($str); $i++){
			$chardata .= ctype_alpha($str[$i]) ? self::$ALPHA_MAP[$str[$i]] : $str[$i];
		}
		// 20文字にパディング（CC4=@で埋める）
		$chardata = str_pad(substr($chardata, 0, 20), 20, '@');

		// チェックディジット計算
		$cdsum = 0;
		for($i = 0; $i < strlen($chardata); $i++){
			$cdsum += self::$CD_VALUES[$chardata[$i]];
		}
		$cd_val = ($cdsum % 19 === 0) ? 0 : 19 - ($cdsum % 19);
		$cd_char = array_search($cd_val, self::$CD_VALUES);

		$this->chardata = 'S'.$chardata.$cd_char.'E';

		// スタートコード
		$this->bars[] = self::BAR_LONG;

		// データ部
		for($i = 0; $i < strlen($chardata); $i++){
			foreach(self::$CHAR_PATTERNS[$chardata[$i]] as $t){
				$this->bars[] = $t;
			}
		}

		// チェックディジット
		foreach(self::$CHAR_PATTERNS[$cd_char] as $t){
			$this->bars[] = $t;
		}

		// ストップコード
		$this->bars[] = self::BAR_LONG;
	}

	/**
	 * 住所を正規化
	 */
	public static function normalizeAddress(string $address): string{
		if(empty($address)){
			return '';
		}
		$address = mb_convert_kana($address, 'as');
		$address = mb_strtoupper($address);
		$address = preg_replace('/[&\/・.]/u', '', $address);
		$address = preg_replace('/[A-Z]{2,}/u', '-', $address);

		// 漢数字変換
		$m = [];
		if(preg_match_all('/([一二三四五六七八九十]+)(丁目|丁|番地|番|号|地割|線|の|ノ)/u', $address, $m)){
			foreach($m[0] as $k => $v){
				$v = preg_replace('/([一二三四五六七八九]+)十([一二三四五六七八九])/u', '${1}${2}', $v);
				$v = preg_replace('/([一二三四五六七八九]+)十/u', '${1}0', $v);
				$v = preg_replace('/十([一二三四五六七八九]+)/u', '1${1}', $v);
				$address = str_replace(
					$m[0][$k],
					str_replace(
						['一','二','三','四','五','六','七','八','九','十'],
						[1,2,3,4,5,6,7,8,9,10],
						$v
					),
					$address
				);
			}
		}
		$address = preg_replace('/[^\w-]/', '-', $address);
		$address = preg_replace('/(\d)F$/', '$1', $address);
		$address = preg_replace('/(\d)F/', '$1-', $address);
		$address = preg_replace('/[-]+/', '-', $address);
		$address = preg_replace('/-([A-Z]+)/', '$1', $address);
		$address = preg_replace('/([A-Z]+)-/', '$1', $address);

		$address = trim($address, '-');
		return $address;
	}

	/**
	 * SVG文字列を生成
	 * @param array $opt オプション
	 *   float bar_height バーの高さ(mm) デフォルト3.6
	 *   float module_width モジュール幅(mm) デフォルト0.6
	 *   float gap ギャップ幅(mm) デフォルト0.6
	 *   string color バーの色 デフォルト #000000
	 *   string bgcolor 背景色 デフォルト transparent
	 */
	public function render_svg(array $opt = []): string{
		$bar_height = $opt['bar_height'] ?? 3.6;
		$module_width = $opt['module_width'] ?? 0.6;
		$gap = $opt['gap'] ?? 0.6;
		$color = $opt['color'] ?? '#000000';
		$bgcolor = $opt['bgcolor'] ?? null;

		$bar_count = count($this->bars);
		$total_width = $bar_count * $module_width + ($bar_count - 1) * $gap;

		$svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$svg .= sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%smm" height="%smm" viewBox="0 0 %s %s">'."\n",
			$total_width,
			$bar_height,
			$total_width,
			$bar_height
		);

		if($bgcolor !== null){
			$svg .= sprintf('<rect width="%s" height="%s" fill="%s"/>'."\n", $total_width, $bar_height, $bgcolor);
		}

		$x = 0;
		$div = $bar_height / 3;

		foreach($this->bars as $bar_type){
			[$y, $h] = $this->barDimensions($bar_type, $bar_height, $div);
			$svg .= sprintf(
				'<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>'."\n",
				round($x, 4),
				round($y, 4),
				$module_width,
				round($h, 4),
				$color
			);
			$x += $module_width + $gap;
		}

		$svg .= '</svg>';
		return $svg;
	}

	/**
	 * SVGファイルを保存
	 */
	public function save_svg(string $filename, array $opt = []): self{
		$dir = dirname($filename);
		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($filename, $this->render_svg($opt));
		return $this;
	}

	/**
	 * PNGバイナリを返す
	 * @param array $opt オプション
	 *   float bar_height バーの高さ(mm) デフォルト3.6
	 *   float module_width モジュール幅(mm) デフォルト0.6
	 *   float gap ギャップ幅(mm) デフォルト0.6
	 *   string color バーの色 デフォルト #000000
	 *   string bgcolor 背景色 デフォルト 透明
	 *   int dpi 解像度 デフォルト300
	 */
	public function render_png(array $opt = []): string{
		$bar_height = $opt['bar_height'] ?? 3.6;
		$module_width = $opt['module_width'] ?? 0.6;
		$gap = $opt['gap'] ?? 0.6;
		$color = $opt['color'] ?? '#000000';
		$bgcolor = $opt['bgcolor'] ?? null;
		$dpi = $opt['dpi'] ?? 300;

		$px_per_mm = $dpi / 25.4;
		$bar_height_px = (int)round($bar_height * $px_per_mm);
		$module_width_px = max(1, (int)round($module_width * $px_per_mm));
		$gap_px = max(1, (int)round($gap * $px_per_mm));

		$bar_count = count($this->bars);
		$total_width_px = $bar_count * $module_width_px + ($bar_count - 1) * $gap_px;

		$canvas = imagecreatetruecolor($total_width_px, $bar_height_px);

		if($bgcolor === null){
			imagealphablending($canvas, false);
			imagesavealpha($canvas, true);
			$bg = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
		}else{
			[$r, $g, $b] = self::hex2rgb($bgcolor);
			$bg = imagecolorallocate($canvas, $r, $g, $b);
		}
		imagefill($canvas, 0, 0, $bg);

		[$r, $g, $b] = self::hex2rgb($color);
		$bar_color = imagecolorallocate($canvas, $r, $g, $b);

		$x = 0;
		$div = $bar_height / 3;
		foreach($this->bars as $bar_type){
			[$y_offset, $height] = $this->barDimensions($bar_type, $bar_height, $div);
			$y_px = (int)round($y_offset * $px_per_mm);
			$h_px = (int)round($height * $px_per_mm);

			imagefilledrectangle($canvas, $x, $y_px, $x + $module_width_px - 1, $y_px + $h_px - 1, $bar_color);
			$x += $module_width_px + $gap_px;
		}

		ob_start();
		imagepng($canvas);
		imagedestroy($canvas);
		return ob_get_clean();
	}

	/**
	 * PNGファイルに保存
	 */
	public function save_png(string $filename, array $opt = []): self{
		$dir = dirname($filename);
		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($filename, $this->render_png($opt));
		return $this;
	}

	/**
	 * バーの種類からY座標と高さを返す
	 * @return array [y_offset, height]
	 */
	private function barDimensions(int $bar_type, float $bar_height, float $div): array{
		return match($bar_type){
			self::BAR_LONG => [0, $bar_height],             // フルハイト 3.6mm
			self::BAR_SEMI_UP => [0, $div * 2],             // 上2/3 2.4mm
			self::BAR_SEMI_DOWN => [$div, $div * 2],        // 下2/3 2.4mm（上1/3オフセット）
			self::BAR_TIMING => [$div, $div],               // 中央1/3 1.2mm
			default => [0, $bar_height],
		};
	}

	private static function hex2rgb(string $color): array{
		$color = ltrim($color, '#');
		return [
			hexdec(substr($color, 0, 2)),
			hexdec(substr($color, 2, 2)),
			hexdec(substr($color, 4, 2)),
		];
	}
}
