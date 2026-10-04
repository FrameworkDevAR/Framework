<?php
namespace Tests\Core;

use Framework\Core\VariableType;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class VariableTypeTest extends TestCase {

    #[DataProvider("providerGet")]
    public function testGet(mixed $value, bool $useLists, VariableType $expected): void {
        $this->assertSame($expected, VariableType::get($value, $useLists));
    }

    public static function providerGet(): array {
        return [
            "assoc array" => [ [ "a" => 1 ], false, VariableType::Array ],
            "list array"  => [ [ "a", "b" ], true, VariableType::List ],
            "bool"        => [ true, false, VariableType::Boolean ],
            "int"         => [ 10, false, VariableType::Integer ],
            "float"       => [ 10.5, false, VariableType::Float ],
            "string"      => [ "test", false, VariableType::String ],
            "object"      => [ new stdClass(), false, VariableType::String ],
        ];
    }


    #[DataProvider("providerGetType")]
    public function testGetType(VariableType $type, string $expected): void {
        $this->assertSame($expected, VariableType::getType($type));
    }

    public static function providerGetType(): array {
        return [
            "array"   => [ VariableType::Array, "array" ],
            "list"    => [ VariableType::List, "array" ],
            "bool"    => [ VariableType::Boolean, "bool" ],
            "int"     => [ VariableType::Integer, "int" ],
            "float"   => [ VariableType::Float, "float" ],
            "string"  => [ VariableType::String, "string" ],
            "default" => [ VariableType::None, "string" ],
        ];
    }


    #[DataProvider("providerGetDocType")]
    public function testGetDocType(VariableType $type, string $expected): void {
        $this->assertSame($expected, VariableType::getDocType($type));
    }

    public static function providerGetDocType(): array {
        return [
            "array"   => [ VariableType::Array,   "array<int|string,mixed>" ],
            "list"    => [ VariableType::List,    "list<string>"            ],
            "bool"    => [ VariableType::Boolean, "bool"                    ],
            "int"     => [ VariableType::Integer, "int"                     ],
            "float"   => [ VariableType::Float,   "float"                   ],
            "string"  => [ VariableType::String,  "string"                  ],
            "default" => [ VariableType::None,    "string"                  ],
        ];
    }


    #[DataProvider("providerDecodeValue")]
    public function testDecodeValue(VariableType $type, string $value, mixed $expected): void {
        $this->assertSame($expected, VariableType::decodeValue($type, $value));
    }

    public static function providerDecodeValue(): array {
        return [
            "array json"     => [ VariableType::Array, '{"a":1}', [ "a" => 1 ] ],
            "array empty"    => [ VariableType::Array, "{}", [] ],
            "list json"      => [ VariableType::List, '["a","","b"]', [ "a", "b" ] ],
            "list empty"     => [ VariableType::List, "", [] ],
            "bool true"      => [ VariableType::Boolean, "1", true ],
            "bool false"     => [ VariableType::Boolean, "0", false ],
            "bool empty"     => [ VariableType::Boolean, "", false ],
            "int value"      => [ VariableType::Integer, "42", 42 ],
            "int empty"      => [ VariableType::Integer, "", 0 ],
            "float value"    => [ VariableType::Float, "1.5", 1.5 ],
            "string value"   => [ VariableType::String, "hello", "hello" ],
            "default"        => [ VariableType::None, "hello", "hello" ],
        ];
    }


    #[DataProvider("providerEncodeValue")]
    public function testEncodeValue(VariableType $type, mixed $value, string $expected): void {
        $this->assertSame($expected, VariableType::encodeValue($type, $value));
    }

    public static function providerEncodeValue(): array {
        return [
            "array empty"    => [ VariableType::Array, [], "{}" ],
            "array json"     => [ VariableType::Array, [ "a" => 1 ], '{"a":1}' ],
            "list empty"     => [ VariableType::List, [], "[]" ],
            "list json"      => [ VariableType::List, [ "a", "b" ], '["a","b"]' ],
            "bool empty"     => [ VariableType::Boolean, "", "0" ],
            "bool true"      => [ VariableType::Boolean, true, "1" ],
            "int empty"      => [ VariableType::Integer, null, "0" ],
            "int value"      => [ VariableType::Integer, 42, "42" ],
            "float value"    => [ VariableType::Float, 1.5, "1.5" ],
            "string value"   => [ VariableType::String, "hello", "hello" ],
            "string invalid" => [ VariableType::String, new stdClass(), "" ],
        ];
    }
}
