<?php
namespace Tests\File;

use Framework\File\Storage;
use Framework\File\File;
use Framework\File\Image;
use Framework\File\MediaFile;
use Framework\File\Type\MediaType;
use Framework\System\Path;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MediaFileTest extends TestCase {
    use TestHelpers;

    private int $mediaID = 0;

    /** @var list<string> */
    private array $tempFiles = [];

    /** @var array<string,string> */
    private array $uploadSources = [];


    protected function setUp(): void {
        $this->mediaID = random_int(100000, 999999);
        MediaFile::setID($this->mediaID);
        $GLOBALS["test_move_uploaded_file"] = false;

        $this->createFixtures();
        $this->createUploadFixtures();
    }

    protected function tearDown(): void {
        Storage::deleteDir(Path::getSourcePath($this->mediaID));
        Storage::deleteDir(Path::getThumbsPath($this->mediaID));

        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        unset($GLOBALS["test_move_uploaded_file"]);
    }


    #[DataProvider("providerSetID")]
    public function testSetID(int $id, int $expected): void {
        MediaFile::setID($id);
        $this->assertSame($expected, $this->getPrivateStaticProperty(MediaFile::class, "id"));
    }

    public static function providerSetID(): array {
        return [
            "zero"     => [ 0, 0 ],
            "positive" => [ 321, 321 ],
            "negative" => [ -123, 0 ],
        ];
    }


    /**
     * A flat Media keeps its files in the source itself, without the folder of the ID
     * @param string $method
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerFlatMedia")]
    public function testAFlatMediaHasNoFolderOfTheID(string $method, string $expected): void {
        $this->setConfig("FILE_FLAT_MEDIA", true);
        try {
            $result = MediaFile::$method("album", "cover.png");
        } finally {
            $this->setConfig("FILE_FLAT_MEDIA", false);
        }
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerFlatMedia(): array {
        return [
            "the path"         => [ "getPath", Path::getSourcePath("album", "cover.png") ],
            "the thumb path"   => [ "getThumbPath", Path::getThumbsPath("album", "cover.png") ],
            "the url"          => [ "getUrl", Path::getSourceUrl("album", "cover.png") ],
            "the thumb url"    => [ "getThumbUrl", Path::getThumbsUrl("album", "cover.png") ],
            "the shared url"   => [ "getSharedUrl", Path::getSourceUrl("album", "cover.png") ],
            "the shared thumb" => [ "getSharedThumbUrl", Path::getThumbsUrl("album", "cover.png") ],
        ];
    }

    public function testTheSharedUrlIsTheOneOfTheFirstID(): void {
        // Every ID shares the Media of the first one, which a File Field points at
        $this->assertSame(Path::getSourceUrl(0, "cover.png"), MediaFile::getSharedUrl("cover.png"));
        $this->assertSame(Path::getThumbsUrl(0, "cover.png"), MediaFile::getSharedThumbUrl("cover.png"));
    }


    #[DataProvider("providerGetPath")]
    public function testGetPath(array $pathParts): void {
        $this->assertSame(
            Path::getSourcePath($this->mediaID, ...$pathParts),
            MediaFile::getPath(...$pathParts),
        );
    }

    public static function providerGetPath(): array {
        return [
            "empty"    => [ [] ],
            "single"   => [ [ "album" ] ],
            "multiple" => [ [ "album", "cover.png" ] ],
            "mixed"    => [ [ "album", 12, "cover.png" ] ],
        ];
    }


    #[DataProvider("providerGetThumbPath")]
    public function testGetThumbPath(array $pathParts): void {
        $this->assertSame(
            Path::getThumbsPath($this->mediaID, ...$pathParts),
            MediaFile::getThumbPath(...$pathParts),
        );
    }

    public static function providerGetThumbPath(): array {
        return [
            "empty"    => [ [] ],
            "single"   => [ [ "album" ] ],
            "multiple" => [ [ "album", "cover.png" ] ],
            "mixed"    => [ [ "album", 12, "cover.png" ] ],
        ];
    }


    #[DataProvider("providerGetUrl")]
    public function testGetUrl(array $pathParts): void {
        $this->assertSame(
            Path::getSourceUrl($this->mediaID, ...$pathParts),
            MediaFile::getUrl(...$pathParts),
        );
    }

    public static function providerGetUrl(): array {
        return [
            "empty"    => [ [] ],
            "single"   => [ [ "album" ] ],
            "multiple" => [ [ "album", "cover.png" ] ],
            "mixed"    => [ [ "album", 12, "cover.png" ] ],
        ];
    }


    #[DataProvider("providerGetThumbUrl")]
    public function testGetThumbUrl(array $pathParts): void {
        $this->assertSame(
            Path::getThumbsUrl($this->mediaID, ...$pathParts),
            MediaFile::getThumbUrl(...$pathParts),
        );
    }

    public static function providerGetThumbUrl(): array {
        return [
            "empty"    => [ [] ],
            "single"   => [ [ "album" ] ],
            "multiple" => [ [ "album", "cover.png" ] ],
            "mixed"    => [ [ "album", 12, "cover.png" ] ],
        ];
    }


    #[DataProvider("providerExists")]
    public function testExists(array $pathParts, bool $expected): void {
        $this->assertSame($expected, MediaFile::exists(...$pathParts));
    }

    public static function providerExists(): array {
        return [
            "root file"   => [ [ "library", "note.txt" ], true ],
            "nested file" => [ [ "library", "album", "cover.png" ], true ],
            "missing"     => [ [ "missing.txt" ], false ],
        ];
    }


    #[DataProvider("providerGetList")]
    public function testGetList(
        string $mediaType,
        string $path,
        string $basePath,
        array $expectedNames,
        string $expectedPath,
    ): void {
        $result = MediaFile::getList($mediaType, $path, $basePath);
        $names  = array_map(fn($item) => $item->name, $result["list"]);

        $this->assertSame($expectedPath, $result["path"]);
        $this->assertSame($expectedNames, $names);

        foreach ($result["list"] as $item) {
            if ($item->name === "...") {
                $this->assertTrue($item->isBack);
            }
            if ($item->name === "album") {
                $this->assertTrue($item->isDir);
            }
            if ($item->name === "photo.png") {
                $this->assertTrue($item->isImage);
                $this->assertSame(40, $item->width);
                $this->assertSame(20, $item->height);
            }
        }
    }

    public static function providerGetList(): array {
        return [
            "all root"       => [ MediaType::Any, "", "library", [ "album", "note.txt", "photo.png" ], "" ],
            "images only"    => [ MediaType::Image, "", "library", [ "album", "photo.png" ], "" ],
            "subdir with up" => [ MediaType::Any, "/album", "library", [ "...", "cover.png" ], "album" ],
            "invalid path"   => [ MediaType::Any, "/missing", "library", [ "album", "note.txt", "photo.png" ], "" ],
        ];
    }


    #[DataProvider("providerCreateDir")]
    public function testCreateDir(string $path, bool $expected): void {
        $result = MediaFile::createDir($path);

        $this->assertSame($expected, $result);
        $this->assertDirectoryExists(Path::getSourcePath($this->mediaID, $path));
        $this->assertDirectoryExists(Path::getThumbsPath($this->mediaID, $path));
    }

    public static function providerCreateDir(): array {
        return [
            "new dir"      => [ "new-folder", true ],
            "existing dir" => [ "existing-folder", false ],
        ];
    }


    #[DataProvider("providerUploadFile")]
    public function testUploadFile(
        string $uploadKey,
        string $fileName,
        array $pathParts,
        bool $expected,
        bool $expectThumb,
    ): void {
        $GLOBALS["test_move_uploaded_file"] = true;

        $tmpName = $this->uploadSources[$uploadKey];
        $file = new File(fileRequest: [
            "name"     => $fileName,
            "type"     => "",
            "tmp_name" => $tmpName,
            "error"    => 0,
            "size"     => is_file($tmpName) ? filesize($tmpName) : 0,
        ]);

        $result   = MediaFile::uploadFile($file, ...$pathParts);
        $filePath = [ ...$pathParts, $fileName ];

        $sourcePath = Path::getSourcePath($this->mediaID, ...$filePath);
        $thumbPath  = Path::getThumbsPath($this->mediaID, ...$filePath);

        $this->assertSame($expected, $result);
        $this->assertSame($expected, Storage::fileExists($sourcePath));
        $this->assertSame($expectThumb, Storage::fileExists($thumbPath));
    }

    public static function providerUploadFile(): array {
        return [
            "text"        => [ "text", "upload.txt", [ "uploads" ], true, false ],
            "image"       => [ "image", "upload.png", [ "uploads" ], true, true ],
            "missing tmp" => [ "missing", "missing.txt", [ "uploads" ], false, false ],
        ];
    }


    #[DataProvider("providerDeletePath")]
    public function testDeletePath(array $pathParts, bool $expected): void {
        $sourcePath = Path::getSourcePath($this->mediaID, ...$pathParts);
        $thumbPath  = Path::getThumbsPath($this->mediaID, ...$pathParts);

        $result = MediaFile::deletePath(...$pathParts);

        $this->assertSame($expected, $result);
        $this->assertFalse(Storage::fileExists($sourcePath));
        $this->assertFalse(Storage::fileExists($thumbPath));
    }

    public static function providerDeletePath(): array {
        return [
            "file"      => [ [ "delete-me.txt" ], true ],
            "directory" => [ [ "delete-folder" ], true ],
            "missing"   => [ [ "missing.txt" ], true ],
        ];
    }


    #[DataProvider("providerRenamePath")]
    public function testRenamePath(string $path, string $oldName, string $newName, bool $expected, bool $expectThumb): void {
        $oldSource = Path::getSourcePath($this->mediaID, $path, $oldName);
        $newSource = Path::getSourcePath($this->mediaID, $path, $newName);
        $oldThumb  = Path::getThumbsPath($this->mediaID, $path, $oldName);
        $newThumb  = Path::getThumbsPath($this->mediaID, $path, $newName);

        $result = $this->runWithSuppressedWarnings(
            fn() => MediaFile::renamePath($path, $oldName, $newName),
            suppress: !$expected,
        );

        $this->assertSame($expected, $result);
        $this->assertFalse(Storage::fileExists($oldSource));
        $this->assertSame($expected, Storage::fileExists($newSource));
        $this->assertFalse(Storage::fileExists($oldThumb));
        $this->assertSame($expectThumb, Storage::fileExists($newThumb));
    }

    public static function providerRenamePath(): array {
        return [
            "text"    => [ "", "rename-me.txt", "renamed.txt", true, false ],
            "image"   => [ "", "rename-photo.png", "renamed.png", true, true ],
            "missing" => [ "", "missing.txt", "renamed.txt", false, false ],
        ];
    }


    /**
     * A File moved without its Thumb gets one made at its new place
     * @param string $action
     * @return void
     */
    #[DataProvider("providerMissingThumb")]
    public function testAMissingThumbIsMadeAtTheNewPlace(string $action): void {
        $this->createImageWithThumb(
            Path::getSourcePath($this->mediaID, "from", "bare.png"),
            Path::getThumbsPath($this->mediaID, "from", "bare.png"),
            50,
            25,
        );
        Storage::deleteFile(Path::getThumbsPath($this->mediaID, "from", "bare.png"));

        $result = $action === "rename"
            ? MediaFile::renamePath("from", "bare.png", "named.png")
            : MediaFile::movePath("from", "to", "bare.png");
        $newPath = $action === "rename" ? [ "from", "named.png" ] : [ "to", "bare.png" ];

        $this->assertTrue($result);
        $this->assertTrue(Storage::fileExists(Path::getSourcePath($this->mediaID, ...$newPath)));
        $this->assertTrue(Storage::fileExists(Path::getThumbsPath($this->mediaID, ...$newPath)));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerMissingThumb(): array {
        return [
            "renamed" => [ "rename" ],
            "moved"   => [ "move" ],
        ];
    }


    /**
     * A path that came in a request can not reach out of the Media
     * @param list<string> $pathParts
     * @param list<string> $expected
     * @return void
     */
    #[DataProvider("providerCleanPath")]
    public function testAPathCanNotGoUp(array $pathParts, array $expected): void {
        $this->assertSame(
            Path::getSourcePath($this->mediaID, ...$expected),
            MediaFile::getPath(...$pathParts),
        );
    }

    /**
     * @return array<string,array{list<string>,list<string>}>
     */
    public static function providerCleanPath(): array {
        return [
            "going up"       => [ [ "..", "secret.txt" ], [ "secret.txt" ] ],
            "going up twice" => [ [ "../../etc/passwd" ], [ "etc", "passwd" ] ],
            "inside a path"  => [ [ "album/../cover.png" ], [ "album", "cover.png" ] ],
            "staying"        => [ [ "./album", "cover.png" ], [ "album", "cover.png" ] ],
            "a plain one"    => [ [ "album", "cover.png" ], [ "album", "cover.png" ] ],
        ];
    }


    #[DataProvider("providerMovePath")]
    public function testMovePath(string $oldPath, string $newPath, string $name, bool $expected, bool $expectThumb): void {
        $oldSource = Path::getSourcePath($this->mediaID, $oldPath, $name);
        $newSource = Path::getSourcePath($this->mediaID, $newPath, $name);
        $oldThumb  = Path::getThumbsPath($this->mediaID, $oldPath, $name);
        $newThumb  = Path::getThumbsPath($this->mediaID, $newPath, $name);

        $result = $this->runWithSuppressedWarnings(
            fn() => MediaFile::movePath($oldPath, $newPath, $name),
            suppress: !$expected,
        );

        $this->assertSame($expected, $result);
        $this->assertFalse(Storage::fileExists($oldSource));
        $this->assertSame($expected, Storage::fileExists($newSource));
        $this->assertFalse(Storage::fileExists($oldThumb));
        $this->assertSame($expectThumb, Storage::fileExists($newThumb));
    }

    public static function providerMovePath(): array {
        return [
            "text"    => [ "from", "to", "move-me.txt", true, false ],
            "image"   => [ "from", "to", "move-photo.png", true, true ],
            "missing" => [ "from", "to", "missing.txt", false, false ],
        ];
    }


    private function createFixtures(): void {
        MediaFile::createDir();
        MediaFile::createDir("library");
        MediaFile::createDir("library", "album");
        MediaFile::createDir("existing-folder");
        MediaFile::createDir("delete-folder");
        MediaFile::createDir("from");
        MediaFile::createDir("to");
        MediaFile::createDir("uploads");

        $this->createTextFile(Path::getSourcePath($this->mediaID, "library", "note.txt"));
        $this->createImageWithThumb(
            Path::getSourcePath($this->mediaID, "library", "photo.png"),
            Path::getThumbsPath($this->mediaID, "library", "photo.png"),
            40,
            20,
        );
        $this->createImageWithThumb(
            Path::getSourcePath($this->mediaID, "library", "album", "cover.png"),
            Path::getThumbsPath($this->mediaID, "library", "album", "cover.png"),
            30,
            15,
        );

        $this->createTextFile(Path::getSourcePath($this->mediaID, "delete-me.txt"));
        $this->createTextFile(Path::getSourcePath($this->mediaID, "delete-folder", "note.txt"));
        $this->createTextFile(Path::getThumbsPath($this->mediaID, "delete-folder", "note.txt"));

        $this->createTextFile(Path::getSourcePath($this->mediaID, "rename-me.txt"));
        $this->createImageWithThumb(
            Path::getSourcePath($this->mediaID, "rename-photo.png"),
            Path::getThumbsPath($this->mediaID, "rename-photo.png"),
            60,
            30,
        );

        $this->createTextFile(Path::getSourcePath($this->mediaID, "from", "move-me.txt"));
        $this->createImageWithThumb(
            Path::getSourcePath($this->mediaID, "from", "move-photo.png"),
            Path::getThumbsPath($this->mediaID, "from", "move-photo.png"),
            50,
            25,
        );
    }

    private function createUploadFixtures(): void {
        $this->uploadSources["text"] = $this->getTempFilePath("upload-source.txt");
        $this->uploadSources["image"] = $this->getTempFilePath("upload-source.png");
        $this->uploadSources["missing"] = $this->getTempFilePath("missing.tmp");

        Storage::writeFile($this->uploadSources["text"], "upload");
        $this->writeFixtureImage($this->uploadSources["image"], 3, 50, 25);
    }

    private function createTextFile(string $path, string $content = "content"): void {
        Storage::createDir(Storage::getDirectory($path));
        Storage::writeFile($path, $content);
    }

    private function createImageFile(string $path, int $width, int $height): void {
        Storage::createDir(Storage::getDirectory($path));
        $this->writeFixtureImage($path, 3, $width, $height);
    }

    private function createImageWithThumb(string $sourcePath, string $thumbPath, int $width, int $height): void {
        $this->createImageFile($sourcePath, $width, $height);
        Storage::createDir(Storage::getDirectory($thumbPath));
        Image::resize($sourcePath, $thumbPath, 200, 200, Image::Resize);
    }

    private function getTempFilePath(string $name): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "media_file_test_" . uniqid() . "_" . $name;
        $this->tempFiles[] = $path;
        return $path;
    }
}
