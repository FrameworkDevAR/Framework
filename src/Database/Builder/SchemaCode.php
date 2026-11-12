<?php
namespace Framework\Database\Builder;

use Framework\Database\SchemaModel;
use Framework\Database\Model\Field;
use Framework\Database\Model\FieldType;
use Framework\Database\Type\ValidateType;
use Framework\Builder\Builder;
use Framework\Date\Date;
use Framework\File\File;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;
use Framework\Utils\Numbers;
use Framework\Utils\URL;
use Framework\Utils\Utils;

/**
 * The Schema Code
 * @phpstan-type IDProperty array{
 *   hasID:       bool,
 *   hasIntID:    bool,
 *   hasStringID: bool,
 *   hasEnumID:   bool,
 *   idName:      string,
 *   idDbName:    string,
 *   idValue:     string,
 *   idType:      string,
 *   idText:      string,
 *   idEnumClass: string,
 * }
 * @phpstan-type Property array{
 *   fieldKey:         string,
 *   fieldName:        string,
 *   fieldText:        string,
 *   fieldArg:         string,
 *   fieldArgValue:    string,
 *   fieldDocDefault:  string,
 *   fieldArgDefault:  string,
 *   fieldDocParent:   string,
 *   fieldArgParent:   string,
 *   fieldDocEdit:     string,
 *   fieldArgEdit:     string,
 *   fieldArgCreate:   string,
 *   fieldParam:       string,
 *   fieldValue:       string,
 *   fieldValueNull:   string,
 *   fieldAssign:      string,
 *   fieldAssignEdit:  string,
 *   fieldAssignReq:   string,
 *   fieldIsRequested: bool,
 * }
 */
class SchemaCode {

    /**
     * Returns the Schema code
     * @param SchemaModel $schemaModel
     * @return string
     */
    public static function getCode(SchemaModel $schemaModel): string {
        $mainFields  = $schemaModel->toBuildData("mainFields");
        $expressions = $schemaModel->toBuildData("expressions");
        $counts      = $schemaModel->toBuildData("counts");
        $relations   = $schemaModel->toBuildData("relations");
        $subRequests = $schemaModel->toBuildData("subRequests");

        $imports     = self::getImports($schemaModel);
        $idField     = self::getIDField($schemaModel);
        $fields      = self::getAllFields($schemaModel);
        $uniques     = self::getSomeFields($schemaModel, isUnique: true);
        $parents     = self::getSomeFields($schemaModel, isParent: true);
        $editParents = $schemaModel->hasPositions ? $parents : [];
        $validations = self::getValidations($schemaModel, $parents);

        $hasRequest  = count($schemaModel->requestedFields) > 0;
        $hasVirtual  = count($schemaModel->virtualFields) > 0;
        $hasUniques  = count($uniques) > 0;
        $hasParents  = count($parents) > 0;
        $hasJsonType = count(self::getSomeFields($schemaModel, isJsonType: true)) > 0;
        $queryName   = $schemaModel->queryClass;

        $contents    = Builder::render("Schema", [
            "namespace"          => $schemaModel->namespace,
            "name"               => $schemaModel->name,
            "tableName"          => $schemaModel->tableName,
            "entityName"         => Strings::lowerCaseFirst($schemaModel->name),
            "entityClass"        => $schemaModel->entityClass,
            "requestClass"       => $schemaModel->requestClass,
            "columnClass"        => $schemaModel->columnClass,
            "statusClass"        => $schemaModel->statusClass,
            "queryClass"         => $schemaModel->queryClass,

            "hasImports"         => count($imports) > 0,
            "imports"            => $imports,
            "frameworkImports"   => self::getFrameworkImports($schemaModel, $idField),

            "hasValidation"      => count($validations) > 0,
            "validations"        => $validations,
            "errorPrefix"        => Strings::toConstantCase($schemaModel->fantasyName) . "_ERROR_",

            "editType"           => $schemaModel->hasID ? "$queryName|{$idField["idType"]}" : $queryName,  // phpcs:ignore
            "hasQuery"           => $schemaModel->hasID || count($uniques) > 0 || !$hasRequest,
            "hasList"            => !$schemaModel->skipList,

            "hasPositions"       => $schemaModel->hasPositions,
            "positionName"       => $schemaModel->positionName,
            "minPositionValue"   => $schemaModel->minPosition,
            "hasTimestamps"      => $schemaModel->hasTimestamps,
            "hasTimestampsValue" => $schemaModel->hasTimestamps ? "true" : "false",
            "hasEnvValue"        => $schemaModel->hasEnv ? "true" : "false",
            "hasStatus"          => $schemaModel->hasStatus,
            "hasStatusValue"     => $schemaModel->hasStatus ? "true" : "false",
            "hasStatusRequest"   => $schemaModel->isRequested("status"),
            "hasUsers"           => $schemaModel->hasUsers,
            "hasUsersValue"      => $schemaModel->hasUsers ? "true" : "false",
            "hasEditTimestamps"  => $schemaModel->canEdit && $schemaModel->hasTimestamps,
            "hasEditUsers"       => $schemaModel->canEdit && $schemaModel->hasUsers,
            "hasEncrypt"         => $schemaModel->hasEncrypt(),
            "canCreate"          => $schemaModel->canCreate,
            "canCreateValue"     => $schemaModel->canCreate ? "true" : "false",
            "canEdit"            => $schemaModel->canEdit,
            "canEditValue"       => $schemaModel->canEdit ? "true" : "false",
            "canReplace"         => $schemaModel->canEdit && !$schemaModel->hasAutoInc,
            "canDelete"          => $schemaModel->canDelete,
            "canDeleteValue"     => $schemaModel->canDelete ? "true" : "false",

            "hasRequest"         => $hasRequest,
            "hasRequestFields"   => self::hasRequestFields($fields),
            "hasVirtual"         => $hasVirtual,
            "mainFields"         => $mainFields,
            "hasExpressions"     => count($expressions) > 0,
            "expressions"        => $expressions,
            "hasCounts"          => count($counts) > 0,
            "counts"             => $counts,
            "hasRelations"       => count($relations) > 0,
            "relations"          => $relations,
            "hasSubRequests"     => count($subRequests) > 0,
            "subRequests"        => $subRequests,
            "fields"             => $fields,
            "uniques"            => $uniques,
            "hasParents"         => $hasParents,
            "parents"            => $parents,
            "parentsList"        => self::joinFields($parents, "fieldParam"),
            "parentsSecList"     => self::joinFields($parents, "fieldParam", ", "),
            "parentsArgList"     => self::joinFields($parents, "fieldArg", ", "),
            "hasEditParents"     => $schemaModel->hasPositions && $hasParents,
            "editParents"        => $editParents,

            "hasJsonType"        => $hasJsonType,
            "hasOperator"        => $schemaModel->hasID || $hasUniques || $hasParents,
        ] + $idField);
        return Strings::replace($contents, "(, ", "(");
    }

    /**
     * Returns used Imports
     * @param SchemaModel $schemaModel
     * @return list<string>
     */
    private static function getImports(SchemaModel $schemaModel): array {
        $result = [];

        foreach ($schemaModel->fields as $field) {
            if ($field->type === FieldType::Enum) {
                $result[$field->enumClass] = 1;
            }
        }

        foreach ($schemaModel->subRequests as $subRequest) {
            // One of a type of its own has no Model, and so nothing to import
            if ($subRequest->modelName !== "") {
                $result["{$subRequest->namespace}\\{$subRequest->modelName}Schema"] = 1;
            }
        }

        foreach ($schemaModel->validates as $validate) {
            if ($validate->typeOf !== "") {
                $result[$validate->typeOf] = 1;
            } elseif ($validate->belongsTo !== "") {
                $result[$validate->belongsTo] = 1;
            }
        }

        $result = array_keys($result);
        sort($result);
        return $result;
    }

    /**
     * Returns the ID Field data
     * @param SchemaModel $schemaModel
     * @return IDProperty
     */
    private static function getIDField(SchemaModel $schemaModel): array {
        return [
            "hasID"       => $schemaModel->hasID,
            "hasIntID"    => $schemaModel->hasID && $schemaModel->idType === FieldType::Number,
            "hasStringID" => $schemaModel->hasID && $schemaModel->idType === FieldType::String,
            "hasEnumID"   => $schemaModel->hasID && $schemaModel->idType === FieldType::Enum,
            "idName"      => $schemaModel->idName,
            "idDbName"    => $schemaModel->idDbName,
            "idValue"     => "\${$schemaModel->idName}",
            "idType"      => $schemaModel->idType->getCodeType(
                $schemaModel->idEnumClass,
                forEntity: false,
            ),
            "idText"      => Strings::upperCaseFirst($schemaModel->idName),
            "idEnumClass" => Strings::substringAfter($schemaModel->idEnumClass, "\\"),
        ];
    }

    /**
     * Returns a list of all the Fields to set
     * @param SchemaModel $schemaModel
     * @return list<Property>
     */
    private static function getAllFields(SchemaModel $schemaModel): array {
        $result = [];
        foreach ($schemaModel->mainFields as $field) {
            if (!$field->isAutoInc()) {
                $result[] = self::getField($schemaModel, $field);
            }
        }
        foreach ($schemaModel->extraFields as $field) {
            if ($field->name === "position") {
                $result[] = self::getField($schemaModel, $field);
            }
        }
        return $result;
    }

    /**
     * Returns true if there is at least one Field requested
     * @param list<Property> $fields
     * @return bool
     */
    private static function hasRequestFields(array $fields): bool {
        foreach ($fields as $field) {
            if ($field["fieldIsRequested"]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a list of Fields with the given property
     * @param SchemaModel $schemaModel
     * @param bool        $isUnique    Optional.
     * @param bool        $isParent    Optional.
     * @param bool        $isJsonType  Optional.
     * @param bool        $isFloatType Optional.
     * @return list<Property>
     */
    private static function getSomeFields(
        SchemaModel $schemaModel,
        bool $isUnique = false,
        bool $isParent = false,
        bool $isJsonType = false,
        bool $isFloatType = false,
    ): array {
        $result = [];
        foreach ($schemaModel->fields as $field) {
            if ($isUnique && $field->isUnique) {
                $result[] = self::getField($schemaModel, $field);
            } elseif ($isParent && $field->isParent) {
                $result[] = self::getField($schemaModel, $field);
            } elseif ($isJsonType && $field->type === FieldType::JSON) {
                $result[] = self::getField($schemaModel, $field);
            } elseif ($isFloatType && $field->type === FieldType::Float) {
                $result[] = self::getField($schemaModel, $field);
            }
        }
        return $result;
    }

    /**
     * Returns the Fields data for the Schema
     * @param SchemaModel $schemaModel
     * @param Field       $field
     * @return Property
     */
    private static function getField(SchemaModel $schemaModel, Field $field): array {
        $type        = $field->type->getCodeType($field->enumClass, forEntity: false);
        $isDate      = $field->type === FieldType::Date;
        $isEnum      = $field->type === FieldType::Enum;
        $isJSON      = $field->type === FieldType::JSON;

        $typeDoc     = $type;
        $typeNull    = "$type|null";
        $canAssign   = !$field->isID && !$field->isParent && !$isDate && !$isEnum;
        $assignDoc   = $canAssign ? "Assign|" : "";

        $default     = FieldType::getDefault($type);
        $defaultNull = $default === "null";
        $docNull     = $defaultNull ? "|null" : "";

        $param       = "\${$field->name}";
        $value       = $param;
        $valueNull   = $param;
        $assign      = $param;
        $assignEdit  = $param;
        $assignReq   = "\$request->{$field->name}";

        if ($isJSON) {
            $typeNull   = "$type|null";
            $typeDoc    = Strings::replace($type, "array", "array<int|string,mixed>");
        } elseif ($type === "float") {
            $assign     = "Numbers::toInt($param, {$field->decimals})";
            $assignEdit = "$param instanceof Assign ? $param : $assign";
            $assignReq  = "Numbers::toInt($assignReq, {$field->decimals})";
        } elseif ($type === "bool") {
            $value      = "$param === true ? 1 : 0";
            $valueNull  = $value;
        }

        return [
            "fieldKey"         => $field->dbName,
            "fieldName"        => $field->name,
            "fieldText"        => Strings::upperCaseFirst($field->name),
            "fieldArg"         => "$type $param",
            "fieldArgValue"    => "$typeDoc $param",
            "fieldDocDefault"  => "$typeDoc$docNull $param",
            "fieldArgDefault"  => "$typeDoc$docNull $param = $default",
            "fieldDocParent"   => "$typeDoc|null $param",
            "fieldArgParent"   => "$typeNull $param",
            "fieldDocCreate"   => "$typeDoc|null $param",
            "fieldArgCreate"   => "$typeNull $param = null",
            "fieldDocEdit"     => "$assignDoc$typeDoc|null $param",
            "fieldArgEdit"     => ($canAssign ? "Assign|$typeNull" : $typeNull) . " $param = null",
            "fieldParam"       => $param,
            "fieldValue"       => $value,
            "fieldValueNull"   => $valueNull,
            "fieldAssign"      => $assign,
            "fieldAssignEdit"  => $assignEdit,
            "fieldAssignReq"   => $assignReq,
            "fieldIsRequested" => $schemaModel->isRequested($field->name),
        ];
    }

    /**
     * Summary of joinFields
     * @param list<Property> $fields
     * @param string         $key
     * @param string         $prefix Optional.
     * @return string
     */
    private static function joinFields(array $fields, string $key, string $prefix = ""): string {
        if (count($fields) === 0) {
            return "";
        }
        $list   = Arrays::createArray($fields, $key);
        $result = Strings::join($list, ", ");
        return $prefix . $result;
    }



    /**
     * Returns the Validations for the Schema
     * @param SchemaModel    $schemaModel
     * @param list<Property> $parents
     * @return list<array<string,mixed>>
     */
    private static function getValidations(SchemaModel $schemaModel, array $parents): array {
        $hasFromDate = false;
        $result      = [];

        foreach ($schemaModel->validates as $validate) {
            if (!$validate->shouldValidate()) {
                continue;
            }

            $validation = [];
            switch ($validate->type) {
            case ValidateType::None:
                break;

            case ValidateType::String:
            case ValidateType::Enum:
                $isRequired = $validate->isRequired && $validate->type !== ValidateType::Enum;
                $invSuffix  = $isRequired || $validate->isUnique || $validate->maxLength > 0;
                $validation = [
                    "isString"     => true,
                    "isRequired"   => $isRequired,
                    "isUnique"     => $validate->isUnique,
                    "typeOf"       => Strings::substringAfter($validate->typeOf, "\\"),
                    "typeInvError" => $validate->getTypeInvalidError($invSuffix),
                    "method"       => $validate->method,
                    "emptySuffix"  => $validate->isUnique || $validate->typeOf !== "" || $validate->maxLength > 0,  // phpcs:ignore
                    "lengthSuffix" => $isRequired || $validate->isUnique || $validate->typeOf !== "",  // phpcs:ignore
                    "maxLength"    => $validate->maxLength,
                    "fieldName"    => $validate->name,
                    "fieldError"   => $validate->getFieldError(),
                ];
                break;

            case ValidateType::Email:
                $validation = [
                    "isEmail"    => true,
                    "isRequired" => $validate->isRequired,
                    "isUnique"   => $validate->isUnique,
                    "fieldName"  => $validate->name,
                    "fieldError" => $validate->getFieldError(),
                ];
                break;

            case ValidateType::Url:
                $validation = [
                    "isUrl"      => true,
                    "isRequired" => $validate->isRequired,
                    "fieldName"  => $validate->name,
                    "fieldError" => $validate->getFieldError(),
                ];
                break;

            case ValidateType::Number:
                $hasTypeOf    = $validate->typeOf !== "";
                $hasBelongsTo = $validate->belongsTo !== "";
                $hasGreater   = $validate->greaterThan !== "";
                $isNumeric    = !$hasTypeOf && !$hasBelongsTo;

                $validation = [
                    "isNumber"         => true,
                    "fieldName"        => $validate->name,
                    "fieldError"       => $validate->getFieldError(),

                    "isRequired"       => $validate->isRequired,
                    "emptyValue"       => $validate->fieldType === FieldType::Float ? "0.0" : "0",
                    "emptySuffix"      => $isNumeric || $validate->isUnique || $hasGreater,

                    "useTypeOfElse"    => $validate->isRequired,
                    "typeOf"           => Strings::substringAfter($validate->typeOf, "\\"),
                    "typeOfError"      => $validate->getTypeOfError(),
                    "method"           => $validate->method,

                    "useBelongsToElse" => $validate->isRequired || $hasTypeOf,
                    "belongsTo"        => Strings::substringAfter($validate->belongsTo, "\\"),
                    "belongsToError"   => $validate->getBelongsToError(),

                    "useNumericElse"   => $validate->isRequired || $hasTypeOf || $hasBelongsTo,
                    "isNumeric"        => $isNumeric,
                    "numericParams"    => $validate->getNumericParams(),
                    "invalidPrefix"    => $validate->isRequired || $validate->isUnique ||
                        $hasGreater,

                    "useUniqueElse"    => $validate->isRequired || $hasTypeOf ||
                        $hasBelongsTo || $isNumeric,
                    "isUnique"         => $validate->isUnique,

                    "useGreaterElse"   => $validate->isRequired || $hasTypeOf || $hasBelongsTo ||
                        $isNumeric || $validate->isUnique,
                    "greaterThan"      => $validate->greaterThan,
                ];
                break;

            case ValidateType::Price:
                $validation = [
                    "isPrice"    => true,
                    "isRequired" => $validate->isRequired,
                    "emptyValue" => $validate->fieldType === FieldType::Float ? "0.0" : "0",
                    "fieldName"  => $validate->name,
                    "fieldError" => $validate->getFieldError(),
                ];
                break;

            case ValidateType::Date:
                $validation = [
                    "isDate"     => true,
                    "isRequired" => $validate->isRequired,
                    "dateName"   => $validate->dateInput,
                    "hasHour"    => $validate->hourInput !== "",
                    "hourName"   => $validate->hourInput,
                    "dateError"  => $validate->getDateError(),
                    "hourError"  => $validate->getHourError(),
                    "fieldName"  => $validate->name,
                    "fieldError" => $validate->getFieldError(),
                ];
                if (Strings::startsWith($validate->name, "from")) {
                    $hasFromDate = true;
                } elseif (Strings::startsWith($validate->name, "to") && $hasFromDate) {
                    $validation += [
                        "hasPeriod"    => true,
                        "fromDateName" => "fromTime",
                        "toDateName"   => "toTime",
                    ];
                    $hasFromDate = false;
                }
                break;

            case ValidateType::List:
                $validation = [
                    "isList"         => true,
                    "typeOf"         => Strings::substringAfter($validate->typeOf, "\\"),
                    "typeInvError"   => $validate->getTypeInvalidError(),
                    "belongsTo"      => Strings::substringAfter($validate->belongsTo, "\\"),
                    "belongsToError" => $validate->getBelongsToError(forList: true),
                    "method"         => $validate->method,
                    "withParent"     => $validate->withParent,
                    "fieldName"      => $validate->name,
                    "fieldError"     => $validate->getFieldError(),
                ];
                break;

            case ValidateType::Status:
                $validation = [
                    "isStatus" => true,
                ];
                break;
            }

            $condition = $validate->createCondition($schemaModel->requestedFields, $parents);
            $result[]  = $validation + [
                "hasIf"     => $condition !== "",
                "condition" => $condition,
                "pads"      => $condition !== "" ? "    " : "",
            ];
        }
        return $result;
    }

    /**
     * Returns the Framework Imports
     * @param SchemaModel $schemaModel
     * @param IDProperty  $idField
     * @return list<string>
     */
    private static function getFrameworkImports(
        SchemaModel $schemaModel,
        array $idField,
    ): array {
        $result = [];

        $hasFloatType = count(self::getSomeFields($schemaModel, isFloatType: true)) > 0;
        if ($hasFloatType || $idField["hasIntID"]) {
            $result[Numbers::class] = 1;
        }
        if ($idField["hasID"]) {
            $result[Arrays::class] = 1;
        }

        foreach ($schemaModel->fields as $field) {
            if ($field->type === FieldType::Date) {
                if ((!$schemaModel->canCreate && $field->name === "createdTime") ||
                    $field->name === "modifiedTime"
                ) {
                    continue;
                }
                $result[Date::class] = 1;
            } elseif ($field->type === FieldType::File) {
                $result[File::class] = 1;
            }
        }

        foreach ($schemaModel->validates as $validate) {
            if (!$validate->shouldValidate()) {
                continue;
            }

            if ($validate->type === ValidateType::Number && $validate->isNumeric) {
                $result[Numbers::class] = 1;
            } elseif ($validate->type === ValidateType::String && $validate->maxLength > 0) {
                $result[Strings::class] = 1;
            } elseif ($validate->type === ValidateType::Email) {
                $result[Utils::class] = 1;
            } elseif ($validate->type === ValidateType::Url) {
                $result[URL::class] = 1;
            }
        }
        return array_keys($result);
    }
}
