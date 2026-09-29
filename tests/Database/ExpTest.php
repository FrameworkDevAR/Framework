<?php
namespace Tests\Database;

use Framework\Database\Query\Exp;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Query Expressions
 *
 * What each one writes, on its own. How a Query takes one, on either side of
 * a condition or as a column, is in the Query tests beside these.
 */
class ExpTest extends TestCase {

    /**
     * An Expression, and the SQL and the params it carries
     * @param callable    $build
     * @param string      $expected
     * @param list<mixed> $params
     * @return void
     */
    #[DataProvider("providerExp")]
    public function testTheExpressionsAreBuilt(
        callable $build,
        string $expected,
        array $params,
    ): void {
        $exp = $build();

        $this->assertSame($expected, $exp->toSQL());
        $this->assertSame($params, $exp->getParams());
    }

    /**
     * One case per way of making an Expression. A value only becomes a param
     * where the SQL cannot hold it, which is why most of them carry none
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerExp(): array {
        return [
            "create"            => [ fn() => Exp::create("a + ?", 1), "a + ?", [ 1 ] ],
            "column"            => [ fn() => Exp::column("t.a"), "t.a", [] ],
            "value"             => [ fn() => Exp::value("text"), "?", [ "text" ] ],

            "count"             => [ fn() => Exp::count(), "COUNT(*)", [] ],
            "count one"         => [ fn() => Exp::count("t.a"), "COUNT(t.a)", [] ],
            "sum"               => [ fn() => Exp::sum("t.a"), "SUM(t.a)", [] ],

            "lower"             => [ fn() => Exp::lower("t.a"), "LOWER(t.a)", [] ],
            "concat"            => [ fn() => Exp::concat("t.a", "t.b"), "CONCAT(t.a, t.b)", [] ],
            "ifNull"            => [ fn() => Exp::ifNull("t.a", 0), "IFNULL(t.a, ?)", [ 0 ] ],
            "if"                => [
                fn() => Exp::if(Exp::column("t.a")->isNull(), "none", "some"),
                "IF(t.a IS NULL, ?, ?)", [ "none", "some" ],
            ],
            "isNull"            => [ fn() => Exp::column("t.a")->isNull(), "t.a IS NULL", [] ],
            "isNotNull"         => [ fn() => Exp::column("t.a")->isNotNull(), "t.a IS NOT NULL", [] ],

            "json"              => [
                fn() => Exp::json("t.d", "x"),
                "JSON_UNQUOTE(JSON_EXTRACT(t.d, ?))", [ "\$.x" ],
            ],
            "jsonValid"         => [ fn() => Exp::jsonValid("t.d"), "JSON_VALID(t.d)", [] ],
            "jsonSearch"        => [
                fn() => Exp::jsonSearch("t.d", "a.jpg"),
                "JSON_SEARCH(t.d, 'one', ?)", [ "a.jpg" ],
            ],
            "jsonContains"      => [
                fn() => Exp::jsonContains("t.d", 2),
                "JSON_CONTAINS(t.d, ?)", [ "2" ],
            ],
            "jsonContains text" => [
                fn() => Exp::jsonContains("t.d", "a"),
                "JSON_CONTAINS(t.d, ?)", [ "\"a\"" ],
            ],
        ];
    }

    public function testAJsonValueIsReadAsText(): void {
        // Read out by its path, so it is found whether it was saved as a number
        // or as a string. A LIKE over the column would take the 5 of a userID 51
        // for the one asked for, which is what this is instead of
        $exp = Exp::json("node.options", "userID");

        $this->assertSame("JSON_UNQUOTE(JSON_EXTRACT(node.options, ?))", $exp->toSQL());
        $this->assertSame([ "\$.userID" ], $exp->getParams());
    }

    public function testTheJsonPathIsBoundAndNotWritten(): void {
        // A quote in the path closes nothing, since the path never reaches the SQL
        $exp = Exp::json("t.d", "a') OR 1=1 -- ");

        $this->assertSame("JSON_UNQUOTE(JSON_EXTRACT(t.d, ?))", $exp->toSQL());
        $this->assertSame([ "\$.a') OR 1=1 -- " ], $exp->getParams());
    }

    /**
     * A null check over another Expression, and the one it wraps
     * @param callable $build
     * @param string   $expected
     * @return void
     */
    #[DataProvider("providerNullChecks")]
    public function testANullCheckTakesAnExpression(callable $build, string $expected): void {
        $exp = $build();

        $this->assertSame($expected, $exp->toSQL());
        // The params of the one being asked are kept, or the placeholder it
        // left in the SQL would have nothing to bind
        $this->assertSame([ "a.jpg" ], $exp->getParams());
    }

    /**
     * @return array<string,array{callable,string}>
     */
    public static function providerNullChecks(): array {
        return [
            "a search that found nothing" => [
                fn() => Exp::jsonSearch("t.d", "a.jpg")->isNull(),
                "JSON_SEARCH(t.d, 'one', ?) IS NULL",
            ],
            "a search that found one"     => [
                fn() => Exp::jsonSearch("t.d", "a.jpg")->isNotNull(),
                "JSON_SEARCH(t.d, 'one', ?) IS NOT NULL",
            ],
        ];
    }

    /**
     * The three parts of an If, and the SQL and the params they come to
     * @param callable    $build
     * @param string      $expected
     * @param list<mixed> $params
     * @return void
     */
    #[DataProvider("providerIf")]
    public function testTheIfTakesValuesAndExpressions(
        callable $build,
        string $expected,
        array $params,
    ): void {
        $exp = $build();

        $this->assertSame($expected, $exp->toSQL());
        $this->assertSame($params, $exp->getParams());
    }

    /**
     * Each of the three is written where it stands when it is an Expression, and
     * bound there when it is a value, so what they bind reads in the same order
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerIf(): array {
        return [
            "two values"        => [
                fn() => Exp::if(Exp::column("t.a")->isNull(), "none", "some"),
                "IF(t.a IS NULL, ?, ?)", [ "none", "some" ],
            ],
            "a column asked"    => [
                fn() => Exp::if("isActive", Exp::column("price"), 0),
                "IF(isActive, price, ?)", [ 0 ],
            ],
            "an expression too" => [
                fn() => Exp::if(Exp::jsonValid("d"), Exp::json("d", "name"), "none"),
                "IF(JSON_VALID(d), JSON_UNQUOTE(JSON_EXTRACT(d, ?)), ?)", [ "\$.name", "none" ],
            ],
            // The condition binds first, then the one it gives, then the other
            "all three binding" => [
                fn() => Exp::if(
                    Exp::jsonSearch("d", "a.jpg")->isNotNull(),
                    Exp::ifNull("t.x", 1),
                    2,
                ),
                "IF(JSON_SEARCH(d, 'one', ?) IS NOT NULL, IFNULL(t.x, ?), ?)", [ "a.jpg", 1, 2 ],
            ],
        ];
    }

    public function testACreateWithNoParamsBindsNothing(): void {
        $exp = Exp::create("NOW()");

        $this->assertSame("NOW()", $exp->toSQL());
        $this->assertSame([], $exp->getParams());
    }

    public function testTheParamsKeepTheOrderTheyWereGivenIn(): void {
        $exp = Exp::create("IF(a > ?, ?, ?)", 1, "hi", "bye");

        $this->assertSame("IF(a > ?, ?, ?)", $exp->toSQL());
        $this->assertSame([ 1, "hi", "bye" ], $exp->getParams());
    }
}
