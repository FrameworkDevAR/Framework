<?php
namespace Tests\Database;

use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Date\Date;
use Framework\File\File;
use Framework\Utils\JSON;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Field Attribute, which turns a property of a Model into a column
 */
class FieldTest extends TestCase {
    use TestHelpers;

    /**
     * Returns a Field of the given type, named the same in every place
     * @param FieldType $type
     * @param int       $length   Optional.
     * @param bool      $isID     Optional.
     * @param bool      $isSigned Optional.
     * @return Field
     */
    private function field(
        FieldType $type,
        int $length = 0,
        bool $isID = false,
        bool $isSigned = false,
    ): Field {
        return Field::create(
            name:       "value",
            prefixName: "value",
            type:       $type,
            isID:       $isID,
            length:     $length,
            isSigned:   $isSigned,
        );
    }



    /**
     * The type of the property, and the type the Field takes from it
     * @param string    $typeName
     * @param bool      $isEnum
     * @param FieldType $expected
     * @return void
     */
    #[DataProvider("providerTypeName")]
    public function testTheTypeIsReadFromTheProperty(
        string $typeName,
        bool $isEnum,
        FieldType $expected,
    ): void {
        $field = (new Field())->setData("value", $typeName, isEnum: $isEnum);

        $this->assertSame($expected, $field->type);
        $this->assertSame("value", $field->name);
        $this->assertSame("value", $field->dbName);
    }

    /**
     * A type the Field does not know falls back to a string, since that is
     * what any column of one comes back from the database as
     * @return array<string,array{string,bool,FieldType}>
     */
    public static function providerTypeName(): array {
        return [
            "a string"     => [ "string", false, FieldType::String ],
            "a number"     => [ "int", false, FieldType::Number ],
            "a float"      => [ "float", false, FieldType::Float ],
            "a boolean"    => [ "bool", false, FieldType::Boolean ],
            "an array"     => [ "array", false, FieldType::Array ],
            "a date"       => [ Date::class, false, FieldType::Date ],
            "a file"       => [ File::class, false, FieldType::File ],
            "a json"       => [ JSON::class, false, FieldType::JSON ],
            "an enum"      => [ "Tests\\Crate", true, FieldType::Enum ],
            "one it lacks" => [ "notAType", false, FieldType::String ],
        ];
    }

    public function testTheEnumClassIsKeptWithTheType(): void {
        $field = (new Field())->setData("kind", "Tests\\Crate", isEnum: true);

        $this->assertSame(FieldType::Enum, $field->type);
        $this->assertSame("Tests\\Crate", $field->enumClass);
    }

    /**
     * The flag on a string property, and the type it turns the column into
     * @param string    $flag
     * @param FieldType $expected
     * @return void
     */
    #[DataProvider("providerStringFlags")]
    public function testAStringTakesTheTypeItsFlagAsksFor(
        string $flag,
        FieldType $expected,
    ): void {
        $field = new Field();
        if ($flag !== "") {
            $field->$flag = true;
        }
        $field->setData("value", "string", isEnum: false);

        $this->assertSame($expected, $field->type);
    }

    /**
     * @return array<string,array{string,FieldType}>
     */
    public static function providerStringFlags(): array {
        return [
            "nothing set" => [ "", FieldType::String ],
            "a text"      => [ "isText", FieldType::Text ],
            "a long text" => [ "isLongText", FieldType::LongText ],
            "an encrypt"  => [ "isEncrypt", FieldType::Encrypt ],
            "a file"      => [ "isFile", FieldType::File ],
        ];
    }



    /**
     * A Field, and the column the migration writes for it
     * @param Field  $field
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerSqlType")]
    public function testTheColumnIsWrittenFromTheTypeAndItsLength(
        Field $field,
        string $expected,
    ): void {
        $this->assertSame($expected, $field->getType());
    }

    /**
     * The length of a number picks the smallest integer that holds it, and a
     * type with no default is written without one
     * @return array<string,array{Field,string}>
     */
    public static function providerSqlType(): array {
        $make = fn(FieldType $type, int $length = 0, bool $isID = false, bool $isSigned = false) =>
            Field::create(name: "value", type: $type, isID: $isID, length: $length, isSigned: $isSigned);

        return [
            "nothing"        => [ $make(FieldType::None), "unknown" ],
            "a date"         => [ $make(FieldType::Date), "int(10) unsigned NOT NULL DEFAULT '0'" ],
            "a longer date"  => [ $make(FieldType::Date, 12), "bigint(12) unsigned NOT NULL DEFAULT '0'" ],
            "a json"         => [ $make(FieldType::JSON), "mediumtext NULL" ],
            "an array"       => [ $make(FieldType::Array), "mediumtext NULL" ],
            "a boolean"      => [ $make(FieldType::Boolean), "tinyint(1) unsigned NOT NULL DEFAULT '0'" ],
            "a number"       => [ $make(FieldType::Number), "int(10) unsigned NOT NULL DEFAULT '0'" ],
            "a tiny number"  => [ $make(FieldType::Number, 2), "tinyint(2) unsigned NOT NULL DEFAULT '0'" ],
            "a small number" => [ $make(FieldType::Number, 4), "smallint(4) unsigned NOT NULL DEFAULT '0'" ],
            "a medium one"   => [ $make(FieldType::Number, 6), "mediumint(6) unsigned NOT NULL DEFAULT '0'" ],
            "a big one"      => [ $make(FieldType::Number, 11), "bigint(11) unsigned NOT NULL DEFAULT '0'" ],
            "a signed one"   => [
                $make(FieldType::Number, 0, isSigned: true),
                "int(10) NOT NULL DEFAULT '0'",
            ],
            "an id"          => [
                $make(FieldType::Number, 0, isID: true),
                "int(10) unsigned NOT NULL AUTO_INCREMENT",
            ],
            "a float"        => [ $make(FieldType::Float), "bigint(20) unsigned NOT NULL DEFAULT '0'" ],
            "a signed float" => [
                $make(FieldType::Float, 0, isSigned: true),
                "bigint(20) NOT NULL DEFAULT '0'",
            ],
            "an enum"        => [ $make(FieldType::Enum), "varchar(255) NOT NULL DEFAULT ''" ],
            "a string"       => [ $make(FieldType::String), "varchar(255) NOT NULL DEFAULT ''" ],
            "a short string" => [ $make(FieldType::String, 50), "varchar(50) NOT NULL DEFAULT ''" ],
            "a file"         => [ $make(FieldType::File), "varchar(255) NOT NULL DEFAULT ''" ],
            "a text"         => [ $make(FieldType::Text), "text NULL" ],
            "a long text"    => [ $make(FieldType::LongText), "longtext NULL" ],
            "an encrypt"     => [ $make(FieldType::Encrypt), "varbinary(255) NOT NULL" ],
        ];
    }

    /**
     * A Field asked for its type without the length
     * @param FieldType $type
     * @param string    $expected
     * @return void
     */
    #[DataProvider("providerSqlTypeAlone")]
    public function testTheColumnIsWrittenWithoutItsLength(
        FieldType $type,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->field($type, length: 20)->getType(withLength: false));
    }

    /**
     * @return array<string,array{FieldType,string}>
     */
    public static function providerSqlTypeAlone(): array {
        return [
            "a number" => [ FieldType::Number, "bigint unsigned NOT NULL DEFAULT '0'" ],
            "a string" => [ FieldType::String, "varchar NOT NULL DEFAULT ''" ],
            "a text"   => [ FieldType::Text, "text NULL" ],
            "nothing"  => [ FieldType::None, "unknown" ],
        ];
    }

    public function testAnIdThatIsNotIncrementedIsWrittenAsAPlainNumber(): void {
        $field = $this->field(FieldType::Number, isID: true);
        $field->notAutoInc = true;

        $this->assertFalse($field->isAutoInc());
        $this->assertSame("int(10) unsigned NOT NULL DEFAULT '0'", $field->getType());
    }

    /**
     * A Field, and whether the database increments it on its own
     * @param FieldType $type
     * @param bool      $isID
     * @param bool      $expected
     * @return void
     */
    #[DataProvider("providerAutoInc")]
    public function testOnlyANumberIdIsIncrementedByTheDatabase(
        FieldType $type,
        bool $isID,
        bool $expected,
    ): void {
        $this->assertSame($expected, $this->field($type, isID: $isID)->isAutoInc());
    }

    /**
     * @return array<string,array{FieldType,bool,bool}>
     */
    public static function providerAutoInc(): array {
        return [
            "a number id" => [ FieldType::Number, true, true ],
            "a number"    => [ FieldType::Number, false, false ],
            "a string id" => [ FieldType::String, true, false ],
            "an enum id"  => [ FieldType::Enum, true, false ],
        ];
    }



    /**
     * The row read from the database, and the value the Field takes from it
     * @param FieldType           $type
     * @param array<string,mixed> $data
     * @param mixed               $expected
     * @return void
     */
    #[DataProvider("providerValues")]
    public function testTheValueIsReadAsTheTypeOfTheColumn(
        FieldType $type,
        array $data,
        mixed $expected,
    ): void {
        $this->assertSame($expected, $this->field($type)->toValues($data)["value"]);
    }

    /**
     * A float is stored as the number without its point, so the decimals of
     * the Field are what put it back
     * @return array<string,array{FieldType,array<string,mixed>,mixed}>
     */
    public static function providerValues(): array {
        return [
            "nothing"           => [ FieldType::None, [ "value" => "x" ], "" ],
            "a date"            => [ FieldType::Date, [ "value" => "1600000000" ], 1600000000 ],
            "an enum"           => [ FieldType::Enum, [ "value" => "Active" ], "Active" ],
            "a json"            => [ FieldType::JSON, [ "value" => '{"one":1}' ], [ "one" => 1 ] ],
            "an array"          => [ FieldType::Array, [ "value" => "[1,2]" ], [ 1, 2 ] ],
            "a boolean"         => [ FieldType::Boolean, [ "value" => "1" ], true ],
            "a boolean that is" => [ FieldType::Boolean, [ "value" => "0" ], false ],
            "a boolean missing" => [ FieldType::Boolean, [], false ],
            "a number"          => [ FieldType::Number, [ "value" => "12" ], 12 ],
            "a float"           => [ FieldType::Float, [ "value" => "150" ], 1.5 ],
            "a string"          => [ FieldType::String, [ "value" => "hi" ], "hi" ],
            "a text"            => [ FieldType::Text, [ "value" => "hi" ], "hi" ],
            "a long text"       => [ FieldType::LongText, [ "value" => "hi" ], "hi" ],
            "an encrypt"        => [ FieldType::Encrypt, [ "valueDecrypt" => "secret" ], "secret" ],
            "an encrypt raw"    => [ FieldType::Encrypt, [ "value" => "raw" ], "" ],
            "a column missing"  => [ FieldType::String, [], "" ],
        ];
    }

    /**
     * A file column, and the urls the Field adds beside it
     * @param string              $filePath
     * @param array<string,mixed> $data
     * @param array<string,mixed> $expected
     * @param bool                $isFlat   Optional.
     * @return void
     */
    #[DataProvider("providerFileValues")]
    public function testAFileColumnCarriesTheUrlsBesideThePath(
        string $filePath,
        array $data,
        array $expected,
        bool $isFlat = false,
    ): void {
        $this->setConfig("FILE_FLAT_MEDIA", $isFlat);
        $field = Field::create(
            name:       "image",
            prefixName: "image",
            type:       FieldType::File,
            filePath:   $filePath,
        );

        $result = $field->toValues($data);
        $this->setConfig("FILE_FLAT_MEDIA", false);
        $this->assertSame($expected, $result);
    }

    /**
     * A Field that names a path has the one url the path builds, and one
     * that does not falls back to the source and the thumbnail
     * @return array<string,array{0:string,1:array<string,mixed>,2:array<string,mixed>,3?:bool}>
     */
    public static function providerFileValues(): array {
        return [
            "a path"              => [
                "crates",
                [ "image" => "one.png" ],
                [ "image" => "one.png", "imageUrl" => "files/crates/one.png" ],
            ],
            "a path with no file" => [
                "crates",
                [],
                [ "image" => "", "imageUrl" => "" ],
            ],
            "no path"             => [
                "",
                [ "image" => "one.png" ],
                [
                    "image"      => "one.png",
                    "imageUrl"   => "files/source/0/one.png",
                    "imageThumb" => "files/thumbs/0/one.png",
                ],
            ],
            "no path, flat media" => [
                "",
                [ "image" => "one.png" ],
                [
                    "image"      => "one.png",
                    "imageUrl"   => "files/source/one.png",
                    "imageThumb" => "files/thumbs/one.png",
                ],
                true,
            ],
            "no path and no file" => [
                "",
                [],
                [ "image" => "", "imageUrl" => "", "imageThumb" => "" ],
            ],
        ];
    }



    /**
     * A name given to a Field, and the column the database is given
     * @param string $name
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerDbName")]
    public function testTheIdColumnsAreNamedInConstantCase(
        string $name,
        string $expected,
    ): void {
        $field = Field::create(name: $name);

        $this->assertSame($expected, $field->setDbName());
        $this->assertSame($expected, $field->dbName);
        $this->assertSame($name !== $expected, $field->isSchemaID());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerDbName(): array {
        return [
            "an id"        => [ "crateID", "CRATE_ID" ],
            "a plain name" => [ "name", "name" ],
            "one of two"   => [ "firstName", "firstName" ],
            "one in caps"  => [ "CRATE_ID", "CRATE_ID" ],
        ];
    }

    public function testTheBuildDataLeavesOutWhatIsAlreadyTheDefault(): void {
        $field = Field::create(name: "name", type: FieldType::String);

        $this->assertSame([
            "name" => "name",
            "type" => FieldType::String,
        ], $field->toBuildData());
    }

    public function testTheBuildDataCarriesWhatIsNotTheDefault(): void {
        $field = Field::create(
            name:     "crateID",
            dbName:   "CRATE_ID",
            type:     FieldType::Number,
            isID:     true,
            decimals: 3,
            filePath: "crates",
        );

        $this->assertSame([
            "name"     => "crateID",
            "type"     => FieldType::Number,
            "dbName"   => "CRATE_ID",
            "isID"     => true,
            "decimals" => 3,
            "filePath" => "crates",
        ], $field->toBuildData());
    }

    /**
     * A Field, and the column the published schema names for it
     * @param Field               $field
     * @param array<string,mixed> $expected
     * @return void
     */
    #[DataProvider("providerSchemaJSON")]
    public function testTheSchemaJsonCarriesOnlyWhatIsTrueOfTheColumn(
        Field $field,
        array $expected,
    ): void {
        $this->assertSame($expected, $field->toSchemaJSON());
    }

    /**
     * @return array<string,array{Field,array<string,mixed>}>
     */
    public static function providerSchemaJSON(): array {
        return [
            "a plain column"    => [
                Field::create(name: "name", type: FieldType::String),
                [ "name" => "name", "type" => "string" ],
            ],
            "one with a length" => [
                Field::create(name: "name", type: FieldType::String, length: 50),
                [ "name" => "name", "type" => "string", "length" => 50 ],
            ],
            "an id"             => [
                Field::create(name: "crateID", dbName: "CRATE_ID", type: FieldType::Number, isID: true),
                [ "name" => "CRATE_ID", "type" => "number", "isPrimary" => true ],
            ],
            "a primary"         => [
                Field::create(name: "name", type: FieldType::String, isPrimary: true),
                [ "name" => "name", "type" => "string", "isPrimary" => true ],
            ],
            "a key"             => [
                Field::create(name: "name", type: FieldType::String, isKey: true),
                [ "name" => "name", "type" => "string", "isKey" => true ],
            ],
        ];
    }

    /**
     * A Field pointing at another Model, and the foreign key it names
     * @param string              $otherField
     * @param array<string,mixed> $expected
     * @return void
     */
    #[DataProvider("providerSchemaForeign")]
    public function testTheForeignKeyNamesTheTableItPointsAt(
        string $otherField,
        array $expected,
    ): void {
        $field = new Field(belongsTo: "CrateItemModel", otherField: $otherField);
        $field->setData("crateID", "int", isEnum: false);
        $field->setDbName();

        $this->assertSame($expected, $field->toSchemaForeign());
    }

    /**
     * With no other field named, the key on both sides is the same column
     * @return array<string,array{string,array<string,mixed>}>
     */
    public static function providerSchemaForeign(): array {
        return [
            "nothing given" => [
                "",
                [ "fromField" => "CRATE_ID", "toTable" => "crate_item", "toField" => "CRATE_ID" ],
            ],
            "another field" => [
                "boxID",
                [ "fromField" => "CRATE_ID", "toTable" => "crate_item", "toField" => "BOX_ID" ],
            ],
        ];
    }
}
