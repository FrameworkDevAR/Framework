<?php
namespace Framework\Discovery;

use Framework\Application;
use Framework\Discovery\Package;
use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Discovery Config
 */
class DiscoveryConfig {

    public const Extension        = ".config.php";
    public const ConsoleExtension = ".console.php";
    public const ListFile         = "ConfigList.php";

    private static bool $loaded = false;



    /**
     * Finds and loads all Config files, the ones that only the console uses too
     * NOTE 1: A config file must end with ".config.php", or ".console.php" for the console
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

        foreach (self::getFilePaths(withConsole: true) as $filePath) {
            include_once $filePath;
        }

        self::$loaded = true;
        return true;
    }

    /**
     * Loads the Config files of a request, from the list that the build generates
     * @return bool
     */
    public static function loadForRequest(): bool {
        if (self::$loaded) {
            return false;
        }

        if (Package::isFramework()) {
            self::$loaded = true;
            return false;
        }

        // Without a build there is no list yet, so they are found walking the App
        $listPath = Storage::parsePath(Package::getBuildPath(), self::ListFile);
        if (file_exists($listPath)) {
            $relPaths = Arrays::toStrings(include $listPath);
        } else {
            $relPaths = self::getRelativePaths();
        }

        foreach ($relPaths as $relPath) {
            $filePath = Application::getBasePath($relPath);
            if (file_exists($filePath)) {
                include_once $filePath;
            }
        }

        self::$loaded = true;
        return true;
    }

    /**
     * Returns the paths of the Config files that a request loads, relative to the App
     * @return list<string>
     */
    public static function getRelativePaths(): array {
        $basePath = Application::getBasePath();
        $result   = [];
        foreach (self::getFilePaths() as $filePath) {
            $relPath  = Strings::replace($filePath, $basePath, "");
            $result[] = Strings::stripStart($relPath, "/");
        }
        return $result;
    }

    /**
     * Returns the paths of the Config files of the App
     * @param bool $withConsole Optional.
     * @return list<string>
     */
    private static function getFilePaths(bool $withConsole = false): array {
        $appPath   = Application::getBasePath();
        $filePaths = Storage::getFilesInDir($appPath, recursive: true, skipVendor: true);
        $result    = [];

        foreach ($filePaths as $filePath) {
            $isConsole = $withConsole && Strings::endsWith($filePath, self::ConsoleExtension);
            if ($isConsole || Strings::endsWith($filePath, self::Extension)) {
                $result[] = $filePath;
            }
        }
        return $result;
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
