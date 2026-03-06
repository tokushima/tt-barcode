<?php
namespace tt\barcode\datamatrix;

/**
 * Data Matrix ECC 200 エンコーダ
 * ISO/IEC 16022 準拠
 */
class DataMatrixEncoder{
	private static array $gf_exp = [];
	private static array $gf_log = [];
	private static bool $gf_initialized = false;

	/**
	 * @return bool[][] Data Matrixマトリクス (true=黒モジュール)
	 */
	public static function encode(string $text): array{
		$codewords = self::encode_ascii($text);
		$symbol = self::select_symbol(count($codewords));

		$codewords = self::pad_codewords($codewords, $symbol[4]);
		$blocks = $symbol[6];

		if($blocks === 1){
			$ec = self::reed_solomon($codewords, $symbol[5], 1);
			$all = array_merge($codewords, $ec);
		}else{
			// 複数ブロック: インターリーブ分割してEC計算
			$ec_per_block = intdiv($symbol[5], $blocks);

			// ブロック分割: インターリーブ (block b = codewords[b], codewords[b+blocks], ...)
			$data_blocks = array_fill(0, $blocks, []);
			for($i = 0; $i < count($codewords); $i++){
				$data_blocks[$i % $blocks][] = $codewords[$i];
			}

			$ec_blocks = [];
			for($b = 0; $b < $blocks; $b++){
				$ec_blocks[] = self::reed_solomon_block($data_blocks[$b], $ec_per_block);
			}

			// データインターリーブ (元の順序に戻す, ブロックサイズが異なる場合に対応)
			$max_data = count($data_blocks[0]);
			$all = [];
			for($i = 0; $i < $max_data; $i++){
				for($b = 0; $b < $blocks; $b++){
					if($i < count($data_blocks[$b])){
						$all[] = $data_blocks[$b][$i];
					}
				}
			}
			// ECインターリーブ
			for($i = 0; $i < $ec_per_block; $i++){
				for($b = 0; $b < $blocks; $b++){
					$all[] = $ec_blocks[$b][$i];
				}
			}
		}

		return self::build_matrix($all, $symbol);
	}

	private static function encode_ascii(string $text): array{
		$codewords = [];
		$len = strlen($text);
		$i = 0;

		while($i < $len){
			$c = ord($text[$i]);

			// 連続する数字2桁を1コードワードに圧縮
			if($c >= 0x30 && $c <= 0x39 && $i + 1 < $len){
				$c2 = ord($text[$i + 1]);
				if($c2 >= 0x30 && $c2 <= 0x39){
					$codewords[] = (($c - 0x30) * 10 + ($c2 - 0x30)) + 130;
					$i += 2;
					continue;
				}
			}

			if($c >= 0 && $c <= 127){
				$codewords[] = $c + 1;
			}else{
				// 拡張ASCII (128-255)
				$codewords[] = DataMatrixData::UPPER_SHIFT;
				$codewords[] = ($c - 128) + 1;
			}
			$i++;
		}
		return $codewords;
	}

	private static function select_symbol(int $data_len): array{
		foreach(DataMatrixData::SYMBOL_SIZES as $sym){
			if($sym[4] >= $data_len){
				return $sym;
			}
		}
		throw new \InvalidArgumentException('Data too large for Data Matrix');
	}

	private static function pad_codewords(array $codewords, int $capacity): array{
		if(count($codewords) < $capacity){
			$codewords[] = DataMatrixData::PAD;
		}
		while(count($codewords) < $capacity){
			$r = ((149 * (count($codewords) + 1)) % 253) + 1;
			$codewords[] = (DataMatrixData::PAD + $r) % 254;
		}
		return $codewords;
	}

	private static function gf_init(): void{
		if(self::$gf_initialized) return;
		self::$gf_exp = array_fill(0, 512, 0);
		self::$gf_log = array_fill(0, 256, 0);

		$x = 1;
		for($i = 0; $i < 255; $i++){
			self::$gf_exp[$i] = $x;
			self::$gf_log[$x] = $i;
			$x <<= 1;
			if($x & 0x100){
				$x ^= DataMatrixData::GF_POLY;
			}
		}
		for($i = 255; $i < 512; $i++){
			self::$gf_exp[$i] = self::$gf_exp[$i - 255];
		}
		self::$gf_initialized = true;
	}

	private static function gf_multiply(int $a, int $b): int{
		if($a === 0 || $b === 0) return 0;
		return self::$gf_exp[self::$gf_log[$a] + self::$gf_log[$b]];
	}

	private static function rs_generator(int $count): array{
		self::gf_init();
		$poly = [1];
		for($i = 0; $i < $count; $i++){
			$new = array_fill(0, count($poly) + 1, 0);
			$factor = self::$gf_exp[$i + 1]; // roots a^1 .. a^count
			for($j = 0; $j < count($poly); $j++){
				$new[$j] ^= $poly[$j];
				$new[$j + 1] ^= self::gf_multiply($poly[$j], $factor);
			}
			$poly = $new;
		}
		return $poly;
	}

	private static function reed_solomon_block(array $data, int $ec_count): array{
		self::gf_init();
		$gen = self::rs_generator($ec_count);
		$result = array_merge($data, array_fill(0, $ec_count, 0));

		for($i = 0; $i < count($data); $i++){
			$coef = $result[$i];
			if($coef !== 0){
				for($j = 0; $j < count($gen); $j++){
					$result[$i + $j] ^= self::gf_multiply($gen[$j], $coef);
				}
			}
		}
		return array_slice($result, count($data));
	}

	private static function reed_solomon(array $data, int $ec_count, int $blocks): array{
		self::gf_init();
		$ec_per_block = intdiv($ec_count, $blocks);
		$data_per_block = intdiv(count($data), $blocks);
		$gen = self::rs_generator($ec_per_block);

		$all_ec = array_fill(0, $ec_count, 0);
		for($b = 0; $b < $blocks; $b++){
			$block = array_slice($data, $b * $data_per_block, $data_per_block);
			$result = array_merge($block, array_fill(0, $ec_per_block, 0));

			for($i = 0; $i < count($block); $i++){
				$coef = $result[$i];
				if($coef !== 0){
					for($j = 0; $j < count($gen); $j++){
						$result[$i + $j] ^= self::gf_multiply($gen[$j], $coef);
					}
				}
			}
			$ec = array_slice($result, count($block));
			for($i = 0; $i < $ec_per_block; $i++){
				$all_ec[$b + $i * $blocks] = $ec[$i];
			}
		}
		return $all_ec;
	}

	private static function build_matrix(array $codewords, array $symbol): array{
		[$rows, $cols, $dr, $dc] = $symbol;

		// マッピングマトリクスのサイズ (ファインダ/クロックトラック除外)
		$map_rows = $rows - ($dr * 2);
		$map_cols = $cols - ($dc * 2);

		// データ配置 (標準配置アルゴリズム)
		$mapping = array_fill(0, $map_rows, array_fill(0, $map_cols, -1));
		self::place_data($mapping, $map_rows, $map_cols, $codewords);

		// フルマトリクス構築
		$matrix = array_fill(0, $rows, array_fill(0, $cols, false));

		// ファインダパターンとクロックトラック配置
		self::place_finder_and_clock($matrix, $rows, $cols, $dr, $dc);

		// マッピングデータをフルマトリクスに転写
		$region_h = intdiv($map_rows, $dr);
		$region_w = intdiv($map_cols, $dc);

		for($r = 0; $r < $map_rows; $r++){
			for($c = 0; $c < $map_cols; $c++){
				$ri = intdiv($r, $region_h);
				$ci = intdiv($c, $region_w);
				$mr = $r + ($ri * 2) + 1;
				$mc = $c + ($ci * 2) + 1;
				$matrix[$mr][$mc] = ($mapping[$r][$c] === 1);
			}
		}

		return $matrix;
	}

	private static function place_finder_and_clock(array &$matrix, int $rows, int $cols, int $dr, int $dc): void{
		$map_rows = $rows - ($dr * 2);
		$map_cols = $cols - ($dc * 2);
		$rh = intdiv($map_rows, $dr);
		$rw = intdiv($map_cols, $dc);

		// 各データ領域のボーダーを配置
		for($ri = 0; $ri < $dr; $ri++){
			$top_row = $ri * ($rh + 2);
			$bot_row = $top_row + $rh + 1;

			for($c = 0; $c < $cols; $c++){
				// 上: クロックトラック (交互パターン)
				$matrix[$top_row][$c] = ($c % 2 === 0);
				// 下: ソリッドライン (L字の底辺, 全て暗)
				$matrix[$bot_row][$c] = true;
			}
		}

		for($ci = 0; $ci < $dc; $ci++){
			$left_col = $ci * ($rw + 2);
			$right_col = $left_col + $rw + 1;

			for($r = 0; $r < $rows; $r++){
				// 左: ソリッドライン (L字の左辺, 全て暗)
				$matrix[$r][$left_col] = true;
				// 右: クロックトラック (交互パターン)
				$matrix[$r][$right_col] = ($r % 2 !== 0);
			}
		}
	}

	/**
	 * ECC 200 標準配置アルゴリズム
	 */
	private static function place_data(array &$matrix, int $rows, int $cols, array $codewords): void{
		$r = 4;
		$c = 0;
		$idx = 0;
		$total = count($codewords);

		while($r < $rows || $c < $cols){
			// 特殊コーナーケース
			if($r === $rows && $c === 0){
				self::place_corner1($matrix, $rows, $cols, $codewords[$idx] ?? 0);
				$idx++;
			}
			if($r === $rows - 2 && $c === 0 && $cols % 4 !== 0){
				self::place_corner2($matrix, $rows, $cols, $codewords[$idx] ?? 0);
				$idx++;
			}
			if($r === $rows - 2 && $c === 0 && $cols % 8 === 4){
				self::place_corner3($matrix, $rows, $cols, $codewords[$idx] ?? 0);
				$idx++;
			}
			if($r === $rows + 4 && $c === 2 && $cols % 8 === 0){
				self::place_corner4($matrix, $rows, $cols, $codewords[$idx] ?? 0);
				$idx++;
			}

			// 上方向斜め移動
			while($r >= 0 && $c < $cols){
				if($r < $rows && $c >= 0 && $matrix[$r][$c] === -1){
					if($idx < $total){
						self::place_utah($matrix, $rows, $cols, $r, $c, $codewords[$idx]);
						$idx++;
					}else{
						self::place_utah($matrix, $rows, $cols, $r, $c, 0);
					}
				}
				$r -= 2;
				$c += 2;
			}
			$r += 1;
			$c += 3;

			// 下方向斜め移動
			while($r < $rows && $c >= 0){
				if($r >= 0 && $c < $cols && $matrix[$r][$c] === -1){
					if($idx < $total){
						self::place_utah($matrix, $rows, $cols, $r, $c, $codewords[$idx]);
						$idx++;
					}else{
						self::place_utah($matrix, $rows, $cols, $r, $c, 0);
					}
				}
				$r += 2;
				$c -= 2;
			}
			$r += 3;
			$c += 1;
		}

		// 未使用モジュールをゼロに
		for($r = 0; $r < $rows; $r++){
			for($c = 0; $c < $cols; $c++){
				if($matrix[$r][$c] === -1){
					$matrix[$r][$c] = 0;
				}
			}
		}

		// 右下コーナー固定パターン (ISO/IEC 16022)
		if($matrix[$rows - 1][$cols - 1] === 0){
			$matrix[$rows - 1][$cols - 1] = 1;
			$matrix[$rows - 2][$cols - 2] = 1;
		}
	}

	/**
	 * Utahシェイプでコードワードの8ビットを配置
	 */
	private static function place_utah(array &$matrix, int $rows, int $cols, int $r, int $c, int $val): void{
		$positions = [
			[-2, -2], [-2, -1],
			[-1, -2], [-1, -1], [-1, 0],
			[0, -2], [0, -1], [0, 0],
		];
		for($i = 0; $i < 8; $i++){
			$bit = ($val >> (7 - $i)) & 1;
			$pr = $r + $positions[$i][0];
			$pc = $c + $positions[$i][1];

			if($pr < 0){ $pr += $rows; $pc += 4 - (($rows + 4) % 8); }
			if($pc < 0){ $pc += $cols; $pr += 4 - (($cols + 4) % 8); }

			$matrix[$pr][$pc] = $bit;
		}
	}

	private static function place_corner1(array &$matrix, int $rows, int $cols, int $val): void{
		$pos = [
			[$rows - 1, 0], [$rows - 1, 1], [$rows - 1, 2],
			[0, $cols - 2], [0, $cols - 1],
			[1, $cols - 1], [2, $cols - 1], [3, $cols - 1],
		];
		for($i = 0; $i < 8; $i++){
			$matrix[$pos[$i][0]][$pos[$i][1]] = ($val >> (7 - $i)) & 1;
		}
	}

	private static function place_corner2(array &$matrix, int $rows, int $cols, int $val): void{
		$pos = [
			[$rows - 3, 0], [$rows - 2, 0], [$rows - 1, 0],
			[0, $cols - 4], [0, $cols - 3], [0, $cols - 2], [0, $cols - 1],
			[1, $cols - 1],
		];
		for($i = 0; $i < 8; $i++){
			$matrix[$pos[$i][0]][$pos[$i][1]] = ($val >> (7 - $i)) & 1;
		}
	}

	private static function place_corner3(array &$matrix, int $rows, int $cols, int $val): void{
		$pos = [
			[$rows - 3, 0], [$rows - 2, 0], [$rows - 1, 0],
			[0, $cols - 2], [0, $cols - 1],
			[1, $cols - 1], [2, $cols - 1], [3, $cols - 1],
		];
		for($i = 0; $i < 8; $i++){
			$matrix[$pos[$i][0]][$pos[$i][1]] = ($val >> (7 - $i)) & 1;
		}
	}

	private static function place_corner4(array &$matrix, int $rows, int $cols, int $val): void{
		$pos = [
			[$rows - 1, 0], [$rows - 1, $cols - 1],
			[0, $cols - 3], [0, $cols - 2], [0, $cols - 1],
			[1, $cols - 3], [1, $cols - 2], [1, $cols - 1],
		];
		for($i = 0; $i < 8; $i++){
			$matrix[$pos[$i][0]][$pos[$i][1]] = ($val >> (7 - $i)) & 1;
		}
	}
}
