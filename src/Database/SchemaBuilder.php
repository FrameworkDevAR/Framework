<?php
namespace Framework\Database;

use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;
use Framework\Discovery\Type\DiscoveryBuilder;
use Framework\Database\Builder\SchemaCode;
use Framework\Database\Builder\EntityCode;
use Framework\Database\Builder\ColumnCode;
use Framework\Database\Builder\QueryCode;
use Framework\Database\Builder\StatusCode;
use Framework\Database\Builder\RequestedCode;
use Framework\File\Storage;

/**
 * The Schema Builder
 */
class SchemaBuilder implements DiscoveryBuilder {

    /**
     * Generates the code
     * @return int
     */
    #[\Override]
    public static function generateCode(): int {
        print("\n");
        $frameModels = SchemaFactory::buildData(forFramework: true);
        $appModels   = SchemaFactory::buildData(forFramework: false, baseModels: $frameModels);

        $created  = self::generateSchemaCode($frameModels, "Framework");
        $created += self::generateSchemaCode($appModels, "App");
        print("\n");
        return $created;
    }

    /**
     * Generates the Code for the given Schemas, into the path each one carries
     * @param list<SchemaModel> $schemaModels
     * @param string            $name
     * @return int
     */
    public static function generateSchemaCode(array $schemaModels, string $name): int {
        $created = 0;

        foreach ($schemaModels as $schemaModel) {
            if (!$schemaModel->fromFramework) {
                Storage::createDir($schemaModel->path);
                Storage::emptyDir($schemaModel->path);
            }
        }

        foreach ($schemaModels as $schemaModel) {
            // Build the main Schema file
            if (!$schemaModel->isEmpty) {
                $schemaName = "{$schemaModel->name}Schema.php";
                $schemaCode = SchemaCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $schemaName, $schemaCode);
                $created += 1;
            }

            // Build the Entity file
            if (!$schemaModel->isEmpty) {
                $entityName = "{$schemaModel->entityClass}.php";
                $entityCode = EntityCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $entityName, $entityCode);
                $created += 1;
            }

            // Build the Column file
            if (!$schemaModel->isEmpty) {
                $columnName = "{$schemaModel->columnClass}.php";
                $columnCode = ColumnCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $columnName, $columnCode);
                $created += 1;
            }

            // Build the Query file
            if (!$schemaModel->isEmpty) {
                $queryName = "{$schemaModel->queryClass}.php";
                $queryCode = QueryCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $queryName, $queryCode);
                $created += 1;
            }

            // Build the Status files
            if ($schemaModel->hasStatus) {
                $statusName = "{$schemaModel->statusClass}.php";
                $statusCode = StatusCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $statusName, $statusCode);
                $created += 1;

                $statusQueryName = "{$schemaModel->statusClass}Where.php";
                $statusQueryCode = StatusCode::getWhereCode($schemaModel);
                Storage::createFile($schemaModel->path, $statusQueryName, $statusQueryCode);
                $created += 1;
            }

            // Build the Request file
            if (count($schemaModel->requestedFields) > 0) {
                $requestedName = "{$schemaModel->requestClass}.php";
                $requestedCode = RequestedCode::getCode($schemaModel);
                Storage::createFile($schemaModel->path, $requestedName, $requestedCode);
                $created += 1;
            }
        }

        $models = count($schemaModels);
        print("- $name Schema codes -> $models models ($created files)\n");
        return $created;
    }



    /**
     * Destroys the Code
     * @return int
     */
    #[\Override]
    public static function destroyCode(): int {
        $deleted  = self::destroySchemaCode(SchemaFactory::buildData(forFramework: true));
        $deleted += self::destroySchemaCode(SchemaFactory::buildData(forFramework: false));
        return $deleted;
    }

    /**
     * Destroys the Code of the given Schemas
     * @param list<SchemaModel> $schemaModels
     * @return int
     */
    public static function destroySchemaCode(array $schemaModels): int {
        $deletedFiles = 0;

        foreach ($schemaModels as $schemaModel) {
            if (!$schemaModel->fromFramework) {
                Storage::deleteDir($schemaModel->path, $deletedFiles);
            }
        }
        return $deletedFiles;
    }
}
