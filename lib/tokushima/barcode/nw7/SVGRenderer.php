<?php
namespace tokushima\barcode\nw7;

/**
 * NW-7バーコードをSVGとしてレンダリングする
 */
class SVGRenderer{
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
	public function font_size(int $size): self{ $this->font_size = $size; return $this; }

	public function render(): string{
		$mw = $this->module_width;
		$wr = $this->wide_ratio;

		$barcode_width = 0;
		foreach($this->bars as [$is_bar, $width]){
			$barcode_width += ($width === 1) ? $mw : $mw * $wr;
		}

		$text_height = $this->show_text ? $this->font_size + 8 : 0;
		$total_w = $barcode_width + $this->margin * 2;
		$total_h = $this->height + $this->margin * 2 + $text_height;

		$svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$svg .= sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.1f %.1f" width="%.1f" height="%.1f">'."\n",
			$total_w, $total_h, $total_w, $total_h
		);
		$svg .= sprintf('<rect width="%.1f" height="%.1f" fill="%s"/>'."\n", $total_w, $total_h, self::escape($this->bg_color));

		$x = (float)$this->margin;
		foreach($this->bars as [$is_bar, $width]){
			$w = ($width === 1) ? $mw : $mw * $wr;
			if($is_bar){
				$svg .= sprintf('<rect x="%.1f" y="%d" width="%.1f" height="%d" fill="%s"/>'."\n",
					$x, $this->margin, $w, $this->height, self::escape($this->fg_color));
			}
			$x += $w;
		}

		if($this->show_text){
			$text_y = $this->margin + $this->height + $this->font_size + 4;
			$text_x = $this->margin + $barcode_width / 2;
			$svg .= sprintf(
				'<text x="%.1f" y="%d" text-anchor="middle" font-family="monospace" font-size="%d" fill="%s">%s</text>'."\n",
				$text_x, $text_y, $this->font_size, self::escape($this->fg_color), self::escape($this->text)
			);
		}

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

	private static function escape(string $value): string{
		return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}
}
