<?php
namespace Framework\Intl;

use Framework\Application;
use Framework\Discovery\Discovery;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;

/**
 * The Internalization Strings Configuration
 */
class IntlConfig {

    private static string $defaultLanguage = "en";
    private static string $stringsDir      = "nls";

    /** @var array<string,string> */
    private static array $scriptDirs = [];

    /** @var list<string> */
    private static array $sourceDirs = [];



    /**
     * Sets the Default Language
     * @param string $lang
     * @return void
     */
    public static function setDefaultLanguage(string $lang): void {
        self::$defaultLanguage = $lang;
    }

    /**
     * Sets the Strings Directory
     * @param string $dir
     * @return void
     */
    public static function setStringsDir(string $dir): void {
        self::$stringsDir = $dir;
    }

    /**
     * Adds a Directory where an App keeps its Strings as a script
     * @param string $name
     * @param string $dir
     * @return void
     */
    public static function addScriptDir(string $name, string $dir): void {
        if ($name !== "" && $dir !== "") {
            self::$scriptDirs[$name] = $dir;
        }
    }

    /**
     * Adds a Directory of Source files, which is where the Strings are used
     * @param string $dir
     * @return void
     */
    public static function addSourceDir(string $dir): void {
        if ($dir !== "" && !Arrays::contains(self::$sourceDirs, $dir)) {
            self::$sourceDirs[] = $dir;
        }
    }



    /**
     * Returns the Default Language
     * @return string
     */
    public static function getDefaultLanguage(): string {
        return self::$defaultLanguage;
    }

    /**
     * Returns the path to the Strings Directory
     * @return string
     */
    public static function getStringsPath(): string {
        return Application::getBasePath(self::$stringsDir);
    }

    /**
     * Returns the path to each Script Directory, by the name it was added with
     * @return array<string,string>
     */
    public static function getScriptPaths(): array {
        $result = [];
        foreach (self::$scriptDirs as $name => $dir) {
            $result[$name] = Application::getBasePath($dir);
        }
        return $result;
    }

    /**
     * Returns the path to each Source Directory
     * @return list<string>
     */
    public static function getSourcePaths(): array {
        $result = [];
        foreach (self::$sourceDirs as $dir) {
            $result[] = Application::getBasePath($dir);
        }
        return $result;
    }



    /**
     * Loads the Strings for the given Language
     * @param string $langCode
     * @return Dictionary
     */
    public static function loadStrings(string $langCode): Dictionary {
        $result = Discovery::loadJSON(self::$stringsDir, $langCode);
        return new Dictionary($result);
    }
}
