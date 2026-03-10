# tt-barcode

PHP 8.3+ のバーコード生成ライブラリ。QRコード、マイクロQRコード、rMQR、Data Matrix、NW-7 (Codabar)、郵便カスタマーバーコードに対応。SVG / PNG 出力をサポート。

## インストール

```bash
composer require tokushima/tt-barcode
```

## QRコード

```php
use tt\barcode\QRCode;

// SVG文字列を取得
$svg = QRCode::svg('https://example.com');

// PNGファイルに保存
QRCode::png('https://example.com', '/path/to/output.png');

// ビルダーパターンでカスタマイズ
$qr = QRCode::create('https://example.com')
    ->module_shape('dots')       // 'square' (デフォルト), 'dots'
    ->finder_shape('modern')     // 'square' (デフォルト), 'round', 'modern'
    ->fg_color('#FF0000')
    ->bg_color('#FFFFFF')
    ->finder_color('#CC0000')
    ->module_size(10)
    ->margin(4)
    ->icon_path('/path/to/logo.png', 0.25);

$svg = $qr->render_svg();              // SVG文字列を取得
$qr->save_svg('/path/to/output.svg');   // ファイルに保存

$binary = $qr->render_png();            // PNGバイナリを取得
$qr->save_png('/path/to/output.png');   // ファイルに保存

// グラデーション (SVGのみ)
$svg = QRCode::create('https://example.com')
    ->module_shape('dots')
    ->gradient('#FF6B6B', '#4ECDC4')
    ->render_svg();
```

### 誤り訂正レベル

| 定数 | 復元率 |
|------|--------|
| `QRCode::EC_L` | 7% |
| `QRCode::EC_M` | 15% (デフォルト) |
| `QRCode::EC_Q` | 25% |
| `QRCode::EC_H` | 30% |

アイコン・背景画像使用時は自動的に `EC_H` が適用されます。

```php
QRCode::create('https://example.com')
    ->icon_path('/path/to/logo.png')
    ->save_png('/path/to/output.png');
```

### 背景画像 (PNGのみ)

```php
QRCode::create('https://example.com')
    ->bg_image('/path/to/photo.jpg', 40)
    ->save_png('/path/to/output.png');
```

第2引数は白モジュール部分の透過率 (0-100, デフォルト50)。値が大きいほど背景画像がはっきり見えます。

### 半透明 (モジュールの不透明度)

```php
QRCode::create('https://example.com')
    ->alpha(50)
    ->save_png('/path/to/output.png');
```

`alpha(0-100)` でモジュール（黒い部分）の不透明度を指定。デフォルト100（完全不透明）。背景画像と組み合わせて使うと、画像上に目立たないQRコードを配置できます。

## マイクロQRコード

```php
use tt\barcode\MicroQR;

// SVG文字列を取得
$svg = MicroQR::svg('12345');

// PNGファイルに保存
MicroQR::png('HELLO', '/path/to/output.png');

// カスタマイズ
$mqr = MicroQR::create('HELLO')
    ->fg_color('#003366')
    ->module_size(15)
    ->margin(3);

$svg = $mqr->render_svg();              // SVG文字列を取得
$mqr->save_svg('/path/to/output.svg');   // ファイルに保存
```

バージョン M1 (11x11) - M4 (17x17)。誤り訂正: `EC_DETECT` (M1のみ), `EC_L`, `EC_M`, `EC_Q` (M4のみ)。

## Data Matrix

```php
use tt\barcode\DataMatrix;

// SVG文字列を取得
$svg = DataMatrix::svg('Hello');

// PNGファイルに保存
DataMatrix::png('Hello', '/path/to/output.png');

// ビルダーパターンでカスタマイズ
$dm = DataMatrix::create('Hello')
    ->fg_color('#003366')
    ->module_size(10)
    ->margin(2);

$svg = $dm->render_svg();              // SVG文字列を取得
$dm->save_svg('/path/to/output.svg');   // ファイルに保存

$binary = $dm->render_png();            // PNGバイナリを取得
$dm->save_png('/path/to/output.png');   // ファイルに保存

// ピクセルサイズ指定
DataMatrix::create('Hello')
    ->size(200)
    ->save_png('/path/to/output.png');
```

ECC 200 準拠。正方形 (10x10 - 144x144) および長方形 (8x18 - 16x48) シンボルに対応。ASCIIエンコーディング (数字2桁圧縮含む)。

## rMQR (Rectangular Micro QR Code)

```php
use tt\barcode\rMQR;

// SVG文字列を取得
$svg = rMQR::svg('Hello');

// PNGファイルに保存
rMQR::png('Hello', '/path/to/output.png');

// ビルダーパターンでカスタマイズ
$rmqr = rMQR::create('Hello')
    ->fg_color('#003366')
    ->module_size(15)
    ->margin(3);

$svg = $rmqr->render_svg();              // SVG文字列を取得
$rmqr->save_svg('/path/to/output.svg');   // ファイルに保存

$binary = $rmqr->render_png();            // PNGバイナリを取得
$rmqr->save_png('/path/to/output.png');   // ファイルに保存

// 誤り訂正レベル指定
rMQR::create('Hello', rMQR::EC_H)->save_svg('/path/to/output.svg');

// バージョン指定
rMQR::create('Hello', rMQR::EC_M, 'R9x43')->save_svg('/path/to/output.svg');
```

ISO/IEC 23941 準拠。32バージョン (R7x43 - R17x139) に対応。誤り訂正: `EC_M` (デフォルト), `EC_H`。数字・英数字・バイトモードを自動選択。

## NW-7 (Codabar)

```php
use tt\barcode\NW7;

// SVG文字列を取得
$svg = NW7::svg('12345');

// PNGファイルに保存
NW7::png('12345', '/path/to/output.png');

// カスタマイズ
$nw7 = NW7::create('12345')
    ->fg_color('#003366')
    ->height(100)
    ->module_width(2)
    ->margin(20)
    ->wide_ratio(2.5)
    ->show_text(false);

$svg = $nw7->render_svg();              // SVG文字列を取得
$nw7->save_svg('/path/to/output.svg');   // ファイルに保存

// スタート/ストップキャラクタ指定
NW7::create('12345', 'A', 'B')
    ->save_png('/path/to/output.png');
```

使用可能文字: `0-9`, `-`, `$`, `:`, `/`, `.`, `+`
スタート/ストップ: `A`, `B`, `C`, `D`

## 郵便カスタマーバーコード

```php
use tt\barcode\CustomerBarcode;

$bar = CustomerBarcode::create('263-0023', '千葉市稲毛区緑町3丁目30-8 郵便ビル403号');

// SVG出力
$svg = $bar->render_svg();              // SVG文字列を取得
$bar->save_svg('/path/to/output.svg');   // ファイルに保存

// PNG出力
$binary = $bar->render_png();            // PNGバイナリを取得
$bar->save_png('/path/to/output.png');   // ファイルに保存

// オプション指定
$svg = $bar->render_svg([
    'bar_height' => 3.6,      // バーの高さ(mm)
    'module_width' => 0.6,    // モジュール幅(mm)
    'gap' => 0.6,             // ギャップ幅(mm)
    'color' => '#000000',     // バーの色
    'bgcolor' => '#FFFFFF',   // 背景色
]);

$bar->save_png('/path/to/output.png', [
    'dpi' => 300,
]);
```

住所の漢数字や全角数字は自動的に変換されます。

## 動作要件

- PHP >= 8.3
- GD拡張 (PNG出力時)
