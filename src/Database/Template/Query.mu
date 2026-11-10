<?php
namespace {{namespace}};

use {{namespace}}\{{columnClass}};{{#imports}}
use {{.}};{{/imports}}

use Framework\Database\Query\SchemaQuery;{{#idDbName}}
use Framework\Database\Query\QueryLike;{{/idDbName}}
use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;{{#queries}}
use Framework\Database\Where\{{.}};{{/queries}}{{#idDbName}}
use Framework\Utils\Strings;{{/idDbName}}

/**
 * The {{name}} Query
 */
class {{queryClass}} extends SchemaQuery {
{{#properties}}

    // {{value}}
    public {{type}} ${{name}};
{{/properties}}



    /**
     * Creates a new {{queryClass}} instance
     */
    public function __construct() {
        parent::__construct("{{tableName}}", "{{idDbName}}");

        {{#properties}}
        $this->{{propName}} = new {{type}}($this->query, "{{value}}");
        {{/properties}}
    }

    /**
     * Adds a where expression
     * @param {{columnClass}}|Exp $column
     * @param Op|null $operator Optional.
     * @param Exp|list<int|string>|int|string|null $value Optional.
     * @param bool $caseSensitive Optional.
     * @param bool|null $condition Optional.
     * @return {{queryClass}}
     */
    public function where(
        {{columnClass}}|Exp $column,
        ?Op $operator = null,
        Exp|array|int|string|null $value = null,
        bool $caseSensitive = false,
        ?bool $condition = null,
    ): {{queryClass}} {
        if ($column instanceof Exp) {
            $this->query->where($column, $operator, $value, $caseSensitive, $condition);
        } elseif ($column !== {{columnClass}}::None) {
            $this->query->where($column->name(), $operator, $value, $caseSensitive, $condition);
        }
        return $this;
    }

    /**
     * Adds a Search expression
     * @param list<{{columnClass}}> $column
     * @param mixed $value
     * @param Op $operator Optional.
     * @param bool $caseInsensitive Optional.
     * @param bool $splitValue Optional.
     * @param string $splitText Optional.
     * @param bool $matchAny Optional.
     * @return {{queryClass}}
     */
    public function search(
        array $column,
        mixed $value,
        Op $operator = Op::Like,
        bool $caseInsensitive = true,
        bool $splitValue = false,
        string $splitText = " ",
        bool $matchAny = false,
    ): {{queryClass}} {
        $columns = [];
        foreach ($column as $col) {
            $columns[] = $col->name();
        }
        $this->query->search($columns, $value, $operator, $caseInsensitive, $splitValue, $splitText, $matchAny);
        return $this;
    }
{{#idDbName}}

    /**
     * Adds an Exists expression
     * @param QueryLike $subQuery
     * @return {{queryClass}}
     */
    public function exists(QueryLike $subQuery): {{queryClass}} {
        $query     = $subQuery->getQuery();
        $tableName = $subQuery->getTableName();
        $joinWith  = "{$this->tableName}.{$this->idDbName}";
        if (!Strings::contains($query->toSQL(), $joinWith)) {
            $query->where(
                "{$tableName}.{$this->idDbName}",
                Op::Equal,
                Exp::column($joinWith),
            );
        }
        $this->query->whereExists($subQuery);
        return $this;
    }

    /**
     * Adds a Not Exists expression
     * @param QueryLike $subQuery
     * @return {{queryClass}}
     */
    public function notExists(QueryLike $subQuery): {{queryClass}} {
        $query     = $subQuery->getQuery();
        $tableName = $subQuery->getTableName();
        $joinWith  = "{$this->tableName}.{$this->idDbName}";
        if (!Strings::contains($query->toSQL(), $joinWith)) {
            $query->where(
                "{$tableName}.{$this->idDbName}",
                Op::Equal,
                Exp::column($joinWith),
            );
        }
        $this->query->whereNotExists($subQuery);
        return $this;
    }
{{/idDbName}}
{{#hasEnv}}

    /**
     * Keeps only the rows whose work can run in the current Environment
     * @return {{queryClass}}
     */
    public function inCurrentEnv(): {{queryClass}} {
        if (!Configs::runsEveryEnv()) {
            $this->env->equal(Configs::getEnvironment());
        }
        return $this;
    }
{{/hasEnv}}
}
