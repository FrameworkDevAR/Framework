<?php
namespace Framework\Database;

use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Validate;
use Framework\Database\Model\Expression;
use Framework\Database\Model\Virtual;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Count;
use Framework\Database\Model\Relation;
use Framework\Database\Model\SubRequest;
use Framework\Database\Model\Index;
use Framework\Database\Status\State;
use Framework\Date\Type\DateType;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Schema Model
 */
class SchemaModel {

    public bool   $isEmpty        = false;
    public string $name           = "";
    public string $tableName      = "";
    public string $fantasyName    = "";
    public string $description    = "";
    public string $path           = "";
    public string $namespace      = "";
    public bool   $fromFramework  = false;


    // Special flags
    public bool $hasUsers         = false;
    public bool $hasTimestamps    = false;
    public bool $hasStatus        = false;
    public bool $canCreate        = false;
    public bool $canEdit          = false;
    public bool $canDelete        = false;
    public bool $skipList         = false;


    // Subclass Names
    public string $entityClass    = "";
    public string $columnClass    = "";
    public string $queryClass     = "";
    public string $statusClass    = "";
    public string $requestClass   = "";


    // Main column data
    public bool      $hasID       = false;
    public bool      $hasAutoInc  = false;
    public string    $idName      = "";
    public string    $idDbName    = "";
    public FieldType $idType      = FieldType::String;
    public string    $idEnumClass = "";

    // Position column data
    public bool   $hasPositions   = false;
    public string $positionName   = "";
    public int    $minPosition    = 0;


    /** @var list<Validate> */
    public array $validates       = [];

    /** @var list<Field> */
    public array $fields          = [];

    /** @var list<Field> */
    public array $mainFields      = [];

    /** @var list<Field> */
    public array $extraFields     = [];

    /** @var list<Virtual> */
    public array $virtualFields   = [];

    /** @var list<Requested> */
    public array $requestedFields = [];

    /** @var list<Expression> */
    public array $expressions     = [];

    /** @var list<Count> */
    public array $counts          = [];

    /** @var list<Relation> */
    public array $relations       = [];

    /** @var list<SubRequest> */
    public array $subRequests     = [];

    /** @var list<Index> */
    public array $indexes         = [];

    /** @var list<State> */
    public array $states          = [];



    /**
     * The Schema Model
     * @param string           $name            Optional.
     * @param string           $fantasyName     Optional.
     * @param string           $description     Optional.
     * @param string           $path            Optional.
     * @param string           $namespace       Optional.
     * @param bool             $fromFramework   Optional.
     * @param bool             $hasUsers        Optional.
     * @param bool             $hasTimestamps   Optional.
     * @param bool             $hasStatus       Optional.
     * @param bool             $canCreate       Optional.
     * @param bool             $canEdit         Optional.
     * @param bool             $canDelete       Optional.
     * @param bool             $skipList        Optional.
     * @param list<Validate>   $validates       Optional.
     * @param list<Field>      $mainFields      Optional.
     * @param list<Virtual>    $virtualFields   Optional.
     * @param list<Requested>  $requestedFields Optional.
     * @param list<Expression> $expressions     Optional.
     * @param list<Count>      $counts          Optional.
     * @param list<Relation>   $relations       Optional.
     * @param list<SubRequest> $subRequests     Optional.
     * @param list<Index>      $indexes         Optional.
     * @param list<State>      $states          Optional.
     */
    public function __construct(
        string $name = "",
        string $fantasyName = "",
        string $description = "",
        string $path = "",
        string $namespace = "",
        bool $fromFramework = false,
        bool $hasUsers = false,
        bool $hasTimestamps = false,
        bool $hasStatus = false,
        bool $canCreate = false,
        bool $canEdit = false,
        bool $canDelete = false,
        bool $skipList = false,
        array $validates = [],
        array $mainFields = [],
        array $virtualFields = [],
        array $requestedFields = [],
        array $expressions = [],
        array $counts = [],
        array $relations = [],
        array $subRequests = [],
        array $indexes = [],
        array $states = [],
    ) {
        $this->isEmpty         = count($mainFields) === 0;

        $this->name            = $name;
        $this->fantasyName     = $fantasyName;
        $this->description     = $description;
        $this->tableName       = self::getDbTableName($name);
        $this->path            = $path;
        $this->namespace       = $namespace;
        $this->fromFramework   = $fromFramework;

        $this->hasUsers        = $hasUsers;
        $this->hasTimestamps   = $hasTimestamps;
        $this->hasStatus       = $hasStatus;
        $this->canCreate       = $canCreate;
        $this->canEdit         = $canEdit;
        $this->canDelete       = $canDelete;
        $this->skipList        = $skipList;

        $this->validates       = $validates;
        $this->mainFields      = $mainFields;
        $this->virtualFields   = $virtualFields;
        $this->requestedFields = $requestedFields;
        $this->expressions     = $expressions;
        $this->counts          = $counts;
        $this->relations       = $relations;
        $this->subRequests     = $subRequests;
        $this->indexes         = $indexes;
        $this->states          = $states;

        $this->entityClass     = "{$this->name}Entity";
        $this->columnClass     = "{$this->name}Column";
        $this->queryClass      = "{$this->name}Query";
        $this->statusClass     = "{$this->name}Status";
        $this->requestClass    = "{$this->name}Request";

        $this->setExtraFields();
        $this->setIDField();
        $this->setPositionField();

        $this->fields = array_merge(
            $this->mainFields,
            $this->extraFields,
        );
    }

    /**
     * Sets the DB Names of the Columns of the Indexes, and keeps the ones every column
     * of which is a Field. The others are returned, by the name of the Index. Only the
     * Factory needs them, as an Index is for the table and never for a Query
     * @return array<string,list<string>>
     */
    public function setIndexColumns(): array {
        $result  = [];
        $indexes = [];

        // An Index naming a column the table has not would break the table, so
        // it is left out rather than written
        foreach ($this->indexes as $index) {
            $missing = $index->setDbColumns($this->fields);
            if (count($missing) > 0) {
                $result[$index->name] = $missing;
            } else {
                $indexes[] = $index;
            }
        }

        $this->indexes = $indexes;
        return $result;
    }

    /**
     * Sets extra fields based on the model attributes
     * @return void
     */
    private function setExtraFields(): void {
        if ($this->hasStatus) {
            $this->extraFields[] = Field::create(
                type:     FieldType::String,
                name:     "status",
                isKey:    true,
                isStatus: true,
            );
        }

        if ($this->canCreate && $this->hasTimestamps) {
            $this->extraFields[] = Field::create(
                type: FieldType::Date,
                name: "createdTime",
            );
        }
        if ($this->canCreate && $this->hasUsers) {
            $this->extraFields[] = Field::create(
                type: FieldType::Number,
                name: "createdUser",
            );
        }

        if ($this->canEdit && $this->hasTimestamps) {
            $this->extraFields[] = Field::create(
                type: FieldType::Date,
                name: "modifiedTime",
            );
        }
        if ($this->canEdit && $this->hasUsers) {
            $this->extraFields[] = Field::create(
                type: FieldType::Number,
                name: "modifiedUser",
            );
        }

        if ($this->canDelete) {
            $this->extraFields[] = Field::create(
                type: FieldType::Boolean,
                name: "isDeleted",
            );
        }
    }

    /**
     * Sets the ID Field of the Model
     * @return void
     */
    public function setIDField(): void {
        foreach ($this->mainFields as $field) {
            if ($field->isID) {
                $this->hasID       = true;
                $this->hasAutoInc  = $field->isAutoInc();
                $this->idName      = $field->name;
                $this->idDbName    = $field->dbName;
                $this->idType      = $field->type;
                $this->idEnumClass = $field->enumClass;
                break;
            }
        }
    }

    /**
     * Sets the Position Field of the Model
     * @return void
     */
    public function setPositionField(): void {
        foreach ($this->mainFields as $field) {
            if ($field->isPosition) {
                $this->hasPositions = true;
                $this->positionName = $field->name;
                $this->minPosition  = $field->minPosition;
                break;
            }
        }
    }



    /**
     * Returns the Fields of the Model
     * @param bool $withTimestamps
     * @param bool $withDeleted
     * @return list<Field>
     */
    public function getFields(bool $withTimestamps, bool $withDeleted): array {
        $result = [];
        foreach ($this->fields as $field) {
            $fieldName = $field->dbName;
            if (!$withTimestamps &&
                ($fieldName === "createdTime" || $fieldName === "modifiedTime")
            ) {
                continue;
            }
            if (!$withDeleted && $fieldName === "isDeleted") {
                continue;
            }
            $result[] = $field;
        }
        return $result;
    }

    /**
     * Returns all the Field Names of the Model
     * @return list<string>
     */
    public function getFieldNames(): array {
        $result = [];
        foreach ($this->fields as $field) {
            $result[] = $field->name;
        }
        foreach ($this->expressions as $expression) {
            $result[] = $expression->name;
        }
        foreach ($this->virtualFields as $virtual) {
            $result[] = $virtual->name;
        }
        foreach ($this->expressions as $expression) {
            $result[] = $expression->name;
        }
        foreach ($this->counts as $count) {
            $result[] = $count->name;
        }
        foreach ($this->relations as $relation) {
            foreach ($relation->fields as $field) {
                $result[] = $field->prefixName;
            }
        }
        return $result;
    }

    /**
     * Returns true if there is an Encrypt Field in the Model
     * @return bool
     */
    public function hasEncrypt(): bool {
        foreach ($this->mainFields as $field) {
            if ($field->type === FieldType::Encrypt) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if there is a Requested Field with the given name
     * @param string $fieldName
     * @return bool
     */
    public function isRequested(string $fieldName): bool {
        foreach ($this->requestedFields as $field) {
            if ($field->isField && $field->name === $fieldName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the Key adding the table as the prefix
     * @param string $key
     * @return string
     */
    public function getKey(string $key): string {
        if (!Strings::contains($key, ".")) {
            $mainKey = $this->tableName;
            return "{$mainKey}.{$key}";
        }
        return $key;
    }



    /**
     * Returns the Build Data for the Schema Builder
     * @param string $name
     * @return list<array{params:string,fields:list<array<string,mixed>>}>
     */
    public function toBuildData(string $name): array {
        $result = [];
        switch ($name) {
        case "mainFields":
            foreach ($this->mainFields as $field) {
                $result[] = $this->generateBuildData($field->toBuildData(), withKey: true);
            }
            break;
        case "expressions":
            foreach ($this->expressions as $expression) {
                $result[] = $this->generateBuildData($expression->toBuildData(), withKey: false);
            }
            break;
        case "counts":
            foreach ($this->counts as $count) {
                $result[] = $this->generateBuildData($count->toBuildData(), withKey: false);
            }
            break;
        case "relations":
            foreach ($this->relations as $relation) {
                $result[] = $this->generateBuildData($relation->toBuildData(), withKey: false);
            }
            break;
        case "subRequests":
            foreach ($this->subRequests as $subRequest) {
                $result[] = $this->generateBuildData($subRequest->toBuildData(), withKey: false);
            }
            break;
        default:
        }
        return $result;
    }

    /**
     * Returns the Build Data for the Schema Builder
     * @param array<string,mixed> $data
     * @param bool                $withKey
     * @return array{params:string,fields:list<array<string,mixed>>}
     */
    private function generateBuildData(array $data, bool $withKey): array {
        $params = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $text   = "";
            $addKey = $withKey;
            if ($value instanceof FieldType) {
                $text = "FieldType::{$value->toString()}";
            } elseif ($value instanceof DateType) {
                $text = "DateType::{$value->toString()}";
            } elseif (is_string($value) && Strings::endsWith($value, "Schema")) {
                $text = "{$value}::getModel()";
            } elseif (is_string($value)) {
                $text = "\"$value\"";
            } elseif (is_bool($value)) {
                $text   = $value ? "true" : "false";
                $addKey = true;
            } elseif (is_numeric($value)) {
                $text = $value;
            }
            $params[] = $addKey ? "$key: $text" : $text;
        }

        $fields = [];
        if (isset($data["fields"]) && is_array($data["fields"])) {
            foreach ($data["fields"] as $value) {
                $value    = Arrays::toStringMixedMap($value);
                $fields[] = $this->generateBuildData($value, withKey: true);
            }
        }

        return [
            "params"    => Strings::join($params, ", "),
            "paramList" => $params,
            "fields"    => $fields,
        ];
    }

    /**
     * Returns the Data for the Schema JSON
     * @return array<string,mixed>
     */
    public function toSchemaJSON(): array {
        $result = [
            "description" => $this->description,
            "fields"      => [],
            "foreigns"    => [],
        ];
        $relationNames = [];

        // Add all the fields including the extra fields
        foreach ($this->fields as $field) {
            $result["fields"][] = $field->toSchemaJSON();
        }

        // Add all the relations as foreigns
        foreach ($this->relations as $relation) {
            if ($relation->relationModel !== null) {
                $relationName         = $relation->getName($relationNames);
                $relationNames[]      = $relationName;
                $result["foreigns"][] = $relation->toSchemaJSON($relationName);
            }
        }

        // Add the main fields that belong to a relation as foreigns
        foreach ($this->mainFields as $field) {
            if ($field->belongsTo !== "" && !Arrays::contains($relationNames, $field->dbName)) {
                $result["foreigns"][] = $field->toSchemaForeign();
            }
        }

        return $result;
    }



    /**
     * Gets the name of the model without the class stuff
     * @param string|null $modelName
     * @return string
     */
    public static function getBaseModelName(?string $modelName): string {
        if ($modelName === null) {
            return "";
        }

        $result = Strings::stripEnd($modelName, "Model");
        if (Strings::contains($result, "\\")) {
            $result = Strings::substringAfter($result, "\\");
        }
        return $result;
    }

    /**
     * Gets the name of the table for the Database
     * @param string $modelName
     * @return string
     */
    public static function getDbTableName(string $modelName): string {
        return Strings::toSnakeCase($modelName);
    }

    /**
     * Generates the name of the field for the Database
     * @param string $name
     * @return string
     */
    public static function getDbFieldName(string $name): string {
        if (Strings::endsWith($name, "ID") && !Strings::isConstantCase($name)) {
            $name = Strings::replace($name, "ID", "Id");
            $name = Strings::toConstantCase($name);
        }
        return $name;
    }
}
