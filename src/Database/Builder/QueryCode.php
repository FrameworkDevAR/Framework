<?php
namespace Framework\Database\Builder;

use Framework\Core\Configs;
use Framework\Database\SchemaModel;
use Framework\Database\Model\FieldType;
use Framework\Builder\Builder;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

/**
 * The Query Code
 * @phpstan-type Property array{
 *   type:     string,
 *   name:     string,
 *   value:    string,
 *   propName: string,
 * }
 */
class QueryCode {

    /**
     * Returns the Query code
     * @param SchemaModel $schemaModel
     * @return string
     */
    public static function getCode(SchemaModel $schemaModel): string {
        $properties = self::getProperties($schemaModel);
        $contents   = Builder::render("Query", [
            "namespace"   => $schemaModel->namespace,
            "name"        => $schemaModel->name,
            "tableName"   => $schemaModel->tableName,
            "idDbName"    => $schemaModel->idDbName,
            "columnClass" => $schemaModel->columnClass,
            "queryClass"  => $schemaModel->queryClass,
            "hasEnv"      => $schemaModel->hasEnv,
            "properties"  => $properties,
            "imports"     => self::getImports($schemaModel),
            "queries"     => self::getQueries($properties),
        ]);
        return $contents;
    }

    /**
     * Returns the Field properties for the Query
     * @param SchemaModel $schemaModel
     * @return list<Property>
     */
    private static function getProperties(SchemaModel $schemaModel): array {
        $nameLength = 0;
        $list       = [];
        $result     = [];

        foreach ($schemaModel->fields as $field) {
            $status = $field->isStatus ? $schemaModel->statusClass : "";
            $list[] = [
                "type"  => self::getWhereType($field->type, $status),
                "name"  => $field->name,
                "value" => "{$schemaModel->tableName}.{$field->dbName}",
            ];
        }

        foreach ($schemaModel->expressions as $expression) {
            $list[] = [
                "type"  => self::getWhereType($expression->type),
                "name"  => $expression->name,
                "value" => $expression->name,
            ];
        }

        foreach ($schemaModel->relations as $relation) {
            $tableName = $relation->getDbTableName();
            foreach ($relation->fields as $field) {
                $status = $field->isStatus ? "{$relation->relationModelName}Status" : "";
                $list[] = [
                    "type"  => self::getWhereType($field->type, $status),
                    "name"  => $field->prefixName,
                    "value" => "{$tableName}.{$field->dbName}",
                ];
            }
        }

        foreach ($list as $property) {
            if ($property["type"] !== "") {
                $nameLength = max($nameLength, Strings::length($property["name"]));
                $result[]   = $property;
            }
        }

        foreach ($result as $index => $property) {
            $result[$index]["propName"] = Strings::padRight(
                $property["name"],
                $nameLength,
            );
        }
        return $result;
    }

    /**
     * Returns the Query Imports for the Query
     * @param SchemaModel $schemaModel
     * @return list<string>
     */
    private static function getImports(SchemaModel $schemaModel): array {
        $result = [];
        if ($schemaModel->hasEnv) {
            $result[Configs::class] = 1;
        }
        foreach ($schemaModel->fields as $field) {
            if ($field->isStatus) {
                $queryName = "{$schemaModel->statusClass}Where";
                $result["{$schemaModel->namespace}\\$queryName"] = 1;
            }
        }

        foreach ($schemaModel->relations as $relation) {
            foreach ($relation->fields as $field) {
                if ($field->isStatus && $relation->relationModel !== null) {
                    $queryName = "{$relation->relationModelName}StatusWhere";
                    $result["{$relation->relationModel->namespace}\\$queryName"] = 1;
                }
            }
        }

        return array_keys($result);
    }

    /**
     * Returns the Queries used in the Query
     * @param list<Property> $properties
     * @return list<string>
     */
    private static function getQueries(array $properties): array {
        $result = [];
        foreach ($properties as $property) {
            if ($property["type"] !== "Query" &&
                !Strings::endsWith($property["type"], "StatusWhere") &&
                !Arrays::contains($result, $property["type"])
            ) {
                $result[] = $property["type"];
            }
        }
        return $result;
    }


    /**
     * Returns the Where Type for a Field Type
     * @param FieldType $type
     * @param string    $status Optional.
     * @return string
     */
    private static function getWhereType(FieldType $type, string $status = ""): string {
        if ($status !== "") {
            return "{$status}Where";
        }
        return match ($type) {
            FieldType::None    => "",

            FieldType::Date    => "DateWhere",
            FieldType::Enum    => "EnumWhere",
            FieldType::JSON    => "JsonWhere",
            FieldType::Array   => "StringWhere",

            FieldType::Boolean => "BooleanWhere",

            FieldType::Number,
            FieldType::Float   => "NumberWhere",

            FieldType::String,
            FieldType::Text,
            FieldType::LongText,
            FieldType::File,
            FieldType::Encrypt => "StringWhere",
        };
    }
}
