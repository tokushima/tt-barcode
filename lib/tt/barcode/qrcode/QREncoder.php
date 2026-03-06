<?php
namespace tt\barcode\qrcode;

/**
 * QRコードエンコーダ
 * 文字列からQRコードのモジュールマトリクスを生成する
 */
class QREncoder{
	private int $version;
	private int $ec_level;
	private int $size;
	private array $modules;
	private array $reserved;

	/**
	 * @return bool[][] QRマトリクス (true=黒モジュール)
	 */
	public static function encode(string $text, int $ec_level = QRData::EC_M, ?int $version = null): array{
		$encoder = new self();
		return $encoder->build($text, $ec_level, $version);
	}

	private function build(string $text, int $ec_level, ?int $version): array{
		$this->ec_level = $ec_level;

		$mode = $this->detect_mode($text);
		$data_bits = $this->encode_data($text, $mode);

		if($version === null){
			$this->version = $this->select_version($data_bits, $mode);
		}else{
			$this->version = $version;
		}
		$this->size = QRData::module_count($this->version);

		$bits = $this->build_bitstream($text, $mode, $data_bits);
		$codewords = $this->bits_to_codewords($bits);
		$final_codewords = $this->add_error_correction($codewords);

		$this->init_matrix();
		$this->place_finder_patterns();
		$this->place_alignment_patterns();
		$this->place_timing_patterns();
		$this->place_dark_module();
		$this->reserve_format_area();
		$this->reserve_version_area();
		$this->place_data($final_codewords);

		$best_mask = $this->apply_best_mask();
		$this->place_format_info($best_mask);
		$this->place_version_info();

		return $this->modules;
	}

	private function detect_mode(string $text): int{
		if(preg_match('/^[0-9]+$/', $text)){
			return QRData::MODE_NUMERIC;
		}
		if(preg_match('/^[0-9A-Z $%*+\-.\/:]+$/', $text)){
			return QRData::MODE_ALPHANUMERIC;
		}
		return QRData::MODE_BYTE;
	}

	private function encode_data(string $text, int $mode): string{
		return match($mode){
			QRData::MODE_NUMERIC => $this->encode_numeric($text),
			QRData::MODE_ALPHANUMERIC => $this->encode_alphanumeric($text),
			QRData::MODE_BYTE => $this->encode_byte($text),
			default => '',
		};
	}

	private function encode_numeric(string $text): string{
		$bits = '';
		foreach(str_split($text, 3) as $chunk){
			$bit_len = match(strlen($chunk)){ 3 => 10, 2 => 7, default => 4 };
			$bits .= str_pad(decbin((int)$chunk), $bit_len, '0', STR_PAD_LEFT);
		}
		return $bits;
	}

	private function encode_alphanumeric(string $text): string{
		$bits = '';
		$chars = QRData::ALPHANUMERIC_CHARS;
		for($i = 0; $i < strlen($text); $i += 2){
			$c1 = strpos($chars, $text[$i]);
			if($i + 1 < strlen($text)){
				$c2 = strpos($chars, $text[$i + 1]);
				$bits .= str_pad(decbin($c1 * 45 + $c2), 11, '0', STR_PAD_LEFT);
			}else{
				$bits .= str_pad(decbin($c1), 6, '0', STR_PAD_LEFT);
			}
		}
		return $bits;
	}

	private function encode_byte(string $text): string{
		$bits = '';
		foreach(unpack('C*', $text) as $byte){
			$bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
		}
		return $bits;
	}

	private function select_version(string $data_bits, int $mode): int{
		for($v = 1; $v <= 40; $v++){
			$char_count_bits = QRData::char_count_bits($mode, $v);
			$total_bits = 4 + $char_count_bits + strlen($data_bits);
			$capacity = QRData::capacity_bytes($v, $this->ec_level) * 8;
			if($total_bits <= $capacity){
				return $v;
			}
		}
		throw new \InvalidArgumentException('Data too large for QR code');
	}

	private function build_bitstream(string $text, int $mode, string $data_bits): string{
		$char_count_bits = QRData::char_count_bits($mode, $this->version);
		$char_count = strlen($text);

		$bits = str_pad(decbin($mode), 4, '0', STR_PAD_LEFT);
		$bits .= str_pad(decbin($char_count), $char_count_bits, '0', STR_PAD_LEFT);
		$bits .= $data_bits;

		$capacity = QRData::capacity_bytes($this->version, $this->ec_level) * 8;
		$remaining = $capacity - strlen($bits);
		$bits .= str_repeat('0', min(4, $remaining));

		if(strlen($bits) % 8 !== 0){
			$bits .= str_repeat('0', 8 - (strlen($bits) % 8));
		}

		$pad = ['11101100', '00010001'];
		$i = 0;
		while(strlen($bits) < $capacity){
			$bits .= $pad[$i % 2];
			$i++;
		}
		return substr($bits, 0, $capacity);
	}

	private function bits_to_codewords(string $bits): array{
		$codewords = [];
		for($i = 0; $i < strlen($bits); $i += 8){
			$codewords[] = bindec(substr($bits, $i, 8));
		}
		return $codewords;
	}

	private function add_error_correction(array $data_codewords): array{
		$info = QRData::VERSION_TABLE[$this->version][$this->ec_level];
		[, $ec_per_block, $g1_blocks, $g1_data, $g2_blocks, $g2_data] = $info;

		$data_blocks = [];
		$ec_blocks = [];
		$offset = 0;

		for($i = 0; $i < $g1_blocks; $i++){
			$block = array_slice($data_codewords, $offset, $g1_data);
			$data_blocks[] = $block;
			$ec_blocks[] = ReedSolomon::encode($block, $ec_per_block);
			$offset += $g1_data;
		}
		for($i = 0; $i < $g2_blocks; $i++){
			$block = array_slice($data_codewords, $offset, $g2_data);
			$data_blocks[] = $block;
			$ec_blocks[] = ReedSolomon::encode($block, $ec_per_block);
			$offset += $g2_data;
		}

		$result = [];
		$max_data = max($g1_data, $g2_data);
		for($i = 0; $i < $max_data; $i++){
			foreach($data_blocks as $block){
				if($i < count($block)){
					$result[] = $block[$i];
				}
			}
		}
		for($i = 0; $i < $ec_per_block; $i++){
			foreach($ec_blocks as $block){
				if($i < count($block)){
					$result[] = $block[$i];
				}
			}
		}
		return $result;
	}

	private function init_matrix(): void{
		$this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
		$this->reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));
	}

	private function place_finder_patterns(): void{
		foreach([[0, 0], [0, $this->size - 7], [$this->size - 7, 0]] as [$row, $col]){
			for($r = -1; $r <= 7; $r++){
				for($c = -1; $c <= 7; $c++){
					$rr = $row + $r;
					$cc = $col + $c;
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
		}
	}

	private function place_alignment_patterns(): void{
		$positions = QRData::ALIGNMENT_POSITIONS[$this->version];
		if(empty($positions)){
			return;
		}
		$count = count($positions);
		for($i = 0; $i < $count; $i++){
			for($j = 0; $j < $count; $j++){
				if(($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)){
					continue;
				}
				for($r = -2; $r <= 2; $r++){
					for($c = -2; $c <= 2; $c++){
						$rr = $positions[$i] + $r;
						$cc = $positions[$j] + $c;
						$this->modules[$rr][$cc] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
						$this->reserved[$rr][$cc] = true;
					}
				}
			}
		}
	}

	private function place_timing_patterns(): void{
		for($i = 8; $i < $this->size - 8; $i++){
			if(!$this->reserved[6][$i]){
				$this->modules[6][$i] = ($i % 2 === 0);
				$this->reserved[6][$i] = true;
			}
			if(!$this->reserved[$i][6]){
				$this->modules[$i][6] = ($i % 2 === 0);
				$this->reserved[$i][6] = true;
			}
		}
	}

	private function place_dark_module(): void{
		$row = 4 * $this->version + 9;
		$this->modules[$row][8] = true;
		$this->reserved[$row][8] = true;
	}

	private function reserve_format_area(): void{
		for($i = 0; $i <= 8; $i++){
			if($i < $this->size) $this->reserved[$i][8] = true;
			if($i < $this->size) $this->reserved[8][$i] = true;
		}
		for($i = $this->size - 8; $i < $this->size; $i++){
			$this->reserved[8][$i] = true;
		}
		for($i = $this->size - 7; $i < $this->size; $i++){
			$this->reserved[$i][8] = true;
		}
	}

	private function reserve_version_area(): void{
		if($this->version < 7) return;
		for($i = 0; $i < 6; $i++){
			for($j = $this->size - 11; $j < $this->size - 8; $j++){
				$this->reserved[$i][$j] = true;
				$this->reserved[$j][$i] = true;
			}
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

		while($col >= 0){
			if($col === 6) $col--;

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
		$best_mask = 0;
		$best_score = PHP_INT_MAX;
		$unmasked = $this->modules;

		for($mask = 0; $mask < 8; $mask++){
			$this->modules = $unmasked;
			$trial = $this->apply_mask($mask);
			$score = $this->evaluate_penalty($trial);
			if($score < $best_score){
				$best_score = $score;
				$best_mask = $mask;
			}
		}
		$this->modules = $unmasked;
		$this->modules = $this->apply_mask($best_mask);
		return $best_mask;
	}

	private function apply_mask(int $mask): array{
		$result = [];
		for($r = 0; $r < $this->size; $r++){
			$result[$r] = [];
			for($c = 0; $c < $this->size; $c++){
				$result[$r][$c] = $this->modules[$r][$c];
				if(!$this->reserved[$r][$c] && $this->mask_condition($mask, $r, $c)){
					$result[$r][$c] = !$result[$r][$c];
				}
			}
		}
		return $result;
	}

	private function mask_condition(int $mask, int $row, int $col): bool{
		return match($mask){
			0 => (($row + $col) % 2 === 0),
			1 => ($row % 2 === 0),
			2 => ($col % 3 === 0),
			3 => (($row + $col) % 3 === 0),
			4 => ((intdiv($row, 2) + intdiv($col, 3)) % 2 === 0),
			5 => (($row * $col) % 2 + ($row * $col) % 3 === 0),
			6 => ((($row * $col) % 2 + ($row * $col) % 3) % 2 === 0),
			7 => ((($row + $col) % 2 + ($row * $col) % 3) % 2 === 0),
			default => false,
		};
	}

	private function evaluate_penalty(array $modules): int{
		$score = 0;
		$n = $this->size;

		// ルール1: 同色5連続以上
		for($r = 0; $r < $n; $r++){
			$count = 1;
			for($c = 1; $c < $n; $c++){
				if($modules[$r][$c] === $modules[$r][$c - 1]){ $count++; }
				else{ if($count >= 5) $score += $count - 2; $count = 1; }
			}
			if($count >= 5) $score += $count - 2;
		}
		for($c = 0; $c < $n; $c++){
			$count = 1;
			for($r = 1; $r < $n; $r++){
				if($modules[$r][$c] === $modules[$r - 1][$c]){ $count++; }
				else{ if($count >= 5) $score += $count - 2; $count = 1; }
			}
			if($count >= 5) $score += $count - 2;
		}

		// ルール2: 2x2ブロック
		for($r = 0; $r < $n - 1; $r++){
			for($c = 0; $c < $n - 1; $c++){
				$v = $modules[$r][$c];
				if($v === $modules[$r][$c + 1] && $v === $modules[$r + 1][$c] && $v === $modules[$r + 1][$c + 1]){
					$score += 3;
				}
			}
		}

		// ルール3: 特定パターン
		$p1 = [true,false,true,true,true,false,true,false,false,false,false];
		$p2 = [false,false,false,false,true,false,true,true,true,false,true];
		for($r = 0; $r < $n; $r++){
			for($c = 0; $c <= $n - 11; $c++){
				$m1 = $m2 = true;
				for($k = 0; $k < 11; $k++){
					if($modules[$r][$c + $k] !== $p1[$k]) $m1 = false;
					if($modules[$r][$c + $k] !== $p2[$k]) $m2 = false;
				}
				if($m1 || $m2) $score += 40;
			}
		}
		for($c = 0; $c < $n; $c++){
			for($r = 0; $r <= $n - 11; $r++){
				$m1 = $m2 = true;
				for($k = 0; $k < 11; $k++){
					if($modules[$r + $k][$c] !== $p1[$k]) $m1 = false;
					if($modules[$r + $k][$c] !== $p2[$k]) $m2 = false;
				}
				if($m1 || $m2) $score += 40;
			}
		}

		// ルール4: 黒比率
		$dark = 0;
		for($r = 0; $r < $n; $r++){
			for($c = 0; $c < $n; $c++){
				if($modules[$r][$c]) $dark++;
			}
		}
		$percent = ($dark * 100) / ($n * $n);
		$score += (intdiv((int)abs($percent - 50), 5)) * 10;

		return $score;
	}

	private function place_format_info(int $mask): void{
		$format_bits = QRData::FORMAT_INFO[$this->ec_level * 8 + $mask];

		$n = $this->size;

		// Copy 1: column 8 (top portion rows 0-8)
		for($i = 0; $i < 6; $i++){
			$this->modules[$i][8] = (($format_bits >> $i) & 1) === 1;
		}
		$this->modules[7][8] = (($format_bits >> 6) & 1) === 1;
		$this->modules[8][8] = (($format_bits >> 7) & 1) === 1;

		// Copy 1: column 8 (bottom portion rows n-7 to n-1)
		for($i = 0; $i < 7; $i++){
			$this->modules[$n - 7 + $i][8] = (($format_bits >> (8 + $i)) & 1) === 1;
		}

		// Copy 2: row 8 (right portion cols n-1 to n-8)
		for($i = 0; $i < 8; $i++){
			$this->modules[8][$n - 1 - $i] = (($format_bits >> $i) & 1) === 1;
		}

		// Copy 2: row 8 (left portion cols 7,5,4,3,2,1,0)
		$this->modules[8][7] = (($format_bits >> 8) & 1) === 1;
		for($i = 0; $i < 6; $i++){
			$this->modules[8][5 - $i] = (($format_bits >> (9 + $i)) & 1) === 1;
		}
	}

	private function place_version_info(): void{
		if($this->version < 7) return;
		$version_bits = QRData::VERSION_INFO[$this->version];

		for($i = 0; $i < 18; $i++){
			$bit = (($version_bits >> $i) & 1) === 1;
			$row = intdiv($i, 3);
			$col = $this->size - 11 + ($i % 3);
			$this->modules[$row][$col] = $bit;
			$this->modules[$col][$row] = $bit;
		}
	}
}
