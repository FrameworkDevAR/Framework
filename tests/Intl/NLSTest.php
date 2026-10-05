<?php
namespace Tests\Intl;

use Framework\Application;
use Framework\Core\Configs;
use Framework\File\Storage;
use Framework\Intl\IntlConfig;
use Framework\Intl\NLS;
use Framework\System\Config;
use Framework\Utils\Dictionary;
use Framework\Utils\Numbers;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NLSTest extends TestCase {
    use TestHelpers;

    private mixed $originalData     = null;
    private mixed $originalLanguage = null;
    private mixed $originalConfig   = null;
    private mixed $originalLoaded   = null;


    protected function setUp(): void {
        $this->originalData     = $this->getPrivateStaticProperty(NLS::class, "data");
        $this->originalLanguage = $this->getPrivateStaticProperty(NLS::class, "language");
        $this->originalConfig   = $this->getPrivateStaticProperty(Configs::class, "data");
        $this->originalLoaded   = $this->getPrivateStaticProperty(Configs::class, "loaded");

        // Inject config urls and mark as loaded so Configs never reads the env files
        $this->setPrivateStaticProperty(Configs::class, "data", [
            "URL"       => "https://example.com/",
            "ADMIN_URL" => "https://admin.example.com/",
        ]);
        $this->setPrivateStaticProperty(Configs::class, "loaded", true);

        // Inject strings under the "en" root code so load() never hits IntlConfig
        $this->setPrivateStaticProperty(NLS::class, "data", [
            "en" => new Dictionary([
                "HELLO"                      => "Hello",
                "GREETING"                   => "Hi {0}, you have {1} messages",
                "GENERAL_AND"                => "and",
                "GENERAL_OR"                 => "or",
                "SELECT_YES_NO"              => [ "No", "Yes" ],
                "COLORS"                     => [ "Red", "Green", "Blue" ],
                "SIZES"                      => [ "Small", "Large" ],
                "APPLE_SINGULAR"             => "{0} apple",
                "APPLE_PLURAL"               => "{0} apples",
                "LIST_SINGULAR"              => "Item: {0}",
                "LIST_PLURAL"                => "Items: {0}",
                "SENTENCE"                   => "Selected: {0}",
                "NUMBER_DECIMAL_SEPARATOR"   => ".",
                "NUMBER_THOUSANDS_SEPARATOR" => ",",
            ]),
        ]);
        $this->setPrivateStaticProperty(NLS::class, "language", "root");
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(NLS::class, "data", $this->originalData);
        $this->setPrivateStaticProperty(NLS::class, "language", $this->originalLanguage);
        $this->setPrivateStaticProperty(Configs::class, "data", $this->originalConfig);
        $this->setPrivateStaticProperty(Configs::class, "loaded", $this->originalLoaded);
    }


    public function testAMissingRootFileIsReportedOnce(): void {
        $warnings = $this->withoutStrings(function (): void {
            NLS::getString("HELLO", "fr");
            NLS::getString("BYE", "fr");
        });
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("There is no Strings file for \"en\"", $warnings[0]);
    }

    public function testALanguageWithoutAFileUsesTheRootQuietly(): void {
        $this->setPrivateStaticProperty(NLS::class, "reported", false);
        $warnings = [];
        set_error_handler(function (int $code, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $this->assertSame("Hello", NLS::getString("HELLO", "fr"));
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $warnings);
    }

    /**
     * Runs the callback with no Strings at all, and returns the warnings it raised
     * @param callable(): void $callback
     * @return list<string>
     */
    private function withoutStrings(callable $callback): array {
        $stringsDir = $this->getPrivateStaticProperty(IntlConfig::class, "stringsDir");
        $reported   = $this->getPrivateStaticProperty(NLS::class, "reported");
        $warnings   = [];

        IntlConfig::setStringsDir("tests/Intl/.tmp_no_strings");
        $this->setPrivateStaticProperty(NLS::class, "data", []);
        $this->setPrivateStaticProperty(NLS::class, "reported", false);
        set_error_handler(function (int $code, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $callback();
        } finally {
            restore_error_handler();
            $this->setPrivateStaticProperty(IntlConfig::class, "stringsDir", $stringsDir);
            $this->setPrivateStaticProperty(NLS::class, "reported", $reported);
        }
        return $warnings;
    }


    #[DataProvider("providerGetSetLanguage")]
    public function testGetSetLanguage(string $language): void {
        NLS::setLanguage($language);
        $this->assertSame($language, NLS::getLanguage());
    }

    public static function providerGetSetLanguage(): array {
        return [
            "spanish" => [ "es" ],
            "english" => [ "en" ],
            "root"    => [ "root" ],
        ];
    }


    #[DataProvider("providerGetString")]
    public function testGetString(string $key, string $expected): void {
        $this->assertSame($expected, NLS::getString($key));
    }

    public static function providerGetString(): array {
        return [
            "existing key" => [ "HELLO", "Hello" ],
            "missing key"  => [ "MISSING", "MISSING" ],
            "empty key"    => [ "", "" ],
        ];
    }


    #[DataProvider("providerGetIndex")]
    public function testGetIndex(string $key, int $index, string $expected): void {
        $this->assertSame($expected, NLS::getIndex($key, $index));
    }

    public static function providerGetIndex(): array {
        return [
            "first index"  => [ "SELECT_YES_NO", 0, "No" ],
            "second index" => [ "SELECT_YES_NO", 1, "Yes" ],
        ];
    }


    #[DataProvider("providerGetList")]
    public function testGetList(string $key, array $expected): void {
        $this->assertSame($expected, NLS::getList($key));
    }

    public static function providerGetList(): array {
        return [
            "colors"      => [ "COLORS", [ "Red", "Green", "Blue" ] ],
            "sizes"       => [ "SIZES", [ "Small", "Large" ] ],
            "missing key" => [ "MISSING", [] ],
        ];
    }


    #[DataProvider("providerGetMap")]
    public function testGetMap(string $key, array $expected): void {
        $this->assertSame($expected, NLS::getMap($key));
    }

    public static function providerGetMap(): array {
        return [
            "colors"      => [ "COLORS", [ "Red", "Green", "Blue" ] ],
            "sizes"       => [ "SIZES", [ "Small", "Large" ] ],
            "missing key" => [ "MISSING", [] ],
        ];
    }


    #[DataProvider("providerGetSelect")]
    public function testGetSelect(string $key, array $expected): void {
        $result = NLS::getSelect($key);
        $actual = array_map(fn($select) => [ $select->key, $select->value ], $result);
        $this->assertSame($expected, $actual);
    }

    public static function providerGetSelect(): array {
        return [
            "colors"      => [ "COLORS", [ [ "0", "Red" ], [ "1", "Green" ], [ "2", "Blue" ] ] ],
            "yes no"      => [ "SELECT_YES_NO", [ [ "0", "No" ], [ "1", "Yes" ] ] ],
            "missing key" => [ "MISSING", [] ],
        ];
    }


    #[DataProvider("providerGetAll")]
    public function testGetAll(array $keys, array $expected): void {
        $this->assertSame($expected, NLS::getAll($keys));
    }

    public static function providerGetAll(): array {
        return [
            "skips empty"  => [ [ "HELLO", "", "MISSING" ], [ "Hello", "MISSING" ] ],
            "all existing" => [ [ "HELLO", "GENERAL_AND" ], [ "Hello", "and" ] ],
            "empty list"   => [ [], [] ],
        ];
    }


    #[DataProvider("providerUrl")]
    public function testUrl(array $args, array $resolvedParts): void {
        $this->assertSame(Config::getUrl(...$resolvedParts), NLS::url($args));
    }

    public static function providerUrl(): array {
        return [
            "string resolved" => [ [ "HELLO" ], [ "Hello" ] ],
            "string and int"  => [ [ "HELLO", 5 ], [ "Hello", 5 ] ],
            "missing key"     => [ [ "MISSING" ], [ "MISSING" ] ],
            "ignores non int" => [ [ "HELLO", 5.5 ], [ "Hello" ] ],
        ];
    }


    #[DataProvider("providerUrlPath")]
    public function testUrlPath(string $urlKey, array $args, array $resolvedParts): void {
        $this->assertSame(Config::getUrlWithKey($urlKey, ...$resolvedParts), NLS::urlPath($urlKey, $args));
    }

    public static function providerUrlPath(): array {
        return [
            "known key"    => [ "adminUrl", [ "HELLO" ], [ "Hello" ] ],
            "fallback key" => [ "missingKey", [ "HELLO", 2 ], [ "Hello", 2 ] ],
        ];
    }


    #[DataProvider("providerFormat")]
    public function testFormat(string $key, array $args, string $expected): void {
        $this->assertSame($expected, NLS::format($key, $args));
    }

    public static function providerFormat(): array {
        return [
            "two args"       => [ "GREETING", [ "Ana", 3 ], "Hi Ana, you have 3 messages" ],
            "missing arg"    => [ "GREETING", [ "Ana" ], "Hi Ana, you have  messages" ],
            "no placeholder" => [ "HELLO", [ "x" ], "Hello" ],
        ];
    }


    #[DataProvider("providerFormatJoin")]
    public function testFormatJoin(array $strings, bool $useOr, string $expected): void {
        $this->assertSame($expected, NLS::formatJoin("SENTENCE", $strings, $useOr));
    }

    public static function providerFormatJoin(): array {
        return [
            "single"  => [ [ "Red" ], false, "Selected: Red" ],
            "two and" => [ [ "Red", "Blue" ], false, "Selected: Red and Blue" ],
            "two or"  => [ [ "Red", "Blue" ], true, "Selected: Red or Blue" ],
        ];
    }


    #[DataProvider("providerPluralize")]
    public function testPluralize(string $key, int $count, string $expected): void {
        $this->assertSame($expected, NLS::pluralize($key, $count));
    }

    public static function providerPluralize(): array {
        return [
            "singular" => [ "APPLE", 1, "1 apple" ],
            "plural"   => [ "APPLE", 3, "3 apples" ],
            "zero"     => [ "APPLE", 0, "0 apples" ],
        ];
    }


    #[DataProvider("providerPluralizeList")]
    public function testPluralizeList(string $key, array $strings, string $expected): void {
        $this->assertSame($expected, NLS::pluralizeList($key, $strings));
    }

    public static function providerPluralizeList(): array {
        return [
            "single item"    => [ "LIST", [ "Red" ], "Item: Red" ],
            "multiple items" => [ "LIST", [ "Red", "Green" ], "Items: Red and Green" ],
        ];
    }


    #[DataProvider("providerJoin")]
    public function testJoin(array $strings, bool $useOr, string $expected): void {
        $this->assertSame($expected, NLS::join($strings, $useOr));
    }

    public static function providerJoin(): array {
        return [
            "empty"     => [ [], false, "" ],
            "single"    => [ [ "Red" ], false, "Red" ],
            "two and"   => [ [ "Red", "Blue" ], false, "Red and Blue" ],
            "three and" => [ [ "Red", "Green", "Blue" ], false, "Red, Green and Blue" ],
            "two or"    => [ [ "Red", "Blue" ], true, "Red or Blue" ],
        ];
    }


    #[DataProvider("providerJoinWithAndOr")]
    public function testJoinWithAndOr(array $strings, bool $useOr, string $expected): void {
        $actual = $useOr ? NLS::joinWithOr($strings) : NLS::joinWithAnd($strings);
        $this->assertSame($expected, $actual);
    }

    public static function providerJoinWithAndOr(): array {
        return [
            "and two"   => [ [ "Red", "Blue" ], false, "Red and Blue" ],
            "or two"    => [ [ "Red", "Blue" ], true, "Red or Blue" ],
            "and three" => [ [ "Red", "Green", "Blue" ], false, "Red, Green and Blue" ],
        ];
    }


    #[DataProvider("providerToYesNo")]
    public function testToYesNo(bool $value, string $expected): void {
        $this->assertSame($expected, NLS::toYesNo($value));
    }

    public static function providerToYesNo(): array {
        return [
            "true"  => [ true, "Yes" ],
            "false" => [ false, "No" ],
        ];
    }


    #[DataProvider("providerFormatNumber")]
    public function testFormatNumber(int|float $number, int $decimals): void {
        $expected = Numbers::formatFloat(
            number:             $number,
            decimals:           $decimals,
            maxForDecimals:     1000,
            decimalSeparator:   ".",
            thousandsSeparator: ",",
        );
        $this->assertSame($expected, NLS::formatNumber($number, $decimals));
    }

    public static function providerFormatNumber(): array {
        return [
            "integer"     => [ 1234, 2 ],
            "float"       => [ 1234.5, 2 ],
            "no decimals" => [ 9876.54, 0 ],
        ];
    }


    public function testLoadReturnsEmpty(): void {
        // No cached data and a strings dir with no file makes loadStrings() empty,
        // so load() falls through to returning a new empty Dictionary.
        $warnings = $this->withoutStrings(function (): void {
            $load   = new \ReflectionMethod(NLS::class, "load");
            $result = $load->invoke(null, "en");
            $this->assertInstanceOf(Dictionary::class, $result);
            $this->assertTrue($result->isEmpty());

            // getString falls back to the untranslated key when nothing is loaded
            $this->assertSame("HELLO", NLS::getString("HELLO", "en"));
        });
        $this->assertCount(1, $warnings);
    }


    public function testLoadReadCache(): void {
        // A strings file with content makes loadStrings() non-empty, so load()
        // returns it and caches it under the language code.
        $fixtureBase = Application::getBasePath("tests/Intl/.tmp_nls");
        $originalDir = $this->getPrivateStaticProperty(IntlConfig::class, "stringsDir");

        $this->setPrivateStaticProperty(NLS::class, "data", []);
        Storage::createDir($fixtureBase);
        Storage::writeFile($fixtureBase . DIRECTORY_SEPARATOR . "en.json", json_encode([ "HELLO" => "Hola" ]));
        IntlConfig::setStringsDir("tests/Intl/.tmp_nls");

        try {
            $load   = new \ReflectionMethod(NLS::class, "load");
            $result = $load->invoke(null, "en");
            $this->assertFalse($result->isEmpty());
            $this->assertSame("Hola", $result->getString("HELLO"));

            // The loaded data is cached under the language code
            $cached = $this->getPrivateStaticProperty(NLS::class, "data");
            $this->assertArrayHasKey("en", $cached);
        } finally {
            $this->setPrivateStaticProperty(IntlConfig::class, "stringsDir", $originalDir);
            Storage::deleteDir($fixtureBase);
        }
    }


    /**
     * Writes the given strings files and points the config at them
     * @param array<string,array<string,string>> $files
     * @return void
     */
    private function useStringsDir(array $files): void {
        $dir  = "tests/.tmp_nls";
        $path = Application::getBasePath($dir);
        Storage::deleteDir($path);
        Storage::createDir($path);

        foreach ($files as $code => $strings) {
            Storage::writeFile("$path/$code.json", (string)json_encode($strings));
        }

        IntlConfig::setStringsDir($dir);
        $this->setPrivateStaticProperty(NLS::class, "data", []);
    }

    protected function assertPostConditions(): void {
        IntlConfig::setStringsDir("nls");
        Storage::deleteDir(Application::getBasePath("tests/.tmp_nls"));
    }



    public function testAFileWithoutANameIsStillRead(): void {
        // It is not listed as a Language, but naming its code reads it: a
        // file of strings for a tool or a test does not need to be offered
        $this->useStringsDir([
            "en" => [ "NAME" => "English", "HELLO" => "Hello" ],
            "qa" => [ "HELLO" => "Test hello" ],
        ]);

        $this->assertSame("Test hello", NLS::getString("HELLO", "qa"));
    }

    public function testACodeWithNoFileFallsToTheRoot(): void {
        $this->useStringsDir([
            "en" => [ "NAME" => "English", "HELLO" => "Hello" ],
        ]);

        $this->assertSame("Hello", NLS::getString("HELLO", "xx"));
    }
}
