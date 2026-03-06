<?php
namespace tt\barcode;

use tt\barcode\microqr\MicroQRData;
use tt\barcode\microqr\MicroQREncoder;
use tt\barcode\microqr\SVGRenderer;
use tt\barcode\microqr\PNGRenderer;

/**
 * マイクロQRコード生成
 *
 * // シンプルなSVG
 * $svg = MicroQR::svg('12345');
 *
 * // PNGファイル保存
 * MicroQR::png('12345', '/path/to/output.png');
 *
 * // カスタマイズ
 * MicroQR::create('HELLO')
 *     ->fg_color('#003366')
 *     ->module_size(15)
 *     ->margin(3)
 *     ->save_svg('/path/to/output.svg');
 *
 * バージョン: M1 (11x11) - M4 (17x17)
 * 誤り訂正:
 *   MicroQR::EC_DETECT (M1のみ), MicroQR::EC_L, MicroQR::EC_M, MicroQR::EC_Q (M4のみ)
 */
class MicroQR{
	const int EC_DETECT = MicroQRData::EC_DETECT;
	const int EC_L = MicroQRData::EC_L;
	const int EC_M = MicroQRData::EC_M;
	const int EC_Q = MicroQRData::EC_Q;

	private array $modules;
	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private int $module_size = 10;
	private int $margin = 2;

	private function __construct(array $modules){
		$this->modules = $modules;
	}

	/**
	 * マイクロQRコードインスタンスを生成 (ビルダーパターン)
	 */
	public static function create(string $text, int $ec_level = MicroQRData::EC_L, ?int $version = null): self{
		return new self(MicroQREncoder::encode($text, $ec_level, $version));
	}

	/**
	 * SVG文字列を返す (ショートカット)
	 */
	public static function svg(string $text, int $ec_level = MicroQRData::EC_L): string{
		return self::create($text, $ec_level)->render_svg();
	}

	/**
	 * PNGファイルに保存 (ショートカット)
	 */
	public static function png(string $text, string $filepath, int $ec_level = MicroQRData::EC_L): void{
		self::create($text, $ec_level)->save_png($filepath);
	}

	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }

	/**
	 * 出力サイズをピクセルで指定（module_sizeを自動計算）
	 */
	public function size(int $px): self{
		$this->module_size = max(1, (int)floor($px / (count($this->modules) + $this->margin * 2)));
		return $this;
	}

	/**
	 * SVG文字列を返す
	 */
	public function render_svg(): string{
		return $this->build_svg()->render();
	}

	/**
	 * SVGファイルに保存
	 */
	public function save_svg(string $filepath): self{
		$this->build_svg()->save($filepath);
		return $this;
	}

	/**
	 * PNGバイナリを返す
	 */
	public function render_png(): string{
		return $this->build_png()->output();
	}

	/**
	 * PNGファイルに保存
	 */
	public function save_png(string $filepath): self{
		$this->build_png()->save($filepath);
		return $this;
	}

	/**
	 * マトリクス (bool[][])
	 */
	public function matrix(): array{
		return $this->modules;
	}

	/**
	 * モジュール数 (1辺)
	 */
	public function module_count(): int{
		return count($this->modules);
	}

	private function build_svg(): SVGRenderer{
		$r = new SVGRenderer($this->modules);
		$r->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_size($this->module_size)->margin($this->margin);
		return $r;
	}

	private function build_png(): PNGRenderer{
		$r = new PNGRenderer($this->modules);
		$r->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_size($this->module_size)->margin($this->margin);
		return $r;
	}
}
