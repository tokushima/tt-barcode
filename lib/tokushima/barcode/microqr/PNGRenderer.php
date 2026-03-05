<?php
namespace tokushima\barcode\microqr;

/**
 * マイクロQRコードをPNGとしてレンダリングする (GDライブラリ使用)
 */
class PNGRenderer{
	private array $modules;
	private int $size;

	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private int $module_size = 10;
	private int $margin = 2;

	public function __construct(array $modules){
		$this->modules = $modules;
		$this->size = count($modules);
	}

	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }

	public function render(): \GdImage{
		$ms = $this->module_size;
		$total = ($this->size + $this->margin * 2) * $ms;

		$img = imagecreatetruecolor($total, $total);
		imagealphablending($img, true);
		imagesavealpha($img, true);

		$bg = $this->alloc($img, $this->bg_color);
		$fg = $this->alloc($img, $this->fg_color);
		imagefilledrectangle($img, 0, 0, $total - 1, $total - 1, $bg);

		for($r = 0; $r < $this->size; $r++){
			for($c = 0; $c < $this->size; $c++){
				if(!$this->modules[$r][$c]) continue;
				$x = ($c + $this->margin) * $ms;
				$y = ($r + $this->margin) * $ms;
				imagefilledrectangle($img, $x, $y, $x + $ms - 1, $y + $ms - 1, $fg);
			}
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
