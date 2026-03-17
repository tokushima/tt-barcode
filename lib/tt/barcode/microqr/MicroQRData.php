<?php
namespace tt\barcode\microqr;

/**
 * マイクロQRコード仕様データテーブル (ISO/IEC 18004 Annex)
 *
 * バージョン M1-M4, サイズ 11x11 - 17x17
 */
class MicroQRData{
	const EC_DETECT = 0; // M1のみ: 誤り検出のみ
	const EC_L = 1;
	const EC_M = 2;
	const EC_Q = 3;

	const MODE_NUMERIC = 0;
	const MODE_ALPHANUMERIC = 1;
	const MODE_BYTE = 2;
	const MODE_KANJI = 3;

	const ALPHANUMERIC_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

	/**
	 * バージョンごとのモジュール数
	 */
	public static function module_count(int $version): int{
		return $version * 2 + 9;
	}

	/**
	 * モード指示子のビット長 (バージョンによって異なる)
	 * M1:0, M2:1, M3:2, M4:3
	 */
	public static function mode_bits(int $version): int{
		return $version - 1;
	}

	/**
	 * 文字数指示子のビット長
	 * [version][mode] => bits
	 */
	const CHAR_COUNT_BITS = [
		1 => [3, 0, 0, 0],  // M1: 数字のみ
		2 => [4, 3, 0, 0],  // M2: 数字,英数字
		3 => [5, 4, 4, 3],  // M3: 数字,英数字,バイト,漢字
		4 => [6, 5, 5, 4],  // M4: 数字,英数字,バイト,漢字
	];

	/**
	 * バージョン/誤り訂正レベルごとのデータ容量
	 * [version][ec_level] => [total_codewords, ec_codewords, data_codewords]
	 */
	const VERSION_TABLE = [
		1 => [
			[5, 2, 3],    // EC_DETECT (誤り検出のみ)
		],
		2 => [
			[10, 5, 5],   // EC_L
			[10, 6, 4],   // EC_M
		],
		3 => [
			[17, 6, 11],  // EC_L
			[17, 8, 9],   // EC_M
		],
		4 => [
			[24, 8, 16],  // EC_L
			[24, 10, 14], // EC_M
			[24, 14, 10], // EC_Q
		],
	];

	/**
	 * バージョンとECレベルの組み合わせでサポートされるモード
	 */
	const SUPPORTED_MODES = [
		1 => [self::EC_DETECT => [self::MODE_NUMERIC]],
		2 => [
			self::EC_L => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC],
			self::EC_M => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC],
		],
		3 => [
			self::EC_L => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC, self::MODE_BYTE, self::MODE_KANJI],
			self::EC_M => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC, self::MODE_BYTE, self::MODE_KANJI],
		],
		4 => [
			self::EC_L => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC, self::MODE_BYTE, self::MODE_KANJI],
			self::EC_M => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC, self::MODE_BYTE, self::MODE_KANJI],
			self::EC_Q => [self::MODE_NUMERIC, self::MODE_ALPHANUMERIC, self::MODE_BYTE, self::MODE_KANJI],
		],
	];

	/**
	 * データ容量（文字数上限）
	 * [version][ec_level][mode] => max_chars
	 */
	const CAPACITY = [
		1 => [self::EC_DETECT => [5, 0, 0, 0]],
		2 => [
			self::EC_L => [10, 6, 0, 0],
			self::EC_M => [8, 5, 0, 0],
		],
		3 => [
			self::EC_L => [23, 14, 9, 6],
			self::EC_M => [18, 11, 7, 4],
		],
		4 => [
			self::EC_L => [35, 21, 15, 9],
			self::EC_M => [30, 18, 13, 8],
			self::EC_Q => [21, 12, 9, 5],
		],
	];

	/**
	 * フォーマット情報 (15bit BCH符号化済み)
	 * インデックス: symbol_number * 4 + data_mask_pattern
	 * symbol_number: version/ECの組み合わせ (0-7)
	 *   0: M1-Detect, 1: M2-L, 2: M2-M, 3: M3-L, 4: M3-M, 5: M4-L, 6: M4-M, 7: M4-Q
	 */
	const SYMBOL_NUMBERS = [
		'1_0' => 0, // M1, EC_DETECT
		'2_1' => 1, // M2, EC_L
		'2_2' => 2, // M2, EC_M
		'3_1' => 3, // M3, EC_L
		'3_2' => 4, // M3, EC_M
		'4_1' => 5, // M4, EC_L
		'4_2' => 6, // M4, EC_M
		'4_3' => 7, // M4, EC_Q
	];

	/**
	 * フォーマット情報ビット列 (15bit)
	 * [symbol_number][mask_pattern] => 15bit value
	 */
	const FORMAT_INFO = [
		// symbol_number 0 (M1-Detect), mask 0-3
		[0x4445, 0x4172, 0x4E2B, 0x4B1C],
		// symbol_number 1 (M2-L)
		[0x55AE, 0x5099, 0x5FC0, 0x5AF7],
		// symbol_number 2 (M2-M)
		[0x6793, 0x62A4, 0x6DFD, 0x68CA],
		// symbol_number 3 (M3-L)
		[0x7678, 0x734F, 0x7C16, 0x7921],
		// symbol_number 4 (M3-M)
		[0x06DE, 0x03E9, 0x0CB0, 0x0987],
		// symbol_number 5 (M4-L)
		[0x1735, 0x1202, 0x1D5B, 0x186C],
		// symbol_number 6 (M4-M)
		[0x2508, 0x203F, 0x2F66, 0x2A51],
		// symbol_number 7 (M4-Q)
		[0x34E3, 0x31D4, 0x3E8D, 0x3BBA],
	];

	/**
	 * マスクパターン条件 (マイクロQRは4種類)
	 */
	public static function mask_condition(int $mask, int $row, int $col): bool{
		return match($mask){
			0 => ($row % 2 === 0),
			1 => ((intdiv($row, 2) + intdiv($col, 3)) % 2 === 0),
			2 => ((($row * $col) % 2 + ($row * $col) % 3) % 2 === 0),
			3 => ((($row + $col) % 2 + ($row * $col) % 3) % 2 === 0),
			default => false,
		};
	}
}
