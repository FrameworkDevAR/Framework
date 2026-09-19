<?php
namespace Tests\Database;

use Framework\Auth\Schema\CredentialColumn;
use Framework\Database\Query\Query;
use Framework\Database\Query\Op;
use Framework\Database\Where\BaseWhere;
use Framework\Database\Where\BooleanWhere;
use Framework\Database\Where\DateWhere;
use Framework\Database\Where\JsonWhere;
use Framework\Database\Where\EnumWhere;
use Framework\Database\Where\NumberWhere;
use Framework\Database\Where\StringWhere;
use Framework\Date\Date;
use Framework\Date\Type\PeriodType;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The typed Wheres, which are what a generated Query offers for a column
 */
class WhereTest extends TestCase {

    /**
     * Returns the SQL without the padding the builder leaves between clauses
     * @param Query $query
     * @return string
     */
    private function sql(Query $query): string {
        $result = preg_replace('/\s+/', " ", $query->toSQL());
        return trim((string)$result);
    }

    /**
     * Applies the given condition to a query, and returns what it built
     * @param object   $where
     * @param callable $apply
     * @param Query    $query
     * @return array{string,list<mixed>}
     */
    private function build(object $where, callable $apply, Query $query): array {
        $apply($where);
        return [ $this->sql($query), $query->getBindings() ];
    }



    /**
     * A condition on a number column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerNumber")]
    public function testTheNumberConditions(callable $apply, string $expected, array $bindings): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new NumberWhere($query, "count"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * The If variants lean on whereIf, which drops a value it reads as empty,
     * and zero is one of those
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerNumber(): array {
        return [
            "equal"            => [ fn(NumberWhere $w) => $w->equal(5), "WHERE count = ?", [ 5 ] ],
            "not equal"        => [ fn(NumberWhere $w) => $w->notEqual(5), "WHERE count <> ?", [ 5 ] ],
            "not equal if"     => [ fn(NumberWhere $w) => $w->notEqualIf(5), "WHERE count <> ?", [ 5 ] ],
            "not equal if 0"   => [ fn(NumberWhere $w) => $w->notEqualIf(0), "", [] ],
            "greater than"     => [ fn(NumberWhere $w) => $w->greaterThan(3), "WHERE count > ?", [ 3 ] ],
            "greater or equal" => [ fn(NumberWhere $w) => $w->greaterOrEqual(3), "WHERE count >= ?", [ 3 ] ],
            "less than"        => [ fn(NumberWhere $w) => $w->lessThan(3), "WHERE count < ?", [ 3 ] ],
            "less or equal"    => [ fn(NumberWhere $w) => $w->lessOrEqual(3), "WHERE count <= ?", [ 3 ] ],
            "in"               => [ fn(NumberWhere $w) => $w->in([ 1, 2 ]), "WHERE count IN (?,?)", [ 1, 2 ] ],
            "not in"           => [ fn(NumberWhere $w) => $w->notIn([ 1, 2 ]), "WHERE count NOT IN (?,?)", [ 1, 2 ] ],
            "compare"          => [ fn(NumberWhere $w) => $w->compare(Op::GreaterThan, 7), "WHERE count > ?", [ 7 ] ],

            "equal if"         => [ fn(NumberWhere $w) => $w->equalIf(5), "WHERE count = ?", [ 5 ] ],
            "equal if zero"    => [ fn(NumberWhere $w) => $w->equalIf(0), "", [] ],
            "equal if false"   => [ fn(NumberWhere $w) => $w->equalIf(5, condition: false), "", [] ],
        ];
    }



    /**
     * A condition on a string column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerString")]
    public function testTheStringConditions(callable $apply, string $expected, array $bindings): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new StringWhere($query, "name"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * Where the wildcards go is the whole difference between these
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerString(): array {
        return [
            "equal"          => [ fn(StringWhere $w) => $w->equal("bob"), "WHERE name = ?", [ "bob" ] ],
            "not equal"      => [ fn(StringWhere $w) => $w->notEqual("bob"), "WHERE name <> ?", [ "bob" ] ],
            // A Text is created as null, and a row nobody wrote it for is as empty as ''
            "is empty"       => [ fn(StringWhere $w) => $w->isEmpty(), "WHERE IFNULL(name, '') = ?", [ "" ] ],
            "is not empty"   => [ fn(StringWhere $w) => $w->isNotEmpty(), "WHERE name <> ?", [ "" ] ],
            "like"           => [ fn(StringWhere $w) => $w->like("bo"), "WHERE name LIKE ?", [ "%bo%" ] ],
            "not like"       => [ fn(StringWhere $w) => $w->notLike("bo"), "WHERE name NOT LIKE ?", [ "%bo%" ] ],
            "starts with"    => [ fn(StringWhere $w) => $w->startsWith("bo"), "WHERE name LIKE ?", [ "bo%" ] ],
            "ends with"      => [ fn(StringWhere $w) => $w->endsWith("ob"), "WHERE name LIKE ?", [ "%ob" ] ],
            "in"             => [ fn(StringWhere $w) => $w->in([ "a", "b" ]), "WHERE name IN (?,?)", [ "a", "b" ] ],
            "not in"         => [ fn(StringWhere $w) => $w->notIn([ "a", "b" ]), "WHERE name NOT IN (?,?)", [ "a", "b" ] ],

            "equal if"       => [ fn(StringWhere $w) => $w->equalIf("bob"), "WHERE name = ?", [ "bob" ] ],
            "equal if empty" => [ fn(StringWhere $w) => $w->equalIf(""), "", [] ],
            "like if false"  => [ fn(StringWhere $w) => $w->likeIf("bo", condition: false), "", [] ],
            "not equal if"   => [ fn(StringWhere $w) => $w->notEqualIf("bob"), "WHERE name <> ?", [ "bob" ] ],
            "not like if"    => [ fn(StringWhere $w) => $w->notLikeIf("bo"), "WHERE name NOT LIKE ?", [ "%bo%" ] ],
            "search"         => [ fn(StringWhere $w) => $w->search("bo"), "WHERE name LIKE ?", [ "%bo%" ] ],
            "search of two"  => [
                fn(StringWhere $w) => $w->search("bo ja", splitValue: true),
                "WHERE ( name LIKE ? AND name LIKE ? )", [ "%bo%", "%ja%" ],
            ],
        ];
    }



    /**
     * A condition on a boolean column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerBoolean")]
    public function testTheBooleanConditions(callable $apply, string $expected, array $bindings): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new BooleanWhere($query, "flag"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * isAny asks for anything at or above zero, which is every row that has one
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerBoolean(): array {
        return [
            "is true"       => [ fn(BooleanWhere $w) => $w->isTrue(), "WHERE flag = ?", [ 1 ] ],
            "is false"      => [ fn(BooleanWhere $w) => $w->isFalse(), "WHERE flag = ?", [ 0 ] ],
            "is any"        => [ fn(BooleanWhere $w) => $w->isAny(), "WHERE flag >= ?", [ 0 ] ],
            "equal true"    => [ fn(BooleanWhere $w) => $w->equal(true), "WHERE flag = ?", [ 1 ] ],
            "equal false"   => [ fn(BooleanWhere $w) => $w->equal(false), "WHERE flag = ?", [ 0 ] ],
            "true if"       => [ fn(BooleanWhere $w) => $w->equalTrueIf(true), "WHERE flag = ?", [ 1 ] ],
            "true if false" => [ fn(BooleanWhere $w) => $w->equalTrueIf(false), "", [] ],
            "false if"      => [ fn(BooleanWhere $w) => $w->equalFalseIf(true), "WHERE flag = ?", [ 0 ] ],
        ];
    }



    /**
     * A condition on a date column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerDate")]
    public function testTheDateConditions(callable $apply, string $expected, array $bindings): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new DateWhere($query, "at"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * A date is bound as the timestamp behind it, and an empty one adds nothing
     * at all rather than asking for zero
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerDate(): array {
        $date = Date::create("2020-01-02");
        $time = $date->toTime();

        return [
            "equal"            => [ fn(DateWhere $w) => $w->equal($date), "WHERE at = ?", [ $time ] ],
            "not equal"        => [ fn(DateWhere $w) => $w->notEqual($date), "WHERE at <> ?", [ $time ] ],
            "not equal if"     => [ fn(DateWhere $w) => $w->notEqualIf($date), "WHERE at <> ?", [ $time ] ],
            "greater than"     => [ fn(DateWhere $w) => $w->greaterThan($date), "WHERE at > ?", [ $time ] ],
            "greater or equal" => [ fn(DateWhere $w) => $w->greaterOrEqual($date), "WHERE at >= ?", [ $time ] ],
            "less than"        => [ fn(DateWhere $w) => $w->lessThan($date), "WHERE at < ?", [ $time ] ],
            "less or equal"    => [ fn(DateWhere $w) => $w->lessOrEqual($date), "WHERE at <= ?", [ $time ] ],

            "is empty"         => [ fn(DateWhere $w) => $w->isEmpty(), "WHERE at = ?", [ 0 ] ],
            "is not empty"     => [ fn(DateWhere $w) => $w->isNotEmpty(), "WHERE at <> ?", [ 0 ] ],

            "an empty date"    => [ fn(DateWhere $w) => $w->equal(Date::empty()), "", [] ],
            "equal if false"   => [ fn(DateWhere $w) => $w->equalIf(Date::create("2020-01-02"), condition: false), "", [] ],
        ];
    }



    /**
     * A condition on an enum column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerEnum")]
    public function testTheEnumConditions(callable $apply, string $expected, array $bindings): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new EnumWhere($query, "period"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * An enum is stored and compared as its name, and one value still goes in
     * through IN rather than an equals
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerEnum(): array {
        return [
            "equal one"      => [ fn(EnumWhere $w) => $w->equal(PeriodType::Today), "WHERE period = ?", [ "Today" ] ],
            "equal two"      => [
                fn(EnumWhere $w) => $w->equal(PeriodType::Today, PeriodType::ThisWeek),
                "WHERE period IN (?,?)", [ "Today", "ThisWeek" ],
            ],
            "not equal"      => [ fn(EnumWhere $w) => $w->notEqual(PeriodType::Today), "WHERE period <> ?", [ "Today" ] ],
            "equal name"     => [ fn(EnumWhere $w) => $w->equalName("Today"), "WHERE period = ?", [ "Today" ] ],
            "not equal name" => [ fn(EnumWhere $w) => $w->notEqualName("Today"), "WHERE period <> ?", [ "Today" ] ],
            "like"           => [ fn(EnumWhere $w) => $w->like("day"), "WHERE period LIKE ?", [ "%day%" ] ],
            "not like"       => [ fn(EnumWhere $w) => $w->notLike("day"), "WHERE period NOT LIKE ?", [ "%day%" ] ],
            "in"             => [
                fn(EnumWhere $w) => $w->in([ PeriodType::Today, PeriodType::ThisWeek ]),
                "WHERE period IN (?,?)", [ "Today", "ThisWeek" ],
            ],
            "not in"         => [ fn(EnumWhere $w) => $w->notIn([ PeriodType::Today ]), "WHERE period <> ?", [ "Today" ] ],
            "is empty"       => [ fn(EnumWhere $w) => $w->isEmpty(), "WHERE period = ?", [ "" ] ],
            "is not empty"   => [ fn(EnumWhere $w) => $w->isNotEmpty(), "WHERE period <> ?", [ "" ] ],
        ];
    }



    /**
     * A condition on a json column, and the query it leaves behind
     * @param callable    $apply
     * @param string      $expected
     * @param list<mixed> $bindings
     * @return void
     */
    #[DataProvider("providerJson")]
    public function testTheJsonConditions(
        callable $apply,
        string $expected,
        array $bindings,
    ): void {
        $query = Query::select("t");
        [ $sql, $bound ] = $this->build(new JsonWhere($query, "options"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
        $this->assertSame($bindings, $bound);
    }

    /**
     * A JSON column can be asked for a value inside it
     * @return array<string,array{callable,string,list<mixed>}>
     */
    public static function providerJson(): array {
        return [
            "json" => [
                fn(JsonWhere $w) => $w->where("flowID", Op::Equal, 5),
                "WHERE JSON_UNQUOTE(JSON_EXTRACT(options, ?)) = ?", [ "$.flowID", 5 ],
            ],
            "json with an operator" => [
                fn(JsonWhere $w) => $w->where("name", Op::NotEqual, "bob"),
                "WHERE JSON_UNQUOTE(JSON_EXTRACT(options, ?)) <> ?", [ "$.name", "bob" ],
            ],
            "in" => [
                fn(JsonWhere $w) => $w->in("flowID", [ 5, 7 ]),
                "WHERE JSON_UNQUOTE(JSON_EXTRACT(options, ?)) IN (?,?)", [ "$.flowID", 5, 7 ],
            ],
            "in with one" => [
                fn(JsonWhere $w) => $w->in("flowID", [ 5 ]),
                "WHERE JSON_UNQUOTE(JSON_EXTRACT(options, ?)) = ?", [ "$.flowID", 5 ],
            ],
            // Nothing to be one of is no condition, the same as the other Wheres
            "in with none" => [
                fn(JsonWhere $w) => $w->in("flowID", []), "", [],
            ],
            "not in" => [
                fn(JsonWhere $w) => $w->where("flowID", Op::NotIn, [ 5, 7 ]),
                "WHERE JSON_UNQUOTE(JSON_EXTRACT(options, ?)) NOT IN (?,?)", [ "$.flowID", 5, 7 ],
            ],

            // The value is looked for whole, which a like over the column cannot do:
            // it would take the ab.jpg of a list for the b.jpg being asked for
            "contains" => [
                fn(JsonWhere $w) => $w->contains("b.jpg"),
                "WHERE JSON_SEARCH(options, 'one', ?) IS NOT NULL", [ "b.jpg" ],
            ],

            "valid" => [
                fn(JsonWhere $w) => $w->isValid(), "WHERE JSON_VALID(options)", [],
            ],

            "empty" => [
                fn(JsonWhere $w) => $w->isEmpty(),
                "WHERE IFNULL(JSON_LENGTH(options), 0) = ?", [ 0 ],
            ],
            "not empty" => [
                fn(JsonWhere $w) => $w->isNotEmpty(),
                "WHERE IFNULL(JSON_LENGTH(options), 0) > ?", [ 0 ],
            ],
            "the whole column" => [
                fn(JsonWhere $w) => $w->like("bob"), "WHERE options LIKE ?", [ "%bob%" ],
            ],
            // The text of the column has the names of the keys in it too, so a like
            // over the whole of it answers for those as well as for the values. The
            // value is lowered on its way to being bound, as every like is
            "a name, not a value" => [
                fn(JsonWhere $w) => $w->like("userID"), "WHERE options LIKE ?", [ "%userid%" ],
            ],
        ];
    }



    /**
     * Something every column can be asked for, whatever it holds
     * @param callable $apply
     * @param string   $expected
     * @return void
     */
    #[DataProvider("providerBase")]
    public function testTheConditionsEveryColumnHas(callable $apply, string $expected): void {
        $query = Query::select("t");
        [ $sql ] = $this->build(new BaseWhere($query, "name"), $apply, $query);

        $this->assertSame(trim("SELECT * FROM `t` $expected"), $sql);
    }

    /**
     * @return array<string,array{callable,string}>
     */
    public static function providerBase(): array {
        return [
            "order ascending"  => [ fn(BaseWhere $w) => $w->orderByAsc(), "ORDER BY name ASC" ],
            "order descending" => [ fn(BaseWhere $w) => $w->orderByDesc(), "ORDER BY name DESC" ],
            "group by"         => [ fn(BaseWhere $w) => $w->groupBy(), "GROUP BY name" ],
            "equal to a column" => [
                fn(BaseWhere $w) => $w->equalColumn(CredentialColumn::Email),
                "WHERE name = credential.email",
            ],
        ];
    }
}
