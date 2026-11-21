<?php
namespace Tests\ImpExp;

use Framework\Date\Date;
use Framework\File\FilePath;
use Framework\File\Storage;
use Framework\ImpExp\ImpExp;
use Framework\ImpExp\ImpExpStorage;
use Framework\ImpExp\ImpExpType;
use Framework\ImpExp\ImpExpStatus;
use Framework\ImpExp\Schema\ImpExpEntity;
use Framework\ImpExp\Schema\ImpExpRequest;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

use Tests\LiveTestCase;
use Tests\TestHelpers;

/**
 * The Import and Export, which run the big ones in blocks
 */
class ImpExpLiveTest extends LiveTestCase {
    use TestHelpers;

    private const CredentialID = 990731;
    private const Module       = "Contact";
    private const DataID       = 7;

    private string $csvPath = "";


    protected function setUp(): void {
        parent::setUp();
        $this->migrateOnce();
        $this->query("DELETE FROM `imp_exp`");
    }

    protected function tearDown(): void {
        ImpExp::setExportLimit(10000);
        ImpExp::setImportLimit(500);
        ImpExp::setExcelLimit(2000);
        ImpExp::setStorage(null);
        Storage::deleteDir(FilePath::getPath("impexp", self::CredentialID));
        if ($this->csvPath !== "" && is_file($this->csvPath)) {
            @unlink($this->csvPath);
        }
    }

    /**
     * Creates an Export of the given IDs and returns it
     * @param list<int> $ids
     * @return ImpExpEntity
     */
    private function createExport(array $ids): ImpExpEntity {
        $impExpID = ImpExp::createExport(
            credentialID: self::CredentialID,
            module:       self::Module,
            dataID:       self::DataID,
            filters:      [ "search" => "ana" ],
            fileName:     "contacts",
            ids:          $ids,
        );
        $this->assertGreaterThan(0, $impExpID);
        return ImpExp::getByID($impExpID);
    }

    /**
     * Creates an Import of a CSV with the given names and returns it
     * @param list<string> $names
     * @return ImpExpEntity
     */
    private function createImport(array $names): ImpExpEntity {
        $this->csvPath = sys_get_temp_dir() . "/impexp_test_" . uniqid() . ".csv";
        Storage::writeFile($this->csvPath, "Name\n" . implode("\n", $names) . "\n");

        $impExpID = ImpExp::createImport(
            credentialID: self::CredentialID,
            module:       self::Module,
            dataID:       self::DataID,
            columns:      [ "name" => 1 ],
            filePath:     $this->csvPath,
            totalRows:    count($names),
        );
        $this->assertGreaterThan(0, $impExpID);
        return ImpExp::getByID($impExpID);
    }

    /**
     * Sets a Storage that keeps the Exports in memory and returns it
     * @return object{ids:array<string,list<int>>,files:array<string,string>,reads:int,fails:bool}
     */
    private function useStorage(): object {
        $storage = new class implements ImpExpStorage {
            /** @var array<string,list<int>> */
            public array $ids   = [];

            /** @var array<string,string> */
            public array $files = [];

            public int  $reads = 0;
            public bool $fails = false;

            public function createExport(ImpExpEntity $impExp, array $ids): bool {
                if ($this->fails) {
                    return false;
                }
                $this->ids[$impExp->fileCode]   = $ids;
                $this->files[$impExp->fileCode] = "";
                return true;
            }

            public function getExportIDs(ImpExpEntity $impExp): array {
                $this->reads += 1;
                return $this->ids[$impExp->fileCode] ?? [];
            }

            public function appendExport(ImpExpEntity $impExp, string $filePath, int $offset): bool {
                $file = $this->files[$impExp->fileCode] ?? null;
                if ($this->fails || $file === null || strlen($file) < $offset) {
                    return false;
                }
                $block = (string)file_get_contents($filePath);
                $this->files[$impExp->fileCode] = substr($file, 0, $offset) . $block;
                return true;
            }

            public function createImport(ImpExpEntity $impExp, string $filePath): bool {
                if ($this->fails) {
                    return false;
                }
                $this->files[$impExp->fileCode] = (string)file_get_contents($filePath);
                return true;
            }

            public function readFile(ImpExpEntity $impExp, string $filePath): bool {
                $this->reads += 1;
                $file = $this->files[$impExp->fileCode] ?? null;
                if ($this->fails || $file === null) {
                    return false;
                }
                return file_put_contents($filePath, $file) !== false;
            }

            public function delete(ImpExpEntity $impExp): bool {
                unset($this->ids[$impExp->fileCode], $this->files[$impExp->fileCode]);
                return true;
            }
        };
        ImpExp::setStorage($storage);
        return $storage;
    }

    /**
     * Returns the amount of Imports and Exports
     * @return int
     */
    private function countRows(): int {
        return count(ImpExp::getAllRunning());
    }

    /**
     * Returns the path of the json of the Import or Export
     * @param ImpExpEntity $impExp
     * @return string
     */
    private function getJsonPath(ImpExpEntity $impExp): string {
        $path = ImpExp::getFilePath($impExp);
        return substr($path, 0, (int)strrpos($path, ".")) . ".json";
    }


    public function testIsLongExportUsesTheExportLimit(): void {
        ImpExp::setExportLimit(10);
        ImpExp::setImportLimit(100);

        $this->assertFalse(ImpExp::isLongExport(10));
        $this->assertTrue(ImpExp::isLongExport(11));
    }

    public function testIsLongImportUsesTheImportLimit(): void {
        ImpExp::setExportLimit(100);
        ImpExp::setImportLimit(10);

        $this->assertFalse(ImpExp::isLongImport(10));
        $this->assertTrue(ImpExp::isLongImport(11));
    }

    public function testIsLongExcelCountsTheRowsOfAnExcel(): void {
        ImpExp::setExcelLimit(2);
        $this->csvPath = sys_get_temp_dir() . "/impexp_test_" . uniqid() . ".xlsx";
        $writer = new Writer();
        $writer->openToFile($this->csvPath);
        foreach ([ "Name", "Ana", "Bob", "Cara" ] as $name) {
            $writer->addRow(Row::fromValues([ $name ]));
        }
        $writer->close();
        $this->assertTrue(ImpExp::isLongExcel($this->csvPath));

        ImpExp::setExcelLimit(3);
        $this->assertFalse(ImpExp::isLongExcel($this->csvPath));
    }

    public function testIsLongExcelSkipsACSV(): void {
        ImpExp::setExcelLimit(0);
        $import = $this->createImport([ "Ana", "Bob" ]);
        $this->assertFalse(ImpExp::isLongExcel($this->csvPath));
        $this->assertSame(ImpExpType::Import, $import->impExpType);
    }

    public function testCreateExportStoresTheIDsAndStartsRunning(): void {
        $export = $this->createExport([ 5, 3, 9 ]);

        $this->assertSame(ImpExpType::Export, $export->impExpType);
        $this->assertSame(ImpExpStatus::Running, $export->impExpStatus);
        $this->assertSame("contacts.csv", $export->fileName);
        $this->assertSame(3, $export->totalRows);
        $this->assertSame(0, $export->doneRows);
        $this->assertSame(32, strlen($export->fileCode));
        $this->assertFileExists($this->getJsonPath($export));
    }

    public function testGetNextIDsKeepsTheOrderFromTheDoneRows(): void {
        $export = $this->createExport([ 5, 3, 9, 1, 7 ]);

        $this->assertSame([ 5, 3 ], ImpExp::getNextIDs($export, 2));

        ImpExp::addRows($export, 2);
        $export = ImpExp::getByID($export->id);
        $this->assertSame([ 9, 1 ], ImpExp::getNextIDs($export, 2));
    }

    public function testGetFilePathIsInTheFolderOfTheCredential(): void {
        $export = $this->createExport([ 1 ]);

        $path = ImpExp::getFilePath($export);
        $this->assertStringEndsWith("{$export->fileCode}.csv", $path);
        $this->assertStringStartsWith(FilePath::getPath("impexp", self::CredentialID), $path);
    }

    public function testAddRowsMarksAsReadyWhenDone(): void {
        $export = $this->createExport([ 1, 2, 3 ]);

        $this->assertFalse(ImpExp::addRows($export, 2));
        $export = ImpExp::getByID($export->id);
        $this->assertSame(2, $export->doneRows);
        $this->assertSame(ImpExpStatus::Running, $export->impExpStatus);

        $this->assertTrue(ImpExp::addRows($export, 1));
        $export = ImpExp::getByID($export->id);
        $this->assertSame(3, $export->doneRows);
        $this->assertSame(ImpExpStatus::Ready, $export->impExpStatus);
        $this->assertTrue($export->expirationTime->isAfter(Date::now()));
        $this->assertFileDoesNotExist($this->getJsonPath($export));
    }

    public function testAddRowsDoesNotChangeACanceledExport(): void {
        $export = $this->createExport([ 1, 2 ]);
        ImpExp::cancel($export);

        $this->assertFalse(ImpExp::addRows($export, 2));
        $this->assertSame(ImpExpStatus::Canceled, ImpExp::getByID($export->id)->impExpStatus);
    }

    public function testCreateExportWithAStorageKeepsNothingHere(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 5, 3, 9 ]);

        $this->assertTrue($export->isStored);
        $this->assertSame([ 5, 3, 9 ], $storage->ids[$export->fileCode]);
        $this->assertSame("", $storage->files[$export->fileCode]);
        $this->assertFileDoesNotExist($this->getJsonPath($export));
    }

    public function testCreateExportFailsWhenTheStorageFails(): void {
        $storage = $this->useStorage();
        $storage->fails = true;

        $impExpID = ImpExp::createExport(
            credentialID: self::CredentialID,
            module:       self::Module,
            dataID:       self::DataID,
            filters:      [],
            fileName:     "contacts",
            ids:          [ 1 ],
        );
        $this->assertSame(0, $impExpID);
        $this->assertSame(0, $this->countRows());
    }

    public function testGetNextIDsReadsTheStorageOnce(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 5, 3, 9 ]);

        $this->assertSame([ 5, 3 ], ImpExp::getNextIDs($export, 2));
        ImpExp::addRows($export, 2);
        $export = ImpExp::getByID($export->id);
        $this->assertSame([ 9 ], ImpExp::getNextIDs($export, 2));
        $this->assertSame(1, $storage->reads);
    }

    public function testAddRowsSendsEachBlockAtTheStoredSize(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 1, 2 ]);

        Storage::writeFile(ImpExp::getFilePath($export), "id\n1\n");
        $this->assertFalse(ImpExp::addRows($export, 1));
        $this->assertFileDoesNotExist(ImpExp::getFilePath($export));
        $export = ImpExp::getByID($export->id);
        $this->assertSame(5, $export->fileSize);

        Storage::writeFile(ImpExp::getFilePath($export), "2\n");
        $this->assertTrue(ImpExp::addRows($export, 1));
        $export = ImpExp::getByID($export->id);
        $this->assertSame(ImpExpStatus::Ready, $export->impExpStatus);
        $this->assertSame(7, $export->fileSize);
        $this->assertSame("id\n1\n2\n", $storage->files[$export->fileCode]);
    }

    public function testAddRowsReplacesABlockSentAgain(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 1, 2 ]);
        $storage->files[$export->fileCode] = "id\nold\n";

        Storage::writeFile(ImpExp::getFilePath($export), "id\n1\n");
        ImpExp::addRows($export, 1);
        $this->assertSame("id\n1\n", $storage->files[$export->fileCode]);
    }

    public function testAddRowsFailsTheExportWhenTheBlockIsNotSent(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 1, 2 ]);
        $storage->fails = true;

        Storage::writeFile(ImpExp::getFilePath($export), "id\n1\n");
        $this->assertFalse(ImpExp::addRows($export, 1));
        $this->assertSame(ImpExpStatus::Failed, ImpExp::getByID($export->id)->impExpStatus);
        $this->assertArrayNotHasKey($export->fileCode, $storage->files);
    }

    public function testAddRowsKeepsAnExportCanceledMeanwhileCanceled(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 1 ]);
        ImpExp::cancel(ImpExp::getByID($export->id));

        Storage::writeFile(ImpExp::getFilePath($export), "id\n1\n");
        $this->assertFalse(ImpExp::addRows($export, 1));
        $this->assertSame(ImpExpStatus::Canceled, ImpExp::getByID($export->id)->impExpStatus);
        $this->assertArrayNotHasKey($export->fileCode, $storage->files);
    }

    public function testRemoveDeletesTheFileOfTheStorage(): void {
        $storage = $this->useStorage();
        $export  = $this->createExport([ 1 ]);

        $this->assertTrue(ImpExp::remove(ImpExp::getByID($export->id)));
        $this->assertArrayNotHasKey($export->fileCode, $storage->files);
        $this->assertArrayNotHasKey($export->fileCode, $storage->ids);
    }

    public function testCreateImportWithAStorageKeepsNothingHere(): void {
        $storage = $this->useStorage();
        $import  = $this->createImport([ "Ana", "Bob" ]);

        $this->assertTrue($import->isStored);
        $this->assertSame("Name\nAna\nBob\n", $storage->files[$import->fileCode]);
        $this->assertFileDoesNotExist(ImpExp::getFilePath($import));
    }

    public function testCreateImportFailsWhenTheStorageFails(): void {
        $storage = $this->useStorage();
        $storage->fails = true;

        $this->csvPath = sys_get_temp_dir() . "/impexp_test_" . uniqid() . ".csv";
        Storage::writeFile($this->csvPath, "Name\nAna\n");
        $impExpID = ImpExp::createImport(
            credentialID: self::CredentialID,
            module:       self::Module,
            dataID:       self::DataID,
            columns:      [ "name" => 1 ],
            filePath:     $this->csvPath,
            totalRows:    1,
        );
        $this->assertSame(0, $impExpID);
        $this->assertSame(0, $this->countRows());
    }

    public function testGetImporterReadsTheStoredFileOnce(): void {
        $storage = $this->useStorage();
        $import  = $this->createImport([ "Ana", "Bob", "Cara" ]);

        $names = [];
        foreach ([ 0, 1 ] as $done) {
            $import->doneRows = $done;
            foreach (ImpExp::getImporter($import, 1) as $row) {
                $names[] = $row->getString("name");
            }
        }
        $this->assertSame([ "Ana", "Bob" ], $names);
        $this->assertSame(1, $storage->reads);
    }

    public function testGetImporterFailsWhenTheStoredFileCanNotBeRead(): void {
        $storage = $this->useStorage();
        $import  = $this->createImport([ "Ana" ]);
        $storage->fails = true;

        $this->expectException(\Exception::class);
        ImpExp::getImporter($import, 1);
    }

    public function testRemoveDeletesTheStoredImport(): void {
        $storage = $this->useStorage();
        $import  = $this->createImport([ "Ana" ]);

        $this->assertTrue(ImpExp::remove(ImpExp::getByID($import->id)));
        $this->assertArrayNotHasKey($import->fileCode, $storage->files);
    }

    public function testCreateImportCopiesTheFile(): void {
        $import = $this->createImport([ "Ana", "Bob" ]);

        $this->assertSame(ImpExpType::Import, $import->impExpType);
        $this->assertSame(ImpExpStatus::Running, $import->impExpStatus);
        $this->assertSame(2, $import->totalRows);
        $this->assertFileExists(ImpExp::getFilePath($import));
        $this->assertSame([], ImpExp::getWarnings($import));
    }

    public function testGetImporterReadsTheNextBlock(): void {
        $import = $this->createImport([ "Ana", "Bob", "Cara" ]);
        ImpExp::addImported($import, 1, 1, []);
        $import = ImpExp::getByID($import->id);

        $names = [];
        foreach (ImpExp::getImporter($import, 1) as $row) {
            $names[] = $row->getString("name");
        }
        $this->assertSame([ "Bob" ], $names);
    }

    public function testAddImportedKeepsTheWarningsUntilTheEnd(): void {
        $import = $this->createImport([ "Ana", "Bob", "Cara" ]);

        $this->assertFalse(ImpExp::addImported($import, 2, 1, [[ "index" => 3, "name" => "Bob" ]]));
        $import = ImpExp::getByID($import->id);
        $this->assertSame(2, $import->doneRows);
        $this->assertSame(1, $import->importedRows);

        $this->assertTrue(ImpExp::addImported($import, 1, 1, []));
        $import = ImpExp::getByID($import->id);
        $this->assertSame(ImpExpStatus::Ready, $import->impExpStatus);
        $this->assertSame(2, $import->importedRows);
        $this->assertSame([[ "index" => 3, "name" => "Bob" ]], ImpExp::getWarnings($import));
        $this->assertFileExists(ImpExp::getFilePath($import));
    }

    public function testCancelAndFailDeleteTheFiles(): void {
        $canceled = $this->createExport([ 1 ]);
        $failed   = $this->createImport([ "Ana" ]);
        Storage::writeFile(ImpExp::getFilePath($canceled), "id\n1\n");

        $this->assertTrue(ImpExp::cancel($canceled));
        $this->assertTrue(ImpExp::fail($failed));

        $this->assertSame(ImpExpStatus::Canceled, ImpExp::getByID($canceled->id)->impExpStatus);
        $this->assertSame(ImpExpStatus::Failed, ImpExp::getByID($failed->id)->impExpStatus);
        $this->assertFileDoesNotExist(ImpExp::getFilePath($canceled));
        $this->assertFileDoesNotExist(ImpExp::getFilePath($failed));
        $this->assertFileDoesNotExist($this->getJsonPath($failed));
    }

    public function testRemoveDeletesItAndItsFile(): void {
        $export = $this->createExport([ 1 ]);
        ImpExp::addRows($export, 1);
        Storage::writeFile(ImpExp::getFilePath($export), "id\n1\n");

        $this->assertTrue(ImpExp::remove($export));
        $this->assertTrue(ImpExp::getByID($export->id)->isEmpty());
        $this->assertFileDoesNotExist(ImpExp::getFilePath($export));
    }

    public function testGetRunningAndGetAllRunning(): void {
        $running = $this->createExport([ 1 ]);
        $ready   = $this->createExport([ 2 ]);
        $import  = $this->createImport([ "Ana" ]);
        ImpExp::addRows($ready, 1);

        $found = ImpExp::getRunning(self::CredentialID, ImpExpType::Export, self::Module, self::DataID);
        $this->assertSame($running->id, $found->id);
        $found = ImpExp::getRunning(self::CredentialID, ImpExpType::Import, self::Module, self::DataID);
        $this->assertSame($import->id, $found->id);
        $found = ImpExp::getRunning(self::CredentialID, ImpExpType::Export, "Account", self::DataID);
        $this->assertTrue($found->isEmpty());

        $ids = array_map(fn($impExp) => $impExp->id, ImpExp::getAllRunning());
        sort($ids);
        $this->assertSame([ $running->id, $import->id ], $ids);
    }

    public function testGetAllForCredentialSkipsTheEnded(): void {
        $running  = $this->createExport([ 1 ]);
        $ready    = $this->createExport([ 2 ]);
        $canceled = $this->createExport([ 3 ]);
        ImpExp::addRows($ready, 1);
        ImpExp::cancel($canceled);

        $ids = array_map(fn($impExp) => $impExp->id, ImpExp::getAllForCredential(self::CredentialID));
        sort($ids);
        $this->assertSame([ $running->id, $ready->id ], $ids);
    }

    public function testTheListIsSearchedAndFiltered(): void {
        $ready = $this->createExport([ 1 ]);
        $this->createExport([ 2 ]);
        $this->createImport([ "Ana" ]);
        ImpExp::addRows($ready, 1);

        $this->assertSame(3, ImpExp::getTotal(new ImpExpRequest()));
        $this->assertSame(1, ImpExp::getTotal(new ImpExpRequest(statuses: [ ImpExpStatus::Ready->name ])));
        $this->assertSame(1, ImpExp::getTotal(new ImpExpRequest(types: [ ImpExpType::Import->name ])));
        $this->assertSame(2, ImpExp::getTotal(new ImpExpRequest(search: "contacts")));
        $this->assertSame(0, ImpExp::getTotal(new ImpExpRequest(search: "accounts")));
        $this->assertSame($ready->id, ImpExp::getList(new ImpExpRequest(statuses: [ "Ready" ]))[0]->id);
    }

    public function testDeleteExpiredRemovesTheExpiredAndTheOldEnded(): void {
        $expired = $this->createExport([ 1 ]);
        $ready   = $this->createExport([ 2 ]);
        $failed  = $this->createExport([ 3 ]);
        $running = $this->createExport([ 4 ]);
        ImpExp::addRows($expired, 1);
        ImpExp::addRows($ready, 1);
        ImpExp::fail($failed);

        $past = Date::now()->subtract(days: 4)->toTime();
        $this->query("UPDATE `imp_exp` SET `expirationTime` = $past WHERE `IMP_EXP_ID` = {$expired->id}");
        $this->query("UPDATE `imp_exp` SET `modifiedTime` = $past WHERE `IMP_EXP_ID` = {$failed->id}");

        $this->assertSame(2, ImpExp::deleteExpired());
        $this->assertTrue(ImpExp::getByID($expired->id)->isEmpty());
        $this->assertTrue(ImpExp::getByID($failed->id)->isEmpty());
        $this->assertFalse(ImpExp::getByID($ready->id)->isEmpty());
        $this->assertFalse(ImpExp::getByID($running->id)->isEmpty());
    }
}
