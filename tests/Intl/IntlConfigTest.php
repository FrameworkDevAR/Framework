<?php
namespace Tests\Intl;

use Framework\Application;
use Framework\Intl\IntlConfig;
use Framework\File\Storage;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class IntlConfigTest extends TestCase {
    use TestHelpers;

    private const FixtureDir = "tests/Intl/.tmp_intl_config";

    private const Strings       = [ "HELLO" => "Hola", "BYE" => "Chau" ];
    private const Notifications = [ "ALERT" => "Alert!" ];

    private string $fixtureBase = "";

    /** @var array<string,mixed> */
    private array $original = [];


    protected function setUp(): void {
        $props = [
            "defaultLanguage", "stringsDir", "notificationsDir",
            "scriptDirs", "sourceDirs",
        ];
        foreach ($props as $prop) {
            $this->original[$prop] = $this->getPrivateStaticProperty(IntlConfig::class, $prop);
        }

        $this->fixtureBase = Application::getBasePath(self::FixtureDir);
        $this->writeFixture("strings", "en", self::Strings);
        $this->writeFixture("notifications", "en", self::Notifications);

        IntlConfig::setStringsDir(self::FixtureDir . "/strings");
        IntlConfig::setNotificationsDir(self::FixtureDir . "/notifications");
    }

    protected function tearDown(): void {
        foreach ($this->original as $prop => $value) {
            $this->setPrivateStaticProperty(IntlConfig::class, $prop, $value);
        }
        Storage::deleteDir($this->fixtureBase);
    }

    private function writeFixture(string $subDir, string $langCode, array $data): void {
        $dir = $this->fixtureBase . DIRECTORY_SEPARATOR . $subDir;
        Storage::createDir($dir);
        Storage::writeFile($dir . DIRECTORY_SEPARATOR . $langCode . ".json", json_encode($data));
    }


    #[DataProvider("providerDefaultLanguage")]
    public function testDefaultLanguage(string $language): void {
        IntlConfig::setDefaultLanguage($language);
        $this->assertSame($language, IntlConfig::getDefaultLanguage());
    }

    public static function providerDefaultLanguage(): array {
        return [
            "spanish" => [ "es" ],
            "english" => [ "en" ],
            "french"  => [ "fr" ],
        ];
    }


    #[DataProvider("providerGetStringsPath")]
    public function testGetStringsPath(string $dir): void {
        IntlConfig::setStringsDir($dir);
        $this->assertSame(Application::getBasePath($dir), IntlConfig::getStringsPath());
    }

    public static function providerGetStringsPath(): array {
        return [
            "default"    => [ "nls/strings" ],
            "custom"     => [ "custom/lang" ],
            "single dir" => [ "strings" ],
        ];
    }


    // The directories of the apps, which only the check reads
    #[DataProvider("providerGetScriptPaths")]
    public function testGetScriptPaths(array $dirs, array $expected): void {
        foreach ($dirs as $name => $dir) {
            IntlConfig::addScriptDir($name, $dir);
        }

        $result = [];
        foreach ($expected as $name => $dir) {
            $result[$name] = Application::getBasePath($dir);
        }
        $this->assertSame($result, IntlConfig::getScriptPaths());
    }

    public static function providerGetScriptPaths(): array {
        $desktop = "../desktop/src/NLS/Strings";
        $chat    = "../chat/src/NLS/Strings";

        return [
            "none"     => [ [], [] ],
            "one app"  => [ [ "Desktop" => $desktop ], [ "Desktop" => $desktop ] ],
            "two apps" => [
                [ "Desktop" => $desktop, "Chat" => $chat ],
                [ "Desktop" => $desktop, "Chat" => $chat ],
            ],
            "unnamed"  => [ [ "" => $desktop ], [] ],
            "no dir"   => [ [ "Desktop" => "" ], [] ],
        ];
    }


    #[DataProvider("providerGetNotificationsPath")]
    public function testGetNotificationsPath(string $dir): void {
        IntlConfig::setNotificationsDir($dir);
        $this->assertSame(Application::getBasePath($dir), IntlConfig::getNotificationsPath());
    }

    public static function providerGetNotificationsPath(): array {
        return [
            "default" => [ "nls/notifications" ],
            "custom"  => [ "custom/notifications" ],
        ];
    }


    // The directories the strings are used in, which only the check reads
    #[DataProvider("providerGetSourcePaths")]
    public function testGetSourcePaths(array $dirs, array $expected): void {
        foreach ($dirs as $dir) {
            IntlConfig::addSourceDir($dir);
        }

        $result = [];
        foreach ($expected as $dir) {
            $result[] = Application::getBasePath($dir);
        }
        $this->assertSame($result, IntlConfig::getSourcePaths());
    }

    public static function providerGetSourcePaths(): array {
        $desktop = "../desktop/src";

        return [
            "none"         => [ [], [] ],
            "one dir"      => [ [ "src" ], [ "src" ] ],
            "two dirs"     => [ [ "src", $desktop ], [ "src", $desktop ] ],
            "no dir"       => [ [ "" ], [] ],
            "the same one" => [ [ "src", "src" ], [ "src" ] ],
        ];
    }


    #[DataProvider("providerLoadStrings")]
    public function testLoadStrings(string $langCode, array $expected, bool $isEmpty): void {
        $result = IntlConfig::loadStrings($langCode);
        $this->assertSame($isEmpty, $result->isEmpty());
        $this->assertSame($expected, $result->toArray());
    }

    public static function providerLoadStrings(): array {
        return [
            "existing"     => [ "en", self::Strings, false ],
            "missing lang" => [ "zz", [], true ],
        ];
    }


    #[DataProvider("providerLoadNotifications")]
    public function testLoadNotifications(string $langCode, array $expected, bool $isEmpty): void {
        $result = IntlConfig::loadNotifications($langCode);
        $this->assertSame($isEmpty, $result->isEmpty());
        $this->assertSame($expected, $result->toArray());
    }

    public static function providerLoadNotifications(): array {
        return [
            "existing"     => [ "en", self::Notifications, false ],
            "missing lang" => [ "zz", [], true ],
        ];
    }
}
