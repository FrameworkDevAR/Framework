<?php
namespace {{namespace}};

use Framework\Core\SettingData;{{#hasJSON}}
use Framework\Utils\JSON;{{/hasJSON}}

/**
 * The Setting
 */
class Setting {

    /**
     * Returns all the Settings
     * @return array<string,array<string,string>|string>|object
     */
    public static function getAll(): array {
        return SettingData::getAll();
    }

    /**
     * Saves all the Settings
     * @param array<string,mixed> $data
     * @return void
     */
    public static function saveAll(array $data): void {
        SettingData::saveAll($data);
    }



{{#sections}}
    /**
     * Returns all the Settings for {{name}}
     * @param bool $asObject Optional.
     * @return array<string,string>|object
     */
    public static function getAll{{name}}(bool $asObject = false): array|object {
        return SettingData::getAll("{{section}}", $asObject);
    }

    /**
     * Saves all the Settings for {{name}}
     * @param array<string,mixed> $data
     * @return void
     */
    public static function save{{name}}(array $data): void {
        SettingData::saveSection("{{section}}", $data);
    }

{{/sections}}
{{#variables}}
{{^isFirst}}



{{/isFirst}}
    /**
     * Returns the value of "{{title}}"
     * @return {{{docType}}}
     */
    public static function {{getter}}{{prefix}}{{name}}(): {{type}} {
        $result = SettingData::get("{{section}}", "{{variable}}");
        {{#isBoolean}}
        return !empty($result);
        {{/isBoolean}}
        {{#isInteger}}
        return $result !== null ? (int)$result : 0;
        {{/isInteger}}
        {{#isFloat}}
        return $result !== null ? (float)$result : 0;
        {{/isFloat}}
        {{#isString}}
        return $result !== null ? (string)$result : "";
        {{/isString}}
        {{#isArray}}
        return $result !== null ? JSON::decodeAsArray($result) : [];
        {{/isArray}}
        {{#isList}}
        return JSON::decodeAsStrings($result, withoutEmpty: true);
        {{/isList}}
    }

    /**
     * Sets the value of "{{title}}"
     * @param {{{docType}}} $value
     * @return bool
     */
    public static function set{{prefix}}{{name}}({{type}} $value): bool {
        {{#isBoolean}}
        $value = !empty($value) ? 1 : 0;
        {{/isBoolean}}
        {{#isArray}}
        $value = JSON::encode($value);
        {{/isArray}}
        {{#isList}}
        $value = JSON::encode($value);
        {{/isList}}
        return SettingData::set("{{section}}", "{{variable}}", (string)$value);
    }
{{/variables}}
}
