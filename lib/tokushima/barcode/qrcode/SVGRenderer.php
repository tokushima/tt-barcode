<?php
namespace tokushima\barcode\qrcode;

/**
 * QRコードマトリクスをSVGとしてレンダリングする
 *
 * デザイン:
 *  - standard: 標準的な正方形モジュール
 *  - rounded: 角丸モジュール
 *  - dots: 円形モジュール
 *  - youtube: YouTube風デザイン (円形モジュール + カスタムファインダパターン)
 */
class SVGRenderer{
	const DESIGN_STANDARD = 'standard';
	const DESIGN_ROUNDED = 'rounded';
	const DESIGN_DOTS = 'dots';
	const DESIGN_YOUTUBE = 'youtube';

	private array $modules;
	private int $size;

	private string $design = self::DESIGN_STANDARD;
	private string $fg_color = '#000000';
	private string $bg_color = '#FFFFFF';
	private ?string $finder_color = null;
	private ?string $gradient_start = null;
	private ?string $gradient_end = null;
	private int $module_size = 10;
	private int $margin = 4;
	private float $dot_scale = 0.85;
	private float $corner_radius = 0.4;
	private ?string $icon_svg = null;
	private ?string $icon_path = null;
	private float $icon_scale = 0.2;

	public function __construct(array $modules){
		$this->modules = $modules;
		$this->size = count($modules);
	}

	public function design(string $design): self{ $this->design = $design; return $this; }
	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function finder_color(string $color): self{ $this->finder_color = $color; return $this; }

	public function gradient(string $start_color, string $end_color): self{
		$this->gradient_start = $start_color;
		$this->gradient_end = $end_color;
		return $this;
	}

	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }
	public function dot_scale(float $scale): self{ $this->dot_scale = max(0.1, min(1.0, $scale)); return $this; }
	public function corner_radius(float $ratio): self{ $this->corner_radius = max(0.0, min(0.5, $ratio)); return $this; }

	public function icon_svg(string $svg, float $scale = 0.2): self{
		$this->icon_svg = $svg;
		$this->icon_scale = $scale;
		return $this;
	}

	public function icon_path(string $path, float $scale = 0.2): self{
		$this->icon_path = $path;
		$this->icon_scale = $scale;
		return $this;
	}

	public function render(): string{
		$ms = $this->module_size;
		$total = ($this->size + $this->margin * 2) * $ms;

		$svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$svg .= sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 %d %d" width="%d" height="%d">'."\n",
			$total, $total, $total, $total
		);
		$svg .= sprintf('<rect width="%d" height="%d" fill="%s"/>'."\n", $total, $total, self::escape($this->bg_color));

		$fill_attr = sprintf('fill="%s"', self::escape($this->fg_color));
		if($this->gradient_start !== null && $this->gradient_end !== null){
			$svg .= '<defs>'."\n";
			$svg .= sprintf(
				'<linearGradient id="qr-grad" x1="0%%" y1="0%%" x2="100%%" y2="100%%">'.
				'<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/>'.
				'</linearGradient>'."\n",
				self::escape($this->gradient_start), self::escape($this->gradient_end)
			);
			$svg .= '</defs>'."\n";
			$fill_attr = 'fill="url(#qr-grad)"';
		}

		$finder_fill = $this->finder_color ? sprintf('fill="%s"', self::escape($this->finder_color)) : $fill_attr;

		$svg .= '<g>'."\n";

		if($this->design === self::DESIGN_YOUTUBE){
			$svg .= $this->render_youtube_finders($finder_fill, $ms);

			for($r = 0; $r < $this->size; $r++){
				for($c = 0; $c < $this->size; $c++){
					if($this->is_finder_module($r, $c) || !$this->modules[$r][$c]) continue;
					$svg .= $this->render_dot_module($r, $c, $ms, $fill_attr);
				}
			}
		}else{
			for($r = 0; $r < $this->size; $r++){
				for($c = 0; $c < $this->size; $c++){
					if(!$this->modules[$r][$c]) continue;
					$f = $this->is_finder_module($r, $c) ? $finder_fill : $fill_attr;

					$svg .= match($this->design){
						self::DESIGN_ROUNDED => $this->render_rounded_module($r, $c, $ms, $f),
						self::DESIGN_DOTS    => $this->render_dot_module($r, $c, $ms, $f),
						default              => $this->render_square_module($r, $c, $ms, $f),
					};
				}
			}
		}
		$svg .= '</g>'."\n";
		$svg .= $this->render_icon($total, $ms);
		$svg .= '</svg>';
		return $svg;
	}

	public function save(string $filepath): void{
		$dir = dirname($filepath);
		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($filepath, $this->render());
	}

	private function render_square_module(int $r, int $c, int $ms, string $fill): string{
		$x = ($c + $this->margin) * $ms;
		$y = ($r + $this->margin) * $ms;
		return sprintf('<rect x="%d" y="%d" width="%d" height="%d" %s/>'."\n", $x, $y, $ms, $ms, $fill);
	}

	private function render_rounded_module(int $r, int $c, int $ms, string $fill): string{
		$x = ($c + $this->margin) * $ms;
		$y = ($r + $this->margin) * $ms;
		$radius = $ms * $this->corner_radius;
		return sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="%.1f" ry="%.1f" %s/>'."\n", $x, $y, $ms, $ms, $radius, $radius, $fill);
	}

	private function render_dot_module(int $r, int $c, int $ms, string $fill): string{
		$cx = ($c + $this->margin) * $ms + $ms / 2;
		$cy = ($r + $this->margin) * $ms + $ms / 2;
		$radius = ($ms / 2) * $this->dot_scale;
		return sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" %s/>'."\n", $cx, $cy, $radius, $fill);
	}

	private function render_youtube_finders(string $fill, int $ms): string{
		$svg = '';
		foreach([[0, 0], [0, $this->size - 7], [$this->size - 7, 0]] as [$pr, $pc]){
			$ox = ($pc + $this->margin) * $ms;
			$oy = ($pr + $this->margin) * $ms;

			$outer = 7 * $ms;
			$svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="%.1f" ry="%.1f" %s/>'."\n",
				$ox, $oy, $outer, $outer, $ms * 1.4, $ms * 1.4, $fill);
			$svg .= sprintf('<rect x="%.1f" y="%.1f" width="%d" height="%d" rx="%.1f" ry="%.1f" fill="%s"/>'."\n",
				$ox + $ms, $oy + $ms, 5 * $ms, 5 * $ms, $ms * 1.0, $ms * 1.0, self::escape($this->bg_color));
			$svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="%.1f" ry="%.1f" %s/>'."\n",
				$ox + 2 * $ms, $oy + 2 * $ms, 3 * $ms, 3 * $ms, $ms * 0.8, $ms * 0.8, $fill);
		}
		return $svg;
	}

	private function render_icon(int $total_size, int $ms): string{
		if($this->icon_svg === null && $this->icon_path === null){
			return '';
		}

		$icon_size = $this->size * $ms * $this->icon_scale;
		$icon_x = ($total_size - $icon_size) / 2;
		$icon_y = ($total_size - $icon_size) / 2;

		$svg = '';
		$padding = $icon_size * 0.15;
		$bg_size = $icon_size + $padding * 2;
		$bg_r = $bg_size * 0.15;
		$svg .= sprintf(
			'<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" ry="%.1f" fill="%s"/>'."\n",
			$icon_x - $padding, $icon_y - $padding, $bg_size, $bg_size, $bg_r, $bg_r, self::escape($this->bg_color)
		);

		if($this->icon_svg !== null){
			$svg .= sprintf(
				'<g transform="translate(%.1f, %.1f)"><svg viewBox="0 0 100 100" width="%.1f" height="%.1f">%s</svg></g>'."\n",
				$icon_x, $icon_y, $icon_size, $icon_size, $this->icon_svg
			);
		}else if($this->icon_path !== null){
			$ext = strtolower(pathinfo($this->icon_path, PATHINFO_EXTENSION));
			if($ext === 'svg'){
				$content = file_get_contents($this->icon_path);
				if(preg_match('/<svg[^>]*>(.*)<\/svg>/s', $content, $m)){
					$viewBox = '0 0 100 100';
					if(preg_match('/viewBox="([^"]*)"/', $content, $vb)) $viewBox = $vb[1];
					$svg .= sprintf('<g transform="translate(%.1f, %.1f)"><svg viewBox="%s" width="%.1f" height="%.1f">%s</svg></g>'."\n",
						$icon_x, $icon_y, $viewBox, $icon_size, $icon_size, $m[1]);
				}
			}else{
				$data = base64_encode(file_get_contents($this->icon_path));
				$mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
				$svg .= sprintf('<image x="%.1f" y="%.1f" width="%.1f" height="%.1f" href="data:%s;base64,%s"/>'."\n",
					$icon_x, $icon_y, $icon_size, $icon_size, $mime, $data);
			}
		}
		return $svg;
	}

	private function is_finder_module(int $r, int $c): bool{
		$s = $this->size;
		return ($r <= 7 && $c <= 7) || ($r <= 7 && $c >= $s - 8) || ($r >= $s - 8 && $c <= 7);
	}

	private static function escape(string $value): string{
		return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}
}
