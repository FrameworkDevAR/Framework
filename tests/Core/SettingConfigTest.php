<?php
namespace Tests\Core;

use Framework\Builder\Builder;
use Framework\Core\SettingConfig;
use Framework\Core\VariableType;
use Framework\Discovery\Package;
use Framework\File\Storage;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingConfigTest extends TestCase {
    use TestHelpers;

    protected function setUp(): void {
        $this->setPrivateStaticProperty(SettingConfig::class, "settings", []);
    }

    protected function tearDown(): void {
        $this->setPrivateStaticProperty(SettingConfig::class, "settings", []);
    }


    public function testRegisterAndGetSettings(): void {
        SettingConfig::register("siteName", SettingConfig::General, VariableType::String, "Test");

        $this->assertSame([
            [
                "variable"     => "siteName",
                "section"      => SettingConfig::General,
                "variableType" => VariableType::String,
                "value"        => "Test",
            ],
        ], SettingConfig::getSettings());
    }


    public function testCollectSettingsWhenEmpty(): void {
        $this->assertSame([], SettingConfig::collectSettings());
    }


    public function testCollectSettingsSectionsSkipGeneral(): void {
        SettingConfig::register("siteName", SettingConfig::General, VariableType::String);
        SettingConfig::register("apiKey", "payments", VariableType::String);

        $result = SettingConfig::collectSettings();

        // The General section is not emitted, only the custom ones
        $this->assertSame([
            [ "section" => "payments", "name" => "Payments" ],
        ], $result["sections"]);
        $this->assertSame(2, $result["total"]);
    }


    #[DataProvider("providerCollectSettingsVariable")]
    public function testCollectSettingsVariable(
        string $variable,
        string $section,
        VariableType $type,
        array $expected,
    ): void {
        SettingConfig::register($variable, $section, $type);
        $result = SettingConfig::collectSettings()["variables"][0];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $result[$key], "key: $key");
        }
    }

    public static function providerCollectSettingsVariable(): array {
        return [
            "general string"    => [
                "siteName",
                SettingConfig::General,
                VariableType::String,
                [
                    "isFirst"   => true,
                    "prefix"    => "",
                    "title"     => "SiteName",
                    "name"      => "SiteName",
                    "type"      => "string",
                    "getter"    => "get",
                    "isString"  => true,
                    "isBoolean" => false,
                ],
            ],
            "sectioned boolean" => [
                "isEnabled",
                "payments",
                VariableType::Boolean,
                [
                    "prefix"    => "Payments",
                    "title"     => "Payments IsEnabled",
                    "type"      => "bool",
                    "getter"    => "is",
                    "isBoolean" => true,
                    "isString"  => false,
                ],
            ],
            "integer"           => [
                "maxItems",
                SettingConfig::General,
                VariableType::Integer,
                [ "type" => "int", "getter" => "get", "isInteger" => true ],
            ],
            "float"             => [
                "taxRate",
                SettingConfig::General,
                VariableType::Float,
                [ "type" => "float", "isFloat" => true ],
            ],
            "array"             => [
                "options",
                SettingConfig::General,
                VariableType::Array,
                [ "type" => "array", "isArray" => true ],
            ],
            "list"              => [
                "emails",
                SettingConfig::General,
                VariableType::List,
                [ "type" => "array", "docType" => "list<string>", "isList" => true, "isArray" => false ],
            ],
        ];
    }


    #[DataProvider("providerCollectSettingsHasJSON")]
    public function testCollectSettingsHasJSON(array $types, bool $expected): void {
        foreach ($types as $index => $type) {
            SettingConfig::register("var$index", SettingConfig::General, $type);
        }

        $this->assertSame($expected, SettingConfig::collectSettings()["hasJSON"]);
    }

    public static function providerCollectSettingsHasJSON(): array {
        return [
            "no array"   => [ [ VariableType::String, VariableType::Integer ], false ],
            "with array" => [ [ VariableType::String, VariableType::Array ], true ],
            "only array" => [ [ VariableType::Array ], true ],
            "with list"  => [ [ VariableType::String, VariableType::List ], true ],
        ];
    }


    public function testASectionIsCollectedOnce(): void {
        // Its methods are written once per section, so a repeated one would not parse
        SettingConfig::register("phone", "store", VariableType::String);
        SettingConfig::register("itemsPerPage", "store", VariableType::Integer);
        SettingConfig::register("emails", "orders", VariableType::List);

        $this->assertSame([
            [ "section" => "store", "name" => "Store" ],
            [ "section" => "orders", "name" => "Orders" ],
        ], SettingConfig::collectSettings()["sections"]);
    }

    public function testTheGeneratedCodeParses(): void {
        SettingConfig::register("phone", "store", VariableType::String);
        SettingConfig::register("itemsPerPage", "store", VariableType::Integer);
        SettingConfig::register("isActive", "orders", VariableType::Boolean);
        SettingConfig::register("emails", "orders", VariableType::List);

        $template = Storage::readFile(Package::getBasePath("src/Core/Template/Setting.mu"));
        $this->setPrivateStaticProperty(Builder::class, "templates", [ "Setting" => $template ]);
        $code = Builder::render("Setting", SettingConfig::collectSettings() + [
            "namespace" => "Tests\\System",
        ]);

        token_get_all($code, TOKEN_PARSE);
        $this->assertSame(1, substr_count($code, "function getAllStore("));
        $this->assertStringContainsString("public static function getOrdersEmails(): array {", $code);
        $this->assertStringContainsString("return JSON::decodeAsStrings(\$result, withoutEmpty: true);", $code);
        $this->assertStringContainsString("public static function saveAll(array \$data): void {", $code);
    }


    public function testCollectSettingsMarksOnlyFirst(): void {
        SettingConfig::register("first", SettingConfig::General, VariableType::String);
        SettingConfig::register("second", SettingConfig::General, VariableType::String);

        $variables = SettingConfig::collectSettings()["variables"];

        $this->assertTrue($variables[0]["isFirst"]);
        $this->assertFalse($variables[1]["isFirst"]);
    }


    public function testDestroyCode(): void {
        $this->assertSame(1, SettingConfig::destroyCode());
    }
}
