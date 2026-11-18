<?php
namespace Tests\ImpExp;

use Framework\Date\Date;
use Framework\File\FilePath;
use Framework\File\Storage;
use Framework\ImpExp\ImpExp;
use Framework\ImpExp\ImpExpType;
use Framework\ImpExp\ImpExpStatus;
use Framework\ImpExp\Schema\ImpExpEntity;
use Framework\ImpExp\Schema\ImpExpRequest;

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
        ImpExp::setRowLimit(30000);
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
     * Returns the path of the json of the Import or Export
     * @param ImpExpEntity $impExp
     * @return string
     */
    private function getJsonPath(ImpExpEntity $impExp): string {
        $path = ImpExp::getFilePath($impExp);
        return substr($path, 0, (int)strrpos($path, ".")) . ".json";
    }


    public function testIsLongUsesTheRowLimit(): void {
        ImpExp::setRowLimit(10);

        $this->assertFalse(ImpExp::isLong(10));
        $this->assertTrue(ImpExp::isLong(11));
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
