<?php
namespace tokushima\barcode\qrcode;

/**
 * GF(256) 上の Reed-Solomon 誤り訂正
 * 原始多項式: x^8 + x^4 + x^3 + x^2 + 1 (0x11d)
 */
class ReedSolomon{
	private static array $exp = [];
	private static array $log = [];
	private static bool $initialized = false;

	private static function init(): void{
		if(self::$initialized){
			return;
		}
		self::$exp = array_fill(0, 512, 0);
		self::$log = array_fill(0, 256, 0);

		$x = 1;
		for($i = 0; $i < 255; $i++){
			self::$exp[$i] = $x;
			self::$log[$x] = $i;
			$x <<= 1;
			if($x & 0x100){
				$x ^= 0x11d;
			}
		}
		for($i = 255; $i < 512; $i++){
			self::$exp[$i] = self::$exp[$i - 255];
		}
		self::$initialized = true;
	}

	/**
	 * @param int[] $data データコードワード配列
	 * @param int $ec_count 誤り訂正コードワード数
	 * @return int[] 誤り訂正コードワード配列
	 */
	public static function encode(array $data, int $ec_count): array{
		self::init();

		$generator = self::generator_polynomial($ec_count);
		$result = array_merge($data, array_fill(0, $ec_count, 0));

		for($i = 0; $i < count($data); $i++){
			$coef = $result[$i];
			if($coef !== 0){
				for($j = 0; $j < count($generator); $j++){
					$result[$i + $j] ^= self::multiply($generator[$j], $coef);
				}
			}
		}
		return array_slice($result, count($data));
	}

	private static function multiply(int $a, int $b): int{
		if($a === 0 || $b === 0){
			return 0;
		}
		return self::$exp[self::$log[$a] + self::$log[$b]];
	}

	private static function generator_polynomial(int $degree): array{
		self::init();
		$poly = [1];

		for($i = 0; $i < $degree; $i++){
			$new_poly = array_fill(0, count($poly) + 1, 0);
			$factor = self::$exp[$i];

			for($j = 0; $j < count($poly); $j++){
				$new_poly[$j] ^= $poly[$j];
				$new_poly[$j + 1] ^= self::multiply($poly[$j], $factor);
			}
			$poly = $new_poly;
		}
		return $poly;
	}
}
