<?php
namespace Framework\Database\Query;

use Framework\Utils\Strings;

/**
 * A Query Expression
 */
class Exp {

    private string $sql;

    /** @var list<float|int|string> */
    private array $params;


    /**
     * Creates a new Exp instance
     * @param string                 $sql
     * @param list<float|int|string> $params Optional.
     */
    private function __construct(string $sql, array $params = []) {
        $this->sql    = $sql;
        $this->params = $params;
    }

    /**
     * Creates an Expression from the given SQL
     * @param string           $sql
     * @param float|int|string ...$params
     * @return Exp
     */
    public static function create(string $sql, float|int|string ...$params): Exp {
        return new Exp($sql, array_values($params));
    }

    /**
     * Creates an Expression with the name of a Column
     * It is what makes a comparison against another Column instead of a value, as a
     * plain string on that side of a where is bound and compared as text
     * @param string $column
     * @return Exp
     */
    public static function column(string $column): Exp {
        return new Exp($column);
    }

    /**
     * Creates an Expression with a Value, which is bound where it stands
     * It is what puts a value on the side of a comparison that is read as a column,
     * so a text can be asked whether it starts with what a column holds
     * @param float|int|string $value
     * @return Exp
     */
    public static function value(float|int|string $value): Exp {
        return new Exp("?", [ $value ]);
    }

    /**
     * Creates an Expression that counts the rows
     * @param string $column Optional.
     * @return Exp
     */
    public static function count(string $column = "*"): Exp {
        return new Exp("COUNT($column)");
    }

    /**
     * Creates an Expression that adds up the values of a Column
     * @param string $column
     * @return Exp
     */
    public static function sum(string $column): Exp {
        return new Exp("SUM($column)");
    }

    /**
     * Creates an Expression with the value of a Column in lower case
     * @param string $column
     * @return Exp
     */
    public static function lower(string $column): Exp {
        return new Exp("LOWER($column)");
    }

    /**
     * Creates an Expression with the value of a Column, or the given one when it is null
     * @param string           $column
     * @param float|int|string $value
     * @return Exp
     */
    public static function ifNull(string $column, float|int|string $value): Exp {
        return new Exp("IFNULL($column, ?)", [ $value ]);
    }

    /**
     * Creates an Expression with one value or the other, as the Condition says
     * @param Exp|string           $condition
     * @param Exp|float|int|string $then
     * @param Exp|float|int|string $else
     * @return Exp
     */
    public static function if(
        Exp|string $condition,
        Exp|float|int|string $then,
        Exp|float|int|string $else,
    ): Exp {
        $sql     = $condition instanceof Exp ? $condition->toSQL() : $condition;
        $params  = $condition instanceof Exp ? $condition->getParams() : [];
        $thenSQL = $then instanceof Exp ? $then->toSQL() : "?";
        $elseSQL = $else instanceof Exp ? $else->toSQL() : "?";

        // An Expression is written where it stands and a value is bound there, so
        // the params of the three come together in the order the SQL reads them
        foreach ([ $then, $else ] as $value) {
            if ($value instanceof Exp) {
                foreach ($value->getParams() as $param) {
                    $params[] = $param;
                }
            } else {
                $params[] = $value;
            }
        }
        return new Exp("IF($sql, $thenSQL, $elseSQL)", $params);
    }

    /**
     * Creates an Expression that joins the given Columns into one value
     * @param string ...$columns
     * @return Exp
     */
    public static function concat(string ...$columns): Exp {
        return new Exp("CONCAT(" . Strings::join($columns, ", ") . ")");
    }

    /**
     * Creates an Expression that reads a value of a JSON column
     * It is read as text so it is found whether it was saved as a number or as a
     * string, which a LIKE over the column can not do without taking 1 for 15 too
     * @param string $column
     * @param string $path
     * @return Exp
     */
    public static function json(string $column, string $path): Exp {
        // The path is bound rather than written into the SQL, so a quote in one
        // closes nothing, and it can be taken from a request like any other value
        return new Exp("JSON_UNQUOTE(JSON_EXTRACT($column, ?))", [ "$.$path" ]);
    }

    /**
     * Creates an Expression that is true when the Column holds a valid JSON
     * @param string $column
     * @return Exp
     */
    public static function jsonValid(string $column): Exp {
        return new Exp("JSON_VALID($column)");
    }

    /**
     * Creates an Expression with the path of the first place a JSON column holds the given text
     * @param string $column
     * @param string $value
     * @return Exp
     */
    public static function jsonSearch(string $column, string $value): Exp {
        // It looks at every value at any depth, inside the lists and the objects, and
        // compares them as texts, so a % or a _ in the value works as in a Like. It is
        // null when no value matches, so asking whether it is there is an isNotNull
        return new Exp("JSON_SEARCH($column, 'one', ?)", [ $value ]);
    }

    /**
     * Creates an Expression that is true when a JSON column holds the given value
     * @param string     $column
     * @param int|string $value
     * @return Exp
     */
    public static function jsonContains(string $column, int|string $value): Exp {
        // The value is compared as a JSON, so it is encoded, and a number is only found
        // as a number and a text as a text. Unlike the search, it has no wildcards, and
        // it looks inside the lists but not inside the objects. The escapes are compared
        // as they are written, so it is encoded with the flags the JSON of a column has
        $json = (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new Exp("JSON_CONTAINS($column, ?)", [ $json ]);
    }



    /**
     * Returns an Expression that is true when this one has no value
     * @return Exp
     */
    public function isNull(): Exp {
        return new Exp("{$this->sql} IS NULL", $this->params);
    }

    /**
     * Returns an Expression that is true when this one has a value
     * @return Exp
     */
    public function isNotNull(): Exp {
        return new Exp("{$this->sql} IS NOT NULL", $this->params);
    }

    /**
     * Returns the SQL of the Expression
     * @return string
     */
    public function toSQL(): string {
        return $this->sql;
    }

    /**
     * Returns the Params the Expression binds
     * @return list<float|int|string>
     */
    public function getParams(): array {
        return $this->params;
    }
}
