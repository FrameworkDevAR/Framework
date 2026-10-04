<?php
namespace Framework\Discovery;

use Framework\Application;
use Framework\Discovery\Type\ComposerData;
use Framework\File\Storage;

/**
 * The Framework Package
 */
class Package {

    // Framework Constants
    public const Namespace   = "Framework\\";
    public const ConfigDir   = "config";
    public const TemplateDir = "data/templates";

    // Documentation
    public const DocsDir     = "docs";
    public const DocsPort    = 2000;

    // Source Directories
    public const SystemDir   = "System";
    public const SchemaDir   = "Schema";
    public const ModelDir    = "Model";


    // The Composer Data of the Framework, read once and kept
    private static ?ComposerData $composer = null;



    /**
     * Loads the Framework Composer Data
     * @return ComposerData
     */
    private static function load(): ComposerData {
        if (self::$composer === null) {
            self::$composer = Composer::readFile(self::getBasePath());
        }
        return self::$composer;
    }

    /**
     * Returns the Framework Composer Data
     * @return ComposerData
     */
    public static function getComposer(): ComposerData {
        return self::load();
    }

    /**
     * Returns the Framework Version
     * @return string
     */
    public static function getVersion(): string {
        return self::load()->version;
    }

    /**
     * Returns the Framework Source Directory
     * @return string
     */
    public static function getSourceDir(): string {
        return self::load()->sourceDir;
    }



    /**
     * Checks if the Application is the Framework itself
     * @return bool
     */
    public static function isFramework(): bool {
        $appPath   = Application::getBasePath();
        $framePath = self::getBasePath();
        return $appPath === $framePath;
    }

    /**
     * Returns the base path to the Framework
     * @param string ...$pathParts
     * @return string
     */
    public static function getBasePath(string ...$pathParts): string {
        $path = Storage::getDirectory(__FILE__, 3);
        return Storage::parsePath($path, ...$pathParts);
    }

    /**
     * Returns the path to the Source Directory
     * @param string ...$pathParts
     * @return string
     */
    public static function getSourcePath(string ...$pathParts): string {
        return self::getBasePath(self::getSourceDir(), ...$pathParts);
    }

    /**
     * Returns the Path used to store the Built files
     * @return string
     */
    public static function getBuildPath(): string {
        return self::getSourcePath(self::SystemDir);
    }
}
