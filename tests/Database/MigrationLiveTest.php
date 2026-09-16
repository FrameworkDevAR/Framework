<?php
namespace Tests\Database;

use Framework\Database\SchemaFactory;
use Framework\Database\SchemaModel;
use Framework\Database\Migration\SchemaMigration;
use Framework\Core\SettingData;

use Tests\LiveTestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;

/**
 * The Schema Migration, against a database it is allowed to rewrite
 *
 * Every test damages the schema and migrates, and the one thing asked of the
 * result is that a second migration finds nothing to do. That is the whole
 * oracle: no expected SQL is written down, only that the database converges
 * on the Models. It also leaves the schema correct for whatever runs next,
 * which is what makes these safe against a database that is kept between runs.
 */
class MigrationLiveTest extends LiveTestCase {

    private const StrayTable  = "not_a_model";
    private const StrayColumn = "notAField";


    /**
     * Migrates the schema and returns what it printed
     * @param bool $canDelete Optional.
     * @return string
     */
    private function migrate(bool $canDelete = false): string {
        return $this->migrateWith([], [], $canDelete);
    }

    /**
     * Migrates the schema with the given renames, and returns what it printed
     * @param list<array{from:string,to:string}>              $tableRenames
     * @param list<array{table:string,from:string,to:string}> $columnRenames
     * @param bool                                            $canDelete     Optional.
     * @return string
     */
    private function migrateWith(
        array $tableRenames,
        array $columnRenames,
        bool $canDelete = false,
    ): string {
        ob_start();
        try {
            SchemaMigration::migrateData($tableRenames, $columnRenames, canDelete: $canDelete);
        } finally {
            $result = ob_get_clean();
        }
        return $result === false ? "" : $result;
    }

    /**
     * Migrates, then asserts a second migration has nothing left to do
     * @param bool $canDelete Optional.
     * @return string
     */
    private function migrateUntilSettled(bool $canDelete = true): string {
        $result = $this->migrate($canDelete);
        $second = $this->migrate($canDelete);

        foreach (SchemaFactory::getData() as $schemaModel) {
            $this->assertStringContainsString(
                "No changes for {$schemaModel->tableName}",
                $second,
                "{$schemaModel->tableName} was still being changed on the second run",
            );
        }
        return $result;
    }

    /**
     * Sets how many renames were already made, which is what a migration
     * starts counting from
     * @param int $movement
     * @param int $rename
     * @return void
     */
    private function setRenamedSoFar(int $movement, int $rename): void {
        SettingData::setCore("movement", $movement);
        SettingData::setCore("rename", $rename);
    }

    /**
     * Returns the Model of the given name
     * @param string $name
     * @return SchemaModel
     */
    private function model(string $name): SchemaModel {
        foreach (SchemaFactory::getData() as $schemaModel) {
            if ($schemaModel->name === $name) {
                return $schemaModel;
            }
        }
        $this->fail("There is no $name Model");
    }



    public function testTheTablesAreCreated(): void {
        $this->migrateUntilSettled();

        foreach (SchemaFactory::getData() as $schemaModel) {
            $this->assertTrue(
                $this->db()->tableExists($schemaModel->tableName),
                "{$schemaModel->tableName} was not created",
            );
        }
    }

    #[Depends("testTheTablesAreCreated")]
    public function testASecondRunDoesNothing(): void {
        // The one a Model of the App extending a Framework one used to break,
        // by dropping the columns it had added and adding them back after
        $result = $this->migrate();

        $this->assertStringNotContainsString("Updated table", $result);
        $this->assertStringNotContainsString("Created table", $result);
    }



    /**
     * A column taken out of the database, which the migration puts back
     * @param string $modelName
     * @param string $column
     * @return void
     */
    #[DataProvider("providerColumn")]
    #[Depends("testTheTablesAreCreated")]
    public function testAColumnIsAddedBack(string $modelName, string $column): void {
        $table = $this->model($modelName)->tableName;
        $this->query("ALTER TABLE `$table` DROP COLUMN `$column`");
        $this->assertFalse($this->db()->columnExists($table, $column));

        $result = $this->migrateUntilSettled();

        $this->assertTrue($this->db()->columnExists($table, $column));
        $this->assertStringContainsString("Updated table $table", $result);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerColumn(): array {
        return [
            "a string" => [ "Credential", "email"        ],
            "a number" => [ "Credential", "timezone"     ],
            "a date"   => [ "EmailQueue", "createdTime"  ],
            "an enum"  => [ "Settings",   "variableType" ],
        ];
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAColumnIsRetyped(): void {
        // A number held as text is the migration's hardest case, since the
        // values have to be rewritten before the column can be narrowed
        $table = $this->model("Credential")->tableName;
        $this->query("ALTER TABLE `$table` MODIFY `timezone` varchar(64) NOT NULL DEFAULT ''");
        $this->assertStringContainsString("varchar", $this->db()->getColumnType($table, "timezone"));

        $result = $this->migrateUntilSettled();

        $this->assertStringContainsString("int", $this->db()->getColumnType($table, "timezone"));
        $this->assertStringContainsString("Updated table $table", $result);
    }

    /**
     * A column the database holds as the wrong type, and the one it is put back to
     * @param string $modelName
     * @param string $column
     * @param string $wrongType
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerRetype")]
    #[Depends("testTheTablesAreCreated")]
    public function testAColumnIsGivenItsLength(
        string $modelName,
        string $column,
        string $wrongType,
        string $expected,
    ): void {
        // A text is reported without a length, so it is compared without one too,
        // and the ALTER that follows still has to carry the one it grows into
        $table = $this->model($modelName)->tableName;
        $this->query("ALTER TABLE `$table` MODIFY `$column` $wrongType");
        $this->assertStringNotContainsString(
            $expected,
            $this->db()->getColumnType($table, $column),
        );

        $this->migrateUntilSettled();

        $this->assertStringContainsString(
            $expected,
            $this->db()->getColumnType($table, $column),
        );
    }

    /**
     * @return array<string,array{string,string,string,string}>
     */
    public static function providerRetype(): array {
        return [
            "a text into a varchar" => [
                "Credential", "email", "text NULL", "varchar(255)",
            ],
            "a varchar into a text" => [
                "Credential", "observations", "varchar(255) NOT NULL DEFAULT ''", "text",
            ],
        ];
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAStrayColumnIsDropped(): void {
        $table  = $this->model("Credential")->tableName;
        $column = self::StrayColumn;
        $this->query("ALTER TABLE `$table` ADD COLUMN `$column` varchar(64) NOT NULL DEFAULT ''");

        // Without the flag it says what it would do and leaves the column
        $result = $this->migrate();
        $this->assertStringContainsString("$column", $result);
        $this->assertTrue($this->db()->columnExists($table, $column));

        $this->migrateUntilSettled();
        $this->assertFalse($this->db()->columnExists($table, $column));
    }

    #[Depends("testTheTablesAreCreated")]
    public function testATableIsCreated(): void {
        $table = $this->model("EmailWhiteList")->tableName;
        $this->query("DROP TABLE `$table`");
        $this->assertFalse($this->db()->tableExists($table));

        $result = $this->migrateUntilSettled();

        $this->assertTrue($this->db()->tableExists($table));
        $this->assertStringContainsString("Created table $table", $result);
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAStrayTableIsDropped(): void {
        $table = self::StrayTable;
        $this->query("CREATE TABLE `$table` (`id` int(10) unsigned NOT NULL)");
        $this->assertTrue($this->db()->tableExists($table));

        // The destructive one: without the flag the table has to survive
        $result = $this->migrate();
        $this->assertStringContainsString("Delete table $table (manually)", $result);
        $this->assertTrue($this->db()->tableExists($table));

        $result = $this->migrate(canDelete: true);
        $this->assertStringContainsString("Deleted table $table", $result);
        $this->assertFalse($this->db()->tableExists($table));
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAnIndexIsCreated(): void {
        $table = $this->model("Credential")->tableName;
        $keys  = [];
        foreach ($this->model("Credential")->fields as $field) {
            if ($field->isKey) {
                $keys[] = $field->dbName;
            }
        }
        if (count($keys) === 0) {
            $this->fail("The Credential Model declares no index");
        }

        $this->query("ALTER TABLE `$table` DROP INDEX `{$keys[0]}`");
        $this->migrateUntilSettled();

        $found = false;
        foreach ($this->db()->getTableKeys($table) as $tableKey) {
            if (isset($tableKey["Key_name"]) && $tableKey["Key_name"] === $keys[0]) {
                $found = true;
            }
        }
        $this->assertTrue($found, "The index {$keys[0]} was not created again");
    }

    #[Depends("testTheTablesAreCreated")]
    public function testThePrimaryKeyIsCreated(): void {
        $table = $this->model("Settings")->tableName;
        $this->query("ALTER TABLE `$table` DROP PRIMARY KEY");
        $this->assertSame([], $this->db()->getPrimaryKeys($table));

        $this->migrateUntilSettled();

        $this->assertNotEmpty($this->db()->getPrimaryKeys($table));
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAColumnIsRenamedByItsCase(): void {
        // One written another way is the same column, so it is moved rather
        // than added beside the one that is already there
        $table = $this->model("Credential")->tableName;
        $this->query(
            "ALTER TABLE `$table` CHANGE `email` `EMAIL` varchar(255) NOT NULL DEFAULT ''",
        );

        $result = $this->migrateUntilSettled();

        // The type goes with it, so it is a change rather than a plain rename
        $this->assertStringContainsString("CHANGE `EMAIL` `email`", $result);
        $this->assertTrue($this->db()->columnExists($table, "email"));
    }

    #[Depends("testTheTablesAreCreated")]
    public function testATableIsRenamedOnRequest(): void {
        $this->setRenamedSoFar(0, 0);
        $from = self::StrayTable;
        $to   = self::StrayTable . "_two";
        $this->query("DROP TABLE IF EXISTS `$from`");
        $this->query("DROP TABLE IF EXISTS `$to`");
        $this->query("CREATE TABLE `$from` (`id` int(10) unsigned NOT NULL)");

        $result = $this->migrateWith([ [ "from" => $from, "to" => $to ] ], []);

        $this->assertStringContainsString("Renamed table $from -> $to", $result);
        $this->assertTrue($this->db()->tableExists($to));
        $this->query("DROP TABLE IF EXISTS `$to`");
    }

    #[Depends("testTheTablesAreCreated")]
    public function testATableThatIsNotThereIsNotRenamed(): void {
        $this->setRenamedSoFar(0, 0);

        $result = $this->migrateWith([
            [ "from" => "not_a_table_at_all", "to" => "another_one" ],
        ], []);

        $this->assertStringContainsString("No table renames required", $result);
        $this->assertFalse($this->db()->tableExists("another_one"));
    }

    #[Depends("testTheTablesAreCreated")]
    public function testARenameIsOnlyAskedForOnce(): void {
        // The one it got to is written down, so the next migration starts
        // after it rather than looking at the ones already done
        $this->setRenamedSoFar(0, 0);
        $from = self::StrayTable;
        $to   = self::StrayTable . "_two";
        $this->query("DROP TABLE IF EXISTS `$from`");
        $this->query("DROP TABLE IF EXISTS `$to`");
        $this->query("CREATE TABLE `$from` (`id` int(10) unsigned NOT NULL)");
        $renames = [ [ "from" => $from, "to" => $to ] ];

        $this->migrateWith($renames, []);
        $this->assertSame(1, SettingData::getCore("movement"));

        // The table is back under the old name, and is left alone this time
        $this->query("CREATE TABLE `$from` (`id` int(10) unsigned NOT NULL)");
        $result = $this->migrateWith($renames, []);

        $this->assertStringContainsString("No table renames required", $result);
        $this->assertTrue($this->db()->tableExists($from));

        $this->query("DROP TABLE IF EXISTS `$from`");
        $this->query("DROP TABLE IF EXISTS `$to`");
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAColumnIsRenamedOnRequest(): void {
        $this->setRenamedSoFar(0, 0);
        $table = self::StrayTable;
        $this->query("DROP TABLE IF EXISTS `$table`");
        $this->query("CREATE TABLE `$table` (`oldName` varchar(50) NOT NULL DEFAULT '')");

        $result = $this->migrateWith([], [
            [ "table" => $table, "from" => "oldName", "to" => "newName" ],
        ]);

        $this->assertStringContainsString("Renamed column oldName -> newName", $result);
        $this->assertTrue($this->db()->columnExists($table, "newName"));
        $this->query("DROP TABLE IF EXISTS `$table`");
    }

    /**
     * A column rename that has nothing to move, which is left alone
     * @param string $table
     * @param string $from
     * @return void
     */
    #[DataProvider("providerNoColumnRename")]
    #[Depends("testTheTablesAreCreated")]
    public function testAColumnThatIsNotThereIsNotRenamed(string $table, string $from): void {
        $this->setRenamedSoFar(0, 0);
        $this->query("DROP TABLE IF EXISTS `" . self::StrayTable . "`");
        $this->query("CREATE TABLE `" . self::StrayTable . "` (`oldName` varchar(50) NOT NULL)");

        $result = $this->migrateWith([], [
            [ "table" => $table, "from" => $from, "to" => "newName" ],
        ]);

        $this->assertStringContainsString("No column renames required", $result);
        $this->query("DROP TABLE IF EXISTS `" . self::StrayTable . "`");
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerNoColumnRename(): array {
        return [
            "a table that is not there"  => [ "not_a_table_at_all", "oldName" ],
            "a column that is not there" => [ self::StrayTable, "notAColumn" ],
        ];
    }

    #[Depends("testTheTablesAreCreated")]
    public function testAColumnAlreadyRenamedIsLeftAlone(): void {
        $this->setRenamedSoFar(0, 0);
        $table = self::StrayTable;
        $this->query("DROP TABLE IF EXISTS `$table`");
        $this->query("CREATE TABLE `$table` (`newName` varchar(50) NOT NULL DEFAULT '')");

        $result = $this->migrateWith([], [
            [ "table" => $table, "from" => "oldName", "to" => "newName" ],
        ]);

        $this->assertStringNotContainsString("No column renames required", $result);
        $this->assertSame(1, SettingData::getCore("rename"));
        $this->query("DROP TABLE IF EXISTS `$table`");
    }
}
