<?php
namespace Framework\Database\Query;

use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;
use Framework\Enum\Enum;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Where Builder
 * @phpstan-type WhereValue Date|Enum|Exp|list<int|string>|int|string|null
 */
class WhereBuilder {

    private string $where = "";

    private bool $addOperator = false;
    private string $nextOperator = "AND";

    /** @var list<string> */
    private array $usedOperators = [];

    private string $limit = "";
    private string $orderBy = "";
    private string $groupBy = "";

    /** @var list<float|int|string> */
    private array $params = [];

    /** @var list<float|int|string> */
    private array $orderParams = [];

    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $groups = [];

    /** @var list<string> */
    private array $orders = [];



    /**
     * Copies the Data from another WhereBuilder instance
     * @param WhereBuilder $builder
     * @return WhereBuilder
     */
    public function copy(WhereBuilder $builder): WhereBuilder {
        $this->where         = $builder->where;
        $this->addOperator   = $builder->addOperator;
        $this->nextOperator  = $builder->nextOperator;
        $this->usedOperators = $builder->usedOperators;

        $this->limit         = $builder->limit;
        $this->orderBy       = $builder->orderBy;
        $this->groupBy       = $builder->groupBy;

        $this->params        = $builder->params;
        $this->orderParams   = $builder->orderParams;
        $this->columns       = $builder->columns;
        $this->groups        = $builder->groups;
        $this->orders        = $builder->orders;
        return $this;
    }

    /**
     * Joins the data from another Where Builder
     * @param WhereBuilder $builder
     * @return WhereBuilder
     */
    public function join(WhereBuilder $builder): WhereBuilder {
        $this->where = Strings::join(
            value:        [ $this->where, $builder->where ],
            glue:         " AND ",
            withoutEmpty: true,
        );

        $this->addOperator   = $builder->addOperator;
        $this->nextOperator  = $builder->nextOperator;
        $this->usedOperators = Arrays::mergeLists(
            $this->usedOperators,
            $builder->usedOperators,
        );

        $this->limit   = $builder->limit;
        $this->orderBy = Strings::join(
            value:        [ $this->orderBy, $builder->orderBy ],
            glue:         ", ",
            withoutEmpty: true,
        );
        $this->groupBy = Strings::join(
            value:        [ $this->groupBy, $builder->groupBy ],
            glue:         ", ",
            withoutEmpty: true,
        );

        $this->params      = Arrays::mergeLists($this->params, $builder->params);
        $this->orderParams = Arrays::mergeLists($this->orderParams, $builder->orderParams);
        $this->columns     = Arrays::mergeLists($this->columns, $builder->columns);
        $this->groups      = Arrays::mergeLists($this->groups, $builder->groups);
        $this->orders      = Arrays::mergeLists($this->orders, $builder->orders);
        return $this;
    }



    /**
     * Adds a Where expression
     * @param Exp|string     $column
     * @param Op|string|null $operator
     * @param WhereValue     $value
     * @param bool           $caseSensitive
     * @return void
     */
    public function where(
        Exp|string $column,
        Op|string|null $operator,
        mixed $value,
        bool $caseSensitive,
    ): void {
        // The params of an Expression are placed before the ones of the value, as the
        // Expression is written into the condition before the value it is compared to
        $expParams = [];
        $isExp     = $column instanceof Exp;
        if ($column instanceof Exp) {
            $expParams = $column->getParams();
            $column    = $column->toSQL();
        }

        // An Expression with nothing to compare to is the whole condition, which is how
        // the ones with no column and operator of their own are written
        if ($operator === null) {
            if (!$isExp) {
                return;
            }

            // The condition names no column of its own, and what the columns are read
            // for is to be matched against the fields of a Model, which its SQL never is
            $prefix       = $this->getWhereOperator();
            $this->where .= "$prefix $column ";
            $this->addParam(...$expParams);
            return;
        }

        // An Expression on the other side is written as it is, so the comparison is
        // against what it says and not against its text bound as a value
        if ($value instanceof Exp) {
            $operator = Op::fromValue($operator);
            if ($operator === Op::None) {
                return;
            }

            $prefix          = $this->getWhereOperator();
            $suffix          = $caseSensitive ? "BINARY" : "";
            $valueSQL        = self::withWildcards($operator, $value->toSQL());
            $this->where    .= "$prefix $column {$operator->toSQL()} $suffix $valueSQL ";
            $this->columns[] = $column;
            $this->addParam(...$expParams);
            $this->addParam(...$value->getParams());
            return;
        }

        if ($value instanceof Date) {
            $value = $value->toTime();
        } elseif ($value instanceof Enum) {
            $value = $value->toString();
        }

        $operator = Op::fromValue($operator);
        $param    = null;
        $binds    = "?";

        switch ($operator) {
        case Op::None:
            return;

        case Op::Equal:
            if (!is_array($value)) {
                $param = $value;
            } elseif (array_is_list($value)) {
                if (count($value) === 1) {
                    $param = $value[0];
                } elseif (count($value) > 1) {
                    $param    = $value;
                    $operator = Op::In;
                    $binds    = $this->createBinds($value);
                }
            }
            break;

        case Op::NotEqual:
            if (!is_array($value)) {
                $param = $value;
            } elseif (array_is_list($value)) {
                if (count($value) === 1) {
                    $param = $value[0];
                } elseif (count($value) > 1) {
                    $param    = $value;
                    $operator = Op::NotIn;
                    $binds    = $this->createBinds($value);
                }
            }
            break;

        case Op::In:
            if (!is_array($value)) {
                $param    = $value;
                $operator = Op::Equal;
            } elseif (array_is_list($value)) {
                if (count($value) === 1) {
                    $param    = $value[0];
                    $operator = Op::Equal;
                } elseif (count($value) > 1) {
                    $param = $value;
                    $binds = $this->createBinds($value);
                }
            }
            break;

        case Op::NotIn:
            if (!is_array($value)) {
                $param    = $value;
                $operator = Op::NotEqual;
            } elseif (array_is_list($value)) {
                if (count($value) === 1) {
                    $param    = $value[0];
                    $operator = Op::NotEqual;
                } elseif (count($value) > 1) {
                    $param = $value;
                    $binds = $this->createBinds($value);
                }
            }
            break;

        case Op::GreaterThan:
        case Op::LessThan:
        case Op::GreaterOrEqual:
        case Op::LessOrEqual:
            $param = $value;
            break;

        case Op::Like:
        case Op::NotLike:
            if (!is_array($value)) {
                $param = Strings::trim($value);
                $param = Strings::toLowerCase($param);
                $param = Strings::addPrefixSuffix($param, "%", "%");
            }
            break;

        case Op::StartsWith:
        case Op::NotStartsWith:
            if (!is_array($value)) {
                $param = Strings::trim($value);
                $param = Strings::toLowerCase($param);
                $param = Strings::addSuffix($param, "%");
            }
            break;

        case Op::EndsWith:
        case Op::NotEndsWith:
            if (!is_array($value)) {
                $param = Strings::trim($value);
                $param = Strings::toLowerCase($param);
                $param = Strings::addPrefix($param, "%");
            }
            break;
        }

        if ($param === null) {
            return;
        }


        $prefix  = $this->getWhereOperator();
        $suffix  = $caseSensitive ? "BINARY" : "";
        $compare = $operator->toSQL();

        $this->where    .= "$prefix $column $compare $suffix $binds ";
        $this->columns[] = $column;

        $this->addParam(...$expParams);
        if (is_array($param)) {
            $this->addParam(...$param);
        } else {
            $this->addParam($param);
        }
    }

    /**
     * Returns the SQL of a value with the wildcards its Operator asks for
     * @param Op     $operator
     * @param string $valueSQL
     * @return string
     */
    private static function withWildcards(Op $operator, string $valueSQL): string {
        // A value is bound with the wildcards already around it, which an Expression
        // never is, so the same ones are put around its SQL instead. Nothing lowers
        // the case here, as the value that is bound is only lowered to meet a column
        // the collation was going to match either way
        return match ($operator) {
            Op::Like,       Op::NotLike       => "CONCAT('%', $valueSQL, '%')",
            Op::StartsWith, Op::NotStartsWith => "CONCAT($valueSQL, '%')",
            Op::EndsWith,   Op::NotEndsWith   => "CONCAT('%', $valueSQL)",
            default                           => $valueSQL,
        };
    }

    /**
     * Adds an OR Where expression
     * @param Exp|string     $column
     * @param Op|string|null $operator
     * @param WhereValue     $value
     * @param bool           $caseSensitive
     * @return void
     */
    public function orWhere(
        Exp|string $column,
        Op|string|null $operator,
        mixed $value,
        bool $caseSensitive,
    ): void {
        // Add an OR before the next expression if needed
        if ($this->addOperator) {
            $this->or();
        }
        $this->where($column, $operator, $value, $caseSensitive);
    }

    /**
     * Adds a Where Exists expression
     * @param Query $subQuery
     * @param bool  $notExists Optional.
     * @return void
     */
    public function whereExists(Query $subQuery, bool $notExists = false): void {
        if ($subQuery->isEmpty() || !$subQuery->isSelect()) {
            return;
        }

        $operator = $this->getWhereOperator();
        $not      = $notExists ? "NOT " : "";
        $sql      = $subQuery->columns("1")->toSQL();

        $this->where .= "$operator {$not}EXISTS ($sql) ";
        $this->addParam(...$subQuery->getBindings());
    }

    /**
     * Returns the Where Operator to be placed before the next expression
     * The operator should be "AND" or "OR"
     * @return string
     */
    private function getWhereOperator(): string {
        $result = "";
        if ($this->addOperator) {
            $result = $this->nextOperator;
        }

        // Always add an Operator in the next expression
        $this->addOperator = true;
        return $result;
    }

    /**
     * Creates a list of question marks for the given array
     * @param list<int|string> $array
     * @return string
     */
    private function createBinds(array $array): string {
        $count = count($array);
        $bind  = [];
        for ($i = 0; $i < $count; $i += 1) {
            $bind[] = "?";
        }
        return "(" . Strings::join($bind, ",") . ")";
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
     * @return void
     */
    public function search(
        array|string $column,
        mixed $value,
        Op|string $operator = Op::Like,
        bool $caseInsensitive = true,
        bool $splitValue = false,
        string $splitText = " ",
        bool $matchAny = false,
    ): void {
        // Prepare the columns
        $columns   = is_array($column) ? $column : [ $column ];
        $multiCols = Arrays::length($columns) > 1;

        // Prepare the values, dropping only the genuinely blank words: a
        // zero is a value someone searches for, and removeEmpty takes it
        $valueParts = [];
        if (is_array($value)) {
            $valueParts = Arrays::toStrings($value);
        } elseif (is_string($value)) {
            $valueParts = $splitValue ? Strings::split($value, $splitText) : [ $value ];
        } else {
            $valueParts = [ Strings::toString($value) ];
        }
        $valueParts = array_values(array_filter($valueParts, fn(string $part) => $part !== ""));

        if ($caseInsensitive) {
            foreach ($valueParts as $index => $valuePart) {
                $valueParts[$index] = Strings::toLowerCase($valuePart);
            }
        }
        $multiParts = Arrays::length($valueParts) > 1;

        // Handle the search
        $isFirst = true;
        if ($multiParts) {
            $this->startParen();
        }
        foreach ($valueParts as $valuePart) {
            if ($multiParts && !$isFirst) {
                if ($matchAny) {
                    $this->or();
                } else {
                    $this->and();
                }
            }
            if ($multiCols) {
                $this->startOr();
            }
            foreach ($columns as $columnSearch) {
                $this->where($columnSearch, $operator, $valuePart, caseSensitive: false);
            }
            if ($multiCols) {
                $this->endOr();
            }
            $isFirst = false;
        }
        if ($multiParts) {
            $this->endParen();
        }
    }

    /**
     * Adds a param to the Query
     * @param float|int|string ...$params
     * @return void
     */
    public function addParam(float|int|string ...$params): void {
        foreach ($params as $param) {
            $this->params[] = $param;
        }
    }


    /**
     * Adds an Open Parenthesis
     * @return void
     */
    public function startParen(): void {
        $operator          = $this->getWhereOperator();
        $this->where      .= "$operator (";
        $this->addOperator = false;
    }

    /**
     * Adds a Close Parenthesis
     * @return void
     */
    public function endParen(): void {
        $this->where      .= ") ";
        $this->addOperator = true;
    }

    /**
     * Adds an And
     * @return void
     */
    public function and(): void {
        $this->where      .= " AND ";
        $this->addOperator = false;
    }

    /**
     * Starts an And expression
     * @return void
     */
    public function startAnd(): void {
        $operator              = $this->getWhereOperator();
        $this->where          .= "$operator (";
        $this->usedOperators[] = $this->nextOperator;
        $this->nextOperator    = "AND";
        $this->addOperator     = false;
    }

    /**
     * Ends an And expression
     * @return void
     */
    public function endAnd(): void {
        $this->endAndOr();
    }

    /**
     * Adds an Or
     * @return void
     */
    public function or(): void {
        $this->where      .= " OR ";
        $this->addOperator = false;
    }

    /**
     * Starts an Or expression
     * @return void
     */
    public function startOr(): void {
        $operator              = $this->getWhereOperator();
        $this->where          .= "$operator (";
        $this->usedOperators[] = $this->nextOperator;
        $this->nextOperator    = "OR";
        $this->addOperator     = false;
    }

    /**
     * Ends an Or expression
     * @return void
     */
    public function endOr(): void {
        $this->endAndOr();
    }

    /**
     * Ends an And o Or expression
     * @return void
     */
    private function endAndOr(): void {
        if (Strings::endsWith($this->where, "AND (")) {
            $this->where = Strings::stripEnd($this->where, "AND (");
        } elseif (Strings::endsWith($this->where, "OR (")) {
            $this->where = Strings::stripEnd($this->where, "OR (");
        } elseif (Strings::endsWith($this->where, "(")) {
            $this->where = Strings::stripEnd($this->where, "(");
        } else {
            $this->where .= ") ";
        }

        $this->nextOperator = array_pop($this->usedOperators) ?? "AND";
        $this->addOperator  = true;
    }



    /**
     * Adds a Group By
     * @param string ...$columns
     * @return void
     */
    public function groupBy(string ...$columns): void {
        foreach ($columns as $column) {
            $prefix         = $this->groupBy !== "" ? "," : "";
            $this->groupBy .= "$prefix $column ";
            $this->groups[] = $column;
        }
    }

    /**
     * Adds an Order By
     * @param string $column
     * @param bool   $isASC
     * @return void
     */
    public function orderBy(string $column, bool $isASC): void {
        if ($column !== "") {
            $prefix         = $this->orderBy !== "" ? "," : "";
            $this->orderBy .= "$prefix $column " . ($isASC ? "ASC" : "DESC");
            $this->orders[] = $column;
        }
    }

    /**
     * Adds an Order By of an Expression
     * @param Exp  $expression
     * @param bool $isASC
     * @return void
     */
    public function orderByExp(Exp $expression, bool $isASC): void {
        $prefix            = $this->orderBy !== "" ? "," : "";
        $this->orderBy    .= "$prefix {$expression->toSQL()} " . ($isASC ? "ASC" : "DESC");
        $this->orderParams = Arrays::mergeLists($this->orderParams, $expression->getParams());
    }

    /**
     * Sets the Limit, removing it when there is nothing to limit
     * @param int      $from
     * @param int|null $to   Optional.
     * @return void
     */
    public function limit(int $from, ?int $to = null): void {
        // With a second value the Limit is a range, and the first page of a single
        // row is one of them, so it can not be taken as no Limit at all
        if ($to !== null) {
            $this->limit = max($from, 0) . ", " . max($to - $from + 1, 1);
        } elseif ($from > 0) {
            $this->limit = (string)$from;
        } else {
            // The last call wins, the way the rest of the Builder behaves
            $this->limit = "";
        }
    }


    /**
     * Returns true if the Query is empty
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->where === "";
    }

    /**
     * Returns true if the given Column is in the Query
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $column): bool {
        return Arrays::contains($this->columns, $column);
    }

    /**
     * Returns true if there is an Order By
     * @param string|null $order Optional.
     * @return bool
     */
    public function hasOrder(?string $order = null): bool {
        if ($order !== null && $order !== "") {
            return Arrays::contains($this->orders, $order);
        }
        return $this->orderBy !== "";
    }

    /**
     * Returns true if there is a Group By
     * @param string|null $group Optional.
     * @return bool
     */
    public function hasGroup(?string $group = null): bool {
        if ($group !== null && $group !== "") {
            return Arrays::contains($this->groups, $group);
        }
        return $this->groupBy !== "";
    }


    /**
     * Returns the Columns
     * @return list<string>
     */
    public function getColumns(): array {
        $result = array_merge($this->columns, $this->groups, $this->orders);
        $result = array_unique($result);
        return array_values($result);
    }

    /**
     * Updates a Column
     * @param string $oldColumn
     * @param string $newColumn
     * @return void
     */
    public function updateColumn(string $oldColumn, string $newColumn): void {
        $this->where   = Strings::replace($this->where, " $oldColumn ", " $newColumn ");
        $this->orderBy = Strings::replace($this->orderBy, " $oldColumn ", " $newColumn ");
        $this->groupBy = Strings::replace($this->groupBy, " $oldColumn ", " $newColumn ");
    }

    /**
     * Returns the complete Query to use with the Database
     * @param bool $addWhere Optional.
     * @return string
     */
    public function toSQL(bool $addWhere = true): string {
        $expression = "";

        // A group that was opened and closed with nothing in it takes its own
        // opening back out, and what it leaves behind is only whitespace
        if (Strings::trim($this->where) !== "") {
            $where       = Strings::stripStart($this->where, "AND ");
            $expression .= ($addWhere ? "WHERE " : "AND ") . $where;
        }
        if ($this->groupBy !== "") {
            $expression .= " GROUP BY {$this->groupBy}";
        }
        if ($this->orderBy !== "") {
            $expression .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limit !== "") {
            $expression .= " LIMIT {$this->limit}";
        }
        return Strings::replacePattern($expression, "!\s+!", " ");
    }

    /**
     * Returns the Params
     * @return list<float|int|string>
     */
    public function getParams(): array {
        // The Order By is written after the Where, so its params go after them too
        return Arrays::mergeLists($this->params, $this->orderParams);
    }
}
