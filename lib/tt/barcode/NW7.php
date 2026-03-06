<?php
namespace tt\barcode;

use tt\barcode\nw7\NW7Encoder;
use tt\barcode\nw7\SVGRenderer;
use tt\barcode\nw7\PNGRenderer;

/**
 * NW-7 (Codabar) バーコード生成
 *
 * // シンプルなSVG
 * $svg = NW7::svg('12345');
 *
 * // PNGファイル保存
 * NW7::png('12345', '/path/to/output.png');
 *
 * // カスタマイズ
 * NW7::create('12345')
 *     ->fg_color('#003366')
 *     ->height(100)
 *     ->margin(20)
 *     ->show_text(false)
 *     ->save_svg('/path/to/output.svg');
 *
 * // スタート/ストップキャラクタ指定
 * NW7::create('12345', 'A', 'B')
 *     ->save_png('/path/to/output.png');
 *
 * 使用可能文字: 0-9, -, $, :, /, ., +
 * スタート/ストップ: A, B, C, D
 */
class NW7{
	private array $bars;
	private string $text;

	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private int $module_width = 2;
	private int $height = 80;
	private int $margin = 10;
	private float $wide_ratio = 2.5;
	private bool $show_text = true;
	private int $font_size = 14;

	private function __construct(array $bars, string $text){
		$this->bars = $bars;
		$this->text = $text;
	}

	/**
	 * NW-7インスタンスを生成 (ビルダーパターン)
	 */
	public static function create(string $data, string $start = 'A', string $stop = 'A'): self{
		$result = NW7Encoder::encode($data, $start, $stop);
		return new self($result['bars'], $result['text']);
	}

	/**
	 * SVG文字列を返す (ショートカット)
	 */
	public static function svg(string $data, string $start = 'A', string $stop = 'A'): string{
		return self::create($data, $start, $stop)->render_svg();
	}

	/**
	 * PNGファイルに保存 (ショートカット)
	 */
	public static function png(string $data, string $filepath, string $start = 'A', string $stop = 'A'): void{
		self::create($data, $start, $stop)->save_png($filepath);
	}

	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function module_width(int $px): self{ $this->module_width = $px; return $this; }
	public function height(int $px): self{ $this->height = $px; return $this; }
	public function margin(int $px): self{ $this->margin = $px; return $this; }
	public function wide_ratio(float $ratio): self{ $this->wide_ratio = $ratio; return $this; }
	public function show_text(bool $show): self{ $this->show_text = $show; return $this; }
	public function font_size(int $size): self{ $this->font_size = $size; return $this; }

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
	 * バーパターン配列を返す
	 * @return array<array{bool, int}> [is_bar, width_units]
	 */
	public function bars(): array{
		return $this->bars;
	}

	/**
	 * エンコード済みテキスト (スタート/ストップ込み)
	 */
	public function text(): string{
		return $this->text;
	}

	private function build_svg(): SVGRenderer{
		$r = new SVGRenderer($this->bars, $this->text);
		$r->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_width($this->module_width)->height($this->height)
			->margin($this->margin)->wide_ratio($this->wide_ratio)
			->show_text($this->show_text)->font_size($this->font_size);
		return $r;
	}

	private function build_png(): PNGRenderer{
		$r = new PNGRenderer($this->bars, $this->text);
		$r->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_width($this->module_width)->height($this->height)
			->margin($this->margin)->wide_ratio($this->wide_ratio)
			->show_text($this->show_text);
		return $r;
	}
}
