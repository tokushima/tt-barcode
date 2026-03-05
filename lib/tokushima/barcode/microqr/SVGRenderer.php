<?php
namespace tokushima\barcode\microqr;

/**
 * マイクロQRコードをSVGとしてレンダリングする
 */
class SVGRenderer{
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

	public function render(): string{
		$ms = $this->module_size;
		$total = ($this->size + $this->margin * 2) * $ms;

		$svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$svg .= sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">'."\n",
			$total, $total, $total, $total
		);
		$svg .= sprintf('<rect width="%d" height="%d" fill="%s"/>'."\n", $total, $total, self::escape($this->bg_color));

		$fill = sprintf('fill="%s"', self::escape($this->fg_color));

		for($r = 0; $r < $this->size; $r++){
			for($c = 0; $c < $this->size; $c++){
				if(!$this->modules[$r][$c]) continue;
				$x = ($c + $this->margin) * $ms;
				$y = ($r + $this->margin) * $ms;
				$svg .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" %s/>'."\n", $x, $y, $ms, $ms, $fill);
			}
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
