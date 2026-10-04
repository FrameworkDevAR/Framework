<?php
namespace Framework\Database\Query;

use Framework\Analysis\Attr\NotTested;
use Framework\Database\Type\Column;
use Framework\Utils\Strings;

/**
 * The Query Assign
 */
class Assign {

    private string $sql;

    /** @var list<float|int|string> */
    private array $params;



    /**
     * Creates a new Assign instance
     * @param string                 $sql    Optional.
     * @param list<float|int|string> $params Optional.
     */
    private function __construct(
        string $sql = "",
        array $params = [],
    ) {
        $this->sql    = $sql;
        $this->params = $params;
    }

    /**
     * Assigns an custom SQL Expression
     * @param string                 $sql
     * @param list<float|int|string> $params Optional.
     * @return Assign
     */
    public static function exp(string $sql, array $params = []): Assign {
        $sql = Strings::replace($sql, "\n", " ");
        $sql = Strings::replacePattern($sql, "!\s+!", " ");
        return new Assign($sql, $params);
    }

    /**
     * Assigns the value of another Column
     * @param string $column
     * @return Assign
     */
    public static function equal(string $column): Assign {
        return new Assign("`{$column}`");
    }

    /**
     * Assigns the Opposite of the value of another Column
     * @param string $column Optional.
     * @return Assign
     */
    public static function not(string $column): Assign {
        return new Assign("!`{$column}`");
    }

    /**
     * Assigns the Field value incremented by a certain Amount
     * @param int $amount Optional.
     * @return Assign
     */
    public static function increase(int $amount = 1): Assign {
        return new Assign("`__FIELD__` + ?", [ $amount ]);
    }

    /**
     * Assigns the Field value decremented by a certain Amount
     * @param int $amount Optional.
     * @return Assign
     */
    public static function decrease(int $amount = 1): Assign {
        return new Assign("`__FIELD__` - ?", [ $amount ]);
    }

    /**
     * Assigns a UUID value
     * @return Assign
     */
    public static function uuid(): Assign {
        return new Assign("UUID()");
    }

    /**
     * Assigns an AES Encrypt of the value with a certain key
     * @param string $value
     * @param string $key
     * @return Assign
     */
    public static function encrypt(string $value, string $key): Assign {
        return new Assign("AES_ENCRYPT(?, ?)", [ $value, $key ]);
    }

    /**
     * Assigns the Column replacing one value with another
     * @param string $value
     * @param string $replace
     * @return Assign
     */
    public static function replace(string $value, string $replace): Assign {
        return new Assign("REPLACE(`__FIELD__`, ?, ?)", [ $value, $replace ]);
    }

    /**
     * Assigns the Column replacing the given Prefix with another, which a Like would not
     * do, as the same text can be further in the value too
     * @param string $prefix
     * @param string $replace
     * @return Assign
     */
    public static function replacePrefix(string $prefix, string $replace): Assign {
        return new Assign("CONCAT(?, SUBSTRING(`__FIELD__`, ?))", [
            $replace,
            Strings::length($prefix) + 1,
        ]);
    }

    /**
     * Assigns the Greatest between the Field value and another value or Column
     * @param Column|int $value
     * @return Assign
     */
    public static function greatest(Column|int $value): Assign {
        if ($value instanceof Column) {
            return new Assign("GREATEST(`__FIELD__`, `{$value->base()}`)");
        }
        return new Assign("GREATEST(`__FIELD__`, ?)", [ $value ]);
    }

    /**
     * Assigns the Field value with the first letter in Upper Case
     * @return Assign
     */
    public static function upperCaseFirst(): Assign {
        return new Assign("CONCAT(UCASE(LEFT(`__FIELD__`, 1)), SUBSTRING(`__FIELD__`, 2))");  // phpcs:ignore
    }

    /**
     * Assigns the Field value with the first letter in Lower Case
     * @return Assign
     */
    public static function lowerCaseFirst(): Assign {
        return new Assign("CONCAT(LCASE(LEFT(`__FIELD__`, 1)), SUBSTRING(`__FIELD__`, 2))");  // phpcs:ignore
    }

    /**
     * Assigns the Field replacing a value in a JSON field
     * @param string $value
     * @param string $replace
     * @return Assign
     */
    public static function jsonReplace(string $value, string $replace): Assign {
        return new Assign(
            "JSON_REPLACE(`__FIELD__`, CAST(JSON_UNQUOTE(JSON_SEARCH(`__FIELD__`, 'one', ?)) AS CHAR), ?)",  // phpcs:ignore
            [ $value, $replace ],
        );
    }

    /**
     * Assigns the Field removing a value in a JSON field
     * @param string $value
     * @return Assign
     */
    public static function jsonRemove(string $value): Assign {
        return new Assign(
            "JSON_REMOVE(`__FIELD__`, CAST(JSON_UNQUOTE(JSON_SEARCH(`__FIELD__`, 'one', ?)) AS CHAR))",  // phpcs:ignore
            [ $value ],
        );
    }



    /**
     * Returns the parameters for the Assign
     * @return list<float|int|string>
     */
    #[NotTested("It needs a Database")]
    public function getParams(): array {
        return $this->params;
    }

    /**
     * Converts the Assign to an SQL expression
     * @param string $field
     * @return string
     */
    public function toSQL(string $field): string {
        return Strings::replace($this->sql, "__FIELD__", $field);
    }
}
