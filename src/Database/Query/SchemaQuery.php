<?php
namespace Framework\Database\Query;

use Framework\Database\Query\Exp;
use Framework\Database\Query\Query;
use Framework\Database\Query\QueryLike;

/**
 * The Schema Query
 */
class SchemaQuery implements QueryLike {

    protected Query $query;

    protected string $tableName;
    protected string $idDbName;



    /**
     * Creates a new SchemaQuery instance
     * @param string $tableName
     * @param string $idDbName  Optional.
     */
    protected function __construct(string $tableName, string $idDbName = "") {
        $this->query     = Query::select($tableName);
        $this->tableName = $tableName;
        $this->idDbName  = $idDbName;
    }

    /**
     * Returns the Query
     * @return Query
     */
    #[\Override]
    public function getQuery(): Query {
        return $this->query;
    }

    /**
     * Returns the Table Name
     * @return string
     */
    #[\Override]
    public function getTableName(): string {
        return $this->tableName;
    }

    /**
     * Returns the ID DB Name
     * @return string
     */
    public function getIDDbName(): string {
        return $this->idDbName;
    }

    /**
     * Returns the Debug SQL
     * @return string
     */
    public function toDebugSQL(): string {
        return $this->query->toDebugSQL();
    }



    /**
     * Returns true if the Query is empty
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->query->isEmpty();
    }

    /**
     * Returns true if the Query is not empty
     * @return bool
     */
    public function isNotEmpty(): bool {
        return $this->query->isNotEmpty();
    }



    /**
     * Adds a Join to the Query
     * @param SchemaQuery|string $queryOrTable
     * @param string             $as           Optional.
     * @param string             $on           Optional.
     * @param string             $type         Optional.
     * @return SchemaQuery
     */
    public function join(
        SchemaQuery|string $queryOrTable,
        string $as = "",
        string $on = "",
        string $type = "LEFT",
    ): SchemaQuery {
        $this->query->join($queryOrTable, $as, $on, $type);
        return $this;
    }

    /**
     * Adds a param to the Query
     * @param int|string $param
     * @return SchemaQuery
     */
    public function addParam(int|string $param): SchemaQuery {
        $this->query->addParam($param);
        return $this;
    }

    /**
     * Adds an Exists expression
     * @param QueryLike $subQuery
     * @return SchemaQuery
     */
    public function whereExists(QueryLike $subQuery): SchemaQuery {
        $this->query->whereExists($subQuery);
        return $this;
    }

    /**
     * Adds a Not Exists expression
     * @param QueryLike $subQuery
     * @return SchemaQuery
     */
    public function whereNotExists(QueryLike $subQuery): SchemaQuery {
        $this->query->whereNotExists($subQuery);
        return $this;
    }



    /**
     * Adds an Open Parenthesis
     * @return void
     */
    public function startParen(): void {
        $this->query->startParen();
    }

    /**
     * Adds a Close Parenthesis
     * @return void
     */
    public function endParen(): void {
        $this->query->endParen();
    }

    /**
     * Adds an And
     * @return void
     */
    public function and(): void {
        $this->query->and();
    }

    /**
     * Starts an And expression
     * @return void
     */
    public function startAnd(): void {
        $this->query->startAnd();
    }

    /**
     * Ends an And expression
     * @return void
     */
    public function endAnd(): void {
        $this->query->endAnd();
    }

    /**
     * Adds an Or
     * @return void
     */
    public function or(): void {
        $this->query->or();
    }

    /**
     * Starts an Or expression
     * @return void
     */
    public function startOr(): void {
        $this->query->startOr();
    }

    /**
     * Ends an Or expression
     * @return void
     */
    public function endOr(): void {
        $this->query->endOr();
    }



    /**
     * Adds an Order By of an Expression, which can bind params
     * @param Exp  $expression
     * @param bool $isASC      Optional.
     * @return void
     */
    public function orderByExp(Exp $expression, bool $isASC = true): void {
        $this->query->orderByExp($expression, $isASC);
    }

    /**
     * Adds a Limit
     * @param int      $from
     * @param int|null $to   Optional.
     * @return void
     */
    public function limit(int $from, ?int $to = null): void {
        $this->query->limit($from, $to);
    }

    /**
     * Adds a limit using pagination
     * @param int $page   Optional.
     * @param int $amount Optional.
     * @return void
     */
    public function paginate(int $page = 0, int $amount = 100): void {
        $this->query->paginate($page, $amount);
    }
}
