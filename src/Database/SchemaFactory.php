<?php
namespace Framework\Database;

use Framework\Analysis\Attr\NotTested;
use Framework\Discovery\Discovery;
use Framework\Discovery\Package;
use Framework\Discovery\Type\DiscoveryClass;
use Framework\Database\SchemaModel;
use Framework\Database\Model\Model;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Model\Validate;
use Framework\Database\Model\Virtual;
use Framework\Database\Model\Requested;
use Framework\Database\Model\Expression;
use Framework\Database\Model\Count;
use Framework\Database\Model\Relation;
use Framework\Database\Model\SubRequest;
use Framework\Database\Model\Index;
use Framework\Database\Status\Status;
use Framework\Database\Status\State;
use Framework\File\Storage;
use Framework\Enum\Enum;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

use Throwable;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

/**
 * The Schema Factory
 */
class SchemaFactory {

    /**
     * Returns all the Schema Models
     * @return list<SchemaModel>
     */
    public static function getData(): array {
        $frameModels = self::buildData(forFramework: true);
        $appModels   = self::buildData(forFramework: false);
        return self::mergeModels($frameModels, $appModels);
    }

    /**
     * Merges the Models of the App over the ones of the Framework
     *
     * A Model of the App that extends one of the Framework is the same table,
     * so the two have to become one. Kept apart, the migration ran the
     * Framework one first and dropped the columns the App had added, only for
     * the App one to add them back a moment later.
     * @param list<SchemaModel> $frameModels
     * @param list<SchemaModel> $appModels
     * @return list<SchemaModel>
     */
    public static function mergeModels(array $frameModels, array $appModels): array {
        $result = [];

        foreach ($frameModels as $schemaModel) {
            $result[$schemaModel->name] = $schemaModel;
        }

        // The App wins, and keeps the place of the Model it replaces
        foreach ($appModels as $schemaModel) {
            $result[$schemaModel->name] = $schemaModel;
        }
        return Arrays::getValues($result);
    }



    /**
     * Builds the Schema Models for the Framework or the Application
     * @param bool $forFramework Optional.
     * @return list<SchemaModel>
     */
    #[NotTested("It needs a Database")]
    public static function buildData(bool $forFramework = false): array {
        $classes      = Discovery::findClasses(forFramework: $forFramework);
        $errors       = [];
        $schemaModels = [];
        $modelIDs     = [];
        $dbNames      = [];

        // Parse the Reflections
        foreach ($classes as $class) {
            $className     = $class->getName();
            $parent        = $class->getParentClass();
            $fileName      = "";
            $namespace     = "";
            $fromFramework = false;
            $modelAttr     = null;

            // Get the Data from the Parent Class
            if ($parent->isNotEmpty()) {
                $fileName      = $parent->getFileName();
                $namespace     = $parent->getNamespaceName();
                $modelAttr     = $parent->getAttribute(Model::class);
                $fromFramework = true;

            // Get some data from the Base Class
            } else {
                $fileName  = $class->getFileName();
                $namespace = $class->getNamespaceName();
                $modelAttr = $class->getAttribute(Model::class);
            }
            if ($fileName === "" || $modelAttr === null) {
                continue;
            }

            // The Indexes are read from wherever the Model attribute was
            $indexAttrs = $parent->isNotEmpty() ?
                $parent->getAttributes(Index::class) :
                $class->getAttributes(Index::class);


            // Get the Path
            $path  = Storage::getDirectory($fileName, 1);
            $path  = Strings::stripEnd($path, "/" . Package::ModelDir);
            $path .= "/" . Package::SchemaDir;

            // Get the Namespace
            $namespace = Strings::stripEnd($namespace, "\\" . Package::ModelDir);
            $namespace = "$namespace\\" . Package::SchemaDir;

            // Get the Model Name
            $modelName = Strings::substringAfter($className, "\\");
            $modelName = Strings::stripEnd($modelName, "Model");


            // Instantiate the Model
            try {
                /** @var Model */
                $model = $modelAttr->newInstance();
            } catch (Throwable $e) {
                $errors[] = "$modelName: Model attribute could not be instantiated ($fileName)";
                continue;
            }

            // Instantiate the Indexes
            $indexes = [];
            foreach ($indexAttrs as $indexAttr) {
                try {
                    $indexes[] = $indexAttr->newInstance();
                } catch (Throwable $e) {
                    $errors[] = "$modelName: Index attribute could not be instantiated ($fileName)";
                    continue 2;
                }
            }

            // Get the Fantasy Name
            $fantasyName = $model->fantasyName;
            if ($fantasyName === "") {
                $fantasyName = $modelName;
            }

            // Get data from the Properties
            $hasStatus       = false;
            $mainFields      = [];
            $validates       = [];
            $virtualFields   = [];
            $requestedFields = [];
            $expressions     = [];
            $counts          = [];
            $relations       = [];
            $subRequests     = [];
            $states          = [];

            // Parse the Properties
            $props = $class->getPropertiesBaseFirst();
            foreach ($props as $prop) {
                $propType  = $prop->getType();
                $fieldName = $prop->getName();
                if (!$propType instanceof ReflectionNamedType) {
                    continue;
                }

                // Get the Type
                $typeName     = $propType->getName();
                $isFieldClass = FieldType::isValidClass($typeName);
                $isStatus     = $typeName === Status::class;
                $isEnum       = false;
                $arrayType    = "";
                $arrayClass   = "";
                $subModelName = "";

                if (!$isFieldClass && !$isStatus && !$propType->isBuiltin()) {
                    [ $isEnum, $isValidEnum ] = self::isPropEnum($typeName);

                    // Skip Enums that are not valid
                    if ($isEnum && !$isValidEnum) {
                        continue 2;
                    }
                }

                // Skip a second time the Position and Status is found
                if ($isStatus && $hasStatus) {
                    continue;
                }

                // Get the Attributes
                $propAttributes = $prop->getAttributes();
                $field          = null;
                $validate       = null;
                $virtual        = null;
                $requested      = null;
                $expression     = null;
                $count          = null;
                $relation       = null;
                $subRequest     = null;

                foreach ($propAttributes as $propAttribute) {
                    try {
                        $instance = $propAttribute->newInstance();
                    } catch (Throwable $e) {
                        $errors[] = "$modelName: $fieldName attribute could not be instantiated ($fileName)";  // phpcs:ignore
                        continue 2;
                    }

                    if ($instance instanceof Field) {
                        $field = $instance;
                    } elseif ($instance instanceof Validate) {
                        $validate = $instance;
                    } elseif ($instance instanceof Virtual) {
                        $virtual = $instance;
                    } elseif ($instance instanceof Requested) {
                        $requested = $instance;
                    } elseif ($instance instanceof Expression) {
                        $expression = $instance;
                    } elseif ($instance instanceof Count) {
                        $count = $instance;
                    } elseif ($instance instanceof Relation) {
                        $relation = $instance;
                    } elseif ($instance instanceof SubRequest) {
                        $subRequest = $instance;
                    }
                }



                // STATUS: If the Type is Status, mark it in the Model.
                if ($isStatus) {
                    $hasStatus = true;
                    foreach ($propAttributes as $attribute) {
                        $instance = $attribute->newInstance();
                        if ($instance instanceof State) {
                            $states[] = $instance;
                        }
                    }
                    if ($validate !== null) {
                        $validates[] = $validate->setStatus();
                    }

                // FIELD: Anything else is a Main Field and can have a Field attribute.
                // VALIDATE: A Main Field can also have a Validate attribute.
                } elseif ($field !== null) {
                    $field->setData($fieldName, $typeName, $isEnum);

                    $mainFields[] = $field;
                    if ($validate !== null) {
                        $validates[] = $validate->setField($field, $fantasyName);
                    }

                // VIRTUAL: If it has a Virtual attribute. It can be an array.
                } elseif ($virtual !== null) {
                    if ($typeName === "array") {
                        [ $arrayType, $subModelName, $arrayClass ] = self::getArrayType(
                            $class,
                            $prop,
                        );
                    }
                    $virtualFields[] = $virtual->setData(
                        $fieldName,
                        $typeName,
                        $arrayType,
                        $arrayClass,
                        $isEnum,
                    );

                // EXPRESSION: If it has an Expression attribute.
                } elseif ($expression !== null) {
                    $expressions[] = $expression->setData($fieldName, $typeName);

                // COUNT: If it has a Count attribute.
                } elseif ($count !== null) {
                    $counts[] = $count->setData($fieldName);

                // RELATION: If the type is a class representing a Model.
                } elseif ($relation !== null) {
                    $relationModelName = Strings::substringAfter($typeName, "\\");
                    $relationModelName = Strings::stripEnd($relationModelName, "Model");
                    $relation->setDataFromAttribute($relationModelName, $fieldName);
                    $relation->parseRelationJoin();
                    $relation->parseOwnerJoin();
                    $relations[] = $relation;

                // SUB-REQUEST: If the type is an Array (No SubRequest attribute required).
                } elseif ($subRequest !== null) {
                    [ $arrayType, $subModelName ] = self::getArrayType($class, $prop);
                    if ($arrayType !== "" || $subModelName !== "") {
                        $subRequests[] = $subRequest->setData(
                            $fieldName,
                            $subModelName,
                            $arrayType,
                        );
                    }
                }


                // REQUESTED: Any property might also be Requested
                if ($requested !== null) {
                    if ($isStatus) {
                        $requestedFields[] = $requested->fromStatus();
                    } elseif ($field !== null) {
                        $requestedFields[] = $requested->fromField($field, $validate !== null);
                    } else {
                        if ($typeName === "array") {
                            [ $arrayType, $subModelName, $arrayClass ] = self::getArrayType(
                                $class,
                                $prop,
                            );
                        }
                        $requestedFields[] = $requested->setData(
                            $fieldName,
                            $typeName,
                            $arrayType,
                            $arrayClass,
                            $subModelName,
                            $isEnum,
                        );
                    }
                }
            }


            // Add the Model
            $schemaModel = new SchemaModel(
                name:            $modelName,
                fantasyName:     $fantasyName,
                description:     $model->description,
                path:            $path,
                namespace:       $namespace,
                fromFramework:   $fromFramework,
                hasUsers:        $model->hasUsers,
                hasTimestamps:   $model->hasTimestamps,
                hasStatus:       $hasStatus,
                canCreate:       $model->canCreate,
                canEdit:         $model->canEdit,
                canDelete:       $model->canDelete,
                skipList:        $model->skipList,
                validates:       $validates,
                mainFields:      $mainFields,
                virtualFields:   $virtualFields,
                requestedFields: $requestedFields,
                expressions:     $expressions,
                relations:       $relations,
                counts:          $counts,
                subRequests:     $subRequests,
                indexes:         $indexes,
                states:          $states,
            );
            $schemaModels[$modelName] = $schemaModel;

            if ($schemaModel->hasID) {
                $modelIDs[$schemaModel->idName] = $modelName;
            }
        }


        // Set the Models in the Relations, Counts and SubRequests
        self::setModels($schemaModels);

        // Set the BelongsTo and the DB Names of the Main Fields
        self::parseMainFields($schemaModels, $modelIDs, $dbNames);

        // Do the final parsing in the Relations
        self::parseRelations($schemaModels, $dbNames);

        // Set the DB Names of the Index Columns, now that the Fields have theirs
        foreach ($schemaModels as $schemaModel) {
            foreach ($schemaModel->setIndexColumns() as $indexName => $columns) {
                foreach ($columns as $column) {
                    $errors[] = "{$schemaModel->name}: Index $indexName names no field $column";
                }
            }
        }


        // Show the Models with Errors
        if (count($errors) > 0) {
            print("\nMODELS WITH ERROR:\n");
            foreach ($errors as $error) {
                print("- $error\n\n");
            }
            print("\n");
        }

        return Arrays::getValues($schemaModels);
    }



    /**
     * Returns true if the Enum and if is valid
     * @param string $typeName
     * @return array{bool,bool}
     */
    private static function isPropEnum(string $typeName): array {
        if (!class_exists($typeName)) {
            return [ false, false ];
        }
        $propClass = new ReflectionClass($typeName);
        if (!$propClass->isEnum()) {
            return [ false, false ];
        }

        // Validate the Enum
        $isValid = true;

        if (!$propClass->implementsInterface(Enum::class)) {
            $isValid = false;
        } elseif (!$propClass->implementsInterface(JsonSerializable::class)) {
            $isValid = false;
        }
        return [ true, $isValid ];
    }

    /**
     * Returns the Field Type from the given Type Name
     * @param DiscoveryClass     $class
     * @param ReflectionProperty $prop
     * @return array{string,string,string}
     */
    #[NotTested("It needs a Database")]
    public static function getArrayType(DiscoveryClass $class, ReflectionProperty $prop): array {
        $comment = $prop->getDocComment();
        if ($comment === false) {
            return [ "", "" , "" ];
        }

        $type      = trim(Strings::substringBetween($comment, "@var", "*/"));
        $modelName = "";
        $typeClass = "";

        if (Strings::endsWith($type, "Model[]", "Model>")) {
            $modelName = Strings::stripEnd($type, "Model[]", "Model>");
            if (Strings::startsWith($type, "array<")) {
                $type      = Strings::substringBetween($type, "array<", ",");
                $modelName = Strings::substringAfter($modelName, ",");
            } else {
                $type      = "";
                $modelName = Strings::stripStart($modelName, "list<");
            }
        } else {
            $typeName  = Strings::substringBetween($type, "list<", ">");
            $typeClass = $class->getUseClassName($typeName);
        }
        return [ $type, $modelName, $typeClass ];
    }



    /**
     * Set the Models in the Relations, Counts and SubRequests
     * @param array<string,SchemaModel> $schemaModels
     * @return void
     */
    private static function setModels(array $schemaModels): void {
        foreach ($schemaModels as $schemaModel) {
            foreach ($schemaModel->relations as $relation) {
                if (isset($schemaModels[$relation->relationModelName])) {
                    $relation->setModels($schemaModels[$relation->relationModelName], $schemaModel);
                }
            }
            foreach ($schemaModel->counts as $count) {
                if (isset($schemaModels[$count->modelName])) {
                    $count->setModel($schemaModels[$count->modelName], $schemaModel);
                }
            }
            foreach ($schemaModel->subRequests as $subRequest) {
                if (isset($schemaModels[$subRequest->modelName])) {
                    $subRequest->setModel($schemaModels[$subRequest->modelName], $schemaModel);
                }
            }
        }
    }

    /**
     * Set the BelongsTo and the DB Names of the Main Fields
     * @param array<string,SchemaModel> $schemaModels
     * @param array<string,string>      $modelIDs
     * @param array<string,string>      $dbNames
     * @return void
     */
    private static function parseMainFields(
        array $schemaModels,
        array $modelIDs,
        array &$dbNames,
    ): void {
        foreach ($schemaModels as $schemaModel) {
            foreach ($schemaModel->mainFields as $field) {
                if (!$field->isID && $field->belongsTo === "") {
                    $field->belongsTo = $modelIDs[$field->name] ?? "";
                }

                // Set the DB Name (Name in uppercase) for the Field when:
                // 1. The main field is an ID of a Model
                if (isset($modelIDs[$field->name])) {
                    $dbNames[$field->name] = $field->setDbName();

                // 2. The field belongs to a Model and the otherField is an ID of a Model
                } elseif ($field->belongsTo !== "" && isset($modelIDs[$field->otherField])) {
                    $dbNames[$field->name] = $field->setDbName();

                // 3. There is a Relation where the Field is the Owner
                } else {
                    foreach ($schemaModel->relations as $relation) {
                        $ownerKey = $relation->ownerFieldName;
                        if ($ownerKey === $field->name &&
                            isset($modelIDs[$relation->relationFieldName])
                        ) {
                            $dbNames[$field->name] = $field->setDbName();
                            break;
                        }
                    }
                }
            }
            $schemaModel->setIDField();
        }
    }

    /**
     * Final parsing of the Relations
     * @param array<string,SchemaModel> $schemaModels
     * @param array<string,string>      $dbNames
     * @return void
     */
    private static function parseRelations(array $schemaModels, array $dbNames): void {
        foreach ($schemaModels as $schemaModel) {
            foreach ($schemaModel->relations as $relation) {
                $relation->generateFields();
                $relation->inferOwnerModelName();
                $relation->setDbNames($dbNames);
            }
        }
    }
}
