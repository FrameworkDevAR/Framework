<?php
namespace Tests\Database;

use Framework\Database\SchemaModel;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Index;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Index over several columns of a Model
 *
 * What it is named and which columns of the table it comes to. It never reaches
 * the generated Schema, as an Index is for the table and no Query asks about
 * one. What the migration does with it is in the live tests, as it takes a
 * table to be seen.
 */
class IndexTest extends TestCase {

    /**
     * The fields of a model, one whose column is named otherwise in the table
     * @return list<Field>
     */
    private static function fields(): array {
        return [
            Field::create(name: "productID", type: FieldType::Number, dbName: "PRODUCT_ID", isID: true),
            Field::create(name: "storeID", type: FieldType::Number, dbName: "STORE_ID"),
            Field::create(name: "name", type: FieldType::String),
        ];
    }



    /**
     * How an Index is declared, and the name and the columns it takes
     * @param callable     $build
     * @param string       $name
     * @param list<string> $columns
     * @param bool         $isUnique
     * @return void
     */
    #[DataProvider("providerDeclared")]
    public function testTheIndexIsNamedAfterItsColumns(
        callable $build,
        string $name,
        array $columns,
        bool $isUnique,
    ): void {
        $index = $build();

        $this->assertSame($name, $index->name);
        $this->assertSame($columns, $index->columns);
        $this->assertSame($isUnique, $index->isUnique);
    }

    /**
     * @return array<string,array{callable,string,list<string>,bool}>
     */
    public static function providerDeclared(): array {
        return [
            "several columns" => [
                fn() => new Index([ "productID", "storeID" ]),
                "productID_storeID", [ "productID", "storeID" ], false,
            ],
            "one column"      => [
                fn() => new Index("name"),
                "name", [ "name" ], false,
            ],
            "unique"          => [
                fn() => new Index([ "productID", "storeID" ], isUnique: true),
                "productID_storeID", [ "productID", "storeID" ], true,
            ],
            "with a name"     => [
                fn() => new Index([ "productID", "storeID" ], name: "byStore"),
                "byStore", [ "productID", "storeID" ], false,
            ],
            "created"         => [
                fn() => Index::create("byStore", [ "storeID", "name" ], isUnique: true),
                "byStore", [ "storeID", "name" ], true,
            ],
        ];
    }

    public function testTheColumnsTakeTheNamesOfTheTable(): void {
        $index   = new Index([ "storeID", "name" ]);
        $missing = $index->setDbColumns(self::fields());

        // The property is storeID, and the column STORE_ID
        $this->assertSame([], $missing);
        $this->assertSame([ "STORE_ID", "name" ], $index->dbColumns);
    }

    public function testAColumnThatIsNoFieldIsReported(): void {
        $index   = new Index([ "storeID", "color", "size" ]);
        $missing = $index->setDbColumns(self::fields());

        $this->assertSame([ "color", "size" ], $missing);
        $this->assertSame([ "STORE_ID" ], $index->dbColumns);
    }

    /**
     * An Index and what the table holds under its name, and whether they are the same
     * @param list<string> $columns
     * @param bool         $isUnique
     * @param bool         $expected
     * @return void
     */
    #[DataProvider("providerIsSame")]
    public function testTheIndexIsComparedToTheTableOne(
        array $columns,
        bool $isUnique,
        bool $expected,
    ): void {
        $index = new Index([ "storeID", "name" ], isUnique: true);
        $index->setDbColumns(self::fields());

        $this->assertSame($expected, $index->isSame($columns, $isUnique));
    }

    /**
     * @return array<string,array{list<string>,bool,bool}>
     */
    public static function providerIsSame(): array {
        return [
            "the same"          => [ [ "STORE_ID", "name" ], true, true ],
            "another order"     => [ [ "name", "STORE_ID" ], true, false ],
            "a column less"     => [ [ "STORE_ID" ], true, false ],
            "not unique"        => [ [ "STORE_ID", "name" ], false, false ],
            "the property name" => [ [ "storeID", "name" ], true, false ],
        ];
    }



    public function testTheModelKeepsTheIndexesItCanResolve(): void {
        $model = new SchemaModel(
            name:       "Product",
            mainFields: self::fields(),
            indexes:    [
                Index::create("byStore", [ "storeID", "name" ]),
                Index::create("broken", [ "storeID", "color" ]),
            ],
        );

        // The one naming a column the table has not is left out, and said
        $this->assertSame([ "broken" => [ "color" ] ], $model->setIndexColumns());
        $this->assertCount(1, $model->indexes);
        $this->assertSame("byStore", $model->indexes[0]->name);
        $this->assertSame([ "STORE_ID", "name" ], $model->indexes[0]->dbColumns);
    }
}
