<?php
namespace Tests\File;

use Framework\File\Type\MediaType;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MediaTypeTest extends TestCase {

    private string $tmpDir = "";
    private string $fixtureDir = "";


    protected function setUp(): void {
        $this->tmpDir     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "media_type_test_" . uniqid();
        $this->fixtureDir = $this->tmpDir . DIRECTORY_SEPARATOR . "folder";

        @mkdir($this->fixtureDir, 0777, true);
    }

    protected function tearDown(): void {
        if (is_dir($this->fixtureDir)) {
            @rmdir($this->fixtureDir);
        }
        if (is_dir($this->tmpDir)) {
            @rmdir($this->tmpDir);
        }
    }


    #[DataProvider("providerIsValid")]
    public function testIsValid(string $type, string $file, string $name, bool $expected): void {
        if ($file === "__DIR__") {
            $file = $this->fixtureDir;
        }
        $this->assertSame($expected, MediaType::isValid($type, $file, $name));
    }

    public static function providerIsValid(): array {
        return [
            "any visible file"   => [ MediaType::Any, "photo.png", "photo.png", true ],
            "any hidden file"    => [ MediaType::Any, ".env", ".env", false ],
            "media image"        => [ MediaType::Media, "photo.png", "photo.png", true ],
            "media video"        => [ MediaType::Media, "clip.mp4", "clip.mp4", true ],
            "media text"         => [ MediaType::Media, "notes.txt", "notes.txt", false ],
            "image"              => [ MediaType::Image, "photo.png", "photo.png", true ],
            // A logo is drawn at any size, so it can be an SVG, which an image can not
            "image svg"          => [ MediaType::Image, "logo.svg", "logo.svg", false ],
            "logo image"         => [ MediaType::Logo, "photo.png", "photo.png", true ],
            "logo svg"           => [ MediaType::Logo, "logo.svg", "logo.svg", true ],
            "logo video"         => [ MediaType::Logo, "clip.mp4", "clip.mp4", false ],
            "media svg"          => [ MediaType::Media, "logo.svg", "logo.svg", false ],
            "video"              => [ MediaType::Video, "clip.mp4", "clip.mp4", true ],
            "audio"              => [ MediaType::Audio, "track.mp3", "track.mp3", true ],
            "text"               => [ MediaType::Text, "notes.txt", "notes.txt", true ],
            "pdf"                => [ MediaType::PDF, "manual.pdf", "manual.pdf", true ],
            "file type"          => [ MediaType::File, "archive.bin", "archive.bin", true ],
            "directory fallback" => [ MediaType::Image, "__DIR__", "folder", true ],
            "unknown type"       => [ "unknown", "archive.bin", "archive.bin", false ],
        ];
    }
}
