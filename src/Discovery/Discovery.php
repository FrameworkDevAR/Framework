<?php
namespace Framework\Discovery;

use Framework\Application;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Discovery\Attr\Priority;
use Framework\File\Storage;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionNamedType;
use Throwable;

/**
 * The Discovery
 */
class Discovery {

    /**
     * Loads a File from the App or defaults to the Framework
     * @param string $fileName
     * @return string
     */
    public static function loadEmailTemplate(string $fileName): string {
        $path   = Application::getBasePath($fileName);
        $result = "";
        if (Storage::fileExists($path)) {
            $result = Storage::readFile($path);
        }
        if ($result === "") {
            $path   = Package::getBasePath($fileName);
            $result = Storage::readFile($path);
        }
        return $result;
    }

    /**
     * Loads a JSON File
     * @param string $dir
     * @param string $fileName
     * @return array<int|string,mixed>
     */
    public static function loadJSON(string $dir, string $fileName): array {
        $file = Strings::addSuffix($fileName, ".json");
        $path = Application::getBasePath($dir, $file);
        return JSON::readFile($path);
    }

    /**
     * Loads a Custom Data File
     * @param string $fileName
     * @return array<string,Dictionary>
     */
    public static function loadCustomData(string $fileName): array {
        $data   = self::loadJSON("", $fileName);
        $result = [];
        foreach ($data as $key => $value) {
            $result[Strings::toString($key)] = new Dictionary($value);
        }
        return $result;
    }



    /**
     * Finds the Classes with the given params
     * @param class-string|null $parentClass  Optional.
     * @param class-string|null $interface    Optional.
     * @param string            $path         Optional.
     * @param bool              $forAll       Optional.
     * @param bool              $forFramework Optional.
     * @param bool              $withError    Optional.
     * @return list<DiscoveryClass>
     */
    public static function findClasses(
        ?string $parentClass = null,
        ?string $interface = null,
        string $path = "",
        bool $forAll = false,
        bool $forFramework = false,
        bool $withError = false,
    ): array {
        if ($forAll) {
            $frameFiles = self::getAllFiles(forFramework: true);
            $appFiles   = self::getAllFiles(forFramework: false);
            $classPaths = array_merge($frameFiles, $appFiles);
        } else {
            $classPaths = self::getAllFiles($forFramework);
        }

        // The Schemas and the System of the Framework are written by the build, so an app can
        // still have a System folder of its own
        $buildPath = Package::getBuildPath() . "/";
        $result    = [];
        foreach ($classPaths as $className => $filePath) {
            $isBuilt = Strings::contains($filePath, "/Schema/");
            if ($isBuilt || Strings::startsWith($filePath, $buildPath)) {
                continue;
            }

            $file        = Storage::readFile($filePath);
            $usedClasses = self::getUsedClasses($file);

            // Skip the Class if any of its used Classes is not found
            if (!self::isValidClass($file, $usedClasses, $classPaths)) {
                if ($withError) {
                    print("Skipping class $className due to missing dependencies.\n");
                }
                continue;
            }

            // Get the Class Reflection and skip if it fails
            $reflection = null;
            $className  = "\\$className";
            try {
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                }
            } catch (Throwable $e) {
                if ($withError) {
                    print("Error loading class: $className: " . $e->getMessage() . "\n");
                }
                continue;
            }

            // Skip if the Reflection is not found
            if ($reflection === null) {
                continue;
            }

            // Check the path
            $path = Storage::addLastSlash($path);
            if ($path !== "" && !Strings::startsWith($filePath, $path)) {
                continue;
            }

            // Check the Parent Class if given
            if ($parentClass !== null && !$reflection->isSubclassOf($parentClass)) {
                continue;
            }

            // Check the Interface if given
            if ($interface !== null && !$reflection->implementsInterface($interface)) {
                continue;
            }

            // Add the Class to the result
            $result[] = new DiscoveryClass($reflection, array_values($usedClasses));
        }

        $result = self::sortClassesByPriority($result);
        return $result;
    }

    /**
     * Returns all the php files in the given Directory
     * @param bool $forFramework
     * @return array<string,string>
     */
    private static function getAllFiles(bool $forFramework): array {
        if ($forFramework) {
            $namespace  = Package::Namespace;
            $sourcePath = Package::getSourcePath();
        } else {
            $namespace  = Application::getNamespace();
            $sourcePath = Application::getSourcePath();
        }

        $filePaths = Storage::getFilesInDir($sourcePath, recursive: true);
        $result    = [];

        foreach ($filePaths as $filePath) {
            if (!Strings::endsWith($filePath, ".php")) {
                continue;
            }

            $className = Strings::replace($filePath, [ $sourcePath, ".php" ], "");
            $className = Strings::substringAfter($className, "/", useFirst: true);
            $className = Strings::replace($className, "/", "\\");
            $className = "{$namespace}{$className}";

            $result[$className] = $filePath;
        }
        return $result;
    }

    /**
     * Returns the used Classes in the given File
     * @param string $file
     * @return array<string,string>
     */
    private static function getUsedClasses(string $file): array {
        $lines  = Strings::split($file, "\n", trim: false, skipEmpty: true);
        $result = [];

        foreach ($lines as $line) {
            if (Strings::startsWith($line, "use ")) {
                $usedClass     = Strings::substringBetween($line, "use ", ";");
                $usedClassName = Strings::substringAfter($usedClass, "\\");
                $result[$usedClassName] = $usedClass;
            }
        }
        return $result;
    }

    /**
     * Checks if the given Class is valid by checking that all its used Classes are found
      * @param string               $fileContent
      * @param array<string,string> $usedClasses
      * @param array<string,string> $classPaths
      * @return bool
      */
    private static function isValidClass(
        string $fileContent,
        array $usedClasses,
        array $classPaths,
    ): bool {
        // Extract the namespace from the file with Regexp
        $namespacePattern = '/^namespace\s+([A-Za-z0-9]+).*$/m';
        $matches          = Strings::getAllMatches($fileContent, $namespacePattern);
        $namespace        = $matches[1] ?? "";
        if ($namespace === "") {
            return false;
        }

        // Extract the class name and extends from the file with Regexp
        $classPattern    = '/^class\s+(\w+)\s*(?:extends\s+([^\s;]+))?.*$/m';
        $matches         = Strings::getAllMatches($fileContent, $classPattern);
        $baseClassName   = $matches[1] ?? "";
        $parentClassName = $matches[2] ?? "";

        if ($baseClassName === "") {
            return false;
        }
        if ($parentClassName === "") {
            return true;
        }

        // Check that the used Class exists
        $fullClassName = $usedClasses[$parentClassName] ?? "";
        if (Strings::startsWith($fullClassName, $namespace) &&
            !isset($classPaths[$fullClassName])
        ) {
            return false;
        }

        // Extract the trait use
        $traitPattern  = '/^    use\s+([A-Za-z0-9\\\\]+);/m';
        $matches       = Strings::getAllMatches($fileContent, $traitPattern);
        $traitClass    = $matches[1] ?? "";
        $fullClassName = $usedClasses[$traitClass] ?? "";
        if (Strings::startsWith($fullClassName, $namespace) &&
            !isset($classPaths[$fullClassName])
        ) {
            return false;
        }

        return true;
    }

    /**
     * Sorts the given Classes by their Priority
     * @param list<DiscoveryClass> $classes
     * @return list<DiscoveryClass>
     */
    private static function sortClassesByPriority(array $classes): array {
        $priorities = [];
        $instances  = [];
        $result     = [];

        foreach ($classes as $class) {
            $priority = $class->getPriority();
            if (!isset($instances[$priority])) {
                $priorities[] = $priority;
            }
            $instances[$priority][] = $class;
        }

        sort($priorities);
        foreach ($priorities as $priority) {
            if (isset($instances[$priority])) {
                foreach ($instances[$priority] as $class) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }



    /**
     * Returns the Reflection Properties of the given Class
     * @param object   $class
     * @param int|null $filter Optional.
     * @return array<ReflectionProperty>
     */
    public static function getReflectionProps(object $class, ?int $filter = null): array {
        if ($filter === null) {
            $filter = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
        }
        $reflection = new ReflectionClass($class);
        return $reflection->getProperties($filter);
    }

    /**
     * Returns the Properties of the given Class
     * @param object   $class
     * @param int|null $filter Optional.
     * @return array<string,string>
     */
    public static function getProperties(object $class, ?int $filter = null): array {
        $props  = self::getReflectionProps($class, $filter);
        $result = [];
        foreach ($props as $prop) {
            $type     = $prop->getType();
            $typeName = "mixed";
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
            }
            $result[$prop->getName()] = $typeName;
        }
        return $result;
    }

    /**
     * Returns the Property Names of the given Class
     * @param object   $class
     * @param int|null $filter Optional.
     * @return array<string>
     */
    public static function getPropertyNames(object $class, ?int $filter = null): array {
        $props  = self::getReflectionProps($class, $filter);
        $result = [];
        foreach ($props as $prop) {
            $result[] = $prop->getName();
        }
        return $result;
    }

    /**
     * Returns the priority from a ReflectionClass or ReflectionMethod.
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @return int
     */
    public static function getPriority(
        ReflectionClass|ReflectionMethod $reflection,
    ): int {
        $attributes = $reflection->getAttributes(Priority::class);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance()->priority;
        }
        return Priority::Normal;
    }
}
