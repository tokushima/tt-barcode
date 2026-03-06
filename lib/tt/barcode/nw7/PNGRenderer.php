<?php
namespace tt\barcode\nw7;

/**
 * NW-7バーコードをPNGとしてレンダリングする (GDライブラリ使用)
 */
class PNGRenderer{
	private array $bars;
	private string $text;

	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private int $module_width = 2;
	private int $height = 80;
	private int $margin = 10;
	private float $wide_ratio = 2.5;
	private bool $show_text = true;
	private int $font_size = 4; // GD built-in font (1-5)

	public function __construct(array $bars, string $text){
		$this->bars = $bars;
		$this->text = $text;
	}

	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function module_width(int $px): self{ $this->module_width = $px; return $this; }
	public function height(int $px): self{ $this->height = $px; return $this; }
	public function margin(int $px): self{ $this->margin = $px; return $this; }
	public function wide_ratio(float $ratio): self{ $this->wide_ratio = $ratio; return $this; }
	public function show_text(bool $show): self{ $this->show_text = $show; return $this; }
	public function font_size(int $size): self{ $this->font_size = max(1, min(5, $size)); return $this; }

	public function render(): \GdImage{
		$mw = $this->module_width;
		$wr = $this->wide_ratio;

		$barcode_width = 0;
		foreach($this->bars as [$is_bar, $width]){
			$barcode_width += ($width === 1) ? $mw : (int)($mw * $wr);
		}

		$text_height = $this->show_text ? imagefontheight($this->font_size) + 6 : 0;
		$total_w = (int)($barcode_width + $this->margin * 2);
		$total_h = $this->height + $this->margin * 2 + $text_height;

		$img = imagecreatetruecolor($total_w, $total_h);
		$bg = $this->alloc($img, $this->bg_color);
		$fg = $this->alloc($img, $this->fg_color);
		imagefilledrectangle($img, 0, 0, $total_w - 1, $total_h - 1, $bg);

		$x = $this->margin;
		foreach($this->bars as [$is_bar, $width]){
			$w = ($width === 1) ? $mw : (int)($mw * $wr);
			if($is_bar){
				imagefilledrectangle($img, $x, $this->margin, $x + $w - 1, $this->margin + $this->height - 1, $fg);
			}
			$x += $w;
		}

		if($this->show_text){
			$text_w = imagefontwidth($this->font_size) * strlen($this->text);
			$text_x = $this->margin + (int)(($barcode_width - $text_w) / 2);
			$text_y = $this->margin + $this->height + 4;
			imagestring($img, $this->font_size, $text_x, $text_y, $this->text, $fg);
		}

		return $img;
	}

	public function save(string $filepath): void{
		$dir = dirname($filepath);
		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		$img = $this->render();
		imagepng($img, $filepath);
		imagedestroy($img);
	}

	public function output(): string{
		$img = $this->render();
		ob_start();
		imagepng($img);
		imagedestroy($img);
		return ob_get_clean();
	}

	private function alloc(\GdImage $img, string $hex): int{
		$hex = ltrim($hex, '#');
		return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
	}
}
