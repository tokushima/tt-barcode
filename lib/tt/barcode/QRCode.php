<?php
namespace tt\barcode;

use tt\barcode\qrcode\QRData;
use tt\barcode\qrcode\QREncoder;
use tt\barcode\qrcode\SVGRenderer;
use tt\barcode\qrcode\PNGRenderer;

/**
 * QRコード生成
 *
 * // シンプルなSVG
 * $svg = QRCode::svg('https://example.com');
 *
 * // PNGファイル保存
 * QRCode::png('https://example.com', '/path/to/output.png');
 *
 * // デザインカスタマイズ
 * QRCode::create('https://example.com')
 *     ->design('youtube')
 *     ->fg_color('#FF0000')
 *     ->finder_color('#CC0000')
 *     ->margin(2)
 *     ->icon_path('/path/to/logo.png', 0.25)
 *     ->save_svg('/path/to/output.svg');
 *
 * // グラデーション
 * QRCode::create('https://example.com')
 *     ->design('dots')
 *     ->gradient('#FF6B6B', '#4ECDC4')
 *     ->save_svg('/path/to/output.svg');
 *
 * デザイン:
 *   'standard' - 標準正方形
 *   'rounded'  - 角丸
 *   'dots'     - 円形
 *   'youtube'  - YouTube風 (円形 + カスタムファインダ)
 *
 * 誤り訂正レベル:
 *   QRCode::EC_L (7%), QRCode::EC_M (15%), QRCode::EC_Q (25%), QRCode::EC_H (30%)
 *   アイコンを使う場合はEC_Hを推奨
 */
class QRCode{
	const int EC_L = QRData::EC_L;
	const int EC_M = QRData::EC_M;
	const int EC_Q = QRData::EC_Q;
	const int EC_H = QRData::EC_H;

	private array $modules;
	private string $design = 'standard';
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

	private function __construct(array $modules){
		$this->modules = $modules;
	}

	/**
	 * QRコードインスタンスを生成 (ビルダーパターン)
	 */
	public static function create(string $text, int $ec_level = QRData::EC_M, ?int $version = null): self{
		return new self(QREncoder::encode($text, $ec_level, $version));
	}

	/**
	 * SVG文字列を返す (ショートカット)
	 */
	public static function svg(string $text, int $ec_level = QRData::EC_M): string{
		return self::create($text, $ec_level)->render_svg();
	}

	/**
	 * PNGファイルに保存 (ショートカット)
	 */
	public static function png(string $text, string $filepath, int $ec_level = QRData::EC_M): void{
		self::create($text, $ec_level)->save_png($filepath);
	}

	public function design(string $design): self{ $this->design = $design; return $this; }
	public function fg_color(string $color): self{ $this->fg_color = $color; return $this; }
	public function bg_color(string $color): self{ $this->bg_color = $color; return $this; }
	public function finder_color(string $color): self{ $this->finder_color = $color; return $this; }
	public function module_size(int $px): self{ $this->module_size = $px; return $this; }
	public function margin(int $modules): self{ $this->margin = $modules; return $this; }

	/**
	 * 出力サイズをピクセルで指定（module_sizeを自動計算）
	 */
	public function size(int $px): self{
		$this->module_size = max(1, (int)floor($px / (count($this->modules) + $this->margin * 2)));
		return $this;
	}
	public function dot_scale(float $scale): self{ $this->dot_scale = $scale; return $this; }
	public function corner_radius(float $ratio): self{ $this->corner_radius = $ratio; return $this; }

	public function gradient(string $start, string $end): self{
		$this->gradient_start = $start;
		$this->gradient_end = $end;
		return $this;
	}

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
	 * QRマトリクス (bool[][])
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
		$r->design($this->design)->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_size($this->module_size)->margin($this->margin)
			->dot_scale($this->dot_scale)->corner_radius($this->corner_radius);

		if($this->finder_color !== null) $r->finder_color($this->finder_color);
		if($this->gradient_start !== null && $this->gradient_end !== null) $r->gradient($this->gradient_start, $this->gradient_end);
		if($this->icon_svg !== null) $r->icon_svg($this->icon_svg, $this->icon_scale);
		if($this->icon_path !== null) $r->icon_path($this->icon_path, $this->icon_scale);
		return $r;
	}

	private function build_png(): PNGRenderer{
		$r = new PNGRenderer($this->modules);
		$r->design($this->design)->fg_color($this->fg_color)->bg_color($this->bg_color)
			->module_size($this->module_size)->margin($this->margin)->dot_scale($this->dot_scale);

		if($this->finder_color !== null) $r->finder_color($this->finder_color);
		if($this->icon_path !== null) $r->icon_path($this->icon_path, $this->icon_scale);
		return $r;
	}
}
