<?php
// spell-checker: ignore  msvideo, flac, quicktime, openxmlformats, mpga, officedocument,
// spell-checker: ignore  msword, wordprocessingml, presentationml, spreadsheetml
namespace Tests\File;

use Framework\File\FileType;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FileTypeTest extends TestCase {

    protected string $tmpDir = "";
    protected string $tmpFile = "";


    protected function setUp(): void {
        $tmpBase = sys_get_temp_dir();
        $this->tmpDir  = $tmpBase . DIRECTORY_SEPARATOR . "ft_test_dir_" . uniqid();
        $this->tmpFile = $tmpBase . DIRECTORY_SEPARATOR . "ft_test_file_" . uniqid() . ".txt";

        @mkdir($this->tmpDir);
        @file_put_contents($this->tmpFile, "hello");
    }

    protected function tearDown(): void {
        if ($this->tmpFile !== "" && file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
        if ($this->tmpDir !== "" && file_exists($this->tmpDir)) {
            @rmdir($this->tmpDir);
        }
    }


    #[DataProvider("providerIsDir")]
    public function testIsDir(string $input, bool $expected): void {
        if ($input === "tmpDir") {
            $value = $this->tmpDir;
        } elseif ($input === "tmpFile") {
            $value = $this->tmpFile;
        } else {
            $value = $input;
        }
        $this->assertSame($expected, FileType::isDir($value));
    }

    public static function providerIsDir(): array {
        return [
            "tmp dir"      => [ "tmpDir", true ],
            "tmp file"     => [ "tmpFile", false ],
            "non existent" => [ "/path/that/does/not/exist", false ],
        ];
    }


    #[DataProvider("providerIsHidden")]
    public function testIsHidden(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isHidden($input));
    }

    public static function providerIsHidden(): array {
        return [
            "hidden"      => [ ".hidden", true ],
            "visible"     => [ "visible.txt", false ],
            "hidden file" => [ ".hidden.txt", true ],
            "empty"       => [ "", false ],
        ];
    }


    #[DataProvider("providerIsImage")]
    public function testIsImage(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isImage($input));
    }

    public static function providerIsImage(): array {
        return [
            "jpg"    => [ "image.jpg", true ],
            "jpeg"   => [ "photo.jpeg", true ],
            "gif"    => [ "graphic.gif", true ],
            "png"    => [ "photo.png", true ],
            "ico"    => [ "icon.ico", true ],
            "avif"   => [ "picture.avif", true ],
            "webp"   => [ "image.webp", true ],
            // An SVG is drawn rather than resized, so it is not one of the images
            "svg"    => [ "logo.svg", false ],

            "pdf"    => [ "document.pdf", false ],
            "zip"    => [ "archive.zip", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsPNG")]
    public function testIsPNG(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isPNG($input));
    }

    public static function providerIsPNG(): array {
        return [
            "png"    => [ "photo.png", true ],
            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsSVG")]
    public function testIsSVG(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isSVG($input));
    }

    public static function providerIsSVG(): array {
        return [
            "svg"       => [ "logo.svg", true ],
            "uppercase" => [ "LOGO.SVG", true ],
            "in a path" => [ "/files/brand/logo.svg", true ],
            "png"       => [ "photo.png", false ],
            "svg first" => [ "logo.svg.png", false ],
            "no ext"    => [ "svg", false ],
            "empty"     => [ "", false ],
        ];
    }


    #[DataProvider("providerIsICO")]
    public function testIsICO(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isICO($input));
    }

    public static function providerIsICO(): array {
        return [
            "ico"    => [ "icon.ico", true ],
            "png"    => [ "photo.png", false ],
            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsVideo")]
    public function testIsVideo(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isVideo($input));
    }

    public static function providerIsVideo(): array {
        return [
            "mov"    => [ "film.mov", true ],
            "mpeg"   => [ "clip.mpeg", true ],
            "m4v"    => [ "movie.m4v", true ],
            "mp4"    => [ "video.mp4", true ],
            "avi"    => [ "movie.avi", true ],
            "mpg"    => [ "video.mpg", true ],
            "wma"    => [ "audio.wma", true ],
            "flv"    => [ "animation.flv", true ],
            "webm"   => [ "video.webm", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsAudio")]
    public function testIsAudio(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isAudio($input));
    }

    public static function providerIsAudio(): array {
        return [
            "mp3"    => [ "music.mp3", true ],
            "mpga"   => [ "audio.mpga", true ],
            "m4a"    => [ "song.m4a", true ],
            "ac3"    => [ "sound.ac3", true ],
            "aiff"   => [ "track.aiff", true ],
            "mid"    => [ "music.mid", true ],
            "ogg"    => [ "audio.ogg", true ],
            "wav"    => [ "sound.wav", true ],
            "weba"   => [ "sound.weba", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsCode")]
    public function testIsCode(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isCode($input));
    }

    public static function providerIsCode(): array {
        return [
            "html"   => [ "index.html", true ],
            "xhtml"  => [ "page.xhtml", true ],
            "sql"    => [ "query.sql", true ],
            "xml"    => [ "data.xml", true ],
            "js"     => [ "app.js", true ],
            "json"   => [ "config.json", true ],
            "css"    => [ "style.css", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsText")]
    public function testIsText(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isText($input));
    }

    public static function providerIsText(): array {
        return [
            "txt"    => [ "notes.txt", true ],
            "md"     => [ "readme.md", true ],
            "csv"    => [ "data.csv", true ],
            "log"    => [ "app.log", true ],
            "rtf"    => [ "document.rtf", true ],
            "json"   => [ "config.json", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsDocument")]
    public function testIsDocument(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isDocument($input));
    }

    public static function providerIsDocument(): array {
        return [
            "doc"    => [ "document.doc", true ],
            "docx"   => [ "report.docx", true ],
            "odt"    => [ "notes.odt", true ],
            "ott"    => [ "document.ott", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsSpreadsheet")]
    public function testIsSpreadsheet(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isSpreadsheet($input));
    }

    public static function providerIsSpreadsheet(): array {
        return [
            "xls"    => [ "data.xls", true ],
            "xlsx"   => [ "report.xlsx", true ],
            "ods"    => [ "numbers.ods", true ],
            "ots"    => [ "data.ots", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsPresentation")]
    public function testIsPresentation(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isPresentation($input));
    }

    public static function providerIsPresentation(): array {
        return [
            "ppt"    => [ "slides.ppt", true ],
            "pptx"   => [ "presentation.pptx", true ],
            "odp"    => [ "talk.odp", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsPDF")]
    public function testIsPDF(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isPDF($input));
    }

    public static function providerIsPDF(): array {
        return [
            "pdf"    => [ "document.pdf", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsZip")]
    public function testIsZip(string $input, bool $expected): void {
        $this->assertSame($expected, FileType::isZip($input));
    }

    public static function providerIsZip(): array {
        return [
            "zip"    => [ "archive.zip", true ],
            "rar"    => [ "archive.rar", true ],
            "gz"     => [ "archive.gz", true ],
            "tar"    => [ "archive.tar", true ],
            "iso"    => [ "disk.iso", true ],
            "7zip"   => [ "archive.7zip", true ],

            "jpg"    => [ "image.jpg", false ],
            "no ext" => [ "file", false ],
            "empty"  => [ "", false ],
        ];
    }


    #[DataProvider("providerIsFile")]
    public function testIsFile(string $input, bool $expected): void {
        if ($input === "tmpDir") {
            $value = $this->tmpDir;
        } else {
            $value = $input;
        }
        $this->assertSame($expected, FileType::isFile($value));
    }

    public static function providerIsFile(): array {
        return [
            "txt"   => [ "notes.txt", true ],
            "dir"   => [ "tmpDir", false ],
            "jpg"   => [ "image.jpg", false ],
            "mp4"   => [ "video.mp4", false ],
            "empty" => [ "", false ],
        ];
    }


    #[DataProvider("providerGetIcon")]
    public function testGetIcon(string $input, string $expected): void {
        $this->assertSame($expected, FileType::getIcon($input));
    }

    public static function providerGetIcon(): array {
        return [
            "image"        => [ "photo.png", "file-image" ],
            "svg"          => [ "logo.svg", "file-image" ],
            "video"        => [ "movie.mp4", "file-video" ],
            "audio"        => [ "music.mp3", "file-audio" ],
            "code"         => [ "index.html", "file-code" ],
            "text"         => [ "notes.txt", "file-text" ],
            "document"     => [ "document.docx", "file-document" ],
            "spreadsheet"  => [ "data.xlsx", "file-spreadsheet" ],
            "presentation" => [ "slides.pptx", "file-presentation" ],
            "pdf"          => [ "document.pdf", "file-pdf" ],
            "zip"          => [ "archive.zip", "file-zip" ],
            "unknown"      => [ "file.unknown", "file" ],
        ];
    }


    #[DataProvider("providerGetColor")]
    public function testGetColor(string $icon, string $expected): void {
        $this->assertSame($expected, FileType::getColor($icon));
    }

    public static function providerGetColor(): array {
        $grey = "hsl(215, 15%, 58%)";

        return [
            "a directory"       => [ "directory", "hsl(216, 90%, 58%)" ],
            "the way back"      => [ "back", "hsl(216, 90%, 58%)" ],
            "an image"          => [ "file-image", "hsl(280, 55%, 65%)" ],
            "a video"           => [ "file-video", "hsl(340, 65%, 65%)" ],
            "an audio"          => [ "file-audio", "hsl(265, 60%, 68%)" ],
            "some code"         => [ "file-code", "hsl(190, 65%, 50%)" ],
            "a document"        => [ "file-document", "hsl(235, 70%, 66%)" ],
            "a spreadsheet"     => [ "file-spreadsheet", "hsl(150, 50%, 45%)" ],
            "a presentation"    => [ "file-presentation", "hsl(25, 80%, 60%)" ],
            "a pdf"             => [ "file-pdf", "hsl(355, 70%, 62%)" ],
            "a zip"             => [ "file-zip", "hsl(45, 75%, 55%)" ],

            // The icon of a plain file is not named, so it takes the grey
            "a plain file"      => [ "file", $grey ],
            "a text file"       => [ "file-text", $grey ],
            "an icon of no one" => [ "not-an-icon", $grey ],
            "no icon at all"    => [ "", $grey ],
        ];
    }


    #[DataProvider("providerGetMimeType")]
    public function testGetMimeType(string $input, string $expected): void {
        $this->assertSame($expected, FileType::getMimeType($input));
    }

    public static function providerGetMimeType(): array {
        return [
            "jpg"            => [ "photo.jpg", "image/jpeg" ],
            "jpeg"           => [ "photo.jpeg", "image/jpeg" ],
            "png"            => [ "image.png", "image/png" ],
            "svg"            => [ "vector.svg", "image/svg+xml" ],
            "ico"            => [ "favicon.ico", "image/x-icon" ],
            "pdf"            => [ "doc.pdf", "application/pdf" ],
            "docx"           => [ "report.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document" ],
            "csv"            => [ "data.csv", "text/csv" ],
            "json"           => [ "config.json", "application/json" ],
            "zip"            => [ "archive.zip", "application/zip" ],
            "mp4"            => [ "movie.mp4", "video/mp4" ],
            "mp3"            => [ "song.mp3", "audio/mpeg" ],
            // The extension is matched case-insensitively
            "uppercase ext"  => [ "PHOTO.JPG", "image/jpeg" ],
            "path with dirs" => [ "uploads/2024/image.PNG", "image/png" ],
            // Unknown or missing extensions return an empty string
            "unknown ext"    => [ "file.xyz", "" ],
            "no extension"   => [ "README", "" ],
            "empty name"     => [ "", "" ],
        ];
    }


    #[DataProvider("providerGetExtension")]
    public function testGetExtension(string $input, string $expected): void {
        $this->assertSame($expected, FileType::getExtension($input));
    }

    public static function providerGetExtension(): array {
        return [
            "3gp"              => [ "video/3gp", "3gp" ],
            "aac"              => [ "audio/x-acc", "aac" ],
            "avi"              => [ "video/x-msvideo", "avi" ],
            "7zip"             => [ "application/x-compressed", "7zip" ],
            "bmp"              => [ "image/bmp", "bmp" ],
            "csv"              => [ "text/comma-separated-values", "csv" ],
            "doc"              => [ "application/msword", "doc" ],
            "docx"             => [ "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "docx" ],
            "flac"             => [ "audio/x-flac", "flac" ],
            "flv"              => [ "video/x-flv", "flv" ],
            "gif"              => [ "image/gif", "gif" ],
            "html"             => [ "text/html", "html" ],
            "ico"              => [ "image/x-icon", "ico" ],
            "jpeg"             => [ "image/jpeg", "jpeg" ],
            "json"             => [ "application/json", "json" ],
            "m4a"              => [ "audio/mp4", "m4a" ],
            "mov"              => [ "video/quicktime", "mov" ],
            "mp3"              => [ "audio/mpeg", "mp3" ],
            "mp4"              => [ "video/mp4", "mp4" ],
            "ogg"              => [ "application/ogg", "ogg" ],
            "pdf"              => [ "application/pdf", "pdf" ],
            "php"              => [ "application/x-httpd-php", "php" ],
            "png"              => [ "image/png", "png" ],
            "pptx"             => [ "application/vnd.openxmlformats-officedocument.presentationml.presentation", "pptx" ],
            "rar"              => [ "application/x-rar-compressed", "rar" ],
            "svg"              => [ "image/svg+xml", "svg" ],
            "txt"              => [ "text/plain", "txt" ],
            "wav"              => [ "audio/wav", "wav" ],
            "weba"             => [ "audio/webm", "weba" ],
            "webm"             => [ "video/webm", "webm" ],
            "webp"             => [ "image/webp", "webp" ],
            "woff2"            => [ "font/woff2", "woff2" ],
            "xlsx"             => [ "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "xlsx" ],
            "xml"              => [ "application/xml", "xml" ],
            "zip"              => [ "application/zip", "zip" ],
            "mime with params" => [ "text/html; charset=UTF-8", "html" ],
            "unknown"          => [ "application/not-registered", "" ],
        ];
    }
}
