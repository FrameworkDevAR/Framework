<?php
namespace Tests\Discovery;

use Framework\Application;
use Framework\Builder\ConfigListCode;
use Framework\Discovery\DiscoveryConfig;
use Framework\Discovery\Package;
use Framework\File\Storage;
use Framework\Utils\Strings;

use Tests\TestHelpers;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Discovery Config
 */
class DiscoveryConfigTest extends TestCase {
    use TestHelpers;

    /**
     * Returns the names of the Config files the Framework ships, without the extension
     * @return list<string>
     */
    private static function configNames(): array {
        $result = [];
        foreach (Storage::getFilesInDir(Package::getBasePath(Package::ConfigDir)) as $filePath) {
            $fileName = Strings::substringAfter($filePath, "/");
            if (Strings::endsWith($fileName, DiscoveryConfig::Extension)) {
                $result[] = Strings::stripEnd($fileName, DiscoveryConfig::Extension);
            }
        }
        return $result;
    }



    /**
     * A config the Framework ships, loaded by the name it is asked for
     * @param string $name
     * @return void
     */
    #[DataProvider("providerShippedConfigs")]
    public function testTheFrameworkShipsTheConfigsItNames(string $name): void {
        $this->assertTrue(DiscoveryConfig::loadDefault($name));
    }

    /**
     * One case per file in the config directory
     * @return array<string,array{string}>
     */
    public static function providerShippedConfigs(): array {
        $result = [];
        foreach (self::configNames() as $name) {
            $result[$name] = [ $name ];
        }
        return $result;
    }

    /**
     * A name no config answers to
     * @param string $name
     * @return void
     */
    #[DataProvider("providerMissingConfigs")]
    public function testAConfigThatIsNotThereIsNotLoaded(string $name): void {
        $this->assertFalse(DiscoveryConfig::loadDefault($name));
    }

    /**
     * The extension is added, so asking for the file itself finds nothing
     * @return array<string,array{string}>
     */
    public static function providerMissingConfigs(): array {
        return [
            "an unknown name"    => [ "NotAConfig" ],
            "the whole filename" => [ "Access.config.php" ],
            "half the extension" => [ "Access.config" ],
            "a path"             => [ "config/Access" ],
            "nothing given"      => [ "" ],
        ];
    }

    /**
     * A name the source asks loadDefault for, against the files that are there
     *
     * A name that differs only in its case works on a case insensitive disk and
     * fails on a Linux server, which is how "access" reached production.
     * @param string $name
     * @return void
     */
    #[DataProvider("providerNamesAskedFor")]
    public function testEveryNameAskedForIsSpelledLikeItsFile(string $name): void {
        $this->assertContains($name, self::configNames());
    }

    /**
     * One case per loadDefault call written anywhere in the source
     * @return array<string,array{string}>
     */
    public static function providerNamesAskedFor(): array {
        $pattern = '/DiscoveryConfig::loadDefault\("([^"]+)"\)/';
        $result  = [];

        foreach (Storage::getFilesInDir(Package::getSourcePath(), recursive: true) as $filePath) {
            if (!Strings::endsWith($filePath, ".php")) {
                continue;
            }
            $matches = Strings::getAllMatches(Storage::readFile($filePath), $pattern);
            if (isset($matches[1])) {
                $name = Strings::toString($matches[1]);
                $result[Strings::substringAfter($filePath, "/") . " asks for $name"] = [ $name ];
            }
        }

        // A provider returning nothing would leave the check unmade
        if (count($result) === 0) {
            throw new AssertionFailedError("No loadDefault call was found in the source");
        }
        return $result;
    }

    public function testTheConfigIsNotLoadedInsideTheFramework(): void {
        // There is no app around it here, so there is nothing of its own to load
        $this->assertTrue(Package::isFramework());
        $this->assertFalse(DiscoveryConfig::load());
    }

    public function testItIsOnlyLoadedOnce(): void {
        DiscoveryConfig::load();

        $this->assertFalse(DiscoveryConfig::load());
    }

    public function testAnAppLoadsEveryConfigFileUnderIt(): void {
        // Rooted at the Framework's own config directory, which is not the
        // Framework itself as far as isFramework can tell, so the walk runs.
        // The files are included once, and these were already loaded above
        $this->withApp(Package::ConfigDir, function (): void {
            $this->assertFalse(Package::isFramework());
            $this->assertTrue(DiscoveryConfig::load());
            $this->assertFalse(DiscoveryConfig::load());
        });
    }

    public function testAnAppWithNoConfigFilesStillCountsAsLoaded(): void {
        $this->withApp(Package::DocsDir, function (): void {
            $this->assertTrue(DiscoveryConfig::load());
        });
    }

    public function testARequestLoadsNothingInsideTheFramework(): void {
        $this->assertTrue(Package::isFramework());
        $this->assertFalse(DiscoveryConfig::loadForRequest());
    }

    public function testARequestWithoutTheListFindsItsConfigs(): void {
        $this->withConfigApp(function (): void {
            $this->assertTrue(DiscoveryConfig::loadForRequest());
            $this->assertTrue($GLOBALS["requestConfigLoaded"] ?? false);
            $this->assertFalse($GLOBALS["consoleConfigLoaded"] ?? false);
        });
    }

    public function testTheConsoleLoadsBothKindsOfConfig(): void {
        $this->withConfigApp(function (): void {
            $this->assertTrue(DiscoveryConfig::load());
            $this->assertTrue($GLOBALS["requestConfigLoaded"] ?? false);
            $this->assertTrue($GLOBALS["consoleConfigLoaded"] ?? false);
        });
    }

    public function testARequestOnlyLoadsTheListedConfigs(): void {
        $this->withConfigApp(function (): void {
            $this->writeList([ "config/Request.config.php" ]);
            $this->assertTrue(DiscoveryConfig::loadForRequest());
            $this->assertTrue($GLOBALS["requestConfigLoaded"] ?? false);
            $this->assertFalse($GLOBALS["consoleConfigLoaded"] ?? false);
            $this->assertFalse(DiscoveryConfig::loadForRequest());
        });
    }

    public function testAListedConfigThatIsGoneIsIgnored(): void {
        $this->withConfigApp(function (): void {
            $this->writeList([ "config/Gone.config.php", "config/Request.config.php" ]);
            $this->assertTrue(DiscoveryConfig::loadForRequest());
            $this->assertTrue($GLOBALS["requestConfigLoaded"] ?? false);
        });
    }

    public function testTheRequestPathsLeaveOutTheConsoleConfigs(): void {
        $this->withConfigApp(function (): void {
            $this->assertSame(
                [ "config/Request.config.php" ],
                DiscoveryConfig::getRelativePaths(),
            );
        });
    }

    public function testTheBuilderListsTheRequestConfigs(): void {
        $this->withConfigApp(function (): void {
            $this->assertSame(
                [ "files" => [ "config/Request.config.php" ], "total" => 1 ],
                ConfigListCode::collectFiles(),
            );
        });
    }

    /**
     * Runs the given callback in an App with a Config for the requests and one for the console
     * @param callable(): void $callback
     * @return void
     */
    private function withConfigApp(callable $callback): void {
        // Each one has its own App, as a file that was included once is not included again
        $appDir     = "tests/Discovery/.tmp_app_" . uniqid();
        $configPath = Application::getBasePath($appDir, Package::ConfigDir);
        $listPath   = Package::getBuildPath() . "/" . DiscoveryConfig::ListFile;
        $listWas    = file_exists($listPath) ? Storage::readFile($listPath) : null;

        Storage::createDir($configPath);
        Storage::writeFile("$configPath/Request.config.php", '<?php $GLOBALS["requestConfigLoaded"] = true;');
        Storage::writeFile("$configPath/Console.console.php", '<?php $GLOBALS["consoleConfigLoaded"] = true;');
        Storage::deleteFile($listPath);

        try {
            $this->withApp($appDir, $callback);
        } finally {
            Storage::deleteDir(Application::getBasePath($appDir));
            if ($listWas !== null) {
                Storage::writeFile($listPath, $listWas);
            } else {
                Storage::deleteFile($listPath);
            }
            unset($GLOBALS["requestConfigLoaded"], $GLOBALS["consoleConfigLoaded"]);
        }
    }

    /**
     * Writes the list of the Config files that a request loads
     * @param list<string> $relPaths
     * @return void
     */
    private function writeList(array $relPaths): void {
        $listPath = Package::getBuildPath() . "/" . DiscoveryConfig::ListFile;
        Storage::createDir(Package::getBuildPath());
        Storage::writeFile($listPath, "<?php\nreturn " . var_export($relPaths, true) . ";\n");
    }

    /**
     * Runs the given callback with the Application rooted at another directory
     * @param string   $baseDir
     * @param callable $callback
     * @return void
     */
    private function withApp(string $baseDir, callable $callback): void {
        /** @var string */
        $baseDirWas = $this->getPrivateStaticProperty(Application::class, "baseDir");
        /** @var bool */
        $loadedWas  = $this->getPrivateStaticProperty(DiscoveryConfig::class, "loaded");

        $this->setPrivateStaticProperty(Application::class, "baseDir", $baseDir);
        $this->setPrivateStaticProperty(DiscoveryConfig::class, "loaded", false);

        try {
            $callback();
        } finally {
            $this->setPrivateStaticProperty(Application::class, "baseDir", $baseDirWas);
            $this->setPrivateStaticProperty(DiscoveryConfig::class, "loaded", $loadedWas);
        }
    }
}
