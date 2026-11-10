<?php
namespace Framework\Database\Query;

use Framework\Core\Configs;
use Framework\Database\SchemaModel;
use Framework\Database\Query\QueryBuilder;
use Framework\Date\Date;
use Framework\Utils\Dictionary;

/**
 * The Modification Builder
 * @phpstan-import-type QueryValue from QueryBuilder
 */
class ModificationBuilder {

    private Query $builder;
    private SchemaModel $schemaModel;


    /**
     * Creates a new Modification instance
     * @param Query       $builder
     * @param SchemaModel $schemaModel
     */
    private function __construct(Query $builder, SchemaModel $schemaModel) {
        $this->builder     = $builder;
        $this->schemaModel = $schemaModel;
    }

    /**
     * Creates a new Insert Modification instance
     * @param SchemaModel $schemaModel
     * @return ModificationBuilder
     */
    public static function insert(SchemaModel $schemaModel): ModificationBuilder {
        $builder = Query::insert($schemaModel->tableName);
        return new ModificationBuilder($builder, $schemaModel);
    }

    /**
     * Creates a new Replace Modification instance
     * @param SchemaModel $schemaModel
     * @return ModificationBuilder
     */
    public static function replace(SchemaModel $schemaModel): ModificationBuilder {
        $builder = Query::replace($schemaModel->tableName);
        return new ModificationBuilder($builder, $schemaModel);
    }

    /**
     * Creates a new Update Modification instance
     * @param SchemaModel $schemaModel
     * @param Query       $query
     * @return ModificationBuilder
     */
    public static function update(
        SchemaModel $schemaModel,
        Query $query,
    ): ModificationBuilder {
        $builder = Query::update($query);
        return new ModificationBuilder($builder, $schemaModel);
    }


    /**
     * Returns the Fields
     * @return Dictionary
     */
    public function getFields(): Dictionary {
        return $this->builder->getFields();
    }

    /**
     * Sets a Field
     * @param string     $key
     * @param QueryValue $value
     * @return ModificationBuilder
     */
    public function setField(string $key, mixed $value): ModificationBuilder {
        $this->builder->set($key, $value);
        return $this;
    }

    /**
     * Adds all the Fields
     * @param array<string,QueryValue> $fields
     * @return ModificationBuilder
     */
    public function addFields(array $fields): ModificationBuilder {
        $this->builder->fields($fields);
        return $this;
    }

    /**
     * Adds the Creation Fields
     * @param int $credentialID Optional.
     * @return ModificationBuilder
     */
    public function addCreation(int $credentialID = 0): ModificationBuilder {
        if ($this->schemaModel->canDelete && !$this->builder->hasField("isDeleted")) {
            $this->builder->set("isDeleted", 0);
        }

        // Only the creation writes the Environment, as an edit of a row copied from another
        // one would take it as its own
        if ($this->schemaModel->hasEnv && !$this->builder->hasField("env")) {
            $this->builder->set("env", Configs::getEnvironment());
        }

        if ($this->schemaModel->canCreate) {
            if ($this->schemaModel->hasTimestamps &&
                !$this->builder->hasField("createdTime")
            ) {
                $this->builder->set("createdTime", Date::now());
            }
            if ($this->schemaModel->hasUsers && $credentialID !== 0) {
                $this->builder->set("createdUser", $credentialID);
            }
        }
        return $this;
    }

    /**
     * Adds the Modification Fields
     * @param int  $credentialID   Optional.
     * @param bool $skipTimestamps Optional.
     * @return ModificationBuilder
     */
    public function addModification(
        int $credentialID = 0,
        bool $skipTimestamps = false,
    ): ModificationBuilder {
        if (!$this->schemaModel->canEdit || $skipTimestamps) {
            return $this;
        }

        if ($this->schemaModel->hasTimestamps &&
            !$this->builder->hasField("modifiedTime")
        ) {
            $this->builder->set("modifiedTime", Date::now());
        }
        if ($this->schemaModel->hasUsers && $credentialID !== 0) {
            $this->builder->set("modifiedUser", $credentialID);
        }
        return $this;
    }



    /**
     * Executes the Modification
     * @return int
     */
    public function execute(): int {
        return $this->builder->execute();
    }
}
