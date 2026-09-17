<?php
namespace Tests\Database;

use Framework\Database\Query\Query;
use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;
use Framework\Database\Query\Assign;
use Framework\Auth\Schema\CredentialQuery;
use Framework\Auth\Schema\CredentialColumn;
use Framework\Date\Date;
use Framework\File\File;
use Framework\Utils\Color;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class QueryTest extends TestCase {

    /**
     * Returns the SQL without the padding the builder leaves between clauses
     * @param Query $query
     * @return string
     */
    private function sql(Query $query): string {
        $result = preg_replace('/\s+/', " ", $query->toSQL());
        return trim((string)$result);
    }



    #[DataProvider("providerSelect")]
    public function testSelect(callable $build, string $expected): void {
        $this->assertEquals($expected, $this->sql($build()));
    }

    public static function providerSelect(): array {
        return [
            "plain"   => [
                fn() => Query::select("products"),
                "SELECT * FROM `products`",
            ],
            "alias"   => [
                function () {
                    $query = Query::select("products", "p");
                    $query->where("p.name", "=", "Widget");
                    return $query;
                },
                "SELECT * FROM `products` AS `p` WHERE p.name = ?",
            ],
            "columns" => [
                function () {
                    $query = Query::select("products");
                    $query->columns("name", "price");
                    return $query;
                },
                "SELECT name, price FROM `products`",
            ],
            "join"    => [
                function () {
                    $query = Query::select("products", "p");
                    $query->join("categories", "c", "c.categoryID = p.categoryID");
                    return $query;
                },
                "SELECT * FROM `products` AS `p` LEFT JOIN categories AS c ON (c.categoryID = p.categoryID)",
            ],
        ];
    }


    #[DataProvider("providerWhere")]
    public function testWhere(callable $build, string $expected, array $bindings): void {
        $query = $build();
        $this->assertEquals($expected, $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerWhere(): array {
        return [
            "equal"      => [
                function () { $q = Query::select("t"); $q->where("id", "=", 5); return $q; },
                "SELECT * FROM `t` WHERE id = ?", [ 5 ],
            ],
            "several"    => [
                function () { $q = Query::select("t"); $q->where("a", "=", 1); $q->where("b", ">", 2); return $q; },
                "SELECT * FROM `t` WHERE a = ? AND b > ?", [ 1, 2 ],
            ],
            "in"         => [
                function () { $q = Query::select("t"); $q->where("id", "IN", [ 1, 2, 3 ]); return $q; },
                "SELECT * FROM `t` WHERE id IN (?,?,?)", [ 1, 2, 3 ],
            ],
            "like"       => [
                function () { $q = Query::select("t"); $q->where("name", Op::Like, "wid"); return $q; },
                "SELECT * FROM `t` WHERE name LIKE ?", [ "%wid%" ],
            ],
            "expression" => [
                function () { $q = Query::select("t"); $q->where(Exp::create("price * ?", 2), ">", 100); return $q; },
                "SELECT * FROM `t` WHERE price * ? > ?", [ 2, 100 ],
            ],
            "json"       => [
                function () { $q = Query::select("t"); $q->where(Exp::json("t.data", "userID"), "=", 5); return $q; },
                "SELECT * FROM `t` WHERE JSON_UNQUOTE(JSON_EXTRACT(t.data, ?)) = ?", [ "$.userID", 5 ],
            ],
            "exp in"     => [
                function () { $q = Query::select("t"); $q->where(Exp::create("LOWER(a)"), "IN", [ "b", "c" ]); return $q; },
                "SELECT * FROM `t` WHERE LOWER(a) IN (?,?)", [ "b", "c" ],
            ],
            "exp params" => [
                function () { $q = Query::select("t"); $q->where(Exp::create("IF(a > ?, b, c)", 1), "=", 2); return $q; },
                "SELECT * FROM `t` WHERE IF(a > ?, b, c) = ?", [ 1, 2 ],
            ],
            "two columns" => [
                function () { $q = Query::select("t"); $q->where("a", ">", Exp::column("b")); return $q; },
                "SELECT * FROM `t` WHERE a > b", [],
            ],
            "both sides"  => [
                function () { $q = Query::select("t"); $q->where(Exp::json("t.d", "x"), "=", Exp::column("t.y")); return $q; },
                "SELECT * FROM `t` WHERE JSON_UNQUOTE(JSON_EXTRACT(t.d, ?)) = t.y", [ "$.x" ],
            ],
        ];
    }

    public function testAnExpressionAloneIsTheWholeCondition(): void {
        $query = Query::select("t");
        $query->where(Exp::column("t.a")->isNull());
        $query->where(Exp::ifNull("t.b", 0), ">", 5);

        $this->assertEquals(
            "SELECT * FROM `t` WHERE t.a IS NULL AND IFNULL(t.b, ?) > ?",
            $this->sql($query),
        );
        $this->assertEquals([ 0, 5 ], $query->getBindings());
    }

    public function testAnExpressionCanBeSelectedWithItsParamsBeforeTheOthers(): void {
        $query = Query::select("t");
        $query->column(Exp::create("IF(a > ?, ?, ?)", 1, "hi", "bye"), "label");
        $query->where("id", "=", 5);

        $this->assertEquals(
            "SELECT IF(a > ?, ?, ?) AS label FROM `t` WHERE id = ?",
            $this->sql($query),
        );
        $this->assertEquals([ 1, "hi", "bye", 5 ], $query->getBindings());
    }

    public function testASubQueryIsSelectedWithItsParams(): void {
        // A sub query is written whole into the select, so what it binds has to be
        // bound here as well. Values are matched to placeholders by their place, so
        // one that is left out does not go missing, it takes the value of the next
        $subQuery = Query::select("c", as: "c2");
        $subQuery->column("COUNT(*)");
        $subQuery->where("c2.x", "=", 7);

        $query = Query::select("t");
        $query->column($subQuery, "total");
        $query->where("id", "=", 3);

        $this->assertEquals(
            "SELECT (SELECT COUNT(*) FROM `c` AS `c2` WHERE c2.x = ? ) AS total FROM `t` WHERE id = ?",
            $this->sql($query),
        );
        $this->assertEquals([ 7, 3 ], $query->getBindings());
    }


    public function testWhereIfSkipsEmptyValues(): void {
        $query = Query::select("t");
        $query->whereIf("name", "=", "");
        $query->where("id", "=", 5);

        $this->assertEquals("SELECT * FROM `t` WHERE id = ?", $this->sql($query));
        $this->assertEquals([ 5 ], $query->getBindings());
    }

    public function testGroupedConditionsAreParenthesised(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->startOr();
        $query->where("b", "=", 2);
        $query->where("c", "=", 3);
        $query->endOr();

        $this->assertEquals("SELECT * FROM `t` WHERE a = ? AND ( b = ? OR c = ? )", $this->sql($query));
        $this->assertEquals([ 1, 2, 3 ], $query->getBindings());
    }

    public function testSearchSplitsTheValueIntoWords(): void {
        $query = Query::select("t");
        $query->search("name", "red widget", splitValue: true);

        $this->assertEquals("SELECT * FROM `t` WHERE ( name LIKE ? AND name LIKE ? )", $this->sql($query));
        $this->assertEquals([ "%red%", "%widget%" ], $query->getBindings());
    }

    public function testWhereExistsNestsTheSubQuery(): void {
        $images = Query::select("images");
        $images->where("productID", "=", 1);

        $query = Query::select("products");
        $query->whereExists($images);

        $this->assertEquals(
            "SELECT * FROM `products` WHERE EXISTS (SELECT 1 FROM `images` WHERE productID = ? )",
            $this->sql($query),
        );
        $this->assertEquals([ 1 ], $query->getBindings());
    }


    #[DataProvider("providerOrdering")]
    public function testOrdering(callable $build, string $expected): void {
        $this->assertEquals($expected, $this->sql($build()));
    }

    public static function providerOrdering(): array {
        return [
            "ascending" => [
                function () { $q = Query::select("t"); $q->orderBy("name", true); return $q; },
                "SELECT * FROM `t` ORDER BY name ASC",
            ],
            "declared"  => [
                function () { $q = Query::select("t"); $q->orderBy("name", true); $q->orderBy("price", false); return $q; },
                "SELECT * FROM `t` ORDER BY name ASC, price DESC",
            ],
            "grouped"   => [
                function () { $q = Query::select("t"); $q->groupBy("categoryID"); return $q; },
                "SELECT * FROM `t` GROUP BY categoryID",
            ],
        ];
    }


    public function testAnExpressionCanOrderWithItsParamsAfterTheWhere(): void {
        // The Order By is written after the Where, so a value it binds has to be bound
        // after the ones of the conditions, or each would take the place of the other
        $query = Query::select("t");
        $query->where("status", "=", "Active");
        $query->orderByExp(Exp::create("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", "Ana%"));
        $query->orderBy("name", true);
        $query->limit(20);

        $this->assertEquals(
            "SELECT * FROM `t` WHERE status = ? ORDER BY CASE WHEN name LIKE ? THEN 0 ELSE 1 END ASC, name ASC LIMIT 20",
            $this->sql($query),
        );
        $this->assertEquals([ "Active", "Ana%" ], $query->getBindings());
    }

    public function testAnExpressionCanOrderDescending(): void {
        $query = Query::select("t");
        $query->orderByExp(Exp::column("t.price"), false);

        $this->assertEquals("SELECT * FROM `t` ORDER BY t.price DESC", $this->sql($query));
        $this->assertEquals([], $query->getBindings());
    }

    #[DataProvider("providerLimit")]
    public function testLimit(int $from, ?int $to, string $expected): void {
        $query = Query::select("t");
        if ($to === null) {
            $query->limit($from);
        } else {
            $query->limit($from, $to);
        }
        $this->assertEquals(trim("SELECT * FROM `t` $expected"), $this->sql($query));
    }

    public static function providerLimit(): array {
        // The second argument is the last row, not an amount, so the count is
        // to - from + 1, and it never drops below one row
        return [
            "amount only"   => [ 10, null, "LIMIT 10" ],
            "from and to"   => [ 5,  15,   "LIMIT 5, 11" ],
            "from zero"     => [ 0,  25,   "LIMIT 0, 26" ],
            "to below from" => [ 20, 10, "LIMIT 20, 1" ],
            "a single row"  => [ 0,  0,  "LIMIT 0, 1" ],
            // An amount below one row is no amount, so it adds no Limit
            "no amount"     => [ 0,  null, "" ],
            "a negative"    => [ -3, null, "" ],
        ];
    }

    #[DataProvider("providerLimitTwice")]
    public function testLimitTwice(int $from, ?int $to, string $expected): void {
        $query = Query::select("t");
        $query->limit(5, 15);
        $query->limit($from, $to);

        $this->assertEquals(trim("SELECT * FROM `t` $expected"), $this->sql($query));
    }

    public static function providerLimitTwice(): array {
        // The last call wins, so one that limits nothing removes the Limit
        return [
            "an amount"  => [ 3,  null, "LIMIT 3" ],
            "a range"    => [ 0,  9,    "LIMIT 0, 10" ],
            "no amount"  => [ 0,  null, "" ],
            "a negative" => [ -3, null, "" ],
        ];
    }

    #[DataProvider("providerPaginate")]
    public function testPaginate(int $page, int $amount, string $expected): void {
        $query = Query::select("t");
        $query->paginate($page, $amount);

        $this->assertEquals("SELECT * FROM `t` $expected", $this->sql($query));
    }

    public static function providerPaginate(): array {
        return [
            "a later page"     => [ 2, 20, "LIMIT 40, 20" ],
            "the first page"   => [ 0, 20, "LIMIT 0, 20" ],
            // The whole page is one row, so the range is 0 to 0
            "one row per page" => [ 0, 1,  "LIMIT 0, 1" ],
        ];
    }


    #[DataProvider("providerWrites")]
    public function testWrites(callable $build, string $expected, array $bindings): void {
        $query = $build();
        $this->assertEquals($expected, $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerWrites(): array {
        return [
            "insert" => [
                function () {
                    $query = Query::insert("products");
                    $query->set("name", "Widget");
                    $query->set("price", 10);
                    return $query;
                },
                "INSERT INTO `products` (`name`, `price`) VALUES (?, ?)",
                [ "Widget", 10 ],
            ],
            "update" => [
                function () {
                    $query = Query::update("products");
                    $query->set("name", "New");
                    $query->where("id", "=", 7);
                    return $query;
                },
                "UPDATE `products` SET `name` = ? WHERE id = ?",
                [ "New", 7 ],
            ],
            "delete" => [
                function () {
                    $query = Query::delete("products");
                    $query->where("id", "=", 9);
                    return $query;
                },
                "DELETE FROM `products` WHERE id = ?",
                [ 9 ],
            ],
        ];
    }


    public function testValuesAreBoundAndNeverInlined(): void {
        $query = Query::select("t");
        $query->where("name", "=", "'; DROP TABLE users; --");

        $this->assertStringNotContainsString("DROP TABLE", $query->toSQL());
        $this->assertStringContainsString("name = ?", $query->toSQL());
        $this->assertEquals([ "'; DROP TABLE users; --" ], $query->getBindings());
    }

    public function testDebugSqlInlinesTheValuesForReading(): void {
        $query = Query::select("t");
        $query->where("name", "=", "Widget");

        $this->assertStringContainsString("'Widget'", $query->toDebugSQL());
        $this->assertStringNotContainsString("?", $query->toDebugSQL());
    }


    public function testOrWhereJoinsWithOr(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->orWhere("b", "=", 2);

        $this->assertEquals("SELECT * FROM `t` WHERE a = ? OR b = ?", $this->sql($query));
        $this->assertEquals([ 1, 2 ], $query->getBindings());
    }

    public function testGroupsNest(): void {
        $query = Query::select("t");
        $query->startOr();
            $query->startAnd();
                $query->where("a", "=", 1);
                $query->where("b", "=", 2);
            $query->endAnd();
            $query->startAnd();
                $query->where("c", "=", 3);
            $query->endAnd();
        $query->endOr();

        $this->assertEquals("SELECT * FROM `t` WHERE ( ( a = ? AND b = ? ) OR ( c = ? ) )", $this->sql($query));
        $this->assertEquals([ 1, 2, 3 ], $query->getBindings());
    }

    public function testWhereNotExistsNegatesTheSubQuery(): void {
        $images = Query::select("images");
        $images->where("productID", "=", 1);

        $query = Query::select("products");
        $query->whereNotExists($images);

        $this->assertEquals(
            "SELECT * FROM `products` WHERE NOT EXISTS (SELECT 1 FROM `images` WHERE productID = ? )",
            $this->sql($query),
        );
        $this->assertEquals([ 1 ], $query->getBindings());
    }


    #[DataProvider("providerSearch")]
    public function testSearch(callable $build, string $expected, array $bindings): void {
        $query = $build();
        $this->assertEquals($expected, $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerSearch(): array {
        return [
            // Several columns are ORed, while split words are ANDed unless matchAny.
            // A single condition is not wrapped in parentheses at all
            "one column"  => [
                function () { $q = Query::select("t"); $q->search("name", "widget"); return $q; },
                "SELECT * FROM `t` WHERE name LIKE ?", [ "%widget%" ],
            ],
            "two columns" => [
                function () { $q = Query::select("t"); $q->search([ "name", "code" ], "x"); return $q; },
                "SELECT * FROM `t` WHERE ( name LIKE ? OR code LIKE ? )", [ "%x%", "%x%" ],
            ],
            "match any"   => [
                function () { $q = Query::select("t"); $q->search("name", "red widget", splitValue: true, matchAny: true); return $q; },
                "SELECT * FROM `t` WHERE ( name LIKE ? OR name LIKE ? )", [ "%red%", "%widget%" ],
            ],
            // Anything that is neither a string nor a list is read as one word
            "a number"    => [
                function () { $q = Query::select("t"); $q->search("code", 42); return $q; },
                "SELECT * FROM `t` WHERE code LIKE ?", [ "%42%" ],
            ],
            // A zero is a value someone searches for, not an empty word
            "a zero"      => [
                function () { $q = Query::select("t"); $q->search("code", "0"); return $q; },
                "SELECT * FROM `t` WHERE code LIKE ?", [ "%0%" ],
            ],
            "a zero word" => [
                function () { $q = Query::select("t"); $q->search("name", "red 0", splitValue: true); return $q; },
                "SELECT * FROM `t` WHERE ( name LIKE ? AND name LIKE ? )", [ "%red%", "%0%" ],
            ],
        ];
    }


    #[DataProvider("providerWriteModes")]
    public function testWriteModes(callable $build, string $expected, array $bindings): void {
        $query = $build();
        $this->assertEquals($expected, $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerWriteModes(): array {
        return [
            // setExp takes raw sql and binds nothing, Assign::exp is the one that binds
            "truncate"  => [
                fn() => Query::truncate("t"),
                "TRUNCATE TABLE `t`", [],
            ],
            "replace"   => [
                function () { $q = Query::replace("t"); $q->set("a", 1); return $q; },
                "REPLACE INTO `t` (`a`) VALUES (?)", [ 1 ],
            ],
            "raw exp"   => [
                function () { $q = Query::update("t"); $q->setExp("views", "views + 1"); return $q; },
                "UPDATE `t` SET `views` = views + 1", [],
            ],
            "bound exp" => [
                function () { $q = Query::update("t"); $q->set("total", Assign::exp("price * ?", [ 3 ])); return $q; },
                "UPDATE `t` SET `total` = price * ?", [ 3 ],
            ],
            "increase"  => [
                function () { $q = Query::update("t"); $q->set("views", Assign::increase()); return $q; },
                "UPDATE `t` SET `views` = `views` + ?", [ 1 ],
            ],
            "uuid"      => [
                function () { $q = Query::update("t"); $q->set("code", Assign::uuid()); return $q; },
                "UPDATE `t` SET `code` = UUID()", [],
            ],
        ];
    }


    public function testAQueryKnowsWhatItIs(): void {
        $query = Query::select("products", "p");
        $query->where("name", "=", "W");
        $query->groupBy("categoryID");
        $query->orderBy("price", false);

        $this->assertEquals("products", $query->getTableName());
        $this->assertTrue($query->isSelect());
        $this->assertTrue($query->isNotEmpty());
        $this->assertFalse($query->isEmpty());
        $this->assertTrue($query->hasOrder());
        $this->assertTrue($query->hasGroup());
    }

    public function testAnUntouchedQueryIsEmpty(): void {
        $query = Query::select("t");

        $this->assertTrue($query->isEmpty());
        $this->assertFalse($query->isNotEmpty());
        $this->assertFalse($query->hasOrder());
        $this->assertFalse($query->hasGroup());
    }

    public function testAWriteIsNotASelect(): void {
        $query = Query::insert("t");
        $query->set("a", 1);

        $this->assertFalse($query->isSelect());
    }

    public function testAWriteReadsNothing(): void {
        // The two run different statements, so each answers empty for what
        // the other is for rather than running it
        $query = Query::insert("t");
        $query->set("a", 1);

        $this->assertSame(0, Query::select("t")->execute());
        $this->assertSame(0, $query->getAll()->count());
    }

    public function testAnEmptySubQueryIsNotNested(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);

        $query->whereExists(Query::select("o"));
        $query->whereExists(Query::insert("o"));

        $this->assertSame("SELECT * FROM `t` WHERE a = ?", $this->sql($query));
    }

    public function testAnEmptyGroupIsTakenBackOut(): void {
        // The opening is written before there is anything to put in it, so
        // closing an empty one has to take the opening away again
        $query = Query::select("t");
        $query->where("a", "=", 1);

        $query->startOr();
        $query->endOr();
        $query->startAnd();
        $query->endAnd();

        $this->assertSame("SELECT * FROM `t` WHERE a = ?", $this->sql($query));
    }

    public function testAnEmptyGroupInsideAnOrGoesToo(): void {
        // The one inside is opened with an OR, so that is what has to be
        // taken away rather than the AND of the outer one
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->startOr();
        $query->where("b", "=", 2);

        $query->startAnd();
        $query->endAnd();

        $query->endOr();
        $this->assertSame("SELECT * FROM `t` WHERE a = ? AND ( b = ? )", $this->sql($query));
    }

    public function testAnEmptyGroupWithNothingBeforeIt(): void {
        $query = Query::select("t");

        $query->startOr();
        $query->endOr();

        $this->assertSame("SELECT * FROM `t`", $this->sql($query));
    }

    public function testTheWhereColumnsAreReported(): void {
        $query = Query::select("t");
        $query->where("name", "=", "W");
        $query->where("price", ">", 1);

        $this->assertTrue($query->hasWhereColumn("name"));
        $this->assertTrue($query->hasWhereColumn("price"));
        $this->assertFalse($query->hasWhereColumn("missing"));
        $this->assertContains("name", $query->getWhereColumns());
    }

    public function testAnExpressionNamesNoColumn(): void {
        // The columns are read to be matched against the fields of a Model, and the
        // SQL of an expression is not one of those, so it is left out of them
        $query = Query::select("t");
        $query->where(Exp::create("isDeleted = 0"));
        $query->where("name", "=", "bob");

        $this->assertSame([ "name" ], $query->getWhereColumns());
        $this->assertFalse($query->hasWhereColumn("isDeleted"));

        // The one an expression is compared against is a column, and stays one
        $other = Query::select("t");
        $other->where("a", "=", Exp::column("b"));
        $this->assertSame([ "a" ], $other->getWhereColumns());
    }


    #[DataProvider("providerInjection")]
    public function testHostileValuesStayBound(string $value): void {
        $query = Query::select("t");
        $query->where("name", "=", $value);

        $this->assertEquals("SELECT * FROM `t` WHERE name = ?", $this->sql($query));
        $this->assertEquals([ $value ], $query->getBindings());
    }

    public static function providerInjection(): array {
        return [
            "quote"     => [ "O'Brien" ],
            "comment"   => [ "widget -- ignore" ],
            "union"     => [ "1 UNION SELECT * FROM credential" ],
            "semicolon" => [ "a; DELETE FROM t" ],
            "backtick"  => [ "`name`" ],
            "percent"   => [ "100%" ],
        ];
    }


    #[DataProvider("providerShape")]
    public function testShapingTheStatement(callable $build, string $expected): void {
        $this->assertEquals($expected, $this->sql($build()));
    }

    public static function providerShape(): array {
        return [
            // from replaces the table the statement was opened with
            "from replaces" => [
                function () {
                    $query = Query::select("a");
                    $query->from("b");
                    return $query;
                },
                "SELECT * FROM `b`",
            ],
            "one column"    => [
                function () {
                    $query = Query::select("t");
                    $query->column("name");
                    return $query;
                },
                "SELECT name FROM `t`",
            ],
            "raw select"    => [
                function () {
                    $query = Query::select("t");
                    $query->addSelect("COUNT(*) AS n");
                    return $query;
                },
                "SELECT COUNT(*) AS n FROM `t`",
            ],
            "raw join"      => [
                function () {
                    $query = Query::select("t");
                    $query->addJoin("INNER JOIN u ON (u.id = t.uid)");
                    return $query;
                },
                "SELECT * FROM `t` INNER JOIN u ON (u.id = t.uid)",
            ],
        ];
    }


    public function testParenthesesCanBeOpenedDirectly(): void {
        $query = Query::select("t");
        $query->startParen();
        $query->where("a", "=", 1);
        $query->endParen();

        $this->assertEquals("SELECT * FROM `t` WHERE ( a = ? )", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    #[DataProvider("providerJoiners")]
    public function testTheJoinerBetweenConditions(bool $useOr, string $expected): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        if ($useOr) {
            $query->or();
        } else {
            $query->and();
        }
        $query->where("b", "=", 2);

        $this->assertEquals("SELECT * FROM `t` WHERE $expected", $this->sql($query));
        $this->assertEquals([ 1, 2 ], $query->getBindings());
    }

    public static function providerJoiners(): array {
        return [
            "and" => [ false, "a = ? AND b = ?" ],
            "or"  => [ true,  "a = ? OR b = ?" ],
        ];
    }

    public function testAParamCanBeBoundToARawExpression(): void {
        $query = Query::select("t");
        $query->where(Exp::create("a = ?"));
        $query->addParam(5);

        $this->assertEquals("SELECT * FROM `t` WHERE a = ?", $this->sql($query));
        $this->assertEquals([ 5 ], $query->getBindings());
    }


    public function testTheFieldsBeingWrittenAreReadable(): void {
        $query = Query::insert("t");
        $query->set("a", 1);
        $query->set("b", 2);

        $this->assertTrue($query->hasField("a"));
        $this->assertFalse($query->hasField("missing"));
        $this->assertEquals([ "a" => 1, "b" => 2 ], $query->getFields()->toArray());
    }

    public function testFieldsSetsSeveralAtOnce(): void {
        $query = Query::insert("t");
        $query->fields([ "a" => 1, "b" => 2 ]);

        $this->assertEquals("INSERT INTO `t` (`a`, `b`) VALUES (?, ?)", $this->sql($query));
        $this->assertEquals([ 1, 2 ], $query->getBindings());
    }

    public function testAWhereColumnCanBeRenamed(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->updateWhereColumn("a", "b");

        $this->assertEquals("SELECT * FROM `t` WHERE b = ?", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    public function testAQueryCanBeTheTableOfAnother(): void {
        $inner = Query::select("products");
        $inner->column("SUM(total) AS total");
        $inner->where("price", ">", 100);

        $query = Query::select("x");
        $query->from($inner, "p");
        $query->where("p.name", "=", "W");

        $this->assertEquals(
            "SELECT * FROM (SELECT SUM(total) AS total FROM `products` WHERE price > ? ) AS `p` WHERE p.name = ?",
            $this->sql($query),
        );
        $this->assertEquals([ 100, "W" ], $query->getBindings());
    }

    public function testOpeningOnAQueryCarriesItsConditionsOver(): void {
        $inner = Query::select("products");
        $inner->where("price", ">", 100);

        $query = Query::select($inner, "p");
        $query->where("p.name", "=", "W");

        $this->assertEquals("SELECT * FROM `products` AS `p` WHERE price > ? AND p.name = ?", $this->sql($query));
        $this->assertEquals([ 100, "W" ], $query->getBindings());
    }

    public function testAnotherTableCanBeListedInTheFrom(): void {
        $query = Query::select("a");
        $query->withTable("b", "bb");
        $query->where("x", "=", 1);

        $this->assertEquals([ 1 ], $query->getBindings());
        $this->assertStringContainsString("FROM `a`", $this->sql($query));
    }


    public function testAQueryBecomesAValueForAnother(): void {
        $inner = Query::select("orders");
        $inner->column("SUM(total)");
        $inner->where("userID", "=", 5);

        $query = Query::update("users");
        $query->set("spent", $inner->toAssign());

        $this->assertEquals(
            "UPDATE `users` SET `spent` = (SELECT SUM(total) FROM `orders` WHERE userID = ? )",
            $this->sql($query),
        );
        $this->assertEquals([ 5 ], $query->getBindings());
    }

    #[DataProvider("providerIfNull")]
    public function testIfNullWrapsTheSubQuery(int|string $fallback, string $expected): void {
        $inner = Query::select("orders");
        $inner->column("SUM(total)");
        $inner->ifNull($fallback);

        $query = Query::update("users");
        $query->set("spent", $inner->toAssign());

        $this->assertEquals("UPDATE `users` SET `spent` = $expected", $this->sql($query));
    }

    public static function providerIfNull(): array {
        // A string fallback is quoted, a number is not
        return [
            "number" => [ 0,      "IFNULL((SELECT SUM(total) FROM `orders` ), 0)" ],
            "string" => [ "none", "IFNULL((SELECT SUM(total) FROM `orders` ), 'none')" ],
        ];
    }

    public function testJoiningASchemaQueryMergesItsConditions(): void {
        $credential = new CredentialQuery();
        $credential->email->equal("ada@example.com");

        $query = Query::select("log_action", "l");
        $query->join($credential);

        // The join is built from the model's id, and the sub query's own
        // conditions come along into the outer where
        $this->assertEquals(
            "SELECT * FROM `log_action` AS `l` "
            . "LEFT JOIN credential ON (credential.CREDENTIAL_ID = log_action.CREDENTIAL_ID) "
            . "WHERE credential.email = ?",
            $this->sql($query),
        );
        $this->assertEquals([ "ada@example.com" ], $query->getBindings());
    }

    #[DataProvider("providerValueTypes")]
    public function testValuesAreCoercedBeforeBinding(callable $build, array $bindings): void {
        $query = Query::update("t");
        $query->set("f", $build());

        $this->assertEquals("UPDATE `t` SET `f` = ?", $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerValueTypes(): array {
        // Everything reaches the database as a scalar, whatever it started as
        return [
            "string" => [ fn() => "x",                    [ "x" ] ],
            "int"    => [ fn() => 5,                      [ 5 ] ],
            "true"   => [ fn() => true,                   [ 1 ] ],
            "false"  => [ fn() => false,                  [ 0 ] ],
            "array"  => [ fn() => [ "x", "y" ],           [ '["x","y"]' ] ],
            "enum"   => [ fn() => Color::Red,             [ Color::Red->toString() ] ],
            "date"   => [ fn() => Date::create(1700000000), [ 1700000000 ] ],
        ];
    }

    public function testAConditionTakesAnEnumOrADate(): void {
        $enum = Query::select("t");
        $enum->where("c", "=", Color::Red);

        $date = Query::select("t");
        $date->where("d", "=", Date::create(1700000000));

        $this->assertEquals([ Color::Red->toString() ], $enum->getBindings());
        $this->assertEquals([ 1700000000 ], $date->getBindings());
    }

    public function testColumnsCanBeAliased(): void {
        $query = Query::select("t");
        $query->columns([ "n" => "name", "p" => "price" ]);

        $this->assertEquals("SELECT name AS n, price AS p FROM `t`", $this->sql($query));
    }

    #[DataProvider("providerConditions")]
    public function testAConditionCanBeSwitchedOff(?bool $condition, string $expected, array $bindings): void {
        $query = Query::select("t");
        $query->where("a", "=", 1, condition: $condition);

        $this->assertEquals(trim("SELECT * FROM `t` $expected"), $this->sql($query));
        $this->assertEquals($bindings, $query->getBindings());
    }

    public static function providerConditions(): array {
        return [
            "on"    => [ true,  "WHERE a = ?", [ 1 ] ],
            "off"   => [ false, "",            [] ],
            "unset" => [ null,  "WHERE a = ?", [ 1 ] ],
        ];
    }

    public function testAnOrConditionCanBeSwitchedOff(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->orWhere("b", "=", 2, condition: false);

        $this->assertEquals("SELECT * FROM `t` WHERE a = ?", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    #[DataProvider("providerWhereIf")]
    public function testWhereIfDecidesFromTheValueAndTheCondition(
        mixed $value,
        ?bool $condition,
        string $expected,
    ): void {
        $query = Query::select("t");
        $query->whereIf("a", "=", $value, $condition);

        $this->assertEquals(trim("SELECT * FROM `t` $expected"), $this->sql($query));
    }

    public static function providerWhereIf(): array {
        // With no condition it goes on the value alone, with one it obeys that
        return [
            "value, no condition"  => [ 1,    null,  "WHERE a = ?" ],
            "empty, no condition"  => [ "",   null,  "" ],
            "null, no condition"   => [ null, null,  "" ],
            "value, condition on"  => [ 1,    true,  "WHERE a = ?" ],
            "value, condition off" => [ 1,    false, "" ],
            "empty, condition on"  => [ "",   true,  "WHERE a = ?" ],
        ];
    }

    public function testSearchingForNothingAddsNoCondition(): void {
        $query = Query::select("t");
        $query->search("name", "");

        $this->assertEquals("SELECT * FROM `t`", $this->sql($query));
        $this->assertEquals([], $query->getBindings());
    }

    public function testASubQueryCanBeSelected(): void {
        $inner = Query::select("orders");
        $inner->column("COUNT(*)");

        $query = Query::select("t");
        $query->addSelect($inner, "n");

        $this->assertEquals("SELECT (SELECT COUNT(*) FROM `orders` ) AS n FROM `t`", $this->sql($query));
    }

    public function testAColumnEnumCanBeSelected(): void {
        $query = Query::select("t");
        $query->addSelect(CredentialColumn::Email);

        $this->assertEquals("SELECT credential.email FROM `t`", $this->sql($query));
    }

    public function testASubQueryCanBeTheValueOfAField(): void {
        $inner = Query::select("orders");
        $inner->column("SUM(x)");

        $query = Query::update("t");
        $query->set("f", $inner);

        $this->assertEquals("UPDATE `t` SET `f` = (SELECT SUM(x) FROM `orders` )", $this->sql($query));
    }

    public function testADeleteCanCarryAJoin(): void {
        $query = Query::delete("a");
        $query->addJoin("LEFT JOIN b ON (b.id = a.id)");
        $query->where("x", "=", 1);

        $this->assertEquals("DELETE `a` FROM `a` LEFT JOIN b ON (b.id = a.id) WHERE x = ?", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    public function testAJoinCanSitBesideAnExtraTable(): void {
        $query = Query::select("a");
        $query->withTable("b", "bb");
        $query->addJoin("LEFT JOIN c ON (c.id = a.id)");
        $query->where("x", "=", 1);

        $this->assertStringContainsString("LEFT JOIN c ON (c.id = a.id)", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    public function testADictionaryAndAFileAreCoercedToScalars(): void {
        $dictionary = Query::update("t");
        $dictionary->set("f", new Dictionary([ "a" => 1 ]));

        $file = Query::update("t");
        $file->set("f", new File("photo.jpg"));

        $this->assertEquals([ '{"a":1}' ], $dictionary->getBindings());
        $this->assertEquals([ "photo.jpg" ], $file->getBindings());
    }

    public function testAnUpdateCanCarryAJoin(): void {
        $query = Query::update("t");
        $query->addJoin("LEFT JOIN u ON (u.id = t.uid)");
        $query->set("a", 1);
        $query->where("x", "=", 2);

        $this->assertEquals("UPDATE `t` LEFT JOIN u ON (u.id = t.uid) SET `a` = ? WHERE x = ?", $this->sql($query));
        $this->assertEquals([ 1, 2 ], $query->getBindings());
    }

    public function testAnUpdateCanListAnotherTable(): void {
        $query = Query::update("t");
        $query->withTable("u", "uu");
        $query->set("a", 1);
        $query->where("x", "=", 2);

        $this->assertEquals("UPDATE `t` , `u` AS `uu` SET `a` = ? WHERE x = ?", $this->sql($query));
        $this->assertEquals([ 1, 2 ], $query->getBindings());
    }


    public function testAnEmptyGroupAddsNothing(): void {
        $query = Query::select("t");
        $query->where("a", "=", 1);
        $query->startAnd();
        $query->endAnd();

        $this->assertEquals("SELECT * FROM `t` WHERE a = ?", $this->sql($query));
        $this->assertEquals([ 1 ], $query->getBindings());
    }

    public function testSearchingAcrossSeveralValues(): void {
        $query = Query::select("t");
        $query->search("n", [ "red", "blue" ]);

        $this->assertEquals("SELECT * FROM `t` WHERE ( n LIKE ? AND n LIKE ? )", $this->sql($query));
        $this->assertEquals([ "%red%", "%blue%" ], $query->getBindings());
    }

    public function testTheOrderAndGroupCanBeAskedForByName(): void {
        $query = Query::select("t");
        $query->orderBy("name", true);
        $query->groupBy("categoryID");

        $this->assertTrue($query->hasOrder("name"));
        $this->assertFalse($query->hasOrder("missing"));
        $this->assertTrue($query->hasGroup("categoryID"));
        $this->assertFalse($query->hasGroup("missing"));
    }

    public function testGetQueryReturnsItself(): void {
        $query = Query::select("t");
        $query->where("x", "=", 1);

        $this->assertSame($query, $query->getQuery());
    }
}
