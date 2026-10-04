<?php
namespace Framework\Core;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Discovery\Attr\Priority;
use Framework\Builder\Builder;
use Framework\Core\VariableType;
use Framework\Utils\Strings;

/**
 * The Setting Config
 * @phpstan-type SettingEntry array{
 *   variable:     string,
 *   section:      string,
 *   variableType: VariableType,
 *   value:        mixed,
 * }
 * @phpstan-type SettingSectionData array{
 *   section: string,
 *   name:    string,
 * }
 * @phpstan-type SettingVariableData array{
 *   isFirst:   bool,
 *   section:   string,
 *   variable:  string,
 *   prefix:    string,
 *   title:     string,
 *   name:      string,
 *   type:      string,
 *   docType:   string,
 *   getter:    string,
 *   isBoolean: bool,
 *   isInteger: bool,
 *   isFloat:   bool,
 *   isString:  bool,
 *   isArray:   bool,
 *   isList:    bool,
 * }
 * @phpstan-type SettingResult array{
 *   sections?:  list<SettingSectionData>,
 *   variables?: list<SettingVariableData>,
 *   hasJSON?:   bool,
 *   total?:     int,
 * }
 */
#[Priority(Priority::Highest)]
class SettingConfig implements DiscoveryBuilder {

    public const Core    = "Core";
    public const General = "General";


    /** @var list<SettingEntry> */
    private static array $settings = [];


    /**
     * Registers a new Setting
     * @param string       $variable
     * @param string       $section
     * @param VariableType $variableType
     * @param mixed        $value        Optional.
     * @return void
     */
    public static function register(
        string $variable,
        string $section,
        VariableType $variableType,
        mixed $value = "",
    ): void {
        self::$settings[] = [
            "variable"     => $variable,
            "section"      => $section,
            "variableType" => $variableType,
            "value"        => $value,
        ];
    }

    /**
     * Returns the registered Settings
     * @return list<SettingEntry>
     */
    public static function getSettings(): array {
        return self::$settings;
    }



    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    #[NotTested("It generates a file")]
    public static function generateCode(): int {
        $data = self::collectSettings();
        return Builder::generateCode("Setting", $data);
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 1;
    }



    /**
     * Collects the Settings used to generate the code
     * @return SettingResult
     */
    public static function collectSettings(): array {
        if (count(self::$settings) === 0) {
            return [];
        }

        [ $variables, $hasJSON ] = self::getVariables();
        return [
            "sections"  => self::getSections(),
            "variables" => $variables,
            "hasJSON"   => $hasJSON,
            "total"     => count($variables),
        ];
    }

    /**
     * Returns the Settings Sections for the generator
     * @return list<SettingSectionData>
     */
    private static function getSections(): array {
        $result = [];

        foreach (self::$settings as $setting) {
            $section = $setting["section"];
            // A Section has many Settings, but its methods are written once
            if (!Strings::isEqual($section, self::General) && !isset($result[$section])) {
                $result[$section] = [
                    "section" => $section,
                    "name"    => Strings::upperCaseFirst($section),
                ];
            }
        }
        return array_values($result);
    }

    /**
     * Returns the Settings Variables for the generator
     * @return array{list<SettingVariableData>,bool}
     */
    private static function getVariables(): array {
        $result  = [];
        $isFirst = true;
        $hasJSON = false;

        foreach (self::$settings as $setting) {
            $section      = $setting["section"];
            $variable     = $setting["variable"];
            $variableType = $setting["variableType"];

            $isGeneral    = Strings::isEqual($section, self::General);
            $prefix       = !$isGeneral ? Strings::upperCaseFirst($section) : "";
            $title        = Strings::toPascalCase($variable);

            $result[] = [
                "isFirst"   => $isFirst,
                "section"   => $section,
                "variable"  => $variable,
                "prefix"    => $prefix,
                "title"     => $prefix !== "" ? "$prefix $title" : $title,
                "name"      => Strings::upperCaseFirst($variable),
                "type"      => VariableType::getType($variableType),
                "docType"   => VariableType::getDocType($variableType),
                "getter"    => $variableType === VariableType::Boolean ? "is" : "get",
                "isBoolean" => $variableType === VariableType::Boolean,
                "isInteger" => $variableType === VariableType::Integer,
                "isFloat"   => $variableType === VariableType::Float,
                "isString"  => $variableType === VariableType::String,
                "isArray"   => $variableType === VariableType::Array,
                "isList"    => $variableType === VariableType::List,
            ];

            $isFirst = false;
            if ($variableType === VariableType::Array || $variableType === VariableType::List) {
                $hasJSON = true;
            }
        }
        return [ $result, $hasJSON ];
    }
}
