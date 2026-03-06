<?php
namespace tt\barcode\microqr;

use tt\barcode\qrcode\ReedSolomon;

/**
 * マイクロQRコードエンコーダ
 * ISO/IEC 18004 マイクロQRコード (M1-M4)
 */
class MicroQREncoder{
	private int $version;
	private int $ec_level;
	private int $size;
	private array $modules;
	private array $reserved;

	/**
	 * @return bool[][] マイクロQRマトリクス (true=黒モジュール)
	 */
	public static function encode(string $text, int $ec_level = MicroQRData::EC_L, ?int $version = null): array{
		$encoder = new self();
		return $encoder->build($text, $ec_level, $version);
	}

	private function build(string $text, int $ec_level, ?int $version): array{
		$this->ec_level = $ec_level;
		$mode = $this->detect_mode($text);

		if($version === null){
			$this->version = $this->select_version($text, $mode);
		}else{
			$this->version = $version;
			$this->validate_version_mode($mode);
		}

		$this->size = MicroQRData::module_count($this->version);

		$bits = $this->build_bitstream($text, $mode);
		$codewords = $this->bits_to_codewords($bits);
		$final_codewords = $this->add_error_correction($codewords);

		$this->init_matrix();
		$this->place_finder_pattern();
		$this->place_timing_patterns();
		$this->reserve_format_area();
		$this->place_data($final_codewords);

		$best_mask = $this->apply_best_mask();
		$this->place_format_info($best_mask);

		return $this->modules;
	}

	private function detect_mode(string $text): int{
		if(preg_match('/^[0-9]+$/', $text)){
			return MicroQRData::MODE_NUMERIC;
		}
		if(preg_match('/^[0-9A-Z $%*+\-.\/:]+$/', $text)){
			return MicroQRData::MODE_ALPHANUMERIC;
		}
		return MicroQRData::MODE_BYTE;
	}

	private function select_version(string $text, int $mode): int{
		$len = ($mode === MicroQRData::MODE_BYTE) ? strlen($text) : mb_strlen($text);

		for($v = 1; $v <= 4; $v++){
			$ec = $this->ec_level;
			if($v === 1) $ec = MicroQRData::EC_DETECT;

			if(!isset(MicroQRData::CAPACITY[$v][$ec])){
				continue;
			}
			$cap = MicroQRData::CAPACITY[$v][$ec][$mode] ?? 0;
			if($cap >= $len){
				if($v === 1) $this->ec_level = MicroQRData::EC_DETECT;
				return $v;
			}
		}
		throw new \InvalidArgumentException('Data too large for Micro QR code');
	}

	private function validate_version_mode(int $mode): void{
		$ec = $this->ec_level;
		if($this->version === 1) $ec = MicroQRData::EC_DETECT;

		if(!isset(MicroQRData::SUPPORTED_MODES[$this->version][$ec])){
			throw new \InvalidArgumentException("EC level not supported for version M{$this->version}");
		}
		if(!in_array($mode, MicroQRData::SUPPORTED_MODES[$this->version][$ec])){
			throw new \InvalidArgumentException("Mode not supported for version M{$this->version}");
		}
	}

	private function encode_data(string $text, int $mode): string{
		$bits = '';
		switch($mode){
			case MicroQRData::MODE_NUMERIC:
				$chunks = str_split($text, 3);
				foreach($chunks as $chunk){
					$val = (int)$chunk;
					$bit_len = (strlen($chunk) === 3) ? 10 : ((strlen($chunk) === 2) ? 7 : 4);
					$bits .= str_pad(decbin($val), $bit_len, '0', STR_PAD_LEFT);
				}
				break;
			case MicroQRData::MODE_ALPHANUMERIC:
				$chars = MicroQRData::ALPHANUMERIC_CHARS;
				for($i = 0; $i < strlen($text); $i += 2){
					$c1 = strpos($chars, $text[$i]);
					if($i + 1 < strlen($text)){
						$c2 = strpos($chars, $text[$i + 1]);
						$bits .= str_pad(decbin($c1 * 45 + $c2), 11, '0', STR_PAD_LEFT);
					}else{
						$bits .= str_pad(decbin($c1), 6, '0', STR_PAD_LEFT);
					}
				}
				break;
			case MicroQRData::MODE_BYTE:
				$bytes = unpack('C*', $text);
				foreach($bytes as $byte){
					$bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
				}
				break;
		}
		return $bits;
	}

	private function build_bitstream(string $text, int $mode): string{
		$data_bits = $this->encode_data($text, $mode);
		$char_count = ($mode === MicroQRData::MODE_BYTE) ? strlen($text) : mb_strlen($text);

		$mode_bits_len = MicroQRData::mode_bits($this->version);
		$ec = ($this->version === 1) ? MicroQRData::EC_DETECT : $this->ec_level;
		$char_count_bits_len = MicroQRData::CHAR_COUNT_BITS[$this->version][$mode];

		$bits = '';
		if($mode_bits_len > 0){
			$bits .= str_pad(decbin($mode), $mode_bits_len, '0', STR_PAD_LEFT);
		}
		$bits .= str_pad(decbin($char_count), $char_count_bits_len, '0', STR_PAD_LEFT);
		$bits .= $data_bits;

		$vt = MicroQRData::VERSION_TABLE[$this->version];
		$ec_idx = ($this->version === 1) ? 0 : $this->ec_level - 1;
		$data_codewords = $vt[$ec_idx][2];
		$capacity = $data_codewords * 8;

		// 終端パターン
		$terminator_len = $this->terminator_bits();
		$remaining = $capacity - strlen($bits);
		$bits .= str_repeat('0', min($terminator_len, $remaining));

		// バイト境界パディング
		if(strlen($bits) % 8 !== 0){
			$bits .= str_repeat('0', 8 - (strlen($bits) % 8));
		}

		// パディングコードワード
		$pad = ['11101100', '00010001'];
		$i = 0;
		while(strlen($bits) < $capacity){
			$bits .= $pad[$i % 2];
			$i++;
		}
		return substr($bits, 0, $capacity);
	}

	private function terminator_bits(): int{
		return match($this->version){
			1 => 3,
			2 => 5,
			3 => 7,
			4 => 9,
			default => 3,
		};
	}

	private function bits_to_codewords(string $bits): array{
		$codewords = [];
		for($i = 0; $i < strlen($bits); $i += 8){
			$codewords[] = bindec(substr($bits, $i, 8));
		}
		return $codewords;
	}

	private function add_error_correction(array $data_codewords): array{
		$ec = ($this->version === 1) ? MicroQRData::EC_DETECT : $this->ec_level;
		$ec_idx = ($this->version === 1) ? 0 : $ec - 1;
		$vt = MicroQRData::VERSION_TABLE[$this->version][$ec_idx];
		$ec_count = $vt[1];

		$ec_codewords = ReedSolomon::encode($data_codewords, $ec_count);
		return array_merge($data_codewords, $ec_codewords);
	}

	private function init_matrix(): void{
		$this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
		$this->reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));
	}

	/**
	 * ファインダパターン (左上に1つだけ)
	 */
	private function place_finder_pattern(): void{
		for($r = -1; $r <= 7; $r++){
			for($c = -1; $c <= 7; $c++){
				$rr = $r;
				$cc = $c;
				if($rr < 0 || $rr >= $this->size || $cc < 0 || $cc >= $this->size){
					continue;
				}
				$this->modules[$rr][$cc] = (
					($r >= 0 && $r <= 6 && ($c === 0 || $c === 6)) ||
					($c >= 0 && $c <= 6 && ($r === 0 || $r === 6)) ||
					($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)
				);
				$this->reserved[$rr][$cc] = true;
			}
		}
		// セパレータ (右と下)
		for($i = 0; $i <= 7; $i++){
			if($i < $this->size){
				$this->modules[$i][7] = false;
				$this->reserved[$i][7] = true;
			}
			if($i < $this->size){
				$this->modules[7][$i] = false;
				$this->reserved[7][$i] = true;
			}
		}
	}

	/**
	 * タイミングパターン (横: 行0, 縦: 列0)
	 */
	private function place_timing_patterns(): void{
		for($i = 8; $i < $this->size; $i++){
			// 水平 (行0)
			if(!$this->reserved[0][$i]){
				$this->modules[0][$i] = ($i % 2 === 0);
				$this->reserved[0][$i] = true;
			}
			// 垂直 (列0)
			if(!$this->reserved[$i][0]){
				$this->modules[$i][0] = ($i % 2 === 0);
				$this->reserved[$i][0] = true;
			}
		}
	}

	private function reserve_format_area(): void{
		// 水平: 行8, 列1-8
		for($c = 1; $c <= 8; $c++){
			$this->reserved[8][$c] = true;
		}
		// 垂直: 列8, 行1-8
		for($r = 1; $r <= 8; $r++){
			$this->reserved[$r][8] = true;
		}
	}

	private function place_data(array $codewords): void{
		$bits = '';
		foreach($codewords as $cw){
			$bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
		}

		$bit_index = 0;
		$col = $this->size - 1;
		$upward = true;

		while($col > 0){
			$rows = $upward ? range($this->size - 1, 0, -1) : range(0, $this->size - 1);
			foreach($rows as $row){
				for($c = 0; $c < 2; $c++){
					$cc = $col - $c;
					if($cc < 0 || $this->reserved[$row][$cc]) continue;
					if($bit_index < strlen($bits)){
						$this->modules[$row][$cc] = ($bits[$bit_index] === '1');
						$bit_index++;
					}
				}
			}
			$col -= 2;
			$upward = !$upward;
		}
	}

	private function apply_best_mask(): int{
		$max_masks = ($this->version === 1) ? 4 : 4;
		$best_mask = 0;
		$best_score = -1;

		for($mask = 0; $mask < $max_masks; $mask++){
			$trial = $this->apply_mask($mask);
			$score = $this->evaluate_mask($trial);
			if($score > $best_score){
				$best_score = $score;
				$best_mask = $mask;
				$this->modules = $trial;
			}
		}
		return $best_mask;
	}

	private function apply_mask(int $mask): array{
		$result = [];
		for($r = 0; $r < $this->size; $r++){
			$result[$r] = [];
			for($c = 0; $c < $this->size; $c++){
				$result[$r][$c] = $this->modules[$r][$c];
				if(!$this->reserved[$r][$c] && MicroQRData::mask_condition($mask, $r, $c)){
					$result[$r][$c] = !$result[$r][$c];
				}
			}
		}
		return $result;
	}

	/**
	 * マイクロQRのマスク評価
	 * SUM1とSUM2の組み合わせで最適マスクを選択
	 */
	private function evaluate_mask(array $modules): int{
		$n = $this->size;

		// SUM1: 最下行の左端からの同色連続数
		$sum1 = 0;
		$last = $modules[$n - 1][1];
		for($c = 1; $c < $n; $c++){
			if($modules[$n - 1][$c] === $last){
				$sum1++;
			}else{
				break;
			}
		}

		// SUM2: 最右列の上端からの同色連続数
		$sum2 = 0;
		$last = $modules[1][$n - 1];
		for($r = 1; $r < $n; $r++){
			if($modules[$r][$n - 1] === $last){
				$sum2++;
			}else{
				break;
			}
		}

		return $sum1 * 16 + $sum2;
	}

	private function place_format_info(int $mask): void{
		$ec = ($this->version === 1) ? MicroQRData::EC_DETECT : $this->ec_level;
		$key = $this->version.'_'.$ec;
		$symbol_number = MicroQRData::SYMBOL_NUMBERS[$key];
		$format_bits = MicroQRData::FORMAT_INFO[$symbol_number][$mask];

		// 水平: 行8, 列1-8 (MSBから)
		for($i = 0; $i < 8; $i++){
			$bit = (($format_bits >> (14 - $i)) & 1) === 1;
			$this->modules[8][$i + 1] = $bit;
		}
		// 垂直: 列8, 行7-1 (残り7bit)
		for($i = 0; $i < 7; $i++){
			$bit = (($format_bits >> (6 - $i)) & 1) === 1;
			$this->modules[7 - $i][8] = $bit;
		}
	}
}
