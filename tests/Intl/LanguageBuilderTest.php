<?php
namespace Tests\Intl;

use Framework\Application;
use Framework\Intl\IntlConfig;
use Framework\Intl\LanguageBuilder;
use Framework\File\Storage;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class LanguageBuilderTest extends TestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Intl/.tmp_lang_builder";

    private string $fixtureBase = "";

    /** @var array<string,mixed> */
    private array $original = [];


    protected function setUp(): void {
        foreach ([ "defaultLanguage", "stringsDir" ] as $prop) {
            $this->original[$prop] = $this->getPrivateStaticProperty(IntlConfig::class, $prop);
        }

        $this->fixtureBase = Application::getBasePath(self::FixtureDir);
        Storage::createDir($this->fixtureBase);
        IntlConfig::setStringsDir(self::FixtureDir);
    }

    protected function tearDown(): void {
        foreach ($this->original as $prop => $value) {
            $this->setPrivateStaticProperty(IntlConfig::class, $prop, $value);
        }
        Storage::deleteDir($this->fixtureBase);
    }

    private function writeLang(string $code, array $data): void {
        Storage::writeFile($this->fixtureBase . DIRECTORY_SEPARATOR . $code . ".json", json_encode($data));
    }


    public function testAnEmptyDirectoryHasNoStrings(): void {
        $this->assertFalse(LanguageBuilder::hasStrings());
    }

    public function testADirectoryWithoutJSONHasNoStrings(): void {
        Storage::writeFile($this->fixtureBase . DIRECTORY_SEPARATOR . "notes.txt", "");
        $this->assertFalse(LanguageBuilder::hasStrings());
    }

    public function testADirectoryWithALanguageFileHasStrings(): void {
        $this->writeLang("en", [ "NAME" => "English" ]);
        $this->assertTrue(LanguageBuilder::hasStrings());
    }

    public function testAMissingDirectoryHasNoStrings(): void {
        IntlConfig::setStringsDir(self::FixtureDir . "/missing");
        $this->assertFalse(LanguageBuilder::hasStrings());
    }

    #[DataProvider("providerCollectLanguages")]
    public function testCollectLanguages(array $files, string $default, array $expectedLanguages, string $expectedRoot): void {
        foreach ($files as $code => $data) {
            $this->writeLang($code, $data);
        }
        IntlConfig::setDefaultLanguage($default);

        $result = LanguageBuilder::collectLanguages();
        $this->assertSame($expectedLanguages, $result["languages"]);
        $this->assertSame($expectedRoot, $result["rootCode"]);
    }

    public static function providerCollectLanguages(): array {
        return [
            "root first then alphabetical" => [
                [
                    "en" => [ "NAME" => "English" ],
                    "de" => [ "NAME" => "Deutsch" ],
                    "es" => [ "NAME" => "Español" ],
                ],
                "en",
                [
                    [ "code" => "en", "name" => "English" ],
                    [ "code" => "de", "name" => "Deutsch" ],
                    [ "code" => "es", "name" => "Español" ],
                ],
                "en",
            ],
            "skips files without name"     => [
                [
                    "en" => [ "NAME" => "English" ],
                    "de" => [ "NAME" => "Deutsch" ],
                    "xx" => [ "OTHER" => "value" ],
                ],
                "en",
                [
                    [ "code" => "en", "name" => "English" ],
                    [ "code" => "de", "name" => "Deutsch" ],
                ],
                "en",
            ],
            "root missing uses first"      => [
                [
                    "de" => [ "NAME" => "Deutsch" ],
                    "es" => [ "NAME" => "Español" ],
                ],
                "zz",
                [
                    [ "code" => "de", "name" => "Deutsch" ],
                    [ "code" => "es", "name" => "Español" ],
                ],
                "de",
            ],
            "empty defaults to english"    => [
                [],
                "en",
                [
                    [ "code" => "en", "name" => "English" ],
                ],
                "en",
            ],
        ];
    }


    public function testDestroyCode(): void {
        $this->assertSame(1, LanguageBuilder::destroyCode());
    }
}
