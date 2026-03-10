<?php
namespace tt\barcode\qrcode;

/**
 * QRコードマトリクスをPNGとしてレンダリングする (GDライブラリ使用)
 *
 * module_shape: 'square' (デフォルト), 'dots'
 * finder_shape: 'square' (デフォルト), 'modern'
 */
class PNGRenderer{
	private array $modules;
	private int $size;

	private string $module_shape = 'square';
	private string $finder_shape = 'square';
	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private ?string $finder_color = null;
	private int $module_size = 10;
	private int $margin = 4;
	private float $dot_scale = 0.85;
	private int $alpha = 100;
	private ?string $icon_path = null;
	private float $icon_scale = 0.2;
	private ?string $bg_image_path = null;
	private int $bg_image_alpha = 50;

	public function __construct(array $modules){
		$this->modules = $modules;
		$this->size = count($modules);
	}

	public function module_shape(string $shape): self{ $this->module_shape = $shape; return $this; }
	public function finder_shape(string $shape): self{ $this->finder_shape = $shape; return $this; }
	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function finder_color(string $color): self{ $this->finder_color = $color; return $this; }
	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }
	public function dot_scale(float $scale): self{ $this->dot_scale = max(0.1, min(1.0, $scale)); return $this; }
	public function alpha(int $percent): self{ $this->alpha = max(0, min(100, $percent)); return $this; }

	public function icon_path(string $path, float $scale = 0.2): self{
		$this->icon_path = $path;
		$this->icon_scale = $scale;
		return $this;
	}

	public function bg_image(string $path, int $alpha = 50): self{
		$this->bg_image_path = $path;
		$this->bg_image_alpha = max(0, min(100, $alpha));
		return $this;
	}

	public function render(): \GdImage{
		$ms = $this->module_size;
		$total = ($this->size + $this->margin * 2) * $ms;

		$img = imagecreatetruecolor($total, $total);
		imagealphablending($img, true);
		imagesavealpha($img, true);

		$bg = $this->alloc($img, $this->bg_color);
		imagefilledrectangle($img, 0, 0, $total - 1, $total - 1, $bg);

		if($this->bg_image_path !== null){
			$this->draw_bg_image($img, $total);
		}

		if($this->alpha < 100){
			$gd_alpha = (int)(127 * (1 - $this->alpha / 100));
			$fg = $this->alloc_alpha($img, $this->fg_color, $gd_alpha);
			$fc = $this->finder_color ? $this->alloc_alpha($img, $this->finder_color, $gd_alpha) : $fg;
		}else{
			$fg = $this->alloc($img, $this->fg_color);
			$fc = $this->finder_color ? $this->alloc($img, $this->finder_color) : $fg;
		}
		$is_dots = ($this->module_shape === 'dots');

		if($this->bg_image_path !== null){
			$white_alpha = $this->alloc_alpha($img, $this->bg_color, (int)(127 * (1 - $this->bg_image_alpha / 100)));
			for($r = 0; $r < $this->size; $r++){
				for($c = 0; $c < $this->size; $c++){
					if($this->modules[$r][$c]) continue;
					$x = ($c + $this->margin) * $ms;
					$y = ($r + $this->margin) * $ms;
					if($is_dots){
						$cx = (int)($x + $ms / 2);
						$cy = (int)($y + $ms / 2);
						$radius = (int)(($ms / 2) * $this->dot_scale);
						imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $white_alpha);
					}else{
						imagefilledrectangle($img, $x, $y, $x + $ms - 1, $y + $ms - 1, $white_alpha);
					}
				}
			}
		}

		$custom_finder = ($this->finder_shape !== 'square');

		if($custom_finder){
			$this->draw_modern_finders($img, $ms, $fc, $bg);
		}

		for($r = 0; $r < $this->size; $r++){
			for($c = 0; $c < $this->size; $c++){
				if(!$this->modules[$r][$c]) continue;

				$is_finder = $this->is_finder_module($r, $c);
				if($is_finder && $custom_finder) continue;

				$color = $is_finder ? $fc : $fg;
				$x = ($c + $this->margin) * $ms;
				$y = ($r + $this->margin) * $ms;

				if($is_dots){
					$cx = (int)($x + $ms / 2);
					$cy = (int)($y + $ms / 2);
					$radius = (int)(($ms / 2) * $this->dot_scale);
					imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $color);
				}else{
					imagefilledrectangle($img, $x, $y, $x + $ms - 1, $y + $ms - 1, $color);
				}
			}
		}

		$this->draw_icon($img, $total, $ms);
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

	private function draw_square_finders(\GdImage $img, int $ms, int $color, int $bg): void{
		foreach([[0, 0], [0, $this->size - 7], [$this->size - 7, 0]] as [$pr, $pc]){
			$ox = ($pc + $this->margin) * $ms;
			$oy = ($pr + $this->margin) * $ms;
			$outer = 7 * $ms;
			imagefilledrectangle($img, $ox, $oy, $ox + $outer - 1, $oy + $outer - 1, $color);
			imagefilledrectangle($img, $ox + $ms, $oy + $ms, $ox + 6 * $ms - 1, $oy + 6 * $ms - 1, $bg);
			imagefilledrectangle($img, $ox + 2 * $ms, $oy + 2 * $ms, $ox + 5 * $ms - 1, $oy + 5 * $ms - 1, $color);
		}
	}

	private function draw_modern_finders(\GdImage $img, int $ms, int $color, int $bg): void{
		foreach([[0, 0], [0, $this->size - 7], [$this->size - 7, 0]] as [$pr, $pc]){
			$ox = ($pc + $this->margin) * $ms;
			$oy = ($pr + $this->margin) * $ms;
			$outer = 7 * $ms;
			// 角丸四角で描画 (GDにはrounded rectがないため矩形+楕円で近似)
			imagefilledrectangle($img, $ox, $oy, $ox + $outer - 1, $oy + $outer - 1, $color);
			imagefilledrectangle($img, $ox + $ms, $oy + $ms, $ox + 6 * $ms - 1, $oy + 6 * $ms - 1, $bg);
			imagefilledrectangle($img, $ox + 2 * $ms, $oy + 2 * $ms, $ox + 5 * $ms - 1, $oy + 5 * $ms - 1, $color);
		}
	}

	private function draw_icon(\GdImage $img, int $total, int $ms): void{
		if($this->icon_path === null || !is_file($this->icon_path)) return;

		$icon_size = (int)($this->size * $ms * $this->icon_scale);
		$icon_x = (int)(($total - $icon_size) / 2);
		$icon_y = (int)(($total - $icon_size) / 2);

		$bg = $this->alloc($img, $this->bg_color);
		$padding = (int)($icon_size * 0.15);
		imagefilledrectangle($img,
			$icon_x - $padding, $icon_y - $padding,
			$icon_x + $icon_size + $padding - 1, $icon_y + $icon_size + $padding - 1, $bg
		);

		$ext = strtolower(pathinfo($this->icon_path, PATHINFO_EXTENSION));
		$icon_img = match($ext){
			'png' => imagecreatefrompng($this->icon_path),
			'jpg', 'jpeg' => imagecreatefromjpeg($this->icon_path),
			default => null,
		};

		if($icon_img){
			imagecopyresampled($img, $icon_img, $icon_x, $icon_y, 0, 0,
				$icon_size, $icon_size, imagesx($icon_img), imagesy($icon_img));
			imagedestroy($icon_img);
		}
	}

	private function is_finder_module(int $r, int $c): bool{
		$s = $this->size;
		return ($r <= 7 && $c <= 7) || ($r <= 7 && $c >= $s - 8) || ($r >= $s - 8 && $c <= 7);
	}

	private function draw_bg_image(\GdImage $img, int $total): void{
		if(!is_file($this->bg_image_path)) return;

		$ext = strtolower(pathinfo($this->bg_image_path, PATHINFO_EXTENSION));
		$src = match($ext){
			'png' => imagecreatefrompng($this->bg_image_path),
			'jpg', 'jpeg' => imagecreatefromjpeg($this->bg_image_path),
			'gif' => imagecreatefromgif($this->bg_image_path),
			'webp' => imagecreatefromwebp($this->bg_image_path),
			default => null,
		};
		if(!$src) return;

		$sw = imagesx($src);
		$sh = imagesy($src);

		$scale = max($total / $sw, $total / $sh);
		$nw = (int)($sw * $scale);
		$nh = (int)($sh * $scale);
		$dx = (int)(($total - $nw) / 2);
		$dy = (int)(($total - $nh) / 2);

		imagecopyresampled($img, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
		imagedestroy($src);
	}

	private function alloc(\GdImage $img, string $hex): int{
		$hex = ltrim($hex, '#');
		return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
	}

	private function alloc_alpha(\GdImage $img, string $hex, int $alpha): int{
		$hex = ltrim($hex, '#');
		return imagecolorallocatealpha($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
	}
}
