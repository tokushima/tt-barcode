<?php
namespace tt\barcode;

use tt\barcode\datamatrix\DataMatrixEncoder;
use tt\barcode\datamatrix\SVGRenderer;
use tt\barcode\datamatrix\PNGRenderer;

/**
 * Data Matrix (ECC 200) 生成
 *
 * // シンプルなSVG
 * $svg = DataMatrix::svg('Hello');
 *
 * // PNGファイル保存
 * DataMatrix::png('Hello', '/path/to/output.png');
 *
 * // カスタマイズ
 * DataMatrix::create('Hello')
 *     ->fg_color('#003366')
 *     ->module_size(10)
 *     ->margin(2)
 *     ->save_svg('/path/to/output.svg');
 */
class DataMatrix{
	private array $modules;
	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private int $module_size = 10;
	private int $margin = 2;

	private function __construct(array $modules){
		$this->modules = $modules;
	}

	public static function create(string $text): self{
		return new self(DataMatrixEncoder::encode($text));
	}

	public static function svg(string $text): string{
		return self::create($text)->render_svg();
	}

	public static function png(string $text, string $filepath): void{
		self::create($text)->save_png($filepath);
	}

	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }

	public function size(int $px): self{
		$max_dim = max(count($this->modules), count($this->modules[0]));
		$this->module_size = max(1, (int)floor($px / ($max_dim + $this->margin * 2)));
		return $this;
	}

	public function render_svg(): string{
		return $this->build_svg()->render();
	}

	public function save_svg(string $filepath): self{
		$this->build_svg()->save($filepath);
		return $this;
	}

	public function render_png(): string{
		return $this->build_png()->output();
	}

	public function save_png(string $filepath): self{
		$this->build_png()->save($filepath);
		return $this;
	}

	public function matrix(): array{
		return $this->modules;
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
