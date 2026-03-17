<?php
namespace tt\barcode\nw7;

/**
 * NW-7 (Codabar) エンコーダ
 *
 * 各文字は7エレメント (バー4本 + スペース3本) で構成
 * N=ナロー(1), W=ワイド(ratio倍)
 */
class NW7Encoder{
	/**
	 * エンコードパターン (バー,スペース,バー,スペース,バー,スペース,バー)
	 * 1=ナロー, 0=ワイド (逆転注意: パターン表記では1がワイド)
	 * ここでは true=ワイド, false=ナロー
	 */
	private const PATTERNS = [
		'0' => [1,1,1,1,1,2,2],
		'1' => [1,1,1,1,2,2,1],
		'2' => [1,1,1,2,1,1,2],
		'3' => [2,2,1,1,1,1,1],
		'4' => [1,1,2,1,1,2,1],
		'5' => [2,1,1,1,1,2,1],
		'6' => [1,2,1,1,1,1,2],
		'7' => [1,2,1,1,2,1,1],
		'8' => [1,2,2,1,1,1,1],
		'9' => [2,1,1,2,1,1,1],
		'-' => [1,1,1,2,2,1,1],
		'$' => [1,1,2,2,1,1,1],
		':' => [2,1,1,1,2,1,2],
		'/' => [2,1,2,1,1,1,2],
		'.' => [2,1,2,1,2,1,1],
		'+' => [1,1,2,1,2,1,2],
		'A' => [1,1,2,2,1,2,1],
		'B' => [1,2,1,2,1,1,2],  // 修正版: 正しいパターンに合わせる
		'C' => [1,1,1,2,1,2,2],
		'D' => [1,1,1,2,2,2,1],
	];

	/**
	 * データ文字列をバーパターン配列に変換
	 *
	 * @return array{bars: array<array{bool, int}>, text: string}
	 *   bars: [is_bar, width_units] の配列
	 *   text: スタート/ストップ込みのテキスト
	 */
	public static function encode(string $data, string $start = 'A', string $stop = 'A'): array{
		$data = strtoupper(trim($data));
		$start = strtoupper($start);
		$stop = strtoupper($stop);

		if(!isset(self::PATTERNS[$start]) || !in_array($start, ['A','B','C','D'])){
			throw new \InvalidArgumentException('Invalid start character: '.$start);
		}
		if(!isset(self::PATTERNS[$stop]) || !in_array($stop, ['A','B','C','D'])){
			throw new \InvalidArgumentException('Invalid stop character: '.$stop);
		}

		$chars = $start.$data.$stop;
		for($i = 0; $i < strlen($chars); $i++){
			if(!isset(self::PATTERNS[$chars[$i]])){
				throw new \InvalidArgumentException('Invalid NW-7 character: '.$chars[$i]);
			}
		}

		$bars = [];
		for($i = 0; $i < strlen($chars); $i++){
			if($i > 0){
				$bars[] = [false, 1]; // 文字間ギャップ (ナロースペース)
			}
			$pattern = self::PATTERNS[$chars[$i]];
			for($j = 0; $j < 7; $j++){
				$is_bar = ($j % 2 === 0); // 偶数=バー, 奇数=スペース
				$bars[] = [$is_bar, $pattern[$j]];
			}
		}

		return [
			'bars' => $bars,
			'text' => $chars,
		];
	}

	/**
	 * 使用可能なデータ文字
	 */
	public static function valid_chars(): string{
		return '0123456789-$:/.+';
	}
}
