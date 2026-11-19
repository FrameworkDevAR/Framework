<?php
namespace Framework\Database\Query;

use Framework\Database\Query\QueryMode;
use Framework\Database\Query\TableDefinition;
use Framework\Database\Query\Assign;
use Framework\Database\Type\Column;
use Framework\Date\Date;
use Framework\Enum\Enum;
use Framework\File\File;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

use JsonSerializable;

/**
 * The Query Builder
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @phpstan-type QueryValue Query|Dictionary|Date|Enum|File|JsonSerializable|array<int|string,mixed>|bool|Assign|float|int|string
 * @phpstan-type SelectValue Query|Column|Exp|string
 */
class QueryBuilder {

    private QueryMode $mode;

    private WhereBuilder $whereBuilder;

    private TableDefinition $table;

    /** @var list<TableDefinition> */
    private array $otherTables = [];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $selects = [];

    /** @var list<float|int|string> */
    private array $selectParams = [];

    /** @var array<string,Assign|float|int|string> */
    private array $fields = [];

    private int|string|null $ifNullValue = null;



    /**
     * Creates a new Query Builder instance
     * @param QueryMode    $mode
     * @param string       $tableName
     * @param string       $asTable
     * @param WhereBuilder $whereBuilder
     */
    public function __construct(
        QueryMode $mode,
        string $tableName,
        string $asTable,
        WhereBuilder $whereBuilder,
    ) {
        $this->mode         = $mode;
        $this->table        = new TableDefinition($tableName, $asTable);
        $this->whereBuilder = $whereBuilder;
    }


    /**
     * Sets the From in the Query
     * @param Query|string $queryOrTable
     * @param string       $as           Optional.
     * @return void
     */
    public function setFrom(Query|string $queryOrTable, string $as = ""): void {
        $this->table = new TableDefinition($queryOrTable, $as);
    }

    /**
     * Adds a new table to the Query
     * @param string $tableName
     * @param string $as        Optional.
     * @return void
     */
    public function addTable(string $tableName, string $as = ""): void {
        $this->otherTables[] = new TableDefinition($tableName, $as);
    }

    /**
     * Adds a join to the Query Builder
     * @param string $join
     * @return void
     */
    public function addJoin(string $join): void {
        $this->joins[] = $join;
    }

    /**
     * Adds a select to the Select Builder
     * @param SelectValue $select
     * @param string      $as     Optional.
     * @return void
     */
    public function addSelect(mixed $select, string $as = ""): void {
        if ($select instanceof Query) {
            // The sub query is written whole, so what it binds is bound here too, or
            // its placeholders would take the values of whatever follows them
            foreach ($select->getBindings() as $param) {
                $this->selectParams[] = $param;
            }
            $select = "({$select->toSQL()})";
        } elseif ($select instanceof Column) {
            $select = $select->name();
        } elseif ($select instanceof Exp) {
            foreach ($select->getParams() as $param) {
                $this->selectParams[] = $param;
            }
            $select = $select->toSQL();
        }
        if ($as !== "") {
            $select .= " AS $as";
        }
        $this->selects[] = $select;
    }

    /**
     * Sets a field to be Inserted or Updated
     * @param string     $field
     * @param QueryValue $value
     * @return void
     */
    public function setField(string $field, mixed $value): void {
        // Convert a Query to SQL
        if ($value instanceof Query) {
            $this->fields[$field] = $value->toAssign();

        // Encode any Dictionary to JSON
        } elseif ($value instanceof Dictionary) {
            $this->fields[$field] = $value->toJSON();

        // Convert any Date to a timestamp
        } elseif ($value instanceof Date) {
            $this->fields[$field] = $value->toTime();

        // Convert any Enum to a string
        } elseif ($value instanceof Enum) {
            $this->fields[$field] = $value->toString();

        // Convert a File to an string
        } elseif ($value instanceof File) {
            $this->fields[$field] = $value->toString();

        // Encode any array to JSON
        } elseif (is_array($value) || $value instanceof JsonSerializable) {
            $this->fields[$field] = JSON::encode($value);

        // Convert any boolean to an integer
        } elseif (is_bool($value)) {
            $this->fields[$field] = $value ? 1 : 0;

        // Set any Assign, float, int or string as is
        } else {
            $this->fields[$field] = $value;
        }
    }

    /**
     * Sets multiple fields to be Inserted or Updated
     * @param array<string,QueryValue> $fields
     * @return void
     */
    public function setFields(array $fields): void {
        foreach ($fields as $field => $value) {
            $this->setField($field, $value);
        }
    }

    /**
     * Sets the If Null value
     * @param int|string $value
     * @return void
     */
    public function setIfNull(int|string $value): void {
        $this->ifNullValue = $value;
    }



    /**
     * Returns the Table Name
     * @return string
     */
    public function getTableName(): string {
        return $this->table->getName();
    }

    /**
     * Returns true if the Field exists
     * @param string $field
     * @return bool
     */
    public function hasField(string $field): bool {
        return !Arrays::isEmpty($this->fields, $field);
    }

    /**
     * Returns the Fields
     * @return Dictionary
     */
    public function getFields(): Dictionary {
        return new Dictionary($this->fields);
    }



    /**
     * Returns the complete SQL expression of the Query
     * @param bool $forDebug Optional.
     * @return string
     */
    public function toSQL(bool $forDebug = false): string {
        $result = match ($this->mode) {
            QueryMode::Select       => $this->toSelectSQL($forDebug),
            QueryMode::Insert       => $this->toInsertSQL(),
            QueryMode::InsertIgnore => $this->toInsertSQL(isIgnore: true),
            QueryMode::Replace      => $this->toReplaceSQL(),
            QueryMode::Update       => $this->toUpdateSQL(),
            QueryMode::Delete       => $this->toDeleteSQL(),
            QueryMode::Truncate     => $this->toTruncateSQL(),
        };

        $result = Strings::replace($result, "\n", " ");
        $result = Strings::replacePattern($result, "!\s+!", " ");
        return $result;
    }

    /**
     * Returns the SQL to add it as an assignment
     * @return Assign
     */
    public function toAssign(): Assign {
        $expression = $this->toSQL();
        $bindings   = $this->getBindings();

        $expression = "({$expression})";
        if ($this->ifNullValue !== null) {
            $value = $this->ifNullValue;
            if (is_string($this->ifNullValue)) {
                $value = "'{$this->ifNullValue}'";
            }
            $expression = "IFNULL($expression, $value)";
        }
        return Assign::exp($expression, $bindings);
    }

    /**
     * Converts the Select Builder to an SQL string
     * @param bool $forDebug Optional.
     * @return string
     */
    private function toSelectSQL(bool $forDebug = false): string {
        $glue = $forDebug ? ",\n    " : ", ";

        $expression = [ "SELECT" ];
        if (count($this->selects) === 0) {
            $expression[] = "*";
        } else {
            $expression[] = Strings::join($this->selects, $glue);
        }

        $expression[] = "FROM " . $this->table->toSQL();
        foreach ($this->joins as $join) {
            $expression[] = $join;
        }

        $expression[] = $this->whereBuilder->toSQL(addWhere: true);
        return Strings::join($expression, " ");
    }

    /**
     * Converts the Query Builder to an Insert SQL string
     * @param bool $isIgnore Optional.
     * @return string
     */
    private function toInsertSQL(bool $isIgnore = false): string {
        $values = [];
        foreach ($this->fields as $field => $value) {
            $values[] = $value instanceof Assign ? $value->toSQL($field) : "?";
        }

        $expression  = $isIgnore ? "INSERT IGNORE INTO " : "INSERT INTO ";
        $expression .= $this->table->toSQL();
        $expression .= " (`" . Strings::joinKeys($this->fields, "`, `") . "`)";
        $expression .= " VALUES (" . Strings::join($values, ", ") . ")";

        return $expression;
    }

    /**
     * Converts the Query Builder to a Replace SQL string
     * @return string
     */
    private function toReplaceSQL(): string {
        $values = [];
        foreach ($this->fields as $field => $value) {
            $values[] = $value instanceof Assign ? $value->toSQL($field) : "?";
        }

        $expression  = "REPLACE INTO ";
        $expression .= $this->table->toSQL();
        $expression .= " (`" . Strings::joinKeys($this->fields, "`, `") . "`)";
        $expression .= " VALUES (" . Strings::join($values, ", ") . ")";

        return $expression;
    }

    /**
     * Converts the Query Builder to an Update SQL string
     * @return string
     */
    private function toUpdateSQL(): string {
        // Start the expression with the Update and From part
        $expression   = [];
        $expression[] = "UPDATE " . $this->table->toSQL();
        foreach ($this->otherTables as $table) {
            $expression[] = ", " . $table->toSQL();
        }

        // Add the Joins if they exist
        foreach ($this->joins as $join) {
            $expression[] = $join;
        }

        // Add the Set part with the fields to update
        $assigns = [];
        foreach ($this->fields as $field => $value) {
            $fieldKey = !Strings::contains($field, ".") ? "`$field`" : $field;
            if ($value instanceof Assign) {
                $assigns[] = "$fieldKey = " . $value->toSQL($field);
            } else {
                $assigns[] = "$fieldKey = ?";
            }
        }
        $expression[] = "SET " . Strings::join($assigns, ", ");

        // Add the Where part
        $expression[] = $this->whereBuilder->toSQL(addWhere: true);

        return Strings::join($expression, " ");
    }

    /**
     * Converts the Query Builder to a Delete SQL string
     * @return string
     */
    private function toDeleteSQL(): string {
        $expression = [ "DELETE" ];
        if (count($this->joins) > 0) {
            $expression[] = $this->table->toSQL();
        }

        $expression[] = "FROM " . $this->table->toSQL();
        foreach ($this->joins as $join) {
            $expression[] = $join;
        }

        $expression[] = $this->whereBuilder->toSQL(addWhere: true);
        return Strings::join($expression, " ");
    }

    /**
     * Converts the Query Builder to a Truncate SQL string
     * @return string
     */
    private function toTruncateSQL(): string {
        return "TRUNCATE TABLE " . $this->table->toSQL();
    }



    /**
     * Returns the bindings for the Query Builder
     * @return list<float|int|string>
     */
    public function getBindings(): array {
        // The Selects are the first thing of the SQL, so their params go first too
        $bindings = Arrays::mergeLists($this->selectParams, $this->table->getBindings());
        foreach ($this->fields as $value) {
            if ($value instanceof Assign) {
                foreach ($value->getParams() as $param) {
                    $bindings[] = $param;
                }
            } else {
                $bindings[] = $value;
            }
        }

        $bindings = Arrays::mergeLists($bindings, $this->whereBuilder->getParams());
        return $bindings;
    }

    /**
     * Interpolates the Params in the given SQL expression
     * @param string                 $expression
     * @param list<float|int|string> $bindings   Optional.
     * @return string
     */
    public function interpolateParams(string $expression, array $bindings): string {
        foreach ($bindings as $value) {
            if (is_string($value)) {
                $value = "'$value'";
            } else {
                $value = Strings::toString($value);
            }
            $expression = Strings::replacePattern($expression, '/\?/', $value, 1);
        }

        // Add new lines for better readability
        $keywords = [
            "FROM", "LEFT JOIN", "VALUES", "SET", "WHERE",
            "ORDER BY", "GROUP BY", "LIMIT",
        ];
        foreach ($keywords as $key) {
            $expression = Strings::replace($expression, "$key ", "\n$key ");
        }

        return $expression;
    }
}
