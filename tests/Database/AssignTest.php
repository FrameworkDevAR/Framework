<?php
namespace Tests\Database;

use Framework\Database\Query\Query;
use Framework\Database\Query\Assign;
use Framework\Database\Type\Column;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AssignTest extends TestCase {

    /**
     * Returns the SET clause an Assign produces, with its bindings
     * @param Assign $assign
     * @return array{string,array<int,mixed>}
     */
    private function setClause(Assign $assign): array {
        $query = Query::update("t");
        $query->set("f", $assign);

        $sql = preg_replace('/\s+/', " ", $query->toSQL());
        $sql = trim(str_replace("UPDATE `t` SET", "", (string)$sql));
        return [ $sql, $query->getBindings() ];
    }



    #[DataProvider("providerAssign")]
    public function testAssign(callable $build, string $expected, array $bindings): void {
        [ $sql, $bound ] = $this->setClause($build());

        $this->assertEquals($expected, $sql);
        $this->assertEquals($bindings, $bound);
    }

    public static function providerAssign(): array {
        return [
            "another column"   => [ fn() => Assign::equal("other"), "`f` = `other`",              [] ],
            "negated column"   => [ fn() => Assign::not("flag"), "`f` = !`flag`",              [] ],
            "increase by one"  => [ fn() => Assign::increase(), "`f` = `f` + ?",              [ 1 ] ],
            "increase by n"    => [ fn() => Assign::increase(5), "`f` = `f` + ?",              [ 5 ] ],
            "decrease by one"  => [ fn() => Assign::decrease(), "`f` = `f` - ?",              [ 1 ] ],
            "decrease by n"    => [ fn() => Assign::decrease(3), "`f` = `f` - ?",              [ 3 ] ],
            "uuid"             => [ fn() => Assign::uuid(), "`f` = UUID()",               [] ],
            "encrypt"          => [ fn() => Assign::encrypt("secret", "key"), "`f` = AES_ENCRYPT(?, ?)",    [ "secret", "key" ] ],
            "replace"          => [ fn() => Assign::replace("old", "new"), "`f` = REPLACE(`f`, ?, ?)",   [ "old", "new" ] ],
            "replace a prefix" => [ fn() => Assign::replacePrefix("old/", "new/"), "`f` = CONCAT(?, SUBSTRING(`f`, ?))", [ "new/", 5 ] ],
            "greatest"         => [ fn() => Assign::greatest(10), "`f` = GREATEST(`f`, ?)",     [ 10 ] ],
            "expression"       => [ fn() => Assign::exp("NOW()"), "`f` = NOW()",                [] ],
            "bound expression" => [ fn() => Assign::exp("price * ?", [ 3 ]), "`f` = price * ?",     [ 3 ] ],
        ];
    }

    public function testCaseHelpersRebuildTheValue(): void {
        [ $upper ] = $this->setClause(Assign::upperCaseFirst());
        [ $lower ] = $this->setClause(Assign::lowerCaseFirst());

        $this->assertEquals("`f` = CONCAT(UCASE(LEFT(`f`, 1)), SUBSTRING(`f`, 2))", $upper);
        $this->assertEquals("`f` = CONCAT(LCASE(LEFT(`f`, 1)), SUBSTRING(`f`, 2))", $lower);
    }

    public function testJsonHelpersSearchBeforeTheyWrite(): void {
        [ $replace, $replaceBound ] = $this->setClause(Assign::jsonReplace("a", "b"));
        [ $remove,  $removeBound ]  = $this->setClause(Assign::jsonRemove("a"));

        $this->assertStringStartsWith("`f` = JSON_REPLACE(`f`, CAST(JSON_UNQUOTE(JSON_SEARCH(", $replace);
        $this->assertEquals([ "a", "b" ], $replaceBound);

        $this->assertStringStartsWith("`f` = JSON_REMOVE(`f`, CAST(JSON_UNQUOTE(JSON_SEARCH(", $remove);
        $this->assertEquals([ "a" ], $removeBound);
    }

    public function testGreatestAgainstAColumnComparesColumns(): void {
        $column = new class implements Column {
            public function name(): string { return "highPrice"; }
            public function key(): string  { return "highPrice"; }
            public function base(): string { return "price"; }
        };

        [ $sql, $bound ] = $this->setClause(Assign::greatest($column));

        // Against a column it compares the two directly, with nothing to bind
        $this->assertEquals("`f` = GREATEST(`f`, `price`)", $sql);
        $this->assertEquals([], $bound);
    }

    public function testTheValuesAreBoundAndNotInlined(): void {
        [ $sql, $bound ] = $this->setClause(Assign::replace("'; DROP TABLE t; --", "x"));

        $this->assertStringNotContainsString("DROP TABLE", $sql);
        $this->assertEquals([ "'; DROP TABLE t; --", "x" ], $bound);
    }
}
