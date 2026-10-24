<?php
namespace Framework\Database\Builder;

use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;
use Framework\Database\Model\FieldType;
use Framework\Builder\Builder;
use Framework\Utils\Strings;

/**
 * The Media Code
 * @phpstan-type MediaField array{
 *   name:       string,
 *   query:      string,
 *   tableName:  string,
 *   fieldName:  string,
 *   isSet:      bool,
 *   isReplace:  bool,
 *   isJSON:     bool,
 *   hasOwner:   bool,
 *   ownerWhere: string,
 * }
 */
class MediaCode implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    public static function generateCode(): int {
        $fields     = self::getFields(SchemaFactory::getData());
        $hasReplace = false;
        $hasText    = false;
        $hasJSON    = false;

        foreach ($fields as $field) {
            if ($field["isReplace"] || $field["isJSON"]) {
                $hasReplace = true;
            }
            if ($field["isReplace"]) {
                $hasText = true;
            }
            if ($field["isJSON"]) {
                $hasJSON = true;
            }
        }

        // Builds the code
        return Builder::generateCode("MediaSchema", [
            "fields"     => $fields,
            "hasFields"  => count($fields) > 0,
            "hasReplace" => $hasReplace,
            "hasText"    => $hasText,
            "hasJSON"    => $hasJSON,
            "total"      => count($fields),
        ]);
    }

    /**
     * Returns a row for every field of the given Models that holds a file
     * @param list<SchemaModel> $schemaModels
     * @return list<MediaField>
     */
    public static function getFields(array $schemaModels): array {
        $result = [];

        foreach ($schemaModels as $schemaModel) {
            foreach ($schemaModel->fields as $field) {
                if (!$field->isFile && !$field->hasFile) {
                    continue;
                }

                // A file named inside text is replaced where it is written,
                // rather than set as the whole value of the column
                $isJSON    = false;
                $isReplace = (
                    $field->type === FieldType::Text ||
                    $field->type === FieldType::LongText ||
                    $field->type === FieldType::JSON
                );
                if ($field->jsonFiles) {
                    $isJSON    = true;
                    $isReplace = false;
                }

                $ownerWhere = self::getOwnerWhere($schemaModel, $field->fileOwner);
                $result[]   = [
                    "name"       => $schemaModel->name,
                    "query"      => Strings::lowerCaseFirst($schemaModel->queryClass),
                    "tableName"  => $schemaModel->tableName,
                    "fieldName"  => $field->name,
                    "isSet"      => !$isReplace && !$isJSON,
                    "isReplace"  => $isReplace,
                    "isJSON"     => $isJSON,
                    "hasOwner"   => $ownerWhere !== "",
                    "ownerWhere" => $ownerWhere,
                ];
            }
        }
        return $result;
    }

    /**
     * Returns the condition that keeps the rows of the Model to the ones of an ID of the Media
     * @param SchemaModel $schemaModel
     * @param string      $fileOwner
     * @return string
     */
    private static function getOwnerWhere(SchemaModel $schemaModel, string $fileOwner): string {
        // A field without an Owner changes the paths of every row
        if ($fileOwner === "") {
            return "";
        }
        foreach ($schemaModel->fields as $field) {
            if ($field->name === $fileOwner) {
                return "`{$field->dbName}` = ?";
            }
        }
        return "";
    }

    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        return 1;
    }
}
