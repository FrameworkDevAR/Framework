<?php
namespace Framework\Database\Query;

use Framework\Database\SchemaModel;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Relation;
use Framework\Database\Query\Query;
use Framework\Database\Query\QueryLike;
use Framework\System\Config;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

/**
 * The Selection Builder
 */
class SelectionBuilder {

    private Query $builder;
    private SchemaModel $schemaModel;
    private Dictionary $request;



    /**
     * Creates a new Selection instance
     * @param SchemaModel $schemaModel
     * @param QueryLike   $query
     */
    private function __construct(SchemaModel $schemaModel, QueryLike $query) {
        $this->builder     = Query::select($query);
        $this->schemaModel = $schemaModel;
        $this->request     = new Dictionary();
    }

    /**
     * Creates a new Selection instance
     * @param SchemaModel $schemaModel
     * @param QueryLike   $query
     * @return SelectionBuilder
     */
    public static function create(SchemaModel $schemaModel, QueryLike $query): SelectionBuilder {
        return new SelectionBuilder($schemaModel, $query);
    }



    /**
     * Adds the Fields to the Selects
     * @param bool $decrypted Optional.
     * @return SelectionBuilder
     */
    public function addFields(bool $decrypted = false): SelectionBuilder {
        $masterKey = Config::getDbKey();
        $mainKey   = $this->schemaModel->tableName;

        if ($this->schemaModel->hasID) {
            $this->builder->addSelect("$mainKey.{$this->schemaModel->idDbName}", as: "id");
        }
        foreach ($this->schemaModel->fields as $field) {
            $fieldName = "$mainKey.{$field->name}";
            if ($decrypted && $field->type === FieldType::Encrypt) {
                $this->builder->addSelect(
                    "CAST(AES_DECRYPT($fieldName, '$masterKey') AS CHAR(255)) {$field->name}Decrypt"
                );
            } elseif ($field->dbName !== $field->name) {
                $this->builder->addSelect("$mainKey.$field->dbName", as: $field->name);
            } else {
                $this->builder->addSelect($fieldName);
            }
        }
        return $this;
    }

    /**
     * Adds the Expressions to the Selects
     * @return SelectionBuilder
     */
    public function addExpressions(): SelectionBuilder {
        foreach ($this->schemaModel->expressions as $expression) {
            $this->builder->addSelect("({$expression->expression})", as: $expression->name);
        }
        return $this;
    }

    /**
     * Adds extra Selects
     * @param list<string>|string $selects
     * @param bool                $addMainKey Optional.
     * @return SelectionBuilder
     */
    public function addSelects(array|string $selects, bool $addMainKey = false): SelectionBuilder {
        $selects = Arrays::toStrings($selects, withoutEmpty: true);
        if (Arrays::isEmpty($selects)) {
            return $this;
        }

        foreach ($selects as $select) {
            if ($addMainKey) {
                $this->builder->addSelect($this->schemaModel->getKey($select));
            } else {
                $this->builder->addSelect($select);
            }
        }
        return $this;
    }

    /**
     * Adds the Joins
     * @param list<string> $extraJoins  Optional.
     * @param bool         $withSelects Optional.
     * @param bool         $onlyUsed    Optional.
     * @return SelectionBuilder
     */
    public function addJoins(
        array $extraJoins = [],
        bool $withSelects = true,
        bool $onlyUsed = false,
    ): SelectionBuilder {
        $relations = $onlyUsed ? $this->getUsedRelations() : $this->schemaModel->relations;
        foreach ($relations as $relation) {
            $this->builder->addJoin($relation->getExpression());

            if ($withSelects) {
                $tableName = $relation->getDbTableName();
                foreach ($relation->fields as $field) {
                    $this->builder->addSelect(
                        "$tableName.{$field->dbName}",
                        as: $field->prefixName,
                    );
                }
            }
        }
        foreach ($extraJoins as $extraJoin) {
            $this->builder->addJoin($extraJoin);
        }
        return $this;
    }

    /**
     * Returns the Relations the conditions use, with the ones those are joined through
     * @return list<Relation>
     */
    private function getUsedRelations(): array {
        // A condition can name a column on its own, and only the table keys put it on
        // the table that owns it, so they have to be set before anything is read. A
        // table that is named nowhere in the statement can then be left out without
        // changing what the query returns
        $this->setTableKeys();
        $sql    = $this->builder->toSQL();
        $tables = [];
        foreach ($this->builder->getWhereColumns() as $column) {
            if (Strings::contains($column, ".")) {
                $tables[] = Strings::substringBefore($column, ".");
            }
        }

        $result = [];
        $added  = true;
        while ($added) {
            $added = false;
            foreach ($this->schemaModel->relations as $relation) {
                $tableName = $relation->getDbTableName();
                if (isset($result[$tableName])) {
                    continue;
                }

                // A Join whose On has a parameter is always added, as the values that the
                // caller gave with addParam are bound by position, and leaving the Join
                // out takes its parameter away and moves every other one
                $isUsed = Arrays::contains($tables, $tableName)
                    || Strings::contains($sql, "$tableName.")
                    || Strings::contains($relation->getExpression(), "?");

                foreach ($result as $usedRelation) {
                    if (SchemaModel::getDbTableName($usedRelation->ownerModelName) === $tableName) {
                        $isUsed = true;
                    }
                }
                if ($isUsed) {
                    $result[$tableName] = $relation;
                    $added              = true;
                }
            }
        }

        // The Joins keep the order of the Model, so a table is joined after the one it needs
        return array_values(array_filter(
            $this->schemaModel->relations,
            fn (Relation $relation) => isset($result[$relation->getDbTableName()]),
        ));
    }

    /**
     * Adds the Counts
     * @return SelectionBuilder
     */
    public function addCounts(): SelectionBuilder {
        foreach ($this->schemaModel->counts as $count) {
            $expression = $count->getExpression($this->schemaModel->tableName);
            $this->builder->addSelect($expression, as: $count->name);
        }
        return $this;
    }



    /**
     * Sets the Table Keys to the condition
     * @return void
     */
    private function setTableKeys(): void {
        $columns = $this->builder->getWhereColumns();
        $mainKey = $this->schemaModel->tableName;

        foreach ($columns as $column) {
            $found = false;
            foreach ($this->schemaModel->fields as $field) {
                if ($column === $field->dbName) {
                    $this->builder->updateWhereColumn($column, "$mainKey.{$field->dbName}");
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                foreach ($this->schemaModel->relations as $relation) {
                    foreach ($relation->fields as $field) {
                        if ($column === $field->dbName) {
                            $tableName = $relation->getDbTableName();
                            $this->builder->updateWhereColumn(
                                $column,
                                "$tableName.{$field->dbName}",
                            );
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if (!$found) {
                foreach ($this->schemaModel->expressions as $expression) {
                    if ($column === $expression->name) {
                        $this->builder->updateWhereColumn($column, "({$expression->expression})");
                        $found = true;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Converts the Query to an SQL Expression for Debugging
     * @return string
     */
    public function toDebugSQL(): string {
        $this->setTableKeys();
        return $this->builder->toDebugSQL();
    }

    /**
     * Converts the Query to an SQL Expression for Debugging
     * @return list<float|int|string>
     */
    public function getBindings(): array {
        return $this->builder->getBindings();
    }

    /**
     * Does a Request to the Query
     * @return SelectionBuilder
     */
    public function request(): SelectionBuilder {
        $this->setTableKeys();
        $this->request = $this->builder->getAll();
        return $this;
    }

    /**
     * Returns the Request Result
     * @return Dictionary
     */
    public function getResult(): Dictionary {
        return $this->request;
    }

    /**
     * Generates the Result from the Request
     * @param list<string>|string|null $extras Optional.
     * @return list<array<string,mixed>>
     */
    public function resolve(array|string|null $extras = null): array {
        $result = [];

        foreach ($this->request as $row) {
            $rowData = $row->toStringsMap();

            $fields = [];
            if ($this->schemaModel->hasID) {
                if ($row->has("id")) {
                    $fields["id"] = $row->get("id");
                } elseif ($row->has($this->schemaModel->idDbName)) {
                    $fields["id"] = $row->get($this->schemaModel->idDbName);
                } elseif ($row->has($this->schemaModel->idName)) {
                    $fields["id"] = $row->get($this->schemaModel->idName);
                }
            }

            // Parse the Fields
            foreach ($this->schemaModel->fields as $field) {
                $values = $field->toValues($rowData);
                $fields = array_merge($fields, $values);
            }

            // Parse the Expressions
            foreach ($this->schemaModel->expressions as $expression) {
                $values = $expression->toValues($rowData);
                $fields = array_merge($fields, $values);
            }

            // Parse the Relations
            foreach ($this->schemaModel->relations as $relation) {
                $values = $relation->toValues($rowData);
                $fields = array_merge($fields, $values);
            }

            // Parse the Counts
            foreach ($this->schemaModel->counts as $count) {
                $fields[$count->name] = $count->getValue($rowData);
            }

            // Parse the Extras
            if (!Arrays::isEmpty($extras)) {
                $extras = Arrays::toStrings($extras);
                foreach ($extras as $extra) {
                    if ($row->has($extra)) {
                        $fields[$extra] = $row->get($extra);
                    }
                }
            }

            $result[] = $fields;
        }
        return $result;
    }
}
