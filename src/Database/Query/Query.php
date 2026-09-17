<?php
namespace Framework\Database\Query;

use Framework\Analysis\Attr\NotTested;
use Framework\Database\Database;
use Framework\Database\Query\QueryLike;
use Framework\Database\Query\QueryMode;
use Framework\Database\Query\QueryBuilder;
use Framework\Database\Query\WhereBuilder;
use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;

/**
 * The Database Query
 * @phpstan-import-type QueryValue  from QueryBuilder
 * @phpstan-import-type SelectValue from QueryBuilder
 * @phpstan-import-type WhereValue  from WhereBuilder
 */
class Query implements QueryLike {

    private QueryMode $mode;
    private QueryBuilder $queryBuilder;
    private WhereBuilder $whereBuilder;


    /**
     * Creates a new Query instance
     * @param QueryMode        $mode
     * @param QueryLike|string $queryOrTable
     * @param string           $asTable      Optional.
     */
    private function __construct(
        QueryMode $mode,
        QueryLike|string $queryOrTable,
        string $asTable = "",
    ) {
        if ($queryOrTable instanceof QueryLike) {
            $tableName = $queryOrTable->getTableName();
        } else {
            $tableName = $queryOrTable;
        }

        $this->mode         = $mode;
        $this->whereBuilder = new WhereBuilder();
        $this->queryBuilder = new QueryBuilder(
            $mode,
            $tableName,
            $asTable,
            $this->whereBuilder,
        );

        if ($queryOrTable instanceof QueryLike) {
            $this->whereBuilder->copy($queryOrTable->getQuery()->whereBuilder);
        }
    }

    /**
     * Creates a new SELECT Query for the given table
     * @param QueryLike|string $queryOrTable
     * @param string           $as           Optional.
     * @return Query
     */
    public static function select(
        QueryLike|string $queryOrTable,
        string $as = "",
    ): Query {
        return new Query(QueryMode::Select, $queryOrTable, $as);
    }

    /**
     * Creates a new INSERT Query for the given table
     * @param QueryLike|string $queryOrTable
     * @return Query
     */
    public static function insert(QueryLike|string $queryOrTable): Query {
        return new Query(QueryMode::Insert, $queryOrTable);
    }

    /**
     * Creates a new REPLACE Query for the given table
     * @param QueryLike|string $queryOrTable
     * @return Query
     */
    public static function replace(QueryLike|string $queryOrTable): Query {
        return new Query(QueryMode::Replace, $queryOrTable);
    }

    /**
     * Creates a new UPDATE Query for the given table
     * @param QueryLike|string $queryOrTable
     * @param string           $as           Optional.
     * @return Query
     */
    public static function update(
        QueryLike|string $queryOrTable,
        string $as = "",
    ): Query {
        return new Query(QueryMode::Update, $queryOrTable, $as);
    }

    /**
     * Creates a new DELETE Query for the given table
     * @param QueryLike|string $queryOrTable
     * @return Query
     */
    public static function delete(QueryLike|string $queryOrTable): Query {
        return new Query(QueryMode::Delete, $queryOrTable);
    }

    /**
     * Creates a new TRUNCATE Query for the given table
     * @param QueryLike|string $queryOrTable
     * @return Query
     */
    public static function truncate(QueryLike|string $queryOrTable): Query {
        return new Query(QueryMode::Truncate, $queryOrTable);
    }



    /**
     * Sets the From in the Query
     * @param Query|string $queryOrTable
     * @param string       $as           Optional.
     * @return Query
     */
    public function from(Query|string $queryOrTable, string $as = ""): Query {
        $this->queryBuilder->setFrom($queryOrTable, $as);
        return $this;
    }

    /**
     * Adds a new table to the Query
     * @param string $tableName
     * @param string $as        Optional.
     * @return Query
     */
    public function withTable(string $tableName, string $as = ""): Query {
        $this->queryBuilder->addTable($tableName, $as);
        return $this;
    }

    /**
     * Adds a Join to the Query
     * @param SchemaQuery|string $queryOrTable
     * @param string             $as           Optional.
     * @param string             $on           Optional.
     * @param string             $type         Optional.
     * @return Query
     */
    public function join(
        SchemaQuery|string $queryOrTable,
        string $as = "",
        string $on = "",
        string $type = "LEFT",
    ): Query {
        // Do a Join using the table name
        if (is_string($queryOrTable)) {
            $expression = "$type JOIN $queryOrTable";
            if ($as !== "") {
                $expression .= " AS $as";
            }
            if ($on !== "") {
                $expression .= " ON ($on)";
            }
            $this->queryBuilder->addJoin($expression);
            return $this;
        }

        // Do a Join using the Query
        $thisTable = $this->getTableName();
        $tableName = $queryOrTable->getTableName();
        $idDbName  = $queryOrTable->getIDDbName();
        if ($on === "" && $idDbName !== "") {
            $on = "{$tableName}.{$idDbName} = {$thisTable}.{$idDbName}";
        }

        // The ON is required for the Join
        if ($idDbName === "") {
            return $this;
        }

        // Add the Join
        $this->queryBuilder->addJoin("$type JOIN $tableName ON ($on)");
        $this->whereBuilder->join($queryOrTable->getQuery()->whereBuilder);
        return $this;
    }

    /**
     * Adds a raw Join to the Query
     * @param string $join
     * @return Query
     */
    public function addJoin(string $join): Query {
        $this->queryBuilder->addJoin($join);
        return $this;
    }

    /**
     * Adds a new Column to the Query
     * @param SelectValue $column
     * @param string      $as     Optional.
     * @return Query
     */
    public function column(mixed $column, string $as = ""): Query {
        $this->queryBuilder->addSelect($column, $as);
        return $this;
    }

    /**
     * Sets the Columns used in a SELECT query
     * @param array<string,SelectValue>|SelectValue $columns
     * @param SelectValue                           ...$selects
     * @return Query
     */
    public function columns(mixed $columns, mixed ...$selects): Query {
        if (is_array($columns)) {
            foreach ($columns as $alias => $column) {
                $this->queryBuilder->addSelect($column, $alias);
            }
        } else {
            $this->queryBuilder->addSelect($columns);
        }

        foreach ($selects as $select) {
            $this->queryBuilder->addSelect($select);
        }
        return $this;
    }

    /**
     * Adds a new Select clause
     * @param SelectValue $select
     * @param string      $as     Optional.
     * @return Query
     */
    public function addSelect(mixed $select, string $as = ""): Query {
        $this->queryBuilder->addSelect($select, $as);
        return $this;
    }

    /**
     * Sets a field to be Inserted or Updated
     * @param string     $field
     * @param QueryValue $value
     * @return Query
     */
    public function set(string $field, mixed $value): Query {
        $this->queryBuilder->setField($field, $value);
        return $this;
    }

    /**
     * Sets a field to be Inserted or Updated using a raw SQL expression
     * @param string $field
     * @param string $sql
     * @return Query
     */
    public function setExp(string $field, string $sql): Query {
        $this->queryBuilder->setField($field, Assign::exp($sql));
        return $this;
    }

    /**
     * Sets multiple fields to be Inserted or Updated
     * @param array<string,QueryValue> $fields
     * @return Query
     */
    public function fields(array $fields): Query {
        $this->queryBuilder->setFields($fields);
        return $this;
    }



    /**
     * Adds a Where expression
     * @param Exp|string     $column
     * @param Op|string|null $operator      Optional.
     * @param WhereValue     $value         Optional.
     * @param bool           $caseSensitive Optional.
     * @param bool|null      $condition     Optional.
     * @return Query
     */
    public function where(
        Exp|string $column,
        Op|string|null $operator = null,
        mixed $value = null,
        bool $caseSensitive = false,
        ?bool $condition = null,
    ): Query {
        if ($condition !== null && !$condition) {
            return $this;
        }
        $this->whereBuilder->where($column, $operator, $value, $caseSensitive);
        return $this;
    }

    /**
     * Adds an OR Where expression
     * @param Exp|string     $column
     * @param Op|string|null $operator      Optional.
     * @param WhereValue     $value         Optional.
     * @param bool           $caseSensitive Optional.
     * @param bool|null      $condition     Optional.
     * @return Query
     */
    public function orWhere(
        Exp|string $column,
        Op|string|null $operator = null,
        mixed $value = null,
        bool $caseSensitive = false,
        ?bool $condition = null,
    ): Query {
        if ($condition !== null && !$condition) {
            return $this;
        }
        $this->whereBuilder->orWhere($column, $operator, $value, $caseSensitive);
        return $this;
    }

    /**
     * Adds a Where expression if the value is not empty
     * @param Exp|string $column
     * @param Op|string  $operator
     * @param WhereValue $value
     * @param bool|null  $condition     Optional.
     * @param bool       $caseSensitive Optional.
     * @return Query
     */
    public function whereIf(
        Exp|string $column,
        Op|string $operator,
        mixed $value,
        ?bool $condition = null,
        bool $caseSensitive = false,
    ): Query {
        if ($condition === true && $value !== null) {
            $this->where($column, $operator, $value, $caseSensitive);
        } elseif ($condition === null && $value !== null && !Arrays::isEmpty($value)) {
            $this->where($column, $operator, $value, $caseSensitive);
        }
        return $this;
    }

    /**
     * Adds a Where Exists expression
     * @param QueryLike $subQuery
     * @return Query
     */
    public function whereExists(QueryLike $subQuery): Query {
        $this->whereBuilder->whereExists($subQuery->getQuery());
        return $this;
    }

    /**
     * Adds a Where Not Exists expression
     * @param QueryLike $subQuery
     * @return Query
     */
    public function whereNotExists(QueryLike $subQuery): Query {
        $this->whereBuilder->whereExists($subQuery->getQuery(), notExists: true);
        return $this;
    }

    /**
     * Adds a Search expression
     * @param list<string>|string $column
     * @param mixed               $value
     * @param Op|string           $operator        Optional.
     * @param bool                $caseInsensitive Optional.
     * @param bool                $splitValue      Optional.
     * @param string              $splitText       Optional.
     * @param bool                $matchAny        Optional.
     * @return Query
     */
    public function search(
        array|string $column,
        mixed $value,
        Op|string $operator = Op::Like,
        bool $caseInsensitive = true,
        bool $splitValue = false,
        string $splitText = " ",
        bool $matchAny = false,
    ): Query {
        // Only nothing at all is nothing to search: a zero is a value
        if ($value === null || $value === "" || $value === [] || $value === false) {
            return $this;
        }

        $this->whereBuilder->search(
            $column,
            $value,
            $operator,
            $caseInsensitive,
            $splitValue,
            $splitText,
            $matchAny,
        );
        return $this;
    }

    /**
     * Adds a param to the Query
     * @param int|string $param
     * @return Query
     */
    public function addParam(int|string $param): Query {
        $this->whereBuilder->addParam($param);
        return $this;
    }


    /**
     * Adds an Open Parenthesis
     * @return Query
     */
    public function startParen(): Query {
        $this->whereBuilder->startParen();
        return $this;
    }

    /**
     * Adds a Close Parenthesis
     * @return Query
     */
    public function endParen(): Query {
        $this->whereBuilder->endParen();
        return $this;
    }

    /**
     * Adds an And
     * @return Query
     */
    public function and(): Query {
        $this->whereBuilder->and();
        return $this;
    }

    /**
     * Starts an And expression
     * @return Query
     */
    public function startAnd(): Query {
        $this->whereBuilder->startAnd();
        return $this;
    }

    /**
     * Ends an And expression
     * @return Query
     */
    public function endAnd(): Query {
        $this->whereBuilder->endAnd();
        return $this;
    }

    /**
     * Adds an Or
     * @return Query
     */
    public function or(): Query {
        $this->whereBuilder->or();
        return $this;
    }

    /**
     * Starts an Or expression
     * @return Query
     */
    public function startOr(): Query {
        $this->whereBuilder->startOr();
        return $this;
    }

    /**
     * Ends an Or expression
     * @return Query
     */
    public function endOr(): Query {
        $this->whereBuilder->endOr();
        return $this;
    }


    /**
     * Adds a Group By
     * @param string ...$columns
     * @return Query
     */
    public function groupBy(string ...$columns): Query {
        $this->whereBuilder->groupBy(...$columns);
        return $this;
    }

    /**
     * Adds an Order By
     * @param string $column
     * @param bool   $isASC
     * @return Query
     */
    public function orderBy(string $column, bool $isASC): Query {
        $this->whereBuilder->orderBy($column, $isASC);
        return $this;
    }

    /**
     * Adds an Order By of an Expression, which can bind params
     * @param Exp  $expression
     * @param bool $isASC      Optional.
     * @return Query
     */
    public function orderByExp(Exp $expression, bool $isASC = true): Query {
        $this->whereBuilder->orderByExp($expression, $isASC);
        return $this;
    }

    /**
     * Adds a Limit
     * @param int      $from
     * @param int|null $to   Optional.
     * @return Query
     */
    public function limit(int $from, ?int $to = null): Query {
        $this->whereBuilder->limit($from, $to);
        return $this;
    }

    /**
     * Adds a limit using pagination
     * @param int $page   Optional.
     * @param int $amount Optional.
     * @return Query
     */
    public function paginate(int $page = 0, int $amount = 100): Query {
        $from = $page * $amount;
        $to   = $from + $amount - 1;
        return $this->limit($from, $to);
    }

    /**
     * Adds an If Null expression
     * @param int|string $value
     * @return Query
     */
    public function ifNull(int|string $value): Query {
        $this->queryBuilder->setIfNull($value);
        return $this;
    }



    /**
     * Returns true if the Field exists
     * @param string $field
     * @return bool
     */
    public function hasField(string $field): bool {
        return $this->queryBuilder->hasField($field);
    }

    /**
     * Returns the Fields
     * @return Dictionary
     */
    public function getFields(): Dictionary {
        return $this->queryBuilder->getFields();
    }

    /**
     * Returns true if the Query is empty
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->whereBuilder->isEmpty();
    }

    /**
     * Returns true if the Query is not empty
     * @return bool
     */
    public function isNotEmpty(): bool {
        return !$this->isEmpty();
    }

    /**
     * Returns true if the Query is a SELECT query
     * @return bool
     */
    public function isSelect(): bool {
        return $this->mode === QueryMode::Select;
    }

    /**
     * Returns the Query
     * @return Query
     */
    #[\Override]
    public function getQuery(): Query {
        return $this;
    }

    /**
     * Returns the Table Name
     * @return string
     */
    #[\Override]
    public function getTableName(): string {
        return $this->queryBuilder->getTableName();
    }

    /**
     * Returns true if the given Column is in the Query
     * @param string $column
     * @return bool
     */
    public function hasWhereColumn(string $column): bool {
        return $this->whereBuilder->hasColumn($column);
    }

    /**
     * Returns true if there is an Order By
     * @param string|null $order Optional.
     * @return bool
     */
    public function hasOrder(?string $order = null): bool {
        return $this->whereBuilder->hasOrder($order);
    }

    /**
     * Returns true if there is a Group By
     * @param string|null $group Optional.
     * @return bool
     */
    public function hasGroup(?string $group = null): bool {
        return $this->whereBuilder->hasGroup($group);
    }

    /**
     * Returns the Columns
     * @return list<string>
     */
    public function getWhereColumns(): array {
        return $this->whereBuilder->getColumns();
    }

    /**
     * Updates a Column
     * @param string $oldColumn
     * @param string $newColumn
     * @return Query
     */
    public function updateWhereColumn(string $oldColumn, string $newColumn): Query {
        $this->whereBuilder->updateColumn($oldColumn, $newColumn);
        return $this;
    }



    /**
     * Executes a SELECT query returning all the values in a Dictionary
     * @param Database|null $db Optional.
     * @return Dictionary
     */
    public function getAll(?Database $db = null): Dictionary {
        if ($this->mode !== QueryMode::Select) {
            return new Dictionary();
        }

        $db         = Database::getInstance($db);
        $expression = $this->queryBuilder->toSQL();
        $bindings   = $this->queryBuilder->getBindings();
        $data       = $db->getData($expression, $bindings);
        return new Dictionary($data);
    }

    /**
     * Executes a SELECT query returning the first value in a Dictionary
     * @param Database|null $db Optional.
     * @return Dictionary
     */
    #[NotTested("It needs a Database")]
    public function getOne(?Database $db = null): Dictionary {
        return $this->limit(1)->getAll($db)->getFirst();
    }

    /**
     * Executes a SELECT query returning the first value of a Column
     * @param string        $column
     * @param Database|null $db     Optional.
     * @return int
     */
    #[NotTested("It needs a Database")]
    public function getInt(string $column, ?Database $db = null): int {
        return $this->getOne($db)->getInt($column);
    }

    /**
     * Executes a SELECT query returning the first value of a Column
     * @param string        $column
     * @param Database|null $db     Optional.
     * @return string
     */
    #[NotTested("It needs a Database")]
    public function getString(string $column, ?Database $db = null): string {
        return $this->getOne($db)->getString($column);
    }

    /**
     * Executes an INSERT, REPLACE or UPDATE query
     * @param Database|null $db Optional.
     * @return int
     */
    public function execute(?Database $db = null): int {
        if ($this->mode === QueryMode::Select) {
            return 0;
        }

        $db         = Database::getInstance($db);
        $expression = $this->queryBuilder->toSQL();
        $bindings   = $this->queryBuilder->getBindings();
        $result     = $db->execute($expression, $bindings);

        if ($this->mode === QueryMode::Insert) {
            return $result ? $db->getInsertID() : 0;
        }
        return $result ? 1 : 0;
    }

    /**
     * Returns the SQL to add it as an assignment
     * @return Assign
     */
    public function toAssign(): Assign {
        return $this->queryBuilder->toAssign();
    }

    /**
     * Returns the SQL expression of the Query
     * @return string
     */
    public function toSQL(): string {
        return $this->queryBuilder->toSQL();
    }

    /**
     * Returns the SQL expression of the Query for Debugging
     * @return string
     */
    public function toDebugSQL(): string {
        $expression = $this->queryBuilder->toSQL(forDebug: true);
        $bindings   = $this->queryBuilder->getBindings();

        return $this->queryBuilder->interpolateParams($expression, $bindings);
    }

    /**
     * Returns the Bindings
     * @return list<float|int|string>
     */
    public function getBindings(): array {
        return $this->queryBuilder->getBindings();
    }
}
