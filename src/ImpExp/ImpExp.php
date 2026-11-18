<?php
namespace Framework\ImpExp;

use Framework\Analysis\Attr\NotTested;
use Framework\ImpExp\Exporter;
use Framework\ImpExp\Importer;
use Framework\ImpExp\ImpExpType;
use Framework\ImpExp\ImpExpStatus;
use Framework\ImpExp\XLSXReader;
use Framework\ImpExp\Schema\ImpExpSchema;
use Framework\ImpExp\Schema\ImpExpEntity;
use Framework\ImpExp\Schema\ImpExpQuery;
use Framework\ImpExp\Schema\ImpExpRequest;
use Framework\ImpExp\Schema\ImpExpColumn;
use Framework\File\FilePath;
use Framework\File\Storage;
use Framework\Date\Date;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\JSON;
use Framework\Utils\Strings;

/**
 * The Import and Export
 */
class ImpExp extends ImpExpSchema {

    // Over these amounts of rows an Export or an Import takes more than about 20 seconds, so it
    // runs in blocks, and an Import is much slower, as it checks and saves each row
    private static int $exportLimit = 10000;
    private static int $importLimit = 500;
    private static int $excelLimit  = 2000;
    private static int $expireDays  = 3;

    private const Folder = "impexp";



    /**
     * Sets the amount of rows over which an Export runs in blocks
     * @param int $exportLimit
     * @return void
     */
    public static function setExportLimit(int $exportLimit): void {
        self::$exportLimit = $exportLimit;
    }

    /**
     * Sets the amount of rows over which an Import runs in blocks
     * @param int $importLimit
     * @return void
     */
    public static function setImportLimit(int $importLimit): void {
        self::$importLimit = $importLimit;
    }

    /**
     * Sets the amount of rows over which an Import has to be a CSV instead of an Excel
     * @param int $excelLimit
     * @return void
     */
    public static function setExcelLimit(int $excelLimit): void {
        self::$excelLimit = $excelLimit;
    }

    /**
     * Returns true if an Export of the given total has to run in blocks
     * @param int $total
     * @return bool
     */
    public static function isLongExport(int $total): bool {
        return $total > self::$exportLimit;
    }

    /**
     * Returns true if an Import of the given total has to run in blocks
     * @param int $total
     * @return bool
     */
    public static function isLongImport(int $total): bool {
        return $total > self::$importLimit;
    }

    /**
     * Returns true if the given file is an Excel too big to import, which has to be a CSV
     * @param string $filePath
     * @return bool
     */
    public static function isLongExcel(string $filePath): bool {
        // Each block of a long Import reads the file from its first row, which is fast in a
        // CSV but means reading the whole Excel file each time, which only takes a few
        // seconds while the file is not too big
        if (Storage::getExtension($filePath) !== "xlsx") {
            return false;
        }
        return XLSXReader::countRows($filePath) - 1 > self::$excelLimit;
    }



    /**
     * Creates the List Query
     * @param ImpExpRequest $request
     * @return ImpExpQuery
     */
    #[\Override]
    protected static function createListQuery(ImpExpRequest $request): ImpExpQuery {
        $query = new ImpExpQuery();
        $query->search([
            ImpExpColumn::FileName,
            ImpExpColumn::CredentialFirstName,
            ImpExpColumn::CredentialLastName,
        ], $request->search);

        $query->createdTime->greaterThan($request->fromDate);
        $query->createdTime->lessThan($request->toDate);
        $query->impExpType->in(ImpExpType::fromList($request->types));
        $query->impExpStatus->in(ImpExpStatus::fromList($request->statuses));
        return $query;
    }



    /**
     * Returns the running Import or Export of the Credential for the given Module and data
     * @param int        $credentialID
     * @param ImpExpType $impExpType
     * @param string     $module
     * @param int        $dataID
     * @return ImpExpEntity
     */
    public static function getRunning(
        int $credentialID,
        ImpExpType $impExpType,
        string $module,
        int $dataID,
    ): ImpExpEntity {
        $query = new ImpExpQuery();
        $query->credentialID->equal($credentialID);
        $query->impExpType->equal($impExpType);
        $query->module->equal($module);
        $query->dataID->equal($dataID);
        $query->impExpStatus->equal(ImpExpStatus::Running);
        $query->inCurrentEnv();
        return self::getEntity($query);
    }

    /**
     * Returns the Imports and Exports of the Credential that are running or ready
     * @param int $credentialID
     * @return list<ImpExpEntity>
     */
    public static function getAllForCredential(int $credentialID): array {
        $query = new ImpExpQuery();
        $query->credentialID->equal($credentialID);
        $query->impExpStatus->in([ ImpExpStatus::Running, ImpExpStatus::Ready ]);
        $query->inCurrentEnv();
        $query->createdTime->orderByDesc();
        return self::getEntityList($query);
    }

    /**
     * Returns the Imports and Exports that are running, to queue their next block
     * @return list<ImpExpEntity>
     */
    public static function getAllRunning(): array {
        $query = new ImpExpQuery();
        $query->impExpStatus->equal(ImpExpStatus::Running);
        $query->inCurrentEnv();
        return self::getEntityList($query);
    }



    /**
     * Creates an Export with the IDs of the rows to write, in the order of the file
     * @param int                 $credentialID
     * @param string              $module
     * @param int                 $dataID
     * @param array<string,mixed> $filters
     * @param string              $fileName
     * @param list<int>           $ids
     * @return int
     */
    public static function createExport(
        int $credentialID,
        string $module,
        int $dataID,
        array $filters,
        string $fileName,
        array $ids,
    ): int {
        // The IDs are taken when the Export starts, so every block reads the same rows in the
        // same order, even when rows are added or deleted while it runs
        $fileCode = Strings::random(32);
        $idsPath  = self::getPath($credentialID, $fileCode, "json");
        if (!JSON::writeFile($idsPath, $ids)) {
            return 0;
        }

        return self::createEntity(
            credentialID: $credentialID,
            impExpType:   ImpExpType::Export,
            module:       $module,
            dataID:       $dataID,
            options:      $filters,
            fileName:     "$fileName.csv",
            fileCode:     $fileCode,
            totalRows:    count($ids),
            impExpStatus: ImpExpStatus::Running,
        );
    }

    /**
     * Creates an Import of the given file, which is copied so it outlives the temp files
     * @param int               $credentialID
     * @param string            $module
     * @param int               $dataID
     * @param array<string,int> $columns
     * @param string            $filePath
     * @param int               $totalRows
     * @return int
     */
    public static function createImport(
        int $credentialID,
        string $module,
        int $dataID,
        array $columns,
        string $filePath,
        int $totalRows,
    ): int {
        // The json of an Import keeps the rows that could not be imported, so they can be
        // downloaded once it ends
        $fileName  = Storage::getBaseName($filePath);
        $fileCode  = Strings::random(32);
        $extension = Storage::getExtension($fileName);
        $copyPath  = self::getPath($credentialID, $fileCode, $extension);
        $jsonPath  = self::getPath($credentialID, $fileCode, "json");
        if (!Storage::copyFile($filePath, $copyPath) || !JSON::writeFile($jsonPath, [])) {
            return 0;
        }

        return self::createEntity(
            credentialID: $credentialID,
            impExpType:   ImpExpType::Import,
            module:       $module,
            dataID:       $dataID,
            options:      $columns,
            fileName:     $fileName,
            fileCode:     $fileCode,
            totalRows:    $totalRows,
            impExpStatus: ImpExpStatus::Running,
        );
    }

    /**
     * Returns the IDs of the next block of the Export
     * @param ImpExpEntity $impExp
     * @param int          $amount
     * @return list<int>
     */
    public static function getNextIDs(ImpExpEntity $impExp, int $amount): array {
        $idsPath = self::getPath($impExp->credentialID, $impExp->fileCode, "json");
        $ids     = Arrays::toInts(JSON::readFile($idsPath));
        return array_slice($ids, $impExp->doneRows, $amount);
    }

    /**
     * Returns an Importer of the next block of the Import, with the columns of the Import
     * @param ImpExpEntity $impExp
     * @param int          $amount
     * @return Importer
     */
    public static function getImporter(ImpExpEntity $impExp, int $amount): Importer {
        $importer = new Importer(self::getFilePath($impExp));
        $importer->setColumns(Arrays::toStringIntMap($impExp->options->toArray()));
        $importer->setRange($impExp->doneRows, $amount);
        return $importer;
    }

    /**
     * Returns the path of the file of the Import or Export
     * @param ImpExpEntity $impExp
     * @return string
     */
    public static function getFilePath(ImpExpEntity $impExp): string {
        $extension = Storage::getExtension($impExp->fileName);
        return self::getPath($impExp->credentialID, $impExp->fileCode, $extension);
    }

    /**
     * Returns the rows of the Import that could not be imported
     * @param ImpExpEntity $impExp
     * @return list<array{index:int,name:string}>
     */
    public static function getWarnings(ImpExpEntity $impExp): array {
        $jsonPath = self::getPath($impExp->credentialID, $impExp->fileCode, "json");
        $list     = new Dictionary(JSON::readFile($jsonPath));
        $result   = [];
        foreach ($list as $warning) {
            $result[] = [
                "index" => $warning->getInt("index"),
                "name"  => $warning->getString("name"),
            ];
        }
        return $result;
    }

    /**
     * Adds the written rows to the Export, and returns true when the file is ready
     * @param ImpExpEntity $impExp
     * @param int          $amount
     * @return bool
     */
    public static function addRows(ImpExpEntity $impExp, int $amount): bool {
        $isDone = $amount === 0 || $impExp->doneRows + $amount >= $impExp->totalRows;
        if ($isDone) {
            Storage::deleteFile(self::getPath($impExp->credentialID, $impExp->fileCode, "json"));
        }
        return self::addDone($impExp, $amount, 0, $isDone);
    }

    /**
     * Adds the read rows to the Import, and returns true when it ended
     * @param ImpExpEntity                       $impExp
     * @param int                                $amount
     * @param int                                $imported
     * @param list<array{index:int,name:string}> $warnings
     * @return bool
     */
    public static function addImported(
        ImpExpEntity $impExp,
        int $amount,
        int $imported,
        array $warnings,
    ): bool {
        if (count($warnings) > 0) {
            $jsonPath = self::getPath($impExp->credentialID, $impExp->fileCode, "json");
            JSON::writeFile($jsonPath, array_merge(self::getWarnings($impExp), $warnings));
        }

        $isDone = $amount === 0 || $impExp->doneRows + $amount >= $impExp->totalRows;
        return self::addDone($impExp, $amount, $imported, $isDone);
    }

    /**
     * Cancels the Import or Export, deleting its files
     * @param ImpExpEntity $impExp
     * @return bool
     */
    public static function cancel(ImpExpEntity $impExp): bool {
        self::deleteFiles($impExp);
        return self::editEntity($impExp->id, impExpStatus: ImpExpStatus::Canceled);
    }

    /**
     * Marks the Import or Export as Failed, deleting its files
     * @param ImpExpEntity $impExp
     * @return bool
     */
    public static function fail(ImpExpEntity $impExp): bool {
        self::deleteFiles($impExp);
        return self::editEntity($impExp->id, impExpStatus: ImpExpStatus::Failed);
    }

    /**
     * Removes the Import or Export and its files
     * @param ImpExpEntity $impExp
     * @return bool
     */
    public static function remove(ImpExpEntity $impExp): bool {
        self::deleteFiles($impExp);
        return self::removeEntity($impExp->id);
    }

    /**
     * Downloads the file of a ready Export
     * @param ImpExpEntity $impExp
     * @return never
     */
    #[NotTested("It sends the file and exits")]
    public static function download(ImpExpEntity $impExp): never {
        $filePath = self::getFilePath($impExp);
        if ($impExp->impExpType !== ImpExpType::Export ||
            $impExp->impExpStatus !== ImpExpStatus::Ready ||
            !Storage::fileExists($filePath)
        ) {
            exit();
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Content-Type: application/csv");
        header("Content-Disposition: attachment; filename=\"{$impExp->fileName}\"");
        header("Content-Length: " . Storage::getFileSize($filePath));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile($filePath);
        exit();
    }

    /**
     * Downloads the rows of a ready Import that could not be imported
     * @param ImpExpEntity $impExp
     * @param string       $title
     * @param string       $fileName
     * @return never
     */
    #[NotTested("It sends the file and exits")]
    public static function downloadWarnings(
        ImpExpEntity $impExp,
        string $title,
        string $fileName,
    ): never {
        $warnings = self::getWarnings($impExp);
        $importer = new Importer(self::getFilePath($impExp));
        $isImport = $impExp->impExpType === ImpExpType::Import;
        if (!$isImport || count($warnings) === 0 || !$importer->isValid()) {
            exit();
        }

        $indexes  = array_column($warnings, "index");
        $exporter = new Exporter(count($warnings), $title, $fileName, useCSV: true);
        foreach ($importer->getHeader() as $index => $cell) {
            $exporter->addHeader((string)$index, $cell->value);
        }
        $exporter->writeHeader();

        foreach ($importer as $index => $row) {
            if (in_array($index, $indexes, strict: true)) {
                $exporter->writeLine(Arrays::toStringsMap($row->toArray()));
            }
        }
        $exporter->download();
    }

    /**
     * Deletes the expired Imports and Exports and the ones that ended without a file
     * @return int
     */
    public static function deleteExpired(): int {
        $time  = Date::now()->subtract(days: self::$expireDays);
        $query = new ImpExpQuery();
        $query->startOr();
        $query->startAnd();
        $query->impExpStatus->equal(ImpExpStatus::Ready);
        $query->expirationTime->lessThan(Date::now());
        $query->endAnd();
        $query->startAnd();
        $query->impExpStatus->in([ ImpExpStatus::Canceled, ImpExpStatus::Failed ]);
        $query->modifiedTime->lessThan($time);
        $query->endAnd();
        $query->endOr();
        $query->inCurrentEnv();

        $list = self::getEntityList($query);
        foreach ($list as $impExp) {
            self::deleteFiles($impExp);
            self::removeEntity($impExp->id);
        }
        return count($list);
    }



    /**
     * Adds the done rows, and marks it as ready when it ended
     * @param ImpExpEntity $impExp
     * @param int          $amount
     * @param int          $imported
     * @param bool         $isDone
     * @return bool
     */
    private static function addDone(
        ImpExpEntity $impExp,
        int $amount,
        int $imported,
        bool $isDone,
    ): bool {
        // Only a running one is changed, as a block that ends after it was canceled must not
        // mark it as ready
        $query = new ImpExpQuery();
        $query->impExpID->equal($impExp->id);
        $query->impExpStatus->equal(ImpExpStatus::Running);

        $doneRows     = $impExp->doneRows + $amount;
        $importedRows = $impExp->importedRows + $imported;
        if (!$isDone) {
            self::editEntity($query, doneRows: $doneRows, importedRows: $importedRows);
            return false;
        }

        return self::editEntity(
            $query,
            doneRows:       $impExp->totalRows,
            importedRows:   $importedRows,
            expirationTime: Date::now()->add(days: self::$expireDays),
            impExpStatus:   ImpExpStatus::Ready,
        );
    }

    /**
     * Deletes the files of the Import or Export
     * @param ImpExpEntity $impExp
     * @return void
     */
    private static function deleteFiles(ImpExpEntity $impExp): void {
        Storage::deleteFile(self::getPath($impExp->credentialID, $impExp->fileCode, "json"));
        Storage::deleteFile(self::getFilePath($impExp));
    }

    /**
     * Returns the path of a file of an Import or Export
     * @param int    $credentialID
     * @param string $fileCode
     * @param string $extension
     * @return string
     */
    private static function getPath(
        int $credentialID,
        string $fileCode,
        string $extension,
    ): string {
        // The files are kept apart from the temp ones, which are emptied when the User logs
        // in, and the random code keeps them from being guessed, as the files have a url
        $path = FilePath::getPath(self::Folder, $credentialID);
        Storage::createDir($path);
        return Storage::parsePath($path, "$fileCode.$extension");
    }
}
