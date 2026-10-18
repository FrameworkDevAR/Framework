<?php
namespace Tests\Core;

use Framework\Core\Configs;
use Framework\File\Storage;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Configs, the env files read into typed values
 *
 * The load reads the files of the repository once for the whole run, so the
 * data is put in place here rather than loaded, and the parsing of a line is
 * asked of the reader itself.
 */
class ConfigsTest extends TestCase {
    use TestHelpers;

    /** @var mixed */
    private mixed $data = null;

    /** @var mixed */
    private mixed $fileName = null;


    protected function setUp(): void {
        Configs::load();
        $this->data     = $this->getPrivateStaticProperty(Configs::class, "data");
        $this->fileName = $this->getPrivateStaticProperty(Configs::class, "fileName");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(Configs::class, "data", $this->data);
        $this->setPrivateStaticProperty(Configs::class, "fileName", $this->fileName);

        Storage::deleteDir(sys_get_temp_dir() . "/framework-configs-app");
        Storage::deleteDir(sys_get_temp_dir() . "/framework-configs-frame");
    }

    /**
     * Puts the given values in place of the ones of the repository
     * @param array<string,mixed> $data
     * @return void
     */
    private function useData(array $data): void {
        $this->setPrivateStaticProperty(Configs::class, "data", $data);
    }

    /**
     * Reads the given env file contents
     * @param string $contents
     * @return array<string,mixed>
     */
    private function readEnv(string $contents): array {
        $path = $this->writeFiles([ ".env.test" => $contents ]);

        /** @var array<string,mixed> */
        return $this->callPrivateStaticMethod(Configs::class, "loadENV", $path, ".env.test");
    }

    /**
     * Writes the given env files into a directory of their own
     * @param array<string,string> $files
     * @param string               $dir   Optional.
     * @return string
     */
    private function writeFiles(array $files, string $dir = "app"): string {
        $path = sys_get_temp_dir() . "/framework-configs-$dir";
        Storage::deleteDir($path);
        Storage::createDir($path);

        foreach ($files as $fileName => $contents) {
            Storage::writeFile("$path/$fileName", $contents);
        }
        return $path;
    }



    /**
     * A line of an env file, and the value it is read as
     * @param string $line
     * @param mixed  $expected
     * @return void
     */
    #[DataProvider("providerLoadEnv")]
    public function testALineIsGivenItsType(string $line, mixed $expected): void {
        $this->assertSame([ "KEY" => $expected ], $this->readEnv($line));
    }

    /**
     * @return array<string,array{string,mixed}>
     */
    public static function providerLoadEnv(): array {
        return [
            "a true"      => [ "KEY = true", true ],
            "a false"     => [ "KEY = false", false ],
            "a string"    => [ "KEY = \"a value\"", "a value" ],
            "an empty"    => [ "KEY = \"\"", "" ],
            "a float"     => [ "KEY = 1.5", 1.5 ],
            "an int"      => [ "KEY = 42", 42 ],
            "a zero"      => [ "KEY = 0", 0 ],
            "a list"      => [ "KEY = [\"a\", \"b\"]", [ "a", "b" ] ],
            "empty list"  => [ "KEY = []", [] ],
        ];
    }

    /**
     * A line of an env file that is not one, and so is skipped
     * @param string $contents
     * @return void
     */
    #[DataProvider("providerSkipped")]
    public function testALineThatIsNotOneIsSkipped(string $contents): void {
        $this->assertSame([], $this->readEnv($contents));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerSkipped(): array {
        return [
            "nothing"       => [ "" ],
            "a blank line"  => [ "   " ],
            "a comment"     => [ "# KEY = 1" ],
            "no separator"  => [ "KEY=1" ],
            "two of them"   => [ "KEY = 1 = 2" ],
        ];
    }

    public function testTheLinesAreReadInOrder(): void {
        $result = $this->readEnv("# A comment\nONE = 1\n\nTWO = \"two\"\n");

        $this->assertSame([ "ONE" => 1, "TWO" => "two" ], $result);
    }

    public function testAFileThatIsNotThereIsEmpty(): void {
        $result = $this->callPrivateStaticMethod(
            Configs::class,
            "loadENV",
            sys_get_temp_dir(),
            ".env.nothing",
        );

        $this->assertSame([], $result);
    }



    public function testAPropertyIsFoundByItsName(): void {
        // The name is written in camel case and the key in constant case
        $this->useData([ "SOME_VALUE" => "a value" ]);

        $this->assertSame("a value", Configs::getString("someValue"));
    }

    public function testAStringHasADefault(): void {
        $this->useData([]);

        $this->assertSame("", Configs::getString("nothing"));
        $this->assertSame("a default", Configs::getString("nothing", "a default"));
    }

    /**
     * A value, and whether it is read as true
     * @param mixed $value
     * @param bool  $expected
     * @return void
     */
    #[DataProvider("providerBoolean")]
    public function testABooleanIsWhatIsThere(mixed $value, bool $expected): void {
        $this->useData([ "KEY" => $value ]);

        $this->assertSame($expected, Configs::getBoolean("key"));
    }

    /**
     * @return array<string,array{mixed,bool}>
     */
    public static function providerBoolean(): array {
        return [
            "a true"     => [ true, true ],
            "a false"    => [ false, false ],
            "a one"      => [ 1, true ],
            "a zero"     => [ 0, false ],
            "a string"   => [ "a value", true ],
            "an empty"   => [ "", false ],
        ];
    }

    public function testAnIntIsANumber(): void {
        $this->useData([ "KEY" => "42" ]);

        $this->assertSame(42, Configs::getInt("key"));
    }

    public function testAnIntHasADefault(): void {
        $this->useData([]);

        $this->assertSame(0, Configs::getInt("nothing"));
        $this->assertSame(7, Configs::getInt("nothing", 7));
    }

    public function testAFloatIsANumber(): void {
        $this->useData([ "KEY" => "1.5" ]);

        $this->assertSame(1.5, Configs::getFloat("key"));
    }

    public function testAFloatHasADefault(): void {
        $this->useData([]);

        $this->assertSame(0.0, Configs::getFloat("nothing"));
        $this->assertSame(2.5, Configs::getFloat("nothing", 2.5));
    }

    /**
     * A value, and the list it is read as
     * @param mixed        $value
     * @param list<string> $expected
     * @return void
     */
    #[DataProvider("providerList")]
    public function testAListIsAlwaysOne(mixed $value, array $expected): void {
        $this->useData([ "KEY" => $value ]);

        $this->assertSame($expected, Configs::getList("key"));
    }

    /**
     * @return array<string,array{mixed,list<string>}>
     */
    public static function providerList(): array {
        return [
            "a list"      => [ [ "a", "b" ], [ "a", "b" ] ],
            "one split"   => [ "a,b", [ "a", "b" ] ],
            "one on its own" => [ "a", [ "a" ] ],
            "one of ints" => [ [ 1, 2 ], [ "1", "2" ] ],
        ];
    }

    public function testAListThatIsNotThereIsEmpty(): void {
        $this->useData([]);

        $this->assertSame([], Configs::getList("nothing"));
    }



    public function testTheDataIsWhatWasRead(): void {
        $this->useData([ "KEY" => "a value" ]);

        $this->assertSame([ "KEY" => "a value" ], Configs::getData());
    }

    public function testThereIsAnEnvironment(): void {
        // This repository is read without one of the .env.* files, so it is
        // the local one it falls back to
        $this->assertSame("local", Configs::getEnvironment());
        $this->assertIsArray(Configs::getEnvironments());
    }



    public function testTheAppWritesOverTheFramework(): void {
        $framePath = $this->writeFiles([ ".env.example" => "ONE = 1\nTWO = 2" ], "frame");
        $appPath   = $this->writeFiles([ ".env" => "TWO = 22" ]);

        $result = Configs::readConfigs($framePath, $appPath, "app.test");

        $this->assertSame([ "ONE" => 1, "TWO" => 22 ], $result["data"]);
        $this->assertSame("local", $result["environment"]);
        $this->assertSame([], $result["environments"]);
    }

    public function testTheNamedFileWritesOverBoth(): void {
        $appPath = $this->writeFiles([
            ".env"            => "ONE = 1\nTWO = 2",
            ".env.production" => "TWO = 222",
        ]);

        $result = Configs::readConfigs($appPath, $appPath, "app.test", ".env.production");

        $this->assertSame(222, $result["data"]["TWO"]);
    }

    public function testANamedFileNamesTheEnvironment(): void {
        // A deploy names the file it runs against, and the environment is
        // the name of that file rather than the local one
        $appPath = $this->writeFiles([ ".env.production" => "URL = \"https://app.test/\"" ]);

        $result = Configs::readConfigs($appPath, $appPath, "app.test", ".env.production");

        $this->assertSame("production", $result["environment"]);
    }

    public function testANamedFileThatIsNotThereIsLocal(): void {
        $appPath = $this->writeFiles([ ".env" => "ONE = 1" ]);

        $result = Configs::readConfigs($appPath, $appPath, "app.test", ".env.production");

        $this->assertSame("local", $result["environment"]);
    }

    public function testTheLocalFileWritesOverTheApp(): void {
        $appPath = $this->writeFiles([
            ".env"       => "ONE = 1\nTWO = 2",
            ".env.local" => "TWO = 22",
        ]);

        $result = Configs::readConfigs($appPath, $appPath, "nobody.test");

        $this->assertSame([ "ONE" => 1, "TWO" => 22 ], $result["data"]);
        $this->assertSame("local", $result["environment"]);
        $this->assertSame([], $result["environments"]);
    }

    public function testTheLocalFileIsNotReadInAnotherEnvironment(): void {
        // A server has no local file, but a checkout that runs a command against
        // a server must not take the values of the machine there
        $appPath = $this->writeFiles([
            ".env"            => "TWO = 2",
            ".env.local"      => "TWO = 22",
            ".env.production" => "URL = \"https://app.test/\"",
        ]);

        $byHost = Configs::readConfigs($appPath, $appPath, "app.test");
        $byName = Configs::readConfigs($appPath, $appPath, "nobody.test", ".env.production");

        $this->assertSame(2, $byHost["data"]["TWO"]);
        $this->assertSame(2, $byName["data"]["TWO"]);
    }

    public function testTheFileOfTheHostIsTheOneRead(): void {
        $appPath = $this->writeFiles([
            ".env"            => "URL = \"https://local.test/\"\nNAME = \"the app\"",
            ".env.staging"    => "URL = \"https://staging.test/\"\nNAME = \"staging\"",
            ".env.production" => "URL = \"https://app.test/\"\nNAME = \"production\"",
        ]);

        $result = Configs::readConfigs($appPath, $appPath, "app.test");

        $this->assertSame("production", $result["environment"]);
        $this->assertSame("production", $result["data"]["NAME"]);
    }

    public function testAHostThatMatchesNothingIsLocal(): void {
        $appPath = $this->writeFiles([
            ".env"            => "NAME = \"the app\"",
            ".env.production" => "URL = \"https://app.test/\"\nNAME = \"production\"",
        ]);

        $result = Configs::readConfigs($appPath, $appPath, "nobody.test");

        $this->assertSame("local", $result["environment"]);
        $this->assertSame("the app", $result["data"]["NAME"]);
    }

    public function testTheEnvironmentsAreTheFilesFound(): void {
        $appPath = $this->writeFiles([
            ".env.example"    => "ONE = 1",
            ".env"            => "ONE = 2",
            ".env.staging"    => "URL = \"https://staging.test/\"",
            ".env.production" => "URL = \"https://app.test/\"",
            "other.txt"       => "not one of them",
        ]);

        $result = Configs::readConfigs($appPath, $appPath, "nobody.test");

        // The example and the main one are not environments, and neither is
        // anything that is not named after them
        sort($result["environments"]);
        $this->assertSame([ "production", "staging" ], $result["environments"]);
    }

    public function testThereIsNothingToRead(): void {
        $path = $this->writeFiles([]);

        $result = Configs::readConfigs($path, $path, "app.test");

        $this->assertSame([], $result["data"]);
        $this->assertSame("local", $result["environment"]);
    }



    public function testThereIsNothingToCollect(): void {
        $this->assertSame([], Configs::collectConfigs([], []));
    }

    public function testAUrlIsKeptApart(): void {
        // They are the ones an environment writes over, so the generated code
        // reads them through a getter of their own
        $result = Configs::collectConfigs([ "FILES_URL" => "https://app.test/" ], []);

        $this->assertSame([
            [ "property" => "filesUrl", "name" => "FilesUrl" ],
        ], $result["urls"]);
        $this->assertSame([], $result["properties"]);
        $this->assertSame(0, $result["total"]);
    }

    /**
     * A config value, and the type the generated getter is given
     * @param mixed  $value
     * @param string $type
     * @param string $getter
     * @return void
     */
    #[DataProvider("providerProperties")]
    public function testAPropertyIsGivenItsType(mixed $value, string $type, string $getter): void {
        $result = Configs::collectConfigs([ "SOME_KEY" => $value ], []);

        $property = $result["properties"][0];
        $this->assertSame("someKey", $property["property"]);
        $this->assertSame("SomeKey", $property["name"]);
        $this->assertSame($type, $property["type"]);
        $this->assertSame($getter, $property["getter"]);
    }

    /**
     * @return array<string,array{mixed,string,string}>
     */
    public static function providerProperties(): array {
        return [
            "a string" => [ "a value", "string", "get" ],
            "a bool"   => [ true, "bool", "is" ],
            "an int"   => [ 42, "int", "get" ],
            "a float"  => [ 1.5, "float", "get" ],
            "a list"   => [ [ "a", "b" ], "array", "get" ],
        ];
    }

    public function testTheEnvironmentsAreNamed(): void {
        $result = Configs::collectConfigs(
            [ "ONE" => 1 ],
            [ "local", "staging", "production" ],
        );

        // The local one is always generated, so it is not one of these
        $this->assertSame([
            [ "name" => "Staging", "environment" => "staging" ],
            [ "name" => "Production", "environment" => "production" ],
        ], $result["environments"]);
    }

    public function testTheFileNameIsTheOneSet(): void {
        // The load reads it over the ENV_FILENAME of the environment
        Configs::setFileName(".env.production");

        $this->assertSame(
            ".env.production",
            $this->getPrivateStaticProperty(Configs::class, "fileName"),
        );
    }
}
