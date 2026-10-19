<?php
namespace Tests\Database;

use Framework\Application;
use Framework\Database\Migration\Migration;
use Framework\Date\Date;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Migration, in what it does without a Database
 */
class MigrationTest extends TestCase {

    use TestHelpers;

    private mixed $migrationsPath = null;
    private mixed $dataDir        = null;


    protected function setUp(): void {
        parent::setUp();
        $this->migrationsPath = $this->getPrivateStaticProperty(Migration::class, "migrationsPath");
        $this->dataDir        = $this->getPrivateStaticProperty(Migration::class, "dataDir");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Migration::class, "migrationsPath", $this->migrationsPath);
        $this->setPrivateStaticProperty(Migration::class, "dataDir", $this->dataDir);
    }



    /**
     * @param string       $migrationsPath
     * @param list<string> $pathParts
     * @param string       $expected
     */
    #[DataProvider("providerGetMediaPath")]
    public function testTheMediaIsInsideTheMigrations(
        string $migrationsPath,
        array $pathParts,
        string $expected,
    ): void {
        Migration::setPath($migrationsPath);

        $this->assertSame(Application::getBasePath($expected), Migration::getMediaPath(...$pathParts));
    }

    /**
     * @return array<string,array{string,list<string>,string}>
     */
    public static function providerGetMediaPath(): array {
        return [
            "the folder"  => [ "config/migrations", [], "config/migrations/media" ],
            "a file"      => [ "config/migrations", [ "logos", "a.svg" ], "config/migrations/media/logos/a.svg" ],
            "a file path" => [ "config/other", [ "logos/a.svg" ], "config/other/media/logos/a.svg" ],
        ];
    }


    /**
     * @param string $dataDir
     * @param bool   $dev
     * @param string $expected
     */
    #[DataProvider("providerSetDataDir")]
    public function testANewMigrationGoesInTheDataDir(string $dataDir, bool $dev, string $expected): void {
        Migration::setDataDir($dataDir);

        $dirName = $this->callPrivateStaticMethod(Migration::class, "getNewDirName", Date::create("2026-10-19"), $dev);
        $this->assertSame($expected, $dirName);
    }

    /**
     * @return array<string,array{string,bool,string}>
     */
    public static function providerSetDataDir(): array {
        return [
            "without one" => [ "", false, "2026/10" ],
            "with one"    => [ "data", false, "data/2026/10" ],
            "a dev one"   => [ "data", true, "dev" ],
        ];
    }
}
