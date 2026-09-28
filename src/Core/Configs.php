<?php
namespace Framework\Core;

use Framework\Application;
use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Attr\Priority;
use Framework\Builder\Builder;
use Framework\Core\VariableType;
use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Numbers;
use Framework\Utils\Server;
use Framework\Utils\Strings;
use Framework\Utils\URL;

/**
 * The Configs
 * @phpstan-type ConfigsData array{
 *   data:         array<string,mixed>,
 *   environment:  string,
 *   environments: list<string>,
 * }
 * @phpstan-type ConfigsResult array{
 *   environments?: list<array{name:string,environment:string}>,
 *   urls?:         list<array<string,mixed>>,
 *   properties?:   list<array<string,mixed>>,
 *   total?:        int,
 * }
 */
#[Priority(Priority::Highest)]
class Configs implements DiscoveryBuilder {

    private static bool   $loaded      = false;
    private static string $environment = "local";
    private static string $fileName    = "";

    /** @var array<string,mixed> */
    private static array $data         = [];

    /** @var list<string> */
    private static array $environments = [];



    /**
     * Sets the Config File Name
     * @param string $fileName
     * @return void
     */
    public static function setFileName(string $fileName): void {
        self::$fileName = $fileName;
    }

    /**
     * Loads the Config Data
     * @return void
     */
    public static function load(): void {
        if (self::$loaded) {
            return;
        }

        // Read using the getenv function or the saved file name
        $fileName = getenv("ENV_FILENAME");
        if (self::$fileName !== "") {
            $fileName = self::$fileName;
        }

        $result = self::readConfigs(
            framePath:   Package::getBasePath(),
            appPath:     Application::getBasePath(),
            currentHost: URL::getHost(Server::getUrl()),
            fileName:    $fileName !== false ? $fileName : "",
        );

        self::$loaded       = true;
        self::$data         = $result["data"];
        self::$environment  = $result["environment"];
        self::$environments = $result["environments"];
    }

    /**
     * Reads the Config Data from the given paths
     *
     * The Framework gives the defaults and the App writes over them, and one
     * of the env files of the App writes over both: the one it was asked for
     * by name, or the one whose url is the one being served.
     *
     * @param string $framePath
     * @param string $appPath
     * @param string $currentHost
     * @param string $fileName    Optional.
     * @return ConfigsData
     */
    public static function readConfigs(
        string $framePath,
        string $appPath,
        string $currentHost,
        string $fileName = "",
    ): array {
        $frameData    = self::loadENV($framePath, ".env.example");
        $appData      = self::loadENV($appPath, ".env");
        $environment  = "local";
        $environments = [];
        $replace      = [];

        // The file was named, so it is the one read and the one the
        // environment is called after — as long as it was really there
        if ($fileName !== "") {
            $replace = self::loadENV($appPath, $fileName);
            $name    = Strings::replace($fileName, ".env.", "");
            if (count($replace) > 0) {
                $environment = $name;
            }

        // Read all the .env.* files in the App Path
        } else {
            $files = Storage::getFilesInDir($appPath);
            foreach ($files as $file) {
                // Skip the example and main env files
                if ($file === ".env.example" || $file === ".env" ||
                    !Strings::startsWith($file, ".env.")
                ) {
                    continue;
                }

                $name = Strings::replace($file, ".env.", "");
                if (Arrays::contains($environments, $name)) {
                    continue;
                }

                $values = self::loadENV($appPath, $file);
                foreach ($values as $key => $value) {
                    if (Strings::endsWith($key, "URL")) {
                        $host = Strings::toString($value);
                        if (URL::getHost($host) === $currentHost) {
                            $environment = $name;
                            $replace     = $values;
                            break;
                        }
                    }
                }
                $environments[] = $name;
            }
        }

        return [
            "data"         => Arrays::merge($frameData, $appData, $replace),
            "environment"  => $environment,
            "environments" => $environments,
        ];
    }

    /**
     * Parses the Contents of the env files
     * @param string $path
     * @param string $fileName
     * @return array<string,mixed>
     */
    private static function loadENV(string $path, string $fileName): array {
        $contents = Storage::readFile($path, $fileName);
        $lines    = Strings::split($contents, "\n");
        $result   = [];

        foreach ($lines as $line) {
            if (trim($line) === "" || Strings::startsWith($line, "#")) {
                continue;
            }

            $parts = Strings::split($line, " = ");
            if (count($parts) !== 2) {
                continue;
            }

            $key   = trim($parts[0]);
            $value = trim($parts[1]);

            if ($value === "true") {
                $value = true;
            } elseif ($value === "false") {
                $value = false;
            } elseif (Strings::startsWith($value, "\"")) {
                $value = Strings::replace($value, "\"", "");
            } elseif (Strings::startsWith($value, "[")) {
                $value = Strings::substringBetween($value, "[", "]");
                $value = Strings::replace($value, [ "\"", " " ], "");
                $value = Strings::split($value, ",");
            } elseif (Strings::contains($value, ".")) {
                $value = (float)$value;
            } else {
                $value = (int)$value;
            }

            $result[$key] = $value;
        }
        return $result;
    }



    /**
     * Returns the Config Data
     * @return array<string,mixed>
     */
    public static function getData(): array {
        self::load();
        return self::$data;
    }

    /**
     * Returns the Config Environments
     * @return list<string>
     */
    public static function getEnvironments(): array {
        self::load();
        return self::$environments;
    }

    /**
     * Returns the Config Environment
     * @return string
     */
    public static function getEnvironment(): string {
        self::load();
        return self::$environment;
    }

    /**
     * Returns a Config Property or null
     * @param string $property
     * @return mixed
     */
    private static function get(string $property): mixed {
        self::load();
        $upperKey = Strings::toConstantCase($property);
        return self::$data[$upperKey] ?? null;
    }

    /**
     * Returns a Config Property as a String
     * @param string $property
     * @param string $default  Optional.
     * @return string
     */
    public static function getString(string $property, string $default = ""): string {
        $value = self::get($property);
        return $value !== null ? Strings::toString($value) : $default;
    }

    /**
     * Returns a Config Property as a Boolean
     * @param string $property
     * @return bool
     */
    public static function getBoolean(string $property): bool {
        $value = self::get($property);
        return !Arrays::isEmpty($value);
    }

    /**
     * Returns a Config Property as an Int
     * @param string $property
     * @param int    $default  Optional.
     * @return int
     */
    public static function getInt(string $property, int $default = 0): int {
        $value = self::get($property);
        if ($value !== null) {
            return Numbers::toInt($value);
        }
        return $default;
    }

    /**
     * Returns a Config Property as a Float
     * @param string $property
     * @param float  $default  Optional.
     * @return float
     */
    public static function getFloat(string $property, float $default = 0): float {
        $value = self::get($property);
        if ($value !== null) {
            return Numbers::toFloat($value);
        }
        return $default;
    }

    /**
     * Returns a Config Property as a List
     * @param string $property
     * @return list<string>
     */
    public static function getList(string $property): array {
        $value = self::get($property);
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return Arrays::toStrings($value);
        }
        $value = Strings::toString($value);
        return Strings::split($value, ",");
    }



    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        return Builder::generateCode("Config", self::collectConfigs(
            self::getData(),
            self::getEnvironments(),
        ));
    }

    /**
     * Collects the Configs used to generate the code
     * @param array<string,mixed> $data
     * @param list<string>        $environments
     * @return ConfigsResult
     */
    public static function collectConfigs(array $data, array $environments): array {
        if (Arrays::isEmpty($data)) {
            return [];
        }

        // The "local" Environment is always generated, so it is skipped here
        $result = [];
        foreach ($environments as $environment) {
            if ($environment === "local") {
                continue;
            }
            $result[] = [
                "name"        => Strings::upperCaseFirst($environment),
                "environment" => $environment,
            ];
        }

        // Builds the code
        [ $urls, $properties ] = self::getProperties($data);
        return [
            "environments" => $result,
            "urls"         => $urls,
            "properties"   => $properties,
            "total"        => count($properties),
        ];
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function destroyCode(): int {
        return 1;
    }

    /**
     * Returns the Config Properties for the generator
     * @param array<string,mixed> $data
     * @return array{list<array<string,mixed>>,list<array<string,mixed>>}
     */
    private static function getProperties(array $data): array {
        $urls       = [];
        $properties = [];

        foreach ($data as $envKey => $value) {
            $property = Strings::toCamelCase($envKey);
            $name     = Strings::toPascalCase($envKey);

            if (Strings::endsWith($envKey, "URL")) {
                $urls[] = [
                    "property" => $property,
                    "name"     => $name,
                ];
                continue;
            }

            $variableType = VariableType::get($value, useLists: true);
            $properties[] = [
                "property"  => $property,
                "name"      => $name,
                "type"      => VariableType::getType($variableType),
                "docType"   => VariableType::getDocType($variableType),
                "getter"    => $variableType === VariableType::Boolean ? "is" : "get",
                "isString"  => $variableType === VariableType::String,
                "isBoolean" => $variableType === VariableType::Boolean,
                "isInteger" => $variableType === VariableType::Integer,
                "isFloat"   => $variableType === VariableType::Float,
                "isList"    => $variableType === VariableType::List,
            ];
        }

        return [ $urls, $properties ];
    }
}
