<?php
namespace Framework\Discovery;

use Framework\Application;
use Framework\Discovery\Package;
use Framework\File\Storage;
use Framework\Utils\Strings;

/**
 * The Discovery Config
 */
class DiscoveryConfig {

    public const Extension = ".config.php";

    private static bool $loaded = false;



    /**
     * Finds and loads all Config files
     * NOTE 1: A config file must end with ".config.php" to be loaded
     * NOTE 2: Only files from the App are loaded
     * @return bool
     */
    public static function load(): bool {
        if (self::$loaded) {
            return false;
        }

        // Don't load the Config inside the Framework
        if (Package::isFramework()) {
            self::$loaded = true;
            return false;
        }

        // Load all the Config files
        $appPath   = Application::getBasePath();
        $filePaths = Storage::getFilesInDir($appPath, recursive: true, skipVendor: true);
        foreach ($filePaths as $filePath) {
            if (Strings::endsWith($filePath, self::Extension)) {
                include_once $filePath;
            }
        }

        self::$loaded = true;
        return true;
    }

    /**
     * Loads a single Config file of the App, for the code that runs without the others
     * @param string $file
     * @return bool
     */
    public static function loadFile(string $file): bool {
        // When all of them are loaded this one is too, and the Framework has no App Config
        if (self::$loaded || Package::isFramework()) {
            return false;
        }

        $configPath = Application::getBasePath(Package::ConfigDir, $file . self::Extension);
        if (file_exists($configPath)) {
            include_once $configPath;
            return true;
        }
        return false;
    }

    /**
     * Loads a Default Config file from the Framework
     * @param string $file
     * @return bool
     */
    public static function loadDefault(string $file): bool {
        $configPath = Package::getBasePath(Package::ConfigDir, $file . self::Extension);
        if (file_exists($configPath)) {
            include_once $configPath;
            return true;
        }
        return false;
    }
}
