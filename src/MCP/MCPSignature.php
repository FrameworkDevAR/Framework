<?php
namespace Framework\MCP;

use Framework\Builder\Builder;
use Framework\Database\Type\SchemaRequest;
use Framework\Date\Date;
use Framework\MCP\Attr\MCPTool;
use Framework\Enum\Enum;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The MCP Signature
 */
class MCPSignature {

    public const ListPrefixes = [ "list", "search" ];

    private const ParamPattern = '/@param\s+(\S+)\s+\$(\w+)[ \t]*([^\n]*)/';



    /**
     * Returns the code that passes the arguments to the given Tool, or null if it can not take them
     * @param ReflectionMethod $method
     * @return string|null
     */
    public static function getArguments(ReflectionMethod $method): ?string {
        $params = $method->getParameters();
        if (count($params) === 0) {
            return "";
        }

        // A Tool takes the Request or one of its Schema like a Route, and each other argument as a
        // param of its own, which is read from the Request with the getter of its type
        $requestType = self::getRequestType($params[0]);
        if (count($params) === 1 && $requestType !== "") {
            return Builder::getRequestParam($method);
        }

        $docTypes = self::getDocParams($method);
        $result   = [];
        if ($requestType !== "") {
            $result[] = self::getRequestCode($requestType);
            $params   = array_slice($params, 1);
        }
        foreach ($params as $param) {
            $getter = self::getGetter($param, $docTypes[$param->getName()]["type"] ?? "");
            if ($getter === null) {
                return null;
            }
            $result[] = "{$param->getName()}: $getter";
        }
        return Strings::join($result, ", ");
    }

    /**
     * Returns the Type of the given Param when it is the Request or one of its Schema
     * @param ReflectionParameter $param
     * @return string
     */
    private static function getRequestType(ReflectionParameter $param): string {
        $type = $param->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return "";
        }

        // A Date or an Enum is an argument of its own, and any other class is the Request
        $typeName = $type->getName();
        if ($typeName === Date::class || is_subclass_of($typeName, Enum::class)) {
            return "";
        }
        return $typeName;
    }

    /**
     * Returns the code that passes the Request, or one of its Schema, of the given Type
     * @param string $typeName
     * @return string
     */
    private static function getRequestCode(string $typeName): string {
        if (Strings::endsWith($typeName, "\\Request")) {
            return "\$request";
        }
        return "\\$typeName::fromRequest(\$request)";
    }

    /**
     * Returns the code that reads the given Param from the Request
     * @param ReflectionParameter $param
     * @param string              $docType
     * @return string|null
     */
    private static function getGetter(ReflectionParameter $param, string $docType): ?string {
        $type = $param->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        // An Enum is read from its name and a Date from the date format, like in a Schema
        $name     = $param->getName();
        $typeName = $type->getName();
        $getter   = self::getBuiltinGetter($name, $typeName, $docType);
        if ($typeName === Date::class) {
            $getter = "\$request->getDate(\"$name\")";
        } elseif (is_subclass_of($typeName, Enum::class)) {
            $getter = "\\$typeName::fromValue(\$request->getString(\"$name\"))";
        }
        if ($getter === null || !$param->isDefaultValueAvailable()) {
            return $getter;
        }

        // An argument that is not sent keeps the default, so a null can tell it apart
        $default = var_export($param->getDefaultValue(), return: true);
        $default = Strings::replace($default, [ "NULL", "array (\n)" ], [ "null", "[]" ]);
        return "\$request->has(\"$name\") ? $getter : $default";
    }

    /**
     * Returns the code that reads the given Param of a built in Type from the Request
     * @param string $name
     * @param string $typeName
     * @param string $docType
     * @return string|null
     */
    private static function getBuiltinGetter(
        string $name,
        string $typeName,
        string $docType,
    ): ?string {
        return match ($typeName) {
            "int"    => "\$request->getInt(\"$name\")",
            "float"  => "\$request->getFloat(\"$name\")",
            "bool"   => "\$request->getBool(\"$name\")",
            "string" => "\$request->getString(\"$name\")",
            "array"  => Strings::contains($docType, "int>")
                ? "\$request->getInts(\"$name\")"
                : "\$request->getStrings(\"$name\")",
            default  => null,
        };
    }



    /**
     * Returns the JSON Schema of the arguments of the given Tool
     * @param ReflectionMethod $method
     * @return array<string,mixed>
     */
    public static function getInputSchema(ReflectionMethod $method): array {
        $params     = $method->getParameters();
        $properties = [];
        $required   = [];

        $className = isset($params[0]) ? self::getRequestType($params[0]) : "";
        if ($className !== "") {
            if (is_subclass_of($className, SchemaRequest::class)) {
                $properties = $className::getInputSchema();
                if (Strings::startsWith($method->getName(), ...self::ListPrefixes)) {
                    $properties += self::getListSchema();
                }
            }
            $params = array_slice($params, 1);
        }

        $docParams = self::getDocParams($method);
        foreach ($params as $param) {
            $name   = $param->getName();
            $schema = self::getParamSchema($param, $docParams[$name]["type"] ?? "");
            if (($docParams[$name]["text"] ?? "") !== "") {
                $schema["description"] = $docParams[$name]["text"];
            }
            $properties[$name] = $schema;
            if (!$param->isOptional()) {
                $required[] = $name;
            }
        }

        // An empty list would be written as an array, and the schema needs an object
        $result = [ "type" => "object", "properties" => (object)$properties ];
        if (count($required) > 0) {
            $result["required"] = $required;
        }
        return $result;
    }

    /**
     * Returns the Description of the given Tool, from its Attribute or its doc comment
     * @param ReflectionMethod $method
     * @return string
     */
    public static function getDescription(ReflectionMethod $method): string {
        $attribute = $method->getAttributes(MCPTool::class)[0] ?? null;
        $tool      = $attribute?->newInstance();
        if ($tool !== null && $tool->description !== "") {
            return $tool->description;
        }

        // The doc comment starts with what the method does, which can take several lines
        // until its tags, as it is what the Assistant reads
        $result = [];
        foreach (Strings::split((string)$method->getDocComment(), "\n") as $line) {
            $text = trim(Strings::stripStart(trim($line), "/**", "*/", "*"));
            if (Strings::startsWith($text, "@")) {
                break;
            }
            if ($text !== "") {
                $result[] = $text;
            }
        }
        return Strings::join($result, " ");
    }

    /**
     * Returns the names of the inputs that the given Tool needs, with the ones of its params
     * @param ReflectionMethod $method
     * @return list<string>
     */
    public static function getRequired(ReflectionMethod $method): array {
        $schema = self::getInputSchema($method);
        if (!isset($schema["required"]) || !is_array($schema["required"])) {
            return [];
        }
        return Arrays::toStrings($schema["required"]);
    }

    /**
     * Returns the JSON Schema of the inputs that sort and paginate a list
     * @return array<string,array<string,string>>
     */
    public static function getListSchema(): array {
        return [
            "page"     => [ "type" => "integer", "description" => "The page, starting at 0" ],
            "amount"   => [ "type" => "integer", "description" => "The amount of each page" ],
            "orderBy"  => [ "type" => "string", "description" => "The field to sort by" ],
            "orderAsc" => [ "type" => "boolean", "description" => "True to sort ascending" ],
        ];
    }

    /**
     * Returns the JSON Schema of the given Param
     * @param ReflectionParameter $param
     * @param string              $docType
     * @return array<string,mixed>
     */
    private static function getParamSchema(ReflectionParameter $param, string $docType): array {
        $type = $param->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : "";
        if ($name === Date::class) {
            return [ "type" => "string", "format" => "date" ];
        }
        if (is_subclass_of($name, Enum::class) && method_exists($name, "getNames")) {
            return [ "type" => "string", "enum" => $name::getNames() ];
        }
        return match ($name) {
            "int"   => [ "type" => "integer" ],
            "float" => [ "type" => "number" ],
            "bool"  => [ "type" => "boolean" ],
            "array" => [
                "type"  => "array",
                "items" => [ "type" => Strings::contains($docType, "int>") ? "integer" : "string" ],
            ],
            default => [ "type" => "string" ],
        };
    }

    /**
     * Returns the type and the text of each Param of the doc comment of the given Method
     * @param ReflectionMethod $method
     * @return array<string,array{type:string,text:string}>
     */
    private static function getDocParams(ReflectionMethod $method): array {
        $comment = (string)$method->getDocComment();
        $result  = [];
        if (preg_match_all(self::ParamPattern, $comment, $matches, PREG_SET_ORDER) === false) {
            return $result;
        }
        foreach ($matches as $match) {
            $text = trim(Strings::replace($match[3], "Optional.", ""));
            $result[$match[2]] = [ "type" => $match[1], "text" => $text ];
        }
        return $result;
    }
}
