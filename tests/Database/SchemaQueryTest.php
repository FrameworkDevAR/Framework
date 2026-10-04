<?php
namespace Tests\Database;

use Framework\Auth\Schema\CredentialQuery;
use Framework\Auth\Schema\CredentialDeviceQuery;
use Framework\Database\Query\Query;
use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;
use Framework\Auth\Schema\CredentialSchema;
use Framework\Database\Model\Count;
use Framework\Database\Model\Expression;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Relation;
use Framework\Database\SchemaModel;
use Framework\Database\Type\SchemaRequest;
use Framework\IO\Request;
use Framework\Utils\Strings;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;

class SchemaQueryTest extends TestCase {

    /**
     * Returns the SQL of the query behind the schema query
     * @param CredentialQuery $query
     * @return string
     */
    private function sql(CredentialQuery $query): string {
        $result = preg_replace('/\s+/', " ", $query->getQuery()->toSQL());
        return trim((string)$result);
    }



    public function testItKnowsTheTableItReads(): void {
        $query = new CredentialQuery();

        $this->assertEquals("credential", $query->getTableName());
        $this->assertEquals("CREDENTIAL_ID", $query->getIDDbName());
    }

    public function testItStartsEmptyAndFillsAsConditionsAreAdded(): void {
        $query = new CredentialQuery();
        $this->assertTrue($query->isEmpty());
        $this->assertFalse($query->isNotEmpty());

        $query->email->equal("ada@example.com");
        $this->assertFalse($query->isEmpty());
        $this->assertTrue($query->isNotEmpty());
    }

    public function testItWrapsARealQuery(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");

        $this->assertInstanceOf(Query::class, $query->getQuery());
        $this->assertStringContainsString("credential.email = ?", $this->sql($query));
    }

    public function testTheTypedColumnsBuildTheConditions(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");
        $query->credentialID->greaterThan(5);

        $this->assertEquals(
            [ "ada@example.com", 5 ],
            $query->getQuery()->getBindings(),
        );
    }

    public function testARawExpressionTakesItsOwnParam(): void {
        $query = new CredentialQuery();
        $query->where(Exp::create("credential.progressValue > ?"));
        $query->addParam(10);

        $this->assertStringContainsString("credential.progressValue > ?", $this->sql($query));
        $this->assertEquals([ 10 ], $query->getQuery()->getBindings());
    }

    public function testAValueInsideAJsonColumnIsAskedForByItsPath(): void {
        $query = new CredentialQuery();
        $query->where(Exp::json("credential.data", "userID"), Op::Equal, 5);

        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(credential.data, ?)) = ?",
            $this->sql($query),
        );
        $this->assertEquals([ "$.userID", 5 ], $query->getQuery()->getBindings());
    }

    public function testAnExpressionIsComparedWithAValue(): void {
        $query = new CredentialQuery();
        $query->where(Exp::create("LOWER(credential.email)"), Op::Equal, "ada@example.com");

        $this->assertStringContainsString("LOWER(credential.email) = ?", $this->sql($query));
        $this->assertEquals([ "ada@example.com" ], $query->getQuery()->getBindings());
    }

    public function testConditionsCanBeGrouped(): void {
        $query = new CredentialQuery();
        $query->startOr();
        $query->email->equal("a@b.c");
        $query->email->equal("d@e.f");
        $query->endOr();

        $this->assertStringContainsString("( credential.email = ? OR credential.email = ? )", $this->sql($query));
    }

    public function testGroupsCanBeNested(): void {
        $query = new CredentialQuery();
        $query->startAnd();
        $query->email->equal("a@b.c");
        $query->endAnd();

        $this->assertStringContainsString("credential.email = ?", $this->sql($query));
    }

    public function testParenthesesCanBeOpenedDirectly(): void {
        $query = new CredentialQuery();
        $query->startParen();
        $query->email->equal("a@b.c");
        $query->endParen();

        $this->assertStringContainsString("( credential.email = ? )", $this->sql($query));
    }

    public function testTheJoinerBetweenConditionsCanBeSet(): void {
        $query = new CredentialQuery();
        $query->email->equal("a@b.c");
        $query->or();
        $query->credentialID->equal(1);

        $this->assertStringContainsString("OR", $this->sql($query));

        $anded = new CredentialQuery();
        $anded->email->equal("a@b.c");
        $anded->and();
        $anded->credentialID->equal(1);

        $this->assertStringContainsString("AND", $this->sql($anded));
    }

    public function testItCanBeLimitedAndPaged(): void {
        $limited = new CredentialQuery();
        $limited->limit(5);

        $paged = new CredentialQuery();
        $paged->paginate(2, 20);

        $this->assertStringContainsString("LIMIT 5", $this->sql($limited));
        $this->assertStringContainsString("LIMIT 40, 20", $this->sql($paged));
    }

    public function testItCanBeOrderedByAnExpression(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");
        $query->orderByExp(Exp::create("CASE WHEN firstName LIKE ? THEN 0 ELSE 1 END", "Ada%"));

        $this->assertStringContainsString(
            "ORDER BY CASE WHEN firstName LIKE ? THEN 0 ELSE 1 END ASC",
            $this->sql($query),
        );
        $this->assertEquals([ "ada@example.com", "Ada%" ], $query->getQuery()->getBindings());
    }

    public function testItCanAskWhetherRowsExistElsewhere(): void {
        // A sub query is consumed by the call, so each one needs its own
        $forExists = new CredentialDeviceQuery();
        $forExists->playerID->equal("abc");
        $exists = new CredentialQuery();
        $exists->whereExists($forExists);

        $forNotExists = new CredentialDeviceQuery();
        $forNotExists->playerID->equal("abc");
        $notExists = new CredentialQuery();
        $notExists->whereNotExists($forNotExists);

        $this->assertStringContainsString("EXISTS (SELECT 1 FROM `credential_device`", $this->sql($exists));
        $this->assertStringContainsString("NOT EXISTS (SELECT 1 FROM `credential_device`", $this->sql($notExists));
    }

    public function testASubQueryCannotBeUsedTwice(): void {
        $devices = new CredentialDeviceQuery();
        $devices->playerID->equal("abc");

        $first = new CredentialQuery();
        $first->whereExists($devices);

        $second = new CredentialQuery();
        $second->whereExists($devices);

        // The call mutates the sub query, so using it again repeats the marker
        // column it added the first time
        $this->assertStringContainsString("EXISTS (SELECT 1 FROM", $this->sql($first));
        $this->assertStringContainsString("EXISTS (SELECT 1, 1 FROM", $this->sql($second));
    }

    public function testAJoinNeedsTheOtherSideToHaveAnId(): void {
        // credential_device has no id of its own, so it can be joined onto but
        // cannot be the thing joined in
        $devices = new CredentialDeviceQuery();
        $devices->join(new CredentialQuery());

        $credential = new CredentialQuery();
        $credential->join(new CredentialDeviceQuery());

        $this->assertStringContainsString(
            "LEFT JOIN credential ON (credential.CREDENTIAL_ID = credential_device.CREDENTIAL_ID)",
            trim((string)preg_replace('/\s+/', " ", $devices->getQuery()->toSQL())),
        );
        $this->assertEquals("SELECT * FROM `credential`", $this->sql($credential));
    }

    public function testTheDebugSqlInlinesTheValues(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");

        $this->assertStringContainsString("'ada@example.com'", $query->toDebugSQL());
    }



    /**
     * A column asked by a request, and whether the Model can sort the list by it
     * @param string $column
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerCanSortBy")]
    public function testTheListIsSortedOnlyByTheColumnsOfTheModel(string $column, bool $expected): void {
        $model = new SchemaModel(
            name:          "Seller",
            hasTimestamps: true,
            canCreate:     true,
            mainFields:    [
                Field::create(name: "sellerID", dbName: "SELLER_ID", type: FieldType::Number, isID: true),
                Field::create(name: "sellerCode", type: FieldType::String),
            ],
            expressions:   [
                Expression::create("fullName", FieldType::String, "CONCAT(firstName, lastName)"),
            ],
            counts:        [
                Count::create("orderCount", "Seller", "Order", "sellerID", "", false),
            ],
            relations:     [
                Relation::create("Store", "", "storeID", "Seller", "storeID", "", [
                    Field::create(name: "title", dbName: "title", prefixName: "storeTitle", type: FieldType::String),
                ]),
            ],
        );

        $this->assertSame($expected, $model->canSortBy($column));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerCanSortBy(): array {
        return [
            "the name of a field"   => [ "sellerCode", true ],
            "the name of the id"    => [ "sellerID", true ],
            "the column of the id"  => [ "SELLER_ID", true ],
            "a column a flag adds"  => [ "createdTime", true ],
            "a field of a relation" => [ "storeTitle", true ],
            "an expression"         => [ "fullName", true ],
            "a count"               => [ "orderCount", true ],
            "an unknown column"     => [ "assistant", false ],
            // Only a name is taken, so one written with its table is not one of them
            "a column with a table" => [ "seller.sellerCode", false ],
            "nothing"               => [ "", false ],
            "an injected statement" => [ "sellerCode, (SELECT SLEEP(5))", false ],
        ];
    }

    /**
     * The order a request asks for, and what of it reaches the SQL of the list
     * @param string $orderBy
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerRequestSort")]
    public function testTheSortOfARequestIsChecked(string $orderBy, string $expected): void {
        $sort   = new SchemaRequest(new Request([ "orderBy" => $orderBy, "orderAsc" => 1 ]));
        $method = new ReflectionMethod(CredentialSchema::class, "generateQuerySort");
        $query  = $method->invoke(null, null, $sort);

        // What is not a column of the Model is left out, and the list comes unsorted
        $this->assertSame($expected, trim(Strings::substringAfter($query->toSQL(), "= ?")));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerRequestSort(): array {
        return [
            "a column of the model" => [ "email", "ORDER BY email ASC" ],
            "an unknown column"     => [ "nothing", "" ],
            "no column"             => [ "", "" ],
            "an injected statement" => [ "email, (SELECT SLEEP(5))", "" ],
            "a second statement"    => [ "email; DROP TABLE credential", "" ],
        ];
    }
}
