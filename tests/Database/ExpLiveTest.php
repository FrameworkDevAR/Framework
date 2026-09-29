<?php
namespace Tests\Database;

use Framework\Database\Query\Exp;
use Framework\Database\Query\Query;
use Framework\Database\Where\StringWhere;
use Framework\Utils\JSON;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Query Expressions, asked of a database
 *
 * What an Expression writes is in the tests beside these. What the database
 * answers to it is here, for the ones whose answer is the point of them.
 */
class ExpLiveTest extends LiveTestCase {

    private const Table = "test_exp";


    protected function setUp(): void {
        parent::setUp();

        $this->query("DROP TABLE IF EXISTS `" . self::Table . "`");
        $this->query(
            "CREATE TABLE `" . self::Table . "` (" .
            "`id` int(10) unsigned NOT NULL, `tags` mediumtext NULL, " .
            "PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );

        // Written the way the Framework writes an Array field, so what is found is
        // what an App would find
        $rows = [
            1 => JSON::encode([ 1, 2 ]),
            2 => JSON::encode([ "1", "2" ]),
            3 => JSON::encode([ "a/b", "ñ" ]),
            4 => JSON::encode([ [ 5, 6 ] ]),
            5 => JSON::encode([ [ "id" => 7 ] ]),
            6 => null,
            7 => "not a json",
        ];
        foreach ($rows as $id => $tags) {
            $this->db()->execute("INSERT INTO `" . self::Table . "` VALUES (?, ?)", [ $id, $tags ]);
        }
    }

    protected function tearDown(): void {
        $this->query("DROP TABLE IF EXISTS `" . self::Table . "`");
    }

    /**
     * Returns the IDs of the rows the given Query finds
     * @param Query $query
     * @return list<int>
     */
    private function idsOf(Query $query): array {
        $result = [];
        foreach ($this->db()->getData($query->toSQL(), $query->getBindings()) as $row) {
            $result[] = $row->getInt("id");
        }
        sort($result);
        return $result;
    }



    /**
     * A value, and the rows whose JSON contains it
     * @param int|string $value
     * @param list<int>  $expected
     * @return void
     */
    #[DataProvider("providerJsonContains")]
    public function testTheJsonContainsTheValue(int|string $value, array $expected): void {
        $query = Query::select(self::Table)->where(Exp::jsonContains("tags", $value));

        $this->assertSame($expected, $this->idsOf($query));
    }

    /**
     * @return array<string,array{int|string,list<int>}>
     */
    public static function providerJsonContains(): array {
        return [
            // A number is only a number and a text only a text, which is what the
            // search can not tell apart
            "a number"              => [ 2, [ 1 ] ],
            "a text"                => [ "2", [ 2 ] ],

            // The database compares the escapes as they are written, so the value
            // is only found when it is encoded the way the column was
            "a slash"               => [ "a/b", [ 3 ] ],
            "an accent"             => [ "ñ", [ 3 ] ],

            "no wildcards"          => [ "a%", [] ],
            "inside a nested list"  => [ 5, [ 4 ] ],
            "not inside an object"  => [ 7, [] ],
            "nothing that is there" => [ 9, [] ],
        ];
    }

    public function testAnyOfTheValuesIsFound(): void {
        $query = Query::select(self::Table);
        (new StringWhere($query, "tags"))->jsonContains([ 1, "2" ]);

        // A null and a text that is no JSON answer null, which is no row, and
        // not an error that would take the others with it
        $this->assertSame([ 1, 2 ], $this->idsOf($query));
    }
}
