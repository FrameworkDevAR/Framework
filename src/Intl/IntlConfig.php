<?php
namespace Framework\Intl;

use Framework\Application;
use Framework\Discovery\Discovery;
use Framework\Discovery\DiscoveryConfig;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;

/**
 * The Internalization Strings Configuration
 */
class IntlConfig {

    private static string $defaultLanguage  = "en";
    private static string $stringsDir       = "nls/strings";
    private static string $notificationsDir = "nls/notifications";

    /** @var array<string,string> */
    private static array $scriptDirs = [];

    /** @var list<string> */
    private static array $sourceDirs = [];

    private static bool $loaded = false;



    /**
     * Loads the Intl Config of the App, the first time that a value is requested
     * @return void
     */
    private static function loadConfig(): void {
        // A request does not load the Config files, so the values of the App would be
        // the defaults. It is marked first, as the Config calls the setters
        if (!self::$loaded) {
            self::$loaded = true;
            DiscoveryConfig::loadFile("Intl");
        }
    }



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
     * Sets the Notifications Directory
     * @param string $dir
     * @return void
     */
    public static function setNotificationsDir(string $dir): void {
        self::$notificationsDir = $dir;
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
        self::loadConfig();
        return self::$defaultLanguage;
    }

    /**
     * Returns the path to the Strings Directory
     * @return string
     */
    public static function getStringsPath(): string {
        self::loadConfig();
        return Application::getBasePath(self::$stringsDir);
    }

    /**
     * Returns the path to the Notifications Directory
     * @return string
     */
    public static function getNotificationsPath(): string {
        self::loadConfig();
        return Application::getBasePath(self::$notificationsDir);
    }

    /**
     * Returns the path to each Script Directory, by the name it was added with
     * @return array<string,string>
     */
    public static function getScriptPaths(): array {
        self::loadConfig();
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
        self::loadConfig();
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
        self::loadConfig();
        $result = Discovery::loadJSON(self::$stringsDir, $langCode);
        return new Dictionary($result);
    }

    /**
     * Loads the Notifications for the given Language
     * @param string $langCode
     * @return Dictionary
     */
    public static function loadNotifications(string $langCode): Dictionary {
        self::loadConfig();
        $result = Discovery::loadJSON(self::$notificationsDir, $langCode);
        return new Dictionary($result);
    }
}
