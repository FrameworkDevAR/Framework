<?php
namespace Tests\Database;

use Framework\Database\SchemaModel;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Expression;
use Framework\Database\Model\Count;
use Framework\Database\Model\Relation;
use Framework\Database\Query\Exp;
use Framework\Database\Query\SelectionBuilder;
use Framework\Database\Query\Query;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;
use ReflectionProperty;

class SelectionTest extends TestCase {

    /**
     * A model with one of everything the selection knows how to add, so each
     * part can be asked for on its own
     * @return SchemaModel
     */
    private function model(): SchemaModel {
        return new SchemaModel(
            name:        "Product",
            mainFields:  [
                Field::create(name: "productID", type: FieldType::Number, dbName: "PRODUCT_ID", isID: true),
                Field::create(name: "name", type: FieldType::String),
                Field::create(name: "code", dbName: "product_code", type: FieldType::String),
                Field::create(name: "secret", type: FieldType::Encrypt),
            ],
            expressions: [
                Expression::create("fullName", FieldType::String, "CONCAT(name, code)"),
            ],
            counts:      [
                Count::create("imageCount", "Product", "ProductImage", "productID", "", false),
            ],
            relations:   [
                Relation::create("Category", "", "categoryID", "Product", "categoryID", "", [
                    Field::create(name: "title", dbName: "title", prefixName: "categoryTitle", type: FieldType::String),
                ]),
            ],
        );
    }

    /**
     * A model with a chain of relations, so a table can be needed on its own
     * and to reach another one, beside one that nothing needs
     * @return SchemaModel
     */
    private function joinModel(): SchemaModel {
        return new SchemaModel(
            name:       "Product",
            mainFields: [
                Field::create(name: "productID", type: FieldType::Number, dbName: "PRODUCT_ID", isID: true),
                Field::create(name: "name", type: FieldType::String),
            ],
            relations:  [
                Relation::create("Category", "", "categoryID", "Product", "categoryID", "", [
                    Field::create(name: "title", dbName: "title", prefixName: "categoryTitle", type: FieldType::String),
                ]),
                Relation::create("CategoryType", "", "categoryTypeID", "Category", "categoryTypeID", "", [
                    Field::create(name: "typeName", dbName: "typeName", prefixName: "categoryTypeName", type: FieldType::String),
                ]),
                Relation::create("Brand", "", "brandID", "Product", "brandID", "", [
                    Field::create(name: "brandName", dbName: "brandName", prefixName: "brandName", type: FieldType::String),
                ]),
            ],
        );
    }

    /**
     * Returns the tables that were joined, in the order they were added
     * @param SelectionBuilder $selection
     * @return list<string>
     */
    private function joinedTables(SelectionBuilder $selection): array {
        preg_match_all('/LEFT JOIN `(\w+)`/', $this->sql($selection), $matches);
        return $matches[1];
    }

    /**
     * Returns the built statement, with the padding collapsed
     * @param SelectionBuilder $selection
     * @return string
     */
    private function sql(SelectionBuilder $selection): string {
        $result = preg_replace('/\s+/', " ", $selection->toDebugSQL());
        return trim((string)$result);
    }

    /**
     * Puts rows in as though the request had returned them
     * @param SelectionBuilder          $selection
     * @param list<array<string,mixed>> $rows
     * @return void
     */
    private function injectRequest(SelectionBuilder $selection, array $rows): void {
        $property = new ReflectionProperty(SelectionBuilder::class, "request");
        $property->setValue($selection, new Dictionary($rows));
    }



    public function testTheIdIsSelectedUnderItsOwnAlias(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();

        $this->assertStringContainsString("product.PRODUCT_ID AS id", $this->sql($selection));
    }

    public function testAColumnWithAnotherDbNameIsAliasedBack(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();

        // The column is product_code in the table and code on the entity
        $this->assertStringContainsString("product.product_code AS code", $this->sql($selection));
    }

    public function testAnEncryptedFieldIsOnlyDecryptedWhenAsked(): void {
        $plain = SelectionBuilder::create($this->model(), Query::select("products"));
        $plain->addFields();

        $decrypted = SelectionBuilder::create($this->model(), Query::select("products"));
        $decrypted->addFields(decrypted: true);

        $this->assertStringNotContainsString("AES_DECRYPT", $this->sql($plain));
        $this->assertStringContainsString("AES_DECRYPT(product.secret", $this->sql($decrypted));
        $this->assertStringContainsString("secretDecrypt", $this->sql($decrypted));
    }

    public function testAnExpressionIsSelectedUnderItsName(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addExpressions();

        $this->assertStringContainsString("(CONCAT(name, code)) AS fullName", $this->sql($selection));
    }

    public function testARelationJoinsAndBringsItsFields(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addJoins();

        $sql = $this->sql($selection);
        $this->assertStringContainsString("LEFT JOIN `category` ON (category.categoryID = product.categoryID)", $sql);
        $this->assertStringContainsString("category.title AS categoryTitle", $sql);
    }

    public function testARelationCanJoinWithoutItsFields(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addJoins(withSelects: false);

        $sql = $this->sql($selection);
        $this->assertStringContainsString("LEFT JOIN `category`", $sql);
        $this->assertStringNotContainsString("categoryTitle", $sql);
    }

    public function testAnExtraJoinIsAddedAsGiven(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addJoins([ "INNER JOIN store ON (store.id = product.storeID)" ]);

        $this->assertStringContainsString("INNER JOIN store ON (store.id = product.storeID)", $this->sql($selection));
    }

    /**
     * The conditions of a Query, and the tables that have to be joined for them
     * @param callable     $build
     * @param list<string> $expected
     * @return void
     */
    #[DataProvider("providerUsedJoins")]
    public function testUnusedJoinsAreLeftOut(callable $build, array $expected): void {
        $selection = SelectionBuilder::create($this->joinModel(), $build());
        $selection->addJoins(withSelects: false, onlyUsed: true);

        $this->assertSame($expected, $this->joinedTables($selection));
    }

    /**
     * A table is joined when a condition names it, when anything else in the
     * statement names it, and when a table that is joined is reached through it
     * @return array<string,array{callable,list<string>}>
     */
    public static function providerUsedJoins(): array {
        return [
            "nothing asked"           => [
                fn() => Query::select("products"),
                [],
            ],
            "a column of its own"     => [
                fn() => Query::select("products")->where("name", "=", "Widget"),
                [],
            ],
            "a column with its table" => [
                fn() => Query::select("products")->where("category.title", "=", "Tools"),
                [ "category" ],
            ],
            "a column on its own"     => [
                fn() => Query::select("products")->where("title", "=", "Tools"),
                [ "category" ],
            ],
            "an order by"             => [
                fn() => Query::select("products")->orderBy("category.title", isASC: true),
                [ "category" ],
            ],
            "a condition as an Exp"   => [
                fn() => Query::select("products")->where(Exp::column("brand.brandName")->isNotNull()),
                [ "brand" ],
            ],
            "one reached through another" => [
                fn() => Query::select("products")->where("category_type.typeName", "=", "Tool"),
                [ "category", "category_type" ],
            ],
        ];
    }

    public function testATableNamedInASelectIsJoined(): void {
        $selection = SelectionBuilder::create($this->joinModel(), Query::select("products"));
        // The selects are added before the joins, so a table named in one of them
        // is in the statement by the time the joins are worked out
        $selection->addSelects("brand.brandName");
        $selection->addJoins(withSelects: false, onlyUsed: true);

        $this->assertSame([ "brand" ], $this->joinedTables($selection));
    }

    public function testAJoinWithAParamIsAlwaysAdded(): void {
        $model = new SchemaModel(
            name:       "Product",
            mainFields: [
                Field::create(name: "name", type: FieldType::String),
            ],
            relations:  [
                Relation::create("StorePrice", "", "productID", "Product", "productID", " AND storeID = ?", [
                    Field::create(name: "price", dbName: "price", prefixName: "storePrice", type: FieldType::Number),
                ]),
            ],
        );

        // The value of the On is given before the condition, as the Join is
        // written before the Where, and both are bound by their position
        $query = Query::select("products");
        $query->addParam(5);
        $query->where("name", "=", "Widget");

        $selection = SelectionBuilder::create($model, $query);
        $selection->addJoins(withSelects: false, onlyUsed: true);

        // Nothing names the table, but leaving it out would hand its 5 to the name
        $sql = $this->sql($selection);
        $this->assertSame([ "store_price" ], $this->joinedTables($selection));
        $this->assertStringContainsString("store_price.STORE_ID = 5)", $sql);
        $this->assertStringContainsString("product.name = 'Widget'", $sql);
    }

    public function testTheExtraJoinsAreAlwaysAdded(): void {
        $selection = SelectionBuilder::create($this->joinModel(), Query::select("products"));
        $selection->addJoins([ "INNER JOIN store ON (store.id = product.storeID)" ], onlyUsed: true);

        // None of the relations is asked about, so only the one given is there
        $sql = $this->sql($selection);
        $this->assertStringContainsString("INNER JOIN store ON (store.id = product.storeID)", $sql);
        $this->assertStringNotContainsString("LEFT JOIN", $sql);
    }

    public function testACountBecomesASubQuery(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addCounts();

        $sql = $this->sql($selection);
        $this->assertStringContainsString("SELECT COUNT(*)", $sql);
        $this->assertStringContainsString("AS imageCount", $sql);
    }

    public function testSelectsCanBeGivenAsRawSql(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addSelects("COUNT(*) AS n");

        $this->assertEquals("SELECT COUNT(*) AS n FROM `products`", $this->sql($selection));
    }

    public function testSelectsCanBeQualifiedWithTheMainTable(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addSelects([ "name" ], addMainKey: true);

        $this->assertEquals("SELECT product.name FROM `products`", $this->sql($selection));
    }

    public function testNoSelectsLeavesTheStatementAlone(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addSelects([], addMainKey: true);

        $this->assertEquals("SELECT * FROM `products`", $this->sql($selection));
    }


    public function testConditionsAreMovedOntoTheTableThatOwnsThem(): void {
        $query = Query::select("products");
        $query->where("name", "=", "Widget");
        $query->where("title", "=", "Tools");
        $query->where("fullName", "=", "WidgetX");

        $selection = SelectionBuilder::create($this->model(), $query);
        $selection->addFields();
        $selection->addJoins();
        (new ReflectionMethod(SelectionBuilder::class, "setTableKeys"))->invoke($selection);

        $where = strstr($this->sql($selection), "WHERE");
        // Its own column takes the main table, the relation's takes the joined
        // one, and the expression is replaced by the expression itself
        $this->assertStringContainsString("product.name = 'Widget'", $where);
        $this->assertStringContainsString("category.title = 'Tools'", $where);
        $this->assertStringContainsString("(CONCAT(name, code)) = 'WidgetX'", $where);
    }


    public function testResolveMapsEveryPartOfTheModel(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();
        $this->injectRequest($selection, [[
            "id"            => "7",
            "productID"     => "7",
            "name"          => "Widget",
            "code"          => "W-1",
            "fullName"      => "WidgetW-1",
            "categoryTitle" => "Tools",
            "imageCount"    => "3",
        ]]);

        $resolved = $selection->resolve();

        $this->assertCount(1, $resolved);
        $this->assertEquals(7, $resolved[0]["productID"]);
        $this->assertEquals("Widget", $resolved[0]["name"]);
        $this->assertEquals("WidgetW-1", $resolved[0]["fullName"]);
        $this->assertEquals("Tools", $resolved[0]["categoryTitle"]);
        $this->assertEquals("3", $resolved[0]["imageCount"]);
    }

    public function testResolveCarriesTheExtrasThatWereAskedFor(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "productID" => "7", "name" => "Widget", "note" => "a note" ],
        ]);

        $plain = $selection->resolve();
        $extra = $selection->resolve("note");

        // Anything not on the model only comes through when it is asked for
        $this->assertArrayNotHasKey("note", $plain[0]);
        $this->assertEquals("a note", $extra[0]["note"]);
    }

    public function testResolveTakesSeveralExtras(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "productID" => "7", "note" => "a", "other" => "b", "missing" => null ],
        ]);

        $resolved = $selection->resolve([ "note", "other", "absent" ]);

        $this->assertEquals("a", $resolved[0]["note"]);
        $this->assertEquals("b", $resolved[0]["other"]);
        $this->assertArrayNotHasKey("absent", $resolved[0]);
    }

    public function testResolveTakesTheIdFromWhicheverColumnCarriesIt(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "id" => "7", "name" => "A" ],
            [ "PRODUCT_ID" => "8", "name" => "B" ],
            [ "productID" => "9", "name" => "C" ],
        ]);

        $resolved = $selection->resolve();

        $this->assertEquals("7", $resolved[0]["id"]);
        $this->assertEquals("8", $resolved[1]["id"]);
        $this->assertEquals("9", $resolved[2]["id"]);
    }

    public function testResolveNumbersAndStringsComeBackTyped(): void {
        $selection = SelectionBuilder::create($this->model(), Query::select("products"));
        $selection->addFields();
        $this->injectRequest($selection, [
            [ "productID" => "7", "name" => "Widget" ],
        ]);

        $resolved = $selection->resolve();

        $this->assertIsInt($resolved[0]["productID"]);
        $this->assertIsString($resolved[0]["name"]);
    }
}
