<?php
namespace Tests\Provider;

use Framework\Provider\PDF;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The PDF Provider
 *
 * The images of a PDF are given to it as data, so what is made of a file is
 * read back here from a folder of its own, and the PDF is only asked to be one.
 */
class PDFTest extends TestCase {
    private const Svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>';

    private string $dir = "";


    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pdf_test_" . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void {
        foreach ((array)glob($this->dir . DIRECTORY_SEPARATOR . "*") as $file) {
            unlink((string)$file);
        }
        rmdir($this->dir);
    }

    /**
     * Writes a file in the folder of the test and returns its path
     * @param string $name
     * @param string $content
     * @return string
     */
    private function write(string $name, string $content): string {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Returns a white PNG of 100 by 100, with a dark square of 10 in the middle when asked
     * @param bool $withContent Optional.
     * @return string
     */
    private static function png(bool $withContent = false): string {
        $image = imagecreatetruecolor(100, 100);
        imagefilledrectangle($image, 0, 0, 99, 99, (int)imagecolorallocate($image, 255, 255, 255));
        if ($withContent) {
            imagefilledrectangle($image, 45, 45, 54, 54, (int)imagecolorallocate($image, 20, 20, 20));
        }

        ob_start();
        imagepng($image);
        return (string)ob_get_clean();
    }

    /**
     * Returns the content inside the given data uri
     * @param string $data
     * @return string
     */
    private static function contentOf(string $data): string {
        return (string)base64_decode(substr($data, (int)strpos($data, ",") + 1), true);
    }



    #[DataProvider("providerImageData")]
    public function testTheImageIsGivenAsData(string $name, string $content, string $mimeType): void {
        $data = PDF::getImageData($this->write($name, $content));

        $this->assertStringStartsWith("data:$mimeType;base64,", $data);
        $this->assertSame($content, self::contentOf($data));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerImageData(): array {
        return [
            "a png"             => [ "logo.png", self::png(), "image/png" ],
            // Nothing can read the type out of an SVG, so its extension says it
            "an svg"            => [ "logo.svg", self::Svg, "image/svg+xml" ],
            "an svg in capital" => [ "LOGO.SVG", self::Svg, "image/svg+xml" ],
        ];
    }

    public function testWithNoImageThereIsNoData(): void {
        $this->assertSame("", PDF::getImageData(""));
        $this->assertSame("", PDF::getCroppedImageData(""));
    }

    public function testTheImageIsCroppedToItsContent(): void {
        $data  = PDF::getCroppedImageData($this->write("logo.png", self::png(withContent: true)));
        $image = imagecreatefromstring(self::contentOf($data));

        // The square and its padding are smaller than half of the image, which is
        // the least it is cropped to
        $this->assertStringStartsWith("data:image/png;base64,", $data);
        $this->assertNotFalse($image);
        $this->assertSame(50, imagesx($image));
        $this->assertSame(50, imagesy($image));
    }

    public function testAnImageWithNoContentIsLeftWhole(): void {
        $content = self::png();
        $data    = PDF::getCroppedImageData($this->write("logo.png", $content));

        $this->assertSame($content, self::contentOf($data));
    }

    public function testAnSvgIsLeftWhole(): void {
        // It is not an image that can be opened, so there is nothing to crop, and
        // trying to open it would warn on every PDF that has one
        $path     = $this->write("logo.svg", self::Svg);
        $warnings = [];
        set_error_handler(static function (int $code, string $text) use (&$warnings): bool {
            $warnings[] = $text;
            return true;
        });
        try {
            $data = PDF::getCroppedImageData($path);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertStringStartsWith("data:image/svg+xml;base64,", $data);
        $this->assertSame(self::Svg, self::contentOf($data));
    }

    public function testThePdfIsCreated(): void {
        $pdf = PDF::create("<p>Hello</p>", title: "A title", withPageNumbers: true);

        $this->assertStringStartsWith("%PDF-", $pdf);
        $this->assertStringContainsString("%%EOF", $pdf);
    }
}
