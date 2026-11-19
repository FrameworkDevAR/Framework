<?php
namespace Tests\File;

use Framework\File\Storage;
use Framework\File\Image;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GdImage;

class ImageTest extends TestCase {
    use TestHelpers;

    private string $tmpDir = "";

    /** @var array<string,string> */
    private array $files = [];


    protected function setUp(): void {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "image_test_" . uniqid();
        @mkdir($this->tmpDir);

        $this->files = [
            "gif"         => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.gif",
            "jpeg"        => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.jpg",
            "png"         => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.png",
            "bmp"         => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.bmp",
            "xbm"         => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.xbm",
            "transparent" => $this->tmpDir . DIRECTORY_SEPARATOR . "transparent.png",
            "large"       => $this->tmpDir . DIRECTORY_SEPARATOR . "large-transparent.png",
            "spaced"      => $this->tmpDir . DIRECTORY_SEPARATOR . "space image.png",
            "text"        => $this->tmpDir . DIRECTORY_SEPARATOR . "sample.txt",
            "missing"     => $this->tmpDir . DIRECTORY_SEPARATOR . "missing.png",
            "corrupt"     => $this->tmpDir . DIRECTORY_SEPARATOR . "corrupt.png",
            "truncated"   => $this->tmpDir . DIRECTORY_SEPARATOR . "truncated.png",
            "oriented"    => $this->tmpDir . DIRECTORY_SEPARATOR . "oriented.jpg",
            "photo"       => $this->tmpDir . DIRECTORY_SEPARATOR . "photo.PNG",
            "empty"       => $this->tmpDir . DIRECTORY_SEPARATOR . "empty.png",
        ];

        $this->writeFixtureImage($this->files["gif"], 1, 30, 15);
        $this->writeFixtureImage($this->files["jpeg"], 2, 200, 100);
        $this->writeFixtureImage($this->files["png"], 3, 10, 20);
        $this->writeFixtureImage($this->files["bmp"], 15, 100, 100);
        $this->writeFixtureImage($this->files["xbm"], 16, 100, 100);
        $this->writeFixtureImage($this->files["transparent"], 3, 20, 10, transparent: true);
        $this->writeFixtureImage($this->files["large"], 3, 60, 60, transparent: true);
        $this->writeFixtureImage($this->files["spaced"], 3, 12, 18);

        @file_put_contents($this->files["text"], "not an image");

        // A .png file with garbage content: passes the extension checks but cannot be read
        @file_put_contents($this->files["corrupt"], "this is not a png");

        // A .png with a valid header but a truncated body: getimagesize works, imagecreatefrompng fails
        $this->writeFixtureImage($this->files["truncated"], 3, 60, 40);
        $pngData = (string)@file_get_contents($this->files["truncated"]);
        @file_put_contents($this->files["truncated"], substr($pngData, 0, 40));

        // A JPEG carrying an EXIF Orientation tag
        $this->writeFixtureImage($this->files["oriented"], 2, 30, 20);
        $this->writeFixtureImage($this->files["photo"], 2, 30, 20);
        @file_put_contents($this->files["empty"], "");
        $this->writeExifOrientation($this->files["oriented"], 6);

        $GLOBALS["test_image_url_files"] = [];
        foreach ($this->files as $path) {
            $GLOBALS["test_image_url_files"][basename($path)] = $path;
        }
    }

    protected function tearDown(): void {
        unset($GLOBALS["test_image_url_files"]);
        Storage::deleteDir($this->tmpDir);
    }


    #[DataProvider("providerHasType")]
    public function testHasType(int $fileType, bool $expected): void {
        $this->assertSame($expected, Image::hasType($fileType));
    }

    public static function providerHasType(): array {
        return [
            "gif"         => [ 1, true ],
            "jpeg"        => [ 2, true ],
            "png"         => [ 3, true ],
            "wbmp"        => [ 15, true ],
            "xbm"         => [ 16, true ],
            "unknown"     => [ 0, false ],
            "unsupported" => [ 4, false ],
        ];
    }


    #[DataProvider("providerIsValidType")]
    public function testIsValidType(string $token, bool $expected, bool $url = false): void {
        $path = $url ? $this->getFixtureUrl($token) : ($this->files[$token] ?? "");
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::isValidType($path),
            suppress: $url,
        );

        $this->assertSame($expected, $result);
    }

    public static function providerIsValidType(): array {
        return [
            "gif"         => [ "gif", true ],
            "jpeg"        => [ "jpeg", true ],
            "png"         => [ "png", true ],
            "text"        => [ "text", false ],
            "missing"     => [ "missing", false ],
            "invalid"     => [ "invalid", false ],
            "png url"     => [ "png", true, true ],
            "spaced url"  => [ "spaced", true, true ],
            "text url"    => [ "text", false, true ],
            "missing url" => [ "missing", false, true ],
        ];
    }


    #[DataProvider("providerGetType")]
    public function testGetType(string $token, int $expected, bool $url = false): void {
        $path = $url ? $this->getFixtureUrl($token) : ($this->files[$token] ?? "");
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getType($path),
            suppress: $url,
        );

        $this->assertSame($expected, $result);
    }

    public static function providerGetType(): array {
        return [
            "gif"         => [ "gif", 1 ],
            "jpeg"        => [ "jpeg", 2 ],
            "png"         => [ "png", 3 ],
            "text"        => [ "text", 0 ],
            "missing"     => [ "missing", 0 ],
            "invalid"     => [ "invalid", 0 ],
            "png url"     => [ "png", 3, true ],
            "spaced url"  => [ "spaced", 3, true ],
            "text url"    => [ "text", 0, true ],
            "missing url" => [ "missing", 0, true ],
        ];
    }


    #[DataProvider("providerGetMimeType")]
    public function testGetMimeType(string $token, string $expected, bool $url = false): void {
        $path = $url ? $this->getFixtureUrl($token) : ($this->files[$token] ?? "");
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getMimeType($path),
            suppress: $url,
        );

        $this->assertSame($expected, $result);
    }

    public static function providerGetMimeType(): array {
        return [
            "gif"         => [ "gif", "image/gif" ],
            "jpeg"        => [ "jpeg", "image/jpeg" ],
            "png"         => [ "png", "image/png" ],
            "text"        => [ "text", "application/octet-stream" ],
            "missing"     => [ "missing", "" ],
            "invalid"     => [ "invalid", "" ],
            "jpeg url"    => [ "jpeg", "image/jpeg", true ],
            "spaced url"  => [ "spaced", "image/png", true ],
            "text url"    => [ "text", "application/octet-stream", true ],
            "missing url" => [ "missing", "application/octet-stream", true ],
        ];
    }


    #[DataProvider("providerGetSize")]
    public function testGetSize(string $base, array $pathParts, array $expected): void {
        $base = $this->files[$base] ?? $this->tmpDir;
        $size = Image::getSize($base, ...$pathParts);
        $this->assertSame($expected, [ $size[0], $size[1], $size[2] ]);
    }

    public static function providerGetSize(): array {
        return [
            "jpeg full path" => [ "jpeg", [], [ 200, 100, 2 ] ],
            "png split path" => [ "tmpDir", [ "sample.png" ], [ 10, 20, 3 ] ],
            "text file"      => [ "text", [], [ 0, 0, 0 ] ],
            "missing"        => [ "missing", [], [ 0, 0, 0 ] ],
            "invalid"        => [ "invalid", [], [ 0, 0, 0 ] ],
        ];
    }


    #[DataProvider("providerGetSizeFromUrl")]
    public function testGetSizeFromUrl(string $token, array $expected, bool $url = false): void {
        $path   = $url ? $this->getFixtureUrl($token) : ($this->files[$token] ?? "");
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getSizeFromUrl($path),
            suppress: true,
        );

        $this->assertSame($expected, [ $result[0], $result[1], $result[2] ]);
    }

    public static function providerGetSizeFromUrl(): array {
        return [
            "gif"         => [ "gif", [ 30, 15, 1 ] ],
            "jpeg"        => [ "jpeg", [ 200, 100, 2 ] ],
            "png"         => [ "png", [ 10, 20, 3 ] ],
            "text"        => [ "text", [ 0, 0, 0 ] ],
            "missing"     => [ "missing", [ 0, 0, 0 ] ],
            "invalid"     => [ "invalid", [ 0, 0, 0 ] ],
            "gif url"     => [ "gif", [ 30, 15, 1 ], true ],
            "jpeg url"    => [ "jpeg", [ 200, 100, 2 ], true ],
            "spaced url"  => [ "spaced", [ 12, 18, 3 ], true ],
            "text url"    => [ "text", [ 0, 0, 0 ], true ],
            "missing url" => [ "missing", [ 0, 0, 0 ], true ],
        ];
    }


    #[DataProvider("providerGetOrientation")]
    public function testGetOrientation(string $token, int $expected): void {
        $path   = $this->files[$token] ?? "";
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getOrientation($path),
            suppress: true,
        );

        $this->assertSame($expected, $result);
    }

    public static function providerGetOrientation(): array {
        return [
            "opaque png"        => [ "png", 0 ],
            "transparent png"   => [ "transparent", 0 ],
            "large transparent" => [ "large", 0 ],
            "jpeg"              => [ "jpeg", 0 ],
            "missing"           => [ "missing", 0 ],
            "invalid"           => [ "invalid", 0 ],
        ];
    }


    #[DataProvider("providerHasTransparency")]
    public function testHasTransparency(string $token, bool $expected): void {
        $path = $this->files[$token] ?? "";
        $this->assertSame($expected, Image::hasTransparency($path));
    }

    public static function providerHasTransparency(): array {
        return [
            "opaque png"            => [ "png", false ],
            "transparent png"       => [ "transparent", true ],
            "large transparent png" => [ "large", true ],
            "jpeg"                  => [ "jpeg", false ],
            "missing"               => [ "missing", false ],
            "invalid"               => [ "invalid", false ],
        ];
    }


    #[DataProvider("providerGetTextWidth")]
    public function testGetTextWidth(string $text, bool $useFontFile, int $fontSize, bool $expectPositive): void {
        $fontFile = "";
        if ($useFontFile) {
            $fontFile = $this->findFontFile();
        }

        $width = Image::getTextWidth($text, $fontFile, $fontSize);

        if ($expectPositive) {
            $this->assertGreaterThan(0, $width);
        } else {
            $this->assertSame(0, $width);
        }
    }

    public static function providerGetTextWidth(): array {
        return [
            "normal"  => [ "Framework", true, 12, true ],
            "empty"   => [ "", true, 12, false ],
            "no font" => [ "Framework", false, 0, false ],
        ];
    }


    #[DataProvider("providerResample")]
    public function testResample(
        string $srcToken,
        string $dstName,
        ?int $orientation,
        bool $expected,
        ?array $expectedSize,
    ): void {
        $srcPath = $this->files[$srcToken] ?? "";
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . $dstName;
        $result  = Image::resample($srcPath, $dstPath, $orientation);

        $this->assertSame($expected, $result);

        if ($expectedSize === null) {
            $this->assertFileDoesNotExist($dstPath);
            return;
        }

        $size = Image::getSize($dstPath);
        $this->assertSame($expectedSize, [ $size[0], $size[1], $size[2] ]);
    }

    public static function providerResample(): array {
        return [
            "jpeg rotate 180" => [ "jpeg", "resample-180.jpg", 3, true, [ 200, 100, 2 ] ],
            "jpeg rotate 90"  => [ "jpeg", "resample-90.jpg", 6, true, [ 100, 200, 2 ] ],
            "png rotate 270"  => [ "png", "resample-270.png", 8, true, [ 20, 10, 3 ] ],
            "no orientation"  => [ "jpeg", "resample-none.jpg", null, false, null ],
            "invalid"         => [ "jpeg", "resample-invalid.jpg", 0, false, null ],
        ];
    }


    #[DataProvider("providerResize")]
    public function testResize(
        string $srcToken,
        string $dstName,
        int $width,
        int $height,
        string $action,
        bool $expected,
        ?array $expectedSize,
    ): void {
        $srcPath = $this->files[$srcToken] ?? "";
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . $dstName;
        $result  = Image::resize($srcPath, $dstPath, $width, $height, $action);

        $this->assertSame($expected, $result);

        if ($expectedSize === null) {
            $this->assertFileDoesNotExist($dstPath);
            return;
        }

        $size = Image::getSize($dstPath);
        $this->assertSame($expectedSize, [ $size[0], $size[1], $size[2] ]);
    }

    public static function providerResize(): array {
        return [
            "resize jpeg"    => [ "jpeg", "resize.jpg", 50, 50, Image::Resize, true, [ 50, 25, 2 ] ],
            "resize png"     => [ "png", "resize.png", 50, 50, Image::Resize, true, [ 25, 50, 3 ] ],
            "maximum small"  => [ "png", "maximum.png", 50, 50, Image::Maximum, true, [ 10, 20, 3 ] ],
            "thumb jpeg"     => [ "jpeg", "thumb.jpg", 50, 50, Image::Thumb, true, [ 50, 50, 2 ] ],
            "missing source" => [ "missing", "missing.jpg", 50, 50, Image::Resize, false, null ],
        ];
    }


    #[DataProvider("providerResizeCrop")]
    public function testResizeCrop(
        string $srcToken,
        string $dstName,
        int $resWidth,
        int $resHeight,
        int $cropX,
        int $cropY,
        int $cropWidth,
        int $cropHeight,
        bool $expected,
        ?array $expectedSize,
    ): void {
        $srcPath = $this->files[$srcToken] ?? "";
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . $dstName;
        $result  = Image::resizeCrop(
            $srcPath,
            $dstPath,
            $resWidth,
            $resHeight,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
        );

        $this->assertSame($expected, $result);

        if ($expectedSize === null) {
            $this->assertFileDoesNotExist($dstPath);
            return;
        }

        $size = Image::getSize($dstPath);
        $this->assertSame($expectedSize, [ $size[0], $size[1], $size[2] ]);
    }

    public static function providerResizeCrop(): array {
        return [
            "jpeg crop"      => [ "jpeg", "crop.jpg", 100, 50, 10, 10, 30, 20, true, [ 30, 20, 2 ] ],
            "png crop"       => [ "png", "crop.png", 40, 20, 5, 5, 10, 10, true, [ 10, 10, 3 ] ],
            "invalid size"   => [ "jpeg", "crop-invalid.jpg", 0, 50, 0, 0, 30, 20, false, null ],
            "invalid crop"   => [ "jpeg", "crop-invalid.jpg", 100, 50, 0, 0, 0, 20, false, null ],
            "missing source" => [ "missing", "crop-missing.jpg", 100, 50, 10, 10, 30, 20, false, null ],
        ];
    }


    #[DataProvider("providerGetContentType")]
    public function testGetContentType(int $imgType, string $expected): void {
        $this->assertSame($expected, Image::getContentType($imgType));
    }

    public static function providerGetContentType(): array {
        return [
            "gif"     => [ 1, "image/gif" ],
            "jpeg"    => [ 2, "image/jpeg" ],
            "png"     => [ 3, "image/png" ],
            "wbmp"    => [ 15, "image/wbmp" ],
            "xbm"     => [ 16, "image/xbm" ],
            "unknown" => [ 99, "image/unknown" ],
        ];
    }


    #[DataProvider("providerGetExtension")]
    public function testGetExtension(int $imgType, string $expected): void {
        $this->assertSame($expected, Image::getExtension($imgType));
    }

    public static function providerGetExtension(): array {
        return [
            "gif"     => [ 1, "gif" ],
            "jpeg"    => [ 2, "jpg" ],
            "png"     => [ 3, "png" ],
            "wbmp"    => [ 15, "bmp" ],
            "xbm"     => [ 16, "xbm" ],
            "unknown" => [ 99, "unknown" ],
        ];
    }


    #[DataProvider("providerCreateSrcImage")]
    public function testCreateSrcImage(int $imgType, string $token, bool $expected): void {
        $image = Image::createSrcImage($imgType, $this->files[$token] ?? "");

        if ($expected) {
            $this->assertInstanceOf(GdImage::class, $image);
            return;
        }

        $this->assertNull($image);
    }

    public static function providerCreateSrcImage(): array {
        return [
            "gif"        => [ 1, "gif", true ],
            "jpeg"       => [ 2, "jpeg", true ],
            "png"        => [ 3, "png", true ],
            "bmp"        => [ 15, "bmp", true ],
            "xbm"        => [ 16, "xbm", true ],
            "wrong type" => [ 99, "jpeg", false ],
            "missing"    => [ 3, "missing", false ],
        ];
    }


    #[DataProvider("providerCreateDstImage")]
    public function testCreateDstImage(int $imgType, int $width, int $height, ?bool $expectedTransparent): void {
        $image = Image::createDstImage($imgType, $width, $height);

        if ($expectedTransparent === null) {
            $this->assertNull($image);
            return;
        }

        $this->assertInstanceOf(GdImage::class, $image);

        $color = imagecolorat($image, 0, 0);
        $alpha = imagecolorsforindex($image, $color)["alpha"];
        $this->assertSame($expectedTransparent ? 127 : 0, $alpha);
    }

    public static function providerCreateDstImage(): array {
        return [
            "gif"            => [ 1, 10, 10, true ],
            "jpeg"           => [ 2, 10, 10, false ],
            "png"            => [ 3, 10, 10, true ],
            "invalid width"  => [ 2, 0, 10, null ],
            "invalid height" => [ 3, 10, -1, null ],
        ];
    }


    #[DataProvider("providerCreateImage")]
    public function testCreateImage(int $imgType, string $fileName, bool $expected): void {
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . $fileName;
        $image   = imagecreatetruecolor(12, 8);

        $this->assertNotFalse($image);
        $result = Image::createImage($imgType, $image, $dstPath);
        $this->assertSame($expected, $result);

        if ($expected) {
            $this->assertFileExists($dstPath);
            $this->assertSame($imgType, Image::getType($dstPath));
            return;
        }

        $this->assertFileDoesNotExist($dstPath);
    }

    public static function providerCreateImage(): array {
        return [
            "gif"     => [ 1, "created.gif", true ],
            "jpeg"    => [ 2, "created.jpg", true ],
            "png"     => [ 3, "created.png", true ],
            "bmp"     => [ 15, "created.bmp", true ],
            "xbm"     => [ 16, "created.xbm", true ],
            "unknown" => [ 99, "created.bin", false ],
        ];
    }


    #[DataProvider("providerThumbnail")]
    public function testThumbnail(
        string $srcToken,
        string $dstName,
        int $width,
        int $height,
        string $action,
        bool $expected,
        ?array $expectedSize,
    ): void {
        $srcPath = $this->files[$srcToken] ?? "";
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . $dstName;
        $result  = Image::thumbnail($srcPath, $dstPath, $width, $height, $action);

        $this->assertSame($expected, $result);

        if ($expectedSize === null) {
            $this->assertFileDoesNotExist($dstPath);
            return;
        }

        $size = Image::getSize($dstPath);
        $this->assertSame($expectedSize, [ $size[0], $size[1], $size[2] ]);
    }

    public static function providerThumbnail(): array {
        return [
            "small thumb copies" => [ "png", "thumb-copy.png", 50, 50, Image::Thumb, true, [ 10, 20, 3 ] ],
            "maximum jpeg"       => [ "jpeg", "thumb-maximum.jpg", 50, 50, Image::Maximum, true, [ 50, 25, 2 ] ],
            "thumb large png"    => [ "large", "thumb-large.png", 30, 30, Image::Thumb, true, [ 30, 30, 3 ] ],
        ];
    }


    #[DataProvider("providerUnreadableImage")]
    public function testGetSizeWithUnreadableImage(string $token): void {
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getSize($this->files[$token]),
            suppress: true,
        );
        $this->assertSame([ 0, 0, 0 ], $result);
    }

    public static function providerUnreadableImage(): array {
        return [
            "corrupt png" => [ "corrupt" ],
            "empty png"   => [ "empty" ],
        ];
    }


    #[DataProvider("providerTransparencyUnreadable")]
    public function testHasTransparencyWithUnreadableImage(string $token): void {
        // Nothing is suppressed, as a file that can not be read gives no warning
        $this->assertFalse(Image::hasTransparency($this->files[$token]));
    }

    /**
     * A photo can come with the extension of a PNG, and a file can be cut
     * or empty when its upload failed
     * @return array<string,array{string}>
     */
    public static function providerTransparencyUnreadable(): array {
        return [
            "corrupt png"       => [ "corrupt" ],
            "truncated png"     => [ "truncated" ],
            "a photo named png" => [ "photo" ],
            "an empty png"      => [ "empty" ],
        ];
    }


    public function testGetTextWidthWithInvalidFont(): void {
        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getTextWidth("Hello", $this->files["text"], 12),
            suppress: true,
        );
        $this->assertSame(0, $result);
    }


    #[DataProvider("providerResampleOrientation")]
    public function testResampleOrientation(string $token, int $orientation, bool $expected): void {
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "resampled.png";
        $result  = Image::resample($this->files[$token], $dstPath, $orientation);

        $this->assertSame($expected, $result);
        if ($expected) {
            $this->assertFileExists($dstPath);
        }
    }

    public static function providerResampleOrientation(): array {
        return [
            "unsupported type"    => [ "text", 1, false ],
            "default orientation" => [ "png", 1, true ],
            "rotate 180"          => [ "png", 3, true ],
        ];
    }


    #[DataProvider("providerResizeUnreadable")]
    public function testResizeWithUnreadableImage(string $action): void {
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "resized-broken.png";
        $result  = $this->runWithSuppressedWarnings(
            fn() => Image::resize($this->files["truncated"], $dstPath, 10, 10, $action),
            suppress: true,
        );
        $this->assertFalse($result);
    }

    public static function providerResizeUnreadable(): array {
        return [
            "resize"  => [ Image::Resize ],
            "maximum" => [ Image::Maximum ],
        ];
    }


    public function testResampleWithUnreadableImage(): void {
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "resampled-broken.png";
        $result  = $this->runWithSuppressedWarnings(
            fn() => Image::resample($this->files["truncated"], $dstPath, 1),
            suppress: true,
        );
        $this->assertFalse($result);
    }


    #[DataProvider("providerGetOrientationFromExif")]
    public function testGetOrientationFromExif(int $orientation): void {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . "exif-$orientation.jpg";
        $this->writeFixtureImage($path, 2, 30, 20);
        $this->writeExifOrientation($path, $orientation);

        $result = $this->runWithSuppressedWarnings(
            fn() => Image::getOrientation($path),
            suppress: true,
        );
        $this->assertSame($orientation, $result);
    }

    public static function providerGetOrientationFromExif(): array {
        return [
            "normal"     => [ 1 ],
            "rotate 180" => [ 3 ],
            "rotate 270" => [ 6 ],
            "rotate 90"  => [ 8 ],
        ];
    }


    public function testResizeCropWithUnreadableImage(): void {
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "cropped-broken.png";
        $result  = $this->runWithSuppressedWarnings(
            fn() => Image::resizeCrop($this->files["truncated"], $dstPath, 20, 20, 0, 0, 10, 10),
            suppress: true,
        );
        $this->assertFalse($result);
    }


    /**
     * Injects an EXIF APP1 segment with the given Orientation into a JPEG
     */
    private function writeExifOrientation(string $path, int $orientation): void {
        $tiff  = "II*\x00\x08\x00\x00\x00";           // little endian, IFD0 at offset 8
        $tiff .= "\x01\x00";                          // 1 entry
        $tiff .= "\x12\x01";                          // tag 0x0112 (Orientation)
        $tiff .= "\x03\x00";                          // type SHORT
        $tiff .= "\x01\x00\x00\x00";                  // count 1
        $tiff .= pack("v", $orientation) . "\x00\x00";
        $tiff .= "\x00\x00\x00\x00";                  // no next IFD

        $exif = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack("n", strlen($exif) + 2) . $exif;
        $jpeg = (string)@file_get_contents($path);

        @file_put_contents($path, "\xFF\xD8" . $app1 . substr($jpeg, 2));
    }

    private function getFixtureUrl(string $token): string {
        $path = $this->files[$token] ?? $this->files["missing"];
        return "http://image.test/" . basename($path);
    }


    /**
     * A part of an Image hidden behind the pixelate filter, given in percentages
     * @param string $srcToken
     * @param int    $x
     * @param int    $y
     * @param int    $width
     * @param int    $height
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerPixelate")]
    public function testPixelate(
        string $srcToken,
        int $x,
        int $y,
        int $width,
        int $height,
        bool $expected,
    ): void {
        $srcPath = $this->files[$srcToken] ?? "";
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "pixelated.png";

        $this->assertSame($expected, Image::pixelate($srcPath, $dstPath, $x, $y, $width, $height));
        $this->assertSame($expected, Storage::fileExists($dstPath));

        // The Image it wrote is one, and it is the size of the one it read
        if ($expected) {
            $this->assertSame(Image::getSize($srcPath), Image::getSize($dstPath));
        }
    }

    /**
     * The part is a percentage of the Image, and has to come out a part of it
     * @return array<string,array{string,int,int,int,int,bool}>
     */
    public static function providerPixelate(): array {
        return [
            "a part of it"        => [ "png", 20, 20, 40, 40, true ],
            "the whole of it"     => [ "png", 0, 0, 100, 100, true ],
            "past the edge"       => [ "png", 80, 80, 40, 40, true ],
            "a part of no width"  => [ "png", 20, 20, 0, 40, false ],
            "a part of no height" => [ "png", 20, 20, 40, 0, false ],
            // Everything of it is off the edge, so what is left to hide is nothing
            "a part of nothing"   => [ "png", 100, 100, 1, 1, false ],
            "a file that is not"  => [ "text", 0, 0, 40, 40, false ],
            "nothing there"       => [ "missing", 0, 0, 40, 40, false ],
        ];
    }

    public function testAPixelatedPartIsNotWhatItWas(): void {
        // A flat Image pixelates to itself, so this one has a dot in its corner
        $srcPath = $this->files["transparent"];
        $dstPath = $this->tmpDir . DIRECTORY_SEPARATOR . "pixelated-dot.png";

        $this->assertTrue(Image::pixelate($srcPath, $dstPath, 0, 0, 50, 50));
        $this->assertNotSame(Storage::readFile($srcPath), Storage::readFile($dstPath));
    }


    /**
     * Returns an Image of the given colour, with one pixel of another at 1,1
     * @param array{int,int,int}      $fill
     * @param array{int,int,int}|null $dot  Optional.
     * @return GdImage
     */
    private function makeImage(array $fill, ?array $dot = null): GdImage {
        $image = imagecreatetruecolor(4, 4);
        $this->assertNotFalse($image);

        $fillColor = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
        $this->assertNotFalse($fillColor);
        imagefilledrectangle($image, 0, 0, 3, 3, $fillColor);

        if ($dot !== null) {
            $dotColor = imagecolorallocate($image, $dot[0], $dot[1], $dot[2]);
            $this->assertNotFalse($dotColor);
            imagesetpixel($image, 1, 1, $dotColor);
        }
        return $image;
    }

    /**
     * The Luminance of a pixel, which the green weighs the most in
     * @param array{int,int,int} $color
     * @param float              $expected
     * @return void
     */
    #[DataProvider("providerGetLuma")]
    public function testGetLuma(array $color, float $expected): void {
        $this->assertSame($expected, Image::getLuma($this->makeImage($color), 0, 0));
    }

    /**
     * @return array<string,array{array{int,int,int},float}>
     */
    public static function providerGetLuma(): array {
        return [
            "white"   => [ [ 255, 255, 255 ], 255.0 ],
            "black"   => [ [ 0, 0, 0 ], 0.0 ],
            // The green counts six times, the red three and the blue once
            "red"     => [ [ 255, 0, 0 ], 76.5 ],
            "green"   => [ [ 0, 255, 0 ], 153.0 ],
            "blue"    => [ [ 0, 0, 255 ], 25.5 ],
            "a grey"  => [ [ 100, 100, 100 ], 100.0 ],
        ];
    }

    public function testAPixelOutsideTheImageIsAsLightAsItGets(): void {
        // Nothing is there to be dark, so nothing is taken for ink
        $image = $this->makeImage([ 0, 0, 0 ]);
        $luma  = $this->runWithSuppressedWarnings(fn() => Image::getLuma($image, 40, 40), true);

        $this->assertSame(255.0, $luma);
    }


    /**
     * A part of an Image that has a pixel dark enough to be ink
     * @param array{int,int,int}      $fill
     * @param array{int,int,int}|null $dot
     * @param int                     $fromX
     * @param int                     $toX
     * @param bool                    $expected
     * @return void
     */
    #[DataProvider("providerHasInk")]
    public function testHasInk(
        array $fill,
        ?array $dot,
        int $fromX,
        int $toX,
        bool $expected,
    ): void {
        $image = $this->makeImage($fill, $dot);

        $this->assertSame($expected, Image::hasInk($image, $fromX, $toX, 0, 3));
    }

    /**
     * The dot is at 1,1, so a part that starts past it does not hold it
     * @return array<string,array{array{int,int,int},array{int,int,int}|null,int,int,bool}>
     */
    public static function providerHasInk(): array {
        $white = [ 255, 255, 255 ];
        $black = [ 0, 0, 0 ];

        return [
            "nothing but white"    => [ $white, null, 0, 3, false ],
            "a black dot in it"    => [ $white, $black, 0, 3, true ],
            "the dot is past it"   => [ $white, $black, 2, 3, false ],
            "the dot is the start" => [ $white, $black, 1, 1, true ],
            "all of it is black"   => [ $black, null, 0, 3, true ],
            // A red is 76 and under the 120 it takes, a green is 153 and over it
            "a red dot counts"     => [ $white, [ 255, 0, 0 ], 0, 3, true ],
            "a green dot does not" => [ $white, [ 0, 255, 0 ], 0, 3, false ],
        ];
    }

    public function testHowDarkTheInkHasToBe(): void {
        $image = $this->makeImage([ 255, 255, 255 ], [ 0, 255, 0 ]);

        // The green dot is at 153, so it is ink to anything asking for more
        $this->assertFalse(Image::hasInk($image, 0, 3, 0, 3));
        $this->assertTrue(Image::hasInk($image, 0, 3, 0, 3, maxLuma: 200));

        // And the white around it is 255, which nothing under that reaches
        $this->assertFalse(Image::hasInk($image, 2, 3, 0, 3, maxLuma: 200));
        $this->assertTrue(Image::hasInk($image, 2, 3, 0, 3, maxLuma: 256));
    }
}
