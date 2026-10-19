<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Database\Migration\Migration;
use Framework\Database\Migration\MediaMigration;
use Framework\File\File;
use Framework\File\Storage;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use Exception;

/**
 * The Media Migration, with an uploader that keeps what it was given
 */
class MediaMigrationTest extends TestCase {

    use TestHelpers;

    private const FixtureDir = "tests/Database/.tmp_media";

    private mixed $migrationsPath = null;
    private mixed $uploader       = null;

    /** @var list<array{path:string,name:string,content:string}> */
    private array $uploaded = [];


    protected function setUp(): void {
        parent::setUp();
        $this->migrationsPath = $this->getPrivateStaticProperty(Migration::class, "migrationsPath");
        $this->uploader       = $this->getPrivateStaticProperty(MediaMigration::class, "uploader");
        $this->uploaded       = [];

        Storage::createDir(Application::getBasePath(self::FixtureDir, "media", "logos"));
        Storage::writeFile(Application::getBasePath(self::FixtureDir, "media", "logos", "a.svg"), "<svg/>");
        Storage::writeFile(Application::getBasePath(self::FixtureDir, "media", "root.txt"), "root");
        Migration::setPath(self::FixtureDir);
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Migration::class, "migrationsPath", $this->migrationsPath);
        $this->setPrivateStaticProperty(MediaMigration::class, "uploader", $this->uploader);
        Storage::deleteDir(Application::getBasePath(self::FixtureDir));
    }

    /**
     * Sets an uploader that keeps each File it gets and answers if it was uploaded
     * @param bool $isUploaded
     * @return void
     */
    private function setUploader(bool $isUploaded): void {
        MediaMigration::setUploader(function (File $file, string $path) use ($isUploaded): bool {
            $this->uploaded[] = [
                "path"    => $path,
                "name"    => $file->getName(),
                "content" => (string)file_get_contents($file->getTmpName()),
            ];
            return $isUploaded;
        });
    }

    /**
     * Uploads the given Files and returns what it printed
     * @param string ...$filePaths
     * @return string
     */
    private function upload(string ...$filePaths): string {
        ob_start();
        try {
            MediaMigration::upload(...$filePaths);
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }



    /**
     * @param list<string>                                          $filePaths
     * @param list<array{path:string,name:string,content:string}> $uploaded
     * @param string                                                $printed
     */
    #[DataProvider("providerUpload")]
    public function testTheFilesAreUploadedToTheirPath(
        array $filePaths,
        array $uploaded,
        string $printed,
    ): void {
        $this->setUploader(isUploaded: true);

        $output = $this->upload(...$filePaths);

        $this->assertSame($uploaded, $this->uploaded);
        $this->assertStringContainsString($printed, $output);
    }

    /**
     * @return array<string,array{list<string>,list<array{path:string,name:string,content:string}>,string}>
     */
    public static function providerUpload(): array {
        $logo = [ "path" => "logos", "name" => "a.svg", "content" => "<svg/>" ];
        $root = [ "path" => "", "name" => "root.txt", "content" => "root" ];
        return [
            "in a folder" => [ [ "logos/a.svg" ], [ $logo ], "Uploaded logos/a.svg" ],
            "at the root" => [ [ "root.txt" ], [ $root ], "Uploaded root.txt" ],
            "missing"     => [ [ "logos/b.svg" ], [], "Skipped logos/b.svg" ],
            "several"     => [ [ "logos/b.svg", "root.txt" ], [ $root ], "Uploaded root.txt" ],
        ];
    }

    public function testAFailedUploadStopsTheMigration(): void {
        $this->setUploader(isUploaded: false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("The File logos/a.svg was not uploaded");
        $this->upload("logos/a.svg", "root.txt");
    }

    public function testItStopsWithoutAnUploader(): void {
        $this->setPrivateStaticProperty(MediaMigration::class, "uploader", null);

        $this->expectException(Exception::class);
        $this->upload("logos/a.svg");
    }
}
