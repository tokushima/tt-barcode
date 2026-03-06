<?php
namespace tt\barcode\rmqr;

use tt\barcode\qrcode\ReedSolomon;

/**
 * rMQR エンコーダ (ISO/IEC 23941)
 */
class rMQREncoder{

	/**
	 * テキストをエンコードして rMQR モジュールマトリクスを返す
	 * @return array<array<int>> 2次元int配列 (0=白, 1=黒)
	 */
	public static function encode_full(string $text, int $ec_level, ?string $version = null): array{
		$mode = self::select_mode($text);
		$ver = ($version !== null) ? self::find_version($version) : self::select_version($text, $mode, $ec_level);

		$bits = self::build_bitstream($text, $mode, $ver, $ec_level);
		$codewords = self::bits_to_codewords($bits, $ver, $ec_level);
		$blocks = self::split_into_blocks($codewords, $ver, $ec_level);
		$final = self::interleave_blocks($blocks);

		$h = $ver[2];
		$w = $ver[3];

		$matrix = array_fill(0, $h, array_fill(0, $w, null));

		self::place_finder_pattern($matrix, $h, $w);
		self::place_finder_sub_pattern($matrix, $h, $w);
		self::place_corner_finder($matrix, $h, $w);
		self::place_alignment_patterns($matrix, $h, $w);
		self::place_timing_patterns($matrix, $h, $w);
		self::place_format_info($matrix, $ver, $ec_level);
		self::place_data($matrix, $final, $ver);

		for($r = 0; $r < $h; $r++){
			for($c = 0; $c < $w; $c++){
				if($matrix[$r][$c] === null) $matrix[$r][$c] = 0;
			}
		}

		return $matrix;
	}

	private static function select_mode(string $text): int{
		if(preg_match('/^[0-9]+$/', $text)){
			return rMQRData::MODE_NUMERIC;
		}
		if(preg_match('/^[0-9A-Z $%*+\\-.\\/\\:]+$/', $text)){
			return rMQRData::MODE_ALNUM;
		}
		return rMQRData::MODE_BYTE;
	}

	private static function find_version(string $name): array{
		foreach(rMQRData::VERSIONS as $v){
			if($v[0] === $name){
				return $v;
			}
		}
		throw new \InvalidArgumentException('Unknown rMQR version: ' . $name);
	}

	private static function select_version(string $text, int $mode, int $ec_level): array{
		foreach(rMQRData::VERSIONS as $v){
			$data_bits_idx = ($ec_level === 0) ? 10 : 11;
			$capacity_bits = $v[$data_bits_idx];

			$cci_idx = match($mode){
				rMQRData::MODE_NUMERIC => 6,
				rMQRData::MODE_ALNUM => 7,
				rMQRData::MODE_BYTE => 8,
				rMQRData::MODE_KANJI => 9,
			};
			$cci_bits = $v[$cci_idx];

			$payload_bits = self::compute_payload_bits($text, $mode, $cci_bits);
			if($payload_bits <= $capacity_bits){
				return $v;
			}
		}
		throw new \OverflowException('Data too long for rMQR');
	}

	private static function compute_payload_bits(string $text, int $mode, int $cci_bits): int{
		$len = strlen($text);
		$bits = 3 + $cci_bits;

		switch($mode){
			case rMQRData::MODE_NUMERIC:
				$bits += intdiv($len, 3) * 10;
				$rem = $len % 3;
				if($rem === 2) $bits += 7;
				elseif($rem === 1) $bits += 4;
				break;
			case rMQRData::MODE_ALNUM:
				$bits += intdiv($len, 2) * 11;
				if($len % 2 === 1) $bits += 6;
				break;
			case rMQRData::MODE_BYTE:
				$bits += $len * 8;
				break;
		}
		return $bits;
	}

	private static function build_bitstream(string $text, int $mode, array $ver, int $ec_level): string{
		$cci_idx = match($mode){
			rMQRData::MODE_NUMERIC => 6,
			rMQRData::MODE_ALNUM => 7,
			rMQRData::MODE_BYTE => 8,
			rMQRData::MODE_KANJI => 9,
		};
		$cci_bits = $ver[$cci_idx];
		$data_bits_idx = ($ec_level === 0) ? 10 : 11;
		$capacity = $ver[$data_bits_idx];

		$bits = str_pad(decbin($mode), 3, '0', STR_PAD_LEFT);
		$bits .= str_pad(decbin(strlen($text)), $cci_bits, '0', STR_PAD_LEFT);

		switch($mode){
			case rMQRData::MODE_NUMERIC:
				$bits .= self::encode_numeric($text);
				break;
			case rMQRData::MODE_ALNUM:
				$bits .= self::encode_alnum($text);
				break;
			case rMQRData::MODE_BYTE:
				$bits .= self::encode_byte($text);
				break;
		}

		// ターミネータ (3ビット)
		$remaining = $capacity - strlen($bits);
		if($remaining >= 3){
			$bits .= '000';
		}elseif($remaining > 0){
			$bits .= str_repeat('0', $remaining);
		}

		return $bits;
	}

	private static function encode_numeric(string $text): string{
		$bits = '';
		$len = strlen($text);
		for($i = 0; $i < $len; $i += 3){
			$group = substr($text, $i, 3);
			$val = (int)$group;
			$bits .= str_pad(decbin($val), strlen($group) === 3 ? 10 : (strlen($group) === 2 ? 7 : 4), '0', STR_PAD_LEFT);
		}
		return $bits;
	}

	private static function encode_alnum(string $text): string{
		$bits = '';
		$len = strlen($text);
		for($i = 0; $i < $len; $i += 2){
			if($i + 1 < $len){
				$val = strpos(rMQRData::ALNUM_TABLE, $text[$i]) * 45 + strpos(rMQRData::ALNUM_TABLE, $text[$i + 1]);
				$bits .= str_pad(decbin($val), 11, '0', STR_PAD_LEFT);
			}else{
				$bits .= str_pad(decbin(strpos(rMQRData::ALNUM_TABLE, $text[$i])), 6, '0', STR_PAD_LEFT);
			}
		}
		return $bits;
	}

	private static function encode_byte(string $text): string{
		$bits = '';
		for($i = 0; $i < strlen($text); $i++){
			$bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
		}
		return $bits;
	}

	private static function bits_to_codewords(string $bits, array $ver, int $ec_level): array{
		if(strlen($bits) % 8 !== 0){
			$bits .= str_repeat('0', 8 - (strlen($bits) % 8));
		}

		$codewords = [];
		for($i = 0; $i < strlen($bits); $i += 8){
			$codewords[] = bindec(substr($bits, $i, 8));
		}

		$blocks_def = ($ec_level === 0) ? $ver[12] : $ver[13];
		$data_count = 0;
		foreach($blocks_def as $bd){
			$data_count += $bd[0] * $bd[2];
		}

		$pad = [0xEC, 0x11];
		$i = 0;
		while(count($codewords) < $data_count){
			$codewords[] = $pad[$i % 2];
			$i++;
		}

		return $codewords;
	}

	private static function split_into_blocks(array $codewords, array $ver, int $ec_level): array{
		$blocks_def = ($ec_level === 0) ? $ver[12] : $ver[13];
		$blocks = [];
		$idx = 0;

		foreach($blocks_def as $bd){
			for($b = 0; $b < $bd[0]; $b++){
				$k = $bd[2];
				$ec_count = $bd[1] - $k;
				$data = array_slice($codewords, $idx, $k);
				$blocks[] = ['data' => $data, 'ec' => ReedSolomon::encode($data, $ec_count)];
				$idx += $k;
			}
		}
		return $blocks;
	}

	private static function interleave_blocks(array $blocks): array{
		$final = [];

		$max_data = max(array_map(fn($b) => count($b['data']), $blocks));
		for($i = 0; $i < $max_data; $i++){
			foreach($blocks as $b){
				if($i < count($b['data'])){
					$final[] = $b['data'][$i];
				}
			}
		}

		$max_ec = max(array_map(fn($b) => count($b['ec']), $blocks));
		for($i = 0; $i < $max_ec; $i++){
			foreach($blocks as $b){
				if($i < count($b['ec'])){
					$final[] = $b['ec'][$i];
				}
			}
		}

		return $final;
	}

	private static function place_finder_pattern(array &$matrix, int $h, int $w): void{
		for($r = 0; $r < 7; $r++){
			for($c = 0; $c < 7; $c++){
				if($r === 0 || $r === 6 || $c === 0 || $c === 6){
					$matrix[$r][$c] = 1;
				}else{
					$matrix[$r][$c] = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4) ? 1 : 0;
				}
			}
		}

		for($r = 0; $r < min(8, $h); $r++){
			$matrix[$r][7] = 0;
		}
		if($h >= 9){
			for($c = 0; $c < 8; $c++){
				$matrix[7][$c] = 0;
			}
		}
	}

	private static function place_finder_sub_pattern(array &$matrix, int $h, int $w): void{
		for($r = 0; $r < 5; $r++){
			for($c = 0; $c < 5; $c++){
				$is_border = ($r === 0 || $r === 4 || $c === 0 || $c === 4);
				$is_center = ($r === 2 && $c === 2);
				$matrix[$h - 5 + $r][$w - 5 + $c] = ($is_border || $is_center) ? 1 : 0;
			}
		}
	}

	private static function place_corner_finder(array &$matrix, int $h, int $w): void{
		$matrix[$h - 1][0] = 1;
		$matrix[$h - 1][1] = 1;
		$matrix[$h - 1][2] = 1;

		if($h >= 11){
			$matrix[$h - 2][0] = 1;
			$matrix[$h - 2][1] = 0;
		}

		$matrix[0][$w - 1] = 1;
		$matrix[0][$w - 2] = 1;
		$matrix[1][$w - 1] = 1;
		$matrix[1][$w - 2] = 0;
	}

	private static function place_alignment_patterns(array &$matrix, int $h, int $w): void{
		$coords = rMQRData::ALIGNMENT_COORDS[$w] ?? [];
		foreach($coords as $cx){
			for($r = 0; $r < 3; $r++){
				for($c = 0; $c < 3; $c++){
					$is_border = ($r === 0 || $r === 2 || $c === 0 || $c === 2);
					$matrix[$r][$cx - 1 + $c] = $is_border ? 1 : 0;
					$matrix[$h - 3 + $r][$cx - 1 + $c] = $is_border ? 1 : 0;
				}
			}
		}
	}

	private static function place_timing_patterns(array &$matrix, int $h, int $w): void{
		$coords = rMQRData::ALIGNMENT_COORDS[$w] ?? [];

		for($c = 0; $c < $w; $c++){
			$color = (($c + 1) % 2 === 1) ? 1 : 0;
			if($matrix[0][$c] === null) $matrix[0][$c] = $color;
			if($matrix[$h - 1][$c] === null) $matrix[$h - 1][$c] = $color;
		}

		$v_cols = array_merge([0, $w - 1], $coords);
		foreach($v_cols as $c){
			for($r = 0; $r < $h; $r++){
				$color = (($r + 1) % 2 === 1) ? 1 : 0;
				if($matrix[$r][$c] === null) $matrix[$r][$c] = $color;
			}
		}
	}

	private static function compute_format_info(array $ver, int $ec_level): int{
		$data = $ver[1];
		if($ec_level === 1){
			$data |= (1 << 5);
		}

		$shifted = $data << 12;
		$tmp = $shifted;
		while(true){
			$msb = self::msb_position($tmp);
			if($msb < 13) break;
			$tmp ^= (rMQRData::FORMAT_BCH_POLY << ($msb - 13));
		}
		return $shifted | $tmp;
	}

	private static function msb_position(int $val): int{
		$pos = 0;
		while($val > 0){
			$pos++;
			$val >>= 1;
		}
		return $pos;
	}

	private static function place_format_info(array &$matrix, array $ver, int $ec_level): void{
		$format = self::compute_format_info($ver, $ec_level);
		$h = $ver[2];
		$w = $ver[3];

		// Finder pattern side
		$masked = $format ^ rMQRData::FORMAT_MASK_FINDER;
		for($n = 0; $n < 18; $n++){
			$matrix[1 + ($n % 5)][8 + intdiv($n, 5)] = ($masked >> $n) & 1;
		}

		// Finder sub pattern side
		$masked = $format ^ rMQRData::FORMAT_MASK_SUB;
		for($n = 0; $n < 15; $n++){
			$matrix[$h - 6 + ($n % 5)][$w - 8 + intdiv($n, 5)] = ($masked >> $n) & 1;
		}
		$matrix[$h - 6][$w - 5] = ($masked >> 15) & 1;
		$matrix[$h - 6][$w - 4] = ($masked >> 16) & 1;
		$matrix[$h - 6][$w - 3] = ($masked >> 17) & 1;
	}

	private static function place_data(array &$matrix, array $final, array $ver): void{
		$h = $ver[2];
		$w = $ver[3];
		$remainder = $ver[5];

		$bits = '';
		foreach($final as $cw){
			$bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
		}
		$bits .= str_repeat('0', $remainder);

		$bit_idx = 0;
		$dy = -1;
		$cx = $w - 2;
		$cy = $h - 6;

		$mask_area = array_fill(0, $h, array_fill(0, $w, false));

		while($bit_idx < strlen($bits)){
			for($x_offset = 0; $x_offset <= 1; $x_offset++){
				$x = $cx - $x_offset;
				if($x < 0 || $x >= $w || $cy < 0 || $cy >= $h) continue;
				if($matrix[$cy][$x] !== null) continue;

				if($bit_idx < strlen($bits)){
					$matrix[$cy][$x] = (int)$bits[$bit_idx];
					$mask_area[$cy][$x] = true;
					$bit_idx++;
				}
			}

			if($dy < 0 && $cy <= 1){
				$cx -= 2;
				$dy = 1;
			}elseif($dy > 0 && $cy >= $h - 2){
				$cx -= 2;
				$dy = -1;
			}else{
				$cy += $dy;
			}
		}

		self::apply_mask($matrix, $mask_area, $h, $w);
	}

	private static function apply_mask(array &$matrix, array $mask_area, int $h, int $w): void{
		for($r = 0; $r < $h; $r++){
			for($c = 0; $c < $w; $c++){
				if(!$mask_area[$r][$c]) continue;
				if((intdiv($r, 2) + intdiv($c, 3)) % 2 === 0){
					$matrix[$r][$c] ^= 1;
				}
			}
		}
	}
}
